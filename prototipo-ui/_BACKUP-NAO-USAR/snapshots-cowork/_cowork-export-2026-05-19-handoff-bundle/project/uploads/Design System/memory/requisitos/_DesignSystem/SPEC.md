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
