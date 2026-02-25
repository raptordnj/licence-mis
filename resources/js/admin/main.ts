import { createPinia } from 'pinia';
import { createApp } from 'vue';

import App from '@/admin/App.vue';
import { registerAuthGuard, router } from '@/admin/router';
import { useAuthStore } from '@/admin/stores/auth';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);

const authStore = useAuthStore(pinia);
registerAuthGuard(authStore);

app.use(router);

void router.isReady().then(() => {
    app.mount('#app');
});
