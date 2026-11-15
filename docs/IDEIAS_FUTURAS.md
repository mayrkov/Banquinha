# Ideias Futuras

Ideias registradas para o futuro do produto. **Nenhuma delas entra no MVP**: implementá-las agora tiraria o projeto do escopo. O MVP continua focado no vendedor piloto (ver [ESCOPO_MVP.md](ESCOPO_MVP.md)).

Registradas em 11/11/2026.

## 1. "iFood simplificado": mapa com vários vendedores

O sistema pode evoluir de uma ferramenta para um vendedor ambulante para uma plataforma aberta a qualquer vendedor, inclusive restaurantes e comércios com endereço fixo.

- Ao abrir o sistema, o cliente vê um **mapa com os vendedores próximos** (uma "bolinha" por vendedor).
- Ao tocar num vendedor, vê o que ele tem à venda: cardápio simples e dinâmico, com disponibilidade real.
- Só aparecem no mapa os vendedores **em atividade**, inclusive os de endereço fixo. Quem está fora de atividade some do mapa, para manter a tela do cliente limpa (regra já adotada, ver [REGRAS_NEGOCIO.md](REGRAS_NEGOCIO.md)).
- [Proposto] Tag visual para o cliente indicando que o vendedor **não fica sempre naquele local** (ambulante). Não é necessário um campo "tipo de vendedor" para isso.
- **App para todos** (Android e iOS, em Flutter): clientes e vendedores baixam o app. A web continua existindo como porta de entrada (links divulgados e busca no Google).

### Impactos técnicos já identificados

- Busca "perto de mim" com **PostGIS** (já disponível no ambiente de desenvolvimento).
- Provedor de mapa com uso comercial (MapTiler, Stadia, Mapbox ou Google): os servidores públicos do OpenStreetMap não permitem uso pesado.
- Agrupamento de pontos no mapa (clustering) quando houver muitos vendedores.
- Atualização em tempo real por WebSocket (Laravel Reverb) no lugar de consultas periódicas.
- Pagamento online com split, se a plataforma cobrar comissão (ver D9 em [HISTORICO_DECISOES.md](HISTORICO_DECISOES.md)).
- Contas de cliente, termos de uso e política de privacidade (LGPD).

## 2. Tags de vendedor

Cada vendedor poderá ter **tags** que descrevem o que vende, por exemplo: `lanche`, `panificação`, `doces`, `bebidas`.

- Objetivo: permitir que o cliente **busque e filtre** vendedores no mapa ("quero lanche perto de mim").
- [Proposto] Lista de tags controlada pela plataforma (o vendedor escolhe entre as existentes), para evitar variações como "lanche", "lanches" e "lanchonete".
