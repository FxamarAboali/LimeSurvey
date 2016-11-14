/**
 * @license This file is part of LimeSurvey
 * See COPYRIGHT.php for copyright notices and details.
 *
 */

/**
 * Change multi numeric question type to slider question type
 *
 * @param {number} qId The qid of the question where apply.
 */

function doNumericSlider(qID,options) {
  $("#vmsg_"+qID+"_default").text(sliderTranslation.help);
  $("#question"+qID+" .slider-container").each(function()
  {
    var inputEl = $(this).find("input:text");
    var myfname = $(inputEl).attr("name");
    var prefix = $(inputEl).data('slider-prefix');
    var suffix = $(inputEl).data('slider-suffix');
    var dispVal= $(inputEl).data('slider-value');
    var separator = $(inputEl).data('separator');
    // We start the slider, and provide it the formated value with prefix and suffix for its tooltip
      var theSlider = $(inputEl).bootstrapSlider({
          formatter: function (value) {
              displayValue = value.toString().replace('.',separator);
              return prefix + displayValue + suffix;
          }
      });
      /* Put some color taken from default boostrap file : allow user to more easily update it*/
      $(this).find(".slider-handle").addClass("bg-primary");// bg-info is not dark enough
      /* If dispVal is not set : move to this : but don't set value : event is set to false,false */
      if(dispVal===''){
        $('#javatbd' + myfname).find('div.tooltip').hide();
        theSlider.bootstrapSlider('setValue', $('#answer' + myfname).data('position'),false,false);
        $(inputEl).val('').trigger('keyup');/* If value is out of range : slider is set to min or max (OK) , but event happen (surely setValue happen a second time here)*/
      }

      // When user change the value of the slider :
      // we need to show the tooltip (if it was hidden)
      // and to update the value of the input element with correct format
      theSlider.on('slideStart', function(){
          $('#javatbd' + myfname).find('div.tooltip').show(); // Show the tooltip
          value = $(inputEl).val(); // We get the current value of the bootstrapSlider
          displayValue = value.toString().replace('.',separator); // We format it with the right separator
          $(inputEl).val(displayValue); // We parse it to the element
      });
      theSlider.on('change', function(event) {
      });
      theSlider.on('slideStop', function(event) {
          $(inputEl).val(event.value.toString().replace('.',separator)).trigger('keyup');// We call the EM by the event
      });

      /* reset action */
      $('#answer' + myfname + '_resetslider').on('click', function() {
          /* Position slider button at position */
          theSlider.bootstrapSlider('setValue', $('#answer' + myfname).data('position'));
          /* if don't set position : reset to '' */
          if(!$('#answer' + myfname).data('set-position')){
            $('#javatbd' + myfname).find('div.tooltip').hide();
            $(inputEl).val('').trigger('keyup');
          }else{
            $(inputEl).trigger('keyup');
          }
      });

  });

}
