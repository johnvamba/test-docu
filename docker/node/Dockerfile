FROM node:22-alpine

ARG UID=1000
ARG GID=1000

# Pre-create node_modules with the host user's ids so the named volume mounted
# over it is writable by `npm install` (new volumes copy this ownership).
RUN mkdir -p /var/www/html/node_modules \
 && chown -R ${UID}:${GID} /var/www/html

WORKDIR /var/www/html
