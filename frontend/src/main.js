import './assets/main.css'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { initTheme } from './theme'

initTheme()

const app = createApp(App)

app.use(router)

app.mount('#app')

// The service worker shows an offline page without a connection; it makes the
// app installable, which the Android app needs. Not in development, where it
// would get in the way of hot reloading.
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'))
}
