<?php
class HugeTextQuestion extends TextQuestion
{
    public function getAnswerHTML()
    {
        global $thissurvey;
        $clang =Yii::app()->lang;
        $extraclass ="";
        if ($thissurvey['nokeyboard']=='Y')
        {
            includeKeypad();
            $kpclass = "text-keypad";
            $extraclass .=" inputkeypad";
        }
        else
        {
            $kpclass = "";
        }

        $checkconditionFunction = "checkconditions";

        $aQuestionAttributes = $this->getAttributeValues();

        if (intval(trim($aQuestionAttributes['maximum_chars']))>0)
        {
            // Only maxlength attribute, use textarea[maxlength] jquery selector for textarea
            $maximum_chars= intval(trim($aQuestionAttributes['maximum_chars']));
            $maxlength= "maxlength='{$maximum_chars}' ";
            $extraclass .=" maxchars maxchars-".$maximum_chars;
        }
        else
        {
            $maxlength= "";
        }

        // --> START ENHANCEMENT - DISPLAY ROWS
        if (trim($aQuestionAttributes['display_rows'])!='')
        {
            $drows=$aQuestionAttributes['display_rows'];
        }
        else
        {
            $drows=30;
        }
        // <-- END ENHANCEMENT - DISPLAY ROWS

        // --> START ENHANCEMENT - TEXT INPUT WIDTH
        if (trim($aQuestionAttributes['text_input_width'])!='')
        {
            $tiwidth=$aQuestionAttributes['text_input_width'];
            $extraclass .=" inputwidth-".trim($aQuestionAttributes['text_input_width']);
        }
        else
        {
            $tiwidth=70;
        }
        // <-- END ENHANCEMENT - TEXT INPUT WIDTH

        // --> START NEW FEATURE - SAVE
        $answer = "<p class=\"question answer-item text-item {$extraclass}\"><label for='answer{$this->fieldname}' class='hide label'>{$clang->gT('Answer')}</label>";
        $answer .='<textarea class="textarea '.$kpclass.'" name="'.$this->fieldname.'" id="answer'.$this->fieldname.'" '
        .'rows="'.$drows.'" cols="'.$tiwidth.'" '.$maxlength.' onkeyup="'.$checkconditionFunction.'(this.value, this.name, this.type)" >';
        // --> END NEW FEATURE - SAVE

        if ($_SESSION['survey_'.$this->surveyid][$this->fieldname]) {$answer .= str_replace("\\", "", $_SESSION['survey_'.$this->surveyid][$this->fieldname]);}
        $answer .= "</textarea>\n";
        $answer .="</p>";
        if (trim($aQuestionAttributes['time_limit']) != '')
        {
            $answer .= return_timer_script($aQuestionAttributes, $this, "answer".$this->fieldname);
        }

        return $answer;
    }

    public function getDataEntry($idrow, &$fnames, $language)
    {
        return "\t<textarea rows='50' cols='70' name='{$this->fieldname}'>"
        .htmlspecialchars($idrow[$this->fieldname], ENT_QUOTES) . "</textarea>\n";
    }

    public function getDBField()
    {
        return 'text';
    }

    public function getDataEntryView($language)
    {
        $qidattributes = $this->getAttributeValues();
        if (trim($qidattributes['display_rows'])!='')
        {
            $drows=$qidattributes['display_rows'];
        } else {
            $drows = 70;
        }

        if (trim($qidattributes['text_input_width']) != '') {
            $tiwidth = $qidattributes['text_input_width'];
        } else {
            $tiwidth = 50;
        }

        if (isset($qidattributes['prefix']) && trim($qidattributes['prefix'][$language->getlangcode()]) != '') {
            $prefix = $qidattributes['prefix'][$language->getlangcode()];
        } else {
            $prefix = '';
        }

        if (isset($qidattributes['suffix']) && trim($qidattributes['suffix'][$language->getlangcode()]) != '') {
            $suffix = $qidattributes['suffix'][$language->getlangcode()];
        } else {
            $suffix = '';
        }
        return $prefix . "<textarea name='{$this->fieldname}' cols='{$tiwidth}' rows='{$drows}'></textarea>" . $suffix;
    }

    public function getPrintAnswers($language)
    {
        return printablesurvey::input_type_image('textarea', $this->getTypeHelp($language), '100%', 30);
    }

    public function getPrintPDF($language)
    {
        $output = array();
        for ($i = 0; $i < 20; $i++)
        {
            $output[] = "____________________";
        }
        return $output;
    }

    public function QueXMLAppendAnswers(&$question)
    {
        global $dom;
        $response = $dom->createElement("response");
        $response->setAttribute("varName", $this->surveyid . 'X' . $this->gid . 'X' . $this->id);
        $response->appendChild(QueXMLCreateFree("longtext",quexml_get_lengthth($this->id,"display_rows","80"),""));
        $question->appendChild($response);
    }

    public function availableAttributes($attr = false)
    {
        $attrs=array("display_rows","em_validation_q","em_validation_q_tip","em_validation_sq","em_validation_sq_tip","statistics_showgraph","statistics_graphtype","hide_tip","hidden","maximum_chars","page_break","text_input_width","time_limit","time_limit_action","time_limit_disable_next","time_limit_disable_prev","time_limit_countdown_message","time_limit_timer_style","time_limit_message_delay","time_limit_message","time_limit_message_style","time_limit_warning","time_limit_warning_display_time","time_limit_warning_message","time_limit_warning_style","time_limit_warning_2","time_limit_warning_2_display_time","time_limit_warning_2_message","time_limit_warning_2_style","random_group");
        return $attr?in_array($attr,$attrs):$attrs;
    }

    public function questionProperties($prop = false)
    {
        $clang=Yii::app()->lang;
        $props=array('description' => $clang->gT("Huge Free Text"),'group' => $clang->gT("Text questions"),'subquestions' => 0,'class' => 'text-huge','hasdefaultvalues' => 1,'assessable' => 0,'answerscales' => 0,'enum' => 0);
        return $prop?$props[$prop]:$props;
    }
}

?>
