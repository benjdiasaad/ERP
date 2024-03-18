require('./bootstrap');
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-icons/font/bootstrap-icons.css'

import {createApp} from 'vue'
import App from './vue/app.vue'
import bootstrap from 'bootstrap/dist/js/bootstrap.bundle.js'


createApp(App).use(bootstrap).mount("#app");

