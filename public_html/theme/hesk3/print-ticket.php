<?php
global $hesk_settings, $hesklang;
/**
 * @var array $tickets
 * @var boolean $showStaffOnlyFields
 */

// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $hesk_settings['hesk_title']; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $hesklang['ENCODING']; ?>">
    <style type="text/css">
        body, table, td, p {
            color: black;
            font-family: Verdana, Geneva, Arial, Helvetica, sans-serif;
            font-size: <?php echo $hesk_settings['print_font_size']; ?>px;
            word-wrap: break-word;
            word-break: break-word;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        hr {
            border: 0;
            color: #9e9e9e;
            background-color: #9e9e9e;
            height: 1px;
            width: 100%;
            text-align: left;
            margin: 20px 0;
        }

        .ticket-info {
            border: 1px solid #ddd;
            margin-bottom: 20px;
            padding: 10px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .reply-section {
            margin-top: 20px; 
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .reply-section .section-title {
            margin-bottom: 10px;
        }

        .signature-section {
            margin-top: 40px; 
            padding: 20px;
            border: 2px solid #ddd;
        }

        .signature-section table {
            border: none;
            width: 100%;
        }

        .signature-section td {
            border: none;
            padding: 10px;
            vertical-align: top;
        }

        .signature-line {
            border-top: 1px solid black;
            width: 100%;
            height: 40px; 
            display: inline-block;
        }

        .signature-label {
            display: inline-block;
            width: 50%;
            font-weight: bold;
        }
    </style>
</head>
<body onload="window.print()">
    <div style="text-align: left;">
        <img src="/logosinfo/infox2.PNG" alt="Logo da Empresa" style="max-width: 200px; height: auto;">
    </div>

    <?php foreach ($tickets as $ticket): ?>
        <div class="ticket-info">
            <div class="section-title"><?php echo $hesklang['ticket_details']; ?></div>
            <table border="0">
                <tr>
                    <td><?php echo $hesklang['subject']; ?>:</td>
                    <td><b><?php echo $ticket['subject']; ?></b></td>
                </tr>
                <?php if ($hesk_settings['sequential'] || $showStaffOnlyFields): ?>
                <tr>
                    <td><?php echo $hesklang['seqid']; ?>:</td>
                    <td><?php echo $ticket['id']; ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><?php echo $hesklang['trackID']; ?>:</td>
                    <td><?php echo $ticket['trackid']; ?></td>
                </tr>
                <tr>
                    <td><?php echo $hesklang['openedby']; ?>:</td>
                    <td><?php echo $ticket['openedby']; ?></td>
                </tr>
                <tr>
                    <td><?php echo $hesklang['aberto_por']; ?>:</td>
                    <td><?php echo $ticket['aberto_por']; ?></td> 
                </tr>
                <tr>
                    <td><?php echo $hesklang['ticket_status']; ?>:</td>
                    <td><?php echo $ticket['status']; ?></td>
                </tr>
                <tr>
                    <td><?php echo $hesklang['created_on']; ?>:</td>
                    <td><?php echo $ticket['dt']; ?></td>
                </tr>
                <tr>
                    <td><?php echo $hesklang['last_update']; ?>:</td>
                    <td><?php echo $ticket['lastchange']; ?></td>
                </tr>
                <?php if ($ticket['owner'] != ''): ?>
                    <tr>
                        <td><?php echo $hesklang['taso3']; ?></td>
                        <td><?php echo $ticket['owner']; ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td><?php echo $hesklang['last_replier']; ?>:</td>
                    <td><?php echo $ticket['repliername']; ?></td>
                </tr>
                <tr>
                    <td><?php echo $hesklang['category']; ?>:</td>
                    <td><?php echo $ticket['categoryName']; ?></td>
                </tr>
                <?php if ($showStaffOnlyFields): ?>
                    <tr>
                        <td><?php echo $hesklang['ts']; ?>:</td>
                        <td><?php echo $ticket['time_worked']; ?></td>
                    </tr>
                    <tr>
                        <td><?php echo $hesklang['due_date']; ?>:</td>
                        <td><?php echo $ticket['due_date']; ?></td>
                    </tr>
                    <tr>
                        <td><?php echo $hesklang['ip']; ?>:</td>
                        <td><?php echo $ticket['ip']; ?></td>
                    </tr>
                    <tr>
                        <td><?php echo $hesklang['email']; ?>:</td>
                        <td><?php echo $ticket['email']; ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td><?php echo $hesklang['name']; ?>:</td>
                    <td><?php echo $ticket['name']; ?></td>
                </tr>
                <?php foreach ($ticket['custom_fields'] as $customField): ?>
                    <tr>
                        <td><?php echo $customField['name']; ?></td>
                        <td><?php echo $customField['value']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php if (count($ticket['notes'])): ?>
                <?php foreach ($ticket['notes'] as $note): ?>
                    <hr>
                    <p><?php echo $hesklang['noteby']; ?> <b><?php echo ($note['name'] ? $note['name'] : $hesklang['e_udel']); ?></b> - <?php echo hesk_date($note['dt'], true); ?><br>
                    <?php echo strlen($note['message']) ? $note['message'] : '<i>no message</i>'; ?></p>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($ticket['message_html'] != ''): ?>
                <hr>
                <p><?php echo $ticket['message_html']; ?></p>
            <?php endif; ?>

            <?php if (count($ticket['replies'])): ?>
                <div class="reply-section">
                    <div class="section-title">Relatos</div>
                    <?php foreach ($ticket['replies'] as $reply): ?>
                        <hr>
                        <table border="0">
                        <table>
                        <table>
                        <tr>
                            <td><?php echo $hesklang['name']; ?>:</td>
                            <td><?php echo $reply['name']; ?></td>
                        </tr>
                        <tr>
                            <td><?php echo $hesklang['message']; ?>:</td>
                            <td><?php echo $reply['message_html']; ?></td>
                        </tr>
                        <tr>
                            <td><?php echo $hesklang['date']; ?>:</td>
                            <td><?php echo $reply['dt']; ?></td>
                        </tr>
                    </table>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="signature-section">
                <table border="0">
                    <tr>
                        <td class="signature-label">Assinatura do Responsável:</td>
                        <td><div class="signature-line"></div></td>
                    </tr>
                    <tr>
                        <td class="signature-label">Assinatura da Testemunha:</td>
                        <td><div class="signature-line"></div></td>
                    </tr>
                </table>
            </div>

            <div style="page-break-after: always;"><?php echo $hesklang['end_ticket']; ?></div>
        </div>
    <?php endforeach; ?>
</body>
</html>
