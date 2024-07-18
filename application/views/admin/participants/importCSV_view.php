<?php
/* @var $this AdminController */

// DO NOT REMOVE This is for automated testing to validate we see that page
echo viewHelper::getViewTestTag('importParticipants');

?>
<div id="pjax-content">
    <div class="container">
        <div class="row">
            <div class="col-12 list-surveys">
                <?php echo TbHtml::form(array("admin/participants/sa/attributeMapCSV"), 'post', array('id' => 'addsurvey', 'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8')); ?>

                <div class="row ls-space margin top-25 bottom-25">
                    <div class="col-12">
                        <div class="mb-3">
                            <label for="the_file" id="fileupload" class='form-label'>
                                <?php eT("Choose the file to upload:"); ?>
                            </label>
                            <div class="col-6">
                                <input id="the_file" type="file" class="form-control" name="the_file" accept='.csv'/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row ls-space margin top-25 bottom-25">
                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label for="characterset1" id="characterset" class='form-label '>
                                <?php eT("Character set of file:"); ?>
                            </label>
                            <div class="col-12">
                                <select name="characterset"  class="form-select" id="characterset1">
                                    <?php
                                    foreach (aEncodingsArray() as $key => $encoding):
                                        ?>
                                        <option value="<?php echo $key; ?>" <?php if ($encoding == gT('Automatic')) {
                                            echo 'selected="selected"';
                                        } ?> ><?php echo $encoding; ?></option>
                                    <?php
                                    endforeach;
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label for="separatorused1" id="separatorused" class='form-label '>
                                <?php eT("Separator used:"); ?>
                            </label>
                            <div class="col-12">
                                <?php
                                $separatorused = array(
                                    "comma" => gT("Comma")
                                    ,
                                    "semicolon" => gT("Semicolon")
                                );
                                ?>

                                <select name="separatorused"  class="form-select" id="separatorused1">
                                    <option value="auto" selected="selected"><?php eT("(Autodetect)"); ?></option>
                                    <?php
                                    foreach ($separatorused as $key => $separator):
                                        ?>
                                        <option value="<?php echo $key; ?>"><?php echo $separator; ?></option>
                                    <?php
                                    endforeach;
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row  ls-space margin top-25 bottom-25">
                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label for="filter1" id="filter" class='form-label '>
                                <?php
                                eT("Filter blank email addresses:");
                                ?>
                                <input class="ls-space margin left-15" type="checkbox" name="filterbea" value="accept" checked="checked" id="filter1">
                            </label>
                        </div>
                    </div>
                </div>
                <div class="row  ls-space margin top-25 bottom-25">
                    <div class="mb-3">
                        <div class="col-12 ">
                            <input type="submit" value="<?php eT("Upload") ?>" class="btn btn-outline-secondary col-md-6 offest-md-3 col-lg-4 offset-lg-4">
                        </div>
                    </div>
                </div>
                <?php echo CHtml::endForm(); ?>


                <div class="col-12 ls-space margin top-25 bottom-25">
                    <div class="card card-primary">
                        <h2 class="card-header ">
                            <?php eT("CSV input format") ?>
                        </h2>
                        <div class='card-body'>

                            <p>
                                <?php eT(
                                    "File should be a standard CSV (comma delimited) file with optional double quotes around values (default for most spreadsheet tools). The first line must contain the field names. The fields can be in any order."
                                ); ?>
                            </p>
                            <span style="font-weight:bold;"><?php eT("Mandatory field:") ?></span> email <br/>
                            <span style="font-weight:bold;"><?php eT("Optional fields:") ?></span> firstname, lastname,blacklisted,language
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <span id="locator" data-location="import">&nbsp;</span>
</div>
