// Light and dark theme. The app follows the system setting until the user
// picks a theme with the toggle; that choice is then kept in localStorage.
// index.html applies the same logic before the app loads, so the page never
// flashes in the wrong theme.
import { ref } from 'vue'

const STORAGE_KEY = 'theme'
const THEME_COLORS = { light: '#f8fafc', dark: '#020617' }

export const isDark = ref(false)

function systemQuery() {
  return window.matchMedia?.('(prefers-color-scheme: dark)')
}

function storedTheme() {
  try {
    return localStorage.getItem(STORAGE_KEY)
  } catch {
    // Storage can be unavailable, e.g. in some private windows.
    return null
  }
}

function applyTheme(dark) {
  isDark.value = dark
  document.documentElement.classList.toggle('dark', dark)
  document
    .querySelector('meta[name="theme-color"]')
    ?.setAttribute('content', dark ? THEME_COLORS.dark : THEME_COLORS.light)
}

export function initTheme() {
  const stored = storedTheme()
  applyTheme(stored ? stored === 'dark' : Boolean(systemQuery()?.matches))

  systemQuery()?.addEventListener('change', (event) => {
    if (!storedTheme()) {
      applyTheme(event.matches)
    }
  })
}

export function toggleTheme() {
  applyTheme(!isDark.value)

  try {
    localStorage.setItem(STORAGE_KEY, isDark.value ? 'dark' : 'light')
  } catch {
    // Without storage the choice lasts until the page is reloaded.
  }
}
