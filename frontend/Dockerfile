FROM node:22-alpine AS builder

WORKDIR /app

# Copy package files
COPY package.json package-lock.json ./
RUN npm ci

# Copy source and build
COPY . .

# VITE_API_URL will be injected at build time via Railway build args / env
ARG VITE_API_URL
ENV VITE_API_URL=$VITE_API_URL

RUN npm run build-only

# Serve with Nginx
FROM nginx:alpine

# Install envsubst (sudah ada di nginx:alpine via gettext)
RUN apk add --no-cache gettext

# Copy built files
COPY --from=builder /app/dist /usr/share/nginx/html

# Nginx config template — PORT akan di-substitute saat startup
COPY nginx.conf /etc/nginx/templates/default.conf.template

# Hapus default config bawaan nginx
RUN rm /etc/nginx/conf.d/default.conf 2>/dev/null || true

EXPOSE ${PORT:-80}

# nginx:alpine sudah punya envsubst support via /docker-entrypoint.d/
# File *.template di /etc/nginx/templates/ otomatis di-process saat container start
CMD ["nginx", "-g", "daemon off;"]
