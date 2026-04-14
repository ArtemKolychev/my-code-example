FROM node:20-bookworm-slim AS deps
WORKDIR /app
RUN corepack enable && corepack prepare pnpm@latest --activate
COPY ai-agent/package.json ai-agent/pnpm-lock.yaml ./
COPY cz-cars-parser /cz-cars-parser
RUN cd /cz-cars-parser && npm install && npm run build
COPY shared-types/package.json /shared-types/package.json
COPY shared-types/dist /shared-types/dist
RUN pnpm install --frozen-lockfile

FROM deps AS builder
COPY ai-agent/ .
RUN pnpm build

# dev: deps ready, source mounted at runtime
FROM deps AS dev

FROM node:20-bookworm-slim AS prod
WORKDIR /app
RUN corepack enable && corepack prepare pnpm@latest --activate
COPY --from=builder /app/dist ./dist
COPY ai-agent/package.json ai-agent/pnpm-lock.yaml ./
COPY cz-cars-parser /cz-cars-parser
COPY --from=deps /cz-cars-parser/dist /cz-cars-parser/dist
COPY shared-types/package.json /shared-types/package.json
COPY shared-types/dist /shared-types/dist
RUN pnpm install --frozen-lockfile --prod --ignore-scripts
ENV NODE_ENV=production
EXPOSE 3001
CMD ["node", "dist/main"]
