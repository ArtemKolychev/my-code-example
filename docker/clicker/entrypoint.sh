#!/bin/bash

# Start Xvfb
Xvfb ${DISPLAY} -screen 0 ${RESOLUTION} -ac +extension GLX +render -noreset &
XVFB_PID=$!

# Wait until Xvfb is ready
for i in $(seq 1 10); do
  if [ -f "/tmp/.X${DISPLAY#:}-lock" ]; then
    break
  fi
  sleep 1
done
sleep 1

# Start Fluxbox window manager
fluxbox &

# Start x11vnc
x11vnc -display ${DISPLAY} -forever -nopw -shared -rfbport 5900 -noxdamage -o /tmp/x11vnc.log &
X11VNC_PID=$!
sleep 1

# Start noVNC websockify proxy
websockify --web /usr/share/novnc 6080 localhost:5900 &

echo "VNC stack started (Xvfb=$XVFB_PID, x11vnc=$X11VNC_PID)"

# Execute main command
exec "$@"
