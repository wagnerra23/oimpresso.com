---
title: Guia Maiara — Coleta de dados pra migração Financeiro Delphi→oimpresso
status: canon
date: 2026-05-21
audience: Maiara (suporte time MCP)
purpose: Passo-a-passo executável — pra cada cliente legacy, Maiara devolve checklist preenchido em ~3-5h
related:
  - MIGRATION-CHECKLIST-LEGACY.md
  - memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md
---

# Guia Maiara — Coleta de dados pra migração Financeiro

> **Olá Maiara!** Este guia te leva passo-a-passo pra coletar tudo que o Felipe precisa pra migrar o Financeiro de UM cliente legacy (Delphi WR Comercial → oimpresso). Estimativa: **3-5h por cliente**. Não precisa saber Laravel — só rodar queries Firebird + falar com cliente.

## O que você vai entregar

Pra cada cliente, devolver na task MCP:

1. **MIGRATION-CHECKLIST-LEGACY.md preenchido** (1 cópia por cliente)
2. **3 arquivos CSV de mapping** (gerados via SQL — `scan-fields.py` faz a maior parte automático):
   - `mapping-planocontas-<cliente>.csv`
   - `mapping-tipopagto-<cliente>.csv`
   - `mapping-codbanco-<cliente>.csv`
3. **Resposta às perguntas do cliente** (Blocos A, B, C do checklist)
4. **Sample 20 registros** das tabelas PESSOAS, FINANCEIRO, CONTRATO (pra Felipe validar mapping)

---

## Passo 1 — Pre-flight (10min)

### 1.1 Identificar o cliente
- Qual cliente vamos migrar agora? _(pergunta Wagner ou olha a fila de tasks)_
- Qual o `business_id` dele no oimpresso? Roda no terminal:
  ```bash
  ssh hostinger 'cd /home/u613912490/domains/oimpresso.com/public_html && php artisan tinker --execute="echo App\Business::where(\"name\", \"like\", \"%NOME_CLIENTE%\")->get([\"id\",\"name\"]);"'
  ```
- Anota o `business_id` no cabeçalho do checklist (cópia do `MIGRATION-CHECKLIST-LEGACY.md`).

### 1.2 Conectar no Firebird do cliente
- Path do banco: `servidor-crm:D:\DadosClientes\<Cliente>\Dados\BANCO.FDB`
- Cliente: usa `isql` ou ferramenta gráfica (DBeaver, FlameRobin):
  ```cmd
  isql -u SYSDBA -p masterkey "servidor-crm:D:\DadosClientes\<Cliente>\Dados\BANCO.FDB"
  ```
- ⚠️ **Só SELECT — nunca INSERT/UPDATE/DELETE**. Banco em produção do cliente.

### 1.3 Confirmar volume
Roda essas 4 queries pra ter ideia do tamanho:
```sql
SELECT COUNT(*) FROM FINANCEIRO WHERE STATUS='ATIVO';
SELECT COUNT(*) FROM PESSOAS;
SELECT COUNT(*) FROM CONTRATO WHERE ATIVO='S';
SELECT COUNT(*) FROM BOLETOS;
```
Anota os 4 números no cabeçalho do checklist.

---

## Passo 2 — Extrair mappings automáticos (30min)

A maior parte dos "mapping CSV" sai sozinho via SQL. Você só precisa colar o resultado num CSV.

### 2.1 Plano de contas do cliente
```sql
SELECT CODIGO, DESCRICAO, TIPO, NIVEL
FROM PLANO_CONTAS
ORDER BY CODIGO;
```
Salva como `mapping-planocontas-<cliente>.csv`. **Anexa coluna `oimpresso_codigo` em branco** — vai ser preenchida no Passo 3 quando o cliente decidir (Bloco A.1).

### 2.2 Tipos de pagamento
```sql
SELECT CODIGO, DESCRICAO
FROM TIPOPAGTO
ORDER BY CODIGO;
```
Salva como `mapping-tipopagto-<cliente>.csv`. **Anexa coluna `oimpresso_enum`** com 1 dos 9 valores:
- `dinheiro`, `pix`, `boleto`, `cartao_credito`, `cartao_debito`, `transferencia`, `cheque`, `compensacao`, `outro`

Pra cada linha, escolhe o enum mais próximo. Em dúvida, marca `outro` + comentário.

### 2.3 Bancos do cliente
```sql
SELECT B.CODIGO, B.NOME, B.AGENCIA, B.CONTA, B.CARTEIRA, B.CONVENIO
FROM BANCO B
WHERE EXISTS (SELECT 1 FROM BOLETOS BO WHERE BO.CODBANCO = B.CODIGO);
```
Salva como `mapping-codbanco-<cliente>.csv`. **Anexa colunas:**
- `febraban_codigo` (3 dígitos — pesquisa rapidinho no Google "código banco X FEBRABAN")
- `oimpresso_account_id` (preenche depois — quando o cliente cadastrar a conta no admin do oimpresso)

### 2.4 Amostras pra Felipe validar
Roda essas 3 queries e salva cada resultado num arquivo separado (`sample-pessoas-<cliente>.csv`, `sample-financeiro-<cliente>.csv`, `sample-contrato-<cliente>.csv`):
```sql
-- Sample PESSOAS (20 random)
SELECT FIRST 20 * FROM PESSOAS ORDER BY DATACADASTRO DESC;

-- Sample FINANCEIRO (20 random ativos)
SELECT FIRST 20 * FROM FINANCEIRO
WHERE STATUS='ATIVO' AND TIPO IN ('RECEBIDA','A RECEBER','PAGA','A PAGAR')
ORDER BY EMISSAO DESC;

-- Sample CONTRATO (20 ativos)
SELECT FIRST 20 * FROM CONTRATO WHERE ATIVO='S' ORDER BY DT_INICIO DESC;
```

### 2.5 Verificar valores estranhos (pra Bloco D do checklist)
Roda essas e anota o resultado no checklist:
```sql
-- TIPO assume valores além dos 4 esperados?
SELECT DISTINCT TIPO FROM FINANCEIRO WHERE STATUS='ATIVO';

-- Tem VALOR<=0 ou NULL?
SELECT COUNT(*) FROM FINANCEIRO WHERE STATUS='ATIVO' AND (VALOR IS NULL OR VALOR <= 0);

-- Tem EMISSAO ou VENCTO NULL?
SELECT
  SUM(CASE WHEN EMISSAO IS NULL THEN 1 ELSE 0 END) AS emissao_null,
  SUM(CASE WHEN VENCTO IS NULL THEN 1 ELSE 0 END) AS vencto_null
FROM FINANCEIRO WHERE STATUS='ATIVO';

-- TIPO=RECEBIDA com DATAPAGTO NULL (bug Delphi?)
SELECT COUNT(*) FROM FINANCEIRO
WHERE STATUS='ATIVO' AND TIPO='RECEBIDA' AND DATAPAGTO IS NULL;

-- CONTRATO com VALOR NULL
SELECT COUNT(*) FROM CONTRATO WHERE ATIVO='S' AND VALOR IS NULL;

-- PESSOAS — quais valores de TIPO (1 char) aparecem?
SELECT TIPO, COUNT(*) FROM PESSOAS GROUP BY TIPO ORDER BY 2 DESC;
```

---

## Passo 3 — Call com o cliente (60-90min)

Marca call ou email longo com o responsável do cliente. **Não decidir nada sozinha** — é o cliente que sabe.

### Roteiro de perguntas (ordem sugerida)

**Bloco A — Plano de contas:**
1. "Você quer manter o plano de contas atual (X contas — abre o CSV `mapping-planocontas`) ou adotar o padrão do oimpresso (47 contas DRE Receita Federal)?"
   - Se manter: cliente preenche `oimpresso_codigo` no CSV (1-1 com plano atual)
   - Se adotar padrão: Maiara mapeia cada conta atual pra 1 das 47 do oimpresso (Felipe ajuda em casos ambíguos)

**Bloco B — Limpeza:**
2. "Tem confiança que registros com `STATUS='INATIVO'` são lixo (excluídos pelo usuário) que pode descartar?"
3. "Registros marcados como `PROVISORIO='S'` (previsão/orçamento) — descartamos?"
4. (Se tem CONTRATO com VALOR NULL) "Tem N contratos sem valor cadastrado. Você preenche manualmente antes da migração ou usamos fallback (média do cliente)?"
5. "Política `cleanup-first`: títulos com vencimento>1 ano, sem boleto, sem movimento — viram write-off no audit (não importam). Confirma?"

**Bloco C — Escopo:**
6. "Migrar TODO histórico (X linhas FINANCEIRO) ou só últimos N meses? Recomendação: 36 meses pra ter histórico tributário."
7. "Migrar boletos JÁ pagos (histórico) ou só os vivos?"
8. "Contratos cancelados (`ATIVO='N'`) — migra pra ter dashboard de churn ou descarta?"

**Bloco D (confirma com cliente o que você já viu no SQL):**
9. "Encontrei que sua tabela FINANCEIRO usa TIPO=[lista do Passo 2.5]. Algum desses tem significado diferente do óbvio?"
10. "Em FINANCEIRO, o `RAZAOSOCIAL` às vezes diverge do PESSOAS (typo, sufixo "ME"). Qual versão você prefere que vire definitiva?"

**Bloco E — Dependências:**
11. "Você usa o sistema pra que vertical? Gráfica / Comércio / Oficina / Outro?"
12. "Suas NFs de entrada são importantes manter no novo sistema, ou só como referência?"
13. "Tem mensalidade recorrente (Modules/FinanceiroAvancado) ou só contas pontuais?"

**Bloco F — Segurança:**
14. "Suas senhas de certificado A1, NFe, NFC-e — você prefere recadastrar no novo sistema ou trazer do Delphi?" (Recomendação: recadastrar — mais seguro)

---

## Passo 4 — Preencher o checklist (30min)

Volta no `MIGRATION-CHECKLIST-LEGACY.md` (cópia que você fez) e preenche:
- Cabeçalho (Passo 1)
- Bloco A, B, C — com resposta do cliente
- Bloco D — com resultado do SQL (Passo 2.5)
- Bloco E — com resposta do cliente
- Bloco F — já marca LGPD ✅ (Wagner liberou 2026-05-21)

Coluna "Dúvida pendente" da tabela mestre: se ficou alguma sem resposta, marca em ⚠️ amarelo e comenta na task MCP pra Felipe/Wagner ajudarem.

---

## Passo 5 — Entrega na task MCP

Posta na task MCP (vai ter 1 task `tasks-create` aberta no seu nome por cliente):

```
✅ Cliente <Nome> — pre-flight completo

Anexos:
- MIGRATION-CHECKLIST-LEGACY-<cliente>.md (preenchido)
- mapping-planocontas-<cliente>.csv
- mapping-tipopagto-<cliente>.csv
- mapping-codbanco-<cliente>.csv
- sample-pessoas-<cliente>.csv (20 linhas)
- sample-financeiro-<cliente>.csv (20 linhas)
- sample-contrato-<cliente>.csv (20 linhas)

Dúvidas pendentes (precisam Wagner/Felipe):
- [lista as ⚠️ amarelas]

Volume estimado: <N> linhas FINANCEIRO, <M> pessoas, <P> contratos ativos
Cutover sugerido pelo cliente: <data>
```

E muda o status da task pra `review` no MCP.

---

## Quando você travar

- **SQL não roda / Firebird não conecta:** chama Felipe (ele instalou o cliente Firebird em todas as máquinas do time)
- **Cliente não responde:** mensagem WhatsApp + emaila — espera 48h — escala pro Wagner
- **Dúvida sobre mapping:** comenta na task MCP marcando @felipe
- **Dúvida sobre regra de negócio:** comenta marcando @wagner

---

## Cronograma sugerido

Pra cada cliente: **3-5h espalhadas em 2-3 dias** (não 1 dia corrido — call com cliente precisa janela)

| Dia | Atividade |
|---|---|
| Dia 1 manhã | Passo 1 + Passo 2 (1h30) |
| Dia 1 tarde | Passo 3 (call cliente, 1h30) — agendar 24-48h antes |
| Dia 2 | Passo 4 + Passo 5 (1h) |

Quando terminar 1 cliente, próximo cliente: Wagner abre nova task `tasks-create` no MCP.

---

## Histórico

- **2026-05-21**: criado por Wagner. Maiara designada coleta. LGPD liberado.
