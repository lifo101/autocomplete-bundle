import {createApp} from 'vue';
import MultiselectAjax from './MultiselectAjax.vue';

Array.from(document.getElementsByClassName('lifo-vue-multiselect')).forEach(el => {
    createApp({components: {MultiselectAjax}}).mount(el);
})
