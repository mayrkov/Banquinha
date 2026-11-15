# Perguntas Abertas e Decisões Pendentes

## Resolvidas (ver [HISTORICO_DECISOES.md](HISTORICO_DECISOES.md))

- [x] O banco do vendedor gera QR Code estático com valor fixo? → Não é necessário: o próprio sistema gera o Pix estático com valor a partir da chave Pix (D6).
- [x] Pagamento presencial ou link de pagamento? → Presencial no MVP; reserva por Pix estático (D21); link do Checkout InfinitePay na fase 2 (D8).
- [x] Como o vendedor comprova que recebeu? → Dinheiro, Pix estático e cartão: confirmação do vendedor (D6, D24). Checkout: confirmação automática (D8).
- [x] Cadastro de cliente? → Obrigatório para reservar (D20).
- [x] Múltiplos vendedores? → Futuro, com gateway com split se houver comissão (D9).
- [x] Confirmar Laravel + SQLite → Laravel 13 (API) + Nuxt + PostgreSQL/PostGIS (D10, D13).
- [x] Autenticação do vendedor → recuperação de senha por e-mail (D15).
- [x] Modo sem internet (PWA) no MVP → sim, no painel do vendedor (D10).
- [x] Status de turno → *Vendendo agora*, *Próxima parada*, *Esgotado hoje* (D25).
- [x] Prazo de resposta e expiração de reservas → prazo definido pelo vendedor, padrão 15 min; sem expiração após aceite (D21, D22).
- [x] Acompanhamento da reserva → aba "Meus pedidos" na conta do cliente (D20).
- [x] Aviso de reserva → notificação push no MVP (D26).
- [x] Ajuste de estoque → sim, com motivo (D27).
- [x] Antiabuso → não necessário (D21).
- [x] Android ou iPhone? → iPhone (D29).
- [x] O vendedor topa registrar toda venda? → Sim, pelo PDV (D2, D27).
- [x] Entrega → entra no MVP (D19).
- [x] E-mail ao cliente → sim, ao aceitar, negar ou expirar (D31).
- [x] Taxa de entrega → configurável pelo vendedor, padrão R$ 0,00 (D33).
- [x] Reserva só com entrega ou também retirada? → As duas; retirada numa parada programada (D30).

## Validar com o vendedor piloto

> O MVP será apresentado pronto (D32). Estas perguntas ficam para depois da apresentação; até lá, o sistema usa valores que o vendedor pode alterar no painel.

- [ ] Confirmar sabores e preços (registrado: Nutella R$ 15,00, doce de leite R$ 10,00, goiabada R$ 10,00).
- [ ] Quantas mensagens por dia ele recebe perguntando "onde você está?" / "ainda tem?"
- [ ] Quantas reservas recebe hoje?
- [ ] Qual o plano de recebimento dele na InfinitePay e a taxa de Pix via link de pagamento?
- [ ] Ele entrega em qualquer lugar ou só perto da rota?

## Técnicas

- [ ] O deeplink do InfiniteTap abre o app com o valor a partir do navegador do iPhone? (Fase 0, com o vendedor)
- [ ] Hospedagem de produção: servidor dedicado (D14); falta escolher provedor e plano.
- [ ] Serviço de envio de e-mail em produção.
- [x] Biblioteca de Web Push para o Laravel → `laravel-notification-channels/webpush` (D39).

## Produto


## Futuro: entrega

- [ ] Raio máximo ou entrega só ao longo da rota?
- [ ] Pedido mínimo?
- [ ] Como mostrar o endereço no mapa junto com a rota na hora de aceitar?

## Futuro: multi-vendedor

- [ ] Modelo de receita da plataforma: comissão por venda, mensalidade ou nenhum?
- [ ] Gateway com split: Mercado Pago marketplace, Woovi, Asaas ou Efí?
- [ ] O gateway aceita vendedor CPF/MEI? Prazo de recebimento? Taxas?
- [ ] Chat entre vendedor e cliente com categorização de mensagens?
- [ ] Um app com perfis (cliente e vendedor) ou dois apps separados?
