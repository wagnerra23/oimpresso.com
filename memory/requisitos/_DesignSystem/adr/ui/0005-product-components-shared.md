# ADR UI-0005 · Camada de componentes de produto em `Components/shared/`

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner, Claude
- **Categoria**: ui

## Contexto

As primitivas `shadcn/ui` (Button, Card, Badge, Input, etc.) cobrem o nível UI mais baixo — são genéricas e acessíveis. Mas cada uma das 19 pages do módulo Ponto (e 6 do DocVault, 12 do Modules, etc.) reimplementa do zero padrões recorrentes:

- KPI card (Dashboard, Espelho, BancoHoras, DocVault dashboard)
- Cabeçalho de tela (h1 + description + action)
- Container de filtros com chips ativos
- Badge de status por domínio (intercorrência, pagamento, importação)
- Estado vazio com CTA
- Barra flutuante de ações em lote

Resultado: drift visual (4 tamanhos diferentes de KPI), ~40% de duplicação de código JSX, manutenção custosa. A `Aprovacoes/Index.tsx` tem 480 linhas — 60% é JSX de tabela/dialog que poderia ser composição.

## Decisão

Criar uma camada intermediária de **componentes de produto** em `resources/js/Components/shared/`:

| Componente | Propósito |
|---|---|
| `PageHeader` | h1 + icon + description + slot action |
| `KpiCard` | Card de indicador com variants de tone (default/success/warning/danger/info) e size (compact/default/large), delta com seta e cor automática |
| `KpiGrid` | Grid responsivo (2/3/4/6 colunas) pra KpiCards |
| `StatusBadge` | Badge semântica por domínio (intercorrência, aprovação, prioridade, payment, importacao, rep) |
| `PageFilters` | Container de filtros com `activeChips` e `onReset` |
| `FilterChip` | Chip de filtro ativo com X (exportado de PageFilters) |
| `EmptyState` | Estado vazio com icon, title, description, action, variants (default/search/error/success) |
| `BulkActionBar` | Barra flutuante fixed-bottom visível quando `selectedCount > 0`, estilo Gmail/Linear |

## Regras

- Todos seguem os 7 preceitos do design system (R-DS-001 a R-DS-007): apenas shadcn+lucide, tokens semânticos, zero CSS custom.
- Convenção `data-slot="nome"` em cada root element pra facilitar CSS override pontual.
- TypeScript strict: nenhum `any` em props.
- Sem estado interno complexo — todos são dumb/controlled. Estado fica na page.

## Showcase

Rota `/showcase/components` (superadmin) renderiza todos os componentes em estados típicos — serve pra:
- Design review visual
- Testar dark mode
- Prints pra documentação
- Onboarding de novo dev no design system

Arquivo: `resources/js/Pages/_Showcase/Components.tsx`.

## Consequências

**Positivas:**
- `Aprovacoes/Index` pode ir de 480 → ~180 linhas
- Dark mode testado 1× no showcase, herda em todas as telas
- Dev novo escreve tela em 30min copy-paste do showcase
- Consistência visual auditável (R-DS-001..007)

**Negativas:**
- Breaking changes no `shared/` exigem refactor global
- Risco de sobre-abstração: se um pattern só existe em 1 tela, NÃO promover pra shared
- Cada componente novo precisa ser documentado aqui (criar entry em tabela)

## Alternativas consideradas

- **Adicionar tudo em `Components/ui/`** (shadcn layer): rejeitado — mistura primitiva genérica com componente de produto.
- **Biblioteca externa tipo Ark UI ou Park UI**: rejeitado — overhead de manter sincro com shadcn já copy-paste; nada que elas fazem justifica.
- **Deixar como está, só pra Ponto**: rejeitado — o ganho é maior quando os 20+ módulos compartilham.

## Próximos passos

1. Refatorar `Ponto/Aprovacoes/Index.tsx` como prova de conceito (Fase 1 do roadmap em `cliente_rotalivre.md` NÃO — em sessão de design 2026-04-24).
2. Adicionar `DataTable v2` (extensão do atual com sticky header, density toggle, bulk mode).
3. Adicionar `MonthPicker`, `TimeRangePicker`, `PrintLayout` em ADR separado quando necessário.
4. Criar teste Pest auditando que toda page em `Ponto/` importa de `@/Components/shared` (não reinventa). Sugestão: `docvault:audit-module PontoWr2` ganha check C16 "uses shared components".
