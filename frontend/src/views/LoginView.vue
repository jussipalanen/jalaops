<script setup>
import { onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { apiGet, ApiError } from '@/api/client'
import { demoLogin, login } from '@/auth'
import AppLogo from '@/components/AppLogo.vue'

// The login window. In demo mode (and local development) it also has buttons
// that sign in as the shared demo user or demo admin without a password.

const route = useRoute()
const router = useRouter()

const form = reactive({ email: '', password: '' })
const errors = ref({})
const error = ref('')
// What is being sent: 'password', 'user' or 'admin'.
const busy = ref('')
const demoButtons = ref(false)

onMounted(async () => {
  try {
    demoButtons.value = (await apiGet('/login')).demo_login === true
  } catch {
    // The form still works without the answer, only the demo buttons are left out.
  }
})

// Back to the page that was asked for, but only to this app's own pages.
function goOn() {
  const redirect = String(route.query.redirect ?? '')
  router.replace(redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/')
}

async function send(kind, action) {
  busy.value = kind
  errors.value = {}
  error.value = ''

  try {
    await action()
    goOn()
  } catch (exception) {
    if (exception instanceof ApiError && exception.status === 422) {
      errors.value = exception.errors
    } else if (exception instanceof ApiError && exception.status === 429) {
      error.value = 'Liian monta kirjautumisyritystä. Odota hetki ja yritä uudelleen.'
    } else {
      error.value = 'Kirjautuminen epäonnistui. Yritä hetken kuluttua uudelleen.'
    }
  } finally {
    busy.value = ''
  }
}

const submit = () => send('password', () => login(form.email, form.password))
const tryDemo = (role) => send(role, () => demoLogin(role))
</script>

<template>
  <!-- Always dark (the `dark` class), in the theme's color: blue or green like the logo. -->
  <div class="login-space dark grid place-items-center px-4 py-10 sm:py-16">
    <div class="grid w-full max-w-md gap-6">
      <div class="grid justify-items-center gap-3 text-center">
        <AppLogo class="size-16 drop-shadow-[0_0_24px_var(--color-primary-500)]" />
        <h1 class="text-3xl font-bold tracking-tight text-white">
          Jala<span class="text-primary-400">Ops</span>
        </h1>
        <p class="text-slate-400">Huoltopyyntöjen hallinta. Kirjaudu sisään jatkaaksesi.</p>
      </div>

      <div class="grid gap-5 rounded-2xl border border-white/10 bg-slate-900/80 p-5 shadow-2xl shadow-black/50 sm:p-7">
        <p v-if="error" class="alert-error mb-0" role="alert">{{ error }}</p>

        <section v-if="demoButtons" class="grid gap-3" aria-labelledby="demo-heading">
          <h2 id="demo-heading" class="text-lg font-bold text-white">Kokeile demoa</h2>
          <p class="text-sm text-slate-400">
            Kirjaudu yhdellä napautuksella, ilman salasanaa. Ylläpitäjä voi lisäksi ottaa moduuleja käyttöön ja poistaa
            niitä käytöstä.
          </p>
          <div class="grid gap-2">
            <button type="button" class="btn btn-primary" :disabled="Boolean(busy)" @click="tryDemo('user')">
              {{ busy === 'user' ? 'Kirjaudutaan…' : 'Kokeile käyttäjänä' }}
            </button>
            <button type="button" class="btn btn-secondary" :disabled="Boolean(busy)" @click="tryDemo('admin')">
              {{ busy === 'admin' ? 'Kirjaudutaan…' : 'Kokeile ylläpitäjänä' }}
            </button>
          </div>
        </section>

        <div v-if="demoButtons" class="flex items-center gap-3 text-sm text-slate-500" aria-hidden="true">
          <span class="h-px flex-1 bg-white/10"></span>
          tai kirjaudu tunnuksilla
          <span class="h-px flex-1 bg-white/10"></span>
        </div>

        <form class="grid gap-5" aria-label="Kirjaudu tunnuksilla" novalidate @submit.prevent="submit">
          <div class="grid gap-1.5">
            <label for="email" class="text-[0.9375rem] font-semibold">Sähköposti</label>
            <input
              id="email"
              v-model="form.email"
              class="form-control"
              type="email"
              autocomplete="username"
              :aria-invalid="Boolean(errors.email)"
              :aria-describedby="errors.email ? 'email-error' : undefined"
            />
            <p v-if="errors.email" id="email-error" class="text-sm text-red-400">{{ errors.email[0] }}</p>
          </div>

          <div class="grid gap-1.5">
            <label for="password" class="text-[0.9375rem] font-semibold">Salasana</label>
            <input
              id="password"
              v-model="form.password"
              class="form-control"
              type="password"
              autocomplete="current-password"
              :aria-invalid="Boolean(errors.password)"
              :aria-describedby="errors.password ? 'password-error' : undefined"
            />
            <p v-if="errors.password" id="password-error" class="text-sm text-red-400">{{ errors.password[0] }}</p>
          </div>

          <button type="submit" class="btn btn-primary" :disabled="Boolean(busy)">
            {{ busy === 'password' ? 'Kirjaudutaan…' : 'Kirjaudu' }}
          </button>
        </form>
      </div>

      <!-- Public pages; the privacy page is also linked from the Google Play listing. -->
      <nav class="flex justify-center gap-4 text-sm" aria-label="Tietoa sovelluksesta">
        <RouterLink to="/about" class="text-slate-400 hover:text-white hover:underline">Tietoja</RouterLink>
        <RouterLink to="/privacy" class="text-slate-400 hover:text-white hover:underline">Tietosuoja</RouterLink>
      </nav>
    </div>
  </div>
</template>
