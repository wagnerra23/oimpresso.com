---
id: dominios-wr-comercial-modulos-financeiro-mapping
mapping: financeiro/contas-bancarias
source_domain: wr-comercial-delphi
source_version: 1468
target_domain: laravel-oimpresso
status: draft
created_at: 2026-05-09
authority: canonical
related_adrs: [0093, 0094, 0118]
related_specs: [requisitos/Financeiro/SPEC.md]
---

# MAPPING — Financeiro / Contas Bancárias

> ⚠️ **Anticorruption Layer documentada** ([ADR 0118](../../../decisions/0118-segregacao-dominios-externos-clientes-legacy.md)). Único arquivo bilíngue (vocabulário Delphi + Laravel) por design. Eric Evans, *Domain-Driven Design* (2003) cap. 14.

## Contexto

Migração one-shot de **contas bancárias do Delphi WR Comercial** (Firebird, schema v1468) → **Laravel oimpresso** (MySQL, multi-tenant `business_id`).

Primeiro caso da Migration Factory. Padrões aprendidos viram `_patterns/` reutilizáveis pra próximas entidades + concorrentes ([visão expandida discutida em sessão 2026-05-09](../../../sessions/)).

> ⭐ **Convenção crítica** ([`CONVENCOES.md` §1](../../CONVENCOES.md)): colunas `COD<TABELA>` no Delphi são chaves estrangeiras pra `<TABELA>(CODIGO)`. Auto-detectado em `lib/fk_resolver.py` + visível no frontmatter `foreign_keys` de cada doc de tabela. Importer usa diretamente — **`CODBANCO` Delphi É código FEBRABAN** (104=Caixa, 341=Itaú validados em ServidorWR2); `CODEMPRESA` resolve via lookup `EMPRESA(CODIGO)` → `CNPJCPF` + `RAZAOSOCIAL`.

## Tabelas no perímetro

### Lado Delphi (origem)

| Tabela Delphi | Colunas | Função | Doc |
|---|---|---|---|
| `BANCOS` | 5 | Extensões sobre bancos FEBRABAN (cooperativa, convênio, ativo) | [tabelas/BANCOS.md](tabelas/BANCOS.md) |
| `CONTAS` | 57 | **Mega-tabela**: dados de conta bancária + config CNAB + email + PIX + WS auth | [tabelas/CONTAS.md](tabelas/CONTAS.md) |
| `BANCOS_CONCILIACAO_BANCARIA` | 12 | Regras de matching extrato → plano contas | [tabelas/BANCOS_CONCILIACAO_BANCARIA.md](tabelas/BANCOS_CONCILIACAO_BANCARIA.md) |
| `BOLETOS` | 26 | Boletos emitidos | [tabelas/BOLETOS.md](tabelas/BOLETOS.md) |
| `FINANCEIRO_BOLETO` | 10 | Tabela auxiliar de arquivo de boleto | [tabelas/FINANCEIRO_BOLETO.md](tabelas/FINANCEIRO_BOLETO.md) |
| `FINANCEIRO_BOLETO_HISTORICO` | 32 | Log de eventos de boleto (cobrança, retorno, baixa, devolução) | [tabelas/FINANCEIRO_BOLETO_HISTORICO.md](tabelas/FINANCEIRO_BOLETO_HISTORICO.md) |
| `CONCILIACAO_BANCARIA` | 16 | Sessão de conciliação | [tabelas/CONCILIACAO_BANCARIA.md](tabelas/CONCILIACAO_BANCARIA.md) |
| `CONCILIACAO_BANCARIA_FINANCEIRO` | 11 | Items conciliados | [tabelas/CONCILIACAO_BANCARIA_FINANCEIRO.md](tabelas/CONCILIACAO_BANCARIA_FINANCEIRO.md) |
| `BANCO_IMAGENS` | 5 | Imagens de logo do banco | [tabelas/BANCO_IMAGENS.md](tabelas/BANCO_IMAGENS.md) |
| `FINANCEIRO_CHEQUE` | 21 | Cheques recebidos | [tabelas/FINANCEIRO_CHEQUE.md](tabelas/FINANCEIRO_CHEQUE.md) |

### Lado Laravel oimpresso (destino)

| Tabela Laravel | Função | Origem |
|---|---|---|
| `accounts` | Conta bancária core (UltimatePOS) | `app/Account.php` + migrations 2018 |
| `fin_contas_bancarias` | Complemento 1-1 com `accounts` (CNAB/boleto/PIX) | `Modules/Financeiro/Database/Migrations/2026_04_24_140003_*` |
| `fin_titulos` | Títulos a receber/pagar | `Modules/Financeiro/Database/Migrations/2026_04_24_*` |
| `fin_titulo_baixas` | Pagamentos/recebimentos com `idempotency_key` | `2026_04_24_140005_*` |
| `fin_caixa_movimentos` | Ledger de fluxo (entrada/saida/ajuste/transferencia) | `2026_04_24_140006_*` |
| `fin_extrato_lancamentos` | Sync extrato bancário (Inter, Sicoob futuro) | `2026_05_07_220000_*` |
| `transaction_payments` | Pagamentos de transações (core) | UltimatePOS |

Spec canônica: [`memory/requisitos/Financeiro/SPEC.md`](../../../requisitos/Financeiro/SPEC.md).

## Mapeamento alto-nível

```
DELPHI                                    LARAVEL OIMPRESSO
─────                                     ────────────────

BANCOS (FEBRABAN ext)            ──────►  fin_contas_bancarias.banco_codigo
                                          + (futuro?) fin_bancos_referencia

CONTAS (mega 57-col)             ──────►  accounts (core: id, name, account_number)
                                          + fin_contas_bancarias (1-1: agencia,
                                            conta, carteira, codigo_cedente, etc)
                                          + fin_contas_bancarias.metadata JSON
                                            (config email, PIX, WS, segredos)

BANCOS_CONCILIACAO_BANCARIA      ──────►  (NOVO?) fin_regras_conciliacao
                                          OU fin_extrato_lancamentos.metadata.regras

BOLETOS (26 col)                 ──────►  fin_titulos (tipo='receber')
                                          + fin_titulo_baixas (após pagamento)

FINANCEIRO_BOLETO_HISTORICO      ──────►  fin_titulo_baixas (eventos de baixa)
                                          + (NOVO?) fin_titulo_eventos
                                            (eventos não-baixa: retorno, mudança status)

CONCILIACAO_BANCARIA + items     ──────►  fin_extrato_lancamentos
                                          + (futuro?) fin_conciliacao_sessoes

FINANCEIRO_CHEQUE                ──────►  (escopo separado — cheque ≠ conta bancária)
```

## Ordem de import (respeitar FKs)

1. **`BANCOS`** → enrichment de `fin_contas_bancarias` (lookup por código FEBRABAN)
2. **`CONTAS`** → criar `accounts` + `fin_contas_bancarias` (split)
3. **`BANCOS_CONCILIACAO_BANCARIA`** → criar regras (decisão pendente: tabela nova ou metadata)
4. **`BOLETOS`** → criar `fin_titulos` (tipo='receber') + `fin_titulo_baixas` se houver pagamento
5. **`FINANCEIRO_BOLETO_HISTORICO`** → eventos (decisão pendente: split em baixas vs metadados)
6. **`CONCILIACAO_BANCARIA` + items** → criar `fin_extrato_lancamentos` (one-shot histórico)

## Mapeamento campo-a-campo

### CONTAS → `accounts` (core) + `fin_contas_bancarias`

`CONTAS` é mega-tabela 57 colunas — split em **3 destinos**:

#### → `accounts` (core UltimatePOS)

| Delphi (CONTAS) | Laravel (accounts) | Notas |
|---|---|---|
| `CODIGO` (PK) | `legacy_id` (custom) | + `legacy_source='wr-comercial'`. ID Laravel auto. |
| `CODEMPRESA` | `business_id` | resolve via `clientes/<alias>/PERFIL.md` mapping |
| `NOME` ou `NOME_CONTA` | `name` | Nome amigável da conta |
| `CODIGO_CEDENTE` ou `CONTA` | `account_number` | Número de conta visível |
| (sempre) | `account_type` = `'saving_current'` | Default — Delphi não distingue |
| (sempre) | `is_closed` = `(ATIVO = 'N')` | Inverte semântica |
| `DT_BALANCO` | `created_at` (audit) | Se vazio, usa `min(created_at)` das transações |

#### → `fin_contas_bancarias` (módulo Financeiro)

| Delphi (CONTAS) | Laravel (fin_contas_bancarias) | Notas |
|---|---|---|
| `CODBANCO` (FK pra BANCOS) | `banco_codigo` | Resolver código FEBRABAN via `BANCOS.CODIGO` |
| `AGENCIA` | `agencia` | |
| `DIGITO_AG` (não no auto-gen, ver schema vivo) | `agencia_dv` | |
| `CONTA` | `conta` | |
| `DIGITO_CC` ou `AGENCIA_CONTA_DV` | `conta_dv` | |
| `CARTEIRA` | `carteira` | |
| `CODIGO_CEDENTE` | `codigo_cedente` | Confirmar duplicação com `account_number` |
| `TIPO_CONVENIO` | `convenio` | |
| `NOME_CEDENTE` (legacy) | `beneficiario_razao_social` | |
| (CNPJ via `CODEMPRESA → EMPRESA.CNPJCPF`) | `beneficiario_documento` | Lookup cross-table |
| (sempre) | `ativo_para_boleto` = `('S' if has_boleto_config else 'N')` | Heurística por presença de campos |

#### → `fin_contas_bancarias.metadata` JSON

Tudo que **não cabe em colunas tipadas** vai como JSON em `metadata`:

```json
{
  "delphi_legacy": {
    "carteira_gera_remessa": "S",
    "variacao_gera_remessa": "N",
    "layout_arquivo": "240",
    "carac_titulo": 1,
    "executa_arquivo_retorno": "...",
    "tipo_carteira_manual": "N",
    "responsavel_emissao": 1,
    "tolerancia": 5,
    "ignorar_retorno_sem_liquidacao": "N",
    "gera_debito_tarifa": "S",
    "cooperativa": "N",
    "agencia_cooperativa": "...",
    "conta_cooperativa": "...",
    "digito_ag_cooperativa": "...",
    "digito_cc_cooperativa": "...",
    "codigo_cedente_cooperativa": "..."
  },
  "boleto_config": {
    "mensagem_protesto": "...",
    "mensagem_multa": "...",
    "mensagem_juros": "...",
    "mensagem_desconto": "...",
    "impr_historico_parcela": "S",
    "impr_plano_de_contas": "N",
    "tolerancia": 5,
    "multa_dias_tolerancia": 0,
    "desconto": 0.0,
    "dia_desconto": 0,
    "baixa_devolucao": 30
  },
  "boleto_email": {
    "assunto": "...",
    "mensagem": "...",
    "exibir_documento": "S",
    "exibir_vencimento": "S",
    "exibir_nota": "S",
    "exibir_valor": "S",
    "exibir_historico": "N",
    "tipo_exibicao_dados": 1,
    "codemail_modelo": null
  },
  "pix": {
    "chave": "...",
    "indicador": "S"
  },
  "ws_bancario": {
    "tem_ws": "S",
    "scopo": "boletos.read boletos.write",
    "endereco": "https://api.banco.com.br",
    "versao_arquivo": 1,
    "versao_layout": 240
  },
  "credenciais": {
    "// AVISO": "Mover pra secret store (Vaultwarden) em produção, não JSON",
    "client_id": "...",
    "client_secret": "...",
    "key_file_b64": "...",
    "cert_file_b64": "...",
    "appkey": "..."
  },
  "vinculos": {
    "codconta_vinculada": null,
    "codconta_transferencia_auto": null,
    "codigo_transmissao": "..."
  }
}
```

⚠️ **Segredos sensíveis** (`CLIENTSECRET`, `KEYFILE`, `CERTFILE`) **não devem ficar em colunas MySQL legíveis**. Importer deve detectar e:
- Em ambiente dev: pode ir pra `metadata.credenciais` com flag `is_dev_only=true`
- Em prod: importer chama Vaultwarden API e armazena referência (`metadata.credenciais.vault_ref="..."`)

### BANCOS → enrichment + (futuro?) `fin_bancos_referencia`

`BANCOS` Delphi tem 5 colunas — extensões sobre tabela FEBRABAN. **Não tem código do banco** explícito (provável FK chamada `CODIGO` herdada do CREATE original em v6 — não capturada pelo parser que começa em v7+).

| Delphi (BANCOS) | Laravel | Estratégia |
|---|---|---|
| `CODIGO` (PK) | n/a | Resolve apenas pra lookup `CONTAS.CODBANCO → BANCOS.CODIGO → código FEBRABAN` |
| `CONCILIACAO_USAR_DESC_RAZAO` | `metadata.usar_descricao_razao` em `fin_contas_bancarias` | Flag de conciliação |
| `CODBANCO_COOPERATIVA` | `metadata.banco_cooperativa_id` | Self-FK |
| `TIPO_CONVENIO` | `convenio` | Já mapeado |
| `ATIVO` | `ativo_para_boleto` | Já mapeado |
| `DT_ALTERACAO` | `updated_at` | Audit |

⚠️ **Lacuna**: Delphi parece não ter tabela explícita de bancos FEBRABAN (banco-código + nome). Provável que esteja **hardcoded no Delphi** ou em tabela criada antes de v6 (fora do `UpdateSQL.txt`). Validar via:
```sql
-- No Firebird vivo do Wagner
SELECT TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS
WHERE RDB$SYSTEM_FLAG=0 AND RDB$RELATION_NAME LIKE 'BANCO%';
```

Se não existir, **mapping resolve via lista FEBRABAN canônica** (built-in no importer Python).

### FINANCEIRO_BOLETO_HISTORICO → `fin_titulo_baixas` + (decisão pendente) `fin_titulo_eventos`

Tabela é **log de eventos de boleto**. Cada linha = 1 evento. Tipos observados via `TIPOOCORRENCIA` (string):
- "BAIXA POR LIQUIDACAO" → cria `fin_titulo_baixas`
- "BAIXA POR DEVOLUCAO" → cria `fin_titulo_baixas` com `tipo='estorno'`
- "ALTERACAO" / "ENTRADA" → metadata, não baixa
- "PROTESTO" → estado externo

#### Eventos de baixa → `fin_titulo_baixas`

| Delphi (FBH) | Laravel (fin_titulo_baixas) | Notas |
|---|---|---|
| `CODIGO` (PK part) | `legacy_id` | + `legacy_source='wr-comercial'` |
| `CODFINANCEIRO_BOLETO` | `titulo_id` | Resolver via lookup `BOLETOS.CODIGO → fin_titulos.id` |
| `CODCONTA` | `conta_bancaria_id` | Resolver via lookup `CONTAS.CODIGO → fin_contas_bancarias.id` |
| `VALOR_CREDITO` | `valor_baixa` | |
| `VALOR_MORA_JUROS` | `juros` | |
| `VALOR_DESCONTO` | `desconto` | |
| (não tem multa explícita) | `multa` | NULL ou calcular do `DIFERENCA` |
| `DT_CREDITO` ou `DATA` | `data_baixa` | Preferir `DT_CREDITO`, fallback `DATA` |
| (heurística) | `meio_pagamento` | Default `'boleto'` (cliente que paga boleto) |
| `CODFINANCEIRO_BOLETO + CODCONTA + BOLETO_NOSSO_NR + DATA` | `idempotency_key` (UUID) | Hash determinístico → mesmo evento gera mesmo UUID, evita dup |
| `OCORRENCIA` ou `MOTIVO` (BLOB) | metadata.observacao | Texto livre |
| `TIPOOCORRENCIA` | metadata.tipo_ocorrencia_origem | Pra rastreabilidade |

#### Eventos não-baixa (decisão pendente)

Eventos sem `VALOR_CREDITO` (ex: ENTRADA, ALTERACAO, PROTESTO) **não viram baixa**. Opções:

1. **Descartar** — só interessa baixa, ENTRADA já está em `fin_titulos.created_at`
2. **`fin_titulo_eventos` (NOVO)** — tabela nova só pra eventos não-baixa (audit)
3. **`fin_titulos.metadata.eventos_legacy`** — JSON com histórico inline

> 🟡 **Decisão pendente Wagner**: prefiro **opção 2** (audit explícito) se time quer trace history; senão **opção 1** (descartar). Pesa: legacy tem 5+ anos de histórico, descartar perde rastreabilidade. Opção 1 mais simples.

### BANCOS_CONCILIACAO_BANCARIA → (decisão pendente)

Regras de matching: dado `DESCRICAO` num lançamento de extrato, atribui `CODPLANOCONTAS` + `TIPO_MOVIMENTO` + `ACAO`.

Laravel oimpresso atualmente **não tem regras de conciliação implementadas** explicitamente — `fin_extrato_lancamentos` apenas armazena lançamentos brutos.

Opções:

1. **Não migrar** — regras Delphi são dependentes de string match em PT-BR; oimpresso futuramente pode ter regras com IA (Claude lê descrição e classifica)
2. **`fin_regras_conciliacao` (NOVO)** — tabela nova com colunas equivalentes
3. **`fin_extrato_lancamentos.metadata.regras_legacy`** — JSON denormalized (pior)

> 🟡 **Decisão pendente Wagner**: opção 1 ou 2. Recomendo opção 1 — regras automatizadas Delphi geralmente não migram bem (são heurísticas frágeis); IA-pair fará classificação melhor pra oimpresso.

### BOLETOS → `fin_titulos` (tipo='receber')

26 colunas no Delphi. Mapping principal:

| Delphi (BOLETOS) | Laravel (fin_titulos) | Notas |
|---|---|---|
| `CODIGO` | `legacy_id` | |
| `CODEMPRESA` | `business_id` | |
| `CODCONTA` | `metadata.conta_bancaria_origem_id` | Boleto não pertence à conta — é emitido por uma conta |
| `NOSSO_NUMERO` | `metadata.nosso_numero` | Identificador CNAB |
| `VALOR_TITULO` ou similar | `valor` | |
| `DT_VENCIMENTO` | `data_vencimento` | |
| `DT_EMISSAO` | `data_emissao` | |
| (heurística) | `tipo` = `'receber'` | Boleto = sempre receber |
| (heurística baseada em FBH) | `status` | `quitado` se tem baixa, `aberto` senão |

Detalhes campo-a-campo dependem de inspecionar `BOLETOS.md` completo (não fizemos ainda — Wagner valida).

## Lacunas e ações pendentes

| # | Lacuna | Ação |
|---|---|---|
| 1 | ~~`BANCOS` Delphi não tem tabela canon de FEBRABAN visível~~ | ✅ **RESOLVIDA 2026-05-09**: convenção FK COD<TABELA> mostra que `CODBANCO` aponta pra `BANCOS(CODIGO)` — onde `BANCOS.CODIGO` É o próprio código FEBRABAN. Importer usa direto via `normalize_banco_codigo()` (zfill 3). Validado em ServidorWR2 (104=Caixa, 341=Itaú). |
| 2 | `CONTAS` colunas v1140 (CLIENTID etc) coladas pelo parser regex | Bug menor — corrigir parser pra aceitar multi-ADD num único statement; ou ler do schema vivo via POC 2 |
| 3 | Decisão sobre `fin_titulo_eventos` | Wagner escolhe opção 1/2/3 antes de Fase 5 |
| 4 | Decisão sobre `fin_regras_conciliacao` | Wagner escolhe opção 1/2 antes de Fase 5 |
| 5 | Segredos bancários (CLIENTSECRET, KEYFILE) | Importer integra com Vaultwarden em prod, JSON em dev |
| 6 | `business_id` resolution per-cliente | Cada cliente Delphi mapeia 1 ou +N business_id no oimpresso. Documentar em `clientes/<alias>/PERFIL.md` |
| 7 | Validar duplicidade `CODIGO_CEDENTE` × `account_number` | Pode ser mesma info em 2 colunas — desambiguar com Wagner |
| 8 | `FINANCEIRO_CHEQUE` (escopo cheques) | Fora desta Fase 4. Wagner decide se entra na próxima ou em fase separada |

## Audit fields (todos os imports)

Toda linha importada recebe:

```sql
-- Em accounts, fin_contas_bancarias, fin_titulos, fin_titulo_baixas, etc:
legacy_source VARCHAR(50)  -- 'wr-comercial-delphi'
legacy_id     VARCHAR(50)  -- valor original do CODIGO Delphi
legacy_imported_at TIMESTAMP
legacy_importer_version VARCHAR(20)  -- ex: '0.1.0'
```

UPSERT idempotente por `(legacy_source, legacy_id)` permite re-rodar import N vezes sem duplicar.

## Validação pós-import

1. **Count match**: `SELECT COUNT(*) FROM accounts WHERE legacy_source='wr-comercial-delphi'` ≈ `SELECT COUNT(*) FROM CONTAS` (Firebird)
2. **Totais financeiros**: soma de `fin_titulos.valor` (importadas) ≈ soma de `BOLETOS.VALOR_TITULO` (Firebird)
3. **Drift check**: campos críticos (`agencia`, `conta`, `codigo_cedente`) batem byte-a-byte
4. **Smoke UI**: abrir `/financeiro/contas` no oimpresso e ver as contas listadas com saldos

## Próxima fase (5)

Importer Python `import-contas-bancarias.py --alias <X> --target-business <N>`:
- Lê registry pra path+senha Firebird
- Aplica mapping deste documento
- UPSERT idempotente
- Logs estruturados + relatório final
- `--dry-run` pra simular antes
