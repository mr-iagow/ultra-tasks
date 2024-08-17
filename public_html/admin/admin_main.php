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

/* Make sure the install folder is deleted */
if (is_dir(HESK_PATH . 'install')) {die('Please delete the <b>install</b> folder from your server for security reasons then refresh this page!');}

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

define('CALENDAR',1);
define('MAIN_PAGE',1);
define('AUTO_RELOAD',1);

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print admin navigation */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>

<div class="main__content tickets">
<div style="margin-left: -16px; margin-right: -24px;">
<?php

/* This will handle error, success and notice messages */
hesk_handle_messages();
?>
</div>
<?php
/* Print tickets? */
if (hesk_checkPermission('can_view_tickets',0))
{
	/* Reset default settings? */
	if ( isset($_GET['reset']) && hesk_token_check() )
	{
		$res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."users` SET `default_list`='' WHERE `id` = '".intval($_SESSION['id'])."'");
        $_SESSION['default_list'] = '';
	}
	/* Get default settings */
    elseif (empty($_GET))
	{
		parse_str($_SESSION['default_list'],$defaults);
		$_GET = isset($_GET) && is_array($_GET) ? array_merge($_GET, $defaults) : $defaults;
	}

	/* Print the list of tickets */
    $href = 'admin_main.php';
	require(HESK_PATH . 'inc/print_tickets.inc.php');

    echo "&nbsp;<br />";

    /* Print forms for listing and searching tickets */
	require(HESK_PATH . 'inc/show_search_form.inc.php');
}
else
{
	echo '<p><i>'.$hesklang['na_view_tickets'].'</i></p>';
}

/*******************************************************************************
The code below handles HESK licensing and must be included in the template.

Removing this code is a direct violation of the HESK End User License Agreement,
will void all support and may result in unexpected behavior.

To purchase a HESK license and support future HESK development please visit:
https://www.hesk.com/buy.php
*******************************************************************************/

/*******************************************************************************
END LICENSE CODE
*******************************************************************************/

echo '</div>';

/* Clean unneeded session variables */
hesk_cleanSessionVars('hide');

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();
?>
