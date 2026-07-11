---
pattern_id: PT-03
nome: Detalhe
camada: 3-padroes-tela
status: draft
versao: 0.1
created: 2026-05-30
parent_adr: UI-0013
golden: resources/js/Pages/Sells/Show.tsx
applied_in:
  - Pages/Sells/Show.tsx
  - Pages/Cliente/Show.tsx
  - Pages/Repair/Show.tsx
  - Pages/OficinaAuto/ServiceOrders/Show.tsx
---

# PT-03 · Detalhe — padrão canônico de tela-Show (1 registro)

> **Camada 3 · Padrão de Tela.** Herda das [Fundações](../README.md) + [Shell](../README.md) e nunca contradiz. Módulo configura os slots, **não** muda a estrutura.
> **Golden code-first** no espírito da [GOLDEN-REFERENCE](../../../../prototipo-ui/GOLDEN-REFERENCE.md): cada regra cita **linha real**. Em dúvida, pergunta ([UI-0013 regra-mestre](../adr/ui/0013-constituicao-ui-v2-camadas.md)) — não inventa.

## Quando aplicar

Tela **full-page de UM registro** (Show): detalhar 1 venda, 1 cliente, 1 OS, 1 OS de oficina. Cabeçalho com a identidade do registro + KPIs/resumo + seções de conteúdo + ações contextuais + histórico.

Não aplicar pra: lista paginável → [PT-01 Lista](PT-01-Lista.md) · cadastro/edição em drawer 760px → PT-02 (drawer-first, [ADR 0185](../../../decisions/0185-drawer-760-canon-entidades-cadastrais.md) — Cliente já migrou via [ADR 0179](../../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)) · dashboard de gráficos → PT-04.

## Golden eleito — `Sells/Show.tsx`

Entre as 4 candidatas, **`Sells/Show.tsx`** é o golden do arquétipo Detalhe. **Por quê:**

- **Anatomia completa dos 6 slots** num só arquivo: header com identidade (`:263-318`), 4 KPIs grandes (`:321-350`), layout 2-col 8/4 (`:353`), seções de conteúdo deferred (`:382-388`), ações contextuais FSM (`:400-422`), histórico unificado (`:665-680`).
- **Charter `tier: A`, vivo** (`Show.charter.md` — Mission/Goals/Non-Goals/UX targets/anti-patterns), 16+ testes anti-regressão (Wave1ShowBaselineTest + Wave1ShowInertiaTest).
- **`Inertia::defer` + `<Deferred fallback>`** correto (`:382`, `DetailSkeleton :150`) — SPA-feel, Tier 0 ([RUNBOOK-inertia-defer-pattern](../RUNBOOK-inertia-defer-pattern.md)).
- **`@/Components/ui`** (Button, DropdownMenu — `:25-31`) + shared (`KpiCard`, `EmptyState`).
- **Ações contextuais por estado** via `VdNextActionPanel` + `FsmActionPanel` ([ADR 0143 FSM LIVE](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) — diferencial-chave do arquétipo Detalhe vs Lista.
- **Timeline unificada** (FSM + pagamentos + atividades, `mode="unified"` `:673`).

**Por que descartei as outras:**

- **`Cliente/Show.tsx`** — bom (header avatar `:152`, 4 StatCard `:197`, defer), MAS o charter está **`status: superseded`** ([ADR 0179](../../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) inverteu pra drawer 760px) — não pode ser golden de um padrão vivo. E usa **tabs `border-b-2`** (`:245`), que é anti-padrão do golden de form (GOLDEN-REFERENCE R2).
- **`Repair/Show.tsx`** — usa `PageHeader` shared (✓) mas tem **cor crua hardcoded** `STAGE_COLOR_MAP` (`:60-67`, `bg-blue-100`/`bg-red-100` — anti-padrão AP1), FSM panel é **stub textual** (`:235`), histórico é placeholder vazio (`:247`).
- **`OficinaAuto/ServiceOrders/Show.tsx`** — V0 scaffold (`:2`), **sem KPIs grandes** (só `<dl>` de status), **sem histórico**, status como `bg-muted` cru (`:183`). Bom uso de Sheet 480px pra itens, mas incompleto pro arquétipo.

## Anatomia · 6 slots fixos

```
┌─────────────────────────────────────────────────────────────┐
│ 1 · Header-identidade   ← voltar · h1 #ID + subtítulo · ações│
├─────────────────────────────────────────────────────────────┤
│ 2 · KPIs/Resumo         grid 4-col · valor grande tabular    │
├──────────────────────────────────┬──────────────────────────┤
│ 3 · Seções conteúdo (8/12)       │ 5 · Ações contextuais     │
│     cliente · linhas · pagtos    │     (4/12) próxima ação   │
│     · frete (deferred)           │     + pipeline + atalhos  │
│ 4 · Histórico/Timeline           │                           │
└──────────────────────────────────┴──────────────────────────┘
                              6 · Overlays (print · emit · cheat-sheet)
```

## 8 regras binárias (sim/não) · âncora em linha real do golden

| # | Regra (pergunta sim/não) | Evidência na golden |
|---|---|---|
| **R1** | **Header tem botão Voltar (ghost icon) + `h1 text-2xl font-semibold` com a identidade do registro (`#{id}`) + subtítulo `text-sm text-muted-foreground`?** Nunca `font-bold`. | `Sells/Show.tsx:265-269` (voltar ghost) · `:271` (h1) · `:274` (subtítulo data·local) |
| **R2** | **Ações ficam à direita do header, primária `variant="default"`, secundárias `outline`/`ghost`, agrupadas em dropdown quando >3?** | `:281-317` (Imprimir `DropdownMenu` + Editar `variant default`) |
| **R3** | **Resumo é grid de KPIs (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`) com valor grande `tabular-nums` via `<KpiCard>` shared?** | `:321` (grid 4-col) · `:322-339` (`<KpiCard>` Total/Pago/Falta) |
| **R4** | **Conteúdo principal usa layout 2-col `lg:grid-cols-12` (8/4) — conteúdo à esquerda (`col-span-8`), ações/contexto à direita (`col-span-4`)?** | `:353` (grid-12) · `:355` (`lg:col-span-8`) · `:392` (`lg:col-span-4` aside) |
| **R5** | **Props caras (linhas, pagamentos, atividades) vêm `Inertia::defer` no controller e o frontend embrulha em `<Deferred fallback={skeleton}>`?** | `:382` (`<Deferred data="detail" fallback={<DetailSkeleton/>}>`) · `:150` (skeleton) |
| **R6** | **Tem zona de ações CONTEXTUAIS por estado do registro (próxima ação / transições FSM), separada das ações genéricas do header?** | `:400-409` (`<VdNextActionPanel>`) · `:413-422` (`<FsmActionPanel>` em `<section>` card) — [ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) |
| **R7** | **Tem seção Histórico/Timeline cronológica (FSM + pagamentos + atividades num stream)?** | `:665-680` (`<section>` Histórico + `<SaleTimeline mode="unified">` `:673`) |
| **R8** | **Cada seção é card `rounded-lg border border-border bg-card` + título `font-semibold text-sm` com ícone lucide, e estado vazio usa `<EmptyState>` shared (não texto solto)?** | `:358` (card cliente) · `:549` (card itens) · `:556` `:611` (`<EmptyState>`) |

**Placar:** 8/8 = canon. 6-7 = 1 round de ajuste. <6 = volta pro Claude Design.

## ✅ Sempre

- **PT-BR** em todo label/copy. Valores BRL via `Intl.NumberFormat('pt-BR')` (`:133`), datas via `pt-BR` (`:137`).
- `tabular-nums` em todo valor/qtd/timestamp (`:584` `:593` `:633`).
- `@/Components/ui` (Button/DropdownMenu) — zero controle nativo. `KpiCard`/`EmptyState` shared (`:23-24`).
- `Inertia::defer` + `<Deferred fallback>` em tudo que custa query (R5).
- Status como **badge sem bg-fill cru** — derivar tom de mapa de tokens (`PAYMENT_STATUS_TONE :119` usa escala `emerald/amber` semântica, não `bg-blue-500` literal).
- Atalhos da tela: `E` editar · `P` imprimir · `Esc` voltar · `?` cheat-sheet (`:218-248`).
- Charter `.charter.md` ao lado do `.tsx` ([skill `charter-first`](../../../../.claude/skills/charter-first/SKILL.md)) + RUNBOOK-show.md ([skill `mwart-process`](../../../../.claude/skills/mwart-process/SKILL.md)).
- Multi-tenant Tier 0: scope `business_id` no controller ([ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md)).

## ❌ Nunca

- Mudar a ordem dos slots (Header-identidade sempre topo · KPIs logo abaixo · ações contextuais na coluna direita).
- **Cor crua hardcoded** em mapa de status (`bg-blue-100`/`bg-red-100` — anti-padrão AP1; é o drift de `Repair/Show.tsx:60-67`). Use escala semântica via token.
- **Tabs `border-b-2`** pra alternar conteúdo — preferir pills `rounded-full` (drift de `Cliente/Show.tsx:245`, GOLDEN-REFERENCE R2).
- Editar inline no Show — edição vai pra `/{rota}/{id}/edit` (Non-Goal do charter).
- Mudar stage FSM por UPDATE direto — `current_stage_id` é trait-protected ([ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)); transição só via `ExecuteStageActionService`/`FsmActionPanel`.
- FSM panel ou histórico como **stub textual/placeholder vazio** (drift de `Repair/Show.tsx:235` e `:247`) — ou implementa de verdade, ou omite a seção.
- Modal full-screen Bootstrap legacy. Overlays print/emit são print-only ou Sheet lateral.

## Estados obrigatórios

1. **Carregado** — headline eager + detail resolvido.
2. **Loading** — `<DetailSkeleton>` (`:150`) enquanto `Inertia::defer` resolve.
3. **Seção vazia** — `<EmptyState>` por seção ("Nenhum item" `:556`, "Nenhum pagamento" `:611`).
4. **Registro sem sub-dado opcional** — seção condicional não renderiza (frete só se `cost > 0`, `:643`).
5. **Sem permissão** — ação some do header (`permissions.edit`/`permissions.print` gating, `:282` `:309`).

## ⚠️ Drift conhecido (corrija ao copiar)

- **KPI "Status pgto" hand-rola `rounded-xl`** (`Sells/Show.tsx:340`) em vez de `<KpiCard>`/`rounded-lg` — mesmo drift `rounded-xl` da GOLDEN-REFERENCE §4. Ao replicar, padronize via `<KpiCard>` ou `rounded-lg`.
- **`PAYMENT_STATUS_TONE` usa `bg-blue-50` pra "partial"** (`:122`) — azul **semântico** sobrevive como status, mas confere se não vira azul-de-marca (regra de ouro [INDEX-DESIGN](../INDEX-DESIGN-MEMORIAS.md) §0 R2 — accent canon é roxo `primary`).
- Charter de `Sells/Show` está `status: wave1-draft` — bump pra `live` quando smoke biz=1 + canary 7d concluírem (Cutover plan do charter).

## Aplicado em (estado real)

| Página | S1 Header | S2 KPIs | S3 Seções | S4 Histórico | S5 Ações ctx | @/ui | Charter | Nota |
|---|---|---|---|---|---|---|---|---|
| `Sells/Show.tsx` | ✓ | ✓ (4) | ✓ defer | ✓ unified | ✓ FSM | ✓ | ✓ A | **golden** |
| `Cliente/Show.tsx` | ✓ avatar | ✓ (4) | ✓ tabs | parcial | dropdown ações | ✓ | superseded | bom (drawer agora) |
| `Repair/Show.tsx` | ✓ PageHeader | — | ✓ | stub | stub | ✓ | ✓ | parcial |
| `OficinaAuto/.../Show.tsx` | ✓ PageHeader | — | ✓ Sheet itens | — | — | ✓ | ✓ | V0 scaffold |

**Métrica adoção PT-03 (2026-05-30):** 4/4 telas-Show têm Slot 1 (Header-identidade) + Slot 3 (Seções). Slot 2 (KPIs grandes) cobertura ~50%. Slot 4 (Histórico real) só Sells. Slot 5 (Ações contextuais FSM) só Sells. Meta: subir Repair/OficinaAuto pra KPIs + histórico real.

## Referências

- **ADR-mãe:** [UI-0013 Constituição UI v2](../adr/ui/0013-constituicao-ui-v2-camadas.md)
- **Golden code:** [Sells/Show.tsx](../../../../resources/js/Pages/Sells/Show.tsx) + charter [Sells/Show.charter.md](../../../../resources/js/Pages/Sells/Show.charter.md)
- **Form golden (irmão):** [GOLDEN-REFERENCE.md](../../../../prototipo-ui/GOLDEN-REFERENCE.md) (Sells/Create)
- **Lista (irmão):** [PT-01-Lista.md](PT-01-Lista.md)
- **Cockpit Pattern V2:** [ADR 0110](../../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md) · **Pattern reuse:** [ADR 0149](../../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)
- **FSM (ações contextuais):** [ADR 0143](../../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- **Defer:** [RUNBOOK-inertia-defer-pattern.md](../RUNBOOK-inertia-defer-pattern.md)
- **Índice mestre design:** [INDEX-DESIGN-MEMORIAS.md](../INDEX-DESIGN-MEMORIAS.md)

## Versão

**v0.1** · 2026-05-30 · primeira formalização (`status: draft`). Golden = Sells/Show. Promove pra `live` quando charter Sells/Show virar `live` + ≥2 telas marcarem 8/8.
**Verificado 2026-07-11** (re-âncora `origin/main`): golden `Sells/Show` confirmado; `pt-conformance` verde (11 declarações PT-03); charter `Sells/Show` segue `status: draft` com `blueprint_screenshot_approval: pendente`. O bump da PT-03 e o do charter compartilham o **mesmo gate de screenshot do Wagner** (F1.5 · [ADR 0107](../../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)) — aprovar o screenshot de `Sells/Show` destrava os dois.
