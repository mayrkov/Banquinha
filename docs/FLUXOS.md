# Fluxos — MVP

## Fluxo 1: Venda presencial [Decidido]

Operado pelo **vendedor**, no celular dele. O cliente não precisa acessar o sistema.

```
1. Cliente chega e pede (ex.: 2 Nutella + 1 goiabada)
2. Vendedor seleciona os sabores e quantidades → sistema mostra o total
   (preço padrão já preenchido; o vendedor pode alterar o preço do item)
3. Vendedor escolhe a forma de pagamento:
   a) Pix      → sistema mostra QR Code com o valor na tela do vendedor
                 → cliente paga → vendedor confere no banco → "Pago"
                 (fase 2: QR do checkout InfinitePay → confirmação automática)
   b) Dinheiro → vendedor recebe → "Pago" (opcional: cálculo de troco)
   c) Cartão   → piloto: sistema abre o app da InfinitePay com o valor preenchido
                 → cliente aproxima o cartão → vendedor volta ao painel
                 → "Pagamento concluído?" Sim = pago / Não = venda cancelada
                 → outras maquininhas: vendedor cobra nela, volta ao sistema
                   e confirma na tela já preenchida
4. Ao confirmar o pagamento: estoque descontado, venda registrada no painel
```

Requisitos: no máximo ~3 toques no caminho comum. Funcionar sem internet (a venda fica no celular e é enviada quando o sinal voltar).

## Fluxo 2: Cliente faz uma reserva [Decidido]

```
1. Cliente acessa a página pública do vendedor (ou toca nele no mapa)
2. Vê status, localização e próximas paradas
3. Vê cardápio com quantidade disponível (estoque − reservas aceitas)
4. Escolhe produtos e quantidades (limitado ao disponível)
5. Faz login (ou cria conta: nome, e-mail, telefone, senha)
6. Escolhe entrega (endereço e ponto de referência; soma a taxa de entrega, se houver)
   ou retirada (uma das paradas programadas)
7. Confirma → reserva "pendente" → aparece em "Meus pedidos"
8. Vendedor recebe notificação push (som/vibração se o painel estiver aberto)
9. Vendedor aceita ou nega dentro do prazo que ele definiu (padrão 15 min)
   - Aceita: sistema revalida disponibilidade, bloqueia as unidades → "aceito"
   - Nega:   "negado"
   - Sem resposta no prazo: "expirado"
10. Cliente vê o novo status em "Meus pedidos" e recebe e-mail
```

## Fluxo 3: Pagamento e entrega/retirada da reserva [Decidido]

```
1. Reserva "aceito" → "Meus pedidos" mostra o Pix (QR e copia e cola) com o valor
2. Cliente paga no app do banco e toca em "Já paguei"
3. Vendedor recebe notificação, confere no app do banco e confirma o pagamento
   → reserva "pago", venda registrada, estoque descontado
   → sistema gera o código de entrega de 4 dígitos, visível ao cliente
   (fase 2, piloto: link do checkout InfinitePay → webhook → confirmação automática)
4. Entrega: vendedor vai ao endereço (botão para abrir no mapa do celular)
   Retirada: cliente vai até a parada escolhida
5. Vendedor toca em "Entregar pedido" e digita o código dito pelo cliente
   → código correto: reserva "entregue"
6. Três dias depois do encerramento, o endereço (se houver) é apagado
```

Se o cliente não pagar, o vendedor pode cancelar a reserva aceita (`cancelado_vendedor`), liberando as unidades.

## Fluxo 4: Cliente cancela uma reserva [Decidido]

```
1. Cliente abre a reserva em "Meus pedidos"
2. Se o status for "pendente", pode cancelar → "cancelado_cliente"
3. Reservas já aceitas não podem ser canceladas pelo app (falar com o vendedor)
```

## Fluxo 5: Turno, localização e rota [Decidido]

```
1. Vendedor informa o estoque inicial do turno
2. Toca em "Iniciar turno" → status "em atividade", aparece no mapa
3. Se quiser, compartilha a localização (atualizada a cada 1–2 min com o painel aberto)
   ou faz "check-in" manual num ponto da rota
4. Cadastra paradas futuras: local, início, fim
5. Toca em "Encerrar turno" → some do mapa, localização para de ser enviada
   → quem abrir a página dele vê "Ops, o vendedor não está em atividade no momento"
```

## Fluxo 6: Fechamento de caixa e ajuste de estoque [Decidido]

```
1. Ao encerrar o turno, o sistema mostra:
   Cartão: R$ X · Pix: R$ Y · Dinheiro: R$ Z · Total por sabor
2. Vendedor compara com o relatório da InfinitePay/maquininha, extrato e dinheiro em mãos
3. Vendedor informa a contagem física das sobras
4. Para cada diferença, informa o motivo:
   - doação (quantidade)
   - perda (quantidade)
   - venda não registrada (quantidade, preço unitário, forma de pagamento) → vira venda
   - outro (quantidade, observação)
5. Sistema ajusta o estoque e atualiza o fechamento e o painel de métricas
```

## Fluxo 7: Gestão de produtos [Decidido]

```
1. Vendedor acessa "Produtos"
2. Cria/edita: nome, descrição, preço padrão, ativo/inativo
3. Produto inativo não aparece no cardápio
```
