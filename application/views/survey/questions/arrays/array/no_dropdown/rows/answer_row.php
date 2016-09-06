<?php
/**
 * Generate a row for the table
 *
 * @var $answer_tds      : the cells of each row, generated with the view rows/cells/*.php
 * @var $myfname
 * @var $answertext
 * @var $value
 */
?>

<!-- answer_row -->
<tr id="javatbd<?php echo $myfname;?>" class="well answers-list radio-list array<?php echo $zebra; ?><?php if($error){ echo " bg-warning";} ?>" role="radiogroup"  aria-labelledby="answertext<?php echo $myfname;?>">
    <th class="answertext <?php if($error){ echo " text-danger";} ?>">
        <div id="answertext<?php echo $myfname;?>"><?php echo $answertext;?></div>
        <input name="java<?php echo $myfname;?>" id="java<?php echo $myfname;?>" value="<?php echo $value;?>" type="hidden">
    </th>
    <?php
        // Defined in answer_td view
        echo $answer_tds;
    ?>

    <?php if ($right_exists): ?>
        <th class='answertextright'>
            <?php echo $answertextright; ?>
        </th>
    <?php endif; ?>

    <?php
        // No answer should come after right text at bipolar question
        echo $no_answer_td;
    ?>

</tr>
<!-- end of answer_row -->
