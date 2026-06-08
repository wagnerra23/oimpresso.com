---
slug: legacy-delphi-index
title: "Hub Canônico Delphi — WR Comercial legacy + migração oimpresso"
type: knowledge-index
authority: canonical
lifecycle: ativo
owner: felipe
last_updated: 2026-05-15
related:
  - 0053-mcp-server-governanca-como-produto
  - 0113-integracao-delphi-laravel-ads-3-caminhos
  - 0118-segregacao-dominios-externos-clientes-legacy
  - 0121-oimpresso-modular-especializado-por-vertical
pii: false
---

# Hub Canônico Delphi — WR Comercial → oimpresso

> **Propósito:** ponto único de entrada pra entender o sistema Delphi legacy (WR Comercial em `D:\Programas\WR Comercial\app\`) e mapear migração pro oimpresso Laravel. **Felipe é owner** — todo conhecimento descoberto vai aqui via PR.

## Contexto

WR Sistemas opera há 26 anos no setor gráfico, majoritariamente Delphi (Object Pascal, RAD Studio versão pré-v13) com banco **Firebird 3.0.12** (1 `.FDB` por cliente, port 3050, charset WIN1252). ~50 clientes ativos legacy (lista em [`memory/clientes-legacy/_index.md`](../clientes-legacy/_index.md)). oimpresso (Laravel 13.6 + PHP 8.4) é a próxima geração — migração gradual, **sem impacto físico no Delphi rodando** ([ADR 0113](../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md): qualquer integração nova é aditiva; Delphi não é recompilado por padrão).

ROTA LIVRE (biz=4 Larissa Termas do Gravatal/SC) é cliente piloto **já no oimpresso novo** — vestuário, não gráfica. 99% volume vendas oimpresso. Diferente dos clientes OfficeImpresso legacy gráfica que ainda rodam Delphi.

Modules verticais especializados ([ADR 0121](../decisions/0121-oimpresso-modular-especializado-por-vertical.md)):

- ✅ **Vestuario** — ROTA LIVRE em prod (CNAE 4781-4/00)
- 🟡 **ComunicacaoVisual** — em construção; 6 saudáveis OfficeImpresso candidatos (Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart) — CNAE 1813-0/01
- ⏸️ **OficinaAuto** — Martinho candidato (CNAE 4520-0/01)
- 🔒 Outros — backlog ADR feature-wish

## Skills relacionadas

- `officeimpresso-source-analysis` ([SKILL](../../.claude/skills/officeimpresso-source-analysis/SKILL.md)) — lê código Delphi `.pas` (fonte autoritativa pra entender comportamento real, SQL exato, validações)
- `officeimpresso-financial-snapshot` ([SKILL](../../.claude/skills/officeimpresso-financial-snapshot/SKILL.md)) — extrai receita/despesa/inadimplência via Python `firebird-driver`, sempre read-only

## Estrutura desta pasta

| Doc | Função |
|---|---|
| `_INDEX.md` (este) | mapa + onboarding pra Felipe |
| `SCHEMA-FIREBIRD.md` | schema canônico tabelas Firebird WR Comercial (extraído via probe + UpdateSQL) |
| `MAPEAMENTO-DELPHI-LARAVEL.md` | tabela Delphi `<Tabela\|Form\|Proc>` → oimpresso `<Module\|Controller\|Service>` |
| `PEGADINHAS.md` | gotchas catalogados durante migração |
| (futuro) `descobertas/YYYY-MM-DD-<area>.md` | descobertas pontuais Felipe vai apendar |

## Docs canon relacionados (NÃO duplicar — referência aqui)

| Doc | Onde mora | Função |
|---|---|---|
| `OFFICEIMPRESSO-FIREBIRD-SCHEMA.md` | [memory/requisitos/Officeimpresso/](../requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md) | schema técnico das 441 tabelas + 9 críticas + queries-template |
| `ARQUITETURA.md` Delphi | [memory/dominios/wr-comercial/](../dominios/wr-comercial/ARQUITETURA.md) | stack interno, fluxo login → processa-dados-cliente, FireDAC, TOImpressoAPI |
| `UPDATESQL.md` | [memory/dominios/wr-comercial/](../dominios/wr-comercial/UPDATESQL.md) | formato dos blocos UpdateSQL.txt v6→v1999 |
| `REGISTRY-API.md` | [memory/dominios/wr-comercial/](../dominios/wr-comercial/REGISTRY-API.md) | `HKCU\Software\Rocha\Office Comercial\Banco\*` — aliases dos ~50 clientes |
| Schema reconstruído por módulo | [memory/dominios/wr-comercial/modulos/](../dominios/wr-comercial/modulos/_summary.md) | 15 módulos Delphi × 393 tabelas vivas v1468 |
| `Modules/Officeimpresso/` | [Modules/Officeimpresso](../../Modules/Officeimpresso/) | módulo Laravel **bridge** (recebe sync Delphi via Connector API) + telas de gestão de licenças |
| `officeimpresso-spec.md` | [memory/](../officeimpresso-spec.md) | spec do módulo Laravel bridge (gestão Licenca_Computador + LicencaLog append-only) |
| Clientes legacy individuais | [memory/clientes-legacy/](../clientes-legacy/_index.md) | 50 entradas no registry × versão Delphi × biz_id × quirks |

## Como descobrir e registrar (workflow Felipe)

Cada vez que você (Felipe) descobrir algo lendo `.pas`, schema Firebird ou comportamento de tela que vale documentar:

1. Criar arquivo `memory/legacy-delphi/descobertas/2026-MM-DD-<area>.md`
2. Template mínimo:
   ```markdown
   # Descoberta — <área>

   **Data:** 2026-MM-DD
   **Trigger:** (porque investiguei isso)
   **Fonte Delphi:** Controller.X.pas:LL-LL (caminho relativo a D:\Programas\WR Comercial\app\)
   **Tabelas Firebird envolvidas:** TABELA_A, TABELA_B
   **Comportamento descoberto:** ...
   **Impacto migração:** (qual Module Laravel, esforço S/M/L/XL, prioridade)
   **PR/Tasks relacionados:** PR #NNN, US-OFFI-NNN
   ```
3. PR linkando ao hub `_INDEX.md` (apêndice da tabela "Descobertas recentes" abaixo se valer destaque)
4. Wagner aprova merge

**Princípio:** descoberta solo na sua cabeça = dívida; descoberta em git = ativo (Constituição v2 §1 *Context as a product* + §7 *Transparência*).

## Onde fica o que (canônico)

| Você precisa de... | Vai em... |
|---|---|
| Tabela Firebird X (estrutura) | [`SCHEMA-FIREBIRD.md`](SCHEMA-FIREBIRD.md) ou probe via skill `officeimpresso-financial-snapshot` |
| Tabela Firebird X (DDL detalhada por módulo) | [`memory/dominios/wr-comercial/modulos/<dom>/tabelas/<X>.md`](../dominios/wr-comercial/modulos/) |
| Form Delphi Y → tela Laravel | [`MAPEAMENTO-DELPHI-LARAVEL.md`](MAPEAMENTO-DELPHI-LARAVEL.md) |
| SQL exato que tela Y roda | Skill `officeimpresso-source-analysis` (lê `Controller.Y.pas`) |
| Bug recorrente migração | [`PEGADINHAS.md`](PEGADINHAS.md) |
| Receita cliente legacy específico | NÃO aqui (PII) — Vaultwarden ou query ad-hoc com `--anonimo` via skill `officeimpresso-financial-snapshot` |
| Decisão arquitetural sobre migração | ADR nova em [`memory/decisions/`](../decisions/) |
| Contrato API runtime Delphi↔Laravel | [ADR 0021](../decisions/0021-officeimpresso-contrato-api-delphi.md) + [ADR 0113](../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md) |

## Convenções de descoberta

- ✅ **Anonimizar PII** — cliente CNPJ/razão social → `[ANONIMO-N]` ou `Cliente_ABC123` (sha1 6 chars). Pode citar nomes-canon já em `memory/why-oimpresso.md` (ROTA LIVRE, Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart).
- ✅ **Linkar fonte Delphi** — `app/Controller/Controller.Venda.pas:123-145` (caminho relativo, sem expor copyright WR)
- ✅ **Linkar tabela Firebird** — `VENDA`, `VENDA_PRODUTO`, `FINANCEIRO`
- ✅ **Apontar alvo Laravel** — `Modules/Sells/Http/Controllers/SellPosController.php` ou candidato a criar
- ✅ **Esforço estimado** — S (≤1d), M (1-3d), L (1-2 semanas), XL (sprint+)
- ❌ **NÃO copiar SQL com dados reais** (snippets de schema ok, dados não)
- ❌ **NÃO subir arquivos `.pas` originais** ao repositório oimpresso (copyright WR Sistemas; máquina Wagner é READ-ONLY local)
- ❌ **NÃO modificar** `D:\Programas\WR Comercial\app\` — leitura apenas

## Estado da migração — visão macro

- **Modules/Officeimpresso** (Laravel) **JÁ EXISTE** — não é migração de schema; é módulo **bridge** que:
  - Recebe sync do Delphi via `POST /connector/api/processa-dados-cliente` (contrato imutável ADR 0021)
  - Gerencia gestão de licenças por máquina (`Licenca_Computador` + `LicencaLog` append-only)
  - Audita acesso/heartbeat dos desktops Delphi rodando em campo
- **Migração de dados** Firebird → MySQL é separado (one-shot por cliente) — scripts em [`scripts/legacy-migration/`](../../scripts/legacy-migration/)
- **3 caminhos de integração** Delphi↔Laravel ([ADR 0113](../decisions/0113-integracao-delphi-laravel-ads-3-caminhos.md)) — sempre aditivo

## Descobertas recentes (apêndice — Felipe atualiza)

| Data | Slug | Área | Insight resumo |
|---|---|---|---|
| _(vazio — primeira descoberta vai aqui)_ | | | |

## Acesso técnico

- **Banco Wagner (smoke):** `192.168.0.55:Banco` (Firebird 3.0.12, port 3050), SYSDBA/masterkey
- **Banco cliente:** `servidor-crm:D:\DadosClientes\<NomeCliente>\Dados\BANCO.FDB`
- **Cliente Python:** `pip install firebird-driver` + `fb.connect('192.168.0.55:Banco', user='SYSDBA', password='masterkey')`
- **Restrição:** SELECT-only, sempre. Nunca INSERT/UPDATE/DELETE em banco produção cliente.
