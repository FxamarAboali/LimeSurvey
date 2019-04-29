import Vue from 'vue';
import CKEditor from '@ckeditor/ckeditor5-vue';

import DataSecurityApp from './DataSecurityApp.vue';

import getAppState from "./storage/store";
import {PluginLog} from "./mixins/logSystem";

//Ignore phpunits testing tags
Vue.config.ignoredElements = ["x-test"];

Vue.use( PluginLog );
Vue.use( CKEditor );

Vue.mixin({
    methods: {
        toggleLoading(forceState=null) {
            if(forceState !== null) {
                if(forceState) {
                    $('#datasecTextEditLoader').fadeIn(200);
                } else {
                    $('#datasecTextEditLoader').fadeOut(400);
                }
                return;
            }
            if($('#datasecTextEditLoader').css('display') == 'none') {
                $('#datasecTextEditLoader').fadeIn(200);
                return;
            }
            $('#datasecTextEditLoader').fadeOut(400);
        },
        translate(value) {
            return window.DataSecTextEditData.i10N[value] || value;
        }
    },
    filters: {
        translate: (value) => {
            return window.DataSecTextEditData.i10N[value] || value;
        }
    }
});
const AppState = getAppState(LS.parameters.surveyid || 0);
const questionEditor = new Vue({
    el: '#advancedDataSecurityTextEditor',
    store: AppState,
    components: {lsdatasectexteditor: DataSecurityApp},
});