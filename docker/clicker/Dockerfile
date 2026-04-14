# deps: install all node_modules + download chromium
FROM node:20-bookworm AS deps
WORKDIR /app
RUN apt-get update && apt-get install -y --no-install-recommends \
    fonts-liberation fonts-noto-core procps dbus-x11 xvfb \
    libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 \
    libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 \
    libgbm1 libasound2 libpango-1.0-0 libcairo2 libatspi2.0-0 \
  && rm -rf /var/lib/apt/lists/*
COPY clicker/package.json clicker/yarn.lock ./
COPY shared-types/package.json /shared-types/package.json
COPY shared-types/dist /shared-types/dist
RUN yarn install --frozen-lockfile
RUN npx rebrowser-playwright install --with-deps chromium || npx rebrowser-playwright install chromium

# builder: compile TypeScript
FROM deps AS builder
COPY clicker/ .
RUN yarn build

# dev: browser ready, source mounted at runtime
FROM deps AS dev
ENV DISPLAY=:99

# prod: compiled dist + prod deps + browser copied from deps
FROM node:20-bookworm AS prod
WORKDIR /app
RUN apt-get update && apt-get install -y --no-install-recommends \
    fonts-liberation fonts-noto-core procps dbus-x11 \
    libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 \
    libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 \
    libgbm1 libasound2 libpango-1.0-0 libcairo2 libatspi2.0-0 \
  && rm -rf /var/lib/apt/lists/*
COPY --from=builder /app/dist ./dist
COPY clicker/package.json clicker/yarn.lock ./
COPY shared-types/package.json /shared-types/package.json
COPY shared-types/dist /shared-types/dist
RUN yarn install --frozen-lockfile --production
COPY --from=deps /root/.cache/ms-playwright /root/.cache/ms-playwright
ENV DISPLAY=:99
ENV NODE_ENV=production
CMD ["node", "dist/src/main"]
