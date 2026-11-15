# App para Localizar Vendedor — Índice da documentação

Sistema web para um vendedor ambulante de pães de queijo recheados (vendedor piloto) centralizar cardápio, estoque, vendas, localização, rota e reservas, com estrutura aberta para outros vendedores no futuro.

## Arquivos

| Arquivo | Conteúdo |
|---------|----------|
| [Contexto.md](Contexto.md) | Problema original e motivação (texto inicial) |
| [Conceito.md](Conceito.md) | Ideia inicial de funcionalidades (texto inicial, mantido como registro) |
| [ESCOPO_MVP.md](ESCOPO_MVP.md) | O que entra no MVP, em que fase, e o que fica para depois |
| [REGRAS_NEGOCIO.md](REGRAS_NEGOCIO.md) | Regras de estoque, vendas, reservas, pagamento e localização |
| [FLUXOS.md](FLUXOS.md) | Passo a passo de cada fluxo (venda presencial, reserva, pagamento, turno, fechamento) |
| [PAGAMENTOS.md](PAGAMENTOS.md) | Desenho de pagamentos: Pix, dinheiro, cartão, InfinitePay, split para multi-vendedor |
| [MODELO_DADOS.md](MODELO_DADOS.md) | Tabelas e relacionamentos |
| [DECISOES_TECNICAS.md](DECISOES_TECNICAS.md) | Stack, integrações, riscos técnicos e primeira tarefa técnica |
| [HISTORICO_DECISOES.md](HISTORICO_DECISOES.md) | Registro das decisões tomadas, com o motivo de cada uma |
| [PERGUNTAS_ABERTAS.md](PERGUNTAS_ABERTAS.md) | O que ainda precisa ser definido |
| [IDEIAS_FUTURAS.md](IDEIAS_FUTURAS.md) | Ideias fora do MVP: mapa com vários vendedores ("iFood simplificado") e tags |

## Ideia central (resumo)

O sistema é, ao mesmo tempo:

1. **Caixa de bolso do vendedor** — toda venda (presencial ou reserva) é registrada no sistema, o que mantém o estoque exato e alimenta o dashboard.
2. **Vitrine para o cliente** — mostra o que ainda tem, onde o vendedor está, por onde vai passar e permite reservar.

Como cada venda é registrada, o estoque mostrado ao cliente é confiável, e isso é o que resolve as perguntas que hoje chegam pelo Instagram e WhatsApp ("onde você está?", "ainda tem?").

## Legenda usada nos documentos

- **[Decidido]** — definido em conversa com o responsável pelo projeto.
- **[Proposto]** — sugestão registrada, ainda a validar com o vendedor ou durante o desenvolvimento.
- **[Futuro]** — fora do MVP.
