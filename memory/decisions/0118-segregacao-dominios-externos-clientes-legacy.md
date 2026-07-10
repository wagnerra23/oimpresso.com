---
slug: 0118-segregacao-dominios-externos-clientes-legacy
number: 118
title: "Segregação de domínios externos e clientes-legacy em pastas top-level no memory/"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-09
module: null
quarter: 2026-Q2
tags: [knowledge-management, ddd, bounded-context, anticorruption-layer, legacy-migration, multi-tenant]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0061, 0070, 0093, 0094, 0113]
pii: false
review_triggers:
  - "terceira integração externa surgir (atualmente: Delphi WR Comercial; previstas: Bling/Tiny/Asaas APIs)"
  - "estrutura `memory/dominios/<sistema>/modulos/` se mostrar inadequada após 3+ módulos documentados"
  - "cliente Delphi for migrado integralmente pro oimpresso e `clientes-legacy/<alias>.md` deixar de ser cross-cutting (vira histórico)"
---

# ADR 0118 — Segregação de domínios externos e clientes-legacy em pastas top-level no `memory/`

## Contexto

Wagner inicia migração estruturada de dados do legacy **Delphi WR Comercial** (50 bancos Firebird registrados em `HKCU\Software\Rocha\Office Comercial\Banco\Caminhos`, schema versionado em `UpdateSQL.txt` v6→v1468, validado em [POC 2 sessão 2026-05-09](../sessions/2026-05-09-fase2-pocs-legacy-migration.md)) **para o Laravel oimpresso** (multi-tenant `business_id`, [ADR 0093](0093-multi-tenant-isolation-tier-0.md)).

A migração tem 3 dimensões cruzando que precisam coexistir como conhecimento canônico:

1. **Domínio funcional** — Vendas, Financeiro, Estoque, Producao, NFe etc
2. **Plataforma** — Delphi (legado, vocabulário próprio: `CONTAS_BANCARIAS`, `FINANCEIRO_BOLETO`, `VERSAO_BANCO`) vs Laravel oimpresso (vocabulário próprio: `bank_accounts`, `transaction_payments`, `business_id`)
3. **Cliente** — 50 bancos Firebird em versões Delphi diferentes (v571..v1474), cada um com customizações ou quirks (ex: ROTA LIVRE biz=4 com `format_date` shift +3h, [ADR 0066](0066-format-date-shift-3h-preservado-legacy-clientes.md))

Hoje `memory/requisitos/<Mod>/` espelha estritamente os módulos Laravel oimpresso. **Misturar conhecimento Delphi** (estrutura de tabelas, formato `UpdateSQL.txt`, API `TRegistry`) **dentro de `requisitos/`** violaria o princípio §5 SoC brutal da Constituição v2 ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)). E criar uma sub-pasta `requisitos/MigracaoLegacy/dominio-delphi/` acoplaria o conhecimento do legado a um único módulo Laravel — frágil se amanhã o módulo for refatorado/dividido.

**Caminho de integração runtime já decidido em [ADR 0113](0113-integracao-delphi-laravel-ads-3-caminhos.md)** (3 caminhos aditivos via Connector API). Esta ADR é complementar — endereça **estrutura de conhecimento canônico** pra suportar migração one-shot dos dados, **não a comunicação runtime**.

Estado da arte: combinação canônica DDD **Bounded Context + Anticorruption Layer** (Eric Evans, 2003) + **Strangler Fig** (Martin Fowler, 2004) + **Brownfield AI** ([artigo abr/2026](https://tianpan.co/blog/2026-04-12-brownfield-ai-integrating-llm-features-into-legacy-codebases)) — Claude Opus 4.7 atua como ACL agent. Zero invenção arquitetural.

## Decisão

Criar **2 pastas top-level novas** em `memory/`:

### `memory/dominios/<sistema-externo>/`

Conhecimento canônico de **sistemas externos não-Laravel** que oimpresso precisa entender (ler dados, traduzir, importar). Cada sistema externo isolado em sua pasta. Estrutura interna espelha a modularidade do **sistema externo** (não a do Laravel oimpresso):

```
memory/dominios/wr-comercial/
├── README.md                    (overview + links)
├── ARQUITETURA.md               (stack Delphi: FireDAC, IBX, TRegistry, etc)
├── UPDATESQL.md                 (formato do .txt + parser spec)
├── REGISTRY-API.md              (HKCU\Software\Rocha estrutura completa)
└── modulos/                     (estrutura modular do sistema EXTERNO)
    ├── financeiro/
    │   ├── README.md
    │   ├── tabelas/CONTAS_BANCARIAS.md      (vocabulário Delphi)
    │   ├── tabelas/FINANCEIRO_BOLETO.md
    │   ├── evolucao.md                       (UPDATE blocks que tocam o domínio)
    │   └── MAPPING.md                        (Delphi → Laravel; aponta pra requisitos/Financeiro/SPEC.md)
    ├── vendas/
    └── ...
```

**Vocabulário do sistema externo é preservado dentro dessa pasta.** O único arquivo que mistura vocabulários é o `MAPPING.md` em cada módulo — fronteira explícita, intencional, equivalente literal a uma **Anticorruption Layer** documentada.

### `memory/clientes-legacy/<alias>.md`

Quirks por cliente em arquivo único cross-cutting (afeta múltiplos módulos do mesmo cliente). Estrutura mínima:

```
memory/clientes-legacy/
├── _index.md                    (matriz: cliente × versão × business_id)
├── rota-livre.md                (versão Delphi 1413, biz_id 4, customizações)
├── display-parana.md
└── ...
```

Cada `<alias>.md` consolida:
- Versão atual do schema Delphi (de `CONFIGURACOES.VALOR WHERE CONFIG='VERSAO_BANCO'`)
- `business_id` correspondente no oimpresso (se já tem)
- Customizações específicas (ex: `format_date` shift, monitor 1280px, regras de PDV peculiares)
- Status de migração (não iniciada, em andamento, smoke ok, completa)

## Justificativa

### Por que pasta top-level nova vs subpasta de `requisitos/`

| Opção | Fitness | Razão |
|---|---|---|
| Tudo em `requisitos/MigracaoLegacy/` | ❌ | Acopla domínio externo a 1 módulo Laravel; viola §5 SoC; força misturar vocabulários |
| `dominios/<sistema>/` top-level | ✅ | DDD Bounded Context literal; sobrevive refatoração de módulos Laravel; escala pra futuras integrações (Bling, Tiny, Asaas API) sem reorganizar |
| Repo separado (submodule) | ❌ | Drástico demais pro tamanho do time (5 devs); complexa o webhook GitHub→MCP |

### Por que `clientes-legacy/` separado de `dominios/`

Cliente é **dimensão cross-cutting** — quirks de ROTA LIVRE afetam financeiro + vendas + estoque ao mesmo tempo. Se ficasse dentro de `dominios/wr-comercial/clientes/`, ficaria amarrado a 1 sistema externo. Se um dia tivermos cliente que migra de **Bling + WR Comercial** ao mesmo tempo, `clientes-legacy/<alias>.md` é o ponto único.

### Por que ADR formal

- Cria **categoria nova top-level** em `memory/` — convenção que vai ser seguida por outros membros do time
- Define template pra futuras integrações externas (Bling, Tiny, Asaas API) — evita reinvenção
- Tira vocabulário do legado do caminho de `requisitos/` Laravel — princípio §5 SoC ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md))
- Migração de auto-mem privada existente ([`reference_delphi_wr_comercial`](../claude/reference_delphi_wr_comercial.md), [`cliente_rotalivre`](../claude/cliente_rotalivre.md)) pra git/MCP — alinha com [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) zero auto-mem privada

### Quando reabrir esta decisão

- Terceira integração externa (Bling, Tiny, ou Asaas API) for documentada e a estrutura `dominios/<sistema>/modulos/` se mostrar inadequada
- Após documentar 3+ módulos Delphi, descobrirmos que a granularidade de `tabelas/<TABELA>.md` é fina demais ou grossa demais
- Cliente Delphi totalmente migrado pro oimpresso e `clientes-legacy/<alias>.md` deixar de ser cross-cutting (vira arquivo histórico)

## Consequências

**Positivas:**
- Vocabulário Delphi (CONTAS_BANCARIAS, VERSAO_BANCO, etc) **não polui** `requisitos/` Laravel
- Conhecimento sobrevive a refatorações dos módulos Laravel — `dominios/wr-comercial/modulos/financeiro/` independe de `Modules/Financeiro/` ser dividido em 3 partes
- Escala pra futuras integrações externas com mesmo template (Bling, Tiny, Asaas)
- ACL **documentada** explicitamente em `MAPPING.md` por módulo — fronteira visível
- Migração de auto-mem privada → git canônico alinha com [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md)
- Cliente cross-cutting em arquivo único reduz duplicação (quirks de ROTA LIVRE não duplicados em 5 docs)

**Negativas / Trade-offs:**
- 2 pastas top-level novas aumentam a árvore raiz `memory/` — overhead cognitivo pra dev novo
- Estrutura `dominios/<sistema>/modulos/<dom>/` requer disciplina (não importar vocabulário Laravel pra dentro; não importar Delphi pra fora)
- Aceitar que `MAPPING.md` é o único arquivo bilíngue — convenção que o time precisa internalizar

**Riscos mitigados:**
- **Vazamento de vocabulário** entre Bounded Contexts → `MAPPING.md` é o único arquivo permitido a misturar; lint/review pode policiar futuramente
- **Auto-mem persistindo conhecimento canônico** → migração explícita prevista nas Fases 1 do plano de migração legacy
- **Pasta `dominios/` virar gaveta** → review_trigger #1 (3ª integração) força revalidar template

## Referências

- [ADR 0061 — Conhecimento canônico em git/MCP, zero auto-mem privada](0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [ADR 0093 — Multi-tenant isolation Tier 0 (`business_id` global scope)](0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição v2 (§5 SoC brutal mãe desta decisão)](0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0113 — Integração runtime Delphi ↔ Laravel ↔ ADs (complementar, não conflita)](0113-integracao-delphi-laravel-ads-3-caminhos.md)
- [ADR 0070 — Jira-style task management (mesmo princípio: sair de markdown ad-hoc)](0070-jira-style-task-management-current-md-removed.md)
- Eric Evans, *Domain-Driven Design* (2003) — Bounded Context, Anticorruption Layer, Context Map
- Martin Fowler, [*StranglerFigApplication*](https://martinfowler.com/bliki/StranglerFigApplication.html) (2004)
- [Brownfield AI — TianPan.co (abr/2026)](https://tianpan.co/blog/2026-04-12-brownfield-ai-integrating-llm-features-into-legacy-codebases) — Strangler Fig + LLM como ACL agent
- POC 2 sessão 2026-05-09 — `scripts/legacy-migration/poc2-firebird-connect.py` validou pressupostos (banco em v1466, 441 tabelas, 2 updates pendentes vs `UpdateSQL.txt` em v1468)
