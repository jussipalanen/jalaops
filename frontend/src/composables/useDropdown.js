import { onBeforeUnmount, ref, watch } from 'vue'

// Open/close state for a dropdown menu or a disclosure (like the ☰ menu). It
// closes on a click outside `container` (a template ref), on Escape, which
// returns focus to the menu's button, and when keyboard focus leaves it.
export function useDropdown(container) {
  const open = ref(false)

  // composedPath() is the click's path when it happened, so a click on an
  // element that re-renders meanwhile (like a ☰/✕ icon) still counts as inside.
  function onDocumentClick(event) {
    if (container.value && !event.composedPath().includes(container.value)) open.value = false
  }

  function onKeydown(event) {
    if (event.key !== 'Escape') return

    const focusWasInside = container.value?.contains(document.activeElement)
    open.value = false

    if (focusWasInside) {
      container.value.querySelector('[aria-expanded]')?.focus()
    }
  }

  // Tabbing past the last item closes the menu.
  function onFocusOut(event) {
    if (event.relatedTarget && !container.value?.contains(event.relatedTarget)) open.value = false
  }

  function listen(on) {
    const method = on ? 'addEventListener' : 'removeEventListener'
    document[method]('click', onDocumentClick)
    document[method]('keydown', onKeydown)
  }

  watch(open, listen)
  watch(container, (element, previous) => {
    previous?.removeEventListener('focusout', onFocusOut)
    element?.addEventListener('focusout', onFocusOut)
  })
  onBeforeUnmount(() => {
    listen(false)
    container.value?.removeEventListener('focusout', onFocusOut)
  })

  return { open, toggle: () => (open.value = !open.value), close: () => (open.value = false) }
}
