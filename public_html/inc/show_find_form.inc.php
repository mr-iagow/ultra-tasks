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
?>

<!-- ** START SEARCH TICKETS FORM ** -->
<section class="ut-find">
    <form action="find_tickets.php" method="get" name="findby" id="findby" class="show_tickets form">
        <div class="ut-find__bar">
            <div class="ut-find__field search-options">
                <label><?php echo $hesklang['s_in']; ?></label>
                <select name="what">
                    <option value="trackid" <?php if ($what=='trackid') {echo 'selected="selected"';} ?> ><?php echo $hesklang['trackID']; ?></option>
                    <?php
                    if ($hesk_settings['sequential'])
                    {
                        ?>
                        <option value="seqid" <?php if ($what=='seqid') {echo 'selected="selected"';} ?> ><?php echo $hesklang['seqid']; ?></option>
                        <?php
                    }
                    ?>
                    <option value="name"    <?php if ($what=='name') {echo 'selected="selected"';} ?> ><?php echo $hesklang['name']; ?></option>
                    <option value="email"	<?php if ($what=='email') {echo 'selected="selected"';} ?> ><?php echo $hesklang['email']; ?></option>
                    <option value="subject" <?php if ($what=='subject') {echo 'selected="selected"';} ?> ><?php echo $hesklang['subject']; ?></option>
                    <option value="message" <?php if ($what=='message') {echo 'selected="selected"';} ?> ><?php echo $hesklang['message']; ?></option>
                    <?php
                    foreach ($hesk_settings['custom_fields'] as $k=>$v)
                    {
                        $selected = ($what == $k) ? 'selected="selected"' : '';
                        if ($v['use'])
                        {
                            $v['name'] = (hesk_mb_strlen($v['name']) > 30) ? hesk_mb_substr($v['name'],0,30) . '...' : $v['name'];
                            echo '<option value="'.$k.'" '.$selected.'>'.$v['name'].'</option>';
                        }
                    }
                    ?>
                    <option value="notes" <?php if ($what=='notes') {echo 'selected="selected"';} ?> ><?php echo $hesklang['notes']; ?></option>
                    <option value="ip" <?php if ($what=='ip') {echo 'selected="selected"';} ?> ><?php echo $hesklang['IP_addr']; ?></option>
                </select>
            </div>
            <div class="ut-find__field ut-find__query">
                <label for="find-q"><?php echo $hesklang['s_for']; ?></label>
                <input class="form-control" type="text" name="q" id="find-q" <?php if (isset($q)) {echo 'value="'.$q.'"';} ?>>
            </div>
            <div class="ut-find__actions">
                <button type="submit" class="btn btn-full" id="findticket"><?php echo $hesklang['find_ticket']; ?></button>
                <a class="btn btn--blue-border ut-find__toggle <?php echo $more2 ? 'is-open' : ''; ?>" id="moreoptions2" href="javascript:void(0)"
                   onclick="hesk_toggleLayerDisplay('divShow2');this.classList.toggle('is-open');document.findby.more2.value=(document.findby.more2.value=='1'?'0':'1');">
                    <?php echo $hesklang['ut_adv_filter']; ?>
                    <svg class="icon icon-chevron-down">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                    </svg>
                </a>
                <input type="hidden" name="more2" value="<?php echo $more2 ? 1 : 0 ; ?>">
            </div>
        </div>
        <div id="divShow2" class="ut-find__advanced" style="display:<?php echo $more2 ? 'block' : 'none' ; ?>">
        <div class="ut-find__grid">
            <div class="ut-find__field search-options">
                <label for="categoryfind"><?php echo $hesklang['category']; ?></label>
                <select id="categoryfind" name="category">
                    <option value="0" ><?php echo $hesklang['any_cat']; ?></option>
                    <?php echo $category_options; ?>
                </select>
            </div>
            <?php
            if ($can_view_ass_others || $can_view_ass_by)
            {
                ?>
                <div class="ut-find__field search-options">
                    <label for="ownerfind"><?php echo $hesklang['owner']; ?></label>
                    <select id="ownerfind" name="owner">
                        <option value="0" ><?php echo $hesklang['anyown']; ?></option>
                        <?php
                        foreach ($admins as $staff_id => $staff_name)
                        {
                            echo '<option value="'.$staff_id.'" '.($owner_input == $staff_id ? 'selected="selected"' : '').'>'.$staff_name.'</option>';
                        }
                        ?>
                    </select>
                </div>
                <?php
            }
            ?>
            <div class="ut-find__field">
                <label for="find-date"><?php echo $hesklang['date']; ?></label>
                <section class="param calendar">
                    <div class="calendar--button">
                        <button type="button">
                            <svg class="icon icon-calendar">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-calendar"></use>
                            </svg>
                        </button>
                        <input name="dt" id="find-date"
                            <?php if ($date_input) {echo 'value="'.$date_input.'"';} ?>
                               type="text" class="datepicker">
                    </div>
                    <div class="calendar--value" <?php echo ($date_input ? 'style="display: block"' : ''); ?>>
                        <span><?php echo $date_input; ?></span>
                        <i class="close">
                            <svg class="icon icon-close">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
                            </svg>
                        </i>
                    </div>
                </section>
            </div>
            <div class="ut-find__field">
                <label for="find-limit"><?php echo $hesklang['display']; ?></label>
                <div class="ut-find__inline">
                    <input type="text" name="limit" id="find-limit" value="<?php echo $maxresults; ?>" class="form-control">
                    <span><?php echo $hesklang['results_page']; ?></span>
                </div>
            </div>
            <div class="ut-find__field ut-find__include">
                <label>
                    <?php echo $hesklang['s_incl']; ?>
                    <a href="" id="selectAllFind" class="plain_link"><?php echo $hesklang['a_select']; ?></a>
                    <a href="" id="deselectAllFind" class="plain_link"><?php echo $hesklang['a_deselect']; ?></a>
                    <a href="" id="toggleAllFind" class="plain_link"><?php echo $hesklang['a_toggle']; ?></a>
                </label>
                <div class="checkbox-list">
                    <div class="checkbox-custom">
                        <input type="checkbox" id="find_show_my" name="s_my" value="1" <?php if ($s_my[2]) echo 'checked'; ?>>
                        <label for="find_show_my"><?php echo $hesklang['s_my']; ?></label>
                    </div>
                    <?php
                    if ($can_view_ass_others || $can_view_ass_by)
                    {
                        ?>
                        <div class="checkbox-custom">
                            <input type="checkbox" id="find_show_ot" name="s_ot" value="1" <?php if ($s_ot[2]) echo 'checked'; ?>>
                            <label for="find_show_ot"><?php echo $hesklang['s_ot']; ?></label>
                        </div>
                        <?php
                    }

                    if ($can_view_unassigned)
                    {
                        ?>
                        <div class="checkbox-custom">
                            <input type="checkbox" id="find_show_un" name="s_un" value="1" <?php if ($s_un[2]) echo 'checked'; ?>>
                            <label for="find_show_un"><?php echo $hesklang['s_un']; ?></label>
                        </div>
                        <?php
                    }
                    ?>
                    <div class="checkbox-custom">
                        <input type="checkbox" id="find_archive" name="archive" value="1" <?php if ($archive[2]) echo 'checked'; ?>>
                        <label for="find_archive"><?php echo $hesklang['disp_only_archived']; ?></label>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </form>
</section>

<script>
$("#selectAllFind").click(function(event) {
    event.preventDefault();
    $("[id^=find_][type=checkbox]").prop('checked', true);
});

$("#deselectAllFind").click(function(event) {
    event.preventDefault();
    $("[id^=find_][type=checkbox]").prop('checked', false);
});

$("#toggleAllFind").click(function(event) {
    event.preventDefault();
    $("[id^=find_][type=checkbox]").each(function() {
        this.checked = !this.checked;
    });
});
</script>

<!-- ** END SEARCH TICKETS FORM ** -->
