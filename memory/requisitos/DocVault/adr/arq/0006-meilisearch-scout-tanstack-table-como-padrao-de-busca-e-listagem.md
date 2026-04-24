# ADR ARQ-0006 (DocVault) · Meilisearch + Scout + TanStack Table como padrão de busca e listagem

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Relacionado**: ADR arq/0004 (substituição DataTables), _DesignSystem ADR UI-0001

## Contexto

ADR arq/0004 concluiu que `yajra/laravel-datatables-oracle` + `DataTables.net` devem ser substituídos por alternativa React-nativa. Análise comparou TanStack Table v8 × AG Grid Community × Material React Table × react-data-grid.

Wagner tem experiência anterior positiva com TanStack e AG Grid, e levantou: "qual melhor com Meilisearch?".

Meilisearch é motor de busca full-text + semântica (discutido previamente — ADRs de observação do ChatGPT consolidados em `memory/requisitos/DocVault/CHANGELOG.md`). Ainda não está instalado.

## Decisão

**Combo canônico pra listagem + busca no projeto:**

```
┌───────────────────────────────────────────────────────┐
│ Backend                                               │
│   Laravel Scout (driver: database | meilisearch)      │
│     → Eloquent Model::search($q)                      │
│   Spatie Query Builder (filter[field]=X, sort=Y)      │
│     → query params estruturados                       │
│   Paginator withQueryString()                         │
│     → JSON pro Inertia                                │
├───────────────────────────────────────────────────────┤
│ Frontend                                              │
│   TanStack Table v8 (headless)                        │
│     → definição de colunas em JSX tipado              │
│     → sort/filter/pagination com state local          │
│   shadcn/ui Table primitives (Table, TableRow, etc.)  │
│     → estilo Tailwind + acessibilidade Radix          │
│   Debounced search input                              │
│     → 300ms antes de refetch                          │
└───────────────────────────────────────────────────────┘
```

### Por que TanStack sobre AG Grid

Com **Meilisearch/Scout**, quem faz filter+sort+search é o **backend**. TanStack Table é headless — delega tudo pro server. AG Grid tem motor interno de filter/sort que colide com search externo.

| Critério | TanStack v8 | AG Grid Community |
|---|---|---|
| Server-side pagination | Manual (fácil via query params) | Exige **AG Grid Enterprise** (pago $999/dev/ano) |
| Headless / BYO-UI Tailwind | ✅ | ❌ (UI própria) |
| Integra com `_formatted` do Meilisearch (HTML highlighted) | JSX `dangerouslySetInnerHTML` — trivial | Custom cellRenderer obrigatório |
| Bundle gzip | 15kB | 200kB |
| Alinha com `_DesignSystem` (Tailwind+shadcn) | ✅ | ❌ |
| Licença | MIT livre | MIT (básico) + Enterprise pago pras features úteis |

**Veredito**: TanStack ganha quando search externo existe (nosso caso). AG Grid só faz sentido quando não há motor de search externo e você precisa de Excel-like inline editing / pivot / agrupamento hierárquico — não é nosso caso.

### Scout driver: `database` agora, `meilisearch` depois

**Fase A (agora — local sem Docker)**: Scout com driver `database` usa fulltext MySQL. Zero server extra. Funciona no Windows sem Meilisearch binary. Limitação: busca só por keyword, sem ranking semântico.

**Fase B (futuro — quando volume justificar ou Meilisearch instalado)**: troca `SCOUT_DRIVER=meilisearch` no `.env`. Zero mudança de código Laravel. Ganha: vector search (embeddings), facets nativas, highlighted matches `_formatted`, tolerância a typos.

### Spatie Query Builder (opcional, entra conforme precisar)

Pra filtros estruturados em URL (`?filter[module]=PontoWr2&sort=-created_at`). Desacoplado do Scout. Adiciona conforme tela pede filtros específicos.

## Plano de execução

### Piloto: DocVault Inbox (esta sessão)

1. **Backend:**
   - `composer require laravel/scout meilisearch/meilisearch-php`
   - `DocEvidence` vira `Searchable` (trait + `toSearchableArray()`)
   - `.env`: `SCOUT_DRIVER=database` (fulltext MySQL, sem dep externa)
   - `InboxController::index` troca paginator por search query

2. **Frontend:**
   - `npm install @tanstack/react-table`
   - `shadcn add table` (copy-paste primitive)
   - Refatorar `Inbox.tsx` pra usar TanStack + debounced input

3. **Validação:**
   - Browser test: busca "bug" retorna evidências kind=bug
   - `docvault:audit-module DocVault` ≥ baseline

### Replicação (próximas sessões)

Depois do piloto validado, aplicar em ordem de valor:
1. `/docs/modulos` já tem filter embutido — não precisa TanStack ainda
2. Telas DataTables do ADR 0004 (Roles, Account, Subscriptions, JobSheet)
3. Novas telas CRUD sempre com esse padrão (entra na SPEC de `_DesignSystem`)

## Consequências

**Positivas:**
- Padrão único pra listar+buscar em todo projeto.
- Remove 140kB de jQuery+DataTables quando migrar usos.
- Prepara pro Meilisearch sem refactor posterior (troca só env).
- Listagens com busca instantânea (300ms debounce — UX moderna).

**Negativas:**
- +15kB no bundle (TanStack Table) — aceitável pro ganho.
- Curva de aprendizado TanStack pra devs novos (docs maduras, exemplos fartos).

**Trade-off consciente**: ganho de manutenibilidade e UX moderna vs overhead de 1 lib headless.

## Alternativas consideradas (revisão com foco Meilisearch)

- **AG Grid Community**: rejeitado pelos motivos acima.
- **AG Grid Enterprise**: overkill + custo.
- **DataTables.net com Meilisearch**: jQuery legado, custom driver inexistente pra Meilisearch.
- **Só shadcn Table sem TanStack**: viável pra tabelas triviais (já usamos em PontoWr2). TanStack entra quando precisar sort/filter/colunas dinâmicas reutilizáveis.

## Sinais de conclusão

- [ ] Laravel Scout instalado + configurado driver `database`
- [ ] `DocEvidence` Searchable com `toSearchableArray`
- [ ] TanStack Table v8 instalado e importável
- [ ] Inbox.tsx refatorado com TanStack + debounced search
- [ ] Busca "bug" no inbox funciona
- [ ] ADR UI-0002 (_DesignSystem) futura: "toda listagem com >10 itens usa TanStack Table"
