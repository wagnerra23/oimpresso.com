/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');
import { InertiaApp } from '@inertiajs/inertia-vue';
import VueFormWizard from 'vue-form-wizard';
import commonFunctions from './commonFunctions';
import nprogress from 'nprogress/nprogress.js';
import DataTable from 'laravel-vue-datatable';
import sortable from 'jquery-ui/ui/widgets/sortable.js';
import VueSelect from "vue-select";
window.toastr = require('toastr');
window.Vue = require('vue');
Vue.mixin({ methods: { route_ziggy: window.route } });
Vue.use(InertiaApp);
Vue.use(VueFormWizard);
Vue.use(commonFunctions);
Vue.use(DataTable);
Vue.component('v-select', VueSelect);

Vue.mixin(require('./trans'));
Vue.prototype._ = _;

//common configuration : tinyMCE editor
import tinymce from '../plugins/tinymce/tinymce.min.js';
import '../plugins/tinymce/themes/silver/theme.min.js';
import '../plugins/tinymce/icons/default/icons.min.js';
import '../plugins/tinymce/plugins/paste/plugin.min.js';
import '../plugins/tinymce/plugins/link/plugin.min.js';
import '../plugins/tinymce/plugins/autolink/plugin.min.js';
import '../plugins/tinymce/plugins/lists/plugin.min.js';
import '../plugins/tinymce/plugins/hr/plugin.min.js';
import '../plugins/tinymce/plugins/anchor/plugin.min.js';
import '../plugins/tinymce/plugins/pagebreak/plugin.min.js';
import '../plugins/tinymce/plugins/codesample/plugin.min.js';
import '../plugins/tinymce/plugins/code/plugin.min.js';
import '../plugins/prism/prism.js';
import '../plugins/tinymce/plugins/textcolor/plugin.min.js';
import '../plugins/tinymce/plugins/image/plugin.min.js';
tinymce.overrideDefaults({
  height: 300,
  theme: 'silver',
  plugins: [
    'paste link autolink lists hr anchor pagebreak'
  ],
  toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify |' +
    ' bullist numlist outdent indent | link | forecolor backcolor',
  menubar: 'edit insert'
});

window.select2 = require('../plugins/select2/js/select2.full.min.js');
window.daterangepicker = require('../../node_modules/daterangepicker/daterangepicker.js');
window.Tagify = require('@yaireo/tagify/dist/tagify.min.js');

//disbale all console log if env is not local
if (APP.APP_ENV != 'local') {
  console.log = function() {}  
}

/**
 * The following block of code may be used to automatically register your
 * Vue components. It will recursively scan this directory for the Vue
 * components and automatically register them with their "basename".
 *
 * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
 */

// const files = require.context('./', true, /\.vue$/i)
// files.keys().map(key => Vue.component(key.split('/').pop().split('.')[0], files(key).default))

Vue.component('example-component', require('./components/ExampleComponent.vue').default);
Vue.component('alert', require('./Pages/Elements/Alert.vue').default);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = document.getElementById('app')

new Vue({
  render: h => h(InertiaApp, {
    props: {
      initialPage: JSON.parse(app.dataset.page),
      resolveComponent: name => require(`./Pages/${name}`).default,
    },
  }),
}).$mount(app)