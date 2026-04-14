#!/bin/bash

# Clean up stale Xvfb lock files from previous runs
rm -f /tmp/.X${DISPLAY#:}-lock /tmp/.X11-unix/X${DISPLAY#:}

# Start Xvfb (socket goes to /tmp/.X11-unix which is shared via volume)
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

# Configure Fluxbox to place Chromium at top-left corner
mkdir -p ~/.fluxbox
cat > ~/.fluxbox/apps <<'EOF'
[app] (name=chromium)
  [Position] (UPPERLEFT) {0 0}
  [Maximized] {no}
[end]
EOF

# Start Fluxbox window manager
fluxbox &

# Start x11vnc
x11vnc -display ${DISPLAY} -forever -nopw -shared -rfbport 5900 -noxdamage &
sleep 1

# Keep display "warm" so Chrome works without an active VNC client.
# Without this, Chrome's CDP session closes on startup when nothing is processing X11 events.
while true; do
  DISPLAY=${DISPLAY} xdotool mousemove --sync 1 1 2>/dev/null
  sleep 10
done &

# Start noVNC websockify proxy
websockify --web /usr/share/novnc 6080 localhost:5900 &

echo "VNC stack ready"

# Keep container alive
wait $XVFB_PID
