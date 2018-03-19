<?php
/**
 * This view displays the Step 1 : pre-installation checks.
 * The javascript inject it inside the div#updaterContainer, in the _updater view. (like any steps)
 *
 * @var object $localChecks an object containing all the checks results
 * @var int $destinationBuild the destination build
 */
?>

<?php
        $urlNew = Yii::app()->createUrl("admin/update", array("update"=>'checkLocalErrors', 'destinationBuild' => $destinationBuild, 'access_token' => $access_token));
        $errors = FALSE;
?>

<h3 class="maintitle"><?php eT('Checking basic requirements...'); ?></h3>

<?php
    if( isset($localChecks->html) )
        echo $localChecks->html;
?>

<table class="table">
    <thead>
        <tr>
            <th class="col-sm-10"><?php eT('Available space in directory:');?></th>
            <th class="col-sm-1"  style="text-align: right"></th>
            <th class="col-sm-1"  style="text-align: right"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($localChecks->files as $file):?>
            <?php if($file->freespace !== 'pass'): ?>
                <tr>
                    <td><?php echo $file->name;?></td>
                    <td></td>
                    <?php if($file->freespace): ?>
                        <td><span class="fa fa-check text-success" alt="right"></span></td>
                    <?php else: ?>
                        <td>
                            <h3 class="label label-danger">
                                <?php eT('Not enough space'); ?>
                            </h3>
                        </td>
                        <?php $errors = true; $ignore = true; ?>
                    <?php endif; ?>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
<?php if($errors): ?>
    <div>
    <em>
        <?php eT("Note : In some hosting, like shared hosting, it can happen that the available free space is not correctly evaluated. If you checked manually that you have enough free space to update, please, just ignore this error."); ?>
    </em>
    <br/><br/>
    </div>
<?php endif;?>

<?php if($localChecks->mysql->docheck !== 'pass'): ?>
    <table class="table">
        <thead>
            <tr>
                <th class="col-sm-10"><?php eT('MYSQL version required:');?></th>
                <th class="col-sm-1"  style="text-align: right"></th>
                <th class="col-sm-1"  style="text-align: right"></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php echo $localChecks->mysql->mysql_ver;?></td>
                <td></td>
                <?php if($localChecks->mysql->result): ?>
                    <td><span class="fa fa-check text-success" alt="right"></span></td>
                <?php else: ?>
                    <td>
                        <h3 class="label label-danger">
                            <?php printf(gT('MYSQL version is only %s'),$localChecks->mysql->local_mysql_ver);?>
                        </h3>
                    </td>
                    <?php $errors = TRUE; $cant_ignore = true; $ignore = false; ?>
                <?php endif; ?>
            </tr>
        </tbody>
    </table>
<?php endif;?>

<table class="table">
    <thead>
        <tr>
            <th class="col-sm-10"><?php eT('PHP version required:');?></th>
            <th class="col-sm-1"  style="text-align: right"></th>
            <th class="col-sm-1"  style="text-align: right"></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-sm-8"><?php echo $localChecks->php->php_ver;?></td>

            <td class="col-sm-1"></td>

            <?php if($localChecks->php->result): ?>
                <td>
                    <span class="fa fa-check text-success" alt="right"></span>
                </td>
            <?php else: ?>
                <td>
                    <h3 class="label label-danger">
                        <?php printf(gT('PHP version is only %s'),$localChecks->php->local_php_ver);?>
                    </h3>
                </td>
                <?php $errors = TRUE; $cant_ignore = true; $ignore = false;?>
            <?php endif; ?>
        </tr>
    </tbody>
</table>

<table class="table">
    <thead>
        <tr>
            <th class="col-sm-10"><?php eT('Required PHP modules:');?></th>
            <th class="col-sm-1"  style="text-align: right"></th>
            <th class="col-sm-1"  style="text-align: right"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($localChecks->php_modules as $name => $module):?>
        <tr>
            <td><?php echo $name;?></td>
            <td></td>

            <?php if($module->installed): ?>
                <td>
                    <span class="fa fa-check text-success" alt="right"></span>
                </td>
            <?php elseif(isset($module->required)): ?>
                <td>
                    <span class="label label-danger">
                        <?php eT('Not found!'); ?>
                    </span>
                </td>
                <?php $errors = TRUE; $cant_ignore = true; $ignore = false;?>
            <?php else: ?>
                <td>
                    <span class="label label-danger">
                        <?php eT('No (but optional)'); ?>
                    </span>
                </td>
            <?php endif; ?>

        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if($errors): ?>
    <?php
    if($ignore && ! $cant_ignore )
    {
            $formUrl = Yii::app()->getController()->createUrl("admin/update/sa/changeLog/");
            echo CHtml::beginForm($formUrl, 'post', array("id"=>"launchChangeLogForm"));
            echo CHtml::hiddenField('destinationBuild' , $destinationBuild);
            echo CHtml::hiddenField('access_token' , $access_token);
    }
    ?>

<p>
    <a class="btn btn-default" href="<?php echo Yii::app()->createUrl("admin/update"); ?>" role="button" aria-disabled="false">
        <?php eT("Cancel"); ?>
    </a>
    <a class="btn btn-default" href="<?php echo $urlNew;?>" role="button" aria-disabled="false">
        <?php eT('Check again');?>
    </a>

    <?php if($ignore  && ! $cant_ignore): ?>

        <?php
            echo CHtml::submitButton(gT('Ignore','unescaped'), array('id'=>'Ignorestep1launch', "class"=>"btn btn-default"));
        ?>
    <?php endif;?>
</p>
<?php if($ignore  && ! $cant_ignore)
            echo CHtml::endForm();
?>

<?php else:?>
<p>
    <?php echo gT('Everything looks alright. Please proceed to the next step.');?>

    <?php
        $formUrl = Yii::app()->getController()->createUrl("admin/update/sa/changeLog/");
        echo CHtml::beginForm($formUrl, 'post', array("id"=>"launchChangeLogForm"));
        echo CHtml::hiddenField('destinationBuild' , $destinationBuild);
        echo CHtml::hiddenField('access_token' , $access_token);
    ?>
        <a class="btn btn-default" href="<?php echo Yii::app()->createUrl("admin/update"); ?>" role="button" aria-disabled="false">
            <?php eT("Cancel"); ?>
        </a>

    <?php
        echo CHtml::submitButton(gT('Continue','unescaped'), array('id'=>'step1launch', "class"=>"btn btn-default"));
        echo CHtml::endForm();
    ?>
</p>

<?php endif;?>

<!-- this javascript code manage the step changing. It will catch the form submission, then load the comfortupdate for the required build -->
<script type="text/javascript" src="<?php echo Yii::app()->baseUrl; ?>/assets/scripts/admin/comfortupdate/comfortUpdateNextStep.js"></script>
<script>
$('#launchChangeLogForm').comfortUpdateNextStep({'step': 1});
</script>
