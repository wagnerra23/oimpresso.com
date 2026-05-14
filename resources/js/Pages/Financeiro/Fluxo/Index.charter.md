---
page: /financeiro/fluxo
component: resources/js/Pages/Financeiro/Fluxo/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-14
parent_module: Financeiro
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-FICHA.md
related_adrs: [arq/0005, ui/0114, 0093, 0094, 0104]
related_us: [US-FIN-014]
related_prototype: prototipo Cowork "Fluxo de Caixa" (Financeiro.html), aprovado [W] 2026-05-09
related_decisions: memory/requisitos/Financeiro/fluxo-visual-comparison.md (Q1-Q4 aprovadas [W] 2026-05-14)
tier: A
charter_version: 1
---

# Page Charter — /financeiro/fluxo

> **Status:** F3 entregue (charter criado junto com Controller + Service + Page + Pest, sessão 2026-05-14).
> Persona: **Eliana [E]** (financeiro escritório) + **Wagner [W]** (dono). Desktop ≥1024px. Decisão de caixa em <30s.

---

## Mission (1 frase)

Mostrar **projeção de 35 dias de fluxo de caixa** (saldo, entradas e saídas dia-a-dia) numa view única — sem Eliana precisar abrir Contas a Receber + Contas a Pagar + Saldo bancário e somar de cabeça pra responder "vou conseguir pagar a folha dia 30?".

---

## Goals — Features (faz)

- 4 KPI cards: **Saldo hoje** (soma `ContaBancaria.saldo_cached`), **Projeção 30 dias** (com delta vs hoje + tone emerald/rose), **Pior dia previsto** (tone amber), **Margem mínima** (R$ [redacted Tier 0] hardcode F1)
- KPI "Saldo hoje" mostra caption com nome da conta principal (+ "outras N" se >1 conta)
- Gráfico de barras 35 dias com linha tracejada amber pra margem mínima
- Barras coloridas por estado:
  - `bg-stone-300` (passado, histórico -2d)
  - `bg-stone-900` (hoje)
  - `bg-stone-700` (futuro)
  - `bg-amber-500` (qualquer dia abaixo da margem mínima — alerta visual)
- Hover na barra: tooltip com `data_label · saldo_acumulado` em fundo `bg-stone-900`
- Tabela "Próximos eventos" (7 dias adiante) com colunas: data | seta ↓/↑ (kind) | descrição | contraparte | categoria | valor | saldo acumulado
- Setas `↓ emerald` (recebimento) / `↑ rose` (pagamento) com fundo colorido
- Valor com sinal `+`/`−` formatado BRL com `tabular-nums`
- Histórico -2d aparece como parte da timeline (Q4 aprovado: contexto recente)
- Empty state na tabela quando não há eventos próximos: "Nenhum evento programado nos próximos 7 dias."
- Multi-tenant Tier 0: `Titulo`, `TituloBaixa`, `ContaBancaria` filtrados por `business_id` global scope ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- Read-only: nenhuma mutação (sem botão de marcar baixa, sem editar, sem excluir)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ Dropdown período (7/15/30/35/60d) — F1 é fixo 35d (Q2 aprovado 2026-05-14); F2 entrega UI controle
- ❌ Margem mínima configurável por usuário — F1 hardcode R$ [redacted Tier 0] (Q3); F2 via `business_settings.margem_minima_caixa`
- ❌ Click linha tabela abre drawer detalhe — F1 não; F2 (US-FIN-019) abre Sheet com `Titulo` completo
- ❌ Atalhos teclado `J/K` navegação tabela — F1 não; F2 mesmo pattern Unificado
- ❌ Export PDF/CSV/XLSX — backlog Eliana (US-FIN-021)
- ❌ Comparativo "vs mês anterior" — escopo DRE
- ❌ Mobile responsive (cards stack) — F1 desktop only (Persona Eliana)
- ❌ Filtro por conta bancária específica — F1 soma todas; futuro filtro conta como Unificado
- ❌ Aging buckets <30 / 30-60 / 90+ — escopo Inadimplência
- ❌ Cache Redis — F1 sem cache (dataset pequeno ~50-200 títulos)
- ❌ Mutação (registrar baixa, criar título) — read-only por design; mutações via `/financeiro/unificado` ou contas-receber/pagar
- ❌ Notificação automática quando saldo cruzar margem mínima — backlog Onda 4 (job + email/whatsapp)

---

## UX Targets

- p95 first-paint < 600ms (Service simples + 2 queries pequenas)
- Cabe em monitor 1280px sem scroll horizontal (Persona Eliana)
- 0 erros JS console
- Tabular nums em TODOS valores monetários (`tabular-nums`)
- KPI grid 4 colunas em `lg:` (≥1024px); stack vertical abaixo de `md:` (mas desktop only por design)
- Tipografia canon ADR 0110: KPI value `text-2xl semibold`, label uppercase `text-[10px]`, tabela row `text-[12.5px]`
- Cores semânticas Cockpit V2: emerald (entrada), rose (saída), amber (alerta margem/pior dia)
- Hover tooltip < 100ms

---

## UX Anti-patterns

- ❌ Gráfico de linhas curvas (canon = barras verticais — mais legível pra projeção dia-a-dia)
- ❌ Cor crua `bg-(blue|green|red)-N` sem semântica (canon = `stone/emerald/rose/amber` com significado)
- ❌ KPI sem caption explicativa (Saldo hoje sem nome da conta vira número solto)
- ❌ Tooltip permanente / sempre visível (canon = `group-hover:` discreto)
- ❌ Tabela com pagination explícita (canon = limitar a 7d adiante, sem paginar; quem quer mais abre /unificado)
- ❌ Margem mínima como número fixo no JSX (canon = vem do backend `margem_minima` Props pra permitir F2 configurável)

---

## Automation Hooks

- Endpoint `FluxoController::index(Request $r): Response` lê `session('user.business_id')` + `?dias=N` (clamp 7..60, default 35)
- Service `FluxoCaixaService::projetar(int $businessId, int $dias = 35): array` orquestra:
  - `SUM(ContaBancaria.saldo_cached)` WHERE ativo (saldo hoje)
  - `Titulo` WHERE `business_id` AND `status IN (aberto,parcial)` AND `vencimento BETWEEN today AND today+N` (futuros)
  - `TituloBaixa` WHERE `business_id` AND `data_baixa BETWEEN today-2 AND today-1` AND `estorno_de_id IS NULL` (histórico)
- Multi-tenant: `Titulo`, `TituloBaixa`, `ContaBancaria` usam `BusinessScope` global scope (defesa em profundidade — Service também explicita)
- Permission canon: `financeiro.dashboard.view` (granularidade `financeiro.fluxo.view` em backlog F2)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir
- ❌ Não dispara WhatsApp / SMS
- ❌ Não escreve no banco no render (test `não dispara mutação em GET /fluxo`)
- ❌ Não chama Service externo (boletos, NFe, Asaas)
- ❌ Não acessa Titulo/Baixa/ContaBancaria de outro `business_id` (Tier 0 ADR 0093 — test `query Titulo respeita business_id global scope`)
- ❌ Não loga PII em plain text (descricao + contraparte podem ter nome cliente; ficam em log SOMENTE via ActivityLog do `Titulo`, nunca duplicados aqui)

---

## Métricas vivas (Pest GUARD em [FluxoControllerTest.php](../../../../Modules/Financeiro/Tests/Feature/FluxoControllerTest.php))

```php
it('renderiza Inertia component Financeiro/Fluxo/Index')
it('expõe Props no shape esperado (saldo_hoje, saldo_30d, pior_dia, margem_minima, conta, dias)')
it('expõe margem_minima padrão R$ [redacted Tier 0] (Q3 hardcode aprovado 2026-05-14)')
it('aplica clamp em ?dias=N (range 7..60, default 35)')
it('Tier 0 IRREVOGÁVEL: query Titulo respeita business_id global scope (ADR 0093)')
it('não dispara mutação em GET /fluxo (read-only puro)')
```

---

## Comparáveis canônicos

- **Linear "Roadmap" timeline** — densidade horizontal com hover tooltip
- **Stripe Dashboard "Balance"** — gráfico saldo com linha de referência
- **QuickBooks Cash Flow Forecast** — projeção 30/60/90d com indicadores de alerta
- **Excluir:** Tiny ERP "Fluxo de Caixa" (UI antiga 2010), Conta Azul (excesso de filtros que Eliana não usa)

---

## Refs

- [Visual comparison F1.5](../../../../memory/requisitos/Financeiro/fluxo-visual-comparison.md) — 8 dimensões + score 88/100 + 4 decisões aprovadas
- [Protótipo F1](../../../../prototipo-ui/prototipos/financeiro-fluxo/page.tsx) — aprovado [W] 2026-05-09
- [Lições F3 Financeiro rejeitado](../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — pre-flight aplicado nesta entrega
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR ui/0114 — Loop Cowork formalizado](../../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0104 — Processo MWART canônico](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [Charter irmão — Visão Unificada](../Unificado/Index.charter.md)

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-14 | Wagner [W] + Claude Code [CL] | F3 entregue — Service + Controller + Page + Pest + charter + route + topnav. Pre-flight `LICOES_F3_FINANCEIRO_REJEITADO.md` aplicado literalmente (Models reais, `session('user.business_id')`, middleware stack canon, `shape*()` helper, `whereNull('deleted_at')`, sem mock `rand()`, sem mutação NO-OP). Q1-Q4 aprovadas [W] 2026-05-14. |
