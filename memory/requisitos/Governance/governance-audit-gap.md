---
id: requisitos-governance-audit-gap
tela: governance/Audit (/governance/audit)
prototipo: prototipo-ui/cowork/governance-page.jsx + governance-telas.jsx
tela_viva: resources/js/Pages/governance/Audit.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — governance/Audit

> Protótipo = porte REVERSO do vivo (governance-page.jsx:1-3 "Espelha as telas vivas"; governance-telas.jsx:1-3 "Espelha AuditController (mcp_audit_log, teto de 200, 4 filtros)"; retrato de ~2026-08-23). Fase 1 = PARIDADE. Charter: `resources/js/Pages/governance/Audit.charter.md` (Non-Goals respeitados, nunca reabertos).

**Veredito:** PARIDADE com 2 itens a decidir — o retrato acrescenta "Limpar filtros" e a contagem do período além do teto; filtros, KPIs, tabela, vazio e rodapé são o vivo.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header / PageHeader | `Audit.tsx:79-83` — `<PageHeader icon="search" title="Audit Log" description=…>` (append-only, ADR 0084, read-only); layout `AppShellV2` em `:209`. Mockup: `governance-page.jsx:403-418` (h1 `TITULOS.auditoria` + subtítulo + selo `superadmin · cross-tenant`) | Nada — paridade (títulos adaptados) |
| Abas do shell (sub-navegação) | `Audit.tsx:78` `<GovernancaSubNav active="audit" />` (lista derivada do DataController, `_shared/GovernancaSubNav.tsx:16-17`). Mockup: `governance-page.jsx:24-30` + `:420-424` | Nada — paridade |
| Nota "Registro imutável" | Vivo: a mesma mensagem vive na descrição do header — `Audit.tsx:82` "Append-only enforced via trigger MySQL (ADR 0084). Read-only — modificação é incidente P0"; não há nota separada (`imutável` → 0 hits). Mockup: `governance-telas.jsx:71-73` (`A.Nota tone="info"`) | Nada — paridade (mesmo conteúdo, posição diferente; rótulo ≠ capacidade) |
| Filtros (período · ator · endpoint · status) | `Audit.tsx:92-156` — 4 `Select` Radix; `updateFilter` em `:65-74` faz `router.get` com `preserveState`+`preserveScroll`+`replace`+`only:['entries','kpis','filters']`. Período limitado a 1h/24h/7d/30d (`:100-103`). Mockup: `governance-telas.jsx:76-108` (segmento de período + 3 selects em memória; hint "não existe janela maior que 30 dias" em `:107`) | Nada — paridade (vivo persiste na URL com partial reload D-14; o teto de 30d existe nos dois lados) |
| Filtro "Resultado" com 4 valores (concluído · negado · erro · cota excedida) | Vivo: `Audit.tsx:148-150` só `ok`/`error` (`denied／quota` → 0 hits). Mockup: `governance-telas.jsx:100-104` lê `RES_LABEL` de `governance-data.jsx:122` com 4 valores | Nada — decisão já registrada (charter §Goals: "status ok/error"); os 2 valores extras vêm do gerador mock (`governance-data.jsx:128-134`) |
| Botão "Limpar filtros" | Vivo: não existe — cada Select volta a "Todos" um a um; `Limpar／limpar／reset／clear` → 0 hits em `Audit.tsx`. Mockup: `governance-telas.jsx:106` (`temFiltro && <button>Limpar filtros`) e `:120` (no vazio) | **Decidir.** Ação de reset dos 4 filtros num clique (mockup `governance-telas.jsx:63-64` `limpo()`/`temFiltro` + `:106`/`:120`) ausente no bloco de filtros `Audit.tsx:92-156` e no vazio `:161-164`. Não é Non-Goal. Construir ou rejeitar por escrito. |
| KPIs (3 cards) | `Audit.tsx:85-89` — Entries no período · Errors (tone warning/success) · Users distintos; calculados sobre a amostra (`AuditController.php:72` `kpisFor($entries)`). Mockup: `governance-telas.jsx:110-114` (mesmos 3, sobre `amostra`) | Nada — paridade (mesma base de cálculo — a amostra teto 200) |
| Tabela de entries (6 colunas) | `Audit.tsx:166-197` — Quando · User (`#user_id`) · Endpoint · Tool/Resource · Status (`Badge` com `statusColor` `:53-56`) · Duração; hover por linha `:180`. Mockup: `governance-telas.jsx:123-146` (Quando · Ator slug · Endpoint · Ferramenta/recurso · Resultado `Selo` · Duração; classe `alerta` na linha não-ok). O `Entry` vivo carrega `user_id`, não slug de ator (`Audit.tsx:17-26`) | Nada — paridade (mesmas 6 colunas; "Ator" vs "User" é o mesmo eixo, apresentado com o dado que o payload tem) |
| Estado vazio | `Audit.tsx:161-164` `<EmptyState title="Sem entries" …>`. Mockup: `governance-telas.jsx:117-120` (`A.Vazio variant="filtered"` com resumo dos filtros e botão limpar — o botão está na linha "Limpar filtros") | Nada — paridade |
| Rodapé do teto (200 por consulta) | `Audit.tsx:202-204` "Limit 200 entries por query. Períodos longos podem truncar — refine filtros". Mockup: `governance-telas.jsx:147-149` (mesmo aviso + "o período tem N" quando `filtrado.length > TETO`, e KPI com sub "de N no período" em `:111`). Vivo: nenhuma contagem além do teto — `Props` (`Audit.tsx:33-48`) não traz total do período; `period_total／total_period／uncapped` → 0 hits | **Decidir.** Contagem real do período além do teto (mockup `governance-telas.jsx:111` e `:147-149`) ausente no rodapé `Audit.tsx:202-204` e nos KPIs `:85-89`; exige um `count()` extra no controller. O charter §UX Targets pede o hint do limite, sem decidir sobre o número. Construir ou rejeitar por escrito. |

## Recibos de ausência
- `grep -nEi 'imutável' resources/js/Pages/governance/Audit.tsx` → 0
- `grep -nEi 'denied|quota' resources/js/Pages/governance/Audit.tsx` → 0
- `grep -nEi 'Limpar|limpar|reset|clear' resources/js/Pages/governance/Audit.tsx` → 0
- `grep -nE 'period_total|total_period|uncapped' resources/js/Pages/governance/Audit.tsx` → 0
