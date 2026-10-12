// Browser tab titles: "Kartta – JalaOps", and the full name on the home page.
export const SITE_TITLE = 'JalaOps – huoltopyyntöjen hallinta'

export function pageTitle(title) {
  return title ? `${title} – JalaOps` : SITE_TITLE
}
