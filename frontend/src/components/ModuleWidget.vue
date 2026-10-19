<script setup>
import { RouterLink } from 'vue-router'

// A module's widget, drawn by the app from what the module returned: a feed
// of items (title, text, date, image, tags). Each item opens the module's
// page with the item's parameters (/tools/modules/news?news=3). The image is
// always a data URL (the backend checks it), so nothing loads from elsewhere.

const props = defineProps({
  widget: { type: Object, required: true },
})

const dateTime = new Intl.DateTimeFormat('fi-FI', {
  day: 'numeric',
  month: 'numeric',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

function formatDate(iso) {
  const date = new Date(iso)
  return Number.isNaN(date.getTime()) ? '' : dateTime.format(date)
}

function itemLink(item) {
  return { path: `/tools/modules/${props.widget.module}`, query: item.params }
}
</script>

<template>
  <section class="card card-body" :aria-labelledby="`widget-${widget.id}`">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
      <h2 :id="`widget-${widget.id}`" class="text-lg font-semibold">{{ widget.title }}</h2>
      <RouterLink
        :to="`/tools/modules/${widget.module}`"
        class="inline-flex min-h-11 items-center text-sm font-semibold text-primary-700 hover:underline lg:min-h-8 dark:text-primary-400"
      >
        Näytä kaikki →
      </RouterLink>
    </div>

    <p v-if="widget.error" class="alert-error mt-3 mb-0" role="alert">{{ widget.error }}</p>
    <p v-else-if="!widget.items.length" class="mt-2 text-slate-500 dark:text-slate-400">Ei vielä sisältöä.</p>

    <ul v-else class="mt-3 grid gap-1">
      <li v-for="(item, index) in widget.items" :key="index">
        <RouterLink
          :to="itemLink(item)"
          class="-mx-2 flex items-start gap-3 rounded-lg p-2 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60"
        >
          <img
            v-if="item.image"
            :src="item.image"
            alt=""
            class="aspect-[4/3] w-20 flex-none rounded-md object-cover sm:w-24"
          />
          <span
            v-else
            class="grid aspect-[4/3] w-20 flex-none place-items-center rounded-md bg-primary-100 text-xl font-bold text-primary-700 sm:w-24 dark:bg-primary-500/15 dark:text-primary-300"
            aria-hidden="true"
          >
            {{ item.title.charAt(0).toUpperCase() }}
          </span>
          <span class="min-w-0">
            <span class="block font-semibold">{{ item.title }}</span>
            <span v-if="item.text" class="line-clamp-2 block text-sm text-slate-600 dark:text-slate-400">{{ item.text }}</span>
            <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">
              <time v-if="item.date" :datetime="item.date">{{ formatDate(item.date) }}</time>
              <template v-for="tag in item.tags" :key="tag"> · #{{ tag }}</template>
            </span>
          </span>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
