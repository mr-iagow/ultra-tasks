#!/usr/bin/php -q
<?php

define('IN_SCRIPT',1);
define('HESK_PATH', dirname(dirname(__FILE__)) . '/');

// Do not send out the default UTF-8 HTTP header
define('NO_HTTP_HEADER',1);

// Get required files and functions
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/email_functions.inc.php');

// Do we require a key if not accessed over CLI?
hesk_authorizeNonCLI();

hesk_load_database_functions();
hesk_dbConnect();

// Load statuses (needed to resolve "Aprovado DDH" / "Reprovado DDH" / etc. to their numeric IDs)
require_once(HESK_PATH . 'inc/statuses.inc.php');
require(HESK_PATH . 'inc/telegram_functions.inc.php');

if (empty($hesk_settings['telegram_bot_token'])) {
    hesk_tg_log('Telegram bot token is not configured (inc/telegram_settings.inc.php missing?), aborting.', true);
}

// The `status` column on the ticket is only ever the *latest* decision, so it can't tell us on its own
// what's still outstanding when both gates are in play - that's what hesk_telegram_approval_requests is for.
$ROLES = hesk_tg_role_config();

foreach ($ROLES as $role => $cfg) {
    if ($cfg['approved_id'] === null || $cfg['rejected_id'] === null) {
        hesk_tg_log("Warning: could not resolve status IDs for role '{$role}' (approved={$cfg['approved_status']}, rejected={$cfg['rejected_status']}). Create them under Admin > Status Personalizados.");
    }
}

// Tickets in these statuses were already handled by a human before the first cron
// run ever saw them - don't start pestering anyone about them.
$TERMINAL_STATUSES = array(3, 6); // Resolvido, Cancelado

hesk_tg_log('Starting pending approvals notification run.');

reconcile_pending_requests($ROLES);

foreach ($ROLES as $role => $cfg) {
    notify_new_requests($role, $cfg, $TERMINAL_STATUSES);
}

hesk_tg_log('Finished pending approvals notification run.');


/*** START FUNCTIONS ***/

function reconcile_pending_requests($roles) {
    global $hesk_settings;

    $res = hesk_dbQuery("SELECT `r`.`id`, `r`.`role`, `r`.`telegram_chat_id`, `r`.`telegram_message_id`, `t`.`status`, `t`.`trackid`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests` AS `r`
        INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `t` ON `t`.`id` = `r`.`ticket_id`
        WHERE `r`.`status` = 'pending'");

    while ($row = hesk_dbFetchAssoc($res)) {
        if (!isset($roles[$row['role']])) {
            continue;
        }

        $cfg = $roles[$row['role']];
        $ticket_status = (int) $row['status'];

        $new_state = null;
        if ($cfg['approved_id'] !== null && $ticket_status === $cfg['approved_id']) {
            $new_state = 'approved';
        } elseif ($cfg['rejected_id'] !== null && $ticket_status === $cfg['rejected_id']) {
            $new_state = 'rejected';
        }

        if ($new_state === null) {
            continue;
        }

        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests`
            SET `status`='".hesk_dbEscape($new_state)."', `responded_at`=NOW()
            WHERE `id`=".intval($row['id']));

        if ($row['telegram_message_id']) {
            $icon = $new_state === 'approved' ? '✅' : '❌';
            $label = $new_state === 'approved' ? 'Aprovado' : 'Reprovado';
            hesk_tg_api('editMessageText', array(
                'chat_id'    => $row['telegram_chat_id'],
                'message_id' => $row['telegram_message_id'],
                'text'       => "{$icon} {$label} diretamente pelo sistema (fora do Telegram).\nTicket #".hesk_tg_escape_markdown($row['trackid']),
            ));
        }

        hesk_tg_log("Reconciled request #{$row['id']} (ticket {$row['trackid']}, role {$row['role']}) as {$new_state}.");
    }
} // END reconcile_pending_requests()


function notify_new_requests($role, $cfg, $terminal_statuses) {
    global $hesk_settings;

    $chat_id = hesk_tg_get_approver_chat_id($role);
    if ($chat_id === null) {
        hesk_tg_log("No active Telegram approver registered for role '{$role}', skipping.");
        return;
    }

    $terminal_sql = implode(',', array_map('intval', $terminal_statuses));

    $sql = "SELECT `t`.`id`, `t`.`trackid`, `t`.`subject`, `t`.`name`, `t`.`priority`, `t`.`message`, `c`.`name` AS `category_name`
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `t`
        INNER JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` AS `c` ON `c`.`id` = `t`.`category`
        LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."telegram_approval_requests` AS `r`
            ON `r`.`ticket_id` = `t`.`id` AND `r`.`role` = '".hesk_dbEscape($role)."'
        WHERE `c`.`".hesk_dbEscape($cfg['category_field'])."` = '1'
            AND `r`.`id` IS NULL
            AND `t`.`status` NOT IN ({$terminal_sql})";

    $res = hesk_dbQuery($sql);
    $roles = hesk_tg_role_config();

    while ($ticket = hesk_dbFetchAssoc($res)) {
        hesk_tg_notify_approval_request($ticket, $role, $roles);
    }
} // END notify_new_requests()
