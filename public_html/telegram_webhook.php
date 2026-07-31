<?php
/**
 * Telegram webhook for the DDH/Auditoria ticket approval flow.
 *
 * Receives callback_query updates (Aprovar / Reprovar button taps) and
 * message updates (the rejection reason, sent as a reply; and /start,
 * used by an approver to discover their chat_id for registration).
 */

define('IN_SCRIPT', 1);
define('HESK_PATH', './');
define('NO_HTTP_HEADER', 1);

require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/email_functions.inc.php');
hesk_load_database_functions();
hesk_dbConnect();

require_once(HESK_PATH . 'inc/statuses.inc.php');
require(HESK_PATH . 'inc/telegram_functions.inc.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

if (empty($hesk_settings['telegram_webhook_secret']) || !hesk_tg_verify_webhook_secret()) {
    http_response_code(403);
    exit();
}

$update = json_decode(file_get_contents('php://input'), true);
if (!is_array($update)) {
    http_response_code(400);
    exit();
}

$ROLES = hesk_tg_role_config();

if (isset($update['callback_query'])) {
    handle_callback_query($update['callback_query'], $ROLES);
} elseif (isset($update['message'])) {
    handle_message($update['message'], $ROLES);
}

// Telegram just wants a fast 200 OK; anything else makes it retry the update.
http_response_code(200);
exit();


/*** START FUNCTIONS ***/

function hesk_tg_verify_webhook_secret() {
    global $hesk_settings;

    $header = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']) ? $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] : '';

    return hash_equals($hesk_settings['telegram_webhook_secret'], $header);
} // END hesk_tg_verify_webhook_secret()


function get_approval_request($request_id) {
    global $hesk_settings;

    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests` WHERE `id`=".intval($request_id)." LIMIT 1");

    if (hesk_dbNumRows($res) != 1) {
        return null;
    }

    return hesk_dbFetchAssoc($res);
} // END get_approval_request()


function apply_ticket_decision($ticket_id, $trackid, $status_id, $status_name, $decided_by) {
    global $hesk_settings, $hesklang;

    $revision = sprintf($hesklang['thist9'], hesk_date(), addslashes($status_name), addslashes($decided_by));

    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`
        SET `status`=".intval($status_id).", `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."')
        WHERE `id`=".intval($ticket_id));
} // END apply_ticket_decision()


function add_internal_note($ticket_id, $hesk_user_id, $message) {
    global $hesk_settings;

    $message = nl2br(hesk_makeURL(hesk_input($message)));

    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` (`ticket`,`who`,`dt`,`message`,`attachments`)
        VALUES (".intval($ticket_id).", ".intval($hesk_user_id).", NOW(), '".hesk_dbEscape($message)."', '')");
} // END add_internal_note()


function handle_callback_query($callback, $roles) {
    global $hesk_settings;

    $callback_id = $callback['id'];
    $data = isset($callback['data']) ? $callback['data'] : '';
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];

    if (!preg_match('/^(appr|rej):(\d+)$/', $data, $matches)) {
        hesk_tg_api('answerCallbackQuery', array('callback_query_id' => $callback_id, 'text' => 'Ação inválida.'));
        return;
    }

    $action = $matches[1];
    $request_id = (int) $matches[2];

    $request = get_approval_request($request_id);
    if ($request === null) {
        hesk_tg_api('answerCallbackQuery', array('callback_query_id' => $callback_id, 'text' => 'Solicitação não encontrada.'));
        return;
    }

    if ((int) $request['telegram_chat_id'] !== (int) $chat_id) {
        hesk_tg_api('answerCallbackQuery', array('callback_query_id' => $callback_id, 'text' => 'Você não tem permissão para responder essa solicitação.', 'show_alert' => true));
        return;
    }

    if ($request['status'] !== 'pending') {
        hesk_tg_api('answerCallbackQuery', array('callback_query_id' => $callback_id, 'text' => 'Essa solicitação já foi respondida.'));
        return;
    }

    if (!isset($roles[$request['role']])) {
        hesk_tg_api('answerCallbackQuery', array('callback_query_id' => $callback_id, 'text' => 'Papel de aprovação desconhecido.'));
        return;
    }

    $cfg = $roles[$request['role']];
    $approver_name = hesk_tg_get_approver_name($request['role'], $chat_id);

    if ($action === 'appr') {
        if ($cfg['approved_id'] === null) {
            hesk_tg_api('answerCallbackQuery', array('callback_query_id' => $callback_id, 'text' => 'Status "'.$cfg['approved_status'].'" não existe no sistema.', 'show_alert' => true));
            return;
        }

        apply_ticket_decision($request['ticket_id'], $request['trackid'], $cfg['approved_id'], $cfg['approved_status'], "{$approver_name} (Telegram - {$cfg['label']})");

        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests`
            SET `status`='approved', `responded_at`=NOW() WHERE `id`=".intval($request_id));

        // Record the decision as a visible note on the ticket, not just the status/history line -
        // keeps approvals and rejections symmetric in what shows up on screen.
        $hesk_user_id = hesk_tg_get_approver_user_id($request['role'], $chat_id);
        if ($hesk_user_id !== null) {
            add_internal_note($request['ticket_id'], $hesk_user_id, sprintf('Aprovado via Telegram (%s) por %s.', $cfg['label'], $approver_name));
        }

        hesk_tg_api('editMessageText', array(
            'chat_id'    => $chat_id,
            'message_id' => $message_id,
            'text'       => "✅ *Aprovado* por ".hesk_tg_escape_markdown($approver_name)." em ".hesk_date()."\nTicket #".hesk_tg_escape_markdown($request['trackid'])."\n\n`appr_ref:{$request_id}`",
            'parse_mode' => 'Markdown',
        ));

        hesk_tg_api('answerCallbackQuery', array('callback_query_id' => $callback_id, 'text' => 'Aprovado!'));

        hesk_tg_api('sendMessage', array(
            'chat_id'      => $chat_id,
            'text'         => "💬 Quer registrar uma observação sobre esta aprovação? *Opcional* - responda esta mensagem com o texto, ou apenas ignore.\n\n`appr_ref:{$request_id}`",
            'parse_mode'   => 'Markdown',
            'reply_markup' => array('force_reply' => true, 'selective' => true),
        ));
        return;
    }

    // action === 'rej' -> ask for the reason via a forced reply, apply nothing yet
    hesk_tg_api('answerCallbackQuery', array('callback_query_id' => $callback_id, 'text' => 'Informe o motivo da reprovação.'));

    hesk_tg_api('sendMessage', array(
        'chat_id'      => $chat_id,
        'text'         => "✏️ Motivo da reprovação (".hesk_tg_escape_markdown($cfg['label']).") para o ticket #".hesk_tg_escape_markdown($request['trackid']).":\n\nResponda esta mensagem com o motivo.\n\n`rej_ref:{$request_id}`",
        'parse_mode'   => 'Markdown',
        'reply_markup' => array('force_reply' => true, 'selective' => true),
    ));
} // END handle_callback_query()


function handle_message($message, $roles) {
    global $hesk_settings;

    $chat_id = $message['chat']['id'];
    $text = isset($message['text']) ? trim($message['text']) : '';

    if ($text === '/start') {
        hesk_tg_api('sendMessage', array(
            'chat_id' => $chat_id,
            'text'    => "Seu chat_id é: {$chat_id}\n\nInforme esse número para o administrador do UltraTasks para ser cadastrado como aprovador.",
        ));
        return;
    }

    $reply_text = isset($message['reply_to_message']['text']) ? $message['reply_to_message']['text'] : '';

    // Is this the (optional) observation reply to an already-approved request?
    if (preg_match('/appr_ref:(\d+)/', $reply_text, $obs_matches)) {
        handle_approval_comment((int) $obs_matches[1], $chat_id, $text, $roles);
        return;
    }

    // Is this a reply to our "motivo da reprovação" prompt?
    if (!preg_match('/rej_ref:(\d+)/', $reply_text, $matches)) {
        return;
    }

    if ($text === '') {
        hesk_tg_api('sendMessage', array('chat_id' => $chat_id, 'text' => 'Motivo vazio, tente novamente respondendo a mensagem anterior.'));
        return;
    }

    $request_id = (int) $matches[1];
    $request = get_approval_request($request_id);

    if ($request === null || $request['status'] !== 'pending') {
        hesk_tg_api('sendMessage', array('chat_id' => $chat_id, 'text' => 'Essa solicitação não está mais pendente.'));
        return;
    }

    if ((int) $request['telegram_chat_id'] !== (int) $chat_id) {
        return;
    }

    if (!isset($roles[$request['role']])) {
        return;
    }

    $cfg = $roles[$request['role']];
    if ($cfg['rejected_id'] === null) {
        hesk_tg_api('sendMessage', array('chat_id' => $chat_id, 'text' => 'Status "'.$cfg['rejected_status'].'" não existe no sistema.'));
        return;
    }

    $approver_name = hesk_tg_get_approver_name($request['role'], $chat_id);

    apply_ticket_decision($request['ticket_id'], $request['trackid'], $cfg['rejected_id'], $cfg['rejected_status'], "{$approver_name} (Telegram - {$cfg['label']})");

    $hesk_user_id = hesk_tg_get_approver_user_id($request['role'], $chat_id);
    if ($hesk_user_id !== null) {
        add_internal_note($request['ticket_id'], $hesk_user_id, sprintf('Reprovado via Telegram (%s) por %s. Motivo: %s', $cfg['label'], $approver_name, $text));
    }

    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests`
        SET `status`='rejected', `reject_reason`='".hesk_dbEscape($text)."', `responded_at`=NOW()
        WHERE `id`=".intval($request_id));

    if ($request['telegram_message_id']) {
        hesk_tg_api('editMessageText', array(
            'chat_id'    => $chat_id,
            'message_id' => $request['telegram_message_id'],
            'text'       => "❌ *Reprovado* por ".hesk_tg_escape_markdown($approver_name)." em ".hesk_date()."\nTicket #".hesk_tg_escape_markdown($request['trackid'])."\n*Motivo:* ".hesk_tg_escape_markdown($text),
            'parse_mode' => 'Markdown',
        ));
    }

    hesk_tg_api('sendMessage', array('chat_id' => $chat_id, 'text' => 'Reprovação registrada. Obrigado!'));
} // END handle_message()


function handle_approval_comment($request_id, $chat_id, $text, $roles) {
    if ($text === '') {
        return;
    }

    $request = get_approval_request($request_id);
    if ($request === null || $request['status'] !== 'approved') {
        return;
    }

    if ((int) $request['telegram_chat_id'] !== (int) $chat_id) {
        return;
    }

    if (!isset($roles[$request['role']])) {
        return;
    }

    $cfg = $roles[$request['role']];
    $hesk_user_id = hesk_tg_get_approver_user_id($request['role'], $chat_id);

    if ($hesk_user_id !== null) {
        add_internal_note($request['ticket_id'], $hesk_user_id, sprintf('Observação da aprovação via Telegram (%s): %s', $cfg['label'], $text));
    }

    hesk_tg_api('sendMessage', array('chat_id' => $chat_id, 'text' => 'Observação registrada. Obrigado!'));
} // END handle_approval_comment()
