# Escopo do MVP — App para Localizar Vendedor

## Visão geral

Sistema web (responsivo, pensado primeiro para celular) para um vendedor ambulante de pães de queijo recheados que hoje gerencia reservas pelo Instagram e WhatsApp. O objetivo é centralizar **cardápio, estoque, vendas, localização, rota programada e reservas** em um único sistema acessível pelo navegador.

**Público-alvo inicial:** 1 vendedor (piloto), que usa **InfinitePay** para receber. A estrutura fica aberta para outros vendedores no futuro.

### Mudança principal em relação à ideia inicial

O sistema deixa de ser só uma vitrine e passa a ser também o **caixa do vendedor**: **toda venda é registrada no sistema** no momento em que acontece, inclusive as presenciais. Assim:

- o estoque fica exato (sabe-se qual sabor foi vendido, mesmo quando sabores têm o mesmo preço);
- o cliente vê a disponibilidade real antes de reservar;
- o dashboard de vendas é alimentado automaticamente.

## Usuários

| Tipo | Cadastro | Login | Acesso |
|------|----------|-------|--------|
| Vendedor | Obrigatório | Sim | Painel no celular (prioridade) ou computador |
| Cliente | Obrigatório só para reservar (nome, e-mail, telefone, senha) | Sim | Aplicação web pública. Sem conta, apenas navega |

O cliente acompanha suas reservas na aba **"Meus pedidos"**.

## Funcionalidades — Vendedor

1. **Produtos:** criar, editar, ativar/desativar (ex.: recheio de carne, frango, Nutella, goiabada, doce de leite).
2. **Estoque:** informar a quantidade inicial do dia/turno. O sistema desconta a cada venda paga. Ajuste de estoque pela contagem física, com motivo (doação, perda, venda não registrada, outro).
3. **Venda presencial (PDV):** selecionar sabores e quantidades → escolher forma de pagamento → confirmar. Deve caber em ~3 toques. Preço padrão já preenchido, com possibilidade de alterar. Funciona sem internet.
4. **Formas de pagamento:** Pix, dinheiro e cartão (detalhes em [PAGAMENTOS.md](PAGAMENTOS.md)).
5. **Reservas:** ver reservas, aceitar ou negar dentro do prazo que ele define (padrão 15 min), confirmar o pagamento e "Entregar pedido" com o código de 4 dígitos do cliente.
6. **Localização e turno:** iniciar/encerrar turno, compartilhar localização.
7. **Rota programada:** cadastrar onde estará e quando (ex.: "Praça Principal, quarta 14, 18h").
8. **Painel de métricas:** por dia, semana e mês: faturamento cheio, descontos e faturamento real; vendas por sabor e forma de pagamento; doações e perdas; reservas; estoque restante.
9. **Fechamento de caixa:** total por forma de pagamento para conferir com maquininha, extrato e dinheiro em mãos.
10. **Notificações push** de reserva nova e de "Já paguei", mesmo com o painel fechado (no iPhone, exige adicionar o painel à tela de início).

## Funcionalidades — Cliente

1. **Status do vendedor:** *Vendendo agora*, *Próxima parada* ou *Esgotado hoje*, com a última atualização. Fora de atividade: some do mapa e a página mostra "Ops, o vendedor não está em atividade no momento".
2. **Cardápio com disponibilidade real** (estoque menos reservas já aceitas).
3. **Localização atual e próximas paradas** da rota.
4. **Conta:** cadastro com nome, e-mail, telefone e senha; recuperação de senha por e-mail.
5. **Reserva com entrega ou retirada:** escolher sabores e quantidades (não pode passar do disponível); para entrega, informar endereço e ponto de referência; para retirada, escolher uma parada programada.
6. **Meus pedidos:** acompanhar o status, pagar pelo Pix após a aceitação, tocar em "Já paguei" e ver o código de entrega.
7. **Cancelamento:** apenas enquanto a reserva estiver pendente.

## Fases sugeridas [Proposto]

| Fase | Conteúdo |
|------|----------|
| **0 — Protótipo técnico** | Testar se o deeplink do InfiniteTap abre e retorna corretamente a partir do navegador (ver [DECISOES_TECNICAS.md](DECISOES_TECNICAS.md)). |
| **1 — MVP** | Produtos, estoque e ajuste, venda presencial (Pix estático, dinheiro, cartão via InfiniteTap com confirmação do vendedor), conta de cliente, reservas com entrega ou retirada e pagamento por Pix estático, código de entrega, notificações push, localização/turno, rota, painel de métricas, fechamento de caixa, termos de uso e política de privacidade. |
| **2 — Pagamento online do piloto** | Checkout Integrado da InfinitePay: link de pagamento (Pix/cartão) com confirmação automática por webhook, para reservas e também para Pix presencial. |
| **3 — Multi-vendedor** | Cadastro de outros vendedores, gateway com split (ver [PAGAMENTOS.md](PAGAMENTOS.md)), configuração "como você recebe no cartão?". |

## Fora do escopo do MVP [Futuro]

- Gateway com split e múltiplos vendedores.
- Chat com categorização de mensagens.
- Avaliações de clientes.
- Relatórios avançados.
- App Flutter para clientes e vendedores (ver [DECISOES_TECNICAS.md](DECISOES_TECNICAS.md)).
- Mapa com vários vendedores ("iFood simplificado") e tags de vendedor (ver [IDEIAS_FUTURAS.md](IDEIAS_FUTURAS.md)).

## Descartado

- **Botão "esgotou" por sabor** como forma principal de controlar estoque: não evita o caso "restam 4, cliente pede 5". Substituído pelo registro de cada venda.
- **Contraoferta ao negar reserva** ("acabou goiabada, quer doce de leite?"): desnecessária com estoque exato, porque o cliente já vê o que está disponível.
