// Browser tab titles: "Kartta – JalaOps", and the full name on the home page.
export const SITE_TITLE = 'JalaOps – huoltopyyntöjen hallinta'

export function pageTitle(title) {
  return title ? `${title} – JalaOps` : SITE_TITLE
}

// The page's description meta. A module's view can change it for its page
// (jalaops.setPage); null puts back the app's own.
let appDescription

export function setDescription(text) {
  const meta = document.querySelector('meta[name="description"]')
  if (!meta) return
  appDescription ??= meta.content
  meta.content = text ?? appDescription
}
