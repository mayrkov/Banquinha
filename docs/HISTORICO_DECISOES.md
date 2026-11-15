# Histórico de Decisões

Registro das decisões tomadas durante a refinação da ideia (10/11/2026), com o contexto de cada uma. Mais recentes no fim.

---

### D1 — A confirmação de reserva pelo vendedor continua obrigatória

**Contexto:** foi sugerido aceitar reservas automaticamente quando houvesse estoque.
**Decisão:** manter a aceitação manual.
**Motivo:** o estoque do sistema não era confiável, porque o vendedor não registrava cada venda na rua. A confirmação é quem garante que o produto existe de verdade.

### D2 — Toda venda passa a ser registrada no sistema

**Contexto:** a alternativa "botão esgotou por sabor" não evita o caso "restam 4 de Nutella, cliente reserva 5". Também não é possível descobrir o sabor vendido pelo extrato do banco, porque vários sabores têm o mesmo preço.
**Decisão:** o sistema vira o caixa do vendedor. Cada venda (presencial ou reserva) é registrada com os sabores; o estoque é descontado ao confirmar o pagamento; o dashboard é alimentado por esses registros.
**Consequência:** o cliente vê a disponibilidade real. Reserva fica limitada ao disponível (estoque − reservas aceitas).

### D3 — Contraoferta ao negar reserva foi descartada

**Motivo:** com estoque exato, o cliente já vê o que está disponível antes de reservar.

### D4 — Entrega fica para depois

**Contexto:** o vendedor pode tanto aguardar a retirada quanto entregar.
**Decisão:** tratar a entrega numa fase futura. MVP só com retirada.

### D5 — A venda presencial é operada pelo vendedor

**Contexto:** a ideia inicial era o cliente acessar o sistema na hora da compra presencial.
**Decisão:** o vendedor opera no celular dele (sabores → forma de pagamento → confirmar). O QR Pix aparece na tela do vendedor.
**Motivo:** na rua, com fila, o cliente não vai abrir um site para comprar um pão de queijo.

### D6 — Pix e dinheiro com confirmação manual no MVP

**Decisão:** o sistema gera o Pix estático com o valor a partir da chave Pix do vendedor (sem integração com banco). O vendedor confere no app do banco e marca como pago. Dinheiro: vendedor marca como pago.
**Limitação aceita:** o Pix estático não avisa automaticamente que o pagamento caiu.

### D7 — Cartão do piloto via InfiniteTap (deeplink)

**Contexto:** o pagamento com cartão é feito por NFC no celular do vendedor, pelo app da InfinitePay. Queria-se evitar ter que abrir o app do banco manualmente e depois registrar no sistema.
**Decisão:** usar a integração oficial e gratuita do InfiniteTap: o sistema abre o app com o valor e recebe de volta o resultado.
**Pendência:** testar se funciona a partir do navegador (Fase 0). Plano B: tela de confirmação pré-preenchida.
**Para outros bancos:** confirmação manual com tela pré-preenchida, porque poucas maquininhas oferecem integração equivalente para sistemas web.

### D8 — O dinheiro sempre vai direto para a conta do vendedor

**Contexto:** gateways como AbacatePay recebem na conta de quem contratou (a plataforma), o que obrigaria a repassar valores a terceiros.
**Decisão:** a plataforma não recebe nem repassa dinheiro. Para o piloto, usar o Checkout Integrado da InfinitePay com o handle do próprio vendedor (pagamento online com confirmação automática, sem mensalidade).

### D9 — Multi-vendedor exigirá gateway com split (se houver comissão)

**Contexto:** para oferecer pagamento online a vários vendedores e a plataforma ficar com uma parte, é preciso split.
**Opções pesquisadas:** Mercado Pago marketplace (Pix + cartão, conta própria do vendedor), Woovi (split só de Pix, modelo de parceiro), Asaas e Efí (subcontas/split).
**Decisão:** escolha adiada para a fase multi-vendedor. Depende do modelo de receita (comissão por venda ou mensalidade).

---

## 11/11/2026 — Stack e visão de produto

### D10 — Backend Laravel 13 somente API, frontend web separado em Nuxt + Vue

**Contexto:** o sistema deve funcionar no navegador e, no futuro, como app nas lojas (Play Store e App Store). Foram comparados Flutter para tudo, Laravel com Blade, TALL Stack (Livewire/Filament), Inertia + Vue e Nuxt separado.
**Decisão:** Laravel 13 como API REST (`/api/v1`) e Nuxt + Vue como projeto web separado.
**Motivo:** a web vira apenas mais um cliente da API, como o futuro app Flutter, o que garante que a API fique completa. O Nuxt oferece SSR (SEO e preview de links no WhatsApp/Instagram) e PWA para o painel do vendedor funcionar sem internet. Livewire foi descartado porque cada clique depende do servidor, o que é ruim na rua com sinal fraco. Flutter Web foi descartado para a parte pública por ser pesado no primeiro carregamento e fraco em SEO.
**Custo aceito:** dois projetos, dois deploys e um servidor Node em produção.

### D11 — App Flutter no futuro, para clientes e vendedores

**Motivo:** resolve limitações da web (GPS em segundo plano, push, integração com o app da InfinitePay). A web continua sendo a porta de entrada de novos clientes.

### D12 — Monorepo

**Decisão:** um repositório com `backend/`, `web/` e, depois, `app/`. Se houver problema, separar.
**Motivo:** desenvolvedor único; mudanças na API e no frontend saem no mesmo commit.

### D13 — PostgreSQL + PostGIS no lugar de SQLite

**Motivo:** a visão de produto inclui busca de vendedores por proximidade. PostGIS 3.5.7 foi testado no PostgreSQL 16 do Lerd e funciona.

### D14 — Ambientes: Lerd, ngrok para testes, servidor dedicado em produção

**Decisão:** desenvolvimento no Lerd; ngrok só para testes que precisam de URL pública; produção em servidor dedicado.

### D15 — Recuperação de senha do vendedor por e-mail

### D16 — Sem distinção entre vendedor ambulante e fixo

**Contexto:** foi considerado um campo "tipo de vendedor".
**Decisão:** não há campo de tipo. O sistema trata todos da mesma forma.
**Motivo:** nenhuma função depende dessa diferença. No máximo, uma tag visual no futuro.

### D17 — Vendedor fora de atividade some do mapa

**Decisão:** só aparecem no mapa os vendedores em atividade, inclusive os de endereço fixo.
**Motivo:** manter a tela do cliente limpa, mostrando apenas quem está funcionando.

### D18 — Marketplace ("iFood simplificado") e tags de vendedor ficam para o futuro

**Decisão:** registrados em [IDEIAS_FUTURAS.md](IDEIAS_FUTURAS.md). Não entram no MVP, para não sair do escopo.

---

## 11/11/2026 — Reservas, entrega, pagamento e controle do vendedor

### D19 — Entrega entra no MVP (revoga D4)

**Contexto:** o vendedor piloto entrega os pedidos reservados.
**Decisão:** a reserva exige endereço de entrega e aceita ponto de referência. O endereço é apagado 3 dias depois do encerramento da reserva (LGPD).

### D20 — Conta obrigatória para reservar

**Decisão:** cliente cria conta com nome, e-mail, telefone e senha para reservar. Sem conta, apenas navega.
**Motivo:** a aba "Meus pedidos" guardada só no navegador se perderia ao trocar de celular ou limpar dados. O telefone é obrigatório para o vendedor poder ligar durante a entrega.

### D21 — Estoque bloqueado ao aceitar; pagamento da reserva por Pix estático

**Contexto:** com Pix estático, o sistema não sabe automaticamente quando o pagamento cai. Bloquear só após o pagamento permitiria aceitar dois clientes para as mesmas unidades.
**Decisão:** as unidades são bloqueadas quando o vendedor aceita. Sem prazo automático para pagar; o vendedor pode cancelar a reserva aceita. O cliente paga pelo Pix estático, toca em "Já paguei", e o vendedor confirma. O checkout automático da InfinitePay continua na Fase 2.
**Consequência:** sem regra antiabuso, porque pedidos pendentes não bloqueiam estoque.

### D22 — Prazo de resposta definido pelo vendedor

**Decisão:** o vendedor define o prazo para responder uma reserva (padrão 15 min). Sem resposta, a reserva expira.
**Revoga:** a expiração de reserva aceita não retirada (não se aplica com entrega e pagamento antecipado).

### D23 — Entrega confirmada por código de 4 dígitos

**Decisão:** ao confirmar o pagamento, o cliente recebe um código de 4 dígitos. O vendedor toca em "Entregar pedido" e digita o código.
**Descartado:** leitura de QR Code, porque o Safari do iPhone não tem leitor nativo para páginas web e a câmera no navegador é menos confiável.

### D24 — Cartão sem retorno automático da InfinitePay

**Decisão:** o sistema abre o app da InfinitePay com o valor; na volta, o vendedor responde "Pagamento concluído?" (Sim registra a venda; Não cancela).
**Motivo:** no iPhone, com o painel instalado na tela de início, o retorno abriria no Safari, sem o login do painel. A Fase 0 passa a testar só se o deeplink abre o app.

### D25 — Status de turno sem "Fora de atividade"

**Decisão:** status *Vendendo agora*, *Próxima parada* e *Esgotado hoje*. Fora de atividade, o vendedor some do mapa e sua página mostra "Ops, o vendedor não está em atividade no momento".

### D26 — Notificações push no MVP

**Decisão:** o vendedor recebe push de reserva nova e de "Já paguei" mesmo com o painel fechado. No iPhone, exige adicionar o painel à tela de início.

### D27 — Ajuste de estoque com motivo e preço editável no PDV

**Contexto:** o vendedor piloto vende alguns pães mais baratos, doa outros e perde alguns.
**Decisão:** o PDV começa com o preço padrão e permite alterar. O ajuste de estoque divide a diferença em doação, perda, venda não registrada (com preço e forma de pagamento, que vira venda) e outro.
**Consequência:** o painel mostra faturamento cheio, descontos, faturamento real, doações e perdas.

### D28 — Painel de métricas do vendedor

**Decisão:** métricas por dia, semana e mês: faturamento, descontos, vendas por sabor e forma de pagamento, doações, perdas, reservas e estoque.

### D29 — Vendedor piloto usa iPhone; preços iniciais

**Registro:** iPhone. Preços informados (a confirmar com o vendedor): Nutella R$ 15,00, doce de leite R$ 10,00, goiabada R$ 10,00.

### D30 — Reserva com entrega ou retirada

**Decisão:** o cliente escolhe entre entrega (endereço e ponto de referência) e retirada numa das paradas programadas do vendedor. O código de 4 dígitos confirma os dois casos.

### D31 — E-mail ao cliente

**Decisão:** o cliente recebe e-mail quando a reserva é aceita (com o Pix para pagar), negada ou expirada.

### D32 — MVP apresentado pronto, sem validação prévia com o vendedor

**Contexto:** não é possível fazer perguntas ao vendedor piloto nem testar com ele antes da apresentação.
**Decisão:** o MVP é construído com valores que o vendedor pode alterar no painel (produtos, preços, prazo de resposta). A Fase 0 deixa de ser pré-requisito: o fluxo de cartão funciona mesmo se o deeplink falhar. A ordem das fases continua a proposta (0 → 1 → 2 → 3), a ser revista depois da apresentação.

### D33 — Taxa de entrega configurável

**Decisão:** o vendedor define a taxa de entrega nas configurações (padrão R$ 0,00). Ela é copiada para a reserva de entrega e somada ao total; retirada não tem taxa. O painel mostra as taxas separadas do faturamento dos produtos.
**Motivo:** não é possível perguntar ao vendedor antes da apresentação (D32).

---

## 11/11/2026 — Revisão da stack e das dependências

Revisão feita antes de iniciar o código. O plano de cada item para depois do MVP está em "Evolução pós-MVP" em [DECISOES_TECNICAS.md](DECISOES_TECNICAS.md).

### D34 — QR do Pix também gerado no frontend

**Contexto:** a venda presencial precisa funcionar sem internet (PWA), mas o Pix estático estava previsto só no backend (biblioteca PHP).
**Decisão:** o painel do vendedor gera o BR Code e o QR no próprio celular (pacote `qrcode` do npm). O backend continua gerando o Pix das reservas e dos e-mails.
**Motivo:** sem sinal, o backend não responde, e o QR não apareceria justamente quando o PDV mais precisa.
**Cuidado:** a regra fica duplicada (PHP e JS). Um teste compara o texto gerado pelos dois lados para os mesmos dados.

### D35 — Dinheiro gravado como inteiro em centavos

**Decisão:** todos os valores em dinheiro são inteiros em centavos, no banco, na API e no frontend. A formatação "R$ 15,00" acontece só na tela.
**Motivo:** o JavaScript erra contas com números decimais (`0.1 + 0.2 ≠ 0.3`), e a fila offline soma valores no celular. A InfinitePay (InfiniteTap e Checkout) já trabalha em centavos.

### D36 — Índice único em (`vendedor_id`, `id_local`) nas vendas

**Decisão:** o banco recusa uma segunda venda com o mesmo `id_local` do mesmo vendedor; a API devolve a venda já gravada em vez de erro.
**Motivo:** com sinal ruim, o celular reenvia a fila sem saber se o envio anterior chegou. Só o banco garante que a venda não seja duplicada, mesmo com dois envios simultâneos.

### D37 — Coordenadas em decimal no MVP; PostGIS na fase multi-vendedor

**Contexto:** o PostGIS foi escolhido (D13) pensando na busca "perto de mim", mas o modelo de dados guarda `latitude`/`longitude` em decimal.
**Decisão:** manter decimal no MVP. O PostgreSQL com a extensão PostGIS continua sendo o banco.
**Motivo:** com um vendedor não há busca por proximidade; o mapa só mostra pontos. A coluna geográfica e o pacote para usá-la no Laravel entrariam sem uso.
**Futuro:** na fase multi-vendedor, migrar para `geography(Point, 4326)` com índice GiST (ver "Evolução pós-MVP").

### D38 — SSR só nas páginas públicas; painel e área do cliente sem SSR

**Decisão:** o Nuxt renderiza no servidor apenas as páginas públicas (página do vendedor e cardápio). O painel do vendedor e "Meus pedidos" rodam só no navegador (`ssr: false` via `routeRules`).
**Motivo:** o SSR existe para SEO e preview de links (D10), o que só importa nas páginas públicas. Nas páginas logadas, o SSR obrigaria o servidor Node a repassar o cookie do Sanctum para a API e complicaria o funcionamento offline do PWA.

### D39 — Bibliotecas definidas

**Decisão:**
- Backend: `laravel/sanctum`, `dedoc/scramble`, `laravel-notification-channels/webpush`, biblioteca de QR Code PHP (`chillerlan/php-qrcode` ou `endroid/qr-code`). Desenvolvimento: `pestphp/pest`, `larastan/larastan`, Laravel Pint.
- Web: `@vite-pwa/nuxt`, `nuxt-auth-sanctum`, `idb-keyval` (ou Dexie), `@nuxtjs/leaflet`, `qrcode`.
**Motivo:** cada uma cobre uma decisão já tomada (PWA, auth SPA, fila offline, mapa, Pix, push) com o pacote mais usado do ecossistema. Conferir a compatibilidade com a versão do Nuxt e do Laravel ao instalar.
**Deixados de fora no MVP:** Horizon, Reverb, gerador de client TypeScript a partir do OpenAPI e bibliotecas de estado/UI. Nenhum é necessário para o escopo atual.

### D40 — Envio da fila offline sem depender de Background Sync

**Contexto:** o vendedor piloto usa iPhone, e o Safari do iOS não suporta Background Sync.
**Decisão:** a fila de vendas é enviada nos eventos `online` e `visibilitychange` e ao abrir o painel, pelo código da página.
**Motivo:** no iPhone o service worker não envia a fila sozinho; se dependesse dele, as vendas ficariam presas no aparelho.

### D41 — Node em versão LTS

**Decisão:** usar Node LTS (24 ou 26) em desenvolvimento e produção. A máquina local está com Node 25.
**Motivo:** versões ímpares do Node não são LTS e perdem suporte em poucos meses; produção precisa de atualizações de segurança.

---

## 15/11/2026 — Início do painel do vendedor

### D42 — Login do painel sem `nuxt-auth-sanctum`

**Contexto:** o painel precisa abrir sem internet (PWA). O módulo checa o usuário na API ao iniciar e, sem sinal, considera o vendedor deslogado.
**Decisão:** composable próprio (`useApi` + `useVendedor`, ~100 linhas) com cookie e CSRF do Sanctum. O perfil fica guardado no aparelho; só um 401 da API desloga.
**Em desenvolvimento:** o Nuxt repassa `/api` e `/sanctum` para o Laravel (`routeRules.proxy`), então navegador e API ficam na mesma origem.

### D43 — Primeira fatia do painel

**Entrou:** login do vendedor, produtos (criar, editar, ativar, estoque), venda presencial com fila offline e Pix gerado no celular, configuração de Pix e cartão.
**Adiado:** `turno_id` nas vendas (entra com a tabela `turnos`), reservas no PDV ("2 reservados"), troco no dinheiro, ajuste de estoque, métricas, push.


## 15/11/2026 — Área do cliente e reservas

### D44 — Pix da reserva montado no navegador (revisa D34)

**Decisão:** após o aceite, a API devolve a chave, o nome e a cidade do Pix do vendedor; "Meus pedidos" monta o BR Code e o QR com o mesmo código do PDV.
**Motivo:** evita duplicar a regra em PHP agora. **Pendente:** os e-mails ao cliente (D31) vão exigir o BR Code no backend; nesse momento, criar a versão PHP e o teste que compara as duas (D34).

### D45 — Login do vendedor e do cliente com guards de sessão separados

**Decisão:** rotas do painel usam `auth:vendedor` e as do cliente `auth:cliente` (sessão do Sanctum em modo SPA). Os dois podem estar logados no mesmo navegador.
**Pendente:** o app Flutter (token) vai exigir `auth:sanctum` com checagem do tipo de usuário.

### D46 — Escopo da área do cliente

**Entrou:** página pública `/v/{slug}` (SSR), cadastro e login do cliente, reserva com entrega ou retirada, Meus pedidos com Pix, "Já paguei" e código de entrega; no painel, Reservas, Turno e paradas. Expiração e apagamento de endereço agendados.
**Adiado:** mapa e GPS, status "Próxima parada" (D25), recuperação de senha do cliente, e-mails (D31), push (D26), termos de uso e política de privacidade.

### D47 — Mapa da tela inicial e GPS do vendedor

**Decisão:** a tela inicial é o mapa (Leaflet + OpenStreetMap via `@nuxtjs/leaflet`) com os vendedores em turno e um cartão com o mais próximo. O painel envia a posição a cada minuto enquanto está aberto e o vendedor liga "Mostrar minha localização no mapa" (aba Turno). Encerrar o turno apaga a posição; posição sem atualização há mais de 2h deixa de aparecer.
**Ambiente:** o site web usa HTTPS local (`lerd secure`), porque o navegador só libera o GPS em HTTPS.
**Adiado:** busca e tags do mapa (D18), check-in manual em parada e coordenadas das paradas no mapa.

### D48 — Mapa sem estabelecimentos: MapLibre + OpenFreeMap (revisa D47)

**Contexto:** o mapa deve mostrar só os vendedores. Os tiles padrão do OpenStreetMap trazem lojas e outros pontos, e o CARTO passou a exigir chave.
**Decisão:** MapLibre GL com o estilo Positron do OpenFreeMap (gratuito, sem chave, uso comercial permitido), que só tem ruas, bairros e nomes. Os vendedores viram botões sobre o mapa. O cartão inferior pode ser recolhido para ver só o mapa.
**Custo:** biblioteca maior que o Leaflet (mapa vetorial com WebGL).

### D49 — Login único

**Decisão:** uma tela só (`/entrar`, endpoint `POST /api/v1/login`). O sistema confere vendedor e depois cliente: vendedor vai para o painel, cliente volta ao início ou à reserva. `/painel/login` redireciona para `/entrar`.

