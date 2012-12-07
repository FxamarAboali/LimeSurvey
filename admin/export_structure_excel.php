<?php
/*
 * LimeSurvey
 * Copyright (C) 2007 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
 * $Id: export_structure_xml.php 11607 2011-12-06 23:19:52Z tmswhite $
 */

include_once("login_check.php");

if (!isset($surveyid))
{
    $surveyid=returnglobal('sid');
}


if (!$surveyid)
{
    echo $htmlheader
    ."<br />\n"
    ."<table width='350' align='center' style='border: 1px solid #555555' cellpadding='1' cellspacing='0'>\n"
    ."\t<tr bgcolor='#555555'><td colspan='2' height='4'><font size='1' face='verdana' color='white'><strong>"
    .$clang->gT("Export Survey")."</strong></td></tr>\n"
    ."\t<tr><td align='center'>\n"
    ."<br /><strong><font color='red'>"
    .$clang->gT("Error")."</font></strong><br />\n"
    .$clang->gT("No SID has been provided. Cannot dump survey")."<br />\n"
    ."<br /><input type='submit' value='"
    .$clang->gT("Main Admin Screen")."' onclick=\"window.open('$scriptname', '_top')\">\n"
    ."\t</td></tr>\n"
    ."</table>\n"
    ."</body></html>\n";
    exit;
}

if (!isset($copyfunction))
{
    $fn = "limesurvey_survey_$surveyid.txt";
    header("Content-Type: text/tab-separated-values charset=UTF-8");
    header("Content-Disposition: attachment; filename=$fn");
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");    // Date in the past
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Pragma: public");                          // HTTP/1.0

    $data =& LimeExpressionManager::ExcelSurveyExport($surveyid);
    
    $lines = array();
    foreach($data as $row)
    {
        $lines[] = implode("\t",str_replace(array("\t","\n","\r"),array(" "," "," "),$row));
    }
    $output = implode("\n",$lines);
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo $output;
    exit;
}
?>
