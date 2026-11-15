#!/bin/sh
# Comando do site no Lerd (.lerd.yaml).
# Com o arquivo .modo-producao: build de produção (poucos arquivos, rápido pelo túnel, com service worker).
# Sem ele: modo dev, com recarga automática ao editar.
set -e
if [ -f .modo-producao ]; then
  npx nuxt build
  exec node .output/server/index.mjs
fi
exec npx nuxt dev
