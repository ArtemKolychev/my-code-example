ARG PNPM_VERSION=10.32.1

FROM node:22-alpine AS builder

ARG PNPM_VERSION
RUN npm install -g pnpm@${PNPM_VERSION}

WORKDIR /app

COPY package.json pnpm-workspace.yaml pnpm-lock.yaml ./
COPY src/shared-types/package.json ./src/shared-types/
COPY src/frontend/package.json ./src/frontend/
COPY src/gateway/package.json ./src/gateway/

RUN pnpm install --filter "@core/frontend..." --frozen-lockfile

COPY src/shared-types ./src/shared-types
COPY src/frontend ./src/frontend

RUN pnpm --filter @core/shared-types build
RUN pnpm --filter @core/frontend build

FROM node:22-alpine AS runner

ENV NODE_ENV=production

WORKDIR /app

COPY --from=builder /app/src/frontend/.next/standalone ./
COPY --from=builder /app/src/frontend/.next/static ./src/frontend/.next/static
COPY --from=builder /app/src/frontend/public ./src/frontend/public

EXPOSE 3001

CMD ["node", "src/frontend/server.js"]
