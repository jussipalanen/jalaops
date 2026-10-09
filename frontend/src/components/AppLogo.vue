<script setup>
// JalaOps mark: a gear (operations) with a check mark (a handled service
// request). The tile uses the theme's primary color, so it is blue in the
// light theme and green in the dark theme.
const gradientId = 'jalaops-logo-gradient'
</script>

<template>
  <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <defs>
      <linearGradient :id="gradientId" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="var(--color-primary-400)" />
        <stop offset="1" stop-color="var(--color-primary-700)" />
      </linearGradient>
    </defs>
    <rect width="64" height="64" rx="15" :fill="`url(#${gradientId})`" />
    <g fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="32" cy="32" r="19" stroke-width="6" stroke-linecap="butt" stroke-dasharray="6.5 8.42" />
      <circle cx="32" cy="32" r="14" stroke-width="5" />
      <path d="M25 32.5l5 5 9-10" stroke-width="4.5" />
    </g>
  </svg>
</template>
