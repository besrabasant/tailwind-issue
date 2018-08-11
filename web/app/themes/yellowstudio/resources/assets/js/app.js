import "babel-polyfill";

require('./bootstrap');
window.Vue = require('vue');

Vue.component('hello-world', require('./components/HelloWorld.vue'));
Vue.component('home-page-carousel', require('./components/HomePageCarousel.vue'));

const app = new Vue({
    el: '#app'
});
