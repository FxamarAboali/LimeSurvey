import Vue from 'vue';
import Vuex from 'vuex';
import VueLocalStorage from 'vue-localstorage';
import Sidebar from './components/sidebar.vue';
import Topbar from './components/topbar.vue';
import getAppState from './store/vuex-store.js';

Vue.use(Vuex);
Vue.use(VueLocalStorage);

Vue.mixin({
  methods: {
    updatePjaxLinks: function () {
      this.$store.commit('updatePjax');
    }
  }
});

const AppState = getAppState(LS.globalUserId);

if (document.getElementById('vue-app-main-container')) {
  // eslint-disable-next-line
  const sidemenu = new Vue({
    el: '#vue-app-main-container',
    store: AppState,
    components: {
      'sidebar': Sidebar,
      'topbar': Topbar,
    },
    created() {
      const
        menuOffset = $('nav.navbar').outerHeight(),
        menuHeight = $('.menubar.surveymanagerbar').outerHeight(),
        footerHeight = $('footer').outerHeight() + 35,
        documentHeight = screen.availHeight || screen.height,
        innerMenuHeight = $('#breadcrumb-container').outerHeight(),
        inSurveyViewHeight = (documentHeight - (menuOffset + menuHeight + footerHeight)),
        generalContainerHeright = inSurveyViewHeight-(innerMenuHeight + 45);
        
        
      this.$store.commit('changeInSurveyViewHeight', inSurveyViewHeight);
      this.$store.commit('changeGeneralContainerHeight', generalContainerHeright);

    },
    mounted() {
      const surveyid = $(this.$el).data('surveyid');
      if(surveyid != 0){
        this.$store.commit('updateSurveyId', surveyid);
      }
      const maxHeight = ($('#in_survey_common').height() - 35) || 400;
      this.$store.commit('changeMaxHeight', maxHeight);
      this.updatePjaxLinks();
    }
  });
}


// $(document).on('ready pjax:complete', ()=>{
//   if($('#vue-app-main-container').length >0 ){

//     const
//         menuOffset = $('nav.navbar').outerHeight()+45,
//         menuHeight = $('.menubar.surveymanagerbar').outerHeight(),
//         footerHeight = $('footer').outerHeight()+65,
//         documentHeight = screen.availHeight || screen.height,
//         innerMenuHeight = $('#surveybarid').outerHeight();

//     let vueAppContainerHeight = documentHeight-( menuOffset + menuHeight + footerHeight );
//     let inSurveyCommonHeight = vueAppContainerHeight - (innerMenuHeight + 45);

//     console.log({
//       menuOffset : menuOffset,
//       menuHeight : menuHeight,
//       footerHeight : footerHeight,
//       documentHeight : documentHeight,
//       innerMenuHeight : innerMenuHeight,
//       vueAppContainerHeight : vueAppContainerHeight,
//       inSurveyCommonHeight : inSurveyCommonHeight
//     });

//      $('#vue-app-main-container').css('min-height', vueAppContainerHeight+'px');
//   }
// });
$(document).on('pjax:send', () => {
  $('#pjax-file-load-container').find('div').css({
    'width': '20%',
    'display': 'block'
  });
});
$(document).on('pjax:complete', () => {
  $('#pjax-file-load-container').find('div').css('width', '100%');
  setTimeout(function () {
    $('#pjax-file-load-container').find('div').css({
      'width': '0%',
      'display': 'none'
    });
  }, 2200);
});
// const topmenu = new Vue(
//   {  
//     el: '#vue-top-menu-app',
//     components: {
//       'topbar' : Topbar,
//     } 
// });
