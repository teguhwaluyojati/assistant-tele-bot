import './bootstrap';

import { createApp } from 'vue';
import ExampleComponent from './components/index.vue';
import LoginComponent from './components/login.vue';
import DashboardComponent from './components/dashboard.vue';


const app = createApp({});
app.component('example-component', ExampleComponent);
app.component('login-component', LoginComponent);
app.component('dashboard-component', DashboardComponent);
app.mount('#app');