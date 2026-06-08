---
title: Como analisar a base OfficeImpresso — método canônico
status: live
date: 2026-05-11
audience: time interno + IA-pair
purpose: padronizar metodologia de análise de cliente legacy WR Sistemas pra evitar adivinhação e acelerar migração
update: 2026-05-11 — Wagner apontou que Controllers Delphi têm SQL exato + bridge OImpresso já existente
---

# Como analisar a base OfficeImpresso

> Metodologia canônica em **3 camadas** complementares. **Source-first** (ler Controllers Delphi) é mais preciso que **probing-first** (queries Firebird). Combinar ambos é o caminho dourado.

## TL;DR — Hierarquia de fontes

| Ordem | Fonte | Resposta a | Custo |
|-------|-------|------------|-------|
| **1ª** | **Controllers Delphi** (`D:\Programas\WR Comercial\app\Controller\*.pas`) | Comportamento estrutural — qual SQL, validações, fluxo | 10 min / tela — fonte autoritativa |
| 2ª | **Schema Firebird** (`RDB$RELATIONS`, `RDB$RELATION_FIELDS`) | Quais colunas existem + tipos | 2 min via script |
| 3ª | **Heatmap UI** (queries agregadas em VENDA/FINANCEIRO/EQUIPAMENTO_VEICULO) | Comportamental — o que cliente USA de fato | 5-10 min / cliente via [sells_grade_heatmap.py](../../scripts/sells_grade_heatmap.py) |
| 4ª | **Probes específicos** (CONFIGURACOES_GRID, OIMPRESSO_LOG, etc) | Estado interno do cliente — quais colunas configurou, último sync | 5 min via script |

## 1. Source-first — Controllers Delphi (PRIMÁRIO)

> **Lição aprendida 2026-05-11:** classifiquei Vargas como "gráfica + frota" e Gold como "gráfica genérica" via heatmap. Wagner corrigiu: Vargas é oficina recapagem, Gold é comvis. Probes de dados podem enganar; **Controllers não enganam** porque são a tela que o cliente vê.

### Por que primário

- **Fonte autoritativa** — o SQL que aparece no Controller É o SQL que roda
- **Zero adivinhação** — não preciso inferir "será que cliente usa coluna X?", está no `SQLInit` ou em `GeraGridDBColumn`
- **Validações explícitas** — `Controller.Venda.Definicoes.pas` lista exatamente quais campos são obrigatórios + valores default
- **Cobre 38 clientes igualmente** — mesmo código pra todos clientes do OfficeImpresso

### Como ler

Ver skill detalhada [.claude/skills/officeimpresso-source-analysis/SKILL.md](../../.claude/skills/officeimpresso-source-analysis/SKILL.md).

Resumo: 7 passos / 10-15 min:
1. Localizar `Controller.<Modulo>.pas`
2. Ler imports → cadeia herança
3. Ler `constructor Create` → SQLInit, Tabela, Caption
4. Ler `FormCreateConsulta` → filtros, ordem, agrupamento default
5. Ler `Controller.<Modulo>.Definicoes.pas` → validações + defaults
6. Ler `SQL.<Modulo>.pas` (se existir) → SQL específico
7. Buscar referências cross-cutting → `OImpresso*`, JOINs, hooks

### Descoberta de 2026-05-11

Delphi já tem **bridge pro oimpresso.com**:
- `Controller.OImpresso.pas` — controlador principal
- `SincronizarContatos/Vendas/Financeiro/Produto/Tudo` — métodos POST API
- Tabela `OIMPRESSO` no Firebird armazena estado de sync
- `OIMPRESSO_LOG` registra cada operação

**Significa que migração não precisa ser cutover** — modelo Asaas-like (Delphi continua + cloud novo via sync) é viável.

## 2. Schema Firebird (SECUNDÁRIO — confirma estrutura)

Útil quando Controller não está acessível OU pra confirmar que coluna referenciada existe na versão do banco do cliente específico (varia entre instalações).

```python
# scripts/probe_schema_X.py — exemplo
import firebird.driver as fb
con = fb.connect('192.168.0.55:D:\\DadosClientes\\Vargas\\Dados\\BANCO.FDB',
                  user='SYSDBA', password='masterkey')
cur = con.cursor()

# Listar colunas da tabela VENDA
cur.execute(
    "SELECT TRIM(RDB$FIELD_NAME) FROM RDB$RELATION_FIELDS "
    "WHERE RDB$RELATION_NAME = 'VENDA' ORDER BY RDB$FIELD_POSITION"
)
for row in cur.fetchall():
    print(row[0])

# Listar tabelas relacionadas a VENDA
cur.execute(
    "SELECT TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS "
    "WHERE RDB$VIEW_BLR IS NULL AND RDB$SYSTEM_FLAG = 0 "
    "AND RDB$RELATION_NAME LIKE '%VENDA%'"
)
```

## 3. Heatmap UI (TERCIÁRIO — comportamento real)

Útil pra responder "o que cliente USA de fato" — diferente de "o que o sistema oferece" (Controllers) ou "o que o schema permite" (RDB$).

Script canônico: [scripts/sells_grade_heatmap.py](../../../scripts/sells_grade_heatmap.py).

13 queries agrupadas (Q1..Q9 + descoberta de schema). Output anonimizado em `memory/research/2026-05-sells-grade-heatmap/`.

**Cuidado** — interpretar resultados sem cruzar com Controllers leva a erros (caso Vargas/Gold 2026-05-11).

## 4. Probes específicos (QUARTÁRIO — estado interno)

Pra dúvidas pontuais:

### CONFIGURACOES_GRID (config por cliente)

```python
cur.execute("SELECT * FROM CONFIGURACOES_GRID WHERE ATIVO='S'")
# → lista de configurações de grid que o cliente CRIOU
# → cobre US-SELL-027 schema discovery dinâmico
```

### OIMPRESSO_LOG (estado de sync)

```python
cur.execute("SELECT * FROM OIMPRESSO_LOG ORDER BY CODIGO DESC ROWS 100")
# → últimas 100 operações de sync com oimpresso.com novo
# → se vazia → cliente nunca sincronizou (não tá usando bridge)
```

### USUARIOS / PERFIL

```python
cur.execute("SELECT NOME, EMAIL, NIVEL FROM USUARIO WHERE ATIVO='S'")
# → quantos usuários ativos
# → quais perfis (Admin, Vendedor, Operador, etc)
# → tamanho operação real
```

## Padrão de mapping Delphi → Laravel

Pra cada feature migrada, documentar em `memory/research/clientes-legacy-officeimpresso/<cliente>/04-mapping-<feature>.md`:

```markdown
## Tela: Lista de Vendas

### Fonte Delphi
- Controller: `Controller.Venda.pas`
- SQL base: `SELECT V.*, P.RAZAOSOCIAL FROM VENDA V LEFT JOIN PESSOAS P ON ...`
- Validações chave: RAZAOSOCIAL obrigatório, ATIVO default 'S'
- Filtros default: Retirar filtros = `(V.STATUS='ATIVO' AND V.OPERACAO='EM VENDA')`

### Destino Laravel
- Controller: `app/Http/Controllers/SellController@index` → `Modules/Sells/Pages/Index.tsx`
- Query base: `Transaction::with('contact')->where('business_id', $business)->where('type', 'sell')`
- Validações chave: `name` obrigatório (FormRequest)
- Filtros default: `status = 'active'`

### Mapping de campos
| Delphi | Laravel |
|--------|---------|
| `VENDA.CODIGO` | `transactions.id` |
| `VENDA.DT_EMISSAO` | `transactions.transaction_date` |
| `VENDA.RAZAOSOCIAL` | `contacts.name` (via JOIN) |
| `VENDA.STATUS` | `transactions.status` |
| ... | ... |

### Gaps / decisões
- Delphi tem `DT_PROMETIDO` opcional → Laravel adicionar `due_date` nullable em `transactions`
- Delphi tem `CODFINANCEIRO_GRUPO` para agrupar → Laravel `transaction_group_id` em `transactions`
```

## Erros recorrentes a evitar

| Erro | Como aconteceu | Mitigação |
|------|----------------|-----------|
| Classificar vertical errado via heatmap (Vargas "frota" → na verdade recapagem) | Vi `EQUIPAMENTO_VEICULO` com PLACA2/CHASSI2 e inferi multi-vertical | Cruzar com **Wagner** antes de gravar perfil — humano sabe negócio real do cliente |
| Buscar PLACA na tabela errada (MENSALIDADE_FINANCEIRO) | Q7 original tinha lookup table errado | **Ler `EQUIPAMENTO_VEICULO` cols** primeiro via `RDB$RELATION_FIELDS` |
| Inferir status só de `VENDA.SITUACAO` | Status produção real vive em 3 tabelas (inline + lookup + FSM) | Q3 v2 lê todas 3 fontes |
| Hardcodar colunas no Layout React | Schema Delphi varia entre clientes | US-SELL-027 schema discovery dinâmico |

## Workflow recomendado por análise

```
Wagner: "Vou abordar o cliente XYZ para migração"

1. (Source) Wagner aponta tipo de negócio + qual vertical OficinaAuto/ComVis/Vestuário
   → 1 min
   
2. (Source) Ler Controllers da feature crítica
   → 15 min
   
3. (Heatmap) Rodar sells_grade_heatmap.py no banco do cliente
   → 5 min
   
4. (Probe) Rodar probes específicos (OIMPRESSO_LOG, CONFIGURACOES_GRID, USUARIOS)
   → 5 min
   
5. (Cross-check) Cruzar 3 fontes — bater Controllers (esperado) vs Heatmap (real)
   → 10 min
   
6. (Doc) Criar/atualizar perfil em memory/research/clientes-legacy-officeimpresso/NN-<cliente>/
   → 15 min
   
TOTAL: ~50 min / cliente — análise robusta + reproduzível
```

## Implicação Modules/Officeimpresso

O módulo `Modules/Officeimpresso` (Laravel) existe e gerencia licenças desktop. **NÃO é onde análise legacy mora** — análise mora em `memory/research/clientes-legacy-officeimpresso/`. Mas Modules/Officeimpresso PODE ganhar features futuras:

- API endpoint pra Delphi enviar telemetria de uso → alimenta heatmap automaticamente
- Dashboard que mostra "saúde de cada cliente OfficeImpresso" → consome perfis canônicos
- Wizard de migração → guia cliente passo-a-passo

Mas isso é trabalho de produto, não de análise. Por ora, análise = arquivos em memory/research/.

## Refs

- [README.md](README.md) — estrutura geral da pasta
- [_LGPD.md](_LGPD.md) — proteção de dados
- Skill [officeimpresso-source-analysis](../../../.claude/skills/officeimpresso-source-analysis/SKILL.md) — leitura de Controllers Delphi
- Skill [officeimpresso-financial-snapshot](../../../.claude/skills/officeimpresso-financial-snapshot/SKILL.md) — análise financeira automatizada
- Script [sells_grade_heatmap.py](../../../scripts/sells_grade_heatmap.py) — extrator UI heatmap
- [HEATMAP-CONSOLIDADO.md](../2026-05-sells-grade-heatmap/HEATMAP-CONSOLIDADO.md) — exemplo de output consolidado
- [Modules/Officeimpresso](../../../Modules/Officeimpresso/) — módulo Laravel de licenças (relação tangencial)
