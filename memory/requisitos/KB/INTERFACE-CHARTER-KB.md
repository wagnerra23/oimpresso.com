---
doc: Interface — Charters na KB (tri-pane + governança)
module: KB
status: spec de interface (pré-implementação)
adr: 0243-charter-governance-kb
base_ui: resources/js/Pages/kb/Index.tsx (tri-pane aprovado por Wagner)
created: 2026-06-01
---

# Interface — Charters na KB

> **Premissa (Wagner):** "eu quero a interface KB, gostei de trabalhar com ela." Esta spec **reusa o tri-pane do `kb/Index.tsx`** (AppShellV2 + PageHeader + KpiGrid + lista master + preview markdown + atalhos `j/k/Enter/Esc//`) e acrescenta a **camada de governança** (sugestão/aprovação/status/maturidade). **Conformidade DS roxo v4** (não herdar o `bg-blue-100` cru do KB legacy — mira ≥ Leader, alvo Champion).

## Rota e navegação

- `GET /kb/charters` — aba "Charters" no KB (SubNav ao lado de Docs / Grafo / Trilhas).
- Deep-link: `/kb/charters?level=page|module&module=Financeiro&status=ratified&q=...`
- Atalhos herdados: `/` foca busca · `j/k` navega · `Enter` abre · `Esc` fecha preview. Novos: `s` propor sugestão · `a` aprovar (se owner) · `r` re-verificar.

## Layout tri-pane

```
┌───────────────────────────────────────────────────────────────────────────┐
│ PageHeader "Charters"  ·  SubNav: Docs | Grafo | Trilhas | [Charters]       │
│ KpiGrid:  Ratified 0/30  ·  Stale 3  ·  Sugestões abertas 5  ·  Módulos 12  │
├───────────────┬───────────────────────────────┬───────────────────────────┤
│ FILTROS (L)   │ LISTA MASTER (centro)         │ PREVIEW + GOVERNANÇA (R)  │
│               │                               │                           │
│ Nível         │ ▸ Page Charter  ⬤ratified     │ ── Núcleo (read-only) ──  │
│  ◉ Todos      │   /cliente  · Cliente         │ # Mission                 │
│  ○ Page       │   owner: wagner · 🥈Leader    │ Goals / Non-Goals / UX    │
│  ○ Module     │ ▸ Module Charter ⬤ratified    │ (markdown render)         │
│ Módulo ▼      │   Financeiro · 🏆 92          │ 🔒 vem do git — PR p/ mudar│
│ Status ▼      │   backlog 14 US · DoD 78%     │                           │
│ Tier ▼        │ ▸ Page Charter  ⚠stale        │ ── Governança ──          │
│ Owner ▼       │   /sells/create · 🥈Leader    │ 💬 Sugestões (ancoradas)  │
│               │   ⚠ vence em 2d               │   #1 @felipe "rever Non-G"│
│ [+ Novo]      │ … (j/k navega)                │      [aprovar][rejeitar]  │
│               │                               │ [+ Propor sugestão (s)]   │
│               │                               │ Histórico · Re-verificar  │
└───────────────┴───────────────────────────────┴───────────────────────────┘
```

## Zona central — card de charter (lista)

Cada linha mostra (reusa `Badge`/`Card` do DS, tokens roxo v4):
- **Tipo**: pill `Page Charter` (roxo) / `Module Charter` (roxo-escuro).
- **Status**: pill `draft`/`in_review`/`ratified`/`outdated`(⚠ stale)/`superseded`.
- **Alvo**: `page:` (rota) ou `module:` (nome) + owner.
- **Maturidade**: badge bronze/prata/ouro/🏆 + nota (liga ao SCREEN-GRADE/`module:grade`).
- **Module Charter** extra: `backlog N US · DoD %` (barra) — vindo do `RequirementsFileReader`.
- **Stale indicator**: "⚠ vence em Xd" quando `verify_due_at` próximo.

## Zona direita — preview com 2 abas

**Aba "Contrato" (núcleo, read-only):**
- Markdown render (ReactMarkdown + remarkGfm, igual `kb/Index`).
- Banner 🔒 *"Núcleo vem do git — para mudar, proponha uma sugestão (vira PR)."*
- Para **Module Charter**: render estruturado — **Meta · Limite · Backlog (DoD% por US) · Changelog · Saúde (nota)** em cards, não markdown cru (≠ `MemCofre/Modulo` que despejava `<pre>` — gap nota 69).

**Aba "Governança":**
- **Sugestões ancoradas** por `block_idx` (igual comentário inline), com `kind` (sugestão/dúvida/errata) + `status`.
- **[+ Propor sugestão]** (`kb.charter.suggest`) — drawer com texto + bloco-alvo. NÃO edita o núcleo.
- **Fila do owner** (se `kb.charter.approve`): aprovar/rejeitar **com comentário obrigatório** (Document360-style) → toast + audit.
- **Status workflow**: stepper `draft → in_review → ratified → outdated`. Transição com permissão.
- **Re-verificar** (1 clique, padrão Guru): zera stale, atualiza `last_verified_at`.
- **Histórico**: versões via git (bridge) — link GitHub (igual `kb/Index` history).

## Fluxos (HITL mínimo = autonomia)

| Ação | Quem | Resultado |
|---|---|---|
| Propor sugestão | qualquer `kb.charter.suggest` | `kb_comment` kind=suggestion status=proposed (núcleo intacto) |
| Aprovar (núcleo) | owner | enfileira PR no `.charter.md` (US-CHTR-020) → merge → bridge → `ratified` |
| Aprovar (anexo) | owner | publica bloco anexo (sem tocar núcleo) |
| Rejeitar | owner | status=rejected + comentário (auditado) |
| Re-verificar | owner | `last_verified_at=now`, sai de `outdated` |

## Componentes reusados (zero reinvenção)

`AppShellV2` · `PageHeader` (shared) · `KpiGrid`/`KpiCard` · `Card` · `Badge` · `Button` · `Select` · `AlertDialog` (confirmação) · `ScrollArea` · `ReactMarkdown`+`remarkGfm` · `sonner` toast — **todos já em uso no `kb/Index.tsx`**.

## Conformidade DS (mira Champion)

- **Tokens roxo v4** (`oklch(0.55 0.15 295)` primary) — **sem `bg-blue-100`/cor crua** (o gap nº1 que trava o KB legacy em Advanced/Leader).
- `Inertia::defer` + skeleton nos painéis caros (lista + preview).
- a11y: status como pill + texto (não só cor); foco/teclado; `aria-live` em toast.
- Charter próprio da tela (`/kb/charters` tem seu `Charters.charter.md`) + Pest GUARD (dogfooding).

## Não-objetivos da interface (V1)
- ❌ Editor WYSIWYG do núcleo (núcleo é git — só sugestão).
- ❌ Aprovação multi-estágio pesada em charter de baixo risco (tiered).
- ❌ Grafo próprio (reusa `/kb/graph` com filtro `charter-of`/`governs-module`).
