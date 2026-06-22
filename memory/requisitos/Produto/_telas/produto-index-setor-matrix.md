---
tela: Produto/Index.charter.md
gerado_por: scripts/governance/reconcile-triplet.mjs (v1 heurístico)
gerado_em: "2026-06-22"
veredito: CONFORME
---

# Matriz de Paridade por Setor — Produto/Index.charter.md

> **Gate 3-way charter↔protótipo↔produção (PT-01, 6 slots).** O CHARTER é a 1ª coluna
> (a fonte de verdade — o spec). v1 HEURÍSTICO: detecção por assinatura, **não** parsing
> semântico completo nem render/screenshot. Catraca estrutural barata, complementar ao
> gate visual F1.5/F3 (Wagner aprova screenshot). Gerado por `reconcile-triplet.mjs --write`.

## Fontes

- **Charter:** `resources/js/Pages/Produto/Index.charter.md`
- **Produção:** `resources/js/Pages/Produto/Index.tsx`
- **Protótipo:** ⚠️ **AUSENTE** — nenhum dos ponteiros declarados existe no filesystem.

### ⚠️ Ponteiros de protótipo órfãos (apontam pro vácuo)

- `prototipo-ui/prototipos/produto-cockpit (frontmatter:blueprint_cowork)`
- `prototipo-ui/prototipos/produto (cowork-map:produto)`
- `ui_kits/cowork-2026-05-09/prod-page.jsx (charter:Refs)`

## Matriz (6 slots PT-01)

| Slot | Charter manda | Protótipo mostra | Produção renderiza | Estado |
|---|---|---|---|---|
| **1 · PageHeader** | `pageheader-ou-header` | `AUSENTE` | `header-sticky` | ✓ CONFORME |
| **2 · ModuleTopNav** | `subnav-ou-tabs` | `AUSENTE` | `tabs` | ✓ CONFORME |
| **3 · Toolbar** | `busca` | `AUSENTE` | `busca+filtros` | ✓ CONFORME |
| **4 · BulkBar** | `qualquer` | `AUSENTE` | `ausente` | ✓ CONFORME |
| **5 · Table/Grid** | `grid` | `AUSENTE` | `grid` | ✓ CONFORME |
| **6 · Drawer** | `qualquer` | `AUSENTE` | `ausente` | ✓ CONFORME |

## Veredito da tela

**✓ CONFORME** — pior slot: — (todos os 6 slots conformes).

> charter ≡ produção em todos os 6 slots PT-01

## Legenda dos estados

- **CONFORME** — charter ≈ produção no slot.
- **DIVERGÊNCIA DECLARADA** — diferem, mas o frontmatter tem `divergence_from_blueprint` (desvio consciente).
- **DIVERGÊNCIA MUDA** — diferem **sem** declaração → FALHA (`--strict` exit 1).

## Limites do v1 (honestidade)

- Detecção por **assinatura** (regex sobre JSX/HTML/charter), não NLU nem AST.
- Slot 5 distingue `table` (`<table`/`<thead`/`<DataTable`) de `grid` (`grid-cols`/`<article`/`card`).
- Charter "manda" inferido de Goals/Non-Goals/UX Anti-patterns por palavra-chave.
- Protótipo AUSENTE não é falha por si — só sinaliza ponteiro órfão. CONFORME/MUDA usa charter×produção.
- **Não** substitui o gate visual (screenshot Wagner) — é catraca estrutural complementar.
