# Plano canônico — Agentes especializados por entidade Delphi · larga escala

> **Calibrado em:** 2026-05-13 pós-Martinho biz=164 LIVE (12m: 1.550 contacts + 5.006 transactions em prod Hostinger)
> **Fonte estudada:** `D:\Programas\WR Comercial\app\Controller\*.pas` (100+ controllers · ~36k linhas) + `Resources\UpdateSQL.txt` (36.272 linhas histórico schema)
> **Refs:** PR #803 (merged) — 5 commits · pipeline 4 fases provado prod

---

## 1. Universo Delphi mapeado (estado-da-arte)

### 1.1 Arquitetura Controllers (Delphi)

| Camada | Arquivos | Função |
|---|---|---|
| **Controllers consulta** | `Controller.<Entidade>.pas` (~80 arquivos) | Cada herda `TController` base · setam `Tabela` + `SQLInit.Text` (read-only seed) |
| **Controllers REST API** | `Controller_<Entidade>.pas` (4 arquivos: `_Venda`, `_Producao`) | Horse REST · SQL inline com JOINs reais usados em produção |
| **Schema migrations** | `Resources/UpdateSQL.txt` | 36.272 linhas · `UPDATE N` blocks · histórico evolução v6 → v1474+ |

### 1.2 Padrão SQL canônico Delphi (descoberta crítica)

**SQLs reais sempre usam JOINs estilo `Controller_Venda.pas`:**

```sql
-- Venda + linhas + produto + categoria (canônica venda completa)
SELECT
  vp.total_relatorio AS total,        -- ← TOTAL REAL está em VENDA_PRODUTO, não VENDA
  v.dt_emissao,
  c.descricao AS category
FROM venda_produto vp
LEFT JOIN venda v ON v.codigo = vp.codvenda
LEFT JOIN produto p ON p.codigo = vp.codproduto
LEFT JOIN produto_categoria c ON p.codproduto_categoria = c.codigo
WHERE v.dt_emissao BETWEEN ? AND ?
```

```sql
-- Equipamento veículo é HERANÇA de EQUIPAMENTO (mesmo CODIGO)
SELECT E.*, EV.PLACA
FROM EQUIPAMENTO E
LEFT JOIN EQUIPAMENTO_VEICULO EV ON EV.CODIGO = E.CODIGO
```

**Implicação:** `import-vendas.py` atual está **incompleto** — só importa header `VENDA`, falta linhas `VENDA_PRODUTO`. Sells Grade Avançada e Sells Lista perdem detalhe de produto.

### 1.3 Convenção FK universal

Confirmado via `memory/research/clientes-legacy-officeimpresso/_MAPPING/relacionamentos-fk-firebird.sql` (76 FK constraints):

> `COD<TABELA>` é FK pra `<TABELA>.CODIGO` (regra cega · zero exceções declaradas)

Exceções DOCUMENTADAS na convenção:
- `VENDA.PLACA` (INT) → `EQUIPAMENTO_VEICULO.CODIGO` (campo é "PLACA" mas valor é FK)
- `CLIENTES` table existe v1404 mas ÓRFÃ (não populada via VENDA.CODCLIENTE_SITE) — Martinho usa pattern INLINE em `VENDA.RAZAOSOCIAL + RESPONSAVEL_*`

### 1.4 Versões UpdateSQL.txt (drift histórico)

Vai de `UPDATE 6` até **last update** (v1474+). UpdateSQL.txt é a fonte canônica de **drift entre clientes**:
- Cliente v1404 (Martinho) — aplicou só até update 1404
- Cliente v1474 (Zoom) — aplicou todos até 1474
- 70 updates de diferença = 65+ tabelas extra (NF-e moderna, fiscal, FSM `PROCESSOS_*`)

---

## 2. Entidades core do oimpresso (mapeadas pra Laravel)

| # | Entidade Delphi | Tabela Firebird | Volume típico | Tabela Laravel | Importer atual | Status |
|---|---|---|---:|---|---|---|
| 1 | **Empresa própria** | `EMPRESA` (113 cols) | 1-10 | `contacts` (type=both) + `business.tax_number_1` | ✅ `import-empresas.py` | done WR2 |
| 2 | **Contatos (clientes)** | `CLIENTES` v1474 OU `VENDA.RAZAOSOCIAL+RESPONSAVEL_*` v1404 | 200-9k | `contacts` (type=customer) | ✅ `import-contacts-from-venda.py` (INLINE pattern) | done Martinho 12m |
| 3 | **Veículos** | `EQUIPAMENTO_VEICULO` herda `EQUIPAMENTO` | 0-1k | `vehicles` (Modules/OficinaAuto) | ✅ `import-vehicles.py` | done Martinho |
| 4 | **Vendas (header)** | `VENDA` (312-400 cols) | 5k-86k | `transactions` (type=sell) | ✅ `import-vendas.py` | done Martinho 12m · **falta venda_produto** |
| 5 | **Linhas de venda** | `VENDA_PRODUTO` (361 cols Martinho · 129k rows) | 10k-300k | `transaction_sell_lines` | ❌ FALTA criar | TBD |
| 6 | **Produtos** | `PRODUTO` | 1k-13k | `products` + `variations` | ❌ FALTA | TBD |
| 7 | **Categoria produto** | `PRODUTO_CATEGORIA` | 5-100 | `categories` | ❌ FALTA | TBD |
| 8 | **Financeiro (AR/AP)** | `FINANCEIRO` (57 cols Martinho · 103k rows) | 50k-500k | `transaction_payments` + cleanup_flags | ❌ FALTA (cleanup-first) | dep US-OFICINA-005 |
| 9 | **Boletos** | `FINANCEIRO_BOLETO` + `_HISTORICO` (15 cols + 2.8k rows Martinho) | 1k-30k | `rb_boletos` (Modules/RecurringBilling) | ❌ FALTA | TBD |
| 10 | **Contas bancárias** | `CONTAS` (91 cols) | 1-30 | `accounts` + `fin_contas_bancarias` | ✅ `import-contas-bancarias.py` | done WR2 |
| 11 | **NF-e/NFC-e** | `NF_*` tables · ~30+ tabelas em v1474 | varia | `Modules/NfeBrasil` | ❌ FALTA | depende módulo maduro |
| 12 | **Produção PCP** | `PRODUCAO_*` · `CENTRO_TRABALHO` · `PROCESSOS_*` | varia | TBD (provável `Modules/ProducaoIndustrial`) | ❌ FALTA | depende módulo |

---

## 3. Estratégia em 3 níveis (proposta)

### Nível 1 — Terminar Martinho all-time (2-4h estimado)

**Inputs:** 8.863 CNPJ distinct + 46.065 vendas + provável 129k linhas

| Tarefa | Estimativa |
|---|---|
| Re-rodar `import-contacts-from-venda` SEM filter date (8.863 CNPJ) | ~15min via SSH tunnel |
| Criar `import-venda-produto.py` (NOVO) + dry-run | ~1h código + 10min dry-run |
| Re-rodar `import-vendas` SEM filter date (46.065 vendas) | ~50min via SSH tunnel |
| Importer venda-produto local Laragon | ~30min smoke + ~30min prod |
| Validar UI prod biz=164 (smoke 3 telas: /contacts, /sells, sells-grade) | manual Wagner |

**Total wallclock:** ~3h Claude trabalhando + Wagner valida no fim.

### Nível 2 — Agentes especializados por entidade (4-6 sessões)

Em vez de **N agentes por cliente**, **1 agente por entidade × N clientes** (DRY).

**Template canônico** `.claude/agents/migracao-<entidade>.md`:
```yaml
name: migracao-<entidade>
description: Use quando Wagner pedir migrar <entidade> de cliente legacy <hash> pro oimpresso biz=N
inputs: cliente_hash + business_id + alias HKCU + versao_firebird (auto-detect)
output: importer Python + dry-run + audit JSON + matriz update
Tier 0: multi-tenant + idempotência legacy_id + 2 sign-offs prod
```

**6 agentes core priorizados (ordem de criação):**
1. `migracao-venda-produto` — bloqueador imediato Sells completo
2. `migracao-produtos` — produtos antes de linhas (FK)
3. `migracao-financeiro` (cleanup-first) — depende US-OFICINA-005
4. `migracao-boletos` — depende `Modules/RecurringBilling`
5. `migracao-nfe` — depende `Modules/NfeBrasil` por cliente
6. `migracao-pcp` — depende `Modules/ProducaoIndustrial` (futuro)

Outros agentes (empresa, contacts-from-venda, vehicles, vendas, contas-bancarias) já existem como scripts — só falta wrapper como agente Markdown.

### Nível 3 — Larga escala (1 sessão framework + N por cliente)

**Premissa:** 50 bancos Firebird × ~12 entidades = 600 execuções potenciais. Não dá pra fazer manualmente.

**Framework proposto:**

#### 3.1 Manifest por cliente
```yaml
# memory/clientes/05-martinho-cacambas.yaml
hash_id: Cliente_731814
business_id: 164
alias_hkcu: MartinhoServidor
versao_firebird: 1404
vertical: oficina-cacamba
fases:
  empresas: {status: pending, owner: skip (CRM órfão)}
  contacts: {status: done, importer: import-contacts-from-venda, mode: INLINE, runs: 1, last_run: 2026-05-13T17:50}
  vehicles: {status: done, importer: import-vehicles, runs: 1, last_run: 2026-05-13T13:31}
  vendas: {status: partial-12m, importer: import-vendas, runs: 1, target_total: 46065, imported: 5006}
  venda_produto: {status: pending, blocker: importer não criado}
  financeiro: {status: pending, blocker: cleanup-first US-OFICINA-005}
```

#### 3.2 Orquestrador
`.claude/agents/migracao-orchestrator.md` recebe `<cliente_hash>` + lê manifest + dispatch agentes por entidade na ordem correta (respeitando FK chains) + atualiza matriz.

#### 3.3 Schema introspection automático
Script `scripts/legacy-migration/probe-schema.py` que pra cada cliente:
- Lê `VERSAO_BANCO`
- Lista tabelas + cols (RDB$RELATION_FIELDS)
- Compara com schema canônico v1474
- Gera `drift-<cliente>-<ts>.json`
- Atualiza manifest YAML

#### 3.4 Idempotência universal
Migration genérica `add_legacy_id_to_<table>` pra tabelas alvo. Pattern:
- `contacts.legacy_id` ✅ done
- `vehicles.legacy_id` ✅ done
- `transactions.legacy_id` ❌ TBD (usa `ref_no` hoje, OK)
- `products.legacy_id` ❌ TBD
- `transaction_sell_lines.legacy_id` ❌ TBD
- `accounts.legacy_id` ✅ done

#### 3.5 Multi-cliente paralelizado
Spawn N agentes general-purpose simultâneos (1 por cliente) — cada um isolado por `business_id` (Tier 0 ADR 0093). Cap em 5 paralelos pra não estourar SSH tunnel.

#### 3.6 CI gate
Pest test integration por cliente:
- `tests/Feature/Migration/Martinho/ContactsImportTest.php`
- `tests/Feature/Migration/Martinho/VendasImportTest.php`
- Test count + sample 3 rows + multi-tenant isolation

---

## 4. Sugestão pragmática (curto prazo)

**Não tente criar os 12 agentes de uma vez.** Wagner gastou créditos demais com agentes anteriores não-coordenados.

Sequência sugerida pra próxima sessão:

1. **Validar Martinho 12m UI** primeiro (Wagner abre browser, valida `/contacts?biz=164` + `/sells`)
2. Se UI OK → **terminar Martinho all-time** (Nível 1)
3. Se all-time OK → **criar `migracao-venda-produto`** (bloqueador detalhe vendas)
4. Se venda-produto OK → escolher **PRÓXIMO cliente** (Vargas v1468 = drift baixo, validar agente noutra versão)
5. Vargas done → criar **manifesto YAML pattern** (Nível 3 começa)
6. Larga escala só depois de 2-3 clientes validados

**Anti-pattern detectado nesta sessão:** rodar all-time sem antes terminar Niveis 1+2 leva a importers incompletos rodando em prod com adapters não-validados.

---

## 5. Refs

- [Pattern canônico](../../reference/migracao-officeimpresso-pattern.md)
- [Agente migracao-officeimpresso](../../../.claude/agents/migracao-officeimpresso.md)
- [Agente migracao-firebird-versoes](../../../.claude/agents/migracao-firebird-versoes.md)
- [Matriz conhecimento](../../reference/matriz-conhecimento-clientes-legacy.md)
- [FK relacionamentos canon](../../research/clientes-legacy-officeimpresso/_MAPPING/relacionamentos-fk-firebird.sql)
- Source Delphi: `D:\Programas\WR Comercial\app\Controller\*.pas` (100+ arquivos) + `Resources\UpdateSQL.txt`
- PR #803 (merged) — 5 commits · pipeline 4 fases provado prod

---
**Criado:** 2026-05-13 ~19h BRT · sessão `angry-liskov-ec22c0` · pós-estudo fonte Delphi (Controller_Venda + Controller.Equipamento.Equipamento_Veiculo) + UpdateSQL.txt sample
