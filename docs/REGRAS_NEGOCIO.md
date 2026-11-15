# Regras de Negócio — MVP

## Estoque [Decidido]

1. Toda venda é registrada no sistema, presencial ou por reserva.
2. O estoque de um produto é descontado quando a venda é **confirmada como paga** (por confirmação automática do provedor ou manual do vendedor).
3. **Disponível para o cliente = estoque − quantidade em reservas aceitas e ainda não pagas.** Reservas pendentes não bloqueiam estoque.
4. Na tela de venda presencial, o vendedor vê o estoque e quanto dele está reservado (ex.: "Nutella: 4 (2 reservados)"), para não vender o pão de quem reservou.

## Ajuste de estoque [Decidido]

1. No fim do turno (ou quando quiser), o vendedor informa a **contagem física** de cada produto. O sistema mostra quanto deveria haver e calcula a diferença.
2. A diferença é dividida por motivo:
   - **Doação:** sai do estoque, sem receita.
   - **Perda** (ex.: caiu no chão): sai do estoque, sem receita.
   - **Venda não registrada:** informa quantidade, **preço unitário praticado** e **forma de pagamento**. O sistema cria uma venda com esses dados, que entra nos ganhos e no fechamento de caixa.
   - **Outro:** sai do estoque, com observação.
3. Exemplo: o sistema indica 10 pães, a contagem dá 3. O vendedor informa 4 doados e 3 vendidos a R$ 5,00 em dinheiro. O sistema registra 4 doações e uma venda de R$ 15,00, e o estoque fica em 3.
4. Todo ajuste fica registrado, com o valor que deixou de entrar (doações e perdas pelo preço padrão; descontos pela diferença entre o preço padrão e o praticado).

## Preço na venda [Decidido]

1. O PDV começa com o **preço padrão** do produto e permite **alterar o preço** na hora da venda (ex.: vender mais barato no fim do dia).
2. A venda guarda o preço padrão e o preço praticado, para o painel mostrar o desconto concedido.

## Reservas [Decidido]

1. **Conta obrigatória:** para reservar, o cliente precisa estar logado. Sem conta, ele só navega e vê cardápio, mapa e paradas.
2. **Entrega ou retirada:** o cliente escolhe.
   - **Entrega:** exige **endereço de entrega** e aceita um ponto de referência. Soma a **taxa de entrega** configurada pelo vendedor (padrão R$ 0,00) ao total.
   - **Retirada:** o cliente escolhe uma das **paradas programadas** do vendedor.
3. **Limite:** o cliente só pode reservar até a quantidade disponível de cada produto. Com 4 disponíveis, não é possível escolher 5.
4. Uma reserva pode ter **vários produtos** (ex.: 2 Nutella + 3 goiabada).
5. **Criação:** a reserva nasce `pendente`. Não bloqueia estoque.
6. **Prazo de resposta:** o vendedor define quantos minutos tem para responder (padrão: 15). Sem resposta no prazo, a reserva vira `expirado` e o cliente é avisado.
7. **Aceitação:** somente o vendedor aceita ou nega. Ao aceitar, o sistema verifica de novo a disponibilidade e **bloqueia** as unidades (`aceito`). Não há prazo automático para o pagamento: as unidades ficam bloqueadas até o pagamento ou até o vendedor cancelar.
8. **Negativa:** status `negado`.
9. **Cancelamento pelo cliente:** só enquanto `pendente` (`cancelado_cliente`).
10. **Cancelamento pelo vendedor:** enquanto `aceito` (ex.: cliente não pagou). As unidades são liberadas (`cancelado_vendedor`).
11. **Pagamento:** depois do aceite, o cliente paga pelo Pix estático mostrado no pedido. Ele pode tocar em "Já paguei" para avisar o vendedor. O vendedor confere no app do banco e confirma. A reserva vira `pago`, a venda é registrada e o estoque é descontado.
12. **Código de entrega:** ao confirmar o pagamento, o sistema gera um **código de 4 dígitos**, visível só para o cliente em "Meus pedidos".
13. **Entrega ou retirada:** o vendedor toca em "Entregar pedido" e digita o código informado pelo cliente. Código correto → `entregue`. O mesmo código vale para a retirada.
14. Sem regra antiabuso no MVP: pedidos pendentes não bloqueiam estoque e o vendedor filtra quem é real ao aceitar.

## Pagamento [Decidido]

Detalhes técnicos em [PAGAMENTOS.md](PAGAMENTOS.md).

1. Formas aceitas: **Pix, dinheiro e cartão**.
2. O **pagamento de reserva só é solicitado depois que o vendedor aceita**, para evitar devolução quando a reserva for negada.
3. **Dinheiro:** o vendedor marca como pago.
4. **Pix estático (gerado pelo sistema):** o vendedor confere no app do banco e marca como pago. Vale para venda presencial e reserva.
5. **Cartão via InfinitePay (piloto):** o sistema abre o app da InfinitePay com o valor. Quando o vendedor volta ao painel, responde "Pagamento concluído?": *Sim* registra a venda e desconta o estoque; *Não* cancela a venda.
6. **Cartão em outra maquininha:** o vendedor cobra na maquininha/app dele e confirma numa tela já preenchida com o valor.
7. **Checkout online InfinitePay (fase 2):** confirmação automática por webhook, **sempre conferida na API** antes de marcar como pago.
8. O dinheiro do cliente vai **direto para a conta do vendedor**. A plataforma não recebe nem repassa valores.

## Localização e turno [Decidido]

1. O vendedor tem um botão **Iniciar/Encerrar turno**. Em atividade ≠ GPS ligado: ele pode estar em atividade num ponto fixo sem compartilhar GPS.
2. Status mostrados ao cliente: *Vendendo agora*, *Próxima parada*, *Esgotado hoje*.
3. Sempre exibir "atualizado há X min".
4. A localização só é enviada durante o turno. Desligamento automático se ficar muito tempo sem atualização (ex.: 2h), para nunca expor a casa do vendedor.
5. O vendedor pode programar paradas com local, data/hora de início e fim.
6. **Vendedor fora de atividade não aparece no mapa**, mesmo que tenha endereço fixo. Só vendedores em atividade são mostrados, para manter a tela do cliente limpa.
7. Quem abre a página do vendedor fora de atividade (ex.: por um link) vê "Ops, o vendedor não está em atividade no momento" e as próximas paradas, se houver.

## Notificações [Decidido]

1. **Vendedor:** notificação push quando chega reserva nova e quando o cliente toca em "Já paguei", mesmo com o painel fechado. Com o painel aberto, também som/vibração.
2. No iPhone, o push web só funciona se o painel for **adicionado à tela de início** (iOS 16.4 ou superior).
3. **Cliente:** e-mail quando a reserva é aceita (com o Pix para pagar), negada ou expirada.

## Clientes [Decidido]

1. Cadastro com **nome, e-mail, telefone e senha**. Recuperação de senha por e-mail. O telefone é obrigatório para o vendedor poder ligar durante a entrega.
2. O cliente vê suas reservas na aba **"Meus pedidos"**.
3. **Endereço de entrega (quando houver) é apagado 3 dias depois** de a reserva ser encerrada (entregue, negada, expirada ou cancelada) (LGPD).
4. Termos de uso e política de privacidade são necessários antes do lançamento, porque o sistema guarda conta, telefone e endereço.

## Painel de métricas do vendedor [Decidido]

Por dia, semana e mês:

1. Faturamento a preço cheio, descontos concedidos e faturamento real. Taxas de entrega aparecem separadas.
2. Vendas por sabor e por forma de pagamento.
3. Doações e perdas, em quantidade e em valor (pelo preço padrão).
4. Reservas: recebidas, aceitas, negadas, expiradas e entregues.
5. Estoque restante.
