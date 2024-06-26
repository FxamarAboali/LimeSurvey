/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/main.js":
/*!*********************!*\
  !*** ./src/main.js ***!
  \*********************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _timeclass__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./timeclass */ \"./src/timeclass.js\");\n/**\n * @file Script for timer\n * @copyright LimeSurvey <http://www.limesurvey.org>\n * @license magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3-or-Later\n */\n\n\nwindow.countdown = function countdown(questionid, surveyid, timer, action, warning, warning2, warninghide, warning2hide, disable) {\n  window.timerObjectSpace = window.timerObjectSpace || {};\n  if (!window.timerObjectSpace[questionid]) {\n    window.timerObjectSpace[questionid] = new _timeclass__WEBPACK_IMPORTED_MODULE_0__[\"default\"]({\n      questionid: questionid,\n      surveyid: surveyid,\n      timer: timer,\n      action: action,\n      warning: warning,\n      warning2: warning2,\n      warninghide: warninghide,\n      warning2hide: warning2hide,\n      disabledElement: disable\n    });\n    window.timerObjectSpace[questionid].startTimer();\n  }\n};\n\n//# sourceURL=webpack:///./src/main.js?");

/***/ }),

/***/ "./src/timeclass.js":
/*!**************************!*\
  !*** ./src/timeclass.js ***!
  \**************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   \"default\": () => (/* binding */ TimerConstructor)\n/* harmony export */ });\nfunction _typeof(o) { \"@babel/helpers - typeof\"; return _typeof = \"function\" == typeof Symbol && \"symbol\" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && \"function\" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? \"symbol\" : typeof o; }, _typeof(o); }\nfunction _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError(\"Cannot call a class as a function\"); } }\nfunction _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if (\"value\" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }\nfunction _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, \"prototype\", { writable: false }); return Constructor; }\nfunction _toPropertyKey(t) { var i = _toPrimitive(t, \"string\"); return \"symbol\" == _typeof(i) ? i : i + \"\"; }\nfunction _toPrimitive(t, r) { if (\"object\" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || \"default\"); if (\"object\" != _typeof(i)) return i; throw new TypeError(\"@@toPrimitive must return a primitive value.\"); } return (\"string\" === r ? String : Number)(t); }\n/**\n * @file Script for timer\n * @copyright LimeSurvey <http://www.limesurvey.org>\n * @license magnet:?xt=urn:btih:1f739d935676111cfff4b4693e3816e664797050&dn=gpl-3.0.txt GPL-v3-or-Later\n */\nvar TimerConstructor = /*#__PURE__*/function () {\n  function TimerConstructor(options) {\n    var _this = this;\n    _classCallCheck(this, TimerConstructor);\n    /* ##### define state and closure vars ##### */\n    this.option = this._parseOptions(options);\n    this.timerWarning = null;\n    this.timerWarning2 = null;\n    this.timerLogger = new ConsoleShim('TIMER#' + options.questionid, !window.debugState.frontend);\n    this.intervalObject = null;\n    this.warning = 0;\n    this.timersessionname = 'timer_question_' + this.option.questionid;\n    this.surveyTimersItemName = 'limesurvey_timers_by_sid_' + this.option.surveyid;\n\n    // Unser timer in local storage if the reset timers flag is set\n    if (LSvar.bResetQuestionTimers) this._unsetTimerInLocalStorage();\n    this.timeLeft = this._getTimerFromLocalStorage() || this.option.timer;\n    this.disable_next = $(\"#disablenext-\" + this.timersessionname).val();\n    this.disable_prev = $(\"#disableprev-\" + this.timersessionname).val();\n\n    //jQuery Elements\n    this.$timerDisplayElement = function () {\n      return $('#LS_question' + _this.option.questionid + '_Timer');\n    };\n    this.$timerExpiredElement = $('#question' + this.option.questionid + '_timer');\n    this.$warningTimeDisplayElement = $('#LS_question' + this.option.questionid + '_Warning');\n    this.$warningDisplayElement = $('#LS_question' + this.option.questionid + '_warning');\n    this.$warning2TimeDisplayElement = $('#LS_question' + this.option.questionid + '_Warning_2');\n    this.$warning2DisplayElement = $('#LS_question' + this.option.questionid + '_warning_2');\n    this.$countDownMessageElement = $(\"#countdown-message-\" + this.timersessionname);\n    this.redirectWarnTime = $('#message-delay-' + this.timersessionname).val();\n    this.$toBeDisabledElement = $('#' + this.option.disabledElement);\n    this.timerLogger.log('Options set:', this.option);\n    return {\n      startTimer: function startTimer() {\n        return _this.startTimer.apply(_this);\n      },\n      finishTimer: function finishTimer() {\n        return _this.finishTimer.apply(_this);\n      }\n    };\n  }\n  return _createClass(TimerConstructor, [{\n    key: \"_parseOptions\",\n    value: /* ##### private methods ##### */\n    /**\n     * Parses the options to default values if not set\n     * @param Object options \n     * @return Object \n     */\n    function _parseOptions(option) {\n      return {\n        questionid: option.questionid || null,\n        surveyid: option.surveyid || null,\n        timer: option.timer || 0,\n        action: option.action || 1,\n        warning: option.warning || 0,\n        warning2: option.warning2 || 0,\n        warninghide: option.warninghide || 0,\n        warning2hide: option.warning2hide || 0,\n        disabledElement: option.disabledElement || null\n      };\n    }\n\n    /**\n     * Takes a duration in seconds and creates an object containing the duration in hours, minutes and seconds\n     * @param int seconds The duration in seconds\n     * @return Object Contains hours, minutes and seconds\n     */\n  }, {\n    key: \"_parseTimeToObject\",\n    value: function _parseTimeToObject(secLeft, asStrings) {\n      asStrings = asStrings || false;\n      var oDuration = moment.duration(secLeft, 'seconds');\n      var sHours = String(oDuration.hours()),\n        sMinutes = String(oDuration.minutes()),\n        sSeconds = String(oDuration.seconds());\n      return {\n        hours: asStrings ? sHours.length == 1 ? '0' + sHours : sHours : parseInt(sHours),\n        minutes: asStrings ? sMinutes.length == 1 ? '0' + sMinutes : sMinutes : parseInt(sMinutes),\n        seconds: asStrings ? sSeconds.length == 1 ? '0' + sSeconds : sSeconds : parseInt(sSecond)\n      };\n    }\n\n    /**\n     * The actions done on each step and the trigger to the finishing action\n     */\n  }, {\n    key: \"_intervalStep\",\n    value: function _intervalStep() {\n      var currentTimeLeft = this._getTimerFromLocalStorage();\n      currentTimeLeft = parseInt(currentTimeLeft) - 1;\n      this.timerLogger.log('Interval emitted | seconds left:', currentTimeLeft);\n      if (currentTimeLeft <= 0) {\n        this.finishTimer();\n      }\n      this._checkForWarning(currentTimeLeft);\n      this._setTimerToLocalStorage(currentTimeLeft);\n      this._setTimer(currentTimeLeft);\n    }\n\n    /**\n     * Set the interval to update the timer visuals\n     */\n  }, {\n    key: \"_setInterval\",\n    value: function _setInterval() {\n      var _this2 = this;\n      if (this._existsDisplayElement()) {\n        this._setTimer(this.option.timer);\n        this.intervalObject = setInterval(function () {\n          return _this2._intervalStep.apply(_this2);\n        }, 1000);\n      }\n    }\n\n    /**\n     * Unset the timer;\n     */\n  }, {\n    key: \"_unsetInterval\",\n    value: function _unsetInterval() {\n      clearInterval(this.intervalObject);\n      this.intervalObject = null;\n    }\n  }, {\n    key: \"_existsDisplayElement\",\n    value: function _existsDisplayElement() {\n      if (!this.$timerDisplayElement().length > 0) {\n        this._unsetInterval();\n        return false;\n      }\n      return true;\n    }\n\n    /**\n     * Sets the timer to the display element\n     */\n  }, {\n    key: \"_setTimer\",\n    value: function _setTimer(currentTimeLeft) {\n      var timeObject = this._parseTimeToObject(currentTimeLeft, true);\n      if (this._existsDisplayElement()) {\n        this.$timerDisplayElement().css({\n          display: 'flex'\n        }).html(this.$countDownMessageElement.html() + \"&nbsp;&nbsp;<div class='ls-timer-time'>\" + timeObject.hours + ':' + timeObject.minutes + ':' + timeObject.seconds + \"</div>\");\n      }\n    }\n\n    /**\n     * Checks if a warning should be shown relative to the interval\n     * @param int currentTime The current amount of seconds gone\n     */\n  }, {\n    key: \"_checkForWarning\",\n    value: function _checkForWarning(currentTime) {\n      if (currentTime == this.option.warning) {\n        this._showWarning();\n      }\n      if (currentTime == this.option.warning2) {\n        this._showWarning2();\n      }\n    }\n    /**\n     * Shows the warning and fades it out after the set amount of time\n     */\n  }, {\n    key: \"_showWarning\",\n    value: function _showWarning() {\n      var _this3 = this;\n      if (this.option.warning !== 0) {\n        this.timerLogger.log('Warning called!');\n        var timeObject = this._parseTimeToObject(this.option.warning, true);\n        this.$warningTimeDisplayElement.html(timeObject.hours + ':' + timeObject.minutes + ':' + timeObject.seconds);\n        this.$warningDisplayElement.removeClass('hidden d-none').css({\n          opacity: 0\n        }).animate({\n          'opacity': 1\n        }, 200);\n        setTimeout(function () {\n          _this3.timerLogger.log('Warning ended!');\n          _this3.$warningDisplayElement.animate({\n            opacity: 0\n          }, 200, function () {\n            _this3.$warningDisplayElement.addClass('hidden d-none');\n          });\n        }, 1000 * this.option.warninghide);\n      }\n    }\n\n    /**\n     * Shows the warning2 and fades it out after the set amount of time\n     */\n  }, {\n    key: \"_showWarning2\",\n    value: function _showWarning2() {\n      var _this4 = this;\n      if (this.option.warning2 !== 0) {\n        this.timerLogger.log('Warning2 called!');\n        var timeObject = this._parseTimeToObject(this.option.warning, true);\n        this.$warning2TimeDisplayElement.html(timeObject.hours + ':' + timeObject.minutes + ':' + timeObject.seconds);\n        this.$warning2DisplayElement.removeClass('hidden d-none').css({\n          opacity: 0\n        }).animate({\n          'opacity': 1\n        }, 200);\n        setTimeout(function () {\n          _this4.timerLogger.log('Warning2 ended!');\n          _this4.$warning2DisplayElement.animate({\n            opacity: 0\n          }, 200, function () {\n            _this4.$warning2DisplayElement.addClass('hidden d-none');\n          });\n        }, 1000 * this.option.warning2hide);\n      }\n    }\n\n    /**\n     * Disables the navigation buttons if necessary\n     */\n  }, {\n    key: \"_disableNavigation\",\n    value: function _disableNavigation() {\n      var _this5 = this;\n      this.timerLogger.log('Disabling navigation');\n      $('.ls-move-previous-btn').each(function (i, item) {\n        $(item).prop('disabled', _this5.disable_prev == 1);\n      });\n      $('.ls-move-next-btn,.ls-move-submit-btn').each(function (i, item) {\n        $(item).prop('disabled', _this5.disable_next == 1);\n      });\n    }\n\n    /**\n     * Enables the navigation buttons\n     */\n  }, {\n    key: \"_enableNavigation\",\n    value: function _enableNavigation() {\n      $('.ls-move-previous-btn').each(function () {\n        $(this).prop('disabled', false);\n      });\n      $('.ls-move-next-btn,.ls-move-submit-btn').each(function () {\n        $(this).prop('disabled', false);\n      });\n    }\n\n    /**\n     * Gets the current timer from the localStorage\n     */\n  }, {\n    key: \"_getTimerFromLocalStorage\",\n    value: function _getTimerFromLocalStorage() {\n      if (!window.localStorage) {\n        return null;\n      }\n      var timeLeft = window.localStorage.getItem('limesurvey_timers_' + this.timersessionname);\n      return !isNaN(parseInt(timeLeft)) ? timeLeft : 0;\n    }\n\n    /**\n     * Sets the current timer to localStorage\n     */\n  }, {\n    key: \"_setTimerToLocalStorage\",\n    value: function _setTimerToLocalStorage(timerValue) {\n      if (!window.localStorage) {\n        return;\n      }\n      window.localStorage.setItem('limesurvey_timers_' + this.timersessionname, timerValue);\n    }\n\n    /**\n     * Appends the current timer's qid to the list of timers for the survey\n     */\n  }, {\n    key: \"_appendTimerToSurveyTimersList\",\n    value: function _appendTimerToSurveyTimersList() {\n      if (!window.localStorage) {\n        return;\n      }\n      var timers = JSON.parse(window.localStorage.getItem(this.surveyTimersItemName) || \"[]\");\n      if (!timers.includes(this.timersessionname)) timers.push(this.timersessionname);\n      window.localStorage.setItem(this.surveyTimersItemName, JSON.stringify(timers));\n    }\n\n    /**\n     * Unsets the timer in localStorage\n     */\n  }, {\n    key: \"_unsetTimerInLocalStorage\",\n    value: function _unsetTimerInLocalStorage() {\n      if (!window.localStorage) {\n        return;\n      }\n      window.localStorage.removeItem('limesurvey_timers_' + this.timersessionname);\n    }\n\n    /**\n     * Finalize Method to show a warning and then redirect\n     */\n  }, {\n    key: \"_warnBeforeRedirection\",\n    value: function _warnBeforeRedirection() {\n      this._disableInput();\n      setTimeout(this._redirectOut, this.redirectWarnTime);\n    }\n\n    /**\n     * Finalize method to just diable the input\n     */\n  }, {\n    key: \"_disableInput\",\n    value: function _disableInput() {\n      this.$toBeDisabledElement.prop('readonly', true);\n      $('#question' + this.option.questionid).find('.answer-container').children('div').not('.timer_header').fadeOut();\n    }\n\n    /**\n     * Show the notice that the time is up and the input is expired\n     */\n  }, {\n    key: \"_showExpiredNotice\",\n    value: function _showExpiredNotice() {\n      this.$timerExpiredElement.removeClass('hidden d-none');\n    }\n\n    /**\n     * redirect to the next page\n     */\n  }, {\n    key: \"_redirectOut\",\n    value: function _redirectOut() {\n      $('#ls-button-submit').trigger('click');\n    }\n    /**\n     * Binds the reset of the localStorage as soon as the participant has submitted the form\n     */\n  }, {\n    key: \"_bindUnsetToSubmit\",\n    value: function _bindUnsetToSubmit() {\n      var _this6 = this;\n      $('#limesurvey').on('submit', function () {\n        _this6._unsetTimerInLocalStorage();\n      });\n    }\n\n    /* ##### public methods ##### */\n\n    /**\n     * Finishing action\n     * Unsets all timers and intervals and then triggers the defined action.\n     * Either redirect, invalidate or warn before redirect\n     */\n  }, {\n    key: \"finishTimer\",\n    value: function finishTimer() {\n      this.timerLogger.log('Timer has ended or was ended');\n      this._unsetInterval();\n      this._enableNavigation();\n      this._bindUnsetToSubmit();\n      this._disableInput();\n      switch (this.option.action) {\n        case 3:\n          //Just warn, don't move on\n          this._showExpiredNotice();\n          break;\n        case 2:\n          //Just move on, no warning\n          this._redirectOut();\n          break;\n        case 1: //fallthrough\n        default:\n          //Warn and move on\n          this._showExpiredNotice();\n          this._warnBeforeRedirection();\n          break;\n      }\n    }\n\n    /** \n     * Starts the timer\n     * Sts the interval to visualize the timer and the timeouts for the warnings.\n     */\n  }, {\n    key: \"startTimer\",\n    value: function startTimer() {\n      if (this.timeLeft == 0) {\n        this.finishTimer();\n        return;\n      }\n      this._appendTimerToSurveyTimersList();\n      this._setTimerToLocalStorage(this.timeLeft);\n      this._disableNavigation();\n      this._setInterval();\n    }\n  }]);\n}();\n\n;\n\n//# sourceURL=webpack:///./src/timeclass.js?");

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module can't be inlined because the eval devtool is used.
/******/ 	var __webpack_exports__ = __webpack_require__("./src/main.js");
/******/ 	
/******/ })()
;