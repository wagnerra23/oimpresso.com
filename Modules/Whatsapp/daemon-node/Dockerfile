# syntax=docker/dockerfile:1.7
# ===========================================
# whatsapp-baileys-nodeapp
# Multi-stage build, non-root, distroless-ish.
# ===========================================

ARG NODE_VERSION=20.18.0

# ---------- Stage 1: deps ----------
FROM node:${NODE_VERSION}-bookworm-slim AS deps
WORKDIR /app

# Baileys precisa de libsystemd + tools nativas para sharp/qrcode em alguns builds.
# git: alguns deps (Baileys/OpenTelemetry transientes) clonam de github via npm.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        dumb-init \
        git \
    && rm -rf /var/lib/apt/lists/*

# Repo não commita package-lock.json (decisão de Agent J PR #669). Usa
# npm install em vez de npm ci pra evitar dependência rígida de lockfile.
# Trade-off: build ligeiramente menos reprodutível mas funcional sem fricção.
COPY package.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm install --no-audit --no-fund --prefer-offline

# ---------- Stage 2: build ----------
FROM deps AS build
COPY tsconfig.json ./
COPY src ./src
RUN npm run build \
    && npm prune --omit=dev

# ---------- Stage 3: runtime ----------
FROM node:${NODE_VERSION}-bookworm-slim AS runtime
WORKDIR /app

ENV NODE_ENV=production \
    HTTP_HOST=0.0.0.0 \
    HTTP_PORT=3000 \
    SESSIONS_DIR=/app/sessions

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        dumb-init \
        tini \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd --system --gid 1001 nodeapp \
    && useradd --system --uid 1001 --gid nodeapp --home /app nodeapp \
    && mkdir -p /app/sessions \
    && chown -R nodeapp:nodeapp /app

COPY --from=build --chown=nodeapp:nodeapp /app/node_modules ./node_modules
COPY --from=build --chown=nodeapp:nodeapp /app/dist ./dist
COPY --chown=nodeapp:nodeapp package.json ./

USER nodeapp
EXPOSE 3000

# Healthcheck consulta o /health interno.
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD node -e "fetch('http://127.0.0.1:'+(process.env.HTTP_PORT||3000)+'/health').then(r=>r.ok?process.exit(0):process.exit(1)).catch(()=>process.exit(1))"

ENTRYPOINT ["/usr/bin/tini", "--"]
CMD ["node", "--enable-source-maps", "dist/server.js"]
