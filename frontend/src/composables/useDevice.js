// The visitor's device, for pages that work differently on a phone or tablet
// than on a computer (a QR code is scanned with another device, so it's shown
// where someone else can scan it).
export function useDevice() {
  const userAgent = window.navigator.userAgent

  // iPads report themselves as Macs, but unlike Macs they have a touch screen.
  const platform = /Android/i.test(userAgent)
    ? 'android'
    : /iPhone|iPad|iPod/.test(userAgent) || (/Macintosh/.test(userAgent) && window.navigator.maxTouchPoints > 1)
      ? 'ios'
      : null

  // Inside the installed app (the Android app or an iPhone home screen icon).
  const installed = Boolean(window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone)

  // A phone or a tablet: the screen people hold in their hand.
  const handheld = platform !== null || installed

  return { platform, installed, handheld }
}
