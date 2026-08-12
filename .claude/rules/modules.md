---
paths:
  - "Modules/**/*.php"
---

# Rule path-scoped — `Modules/**/*.php`

> Carrega quando Claude lê/edita PHP dentro de qualquer módulo nWidart. Complementa skill Tier A `preflight-modulo` + hook `modulo-preflight-warning.ps1`.

## Workflow 3 fases obrigatório (Tier 0 IRREVOGÁVEL)

Regra Primária [`memory/proibicoes.md`](../../memory/proibicoes.md) §"REGRA PRIMÁRIA — Mexeu, REGISTRA":

1. **PRE-FLIGHT** — antes de Edit/Write ler:
   - **`memory/requisitos/<Modulo>/SCOPE.md`** — FONTE PRIMÁRIA: frontmatter lista `url_prefixes`, `contains` (1-liner por controller), `not_contains` (cross-cutting documentado), `drift_alerts`, `db_tables_owned`, `charter_adr`. Antes de Glob/Grep, abra SCOPE — responde 80% das perguntas "onde está X em Y / que rotas Y tem / quem owna tabela Z". Se SCOPE responder, cite-o; se estiver desatualizado, proponha PR de fix junto.
   - `memory/requisitos/<Modulo>/SPEC.md` (US-XXX-NNN)
   - `memory/requisitos/<Modulo>/RUNBOOK-*.md` (se MWART .tsx)
   - `memory/requisitos/<Modulo>/CAPTERRA*.md` (escopo aprovado)
   - `memory/requisitos/<Modulo>/BRIEFING.md` (estado consolidado)
   - ADRs via `decisions-search query:"<modulo lowercase>"`

   **Reverse-lookup cross-modular** ("quem usa X cross-cutting?"): `Grep "<termo>" memory/requisitos/*/SCOPE.md` — `not_contains` + `drift_alerts` revelam decisões conscientes sem precisar varrer código. Origem: 2026-05-17 — pergunta "reunir rotas governança" mapeada via 4 Glob + 2 Grep quando `memory/requisitos/Governance/SCOPE.md` + `memory/requisitos/Jana/SCOPE.md` respondiam direto.
2. **DURING** — commit incremental por step lógico; `git push` WIP a cada ~30min; `TodoWrite` mark completed; NUNCA `git checkout` sem `stash`/`commit`
3. **POST** — `mexeu, registra` — PR no git + CI verde + merge + docs canon

## Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

- Toda Eloquent Model que toca dados de negócio DEVE ter `business_id` global scope
- NÃO usar `withoutGlobalScopes` sem comentário `// SUPERADMIN: <razão>`
- Job assíncrono SEMPRE recebe `$businessId` no constructor (session() não funciona em fila)
- Pest test biz=1 obrigatório ([ADR 0101](../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)) — nunca biz=cliente real

## Nomenclatura PHP/DB dentro do módulo

> Absorvido de `memory/04-conventions.md` em 2026-08-10 ([W]: *"se o arquivo tiver destino correto,
> junte no arquivo correto"*). Aquele arquivo era **FÓSSIL pré-Constituição v2** e misturava isto,
> que é válido e **não existia em mais lugar nenhum do canon**, com stale já morto (Laravel 10,
> branch `develop`) — o stale ficou de fora. Escrito com `Ponto` como exemplo; vale pra todo módulo.

| O quê | Convenção |
|---|---|
| **Tabelas** | prefixo do módulo (`ponto_marcacoes`) · `snake_case` · plural quando entidade · junção nomeia a dependente (`ponto_escala_turnos`) |
| **Models** | `PascalCase` **singular** em `Modules\<X>\Entities\` · domínio em **PT** (`Marcacao`, `Intercorrencia`, `BancoHorasSaldo`) · enum como `const` de classe (`Marcacao::TIPO_ENTRADA`) |
| **Controllers** | `PascalCase` + `Controller` · agrupado por **seção do menu**, não por entidade · REST padrão (`index`/`create`/`store`/`show`/`edit`/`update`/`destroy`) + customizado por **ação em PT** (`aprovar`, `rejeitar`, `submeter`) |
| **Services** | `PascalCase` + `Service` · **um por domínio** (`ApuracaoService`, `BancoHorasService`) |
| **Rotas** | prefixo `/<modulo>/` · nome `<modulo>.{secao}.{acao}` (`ponto.aprovacoes.aprovar`) · API `/api/v1/<modulo>/` com nome `api.<modulo>.*` |
| **Blade** | `Resources/views/{secao}/{acao}.blade.php` · partial com prefixo `_` (`_tabela.blade.php`) |

⚠️ Nomenclatura de **componente React** não está aqui — é [`components.md`](components.md) (árvore
canônica UI-0013). Este bloco é só PHP/DB.

## Padrões UltimatePOS herdados

- Stack middlewares rotas web: `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']`
- NÃO modificar tabelas core (`users`, `business`, `employees`) sem bridge table
- Roles Spatie com suffix `#{biz}` quando tabela `roles.business_id` NOT NULL existir

## Skills relacionadas

`preflight-modulo` (Tier A) · `multi-tenant-patterns` (Tier A) · `commit-discipline` (Tier A) · `criar-modulo` (Tier B) · `como-integrar` (Tier B)
