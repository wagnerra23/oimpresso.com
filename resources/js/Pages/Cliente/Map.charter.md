---
page: /contacts/contact_map
component: resources/js/Pages/Cliente/Map.tsx
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Cliente
related_adrs: [110, 107, 93, 94, 104, 149]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/clientes-page.jsx"
  blueprint_screenshot_approval: "N/A (divergente)"
  derived_screens: [Map]
  divergence_from_blueprint: "split-screen com Leaflet/Google Maps lateral, divergente do Index lista"
---

# Page Charter — /contacts/contact_map (DRAFT)

> Backend canon: `ContactController::contactMap()` linha 1634. **Divergência ADR 0149:** Layout split-pane com lista lateral 1/3 + mapa 2/3. Wagner aprovou divergência pra utility/visualization page.

## Mission

Visualização geográfica dos clientes com lista lateral pesquisável + mapa embed Google Maps. Substitui Blade `contact.contact_map.blade.php` mantendo `$contact->position` (campo legacy "lat,lng" string).

## Goals

- Layout split: aside 1-col (lista de clientes pesquisável) + main 2-col (mapa)
- Search input no aside filtra cliente por nome/cidade
- Click no item da lista seleciona e renderiza mapa com iframe Google Maps embed
- Indicador visual MapPin colorido (azul = selecionado, emerald = tem posição, gray = sem posição)
- Display de contatos sem position com badge "Sem posição" + edit suggestion
- Multi-tenant: `business_id` global scope (Contact::where).

## Non-Goals

- ❌ Edição de coordenadas inline (vai pra /contacts/{id}/edit)
- ❌ Geocoding automático CEP → lat,lng (cron separado, scope futuro)
- ❌ Mapa interativo com marcadores múltiplos simultaneous (futuro com Leaflet self-host)
- ❌ Cluster de pins (Leaflet plugin, futuro)
- ❌ Calcular rota multi-stop (Modules/Entregas futuro)

## UX Targets

- p95 first-paint lista < 800ms (com 500 contatos)
- Mapa carrega < 2s (iframe Google async)
- Cabe 1280px sem scroll horizontal

## Automation Anti-hooks

- ❌ Não envia lat,lng pra Google Maps Geocoding API (custo $$$, opt-in)
- ❌ Não modifica position do contact (read-only)
- ❌ Não acessa Contact de outro `business_id`

## Refs

- Backend: `ContactController::contactMap()`
- Pattern divergência: ADR 0149 §"Casos que NÃO se qualificam"
