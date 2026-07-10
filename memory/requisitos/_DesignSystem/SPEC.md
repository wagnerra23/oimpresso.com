---
slug: design-system
title: "Especificação funcional · Design System"
type: spec
module: _DesignSystem
status: ativo
owner: wagner
version: "1.1"
last_updated: "2026-05-25"
---

# Especificação funcional · Design System

## 1. Escopo

Conjunto de regras obrigatórias para qualquer tela React do sistema. Garante consistência visual e acessibilidade mínima.

## 2. Regras

### R-DS-001 · Todo componente usa primitivas shadcn existentes antes de criar novo

```gherkin
Dado que um dev vai renderizar um botão
Quando ele escreve o JSX
Então usa `<Button>` importado de `@/Components/ui/button`
E nunca `<button>` HTML cru (exceto em casos de acessibilidade custom)

Dado que precisa de um novo componente (ex: DateRangePicker)
Quando não existe em shadcn nem em Components/shared/
Então primeiro verifica shadcn/ui docs, depois cria em Components/shared/
E nunca copia markup de uma tela pra outra
```

**Por quê**: consistência visual + acessibilidade embutida + manutenção centralizada.

**Testado em:** `Modules/SRS/Tests/Unit/DesignSystemAuditTest::test_no_raw_buttons` (futuro)

### R-DS-002 · Cores sempre via tokens semânticos

```gherkin
Dado que um dev precisa aplicar cor numa UI
Quando escolhe a classe Tailwind
Então usa tokens semânticos: `bg-primary`, `text-muted-foreground`, `border-border`
E nunca cores cruas: `bg-blue-500`, `text-gray-700`

Exceções aceitas: cores de status fixo (emerald/amber/red) em KPIs e progress bars
```

**Por quê**: dark mode automático + rebranding futuro sem refactor massivo.

**Testado em:** [TODO]

### R-DS-003 · Iconografia única via lucide-react

```gherkin
Dado que um componente precisa de ícone
Quando o dev importa
Então sempre de `lucide-react`
E nunca de @radix-ui/react-icons, heroicons, react-icons, emojis, svg custom
```

**Por quê**: peso do bundle, consistência de traço, acessibilidade (aria-hidden automático).

**Testado em:** [TODO]

### R-DS-004 · Espaçamento em múltiplos de 4px

```gherkin
Dado que um dev define padding/margin/gap
Quando escreve Tailwind
Então usa `-1, -2, -3, -4, -6, -8, -12, -16` (4/8/12/16/24/32/48/64 px)
E nunca valores arbitrários como `-[17px]` (exceto caso documentado em ADR)
```

**Por quê**: grid visual consistente.

**Testado em:** [TODO]

### R-DS-005 · Dark mode obrigatório em toda tela nova

```gherkin
Dado que uma tela React é criada
Quando o dev usa qualquer cor não-semântica
Então deve testar em ambos modos (light/dark) antes do PR
E não pode ter contraste < 4.5:1 em nenhum deles
```

**Por quê**: usuários alternam modos; tela quebrada no dark é bug.

**Testado em:** [TODO]

### R-DS-006 · Focus visível em todo elemento clicável

```gherkin
Dado que um elemento é clicável (button, link, role=button)
Quando o usuário navega com Tab
Então um outline visível aparece (padrão shadcn: `ring-2 ring-ring ring-offset-2`)
E nunca `outline-none` sem substituto
```

**Por quê**: WCAG 2.2 AA, usabilidade teclado.

**Testado em:** [TODO]

### R-DS-007 · Nenhum CSS custom sem ADR UI

```gherkin
Dado que a solução padrão Tailwind+shadcn não cobre um caso
Quando o dev precisa escrever CSS custom (arquivo .css ou <style>)
Então abre um ADR em _DesignSystem/adr/ui/NNNN justificando
E documenta o hack no comment do código
```

**Por quê**: evita drift silencioso, força documentação de decisão.

**Testado em:** [TODO]

### R-DS-008 · Telas de listagem operacional seguem o template ADR 0006

```gherkin
Dado que uma tela nova é uma listagem filtrada com ações (padrão CRUD)
Quando o dev cria o .tsx
Então importa os componentes shared: PageHeader, KpiGrid+KpiCard, PageFilters,
  StatusBadge, EmptyState, BulkActionBar (os que aplicarem)
E NÃO reescreve <h1>/Badge com variant calculado/div com Inbox icon custom

Dado que a tela não se encaixa no template (gráfico custom, chat, árvore, form)
Quando o dev abre ADR per-tela em memory/requisitos/{Modulo}/adr/ui/
Então a exceção é documentada + referenciada na tabela "Exceções" do ADR UI-0006
```

**Por quê**: consistência cross-módulo + velocidade de novo dev + facilita auditoria.

**Testado em:** _lacuna — Modules/Ponto/Tests/Feature/AprovacoesIndexTest não existe no repo (prova de conceito 2026-04-24; reconciliação 2026-07-01, cobertura a criar)_. Check C16 futuro no `ModuleAuditor`: toda page em listagem importa de `@/Components/shared/`.

### R-DS-009 · Telas core do ERP nascem dentro do Cockpit (AppShellV2)

```gherkin
Dado que uma tela nova faz parte do fluxo operacional do ERP
  (chat, tarefas, dashboard de módulo, listagem CRUD de OS/CRM/FIN/PNT)
Quando o dev cria o .tsx
Então envolve o conteúdo em <AppShellV2> (Cockpit) — não em <AppShell> legado
E persiste estado de UI em localStorage com prefixo "oimpresso.cockpit.*"

Dado que a tela é administrativa standalone (Showcase, Modulos manage, settings superadmin isolado)
Quando o dev cria o .tsx
Então pode usar <AppShell> legado — mas registra a exceção em ADR per-tela
```

**Por quê**: o Cockpit traz sidebar dual Chat/Menu, topbar contextual e Apps Vinculados — eliminando a rotação entre N telas pra montar contexto. Telas administrativas raras (1-2x/mês) não precisam disso.

**Testado em:** `Pages/Copiloto/Cockpit.tsx` (rota `/copiloto/cockpit` em produção 2026-04-27).

### R-DS-010 · Apps Vinculados pra contexto multi-módulo na coluna direita

```gherkin
Dado que uma tela do Cockpit tem entidade em foco
  (uma conversa, uma OS, uma tarefa, um cliente)
Quando essa entidade tem dados em outros módulos relacionados
Então o painel da coluna direita renderiza blocos LBlock por módulo
  (Os/Cliente/Financeiro/Ponto/Anexos/Historico)
E cada bloco é colapsável com persistência localStorage por chave individual
E cada bloco mostra resumo enxuto + 1 CTA primária (não duplica a info inteira)

Dado que a tela não tem entidade em foco
Quando renderiza
Então a coluna direita some — não fica vazia ou com placeholder estático
```

**Por quê**: o usuário operacional precisa ver "tudo do contexto" ao mesmo tempo, sem trocar de tela. Mas só faz sentido quando há contexto.

**Testado em:** `LinkedAppsPanel` em `Pages/Copiloto/Cockpit.tsx` — 5 cards (OS+CRM+FIN+Anexos+Historico) reagindo à conversa em foco.

### R-DS-011 · Origin badges identificam módulo de origem cross-cockpit

```gherkin
Dado que um item ou bloco tem origem em um módulo específico do ERP
  (uma tarefa, uma conversa-tipo, um app vinculado)
Quando renderiza o badge de origem
Então usa as cores semânticas oficiais:
  • OS  = amber  (oklch 0.93/0.07/70)
  • CRM = blue   (oklch 0.92/0.06/220)
  • FIN = green  (oklch 0.93/0.07/145)
  • PNT = violet (oklch 0.93/0.06/295)
  • MFG = orange (oklch 0.93/0.05/30)
E nunca inventa cor própria pra "destacar" o módulo
```

**Por quê**: o usuário escaneia origens visualmente em meio segundo. 5 cores fixas mapeadas no cérebro. Inventar nova cor pra novo módulo quebra o padrão.

**Reservado pra futuros módulos:** se aparecer 6º grupo, abre ADR escolhendo nova cor harmônica (não diluindo as 5 existentes).

**Testado em:** classes `.origin-badge.o-{OS|CRM|FIN|PNT|MFG}` em `cockpit.css`.

### R-DS-012 · Persistência de UI em `localStorage` com namespacing

```gherkin
Dado que uma tela do Cockpit guarda estado entre sessões
  (aba ativa, conversa selecionada, painel colapsado, filtro ativo, tweaks)
Quando salva no localStorage
Então usa prefixo "oimpresso.cockpit.*" pras chaves do shell e
       prefixo "oimpresso.linked.*" pros blocos vinculados
       prefixo "oimpresso.<modulo>.*" pra estado interno do módulo

E nunca sessionStorage (perde na nova aba)
E nunca chaves sem prefixo (colide com outras libs)
```

**Por quê**: F5 não pode trocar a UX. Wagner exigiu em 2026-04-26 (ver auto-memória `preference_cache_estado_preservado`).

**Testado em:** chaves `oimpresso.cockpit.{sidebar.tab,chat.tab,linked.collapsed,conv,tweaks.{vibe,density,accentHue,open}}` + `oimpresso.linked.{os,client,fin,att,hist}.collapsed`.

---

## User stories

### US-_DESIGNSYSTEM-001 · DESIGN.md root TOC executivo apontando pros docs canon

> owner: wagner · priority: p1 · estimate: 0.5h · status: done · type: story
> blocked_by: —
> closed_at: 2026-05-25
> closed_reason: REDUNDANTE — `/Design.md` (336 linhas) já existe desde 2026-05-08 e cumpre exatamente o papel proposto (TOC executivo §1 "O que você quer fazer?" + 16 seções de regras canon). Investigação durante PR #1563 expôs que a US foi criada sem inventariar `ls D:/oimpresso.com/*.md` adequadamente (falha de exploração inicial). Fix concreto entregue neste mesmo PR: 4 patches incrementais no Design.md atualizando §2 (workflow → prototipo-ui/PROTOCOL.md ADR 0114) + §7 (hierarquia → ADR UI-0013 Constituição UI v2 mãe atual) + §9 (tokens → ADR 0190 primary roxo 295) + §15 (checklist → link PRE-MERGE-UI + gates CI). Aprendizado canon: SEMPRE `ls root` antes de "criar TOC executivo".

## Contexto

Dev humano novo (Felipe/Maiara/Eliana/Luiz) e agente Claude novo caem no repo e não acham o design system fragmentado em `memory/requisitos/_DesignSystem/` + ADRs UI. Falta 1 página executiva no root pra bater o olho em 60s.

Origem: conversa Wagner 2026-05-25 — Claude do chat (claude.ai) propôs "DESIGN.md canônico" sem saber que oimpresso já tem Constituição UI v2 + PRE-MERGE-UI + prototipo-ui/PROTOCOL.md. Validamos que gap real é só o TOC root.

## Acceptance Criteria

- [ ] Criar `/DESIGN.md` no root do repo (≤200 linhas)
- [ ] TOC apontando pros docs canon existentes:
  - Constituição UI v2 (ADR UI-0013) — 4 camadas Fundações→Shell→PT→Módulo
  - PT-01 Lista (`memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md`)
  - PRE-MERGE-UI (`memory/requisitos/_DesignSystem/PRE-MERGE-UI.md`) — checklist 6 camadas + AP1-AP8
  - `prototipo-ui/PROTOCOL.md` — loop Cowork ↔ Claude Code 7 fases
  - Tokens (`resources/css/cockpit.css`, `inertia.css`, primary roxo 295 ADR 0190)
  - Componentes shared (`resources/js/Components/shared/` + `Components/ui/` shadcn)
  - PageHeader canon (ADR 0180/0182/0189/0190)
  - Workflows visuais (visual-regression.yml, ui-lint.yml, pr-ui-judge.yml, mwart-gate.yml)
- [ ] Seção "Onde começar" por persona (dev novo · agente Claude novo · Wagner)
- [ ] Link no `README.md` raiz pra `DESIGN.md`
- [ ] CHANGELOG `_DesignSystem` apendado

## Não-objetivos

- ❌ NÃO duplicar conteúdo dos docs canon (só apontar)
- ❌ NÃO criar regras novas (só consolidar discovery)
- ❌ NÃO substituir CLAUDE.md (CLAUDE.md = primer pra agente; DESIGN.md = primer pra humano de UI)

---

### US-_DESIGNSYSTEM-002 · Catálogo navegável de componentes (rota Inertia /dev/components)

> owner: wagner · priority: p2 · estimate: 6h · status: todo · type: story
> blocked_by: —

## Contexto

Inventário hoje: 21 componentes em `Components/shared/` + 20 em `Components/ui/` (shadcn) + dezenas em `Components/{cockpit,clientes,jana,PageHeader,Site,...}/`. Descoberta atual via `grep` no `Components/`. Time MCP (Felipe/Maiara/Eliana/Luiz) reinventa componente porque não acha o shared.

Decisão arquitetural recomendada: **rota Inertia `/dev/components` em vez de Storybook**. Razões:
- Zero dependency nova (alinhado com skill `oimpresso-stack` — Laravel/Inertia/React monolito)
- Renderiza com tokens oklch reais (ADR 0190 primary roxo 295) — não simulação
- Multi-tenant Tier 0 fácil (esconde com `@can('superadmin')`)
- Funciona com auth/CSRF/etc do app real

Storybook valeria se time crescer pra 10+ devs frontend (não é o caso 2026).

## Acceptance Criteria

- [ ] Rota `GET /dev/components` (gate `@can('superadmin')` ou env=local) — Wagner decide gating
- [ ] Página Inertia `Pages/Dev/Components/Index.tsx` com sidebar por categoria (shared · ui · cockpit · clientes · jana · ...)
- [ ] Pra cada componente: nome · path · variants/states visíveis · snippet de uso · link pra `.charter.md` se existir
- [ ] Filtro busca por nome
- [ ] Indicador "✅ shared canon" vs "⚠️ módulo-específico" (anti-padrão AP2 PRE-MERGE-UI)
- [ ] Seed inicial cobre top 10 componentes mais usados (PageHeader, DataTable, EmptyState, StatusBadge, etc — medir via `grep -r import` ranking)
- [ ] Pest test rota retorna 200 + 403 sem perm

## Não-objetivos

- ❌ NÃO adicionar Storybook como dep (decisão arquitetural)
- ❌ NÃO documentar TODOS os componentes na v1 (top 10 + estrutura extensível)
- ❌ NÃO exigir charter pra todo componente (charter é por Page, não Component — ADR 0119)

## Trade-off explícito

Storybook tem ecossistema (addons a11y, viewport, dark mode toggle), mas custo de manutenção MDX/CSF e build extra. Rota Inertia tem custo zero novo mas reinventa addons. Wagner decide se v2 vira Storybook se time crescer.

---

### US-_DESIGNSYSTEM-003 · Decidir e ligar pr-ui-judge.yml (Claude Sonnet 4.5 review automático ~$3/mês)

> owner: wagner · priority: p2 · estimate: 0.5h · status: todo · type: story
> blocked_by: —

## Contexto

Workflow `.github/workflows/pr-ui-judge.yml` (Onda 4.1 AUTOMATION-ROADMAP) está PRONTO mas DESLIGADO via kill switch `vars.PR_UI_JUDGE_ENABLED == 'true'`. Avalia PR Inertia/React contra Constituição UI v2 (ADR UI-0013) com Claude Sonnet 4.5, posta comentário inline com:

- Score 0-100
- 9 dimensões (tipografia · cores · espaçamento · componentes · header/nav · estados · ícones · bordas · responsividade)
- Violações estruturais (drawer modal sobre modal, slot reinventado, layout violando PT-01)
- Sugestões cirúrgicas

Custo real validado no YAML:
- ~$0.034/PR (primeiro do dia)
- ~$0.005/PR (subsequentes com prompt caching ~85% reuse)
- ~$3/mês a 100 PRs/mês

Complementa (não substitui) `ui-lint.yml` (sintático grep) com análise semântica que grep não vê.

## Decisão Wagner

Liga ou deixa desligado? Tabela trade-off:

| Liga (✅) | Deixa desligado (❌) |
|---|---|
| Review automático em todo PR Inertia | $0 mês |
| Pega regressões semânticas que `ui-lint` não vê | Wagner mantém revisão manual |
| Time MCP recebe feedback estruturado sem esperar Wagner | Risco de regressão silenciosa |
| ~$3/mês a 100 PRs (irrelevante vs custo Brain B Jana) | — |
| Score 0-100 vira métrica histórica de saúde UI | — |

## Acceptance Criteria (se Wagner aprovar)

- [ ] Confirmar `ANTHROPIC_API_KEY` já existe em GitHub Secrets (provavelmente sim)
- [ ] `gh variable set PR_UI_JUDGE_ENABLED --body true --repo wagnerra/oimpresso` (ou via UI Settings → Variables)
- [ ] Abrir 1 PR teste pequeno em `resources/js/Pages/` pra validar funcionamento (comentário deve aparecer em 5min)
- [ ] Monitorar primeiros 5 PRs — verificar custo real bate com estimado ($0.034 → $0.005)
- [ ] Apendar evento ADR UI-0013 ou ADR de governança UI com data de ativação + métricas iniciais
- [ ] Se score médio < 70 nos primeiros 10 PRs, abrir cycle de remediação (gap entre código e Constituição maior que esperado)

## Não-objetivos

- ❌ NÃO usar `--strict` (exit 1) na v1 — começa em modo "feedback advisory", Wagner decide upgrade depois
- ❌ NÃO ligar sem comunicar time MCP (Felipe/Maiara/Eliana/Luiz vão receber comentários Brain B em todo PR)

## Estimate

0h código (já pronto). 30min decisão + ativação + smoke test. Custo recorrente ~$3/mês.

---

## Onda prevenção bugs MWART (US-_DESIGNSYSTEM-004..013) — 2026-05-28

> 10 tasks geradas a partir do dossier `memory/sessions/2026-05-28-arte-prevencao-bugs-mwart-larissa.md` e ADRs 0209/0210/0211 (PR #1837 propostos). Frontend enforcement passivo (ESLint, Wayfinder pilots, TanStack Query, MSW Vitest, custom rules).

### US-_DESIGNSYSTEM-004 · Install ESLint 9 flat-config + plugins canon + baseline ratchet

> owner: — · priority: p0 · estimate: 3h · status: todo · type: story
> blocked_by: —

**Onda 1 · habilita enforcement JS/TS.** `npm install -D eslint@9 @typescript-eslint/parser @typescript-eslint/eslint-plugin eslint-plugin-react-hooks eslint-plugin-jsx-a11y eslint-plugin-react-refresh`. `eslint.config.js` flat config com `@typescript-eslint/recommended`, `react-hooks/recommended`, `jsx-a11y/recommended` (sem no-autofocus), `react-refresh/only-export-components`. Baseline JSON ratchet idêntico a `ui-lint-baseline.json`.

**Acceptance:** `npm run lint` roda local; baseline gerado; sem mudança em comportamento UI.

Refs: ADR 0209

### US-_DESIGNSYSTEM-005 · Workflow eslint-gate.yml — CI ratchet contra baseline

> owner: — · priority: p0 · estimate: 1h · status: todo · type: story
> blocked_by: US-_DESIGNSYSTEM-004

**Onda 1.** `.github/workflows/eslint-gate.yml` espelhando `ui-lint.yml`. Dispara em PR tocando `resources/js/**/*.{ts,tsx}`. Roda `npx eslint --format=json` + comparação baseline. Falha só em REGRESSÃO. Annotations inline GitHub.

**Acceptance:** delta=0 verde; delta>0 vermelho com path:linha:rule.

Refs: ADR 0209

### US-_DESIGNSYSTEM-006 · Pilot Sells/Create.tsx com Wayfinder types (R8 raiz)

> owner: — · priority: p0 · estimate: 2h · status: todo · type: story
> blocked_by: US-INFRA-022

**Onda 3 · piloto 1.** Migrar `resources/js/Pages/Sells/Create.tsx` pra consumir tipos Wayfinder gerados. Substituir interface manual `CustomerSearchResult` pelo gerado. Substituir `SellsCreatePageProps` pelo gerado. Smoke prod biz=4 (Larissa) — validar cliente VIP com `selling_price_group_id` recalcula preços (R8 raiz).

**Acceptance:** `tsc --noEmit` limpo; smoke prod cliente trocado → carrinho recalcula; nenhuma regressão R7/R8/R9/R10.

Refs: ADR 0210 Fase 2

### US-_DESIGNSYSTEM-007 · Pilot Financeiro/Unificado/Index.tsx com Wayfinder types

> owner: — · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: US-INFRA-022

**Onda 3 · piloto 2.** Segunda tela mais reportada. Migrar pra Wayfinder.

**Acceptance:** type drift impossível em paths Inertia props; smoke prod biz=1 OK.

Refs: ADR 0210 Fase 2

### US-_DESIGNSYSTEM-008 · Install TanStack Query v5 + Provider em AppShellV2

> owner: — · priority: p0 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Onda 4 · data-fetching moderno.** `npm install @tanstack/react-query @tanstack/react-query-devtools`. `QueryClient` em `AppShellV2` ou layout-raiz. `<QueryClientProvider>` envolve app. `<ReactQueryDevtools initialIsOpen={false} />` em DEV. Defaults: staleTime 60s, gcTime 5min, retry 1.

**Acceptance:** Provider ativo; DevTools visível em dev; bundle prod aumenta ~16KB gzip (esperado).

Refs: ADR 0211 Fase 1

### US-_DESIGNSYSTEM-009 · Migrar ProductSearchAutocomplete pra useQuery (R7 raiz)

> owner: — · priority: p0 · estimate: 3h · status: todo · type: story
> blocked_by: US-_DESIGNSYSTEM-008

**Onda 4 · piloto 1.** Substituir `useEffect+setTimeout+AbortController+lastSelectedAtRef` em `ProductSearchAutocomplete.tsx` por `useQuery({queryKey: ['products', term, locationId], queryFn: ({signal}) => fetch(..., {signal})})`. Manter fixes R7 (AbortController + sentinela) como defesa-em-profundidade durante transição.

**Acceptance:** Pest 11 estruturais R7 verde; smoke prod biz=4 scanner USB → qty incrementa, dropdown não reabre; LOC reduz ~50-80.

Refs: ADR 0211 Fase 2

### US-_DESIGNSYSTEM-010 · Migrar CustomerSearchAutocomplete pra useQuery

> owner: — · priority: p0 · estimate: 3h · status: todo · type: story
> blocked_by: US-_DESIGNSYSTEM-008

**Onda 4 · piloto 2.** Substituir `useEffect+setTimeout` por `useQuery`. Race-protection grátis. Cache permite trocar cliente e voltar sem refetch.

**Acceptance:** Pest existentes verde; smoke prod cliente busca rápida.

Refs: ADR 0211 Fase 2

### US-_DESIGNSYSTEM-011 · MSW + Vitest fake-timers + suite scanner-race.test.tsx

> owner: — · priority: p1 · estimate: 4h · status: todo · type: story
> blocked_by: US-_DESIGNSYSTEM-008 + US-_DESIGNSYSTEM-009

**Onda 4 · test infra.** `npm install -D msw vitest @vitest/ui`. `vitest.config.ts` setup mínimo. Suite `tests/scanner-race.test.tsx` simula `KeyboardEvent sequence USB <50ms` + Enter. Workflow `vitest-gate.yml`.

**Acceptance:** suite cobre A (1 bipa), B (2 bipas mesmo SKU = qty 2), C (Enter duplo durante loading); MSW mocka `/products/list`.

Refs: ADR 0211 Fase 3

### US-_DESIGNSYSTEM-012 · Custom ESLint rule no-uncancelled-fetch-in-effect

> owner: — · priority: p2 · estimate: 5h · status: todo · type: story
> blocked_by: US-_DESIGNSYSTEM-004 + US-_DESIGNSYSTEM-008

**Onda 4 · enforcement final.** Detecta `useEffect` com `fetch()` sem `AbortController` OU sem `useQuery`. Custom rule via `@typescript-eslint/utils`. Baseline absorve existentes.

**Acceptance:** rule + fixture useEffect fetch sem abort = erro; useQuery limpo = OK; AbortController explícito = OK.

Refs: ADR 0211 Fase 5

### US-_DESIGNSYSTEM-013 · Catalogar AP-16 "Debounce + Promise sem cancelamento" no LICOES_F3

> owner: — · priority: p2 · estimate: 1h · status: todo · type: story
> blocked_by: —

**Onda 4 · doc.** Adicionar AP-16 em `LICOES_F3_FINANCEIRO_REJEITADO.md`. Exemplo R7 (PR #1824). Cross-ref ADR 0211.

**Acceptance:** PR docs adiciona AP-16.

Refs: ADR 0211 Fase 4

### US-_DESIGNSYSTEM-014 · Lote seguro: R9 <main> aninhado + R3 localStorage prefix

> owner: — · priority: p1 · estimate: 2h · status: todo · type: story
> blocked_by: —

**Lote determinístico, SEM gate visual** (estrutural/invisível). Derivado da worklist de auditoria paralela.

- **R9** `<main>` aninhado → `<div role="region">` em ~13 telas (AppShellV2 já provê o `<main>`; Page não deve aninhar — AP9). ⚠️ conferir públicas: `Site/*` fora do AppShell podem ter `<main>` legítimo — não tocar.
- **R3** localStorage sem prefixo → `oimpresso.<mod>.*` (1 tela).

**Fecha por evidência:** `node prototipo-ui/audit/score-mechanized.mjs` mostra R9=0 e R3=0. Sem screenshot (invisível).
Ref: `prototipo-ui/audit/BACKLOG-FIXES.md` · parent: worklist-auditoria-paralela.

### US-_DESIGNSYSTEM-015 · Lote ícones: R6 emoji + R4 svg/lib → lucide-react

> owner: — · priority: p2 · estimate: 3h · status: todo · type: story
> blocked_by: —

Trocar emoji (R6, ~21 telas) e svg-inline / lib-não-lucide (R4, ~18 telas) por ícone `lucide-react` (UI-0003 · AP4 · AP6). Visual leve.

**Fecha por evidência:** scorer R6=0 e R4=0 + screenshot que não regrediu.
Ref: `prototipo-ui/audit/BACKLOG-FIXES.md` (lotes R6, R4) — telas exatas em `reports/`. parent: worklist-auditoria-paralela.

### US-_DESIGNSYSTEM-016 · Lote R1 cor crua → token roxo (codemod, 17 piores primeiro)

> owner: — · priority: p1 · estimate: 6h · status: todo · type: story
> blocked_by: —

Codemod cor crua → token DS (roxo 295) em 40 telas (R1 = hex/oklch/rgb literal). **17 piores primeiro** (Financeiro/RecurringBilling/Cliente/Sells — todas R1+R2, módulos que mais faturam). Execução medida do P1 do PLANO-DESIGN-TELAS (≈ US-TR-310 Onda 1).

**Fecha por evidência:** scorer R1=0 nas telas do lote **+** screenshot golden aprovado (gate visual Wagner, ADR 0114). Cor é visível — NÃO fecha sem o print.
Ref: `prototipo-ui/audit/BACKLOG-FIXES.md` (lote R1). parent: worklist-auditoria-paralela.

### US-_DESIGNSYSTEM-017 · Lote R2 nativo → DS (prioriza ds/* pela worklist, faseado)

> owner: — · priority: p1 · estimate: 24h · status: todo · type: story
> blocked_by: —

Migrar elementos nativos → DS (`<select>`→Select, `<input>`→Input, `<textarea>`→Textarea, `<table>`→DataTable) — R2, 141 telas. **NÃO é trabalho novo:** é a migração `ds/no-native-*` já rastreada na Matriz (`DS_ADOCAO_INDICE.md`); esta task só a **prioriza pela worklist** (17 piores primeiro). Faseado por módulo.

**Fecha por evidência:** `ds/no-native-select|checkbox|radio` = 0 no ratchet (`config/eslint-baseline.json`) por módulo + scorer R2=0.
Ref: `BACKLOG-FIXES.md` (lote R2) + `MATRIZ_MIGRACAO_DS.md`. parent: worklist-auditoria-paralela.

### US-_DESIGNSYSTEM-018 · R7 status bg-fill: confirmar AP7 via Fase 2, depois codemod dot+texto

> owner: — · priority: p3 · estimate: 8h · status: todo · type: story
> blocked_by: —

R7 (`bg-*-100` fill → dot+texto, AP7) é heurística ampla (80/239 telas) — **não codar cego**. Primeiro a Fase 2 (agentes LLM read-only) confirma quais são AP7 real (badge de status) vs fundo legítimo. Só depois codemod nas confirmadas.

**Fecha por evidência:** lista confirmada pelo agente Fase 2 + scorer R7=0 nas confirmadas + screenshot. Depende da Fase 2 rodar.
Ref: `BACKLOG-FIXES.md` (lote R7). parent: worklist-auditoria-paralela.

---

### US-_DESIGNSYSTEM-027 · F0 · Congelar crescimento CSS (PARCIAL — falta rule css.md)

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

Roadmap MANUAL-CSS-JS §5 passo F0. **Já feito:** `css-size-gate` (ratchet de linhas) + `pageheader-gate`. **Falta:** rule `.claude/rules/css.md` que carrega SSOT+manual ao tocar `resources/css/**` + reforçar "proibir .css novo sem ADR". Esforço baixo. Fonte: memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md

---

### US-_DESIGNSYSTEM-028 · F1 · Inventariar débito de identidade (azul marca vs semântico)

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

MANUAL §5 F1. Mapear azul de MARCA legacy (#1572E8, @apply) que deve migrar vs azul SEMÂNTICO (status/origin-badge) que sobrevive; listar onde `.cockpit` define token paralelo ao `@theme`. Esforço baixo, sem ADR (cor já é canon 0235/0249).

---

### US-_DESIGNSYSTEM-029 · F2 · Token único em @theme (cockpit consome var(), não define)

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

MANUAL §5 F2. Dobrar `.cockpit`/legacy nos tokens DS v6 do Tailwind v4 (`inertia.css`); `cockpit.css` passa a CONSUMIR (`var(--…)`), não definir. `foundation:check` cobre. Fontes de token 3→1. Esforço médio.

---

### US-_DESIGNSYSTEM-030 · F3 · Criar Components/layout/ (Box/Stack/Inline/Grid/Container/Text)

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

MANUAL §2.1 + §5 F3. A camada que FALTA: primitivos de layout onde espaço só vem de token (Polaris/Radix Themes/Geist têm; oimpresso não). Doc no DS + ≥1 tela composta 100% por primitivos (zero flex solto, zero .css). Esforço médio. NOTA: criar componente novo = gate visual [W].

---

### US-_DESIGNSYSTEM-031 · F4 · Unificar PageHeader 104→0 (PARCIAL — pageheader-gate já congela)

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

MANUAL §5 F4. 2 PageHeaders: canon novo `@/Components/PageHeader` (~15 telas) vs antigo `@/Components/shared/PageHeader` (104 telas). **Já feito:** `pageheader-gate` (ratchet) proíbe tela NOVA adotar o antigo. **Falta:** toda tela tocada migra header antigo→canon no mesmo PR (gate visual natural); deletar `shared/PageHeader` quando contador zerar. Incremental.

---

### US-_DESIGNSYSTEM-032 · F5 · Dissolver os 2 mega-bundles CSS tela-a-tela

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

MANUAL §5 F5. `cowork-canon-financeiro-bundle` (~4.7k linhas) + `sells-cowork` (~4.0k) reescritos em Tailwind+primitivos tela-a-tela (tela tocada = fatia do bundle deletada). Métrica: linhas bespoke ↓ a cada PR. **Candidatos imediatos** (achados 2026-06-06): 6 bundles órfãos `app/base/components/layout/themes/utilities.css` (stubs Tailwind v3 mortos, confirmado seguro deletar — rebaseline css-size+stylelint). Esforço alto, incremental.

---

### US-_DESIGNSYSTEM-033 · F6 · Saúde do lint (zerar parser_error/no-undef P0, encolher jsx-a11y)

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

MANUAL §5 F6. Zerar `__parser_error__` + `no-undef` (bugs P0, não dívida tolerada); depois encolher `jsx-a11y/*` (acessibilidade). Baseline ESLint só encolhe (~1.073 em 2026-06-06, de 1.340). Esforço médio.

---

### US-_DESIGNSYSTEM-034 · F7 · Gates de regressão visual + axe + unificar vite

> owner: — · priority: p3 · status: todo · type: story
> blocked_by: —

MANUAL §5 F7. Ligar visual-regression como gate (screen-grade/`design:review` já existem) + axe nas telas (`screen-qa`); unificar `vite.config` quando o último Blade morrer. Esforço médio, dep. MWART.

### US-_DESIGNSYSTEM-035 · Showcase do DS no gate visual L2 (default+dark) — matar a classe do #3420

> owner: — · priority: p2 · status: todo · type: story
> blocked_by: —

- Contexto: token global quebrado no render (caso real #3420 — Lightning CSS dropou defs `--fin-*` → KPIs brancos com CI verde) só é pego hoje se atingir uma das telas baselinadas. Um snapshot do Showcase do DS (`/showcase/components`, `_Showcase/Components.tsx`) em default+dark pegaria a classe inteira numa tela só.
- Bloqueios mapeados (sessão 2026-07-10):
  - Rota `/showcase/components` é `superadmin`-middleware — o harness do VRT loga admin do tenant (403 → skip graceful → baseline vazio). Decidir: rota alternativa env-guarded pro CI, ou seed com superadmin.
  - `_Showcase/Components.tsx` NÃO tem charter — lint L2 (enforcing #3910) exige charter existente com `states:` sincronizado. Criar charter mínimo (tela interna dev/design-review).

**Acceptance:**
- [ ] Entrada `showcase` no `tests/Browser/visreg-states.json` (default+dark) + charter sincronizado
- [ ] Baselines geradas via update-mode + snap reproduzível 2 runs
- [ ] Prova contrafactual: quebrar 1 token de fundação em branch descartável → gate acusa

**Refs:** incidente #3420 · medição re-trabalho visual sessão 2026-07-10.

---

**Última atualização (US-_DESIGNSYSTEM-035):** 2026-07-10 — Showcase no VRT L2 (adiamento registrado do pacote dark-enforcing; bloqueios rota superadmin + charter ausente mapeados).

**Última atualização (US-_DESIGNSYSTEM-027..034):** 2026-06-06 — roadmap F0–F7 do [MANUAL-CSS-JS](MANUAL-CSS-JS.md) §5 seedado como tasks (de doc → MCP, conforme o próprio manual manda). IDs 027–034 (gap 019–026 = drift do contador do servidor MCP que sugeriu 027 vs SPEC.md max 018 — a reconciliar no servidor). F0/F4 marcados PARCIAL (já têm gates ativos).

**Última atualização (US-_DESIGNSYSTEM-014..018):** 2026-05-31 — backlog de fixes da worklist de auditoria paralela (5 lotes por regra mecanizada: R9+R3 · R6+R4 · R1 · R2 · R7). Fecha por evidência (scorer = regra zerada). Ref `prototipo-ui/audit/BACKLOG-FIXES.md`.

**Última atualização (US-_DESIGNSYSTEM-004..013):** 2026-05-28 — adicionadas 10 tasks Onda prevenção bugs MWART frontend (ADRs 0209-0211 propostos no PR #1837). Atacam R7/R8-class via ESLint baseline, Wayfinder type-gen, TanStack Query data-fetching, MSW Vitest scanner-race tests.

**Última atualização:** 2026-05-25 — adicionadas US-001/002/003 (batch validação design system pós-conversa Wagner com Claude do chat — 4 tasks no MCP US-_DESIGNSYSTEM-001/002/003 + US-INFRA-012)
