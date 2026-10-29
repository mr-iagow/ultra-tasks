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

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Check permissions for this feature */
hesk_checkPermission('can_man_cat');

// Possible priorities
$priorities = array(
	3 => array('id' => 3, 'value' => 'low', 'text' => $hesklang['low'],		'formatted' => $hesklang['low']),
	2 => array('id' => 2, 'value' => 'medium', 'text' => $hesklang['medium'],		'formatted' => $hesklang['medium']),
	1 => array('id' => 1, 'value' => 'high', 'text' => $hesklang['high'],		'formatted' => $hesklang['high']),
	0 => array('id' => 0, 'value' => 'critical', 'text' => $hesklang['critical'],	'formatted' => $hesklang['critical']),
);

/* What should we do? */
if ( $action = hesk_REQUEST('a') ) {
	if ( defined('HESK_DEMO') )  {hesk_process_messages($hesklang['ddemo'], 'manage_categories.php', 'NOTICE');}
	elseif ($action == 'remove')     {remove();}
	elseif ($action == 'order')      {order_cat();}
	elseif ($action == 'type')       {toggle_type();}
	elseif ($action == 'priority')   {change_priority();}
	elseif ($action == 'due-date')   {change_default_due_date();}
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

/* This will handle error, success and notice messages */
if (!hesk_SESSION('error')) {
    hesk_handle_messages();
}
?>
<div class="main__content categories ut-team ut-cats">
    <section class="categories__head ut-team__head">
        <div class="ut-team__title">
        <h2>
            <?php echo $hesklang['menu_cat']; ?>
            <div class="tooltype right out-close">
                <svg class="icon icon-info">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                </svg>
                <div class="tooltype__content">
                    <div class="tooltype__wrapper">
                        <?php echo $hesklang['cat_intro']; ?>
                    </div>
                </div>
            </div>
        </h2>
        <p class="ut-team__summary" id="utCatSummary"></p>
        </div>
        <a href="manage_category.php" class="btn btn-full" ripple="ripple">
            <?php echo $hesklang['add_cat']; ?>
        </a>
    </section>

    <!-- Search and filters (client side) -->
    <section class="ut-team__toolbar">
        <div class="ut-team__search">
            <svg class="icon icon-search">
                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-search"></use>
            </svg>
            <input type="text" class="form-control" id="utCatSearch" placeholder="<?php echo $hesklang['ut_cat_search']; ?>" autocomplete="off">
        </div>
        <div class="ut-cats__filters">
            <div class="ut-team__roles" role="group">
                <button type="button" class="ut-team__role is-active" data-type=""><?php echo $hesklang['ut_team_all']; ?> <span></span></button>
                <button type="button" class="ut-team__role" data-type="public"><?php echo $hesklang['ut_cat_publics']; ?> <span></span></button>
                <button type="button" class="ut-team__role" data-type="private"><?php echo $hesklang['ut_cat_privates']; ?> <span></span></button>
            </div>
            <select id="utCatPriority" class="ut-cats__select" aria-label="<?php echo $hesklang['priority']; ?>">
                <option value=""><?php echo $hesklang['ut_cat_any_priority']; ?></option>
                <?php foreach (array(0, 1, 2, 3) as $p): ?>
                    <option value="<?php echo $p; ?>"><?php echo trim($priorities[$p]['text'], ' *'); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="utCatDue" class="ut-cats__select" aria-label="<?php echo $hesklang['category_default_due_date']; ?>">
                <option value=""><?php echo $hesklang['ut_cat_any_due']; ?></option>
                <option value="1"><?php echo $hesklang['ut_cat_with_due']; ?></option>
                <option value="0"><?php echo $hesklang['ut_cat_no_due']; ?></option>
            </select>
        </div>
    </section>
    <div class="table-wrap">
        <div class="table">
            <table id="default-table" class="table sindu-table">
                <thead>
                <tr>
                    <th><?php echo $hesklang['id']; ?></th>
                    <th><?php echo $hesklang['cat_name']; ?></th>
                    <th>
                        <span><?php echo $hesklang['priority']; ?></span>
                        <?php if ($hesk_settings['cust_urgency']): ?>
                        <div class="tooltype right out-close">
                            <svg class="icon icon-info">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                            </svg>
                            <div class="tooltype__content">
                                <div class="tooltype__wrapper">
                                    <?php echo $hesklang['cat_pri_info'] . ' <a href="#">' . $hesklang['cpri'] . '</a>'; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </th>
                    <th>
                        <span><?php echo $hesklang['not']; ?></span>
                    </th>
                    <th>
                        <span class="tooltip" title="<?php echo hesk_htmlspecialchars($hesklang['cat_type']); ?>"><?php echo $hesklang['ut_cat_type']; ?></span>
                    </th>
                    <?php if ($hesk_settings['autoassign']): ?>
                    <th><?php echo $hesklang['aass']; ?></th>
                    <?php endif; ?>
                    <th class="due-date"><?php echo $hesklang['category_default_due_date'] ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php
                /* Get number of tickets per category */
                $tickets_all   = array();
                $tickets_total = 0;

                $res = hesk_dbQuery('SELECT COUNT(*) AS `cnt`, `category` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` GROUP BY `category`');
                while ($tmp = hesk_dbFetchAssoc($res))
                {
                    $tickets_all[$tmp['category']] = $tmp['cnt'];
                    $tickets_total += $tmp['cnt'];
                }

                /* Get list of categories */
                $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` ASC");
                $keyed_categories = array();
                $options='';

                $i=1;
                $j=0;
                $num = hesk_dbNumRows($res);

                while ($mycat=hesk_dbFetchAssoc($res)) {
                    $keyed_categories[$mycat['id']] = $mycat;
                }

                foreach ($keyed_categories as $id => $mycat) {
                    $j++;

                    $table_row = '';
                    if (isset($_SESSION['selcat2']) && $mycat['id'] == $_SESSION['selcat2'])
                    {
                        $table_row = 'class="ticket-new"';
                        unset($_SESSION['selcat2']);
                    }
                    else
                    {
                        $color = $i ? 'admin_white' : 'admin_gray';
                    }

                    $tmp   = $i ? 'White' : 'Blue';
                    $style = 'class="option'.$tmp.'OFF" onmouseover="this.className=\'option'.$tmp.'ON\'" onmouseout="this.className=\'option'.$tmp.'OFF\'"';
                    $i     = $i ? 0 : 1;

                    /* Number of tickets and graph width */
                    $all = isset($tickets_all[$mycat['id']]) ? $tickets_all[$mycat['id']] : 0;
                    $width_all = 0;
                    if ($tickets_total && $all)
                    {
                        $width_all  = round(($all / $tickets_total) * 100);
                    }

                    $options .= '<option value="'.$mycat['id'].'" ';
                    $options .= (isset($_SESSION['selcat']) && $mycat['id'] == $_SESSION['selcat']) ? ' selected="selected" ' : '';
                    $options .= '>'.$mycat['name'].'</option>';


                    ?>
                    <?php
                    $has_due = ! ($mycat['default_due_date_amount'] === null && $mycat['default_due_date_unit'] === null);
                    $row_data = 'data-type="'.($mycat['type'] == 0 ? 'public' : 'private').'" data-priority="'.intval($mycat['priority']).'" data-due="'.($has_due ? 1 : 0).'"'
                              . ' data-search="'.hesk_htmlspecialchars(hesk_mb_strtolower('#'.$mycat['id'].' '.hesk_html_entity_decode($mycat['name']))).'"';
                    ?>
                    <tr <?php echo $table_row; ?> <?php echo $row_data; ?> data-category-id="<?php echo $mycat['id']; ?>" data-autoassign-enabled="<?php echo $mycat['autoassign'] ?>" data-autoassign-config="<?php echo hesk_stripslashes($mycat['autoassign_config']); ?>">
                        <td><span class="ut-team__muted">#<?php echo $mycat['id']; ?></span></td>
                        <td>
                            <span class="category-name ut-team__name"><?php echo $mycat['name']; ?></span>
                        </td>
                        <td class="nowrap">
                            <span class="priority<?php echo $mycat['priority']; ?>">
                                <?php echo trim($priorities[$mycat['priority']]['text'], ' *'); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $tickets_url = 'show_tickets.php?category='.$mycat['id'].'&amp;s_all=1&amp;s_my=1&amp;s_ot=1&amp;s_un=1';
                            ?>
                            <a class="tooltip" data-ztt_vertical_offset="0" href="<?php echo $tickets_url; ?>" title="<?php echo $hesklang['list_tickets_cat']; ?>">
                                <b><?php echo $all; ?></b>
                                <span class="ut-team__muted">(<?php echo $width_all; ?>%)</span>
                            </a>
                            <span class="ut-cats__bar"><span style="width: <?php echo max($all ? 2 : 0, $width_all); ?>%"></span></span>
                        </td>
                        <td>
                            <span class="ut-team__badge <?php echo $mycat['type'] == 0 ? 'is-on' : 'is-off'; ?>"><?php echo $mycat['type'] == 0 ? $hesklang['ut_cat_public'] : $hesklang['ut_cat_private']; ?></span>
                        </td>
                        <?php if ($hesk_settings['autoassign']): ?>
                        <td class="assign">
                            <?php
                            if ($mycat['autoassign']) {
                                echo $hesklang['on'];
                            } else {
                                echo $hesklang['off'];
                            } ?>
                            <?php if (($display = hesk_getAutoAssignConfigDisplay($mycat['autoassign_config'])) !== '') { ?>
                                <div class="autoassign-config-display">
                                    (<?php echo $display; ?>)
                                </div>
                            <?php } ?>
                        </td>
                        <?php endif; ?>
                        <td class="due-date">
                            <?php if ($mycat['default_due_date_amount'] === null && $mycat['default_due_date_unit'] === null) {
                                echo $hesklang['none'];
                            } else {
                                echo $mycat['default_due_date_amount'] . ' ' . $hesklang["d_{$mycat['default_due_date_unit']}"];
                            } ?>
                        </td>
                        <td class="nowrap generate">
                            <a class="tooltip" href="javascript:"
                               title="<?php echo $hesklang['geco']; ?>"
                               <?php echo $mycat['type'] == 1 ? 'style="visibility: hidden"' : '' ?>
                               data-action="generate-link"
                               data-link="<?php echo htmlspecialchars($hesk_settings['hesk_url']) . '/index.php?a=add&catid=' . intval($mycat['id']); ?>">
                                <svg class="icon icon-export">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-export"></use>
                                </svg>
                            </a>
                            <a class="tooltip" href="manage_category.php?id=<?php echo $mycat['id']; ?>"
                               title="<?php echo $hesklang['edit']; ?>">
                                <svg class="icon icon-edit-ticket">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-edit-ticket"></use>
                                </svg>
                            </a>
                            <?php
                            if ($num > 1) {
                                if ($j == 1) {
                                    ?>
                                    <a href="#" class="ut-cats__move" style="visibility: hidden">
                                        <svg class="icon icon-chevron-up">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <a class="tooltip ut-cats__move" href="manage_categories.php?a=order&amp;catid=<?php echo $mycat['id']; ?>&amp;move=15&amp;token=<?php hesk_token_echo(); ?>"
                                       title="<?php echo $hesklang['move_dn']; ?>">
                                        <svg class="icon icon-chevron-down">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <?php
                                    echo'';
                                } elseif ($j == $num) {
                                    ?>
                                    <a class="tooltip ut-cats__move" href="manage_categories.php?a=order&amp;catid=<?php echo $mycat['id']; ?>&amp;move=-15&amp;token=<?php hesk_token_echo(); ?>"
                                       title="<?php echo $hesklang['move_up']; ?>">
                                        <svg class="icon icon-chevron-up">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <a href="#" class="ut-cats__move" style="visibility: hidden"
                                       title="<?php echo $hesklang['move_dn']; ?>">
                                        <svg class="icon icon-chevron-down">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <?php
                                } else {
                                    ?>
                                    <a class="tooltip ut-cats__move" href="manage_categories.php?a=order&amp;catid=<?php echo $mycat['id']; ?>&amp;move=-15&amp;token=<?php hesk_token_echo(); ?>"
                                       title="<?php echo $hesklang['move_up']; ?>">
                                        <svg class="icon icon-chevron-up">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <a class="tooltip ut-cats__move" href="manage_categories.php?a=order&amp;catid=<?php echo $mycat['id']; ?>&amp;move=15&amp;token=<?php hesk_token_echo(); ?>"
                                       title="<?php echo $hesklang['move_dn']; ?>">
                                        <svg class="icon icon-chevron-down">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <?php
                                }
                            }
                            ?>
                            <?php
                            if ($mycat['id'] != 1):
                                $modal_body = $hesklang['confirm_del_cat'];
                                if ($all > 0) {
                                    //-- $j - 2 because $j is 1-indexed and the first category can't be deleted
                                    $modal_body .= '<br><br>'.
                                        '<div><b>'.sprintf($hesklang['select_new_category'], $all).'</b></div>'.
                                        '<select id="targetCat'.($j - 2).'" name="modal-dropdown" onchange="hesk_updateDeleteCategoryUrl('.($j - 2).')">';

                                    foreach ($keyed_categories as $potential_transfer_id => $dropdown_category) {
                                        //-- Don't allow transferring to self
                                        if ($potential_transfer_id === $id) {
                                            continue;
                                        }

                                        $modal_body .= '<option value="'.$potential_transfer_id.'">'.$dropdown_category['name'].'</option>';
                                    }
                                    $modal_body .= '</select>';
                                }
                                $modal_id = hesk_generate_delete_modal($hesklang['confirm_deletion'],
                                    $modal_body,
                                    'manage_categories.php?a=remove&catid='. $mycat['id'] .'&token='. hesk_token_echo(0).'&targetCategory=1');
                                ?>
                            <a class="tooltip delete" title="<?php echo $hesklang['delcat']; ?>" href="javascript:" data-modal="[data-modal-id='<?php echo $modal_id; ?>']">
                                <svg class="icon icon-delete">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-delete"></use>
                                </svg>
                            </a>
                            <?php
                            endif;
                            ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            <p class="ut-team__empty" id="utCatEmpty" hidden><?php echo $hesklang['ut_cat_empty']; ?></p>
        </div>
    </div>
    <div class="ut-team__footer">
        <span class="ut-team__range" id="utCatRange"></span>
        <nav class="ut-team__pages" id="utCatPages" aria-label="<?php echo $hesklang['ut_team_pages']; ?>"></nav>
    </div>
</div>
<script>
(function () {
    var PER_PAGE = 25;
    var table = document.getElementById('default-table');
    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-type]'));
    var search = document.getElementById('utCatSearch');
    var types = document.querySelectorAll('.ut-cats .ut-team__role');
    var prioritySel = document.getElementById('utCatPriority');
    var dueSel = document.getElementById('utCatDue');
    var empty = document.getElementById('utCatEmpty');
    var rangeEl = document.getElementById('utCatRange');
    var pagesEl = document.getElementById('utCatPages');
    var type = '';
    var page = 1;

    var count = { '': rows.length, 'public': 0, 'private': 0 };
    rows.forEach(function (r) { count[r.dataset.type]++; });
    types.forEach(function (b) { b.querySelector('span').textContent = count[b.dataset.type]; });
    document.getElementById('utCatSummary').textContent = <?php echo json_encode($hesklang['ut_cat_summary']); ?>
        .replace('%1', count['']).replace('%2', count['public']).replace('%3', count['private']);

    function filtered() {
        return search.value.trim() !== '' || type !== '' || prioritySel.value !== '' || dueSel.value !== '';
    }

    function matching() {
        var term = search.value.trim().toLowerCase();
        return rows.filter(function (r) {
            return (!type || r.dataset.type === type)
                && (prioritySel.value === '' || r.dataset.priority === prioritySel.value)
                && (dueSel.value === '' || r.dataset.due === dueSel.value)
                && (!term || r.dataset.search.indexOf(term) !== -1);
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
                document.querySelector('.ut-cats .ut-team__toolbar').scrollIntoView({ block: 'start', behavior: 'smooth' });
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
        // Moving up/down only makes sense in the full, unfiltered order
        table.classList.toggle('is-filtered', filtered());

        rangeEl.textContent = list.length
            ? <?php echo json_encode($hesklang['ut_cat_range']); ?>.replace('%1', first + 1).replace('%2', first + visible.length).replace('%3', list.length)
            : '';

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
    prioritySel.addEventListener('change', function () { page = 1; render(); });
    dueSel.addEventListener('change', function () { page = 1; render(); });
    types.forEach(function (b) {
        b.addEventListener('click', function () {
            types.forEach(function (x) { x.classList.remove('is-active'); });
            b.classList.add('is-active');
            type = b.dataset.type;
            page = 1;
            render();
        });
    });

    // After saving or moving a category, open the page where it is highlighted
    var highlighted = table.querySelector('tbody tr.ticket-new');
    if (highlighted) {
        page = Math.floor(rows.indexOf(highlighted) / PER_PAGE) + 1;
    }
    render();
})();
</script>
<div class="notification-flash green" data-type="link-generate-message">
    <i class="close">
        <svg class="icon icon-close">
            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
        </svg>
    </i>
    <div class="notification--title"><?php echo $hesklang['genl']; ?></div>
    <div class="notification--text"><?php echo $hesklang['genl2']; ?></div>
</div>
<?php
hesk_cleanSessionVars('error');
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/
function remove()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

    $_SERVER['PHP_SELF'] = 'manage_categories.php';

	$mycat = intval( hesk_GET('catid') ) or hesk_error($hesklang['no_cat_id']);
	if ($mycat == 1)
    {
    	hesk_process_messages($hesklang['cant_del_default_cat'],$_SERVER['PHP_SELF']);
    }

	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='".intval($mycat)."'");
	if (hesk_dbAffectedRows() != 1)
    {
    	hesk_error("$hesklang[int_error]: $hesklang[cat_not_found].");
    }

    $new_category = hesk_GET('targetCategory', 1);
    // Don't update resolved tickets "Last modified"
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `category`=".intval($new_category).", `lastchange`=`lastchange` WHERE `category`='".intval($mycat)."' AND `status` = '3'");
    // For unresolved tickets, update the "Last modified"
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `category`=".intval($new_category)." WHERE `category`='".intval($mycat)."'");

    hesk_process_messages($hesklang['cat_removed_db'],$_SERVER['PHP_SELF'],'SUCCESS');
} // End remove()


function order_cat()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$catid = intval( hesk_GET('catid') ) or hesk_error($hesklang['cat_move_id']);
	$_SESSION['selcat2'] = $catid;

	$cat_move=intval( hesk_GET('move') );

	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` SET `cat_order`=`cat_order`+".intval($cat_move)." WHERE `id`='".intval($catid)."'");
	if (hesk_dbAffectedRows() != 1)
    {
    	hesk_error("$hesklang[int_error]: $hesklang[cat_not_found].");
    }

	/* Update all category fields with new order */
	$res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` ASC");

	$i = 10;
	while ($mycat=hesk_dbFetchAssoc($res))
	{
	    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` SET `cat_order`=".intval($i)." WHERE `id`='".intval($mycat['id'])."'");
	    $i += 10;
	}

    header('Location: manage_categories.php');
    exit();
} // End order_cat()
?>
