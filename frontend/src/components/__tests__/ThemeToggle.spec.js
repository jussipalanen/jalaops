import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import ThemeToggle from '../ThemeToggle.vue'
import { initTheme } from '@/theme'

describe('ThemeToggle', () => {
  afterEach(() => {
    localStorage.clear()
    document.documentElement.classList.remove('dark')
  })

  it('switches between light and dark and remembers the choice', async () => {
    initTheme()
    const wrapper = mount(ThemeToggle)

    expect(document.documentElement.classList.contains('dark')).toBe(false)
    expect(wrapper.attributes('aria-label')).toBe('Vaihda tummaan teemaan')

    await wrapper.trigger('click')

    expect(document.documentElement.classList.contains('dark')).toBe(true)
    expect(localStorage.getItem('theme')).toBe('dark')
    expect(wrapper.attributes('aria-label')).toBe('Vaihda vaaleaan teemaan')

    await wrapper.trigger('click')

    expect(document.documentElement.classList.contains('dark')).toBe(false)
    expect(localStorage.getItem('theme')).toBe('light')
  })

  it('uses a saved theme', () => {
    localStorage.setItem('theme', 'dark')
    initTheme()

    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })
})
