ARG PNPM_VERSION=10.32.1

FROM node:22-alpine AS builder

ARG PNPM_VERSION
RUN npm install -g pnpm@${PNPM_VERSION}

WORKDIR /app

COPY package.json pnpm-workspace.yaml pnpm-lock.yaml ./
COPY src/shared-types/package.json ./src/shared-types/
COPY src/gateway/package.json ./src/gateway/
COPY src/frontend/package.json ./src/frontend/

RUN pnpm install --filter "@core/gateway..." --frozen-lockfile

COPY src/shared-types ./src/shared-types
COPY src/gateway ./src/gateway

RUN pnpm --filter @core/shared-types build
RUN pnpm --filter @core/gateway build

FROM node:22-alpine AS runner

ARG PNPM_VERSION
RUN npm install -g pnpm@${PNPM_VERSION}

WORKDIR /app

COPY --from=builder /app/package.json /app/pnpm-workspace.yaml /app/pnpm-lock.yaml ./
COPY --from=builder /app/src/shared-types/package.json ./src/shared-types/
COPY --from=builder /app/src/gateway/package.json ./src/gateway/
COPY --from=builder /app/src/frontend/package.json ./src/frontend/

RUN pnpm install --filter "@core/gateway..." --frozen-lockfile --prod

COPY --from=builder /app/src/shared-types/dist ./src/shared-types/dist
COPY --from=builder /app/src/gateway/dist ./src/gateway/dist

EXPOSE 3000

CMD ["node", "src/gateway/dist/main.js"]
