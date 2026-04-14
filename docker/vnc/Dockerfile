FROM debian:bookworm-slim

RUN apt-get update && apt-get install -y --no-install-recommends \
    xvfb fluxbox \
    x11vnc \
    novnc websockify \
    fonts-liberation fonts-noto-core \
    procps dbus-x11 \
    xdotool \
  && rm -rf /var/lib/apt/lists/*

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV DISPLAY=:99
ENV RESOLUTION=${RESOLUTION}

EXPOSE 5900 6080

ENTRYPOINT ["/entrypoint.sh"]
