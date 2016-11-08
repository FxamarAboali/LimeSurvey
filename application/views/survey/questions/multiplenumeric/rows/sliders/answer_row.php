<?php
/**
 * Multiple short texts question, item input text Html
 * @var $tip
 * @var $alert
 * @var $maxlength
 * @var $tiwidth
 * @var $extraclass
 * @var $sDisplayStyle
 * @var $prefix
 * @var $myfname
 * @var $labelText
 * @var $prefix
 * @var $kpclass
 * @var $rows
 * @var $checkconditionFunction
 * @var $dispVal
 * @var $suffix
 * @var $sUnformatedValue
 * @var $slider_min
 * @var $slider_max
 * @var $slider_step
 * @var $slider_default
 * @var $slider_orientation
 * @var $slider_handle
 * @var $slider_reset
 * @var $sSeparator
 */
?>

<li id='javatbd<?php echo $myfname; ?>' class="question-item answer-item numeric-item text-item slider-item form-group <?php echo $extraclass;?><?php if($alert):?> has-error<?php endif; ?>" <?php echo $sDisplayStyle;?>>
    <label class='control-label col-xs-12 col-sm-<?php echo $sLabelWidth; ?><?php if($alert):?> errormandatory<?php endif; ?>' for="answer<?php echo$myfname;?>">
        <?php echo $labelText; ?>
    </label>
    <div class="col-xs-12 col-sm-<?php echo $sInputContainerWidth; ?> container-fluid">
            <?php if (!empty($sliderleft)): ?>
                <div class='col-xs-6 col-sm-2 slider-left text-right'><?php echo $sliderleft;?></div>
            <?php endif; ?>

            <?php if (!empty($sliderright)): ?>
                <div class='col-xs-6 col-sm-2 col-sm-push-<?php echo $sliderWidth ?> slider-right text-left'><?php echo $sliderright;?></div>
            <?php endif; ?>

            <div class="slider-container ls-input-group col-xs-12 col-sm-<?php echo $sliderWidth ?> col-sm-pull-2">
            <?php
            /* FF show issue + prefix/suffix must be encoded */
            echo CHtml::textField($myfname,$dispVal,array(
                'class'=>'form-control',
                'id'=>"answer{$myfname}",
                'data-slider-value'=>($dispVal ? $dispVal : ''),
                'data-slider-min'=>$slider_min,
                'data-slider-max'=>$slider_max,
                'data-slider-step'=>$slider_step,
                'data-slider-orientation'=>$slider_orientation,
                'data-slider-handle'=>$slider_handle,
                'data-slider-tooltip'=>'always',
                'data-slider-reset'=>$slider_reset,
                'data-slider-prefix'=>$prefix,
                'data-slider-suffix'=>$suffix,
                'data-separator'=>$sSeparator,
                'data-number'=>true,
                'data-integer'=>$integeronly,
            ));
            ?>
                <?php if($slider_showminmax): ?>
                    <div class='pull-left help-block'><?php echo $slider_min; ?></div>
                    <div class='pull-right help-block'><?php echo $slider_max; ?></div>
                <?php endif; ?>
                <?php if ($slider_reset): ?>
                    <div class="ls-input-group-extra ls-no-js-hidden ls-input-group-reset">
                    <div id="answer<?php echo $myfname; ?>_resetslider" class='btn btn-default btn-sm btn-slider-reset'>
                        <span class='fa fa-times slider-reset-icon' aria-hidden='true'></span><span class="slider-reset-text">&nbsp;<?php eT("Reset"); ?></span>
                    </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <input type="hidden" name="slider_user_no_action_<?php echo $myfname; ?>" id="slider_user_no_action_<?php echo $myfname; ?>" value="<?php echo ($dispVal ? 0 : 1);?>" />
    </div>
</li>
<!-- end of answer_row -->
