# Domínio externo — Delphi WR Comercial

Conhecimento canônico do **legacy Delphi WR Comercial** que oimpresso precisa entender pra migrar dados dos 50 clientes Firebird → Laravel multi-tenant. Pasta criada por [ADR 0118](../../decisions/0118-segregacao-dominios-externos-clientes-legacy.md) — segregação Bounded Context (Eric Evans, *DDD* 2003).

> ⚠️ **Vocabulário Delphi** preservado dentro desta pasta — `CONTAS_BANCARIAS`, `FINANCEIRO_BOLETO`, `VERSAO_BANCO` etc. **Vocabulário Laravel oimpresso fica em `memory/requisitos/<Mod>/`** e nunca vaza pra cá. Único arquivo que mistura é `MAPPING.md` em cada módulo — é a Anticorruption Layer documentada.

## Identidade do sistema

- **Nome interno:** WR Comercial / Office Comercial
- **Stack:** Delphi RAD Studio (versão recente; v13 disponível mas projeto em versão anterior)
- **Banco:** Firebird (1 .FDB por cliente)
- **Distribuição:** binário compilado distribuído pra ~50 máquinas clientes (gráficas, oficinas, etc)
- **Origem:** repo SVN em `http://servidor-crm:8777/svn/Programas`, working copy em `D:/Programas/WR Comercial/`
- **Status:** legado em produção; **não é recompilado** ([ADR 0113](../../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md))

## Arquivos nesta pasta

| Arquivo | Conteúdo |
|---|---|
| [`ARQUITETURA.md`](ARQUITETURA.md) | Stack interno, fluxo login→processa-dados-cliente, como inspecionar |
| [`UPDATESQL.md`](UPDATESQL.md) | Formato do `UpdateSQL.txt` (1452 blocos, v6→v1999), parser spec, estado |
| [`REGISTRY-API.md`](REGISTRY-API.md) | `HKCU\Software\Rocha\Office Comercial\Banco\*` — alias→path→senha |
| `modulos/<dom>/` | Estrutura por módulo Delphi (financeiro, vendas, estoque, ...). Criada incrementalmente conforme migração avança (Fase 3 do plano) |

## Módulos Delphi conhecidos (parcial — completar conforme avançamos)

Inferidos das subpastas `D:/Programas/WR Comercial/app/` + tabelas observadas + registry editor:

- **agenda** — agenda+kanban (`AGENDA*` tables)
- **financeiro** — contas bancárias, boletos, cheques, caixa (`FINANCEIRO*`, `CONTAS_BANCARIAS`, `BANCO*`)
- **vendas** — vendas, orçamentos, pedidos, PDV (`VENDA*`)
- **producao** — orçamentos gráficos, kanban produção
- **estoque** — produtos, lotes, movimentos
- **nfe** — NF-e entrada/saída (`NF_*`)
- **cadastros** — pessoas, fornecedores, funcionários (`PESSOAS*`, `FORNECEDOR`)
- **wr_controle** — metadados de configuração (telas, KPIs, filtros)

> Módulos NÃO batem 1:1 com `Modules/` Laravel oimpresso. Mapeamento N:M, declarado em cada `MAPPING.md`. Ex: Delphi **producao** → Laravel `Modules/Project/` + `Modules/Repair/`.

## Como ler / consultar

- **Schema Firebird** ao vivo: `python scripts/legacy-migration/poc2-firebird-connect.py --alias <X>`
- **Histórico de schema:** [`UPDATESQL.md`](UPDATESQL.md) + `scripts/legacy-migration/output/updatesql-parsed.json` (gerado por POC 1)
- **Código Delphi:** `D:/Programas/WR Comercial/` (SVN; `wc.db` SQLite indexa metadados)
- **Estado por cliente:** [`memory/clientes-legacy/<alias>.md`](../../clientes-legacy/) (versão, biz_id, quirks)

## Onde isso encaixa

- Migração one-shot (extrair dados Firebird→MySQL): plano em conversa 2026-05-09; scripts em [`scripts/legacy-migration/`](../../../scripts/legacy-migration/)
- Integração runtime (Delphi cliente desktop chama Laravel via Connector API): [ADR 0113](../../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md) — separado e complementar a esta migração
