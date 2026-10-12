#!/usr/bin/env bash
# Runs inside the jalaops-android image for ./dev android. The upload key lives
# outside the repository in /keys (~/.jalaops/android on the host); on the
# first run it's created there with a random password.
set -euo pipefail

keys=/keys
keystore="$keys/upload.jks"
secrets="$keys/signing.env"

if [ ! -f "$keystore" ]; then
    echo "Creating the upload key in ~/.jalaops/android (first build only)."
    password="$(node -e "console.log(require('crypto').randomBytes(24).toString('base64url'))")"
    keytool -genkeypair -keystore "$keystore" -alias jalaops -keyalg RSA -keysize 2048 -validity 10000 \
        -dname "CN=JalaOps, O=Jussi Alanen, C=FI" -storepass "$password" -keypass "$password" > /dev/null
    # Readable only by the developer.
    (umask 077 && printf 'ANDROID_KEYSTORE_PASSWORD=%s\nANDROID_KEY_PASSWORD=%s\n' "$password" "$password" > "$secrets")
    echo "Back up ~/.jalaops/android: Google Play only accepts updates signed with this key."
fi

set -a
. "$secrets"
set +a
export ANDROID_KEYSTORE="$keystore"

# Android opens the app without an address bar only when the site's
# assetlinks.json lists the signing key's fingerprint.
fingerprint="$(keytool -list -v -keystore "$keystore" -alias jalaops -storepass "$ANDROID_KEYSTORE_PASSWORD" | sed -n 's/^.*SHA256: //p' | tr -d ' \r')"
if ! grep -q "$fingerprint" frontend/public/.well-known/assetlinks.json; then
    echo "Warning: frontend/public/.well-known/assetlinks.json doesn't list this key's fingerprint:"
    echo "  $fingerprint"
    echo "Add it there and deploy the frontend, or the app opens with an address bar."
fi

scripts/android/build.sh
