<?php
/**
 *
 * This file is part of HESK - PHP Help Desk Software.
 *
 * (c) Copyright Klemen Stirn. All rights reserved.
 * https://www.hesk.com
 *
 * For the full copyright and license agreement information visit
 * https://www.hesk.com/eula.php
 *
 */

define('IN_SCRIPT',1);
define('HESK_PATH','../');
define('LOAD_TABS',1);

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/profile_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Check permissions for this feature */
$can_man_users = hesk_checkPermission('can_man_users', false);

// This is a sensitive page, double-check user authentication
if ($can_man_users) {
    $can_view_users = true;
    hesk_check_user_elevation('manage_users.php');
} else {
    $can_view_users = hesk_checkPermission('can_view_users');
}

/* Possible user features */
$hesk_settings['features'] = array(
'can_view_tickets',		/* User can read tickets */
'can_reply_tickets',	/* User can reply to tickets */
'can_del_tickets',		/* User can delete tickets */
'can_edit_tickets',		/* User can edit tickets */
'can_merge_tickets',	/* User can merge tickets */
'can_resolve',			/* User can resolve tickets */
'can_submit_any_cat',	/* User can submit a ticket to any category/department */
'can_del_notes',		/* User can delete ticket notes posted by other staff members */
'can_change_cat',		/* User can move ticket to any category/department */
'can_change_own_cat',	/* User can move ticket to a category/department he/she has access to */
'can_due_date',         /* User can set and modify due date */
'can_man_kb',			/* User can manage knowledgebase articles and categories */
'can_man_users',		/* User can create and edit staff accounts */
'can_view_users',		/* User can view staff accounts, but not create or edit them */
'can_man_cat',			/* User can manage categories/departments */
'can_man_canned',		/* User can manage canned responses */
'can_man_ticket_tpl',	/* User can manage ticket templates */
'can_man_settings',		/* User can manage help desk settings */
'can_add_archive',		/* User can mark tickets as "Tagged" */
'can_assign_self',		/* User can assign tickets to himself/herself */
'can_assign_others',	/* User can assign tickets to other staff members */
'can_view_unassigned',	/* User can view unassigned tickets */
'can_view_ass_others',	/* User can view tickets that are assigned to other staff */
'can_view_ass_by',      /* User can view tickets he/she assigned to others */
'can_run_reports',		/* User can run reports and see statistics (only allowed categories and self) */
'can_run_reports_full', /* User can run reports and see statistics (unrestricted) */
'can_export',			/* User can export own tickets to Excel */
'can_view_online',		/* User can view what staff members are currently online */
'can_ban_emails',		/* User can ban email addresses */
'can_unban_emails',		/* User can delete email address bans. Also enables "can_ban_emails" */
'can_ban_ips',			/* User can ban IP addresses */
'can_unban_ips',		/* User can delete IP bans. Also enables "can_ban_ips" */
'can_privacy',  		/* User can use privacy tools (Anonymize tickets) */
'can_service_msg',		/* User can manage service messages shown in customer interface */
'can_email_tpl',		/* User can manage email templates */
);

/* Set default values */
$default_userdata = array(

	// Profile info
	'name' => '',
	'email' => '',
	'cleanpass' => '',
	'user' => '',
	'autoassign' => 'Y',

	// Signature
	'signature' => '',

	// Permissions
	'isadmin' => 1,
	'categories' => array('1'),
	'features' => array('can_view_tickets','can_reply_tickets','can_change_cat','can_assign_self','can_view_unassigned','can_view_online','can_resolve','can_submit_any_cat'),

	// Preferences
	'afterreply' => 0,

	// Defaults
	'autostart' => 1,
	'notify_customer_new' => 1,
	'notify_customer_reply' => 1,
	'show_suggested' => 1,
	'autoreload' => 0,

	// Notifications
	'notify_new_unassigned' => 1,
	'notify_overdue_unassigned' => 1,
	'notify_new_my' => 1,
	'notify_overdue_my' => 1,
	'notify_reply_unassigned' => 1,
	'notify_reply_my' => 1,
	'notify_assigned' => 1,
	'notify_note' => 1,
	'notify_pm' => 1,
);

/* A list of all categories */
$hesk_settings['categories'] = array();
$res = hesk_dbQuery('SELECT `id`,`name` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'categories` ORDER BY `cat_order` ASC');
while ($row=hesk_dbFetchAssoc($res))
{
	if ( hesk_okCategory($row['id'], 0) )
    {
		$hesk_settings['categories'][$row['id']] = $row['name'];
    }
}

/* Non-admin users may not create users with more permissions than they have */
if ( ! $_SESSION['isadmin'])
{
	/* Can't create admin users */
    if ( isset($_POST['isadmin']) )
	{
    	unset($_POST['isadmin']);
	}

    /* Can only add features he/she has access to */
	$hesk_settings['features'] = array_intersect( explode(',', $_SESSION['heskprivileges']) , $hesk_settings['features']);

	/* Can user modify auto-assign setting? */
    if ($hesk_settings['autoassign'] && ( ! hesk_checkPermission('can_assign_self', 0) || ! hesk_checkPermission('can_assign_others', 0) ) )
    {
    	$hesk_settings['autoassign'] = 0;
    }
}

/* Use any set values, default otherwise */
foreach ($default_userdata as $k => $v)
{
	if ( ! isset($_SESSION['userdata'][$k]) )
    {
    	$_SESSION['userdata'][$k] = $v;
    }
}

$_SESSION['userdata'] = hesk_stripArray($_SESSION['userdata']);

/* What should we do? */
if ( $action = hesk_REQUEST('a') )
{
    // Check permission again - required manage users permission for all actions
    hesk_checkPermission('can_man_users');

	if ($action == 'reset_form')
	{
		$_SESSION['edit_userdata'] = TRUE;
		header('Location: ./manage_users.php');
	}
	elseif ($action == 'edit')       {edit_user();}
	elseif ( defined('HESK_DEMO') )  {hesk_process_messages($hesklang['ddemo'], 'manage_users.php', 'NOTICE');}
	elseif ($action == 'new')        {new_user();}
	elseif ($action == 'save')       {update_user();}
	elseif ($action == 'remove')     {remove();}
	elseif ($action == 'autoassign') {toggle_autoassign();}
    elseif ($action == 'resetmfa')   {reset_mfa();}
    elseif ($action == 'forcepwreset') {force_password_reset_all();}
    elseif ($action == 'grantexport') {grant_export_all();}
    else 							 {hesk_error($hesklang['invalid_action']);}
}

else
{

/* If one came from the Edit page make sure we reset user values */

if (isset($_SESSION['save_userdata']))
{
	$_SESSION['userdata'] = $default_userdata;
    unset($_SESSION['save_userdata']);
}
if (isset($_SESSION['edit_userdata']))
{
	$_SESSION['userdata'] = $default_userdata;
    unset($_SESSION['edit_userdata']);
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>

<?php
/* This will handle error, success and notice messages */
if (!hesk_SESSION(array('userdata', 'errors'))) {
    hesk_handle_messages();
}

// If POP3 fetching is active, no user should have the same email address
if ($hesk_settings['pop3'] && hesk_validateEmail($hesk_settings['pop3_user'], 'ERR', 0))
{
    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `email` LIKE '".hesk_dbEscape($hesk_settings['pop3_user'])."'");

    if (hesk_dbNumRows($res) > 0)
    {
        while ($myuser = hesk_dbFetchAssoc($res))
        {
            if (compare_user_permissions($myuser['id'], $myuser['isadmin'], explode(',', $myuser['categories']) , explode(',', $myuser['heskprivileges'])))
            {
                hesk_show_notice(sprintf($hesklang['pop3_warning'], $myuser['name'], $hesk_settings['pop3_user']) . "<br /><br />" . $hesklang['fetch_warning'], $hesklang['warn']);
                break;
            }
        }
    }
}

// If IMAP fetching is active, no user should have the same email address
if ($hesk_settings['imap'] && hesk_validateEmail($hesk_settings['imap_user'], 'ERR', 0))
{
    $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `email` LIKE '".hesk_dbEscape($hesk_settings['imap_user'])."'");

    if (hesk_dbNumRows($res) > 0)
    {
        while ($myuser = hesk_dbFetchAssoc($res))
        {
            if (compare_user_permissions($myuser['id'], $myuser['isadmin'], explode(',', $myuser['categories']) , explode(',', $myuser['heskprivileges'])))
            {
                hesk_show_notice(sprintf($hesklang['imap_warning'], $myuser['name'], $hesk_settings['imap_user']) . "<br /><br />" . $hesklang['fetch_warning'], $hesklang['warn']);
                break;
            }
        }
    }
}

// We probably shouldn't have two or more users with the same email address; show a notice if so
$res = hesk_dbQuery('SELECT `email`, COUNT(*) AS `cnt` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'users` GROUP BY `email` HAVING `cnt` > 1');
if (hesk_dbNumRows($res) > 0)
{
    $emails = array();
    while ($row = hesk_dbFetchAssoc($res))
    {
        $emails[$row['email']] = $row['cnt'];
    }

    //hesk_show_notice($hesklang['uue'] . '<br><br>' . implode('<br>', array_keys($emails)));
    hesk_show_notice($hesklang['uue']);
}
?>
<div class="main__content team ut-team">
    <section class="team__head ut-team__head">
        <div class="ut-team__title">
        <h2>
            <?php echo $hesklang['team']; ?>
            <div class="tooltype right out-close">
                <svg class="icon icon-info">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                </svg>
                <div class="tooltype__content">
                    <div class="tooltype__wrapper">
                        <?php echo $hesklang['users_intro']; ?>
                    </div>
                </div>
            </div>
        </h2>
        <p class="ut-team__summary" id="utTeamSummary"></p>
        </div>
        <?php if ($can_man_users): ?>
        <div class="ut-team__actions">
        <div class="ut-team__bulk">
        <?php
        $force_pwreset_modal_id = hesk_generate_delete_modal($hesklang['pass_reset_forced_all_title'],
            $hesklang['pass_reset_forced_all_confirm'],
            'manage_users.php?a=forcepwreset&amp;token='.hesk_token_echo(0),
            $hesklang['pass_reset_forced_all_yes']);
        ?>
        <a href="javascript:" data-modal="[data-modal-id='<?php echo $force_pwreset_modal_id; ?>']" class="btn btn btn--blue-border"><?php echo $hesklang['pass_reset_forced_all_button']; ?></a>
        <?php
        // Staff can only give permissions they have themselves
        if (hesk_checkPermission('can_export', 0)):
            $grant_export_modal_id = hesk_generate_delete_modal($hesklang['ut_grant_export_title'],
                $hesklang['ut_grant_export_confirm'],
                'manage_users.php?a=grantexport&amp;token='.hesk_token_echo(0),
                $hesklang['ut_grant_export_yes']);
        ?>
        <a href="javascript:" data-modal="[data-modal-id='<?php echo $grant_export_modal_id; ?>']" class="btn btn btn--blue-border"><?php echo $hesklang['ut_grant_export_button']; ?></a>
        <?php endif; ?>
        </div>
        <button class="btn btn-full" ripple="ripple" data-action="team-create"><?php echo $hesklang['new_team_member']; ?></button>
        </div>
        <?php endif; ?>
    </section>

    <!-- Search and role filter (client side) -->
    <section class="ut-team__toolbar">
        <div class="ut-team__search">
            <svg class="icon icon-search">
                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-search"></use>
            </svg>
            <input type="text" class="form-control" id="utTeamSearch" placeholder="<?php echo $hesklang['ut_team_search']; ?>" autocomplete="off">
        </div>
        <div class="ut-team__roles" role="group">
            <button type="button" class="ut-team__role is-active" data-role=""><?php echo $hesklang['ut_team_all']; ?> <span></span></button>
            <button type="button" class="ut-team__role" data-role="admin"><?php echo $hesklang['ut_team_admins']; ?> <span></span></button>
            <button type="button" class="ut-team__role" data-role="staff"><?php echo $hesklang['staff']; ?> <span></span></button>
        </div>
    </section>
    <div class="table-wrap">
        <div class="table">
            <table id="default-table" class="table sindu-table">
                <thead>
                <tr>
                    <th><?php echo $hesklang['ut_team_member']; ?></th>
                    <th><?php echo $hesklang['username']; ?></th>
                    <th><?php echo $hesklang['role']; ?></th>
                    <th><?php echo $hesklang['ut_team_tickets']; ?></th>
                    <?php
                    /* Is user rating enabled? */
                    if ($hesk_settings['rating']) {
                        ?>
                        <th><?php echo $hesklang['rating']; ?></th>
                        <?php
                    }

                    /* Is autoassign enabled? */
                    if ($hesk_settings['autoassign']) {
                        ?>
                        <th><?php echo $hesklang['aass']; ?></th>
                        <?php
                    }
                    ?>
                    <th><?php echo $hesklang['mfa_short']; ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php
                $tickets_per_user = array();
                $tickets_per_user_rs = hesk_dbQuery('SELECT COUNT(1) AS `cnt`, `owner`, CASE WHEN `status` = 3 THEN 0 ELSE 1 END AS `open` 
                    FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` 
                    GROUP BY `owner`, CASE WHEN `status` = 3 THEN 0 ELSE 1 END');
                while ($row = hesk_dbFetchAssoc($tickets_per_user_rs)) {
                    if (!isset($tickets_per_user[$row['owner']])) {
                        $tickets_per_user[$row['owner']] = array(
                            'open' => 0,
                            'closed' => 0,
                            'total' => 0
                        );
                    }

                    $tickets_per_user[$row['owner']]['total'] += $row['cnt'];
                    if ($row['open']) {
                        $tickets_per_user[$row['owner']]['open'] += $row['cnt'];
                    } else {
                        $tickets_per_user[$row['owner']]['closed'] += $row['cnt'];
                    }
                }
                $res = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'users` ORDER BY `name` ASC');

                $cannot_manage = array();

                while ($myuser = hesk_dbFetchAssoc($res)) {

                    $can_manage_this_user = !$can_man_users ? false : compare_user_permissions($myuser['id'], $myuser['isadmin'], explode(',', $myuser['categories']) , explode(',', $myuser['heskprivileges']));
                    $can_view_this_user = ($can_manage_this_user || $can_view_users) ? true : false;

                    if (!$can_view_this_user) {
                        $cannot_manage[$myuser['id']] = array('name' => $myuser['name'], 'user' => $myuser['user'], 'email' => $myuser['email']);
                        continue;
                    }

                    $is_admin_row = (bool) $myuser['isadmin'];
                    $plain_name = $myuser['name'];

                    $table_row = 'data-role="'.($is_admin_row ? 'admin' : 'staff').'" data-search="'.hesk_htmlspecialchars(hesk_mb_strtolower(hesk_html_entity_decode($myuser['name'].' '.$myuser['email'].' '.$myuser['user']))).'"';
                    if (isset($_SESSION['seluser']) && $myuser['id'] == $_SESSION['seluser']) {
                        $table_row .= ' class="ticket-new"';
                        unset($_SESSION['seluser']);
                    }

                    /* User online? */
                    if ($hesk_settings['online']) {
                        if (isset($hesk_settings['users_online'][$myuser['id']])) {
                            $myuser['name'] = '
                                <svg class="icon icon-assign is-online">
                                  <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-assign"></use>
                                </svg>' .
                                $myuser['name'];
                        }
                        else
                        {
                            $myuser['name'] = '
                                <svg class="icon icon-assign-no is-offline">
                                  <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-assign-no"></use>
                                </svg>' .
                                $myuser['name'];
                        }
                    }

                    /* To edit yourself go to "Profile" page, not here. */
                    if ($myuser['id'] == $_SESSION['id']) {
                        $edit_code = '
                            <a href="profile.php" class="edit tooltip" title="'.$hesklang['edit'].'">
                                <svg class="icon icon-edit-ticket">
                                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-edit-ticket"></use>
                                </svg>
                            </a>';
                    } elseif ($can_manage_this_user) {
                        $edit_code = '
                            <a href="manage_users.php?a=edit&amp;id='.$myuser['id'].'" class="edit tooltip" title="'.$hesklang['edit'].'">
                                <svg class="icon icon-edit-ticket">
                                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-edit-ticket"></use>
                                </svg>
                            </a>';
                    } else {
                        $edit_code = '';
                    }

                    if ($myuser['isadmin']) {
                        $myuser['isadmin'] = $hesklang['administrator'];
                    } else {
                        $myuser['isadmin'] = $hesklang['staff'];
                    }

                    /* Deleting user with ID 1 (default administrator) is not allowed */
                    if ($myuser['id'] == 1) {
                        $remove_code = '';
                    } elseif ($myuser['id'] == $_SESSION['id']) {
                        // You cannot delete your own account
                        $remove_code = '';
                    } elseif ($can_manage_this_user) {
                        $modal_body = $hesklang['sure_remove_user'];
                        if (isset($tickets_per_user[$myuser['id']]) && $tickets_per_user[$myuser['id']]['total'] > 0) {
                            $total_tickets = $tickets_per_user[$myuser['id']]['total'];
                            $open_tickets = $tickets_per_user[$myuser['id']]['open'];
                            $modal_body .= '<br><br>'.
                                '<div class="notification orange">
                                    <b>'.$hesklang['sm_notice'].'</b><br> '.sprintf($hesklang['deleting_user_with_tickets'], $total_tickets, $open_tickets).'
                                </div>';
                        }

                        $modal_id = hesk_generate_delete_modal($hesklang['confirm_deletion'],
                            $modal_body,
                            'manage_users.php?a=remove&amp;id='.$myuser['id'].'&amp;token='.hesk_token_echo(0));
                        $remove_code = '
                        <a href="javascript:" data-modal="[data-modal-id=\''.$modal_id.'\']"
                            title="'.$hesklang['remove'].'"
                            class="delete tooltip">
                            <svg class="icon icon-delete">
                                <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-delete"></use>
                            </svg>
                        </a>';
                    } else {
                        $remove_code = '';
                    }

                    /* Is auto assign enabled? */
                    if ($hesk_settings['autoassign']) {
                        if ($myuser['autoassign']) {
                            $autoassign_code = '
                                <label class="switch-checkbox">
                                    <a class="tooltip" data-ztt_vertical_offset="-5" id="autoassign-'.$myuser['id'].'" href="manage_users.php?a=autoassign&amp;s=0&amp;id='.$myuser['id'].'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['aaon'].'">
                                        <input type="checkbox" checked>
                                        <div class="switch-checkbox__bullet">
                                            <i>
                                                <svg class="icon icon-close">
                                                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-close"></use>
                                                </svg>
                                                <svg class="icon icon-tick">
                                                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-tick"></use>
                                                </svg>
                                            </i>
                                        </div>
                                    </a>
                                </label>
                                ';
                        } elseif ($can_manage_this_user) {
                            $autoassign_code = '
                                <label class="switch-checkbox">
                                    <a class="tooltip" data-ztt_vertical_offset="-5" id="autoassign-'.$myuser['id'].'"  href="manage_users.php?a=autoassign&amp;s=1&amp;id='.$myuser['id'].'&amp;token='.hesk_token_echo(0).'" title="'.$hesklang['aaoff'].'">
                                        <input type="checkbox">
                                        <div class="switch-checkbox__bullet">
                                            <i>
                                                <svg class="icon icon-close">
                                                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-close"></use>
                                                </svg>
                                                <svg class="icon icon-tick">
                                                    <use xlink:href="' . HESK_PATH . 'img/sprite.svg#icon-tick"></use>
                                                </svg>
                                            </i>
                                        </div>
                                    </a>
                                </label>';
                        } else {
                            $autoassign_code = '';
                        }
                    } else {
                        $autoassign_code = '';
                    }

                    // Member cell: initials, name and e-mail; open / total tickets owned
                    $initials = ut_team_initials($plain_name);
                    $role_class = $is_admin_row ? 'is-admin' : 'is-staff';
                    $tk = isset($tickets_per_user[$myuser['id']]) ? $tickets_per_user[$myuser['id']] : array('open' => 0, 'total' => 0);
                    $tickets_cell = '<span class="ut-team__tk"><b>'.intval($tk['open']).'</b> '.$hesklang[intval($tk['open']) == 1 ? 'ut_team_open_one' : 'ut_team_open'].'</span><span class="ut-team__muted">'.sprintf($hesklang['ut_team_total'], intval($tk['total'])).'</span>';

                    echo <<<EOC
<tr $table_row>
<td>
    <div class="ut-team__member">
        <span class="ut-avatar">$initials</span>
        <div>
            <div class="ut-team__name">$myuser[name]</div>
            <a class="ut-team__email" href="mailto:$myuser[email]">$myuser[email]</a>
        </div>
    </div>
</td>
<td class="ut-team__user">$myuser[user]</td>
<td><span class="ut-team__badge $role_class">$myuser[isadmin]</span></td>
<td class="nowrap">$tickets_cell</td>

EOC;

                    if ($hesk_settings['rating']) {
                        $alt = $myuser['rating'] ? sprintf($hesklang['rated'], sprintf("%01.1f", $myuser['rating']), ($myuser['ratingneg']+$myuser['ratingpos'])) : $hesklang['not_rated'];
                        echo '<td style="text-align:center; white-space:nowrap;">
                            '.hesk3_get_rating($myuser['rating']).'
                        </td>';
                    }

                    if ($hesk_settings['autoassign']) {
                        echo '<td>' . $autoassign_code . '</td>';
                    }

                    $mfa_enrollment = intval($myuser['mfa_enrollment']);
                    $mfa_status = $hesklang['mfa_method_none'];
                    $mfa_reset = '';
                    $modal_id = hesk_generate_delete_modal($hesklang['mfa_reset_to_default'],
                        $hesklang['mfa_reset_confirm'],
                        'manage_users.php?a=resetmfa&amp;id='.$myuser['id'].'&amp;token='.hesk_token_echo(0),
                        $hesklang['mfa_reset_yes']);

                    if ($mfa_enrollment === 1) {
                        $mfa_status = $hesklang['mfa_method_email'];

                        if (!$hesk_settings['require_mfa'] && $can_manage_this_user) {

                            $mfa_reset = '<div class="tooltype right out-close">
                                <a href="javascript:" data-modal="[data-modal-id=\''.$modal_id.'\']"
                                    title="'.$hesklang['mfa_reset_to_default'].'"
                                    class="delete tooltip">
                                    <svg class="icon icon-refresh">
                                        <use xlink:href="'. HESK_PATH . 'img/sprite.svg#icon-refresh"></use>
                                    </svg>
                                </a>
                            </div>';
                        }
                    } elseif ($mfa_enrollment === 2) {
                        $mfa_status = $hesklang['mfa_method_auth_app_short'];

                        if ($can_manage_this_user) {
                            $mfa_reset = '<div class="tooltype right out-close">
                                    <a href="javascript:" data-modal="[data-modal-id=\''.$modal_id.'\']"
                                        title="'.$hesklang['mfa_reset_to_default'].'"
                                        class="delete tooltip">
                                        <svg class="icon icon-refresh">
                                            <use xlink:href="'. HESK_PATH . 'img/sprite.svg#icon-refresh"></use>
                                        </svg>
                                    </a>
                                </div>';
                        }
                    }

                    $mfa_class = $mfa_enrollment ? 'is-on' : 'is-off';
                    echo <<<EOC
<td><div class="ut-team__mfa"><span class="ut-team__badge $mfa_class">$mfa_status</span> $mfa_reset</div></td>
<td class="nowrap buttons"><p>$edit_code $remove_code</p></td>
</tr>

EOC;
                } // End while
                ?>
                </tbody>
            </table>
            <p class="ut-team__empty" id="utTeamEmpty" hidden><?php echo $hesklang['ut_team_empty']; ?></p>
        </div>
    </div>
    <div class="ut-team__footer" id="utTeamFooter">
        <span class="ut-team__range" id="utTeamRange"></span>
        <nav class="ut-team__pages" id="utTeamPages" aria-label="<?php echo $hesklang['ut_team_pages']; ?>"></nav>
    </div>
</div>
<script>
(function () {
    var PER_PAGE = 25;
    var rows = Array.prototype.slice.call(document.querySelectorAll('#default-table tbody tr[data-role]'));
    var search = document.getElementById('utTeamSearch');
    var roles = document.querySelectorAll('.ut-team__role');
    var empty = document.getElementById('utTeamEmpty');
    var rangeEl = document.getElementById('utTeamRange');
    var pagesEl = document.getElementById('utTeamPages');
    var role = '';
    var page = 1;

    var count = { '': rows.length, admin: 0, staff: 0 };
    rows.forEach(function (r) { count[r.dataset.role]++; });
    roles.forEach(function (b) { b.querySelector('span').textContent = count[b.dataset.role]; });
    document.getElementById('utTeamSummary').textContent = <?php echo json_encode($hesklang['ut_team_summary']); ?>
        .replace('%1', count['']).replace('%2', count.admin).replace('%3', count.staff);

    // Rows that match the search and role filter
    function matching() {
        var term = search.value.trim().toLowerCase();
        return rows.filter(function (r) {
            return (!role || r.dataset.role === role) && (!term || r.dataset.search.indexOf(term) !== -1);
        });
    }

    function pageButton(p, label, current, disabled) {
        var b = document.createElement('button');
        b.type = 'button';
        b.innerHTML = label;
        if (current) {
            b.className = 'is-current';
            b.setAttribute('aria-current', 'page');
        }
        if (disabled) {
            b.disabled = true;
        } else {
            b.addEventListener('click', function () {
                page = p;
                render();
                document.querySelector('.ut-team__toolbar').scrollIntoView({ block: 'start', behavior: 'smooth' });
            });
        }
        return b;
    }

    function render() {
        var list = matching();
        var pages = Math.max(1, Math.ceil(list.length / PER_PAGE));
        page = Math.min(Math.max(1, page), pages);
        var first = (page - 1) * PER_PAGE;
        var visible = list.slice(first, first + PER_PAGE);

        rows.forEach(function (r) { r.hidden = visible.indexOf(r) === -1; });
        empty.hidden = list.length > 0;

        rangeEl.textContent = list.length
            ? <?php echo json_encode($hesklang['ut_team_range']); ?>.replace('%1', first + 1).replace('%2', first + visible.length).replace('%3', list.length)
            : '';

        // Previous, first, window of pages around the current one, last, next
        pagesEl.innerHTML = '';
        if (pages > 1) {
            pagesEl.appendChild(pageButton(page - 1, '&lsaquo;', false, page === 1));
            var from = Math.max(1, page - 2), to = Math.min(pages, page + 2);
            // "…" only hides two or more pages; a single missing page is shown instead
            if (from === 3) from = 2;
            if (to === pages - 2) to = pages - 1;
            if (from > 1) {
                pagesEl.appendChild(pageButton(1, '1', false, false));
                if (from > 2) pagesEl.appendChild(Object.assign(document.createElement('span'), { textContent: '…' }));
            }
            for (var p = from; p <= to; p++) {
                pagesEl.appendChild(pageButton(p, String(p), p === page, false));
            }
            if (to < pages) {
                if (to < pages - 1) pagesEl.appendChild(Object.assign(document.createElement('span'), { textContent: '…' }));
                pagesEl.appendChild(pageButton(pages, String(pages), false, false));
            }
            pagesEl.appendChild(pageButton(page + 1, '&rsaquo;', false, page === pages));
        }
    }

    search.addEventListener('input', function () { page = 1; render(); });
    roles.forEach(function (b) {
        b.addEventListener('click', function () {
            roles.forEach(function (x) { x.classList.remove('is-active'); });
            b.classList.add('is-active');
            role = b.dataset.role;
            page = 1;
            render();
        });
    });

    // After creating or editing a user, open the page where that user is highlighted
    var highlighted = document.querySelector('#default-table tbody tr.ticket-new');
    if (highlighted) {
        page = Math.floor(rows.indexOf(highlighted) / PER_PAGE) + 1;
    }
    render();
})();
</script>
<?php if ($can_man_users): ?>
<div class="right-bar team-create" <?php echo hesk_SESSION(array('userdata','errors')) ? 'style="display: block"' : ''; ?>>
    <div class="right-bar__body form" data-step="1">
        <h3>
            <a href="manage_users.php?a=reset_form">
                <svg class="icon icon-back">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-back"></use>
                </svg>
                <span><?php echo $hesklang['add_user']; ?></span>
            </a>
        </h3>
        <?php
        if (hesk_SESSION(array('userdata', 'errors'))) {
            hesk_handle_messages();
        }
        ?>
        <form name="form1" method="post" action="manage_users.php" class="form <?php echo hesk_SESSION(array('userdata','errors')) ? 'invalid' : ''; ?>">
            <?php hesk_profile_tab('userdata', false); ?>

            <!-- Submit -->
            <div class="right-bar__footer">
                <input type="hidden" name="a" value="new">
                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                <button type="button" class="btn btn-border" ripple="ripple" data-action="back"><?php echo $hesklang['wizard_back']; ?></button>
                <button type="button" class="btn btn-full next" data-action="next" ripple="ripple"><?php echo $hesklang['wizard_next']; ?></button>
                <button type="submit" class="btn btn-full save" data-action="save" ripple="ripple"><?php echo $hesklang['create_user']; ?></button>
            </div>
        </form>
    </div>
</div>
<?php
endif;

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();

} // End else


/*** START FUNCTIONS ***/


// Up to two initials for the member avatar
function ut_team_initials($name)
{
    $parts = preg_split('/\s+/', trim(hesk_html_entity_decode($name)));
    $out = hesk_mb_substr($parts[0], 0, 1);
    if (count($parts) > 1)
    {
        $out .= hesk_mb_substr(end($parts), 0, 1);
    }
    return hesk_htmlspecialchars(hesk_mb_strtoupper($out));
}


function compare_user_permissions($compare_id, $compare_isadmin = null, $compare_categories = null, $compare_features = null)
{
	global $hesk_settings;

    /* Comparing myself? */
    if ($compare_id == $_SESSION['id'])
    {
    	return true;
    }

    /* Admins have full access, no need to compare */
	if ($_SESSION['isadmin'])
    {
    	return true;
    }
    elseif ($compare_isadmin)
    {
    	return false;
    }

    // Do we need to get data from the database?
    if ($compare_categories === null)
    {
        $res = hesk_dbQuery("SELECT `isadmin`, `categories`, `heskprivileges` AS `features` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='".intval($compare_id)."' LIMIT 1");
        $row = hesk_dbFetchAssoc($res);

        // If this user is an admin and we're not - no need to check further
        if ($row['isadmin'])
        {
            return false;
        }

        $compare_features = explode(',', $row['features']);
        $compare_categories = explode(',', $row['categories']);
    }

	/* Compare categories */
    foreach ($compare_categories as $catid)
    {
    	if ( ! array_key_exists($catid, $hesk_settings['categories']) )
        {
        	return false;
        }
    }

	/* Compare features */
    foreach ($compare_features as $feature)
    {
    	if ( ! in_array($feature, $hesk_settings['features']) )
        {
        	return false;
        }
    }

    return true;

} // END compare_user_permissions()


function edit_user()
{
	global $hesk_settings, $hesklang, $default_userdata;

	$id = intval( hesk_GET('id') ) or hesk_error("$hesklang[int_error]: $hesklang[no_valid_id]");

	/* To edit self fore using "Profile" page */
    if ($id == $_SESSION['id'])
    {
    	hesk_process_messages($hesklang['eyou'],'profile.php','NOTICE');
    }

    $_SESSION['edit_userdata'] = TRUE;

    if ( ! isset($_SESSION['save_userdata']))
    {
		$res = hesk_dbQuery("SELECT *,`heskprivileges` AS `features` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='".intval($id)."' LIMIT 1");
    	$_SESSION['userdata'] = hesk_dbFetchAssoc($res);

        /* Store original username for display until changes are saved successfully */
        $_SESSION['original_user'] = $_SESSION['userdata']['user'];

        /* A few variables need special attention... */
        if ($_SESSION['userdata']['isadmin'])
        {
	        $_SESSION['userdata']['features'] = $default_userdata['features'];
	        $_SESSION['userdata']['categories'] = $default_userdata['categories'];
        }
        else
        {
	        $_SESSION['userdata']['features'] = explode(',',$_SESSION['userdata']['features']);
	        $_SESSION['userdata']['categories'] = explode(',',$_SESSION['userdata']['categories']);
        }
        $_SESSION['userdata']['cleanpass'] = '';
    }

	/* Make sure we have permission to edit this user */
	if ( ! compare_user_permissions($id, $_SESSION['userdata']['isadmin'], $_SESSION['userdata']['categories'], $_SESSION['userdata']['features']) )
	{
		hesk_process_messages($hesklang['npea'],'manage_users.php');
	}

    /* Print header */
	require_once(HESK_PATH . 'inc/header.inc.php');

	/* Print main manage users page */
	require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
	?>
    <div class="right-bar team-create" style="display: block">
        <div class="right-bar__body form" data-step="1">
            <h3>
                <a href="manage_users.php">
                    <svg class="icon icon-back">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-back"></use>
                    </svg>
                    <span><?php echo $hesklang['editing_user'].' '.$_SESSION['original_user']; ?></span>
                </a>
            </h3>
            <?php
            if (hesk_SESSION(array('userdata', 'errors'))) {
                /* This will handle error, success and notice messages */
                echo '<div style="margin: -24px -24px 10px -16px;">';
                hesk_handle_messages();
                echo '</div>';
            }
            ?>
            <form name="form1" method="post" action="manage_users.php" class="form <?php echo hesk_SESSION(array('userdata','errors')) ? 'invalid' : ''; ?>">
                <?php hesk_profile_tab('userdata', false); ?>

                <!-- Submit -->
                <div class="right-bar__footer">
                    <input type="hidden" name="a" value="save">
                    <input type="hidden" name="userid" value="<?php echo $id; ?>" />
                    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>">
                    <button type="button" class="btn btn-border" ripple="ripple" data-action="back"><?php echo $hesklang['wizard_back']; ?></button>
                    <button type="button" class="btn btn-full next" data-action="next" ripple="ripple"><?php echo $hesklang['wizard_next']; ?></button>
                    <button type="submit" class="btn btn-full save" data-action="save" ripple="ripple"><?php echo $hesklang['save_changes']; ?></button>
                </div>
            </form>
        </div>
    </div>

	<?php
	require_once(HESK_PATH . 'inc/footer.inc.php');
	exit();
} // End edit_user()


function new_user()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

	$myuser = hesk_validateUserInfo();

    /* Categories and Features will be stored as a string */
    $myuser['categories'] = implode(',',$myuser['categories']);
    $myuser['features'] = implode(',',$myuser['features']);
    $myuser['equipe_colaborador'] = hesk_input($_POST['equipe_colaborador']) ?? ''; // Capture the team field

    /* Check for duplicate usernames */
	$result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `user` = '".hesk_dbEscape($myuser['user'])."' LIMIT 1");
	if (hesk_dbNumRows($result) != 0)
	{
        hesk_process_messages($hesklang['duplicate_user'],'manage_users.php');
	}

    /* Admins will have access to all features and categories */
    if ($myuser['isadmin'])
    {
		$myuser['categories'] = '';
		$myuser['features'] = '';
    }

    hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."users` (
        `user`,
        `pass`,
        `isadmin`,
        `name`,
        `email`,
        `signature`,
        `categories`,
        `autoassign`,
        `heskprivileges`,
        `afterreply`,
        `autostart`,
        `autoreload`,
        `notify_customer_new`,
        `notify_customer_reply`,
        `show_suggested`,
        `notify_new_unassigned`,
        `notify_overdue_unassigned`,
        `notify_new_my`,
        `notify_overdue_my`,
        `notify_reply_unassigned`,
        `notify_reply_my`,
        `notify_assigned`,
        `notify_pm`,
        `notify_note`,
        `equipe_colaborador`
    ) VALUES (
        '".hesk_dbEscape($myuser['user'])."',
        '".hesk_dbEscape($myuser['pass'])."',
        '".intval($myuser['isadmin'])."',
        '".hesk_dbEscape($myuser['name'])."',
        '".hesk_dbEscape($myuser['email'])."',
        '".hesk_dbEscape($myuser['signature'])."',
        '".hesk_dbEscape($myuser['categories'])."',
        '".intval($myuser['autoassign'])."',
        '".hesk_dbEscape($myuser['features'])."',
        '".($myuser['afterreply'])."',
        '".($myuser['autostart'])."',
        '".($myuser['autoreload'])."',
        '".($myuser['notify_customer_new'])."',
        '".($myuser['notify_customer_reply'])."',
        '".($myuser['show_suggested'])."',
        '".($myuser['notify_new_unassigned'])."',
        '".($myuser['notify_overdue_unassigned'])."',
        '".($myuser['notify_new_my'])."',
        '".($myuser['notify_overdue_my'])."',
        '".($myuser['notify_reply_unassigned'])."',
        '".($myuser['notify_reply_my'])."',
        '".($myuser['notify_assigned'])."',
        '".($myuser['notify_pm'])."',
        '".($myuser['notify_note'])."',
        '".hesk_dbEscape($myuser['equipe_colaborador'])."'
    )");
    

    $_SESSION['seluser'] = hesk_dbInsertID();

    unset($_SESSION['userdata']);

    hesk_process_messages(sprintf($hesklang['user_added_success'],$myuser['user'],$myuser['cleanpass']),'./manage_users.php','SUCCESS');
} // End new_user()


function update_user()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

    $_SESSION['save_userdata'] = TRUE;

	$tmp = intval( hesk_POST('userid') ) or hesk_error("$hesklang[int_error]: $hesklang[no_valid_id]");

	/* To edit self fore using "Profile" page */
    if ($tmp == $_SESSION['id'])
    {
    	hesk_process_messages($hesklang['eyou'],'profile.php','NOTICE');
    }

    $_SERVER['PHP_SELF'] = './manage_users.php?a=edit&id='.$tmp;
	$myuser = hesk_validateUserInfo(0,$_SERVER['PHP_SELF']);
    $myuser['id'] = $tmp;

    // Make sure we have permission to edit this user
    if ( ! compare_user_permissions($myuser['id']))
    {
        hesk_process_messages($hesklang['npea'],'manage_users.php');
    }

    /* Check for duplicate usernames */
	$res = hesk_dbQuery("SELECT `id`,`isadmin`,`categories`,`heskprivileges` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `user` = '".hesk_dbEscape($myuser['user'])."' LIMIT 1");
	if (hesk_dbNumRows($res) == 1)
	{
    	$tmp = hesk_dbFetchAssoc($res);

        /* Duplicate? */
        if ($tmp['id'] != $myuser['id'])
        {
        	hesk_process_messages($hesklang['duplicate_user'],$_SERVER['PHP_SELF']);
        }

		/* Do we have permission to edit this user? */
		if ( ! compare_user_permissions($tmp['id'], $tmp['isadmin'], explode(',', $tmp['categories']) , explode(',', $tmp['heskprivileges'])) )
		{
			hesk_process_messages($hesklang['npea'],'manage_users.php');
		}
	}

    /* Admins will have access to all features and categories */
    if ($myuser['isadmin'])
    {
		$myuser['categories'] = '';
		$myuser['features'] = '';
    }
	/* Not admin */
	else
    {
		/* Categories and Features will be stored as a string */
	    $myuser['categories'] = implode(',',$myuser['categories']);
	    $myuser['features'] = implode(',',$myuser['features']);

    	/* Unassign tickets from categories that the user had access before but doesn't anymore */
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `owner`=0 WHERE `owner`='".intval($myuser['id'])."' AND `category` NOT IN (".$myuser['categories'].")");
    }

	hesk_dbQuery(
    "UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET
    `user`='".hesk_dbEscape($myuser['user'])."',
    `name`='".hesk_dbEscape($myuser['name'])."',
    `email`='".hesk_dbEscape($myuser['email'])."',
    `signature`='".hesk_dbEscape($myuser['signature'])."'," . ( isset($myuser['pass']) ? "`pass`='".hesk_dbEscape($myuser['pass'])."'," : '' ) . "
    `categories`='".hesk_dbEscape($myuser['categories'])."',
    `isadmin`='".intval($myuser['isadmin'])."',
    `autoassign`='".intval($myuser['autoassign'])."',
    `heskprivileges`='".hesk_dbEscape($myuser['features'])."',
	`afterreply`='".($myuser['afterreply'])."' ,
	`autostart`='".($myuser['autostart'])."' ,
	`autoreload`='".($myuser['autoreload'])."' ,
	`notify_customer_new`='".($myuser['notify_customer_new'])."' ,
	`notify_customer_reply`='".($myuser['notify_customer_reply'])."' ,
	`show_suggested`='".($myuser['show_suggested'])."' ,
	`notify_new_unassigned`='".($myuser['notify_new_unassigned'])."' ,
	`notify_overdue_unassigned`='".($myuser['notify_overdue_unassigned'])."' ,
	`notify_new_my`='".($myuser['notify_new_my'])."' ,
	`notify_overdue_my`='".($myuser['notify_overdue_my'])."' ,
	`notify_reply_unassigned`='".($myuser['notify_reply_unassigned'])."' ,
	`notify_reply_my`='".($myuser['notify_reply_my'])."' ,
	`notify_assigned`='".($myuser['notify_assigned'])."' ,
	`notify_pm`='".($myuser['notify_pm'])."',
	`notify_note`='".($myuser['notify_note'])."',
    `equipe_colaborador`='".hesk_dbEscape($myuser['equipe_colaborador'])."'
    WHERE `id`='".intval($myuser['id'])."'");

    unset($_SESSION['save_userdata']);
    unset($_SESSION['userdata']);

    // Clear users' authentication tokens if password changed
    if (defined('PASSWORD_CHANGED')) {
        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` WHERE `user_id` = ".intval($myuser['id']));
    }

    $_SESSION['seluser'] = $myuser['id'];

    hesk_process_messages( $hesklang['user_profile_updated_success'], './manage_users.php','SUCCESS');
} // End update_profile()


function hesk_validateUserInfo($pass_required = 1, $redirect_to = './manage_users.php')
{
	global $hesk_settings, $hesklang;

    $hesk_error_buffer = '';
    $errors = array();

    if (hesk_input(hesk_POST('name'))) {
        $myuser['name'] = hesk_input(hesk_POST('name'));
    } else {
        $hesk_error_buffer .= '<li>' . $hesklang['enter_real_name'] . '</li>';
        $errors[] = 'name';
    }

    if (hesk_validateEmail( hesk_POST('email'), 'ERR', 0)) {
        $myuser['email'] = hesk_validateEmail( hesk_POST('email'), 'ERR', 0);
    } else {
        $hesk_error_buffer .= '<li>' . $hesklang['enter_valid_email'] . '</li>';
        $errors[] = 'email';
    }

    if (hesk_input( hesk_POST('user') )) {
        $myuser['user'] = hesk_input(hesk_POST('user'));
    } else {
        $hesk_error_buffer .= '<li>' . $hesklang['enter_username'] . '</li>';
        $errors[] = 'user';
    }

      // Validação do campo equipe_colaborador
      if (hesk_input(hesk_POST('equipe_colaborador'))) {
        $myuser['equipe_colaborador'] = hesk_input(hesk_POST('equipe_colaborador'));
    } else {
        $hesk_error_buffer .= '<li>' . $hesklang['enter_team_name'] . '</li>';
        $errors[] = 'equipe_colaborador';
    }

	$myuser['isadmin']	  = empty($_POST['isadmin']) ? 0 : 1;
	$myuser['signature']  = hesk_input( hesk_POST('signature') );
    $myuser['autoassign'] = hesk_POST('autoassign') == 'Y' ? 1 : 0;

    /* If it's not admin at least one category and fature is required */
    $myuser['categories']	= array();
    $myuser['features']		= array();

    if ($myuser['isadmin']==0)
    {
    	if (empty($_POST['categories']) || ! is_array($_POST['categories']) )
        {
			$hesk_error_buffer .= '<li>' . $hesklang['asign_one_cat'] . '</li>';
			$errors[] = 'categories';
        }
        else
        {
			foreach ($_POST['categories'] as $tmp)
			{
            	if (is_array($tmp))
                {
                	continue;
                }

				if ($tmp = intval($tmp))
				{
					$myuser['categories'][] = $tmp;
				}
			}
        }

    	if (empty($_POST['features']) || ! is_array($_POST['features']) )
        {
			$hesk_error_buffer .= '<li>' . $hesklang['asign_one_feat'] . '</li>';
			$errors[] = 'features';
        }
        else
        {
			foreach ($_POST['features'] as $tmp)
			{
				if (in_array($tmp,$hesk_settings['features']))
				{
					$myuser['features'][] = $tmp;
				}
			}
        }

        // One needs view tickets permissions in one has reply to permission
        if (in_array('can_reply_tickets', $myuser['features']))
        {
            $myuser['features'][] = 'can_view_tickets';
        }
	}

	if (hesk_mb_strlen($myuser['signature'])>1000)
    {
    	$hesk_error_buffer .= '<li>' . $hesklang['signature_long'] . '</li>';
    	$errors[] = 'signature';
    }

    /* Password */
	$myuser['cleanpass'] = '';

	$newpass = hesk_input( hesk_POST('newpass') );
	$passlen = strlen($newpass);

	if ($pass_required || $passlen > 0)
	{
        /* At least 5 chars? */
        if ($passlen < 5)
        {
        	$hesk_error_buffer .= '<li>' . $hesklang['password_not_valid'] . '</li>';
        	$errors[] = 'passwords';
        }
        // Too long?
        elseif ($passlen > 64)
        {
            $hesk_error_buffer .= '<li>' . $hesklang['pass_len'] . '</li>';
            $errors[] = 'passwords';
        }
        /* Check password confirmation */
        else
        {
        	$newpass2 = hesk_input( hesk_POST('newpass2') );

			if ($newpass != $newpass2)
			{
				$hesk_error_buffer .= '<li>' . $hesklang['passwords_not_same'] . '</li>';
                $errors[] = 'passwords';
			}
            else
            {
                $myuser['pass'] = hesk_password_hash($newpass);
                $myuser['cleanpass'] = $newpass;
                define('PASSWORD_CHANGED', true);
            }
        }
	}

    /* After reply */
    $myuser['afterreply'] = intval( hesk_POST('afterreply') );
    if ($myuser['afterreply'] != 1 && $myuser['afterreply'] != 2)
    {
    	$myuser['afterreply'] = 0;
    }

    // Defaults
    $myuser['autostart']				= isset($_POST['autostart']) ? 1 : 0;
    $myuser['notify_customer_new']		= isset($_POST['notify_customer_new']) ? 1 : 0;
    $myuser['notify_customer_reply']	= isset($_POST['notify_customer_reply']) ? 1 : 0;
    $myuser['show_suggested']			= isset($_POST['show_suggested']) ? 1 : 0;
    $myuser['autoreload']				= isset($_POST['autoreload']) ? 1 : 0;

    if ($myuser['autoreload'])
    {
        $myuser['autoreload'] = intval(hesk_POST('reload_time'));

        if (hesk_POST('secmin') == 'min')
        {
            $myuser['autoreload'] *= 60;
        }

        if ($myuser['autoreload'] < 0 || $myuser['autoreload'] > 65535)
        {
            $myuser['autoreload'] = 30;
        }
    }

    /* Notifications */
    $myuser['notify_new_unassigned']	    = empty($_POST['notify_new_unassigned']) ? 0 : 1;
    $myuser['notify_overdue_unassigned']    = empty($_POST['notify_overdue_unassigned']) ? 0 : 1;
    $myuser['notify_new_my'] 			    = empty($_POST['notify_new_my']) ? 0 : 1;
    $myuser['notify_overdue_my']            = empty($_POST['notify_overdue_my']) ? 0 : 1;
    $myuser['notify_reply_unassigned']	    = empty($_POST['notify_reply_unassigned']) ? 0 : 1;
    $myuser['notify_reply_my']			    = empty($_POST['notify_reply_my']) ? 0 : 1;
    $myuser['notify_assigned']			    = empty($_POST['notify_assigned']) ? 0 : 1;
    $myuser['notify_note']				    = empty($_POST['notify_note']) ? 0 : 1;
    $myuser['notify_pm']				    = empty($_POST['notify_pm']) ? 0 : 1;

    /* Save entered info in session so we don't lose it in case of errors */
	$_SESSION['userdata'] = $myuser;

    /* Any errors */
    if (strlen($hesk_error_buffer))
    {
		if ($myuser['isadmin'])
		{
			// Preserve default staff data for the form
			global $default_userdata;
        	$_SESSION['userdata']['features'] = $default_userdata['features'];
        	$_SESSION['userdata']['categories'] = $default_userdata['categories'];
		}
        $_SESSION['userdata']['errors'] = $errors;

        $hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
    	hesk_process_messages($hesk_error_buffer,$redirect_to);
    }

	// "can_unban_emails" feature also enables "can_ban_emails"
	if ( in_array('can_unban_emails', $myuser['features']) && ! in_array('can_ban_emails', $myuser['features']) )
	{
    	$myuser['features'][] = 'can_ban_emails';
	}

    // "can_unban_ips" feature also enables "can_ban_ips"
    if ( in_array('can_unban_ips', $myuser['features']) && ! in_array('can_ban_ips', $myuser['features']) )
    {
        $myuser['features'][] = 'can_ban_ips';
    }

	return $myuser;

} // End hesk_validateUserInfo()


function remove()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$myuser = intval( hesk_GET('id' ) ) or hesk_error($hesklang['no_valid_id']);

    /* You can't delete the default user */
	if ($myuser == 1)
    {
        hesk_process_messages($hesklang['cant_del_admin'],'./manage_users.php');
    }

    /* You can't delete your own account (the one you are logged in) */
	if ($myuser == $_SESSION['id'])
    {
        hesk_process_messages($hesklang['cant_del_own'],'./manage_users.php');
    }

    // Make sure we have permission to edit this user
    if ( ! compare_user_permissions($myuser))
    {
        hesk_process_messages($hesklang['npea'],'manage_users.php');
    }

    /* Un-assign all tickets for this user */
    // Don't update resolved tickets "Last modified"
    // $res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `owner`=0, `lastchange`=`lastchange` WHERE `owner`='".intval($myuser)."' AND `status` = '3'");
    // For unresolved tickets, update the "Last modified"
    $res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `owner`=0 WHERE `owner`='".intval($myuser)."' AND `status` <> '3'");

    /* Delete user info */
	$res = hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='".intval($myuser)."'");
	if (hesk_dbAffectedRows() != 1)
    {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['user_not_found'],'./manage_users.php');
    }

	/* Delete any user reply drafts */
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `owner`={$myuser}");

    // Clear users' authentication and MFA tokens
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."auth_tokens` WHERE `user_id` = {$myuser}");
    hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mfa_verification_tokens` WHERE `user_id` = {$myuser}");

	// Refresh autoassign configs to ensure their ID is gone
    hesk_updateAutoassignConfigs();

    hesk_process_messages($hesklang['sel_user_removed'],'./manage_users.php','SUCCESS');
} // End remove()


function toggle_autoassign()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$myuser = intval( hesk_GET('id' ) ) or hesk_error($hesklang['no_valid_id']);
    $_SESSION['seluser'] = $myuser;

    if ( intval( hesk_GET('s') ) )
    {
		$autoassign = 1;
        $tmp = $hesklang['uaaon'];
    }
    else
    {
        $autoassign = 0;
        $tmp = $hesklang['uaaoff'];
    }

	/* Update auto-assign settings */
	$res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET `autoassign`='{$autoassign}' WHERE `id`='".intval($myuser)."'");
	if (hesk_dbAffectedRows() != 1)
    {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['user_not_found'],'./manage_users.php');
    }

    hesk_process_messages($tmp,'./manage_users.php','SUCCESS');
} // End toggle_autoassign()

function reset_mfa() {
    global $hesk_settings, $hesklang;

    /* A security check */
    hesk_token_check();

    require(HESK_PATH . 'inc/mfa_functions.inc.php');

    $myuser = intval(hesk_GET('id')) or hesk_error($hesklang['no_valid_id']);

    // Make sure we have permission to edit this user
    if ( ! compare_user_permissions($myuser))
    {
        hesk_process_messages($hesklang['npea'],'manage_users.php');
    }

    $_SESSION['seluser'] = $myuser;

    $target_enrollment = 0;
    if ($hesk_settings['require_mfa']) {
        $target_enrollment = 1;
    }

    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET `mfa_enrollment` = {$target_enrollment}, `mfa_secret` = NULL WHERE `id` = {$myuser}");

    if (hesk_dbAffectedRows() != 1) {
        hesk_process_messages($hesklang['int_error'].': '.$hesklang['user_not_found'],'./manage_users.php');
    }

    delete_mfa_backup_codes($myuser);
    delete_mfa_codes($myuser);

    hesk_process_messages($hesklang['mfa_reset'], './manage_users.php', 'SUCCESS');
}

function force_password_reset_all() {
    global $hesk_settings, $hesklang;

    /* A security check */
    hesk_token_check();

    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET `pass_reset_required` = '1'");

    hesk_process_messages($hesklang['pass_reset_forced_all'], './manage_users.php', 'SUCCESS');
}

// Gives "can export tickets" to every staff member (admins already have every permission)
function grant_export_all() {
    global $hesk_settings, $hesklang;

    /* A security check */
    hesk_token_check();

    // Staff can only give permissions they have themselves
    hesk_checkPermission('can_export');

    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users`
                  SET `heskprivileges` = IF(`heskprivileges` = '', 'can_export', CONCAT(`heskprivileges`, ',can_export'))
                  WHERE `isadmin` = '0' AND CONCAT(',', `heskprivileges`, ',') NOT LIKE '%,can_export,%'");

    hesk_process_messages(sprintf($hesklang['ut_grant_export_done'], hesk_dbAffectedRows()), './manage_users.php', 'SUCCESS');
}
?>
