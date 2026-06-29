---
slug: financeiro-dre-index
page: /financeiro/dre
component: resources/js/Pages/Financeiro/Dre/Index.tsx
status: live
module: Financeiro
persona: ["wagner", "eliana"]
stories: [US-FIN-014a]
visual_comparison: memory/requisitos/Financeiro/dre-visual-comparison.md
canon_source: public/cowork-preview/erp-shell/financeiro-telas-extras.jsx (TelaDRE linha 361-483)
related_adrs: [0114-prototipo-ui-cowork-loop-formalizado, 0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho, 0107-emendation-0104-visual-comparison-gate-f3, 0109-claude-design-plugin-integrado-processo-mwart]
created_at: 2026-05-20
---

# Charter — Financeiro / DRE gerencial

## Mission

Wagner (dono) e Eliana (financeiro) respondem **"deu lucro este mês?"** em <60s, com leitura hierárquica clássica de DRE (Receita bruta → Deduções → Receita líquida → Custos → Lucro bruto → Despesas → Resultado operacional), comparativa com mês anterior e % sobre Receita Líquida — sem ter que abrir planilha externa.

## Goals

1. **Hierarquia visual clássica DRE** — header / item indent / subtotal cinza / subtotal final destacado em preto. Tipografia tabular-nums + densidade financeira (linhas `py-1.5` em items, `py-2.5` em subtotais).
2. **Comparativo mês anterior em 1 vista** — coluna Δ% colorida (emerald positivo, rose negativo) por linha; coluna % RL (Receita Líquida = 100% base) com sinal preservado.
3. **Toggle período Mês/Trim/Ano/12m** — F1 entrega só Mês funcional; outros renderizam disabled (`opacity-50`) com tooltip "Em breve" (US-FIN-DRE-PERIODOS backlog).
4. **Export PDF + Excel inline** — botões no header do Card, geração via `dompdf` + `maatwebsite/excel` (já no projeto).
5. **Cards bottom contexto** — Margem operacional (com meta 12% hardcode F1, US-FIN-DRE-META backlog config tenant) + Top 3 categorias de receita (mesma query do DRE_TEMPLATE — sem duplicar SQL).
6. **Topnav contextual módulo** — 7 botões espelhando Unificado (Buscar ⌘K · Resumir mês · Fechamento · Apresentar · Conciliar · Plano de contas · Novo lançamento) — copy-paste inline em F1 (US-FIN-TOPNAV-COMPONENT backlog).

## Non-goals (F1)

- ❌ **Mobile responsive** — desktop only ≥1024px (persona Wagner desktop fixo, Eliana monitor 1280px). Mobile fica US-FIN-025.
- ❌ **Filtro por centro de custo** — ROTA LIVRE biz=4 não usa CC. Backlog US-FIN-DRE-CC.
- ❌ **Comparativo Realizado vs Orçado** — Wagner não fez orçamento formal ainda. Backlog US-FIN-DRE-ORC.
- ❌ **Edição inline de categorias / DRE_TEMPLATE customizável** — F1 usa template hardcode Comunicação Visual; tela de edição vira `/financeiro/dre/mapping` em F2+.
- ❌ **Atalhos teclado J/K/1-4 / drill-down drawer** — F1 é read-only puro; interação F2+.
- ❌ **Skeleton loading custom** — SSR Inertia padrão; skeleton vira F2 se p95 ≥ 400ms.
- ❌ **Histórico > mês anterior** — só `prev = M-1`. Média 3m / YoY entram em F2 via `?compare=avg3m|yoy`.

## UX targets

- **Tempo até resposta "deu lucro?"** — visual subtotal `Resultado operacional` (preto, posição fixa última linha) preto se 0/positivo, com texto rose se negativo. Wagner identifica em <5s.
- **Densidade** — 18-20 linhas visíveis em 1280×720 sem scroll. Cards bottom sempre visíveis (1 viewport leitura completa).
- **Hierarquia tipográfica** — 3 níveis: header `font-medium`, item `text-stone-600`, subtotal `font-semibold text-[14px]`, subtotal highlight `bg-stone-900 text-white text-[14px] font-bold`.
- **Cor semântica consistente** — token DS `text-success` sempre = positivo/receita; `text-destructive` sempre = negativo/dedução-custo-despesa (antes `text-emerald-700`/`text-rose-700` crus; migrado pro token semântico — mode-aware, SSOT `semantic.tokens.json`). Mesma família de Fluxo + Unificado.
- **Latência p95** — <250ms server-side com 2k títulos no período (target Service + render).

## Anti-hooks (NÃO fazer)

- ❌ **Mostrar valores brutos sem hierarquia** — qualquer "DRE flat" (lista de linhas sem header/item/subtotal) é regressão. Anti-pattern original em `Relatorios/Index.tsx` que motivou esta reaplicação.
- ❌ **Quebrar a família visual `os-page-h fin-page-h`** — qualquer header customizado divergente vira UX dissonância com Unificado/Fluxo/Cobranca.
- ❌ **Reintroduzir `Card` shadcn como wrapper externo** — canon usa `bg-white border border-stone-200 rounded-md shadow-sm` direto (mesmo padrão fin-stats).
- ❌ **Permitir filtro `business_id` via query param** — multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)). `business_id` SEMPRE vem de `session('user.business_id')`.
- ❌ **Cachear DRE sem invalidação por evento `TituloUpdated`** — F1 NÃO cacheia. Se F2 introduzir Redis, invalidação tem que ser por evento, nunca TTL puro (Eliana pode lançar boleto e abrir DRE no mesmo segundo).
- ❌ **Calcular `% RL` ignorando sinal do numerador** — confirmado no screenshot canon 2026-05-20: deduções/custos/despesas devem mostrar `-9.3%` (não `9.3%`).
- ❌ **Duplicar query top categorias receita** — reaproveitar payload do `DreService::montar()` (top 3 da subseção "Receita operacional bruta"). N+1 = regressão.
- ❌ **`Resultado operacional` highlight sem fundo preto** — canon define `highlight: true → bg-stone-900 text-white`. Família visual constante.

## Decision log

- **2026-05-20** — Q1-Q8b aprovados por Wagner ("ok pode fazer", "pode fazer todos em paralelo"). Visual-comparison `dre-visual-comparison.md` → `status: approved`. Screenshot canon TelaDRE aprovado em chat (gate F1.5 ADR 0107 cumprido).
- **2026-05-20** — Decisão Q8b: deprecar tab DRE em `Relatorios/Index.tsx`, mantendo Fluxo+Resumo na Relatorios. PR D fecha o cleanup.
- **2026-05-20** — Decisão Q8a: topnav contextual = copy-paste inline em F1 (de `Unificado/Index.tsx:963-1043`). Extração `<FinModuleTopnav>` vira backlog US-FIN-TOPNAV-COMPONENT (gatilho: 3ª tela usar mesmos 7 botões).

## Quem pode mexer

- **Wagner [W]** — aprova qualquer mudança em Goals / Non-goals / Anti-hooks. ADR obrigatório pra qualquer alteração no DRE_TEMPLATE hierárquico (mexe em contábil, decisão estratégica).
- **Eliana [E]** — pode pedir mudança UX (microcopy / formato data / posição botões) via feedback canal direto, mas Wagner valida.
- **Claude [CL]** — implementa, abre PR, não altera contrato Props nem DRE_TEMPLATE sem aprovação Wagner.
