<script setup>
import { computed, useTemplateRef } from 'vue'
import { useRouter } from 'vue-router'
import { logout, user } from '@/auth'
import { useDropdown } from '@/composables/useDropdown'

// The logged-in user in the header: a round button with their initials, and
// a menu with their name, email and role and the logout button.

const router = useRouter()
const menu = useTemplateRef('menu')
const { open, toggle, close } = useDropdown(menu)

const initials = computed(() =>
  user.value.name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join(''),
)

async function logOut() {
  close()

  try {
    await logout()
  } finally {
    router.push({ name: 'login' })
  }
}
</script>

<template>
  <div ref="menu" class="relative">
    <button
      type="button"
      class="grid size-10 cursor-pointer place-items-center rounded-full bg-primary-100 text-sm font-bold text-primary-800 transition-colors hover:bg-primary-200 dark:bg-primary-500/15 dark:text-primary-300 dark:hover:bg-primary-500/25"
      :aria-expanded="open"
      aria-haspopup="true"
      :aria-label="`Käyttäjä: ${user.name}`"
      :title="user.name"
      @click="toggle"
    >
      {{ initials }}
    </button>
    <div
      v-show="open"
      class="user-menu absolute right-0 z-30 mt-1 w-64 max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-slate-200 bg-white text-left shadow-lg dark:border-slate-700 dark:bg-slate-900"
    >
      <div class="grid gap-1 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
        <p class="truncate font-semibold">{{ user.name }}</p>
        <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ user.email }}</p>
        <p>
          <span class="badge" :class="user.role === 'admin' ? 'badge-new' : 'priority-low'">{{ user.role_label }}</span>
        </p>
      </div>
      <button
        type="button"
        class="flex min-h-11 w-full cursor-pointer items-center px-4 font-semibold hover:bg-slate-100 lg:min-h-9 dark:hover:bg-slate-800"
        @click="logOut"
      >
        Kirjaudu ulos
      </button>
    </div>
  </div>
</template>
