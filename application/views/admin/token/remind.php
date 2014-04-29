<?php echo PrepareEditorScript(true, $this); ?>
<div class='header ui-widget-header'>
    <?php eT("Send email reminder"); ?></div><br />

<?php if ($thissurvey['active'] != 'Y') { ?>
    <div class='messagebox ui-corner-all'><div class='warningheader'><?php eT('Warning!'); ?></div><?php eT("This survey is not yet activated and so your participants won't be able to fill out the survey."); ?></div>
<?php } ?>

<div id='tabs'>
    <ul>
        <?php
        foreach ($surveylangs as $language)
        {
            //GET SURVEY DETAILS
            echo '<li><a href="#tabpage_' . $language . '">' . getLanguageNameFromCode($language, false);
            if ($language == $baselang)
            {
                echo "(" . gT("Base language") . ")";
            }
            echo "</a></li>";
        }
        ?>
    </ul>

    <?php echo CHtml::form(array("admin/tokens/sa/email/action/remind/surveyid/{$surveyid}"), 'post', array('id'=>'sendreminder', 'class'=>'form30')); ?>
        <?php
        foreach ($surveylangs as $language)
        {
            //GET SURVEY DETAILS
            if (!$thissurvey[$language]['email_remind'])
            {
                $thissurvey[$language]['email_remind'] = str_replace("\n", "\r\n", gT("Dear {FIRSTNAME},\n\nRecently we invited you to participate in a survey.\n\nWe note that you have not yet completed the survey, and wish to remind you that the survey is still available should you wish to take part.\n\nThe survey is titled:\n\"{SURVEYNAME}\"\n\n\"{SURVEYDESCRIPTION}\"\n\nTo participate, please click on the link below.\n\nSincerely,\n\n{ADMINNAME} ({ADMINEMAIL})\n\n----------------------------------------------\nClick here to do the survey:\n{SURVEYURL}") . "\n\n" . gT("If you do not want to participate in this survey and don't want to receive any more invitations please click the following link:\n{OPTOUTURL}"));
            }
            echo "<div id='tabpage_{$language}'><ul>"
            . "<li><label for='from_$language' >" . gT("From") . ":</label>\n"
            . "<input type='text' size='50' name='from_$language' id='from_$language' value=\"".htmlspecialchars($thissurvey['adminname'],ENT_QUOTES,'UTF-8')." <".htmlspecialchars($thissurvey['adminemail'],ENT_QUOTES,'UTF-8').">\" /></li>\n"
            . "<li><label for='subject_$language' >" . gT("Subject") . ":</label>";

            $fieldsarray["{ADMINNAME}"] = $thissurvey['adminname'];
            $fieldsarray["{ADMINEMAIL}"] = $thissurvey['adminemail'];
            $fieldsarray["{SURVEYNAME}"] = $thissurvey[$language]['name'];
            $fieldsarray["{SURVEYDESCRIPTION}"] = $thissurvey[$language]['description'];
            $fieldsarray["{EXPIRY}"] = $thissurvey["expiry"];

            $subject = Replacefields($thissurvey[$language]['email_remind_subj'], $fieldsarray, false);
            $textarea = Replacefields($thissurvey[$language]['email_remind'], $fieldsarray, false);
            if ($ishtml !== true)
            {
                $textarea = str_replace(array('<x>', '</x>'), array(''), $textarea);
            }

            echo "<input type='text' size='83' id='subject_$language' name='subject_$language' value=\"$subject\" /></li><li>\n"
            . "<label for='message_$language'>" . gT("Message") . ":</label>\n"
            . "<div  class='htmleditor'>\n"
            . "<textarea name='message_$language' id='message_$language' rows='20' cols='80' >";
            echo htmlspecialchars($textarea);
            echo "</textarea>"
            . "</div>\n"
            . getEditor("email-rem", "message_$language", "[" . gT("Reminder Email:", "js") . "](" . $language . ")", $surveyid, '', '', "tokens")
            . "</li>\n"
            . "</ul></div>";
        }
        ?>
<ul>

    <?php
    if (count($tokenids)>0)
    { ?>
        <li>
            <label><?php eT("Send reminder to token ID(s):"); ?></label>
        <?php echo implode(", ", (array) $tokenids); ?></li>
    <?php } ?>
    <li><label for='bypassbademails'>
            <?php eT("Bypass token with failing email addresses"); ?>:</label>
        <select id='bypassbademails' name='bypassbademails'>
            <option value='Y'><?php eT("Yes"); ?></option>
            <option value='N'><?php eT("No"); ?></option>
        </select></li>
    <li><label for='minreminderdelay'>
<?php eT("Min days between reminders"); ?>:</label>
        <input type='text' value='' name='minreminderdelay' id='minreminderdelay' /></li>

    <li><label for='maxremindercount'>
<?php eT("Max reminders"); ?>:</label>
        <input type='text' value='' name='maxremindercount' id='maxremindercount' /></li>
</ul><p>
    <input type='submit' value='<?php eT("Send Reminders"); ?>' />
    <input type='hidden' name='ok' value='absolutely' />
    <input type='hidden' name='subaction' value='remind' />

    <?php if (!empty($tokenids)) { ?>
    <input type='hidden' name='tokenids' value='<?php echo implode('|', (array) $tokenids); ?>' />
    <?php } ?>

    </form>
</div>
