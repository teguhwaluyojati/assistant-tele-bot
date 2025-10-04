import './bootstrap';

import { createApp } from 'vue';
import ExampleComponent from './components/index.vue';

const app = createApp({});
app.component('example-component', ExampleComponent);
app.mount('#app');