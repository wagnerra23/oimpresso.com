---
date: 2026-06-02
hour: "17:30 BRT"
topic: "Inventário do handoff bundle Claude Design (Cowork) vs origin/main — o que implementar"
authors: [C]
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0235-cor-primary-roxo-canon, 0110-cockpit-v2]
---

# Inventário — Handoff bundle "Oimpresso ERP Comunicação Visual" (Claude Design) vs `main`

> Origem: bundle exportado de claude.ai/design (`api.anthropic.com/v1/design/h/gLpyC-…`),
> 36 chats + `project/` (1144 arquivos). É o **espelho Cowork** deste repo.
> Wagner pediu "implementar os designs do projeto". Este doc mapeia o que é seguro,
> o que já está em `main`, e o que é Tier 0 (só [W]). Fontes lidas: bundle `STATUS.md`,
> `COWORK_NOTES.md` (📥 Pendentes), `CODE_NOTES.md`, `chats/chat36.md`, 33 `PROMPT_PARA_CODE_*.md`.

## ✅ FEITO nesta sessão (não-Tier-0, seguro)

| # | Item | Alvo no repo | Status |
|---|------|--------------|--------|
| 1 | **Fix Financeiro KPI** — reflow `@media` viewport → `@container` (cura colisão 5-col na Larissa @1280px, conteúdo ~1040px pós-sidebar) | `resources/css/fin-cowork.css` (`.fin-curadoria` vira container `finbody`; `@container finbody (max-width:1100px/600px)`) | ✅ aplicado · ratchet stylelint **delta 0** |
| 2 | **Mirror CRM trio** (docs) — charter + casos grounded em `crm-page.jsx` (8.6) | `prototipo-ui/prototipos/crm/charter.md` + `decisoes.md` | ✅ criado (pasta-por-tela, igual `clientes/`) |

## ✅ JÁ EM `main` — NÃO refazer (confirmado no bundle vs CODE_NOTES)

PRs merged que o bundle marca como processados (`COWORK_NOTES §📥`):
- **#2119 + #2121** — A2 accent 220→295 (+`CockpitAccentCanonTest`) · A1 gate AppShellV2 (224→0) · B smoke core-screens · C1 `#fff`→`var(--surface)` 30× · charters Vendas/Compras + INVENTARIO mirrorados · gates ui-architecture/multi-tenant
- **#2069** Jana Pro F3 · **#2073** prep 3 Tier 0 de IA · **#2078** gerador `design:review` por tela
- **#2054** Stylelint gate (G5) · **#2061** charters de papel + ADR 0242 · **#2062** README HANDOFF-ENTRY · **#2064** G4 retorno · **#2053** Financeiro v10
- Prompts `SESSAO-2026-06-02` (v3) e `REFORCO-APPSHELL-TESTES` = **DONE**.
- DS-fusion→app.css / `@media`→`@container` (alvos errados, repo já roxo+warm) = **SUPERSEDED** no próprio bundle.

## 🔒 TIER 0 — só [W] (abre PR, espera aprovação). Menu pra Wagner escolher:

| Tier0 | O que é | Esforço | Risco | Arquivo-fonte handoff |
|-------|---------|---------|-------|----------------------|
| **T0-A** | **DS v5 → `app.css`** — espelhar a camada COMPAT do `ds-v5/tokens.css` (29 tokens de quebra via alias `var()`) na fonte canônica de token do repo; conferir `cowork-financeiro-bundle.css` (188 hex) + `Sidebar.tsx vibeAccent` 220 não brigam com roxo canon | médio | alto (token global, multi-módulo) | `COWORK_NOTES` §"DS v5 fonte única" + `_PROPOSTA-0245` |
| **T0-B** | **CRM Blade→Inertia** — migrar UltimatePOS legado pra Inertia page seguindo a charter/casos recém-mirrorados (emoji→lucide, tokenizar hues estágio) | grande (programa) | alto (MWART 5 fases) | `PROMPT_PARA_CODE_CRM-TRIO.md` (parte 🟡) |
| **T0-C** | **DS-ROADMAP-ATÉ-ZERO** — 6 filas em série (A controles+FieldError · B cor-crua→token · C Onda G badge +5 variants · D lote-badge 410 · E FormSection · F icons). PARA no gate visual a cada fila | grande (sequenciado) | médio (incremental) | `PROMPT_PARA_CODE_DS-ROADMAP-ATE-ZERO.md` + `PR-C1..C7` |
| **T0-D** | **ADR formal shift 0-humano** + merge `docs/cowork-loop-protocol-10-4` + decidir migrar drift `.css` pro roxo | pequeno | Tier 0 (ADR) | `STATUS §Em aberto 0` |
| **T0-E** | **IA — ENABLE Tier 0** — ligar OTel collector + LGPD purge prod + cadência RAGAS + Meilisearch HA (custo/infra) | grande (infra) | alto (prod/$) | `COWORK_NOTES` "IA ENABLE" |

> ADRs não podem receber número por mim (soberania [W], ADR 0238 · append-only). `_PROPOSTA-0245`
> existe no bundle como proposta; numerar = ato de [W] no git.

## 🟡 Telas no bundle (design pronto) × estado no repo

| Tela | Nota Cowork | Repo | Próximo passo |
|------|-------------|------|---------------|
| Oficina/OS | F1 build (semente v5) | Inertia parcial | F1.5 critique → F2 [W] → F3 |
| Vendas | 9.5 | Index ok (Create POS aposentado) | F0 venda CV quando [W] retomar |
| Compras | 9.4 | migrado (0 hex) | espelhar resto no repo |
| Financeiro | 8.0→8.4 | Inertia vivo | **fix KPI feito**; migrar resto p/ v5 |
| Inbox/Caixa | 9.75 (régua) | — | não mexer (gabarito de método) |
| CRM | 8.6 | **Blade legado** | T0-B (migração) — trio mirrorado ✅ |
| Clientes | molde PT-03 | Inertia | replicar nos 3 cadastros |

## Decisão pendente de [W]
Escolher qual(is) Tier 0 (T0-A…E) eu encaro agora. Os 2 itens seguros (fix Financeiro + mirror CRM) já estão aplicados e prontos pra commit/PR.
