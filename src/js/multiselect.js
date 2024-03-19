import Vue from 'vue';
import MultiselectAjax from './MultiselectAjax.vue';

Array.from(document.getElementsByClassName('lifo-vue-multiselect')).forEach(el => {
    new Vue({el, components: {MultiselectAjax}})
})
