<?php
/**
 * Shared helpers for the Telegram DDH/Auditoria approval integration.
 * Used by cron/notify_pending_approvals.php and telegram_webhook.php.
 */

if (!defined('IN_SCRIPT')) {die('Invalid attempt!');}

function hesk_tg_log($message, $do_die = false) {
    global $hesk_settings;

    if ($hesk_settings['debug_mode'] || php_sapi_name() === 'cli') {
        echo $message . "\n";
    }

    if ($do_die) {
        exit();
    }
} // END hesk_tg_log()


function hesk_tg_role_config() {
    static $roles = null;

    if ($roles !== null) {
        return $roles;
    }

    // DDH and Auditoria are independent/parallel gates - each category can require either, both, or neither.
    $roles = array(
        'ddh' => array(
            'category_field'  => 'require_ddh_approval',
            'label'           => 'DDH',
            'approved_status' => 'Aprovado DDH',
            'rejected_status' => 'Reprovado DDH',
        ),
        'auditoria' => array(
            'category_field'  => 'require_auditoria_approval',
            'label'           => 'Auditoria',
            'approved_status' => 'Aprovado Auditoria',
            'rejected_status' => 'Reprovado Auditoria',
        ),
    );

    foreach ($roles as $role => &$cfg) {
        $cfg['approved_id'] = hesk_tg_status_id_by_name($cfg['approved_status']);
        $cfg['rejected_id'] = hesk_tg_status_id_by_name($cfg['rejected_status']);
    }
    unset($cfg);

    return $roles;
} // END hesk_tg_role_config()


function hesk_tg_status_id_by_name($name) {
    global $hesk_settings;

    foreach ($hesk_settings['statuses'] as $id => $data) {
        if (isset($data['name']) && $data['name'] === $name) {
            return (int) $id;
        }
    }

    return null;
} // END hesk_tg_status_id_by_name()


function hesk_tg_get_approver_chat_id($role) {
    global $hesk_settings;

    $res = hesk_dbQuery("SELECT `telegram_chat_id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approvers` WHERE `role`='".hesk_dbEscape($role)."' AND `active`='1' LIMIT 1");

    if (hesk_dbNumRows($res) != 1) {
        return null;
    }

    return hesk_dbResult($res);
} // END hesk_tg_get_approver_chat_id()


function hesk_tg_get_approver_name($role, $chat_id) {
    global $hesk_settings;

    $res = hesk_dbQuery("SELECT `u`.`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approvers` AS `a`
        INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `u` ON `u`.`id` = `a`.`hesk_user_id`
        WHERE `a`.`role`='".hesk_dbEscape($role)."' AND `a`.`telegram_chat_id`=".intval($chat_id)." AND `a`.`active`='1' LIMIT 1");

    if (hesk_dbNumRows($res) != 1) {
        return 'Aprovador Telegram';
    }

    return hesk_dbResult($res);
} // END hesk_tg_get_approver_name()


function hesk_tg_get_approver_user_id($role, $chat_id) {
    global $hesk_settings;

    $res = hesk_dbQuery("SELECT `hesk_user_id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approvers`
        WHERE `role`='".hesk_dbEscape($role)."' AND `telegram_chat_id`=".intval($chat_id)." AND `active`='1' LIMIT 1");

    if (hesk_dbNumRows($res) != 1) {
        return null;
    }

    return (int) hesk_dbResult($res);
} // END hesk_tg_get_approver_user_id()


function hesk_tg_ticket_body_preview($message, $max_length = 1200) {
    $plain = strip_tags(hesk_msgToPlain($message, 1, 0));
    $plain = trim(preg_replace("/\n{3,}/", "\n\n", $plain));

    if ($plain === '') {
        return '(sem conteúdo)';
    }

    if (mb_strlen($plain) > $max_length) {
        $plain = mb_substr($plain, 0, $max_length) . '…';
    }

    return $plain;
} // END hesk_tg_ticket_body_preview()


function hesk_tg_escape_markdown($text) {
    return str_replace(array('_', '*', '`', '['), array('\\_', '\\*', '\\`', '\\['), $text);
} // END hesk_tg_escape_markdown()


function hesk_tg_api($method, $params) {
    global $hesk_settings;

    $url = 'https://api.telegram.org/bot' . $hesk_settings['telegram_bot_token'] . '/' . $method;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        hesk_tg_log("Telegram API transport error ({$method}): {$curl_error}");
        return null;
    }

    $decoded = json_decode($response, true);
    if (!isset($decoded['ok']) || !$decoded['ok']) {
        hesk_tg_log("Telegram API rejected request ({$method}): {$response}");
        return null;
    }

    return $decoded['result'];
} // END hesk_tg_api()


function hesk_tg_send_document($chat_id, $file_path, $filename, $caption = null) {
    global $hesk_settings;

    if (!is_readable($file_path)) {
        hesk_tg_log("Attachment not readable, skipping: {$file_path}");
        return null;
    }

    $url = 'https://api.telegram.org/bot' . $hesk_settings['telegram_bot_token'] . '/sendDocument';

    $mime = function_exists('mime_content_type') ? mime_content_type($file_path) : false;
    $post_fields = array(
        'chat_id'  => $chat_id,
        'document' => new CURLFile($file_path, $mime ?: 'application/octet-stream', $filename),
    );

    if ($caption !== null) {
        $post_fields['caption'] = $caption;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        hesk_tg_log("Telegram API transport error (sendDocument): {$curl_error}");
        return null;
    }

    $decoded = json_decode($response, true);
    if (!isset($decoded['ok']) || !$decoded['ok']) {
        hesk_tg_log("Telegram API rejected request (sendDocument): {$response}");
        return null;
    }

    return $decoded['result'];
} // END hesk_tg_send_document()


/**
 * Sends the "aprovação necessária" message (with Aprovar/Reprovar buttons) for one
 * ticket+role, creates the tracking row in hesk_telegram_approval_requests, and follows
 * up with the ticket's attachments. Used both by the instant notify-on-creation hook
 * (inc/posting_functions.inc.php) and by the periodic cron sweep/safety-net
 * (cron/notify_pending_approvals.php) - they must never drift apart on what gets sent.
 *
 * $ticket needs: id, trackid, subject, name, message, category_name.
 * Returns true if the notification was sent, false otherwise (already pending, no
 * approver registered, or the Telegram API call failed).
 */
function hesk_tg_notify_approval_request($ticket, $role, $roles) {
    global $hesk_settings;

    if (!isset($roles[$role])) {
        return false;
    }
    $cfg = $roles[$role];

    $chat_id = hesk_tg_get_approver_chat_id($role);
    if ($chat_id === null) {
        hesk_tg_log("No active Telegram approver registered for role '{$role}', skipping ticket {$ticket['trackid']}.");
        return false;
    }

    $existing = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests`
        WHERE `ticket_id`=".intval($ticket['id'])." AND `role`='".hesk_dbEscape($role)."' LIMIT 1");
    if (hesk_dbNumRows($existing) > 0) {
        return false;
    }

    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests`
        (`ticket_id`, `trackid`, `role`, `telegram_chat_id`, `status`, `created_at`)
        VALUES (".intval($ticket['id']).", '".hesk_dbEscape($ticket['trackid'])."', '".hesk_dbEscape($role)."', ".intval($chat_id).", 'pending', NOW())");
    $request_id = hesk_dbInsertID();

    $text = sprintf(
        "🔔 *Aprovação necessária (%s)*\n\n*Ticket:* #%s\n*Categoria:* %s\n*Assunto:* %s\n*Solicitante:* %s\n\n*Conteúdo:*\n%s\n\n[Abrir no sistema](%s)",
        hesk_tg_escape_markdown($cfg['label']),
        hesk_tg_escape_markdown($ticket['trackid']),
        hesk_tg_escape_markdown($ticket['category_name']),
        hesk_tg_escape_markdown($ticket['subject']),
        hesk_tg_escape_markdown($ticket['name']),
        hesk_tg_escape_markdown(hesk_tg_ticket_body_preview($ticket['message'])),
        rtrim($hesk_settings['hesk_url'], '/') . '/admin/admin_ticket.php?track=' . urlencode($ticket['trackid'])
    );

    $result = hesk_tg_api('sendMessage', array(
        'chat_id'                  => $chat_id,
        'text'                     => $text,
        'parse_mode'               => 'Markdown',
        'disable_web_page_preview' => true,
        'reply_markup'             => array(
            'inline_keyboard' => array(
                array(
                    array('text' => '✅ Aprovar', 'callback_data' => "appr:{$request_id}"),
                    array('text' => '❌ Reprovar', 'callback_data' => "rej:{$request_id}"),
                ),
            ),
        ),
    ));

    if ($result === null) {
        // Sending failed - drop the row so this ticket/role gets retried by the next cron sweep
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests` WHERE `id`=".intval($request_id));
        hesk_tg_log("Failed to notify {$role} approver for ticket {$ticket['trackid']}, will retry next run.");
        return false;
    }

    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests`
        SET `telegram_message_id`=".intval($result['message_id'])."
        WHERE `id`=".intval($request_id));

    hesk_tg_log("Notified {$role} approver for ticket {$ticket['trackid']} (request #{$request_id}).");

    hesk_tg_send_ticket_attachments($chat_id, $ticket['trackid']);

    return true;
} // END hesk_tg_notify_approval_request()


function hesk_tg_send_ticket_attachments($chat_id, $trackid) {
    global $hesk_settings;

    $res = hesk_dbQuery("SELECT `saved_name`, `real_name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `ticket_id`='".hesk_dbEscape($trackid)."'");

    while ($att = hesk_dbFetchAssoc($res)) {
        $file_path = HESK_PATH . $hesk_settings['attach_dir'] . '/' . $att['saved_name'];

        $result = hesk_tg_send_document($chat_id, $file_path, $att['real_name']);
        if ($result === null) {
            hesk_tg_log("Failed to send attachment '{$att['real_name']}' for ticket {$trackid}.");
        }
    }
} // END hesk_tg_send_ticket_attachments()


/**
 * Hook for whenever a file gets attached to a ticket AFTER it was created (a staff
 * reply, an internal note, a customer reply) - not just at submission time. If the
 * ticket still has a pending Telegram approval, the new file(s) get forwarded to
 * whoever is being asked to decide, so they see the full documentation before acting.
 *
 * $new_attachments: array of ['saved_name' => ..., 'real_name' => ...].
 * Best-effort: any failure here must never break the reply/note being saved.
 */
function hesk_tg_send_late_attachments($trackid, $new_attachments) {
    global $hesk_settings;

    if (empty($hesk_settings['telegram_bot_token']) || empty($new_attachments)) {
        return;
    }

    $res = hesk_dbQuery("SELECT DISTINCT `telegram_chat_id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests`
        WHERE `trackid`='".hesk_dbEscape($trackid)."' AND `status`='pending'");

    $chat_ids = array();
    while ($row = hesk_dbFetchAssoc($res)) {
        $chat_ids[] = $row['telegram_chat_id'];
    }

    if (empty($chat_ids)) {
        return;
    }

    foreach ($chat_ids as $chat_id) {
        foreach ($new_attachments as $att) {
            $file_path = HESK_PATH . $hesk_settings['attach_dir'] . '/' . $att['saved_name'];
            $result = hesk_tg_send_document($chat_id, $file_path, $att['real_name'], "📎 Novo anexo no ticket #{$trackid}");
            if ($result === null) {
                hesk_tg_log("Failed to send late attachment '{$att['real_name']}' for ticket {$trackid}.");
            }
        }
    }
} // END hesk_tg_send_late_attachments()


/**
 * Instant-notify hook, called from hesk_newTicket() right after a ticket is inserted.
 * Best-effort: any failure here must never break ticket submission itself.
 */
function hesk_tg_notify_new_ticket($ticket_id, $trackid, $category_id, $subject, $name, $message) {
    global $hesk_settings;

    if (empty($hesk_settings['telegram_bot_token'])) {
        return;
    }

    $flags = hesk_categoryApprovalFlags($category_id);
    if (!$flags['ddh'] && !$flags['auditoria']) {
        return;
    }

    $roles = hesk_tg_role_config();
    $ticket = array(
        'id'            => $ticket_id,
        'trackid'       => $trackid,
        'subject'       => $subject,
        'name'          => $name,
        'message'       => $message,
        'category_name' => hesk_getCategoryName($category_id),
    );

    if ($flags['ddh']) {
        hesk_tg_notify_approval_request($ticket, 'ddh', $roles);
    }
    if ($flags['auditoria']) {
        hesk_tg_notify_approval_request($ticket, 'auditoria', $roles);
    }
} // END hesk_tg_notify_new_ticket()


/**
 * Cancels/voids any still-pending Telegram approval requests for a ticket: marks the
 * tracking rows 'voided' and edits their Telegram messages to remove the buttons and
 * explain why. Used both proactively (ticket cancelled/resolved via the normal status
 * screens) and reactively (someone tries to click Aprovar/Reprovar on a request whose
 * ticket turns out to already be voided - see telegram_webhook.php).
 */
function hesk_tg_void_pending_requests($ticket_id, $trackid, $reason_text) {
    global $hesk_settings;

    $res = hesk_dbQuery("SELECT `id`, `role`, `telegram_chat_id`, `telegram_message_id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests`
        WHERE `ticket_id`=".intval($ticket_id)." AND `status`='pending'");

    while ($row = hesk_dbFetchAssoc($res)) {
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests`
            SET `status`='voided', `responded_at`=NOW() WHERE `id`=".intval($row['id']));

        if ($row['telegram_message_id']) {
            hesk_tg_api('editMessageText', array(
                'chat_id'    => $row['telegram_chat_id'],
                'message_id' => $row['telegram_message_id'],
                'text'       => "⚠️ *Ação não é mais necessária*\nTicket #".hesk_tg_escape_markdown($trackid)."\n".hesk_tg_escape_markdown($reason_text),
                'parse_mode' => 'Markdown',
            ));
        }

        hesk_tg_log("Voided request #{$row['id']} (ticket {$trackid}, role {$row['role']}): {$reason_text}");
    }
} // END hesk_tg_void_pending_requests()


/**
 * Hook for whenever a ticket's status changes outside the Telegram flow (staff/customer
 * cancelling, resolving, etc.). If the ticket lands on a terminal "handled elsewhere"
 * status, any pending approval requests for it are voided immediately - so nobody
 * approves/rejects something that no longer needs a decision.
 */
function hesk_tg_notify_ticket_status_changed($trackid, $new_status_id) {
    global $hesk_settings;

    if (empty($hesk_settings['telegram_bot_token'])) {
        return;
    }

    // Resolvido, Cancelado - anything else is still "in flight" and shouldn't void a pending ask.
    $terminal_statuses = array(3, 6);
    if (!in_array((int) $new_status_id, $terminal_statuses, true)) {
        return;
    }

    $res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `trackid`='".hesk_dbEscape($trackid)."' LIMIT 1");
    if (hesk_dbNumRows($res) != 1) {
        return;
    }
    $ticket_id = hesk_dbResult($res);

    $status_name = hesk_get_status_name((int) $new_status_id);
    hesk_tg_void_pending_requests($ticket_id, $trackid, "Chamado marcado como \"{$status_name}\" antes da decisão.");
} // END hesk_tg_notify_ticket_status_changed()
