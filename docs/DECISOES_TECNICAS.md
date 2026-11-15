# Decisões Técnicas

## Plataforma [Decidido]

- Aplicação web responsiva, **pensada primeiro para celular** (inclusive o painel do vendedor, que vende na rua).
- Cliente acessa pelo navegador, sem instalar nada.
- Painel do vendedor como **PWA**, para permitir a venda presencial sem internet (ver "Uso sem internet" abaixo).
- [Futuro] App **Flutter** (Android e iOS) para clientes e vendedores, consumindo a mesma API. A web continua existindo.

## Stack [Decidido em 11/11/2026]

| Item | Escolha | Motivo |
|------|---------|--------|
| Backend | **Laravel 13**, somente API REST (`/api/v1`, Sanctum) | API única para a web e para o futuro app Flutter |
| Frontend web | **Nuxt + Vue**, projeto separado | SSR para SEO e preview de links; PWA para uso sem internet; a web é só mais um cliente da API |
| App | **Flutter** [Futuro] | Android e iOS com o mesmo código; resolve GPS em segundo plano e push |
| Banco | **PostgreSQL 16 + PostGIS 3.5** | Consultas geográficas ("perto de mim"); PostGIS já disponível no Lerd |
| Ambiente local | **Lerd** (PHP 8.5, PostgreSQL, Redis, Mailpit) | Já instalado e configurado |
| Mapa | **MapLibre GL + OpenFreeMap** (estilo Positron) | Gratuito, sem chave, uso comercial permitido; mapa só com ruas e bairros, sem estabelecimentos (D48) |
| Atualização em tempo quase real | Página do cliente consulta o servidor a cada ~30s | Websocket não é necessário no MVP |
| QR Code / Pix | BR Code + QR no backend (reservas, e-mails) **e** no frontend (PDV offline) | Pix estático sem integração; sem internet o PDV ainda gera o QR (D34) |
| Node | **LTS** (24 ou 26) | Versões ímpares não recebem suporte longo (D41) |

### Bibliotecas [Decidido em 11/11/2026, D39]

| Projeto | Pacote | Uso |
|---------|--------|-----|
| backend | `laravel/sanctum` | Autenticação (cookie na web, token no app) |
| backend | `dedoc/scramble` | Documentação OpenAPI gerada do código |
| backend | `laravel-notification-channels/webpush` | Notificações push |
| backend | `chillerlan/php-qrcode` ou `endroid/qr-code` | QR do Pix |
| backend (dev) | `pestphp/pest`, `larastan/larastan`, Laravel Pint | Testes, análise estática, estilo |
| web | `@vite-pwa/nuxt` | Service worker / PWA |
| web | ~~`nuxt-auth-sanctum`~~ | Substituído por composable próprio (`useApi`/`useVendedor`), ver D42 |
| web | `idb-keyval` (ou Dexie) | Fila de vendas offline em IndexedDB |
| web | `maplibre-gl` | Mapa vetorial (somente no navegador), substitui `@nuxtjs/leaflet` (D48) |
| web | `qrcode` | QR do Pix no PDV offline |

Conferir a compatibilidade com a versão do Nuxt e do Laravel ao instalar.

### Convenções de dados

- Dinheiro como **inteiro em centavos** no banco, na API e no frontend (D35).
- Datas em UTC no banco, exibidas em `America/Sao_Paulo`.
- Coordenadas em `latitude`/`longitude` decimal no MVP (D37).

### Renderização no Nuxt (D38)

- **SSR:** só páginas públicas (página do vendedor, cardápio), por SEO e preview de links.
- **Sem SSR** (`ssr: false` via `routeRules`): painel do vendedor e "Meus pedidos".

### Regras da arquitetura

- **Regra de negócio fica no backend**, em classes de serviço ou Actions, nunca no frontend. A web e o app chamam os mesmos endpoints.
- **Contrato da API documentado com OpenAPI** desde o início (ex.: Scramble), para a web e o app não divergirem.
- **API versionada** (`/api/v1`): versões antigas do app continuam chamando a API por meses depois de uma atualização.
- **Autenticação:** a web usa Sanctum em modo SPA (cookie de sessão); o app usa token guardado no armazenamento seguro do aparelho. Para o cookie funcionar, web e API ficam no mesmo domínio raiz (ex.: `dominio.com` e `api.dominio.com`).
- **Recuperação de senha do vendedor por e-mail.** Em desenvolvimento, os e-mails caem no Mailpit do Lerd; em produção, será preciso um serviço de envio (SMTP).

## Estrutura do repositório [Decidido]

Monorepo: um único repositório Git com projetos independentes. Se houver problema, os projetos podem ser separados em repositórios próprios.

```
backend/   Laravel 13 (API)
web/       Nuxt + Vue
app/       Flutter [Futuro]
docs/      documentação (estes arquivos)
```

No Lerd, o site aponta para a pasta `backend/`, não para a raiz do repositório.

## Uso sem internet (PWA) [Decidido]

O Nuxt permite o uso offline, mas ele precisa ser construído:

- módulo PWA (service worker) para o painel do vendedor abrir sem sinal;
- fila local de vendas em IndexedDB, enviada quando a conexão volta;
- ID da venda gerado no celular (`id_local`), para o reenvio não duplicar a venda. O banco garante isso com índice único em (`vendedor_id`, `id_local`) (D36);
- QR do Pix gerado no próprio celular, sem chamar a API (D34);
- envio da fila nos eventos `online` e `visibilitychange` e ao abrir o painel, porque o Safari do iOS não tem Background Sync (D40).

Limites: o primeiro acesso precisa de internet, e no iPhone o Safari pode apagar dados locais de sites pouco usados. Por isso a fila é enviada assim que houver sinal.

## Notificações push [Decidido]

- Web Push (service worker do PWA) para avisar o vendedor de reserva nova e de "Já paguei", mesmo com o painel fechado.
- No iPhone, só funciona com o painel **adicionado à tela de início** (iOS 16.4+). Exige HTTPS.
- Cliente recebe e-mail quando a reserva é aceita, negada ou expirada.

## Tarefas agendadas [Decidido]

- Expirar reservas pendentes após o prazo de resposta do vendedor.
- Apagar endereços de entrega 3 dias após o encerramento da reserva (LGPD).

## Ambientes [Decidido]

- **Desenvolvimento:** Lerd na máquina local.
- **Teste com URL pública** (webhooks, teste no celular, Fase 0): ngrok apontando para o Lerd, somente durante os testes. Ver cuidados em "Riscos técnicos".
- **Produção:** servidor dedicado (provedor ainda a definir), com HTTPS.

### O que precisa rodar em produção

- PHP-FPM + servidor web para a API.
- Processo Node para o Nuxt (SSR das páginas públicas), mantido por um gerenciador de processos (ex.: PM2 ou systemd).
- Cron chamando `php artisan schedule:run` a cada minuto (expirar reservas, apagar endereços).
- Worker de fila (`php artisan queue:work`) com Redis, para e-mails e push não travarem as requisições.
- PostgreSQL com PostGIS e Redis.

## Integrações de pagamento

Detalhes em [PAGAMENTOS.md](PAGAMENTOS.md).

- **Piloto:** InfiniteTap (cartão presencial por deeplink) e, na fase 2, Checkout Integrado InfinitePay (Pix/cartão online com webhook).
- **Outros vendedores:** Pix estático e confirmação manual.
- **Multi-vendedor com comissão:** gateway com split (Mercado Pago marketplace, Woovi, Asaas ou Efí).

## Fase 0 — Protótipo do InfiniteTap [Proposto]

> **Restrição (11/11/2026):** o MVP será apresentado pronto ao vendedor piloto, sem testes prévios com ele. Por isso a Fase 0 **não é pré-requisito**: o fluxo de cartão já funciona mesmo se o deeplink falhar (o vendedor abre o app manualmente e responde "Pagamento concluído?"). O teste abaixo acontece na apresentação, ou antes, se houver um iPhone com o app InfinitePay disponível.

Objetivo: descobrir se o deeplink **abre o app da InfinitePay com o valor** a partir do navegador. O retorno automático não é usado: ao voltar ao painel, o vendedor responde "Pagamento concluído?".

1. Página mínima com um botão "Cobrar R$ 1,00" que abre `infinitepaydash://infinitetap-app?amount=100&payment_method=debit&order_id=TESTE1&result_url=<URL do painel>&app_client_referrer=<nome>&af_force_deeplink=true`.
2. Expor a página com ngrok.
3. Testar no **iPhone do vendedor piloto**, com o app InfinitePay logado na conta dele, em dois modos: no Safari e com o painel adicionado à tela de início. É uma cobrança real de R$ 1,00.
4. Resultado:
   - Abriu com o valor → fluxo do MVP confirmado.
   - Não abriu → o vendedor abre o app manualmente e confirma na tela pré-preenchida; avaliar antecipar o app Flutter do vendedor.

## Riscos técnicos [Decidido registrar]

1. **GPS no navegador para quando a tela apaga ou o navegador vai para segundo plano.** Uma aplicação web não envia localização contínua com o celular no bolso. MVP: localização atualizada enquanto o painel está aberto (a cada 1–2 min) + check-in manual nas paradas. O app Flutter resolve.
2. **Internet ruim na rua.** Se a venda depender de sinal, o vendedor deixa de registrar. Mitigação: PWA com fila local de vendas (ver "Uso sem internet").
3. **Webhooks exigem servidor público.** Em desenvolvimento local, usar ngrok:
   - o Lerd responde pelo nome do domínio, então o ngrok precisa reescrever o `Host` (ex.: `ngrok http https://backend.test --host-header=backend.test`; se der erro de certificado, apontar para `http://`);
   - usar o domínio estático gratuito do ngrok, para não reconfigurar `APP_URL`, `result_url` e webhooks a cada reinício;
   - o plano gratuito mostra uma página de aviso na primeira visita pelo navegador, o que pode atrapalhar o retorno do InfiniteTap;
   - configurar `TrustProxies` no Laravel para gerar links `https://` corretos.
4. **Túnel expõe a máquina local.** Com o ngrok aberto, manter `APP_DEBUG=false`, usar só dados de teste e fechar o túnel ao terminar.
5. **Webhook da InfinitePay não é assinado.** Sempre confirmar o pagamento via `payment_check` antes de marcar como pago.
6. **Disciplina de registro.** Em dias cheios, vendas vão deixar de ser registradas. Mitigação: venda em ~3 toques e ajuste de estoque no fim do turno.
7. **Integrações de cartão de outros provedores** geralmente exigem app nativo (PagBank, SumUp) ou maquininha específica (Mercado Pago Point Smart).

## Escopo de usuários

- Inicialmente 1 vendedor. Estrutura de dados já separada por `vendedor_id` para permitir vários.
- Não há campo "tipo de vendedor" (ambulante ou fixo): para o sistema, os dois funcionam igual.

## Evolução pós-MVP [Proposto]

O que foi simplificado no MVP de propósito e o que fazer quando o projeto crescer. Cada item diz o gatilho (quando mudar) e o caminho.

| Simplificação no MVP | Gatilho para mudar | O que fazer |
|---|---|---|
| Coordenadas em `latitude`/`longitude` decimal (D37) | Início do multi-vendedor / mapa "perto de mim" | Migration que cria coluna `geography(Point, 4326)` em `localizacao_vendedor` e `paradas`, preenche a partir de lat/long e cria índice GiST. Usar `clickbar/laravel-magellan` no Eloquent. Busca com `ST_DWithin`. A API pode continuar devolvendo lat/long |
| Mapa com tiles públicos do OpenStreetMap | Muitos usuários ou vários vendedores | Trocar o provedor de tiles (MapTiler, Stadia, Mapbox ou Google). O Leaflet continua; muda só a URL dos tiles. Adicionar clustering de pontos |
| Página do cliente consulta o servidor a cada ~30s | Vários vendedores ou carga alta no servidor | Laravel Reverb (WebSocket) para localização, estoque e status de reserva |
| Fila offline sem Background Sync (D40) | App Flutter do vendedor | O app guarda a fila localmente e envia em segundo plano. A regra do `id_local` e o índice único (D36) continuam iguais |
| GPS só com o painel aberto | App Flutter do vendedor | Localização em segundo plano pelo app nativo |
| Pix gerado no backend e no frontend (D34) | Checkout InfinitePay (Fase 2) ou gateway com split | O Pix com confirmação automática vem do provedor e exige internet. O Pix estático offline continua como reserva para quando não houver sinal |
| Fila do Laravel sem painel de monitoramento | Muitos jobs (e-mails, push, webhooks) ou falhas difíceis de achar | Laravel Horizon |
| Tipos da API escritos à mão no Nuxt | Dois clientes da API (web + Flutter) ou erros por divergência de contrato | Gerar tipos a partir do OpenAPI do Scramble (ex.: `openapi-typescript` na web, `openapi-generator` no Flutter) |
| Um único vendedor | Fase 3 (multi-vendedor) | Cadastro de vendedores, gateway com split (D9), configuração "Como você recebe no cartão?", tags de vendedor (ver [IDEIAS_FUTURAS.md](IDEIAS_FUTURAS.md)) |

O que **não** muda depois do MVP: dinheiro em centavos (D35), datas em UTC, API versionada em `/api/v1`, regra de negócio no backend e SSR só nas páginas públicas (D38).

## Ainda a decidir

- Provedor e plano do servidor dedicado de produção.
- Serviço de envio de e-mail em produção.
