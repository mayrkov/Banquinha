# Pagamentos

Pesquisa feita em 10/11/2026. Condições de provedores mudam com frequência: confirmar na documentação atual antes de implementar.

## Princípios [Decidido]

1. **O dinheiro do cliente vai direto para a conta do vendedor.** A plataforma não recebe nem repassa valores (evita responsabilidade fiscal e de guarda de dinheiro de terceiros).
2. **A venda é registrada no sistema; a forma de pagamento é um atributo dela.** Isso vale para qualquer banco ou maquininha.
3. **Reserva só é paga depois de aceita** pelo vendedor.
4. Toda confirmação automática recebida por webhook é **conferida na API do provedor** antes de marcar como pago.

## Resumo por situação

| Situação | Piloto (InfinitePay) | Outros vendedores |
|---|---|---|
| Reserva online | MVP: Pix estático → cliente avisa "Já paguei" → vendedor confirma. Fase 2: link do Checkout InfinitePay → confirmação automática | Pix estático → vendedor confirma |
| Presencial — Pix | Fase 1: Pix estático → vendedor confirma. Fase 2: QR do checkout → automática | Pix estático → vendedor confirma |
| Presencial — cartão | InfiniteTap por deeplink → vendedor responde "Pagamento concluído?" | Maquininha dele → tela pré-preenchida → vendedor confirma |
| Dinheiro | Vendedor marca como pago | Vendedor marca como pago |

## 1. Pix estático gerado pelo sistema (sem integração)

- O QR Code Pix é um texto padronizado pelo Banco Central (BR Code / "Pix copia e cola") com: chave Pix, nome e cidade do recebedor, valor e um identificador opcional.
- O sistema monta esse texto a partir da **chave Pix cadastrada pelo vendedor** e gera o QR. Não há comunicação com banco.
- O dinheiro cai na conta do vendedor.
- **Limitação:** o sistema não sabe quando o pagamento cai. O vendedor confere no app do banco (nome do pagador e valor; o identificador aparece em alguns bancos).
- Implementação: bibliotecas PHP open source para "Pix BR Code" / "Pix copia e cola" + qualquer biblioteca de QR Code.

## 2. Cartão presencial — InfiniteTap por deeplink (piloto)

Integração oficial e gratuita da InfinitePay, sem chave de API.

- Deeplink de início: `infinitepaydash://infinitetap-app?...`
  - `amount` — centavos (mínimo 100)
  - `payment_method` — `credit` ou `debit`
  - `installments` — até 12 (parcela mínima R$ 1,00)
  - `order_id` — ID da venda no sistema
  - `result_url` — endereço de retorno
  - `app_client_referrer` — nome do sistema (sempre o mesmo)
  - `handle`, `doc_number` — opcionais, para verificar a conta logada
  - `af_force_deeplink=true` — no iOS
- Retorno (sucesso ou erro) em `result_url`: `order_id`, `nsu`, `aut`, `card_brand`, `user_id`, `access_id`, `handle`, `merchant_document`, e `warning` em caso de erro.
- Requisitos: app InfinitePay instalado e logado; uma venda de baixo valor feita antes no app (pode ser cancelada); no iOS, aceitar os termos da Apple no primeiro uso.
- **Decisão [11/11/2026]:** o sistema **não depende do retorno** em `result_url`. Ao voltar ao painel, o vendedor responde "Pagamento concluído?" (Sim registra a venda; Não cancela). Motivo: no iPhone, com o painel instalado na tela de início, o retorno abriria no Safari, que não compartilha o login com o painel instalado.
- **Risco:** a documentação é voltada a apps nativos. Precisa testar se o deeplink abre o app com o valor a partir do navegador (ver Fase 0 em [DECISOES_TECNICAS.md](DECISOES_TECNICAS.md)).
- **Plano B:** se não abrir, o vendedor abre o app da InfinitePay manualmente e confirma na tela pré-preenchida; ou antecipar o app Flutter do vendedor.
- Documentação: https://www.infinitepay.io/checkout-tap

## 3. Pagamento online — Checkout Integrado InfinitePay (fase 2, piloto)

- `POST https://api.checkout.infinitepay.io/links` com `handle` do vendedor (InfiniteTag sem `$`), `items` (descrição, quantidade, preço em centavos), `order_nsu`, `redirect_url`, `webhook_url`.
- Cliente paga por **Pix ou cartão** (crédito em até 12x). O dinheiro cai na conta dona do handle.
- Webhook com eventos `paid`, `refunded`, `canceled`. **Não é assinado:** sempre conferir em `POST https://api.checkout.infinitepay.io/payment_check`.
- Sem chave de API para o básico. A chave (criada na conta do vendedor) só é necessária para opções avançadas (desligar Pix, validade do link, etc.).
- Sem mensalidade; taxa de link de pagamento da conta do vendedor. **Conferir a taxa do Pix via link**, que pesa em vendas de R$ 5.
- O webhook exige servidor com endereço público (em desenvolvimento local, usar túnel).
- Também pode ser usado na venda presencial: QR do link na tela do vendedor → cliente paga no celular dele → confirmação automática.
- Não tem split nem subcontas.
- Documentação: https://www.infinitepay.io/checkout-documentacao

## 4. Integrações de cartão de outros provedores (pesquisa)

| Provedor | Integração | Serve para sistema web? |
|---|---|---|
| InfinitePay (InfiniteTap) | Deeplink oficial, gratuito, Android e iOS | Sim (a testar) |
| Mercado Pago (Point Smart) | API envia cobrança para a maquininha física e notifica o status | Sim, mas exige Point Smart. Não há nada documentado para o Point Tap (celular) |
| PagBank (Tap On) | SDK para app Android nativo, com chave da equipe de integração. Sem iOS | Só como app Android |
| SumUp | URL scheme `sumupmerchant://` em app Android; descontinuado pela SumUp | Não recomendado |
| Ton/Stone (TapTon) | Nenhuma documentação pública encontrada | Não |

**Decisão [Proposto]:** configuração por vendedor "Como você recebe no cartão?" → InfinitePay (automático) / Mercado Pago Point Smart (automático, futuro) / Outro (confirmação manual com tela pré-preenchida).

## 5. Multi-vendedor: gateway com split [Futuro]

Necessário **somente se a plataforma for ficar com uma comissão por venda**. Se o modelo de receita for mensalidade (ou nenhum no início), basta cada vendedor conectar a própria conta.

| Gateway | Pix | Cartão | Modelo | Observação |
|---|---|---|---|---|
| Mercado Pago (marketplace) | Sim | Sim | Vendedor conecta a conta MP dele (OAuth); plataforma define comissão | Dinheiro direto na conta do vendedor. Mais próximo do objetivo |
| Woovi / OpenPix | Sim | Não no split | Split Partner (afiliados, plataforma cobra taxa) ou subcontas | Sem taxa extra pelo split; ativação pelo suporte. Subcontas deixam o dinheiro na estrutura da conta da plataforma |
| Asaas | Sim | Sim | Subcontas + split | Bem documentado; cadastro/análise de cada vendedor |
| Efí | Sim | Sim (cobranças) | Split Pix e de cobranças | Recebedores precisam de conta Efí |
| AbacatePay | — | — | Não foi confirmado suporte a split | Sem split, o dinheiro cairia na conta da plataforma: não serve |

Perguntas para o comercial do gateway escolhido: aceita vendedor CPF/MEI ou só CNPJ? Como o vendedor se cadastra? Em quanto tempo o dinheiro fica disponível? Taxas por Pix e cartão?

## 6. Modelo de dados relacionado

Ver tabela `pagamentos` em [MODELO_DADOS.md](MODELO_DADOS.md). Uma venda pode ter várias tentativas de pagamento (uma falha e outra aprovada), e novos provedores entram sem mudar a tabela de vendas.
