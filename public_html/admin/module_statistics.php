<?php
/**
 * UltraTasks - Statistics module (own implementation for the local install,
 * replaces the HESK Cloud showcase page).
 *
 * Help desk usage and team performance for tickets opened in a period:
 * open vs resolved, assignment, answers, status, volume by day/weekday/hour,
 * time to first staff reply, time to resolve and staff replies per ticket.
 * Read only. Staff only see the categories and tickets they already have access to.
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

// Check permissions for this feature
hesk_checkPermission('can_run_reports');

require_once(HESK_PATH . 'inc/statuses.inc.php');

$S = $hesklang['statistics'];
$is_admin = ! empty($_SESSION['isadmin']);
$can_filter_owner = $is_admin || hesk_checkPermission('can_view_ass_others', 0) || hesk_checkPermission('can_view_ass_by', 0);

/*** Filter options ***/

$ut_cats = array();
$res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE ".hesk_myCategories('id')." ORDER BY `cat_order` ASC");
while ($row = hesk_dbFetchAssoc($res))
{
    $ut_cats[intval($row['id'])] = $row['name'];
}

$ut_staff = array();
$res = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` ORDER BY `name` ASC");
while ($row = hesk_dbFetchAssoc($res))
{
    $ut_staff[intval($row['id'])] = $row['name'];
}

/*** Period ***/

function ut_st_date($key)
{
    $val = trim(hesk_GET($key));
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $val, $m) && checkdate($m[2], $m[3], $m[1]))
    {
        return $val;
    }
    return '';
}

$today = date('Y-m-d');
$presets = array(
    '7d'    => array($hesklang['ut_st_p7'],    date('Y-m-d', strtotime('-6 days')),  $today),
    '30d'   => array($hesklang['ut_st_p30'],   date('Y-m-d', strtotime('-29 days')), $today),
    'month' => array($hesklang['ut_st_pm'],    date('Y-m-01'),                         $today),
    'last'  => array($hesklang['ut_st_plm'],   date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))),
    '90d'   => array($hesklang['ut_st_p90'],   date('Y-m-d', strtotime('-89 days')), $today),
    'year'  => array($hesklang['ut_st_py'],    date('Y-01-01'),                         $today),
    'all'   => array($hesklang['ut_st_pall'],  '', ''),
);

$preset = isset($presets[hesk_GET('p')]) ? hesk_GET('p') : '';
if ($preset !== '')
{
    $date_from = $presets[$preset][1];
    $date_to   = $presets[$preset][2];
}
else
{
    $date_from = ut_st_date('from');
    $date_to   = ut_st_date('to');
    if ($date_from === '' && $date_to === '' && ! isset($_GET['from']))
    {
        $preset = '30d';
        $date_from = $presets['30d'][1];
        $date_to   = $presets['30d'][2];
    }
}
if ($date_from !== '' && $date_to !== '' && $date_from > $date_to)
{
    list($date_from, $date_to) = array($date_to, $date_from);
}

$cat = intval(hesk_GET('cat'));
if ( ! isset($ut_cats[$cat]))
{
    $cat = 0;
}
$owner = $can_filter_owner ? intval(hesk_GET('owner')) : 0;
if ($owner > 0 && ! isset($ut_staff[$owner]))
{
    $owner = 0;
}

/*** Data: tickets opened in the period that this user can see ***/

$where = array(hesk_myCategories(), hesk_myOwnership());
if ($date_from !== '')
{
    $where[] = "`dt` >= '".hesk_dbEscape($date_from)." 00:00:00'";
}
if ($date_to !== '')
{
    $where[] = "`dt` <= '".hesk_dbEscape($date_to)." 23:59:59'";
}
if ($cat)
{
    $where[] = "`category` = ".intval($cat);
}
if ($owner == -1)
{
    $where[] = "`owner` = 0";
}
elseif ($owner > 0)
{
    $where[] = "`owner` = ".intval($owner);
}

/*** Tickets behind a key number (pop-up list, 20 per page) ***/

if (hesk_GET('list') !== '')
{
    $lists = array(
        'total'    => array($hesklang['ut_st_k_total'], '1', ''),
        'open'     => array($S['open'], "`status` != 3", ''),
        'resolved' => array($S['resolved'], "`status` = 3", ''),
        'unas'     => array($hesklang['ut_st_k_unas'], "`status` != 3 AND `owner` = 0", ''),
        'first'    => array($hesklang['ut_st_k_first'], "`staffreplies` > 0 AND `firstreply` IS NOT NULL", 'firstreply'),
        'solve'    => array($hesklang['ut_st_k_solve'], "`status` = 3 AND `closedat` IS NOT NULL", 'closedat'),
    );
    $key = isset($lists[hesk_GET('list')]) ? hesk_GET('list') : 'total';
    list($list_title, $list_where, $time_col) = $lists[$key];
    $per_page = 20;

    $sql_where = implode(' AND ', $where).' AND '.$list_where;
    $res = hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE ".$sql_where);
    $list_total = intval(hesk_dbResult($res));
    $list_pages = max(1, (int) ceil($list_total / $per_page));
    $list_page = min($list_pages, max(1, intval(hesk_GET('page', 1))));

    $res = hesk_dbQuery("SELECT `trackid`, `id`, `name`, `subject`, `category`, `status`, `owner`, `openedby`, `dt`, `due_date`"
        .($time_col ? ", `".$time_col."` AS `time_end`" : '')."
        FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE ".$sql_where."
        ORDER BY `dt` DESC, `id` DESC LIMIT ".(($list_page - 1) * $per_page).", ".$per_page);

    $now = time();
    ob_start();
    ?>
    <table class="ut-rep__table ut-st-list">
        <thead>
            <tr>
                <th><?php echo $hesklang['trackID']; ?></th>
                <th><?php echo $hesklang['ut_rep_request']; ?></th>
                <th><?php echo rtrim($hesklang['name'], ': '); ?></th>
                <th><?php echo $hesklang['ut_rep_openedby']; ?></th>
                <th><?php echo $hesklang['owner']; ?></th>
                <th><?php echo $hesklang['status']; ?></th>
                <th><?php echo $hesklang['ut_rep_opened']; ?></th>
                <th><?php echo $hesklang['due_date']; ?></th>
                <?php if ($time_col): ?><th><?php echo $hesklang[$key == 'first' ? 'ut_st_col_first' : 'ut_st_col_solve']; ?></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php while ($t = hesk_dbFetchAssoc($res)):
            $due = '&ndash;';
            if ($t['due_date'])
            {
                $late = $t['status'] != 3 && strtotime($t['due_date']) < $now;
                $due = '<span class="ut-due'.($late ? ' is-overdue' : '').'">'.hesk_format_due_date($t['due_date']).($late ? '<small>'.$hesklang['ql_ovr'].'</small>' : '').'</span>';
            }
            $opened_by = $t['openedby'] > 0 ? (isset($ut_staff[$t['openedby']]) ? $ut_staff[$t['openedby']] : '#'.$t['openedby']) : $hesklang['ut_rep_customer'];
            ?>
            <tr>
                <td class="ut-rep__nowrap">
                    <a class="ut-rep__track" href="admin_ticket.php?track=<?php echo urlencode($t['trackid']); ?>" target="_blank" rel="noopener"><?php echo $t['trackid']; ?></a>
                    <div class="ut-rep__muted">#<?php echo intval($t['id']); ?></div>
                </td>
                <td>
                    <span class="ut-rep__subject"><?php echo $t['subject']; ?></span>
                    <?php $cat_name = isset($ut_cats[$t['category']]) ? $ut_cats[$t['category']] : ''; ?>
                    <div class="ut-rep__muted" title="<?php echo hesk_htmlspecialchars(hesk_html_entity_decode($cat_name)); ?>"><?php echo $cat_name; ?></div>
                </td>
                <td><?php echo $t['name']; ?></td>
                <td><?php echo $opened_by; ?></td>
                <td><?php echo $t['owner'] ? (isset($ut_staff[$t['owner']]) ? $ut_staff[$t['owner']] : '#'.$t['owner']) : '<span class="ut-rep__muted">'.$hesklang['unas'].'</span>'; ?></td>
                <td><span class="ut-status"><?php echo hesk_get_admin_ticket_status($t['status']); ?></span></td>
                <td class="ut-rep__nowrap"><?php echo hesk_date($t['dt'], true); ?></td>
                <td class="ut-rep__nowrap"><?php echo $due; ?></td>
                <?php if ($time_col): ?><td class="ut-rep__nowrap"><?php echo ut_st_duration(max(0, (strtotime($t['time_end']) - strtotime($t['dt'])) / 60)); ?></td><?php endif; ?>
            </tr>
        <?php endwhile; ?>
        <?php if ( ! $list_total): ?>
            <tr><td colspan="9" class="ut-rep__empty"><?php echo $S['ntsp']; ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
    $table = ob_get_clean();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'title' => strip_tags($list_title),
        'total' => $list_total,
        'page'  => $list_page,
        'pages' => $list_pages,
        'range' => $list_total ? sprintf($hesklang['ut_st_dd_range'], ($list_page - 1) * $per_page + 1, min($list_total, $list_page * $per_page), ut_st_num($list_total)) : '',
        'html'  => $table,
    ));
    exit();
}

$res = hesk_dbQuery("SELECT `dt`, `firstreply`, `closedat`, `status`, `owner`, `staffreplies`
                     FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE ".implode(' AND ', $where));

$first_buckets = array(30 => 'ct_30', 60 => 'ct_1h', 120 => 'ct_2h', 240 => 'ct_4h', 1440 => 'ct_1d', 2880 => 'ct_2d', 4320 => 'ct_3d', 10080 => 'ct_1w', PHP_INT_MAX => 'ct_wp');
$solve_buckets = array(60 => 'ct_1h', 120 => 'ct_2h', 240 => 'ct_4h', 1440 => 'ct_1d', 2880 => 'ct_2d', 4320 => 'ct_3d', 10080 => 'ct_1w', 43200 => 'ct_1m', PHP_INT_MAX => 'ct_mp');
$reply_buckets = array(0 => 'tr_0', 1 => 'tr_1', 2 => 'tr_2', 5 => 'tr_5', 10 => 'tr_10', PHP_INT_MAX => 'tr_99');

$d = array(
    'total' => 0, 'open' => 0, 'resolved' => 0,
    'open_assigned' => 0, 'open_unassigned' => 0, 'open_answered' => 0, 'open_unanswered' => 0,
    'status' => array(),
    'mday' => array_fill(1, 31, 0), 'wday' => array_fill(0, 7, 0), 'hour' => array_fill(0, 24, 0),
    'first' => array_fill_keys(array_values($first_buckets), 0), 'first_n' => 0, 'first_min' => 0,
    'solve' => array_fill_keys(array_values($solve_buckets), 0), 'solve_n' => 0, 'solve_min' => 0,
    'replies' => 0, 'answered' => 0, 'resolved_no_reply' => 0, 'resolved_replies' => 0, 'resolved_answered' => 0, 'resolved_answered_replies' => 0,
    'rb_all' => array_fill_keys(array_values($reply_buckets), 0),
    'rb_res' => array_fill_keys(array_values($reply_buckets), 0),
    'rb_one' => array_fill_keys(array_values($reply_buckets), 0),
);

function ut_st_bucket($value, $buckets)
{
    foreach ($buckets as $limit => $key)
    {
        if ($value <= $limit)
        {
            return $key;
        }
    }
    return end($buckets);
}

while ($t = hesk_dbFetchAssoc($res))
{
    $d['total']++;
    $created = strtotime($t['dt']);
    $resolved = $t['status'] == 3;
    $sr = intval($t['staffreplies']);

    $d['mday'][intval(date('j', $created))]++;
    $d['wday'][intval(date('w', $created))]++;
    $d['hour'][intval(date('G', $created))]++;

    if ($resolved)
    {
        $d['resolved']++;
    }
    else
    {
        $d['open']++;
        $d[$t['owner'] ? 'open_assigned' : 'open_unassigned']++;
        $d[$sr > 0 ? 'open_answered' : 'open_unanswered']++;
        $d['status'][$t['status']] = isset($d['status'][$t['status']]) ? $d['status'][$t['status']] + 1 : 1;
    }

    // Time to first staff reply
    if ($sr > 0 && $t['firstreply'])
    {
        $min = max(0, (strtotime($t['firstreply']) - $created) / 60);
        $d['first'][ut_st_bucket($min, $first_buckets)]++;
        $d['first_n']++;
        $d['first_min'] += $min;
    }

    // Time from submitting to resolving
    if ($resolved && $t['closedat'])
    {
        $min = max(0, (strtotime($t['closedat']) - $created) / 60);
        $d['solve'][ut_st_bucket($min, $solve_buckets)]++;
        $d['solve_n']++;
        $d['solve_min'] += $min;
    }

    // Staff replies
    $d['replies'] += $sr;
    $rb = ut_st_bucket($sr, $reply_buckets);
    $d['rb_all'][$rb]++;
    if ($sr > 0)
    {
        $d['answered']++;
        $d['rb_one'][$rb]++;
    }
    if ($resolved)
    {
        $d['rb_res'][$rb]++;
        $d['resolved_replies'] += $sr;
        if ($sr == 0)
        {
            $d['resolved_no_reply']++;
        }
        else
        {
            $d['resolved_answered']++;
            $d['resolved_answered_replies'] += $sr;
        }
    }
}

/*** Charts (inline SVG, no external library) ***/

$ut_palette = array('#662D8E', '#38bc7d', '#f5b700', '#e64342', '#2f80ed', '#9b51e0', '#f2994a', '#56ccf2', '#b4bac6', '#219653', '#eb5757', '#828282');

function ut_st_pct($part, $total, $decimals = 1)
{
    return $total ? round($part / $total * 100, $decimals) : 0;
}

function ut_st_num($n, $decimals = 0)
{
    return number_format($n, $decimals, ',', '.');
}

// Duration in minutes as "14h 42min" (+ "about 2 days" when long)
function ut_st_duration($minutes)
{
    global $S;
    if ($minutes === null)
    {
        return $S['na'];
    }
    $h = floor($minutes / 60);
    $m = round($minutes - $h * 60);
    $txt = ($h ? ut_st_num($h).'h ' : '').$m.'min';
    if ($minutes >= 1440)
    {
        $days = $minutes / 1440;
        $txt .= ' <small>('.sprintf($S['about'], ut_st_num($days, $days < 10 ? 1 : 0).' '.($days >= 2 ? 'dias' : 'dia')).')</small>';
    }
    return $txt;
}

/**
 * Donut chart with legend. $items = array(array(label, value, color), ...)
 */
function ut_st_donut($title, $items)
{
    $total = 0;
    foreach ($items as $it)
    {
        $total += $it[1];
    }
    $r = 15.915; // circumference = 100
    $svg = '<svg viewBox="0 0 42 42" class="ut-st-donut__svg" role="img" aria-label="'.hesk_htmlspecialchars(strip_tags($title)).'">';
    $svg .= '<circle cx="21" cy="21" r="'.$r.'" fill="none" stroke="#eef0f4" stroke-width="6"></circle>';
    $offset = 25;
    foreach ($items as $it)
    {
        $p = ut_st_pct($it[1], $total, 3);
        if ($p <= 0)
        {
            continue;
        }
        $svg .= '<circle cx="21" cy="21" r="'.$r.'" fill="none" stroke="'.$it[2].'" stroke-width="6" stroke-dasharray="'.$p.' '.(100 - $p).'" stroke-dashoffset="'.$offset.'"><title>'.hesk_htmlspecialchars(strip_tags($it[0])).': '.$it[1].' ('.ut_st_num(ut_st_pct($it[1], $total), 1).'%)</title></circle>';
        $offset -= $p;
    }
    $svg .= '<text x="21" y="21.5" class="ut-st-donut__total">'.ut_st_num($total).'</text>';
    $svg .= '<text x="21" y="26" class="ut-st-donut__label">'.$GLOBALS['hesklang']['ut_st_tickets'].'</text>';
    $svg .= '</svg>';

    $legend = '<ul class="ut-st-legend">';
    foreach ($items as $it)
    {
        $legend .= '<li><i style="background:'.$it[2].'"></i><span title="'.hesk_htmlspecialchars(strip_tags($it[0])).'">'.$it[0].'</span><b>'.ut_st_num($it[1]).'</b><em>'.ut_st_num(ut_st_pct($it[1], $total), 1).'%</em></li>';
    }
    $legend .= '</ul>';

    return '<div class="ut-st-chart ut-st-donut"><h3>'.$title.'</h3><div class="ut-st-donut__body">'.$svg.$legend.'</div></div>';
}

/**
 * Vertical bar chart. $bars = array(label => value). $opts: percent (values are %), avg (append average bar), colors (per bar)
 */
function ut_st_bars($title, $bars, $xlabel, $ylabel, $opts = array())
{
    $values = array_values($bars);
    $labels = array_keys($bars);
    if ( ! empty($opts['avg']) && count($values))
    {
        $values[] = round(array_sum($values) / count($values), 1);
        $labels[] = $GLOBALS['S']['average'];
    }
    $n = count($values);
    $max = max(1, max($values));
    if ( ! empty($opts['percent']))
    {
        $max = 100;
    }

    // Drawing size matches the space the chart takes on the page, so texts keep the same size
    $w = isset($opts['w']) ? $opts['w'] : 760;
    $h = isset($opts['h']) ? $opts['h'] : 240;
    $left = 44; $bottom = 44; $top = 22;
    $plot_w = $w - $left - 8;
    $plot_h = $h - $bottom - $top;
    $slot = $plot_w / max(1, $n);
    $bw = max(4, min(46, $slot * 0.62));

    $svg = '<svg viewBox="0 0 '.$w.' '.$h.'" class="ut-st-bars__svg" role="img" aria-label="'.hesk_htmlspecialchars(strip_tags($title)).'">';
    // grid and y axis
    for ($i = 0; $i <= 4; $i++)
    {
        $y = $top + $plot_h - $plot_h * $i / 4;
        $val = $max * $i / 4;
        $svg .= '<line x1="'.$left.'" x2="'.($w - 8).'" y1="'.$y.'" y2="'.$y.'" class="ut-st-grid"></line>';
        $svg .= '<text x="'.($left - 6).'" y="'.($y + 4).'" class="ut-st-axis" text-anchor="end">'.(! empty($opts['percent']) ? round($val).'%' : ut_st_num($val, $max < 8 && $val != round($val) ? 1 : 0)).'</text>';
    }
    foreach ($values as $i => $v)
    {
        $bh = $max ? $plot_h * $v / $max : 0;
        $x = $left + $slot * $i + ($slot - $bw) / 2;
        $y = $top + $plot_h - $bh;
        $is_avg = ! empty($opts['avg']) && $i == $n - 1;
        $color = $is_avg ? '#f5b700' : (isset($opts['colors'][$i]) ? $opts['colors'][$i] : '#662D8E');
        $shown = ! empty($opts['percent']) ? ut_st_num($v, 1).'%' : ut_st_num($v, $v == round($v) ? 0 : 1);
        $svg .= '<rect x="'.round($x, 2).'" y="'.round($y, 2).'" width="'.round($bw, 2).'" height="'.round(max(0, $bh), 2).'" rx="3" fill="'.$color.'"><title>'.hesk_htmlspecialchars($labels[$i]).': '.$shown.'</title></rect>';
        if ($n <= 24 || $v)
        {
            $svg .= '<text x="'.round($x + $bw / 2, 2).'" y="'.round($y - 5, 2).'" class="ut-st-value" text-anchor="middle">'.$shown.'</text>';
        }
        $svg .= '<text x="'.round($x + $bw / 2, 2).'" y="'.($top + $plot_h + 16).'" class="ut-st-axis" text-anchor="middle">'.hesk_htmlspecialchars($labels[$i]).'</text>';
    }
    $svg .= '<text x="'.($left + $plot_w / 2).'" y="'.($h - 6).'" class="ut-st-axis-title" text-anchor="middle">'.hesk_htmlspecialchars($xlabel).'</text>';
    $svg .= '<text x="12" y="'.($top + $plot_h / 2).'" class="ut-st-axis-title" text-anchor="middle" transform="rotate(-90 12 '.($top + $plot_h / 2).')">'.hesk_htmlspecialchars($ylabel).'</text>';
    $svg .= '</svg>';

    return '<div class="ut-st-chart ut-st-bars"><h3>'.$title.'</h3>'.$svg.'</div>';
}

// Green (fast) to red (slow) for time distributions
function ut_st_heat($n)
{
    $scale = array('#27ae60', '#6fcf97', '#a3d977', '#cfe36b', '#f2c94c', '#f2994a', '#eb7a3c', '#e64342', '#c0392b');
    $out = array();
    for ($i = 0; $i < $n; $i++)
    {
        $out[] = $scale[min(count($scale) - 1, (int) round($i * (count($scale) - 1) / max(1, $n - 1)))];
    }
    return $out;
}

function ut_st_distribution($buckets, $total)
{
    global $S;
    $pct = array();
    $cum = array();
    $acc = 0;
    foreach ($buckets as $key => $count)
    {
        $acc += $count;
        $pct[$S[$key]] = ut_st_pct($count, $total);
        $cum[$S[$key]] = ut_st_pct($acc, $total);
    }
    return array($pct, $cum);
}

// Status colors: default statuses use CSS classes, custom ones store their own color
function ut_st_status_color($id, $i)
{
    global $hesk_settings, $ut_palette;
    $classes = array('open' => '#e64342', 'waitingreply' => '#f2994a', 'replied' => '#2f80ed', 'resolved' => '#27ae60', 'inprogress' => '#9b51e0', 'onhold' => '#828282');
    $st = isset($hesk_settings['statuses'][$id]) ? $hesk_settings['statuses'][$id] : array();
    if (isset($st['class']) && isset($classes[$st['class']]))
    {
        return $classes[$st['class']];
    }
    if ( ! empty($st['color']) && $st['color'] !== '#')
    {
        return $st['color'];
    }
    return $ut_palette[$i % count($ut_palette)];
}

/*** Page ***/

require_once(HESK_PATH . 'inc/header.inc.php');
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

$period_txt = $date_from === '' && $date_to === ''
    ? $hesklang['ut_st_pall']
    : sprintf($hesklang['ut_st_period_txt'], $date_from !== '' ? hesk_translate_date_string(date($hesk_settings['format_date'], strtotime($date_from))) : '…', $date_to !== '' ? hesk_translate_date_string(date($hesk_settings['format_date'], strtotime($date_to))) : '…');
?>
<div class="main__content ut-rep ut-st">
    <div class="ut-rep__wrap">

        <div class="ut-rep__head ut-rep__card">
            <span class="ut-rep__head-icon">
                <svg class="icon icon-reports">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-reports"></use>
                </svg>
            </span>
            <div class="ut-st__head-text">
                <div class="ut-crumbs"><?php echo $hesklang['modules']; ?> / <?php echo $S['tab']; ?></div>
                <h1 class="ut-rep__title"><?php echo $S['tab']; ?></h1>
                <p class="ut-rep__muted"><?php echo $S['intro']; ?></p>
            </div>
            <button type="button" class="btn btn--blue-border ut-st__print" onclick="window.print()"><?php echo $hesklang['ut_rep_print']; ?></button>
        </div>

        <!-- Filters -->
        <form method="get" action="module_statistics.php" class="ut-rep__card ut-st__filters">
            <div class="ut-st__presets" role="group">
                <?php foreach ($presets as $key => $p): ?>
                    <a class="ut-team__role<?php echo $preset === $key ? ' is-active' : ''; ?>" href="module_statistics.php?p=<?php echo $key; ?><?php echo $cat ? '&amp;cat='.$cat : ''; ?><?php echo $owner ? '&amp;owner='.$owner : ''; ?>"><?php echo $p[0]; ?></a>
                <?php endforeach; ?>
            </div>
            <div class="ut-st__fields">
                <label><?php echo $hesklang['ut_rep_opened_from']; ?>
                    <input type="date" class="form-control" name="from" value="<?php echo $date_from; ?>">
                </label>
                <label><?php echo $hesklang['ut_rep_opened_to']; ?>
                    <input type="date" class="form-control" name="to" value="<?php echo $date_to; ?>">
                </label>
                <label><?php echo $hesklang['category']; ?>
                    <select name="cat" class="ut-rep-select">
                        <option value="0"><?php echo $hesklang['ut_st_all_cats']; ?> (<?php echo count($ut_cats); ?>)</option>
                        <?php foreach ($ut_cats as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $cat == $id ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($can_filter_owner): ?>
                <label><?php echo $hesklang['owner']; ?>
                    <select name="owner" class="ut-rep-select">
                        <option value="0"><?php echo $hesklang['ut_st_all_owners']; ?></option>
                        <option value="-1" <?php echo $owner == -1 ? 'selected' : ''; ?>><?php echo $hesklang['unas']; ?></option>
                        <?php foreach ($ut_staff as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $owner == $id ? 'selected' : ''; ?>><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <button type="submit" class="btn btn-full"><?php echo $hesklang['ut_rep_apply']; ?></button>
            </div>
            <p class="ut-rep__muted ut-st__period"><?php echo $hesklang['ut_st_period']; ?>: <b><?php echo $period_txt; ?></b>
                <?php if ($cat) { echo ' &middot; '.$ut_cats[$cat]; } ?>
                <?php if ($owner == -1) { echo ' &middot; '.$hesklang['unas']; } elseif ($owner > 0) { echo ' &middot; '.$ut_staff[$owner]; } ?>
            </p>
        </form>

        <?php if ( ! $d['total']): ?>
            <div class="ut-rep__card ut-st__empty"><?php echo $S['ntsp']; ?></div>
        <?php else: ?>

        <!-- Key numbers -->
        <div class="ut-st__kpis">
            <?php
            $kpis = array(
                array($hesklang['ut_st_k_total'], ut_st_num($d['total']), '', $hesklang['ut_st_h_total']),
                array($S['open'], ut_st_num($d['open']), ut_st_num(ut_st_pct($d['open'], $d['total'])).'%', $hesklang['ut_st_h_open']),
                array($S['resolved'], ut_st_num($d['resolved']), ut_st_num(ut_st_pct($d['resolved'], $d['total'])).'%', $hesklang['ut_st_h_resolved']),
                array($hesklang['ut_st_k_unas'], ut_st_num($d['open_unassigned']), $hesklang['ut_st_k_of_open'], $hesklang['ut_st_h_unas']),
                array($hesklang['ut_st_k_first'], ut_st_duration($d['first_n'] ? $d['first_min'] / $d['first_n'] : null), $hesklang['ut_st_k_avg'], sprintf($hesklang['ut_st_h_first'], ut_st_num($d['first_n']))),
                array($hesklang['ut_st_k_solve'], ut_st_duration($d['solve_n'] ? $d['solve_min'] / $d['solve_n'] : null), $hesklang['ut_st_k_avg'], sprintf($hesklang['ut_st_h_solve'], ut_st_num($d['solve_n']))),
            );
            $kpi_lists = array('total', 'open', 'resolved', 'unas', 'first', 'solve');
            foreach ($kpis as $i => $k): ?>
                <div class="ut-rep__card ut-st__kpi" tabindex="0" role="button" data-list="<?php echo $kpi_lists[$i]; ?>" aria-label="<?php echo hesk_htmlspecialchars(strip_tags($k[0].': '.$k[3].' '.$hesklang['ut_st_dd_hint'])); ?>">
                    <span><?php echo $k[0]; ?> <i class="ut-st__info" aria-hidden="true">i</i></span>
                    <div class="ut-st__tip" role="tooltip"><?php echo $k[3]; ?><span class="ut-st__tip-hint"><?php echo $hesklang['ut_st_dd_hint']; ?></span></div>
                    <strong><?php echo $k[1]; ?></strong>
                    <?php if ($k[2] !== ''): ?><em><?php echo $k[2]; ?></em><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Overview -->
        <section class="ut-rep__card">
            <h2 class="ut-section-label"><?php echo $hesklang['ut_st_overview']; ?></h2>
            <div class="ut-st__grid ut-st__grid--4">
                <?php
                echo ut_st_donut($S['pie_title_ro'], array(array($S['open'], $d['open'], '#e64342'), array($S['resolved'], $d['resolved'], '#27ae60')));
                echo ut_st_donut($S['pie_title_au'], array(array($S['unanswered'], $d['open_unanswered'], '#e64342'), array($S['answered'], $d['open_answered'], '#27ae60')));
                echo ut_st_donut($S['pie_title_as'], array(array($S['unas'], $d['open_unassigned'], '#e64342'), array($S['ass'], $d['open_assigned'], '#27ae60')));
                $items = array();
                $i = 0;
                arsort($d['status']);
                foreach ($d['status'] as $id => $count)
                {
                    $items[] = array(hesk_get_status_name($id), $count, ut_st_status_color($id, $i++));
                }
                echo ut_st_donut($S['pie_title_so'], $items);
                ?>
            </div>
        </section>

        <!-- Volume -->
        <section class="ut-rep__card">
            <h2 class="ut-section-label"><?php echo $hesklang['ut_st_volume']; ?></h2>
            <?php
            $wd_names = array($hesklang['d0'], $hesklang['d1'], $hesklang['d2'], $hesklang['d3'], $hesklang['d4'], $hesklang['d5'], $hesklang['d6']);
            $wd = array();
            foreach ($d['wday'] as $i => $v)
            {
                $wd[$wd_names[$i]] = $v;
            }
            $hours = array();
            foreach ($d['hour'] as $i => $v)
            {
                $hours[sprintf('%02d', $i)] = $v;
            }
            ?>
            <div class="ut-st__grid ut-st__grid--2">
                <?php echo ut_st_bars($S['chart_title_wd'], $wd, $S['axis8'], $S['axis7'], array('avg' => 1)); ?>
                <?php echo ut_st_bars($S['chart_title_hd'], $hours, $S['axis9'], $S['axis7'], array('avg' => 1)); ?>
            </div>
            <?php echo ut_st_bars($S['chart_title_md'], $d['mday'], $S['axis10'], $S['axis7'], array('avg' => 1, 'w' => 1540, 'h' => 250)); ?>
        </section>

        <!-- First reply -->
        <section class="ut-rep__card">
            <h2 class="ut-section-label"><?php echo $S['chart_title_tfr']; ?></h2>
            <p class="ut-st__fact"><?php echo $S['atfr']; ?> <b><?php echo ut_st_duration($d['first_n'] ? $d['first_min'] / $d['first_n'] : null); ?></b></p>
            <?php list($pct, $cum) = ut_st_distribution($d['first'], $d['first_n']); $heat = ut_st_heat(count($pct)); ?>
            <div class="ut-st__grid ut-st__grid--2">
                <?php echo ut_st_bars($S['chart_title_tfr'], $pct, $S['axis4'], $S['axis1'], array('percent' => 1, 'colors' => $heat)); ?>
                <?php echo ut_st_bars($S['chart_title_tfrc'], $cum, $S['axis4'], $S['axis1'], array('percent' => 1, 'colors' => $heat)); ?>
            </div>
        </section>

        <!-- Resolution -->
        <section class="ut-rep__card">
            <h2 class="ut-section-label"><?php echo $S['chart_title_ttr']; ?></h2>
            <p class="ut-st__fact"><?php echo $S['attr']; ?> <b><?php echo ut_st_duration($d['solve_n'] ? $d['solve_min'] / $d['solve_n'] : null); ?></b></p>
            <?php list($pct, $cum) = ut_st_distribution($d['solve'], $d['solve_n']); $heat = ut_st_heat(count($pct)); ?>
            <div class="ut-st__grid ut-st__grid--2">
                <?php echo ut_st_bars($S['chart_title_ttr'], $pct, $S['axis5'], $S['axis2'], array('percent' => 1, 'colors' => $heat)); ?>
                <?php echo ut_st_bars($S['chart_title_ttrc'], $cum, $S['axis5'], $S['axis2'], array('percent' => 1, 'colors' => $heat)); ?>
            </div>
        </section>

        <!-- Staff replies -->
        <section class="ut-rep__card">
            <h2 class="ut-section-label"><?php echo $S['chart_title_sr']; ?></h2>
            <ul class="ut-st__facts">
                <li><?php echo $S['tsr']; ?> <b><?php echo ut_st_num($d['replies']); ?></b></li>
                <li><?php echo $S['trept']; ?> <b><?php echo ut_st_num($d['answered']); ?></b></li>
                <li><?php echo $S['trnr']; ?> <b><?php echo ut_st_num($d['resolved_no_reply']); ?></b></li>
                <li><?php echo $S['tsrt']; ?> <b><?php echo ut_st_num($d['total'] ? $d['replies'] / $d['total'] : 0, 1); ?></b></li>
                <li><?php echo $S['tsro']; ?> <b><?php echo ut_st_num($d['answered'] ? $d['replies'] / $d['answered'] : 0, 1); ?></b></li>
                <li><?php echo $S['tsrc']; ?> <b><?php echo ut_st_num($d['resolved'] ? $d['resolved_replies'] / $d['resolved'] : 0, 1); ?></b></li>
                <li><?php echo $S['tsrr']; ?> <b><?php echo ut_st_num($d['resolved_answered'] ? $d['resolved_answered_replies'] / $d['resolved_answered'] : 0, 1); ?></b></li>
            </ul>
            <?php
            $rb = function ($buckets, $total) use ($S) {
                $out = array();
                foreach ($buckets as $key => $count)
                {
                    $out[$S[$key]] = ut_st_pct($count, $total);
                }
                return $out;
            };
            ?>
            <div class="ut-st__grid ut-st__grid--3">
                <?php echo ut_st_bars($S['chart_title_srt'], $rb($d['rb_all'], $d['total']), $S['axis6'], $S['axis3'], array('percent' => 1, 'w' => 500, 'h' => 250)); ?>
                <?php echo ut_st_bars($S['chart_title_srtr'], $rb($d['rb_res'], $d['resolved']), $S['axis6'], $S['axis3'], array('percent' => 1, 'w' => 500, 'h' => 250)); ?>
                <?php echo ut_st_bars($S['chart_title_srt1'], $rb(array_slice($d['rb_one'], 1, null, true), $d['answered']), $S['axis6'], $S['axis3'], array('percent' => 1, 'w' => 500, 'h' => 250)); ?>
            </div>
        </section>

        <p class="ut-rep__muted ut-st__note"><?php echo $hesklang['ut_st_note']; ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Tickets behind a key number -->
<div class="ut-rep-modal" id="utStList" hidden>
    <div class="ut-rep-modal__box ut-st-list__box" role="dialog" aria-modal="true" aria-labelledby="utStListTitle">
        <div class="ut-rep-modal__head">
            <div>
                <h2 id="utStListTitle"></h2>
                <p class="ut-rep__muted" id="utStListSub"></p>
            </div>
            <button type="button" class="ut-rep-modal__close" data-close title="<?php echo $hesklang['ut_rep_cancel']; ?>">
                <svg class="icon icon-close"><use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use></svg>
            </button>
        </div>
        <div class="ut-rep-modal__body" id="utStListBody"></div>
        <div class="ut-rep-modal__foot">
            <span class="ut-rep__muted" id="utStListRange"></span>
            <span class="ut-rep-modal__spacer"></span>
            <nav class="ut-team__pages" id="utStListPages"></nav>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('utStList');
    var body = document.getElementById('utStListBody');
    var pagesEl = document.getElementById('utStListPages');
    // Same filters as the page
    var filters = <?php echo json_encode(array_filter(array('p' => $preset, 'from' => $preset === '' ? $date_from : '', 'to' => $preset === '' ? $date_to : '', 'cat' => $cat ?: '', 'owner' => $owner ?: ''), 'strlen')); ?>;
    var current = null, request = 0;

    function open() {
        modal.hidden = false;
        document.body.classList.add('ut-rep-noscroll');
    }
    function close() {
        modal.hidden = true;
        document.body.classList.remove('ut-rep-noscroll');
        if (current) current.card.focus();
    }
    modal.addEventListener('click', function (e) {
        if (e.target === modal || e.target.closest('[data-close]')) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) close();
    });

    function pageButton(p, label, isCurrent, disabled) {
        var b = document.createElement('button');
        b.type = 'button';
        b.innerHTML = label;
        if (isCurrent) { b.className = 'is-current'; b.setAttribute('aria-current', 'page'); }
        if (disabled) b.disabled = true; else b.addEventListener('click', function () { load(p); });
        return b;
    }

    function renderPages(page, pages) {
        pagesEl.innerHTML = '';
        if (pages < 2) return;
        pagesEl.appendChild(pageButton(page - 1, '&lsaquo;', false, page === 1));
        var from = Math.max(1, page - 2), to = Math.min(pages, page + 2);
        // "…" only hides two or more pages; a single missing page is shown instead
        if (from === 3) from = 2;
        if (to === pages - 2) to = pages - 1;
        if (from > 1) {
            pagesEl.appendChild(pageButton(1, '1', false, false));
            if (from > 2) pagesEl.appendChild(Object.assign(document.createElement('span'), { textContent: '…' }));
        }
        for (var p = from; p <= to; p++) pagesEl.appendChild(pageButton(p, String(p), p === page, false));
        if (to < pages) {
            if (to < pages - 1) pagesEl.appendChild(Object.assign(document.createElement('span'), { textContent: '…' }));
            pagesEl.appendChild(pageButton(pages, String(pages), false, false));
        }
        pagesEl.appendChild(pageButton(page + 1, '&rsaquo;', false, page === pages));
    }

    function load(page) {
        var mine = ++request;
        body.classList.add('is-loading');
        var params = new URLSearchParams(filters);
        params.set('list', current.list);
        params.set('page', page);
        fetch('module_statistics.php?' + params.toString(), { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (data) {
                if (mine !== request) return; // an older page answered late
                document.getElementById('utStListTitle').textContent = data.title;
                document.getElementById('utStListRange').textContent = data.range;
                body.innerHTML = data.html;
                body.scrollTop = 0;
                renderPages(data.page, data.pages);
            })
            .catch(function () {
                if (mine !== request) return;
                body.innerHTML = '<p class="ut-rep__empty">' + <?php echo json_encode($hesklang['ut_st_dd_error']); ?> + '</p>';
            })
            .then(function () { body.classList.remove('is-loading'); });
    }

    function show(card) {
        current = { list: card.dataset.list, card: card };
        document.getElementById('utStListTitle').textContent = card.querySelector('span').textContent.trim();
        document.getElementById('utStListSub').textContent = <?php echo json_encode(strip_tags($hesklang['ut_st_period'].': '.$period_txt.($cat ? ' · '.$ut_cats[$cat] : '').($owner == -1 ? ' · '.$hesklang['unas'] : ($owner > 0 ? ' · '.$ut_staff[$owner] : '')))); ?>;
        document.getElementById('utStListRange').textContent = '';
        pagesEl.innerHTML = '';
        body.innerHTML = '<p class="ut-rep__empty">' + <?php echo json_encode($hesklang['ut_st_dd_loading']); ?> + '</p>';
        open();
        load(1);
    }

    document.querySelectorAll('.ut-st__kpi[data-list]').forEach(function (card) {
        card.addEventListener('click', function () { show(card); });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); show(card); }
        });
    });
})();
</script>
<?php
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();
