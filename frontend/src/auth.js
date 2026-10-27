import { computed, ref } from 'vue'
import { apiGet, apiPost, onUnauthorized } from '@/api/client'

// The logged-in user, shared by the whole app. The backend keeps the login in
// a session cookie and checks every API call; this only tells the pages who
// is logged in, for the header and the admin-only controls.

export const user = ref(null)
export const isAdmin = computed(() => user.value?.role === 'admin')

let loading = null

// Asks the API who is logged in, once; null when no one is.
export function loadUser() {
  loading ??= apiGet('/user')
    .then(({ data }) => (user.value = data))
    .catch(() => (user.value = null))

  return loading
}

export async function login(email, password) {
  user.value = (await apiPost('/login', { email, password })).data
  loading = Promise.resolve(user.value)
}

// Signs in as the shared demo user ('user') or demo admin ('admin').
export async function demoLogin(role) {
  user.value = (await apiPost('/login/demo', { role })).data
  loading = Promise.resolve(user.value)
}

export async function logout() {
  try {
    await apiPost('/logout')
  } finally {
    forget()
  }
}

function forget() {
  user.value = null
  loading = Promise.resolve(null)
}

// A 401 while logged in means the session expired: the router's guard then
// opens the login page (see installAuthGuard).
let onExpired = () => {}

onUnauthorized(() => {
  if (user.value) {
    forget()
    onExpired()
  }
})

// Pages need a login unless their route has meta.public, and the admin role
// with meta.admin. The login page returns to the page that was asked for
// (?redirect=), e.g. a request's QR code.
export function installAuthGuard(router) {
  router.beforeEach(async (to) => {
    if (to.meta.public) {
      return
    }

    if (!(await loadUser())) {
      return { name: 'login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } }
    }

    if (to.meta.admin && !isAdmin.value) {
      return { name: 'home' }
    }
  })

  onExpired = () => {
    const { fullPath, meta } = router.currentRoute.value

    if (!meta.public) {
      router.push({ name: 'login', query: { redirect: fullPath } })
    }
  }
}
