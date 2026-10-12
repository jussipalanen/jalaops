import { describe, expect, it } from 'vitest'
import router from '@/router'

describe('router', () => {
  it('gives each page its own browser tab title', async () => {
    await router.push('/map')
    expect(document.title).toBe('Kartta – JalaOps')

    await router.push('/requests')
    expect(document.title).toBe('Pyynnöt – JalaOps')

    await router.push('/')
    expect(document.title).toBe('JalaOps – huoltopyyntöjen hallinta')
  })
})
