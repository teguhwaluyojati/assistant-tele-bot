import './bootstrap';

import { createApp } from 'vue';
import ExampleComponent from './components/index.vue';
import LoginComponent from './components/login.vue';


const app = createApp({});
app.component('example-component', ExampleComponent);
app.component('login-component', LoginComponent);
app.mount('#app');