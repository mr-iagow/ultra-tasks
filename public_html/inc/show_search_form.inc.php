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

require_once(HESK_PATH . 'inc/search_form_setup.inc.php');

// Search form is normally printed above the ticket list; print it here if the list was not shown (e.g. search errors)
require_once(HESK_PATH . 'inc/show_find_form.inc.php');

#echo "SQL: $sql";
?>

<!-- ** START SHOW TICKET FORM ** -->
<h2 class="ut-section-title"><?php echo $hesklang['show_tickets']; ?></h2>
<div class="table-wrap ut-show">
    <form name="showt" action="show_tickets.php" method="get" class="show_tickets form">
        <div class="search-option">
            <div class="search-name">
                <?php echo $hesklang['status']; ?>
                <br><a href="" id="selectAllStatus" class="plain_link"><?php echo $hesklang['a_select']; ?></a>
                <br><a href="" id="deselectAllStatus" class="plain_link"><?php echo $hesklang['a_deselect']; ?></a>
                <br><a href="" id="toggleAllStatus" class="plain_link"><?php echo $hesklang['a_toggle']; ?></a>
            </div>
            <div class="search-options">
                <div class="checkbox-list">
                <?php
                hesk_get_status_checkboxes($status);
                ?>
                </div>
            </div>
        </div>
        <div id="topSubmit" style="display:<?php echo $more ? 'none' : 'block' ; ?>">
            <div style="display: flex">
                <button type="submit" class="btn btn-full"><?php echo $hesklang['show_tickets']; ?></button>
                <a class="btn btn--blue-border" href="javascript:void(0)" onclick="Javascript:hesk_toggleLayerDisplay('divShow');Javascript:hesk_toggleLayerDisplay('topSubmit');document.showt.more.value='1';"><?php echo $hesklang['mopt']; ?></a>
            </div>
        </div>
        <div id="divShow" style="display:<?php echo $more ? 'block' : 'none' ; ?>">
            <div class="search-option">
                <div class="search-name">
                    <?php echo $hesklang['priority']; ?>
                    <br><a href="" id="selectAllPriority" class="plain_link"><?php echo $hesklang['a_select']; ?></a>
                    <br><a href="" id="deselectAllPriority" class="plain_link"><?php echo $hesklang['a_deselect']; ?></a>
                    <br><a href="" id="toggleAllPriority" class="plain_link"><?php echo $hesklang['a_toggle']; ?></a>
                </div>
                <div class="search-options">
                    <div class="checkbox-list">
                        <div class="checkbox-custom">
                            <input type="checkbox" id="priority_0" name="p0" value="1" <?php if (isset($priority[0])) {echo 'checked';} ?>>
                            <label for="priority_0"><span class="priority0"><?php echo $hesklang['critical']; ?></span></label>
                        </div>
                        <div class="checkbox-custom">
                            <input type="checkbox" id="priority_1" name="p1" value="1" <?php if (isset($priority[1])) {echo 'checked';} ?>>
                            <label for="priority_1"><span class="priority1"><?php echo $hesklang['high']; ?></span></label>
                        </div>
                        <div class="checkbox-custom">
                            <input type="checkbox" id="priority_2" name="p2" value="1" <?php if (isset($priority[2])) {echo 'checked';} ?>>
                            <label for="priority_2"><span class="priority2"><?php echo $hesklang['medium']; ?></span></label>
                        </div>
                        <div class="checkbox-custom">
                            <input type="checkbox" id="priority_3" name="p3" value="1" <?php if (isset($priority[3])) {echo 'checked';} ?>>
                            <label for="priority_3"><span class="priority3"><?php echo $hesklang['low']; ?></span></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="search-option">
                <div class="search-name">
                    <?php echo $hesklang['show']; ?>
                    <br><a href="" id="selectAllShow" class="plain_link"><?php echo $hesklang['a_select']; ?></a>
                    <br><a href="" id="deselectAllShow" class="plain_link"><?php echo $hesklang['a_deselect']; ?></a>
                    <br><a href="" id="toggleAllShow" class="plain_link"><?php echo $hesklang['a_toggle']; ?></a>
                </div>
                <div class="search-options">
                    <div class="checkbox-list">
                        <div class="checkbox-custom">
                            <input type="checkbox" id="show_my" name="s_my" value="1" <?php if ($s_my[1]) echo 'checked'; ?>>
                            <label for="show_my"><?php echo $hesklang['s_my']; ?></label>
                        </div>
                        <?php
                        if ($can_view_unassigned)
                        {
                            ?>
                            <div class="checkbox-custom">
                                <input type="checkbox" id="show_un" name="s_un" value="1" <?php if ($s_un[1]) echo 'checked'; ?>>
                                <label for="show_un"><?php echo $hesklang['s_un']; ?></label>
                            </div>
                            <?php
                        }
                        ?>
                        <?php
                        if ($can_view_ass_others || $can_view_ass_by)
                        {
                            ?>
                            <div class="checkbox-custom">
                                <input type="checkbox" id="show_ot" name="s_ot" value="1" <?php if ($s_ot[1]) echo 'checked'; ?>>
                                <label for="show_ot"><?php echo $hesklang['s_ot']; ?></label>
                            </div>
                            <?php
                        }
                        ?>
                        <div class="checkbox-custom">
                            <input type="checkbox" id="show_archive" name="archive" value="1" <?php if ($archive[1]) echo 'checked'; ?>>
                            <label for="show_archive"><?php echo $hesklang['disp_only_archived']; ?></label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="search-option">
                <div class="search-name">
                    <?php echo $hesklang['sort_by']; ?>
                </div>
                <div class="search-options">
                    <div class="radio-list">
                        <?php
                        array_unshift($hesk_settings['ticket_list'], 'priority');
                        $hesk_settings['possible_ticket_list']['priority'] = $hesklang['priority'];

                        foreach ($hesk_settings['ticket_list'] as $key):
                            if (!key_exists($key, $hesk_settings['possible_ticket_list'])) {
                                continue;
                            }
                            ?>
                            <div class="radio-custom">
                                <input type="radio" id="sort<?php echo $key; ?>" name="sort" value="<?php echo $key; ?>"
                                    <?php if ($sort == $key) { echo 'checked'; } ?>>
                                <label for="sort<?php echo $key; ?>">
                                    <?php echo $hesk_settings['possible_ticket_list'][$key]; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="search-option">
                <div class="search-name">
                    <?php echo $hesklang['gb']; ?>
                </div>
                <div class="search-options">
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio" name="g" value="" id="g_" <?php if (!$group) {echo 'checked';} ?>>
                            <label for="g_">
                                <?php echo $hesklang['dg']; ?>
                            </label>
                        </div>
                        <?php
                        if ($can_view_unassigned || $can_view_ass_others || $can_view_ass_by)
                        {
                            ?>
                            <div class="radio-custom">
                                <input type="radio" name="g" value="owner" id="g_owner" <?php if ($group == 'owner') {echo 'checked';} ?>>
                                <label for="g_owner">
                                    <?php echo $hesklang['owner']; ?>
                                </label>
                            </div>
                            <?php
                        }
                        ?>
                        <div class="radio-custom">
                            <input type="radio" name="g" value="category" id="g_category" <?php if ($group == 'category') {echo 'checked';} ?>>
                            <label for="g_category">
                                <?php echo $hesklang['category']; ?>
                            </label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" name="g" value="priority" id="g_priority" <?php if ($group == 'priority') {echo 'checked';} ?>>
                            <label for="g_priority">
                                <?php echo $hesklang['priority']; ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="search-option">
                <div class="search-name">
                    <?php echo $hesklang['due_date']; ?>
                </div>
                <div class="search-options">
                    <div class="radio-list">
                        <div class="radio-custom" style="margin-top: 0px;">
                            <input type="radio" id="duedate_specific" name="duedate_option" value="specific" <?php if ($duedate_search_type === 'specific') echo 'checked'; ?>>
                            <label for="duedate_specific"><?php echo $hesklang['search_due_date_specific']; ?></label>
                            <section class="param calendar">
                                <div class="calendar--button">
                                    <button type="button" onclick="document.getElementById('duedate_specific').checked = true">
                                        <svg class="icon icon-calendar">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-calendar"></use>
                                        </svg>
                                    </button>
                                    <input name="duedate_specific_date" id="duedate_specific_date"
                                        <?php if ($duedate_input) {echo 'value="'.$duedate_input.'"';} ?>
                                           type="text" class="datepicker">
                                </div>
                                <div class="calendar--value" <?php echo ($duedate_input ? 'style="display: block"' : ''); ?>>
                                    <span><?php echo $duedate_input; ?></span>
                                    <i class="close">
                                        <svg class="icon icon-close">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                                        </svg>
                                    </i>
                                </div>
                            </section>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" id="duedate_range" name="duedate_option" value="range" <?php if ($duedate_search_type === 'range') echo 'checked'; ?>>
                            <label for="duedate_range"><?php echo $hesklang['search_due_date_range']; ?></label>
                            <input class="form-control" type="text" id="duedate_amount_value" name="duedate_amount_value" value="<?php echo $duedate_amount_value; ?>" style="width: 25%"
                                   onkeyup="document.getElementById('duedate_range').checked = true">
                            <label for="duedate_amount_value">&nbsp;</label>
                            <select name="duedate_amount_unit" onclick="document.getElementById('duedate_range').checked = true" onchange="document.getElementById('duedate_range').checked = true" style="margin-top:5px;margin-bottom:5px;">
                                <option value="day" <?php if ($duedate_amount_unit === 'day') echo 'selected'; ?>><?php echo $hesklang['d_day']; ?></option>
                                <option value="week" <?php if ($duedate_amount_unit === 'week') echo 'selected'; ?>><?php echo $hesklang['d_week']; ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="search-option">
                <div class="search-name">
                    <?php echo $hesklang['category']; ?>
                </div>
                <div class="search-options">
                    <select name="category">
                        <option value="0" ><?php echo $hesklang['any_cat']; ?></option>
                        <?php echo $category_options; ?>
                    </select>
                </div>
            </div>
            <div class="search-option">
                <div class="search-name">
                    <?php echo $hesklang['display']; ?>
                </div>
                <div class="search-options">
                    <input class="form-control" type="text" name="limit" value="<?php echo $maxresults; ?>" style="width: 25%">
                    <?php echo $hesklang['tickets_page']; ?>
                </div>
            </div>
            <div class="search-option">
                <div class="search-name">
                    <?php echo $hesklang['order']; ?>
                </div>
                <div class="search-options">
                    <div class="radio-list">
                        <div class="radio-custom">
                            <input type="radio" name="asc" value="1" id="g_asc1" <?php if ($asc) {echo 'checked';} ?>>
                            <label for="g_asc1">
                                <?php echo $hesklang['ascending']; ?>
                            </label>
                        </div>
                        <div class="radio-custom">
                            <input type="radio" name="asc" value="0" id="g_asc0" <?php if (!$asc) {echo 'checked';} ?>>
                            <label for="g_asc0">
                                <?php echo $hesklang['descending']; ?>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="search-option">
                <div class="search-name">
                    <?php echo $hesklang['opt']; ?>
                </div>
                <div class="search-options">
                    <div class="checkbox-list">
                        <div class="checkbox-custom">
                            <input type="checkbox" name="cot" id="g_cot" value="1" <?php if ($cot) {echo 'checked';} ?>>
                            <label for="g_cot"><?php echo $hesklang['cot']; ?></label>
                        </div>
                        <div class="checkbox-custom">
                            <input type="checkbox" name="def" value="1" id="g_def">
                            <label for="g_def"><?php echo $hesklang['def']; ?></label>&nbsp;
                            (<a href="admin_main.php?reset=1&amp;token=<?php echo hesk_token_echo(0); ?>"><?php echo $hesklang['redv']; ?></a>)
                        </div>
                    </div>
                </div>
            </div>
            <div id="bottomSubmit">
                <div style="display: flex">
                    <button type="submit" id="bottom-showtickets" class="btn btn-full"><?php echo $hesklang['show_tickets']; ?></button>
                    <a class="btn btn--blue-border" href="javascript:void(0)" onclick="hesk_toggleLayerDisplay('divShow');hesk_toggleLayerDisplay('topSubmit');document.showt.more.value='0';"><?php echo $hesklang['lopt']; ?></a>
                    <input type="hidden" name="more" value="<?php echo $more ? 1 : 0 ; ?>">
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// STATUS
$("#selectAllStatus").click(function(event) {
    event.preventDefault();
    $("[id^=status_][type=checkbox]").prop('checked', true);
});

$("#deselectAllStatus").click(function(event) {
    event.preventDefault();
    $("[id^=status_][type=checkbox]").prop('checked', false);
});

$("#toggleAllStatus").click(function(event) {
    event.preventDefault();
    $("[id^=status_][type=checkbox]").each(function() {
        this.checked = !this.checked;
    });
});

// PRIORITY
$("#selectAllPriority").click(function(event) {
    event.preventDefault();
    $("[id^=priority_][type=checkbox]").prop('checked', true);
});

$("#deselectAllPriority").click(function(event) {
    event.preventDefault();
    $("[id^=priority_][type=checkbox]").prop('checked', false);
});

$("#toggleAllPriority").click(function(event) {
    event.preventDefault();
    $("[id^=priority_][type=checkbox]").each(function() {
        this.checked = !this.checked;
    });
});

// SHOW
$("#selectAllShow").click(function(event) {
    event.preventDefault();
    $("[id^=show_][type=checkbox]").prop('checked', true);
});

$("#deselectAllShow").click(function(event) {
    event.preventDefault();
    $("[id^=show_][type=checkbox]").prop('checked', false);
});

$("#toggleAllShow").click(function(event) {
    event.preventDefault();
    $("[id^=show_][type=checkbox]").each(function() {
        this.checked = !this.checked;
    });
});
</script>

<!-- ** END SHOW TICKET FORM ** -->

