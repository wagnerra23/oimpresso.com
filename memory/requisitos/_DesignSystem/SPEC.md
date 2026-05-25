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

**Testado em:** `Modules/MemCofre/Tests/Unit/DesignSystemAuditTest::test_no_raw_buttons` (futuro)

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

**Testado em:** `Modules/PontoWr2/Tests/Feature/AprovacoesIndexTest` (prova de conceito 2026-04-24). Check C16 futuro no `ModuleAuditor`: toda page em listagem importa de `@/Components/shared/`.

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

## 3. Backlog (User Stories)

### US-_DESIGNSYSTEM-001 · DESIGN.md root TOC executivo apontando pros docs canon

> owner: wagner · priority: p1 · estimate: 0.5h · status: todo · type: story
> blocked_by: —

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

**Última atualização:** 2026-05-25 — adicionadas US-001/002/003 (batch validação design system pós-conversa Wagner com Claude do chat — 4 tasks no MCP US-_DESIGNSYSTEM-001/002/003 + US-INFRA-012)
