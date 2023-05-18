<div class="accordion">
  <div class="accordion-item">
    <h2 class="accordion-header" id="panelsStayOpen-headingOne">
      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseOne" aria-expanded="true" aria-controls="panelsStayOpen-collapseOne">
        <h2 class="summary-title py-1"><?php eT("Response summary"); ?></h2>
      </button>
    </h2>
    <div id="panelsStayOpen-collapseOne" class="accordion-collapse collapse show" aria-labelledby="panelsStayOpen-headingOne">
      <div class="accordion-body">
        <div class="row">
          <div class="col-12 content-right">
            <div class="row">
              <div class="col summary-detail">
                <?php eT("Full responses"); ?>
              </div>
              <div class="col">
                <?php echo $num_completed_answers; ?>
              </div>
              <div class="col">
              </div>
            </div>
            <div class="row">
              <div class="col summary-detail">
                <?php eT("Incomplete responses"); ?>
              </div>
              <div class="col">
                <?php echo $num_completed_answers; ?>
              </div>
              <div class="col">
              </div>
            </div>
            <div class="row">
              <div class="col summary-detail">
                <?php eT("Total responses"); ?>
              </div>
              <div class="col">
                <?php echo $num_total_answers; ?>
              </div>
              <div class="col">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (isset($with_token)) : ?>
  <h3><?php eT("Survey participant summary"); ?></h3>
  <div class="row">
    <div class="col-12 content-right">
      <table class='ls-statisticssummary table'>
        <tbody>
          <tr>
            <th><?php eT("Total invitations sent"); ?></th>
            <td><?php echo $tokeninfo['sent']; ?></td>
          </tr>
          <tr>
            <th><?php eT("Total surveys completed"); ?></th>
            <td><?php echo $tokeninfo['completed']; ?></td>
          </tr>
          <tr>
            <th><?php eT("Total with no unique access code"); ?></th>
            <td><?php echo $tokeninfo['invalid'] ?></td>
          </tr>
          <tr class="ls-statisticssummary__sum">
            <th><?php eT("Total records"); ?></th>
            <td><?php echo $tokeninfo['count']; ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>