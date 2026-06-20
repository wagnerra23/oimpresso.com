---
name: officeimpresso-source-analysis
description: ATIVAR quando precisar entender comportamento real de uma tela/feature do OfficeImpresso legacy (Delphi WR Comercial) — em vez de inferir via probes no Firebird, **lê código fonte Delphi em D:\Programas\WR Comercial\app\**. Resolve perguntas como "qual SQL exato a tela Lista de Vendas usa?", "quais campos validação aplica?", "como Delphi se sincroniza com oimpresso.com?". Substitui probing exploratório do Firebird por leitura de Controllers .pas — fonte autoritativa. Pré-requisito pra migração precisa (mapping Delphi→Laravel). Complementa skill `officeimpresso-financial-snapshot` (snapshots de dados) com fonte estrutural.
type: tier-b-auto-trigger
status: draft
date: 2026-05-11
tier: B
---

# officeimpresso-source-analysis — análise via código-fonte Delphi

> Em vez de **adivinhar** via probes/queries Firebird, **lê os Controllers Delphi .pas** que rodam no cliente. Eles têm SQL exato, validações, mapping campos, lógica de negócio. Fonte autoritativa zero-incerteza.

## Quando usar

✅ ATIVAR:
- Pergunta "qual SQL exato a tela X usa?" — ler `Controller.X.pas` + `SQL.X.pas`
- Pergunta "quais validações Delphi aplica em VENDA?" — ler `Controller.Venda.Definicoes.pas`
- Pergunta "como Delphi sincroniza com oimpresso.com?" — ler `Controller.OImpresso.pas`
- Pergunta "quais colunas o grid mostra por default?" — ler `Classes.Consulta.pas` + `Controller.Configuracoes_Grid.pas`
- Migração de feature (Delphi → Laravel) — usar fonte como referência canônica

❌ NÃO ATIVAR:
- Análise quantitativa de dados (uso real) — usar skill `officeimpresso-financial-snapshot` ou `sells_grade_heatmap.py`
- Cliente específico — Controllers são genéricos (mesmo código pra todos 38 clientes); skill financial-snapshot pega dados reais

## Pré-requisitos

1. Acesso à máquina do Wagner — diretório `D:\Programas\WR Comercial\app\` (não fica em LAN/repositório)
2. Conhecer estrutura básica Pascal (Delphi) — `unit`/`interface`/`implementation`, `class`/`procedure`/`function`, `inherited` (herança)
3. Familiaridade com Object Pascal NÃO é obrigatória — sintaxe é legível

## Mapa da árvore Delphi

```
D:\Programas\WR Comercial\app\
├── Controller\          ← Controllers (tela por tela)
│   ├── Controller.Mestre.pas        ← classe BASE (3.444 LOC) — TODOS herdam dela
│   ├── Controller.Venda.pas         ← Lista de Vendas (4.010 LOC)
│   ├── Controller.Venda.Definicoes.pas    ← validações + valores default da VENDA
│   ├── Controller.Venda.Orcamento.pas     ← variação: tela Orçamento
│   ├── Controller.Venda.NotaFiscal.pas    ← variação: tela NF
│   ├── Controller.Venda.PDV.pas           ← variação: PDV
│   ├── Controller.OImpresso.pas           ← BRIDGE pro oimpresso.com novo!
│   ├── Controller.OImpresso_Configuracao.pas
│   ├── Controller.OImpresso_Log.pas
│   ├── Controller.Pessoas.OImpresso.pas   ← sync de Pessoas Delphi → oimpresso
│   ├── Controller.Configuracoes_Grid.pas  ← META: configuração de COLUNAS de grid (CONFIGURACOES_GRID Firebird)
│   └── Controller.<Modulo>.pas            ← demais (Compra, Boleto, Financeiro, ...)
│
├── SQL\                 ← SQL específico por módulo (statements custom)
│   ├── SQL.Venda.pas
│   ├── SQL.WR_App.pas       ← WR_* = framework dinâmico
│   ├── SQL.WR_Config.pas
│   ├── SQL.WR_Componente.pas
│   ├── SQL.WR_Filtro.pas
│   └── ...
│
├── Classes\             ← Classes auxiliares
│   ├── Classes.Consulta.pas        ← TConsulta — UI de grid/filtros
│   ├── Classe.Mestre.OImpresso.pas ← BRIDGE — métodos de sync
│   ├── Classes.APP.pas             ← TWR_APP — registro do app
│   └── Classes.Kanban.pas          ← suporte Kanban (Wagner tem!)
│
├── Models\              ← Models (mapeamento ORM)
├── Validacao\           ← Sistema de validação dinâmica
├── Fiscal\              ← NFe/NFCe/NFSe
├── NFSe\
├── Services\            ← Lógica de negócio
└── Utils\
```

## Fluxo de leitura (10 min/tela)

### Passo 1 — Identificar Controller relevante

Pergunta:  "qual SQL/comportamento da tela `Lista de Vendas`?" 

Procurar nome do Controller principal:
```bash
ls "D:/Programas/WR Comercial/app/Controller/" | grep -i venda
```

Resultado típico: `Controller.Venda.pas` (principal) + variações (`Venda.Orcamento.pas`, etc).

### Passo 2 — Identificar cadeia de herança

Todo Controller herda de `TControllerMestre` (base 3.444 LOC). Ler imports do arquivo:
```pascal
uses Controller, Controller.Mestre, ...
```

Cadeia típica: `TControllerVenda` → `TControllerMestre` → `TObject`.

### Passo 3 — Ler `constructor Create` (50 linhas top)

Tem o essencial:
- `Caption := 'Vendas'` — nome da tela
- `Tabela := 'VENDA'` — tabela principal
- `SQLInit.Text := 'SELECT V.* FROM VENDA V'` — **SQL base da consulta**

### Passo 4 — Ler `FormCreateConsulta` (configuração do grid)

Tem:
- Filtros default (`GetFiltroProNome('Retirar filtros').SQL := ...`)
- Ordenação default (`SortOrder := soDescending`)
- Agrupamento default (`AgrupadorAtivo := GetAgrupamentoProNome('Tabela')`)

### Passo 5 — Ler `Controller.<X>.Definicoes.pas` (validações + defaults)

Arquivo gerado automaticamente. Tem:
- Valores default ao inserir (`AdicionarValorPadrao('ATIVO', 'S')`)
- Regras de validação (`AdicionarRegra('RAZAOSOCIAL', 'obrigatorio', ...)`)
- Contextos condicionais (`DefinirContexto('CTX_EMPRESA_NFE', ...)` + `SetCondicaoContexto('EMITE_NFE=S')`)

### Passo 6 — Ler `SQL.<X>.pas` (se existir — SQL específico)

Statements custom (ex: `UPDATE VENDA SET CODVENDA_PRINCIPAL`) que não estão no fluxo principal.

### Passo 7 — Buscar referências cross-cutting

- "Como essa tela aparece no menu?" — buscar `RegisterController(PathXXX, ...)` no rodapé do `.pas`
- "Como ela se conecta com produtos?" — procurar JOIN com `VENDA_PRODUTO` nos SQL
- "Tem hook pra oimpresso.com?" — procurar `OImpresso*` no controller

## TControllerMestre — o que herdar dele dá

Toda tela tem (via herança):

| Field/Method | Função |
|--------------|--------|
| `SQLInit` (TStringList) | SQL base — `select ... from <Tabela>` |
| `SQLWhere` | WHERE adicional (filtros aplicados) |
| `SQLOrderBy` | ORDER BY default |
| `ConsultaFiltros` | TConsultaFiltros — lista de filtros nomeados |
| `ConsultaGroups` | TConsultaGroups — agrupamentos |
| `GridConsulta` | cxGridDBTableView — o grid real |
| `GridDBColumnList` | TList<TWR_GridDBColumn> — colunas configuradas |
| `PermissaoList` | TList<TWR_Componente> — controle de permissão por campo |
| `ConfigList` | TList<TWR_Config> — config por usuário |
| `Validacao` | TValidationManager — sistema de validação |
| `OImpresso` | TMestreOImpresso — **bridge pro oimpresso.com novo** |
| `Kanban` | TKanbanManager — suporte Kanban |
| `OImpressoPrepareFieldsForSet(query, json)` | transforma dados Delphi → JSON pra POST API |
| `OImpressoPrepareFieldsForGet(query, json)` | transforma JSON → Delphi |
| `GeraFDQueryOImpressoPost(json)` | gera FDQuery pra POST |
| `GeraFDQueryOImpressoGet(json)` | gera FDQuery pra GET |
| `Insert` / `Edit` / `Cancel` / `Post` / `Delete` | CRUD virtual (override pra customizar) |

## Bridge Delphi ↔ oimpresso.com (descoberta 2026-05-11)

Wagner já tem **`Controller.OImpresso.pas`** que faz:

| Método | O que faz |
|--------|-----------|
| `LoginDaAPI(Sender)` | autentica na API REST do oimpresso.com via `Controller.Pessoas.OImpresso` |
| `SincronizarContatos(Sender)` | POST /api/oimpresso/contatos pra cada PESSOAS modificado |
| `SincronizarVendas(Sender)` | POST /api/oimpresso/vendas |
| `SincronizarFinanceiro(Sender)` | POST /api/oimpresso/financeiro |
| `SincronizarProduto(Sender)` | POST /api/oimpresso/produto |
| `SincronizarTudo(Sender)` | dispara todos sequencial |

E tem tabelas no Firebird pra controle:
- `OIMPRESSO` — registros sincronizados (master)
- `OIMPRESSO_LOG` — log de cada operação (sucesso/erro/timestamp)
- `OIMPRESSO_CONFIGURACAO` — config por cliente (endpoint, credenciais)

**Implicação estratégica:** migração não precisa ser cutover Big Bang. Cliente pode:
1. Continuar usando Delphi pro operacional (já familiar)
2. Habilitar sync OImpresso
3. Acessar oimpresso.com pra features cloud/IA/relatórios (Jana, dashboards)
4. Migrar gradualmente em ritmo próprio

Modelo "Asaas-like" — ferramenta nova como **complemento cloud**, não substituição imediata.

## Como descobrir SQL real de uma tela específica

Exemplo: "Qual SQL a Lista de Vendas Delphi roda quando abre?"

```bash
# 1. Localizar Controller
ls "D:/Programas/WR Comercial/app/Controller/" | grep -i venda
# → Controller.Venda.pas (principal)

# 2. Ler SQLInit no constructor
head -100 "D:/Programas/WR Comercial/app/Controller/Controller.Venda.pas" | grep -A2 "SQLInit"

# 3. Ler filtros default em FormCreateConsulta
grep -n "FormCreateConsulta\|GetFiltroProNome\|GetAgrupamentoProNome" \
  "D:/Programas/WR Comercial/app/Controller/Controller.Venda.pas" | head -30

# 4. Ler colunas (TWR_GridDBColumn) via consulta a CONFIGURACOES_GRID no Firebird
# → tabela CONFIGURACOES_GRID + IUI definido em GeraGridDBColumn
```

## Pra migração — workflow recomendado

| Etapa | Ação |
|-------|------|
| 1 | Wagner aponta a tela: "preciso migrar Lista de Vendas" |
| 2 | Ler `Controller.Venda.pas` + `.Definicoes.pas` + `SQL.Venda.pas` (15 min) |
| 3 | Documentar em `memory/research/clientes-legacy-officeimpresso/<cliente>/04-mapping-tela-<X>.md`: |
|   | - SQL base inicial Delphi |
|   | - Filtros default |
|   | - Validações |
|   | - Mapping campos Delphi (`VENDA.RAZAOSOCIAL`) → Laravel (`transactions.customer_name`) |
| 4 | Cruzar com heatmap UI ([sells_grade_heatmap.py](../../../scripts/sells_grade_heatmap.py)) — confirma o que cliente usa de fato |
| 5 | Implementar Laravel via PR pequeno preservando regras Delphi |
| 6 | Validar paridade com Pest |

## Restrições

- ❌ NUNCA modificar `D:\Programas\WR Comercial\app\` — leitura READ-ONLY
- ❌ NÃO commitar nenhum `.pas` do Delphi no repositório oimpresso.com (código de Wagner, não FOSS — vive separado)
- ✅ Citar trechos pequenos no contexto de docs/ADRs (fair use pra documentar comportamento)
- ✅ Salvar mapping/análise em `memory/research/clientes-legacy-officeimpresso/<cliente>/`

## ROI

- **Antes (probes Firebird):** "Vou rodar 10 queries e adivinhar o que cliente usa" — 30 min, sinais indiretos, taxa de erro alta (como aconteceu com Vargas/Gold em 2026-05-11)
- **Agora (source-analysis):** "Vou ler o Controller que **gera** a tela" — 10 min, fonte autoritativa, zero adivinhação

Combinar ambos é o caminho dourado: code-source dá o estrutural, heatmap dá o comportamental real do cliente.

## Refs

- [memory/research/clientes-legacy-officeimpresso/_COMO-ANALISAR.md](../../../memory/research/clientes-legacy-officeimpresso/_COMO-ANALISAR.md) — metodologia detalhada
- Skill irmã [officeimpresso-financial-snapshot](../officeimpresso-financial-snapshot/SKILL.md) — análise quantitativa de dados (complementar)
- [Modules/Officeimpresso](../../../Modules/Officeimpresso/) — módulo Laravel que recebe sync via API
- [ADR 0021 Officeimpresso contrato API Delphi](../../../memory/decisions/0021-officeimpresso-contrato-api-delphi.md) — fluxo API atual

## Histórico

- 2026-05-11: skill criada após Wagner apontar que Controllers Delphi têm SQL exato + bridge OImpresso já existente. Substitui guessing por reading.
