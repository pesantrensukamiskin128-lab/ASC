import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin } from '@tanstack/vue-query'
import Toast, { type PluginOptions, POSITION } from 'vue-toastification'
import 'vue-toastification/dist/index.css'
import { registerSW } from 'virtual:pwa-register'

import App from './App.vue'
import router from './router'

// Daftarkan service worker — auto-update saat ada versi baru
registerSW({
  onNeedRefresh() {
    // Service worker baru tersedia, auto-update
  },
  onOfflineReady() {
    console.log('ASC siap digunakan offline')
  },
})

const toastOptions: PluginOptions = {
  position: POSITION.TOP_RIGHT,
  timeout: 3000,
  closeOnClick: true,
  pauseOnHover: true,
}

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(VueQueryPlugin)
app.use(Toast, toastOptions)

app.mount('#app')
