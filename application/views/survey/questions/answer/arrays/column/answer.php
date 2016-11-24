<?php

/**
 * Array by column
 *
 * @var $anscount
 * @var $cellwidth
 */
 ?>
<table class="<?php echo $coreClass; ?> table table-bordered table-col-hover" role="group" aria-labelledby="ls-question-text-<?php echo $basename ?>">
    <colgroup>
        <col class="col-answers" style='width: <?php echo $answerwidth; ?>%;' />
        <?php foreach ($aQuestions as $i=>$question): ?>
            <col
                class="answers-list radio-list <?php echo ($i % 2)?'ls-odd':'ls-even';?> <?php if($question['errormandatory']): echo " has-error"; endif; ?>"
                style='width: <?php echo $cellwidth; ?>%;'
                role="radiogroup"
                aria-labelledby="answertext<?php echo $question['myfname'];?>"
                >
                <!-- @todo : control if radiogroup can be used in col : https://www.w3.org/TR/wai-aria/roles -->
        <?php endforeach; ?>
    </colgroup>
    <thead><!-- The global concept is hard to understand : must control if aria-labelledby for radio-group is OK and if we can add aria-hidden here -->
        <tr class='ls-heading'><!-- unsure for ls-heading class here -->
            <td></td>
            <?php
            foreach ($aQuestions as $i=>$question): ?>
                <th id="answertext<?php echo $question['myfname'];?>" class="answertext control-label <?php if($question['errormandatory']){ echo " has-error error-mandatory";} ?>">
                    <?php echo $question['question']; ?>
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($labels as $ansrow): ?>
            <tr id="javatbd<?php echo $ansrow['code'];?>" class="answers-list">
                <th class="answertext" id="label-<?php echo $ansrow['code'];?>">
                    <?php echo $ansrow['answer']; ?>
                </th>
                <?php
                foreach ($anscode as $i => $ld): ?>
                    <td class="answer_cell_<?php echo $ld;?> answer-item radio-item">
                            <input
                                type="radio"
                                name="<?php echo $aQuestions[$i]['myfname']; ?>"
                                value="<?php echo $ansrow['code']; ?>"
                                id="answer<?php echo $aQuestions[$i]['myfname']; ?>-<?php echo $ansrow['code']; ?>"
                                <?php echo $checked[$ansrow['code']][$ld]; ?>
                                 />
                                <label class="ls-label-xs-visibility " for="answer<?php echo $aQuestions[$i]['myfname']; ?>-<?php echo $ansrow['code']; ?>">
                                    <?php echo $aQuestions[$i]['question'];?>
                                </label>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php foreach ($anscode as $i => $ld): ?>
    <input
        type="hidden"
        name="java<?php echo $aQuestions[$i]['myfname']; ?>"
        id="java<?php echo $aQuestions[$i]['myfname']; ?>"
        value="<?php echo $aQuestions[$i]['myfname_value']; ?>"
    />
<?php endforeach; ?>
