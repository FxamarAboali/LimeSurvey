<div class="container-fluid welcome">
    <div class="row text-center">
        <div class="col-xxl-3 offset-xl-4 col-md-6 offset-md-3">
            <div class="card login-panel" id="panel-1">

                <!-- Header -->
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                          <img alt='logo' id="profile-img" class="profile-img-card img-responsive center-block" src="<?php echo LOGO_URL;?>" />
                        </div>
                    </div>
                </div>

                <!-- Action Name -->
                <div class="row login-title login-content">
                      <div class="col-12">
                       <h3><?php eT('Recover your password'); ?></h3>
                    </div>
                </div>

                <!-- Form -->
                <?php echo CHtml::form(array("admin/authentication/sa/forgotpassword"), 'post', array('id'=>'forgotpassword','name'=>'forgotpassword'));?>
                    <div class="row login-content login-content-form">
                        <div class="col-12">
                            <div class="alert alert-info" role="alert">
                                <?php echo $message; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="row login-submit login-content">
                        <div class="col-12">
                            <a href='<?php echo $this->createUrl("/admin/authentication/sa/login"); ?>'><?php eT('Continue'); ?></a>
                        </div>
                    </div>
                <?php echo CHtml::endForm(); ?>
            </div>
        </div>
    </div>
</div>
