<!-- Quick CSV report -->
<a class="btn btn-outline-secondary" role="button" onClick="window.open('<?php echo Yii::App()->createUrl("quotas/quickCSVReport/surveyid/$surveyid") ?>', '_top')">
  <?php eT("Quick CSV report"); ?>
</a>

<!-- Add new quota -->
<a class="btn btn-outline-secondary quota_new" role="button" href="<?php echo Yii::App()->createUrl("quotas/AddNewQuota/surveyid/$surveyid") ?>">
    <?php eT("Add new quota"); ?>
</a>
