<?php
/**
 * Generate a row for the table
 *
 * @var $answer_tds  : the cells of each row, generated with the view rows/cells/*.php
 * @var $myfname
 * @var $error
 * @var $answertext
 * @var $value
 * @var $rightTd
 * @var $rightTdEmpty
 * @var answerwidth
 * @var $formatedRowTotal
 */
?>

<!-- answer_row -->
<tr id="javatbd<?php echo $myfname;?>" class="<?php echo $coreRowClass;?> <?php echo ($odd) ? "ls-odd" : "ls-even"; ?><?php echo ($error) ? " has-error" : ""; ?>" role="group" aria-labelledby="answertext<?php echo $myfname;?>">
    <th id="answertext<?php echo $myfname;?>" class="answertext control-label">
        <?php echo $answertext; ?>
        <input
            type="hidden"
            name="java<?php echo $myfname;?>"
            id="java<?php echo $myfname;?>"
            value="<?php echo $value;?>"
        />
    </th>

    <!-- all cells for this row -->
    <?php echo $answer_tds;?>

    <!-- Total -->
    <?php if($rightTd): ?>
        <?php if($rightTdEmpty): ?>
            <td class="answertextright"></td>
        <?php else: ?>
            <td class="answertextright"><?php echo $answertext; ?></td>
        <?php endif; ?>
    <?php endif;?>

    <!-- Formated total -->
    <?php echo $formatedRowTotal; ?>
</tr>
<!-- end of answer_row -->
