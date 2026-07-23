---
id: requisitos-officeimpresso-officeimpresso-firebird-schema
title: OfficeImpresso (legacy Delphi WR Comercial) — Firebird schema reference
status: canon
date: 2026-05-09
audience: time interno + Claude
purpose: referência de acesso e schema dos bancos OfficeImpresso, reusada em (a) análises financeiras multi-cliente, (b) migrações pro oimpresso.com novo
---

# OfficeImpresso — Firebird schema reference

> Documento canônico. Usado em análises financeiras + migrações de cliente legacy → oimpresso.com.

## 1. Acesso técnico

### 1.1 Servidor produção própria (WR Sistemas)
- **Alias**: `ServidorWR2` (na lista de bancos do gerenciador Delphi)
- **Conexão**: `192.168.0.55:Banco` (Firebird 3.0.12, port 3050)
- **DSN-equiv**: `192.168.0.55:Banco` (sem caminho local — é alias remoto)
- **Caminho real no servidor**: `C:\WR Sistema\Dados\BANCO.FDB`
- **Credencial**: `SYSDBA` / `masterkey` (default fábrica Firebird, herança Delphi)

### 1.2 Servidor clientes WR Sistemas (38+ bases registradas)
Cada cliente OfficeImpresso roda seu próprio Firebird. Todas seguem o **mesmo schema** (mesma versão Delphi). Lista parcial visível no gerenciador Delphi (HKCU\Software\Rocha\Office Comercial\Banco\Caminhos):

```
servidor-crm:D:\DadosClientes\<NomeCliente>\Dados\BANCO.FDB
```

Exemplos: Estilo · Extreme · Fixar · Fluxo · GPSinalizacao · GSX · Gold · GoldenPrint · Guia Decor · HexiPrint · Lebrinha · Martinho · Max · Mecanica Lebrinha · Medeiros Produtos Limpeza · Metalurgica SF · Mhundo · Midia OFF · Midia e CIA · MilLetras · Movessul · Multimage · NewPrintFoz · Personalise · Produart · RG Comunicacao · SCMola · Safety · Studium Vinil · TechPress · TechPressLocal · Vargas · Vargas Acessorios · Wow · Zoom (38 totais).

⚠️ **Atenção**: cada banco tem dados de UM cliente OFFICE-IMPRESSO. A análise multi-cliente conecta em CADA banco, não em um banco master.

### 1.3 Cliente Python (preferido)
```bash
pip install firebird-driver
```

```python
import firebird.driver as fb
con = fb.connect('192.168.0.55:Banco', user='SYSDBA', password='masterkey')
cur = con.cursor()
```

### 1.4 Cliente isql (alternativa)
```cmd
isql -u SYSDBA -p masterkey "192.168.0.55:Banco"
```

### 1.5 Restrições obrigatórias
- ❌ NUNCA fazer INSERT/UPDATE/DELETE — apenas SELECT (banco produção)
- ❌ NUNCA exportar dados sem anonimização pra git público
- ❌ NUNCA commitar credenciais em código (usar `.env`)
- ✅ Criar arquivo `.gitignore` em qualquer pasta com relatórios contendo dados reais

## 2. Schema essencial — 441 tabelas, 9 críticas

### 2.1 Visão hierárquica
```
PESSOAS (cadastro mestre — clientes, fornecedores, funcionários)
  └→ CONTRATO (contratos vivos por cliente — MRR contratado)
       └→ MENSALIDADE (templates mensais)
            └→ MENSALIDADE_FINANCEIRO (lançamentos individuais)
                 └→ FINANCEIRO (master de lançamentos — receita+despesa)
                      └→ BOLETOS (cobrança bancária)
                           └→ FINANCEIRO_BOLETO_HISTORICO (eventos boleto)
```

### 2.2 Tabela `FINANCEIRO` (master — 59.186 lançamentos no master)

Master de TODOS os lançamentos financeiros (receita + despesa). É a tabela canônica pra análise.

**Colunas-chave** (de 63 totais):
- `CODIGO` (PK)
- `RAZAOSOCIAL` ⚠️ PII — nome do cliente/fornecedor
- `VALOR` (DECIMAL)
- `EMISSAO`, `VENCTO`, `DATAPAGTO` (datas)
- `TIPO` ⭐ valores: `'RECEBIDA'`, `'A RECEBER'`, `'PAGA'`, `'A PAGAR'`
- `STATUS` ⭐ valores: `'ATIVO'` (vivo), `'INATIVO'` (cancelado/excluído)
- `DOCUMENTO`, `NOTAFISCAL`
- `HISTORICO` (descrição livre)
- `JUROS`, `DESCONTO`, `MULTA`
- `CODPLANOCONTAS`, `CODTIPOPAGTO`, `CODCONDICAOPAGTO`
- `PARCELA`, `CODFINANCEIRO_GRUPO`
- `BOLETO_NOSSO_NR`, `BOLETO_OCORENCIA`
- `CODNF_ENTRADA` (FK pra nota de entrada)
- `MOTIVO_EXCLUSAO`, `DT_EXCLUSAO`
- `PROVISORIO` (lançamento previsto não-confirmado)

### 2.3 Tabela `MENSALIDADE_FINANCEIRO` (17.749 linhas)

Lançamentos de mensalidade (cobrança recorrente). Sub-tipo de FINANCEIRO mas com escopo SaaS.

**Colunas-chave**:
- `CODIGO` · `CODMENSALIDADE` (FK MENSALIDADE)
- `VALOR` · `DT_VENCTO` · `DT_EMISSAO`
- `STATUS` (`'ATIVO'`/`'INATIVO'`) · `TIPO` (`'A RECEBER'`)
- `RAZAOSOCIAL` ⚠️ PII
- `TIPOPAGTO` (`'BOLETO'`, `'PIX'`, etc)
- `PESSOA_RESPONSAVEL_CODIGO`
- `PLACA`, `MARCAMODELO`, `ANO` (campos auto que vazaram pra schema — gráficas não usam)

### 2.4 Tabela `CONTRATO` (313 contratos, 244 ativos)

Contratos vivos = MRR contratado.

- `CODIGO` · `ATIVO` (`'S'`/`'N'`) · `VALOR`
- `DT_INICIO`, `DT_FIM`
- `CODPESSOA` (FK PESSOAS)
- 62 contratos com VALOR=NULL precisam reconciliação antes de migrar

### 2.5 Tabela `PESSOAS` (13.703 cadastros — clientes/fornecedores/funcionários/transportadores)

⚠️ **329 colunas** — mas só ~30 são canônicas pra análise.

**Colunas críticas**:
- `CODIGO` (chave) · `RAZAOSOCIAL` · `FANTASIA` · `CNPJCPF`
- `TIPO` (1 char): provavelmente `'C'`=cliente, `'F'`=fornecedor, `'T'`=transportador (validar)
- `BLOQUEADO` (`'S'`/`'N'`)
- `ENDERECO`, `BAIRRO`, `CIDADE`, `UF`, `CEP`
- `FONE1`, `FONE2`, `EMAIL`
- `LIMITECREDITO`, `CODCONDICAOPAGTO`
- `DATACADASTRO`

### 2.6 Tabela `BOLETOS` (29.946 boletos)

Boletos bancários emitidos.

- `CODIGO` · `CODFINANCEIRO` (FK) · `CODBANCO`
- `CARTEIRA`, `TIPO`, `OCORENCIA`
- `JUROS_MORA`, `MULTA`, `DESCONTO`
- `SITUACAO`, `BAIXA_DEVOLUCAO`
- `DT_REMESSA`, `DT_RETORNO`

### 2.7 Tabela `BALANCO_TITULO` (152 linhas)

Demonstrativos contábeis. Pra fluxo de caixa estruturado.

### 2.8 Tabela `VENDA` (1.866 vendas) + `VENDA_FINANCEIRO` (3.404 lançamentos)

OS / vendas comerciais com lançamento financeiro associado. Importante pro funil produção→cobrança.

### 2.9 Tabela `NOTA_FISCAL` (231 NFs)

NFs emitidas. Pequeno volume (provável que clientes legacy não usem NFC-e/NFS-e do OfficeImpresso).

## 3. Queries-template canônicas (SELECT only)

### 3.1 Sumário financeiro 12m (cliente atual = banco conectado)

```sql
SELECT
  (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO
   WHERE TIPO='RECEBIDA' AND STATUS='ATIVO'
     AND DATAPAGTO BETWEEN DATEADD(YEAR, -1, CURRENT_DATE) AND CURRENT_DATE) AS REC_12M,
  (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO
   WHERE TIPO='PAGA' AND STATUS='ATIVO'
     AND DATAPAGTO BETWEEN DATEADD(YEAR, -1, CURRENT_DATE) AND CURRENT_DATE) AS PAG_12M,
  (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO
   WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
     AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL) AS A_RECEBER_VENCIDAS,
  (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO
   WHERE TIPO='A PAGAR' AND STATUS='ATIVO'
     AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL) AS A_PAGAR_VENCIDAS
FROM RDB$DATABASE;
```

### 3.2 Receita mensal recebida 24m

```sql
SELECT EXTRACT(YEAR FROM DATAPAGTO) AS ANO,
       EXTRACT(MONTH FROM DATAPAGTO) AS MES,
       COUNT(*) AS N,
       SUM(VALOR) AS TOTAL
FROM FINANCEIRO
WHERE TIPO = 'RECEBIDA' AND STATUS = 'ATIVO'
  AND DATAPAGTO IS NOT NULL
  AND DATAPAGTO >= DATEADD(YEAR, -2, CURRENT_DATE)
GROUP BY 1, 2 ORDER BY 1, 2;
```

### 3.3 Top N clientes 12m por receita

```sql
SELECT FIRST 30
       RAZAOSOCIAL, COUNT(*), SUM(VALOR), MAX(DATAPAGTO)
FROM FINANCEIRO
WHERE TIPO='RECEBIDA' AND STATUS='ATIVO'
  AND DATAPAGTO BETWEEN DATEADD(YEAR, -1, CURRENT_DATE) AND CURRENT_DATE
  AND RAZAOSOCIAL IS NOT NULL
GROUP BY RAZAOSOCIAL
ORDER BY 3 DESC;
```

### 3.4 Inadimplência detalhada (clientes em atraso)

```sql
SELECT RAZAOSOCIAL, COUNT(*), SUM(VALOR), MAX(VENCTO)
FROM FINANCEIRO
WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
  AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL
  AND RAZAOSOCIAL IS NOT NULL
GROUP BY RAZAOSOCIAL
ORDER BY 3 DESC;
```

### 3.5 MRR atual (mês corrente)

```sql
SELECT COUNT(*), SUM(VALOR)
FROM MENSALIDADE_FINANCEIRO
WHERE STATUS='ATIVO'
  AND DT_VENCTO BETWEEN
        DATEADD(DAY, 1-EXTRACT(DAY FROM CURRENT_DATE), CURRENT_DATE) AND
        LAST_DAY(CURRENT_DATE);
```

## 4. Migração pro oimpresso.com novo

### 4.1 Mapeamento ENT → ENT (preliminar)
| OfficeImpresso (Firebird) | oimpresso.com (Laravel/MySQL) |
|---------------------------|-------------------------------|
| `PESSOAS` (TIPO='C') | `contacts` (`type='customer'`) |
| `PESSOAS` (TIPO='F') | `contacts` (`type='supplier'`) |
| `CONTRATO` ATIVO='S' | `subscription_contracts` |
| `MENSALIDADE_FINANCEIRO` | `subscription_invoices` |
| `FINANCEIRO` TIPO='RECEBIDA' | `transaction_payments` (paid) |
| `FINANCEIRO` TIPO='A RECEBER' | `transactions` (sell.payment_status=due) |
| `FINANCEIRO` TIPO='PAGA' | `expense_payments` |
| `FINANCEIRO` TIPO='A PAGAR' | `expenses` (status=open) |
| `BOLETOS` | `transaction_boletos` (Asaas integration) |
| `VENDA` | `transactions` (type='sell') |
| `NOTA_FISCAL` | `nfe_brasil_transactions` |

### 4.2 Padrão de migração canônico
1. **Stage**: ETL Firebird → MySQL staging (preservar IDs originais em `legacy_id`)
2. **Reconcile**: validar totais antes/depois
3. **Cutover**: 1 cliente por vez, com paralelo 30d
4. **Decommission**: arquivar BANCO.FDB original em backup imutável (não deletar)

### 4.3 Custo estimado
~16h IA-pair por cliente (Felipe) + 4h Wagner aprovação. Multi-cliente em série, não paralelo.

## 5. Skill + Runbook + Feature relacionados

- **Skill**: [.claude/skills/officeimpresso-financial-snapshot/SKILL.md](../../../.claude/skills/officeimpresso-financial-snapshot/SKILL.md) — automatiza coleta da análise pra qualquer banco cliente
- **Runbook operacional**: [RUNBOOK-financial-snapshot-cliente.md](RUNBOOK-financial-snapshot-cliente.md) — passo-a-passo manual
- **Feature comercial proposta**: [feature-financial-snapshot-multi-cliente.md](../../decisions/proposals/feature-financial-snapshot-multi-cliente.md) — vira produto pago

## 6. Histórico do conhecimento

- **2026-05-09**: schema descoberto via Python firebird-driver, 441 tabelas listadas, 9 críticas mapeadas, queries-template validadas em ServidorWR2:Banco produção. Conexão LAN 192.168.0.55:3050. Análise rendeu R$ [redacted Tier 0]k ARR + déficit operacional 12m revelado.
- Próximas atualizações: cada vez que descobrir campo novo ou table novo durante análise/migração.
