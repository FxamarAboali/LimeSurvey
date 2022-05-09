<?php
/* @var $this PermissiontemplatesController */
/* @var $data Permissiontemplates */
?>

<div class="modal-header">
    <h5 class="modal-title" id="modalTitle-addedit"><?=sprintf(gT('Permission role %s'), $oModel->name);?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <div class="container-center">
        <div class="row">
            <div class="col-12 well">
                <?=$oModel->description?>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-4 col-12">
                <?=gT('Users assigned to this role')?>
            </div>
            <div class="col-lg-8 col-12">
                <ul class="list-group">
                    <?php foreach( $oModel->connectedUserobjects as $oUser) {
                        echo sprintf('<li class="list-group-item">%s - %s (%s)</li>', $oUser->uid, $oUser->full_name, $oUser->users_name);
                    } ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<div class="modal-footer modal-footer-buttons">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal">
        &nbsp
        <?php
        eT("Close"); ?>
    </button>
</div>
