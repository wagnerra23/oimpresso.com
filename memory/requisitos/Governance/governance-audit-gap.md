---
id: requisitos-governance-audit-gap
tela: governance/Audit (/governance/audit)
prototipo: prototipo-ui/cowork/Wagner/governance-page.jsx + governance-telas.jsx
tela_viva: resources/js/Pages/governance/Audit.tsx
gerado_em: 2026-09-29
---

# GAP-SPEC — governance/Audit

> Protótipo = porte REVERSO do vivo (governance-page.jsx:1-3 "Espelha as telas vivas"; governance-telas.jsx:1-3 "Espelha AuditController (mcp_audit_log, teto de 200, 4 filtros)"; retrato de ~2026-08-23). Fase 1 = PARIDADE. Charter: `resources/js/Pages/governance/Audit.charter.md` (Non-Goals respeitados, nunca reabertos).

**Veredito:** PARIDADE com 1 item a decidir — a contagem do período além do teto. (Em 2026-09-06 eram 2: "Limpar filtros" também estava a decidir; foi construído em 2026-09-09, #7089 — ver a nota abaixo.) Filtros, KPIs, tabela, vazio, rodapé e agora "Limpar filtros" são o vivo.

> **Decidido em 2026-09-09** (os dois itens, por escrito, como o gap pedia):
> - **"Limpar filtros" — CONSTRUÍDO.** `Audit.tsx` ganhou `hasFilter` + `clearFilters` (o reset volta ao default `'24h'` do `AuditController.php:33`, não a "sem período") e o vazio passou a explicar a combinação. Front puro, zero fonte nova. Travado por `tests/js/governance-filtros.test.tsx` (mordida provada: mutar `hasFilter` para `false` deixa 4 casos vermelhos).
> - **Contagem do período além do teto — NÃO construído nesta leva.** Não é rejeição de mérito: exige `count()` extra no `AuditController` e campo novo no payload — é backend, fora do intent deste PR (front dos GAP-SPEC). Segue como item aberto.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header / PageHeader | `Audit.tsx:95-99` — `<PageHeader icon="search" title="Audit Log" description=…>` (append-only, ADR 0084, read-only); layout `AppShellV2` em `:243`. Mockup: `governance-page.jsx:404-415` (h1 `TITULOS.auditoria` + `<p data-contract="aviso-rota">` em `:407` — aviso de rota "a raiz /governance redireciona para /ia", não subtítulo da tela — + selo `superadmin · cross-tenant` em `:413`) | Nada — paridade (títulos adaptados) |
| Abas do shell (sub-navegação) | `Audit.tsx:94` `<GovernancaSubNav active="audit" />` (lista derivada do DataController, `_shared/GovernancaSubNav.tsx:16-17`). Mockup: `governance-page.jsx:25-30` (`VIEWS`) + `:417-418` (`CliTabs`) | Nada — paridade |
| Nota "Registro imutável" | Vivo: a mesma mensagem vive na descrição do header — `Audit.tsx:98` "Append-only enforced via trigger MySQL (ADR 0084). Read-only — modificação é incidente P0"; não há nota separada (`imutável` → 0 hits). Mockup: `governance-telas.jsx:71-73` (`A.Nota tone="info"`) | Nada — paridade (mesmo conteúdo, posição diferente; rótulo ≠ capacidade) |
| Filtros (período · ator · endpoint · status) | `Audit.tsx:108-180` — 4 `Select` Radix (grid em `:110-170`); `updateFilter` em `:67-76` faz `router.get` com `preserveState`+`preserveScroll`+`replace`+`only:['entries','kpis','filters']`. Período limitado a 1h/24h/7d/30d (`:116-119`). Mockup: `governance-telas.jsx:76-104` (segmento de período + 3 selects em memória; hint "não existe janela maior que 30 dias" em `:103`) | Nada — paridade (vivo persiste na URL com partial reload D-14; o teto de 30d existe nos dois lados) |
| Filtro "Resultado" com 4 valores (concluído · negado · erro · cota excedida) | Vivo: `Audit.tsx:164-166` só `ok`/`error` (`denied／quota` → 0 hits). Mockup: `governance-telas.jsx:95-101` lê `RES_LABEL` de `governance-data.jsx:122` com 4 valores | Nada — decisão já registrada (charter §Goals: "status ok/error"); os 2 valores extras vêm do gerador mock (`governance-data.jsx:128-134`) |
| Botão "Limpar filtros" | Vivo (2026-09-29, re-medido): CONSTRUÍDO no #7089 (2026-09-09) — `Audit.tsx:80-81` `hasFilter` + `:83-90` `clearFilters` (volta ao default `'24h'` do `AuditController.php:33`); botão no bloco de filtros `:172-178` e no vazio `:196`. Mockup: `governance-telas.jsx:102` (`temFiltro && <button>Limpar filtros`) e `:116` (no vazio); `limpo()`/`temFiltro` em `:63-64` | Nada — paridade (construído em 2026-09-09, #7089; travado por `tests/js/governance-filtros.test.tsx`) |
| KPIs (3 cards) | `Audit.tsx:101-105` — Entries no período · Errors (tone warning/success) · Users distintos; calculados sobre a amostra (`AuditController.php:72` `kpisFor($entries)`). Mockup: `governance-telas.jsx:106-110` (mesmos 3, sobre `amostra`) | Nada — paridade (mesma base de cálculo — a amostra teto 200) |
| Tabela de entries (6 colunas) | `Audit.tsx:201-230` — Quando · User (`#user_id`) · Endpoint · Tool/Resource · Status (`Badge` com `statusColor` `:55-58`) · Duração; hover por linha `:214`. Mockup: `governance-telas.jsx:119-142` (Quando · Ator slug · Endpoint · Ferramenta/recurso · Resultado `Selo` · Duração; classe `alerta` na linha não-ok). O `Entry` vivo carrega `user_id`, não slug de ator (`Audit.tsx:19-28`) | Nada — paridade (mesmas 6 colunas; "Ator" vs "User" é o mesmo eixo, apresentado com o dado que o payload tem) |
| Estado vazio | `Audit.tsx:185-198` `<EmptyState …>` — desde 2026-09-09 (#7089) com filtro ativo mostra "Essa combinação de filtros não devolve nada", o resumo dos filtros e o botão "Limpar filtros" (`:190-196`); sem filtro, "Sem entries". Mockup: `governance-telas.jsx:113-116` (`A.Vazio variant="filtered"` com resumo dos filtros e botão limpar) | Nada — paridade |
| Rodapé do teto (200 por consulta) | `Audit.tsx:236-238` "Limit 200 entries por query. Períodos longos podem truncar — refine filtros". Mockup: `governance-telas.jsx:143-145` (mesmo aviso + "o período tem N" quando `filtrado.length > TETO`, e KPI com sub "de N no período" em `:107`). Vivo: nenhuma contagem além do teto — `Props` (`Audit.tsx:35-50`) não traz total do período; `period_total／total_period／uncapped` → 0 hits | **Decidir.** Contagem real do período além do teto (mockup `governance-telas.jsx:107` e `:143-145`) ausente no rodapé `Audit.tsx:236-238` e nos KPIs `:101-105`; exige um `count()` extra no controller. O charter §UX Targets pede o hint do limite, sem decidir sobre o número. Construir ou rejeitar por escrito. |

## Recibos de ausência
- `grep -nEi 'imutável' resources/js/Pages/governance/Audit.tsx` → 0
- `grep -nEi 'denied|quota' resources/js/Pages/governance/Audit.tsx` → 0
- `grep -nEi 'Limpar|limpar|reset|clear' resources/js/Pages/governance/Audit.tsx` → 0 (fato de 2026-09-06; em 2026-09-29 → 5 linhas — o botão foi construído no #7089, não é mais recibo de ausência)
- `grep -nE 'period_total|total_period|uncapped' resources/js/Pages/governance/Audit.tsx` → 0
