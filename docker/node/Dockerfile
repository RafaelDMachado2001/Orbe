# syntax=docker/dockerfile:1
FROM node:22-bookworm-slim

ARG UID=1000
ARG GID=1000
RUN if [ "$(id -u node)" != "$UID" ]; then \
        usermod -o -u "${UID}" node && groupmod -o -g "${GID}" node; \
    fi

# O diretorio precisa existir na imagem e ja pertencer ao usuario: um volume
# nomeado herda dono e permissao do caminho da imagem no primeiro mount. Sem
# isso o volume nasce como root e o npm install nao consegue escrever.
RUN mkdir -p /app/node_modules && chown -R node:node /app

WORKDIR /app
COPY docker/node/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
