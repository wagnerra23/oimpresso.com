---
slug: 0119-migration-factory-capacidade-institucional
number: 119
title: "Migration Factory — capacidade institucional do oimpresso pra ingerir cliente de qualquer ERP legacy"
type: adr
status: deprecated
authority: canonical
lifecycle: arquivado
decided_by: [W]
decided_at: 2026-05-09
module: null
quarter: 2026-Q2
tags: [migration-factory, legacy-migration, business-strategy, brownfield-ai, ddd, multi-tenant]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0061, 0070, 0093, 0094, 0106, 0113, 0118]
pii: false
review_triggers:
  - "terceira fonte de migração entrar em produção (atual: WR Comercial; previstas: Bling, Tiny, Sankhya, concorrentes nicho gráfico)"
  - "mais de 5 clientes simultaneamente em estado migração em andamento (justifica UI dedicada)"
  - "revenue de Migration as a Service ultrapassar 10% do MRR (vira pilar comercial, exige SLA formal)"
  - "cliente migrar de legacy mas voltar pro velho em <30 dias (sinal de qualidade insuficiente — revisar receita)"
---

# ADR 0119 — Migration Factory — capacidade institucional do oimpresso

## Contexto

Sessão 2026-05-09 entregou pipeline ponta-a-ponta Delphi WR Comercial → Laravel oimpresso (8 PRs, ~14k linhas, 3 contas reais validadas em smoke biz=1). Pipeline aplica padrão **Strangler Fig + Anticorruption Layer + Brownfield AI** ([ADR 0118](0118-segregacao-dominios-externos-clientes-legacy.md), [Pattern 01](../dominios/_patterns/01-strangler-fig-acl-brownfield-ai.md)) com Claude Opus 4.7 atuando como ACL agent.

Wagner explicitou em sessão a visão expandida:

> *"eu vou querer usar esse conhecimento de migrar banco dos clientes. Principalmente de bancos de clientes que usam outro sistema é um tema muito importante. acredito que o futuro pegaremos o banco do cliente e migrar tudo."*

Implicações:

1. Migração não é escopo restrito ao **legacy próprio** (Delphi WR Comercial dos 50 clientes existentes). É **capacidade comercial** — qualquer cliente em qualquer ERP velho ganha porta de saída pro oimpresso.

2. Diferencial competitivo no mercado de ERPs gráficos BR: hoje cliente que quer trocar de Zênite/Mubisys/Alfa/Visua/Calcgraf/Calcme/Bling/Tiny precisa abandonar histórico ou re-digitar manualmente. Fricção alta = bloqueio comercial.

3. ROTA LIVRE (biz=4, cliente 99% volume) deixa de ser exceção e vira **template de cliente migrado** — se ela usasse outro ERP antes, a Migration Factory teria capturado o histórico.

4. Mercado addressable: concorrentes nicho gráfico têm ~1000+ gráficas combinadas; ERPs SaaS BR (Bling/Tiny/Conta Azul) têm milhões de pequenas empresas — universo de leads via migração.

## Decisão

Adotar **Migration Factory** como **capacidade institucional Tier B** do oimpresso, com 4 componentes:

### 1. Conhecimento canônico em `memory/dominios/`

Estrutura formalizada por [ADR 0118](0118-segregacao-dominios-externos-clientes-legacy.md):
- `memory/dominios/<sistema>/` — 1 pasta por sistema externo (atual: `wr-comercial/`; previsto: `bling/`, `tiny/`, etc)
- `memory/dominios/_patterns/` — 7 patterns reusáveis agnósticos de fonte ([README](../dominios/_patterns/README.md))
- `memory/dominios/_template/` — skeleton pra novo sistema externo
- `memory/dominios/_overview.md` — taxonomia + roadmap geral
- `memory/clientes-legacy/<alias>.md` — quirks por cliente cross-cutting

### 2. Engine reusável `Modules/MigrationFactory/` (Laravel — futuro)

Quando criado (escopo separado, várias sessões), terá:
- **Drivers** plugáveis por tipo de fonte (Firebird, REST API, CSV, scraping)
- **Mappers** declarativos por sistema×entidade
- **Validators** (drift check, totals match, count match)
- **UI superadmin** dashboard de jobs em andamento
- **Agendamento** via cron (sync periódico se cliente quiser parallel run temporário)

Hoje, o equivalente vive em `scripts/legacy-migration/` (Python local Wagner). **Promoção pra `Modules/MigrationFactory/` Laravel acontece quando 2º sistema externo entrar** — antes, é overengineering ([ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md) §"no premature abstraction").

### 3. Operação superadmin only

UI futura `/migration-factory/` ficará restrita a `can('superadmin')` (Wagner inicialmente; time interno conforme expandir). Os DADOS são **tenant-scoped** via `business_id` obrigatório ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)); o USO da factory é **superadmin only**. Padrão validado em [PR #353](https://github.com/wagnerra23/oimpresso.com/pull/353) — `accounts_legacy_map.business_id` global scope + `withoutGlobalScope(BusinessScopeImpl::class)` é caso superadmin deliberado.

### 4. Catálogo de fontes incremental

Atual:
- ✅ **WR Comercial** (Delphi, Firebird) — 50 bancos registrados em `HKCU\Software\Rocha\Office Comercial\Banco\Caminhos`. 1 cliente migrado em smoke (Wagner biz=1).

Previsto (sem prazo definido — cresce sob demanda real):
- ⏳ **Bling** (REST API, ERP SaaS BR) — mercado grande pequenas empresas
- ⏳ **Tiny** (REST API, ERP SaaS BR) — concorrente direto Bling
- ⏳ **Concorrentes nicho gráfico** (Zênite, Mubisys, Alfa Networks, Visua, Calcgraf, Calcme) — captura por migração
- ⏳ **Sankhya / TOTVS Protheus / Microsiga** (ERPs enterprise) — demanda baixa, valor alto
- ⏳ **Asaas / Iugu / Pagar.me** (gateways financeiros) — já parcialmente integrado runtime via Connector ([ADR 0113](0113-integracao-delphi-laravel-ads-3-caminhos.md))

Próximo concorrente alvo escolhido **sob demanda real do mercado** — não antecipar.

## Justificativa

### Por que Tier B (não Tier A)

[Constituição v2 ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §6 Tiers:
- Tier 0: irrevogável (multi-tenant, business_id global scope)
- Tier A: princípios duros (commit-discipline, mwart-process)
- Tier B: capacidades institucionais (ADS, Migration Factory)
- Tier C: convenções operacionais

Migration Factory é **capacidade**, não princípio. Pode evoluir/desmontar/refatorar sem violar nenhuma irrevogabilidade. Documenta-se ADR 0119 mas não vira skill always-on — ativada por contexto (auto-mem skill `automem-pending` Tier B já cobre triggers tipo "Delphi", "Asaas", "concorrentes").

### Por que NÃO criar `Modules/MigrationFactory/` agora

3 razões:

1. **Princípio "no premature abstraction"** ([ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md)) — abstrair antes de 2º caso é especulativo. O importer Python atual tem driver Firebird hardcoded; quando 2º caso (Bling REST API) chegar, refatoração IA-pair em fator 10x extrai DriverInterface.

2. **`Modules/<X>/` Laravel exige checklist completo** — skill `criar-modulo` mostra 8 peças obrigatórias + 3 rotas Install + tests + UI Inertia + permissions. Escopo de várias sessões. Sem 2º cliente real demandando UI, é trabalho que envelhece.

3. **Operação atual é ad-hoc Wagner** — Wagner roda `python import-contas-bancarias.py --alias X --target-business N` direto. Não há demanda imediata por dashboard/agendamento/multi-user.

Promoção pra Laravel acontece quando: (a) 2º sistema externo entrar → engine genérico justificado; (b) cliente externo demandar self-service → UI necessária; (c) volume superar 5 imports simultâneos → coordenação dedicada vale.

### Por que separado de [ADR 0113](0113-integracao-delphi-laravel-ads-3-caminhos.md)

ADR 0113 endereça **integração runtime** Delphi cliente desktop ↔ Laravel oimpresso (3 caminhos aditivos via Connector API). É **comunicação contínua** entre 2 sistemas que coexistem.

ADR 0119 endereça **migração one-shot de dados** legacy → oimpresso. É **transferência de propriedade** — cliente abandona o velho ao final.

São complementares, não conflitantes. Cliente pode estar simultaneamente em ambos os fluxos durante a transição (Delphi ainda chama Connector enquanto Migration Factory copia histórico).

### Quando reabrir esta decisão

- **3ª fonte de migração** entrar em produção (forçará abstração de DriverInterface formal — vira `Modules/MigrationFactory/` Laravel)
- **5+ clientes simultaneamente em migração** (justifica UI dedicada com job queue, status page, alerting)
- **Migration as a Service** ultrapassar 10% do MRR (vira pilar comercial, exige SLA formal + suporte dedicado)
- **Cliente migrar mas voltar pro velho em <30 dias** (sinal de qualidade insuficiente — revisar receita end-to-end)

## Consequências

**Positivas:**
- Diferencial comercial concreto: "Saia do seu ERP, mantenha tudo" vira argumento de venda
- Concorrentes nicho gráfico viram fonte de leads ("clientes deles preferem oimpresso e podem migrar")
- ROTA LIVRE deixa de ser exceção; se eventualmente migrar de outro sistema, captura histórico
- 7 patterns reusáveis ([memory/dominios/_patterns/](../dominios/_patterns/)) reduzem custo marginal de N-ésima migração
- Conhecimento canônico em git/MCP — qualquer dev do time pode atacar próximo sistema externo
- Operação superadmin clara — clientes não veem nem precisam saber da factory

**Negativas / Trade-offs:**
- Catálogo `dominios/<sistema>/` cresce — disciplina pra não inflar com sistemas hipotéticos sem cliente real
- Decisão pendente quando ABSTRAIR engine: cedo demais = especulativo; tarde demais = duplicação
- Vaultwarden integration pra segredos legacy (cap. credenciais bancárias, OAuth tokens) ainda não implementada — bloqueador pra prod sem isso
- Modelo de receita "Migration as a Service" ainda não validado comercialmente — premissa de demanda

**Riscos mitigados:**
- **Pattern explosion**: 7 patterns canônicos cobrem core; novos viram pattern só após 2+ casos validados
- **Migration drift entre sistemas**: cada `dominios/<sistema>/CONVENCOES.md` é local, não polui patterns globais
- **Vazamento cross-tenant em factory**: bridge tables `<core>_legacy_map` com `business_id` scope global ([Pattern 02](../dominios/_patterns/02-bridge-tables-para-core.md))
- **Importer rodando direto em prod**: Pattern 07 three-mode (`dry-run/local/prod` com `--confirm` obrigatório)

## Referências

- [ADR 0061 — Conhecimento canônico em git/MCP, zero auto-mem](0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [ADR 0093 — Multi-tenant Tier 0](0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição v2 (Tiers, SoC brutal)](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0106 — Recalibração 10x IA-pair (no premature abstraction)](0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0113 — Integração runtime Delphi↔Laravel (complementar)](0113-integracao-delphi-laravel-ads-3-caminhos.md)
- [ADR 0118 — Segregação dominios externos (estrutural mãe)](0118-segregacao-dominios-externos-clientes-legacy.md)
- [memory/dominios/_patterns/](../dominios/_patterns/) — 7 patterns reusáveis
- [Sessão 2026-05-09 — pipeline completo](../sessions/2026-05-09-pipeline-legacy-migration-completo.md)
- Eric Evans, *Domain-Driven Design* (2003) — Bounded Context, ACL
- Martin Fowler, [*StranglerFigApplication*](https://martinfowler.com/bliki/StranglerFigApplication.html) (2004)
- [Brownfield AI — TianPan abr/2026](https://tianpan.co/blog/2026-04-12-brownfield-ai-integrating-llm-features-into-legacy-codebases)
