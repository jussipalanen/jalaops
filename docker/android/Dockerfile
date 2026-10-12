# Image for building the Android app (a Trusted Web Activity) with Bubblewrap.
# Used by ./dev android, so building needs only Docker on the developer's machine.
FROM node:22-bookworm

ARG BUBBLEWRAP_VERSION=1.27.0
# Android command-line tools (sdkmanager); the number is Google's build id.
ARG ANDROID_CMDLINE_TOOLS=11076708
# The versions Bubblewrap 1.27 builds with (its BUILD_TOOLS_VERSION and compileSdkVersion).
ARG ANDROID_BUILD_TOOLS=36.1.0
ARG ANDROID_PLATFORM=36

ENV JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64 \
    ANDROID_HOME=/opt/android-sdk
ENV PATH="${ANDROID_HOME}/cmdline-tools/latest/bin:${PATH}"

RUN apt-get update \
    && apt-get install -y --no-install-recommends openjdk-17-jdk-headless unzip \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p "$ANDROID_HOME/cmdline-tools" \
    && curl -fsSL -o /tmp/cmdline-tools.zip "https://dl.google.com/android/repository/commandlinetools-linux-${ANDROID_CMDLINE_TOOLS}_latest.zip" \
    && unzip -q /tmp/cmdline-tools.zip -d "$ANDROID_HOME/cmdline-tools" \
    && mv "$ANDROID_HOME/cmdline-tools/cmdline-tools" "$ANDROID_HOME/cmdline-tools/latest" \
    && rm /tmp/cmdline-tools.zip \
    && yes | sdkmanager --licenses > /dev/null \
    && sdkmanager --install "platform-tools" "platforms;android-${ANDROID_PLATFORM}" "build-tools;${ANDROID_BUILD_TOOLS}" > /dev/null \
    # Bubblewrap expects sdkmanager in <sdk>/bin, as its own installer lays it out.
    && ln -s cmdline-tools/latest/bin "$ANDROID_HOME/bin" \
    && ln -s cmdline-tools/latest/lib "$ANDROID_HOME/lib" \
    # The build runs as the developer's own user, so the SDK must be readable and writable.
    && chmod -R a+rwX "$ANDROID_HOME"

RUN npm install -g "@bubblewrap/cli@${BUBBLEWRAP_VERSION}"
