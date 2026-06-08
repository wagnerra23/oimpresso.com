# Visual Comparison — Cliente/Map (W1-B3)

## Divergência (ADR 0149)
**Split-pane com mapa lateral** — layout visualization-utility divergente do Index lista.

## Justificativa
- Foco em geolocalização (mapa grande) vs Index foco em transação
- Layout split 1/3 (lista) + 2/3 (mapa) — não cabe em pattern card-list
- Iframe Google Maps embed = elemento visual dominante

## Layout
- Header 7xl + breadcrumb + subtitle "N clientes com posição"
- Grid 1/3 aside (search + lista scroll) + 2/3 main (mapa iframe)
- Indicadores visuais MapPin colorido (azul/emerald/gray)

## Renderização
- v1: iframe Google Maps embed (sem API key, gratuito)
- v2 futuro: Leaflet self-hosted

## Acessibilidade
- Lista é `<button>` clicável (não `<div>` com onClick)
- Disabled state pra contatos sem position
- Iframe tem `title` semântico

## Gate F1.5
✅ Divergência aprovada via ADR 0149 (visualization-utility page)
