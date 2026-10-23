<?php
/**
 * UltraTasks - ticket report with advanced filter, print, PDF (browser) and CSV export.
 *
 * Access follows the ticket list: staff only see and filter the categories they
 * are allowed to (hesk_myCategories) and the tickets their ownership permissions
 * allow (hesk_myOwnership). Administrators see everything.
 */

define('IN_SCRIPT',1);
define('HESK_PATH','../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Anyone who can view tickets may use the report: it only lists the tickets they already see in the ticket list
if ( ! hesk_checkPermission('can_run_reports', 0))
{
    hesk_checkPermission('can_view_tickets');
}

// Load custom fields and statuses
require_once(HESK_PATH . 'inc/custom_fields.inc.php');
require_once(HESK_PATH . 'inc/statuses.inc.php');

$is_admin            = ! empty($_SESSION['isadmin']);
$can_export          = hesk_checkPermission('can_export', 0);
$can_view_ass_others = hesk_checkPermission('can_view_ass_others', 0);
$can_view_ass_by     = hesk_checkPermission('can_view_ass_by', 0);
$can_filter_owner    = $is_admin || $can_view_ass_others || $can_view_ass_by;

$db_tickets = "`".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`";

/*** Options the user is allowed to filter by ***/

// Categories: only the ones this user has access to (all for admins)
$ut_cats = array();
$res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE ".hesk_myCategories('id')." ORDER BY `cat_order` ASC");
while ($row = hesk_dbFetchAssoc($res))
{
    $ut_cats[intval($row['id'])] = $row['name'];
}

// Staff (responsible / opened by)
$ut_staff = array();
$res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` ORDER BY `name` ASC");
while ($row = hesk_dbFetchAssoc($res))
{
    $ut_staff[intval($row['id'])] = $row['name'];
}

// Custom fields used in the categories this user can access
$ut_custom = array();
foreach ($hesk_settings['custom_fields'] as $k => $v)
{
    if ( ! $v['use'])
    {
        continue;
    }

    $in_my_cats = $is_admin;
    foreach (array_keys($ut_cats) as $cat_id)
    {
        if ($in_my_cats)
        {
            break;
        }
        $in_my_cats = hesk_is_custom_field_in_category($k, $cat_id);
    }

    if ($in_my_cats)
    {
        $ut_custom[$k] = $v['name'];
    }
}
asort($ut_custom, SORT_NATURAL | SORT_FLAG_CASE);

// Labels without the trailing colon used in the ticket form
$lbl_name  = rtrim($hesklang['name'], ': ');
$lbl_email = rtrim($hesklang['email'], ': ');

$ut_priorities = array(
    0 => trim($hesklang['critical'], ' *'),
    1 => $hesklang['high'],
    2 => $hesklang['medium'],
    3 => $hesklang['low'],
);

$ut_sla = array(
    'overdue' => $hesklang['ut_rep_sla_overdue'],
    'soon'    => $hesklang['ut_rep_sla_soon'],
    'ontime'  => $hesklang['ut_rep_sla_ontime'],
    'none'    => $hesklang['ut_rep_sla_none'],
);

/*** Read filters from the query string ***/

function ut_rep_ints($key, $allowed)
{
    $out = array();
    if (isset($_GET[$key]) && is_array($_GET[$key]))
    {
        foreach ($_GET[$key] as $val)
        {
            if (is_array($val) || ! preg_match('/^-?\d+$/', $val))
            {
                continue;
            }
            $val = intval($val);
            if (in_array($val, $allowed, true) && ! in_array($val, $out, true))
            {
                $out[] = $val;
            }
        }
    }
    return $out;
}

function ut_rep_date($key)
{
    $val = trim(hesk_GET($key));
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $val, $m) && checkdate($m[2], $m[3], $m[1]))
    {
        return $val;
    }
    return '';
}

function ut_rep_text($key, $max = 255)
{
    return hesk_mb_substr(trim(hesk_GET($key)), 0, $max);
}

$F = array(
    'trackid'     => ut_rep_text('trackid', 20),
    'cat'         => ut_rep_ints('cat', array_keys($ut_cats)),
    'owner'       => $can_filter_owner ? ut_rep_ints('owner', array_merge(array(-1), array_keys($ut_staff))) : array(),
    'openedby'    => ut_rep_ints('openedby', array_merge(array(0), array_keys($ut_staff))),
    'name'        => ut_rep_text('name'),
    'email'       => ut_rep_text('email'),
    'dt_from'     => ut_rep_date('dt_from'),
    'dt_to'       => ut_rep_date('dt_to'),
    'due_from'    => ut_rep_date('due_from'),
    'due_to'      => ut_rep_date('due_to'),
    'closed_from' => ut_rep_date('closed_from'),
    'closed_to'   => ut_rep_date('closed_to'),
    'status'      => ut_rep_ints('status', array_map('intval', array_keys($hesk_settings['statuses']))),
    'priority'    => ut_rep_ints('priority', array(0, 1, 2, 3)),
    'sla'         => isset($ut_sla[hesk_GET('sla')]) ? hesk_GET('sla') : '',
    'text'        => ut_rep_text('text'),
    'cf'          => isset($ut_custom[hesk_GET('cf')]) ? hesk_GET('cf') : '',
    'cfv'         => ut_rep_text('cfv'),
);

// Custom field value without a field (or the other way round) does nothing
if ($F['cf'] === '' || $F['cfv'] === '')
{
    $F['cf'] = $F['cfv'] = '';
}

// First visit: tickets opened in the last 7 days
if ( ! isset($_GET['f']))
{
    $F['dt_from'] = date('Y-m-d', strtotime('-6 days'));
    $F['dt_to']   = date('Y-m-d');
}

/*** Build the SQL ***/

// Text is stored HTML-escaped by HESK ("L &amp; S"), so search the escaped form, like find_tickets.php
function ut_rep_like($text)
{
    return "'%".hesk_dbEscape(hesk_dbLike(hesk_input($text)))."%'";
}

$where = array(hesk_myCategories(), hesk_myOwnership());

if ($F['trackid'] !== '')
{
    $where[] = "( `trackid` LIKE ".ut_rep_like($F['trackid']).(ctype_digit($F['trackid']) ? " OR `id` = ".intval($F['trackid']) : '')." )";
}
if (count($F['cat']))
{
    $where[] = "`category` IN (".implode(',', $F['cat']).")";
}
if (count($F['owner']))
{
    $owners = array_map(function ($id) { return $id == -1 ? 0 : $id; }, $F['owner']);
    $where[] = "`owner` IN (".implode(',', $owners).")";
}
if (count($F['openedby']))
{
    // "Customer" (0) also covers tickets opened by email piping, saved as -1, -2 or -3
    $staff_ids = array_diff($F['openedby'], array(0));
    $opened = array();
    if (in_array(0, $F['openedby'], true))
    {
        $opened[] = "`openedby` <= 0";
    }
    if (count($staff_ids))
    {
        $opened[] = "`openedby` IN (".implode(',', $staff_ids).")";
    }
    $where[] = "( ".implode(' OR ', $opened)." )";
}
if ($F['name'] !== '')
{
    $where[] = "`name` LIKE ".ut_rep_like($F['name'])." COLLATE '".hesk_dbCollate()."'";
}
if ($F['email'] !== '')
{
    $where[] = "`email` LIKE ".ut_rep_like($F['email']);
}
if ($F['closed_from'] !== '' || $F['closed_to'] !== '')
{
    $where[] = "`status` = 3";
}
foreach (array('dt' => 'dt', 'due' => 'due_date', 'closed' => 'closedat') as $key => $column)
{
    if ($F[$key.'_from'] !== '')
    {
        $where[] = "`$column` >= '".hesk_dbEscape($F[$key.'_from'])." 00:00:00'";
    }
    if ($F[$key.'_to'] !== '')
    {
        $where[] = "`$column` <= '".hesk_dbEscape($F[$key.'_to'])." 23:59:59'";
    }
}
if (count($F['status']))
{
    $where[] = "`status` IN (".implode(',', $F['status']).")";
}
if (count($F['priority']))
{
    $where[] = "`priority` IN ('".implode("','", $F['priority'])."')";
}
switch ($F['sla'])
{
    case 'overdue':
        $where[] = "`status` != 3 AND `due_date` IS NOT NULL AND `due_date` < NOW()";
        break;
    case 'soon':
        $where[] = "`status` != 3 AND `due_date` BETWEEN NOW() AND (NOW() + INTERVAL ".intval($hesk_settings['due_soon'])." DAY)";
        break;
    case 'ontime':
        $where[] = "`due_date` IS NOT NULL AND ( (`status` != 3 AND `due_date` >= NOW()) OR (`status` = 3 AND `closedat` <= `due_date`) )";
        break;
    case 'none':
        $where[] = "`due_date` IS NULL";
        break;
}
if ($F['text'] !== '')
{
    $like = ut_rep_like($F['text']);
    $where[] = "( `subject` LIKE $like COLLATE '".hesk_dbCollate()."' OR `message` LIKE $like COLLATE '".hesk_dbCollate()."' )";
}
if ($F['cf'] !== '')
{
    $where[] = "`".hesk_dbEscape($F['cf'])."` LIKE ".ut_rep_like($F['cfv'])." COLLATE '".hesk_dbCollate()."'";
}

// Export only the selected tickets?
$selected_ids = array();
if (isset($_GET['ids']) && is_array($_GET['ids']))
{
    $selected_ids = array_values(array_unique(array_filter(array_map('intval', $_GET['ids']))));
}

$sql_where = implode(' AND ', $where);

// Sorting
$sort_map = array(
    'trackid'  => '`trackid`',
    'subject'  => '`subject`',
    'name'     => '`name`',
    'dt'       => '`dt`',
    'due'      => '`due_date`',
    'status'   => '`status`',
    'priority' => '`priority`',
);
$sort  = isset($sort_map[hesk_GET('sort')]) ? hesk_GET('sort') : 'dt';
$order = hesk_GET('order') === 'asc' ? 'asc' : 'desc';
$sql_order = " ORDER BY ".$sort_map[$sort]." ".strtoupper($order).", `id` ".strtoupper($order);

// Paging
$limits = array(25, 50, 100, 200);
$limit = in_array(intval(hesk_GET('limit')), $limits, true) ? intval(hesk_GET('limit')) : 25;
$page  = max(1, intval(hesk_GET('page', 1)));

/*** Helpers for links ***/

// Query string parameters that keep the current filters
function ut_rep_params($F, $extra = array(), $without = '')
{
    $params = array('f' => 1);
    foreach ($F as $key => $val)
    {
        if ($key === $without || $val === '' || $val === array())
        {
            continue;
        }
        $params[$key] = $val;
    }
    if ($without === 'cf')
    {
        unset($params['cfv']);
    }
    return array_merge($params, $extra);
}

function ut_rep_url($params)
{
    return 'ut_reports.php?' . http_build_query($params, '', '&amp;');
}

function ut_rep_format_date($date)
{
    global $hesk_settings;
    return hesk_translate_date_string(date($hesk_settings['format_date'], strtotime($date)));
}

function ut_rep_initials($name)
{
    $parts = preg_split('/\s+/', trim(hesk_html_entity_decode($name)));
    $out = hesk_mb_substr($parts[0], 0, 1);
    if (count($parts) > 1)
    {
        $out .= hesk_mb_substr(end($parts), 0, 1);
    }
    return hesk_htmlspecialchars(hesk_mb_strtoupper($out));
}

function ut_rep_plain($text)
{
    return trim(hesk_html_entity_decode(strip_tags(str_replace(array('<br>', '<br />'), ' ', $text))));
}

// CSV cell: plain text, and never interpreted as a formula by Excel (CSV injection)
function ut_rep_csv($text)
{
    $text = ut_rep_plain($text);
    return preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
}

// Labels of the active filters, each with the link that removes it
function ut_rep_chips($F)
{
    global $hesklang, $hesk_settings, $ut_cats, $ut_staff, $ut_priorities, $ut_sla, $ut_custom, $lbl_name, $lbl_email;

    $names = function ($ids, $list) {
        $out = array();
        foreach ($ids as $id)
        {
            $out[] = isset($list[$id]) ? $list[$id] : $id;
        }
        return implode(', ', $out);
    };

    $chips = array();
    if ($F['trackid'] !== '')      $chips['trackid']     = $hesklang['trackID'].': '.hesk_htmlspecialchars($F['trackid']);
    if (count($F['cat']))          $chips['cat']         = $hesklang['category'].': '.(count($F['cat']) > 2 ? sprintf($hesklang['ut_rep_n_selected'], count($F['cat'])) : $names($F['cat'], $ut_cats));
    if (count($F['owner']))        $chips['owner']       = $hesklang['owner'].': '.$names($F['owner'], array(-1 => $hesklang['unas']) + $ut_staff);
    if (count($F['openedby']))     $chips['openedby']    = $hesklang['ut_rep_openedby'].': '.$names($F['openedby'], array(0 => $hesklang['ut_rep_customer']) + $ut_staff);
    if ($F['name'] !== '')         $chips['name']        = $lbl_name.': '.hesk_htmlspecialchars($F['name']);
    if ($F['email'] !== '')        $chips['email']       = $lbl_email.': '.hesk_htmlspecialchars($F['email']);
    if ($F['dt_from'] !== '')      $chips['dt_from']     = $hesklang['ut_rep_opened_from'].': '.ut_rep_format_date($F['dt_from']);
    if ($F['dt_to'] !== '')        $chips['dt_to']       = $hesklang['ut_rep_opened_to'].': '.ut_rep_format_date($F['dt_to']);
    if ($F['due_from'] !== '')     $chips['due_from']    = $hesklang['ut_rep_due_from'].': '.ut_rep_format_date($F['due_from']);
    if ($F['due_to'] !== '')       $chips['due_to']      = $hesklang['ut_rep_due_to'].': '.ut_rep_format_date($F['due_to']);
    if ($F['closed_from'] !== '')  $chips['closed_from'] = $hesklang['ut_rep_closed_from'].': '.ut_rep_format_date($F['closed_from']);
    if ($F['closed_to'] !== '')    $chips['closed_to']   = $hesklang['ut_rep_closed_to'].': '.ut_rep_format_date($F['closed_to']);
    if (count($F['status']))
    {
        $st = array();
        foreach ($F['status'] as $s)
        {
            $st[] = hesk_get_status_name($s);
        }
        $chips['status'] = $hesklang['status'].': '.implode(', ', $st);
    }
    if (count($F['priority']))     $chips['priority']    = $hesklang['priority'].': '.$names($F['priority'], $ut_priorities);
    if ($F['sla'] !== '')          $chips['sla']         = $hesklang['ut_rep_sla'].': '.$ut_sla[$F['sla']];
    if ($F['text'] !== '')         $chips['text']        = $hesklang['ut_rep_text'].': '.hesk_htmlspecialchars($F['text']);
    if ($F['cf'] !== '')           $chips['cf']          = $ut_custom[$F['cf']].': '.hesk_htmlspecialchars($F['cfv']);

    return $chips;
}

/*** Export: CSV, print and PDF (print view saved as PDF by the browser) ***/

$export = hesk_GET('export');
if (in_array($export, array('csv', 'print', 'pdf'), true))
{
    hesk_checkPermission('can_export');

    $sql_export_where = $sql_where;
    if (count($selected_ids))
    {
        $sql_export_where .= " AND `id` IN (".implode(',', $selected_ids).")";
    }

    $max_rows = $export === 'csv' ? 20000 : 2000;

    $custom_cols = '';
    foreach (array_keys($ut_custom) as $k)
    {
        $custom_cols .= ", `".$k."`";
    }

    $res = hesk_dbQuery("SELECT `id`, `trackid`, `name`, `email`, `category`, `priority`, `subject`, `dt`, `lastchange`, `closedat`,
                                `status`, `openedby`, `owner`, `due_date`, `replies` $custom_cols
                         FROM $db_tickets WHERE $sql_export_where $sql_order LIMIT ".($max_rows + 1));

    $rows = array();
    while ($row = hesk_dbFetchAssoc($res))
    {
        $rows[] = $row;
    }
    $truncated = count($rows) > $max_rows;
    if ($truncated)
    {
        array_pop($rows);
    }

    // Common columns
    $columns = array(
        'id'         => $hesklang['seqid'],
        'trackid'    => $hesklang['trackID'],
        'dt'         => $hesklang['ut_rep_opened'],
        'lastchange' => $hesklang['last_update'],
        'closedat'   => $hesklang['ut_rep_closed'],
        'due_date'   => $hesklang['due_date'],
        'category'   => $hesklang['category'],
        'subject'    => $hesklang['subject'],
        'name'       => $lbl_name,
        'email'      => $lbl_email,
        'owner'      => $hesklang['owner'],
        'openedby'   => $hesklang['ut_rep_openedby'],
        'status'     => $hesklang['status'],
        'priority'   => $hesklang['priority'],
        'replies'    => $hesklang['replies'],
    );

    $value = function ($row, $key) use ($ut_cats, $ut_staff, $ut_priorities, $hesklang) {
        switch ($key)
        {
            case 'dt':
            case 'lastchange':
                return hesk_date($row[$key], true);
            case 'closedat':
                return $row['status'] == 3 && $row['closedat'] ? hesk_date($row['closedat'], true) : '';
            case 'due_date':
                return $row['due_date'] ? hesk_format_due_date($row['due_date']) : '';
            case 'category':
                return isset($ut_cats[$row['category']]) ? $ut_cats[$row['category']] : $row['category'];
            case 'owner':
                return $row['owner'] ? (isset($ut_staff[$row['owner']]) ? $ut_staff[$row['owner']] : $row['owner']) : $hesklang['unas'];
            case 'openedby':
                return $row['openedby'] > 0 ? (isset($ut_staff[$row['openedby']]) ? $ut_staff[$row['openedby']] : $row['openedby']) : $hesklang['ut_rep_customer'];
            case 'status':
                return hesk_get_status_name($row['status']);
            case 'priority':
                return $ut_priorities[intval($row['priority'])];
            default:
                return $row[$key];
        }
    };

    if ($export === 'csv')
    {
        // Custom fields with at least one value in the exported tickets
        $used_custom = array();
        foreach (array_keys($ut_custom) as $k)
        {
            foreach ($rows as $row)
            {
                if (strlen(trim($row[$k])))
                {
                    $used_custom[$k] = $ut_custom[$k];
                    break;
                }
            }
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio_chamados_'.date('Y-m-d_Hi').'.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accents correctly
        fputcsv($out, array_map('ut_rep_plain', array_merge(array_values($columns), array_values($used_custom))), ';');

        foreach ($rows as $row)
        {
            $line = array();
            foreach (array_keys($columns) as $key)
            {
                $line[] = ut_rep_csv($value($row, $key));
            }
            foreach (array_keys($used_custom) as $k)
            {
                $line[] = ut_rep_csv($row[$k]);
            }
            fputcsv($out, $line, ';');
        }
        fclose($out);
        exit();
    }

    // Print / PDF view
    $chips = ut_rep_chips($F);
    $print_cols = array('trackid', 'dt', 'category', 'subject', 'name', 'owner', 'status', 'priority', 'due_date');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="<?php echo $hesklang['ENCODING']; ?>">
    <title><?php echo $hesklang['ut_rep_title'].' - '.$hesk_settings['hesk_title']; ?></title>
    <link rel="stylesheet" href="<?php echo HESK_PATH; ?>css/ut_reports.css?<?php echo filemtime(HESK_PATH . 'css/ut_reports.css'); ?>">
</head>
<body class="ut-rep-print">
    <div class="ut-rep-print__bar">
        <?php // Opened in a new tab by the report: close it; otherwise go back to the report with the same filters ?>
        <a class="ut-rep-print__back" href="<?php echo ut_rep_url(ut_rep_params($F, array('sort' => $sort, 'order' => $order))); ?>"
           onclick="if (window.opener) { window.close(); return false; }">&larr; <?php echo $hesklang['ut_rep_back']; ?></a>
        <span class="ut-rep-print__spacer"></span>
        <?php if ($export === 'pdf'): ?>
            <span><?php echo $hesklang['ut_rep_pdf_hint']; ?></span>
        <?php endif; ?>
        <button type="button" onclick="window.print()"><?php echo $export === 'pdf' ? $hesklang['ut_rep_save_pdf'] : $hesklang['ut_rep_print']; ?></button>
    </div>
    <h1><?php echo $hesklang['ut_rep_title']; ?></h1>
    <p class="ut-rep-print__meta">
        <?php echo $hesk_settings['hesk_title']; ?> &middot;
        <?php echo sprintf($hesklang['ut_rep_generated'], hesk_date(), $_SESSION['name']); ?> &middot;
        <?php echo sprintf($hesklang['ut_rep_n_tickets'], count($rows)); ?>
        <?php if (count($selected_ids)) { echo '&middot; '.$hesklang['ut_rep_only_selected']; } ?>
    </p>
    <?php if (count($chips)): ?>
        <p class="ut-rep-print__filters"><b><?php echo $hesklang['ut_rep_filters']; ?>:</b> <?php echo implode(' &middot; ', $chips); ?></p>
    <?php endif; ?>
    <?php if ($truncated): ?>
        <p class="ut-rep-print__warn"><?php echo sprintf($hesklang['ut_rep_truncated'], $max_rows); ?></p>
    <?php endif; ?>
    <table>
        <thead>
            <tr>
                <?php foreach ($print_cols as $key): ?>
                    <th><?php echo $columns[$key]; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($print_cols as $key): ?>
                        <td><?php echo $value($row, $key); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ( ! count($rows)): ?>
                <tr><td colspan="<?php echo count($print_cols); ?>"><?php echo $hesklang['ut_rep_empty']; ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
    <?php
    exit();
}

/*** Page ***/

$res = hesk_dbQuery("SELECT COUNT(*) FROM $db_tickets WHERE $sql_where");
$total = intval(hesk_dbResult($res));

$total_pages = max(1, (int) ceil($total / $limit));
if ($page > $total_pages)
{
    $page = $total_pages;
}

$res = hesk_dbQuery("SELECT `id`, `trackid`, `name`, `email`, `category`, `priority`, `subject`, `dt`, `status`, `openedby`, `owner`, `due_date`
                     FROM $db_tickets WHERE $sql_where $sql_order LIMIT ".(($page - 1) * $limit).", ".$limit);

$chips = ut_rep_chips($F);
$keep  = array('sort' => $sort, 'order' => $order, 'limit' => $limit);

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print admin navigation */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>
<div class="main__content ut-rep">
    <div class="ut-rep__wrap">

        <div class="ut-rep__head ut-rep__card">
            <span class="ut-rep__head-icon">
                <svg class="icon icon-reports">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-reports"></use>
                </svg>
            </span>
            <div>
                <div class="ut-crumbs"><?php echo $hesklang['reports']; ?> / <?php echo $hesklang['tickets']; ?></div>
                <h1 class="ut-rep__title"><?php echo $hesklang['ut_rep_title']; ?></h1>
            </div>
        </div>

        <?php hesk_handle_messages(); ?>

        <div class="ut-rep__card">
            <div class="ut-rep__toolbar">
                <div class="ut-rep__toolbar-left">
                    <?php if ($can_export): ?>
                        <button type="button" class="ut-rep__round" id="utRepExportOpen" title="<?php echo $hesklang['ut_rep_export']; ?>">
                            <svg class="icon icon-print">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-print"></use>
                            </svg>
                        </button>
                    <?php endif; ?>
                    <span class="ut-rep__count">
                        <?php echo sprintf($hesklang[$total == 1 ? 'ut_rep_found_one' : 'ut_rep_found'], $total); ?>
                        <span id="utRepSelCount" hidden></span>
                    </span>
                </div>
                <div class="ut-rep__toolbar-right">
                    <a class="ut-rep__clear" href="<?php echo ut_rep_url(array_merge(array('f' => 1), $keep)); ?>" title="<?php echo $hesklang['ut_rep_clear']; ?>"<?php echo count($chips) ? '' : ' hidden'; ?>>
                        <?php echo $hesklang['ut_rep_clear']; ?>
                    </a>
                    <button type="button" class="ut-rep__round ut-rep__round--accent" id="utRepFilterOpen" title="<?php echo $hesklang['ut_adv_filter']; ?>">
                        <svg class="icon icon-filters">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-filters"></use>
                        </svg>
                        <?php if (count($chips)): ?><span class="ut-rep__badge"><?php echo count($chips); ?></span><?php endif; ?>
                    </button>
                    <a class="ut-rep__round" href="<?php echo ut_rep_url(ut_rep_params($F, array_merge($keep, array('page' => $page)))); ?>" title="<?php echo $hesklang['ut_rep_refresh']; ?>">
                        <svg class="icon icon-refresh">
                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-refresh"></use>
                        </svg>
                    </a>
                </div>
            </div>

            <?php if (count($chips)): ?>
                <div class="ut-rep__chips">
                    <?php foreach ($chips as $key => $label): ?>
                        <a class="ut-rep__chip" href="<?php echo ut_rep_url(ut_rep_params($F, $keep, $key)); ?>" title="<?php echo $hesklang['ut_rep_remove']; ?>">
                            <span><?php echo $label; ?></span>
                            <svg class="icon icon-close">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                            </svg>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="ut-rep__table-wrap">
                <table class="ut-rep__table">
                    <thead>
                        <tr>
                            <th class="ut-rep__chk"><input type="checkbox" id="utRepAll" title="<?php echo $hesklang['ut_rep_select_page']; ?>"></th>
                            <?php
                            $heads = array(
                                'trackid' => $hesklang['trackID'],
                                'subject' => $hesklang['ut_rep_request'],
                                'name'    => $lbl_name,
                                ''        => $hesklang['owner'],
                                'dt'      => $hesklang['ut_rep_opened'],
                                'due'     => $hesklang['due_date'],
                                'status'  => $hesklang['status'],
                            );
                            foreach ($heads as $key => $label)
                            {
                                if ($key === '')
                                {
                                    echo '<th>'.$label.'</th>';
                                    continue;
                                }
                                $is_active = $sort === $key;
                                $next = $is_active && $order === 'desc' ? 'asc' : 'desc';
                                echo '<th class="'.($is_active ? 'is-sorted is-'.$order : '').'"><a href="'.ut_rep_url(ut_rep_params($F, array('sort' => $key, 'order' => $next, 'limit' => $limit))).'">'.$label.'</a></th>';
                            }
                            ?>
                            <th class="ut-rep__actions"><?php echo $hesklang['ut_rep_actions']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $now = time();
                    while ($row = hesk_dbFetchAssoc($res))
                    {
                        $owner_name = $row['owner'] && isset($ut_staff[$row['owner']]) ? $ut_staff[$row['owner']] : '';
                        $due_class = '';
                        if ($row['due_date'] && $row['status'] != 3)
                        {
                            $due_ts = strtotime($row['due_date']);
                            if ($due_ts < $now)
                            {
                                $due_class = ' is-overdue';
                            }
                            elseif ($due_ts < $now + 86400 * intval($hesk_settings['due_soon']))
                            {
                                $due_class = ' is-due-soon';
                            }
                        }
                        $link = 'admin_ticket.php?track='.urlencode($row['trackid']).'&amp;Refresh='.mt_rand(10000, 99999);
                        ?>
                        <tr class="ut-prio-<?php echo intval($row['priority']); ?>">
                            <td class="ut-rep__chk"><input type="checkbox" class="ut-rep__row" value="<?php echo intval($row['id']); ?>"></td>
                            <td class="ut-rep__nowrap">
                                <a class="ut-rep__track" href="<?php echo $link; ?>"><?php echo $row['trackid']; ?></a>
                                <div class="ut-rep__muted">#<?php echo intval($row['id']); ?></div>
                            </td>
                            <td>
                                <a class="ut-rep__subject" href="<?php echo $link; ?>"><?php echo $row['subject']; ?></a>
                                <div class="ut-rep__muted"><?php echo isset($ut_cats[$row['category']]) ? $ut_cats[$row['category']] : ''; ?></div>
                            </td>
                            <td>
                                <?php echo $row['name']; ?>
                                <?php if ($row['email'] !== ''): ?><div class="ut-rep__muted"><?php echo $row['email']; ?></div><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($owner_name !== ''): ?>
                                    <span class="ut-owner"><span class="ut-avatar"><?php echo ut_rep_initials($owner_name); ?></span><span><?php echo $owner_name; ?></span></span>
                                <?php else: ?>
                                    <span class="ut-owner is-unassigned"><span class="ut-avatar">&ndash;</span><span><?php echo $hesklang['unas']; ?></span></span>
                                <?php endif; ?>
                            </td>
                            <td class="ut-rep__nowrap"><?php echo hesk_date($row['dt'], true); ?></td>
                            <td class="ut-rep__nowrap"><span class="ut-due<?php echo $due_class; ?>"><?php echo $row['due_date'] ? hesk_format_due_date($row['due_date']) : '&ndash;'; ?></span></td>
                            <td><span class="ut-status"><?php echo hesk_get_admin_ticket_status($row['status']); ?></span></td>
                            <td class="ut-rep__actions">
                                <a href="<?php echo $link; ?>" title="<?php echo $hesklang['ut_rep_open_ticket']; ?>">
                                    <svg class="icon icon-edit-ticket">
                                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-edit-ticket"></use>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        <?php
                    }

                    if ( ! $total)
                    {
                        echo '<tr><td colspan="9" class="ut-rep__empty">'.$hesklang['ut_rep_empty'].'</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>

            <div class="ut-rep__footer">
                <form method="get" action="ut_reports.php" class="ut-rep__limit">
                    <?php
                    foreach (ut_rep_params($F, array('sort' => $sort, 'order' => $order)) as $key => $val)
                    {
                        foreach ((array) $val as $item)
                        {
                            echo '<input type="hidden" name="'.$key.(is_array($val) ? '[]' : '').'" value="'.hesk_htmlspecialchars($item).'">';
                        }
                    }
                    ?>
                    <label for="utRepLimit"><?php echo $hesklang['ut_rep_per_page']; ?></label>
                    <select name="limit" id="utRepLimit" onchange="this.form.submit()">
                        <?php foreach ($limits as $l): ?>
                            <option value="<?php echo $l; ?>" <?php echo $l == $limit ? 'selected' : ''; ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($total_pages > 1): ?>
                    <div class="ut-rep__pages">
                        <?php
                        $page_link = function ($p, $label, $current = false) use ($F, $keep) {
                            return '<a class="'.($current ? 'is-current' : '').'" href="'.ut_rep_url(ut_rep_params($F, array_merge($keep, array('page' => $p)))).'">'.$label.'</a>';
                        };
                        if ($page > 1) echo $page_link($page - 1, '&lsaquo;');
                        for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++)
                        {
                            echo $page_link($p, $p, $p == $page);
                        }
                        if ($page < $total_pages) echo $page_link($page + 1, '&rsaquo;');
                        ?>
                        <span class="ut-rep__muted"><?php echo sprintf($hesklang['ut_rep_page_of'], $page, $total_pages); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Advanced filter -->
<div class="ut-rep-modal" id="utRepFilter" hidden>
    <div class="ut-rep-modal__box ut-rep-modal__box--filter" role="dialog" aria-modal="true" aria-labelledby="utRepFilterTitle">
        <form method="get" action="ut_reports.php" id="utRepFilterForm">
            <input type="hidden" name="f" value="1">
            <input type="hidden" name="sort" value="<?php echo $sort; ?>">
            <input type="hidden" name="order" value="<?php echo $order; ?>">
            <input type="hidden" name="limit" value="<?php echo $limit; ?>">

            <div class="ut-rep-modal__head">
                <h2 id="utRepFilterTitle"><?php echo $hesklang['ut_adv_filter']; ?></h2>
                <button type="button" class="ut-rep-modal__close" data-close title="<?php echo $hesklang['ut_rep_cancel']; ?>">
                    <svg class="icon icon-close"><use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use></svg>
                </button>
            </div>

            <div class="ut-rep-modal__body">
                <p class="ut-rep__muted"><?php echo $hesklang['ut_rep_filter_hint']; ?></p>

                <div class="ut-rep-form">
                    <div class="ut-rep-field ut-rep-field--full">
                        <label for="rep_trackid"><?php echo $hesklang['trackID']; ?> / <?php echo $hesklang['seqid']; ?></label>
                        <input type="text" class="form-control" id="rep_trackid" name="trackid" value="<?php echo hesk_htmlspecialchars($F['trackid']); ?>">
                    </div>

                    <div class="ut-rep-field ut-rep-field--full">
                        <label><?php echo $hesklang['category']; ?> <span class="ut-rep__muted">(<?php echo sprintf($hesklang['ut_rep_cat_access'], count($ut_cats)); ?>)</span></label>
                        <?php ut_rep_multi('cat', $ut_cats, $F['cat']); ?>
                    </div>

                    <?php if ($can_filter_owner): ?>
                    <div class="ut-rep-field">
                        <label><?php echo $hesklang['owner']; ?></label>
                        <?php ut_rep_multi('owner', array(-1 => $hesklang['unas']) + $ut_staff, $F['owner']); ?>
                    </div>
                    <?php endif; ?>

                    <div class="ut-rep-field<?php echo $can_filter_owner ? '' : ' ut-rep-field--full'; ?>">
                        <label><?php echo $hesklang['ut_rep_openedby']; ?></label>
                        <?php ut_rep_multi('openedby', array(0 => $hesklang['ut_rep_customer']) + $ut_staff, $F['openedby']); ?>
                    </div>

                    <div class="ut-rep-field">
                        <label for="rep_name"><?php echo $lbl_name; ?></label>
                        <input type="text" class="form-control" id="rep_name" name="name" value="<?php echo hesk_htmlspecialchars($F['name']); ?>">
                    </div>
                    <div class="ut-rep-field">
                        <label for="rep_email"><?php echo $lbl_email; ?></label>
                        <input type="text" class="form-control" id="rep_email" name="email" value="<?php echo hesk_htmlspecialchars($F['email']); ?>">
                    </div>

                    <div class="ut-rep-field">
                        <label for="rep_dt_from"><?php echo $hesklang['ut_rep_opened_from']; ?></label>
                        <input type="date" class="form-control" id="rep_dt_from" name="dt_from" value="<?php echo $F['dt_from']; ?>">
                    </div>
                    <div class="ut-rep-field">
                        <label for="rep_dt_to"><?php echo $hesklang['ut_rep_opened_to']; ?></label>
                        <input type="date" class="form-control" id="rep_dt_to" name="dt_to" value="<?php echo $F['dt_to']; ?>">
                    </div>
                    <div class="ut-rep-field">
                        <label for="rep_due_from"><?php echo $hesklang['ut_rep_due_from']; ?></label>
                        <input type="date" class="form-control" id="rep_due_from" name="due_from" value="<?php echo $F['due_from']; ?>">
                    </div>
                    <div class="ut-rep-field">
                        <label for="rep_due_to"><?php echo $hesklang['ut_rep_due_to']; ?></label>
                        <input type="date" class="form-control" id="rep_due_to" name="due_to" value="<?php echo $F['due_to']; ?>">
                    </div>
                    <div class="ut-rep-field">
                        <label for="rep_closed_from"><?php echo $hesklang['ut_rep_closed_from']; ?></label>
                        <input type="date" class="form-control" id="rep_closed_from" name="closed_from" value="<?php echo $F['closed_from']; ?>">
                    </div>
                    <div class="ut-rep-field">
                        <label for="rep_closed_to"><?php echo $hesklang['ut_rep_closed_to']; ?></label>
                        <input type="date" class="form-control" id="rep_closed_to" name="closed_to" value="<?php echo $F['closed_to']; ?>">
                    </div>

                    <div class="ut-rep-field ut-rep-field--full">
                        <label><?php echo $hesklang['status']; ?></label>
                        <div class="ut-rep-pills">
                            <?php foreach ($hesk_settings['statuses'] as $id => $st): ?>
                                <label class="ut-rep-pill">
                                    <input type="checkbox" name="status[]" value="<?php echo intval($id); ?>" <?php echo in_array(intval($id), $F['status'], true) ? 'checked' : ''; ?>>
                                    <span><?php echo hesk_get_status_name($id); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="ut-rep-field">
                        <label><?php echo $hesklang['priority']; ?></label>
                        <div class="ut-rep-pills">
                            <?php foreach ($ut_priorities as $id => $label): ?>
                                <label class="ut-rep-pill">
                                    <input type="checkbox" name="priority[]" value="<?php echo $id; ?>" <?php echo in_array($id, $F['priority'], true) ? 'checked' : ''; ?>>
                                    <span><?php echo $label; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="ut-rep-field">
                        <label for="rep_sla"><?php echo $hesklang['ut_rep_sla']; ?></label>
                        <select id="rep_sla" name="sla" class="ut-rep-select">
                            <option value=""><?php echo $hesklang['ut_rep_any']; ?></option>
                            <?php foreach ($ut_sla as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $F['sla'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ut-rep-field ut-rep-field--full">
                        <label for="rep_text"><?php echo $hesklang['ut_rep_text']; ?></label>
                        <input type="text" class="form-control" id="rep_text" name="text" value="<?php echo hesk_htmlspecialchars($F['text']); ?>">
                    </div>

                    <?php if (count($ut_custom)): ?>
                    <div class="ut-rep-field">
                        <label for="rep_cf"><?php echo $hesklang['ut_rep_custom']; ?></label>
                        <select id="rep_cf" name="cf" class="ut-rep-select">
                            <option value=""><?php echo $hesklang['ut_rep_choose_field']; ?></option>
                            <?php foreach ($ut_custom as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $F['cf'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ut-rep-field">
                        <label for="rep_cfv"><?php echo $hesklang['ut_rep_contains']; ?></label>
                        <input type="text" class="form-control" id="rep_cfv" name="cfv" value="<?php echo hesk_htmlspecialchars($F['cfv']); ?>">
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ut-rep-modal__foot">
                <button type="button" class="btn btn--blue-border" id="utRepFilterReset"><?php echo $hesklang['ut_rep_clear_form']; ?></button>
                <span class="ut-rep-modal__spacer"></span>
                <button type="button" class="btn btn--blue-border" data-close><?php echo $hesklang['ut_rep_cancel']; ?></button>
                <button type="submit" class="btn btn-full"><?php echo $hesklang['ut_rep_apply']; ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($can_export): ?>
<!-- Export -->
<div class="ut-rep-modal" id="utRepExport" hidden>
    <div class="ut-rep-modal__box ut-rep-modal__box--small" role="dialog" aria-modal="true" aria-labelledby="utRepExportTitle">
        <div class="ut-rep-modal__head">
            <h2 id="utRepExportTitle"><?php echo $hesklang['ut_rep_export_q']; ?></h2>
            <button type="button" class="ut-rep-modal__close" data-close title="<?php echo $hesklang['ut_rep_cancel']; ?>">
                <svg class="icon icon-close"><use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use></svg>
            </button>
        </div>
        <div class="ut-rep-modal__body">
            <p class="ut-rep__muted" id="utRepExportScope"></p>
            <div class="ut-rep-export">
                <button type="button" class="btn btn--blue-border" data-export="print"><?php echo $hesklang['ut_rep_print']; ?></button>
                <button type="button" class="btn btn--blue-border" data-export="pdf">.PDF</button>
                <button type="button" class="btn btn--blue-border" data-export="csv">.CSV</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    // Modals
    function openModal(id) {
        var m = document.getElementById(id);
        if (!m) return;
        m.hidden = false;
        document.body.classList.add('ut-rep-noscroll');
    }
    function closeModal(m) {
        m.hidden = true;
        document.body.classList.remove('ut-rep-noscroll');
    }
    document.querySelectorAll('.ut-rep-modal').forEach(function (m) {
        m.addEventListener('click', function (e) {
            if (e.target === m || e.target.closest('[data-close]')) {
                closeModal(m);
            }
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.ut-rep-modal:not([hidden])').forEach(closeModal);
        }
    });
    document.getElementById('utRepFilterOpen').addEventListener('click', function () { openModal('utRepFilter'); });

    // Searchable multi-select lists
    document.querySelectorAll('.ut-rep-multi').forEach(function (box) {
        var search = box.querySelector('.ut-rep-multi__search');
        var items = box.querySelectorAll('.ut-rep-multi__item');
        var count = box.querySelector('.ut-rep-multi__count');

        function refresh() {
            var n = box.querySelectorAll('.ut-rep-multi__item input:checked').length;
            count.textContent = n ? count.dataset.label.replace('%d', n) : '';
        }
        search.addEventListener('input', function () {
            var term = search.value.toLowerCase();
            items.forEach(function (it) {
                it.hidden = it.textContent.toLowerCase().indexOf(term) === -1;
            });
        });
        box.querySelector('[data-multi="visible"]').addEventListener('click', function () {
            items.forEach(function (it) { if (!it.hidden) it.querySelector('input').checked = true; });
            refresh();
        });
        box.querySelector('[data-multi="none"]').addEventListener('click', function () {
            items.forEach(function (it) { it.querySelector('input').checked = false; });
            refresh();
        });
        box.addEventListener('change', refresh);
        refresh();
    });

    // Clear every field of the filter form (still needs "Apply")
    document.getElementById('utRepFilterReset').addEventListener('click', function () {
        var form = document.getElementById('utRepFilterForm');
        form.querySelectorAll('input[type=text], input[type=date]').forEach(function (i) { i.value = ''; });
        form.querySelectorAll('input[type=checkbox]').forEach(function (i) { i.checked = false; });
        form.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
        form.querySelectorAll('.ut-rep-multi').forEach(function (b) { b.dispatchEvent(new Event('change')); });
    });

    // Row selection
    var all = document.getElementById('utRepAll');
    var rows = document.querySelectorAll('.ut-rep__row');
    var selCount = document.getElementById('utRepSelCount');
    function selectedIds() {
        return Array.prototype.filter.call(rows, function (r) { return r.checked; }).map(function (r) { return r.value; });
    }
    function refreshSelection() {
        var n = selectedIds().length;
        selCount.hidden = !n;
        selCount.textContent = n ? <?php echo json_encode($hesklang['ut_rep_n_selected']); ?>.replace('%d', n) : '';
        all.checked = n && n === rows.length;
        rows.forEach(function (r) { r.closest('tr').classList.toggle('is-selected', r.checked); });
    }
    all.addEventListener('change', function () {
        rows.forEach(function (r) { r.checked = all.checked; });
        refreshSelection();
    });
    rows.forEach(function (r) { r.addEventListener('change', refreshSelection); });

    // Export
    var exportOpen = document.getElementById('utRepExportOpen');
    if (exportOpen) {
        var baseQuery = <?php echo json_encode(http_build_query(ut_rep_params($F, array('sort' => $sort, 'order' => $order)))); ?>;
        var total = <?php echo intval($total); ?>;
        exportOpen.addEventListener('click', function () {
            var n = selectedIds().length;
            document.getElementById('utRepExportScope').textContent = n
                ? <?php echo json_encode($hesklang['ut_rep_export_sel']); ?>.replace('%d', n)
                : <?php echo json_encode($hesklang['ut_rep_export_all']); ?>.replace('%d', total);
            openModal('utRepExport');
        });
        document.querySelectorAll('[data-export]').forEach(function (b) {
            b.addEventListener('click', function () {
                var type = b.dataset.export;
                var url = 'ut_reports.php?' + baseQuery + '&export=' + type;
                selectedIds().forEach(function (id) { url += '&ids[]=' + encodeURIComponent(id); });
                if (type === 'csv') {
                    window.location.href = url;
                } else {
                    window.open(url, '_blank');
                }
                closeModal(document.getElementById('utRepExport'));
            });
        });
    }
})();
</script>
<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** Functions ***/

// Searchable list of checkboxes (categories, staff)
function ut_rep_multi($name, $options, $selected)
{
    global $hesklang;
    ?>
    <div class="ut-rep-multi">
        <div class="ut-rep-multi__top">
            <input type="text" class="form-control ut-rep-multi__search" placeholder="<?php echo $hesklang['ut_rep_type_filter']; ?>" autocomplete="off">
            <button type="button" class="ut-rep-multi__btn" data-multi="visible"><?php echo $hesklang['ut_rep_mark_visible']; ?></button>
            <button type="button" class="ut-rep-multi__btn" data-multi="none"><?php echo $hesklang['ut_rep_unmark']; ?></button>
            <span class="ut-rep-multi__count" data-label="<?php echo hesk_htmlspecialchars($hesklang['ut_rep_n_selected']); ?>"></span>
        </div>
        <div class="ut-rep-multi__list">
            <?php foreach ($options as $id => $label): ?>
                <label class="ut-rep-multi__item">
                    <input type="checkbox" name="<?php echo $name; ?>[]" value="<?php echo intval($id); ?>" <?php echo in_array(intval($id), $selected, true) ? 'checked' : ''; ?>>
                    <span><?php echo $label; ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
} // END ut_rep_multi()
