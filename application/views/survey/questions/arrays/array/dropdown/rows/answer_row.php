<?php
/**
 * @var $myfname
 * @var $answertext
 * @var $value
 * @var $error
 * @var $checkconditions
 * @var $options
 * @var $thRight
 * @var $tdRight
 */
?>
<tr id="javatbd<?php echo $myfname;?>" class="well question-item answer-item dropdown-item array<?php echo $zebra; ?><?php if($error){ echo " bg-warning";} ?>" >
    <th class="answertext align-middle<?php if($error){ echo " text-danger";} ?>">
        <label for="answer<?php echo $myfname;?>">
            <?php echo $answertext; ?>
        </label>
        <input
            type="hidden"
            name="java<?php echo $myfname; ?>"
            id="java<?php echo $myfname;?>"
            value="<?php echo $value;?>"
        />
    </th>
    <td>
        <select class="form-control" name="<?php echo $myfname; ?>" id="answer<?php echo $myfname; ?>" onchange="checkconditions(this.value, this.name, this.type);">
            <?php foreach($options as $option):?>
                <option value="<?php echo $option['value'];?>" <?php echo $option['selected'];?>>
                    <?php echo $option['text'];?>
                </option>
            <?php endforeach; ?>
        </select>
    </td>

    <?php if ($right_exists): ?>
        <th class='answertextright align-middle'>
            <label>
                <?php echo $answertextright; ?>
            </label>
        </th>
    <?php endif; ?>
</tr>
