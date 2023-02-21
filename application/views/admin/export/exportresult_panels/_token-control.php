<?php
    $attrfieldnames=getTokenFieldsAndNames($surveyid,true);
?>

<div class="card" id="panel-7">
  <div class="card-header ">
    <div class="">
      <?php eT("Participant control");?>
    </div>
  </div>
  <div class="card-body">
    <div class="alert alert-info alert-dismissible" role="alert">
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      <?php eT('Your survey can export associated participant data with each response. Select any additional fields you would like to export.'); ?>
    </div>

    <label for='attribute_select' class="col-md-4 form-label">
      <?php eT("Choose participant fields:");?>
    </label>
    <div class="col-md-8">
      <select name='attribute_select[]' multiple size='20' class="form-select" id="attribute_select">
        <option value='first_name' id='first_name'>
          <?php eT("First name");?>
        </option>
        <option value='last_name' id='last_name'>
          <?php eT("Last name");?>
        </option>
        <option value='email_address' id='email_address'>
          <?php eT("Email address");?>
        </option>

        <?php 
            foreach ($attrfieldnames as $attr_name=>$attr_desc)
            {
                echo "<option value='$attr_name' id='$attr_name' />".$attr_desc['description']."</option>\n";
            } 
        ?>
      </select>
    </div>
  </div>
</div>
