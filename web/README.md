# Banquinha — web (Nuxt)

Painel do vendedor (PWA, sem SSR) em `/painel`. Páginas públicas do cliente ainda não feitas.

## Rodar

Tudo pelo Lerd:

- `https://banquinha.test` — este Nuxt, como site host-proxy (`.lerd.yaml`): o Lerd roda o `npm run dev` na porta 3000 com o Node do `.nvmrc` (24 LTS).
- `http://api.banquinha.test` — a API Laravel (`backend/`). O Nuxt repassa `/api` e `/sanctum` para ela (mude com `NUXT_API_ALVO`).

```bash
npm install
lerd link          # uma vez; depois o Lerd sobe o dev server sozinho
lerd restart       # reinicia o dev server (ex.: após mudar nuxt.config.ts)
journalctl --user -u lerd-app-web -f   # logs
npm test           # testes de utils (Pix, dinheiro)
```

Painel: `https://banquinha.test/painel`.

## Endereços e logins de desenvolvimento

| Tela | Endereço | Login (criado por `php artisan db:seed`) |
|------|----------|-------|
| Início (mapa dos vendedores em turno) | `https://banquinha.test/` | — |
| Página pública do vendedor | `https://banquinha.test/v/vendedor-piloto` | — |
| Login único (cliente ou vendedor) | `https://banquinha.test/entrar` | cliente: `cliente@banquinha.test` / `password`; vendedor: `vendedor@banquinha.test` / `password` |

O login identifica o tipo de conta: vendedor vai para o painel, cliente volta ao início. Essas senhas valem só no banco local.

O vendedor só aparece no início depois de **Iniciar turno** (aba Turno do painel). Para aparecer como ponto no mapa, ligue **Mostrar minha localização no mapa** na mesma aba: o painel envia o GPS a cada minuto enquanto estiver aberto.

O site usa HTTPS local (`lerd secure`) porque o navegador só libera o GPS em HTTPS.

## Modo produção (teste no celular)

O Lerd roda `npm run servir` (`scripts/servir.sh`). Com o arquivo `web/.modo-producao`, ele serve o build de produção: poucos arquivos (bom pelo túnel) e com service worker. Sem o arquivo, roda o modo dev, com recarga automática.

```bash
touch .modo-producao && lerd restart   # produção (cada mudança no código exige novo lerd restart)
rm .modo-producao && lerd restart      # volta ao modo dev
lerd share --localhost-run web         # túnel HTTPS para abrir no celular
```

Ao voltar para o modo dev, se o painel parecer desatualizado, limpe os dados do site no navegador (o service worker do modo produção fica guardado).
