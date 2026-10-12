#!/usr/bin/env bash
# Builds the Android app (a Trusted Web Activity) with Bubblewrap from
# android/twa-manifest.json, and writes android/dist/jalaops.apk (install on a
# phone) and android/dist/jalaops.aab (upload to Google Play).
#
# Runs in the jalaops-android Docker image (./dev android) or in CI. Needs Node,
# Java 17 (JAVA_HOME), the Android SDK (ANDROID_HOME), Bubblewrap, python3 and
# the upload key:
#   ANDROID_KEYSTORE           path to the keystore (.jks)
#   ANDROID_KEYSTORE_PASSWORD  its password
#   ANDROID_KEY_PASSWORD       the key's password
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
: "${ANDROID_KEYSTORE:?Set ANDROID_KEYSTORE to the upload keystore}"
: "${ANDROID_KEYSTORE_PASSWORD:?Set ANDROID_KEYSTORE_PASSWORD}"
: "${ANDROID_KEY_PASSWORD:?Set ANDROID_KEY_PASSWORD}"

build="$root/android/build"
dist="$root/android/dist"
rm -rf "$build"
mkdir -p "$build" "$dist"
cp "$ANDROID_KEYSTORE" "$build/upload.jks"

# The icons are served from this commit's frontend/public, not from the live
# site, so a build always uses the icons it was committed with.
icons_port=8765
python3 -m http.server "$icons_port" --bind 127.0.0.1 --directory "$root/frontend/public" > /dev/null 2>&1 &
icons_pid=$!
# Also remove the build's copy of the key afterwards, even if the build fails.
trap 'kill "$icons_pid" 2> /dev/null || true; rm -f "$build/upload.jks"' EXIT
sleep 1

# The app's version follows the web app's: 0.24.1 -> version code 2401.
node - "$root" "$icons_port" <<'NODE'
const fs = require('fs')
const [root, port] = process.argv.slice(2)
const { version } = require(`${root}/frontend/package.json`)
const [major, minor, patch] = version.split('.').map(Number)
const manifest = JSON.parse(fs.readFileSync(`${root}/android/twa-manifest.json`, 'utf8'))
const local = (url) => url.replace(`https://${manifest.host}`, `http://127.0.0.1:${port}`)

manifest.appVersion = version
manifest.appVersionCode = major * 10000 + minor * 100 + patch
manifest.iconUrl = local(manifest.iconUrl)
manifest.maskableIconUrl = local(manifest.maskableIconUrl)
manifest.shortcuts = manifest.shortcuts.map((shortcut) => ({ ...shortcut, chosenIconUrl: local(shortcut.chosenIconUrl) }))

fs.writeFileSync(`${root}/android/build/twa-manifest.json`, JSON.stringify(manifest, null, 2))
console.log(`Building JalaOps ${version} (version code ${manifest.appVersionCode})`)
NODE

# Tell Bubblewrap where Java and the Android SDK are, so it never asks.
mkdir -p "$HOME/.bubblewrap"
printf '{"jdkPath":"%s","androidSdkPath":"%s"}\n' "$JAVA_HOME" "$ANDROID_HOME" > "$HOME/.bubblewrap/config.json"

cd "$build"
bubblewrap update --skipVersionUpgrade --manifest=./twa-manifest.json
BUBBLEWRAP_KEYSTORE_PASSWORD="$ANDROID_KEYSTORE_PASSWORD" BUBBLEWRAP_KEY_PASSWORD="$ANDROID_KEY_PASSWORD" \
    bubblewrap build --skipPwaValidation --manifest=./twa-manifest.json

cp app-release-signed.apk "$dist/jalaops.apk"
cp app-release-bundle.aab "$dist/jalaops.aab"
echo "Built android/dist/jalaops.apk and android/dist/jalaops.aab"
