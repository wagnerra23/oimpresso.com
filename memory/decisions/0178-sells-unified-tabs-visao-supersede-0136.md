---
slug: 0178-sells-unified-tabs-visao-supersede-0136
number: 178
title: "Sells: unificar Lista + Grade Avançada numa só tabela com tabs de Visão (Operacional / Financeira / Produção) — supersede ADR 0136"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-21"
module: sells
quarter: 2026-Q2
tags: [ux, unification, tabs, persona, refactor, ADR-0105-cliente-sinal, ADR-0136-superseded, multi-tenant-tier-0]
supersedes: [0136-sells-grade-avancada-modo-toggle]
supersedes_partially: []
amends: []
superseded_by: []
related: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0104-processo-mwart-canonico-unico-caminho, 0105-cliente-como-sinal-guiar-sem-mandar, 0107-emendation-0104-visual-comparison-gate-f3, 0136-sells-grade-avancada-modo-toggle]
pii: false
review_triggers:
  - "Larissa @ ROTA LIVRE biz=4 reclamar que tab default 'Operacional' perdeu coluna que ela usava → ajustar visibleColumns Operacional"
  - "≥3 clientes OfficeImpresso migrados sentirem falta de feature da Grade Avançada legacy não portada pra tab Financeira/Produção → ampliar visibleColumns ou adicionar tab 4"
  - "Sinal qualificado de persona 4 (ex compras / contábil) pedir tab nova → re-avaliar arquitetura (mais que 3 tabs talvez exija nav lateral em vez de tabs horizontais)"
---

# ADR 0178 — Sells: unificar Lista + Grade Avançada em tabs de Visão

## Contexto

[ADR 0136](0136-sells-grade-avancada-modo-toggle.md) (2026-05-11) splittou `Sells/Index.tsx` em duas views via toggle `viewMode='lista'|'grade-avancada'` pra atender 2 personas distintas:

- **Lista** (default ROTA LIVRE biz=4 + clientes nascidos no oimpresso): visão **operacional** — pipeline FSM, fiscal badges, SLA pill, row-actions DANFE/XML, ★ favs, items_summary, hora.
- **Grade Avançada** (default `business.legacy_origin='officeimpresso'`): visão **financeira/poder** — Localização, Pago, A receber, Produção badge, multiseleção, totalizador sticky, agrupamento TanStack, filtros multi-data, date_field 7 opções.

Após 10 dias de produção LIVE, sinal qualificado ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)) chegou via Larissa @ ROTA LIVRE biz=4 (2026-05-21) — *"Lista e grade avançada estão conflitando as informações"*. Mapeamento via subagente `wagner-understand` confirmou 26 dimensões catalogadas; 10 features são compartilhadas mas com semântica/layout divergente (ex: Status — Lista mostra pill `os-stage`, Grade mostra `PaymentStatusBadge`; Pagamento — Lista junta método+SLA, Grade só método; etc).

Além disso, custos de manter o split:

- **777 LOC duplicado** em `SellsGradeAvancada.tsx` (2 paths internos próprios: HTML default sem agrupamento + TanStack GroupedTable com agrupamento)
- **Charter Sells/Index drift** — Non-Goals do charter v3 dizia "toggle não montada" mas código LIVE tem o toggle (ADR 0136 era proposta antes da impl real)
- **3 PRs hoje** (#1311 default `'todas'`, #1314 Pagamento Grade TanStack, #1317 Pagamento Grade HTML, #1320 Pagar inline) **tiveram que ser aplicados em ambos paths em sequência** pra manter paridade — fricção alta
- **OfficeImpresso piloto não migrou** ainda (Modules/OficinaAuto está em piloto Martinho, Sells ainda não tem cliente legacy migrado), então a assunção de "personas mutuamente exclusivas" do ADR 0136 não foi testada com sinal real

## Decisão

**Sells/Index.tsx unifica Lista + Grade Avançada numa só `<SellsTabelaUnificada>` controlada por 3 tabs de Visão no header:**

```
┌────────────────────────────────────────────────────────────┐
│  Vendas                                          [+ Nova]  │
│  ┌──────────────────────────────────────┐                 │
│  │ Operacional  │ Financeira │ Produção │  ⓘ Visão        │
│  └──────────────────────────────────────┘                 │
│  [filtros multi-data + busca + saved view]                │
│  ┌──────────────────────────────────────┐                 │
│  │ tabela com colunas pré-set da Visão  │                 │
│  └──────────────────────────────────────┘                 │
│  [totalizador sticky bottom]                              │
└────────────────────────────────────────────────────────────┘
```

| Visão | Default pra | Colunas (≤9, cabe 1280px Larissa) | Features |
|---|---|---|---|
| **Operacional** | ROTA LIVRE biz=4 (cliente nascido no oimpresso) | Venda · Data · Cliente · Atendido por · Pipeline · Fiscal · Pagamento · Total · Status (com row-actions DANFE/XML/Pagar) | ★ favs, J/K nav, SLA pill embutido na coluna Pagamento, items_summary inline no Cliente |
| **Financeira** | Eliana (financeiro) + auditoria | Venda · Data · Cliente · Total · Pago · A receber · Pagamento · Status · Comissão | Multiseleção + bulk-actions, totalizador sticky, agrupamento (Cliente/Status/Mês), date_field 7 opções |
| **Produção** | OfficeImpresso migrado (futuro) | Venda · Data · Cliente · Localização · Produção · Pipeline · Pagamento · Total · Status | Agrupamento "venda agrupada", sub-linha produtos (US-SELL-022 futuro), batch print |

**Estado persistido per-business** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md) Tier 0): `oimpresso.sells.b<bizId>.visao = 'operacional'|'financeira'|'producao'` (default `'operacional'` exceto se `business.legacy_origin='officeimpresso'` → `'produção'`).

**Migração silenciosa do localStorage atual** (`oimpresso.sells.b<bizId>.viewMode`):
- `'lista'` → `'operacional'`
- `'grade-avancada'` → `'financeira'` (heurística: quem usava Grade buscava Pago/A receber/totalizador)

Tabela base subjacente é **uma só** (`SellsTabelaUnificada`) com prop `visibleColumns: ColumnId[]`. Features condicionais (multiseleção, totalizador, agrupamento) ativam via boolean props quando a tab atual habilita.

## Justificativa

**Por que tabs verticais e não:**

- **(a) União pura** — 15+ colunas numa só tabela com column visibility menu user-driven. Cabe em 1280px Larissa? Não. Densidade quebra leitura. Descartado.
- **(b) Densidade configurável** (compacto vs expandido) — preserva 2 modos mas mantém ambiguidade "qual modo eu uso pra quê?". Não resolve as 10 features compartilhadas com semântica divergente. Bom fallback se (c) der ruim.
- **(d) Manter split + reduzir conflitos** — adicionar Pago/A receber em Lista e SLA pill em Grade. Larissa pediu *"uma só com todas funções unificadas"* — (d) não atende esse pedido explícito.

**Tabs verticais (c) é a única opção que:**
1. Atende o pedido literal "uma só"
2. Mantém ≤9 cols/tab (cabe 1280px Larissa sem scroll horizontal)
3. Multi-persona aware (cada persona tem seu default — não força Larissa a trocar de view)
4. Permite features condicionais sem duplicar componente
5. ADR 0136 sai limpo (supersede explícito, não amends que vira spaghetti)
6. Deleta 777 LOC duplicados de `SellsGradeAvancada.tsx`

**Quando faz sentido reabrir esta ADR:**
- Persona 4 surgir (ex: tab "Contábil" pra SPED/DRE) e tabs horizontais ficarem apertadas → migrar pra nav lateral
- Sinal de power-user pedir multi-tab simultânea (split-screen) → trocar tabs por sheets/painéis
- Browser MCP smoke mostrar que Larissa abandona tab Operacional → revisar default ou conteúdo da tab

## Consequências

**Positivas:**

- **Fim do drift Charter ↔ código** — charter v1 (criado neste PR) reflete realidade live
- **Paridade automática** — todo PR futuro de coluna nova mexe em 1 lugar (`SellsTabelaUnificada.visibleColumns`), não em 2 paths (Lista + Grade HTML + Grade TanStack)
- **Manutenção -60%** (estimativa) — 777 LOC `SellsGradeAvancada.tsx` saem
- **Larissa preservada** — tab "Operacional" default = colunas Lista atual (mudança quase invisível pra ela; risco baixo de fricção como "decora fluxos" 2026-04-24)
- **Persona-aware** — Eliana cai direto em Financeira pelo perfil, sem precisar toggle
- **Sells/Index.charter.md** nasce v1 documentando Mission/Goals/Non-Goals da unificação

**Negativas / Trade-offs:**

- **Mudança de paradigma `viewMode` → `visao`** — usuários que decoraram nome "Grade Avançada" precisam aprender "Financeira". Mitigação: localStorage migration silenciosa + tooltip explicativo na primeira semana
- **6 PRs ≤300 LOC** com aprovação Wagner em cada — wall-clock 12-16h em 2-3 dias úteis
- **Pest snapshot regression** — `SellsIndexCoworkPayloadTest` 11 testes precisam re-baselined pós-refactor (payload backend não muda, mas DOM rendering muda)
- **`SellsToggleViewMode.tsx` deletado** — qualquer outra tela que importava (Repair? Compras? OficinaAuto?) precisa migrar pra equivalente. Grep mostra uso isolado em Sells, baixo risco

**Riscos mitigados:**

- **Larissa decora fluxos** ([feedback](../../memory/reference/feedback-cliente-rotalivre.md)) — default Operacional preserva colunas + ordem da Lista atual
- **OfficeImpresso churn de migração** — tab Produção pré-criada pra absorver power-users sem chocar
- **Re-render loop catalogado em ADR 0136 §Riscos** — `SellsTabelaUnificada` extrai useMemo no `visibleColumns` (mesma mitigação que ADR 0136 fix `93286099b` aplicou pra TanStack)

## Plano de execução (6 PRs atômicos, [commit-discipline](0094-constituicao-v2-7-camadas-8-principios.md) ≤300 LOC)

| PR | Conteúdo | Bloqueia? |
|---|---|---|
| 1 | **Este ADR** + charter Sells/Index v1 + visual-comparison F1.5 placeholder (DOCS only) | — |
| 2 | `SellsTabsVisao.tsx` novo + feature-flag `?tabs=1` (renderiza tabs ao lado do toggle legacy, sem trocar tabela) | PR1 |
| 3 | `SellsTabelaUnificada.tsx` extract + `visibleColumns` prop (tabela base extraída de Index.tsx renderizando ambas geometrias) | PR2 |
| 4 | Index.tsx usa unificada + delete `SellsGradeAvancada.tsx` + `SellsToggleViewMode.tsx` (.bak retidos 30d) + localStorage migration silenciosa | PR3 |
| 5 | Row-action "+R$" popover refinement (substitui modal full do PR #1320 por popover em-place mais fluido, se necessário) | PR4 |
| 6 | TS check final + Pest snapshot re-baseline + smoke biz=1 Brave + remoção feature-flag | PR5 |

## Referências

- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Tier 0 multi-tenant (escopo localStorage)
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (charter > spec, SoC brutal)
- [ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — MWART (5 fases)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0107](0107-emendation-0104-visual-comparison-gate-f3.md) — Visual comparison F1.5 gate
- [ADR 0136](0136-sells-grade-avancada-modo-toggle.md) — **superseded por este**
- [Dossiê wagner-understand](../sessions/2026-05-21-understand-sells-unificar-lista-grade.md) — matriz 26 dimensões + estimate detalhado
