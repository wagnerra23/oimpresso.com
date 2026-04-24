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

**Testado em:** `Modules/DocVault/Tests/Unit/DesignSystemAuditTest::test_no_raw_buttons` (futuro)

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
