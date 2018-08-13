import "babel-polyfill";

// import external dependencies
// import 'jquery';

// Import everything from autoload
import "./autoload/_bootstrap"

window.Vue = require('vue');

import { library } from '@fortawesome/fontawesome-svg-core'
import { faCoffee } from '@fortawesome/free-solid-svg-icons'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

Vue.config.productionTip = false

Vue.component('font-awesome-icon', FontAwesomeIcon)
Vue.component('home-page-carousel', require('./components/HomePageCarousel.vue'));

// import local dependencies
import Router from './util/Router';
import common from './routes/common';
import home from './routes/home';
import aboutUs from './routes/about';

/** Populate Router instance with DOM routes */
const routes = new Router({
  // All pages
  common,
  // Home page
  home,
  // About Us page, note the change from about-us to aboutUs.
  aboutUs,
});

// Load Events
jQuery(document).ready(() => routes.loadEvents());
