# Modelo de Dados — MVP

Valores de status gravados sem acento (ex.: `entregue`). Banco: PostgreSQL + PostGIS.

- **Valores em dinheiro** são gravados como inteiro em centavos (ex.: R$ 15,00 = `1500`). Ver D35.
- **Coordenadas** ficam em `latitude`/`longitude` decimal no MVP. A troca por coluna PostGIS `geography(Point, 4326)` está planejada para o multi-vendedor. Ver D37.
- **Datas** são gravadas em UTC e exibidas em `America/Sao_Paulo`.

## Tabelas

### vendedores
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| id | inteiro | Não | PK |
| nome | string | Não | |
| email | string | Não | Único, login |
| telefone | string | Sim | |
| senha | string | Não | Hash |
| descricao | texto | Sim | Perfil público |
| chave_pix | string | Sim | Para gerar Pix estático |
| nome_recebedor_pix | string | Sim | Nome que vai no BR Code |
| cidade_recebedor_pix | string | Sim | Cidade que vai no BR Code |
| provedor_cartao | string | Sim | `infinitepay`, `mercadopago_point`, `outro` |
| infinitepay_handle | string | Sim | InfiniteTag sem `$` |
| prazo_resposta_min | inteiro | Não | Minutos para responder uma reserva. Padrão 15 |
| taxa_entrega | inteiro (centavos) | Não | Padrão 0 |
| em_atividade | booleano | Não | Turno iniciado. Fora de atividade = não aparece no mapa |
| criado_em / atualizado_em | datetime | | |

### clientes
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| id | inteiro | Não | PK |
| nome | string | Não | |
| email | string | Não | Único, login |
| telefone | string | Não | Contato durante a entrega |
| senha | string | Não | Hash |
| criado_em / atualizado_em | datetime | | |

> Vendedor e cliente ficam em tabelas separadas: cada um tem login e telas próprias.

### produtos
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| id | inteiro | Não | PK |
| vendedor_id | inteiro | Não | FK → vendedores |
| nome | string | Não | Ex.: Pão de queijo recheado de Nutella |
| descricao | texto | Sim | |
| preco | inteiro (centavos) | Não | Preço padrão |
| estoque | inteiro | Não | Quantidade física atual |
| ativo | booleano | Não | Visível no cardápio |
| criado_em / atualizado_em | datetime | | |

> Disponível para reserva = `estoque` − soma das quantidades em reservas `aceito` desse produto. Calculado, não gravado. Ao virar `pago`, o estoque é descontado e a reserva deixa de contar como bloqueio.

### reservas
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| id | inteiro | Não | PK |
| vendedor_id | inteiro | Não | FK → vendedores |
| cliente_id | inteiro | Não | FK → clientes |
| codigo | string | Não | Único, aleatório, 8+ caracteres. Identifica a reserva em links e e-mails (não usar o `id` sequencial) |
| tipo | string | Não | `entrega` ou `retirada` |
| parada_id | inteiro | Sim | FK → paradas. Obrigatório quando `tipo` = `retirada` |
| endereco_entrega | texto | Sim | Obrigatório quando `tipo` = `entrega`; apagado 3 dias após o encerramento |
| referencia_entrega | string | Sim | Ponto de referência; apagado junto com o endereço |
| endereco_apagado_em | datetime | Sim | Quando o endereço foi apagado |
| taxa_entrega | inteiro (centavos) | Não | Copiada do vendedor na criação; 0 na retirada |
| valor_total | inteiro (centavos) | Não | Itens + taxa de entrega |
| status | string | Não | `pendente`, `aceito`, `negado`, `expirado`, `cancelado_cliente`, `cancelado_vendedor`, `pago`, `entregue` |
| responder_ate | datetime | Não | Criação + `prazo_resposta_min` do vendedor |
| cliente_informou_pagamento_em | datetime | Sim | Quando o cliente tocou em "Já paguei" |
| codigo_entrega | string | Sim | 4 dígitos, gerado ao confirmar o pagamento |
| encerrada_em | datetime | Sim | Entregue, negada, expirada ou cancelada. Base para apagar o endereço |
| criado_em / atualizado_em | datetime | | |

### itens_reserva
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| id | inteiro | Não | PK |
| reserva_id | inteiro | Não | FK → reservas |
| produto_id | inteiro | Não | FK → produtos |
| quantidade | inteiro | Não | |
| preco_unitario | inteiro (centavos) | Não | Preço no momento da reserva |

### vendas
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| id | inteiro | Não | PK |
| vendedor_id | inteiro | Não | FK → vendedores |
| origem | string | Não | `presencial`, `reserva` ou `ajuste` (venda não registrada informada no ajuste de estoque) |
| reserva_id | inteiro | Sim | FK → reservas |
| turno_id | inteiro | Sim | FK → turnos |
| forma_pagamento | string | Sim | `pix`, `dinheiro`, `cartao` (definida ao pagar) |
| taxa_entrega | inteiro (centavos) | Não | Padrão 0. Vem da reserva |
| valor_total | inteiro (centavos) | Não | Soma dos preços praticados + taxa de entrega |
| status | string | Não | `aberta`, `paga`, `cancelada` |
| pago_em | datetime | Sim | Momento em que o estoque é descontado |
| criado_em | datetime | Não | |
| id_local | string | Sim | UUID gerado no celular, para sincronizar vendas feitas sem internet sem duplicar. Índice único em (`vendedor_id`, `id_local`) (D36) |

### itens_venda
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| id | inteiro | Não | PK |
| venda_id | inteiro | Não | FK → vendas |
| produto_id | inteiro | Não | FK → produtos |
| quantidade | inteiro | Não | |
| preco_padrao | inteiro (centavos) | Não | Preço do produto no momento da venda |
| preco_unitario | inteiro (centavos) | Não | Preço praticado. Desconto = `preco_padrao` − `preco_unitario` |

### pagamentos
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| id | inteiro | Não | PK |
| venda_id | inteiro | Não | FK → vendas |
| provedor | string | Não | `manual`, `pix_estatico`, `infinitetap`, `infinitepay_checkout`, ... |
| metodo | string | Não | `pix`, `dinheiro`, `credito`, `debito` |
| valor | inteiro (centavos) | Não | |
| parcelas | inteiro | Sim | |
| status | string | Não | `pendente`, `aprovado`, `recusado`, `cancelado`, `estornado` |
| id_externo | string | Sim | NSU / ID da transação no provedor |
| codigo_autorizacao | string | Sim | |
| bandeira | string | Sim | |
| link | string | Sim | Link do checkout, quando houver |
| dados_retorno | json | Sim | Retorno bruto do provedor, para suporte |
| pago_em | datetime | Sim | |
| criado_em | datetime | Não | |

### turnos
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| id | inteiro | Não | PK |
| vendedor_id | inteiro | Não | FK → vendedores |
| inicio | datetime | Não | |
| fim | datetime | Sim | |

### ajustes_estoque
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| id | inteiro | Não | PK |
| produto_id | inteiro | Não | FK → produtos |
| turno_id | inteiro | Sim | FK → turnos |
| motivo | string | Não | `doacao`, `perda`, `venda_nao_registrada`, `outro` |
| quantidade | inteiro | Não | Unidades retiradas do estoque por este motivo |
| venda_id | inteiro | Sim | FK → vendas. Preenchido quando `motivo` = `venda_nao_registrada` |
| preco_padrao | inteiro (centavos) | Não | Para calcular o valor de doações e perdas |
| observacao | string | Sim | Obrigatória quando `motivo` = `outro` |
| criado_em | datetime | Não | |

> Uma contagem pode gerar várias linhas (ex.: 4 `doacao` + 3 `venda_nao_registrada` do mesmo produto).

### localizacao_vendedor
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| vendedor_id | inteiro | Não | PK/FK → vendedores (uma linha por vendedor) |
| latitude | decimal | Não | |
| longitude | decimal | Não | |
| atualizado_em | datetime | Não | Usado para "atualizado há X min" e desligamento automático |

### paradas (antes `rota_programada`)
| Campo | Tipo | Nulo | Observação |
|-------|------|------|------------|
| id | inteiro | Não | PK |
| vendedor_id | inteiro | Não | FK → vendedores |
| local | string | Não | Ex.: Praça Principal |
| latitude / longitude | decimal | Sim | Para aparecer no mapa |
| inicio | datetime | Não | |
| fim | datetime | Sim | |
| ativo | booleano | Não | |
| criado_em | datetime | Não | |

### inscrições de push
Guardadas na tabela do pacote de Web Push usado pelo Laravel (endpoint e chaves de cada aparelho, ligadas ao vendedor).

## Relacionamentos

- Vendedor → muitos produtos, reservas, vendas, turnos, paradas; uma localização atual.
- Cliente → muitas reservas.
- Reserva → muitos itens_reserva; opcionalmente uma parada (retirada); ao ser paga gera uma venda.
- Venda → muitos itens_venda e muitos pagamentos (tentativas).
- Produto → muitos itens_reserva, itens_venda e ajustes_estoque.
- Ajuste de estoque `venda_nao_registrada` → uma venda.

## Exemplo

1. Turno iniciado com Nutella: estoque 10.
2. Cliente reserva 3 Nutella → `pendente`; disponível continua 10.
3. Vendedor aceita → `aceito`; disponível para outros = 7; estoque físico continua 10.
4. Venda presencial de 2 Nutella paga no cartão → estoque 8; disponível = 5.
5. Cliente paga o Pix e toca em "Já paguei"; vendedor confere e confirma → reserva `pago`, venda `paga` (origem `reserva`), estoque 5, código de entrega gerado; disponível = 5.
6. Vendedor entrega, digita o código → reserva `entregue`. Três dias depois, o endereço é apagado.
7. Fim do turno: sistema indica 5, contagem física 1 → vendedor informa 2 doados e 2 vendidos a R$ 8,00 em dinheiro. O sistema registra 2 `doacao` e uma venda de R$ 16,00 (origem `ajuste`); estoque 1.
