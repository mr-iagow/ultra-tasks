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

/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {die('Invalid attempt');} 

if ( ! isset($status) )
{
	$status = $hesk_settings['statuses'];
    unset($status[3]);
}

if ( ! isset($priority) )
{
	$priority = array(
	0 => 'CRITICAL',
	1 => 'HIGH',
	2 => 'MEDIUM',
	3 => 'LOW',
	);
}

if ( ! isset($what) )
{
	$what = 'trackid';
}

if ( ! isset($owner_input) )
{
	$owner_input = 0;
}

if ( ! isset($date_input) )
{
	$date_input = '';
}

if (hesk_GET('duedate_option') === '') {
    $duedate_search_type = 'specific';
}

$duedate_input = hesk_GET('duedate_specific_date');
$duedate_amount_value = hesk_GET('duedate_amount_value');
$duedate_amount_unit = hesk_GET('duedate_amount_unit', 'day');

/* Can view tickets that are unassigned or assigned to others? */
$can_view_ass_others = hesk_checkPermission('can_view_ass_others',0);
$can_view_unassigned = hesk_checkPermission('can_view_unassigned',0);
$can_view_ass_by     = hesk_checkPermission('can_view_ass_by', 0);

/* Category options */
$category_options = '';
if ( isset($hesk_settings['categories']) && count($hesk_settings['categories']) )
{
	foreach ($hesk_settings['categories'] as $row['id'] => $row['name'])
	{
		$row['name'] = (hesk_mb_strlen($row['name']) > 30) ? hesk_mb_substr($row['name'],0,30) . '...' : $row['name'];
		$selected = ($row['id'] == $category) ? 'selected="selected"' : '';
		$category_options .= '<option value="'.$row['id'].'" '.$selected.'>'.$row['name'].'</option>';
	}
}
else
{
	$res2 = hesk_dbQuery('SELECT `id`, `name` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'categories` WHERE ' . hesk_myCategories('id') . ' ORDER BY `cat_order` ASC');
	while ($row=hesk_dbFetchAssoc($res2))
	{
		$row['name'] = (hesk_mb_strlen($row['name']) > 30) ? hesk_mb_substr($row['name'],0,30) . '...' : $row['name'];
		$selected = ($row['id'] == $category) ? 'selected="selected"' : '';
		$category_options .= '<option value="'.$row['id'].'" '.$selected.'>'.$row['name'].'</option>';
	}
}

/* List of staff */
if (($can_view_ass_others || $can_view_ass_by) && ! isset($admins))
{
	$admins = array();
	$res2 = hesk_dbQuery("SELECT `id`,`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` ORDER BY `name` ASC");
	while ($row=hesk_dbFetchAssoc($res2))
	{
		$admins[$row['id']]=$row['name'];
	}
}

$more = empty($_GET['more']) ? 0 : 1;
$more2 = empty($_GET['more2']) ? 0 : 1;

?>
