<script type='text/javascript'>
    var graphUrl = "<?php echo Yii::app()->getController()->createUrl("admin/statistics/sa/graph"); ?>";
</script>

<div class="container public-stats">
    <div class="row">
        <div class="col-sm-12">
            <div id='statsContainer'>
                <div id='statsHeader'>
                    <div class='h3 statsSurveyTitle'><?php echo $thisSurveyTitle; ?></div>
                    <div class='statsNumRecords'><?php echo gT("Total records in survey") . " : $totalrecords"; ?></div>
                </div>
                <div class="public-stats__content">
                    <?php if (isset($statisticsoutput) && $statisticsoutput) {
                        echo $statisticsoutput;
                    } ?><br/>
                </div>
            </div>
            <input type='hidden' class='hidemenubutton'/>
        </div>
    </div>
</div>