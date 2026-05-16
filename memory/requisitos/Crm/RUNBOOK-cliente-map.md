# RUNBOOK — Migração MWART /contacts/contact_map → Cliente/Map (W1-B3)

## 1. Tela
- **Legacy:** Blade `contact.contact_map`
- **Inertia:** `resources/js/Pages/Cliente/Map.tsx`
- **Controller:** `ContactController::contactMap()` (linha 1634)
- **Flag:** `mwart.cliente_map.enabled`

## 2. Objetivo
Split-screen: lista lateral pesquisável + mapa Google Maps embed.

## 3. Divergência ADR 0149
Split-pane com mapa lateral — layout divergente do Index lista. Aprovado utility/visualization page.

## 4. Renderização do mapa
- v1: iframe Google Maps embed `https://maps.google.com/maps?q={position}&output=embed` (sem API key, gratuito)
- v2 futuro: Leaflet self-hosted (sem dependência Google, sem rate-limit)

## 5. Campo `position` legacy
- Format: "lat,lng" string (Delphi legacy)
- Backend filtra `whereNotNull('position')` — não vem null

## 6. Multi-tenant
- `Contact::where('business_id', $business_id)->whereNotNull('position')`
- `all_contacts` também scope por business_id

## 7. Variáveis env
```env
MWART_CLIENTE_MAP=false
MWART_CLIENTE_MAP_BIZ=1
```

## 8. Pest tests
- `Wave1MapBaselineTest.php`
- `Wave1MapInertiaTest.php`

## 9. Limitações conhecidas
- v1 iframe Google = sem clustering, sem multi-pin simultâneo
- v2 com Leaflet vai entregar essas features
