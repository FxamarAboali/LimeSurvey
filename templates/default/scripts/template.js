/*
 * LimeSurvey
 * Copyright (C) 2007-2016 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v3 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
 *
 * Description: Javascript file for templates. Put JS-functions for your template here.
 *
 *
 */


/**
 * The function focusFirst puts the Focus on the first non-hidden element in the Survey.
 * Normally this is the first input field (the first answer).
 */
function focusFirst(Event)
{
    $('#limesurvey :input:visible:enabled:first').focus();
}

/**
 * Code included inside this will only run once the page Document Object Model (DOM) is ready for JavaScript code to execute
 * @see https://learn.jquery.com/using-jquery-core/document-ready/
 */
$(document).ready(function()
{
    /* Uncomment below if you want to use the focusFirst function */
    //focusFirst();
    /* Some function are launched in endpage.pstpl */
    hideEmptyPart();
    addHoverColumn();
    triggerEmClassChangeTemplate();

    // If list of nav-bar action is empty: remove it (else .navbar-toggle is shown on small screen) //
    if(!$("#navbar li").length){
        $("#navbar").remove();
        $("[data-target='#navbar']").remove();
    }
    // Scroll to first error
    if($(".input-error").length > 0) {
        $('#bootstrap-alert-box-modal').on('hidden.bs.modal', function () {
            $firstError = $(".input-error").first();
            $pixToScroll = ( $firstError.offset().top - 100 );
            $('html, body').animate({
                 scrollTop: $pixToScroll + 'px'
             }, 'fast');
        });
    }

    $('.language-changer').each(function(){
        $that = $(this);
        if(!$.trim($that.children('div').html()))
        {
            $that.hide();
        }
    });

    // Hide the menu buttons at the end of the Survey
    if($(".hidemenubutton").length>0)
    {
        $('.navbar-right').hide();
    }

    // Survey list footer
    if($('#surveyListFooter').length>0)
    {
        $surveyListFooter = $('#surveyListFooter');
        $('#outerframeContainer').after($surveyListFooter);
    }

    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })
});
/**
 * Code included inside this will run each time windows is resized
 * @see https://api.jquery.com/resize/
 */
$(window).resize(function () {
    fixBodyPadding();
});
/**
 * showStartPopups : replace core function : allow HTML and use it.
 */
function showStartPopups(){
    if(LSvar.showpopup && $(LSvar.startPopups).length){
        startPopup=LSvar.startPopups.map(function(text) {
            return "<p>"+text+"</p>";
        });
        $("#bootstrap-alert-box-modal .modal-body").html(startPopup);
        $("#bootstrap-alert-box-modal").modal('show');
    }
}
/**
 * Replace all existing alert default javascript function
 */
window.alert = function(message, title) {
    $(function() {
        $("#bootstrap-alert-box-modal .modal-header .h4").text(title || "");
        $("#bootstrap-alert-box-modal .modal-body").html("<p>"+message+"</p>" || "");
        $("#bootstrap-alert-box-modal").modal('show');
    });
};

/**
 * fix padding of body according to navbar-fixed-top
 * in endpage and in $(window).resize
 */
function fixBodyPadding(){
    /* The 60 px is fixed in template.css */
    $("body").css("padding-top",$(".navbar-fixed-top").height()+"px")
}
/**
 * Set suffix/prefix clone for little screen (at top)
 */
function sliderSuffixClone(){
$(".numeric-multi .slider-item .slider-right").each(function(){
    if($(this).closest(".slider-item").find(".slider-left").length){
        var colWidth="6";
    }else{
        var colWidth="12";
    }
    $(this).clone().removeClass("col-xs-12").addClass("visible-xs-block col-xs-"+colWidth).prop("aria-hidden",true).insertBefore($(this).prev(".slider-container"));
    $(this).addClass("hidden-xs");
    $(this).closest(".slider-item").find(".slider-left").removeClass("col-xs-12").addClass("col-xs-6");
});
}
/**
 * Add class hover to column in table-col-hover
 * We can't use CSS solution : need no background
 */
function addHoverColumn(){
    $(".table-col-hover").on({
        mouseenter: function () {
            $(this).closest(".table-col-hover").find("col").eq($(this).parent(".answers-list").children().index($(this))).addClass("hover");
        },
        mouseleave: function () {
            $(this).closest(".table-col-hover").find("col").removeClass("hover");
        }
    }, ".answer-item");

}
/**
 * Hide some part if empty
 * Some can be needed if contain only js
 * Some are not really needed : little margin only is shown
 */
function hideEmptyPart()
{
    $(".question-help-container").each(function(){
        if($(this).text().trim()==""){/* Only if have only script tag inside or empty tag */
            $(this).addClass("hidden");
        }
    });
    $(".group-description").each(function(){
        if($(this).text().trim()==""){/* Only if have only script tag inside or empty tag */
            $(this).addClass("hidden");
        }
    });
    $(".question-help-container.hidden").on("html:updated",function(){
        if($(this).text().trim()!=""){
            $(this).removeClass("hidden");
        }
    });
    $(".question-help-container").on("html:updated",function(){ // .question-help-container:not(.hidden) don't work ?
        if($(this).text().trim()==""){
            $(this).addClass("hidden");
        }
    });
}

/**
 * Update some class when em-tips is success/error
 * @see core/package/limesurvey/survey.js:triggerEmClassChange
 */
function triggerEmClassChangeTemplate(){
    $('.ls-em-tip').on('classChangeError', function() {
        /* If user choose hide-tip : leave it */
        //~ $parent = $(this).parent('div.qquestion-valid-container');
        //~ if ($parent.hasClass('hide-tip'))
        //~ {
            //~ $parent.removeClass('hide-tip',1);
            //~ $parent.addClass('tip-was-hidden',1);
        //~ }
        $questionContainer = $(this).parents('div.question-container');
        $questionContainer.addClass('input-error'); /* No difference betwwen error after submit and error before submit : think (Shnoulle) it's better to have a difference */
    });

    $('.ls-em-tip').on('classChangeGood', function() {
        /* If user choose hide-tip : leave it */
        //~ $parent = $(this).parents('div.question-valid-container');
        //~ $parent.removeClass('text-danger');
        //~ $parent.addClass('text-info');
        //~ if ($parent.hasClass('tip-was-hidden'))
        //~ {
            //~ $parent.removeClass('tip-was-hidden').addClass('hide-tip');
        //~ }
        $questionContainer = $(this).parents('div.question-container');
        $questionContainer.removeClass('input-error');/* Not working with mandatory question ... */
    });
}
/**
 * Hide question if all sub-questions is hidden
 * @see core/package/limesurvey/survey.js:triggerEmRelevanceSubQuestion
 * @see https://bugs.limesurvey.org/view.php?id=10055 (partial)
 * Must be before ready (event happen before ready)
 */
function hideQuestionWithRelevanceSubQuestion(){
    $("[id^='question']").on('relevance:on',"[id^='javatbd']",function(event,data) {
        if(event.target != this) return; // not needed now, but after (2016-11-07)
        data = $.extend({style:'hidden'}, data);
        if(data.style=='hidden'){
            $(this).closest("[id^='question']:not(.ls-unrelevant)").removeClass("ls-hidden")
        }
    });
    $("[id^='question']").on('relevance:off',"[id^='javatbd']",function(event,data) {
        if(event.target != this) return; // not needed now, but after (2016-11-07)
        data = $.extend({style:'hidden'}, data);
        if(data.style=='hidden'){
            if($(this).closest("[id^='question']").find("[id^='javatbd']:visible").length==0){
                $(this).closest("[id^='question']").addClass("ls-hidden");// ls-hidden only is used only for Equation question type actually (but think must fix and use another class)
            }
        }
    });
}
