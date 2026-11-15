# Banquinha — web (Nuxt)

Aplicação Nuxt 4 com o site público do cliente e o painel PWA do vendedor. Consome a API Laravel em `backend/`; o painel e a área do cliente rodam no navegador, e as páginas públicas usam SSR para SEO e preview de links.

## Páginas

| Página | Rota | Renderização |
|--------|------|--------------|
| Início, com o mapa dos vendedores em turno | `/` | SSR |
| Página pública do vendedor | `/v/[slug]` | SSR |
| Reserva (entrega ou retirada) | `/v/[slug]/reservar` | navegador |
| Entrar | `/entrar` | navegador |
| Cadastro de cliente | `/cadastro` | navegador |
| Meus pedidos | `/pedidos` | navegador |
| Painel do vendedor (PDV) | `/painel` | navegador / PWA |
| Produtos, reservas, turno e conta | `/painel/...` | navegador / PWA |

As regras de `ssr` ficam em `routeRules` no `nuxt.config.ts` (D38).

## Rodar

Tudo pelo Lerd:

- `https://banquinha.test` — este Nuxt, como site host-proxy (`.lerd.yaml`): o Lerd roda o `npm run servir` na porta 3000 com o Node do `.nvmrc` (24 LTS).
- `http://api.banquinha.test` — a API Laravel (`backend/`). O Nuxt repassa `/api` e `/sanctum` para ela.

```bash
npm install
lerd link --yes                        # uma vez; lê o .lerd.yaml e aprova o dev server
lerd restart                           # reinicia o dev server (ex.: após mudar nuxt.config.ts)
journalctl --user -u lerd-app-web -f   # logs
npm test                               # testes das utils
```

Se a API estiver em outro endereço, crie um `.env`:

```dotenv
NUXT_API_ALVO=http://seu-dominio.test
```

E rode `lerd restart`.

## Endereços e logins de desenvolvimento

| Tela | Endereço | Login (criado por `lerd artisan db:seed` no `backend/`) |
|------|----------|------------------------------------------|
| Início (mapa dos vendedores em turno) | `https://banquinha.test/` | — |
| Página pública do vendedor | `https://banquinha.test/v/vendedor-piloto` | — |
| Reserva (entrega ou retirada) | `https://banquinha.test/v/vendedor-piloto/reservar` | cliente |
| Login único (cliente ou vendedor) | `https://banquinha.test/entrar` | cliente: `cliente@banquinha.test` / `password`; vendedor: `vendedor@banquinha.test` / `password` |
| Meus pedidos | `https://banquinha.test/pedidos` | cliente |
| Painel do vendedor | `https://banquinha.test/painel` | vendedor |

O login identifica o tipo de conta: vendedor vai para o painel, cliente volta ao início. Essas senhas valem só no banco local.

O vendedor só aparece no início depois de **Iniciar turno** (aba Turno do painel). Para aparecer como ponto no mapa, ligue **Mostrar minha localização no mapa** na mesma aba: o painel envia o GPS a cada minuto enquanto estiver aberto.

O `.lerd.yaml` já deixa o site em HTTPS local (`secured: true`), porque o navegador só libera o GPS em HTTPS.

## API e autenticação

- O navegador chama `/api` e `/sanctum` na mesma origem; o proxy do Nuxt repassa para a API e o cookie de sessão do Sanctum funciona sem CORS.
- `useApi` centraliza as chamadas e os erros; `useVendedor` e `useCliente` cuidam da sessão de cada tipo de conta.
- A API devolve o objeto direto, sem envelope `data`.
- A referência gerada pelo Scramble fica em `http://api.banquinha.test/docs/api` (interface) e `/docs/api.json` (OpenAPI).

## PWA e offline

- `@vite-pwa/nuxt` gera o manifest e o service worker no build de produção. Em desenvolvimento o service worker fica desligado (`devOptions.enabled: false`).
- A casca SPA de `/painel` é pré-renderizada para o painel abrir sem internet.
- `useFilaVendas` guarda as vendas no IndexedDB com um `id_local`; a fila é enviada ao abrir o painel e nos eventos `online` e `visibilitychange` (D40). O índice único no banco evita duplicar a venda.
- O QR do Pix é gerado no próprio aparelho (`utils/pix.ts`), então o PDV continua funcionando sem sinal.
- Ao voltar do modo produção para o dev, limpe os dados do site no navegador: o service worker do build anterior fica guardado.

## Modo produção (teste no celular)

O Lerd roda `npm run servir` (`scripts/servir.sh`). Com o arquivo `web/.modo-producao`, ele serve o build de produção: poucos arquivos (bom pelo túnel) e com service worker. Sem o arquivo, roda o modo dev, com recarga automática.

```bash
touch .modo-producao && lerd restart   # produção (cada mudança no código exige novo lerd restart)
rm .modo-producao && lerd restart      # volta ao modo dev
lerd share --localhost-run web         # túnel HTTPS para abrir no celular
```

## Estrutura

```text
app/
├── pages/          rotas do site público, Meus pedidos e painel
├── components/     mapa, mini mapa e ícones
├── composables/    API, cliente, vendedor, carrinho, fila offline e GPS
├── utils/          Pix, dinheiro, geo, cores e mapa
├── middleware/     guards de cliente e painel
├── layouts/        layout do painel
└── plugins/        envio da fila ao ficar online/visível
```

`nuxt.config.ts` concentra proxy da API, SSR, PWA e `runtimeConfig`. `scripts/servir.sh` é o comando do site no Lerd.

## Documentação

A visão do produto, as regras de negócio e as decisões técnicas estão em [`../docs/`](../docs/README.md). Os principais:

- [Escopo do MVP](../docs/ESCOPO_MVP.md)
- [Regras de negócio](../docs/REGRAS_NEGOCIO.md)
- [Fluxos](../docs/FLUXOS.md)
- [Modelo de dados](../docs/MODELO_DADOS.md)
- [Decisões técnicas](../docs/DECISOES_TECNICAS.md)
