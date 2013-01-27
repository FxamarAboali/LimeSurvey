<?php
class YNRadioArrayQuestion extends RadioArrayQuestion
{
    public function getAnswerHTML()
    {
        global $notanswered, $thissurvey;
        $extraclass ="";
        $clang = Yii::app()->lang;
        $caption=$clang->gT("An array with sub-question on each line. The answers are yes, no, uncertain and are contained in the table header. ");
        $checkconditionFunction = "checkconditions";

        $qquery = "SELECT other FROM {{questions}} WHERE qid=".$this->id." AND language='".$_SESSION['survey_'.$this->surveyid]['s_lang']."'";
        $qresult = dbExecuteAssoc($qquery); //Checked
        $qrow = $qresult->readAll();
        $other = isset($qrow['other']) ? $qrow['other'] : '';
        $aQuestionAttributes=$this->getAttributeValues();
        if (trim($aQuestionAttributes['answer_width'])!='')
        {
            $answerwidth=$aQuestionAttributes['answer_width'];
        }
        else
        {
            $answerwidth = 20;
        }
        $cellwidth  = 3; // number of columns
        if ($this->mandatory != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
        {
            ++$cellwidth; // add another column
            $caption.=$clang->gT("The last cell are for no answer. ");
        }
        $cellwidth = round((( 100 - $answerwidth ) / $cellwidth) , 1); // convert number of columns to percentage of table width

        $ansresult = $this->getChildren();
        $anscount = count($ansresult);
        $fn = 1;
        $answer = "\n<table class=\"question subquestions-list questions-list {$extraclass}\" summary=\"".str_replace('"','' ,strip_tags($this->text))." - a Yes/No/uncertain Likert scale array\">\n"
        . "<caption class=\"hide screenreader\">{$caption}</caption>\n"
        . "\t<colgroup class=\"col-responses\">\n"
        . "\n\t<col class=\"col-answers\" width=\"$answerwidth%\" />\n";
        $odd_even = '';
        for ($xc=1; $xc<=3; $xc++)
        {
            $odd_even = alternation($odd_even);
            $answer .= "<col class=\"$odd_even\" width=\"$cellwidth%\" />\n";
        }
        if ($this->mandatory != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
        {
            $odd_even = alternation($odd_even);
            $answer .= "<col class=\"col-no-answer $odd_even\" width=\"$cellwidth%\" />\n";
        }
        $answer .= "\t</colgroup>\n\n"
        . "\t<thead>\n<tr class=\"array1\">\n"
        . "\t<td>&nbsp;</td>\n"
        . "\t<th>".$clang->gT('Yes')."</th>\n"
        . "\t<th>".$clang->gT('Uncertain')."</th>\n"
        . "\t<th>".$clang->gT('No')."</th>\n";
        if ($this->mandatory != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
        {
            $answer .= "\t<th>".$clang->gT('No answer')."</th>\n";
        }
        $answer .= "</tr>\n\t</thead>";
        $answer_t_content = '<tbody>';
        if ($anscount==0)
        {
            $answer.="<tr>\t<th class=\"answertext\">".$clang->gT('Error: This question has no answers.')."</th>\n</tr>\n";
        }
        else
        {
            $trbc = '';
            foreach($ansresult as $ansrow)
            {
                $myfname = $this->fieldname.$ansrow['title'];
                $answertext = dTexts__run($ansrow['question']);
                /* Check if this item has not been answered: the 'notanswered' variable must be an array,
                containing a list of unanswered questions, the current question must be in the array,
                and there must be no answer available for the item in this session. */
                if ($this->mandatory=='Y' && (is_array($notanswered)) && (array_search($myfname, $notanswered) !== FALSE) && ($_SESSION['survey_'.$this->surveyid][$myfname] == '') ) {
                    $answertext = "<span class='errormandatory'>{$answertext}</span>";
                }
                $trbc = alternation($trbc , 'row');

                // Get array_filter stuff
                list($htmltbody2, $hiddenfield)=return_array_filter_strings($this, $aQuestionAttributes, $thissurvey, $ansrow, $myfname, $trbc, $myfname,"tr","$trbc answers-list radio-list");

                $answer_t_content .= $htmltbody2;

                $answer_t_content .= "\t<th class=\"answertext\">\n"
                . $hiddenfield
                . "\t\t\t\t$answertext</th>\n"
                . "\t<td class=\"answer_cell_Y answer-item radio-item\">\n<label for=\"answer$myfname-Y\" class=\"hide\">{$clang->gT('Yes')}</label>\n"
                . "\t<input class=\"radio\" type=\"radio\" name=\"$myfname\" id=\"answer$myfname-Y\" value=\"Y\" ";
                if (isset($_SESSION['survey_'.$this->surveyid][$myfname]) && $_SESSION['survey_'.$this->surveyid][$myfname] == 'Y')
                {
                    $answer_t_content .= CHECKED;
                }
                // --> START NEW FEATURE - SAVE
                $answer_t_content .= " onclick=\"$checkconditionFunction(this.value, this.name, this.type)\" />\n\t</td>\n"
                . "\t<td class=\"answer_cell_U answer-item radio-item\">\n<label for=\"answer$myfname-U\" class=\"hide\">{$clang->gT('Uncertain')}</label>\n"
                . "<input class=\"radio\" type=\"radio\" name=\"$myfname\" id=\"answer$myfname-U\" value=\"U\" ";
                // --> END NEW FEATURE - SAVE

                if (isset($_SESSION['survey_'.$this->surveyid][$myfname]) && $_SESSION['survey_'.$this->surveyid][$myfname] == 'U')
                {
                    $answer_t_content .= CHECKED;
                }
                // --> START NEW FEATURE - SAVE
                $answer_t_content .= " onclick=\"$checkconditionFunction(this.value, this.name, this.type)\" />\n\t</td>\n"
                . "\t<td class=\"answer_cell_N answer-item radio-item\">\n<label for=\"answer$myfname-N\" class=\"hide\">{$clang->gT('No')}</label>\n"
                . "<input class=\"radio\" type=\"radio\" name=\"$myfname\" id=\"answer$myfname-N\" value=\"N\" ";
                // --> END NEW FEATURE - SAVE

                if (isset($_SESSION['survey_'.$this->surveyid][$myfname]) && $_SESSION['survey_'.$this->surveyid][$myfname] == 'N')
                {
                    $answer_t_content .= CHECKED;
                }
                // --> START NEW FEATURE - SAVE
                $answer_t_content .= " onclick=\"$checkconditionFunction(this.value, this.name, this.type)\" />\n"
                . "<input type=\"hidden\" name=\"java$myfname\" id=\"java$myfname\" value=\"";
                // --> END NEW FEATURE - SAVE
                if (isset($_SESSION['survey_'.$this->surveyid][$myfname]))
                {
                    $answer_t_content .= $_SESSION['survey_'.$this->surveyid][$myfname];
                }
                $answer_t_content .= "\" />\n\t</td>\n";

                if ($this->mandatory != 'Y' && SHOW_NO_ANSWER == 1)
                {
                    $answer_t_content .= "\t<td class=\"answer-item radio-item noanswer-item\">\n\t<label for=\"answer$myfname-\" class=\"hide\">{$clang->gT('No answer')}</label>\n"
                    . "\t<input class=\"radio\" type=\"radio\" name=\"$myfname\" id=\"answer$myfname-\" value=\"\" ";
                    if (!isset($_SESSION['survey_'.$this->surveyid][$myfname]) || $_SESSION['survey_'.$this->surveyid][$myfname] == '')
                    {
                        $answer_t_content .= CHECKED;
                    }
                    // --> START NEW FEATURE - SAVE
                    $answer_t_content .= " onclick=\"$checkconditionFunction(this.value, this.name, this.type)\" />\n\t</td>\n";
                    // --> END NEW FEATURE - SAVE
                }
                $answer_t_content .= "</tr>";
                $fn++;
            }
        }
        $answer .=  $answer_t_content . "\t\n</tbody>\n</table>\n";
        return $answer;
    }

    public function getDataEntry($idrow, &$fnames, $language)
    {
        $clang = Yii::app()->lang;
        $output = "<table>\n";
        $q = $this;
        while ($q->id == $this->id)
        {
            $output .= "\t<tr>\n"
            ."<td align='right'>{$q->sq}</td>\n"
            ."<td>\n"
            ."\t<input type='radio' class='radiobtn' name='{$q->fieldname}' value='Y'";
            if ($idrow[$q->fieldname] == "Y") {$output .= " checked";}
            $output .= " />".$clang->gT("Yes")."&nbsp;\n"
            ."\t<input type='radio' class='radiobtn' name='{$q->fieldname}' value='U'";
            if ($idrow[$q->fieldname] == "U") {$output .= " checked";}
            $output .= " />".$clang->gT("Uncertain")."&nbsp;\n"
            ."\t<input type='radio' class='radiobtn' name='{$q->fieldname}' value='N'";
            if ($idrow[$q->fieldname] == "N") {$output .= " checked";}
            $output .= " />".$clang->gT("No")."&nbsp;\n"
            ."</td>\n"
            ."\t</tr>\n";
            if(!$fname=next($fnames)) break;
            $q=$fname['q'];
        }
        prev($fnames);
        $output .= "</table>\n";
        return $output;
    }

    public function getExtendedAnswer($value, $language)
    {
        switch($value)
        {
            case "Y": return $language->gT("Yes")." [$value]";
            case "N": return $language->gT("No")." [$value]";
            case "U": return $language->gT("Uncertain")." [$value]";
            default: return $value;
        }
    }

    public function getFullAnswer($answerCode, $export, $survey)
    {
        switch ($answerCode)
        {
            case 'Y':
                return $export->translator->translate('Yes', $export->languageCode);
            case 'N':
                return $export->translator->translate('No', $export->languageCode);
            case 'U':
                return $export->translator->translate('Uncertain', $export->languageCode);
        }
    }

    public function getSPSSData($data, $iLength, $na, $qs)
    {
        if ($data == 'Y')
        {
            return $sq . "'1'" . $sq;
        } else if ($data == 'N'){
            return $sq . "'2'" . $sq;
        } else if ($data == 'U'){
            return $sq . "'3'" . $sq;
        } else {
            return $na;
        }
    }

    public function getSPSSAnswers()
    {
        $answers[] = array('code'=>1, 'value'=>$clang->gT('Yes'));
        $answers[] = array('code'=>2, 'value'=>$clang->gT('No'));
        $answers[] = array('code'=>3, 'value'=>$clang->gT('Uncertain'));
        return $answers;
    }

    public function getAnswerArray($em)
    {
        $clang = Yii::app()->lang;
        return array('Y' => $clang->gT("Yes"), 'N' => $clang->gT("No"), 'U' => $clang->gT("Uncertain"));
    }

    public function getVarAttributeValueNAOK($name, $default, $gseq, $qseq, $ansArray)
    {
        return LimeExpressionManager::GetVarAttribute($name,'code',$default,$gseq,$qseq);
    }

    public function getVarAttributeShown($name, $default, $gseq, $qseq, $ansArray)
    {
        $code = LimeExpressionManager::GetVarAttribute($name,'code',$default,$gseq,$qseq);

        if (is_null($ansArray))
        {
            return $default;
        }
        else
        {
            if (isset($ansArray[$code])) {
                $answer = $ansArray[$code];
            }
            else {
                $answer = $default;
            }
            return $answer;
        }
    }

    public function getShownJS()
    {
        return 'return (typeof attr.answers[value] === "undefined") ? "" : attr.answers[value];';
    }

    public function getValueJS()
    {
        return 'return value;';
    }

    public function getDataEntryView($language)
    {
        $meaquery = "SELECT title, question FROM {{questions}} WHERE parent_qid={$this->id} AND language='{$language->getlangcode()}' ORDER BY question_order";
        $mearesult = dbExecuteAssoc($meaquery)->readAll();
        $output = "<table>";
        foreach ($mearesult as $mearow)
        {
            $output .= "<tr>";
            $output .= "<td align='right'>{$mearow['question']}</td>";
            $output .= "<td>";
            $output .= "<select name='{$this->fieldname}{$mearow['title']}'>";
            $output .= "<option value=''>{$language->gT("Please choose")}..</option>";
            $output .= "<option value='Y'>{$language->gT("Yes")}..</option>";
            $output .= "<option value='U'>{$language->gT("Uncertain")}..</option>";
            $output .= "<option value='N'>{$language->gT("No")}..</option>";
            $output .= "</select>";
            $output .= "</td>";
            $output .= "</tr>";
        }
        $output .= "</table>";
        return $output;
    }

    public function getPrintAnswers($language)
    {
        $fieldname = $this->surveyid . 'X' . $this->gid . 'X' . $this->id;
        $condition = "parent_qid = '{$this->id}'  AND language= '{$language->getlangcode()}'";
        $mearesult= Questions::model()->getAllRecords( $condition, array('question_order'));

        $output = '
        <table>
            <thead>
                <tr>
                    <td>&nbsp;</td>
                    <th>'.$language->gT("Yes").(Yii::app()->getConfig('showsgqacode') ? " (Y)" : '').'</th>
                    <th>'.$language->gT("Uncertain").(Yii::app()->getConfig('showsgqacode') ? " (U)" : '').'</th>
                    <th>'.$language->gT("No").(Yii::app()->getConfig('showsgqacode') ? " (N)": '').'</th>
                </tr>
            </thead>
            <tbody>
        ';

        $j=0;
        $rowclass = 'array1';
        foreach ($mearesult->readAll() as $mearow)
        {
            $output .= "\t\t<tr class=\"$rowclass\">\n";

            $output .= "\t\t\t<th class=\"answertext\">{$mearow['question']}".(Yii::app()->getConfig('showsgqacode') ? " (".$fieldname.$mearow['title'].")" : '')."</th>\n";
            $output .= "\t\t\t<td>".printablesurvey::input_type_image('radio',$language->gT("Yes"))."</td>\n";
            $output .= "\t\t\t<td>".printablesurvey::input_type_image('radio',$language->gT("Uncertain"))."</td>\n";
            $output .= "\t\t\t<td>".printablesurvey::input_type_image('radio',$language->gT("No"))."</td>\n";
            $output .= "\t\t</tr>\n";
            $j++;
            $rowclass = alternation($rowclass,'row');
        }
        $output .= "\t</tbody>\n</table>\n";
        return $output;
    }

    public function getPrintPDF($language)
    {
        $condition = "parent_qid = '{$this->id}'  AND language= '{$language->getlangcode()}'";
        $mearesult= Questions::model()->getAllRecords( $condition, array('question_order'));

        $pdfoutput = array();
        $j=0;
        foreach ($mearesult->readAll() as $mearow)
        {
            $pdfoutput[$j]=array($mearow['question']," o ".$language->gT("Yes")," o ".$language->gT("Uncertain")," o ".$language->gT("No"));
            $j++;
        }
        return $pdfoutput;
    }

    public function getConditionAnswers()
    {
        $clang = Yii::app()->lang;
        $canswers = array();

        $fresult = Answers::model()->findAllByAttributes(array(
        'qid' => $this->id,
        "language" => Survey::model()->findByPk($this->surveyid)->language,
        'scale_id' => 0,
        ), array('order' => 'sortorder, code'));

        foreach ($fresult as $frow)
        {
            $canswers[]=array($this->surveyid.'X'.$this->gid.'X'.$this->id.$arows['title'], "Y", $clang->gT("Yes"));
            $canswers[]=array($this->surveyid.'X'.$this->gid.'X'.$this->id.$arows['title'], "U", $clang->gT("Uncertain"));
            $canswers[]=array($this->surveyid.'X'.$this->gid.'X'.$this->id.$arows['title'], "N", $clang->gT("No"));

            if ($this->mandatory != 'Y')
            {
                $canswers[]=array($this->surveyid.'X'.$this->gid.'X'.$this->id.$arows['title'], "", $clang->gT("No answer"));
            }
        }

        return $canswers;
    }

    public function QueXMLAppendAnswers(&$question)
    {
        global $dom, $quexmllang;
        $qlang = new limesurvey_lang($quexmllang);
        $response = $dom->createElement("response");
        $response->setAttribute("varName", $this->surveyid . 'X' . $this->gid . 'X' . $this->id);
        quexml_create_subQuestions($question,$this->id,$this->surveyid . 'X' . $this->gid . 'X' . $this->id);
        $response->appendChild(QueXMLFixedArray(array($qlang->gT("Yes") => 'Y',$qlang->gT("Uncertain") => 'U',$qlang->gT("No") => 'N')));
        $question->appendChild($response);
    }

    public function availableAttributes($attr = false)
    {
        $attrs=array("answer_width","array_filter","array_filter_exclude","array_filter_style","em_validation_q","em_validation_q_tip","exclude_all_others","statistics_showgraph","statistics_graphtype","hide_tip","hidden","max_answers","min_answers","page_break","public_statistics","random_order","parent_order","scale_export","random_group");
        return $attr?in_array($attr,$attrs):$attrs;
    }

    public function questionProperties($prop = false)
    {
        $clang=Yii::app()->lang;
        $props=array('description' => $clang->gT("Array (Yes/No/Uncertain)"),'group' => $clang->gT('Arrays'),'subquestions' => 1,'class' => 'array-yes-uncertain-no','hasdefaultvalues' => 0,'assessable' => 1,'answerscales' => 0,'enum' => 0);
        return $prop?$props[$prop]:$props;
    }
}
?>
