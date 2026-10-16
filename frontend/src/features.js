// Features that are switched off for now. Each turns on when its VITE_ variable
// is "true" at build time (see .env.example).

// The Mobiilisovellukset page (/mobile-apps) and its links, off while Google Play
// Protect warns about the APK downloaded from GitHub.
export const mobileAppsPage = import.meta.env.VITE_MOBILE_APPS_PAGE === 'true'
