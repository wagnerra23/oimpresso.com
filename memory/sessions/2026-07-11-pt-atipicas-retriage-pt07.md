# Retriagem das 7 telas atípicas da wave-1 PT + PT-07 Feed/Timeline

**Data:** 2026-07-11 · **Branch:** `claude/pt-atipicas-retriage` (de `origin/main` 7d9b19b8bb fresco) · **Autor:** [CC]

## Contexto

A wave-1 de declaração de Padrão de Tela ([#4109](https://github.com/wagnerra23/oimpresso.com/pull/4109)) declarou 63 telas e a rede `pt-conformance.mjs` (#4108) barrou 7 como atípicas/fuzzy — foram **revertidas pra ficar silenciosas honestas** (sem `related_prototype`, fora do escopo do gate). Esta sessão decidiu por **evidência (leitura do `.tsx`)** o destino de cada uma: PT novo, ampliar o gate, ou bespoke.

## Método

Trabalhei de `origin/main` fresco (checkout base estava −5053). Li os 6 `.tsx` nomeados + fiz **censo de arquétipo na árvore inteira** de `Pages/` pra aplicar a regra dura **"cria PT só se ≥2 telas compartilham o arquétipo"**.

## Decisão por tela (verificada)

| Tela | Arquétipo real (evidência) | Decisão | Como declarei |
|---|---|---|---|
| `team-mcp/CcSessions/Index` | Feed cronológico (`data-testid="cc-feed"` + `.map` + `fmtRelative` + drawer + KpiGrid shared) | **(a) PT-07** — golden | `related_prototype: n/a (herda PT-07 …) · golden` |
| `ProjectMgmt/Inbox/Index` | Feed/inbox (notificações agrupadas via `.map`, `timeAgo`, J/K, KpiGrid) | **(a) PT-07** | `related_prototype: n/a (herda PT-07 …)` |
| `Fiscal/Eventos` | Timeline append-only (`fx-timeline` + `.map`, chips por tipo) | **(a) PT-07** | `related_prototype: fiscal-page.jsx §11 … — herda PT-07` |
| `ComunicacaoVisual/Index` | **Ferramenta/Calculadora** de m² (`useState`+`useMemo`, sem form/list/feed) | **(c) bespoke** — arquétipo **único** no repo → NÃO cria PT-06 | `related_prototype: n/a — ferramenta bespoke … (sem PT)` (sem token PT → fora do gate) |
| `Jana/Cockpit` | Híbrido chat + dashboard (KPICard/AnaliseCard **locais**, não shared) | **(c) bespoke** → protótipo real | migrou `visual_source` → `related_prototype: chat-jana.jsx` |
| `team-mcp/Forja/Cockpit` | Hub com abas (dispatcher por `tab`) | **(c) bespoke** → protótipo real | migrou `visual_source` → `related_prototype: forja-page.jsx` |

**Censo que fundamentou:** Feed/Timeline tem **3 páginas** top-level (Eventos, Inbox, CcSessions) → justifica PT-07. Calculadora tem **1** (ComVis) → PT-06 fica **deferred** (documentado no PT-07 §Arquétipo vizinho). Os dois "cockpits" são arquétipos **diferentes entre si** (chat+dash vs hub) → nenhum vira PT; cada um bespoke apontando pro seu `-page.jsx`.

**Não ampliei PT-04** (opção b): seria afrouxar `kpi` pra aceitar qualquer `*Card` de agregação = defeitar o gate. Bespoke é a call honesta pros cockpits.

## Entregas

1. **`padroes-tela/PT-07-Feed-Timeline.md`** (novo, status **draft**) — golden `CcSessions/Index`, 6 slots, 8 regras binárias ancoradas em linha real, §Drift (Fiscal usa CSS `fx-*` próprio = ilha), tabela "aplicado em" (1/3 no canon completo).
2. **`pt-conformance.mjs`** — sinal `feed` (timeline/feed markers OU `timeAgo`/`fmtRelative`) + `REQUIRED['PT-07']` + `claimedPT` agora `[1-57]` (reconhece 01-05+07, **exclui 06** de propósito — declaração-fantasma de PT inexistente não passa). +5 casos de selftest.
3. **8 charters** declarados/corrigidos:
   - 3 feed → `related_prototype` com token PT-07 (CONFORME, verificado).
   - ComVis → nota bespoke-DS **sem token PT**.
   - Jana/Cockpit + Forja/Cockpit → `visual_source` → `related_prototype` (âncora canônica).
   - **kb/Index + kb/Index.v2** → typo `related_prototipo` → `related_prototype` (âncora não resolvia).
   - **Sells/Caixa/Index** → migrou `visual_source` → `related_prototype`.
4. **README + INDEX do DesignSystem** — catálogo de PT atualizado (estava stale dizendo "PT-02..05 não documentado") + PT-07 + nota PT-06 deferred.
5. **Catraca `design-coverage`** subida 81 → **90** (9 telas ganharam fonte declarada).

## Verificação (tudo local, verde)

```
pt-conformance --selftest   → 14 casos OK
pt-conformance --check      → 66 declarações, 0 mismatch (3 PT-07 CONFORME)
ancora --selftest           → OK · --list resolve as 9 telas tocadas
design-coverage --check     → declared 90 ≥ baseline 90
integrity-check             → todos IT duros PASS (144 charters c/ tela viva)
ds-guard (PT-07.md)         → limpo · cowork-ssot-guard → OK
```

## Follow-ups (fora de escopo, não toquei)

- **8 charters ainda em `visual_source`** (CaixaUnificada, OficinaAuto ×2, RecurringBilling ×4) — legado; âncora não resolve no `--list`. Mesma migração `visual_source`→`related_prototype` quando alguém tocar cada um.
- **PT-06 Ferramenta/Calculadora** — formalizar quando surgir 2ª calculadora/ferramenta.
- **Convergência PT-07** (→ live): desbundlar `Fiscal/Eventos` (fx-* → shared) + trocar EmptyState hand-rolled do Inbox pelo shared.
- Charter body de `ComunicacaoVisual/Index` descreve "landing/dashboard 3 widgets" mas o `.tsx` é calculadora — drift de charter, não corrigido aqui.

## R10

Sem merge sem aprovação Wagner. PR aberto pra review.
