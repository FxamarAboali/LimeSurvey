<?php
    $aPermissiontemplates = Permissiontemplates::model()->findAll();
?>

<div class="modal-body selector--edit-role-container">
    <div class="container form">        
        <div class="row">
            <?php
            $this->widget('ext.AlertWidget.AlertWidget', [
                'text' => gT("Careful: Applying a role to the user will overwrite any individual permissions given to the user!"),
                'type' => 'info',
            ]);
            ?>
        </div>
        <div class="mb-3">
            <label for="roleselector"><?=gT("Select role to apply to users")?></label>
            <select class="form-select select post-value" name="roleselector" id="roleselector" multiple >
                <?php foreach($aPermissiontemplates as $oPermissiontemplate) {
                    echo "<option value='".$oPermissiontemplate->ptid."'>".$oPermissiontemplate->name."</option>";
                } ?>
            </select>
        </div>
    </div>
</div>
