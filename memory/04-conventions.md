# 04 — Convenções do Projeto

> ⚠️ **FÓSSIL (pré-Constituição v2) — verificar contra canon.** Regras válidas misturadas com stale: "Laravel 10" em migrations (hoje **13.6**) e branch `develop` (hoje **`main`-protected**). Convenções canônicas atuais: `memory/how-trabalhar.md` + skills Tier A + ADRs. Ver `memory/governance/AUDITORIA-CONFLITOS-MEMORIA-2026-06-07.md`.

## Idioma

- **Mensagens para o usuário / UI:** PT-BR
- **Commit messages:** PT-BR (ex.: `feat(apuracao): aplicar tolerancia art 58 clt`)
- **Comentários:** PT-BR quando explicam regra de negócio; EN quando são TODOs técnicos genéricos
- **Documentação:** PT-BR
- **Código (classes, variáveis, métodos):** EN em geral, mas **domínio de negócio em PT** (ex.: classe `Marcacao`, método `aprovar()`, variável `$apuracao`)

## Naming

### Tabelas
- Prefixo do módulo: `ponto_` (ex.: `ponto_marcacoes`, `ponto_apuracao_dia`)
- snake_case, plural quando entidade
- Junção: `ponto_escala_turnos` (entidade dependente)

### Models (Entities)
- PascalCase, singular, em `Modules\Ponto\Entities\`
- Domínio PT: `Marcacao`, `Intercorrencia`, `ApuracaoDia`, `BancoHorasSaldo`
- Constantes de enum como `const` de classe: `Marcacao::TIPO_ENTRADA`

### Controllers
- PascalCase + `Controller`, em `Modules\Ponto\Http\Controllers\`
- Agrupamento por seção do menu: `DashboardController`, `EspelhoController`, `AprovacaoController`, etc.
- Métodos padrão REST: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`
- Métodos customizados em snake por ação: `aprovar`, `rejeitar`, `submeter`, `cancelar`

### Services
- PascalCase + `Service`, em `Modules\Ponto\Services\`
- Um service por domínio: `ApuracaoService`, `BancoHorasService`

### Rotas
- Prefixo: `/ponto/`
- Nome: `ponto.{secao}.{acao}` — ex.: `ponto.aprovacoes.aprovar`
- API: `/api/v1/ponto/`, nome `api.ponto.*`

### Arquivos Blade
- `Resources/views/{secao}/{acao}.blade.php`
- Partials: prefixo `_` — ex.: `_tabela.blade.php`

## Estilo de código

- **PSR-12** para PHP
- **Declarações estritas:** `declare(strict_types=1);` no topo de arquivos novos
- **Typed properties** sempre que possível
- **Enums nativos** (PHP 8.1+) preferidos a constantes, mas constantes ainda aparecem no código atual pelo padrão UltimatePOS
- **Injeção via construtor** em serviços/controllers (`public function __construct(protected X $x)`)
- **Early returns** para reduzir nesting
- **Evite Facades exceto as padrão Laravel** (`DB`, `Cache`, `Log`, `Storage`)

## Migrations

- Timestamp no nome: `2026_04_18_000001_create_...`
- Use `new class extends Migration` (Laravel 10)
- Sempre declare `down()`
- Índices compostos em ordem de seletividade: `['business_id', 'data', 'estado']`
- FKs explícitas com comportamento em cascata documentado

## Testes

- **PHPUnit** para unit e feature
- Localização: `Modules/Ponto/Tests/{Unit,Feature}/`
- Nome: `ApurarDiaTest.php`, métodos `test_apura_dia_sem_marcacoes_retorna_falta_integral`
- Cobertura mínima obrigatória: regras CLT em `ApuracaoService`
- **Toda função/endpoint/migration nova sai com teste Pest cobrindo o contrato** (URL, response shape, chave única, nome de coluna). Foco nos **pontos de contrato**, não 100% cobertura. Exemplo: `tests/Feature/Connector/DelphiOImpressoContractTest.php` blinda contrato Delphi via reflection sobre source — sem setup pesado de fixtures.

## Git / Commit

Conventional Commits em PT-BR:

```
feat(apuracao): aplicar tolerancia do art 58 clt
fix(marcacao): bloquear delete via trigger ausente em mariadb
refactor(banco-horas): extrair calculo para service
docs(memory): registrar adr 0007 sobre ledger
chore(deps): atualizar spatie/permission para 6.x
test(apuracao): cobrir cenario de intrajornada curta
```

Tipos: `feat`, `fix`, `refactor`, `docs`, `chore`, `test`, `style`, `perf`, `ci`

## Branches

- `main` — protegida, só merge via PR
- `develop` — integração contínua
- `feature/<ticket>-<slug>` — ex.: `feature/PW-012-afd-parser`
- `hotfix/<slug>` — correções urgentes

## Pull Requests

Template mínimo:

```md
## O que muda
(bullets do que foi feito)

## Por que muda
(link para ticket ou ADR)

## Como testar
(passos)

## Checklist
- [ ] Testes novos/atualizados
- [ ] Migration up/down ok
- [ ] Documentação atualizada se arquitetura mudou
- [ ] ADR criada se decisão estrutural
```

## Formatação de código (tooling)

- `php-cs-fixer` configurado com preset `@PSR12` + `array_syntax:short`
- `phpstan` nível 6 (aumentar gradualmente)
- `larastan` para specific Laravel

## Datas e timezones

- Banco armazena **UTC** sempre
- Apresentação: `America/Sao_Paulo` (ou tz do business)
- Marcações: sempre com timezone explícito
- Formato apresentação: `d/m/Y H:i` (PT-BR)

## Dinheiro / horas

- Horas em **minutos (integer)** internamente — evita float
- Apresentação: `+HH:MM` / `-HH:MM` via helper `formatarMinutos($min)`

## Inertia / SPA

- **Pages NÃO recarregam total entre cliques.** Use `<Link preserveScroll preserveState>`, `router.get(url, params, { preserveState: true, preserveScroll: true, only: [...] })`. NUNCA `window.location.reload()` ou `router.reload()` sem `only:[]`. Sentinela code review: PR adicionando reload sem `only` é regressão UX crítica.
- **Persistent Layout obrigatório.** Pages usam `Component.layout = (page) => <AppShell>{page}</AppShell>` — NUNCA envolver o return em `<AppShell>...</AppShell>` direto (sidebar pisca, perde estado accordion). `AppShell` detecta topnav via `useAutoModuleNav()` lendo `shell.topnavs` compartilhado — não passar `moduleNav` prop.
- **useForm sem opções extras** = correto pra Inertia v3 (default já é `forceFormData: false` quando não há upload). Após submit que redireciona, usar `onFinish: () => form.reset('field1', ...)` por campo, não reset total.

## Integração Delphi (Officeimpresso)

- **Contrato request/response IMUTÁVEL** — Delphi cliente em produção não recompila. Mudanças aditivas (campo opcional novo, endpoint novo) OK; renomear/trocar tipo/remover campo = quebra cliente real. Detalhe completo em [ADR 0021](decisions/0021-officeimpresso-contrato-api-delphi.md).
- **Delphi corrompe UTF-8** em ShowMessage/MessageBox/clipboard. Em payloads críticos pra Delphi, manter strings (status code, version `2026.1.0.5`, tokens) em **ASCII puro**. Ao diagnosticar erro vindo do Delphi, confiar só em tokens ASCII (paths, HTTP status, JSON keys); acentos podem virar mojibake no transporte.

---

**Última atualização:** 2026-05-09
