####################################################################
# Foogra web — standalone frontend image for the split deployment.
#
# Builds the React/Vite SPA and serves it from its own nginx container,
# proxying /api, /sanctum, /docs, /storage and /up to the api service
# (docker/api.Dockerfile) so the browser only ever sees one origin.
####################################################################

FROM node:22-alpine AS build

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./

# Left empty on purpose: with the SPA and API proxied through the same
# nginx origin, axios's relative "/api/v1" baseURL is all that's needed —
# see frontend/src/lib/api.ts.
ARG VITE_API_URL=""
ENV VITE_API_URL=${VITE_API_URL}

RUN npm run build

FROM nginx:1.27-alpine AS runtime

ENV API_HOST=api \
    API_PORT=8000

COPY docker/web/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s CMD wget -qO- http://127.0.0.1:8080/healthz || exit 1
