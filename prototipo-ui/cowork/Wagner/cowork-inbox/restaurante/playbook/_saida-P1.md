---
sessao: "P1"
thread: "P1 · Protótipo: Reservas, Cozinha e Pedidos"
dono: "[CC]"
data: 2026-10-07
base_lida: wagnerra23/oimpresso.com@main a6a073502b1e (Blades restaurant/*)
---
# _saida-P1 · as 3 telas que faltavam entraram no build

## Diferença da ficha
**Modificadores já existia** no build (`cfg-modificadores`, `configuracoes-cadastros.jsx` — tabela do `_saida-00` do sistema). A P1 fez 3 telas, não 4. A thread 02 do índice (Modificadores) usa a rota existente como alvo.

## Feito (só no build daqui)
| rota | tela | fonte de forma |
|---|---|---|
| `cfg-reservas` | lista + drawer de detalhe (mudar situação: aguardando · reservado · concluído · cancelado; excluir; enviar notificação) + drawer "Nova reserva" (local · cliente · correspondente · mesa · atendente · início · fim · nota · enviar notificação) | `restaurant/booking/{create,show}.blade.php` |
| `cfg-cozinha` | cartão por pedido (#nota · feito às · cliente · mesa · local · itens) + "Marcar como preparado"; some quando tudo preparado | `restaurant/kitchen/index` + `partials/show_orders` |
| `cfg-pedidos-rest` | filtro por atendente · "Itens prontos para servir" (marcar item como servido) · "Todos os seus pedidos" (marcar pedido como servido) | `restaurant/orders/index` + `partials/line_orders` |

- Situação do pedido **derivada dos itens**, mesma regra do `show_orders.blade.php`; cozinha e atendente mexem nos mesmos itens.
- Arquivos: `restaurante-operacao.jsx` (novo, `window.RestauranteOperacaoPage`) · `integra-extras.jsx` (expõe `RestauranteDados`, abas compartilhadas) · `app.jsx` (3 rotas) · `data.jsx` (3 entradas em Configurações) · host (script + bump).
- As 5 abas (Mesas · Atendentes · Reservas · Cozinha · Pedidos) aparecem nas 5 telas.

## Não feito
- Calendário de reservas (a Blade tem fullcalendar): ficou lista ordenada por início — se [W] quiser calendário, é Tweak.
- Atualização ao vivo da cozinha: botão "Atualizar", como a Blade.

## Para a A1
Rotas medíveis: `cfg-mesas` · `cfg-modificadores` · `cfg-reservas` · `cfg-cozinha` · `cfg-pedidos-rest`.
