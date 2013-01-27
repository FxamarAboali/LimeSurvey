<?php
class RadioArrayQuestion extends ArrayQuestion
{
    public function getAnswerHTML()
    {
        global $thissurvey;
        global $notanswered;
        $repeatheadings = Yii::app()->getConfig("repeatheadings");
        $minrepeatheadings = Yii::app()->getConfig("minrepeatheadings");
        $extraclass ="";
        $caption="";// Just leave empty, are replaced after
        $clang = Yii::app()->lang;
        $checkconditionFunction = "checkconditions";
        $qquery = "SELECT other FROM {{questions}} WHERE qid={$this->id} AND language='".$_SESSION['survey_'.$this->surveyid]['s_lang']."'";
        $qresult = dbExecuteAssoc($qquery);     //Checked
        $qrow = $qresult->read(); $other = $qrow['other'];
        $lquery = "SELECT * FROM {{answers}} WHERE qid={$this->id} AND language='".$_SESSION['survey_'.$this->surveyid]['s_lang']."' and scale_id=0 ORDER BY sortorder, code";

        $aQuestionAttributes = $this->getAttributeValues();
        if (trim($aQuestionAttributes['answer_width'])!='')
        {
            $answerwidth=$aQuestionAttributes['answer_width'];
        }
        else
        {
            $answerwidth=20;
        }
        $columnswidth=100-$answerwidth;

        if ($aQuestionAttributes['use_dropdown'] == 1)
        {
            $useDropdownLayout = true;
            $extraclass .=" dropdown-list";
            $caption=$clang->gT("An array with sub-question on each line. You have to select your answer.");
        }
        else
        {
            $useDropdownLayout = false;
            $caption=$clang->gT("An array with sub-question on each line. The answers are contained in the table header. ");
        }
        if(ctype_digit(trim($aQuestionAttributes['repeat_headings'])) && trim($aQuestionAttributes['repeat_headings']!=""))
        {
            $repeatheadings = intval($aQuestionAttributes['repeat_headings']);
            $minrepeatheadings = 0;
        }
        $lresult = dbExecuteAssoc($lquery);   //Checked
        if ($useDropdownLayout === false && $lresult->count() > 0)
        {
            foreach ($lresult->readAll() as $lrow)
            {
                $labelans[]=$lrow['answer'];
                $labelcode[]=$lrow['code'];
            }

            $ansquery = "SELECT question FROM {{questions}} WHERE parent_qid={$this->id} AND question like '%|%' ";
            $ansresult = dbExecuteAssoc($ansquery);  //Checked
            if ($ansresult->count()>0) {$right_exists=true;$answerwidth=$answerwidth/2;} else {$right_exists=false;}
            // $right_exists is a flag to find out if there are any right hand answer parts. If there arent we can leave out the right td column
            $ansresult = $this->getChildren();
            $anscount = count($this->getChildren());
            $fn=1;

            $numrows = count($labelans);
            if ($right_exists)
            {
                ++$numrows;
                $caption.=$clang->gT("After answers, a cell give some information. ");
            }
            if ($this->mandatory != 'Y' && SHOW_NO_ANSWER == 1)
            {
                ++$numrows;
                $caption.=$clang->gT("The last cell are for no answer. ");
            }
            $cellwidth = round( ($columnswidth / $numrows ) , 1 );

            $answer_start = "\n<table class=\"question subquestions-list questions-list {$extraclass}\" >"
                          . "<caption class=\"hide screenreader\">{$caption}</caption>\n";
            
            $answer_head_line= "\t<td>&nbsp;</td>\n";
            foreach ($labelans as $ld)
            {
                $answer_head_line .= "\t<th>".$ld."</th>\n";
            }
            if ($right_exists) {$answer_head_line .= "\t<td>&nbsp;</td>\n";}
            if ($this->mandatory != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory and we can show "no answer"
            {
                $answer_head_line .= "\t<th>".$clang->gT('No answer')."</th>\n";
            }
            $answer_head = "\t<thead><tr>\n".$answer_head_line."</thead></tr>\n\t\n";

            $answer = '<tbody>';
            $trbc = '';

            foreach($ansresult as $ansrow)
            {
                if (isset($repeatheadings) && $repeatheadings > 0 && ($fn-1) > 0 && ($fn-1) % $repeatheadings == 0)
                {
                    if ( ($anscount - $fn + 1) >= $minrepeatheadings )
                    {
                        $answer .= "</tbody>\n<tbody>";// Close actual body and open another one
                        $answer .= "<tr class=\"repeat headings\">{$answer_head_line}</tr>";
                    }
                }
                $myfname = $this->fieldname.$ansrow['title'];
                $answertext = dTexts__run($ansrow['question']);
                $answertextsave=$answertext;
                if (strpos($answertext,'|'))
                {
                    $answertext=substr($answertext,0, strpos($answertext,'|'));
                }
                /* Check if this item has not been answered: the 'notanswered' variable must be an array,
                containing a list of unanswered questions, the current question must be in the array,
                and there must be no answer available for the item in this session. */

                if (strpos($answertext,'|')) {$answerwidth=$answerwidth/2;}

                if ($this->mandatory=='Y' && (is_array($notanswered)) && (array_search($myfname, $notanswered) !== FALSE) && ($_SESSION['survey_'.$this->surveyid][$myfname] == '') ) {
                    $answertext = '<span class="errormandatory">'.$answertext.'</span>';
                }
                // Get array_filter stuff
                //
                // TMSW - is this correct?
                $trbc = alternation($trbc , 'row');
                list($htmltbody2, $hiddenfield)=return_array_filter_strings($this, $aQuestionAttributes, $thissurvey, $ansrow, $myfname, $trbc, $myfname,"tr","$trbc answers-list radio-list");
                $fn++;
                $answer .= $htmltbody2;

                $answer .= "\t<th class=\"answertext\">\n$answertext"
                . $hiddenfield
                . "<input type=\"hidden\" name=\"java$myfname\" id=\"java$myfname\" value=\"";
                if (isset($_SESSION['survey_'.$this->surveyid][$myfname]))
                {
                    $answer .= $_SESSION['survey_'.$this->surveyid][$myfname];
                }
                $answer .= "\" />\n\t</th>\n";

                $thiskey=0;
                foreach ($labelcode as $ld)
                {
                    $answer .= "\t\t\t<td class=\"answer_cell_00$ld answer-item radio-item\">\n"
                    . "<label for=\"answer$myfname-$ld\" class=\"hide\">{$labelans[$thiskey]}</label>\n"
                    . "\t<input class=\"radio\" type=\"radio\" name=\"$myfname\" value=\"$ld\" id=\"answer$myfname-$ld\" ";
                    if (isset($_SESSION['survey_'.$this->surveyid][$myfname]) && $_SESSION['survey_'.$this->surveyid][$myfname] == $ld)
                    {
                        $answer .= CHECKED;
                    }
                    // --> START NEW FEATURE - SAVE
                    $answer .= " onclick=\"$checkconditionFunction(this.value, this.name, this.type)\" />\n"
                    . "</label>\n"
                    . "\t</td>\n";
                    // --> END NEW FEATURE - SAVE

                    $thiskey++;
                }
                if (strpos($answertextsave,'|'))
                {
                    $answertext=substr($answertextsave,strpos($answertextsave,'|')+1);
                    $answer .= "\t<th class=\"answertextright\">$answertext</th>\n";
                }
                elseif ($right_exists)
                {
                    $answer .= "\t<td class=\"answertextright\">&nbsp;</td>\n";
                }

                if ($this->mandatory != 'Y' && SHOW_NO_ANSWER == 1)
                {
                    $answer .= "\t<td class=\"answer-item radio-item noanswer-item\">\n<label for=\"answer$myfname-\" class=\"hide\">{$clang->gT('No answer')}</label>\n"
                    ."\t<input class=\"radio\" type=\"radio\" name=\"$myfname\" value=\"\" id=\"answer$myfname-\" ";
                    if (!isset($_SESSION['survey_'.$this->surveyid][$myfname]) || $_SESSION['survey_'.$this->surveyid][$myfname] == '')
                    {
                        $answer .= CHECKED;
                    }
                    // --> START NEW FEATURE - SAVE
                    $answer .= " onclick=\"$checkconditionFunction(this.value, this.name, this.type)\"  />\n\t</td>\n";
                    // --> END NEW FEATURE - SAVE
                }

                $answer .= "</tr>\n";
                //IF a MULTIPLE of flexi-redisplay figure, repeat the headings
            }
            $answer .= "</tbody>\n";
            $answer_cols = "\t<colgroup class=\"col-responses\">\n"
            ."\t<col class=\"col-answers\" width=\"$answerwidth%\" />\n" ;

            $odd_even = '';
            foreach ($labelans as $c)
            {
                $odd_even = alternation($odd_even);
                $answer_cols .= "<col class=\"$odd_even\" width=\"$cellwidth%\" />\n";
            }
            if ($right_exists)
            {
                $odd_even = alternation($odd_even);
                $answer_cols .= "<col class=\"answertextright $odd_even\" width=\"$answerwidth%\" />\n";
            }
            if ($this->mandatory != 'Y' && SHOW_NO_ANSWER == 1) //Question is not mandatory
            {
                $odd_even = alternation($odd_even);
                $answer_cols .= "<col class=\"col-no-answer $odd_even\" width=\"$cellwidth%\" />\n";
            }
            $answer_cols .= "\t</colgroup>\n";

            $answer = $answer_start . $answer_cols . $answer_head .$answer ."</table>\n";
        }
        elseif ($useDropdownLayout === true && $lresult->count() > 0)
        {
            foreach($lresult->readAll() as $lrow)
                $labels[]=Array('code' => $lrow['code'],
                'answer' => $lrow['answer']);
            $ansquery = "SELECT question FROM {{questions}} WHERE parent_qid={$this->id} AND question like '%|%' ";
            $ansresult = dbExecuteAssoc($ansquery);  //Checked
            if ($ansresult->count()>0) {$right_exists=true;$answerwidth=$answerwidth/2;} else {$right_exists=false;}
            // $right_exists is a flag to find out if there are any right hand answer parts. If there arent we can leave out the right td column
            $ansresult = $this->getChildren(); //Checked
            $anscount = count($ansresult);
            $fn=1;

            $numrows = count($labels);
            if ($this->mandatory != 'Y' && SHOW_NO_ANSWER == 1)
            {
                ++$numrows;
            }
            if ($right_exists)
            {
                ++$numrows;
            }
            $cellwidth = round( ($columnswidth / $numrows ) , 1 );

            $answer_start = "\n<table class=\"question subquestions-list questions-list {$extraclass}\" summary=\"".str_replace('"','' ,strip_tags($this->text))." - an array type question\" >\n";

            $answer = "\t<tbody>\n";
            $trbc = '';

            foreach ($ansresult as $ansrow)
            {
                $myfname = $this->fieldname.$ansrow['title'];
                $trbc = alternation($trbc , 'row');
                $answertext=$ansrow['question'];
                $answertextsave=$answertext;
                if (strpos($answertext,'|'))
                {
                    $answertext=substr($answertext,0, strpos($answertext,'|'));
                }
                /* Check if this item has not been answered: the 'notanswered' variable must be an array,
                containing a list of unanswered questions, the current question must be in the array,
                and there must be no answer available for the item in this session. */

                if (strpos($answertext,'|')) {$answerwidth=$answerwidth/2;}

                if ($this->mandatory=='Y' && (is_array($notanswered)) && (array_search($myfname, $notanswered) !== FALSE) && ($_SESSION['survey_'.$this->surveyid][$myfname] == '') ) {
                    $answertext = '<span class="errormandatory">'.$answertext.'</span>';
                }
                // Get array_filter stuff
                list($htmltbody2, $hiddenfield)=return_array_filter_strings($this, $aQuestionAttributes, $thissurvey, $ansrow, $myfname, $trbc, $myfname,"tr","$trbc question-item answer-item dropdown-item");
                $answer .= $htmltbody2;

                $answer .= "\t<th class=\"answertext\">\n$answertext"
                . $hiddenfield
                . "<input type=\"hidden\" name=\"java$myfname\" id=\"java$myfname\" value=\"";
                if (isset($_SESSION['survey_'.$this->surveyid][$myfname]))
                {
                    $answer .= $_SESSION['survey_'.$this->surveyid][$myfname];
                }
                $answer .= "\" />\n\t</th>\n";

                $answer .= "\t<td >\n"
                . "<select name=\"$myfname\" id=\"answer$myfname\" onchange=\"$checkconditionFunction(this.value, this.name, this.type);\">\n";

                if (!isset($_SESSION['survey_'.$this->surveyid][$myfname]) || $_SESSION['survey_'.$this->surveyid][$myfname] =='')
                {
                    $answer .= "\t<option value=\"\" ".SELECTED.'>'.$clang->gT('Please choose')."...</option>\n";
                }

                foreach ($labels as $lrow)
                {
                    $answer .= "\t<option value=\"".$lrow['code'].'" ';
                    if (isset($_SESSION['survey_'.$this->surveyid][$myfname]) && $_SESSION['survey_'.$this->surveyid][$myfname] == $lrow['code'])
                    {
                        $answer .= SELECTED;
                    }
                    $answer .= '>'.flattenText($lrow['answer'])."</option>\n";
                }
                // If not mandatory and showanswer, show no ans
                if ($this->mandatory != 'Y' && SHOW_NO_ANSWER == 1)
                {
                    $answer .= "\t<option value=\"\" ";
                    if (!isset($_SESSION['survey_'.$this->surveyid][$myfname]) || $_SESSION['survey_'.$this->surveyid][$myfname] == '')
                    {
                        $answer .= SELECTED;
                    }
                    $answer .= '>'.$clang->gT('No answer')."</option>\n";
                }
                $answer .= "</select>\n";

                if (strpos($answertextsave,'|'))
                {
                    $answertext=substr($answertextsave,strpos($answertextsave,'|')+1);
                    $answer .= "\t<th class=\"answertextright\">$answertext</th>\n";
                }
                elseif ($right_exists)
                {
                    $answer .= "\t<td class=\"answertextright\">&nbsp;</td>\n";
                }

                $answer .= "</tr>\n";
                //IF a MULTIPLE of flexi-redisplay figure, repeat the headings
                $fn++;
            }
            $answer .= "\t</tbody>";
            $answer = $answer_start . $answer . "\n</table>\n";
        }
        else
        {
            $answer = "\n<p class=\"error\">".$clang->gT("Error: There are no answer options for this question and/or they don't exist in this language.")."</p>\n";
        }
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
            ."<td>{$fname['subquestion']}";
            if (isset($fname['scale']))
            {
                $output .= " (".$fname['scale'].')';
            }
            $output .="</td>\n";
            $scale_id=0;
            if (isset($q->scale)) $scale_id=$q->scale;
            $fquery = "SELECT * FROM {{answers}} WHERE qid='{$q->id}' and scale_id={$scale_id} and language='$language->getlangcode()' order by sortorder, answer";
            $fresult = dbExecuteAssoc($fquery);
            $output .= "<td>\n";
            foreach ($fresult->readAll() as $frow)
            {
                $output .= "\t<input type='radio' class='radiobtn' name='{$q->fieldname}' value='{$frow['code']}'";
                if ($idrow[$q->fieldname] == $frow['code']) {$output .= " checked";}
                $output .= " />".$frow['answer']."&nbsp;\n";
            }
            //Add 'No Answer'
            $output .= "\t<input type='radio' class='radiobtn' name='{$q->fieldname}' value=''";
            if ($idrow[$q->fieldname] == '') {$output .= " checked";}
            $output .= " />".$clang->gT("No answer")."&nbsp;\n";

            $output .= "</td>\n"
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
        if ($value == "-oth-")
        {
            return $language->gT("Other"). "[-oth-]";
        }
        $scale=isset($this->scale)?$this->scale:0;
        $result = Answers::model()->getAnswerFromCode($this->id,$value,$language->langcode,$scale) or die ("Couldn't get answer type."); //Checked
        if($result->count())
        {
            $result =array_values($result->readAll());
            return $result[count($result)-1]['answer']." [$value]";
        }
        return $value;
    }

    public function setAssessment()
    {
        $this->assessment_value = 0;
        if (isset($_SESSION['survey_'.$this->surveyid][$this->fieldname]))
        {
            $usquery = "SELECT assessment_value FROM {{answers}} where qid=".$this->id." and language='$baselang' and code=".dbQuoteAll($_SESSION['survey_'.$this->surveyid][$this->fieldname]);
            $usresult = dbExecuteAssoc($usquery);          //Checked
            if ($usresult)
            {
                $usrow = $usresult->read();
                $this->assessment_value=(int) $usrow['assessment_value'];
            }
        }
        return true;
    }

    public function getFullAnswer($answerCode, $export, $survey)
    {
        $answers = $survey->getAnswers($this->id, 0);
        return (isset($answers[$answerCode])) ? $answers[$answerCode]['answer'] : "";
    }

    public function getSPSSAnswers()
    {
        global $language, $length_vallabel;
        $query = "SELECT {{answers}}.code, {{answers}}.answer,
        {{questions}}.type FROM {{answers}}, {{questions}} WHERE";

        if (isset($this->scale)) $query .= " {{answers}}.scale_id = " . (int) $this->scale . " AND";

        $query .= " {{answers}}.qid = '".$this->id."' and {{questions}}.language='".$language."' and  {{answers}}.language='".$language."'
        and {{questions}}.qid='".$this->id."' ORDER BY sortorder ASC";
        $result= Yii::app()->db->createCommand($query)->query(); //Checked
        foreach ($result->readAll() as $row)
        {
            $answers[] = array('code'=>$row['code'], 'value'=>mb_substr(stripTagsFull($row["answer"]),0,$length_vallabel));
        }
        return $answers;
    }

    public function getAnswerArray($em)
    {
        return (isset($em->qans[$this->id]) ? $em->qans[$this->id] : NULL);
    }

    public function getVarAttributeValueNAOK($name, $default, $gseq, $qseq, $ansArray)
    {
        $code = LimeExpressionManager::GetVarAttribute($name,'code',$default,$gseq,$qseq);
        $scale_id = LimeExpressionManager::GetVarAttribute($name,'scale_id','0',$gseq,$qseq);
        $which_ans = $scale_id . '~' . $code;
        if (is_null($ansArray))
        {
            return $default;
        }
        else
        {
            if (isset($ansArray[$which_ans])) {
                $answerInfo = explode('|',$ansArray[$which_ans]);
                $answer = $answerInfo[0];
            }
            else {
                $answer = $default;
            }
            return $answer;
        }
    }

    public function getShownJS()
    {
        return 'which_ans = "0~" + value;'
                . 'if (typeof attr.answers[which_ans] === "undefined") return value;'
                . 'answerParts = attr.answers[which_ans].split("|");'
                . 'answerParts.shift();'
                . 'return answerParts.join("|");';
    }

    public function getValueJS()
    {
        return 'which_ans = "0~" + value;'
                . 'if (typeof attr.answers[which_ans] === "undefined") return "";'
                . 'answerParts = attr.answers[which_ans].split("|");'
                . 'return answerParts[0];';
    }

    public function getDataEntryView($language)
    {
        $meaquery = "SELECT title, question FROM {{questions}} WHERE parent_qid={$this->id} AND language='{$language->getlangcode()}' ORDER BY question_order";
        $mearesult = dbExecuteAssoc($meaquery)->readAll() or safeDie ("Couldn't get answers, Type \":\"<br />$meaquery<br />");

        $fquery = "SELECT * FROM {{answers}} WHERE qid={$this->id} AND language='{$language->getlangcode()}' ORDER BY sortorder, code";
        $fresult = dbExecuteAssoc($fquery)->readAll();

        $output = "<table>";
        foreach ( $mearesult as $mearow)
        {

            if (strpos($mearow['question'],'|'))
            {
                $answerleft=substr($mearow['question'],0,strpos($mearow['question'],'|'));
                $answerright=substr($mearow['question'],strpos($mearow['question'],'|')+1);
            }
            else
            {
                $answerleft=$mearow['question'];
                $answerright='';
            }

            $output .= "<tr>";
            $output .= "<td align='right'>{$answerleft}</td>";
            $output .= "<td>";
            $output .= "<select name={$this->fieldname}{$mearow['title']}'>";
            $output .= "<option value=''>{$language->gT("Please choose")}..</option>";

            foreach ($fresult as $frow)
            {
                $output .= "<option value='{$frow['code']}'>{$frow['answer']}</option>";
            }
            $output .= "</select>";
            $output .= "</td>";
            $output .= "<td align='left'>{$answerright}</td>";
            $output .= "</tr>";
        }
        $output .= "</table>";
        return $output;
    }

    public function getTypeHelp($language)
    {
        return $language->gT("Please choose the appropriate response for each item:");
    }

    public function getPrintAnswers($language)
    {
        $fieldname = $this->surveyid . 'X' . $this->gid . 'X' . $this->id;
        $qidattributes = $this->getAttributeValues();
        $mearesult=Questions::model()->getAllRecords(" parent_qid='{$this->id}'  AND language='{$language->getlangcode()}' ", array('question_order'));

        $fresult=Answers::model()->getAllRecords(" scale_id=0 AND qid='{$this->id}'  AND language='{$language->getlangcode()}'", array('sortorder','code'));

        $fcount = $fresult->getRowCount();
        $i=1;
        $column_headings = array();
        foreach ($fresult->readAll() as $frow)
        {
            $column_headings[] = $frow['answer'].(Yii::app()->getConfig('showsgqacode') ? " (".$frow['code'].")": '');
        }
        if (trim($qidattributes['answer_width'])!='')
        {
            $iAnswerWidth=100-$qidattributes['answer_width'];
        }
        else
        {
            $iAnswerWidth=80;
        }
        if (count($column_headings)>0)
        {
            $col_width = round($iAnswerWidth / count($column_headings));

        }
        else
        {
            $heading='';
        }
        $output = "\n<table>\n\t<thead>\n\t\t<tr>\n";
        $output .= "\t\t\t<td>&nbsp;</td>\n";
        foreach($column_headings as $heading)
        {
            $output .= "\t\t\t<th style=\"width:$col_width%;\">$heading</th>\n";
        }
        $i++;
        $output .= "\t\t</tr>\n\t</thead>\n\n\t<tbody>\n";
        $counter = 1;
        $rowclass = 'array1';

        foreach ($mearesult->readAll() as $mearow)
        {
            $output .= "\t\t<tr class=\"$rowclass\">\n";
            $rowclass = alternation($rowclass,'row');

            //semantic differential question type?
            if (strpos($mearow['question'],'|'))
            {
                $answertext = substr($mearow['question'],0, strpos($mearow['question'],'|')).(Yii::app()->getConfig('showsgqacode') ? " (".$fieldname.$mearow['title'].")" : '')." ";
            }
            else
            {
                $answertext=$mearow['question'].(Yii::app()->getConfig('showsgqacode') ? " (".$fieldname.$mearow['title'].")" : '');
            }

            if (trim($answertext)=='') $answertext='&nbsp;';

            if (trim($qidattributes['answer_width'])!='')
            {
                $sInsertStyle=' style="width:'.$qidattributes['answer_width'].'%" ';
            }
            else
            {
                $sInsertStyle='';
            }
            $output .= "\t\t\t<th $sInsertStyle class=\"answertext\">$answertext</th>\n";

            for ($i=1; $i<=$fcount; $i++)
            {
                $output .= "\t\t\t<td>".printablesurvey::input_type_image('radio')."</td>\n";

            }
            $counter++;

            //semantic differential question type?
            if (strpos($mearow['question'],'|'))
            {
                $answertext2=substr($mearow['question'],strpos($mearow['question'],'|')+1);
                $output .= "\t\t\t<th class=\"answertextright\">$answertext2</th>\n";
            }
            $output .= "\t\t</tr>\n";
        }
        $output .= "\t</tbody>\n</table>\n";
        return $output;
    }

    public function getPrintPDF($language)
    {
        $fieldname = $this->surveyid . 'X' . $this->gid . 'X' . $this->id;
        $mearesult=Questions::model()->getAllRecords(" parent_qid='{$this->id}'  AND language='{$language->getlangcode()}' ", array('question_order'));

        $fresult=Answers::model()->getAllRecords(" scale_id=0 AND qid='{$this->id}'  AND language='{$language->getlangcode()}'", array('sortorder','code'));
        $fcount = $fresult->getRowCount();

        $i=1;
        $pdfoutput = array();
        $pdfoutput[0][0]='';
        $heading='';
        foreach ($fresult->readAll() as $frow)
        {
            $heading = $frow['answer'].(Yii::app()->getConfig('showsgqacode') ? " (".$frow['code'].")" : '');
        }

        $pdfoutput[0][$i] = $heading;
        $i++;
        $counter = 1;

        foreach ($mearesult->readAll() as $mearow)
        {
            $pdfoutput[$counter][0]=$mearow['question'];
            for ($i=1; $i<=$fcount; $i++)
            {
                $pdfoutput[$counter][$i] = "o";

            }
            $counter++;
        }
        return $pdfoutput;
    }

    public function getConditionAnswers()
    {
        $clang = Yii::app()->lang;
        $canswers = array();

        $aresult = Questions::model()->findAllByAttributes(array('parent_qid'=>$this->id, 'language' => Survey::model()->findByPk($this->surveyid)->language), array('order' => 'question_order ASC'));

        $fresult = Answers::model()->findAllByAttributes(array(
        'qid' => $this->id,
        "language" => Survey::model()->findByPk($this->surveyid)->language,
        'scale_id' => 0,
        ), array('order' => 'sortorder, code'));
        foreach ($aresult as $arows)
        {
            foreach ($fresult as $frow)
            {
                $canswers[]=array($this->surveyid.'X'.$this->gid.'X'.$this->id.$arows['title'], $frow['code'], $frow['answer']);

                if ($this->mandatory != 'Y')
                {
                    $canswers[]=array($this->surveyid.'X'.$this->gid.'X'.$this->id.$arows['title'], "", $clang->gT("No answer"));
                }
            }
        }

        return $canswers;
    }

    public function getConditionQuestions()
    {
        $cquestions = array();

        $aresult = Questions::model()->findAllByAttributes(array('parent_qid'=>$this->id, 'language' => Survey::model()->findByPk($this->surveyid)->language), array('order' => 'question_order ASC'));
        foreach ($aresult as $arows)
        {
            $shortanswer = "{$arows['title']}: [" . flattenText($arows['question']) . "]";
            $shortquestion = $this->title.":$shortanswer ".flattenText($this->text);
            $cquestions[] = array($shortquestion, $this->id, false, $this->surveyid.'X'.$this->gid.'X'.$this->id.$arows['title']);
        }
        return $cquestions;
    }

    public function QueXMLAppendAnswers(&$question)
    {
        global $dom;
        $response = $dom->createElement("response");
        $response->setAttribute("varName", $this->surveyid . 'X' . $this->gid . 'X' . $this->id);
        quexml_create_subQuestions($question,$this->id,$this->surveyid . 'X' . $this->gid . 'X' . $this->id);
        $response->appendChild(QueXMLCreateFixed($this->id,false,false,0,$this->isother == 'Y',$this->surveyid . 'X' . $this->gid . 'X' . $this->id));
        $question->appendChild($response);
    }

    public function availableAttributes($attr = false)
    {
        $attrs=array("answer_width","repeat_headings","array_filter","array_filter_exclude","array_filter_style","em_validation_q","em_validation_q_tip","exclude_all_others","statistics_showgraph","statistics_graphtype","hide_tip","hidden","max_answers","min_answers","page_break","public_statistics","random_order","parent_order","use_dropdown","scale_export","random_group");
        return $attr?in_array($attr,$attrs):$attrs;
    }

    public function questionProperties($prop = false)
    {
        $clang=Yii::app()->lang;
        $props=array('description' => $clang->gT("Array"),'group' => $clang->gT('Arrays'),'subquestions' => 1,'class' => 'array-flexible-row','hasdefaultvalues' => 0,'assessable' => 1,'answerscales' => 1,'enum' => 0);
        return $prop?$props[$prop]:$props;
    }
}
?>
