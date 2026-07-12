---
page: /ads/admin/confidence
component: resources/js/Pages/ads/Admin/Confidence.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/confidence (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/ConfidenceController@index` (rota `ads.admin.confidence.index`, middleware `auth` — V1 superadmin Wagner). Lê `mcp_confidence_scores` (todos os pares, sem filtro de business_id nesta query) e deriva 4 KPIs. Página read-only do Confidence Engine.

---

## Mission
Dar transparência ao Confidence Engine do ADS: mostrar, por par (domínio × tipo de evento), a confiança acumulada do sistema (sobe com acertos, cai com modificações/rejeições) e o nível HiTL resultante. Quando o score supera 0.70 e a Policy permite, o Brain A passa a executar autônomo — esta tela é onde Wagner audita esse aprendizado sem tocar código.

---

## Goals — Features (faz)
- Exibe 4 KPIs de topo: total de pares (domínio × tipo), autônomos HiTL-0, score médio (3 casas), total de execuções.
- Tabela (desktop ≥md) + card-stack (mobile <md) com score, faixa semântica (Alta/Atende/Baixa/Inicial), nível HiTL (0-3), amostras, aprovações/falhas consecutivas e último outcome.
- Cor nunca é o único sinal: cada faixa e nível carrega ícone lucide + label textual (a11y daltonismo — ADR UI-0013); tabela tem `<caption>` sr-only + `scope`.
- Ordena por score decrescente (vem do controller `orderByDesc('score')`).
- EmptyState quando ainda não há scores registrados.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO edita scores nem thresholds pela UI — o Confidence Engine ajusta scores só via aprovação/rejeição de decisões em outras telas.
- ❌ NÃO promove par pra autônomo (isso é decisão de Policy via PR git, ver `/ads/admin/patterns`).
- ❌ NÃO filtra scores por business_id nesta query (lê `mcp_confidence_scores` global) — inferência pendente: confirmar com Wagner se o Confidence Engine é per-business ou global e se a listagem deveria escopar por tenant.
- ❌ NÃO exporta CSV nem tem paginação (lista inteira de uma vez).

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 com breadcrumb ADS › Confidence.

---

## Automation hooks (faz)
- Nenhum automático nesta tela — é read-only. Os scores exibidos são mutados fora daqui (ConfidenceEngine reage a approve/reject de decisões).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Sem polling / auto-refresh (dado só atualiza no reload manual).
- ❌ Sem mutação em GET — nenhuma ação de escrita parte desta tela.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar escopo tenant de `mcp_confidence_scores` (global vs per-business)
