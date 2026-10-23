<?php
global $hesk_settings, $hesklang;
/**
 * @var string $trackingId
 * @var boolean $emailProvided
 * @var int $categoryId
 */

// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}

require_once(TEMPLATE_PATH . 'customer/util/alerts.php');

// Category 99 is the Whistleblowing Channel ("Canal de Denúncias")
$is_den = isset($categoryId) && $categoryId == 99;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title><?php echo $is_den ? $hesklang['ut_den_title'] : $hesk_settings['hesk_title']; ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=Edge" />
    <meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0" />
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo HESK_PATH; ?>img/favicon/info180x180.png" />
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo HESK_PATH; ?>img/favicon/info32x32.png" />
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo HESK_PATH; ?>img/favicon/info16x16.png" />
    <link rel="manifest" href="<?php echo HESK_PATH; ?>img/favicon/site.webmanifest" />
    <link rel="mask-icon" href="<?php echo HESK_PATH; ?>img/favicon/safari-pinned-tab.svg" color="#5bbad5" />
    <link rel="shortcut icon" href="<?php echo HESK_PATH; ?>img/favicon/info.ico.ico" />
    <meta name="msapplication-TileColor" content="#2d89ef" />
    <meta name="msapplication-config" content="<?php echo HESK_PATH; ?>img/favicon/browserconfig.xml" />
    <meta name="theme-color" content="#ffffff" />
    <meta name="format-detection" content="telephone=no" />
    <link rel="stylesheet" media="all" href="<?php echo TEMPLATE_PATH; ?>customer/css/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.css?<?php echo $hesk_settings['hesk_version']; ?>" />
    <?php if ($is_den): ?>
    <link rel="stylesheet" media="all" href="<?php echo TEMPLATE_PATH; ?>customer/css/ut-denuncia.css?<?php echo filemtime(TEMPLATE_PATH . 'customer/css/ut-denuncia.css'); ?>" />
    <?php endif; ?>
    <?php include(TEMPLATE_PATH . '../../head.txt'); ?>
</head>

<body class="cust-help<?php echo $is_den ? ' ut-den-page' : ''; ?>">
<?php include(TEMPLATE_PATH . '../../header.txt'); ?>
<div class="wrapper">
    <main class="main">
        <?php // The complaint channel has no top bar unless the language selector needs it ?>
        <?php if ( ! $is_den || $hesk_settings['can_sel_lang']): ?>
        <header class="header">
            <div class="contr">
                <div class="header__inner">
                    <?php if ( ! $is_den): ?>
                    <a href="<?php echo $hesk_settings['hesk_url']; ?>" class="header__logo">
                        <?php echo $hesk_settings['hesk_title']; ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($hesk_settings['can_sel_lang']): ?>
                        <div class="header__lang">
                            <form method="get" action="" style="margin:0;padding:0;border:0;white-space:nowrap;">
                                <div class="dropdown-select center out-close">
                                    <select name="language" onchange="this.form.submit()">
                                        <?php hesk_listLanguages(); ?>
                                    </select>
                                </div>
                                <?php foreach (hesk_getCurrentGetParameters() as $key => $value): ?>
                                    <input type="hidden" name="<?php echo hesk_htmlentities($key); ?>"
                                           value="<?php echo hesk_htmlentities($value); ?>">
                                <?php endforeach; ?>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        <?php endif; ?>
        <?php if ( ! $is_den): ?>
        <div class="breadcrumbs">
            <div class="contr">
                <div class="breadcrumbs__inner">
                    <a href="<?php echo $hesk_settings['site_url']; ?>">
                        <span><?php echo $hesk_settings['site_title']; ?></span>
                    </a>
                    <svg class="icon icon-chevron-right">
                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-chevron-right"></use>
                    </svg>
                    <a href="<?php echo $hesk_settings['hesk_url']; ?>">
                        <span><?php echo $hesk_settings['hesk_title']; ?></span>
                    </a>
                    <svg class="icon icon-chevron-right">
                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-chevron-right"></use>
                    </svg>
                    <div class="last"><?php echo $hesklang['ticket_submitted']; ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="main__content">
            <div class="contr">
                <?php if ($is_den): ?>
                <section class="ut-den-done">
                    <svg class="ut-den-shield" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5v6c0 5.25 3.4 10.15 8 11 4.6-.85 8-5.75 8-11V5l-8-3Zm-1.2 14.2-3.5-3.5 1.4-1.4 2.1 2.1 4.9-4.9 1.4 1.4-6.3 6.3Z"/></svg>
                    <p class="ut-den-hero__org"><?php echo $hesklang['ut_den_title']; ?></p>
                    <h1 class="ut-den-done__title"><?php echo $hesklang['ut_den_ok_t']; ?></h1>
                    <p class="ut-den-done__text"><?php echo $hesklang['ut_den_ok']; ?></p>
                    <div class="ut-den-protocol">
                        <span><?php echo $hesklang['ut_den_protocol']; ?></span>
                        <strong id="utDenProtocol"><?php echo $trackingId; ?></strong>
                        <button type="button" class="ut-den-copy" id="utDenCopy" data-done="<?php echo $hesklang['ut_den_copied']; ?>"><?php echo $hesklang['ut_den_copy']; ?></button>
                    </div>
                    <p class="ut-den-keep"><?php echo $hesklang['ut_den_keep']; ?></p>
                    <a class="btn btn-full" ripple="ripple" href="<?php echo $hesk_settings['hesk_url']; ?>/ticket.php?track=<?php echo $trackingId ?>">
                        <?php echo $hesklang['ut_den_follow']; ?>
                    </a>
                </section>
                <script>
                document.getElementById('utDenCopy').addEventListener('click', function () {
                    var btn = this, text = document.getElementById('utDenProtocol').textContent;
                    function done() { btn.textContent = btn.dataset.done; }
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(text).then(done);
                    } else {
                        var t = document.createElement('textarea');
                        t.value = text;
                        document.body.appendChild(t);
                        t.select();
                        document.execCommand('copy');
                        document.body.removeChild(t);
                        done();
                    }
                });
                </script>
                <?php else: ?>
                <div class="main__content notice-flash">
                    <div class="notification green">
                        <p><b><?php echo $hesklang['ticket_submitted']; ?></b></p>
                        <p>
                            <?php echo $hesklang['ticket_submitted_success']; ?>: <span class="font-weight-bold"><?php echo $trackingId; ?></span>
                            <br><br>
                            <?php
                            if (!$emailProvided) {
                                echo $hesklang['write_down'];
                            }
                            if ($emailProvided && $hesk_settings['notify_new'] && $hesk_settings['spam_notice']) {
                                echo $hesklang['spam_inbox'];
                            }
                            ?><br><br>
                            <a class="btn btn-full" ripple="ripple" href="<?php echo $hesk_settings['hesk_url']; ?>/ticket.php?track=<?php echo $trackingId ?>">
                                <?php echo $hesklang['view_your_ticket']; ?>
                            </a>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
<?php
/*******************************************************************************
The code below handles HESK licensing and must be included in the template.

Removing this code is a direct violation of the HESK End User License Agreement,
will void all support and may result in unexpected behavior.

To purchase a HESK license and support future HESK development please visit:
https://www.hesk.com/buy.php
*******************************************************************************/
$hesk_settings['hesk_license']('Qo8Zm9vdGVyIGNsYXNzPSJmb290ZXIiPg0KICAgIDxwIGNsY
XNzPSJ0ZXh0LWNlbnRlciI+UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3d3dy5oZXNrLmNvbSIgY
2xhc3M9ImxpbmsiPkhlbHAgRGVzayBTb2Z0d2FyZTwvYT4gPHNwYW4gY2xhc3M9ImZvbnQtd2VpZ2h0L
WJvbGQiPkhFU0s8L3NwYW4+PGJyPk1vcmUgSVQgZmlyZXBvd2VyPyBUcnkgPGEgaHJlZj0iaHR0cHM6L
y93d3cuc3lzYWlkLmNvbS8/dXRtX3NvdXJjZT1IZXNrJmFtcDt1dG1fbWVkaXVtPWNwYyZhbXA7dXRtX
2NhbXBhaWduPUhlc2tQcm9kdWN0X1RvX0hQIiBjbGFzcz0ibGluayI+U3lzQWlkPC9hPjwvcD4NCjwvZ
m9vdGVyPg0K',"\104", "a809404e0adf9823405ee0b536e5701fb7d3c969");
/*******************************************************************************
END LICENSE CODE
*******************************************************************************/
?>
    </main>
</div>
<?php include(TEMPLATE_PATH . '../../footer.txt'); ?>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/jquery-3.5.1.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/hesk_functions.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/svg4everybody.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/selectize.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
</body>
</html>
