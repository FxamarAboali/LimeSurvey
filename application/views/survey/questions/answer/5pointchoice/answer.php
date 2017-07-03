<?php
/**
 * 5 point choice Html
 *
 * @var $sRows         : the rows, generated with the view item_row.php
 * @var $slider_rating : slider rating display in question attribute
 *
 * @var $id
 * @var $sliderId  $ia[0];
 * @var $name'                   => $ia[1],
 * @var $sessionValue
 */
?>

<!-- 5 point choice -->

<!-- answer -->
<ul class="<?php echo $coreClass;?> list-unstyled form-inline" role="radiogroup" aria-labelledby="ls-question-text-<?php echo $name; ?>">
    <?php
        // item_row.php
        echo $sRows;
    ?>
</ul>
<?php
/* Value for expression manager javascript (use id) ; no need to submit */
echo \CHtml::hiddenField("java{$name}",$sessionValue,array(
    'id' => "java{$name}",
    'disabled' => true,
));
?>

<?php if($slider_rating==1):?>
    <script type='text/javascript'>
    <!--
        doRatingStar( <?php echo  $sliderId;?> );
    -->
    </script>
<?php elseif($slider_rating==2):?>
    <script type='text/javascript'>
    <!--
        var doRatingSlider_<?php echo  $sliderId; ?> = new getRatingSlider( <?php echo  $sliderId; ?> );
        doRatingSlider_<?php echo  $sliderId; ?>();
    -->
    </script>
<?php endif;?>
<!-- end of answer -->
