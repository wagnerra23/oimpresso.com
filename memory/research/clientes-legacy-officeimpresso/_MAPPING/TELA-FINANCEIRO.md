---
id: research-clientes-legacy-officeimpresso-mapping-tela-financeiro
title: Mapping canônico — Tela "Financeiro" Delphi → oimpresso.com
status: live
date: 2026-05-11
audience: dev migrando OfficeImpresso → oimpresso.com novo
source_files:
  - "D:/Programas/WR Comercial/app/Controller/Controller.Financeiro.pas (1239 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Financeiro.AReceber.pas (104 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Financeiro.APagar.pas (112 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Financeiro.Recebimento.pas"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Financeiro.Cheque.pas"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Financeiro_Boleto.pas"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Financeiro_Centro_Custo.pas"
method: source-first (skill officeimpresso-source-analysis)
---

# Mapping canônico — Tela "Financeiro" Delphi

> **Fonte autoritativa:** Controllers Delphi reais.
> **Tabela mestre:** `FINANCEIRO` (single ledger pra AR + AP + caixa) — diferente de UltimatePOS que separa `transaction_payments` por transação. ⚠️ Cliente grande tem **59k+ linhas** nessa tabela.

## 1. Cadeia de herança

```
TObject
  └─ TControllerMestre               (Controller.Mestre.pas)
      └─ TControllerfinanceiro       (Controller.Financeiro.pas, 1239 LOC) — base ledger único + serviços
          ├─ TControllerFinanceiroAReceber   (AReceber.pas, 104 LOC) — view filtrada TIPO IN (A RECEBER, RECEBIDA)
          ├─ TControllerFinanceiroAPagar     (APagar.pas, 112 LOC) — view filtrada TIPO IN (A PAGAR, PAGA)
          ├─ TControllerFinanceiro.Recebimento — modal pagamento parcela AR
          └─ TControllerFinanceiro.Cheque    — cadastro/gestão cheques

  Auxiliares (não herdam):
  └─ Controller.Financeiro_Boleto*           — geração boletos (3 arquivos)
  └─ Controller.Financeiro_Centro_Custo      — cadastro centros de custo
  └─ Controller.Boleto.Financeiro            — wrapper boleto + financeiro
  └─ Controller.Boleto.WS.Financeiro         — webservice retorno boletos
  └─ Controller.Venda_Financeiro             — geração AR a partir Venda
  └─ Controller.Venda_Financeiro_TEF         — pagamento cartão TEF
```

`Caption := 'Financeiro'`, `Tabela := 'FINANCEIRO'`, `Path := PathFINANCEIRO`. Subclasses redefinem Path: `PathFINANCEIRO_A_PAGAR`, `PathFINANCEIRO_A_RECEBER`.

## 2. SQL base (`TControllerFinanceiroAPagar.Create` / `AReceber.Create`)

Subclasses substituem SQL pra remover joins desnecessários:

```sql
-- Controller.Financeiro.APagar.Create:40 e AReceber.Create:38 (idêntico)
SELECT B.*
FROM FINANCEIRO B
```

⚠️ **`select *`** — Delphi puxa todas as colunas. Em cliente grande (59k linhas, FINANCEIRO tem 80+ colunas) isso é caro. Migração Laravel: SELECT explícito + paginação obrigatória.

A tela base (`TControllerfinanceiro`) NÃO define `SQLInit.Text` no Create — depende da subclasse selecionada via tile (`BaseItenList`):

```pascal
// Controller.Financeiro.pas:479-485
BaseItenList.Add(TWR_Base_tile.Create('Financeiro',         '/financeiro/financeiro',         OnClick_ActivateDetail));
BaseItenList.Add(TWR_Base_tile.Create('Contas a Receber',   '/financeiro/contasAReceber',     OnClick_ActivateDetail));
BaseItenList.Add(TWR_Base_tile.Create('Contas a Pagar',     '/financeiro/pagar',              OnClick_ActivateDetail));
```

## 3. Filtros padrão (subclasses — `FormCreateConsulta`)

```pascal
// Controller.Financeiro.APagar.pas:78-79
procedure TControllerFinanceiroAPagar.FormCreateConsulta(AOwner: TComponent);
begin
  GetFiltroProNome('Retirar filtros').SQL := '(B.ATIVO = ''S'')';
  GetFiltroProNome('Arquivados').SQL      := 'Not (B.ATIVO = ''S'')';
end;
```

Filtros por TIPO (em código comentado, mas implementado em telas dinâmicas — `Controller.Financeiro.pas:570-577`):

| Checkbox UI | WHERE adicional |
|-------------|------------------|
| **A Receber** unchecked | `(F.TIPO <> 'A RECEBER')` |
| **Recebida** unchecked | `(F.TIPO <> 'RECEBIDA')` |
| **A Pagar** unchecked | `(F.TIPO <> 'A PAGAR')` |
| **Paga** unchecked | `(F.TIPO <> 'PAGA')` |

→ Default: vê todos 4 tipos; usuário desmarca os indesejados.

**STATUS adicional** (linha 564-567): `STATUS like 'ATIVO%'` (todos exceto Arquivados); arquivados via aba lateral.

## 4. Agrupadores dinâmicos (Controller.Financeiro.pas:582-622)

Diferente de Venda/Pessoas que registra agrupadores fixos, o Financeiro tem **agrupamento dinâmico** — quando usuário arrasta coluna do grid:

```pascal
// Mapping campo UI → SQL alias
'CONTA'           → 'C.<campo>'                  (CONTA.CODIGO via JOIN)
'USUARIO_CONTA'   → 'UC.USUARIO'                 (usuário que conciliou)
'PLANOCONTAS'     → 'PC.CODIGO'                  (FK CODPLANOCONTAS)
'CENTRO_CUSTO'    → 'CC.CODIGO'                  (FK CODCENTRO_CUSTO)
'CENTRO_CUSTO_PAI' → 'CC.CODCENTRO_CUSTO_PAI'
default            → 'F.<campo>' (ou COD<campo> se autoincremento)
```

→ Laravel: implementar groupBy dinâmico via params (`?group_by=plano_contas`) + alias map.

## 5. Datas filtráveis (implícito — não há `InitializeDatasNaConsulta` explícito)

Pelo uso espalhado no código, os campos data filtráveis são:

| Campo SQL | Significado |
|-----------|-------------|
| `EMISSAO` | Data emissão lançamento |
| `VENCTO` | Data vencimento parcela |
| `DATAPAGTO` | Data efetiva pagamento (null se em aberto) |
| `DT_NOTAFISCAL` | Data NFe associada |
| `DT_COMPETENCIA` | Competência contábil (default = DataPagto ou Emissão) |
| `DT_CONCILIADO` | Data conciliação bancária |

→ Laravel: dropdown header 6 datas (vs 7 da Venda) — semântica similar.

## 6. Schema da tabela `FINANCEIRO` (extraído do INSERT linhas 896-907)

```sql
INSERT INTO FINANCEIRO (
  CODPLANOCONTAS, CODCONDICAOPAGTO, CODCONTA, CODIGO, CODPEDIDO,
  PESSOA_RESPONSAVEL_CODIGO, CODTIPOPAGTO, CODUSUARIO, CONDICAOPAGTO,
  CONTATOS, DATAPAGTO, DESCONTO, DOCUMENTO, EMISSAO, HISTORICO, JUROS,
  NOTAFISCAL, PARCELA, RAZAOSOCIAL, STATUS, TIPO, PESSOA_RESPONSAVEL_TIPO,
  DT_NOTAFISCAL, TIPOPAGTO, VALOR, VENCTO, CODNF_ENTRADA, CODFINANCEIRO_GRUPO,
  CODEMPRESA, PESSOA_RESPONSAVEL_SEQUENCIA, PREVISAO, AGRUPADOR,
  CHEQUE_NUMERO, DT_COMPETENCIA, CODUSUARIO_CONTA
) VALUES (...)
```

**35 colunas no INSERT.** Tabela completa tem ~80 colunas (incluindo boleto/PIX/conciliação/correção monetária). Estrutura genérica AR/AP/Caixa single-ledger.

## 7. Valores padrão / Defaults

Não há `Controller.Financeiro.Definicoes.pas` — defaults são preenchidos em `ServicesFinanceiro_Lancamento_Financeiro` (linhas 858-972):

| Campo | Default lógico | Notas |
|-------|----------------|-------|
| `STATUS` | passado pelo chamador (geralmente `'ATIVO'`) | |
| `TIPO` | passado pelo chamador (`A RECEBER`, `A PAGAR`, `RECEBIDA`, `PAGA`) | enum implícito |
| `DT_COMPETENCIA` | **`DtCompetencia` se informado, senão `DtPagto`, senão `DtEmissao`** (linhas 949-954) | regra de cascata |
| `CODUSUARIO_CONTA` | `Usuario.Codigo` corrente | (auto) |
| `EMISSAO` | passado pelo chamador (current date típico) | |
| `VENCTO` | passado pelo chamador (obrigatório AR/AP) | |
| `PREVISAO` | passado pelo chamador (`AValorPrevisao`) | valor previsto vs real |

## 8. Validações no INSERT (`ServicesFinanceiro_Lancamento_Financeiro` linhas 858-972)

```pascal
// 1. Caixa aberto check (se módulo Caixa ativo + tem DtPagto)
if GetSituacaoCaixaSQL(Transacao, ADtPagto, ACodConta).Situacao = scCaixaFechado then
  Abort;   // bloqueia lançamento em caixa fechado

// 2. Conta bancária válida + situação
wrFuncoesValidarSituacaoContaBanco(QuerX.DataSet);

// 3. Plano de contas válido
VerificaPlanoContasFinanceiro(Transacao, ACodPlanoContas);

// 4. Conciliação (Conciliar Contas — linha 626-662)
if AFinanceiro.FieldByName('DT_CONCILIADO').IsNull then
  // permite conciliar; senão pula
```

Idempotência conciliação: `DT_CONCILIADO is null` é condição pra alterar (linha 640) — não re-concilia.

## 9. Saldo das contas (`ServicesFinanceiro_AtualizaSaldo` + `AtualizaSaldoCompleto`)

Mantém tabela espelho `FINANCEIRO_SALDO(CODCONTA, DATA, SALDO_EFETIVO, SALDO_PREVISIONADO)` agregando por dia (linhas 712-857):

```sql
-- Saldo efetivo (caixa real, com base em DATAPAGTO)
SUM(VALOR + COALESCE(JUROS,0) - COALESCE(DESCONTO,0) * iif(TIPO='PAGA', -1, 1))
FROM FINANCEIRO
WHERE STATUS like 'ATIVO%'
  AND TIPO in ('RECEBIDA', 'PAGA')
  AND DATAPAGTO between :DtInicio and :DtFim

-- Saldo previsionado (projeção, base em VENCTO)
SUM(VALOR + ...) onde TIPO in ('A PAGAR', 'A RECEBER') AND VENCTO between ...
```

→ Laravel: rodar via job/scheduler ou trigger db. UltimatePOS já tem `account_balance` table — verificar adaptação.

## 10. Mapping de campos Delphi → Laravel/oimpresso

### 10.1 Core (sempre migrar)

| Delphi `FINANCEIRO.*` | Laravel | Notas |
|------------------------|---------|-------|
| `CODIGO` (integer) | `id` (bigint AI) | |
| `TIPO` (`A RECEBER`/`RECEBIDA`/`A PAGAR`/`PAGA`) | `transaction_payments.is_return + .method` derivado, ou nova coluna `type` | UltimatePOS payments são sempre vinculados a transactions; aqui é standalone |
| `VALOR` | `amount` | obrigatório |
| `JUROS`, `DESCONTO` | `interest_amount`, `discount_amount` | |
| `PREVISAO` (valor previsto vs realizado) | `expected_amount` (novo) | controle previsão |
| `EMISSAO` | `issued_at` ou `created_at` | data lançamento |
| `VENCTO` | `due_date` | obrigatório AR/AP |
| `DATAPAGTO` | `paid_at` (datetime) | null = não pago |
| `DT_COMPETENCIA` | `competence_date` | regime competência contábil |
| `DT_CONCILIADO` | `reconciled_at` | null = não conciliado |
| `DT_NOTAFISCAL` | `nfe_date` ou `related_nfe.date` | |
| `PARCELA` (`1/3`, `2/3`...) | `installment_label` (string) | |
| `STATUS` (`ATIVO`/`INATIVO`/`ATIVO ESPERA`) | `status` enum + soft delete | |
| `HISTORICO` | `description` | livre |
| `OBSERVACAO` | `notes` | livre |
| `DOCUMENTO` | `document_no` (string 20) | nº documento/duplicata |
| `NOTAFISCAL` | `nfe_number` ou derived | |

### 10.2 Relacionamentos

| Delphi | Laravel |
|--------|---------|
| `PESSOA_RESPONSAVEL_CODIGO` | `contact_id` (FK contacts) — cliente (AR) ou fornecedor (AP) |
| `PESSOA_RESPONSAVEL_TIPO` (`CLI`/`FOR`) | derivado de `contact.type` | |
| `PESSOA_RESPONSAVEL_SEQUENCIA` | (descartar — usar contact_id) | |
| `CODCONTA` | `account_id` (FK `accounts`) — conta bancária/caixa |
| `CODPLANOCONTAS` | `chart_account_id` (FK `chart_accounts`) — plano de contas |
| `CODCENTRO_CUSTO` | `cost_center_id` (FK `cost_centers`) | |
| `CODPEDIDO` (FK VENDA) | `transaction_id` (FK transactions sell) | vínculo AR ← Venda |
| `CODNF_ENTRADA` (FK NF_ENTRADA) | `purchase_id` (FK transactions purchase) | vínculo AP ← Compra |
| `CODFINANCEIRO_GRUPO` | `payment_group_id` (FK — agrupa parcelas relacionadas) | mesma venda gera grupo |
| `CODCONDICAOPAGTO` | `payment_terms_id` | parcelado X vezes |
| `CONDICAOPAGTO` (texto desnorm) | derivado | |
| `CODTIPOPAGTO` | `payment_method_id` (FK) — boleto/dinheiro/cheque/PIX/cartão |
| `TIPOPAGTO` (texto) | derivado | |
| `CODUSUARIO` (lançador) | `created_by` (FK users) |
| `CODUSUARIO_CONTA` (responsável conta) | `account_user_id` (FK users) |
| `CODEMPRESA` | `business_id` (multi-tenant Tier 0) |
| `AGRUPADOR` (integer) | `payment_batch_id` (lote conciliação) | |

### 10.3 Boleto / Cheque (campos satélites)

| Delphi | Laravel |
|--------|---------|
| `CHEQUE_NUMERO` | `check_number` |
| `BOLETO_NOSSO_NR` | `boleto_our_number` (linha 75 — campo opcional INSERT) |
| `BOLETO_LINHA_DIGITAVEL` | `boleto_barcode` |
| `BOLETO_VENCTO_*`, `BOLETO_VALOR_*` | colunas específicas boleto |
| `WS_RETORNO_STATUS`, `WS_ULTIMA_CONSULTA` | tracking webservice retorno |

→ Cobertura via `Modules/RecurringBilling` + `Modules/Financeiro` no oimpresso (já existe).

### 10.4 RAZAOSOCIAL/CONTATOS denormalizados

| Delphi | Laravel | Notas |
|--------|---------|-------|
| `RAZAOSOCIAL` | derivado via JOIN `contact.name` | denormalizado no Delphi pra ler rápido |
| `CONTATOS` | `contact_persons` (text — pessoas pra cobrar) | denormalizado também |

Em Laravel não denormalizar — JOIN no SELECT é trivial.

## 11. Serviços críticos

### 11.1 `ServicesFinanceiro_Lancamento_Financeiro` (INSERT principal — 115 LOC)

Recebe ~30 parâmetros, cria 1 row em FINANCEIRO. Usado por:
- Venda quando confirma (gera N parcelas AR)
- Compra quando finaliza (gera N parcelas AP)
- Lançamento manual (modal "Nova Receita" / "Nova Despesa")
- Recebimento parcial (gera linha `RECEBIDA` parcial + atualiza original)

### 11.2 `ServicesFinanceiro_Lancamento_Financeiro_Historico`

Variante append-only em `FINANCEIRO_HISTORICO` — auditoria de alterações.

### 11.3 `ServicesFinanceiro_Boleto_GerarBoleto`

Gera boleto bancário a partir de linha FINANCEIRO. Integra com webservice banco (`ServicesFinanceiro_Boleto_RegistraRetornoDoWS`).

### 11.4 `ServicesFinanceiro_DuplicaContasPagas` / `DuplicaContasAReceber`

Duplica linhas pré-existentes (legado de tabela antiga `CONTASAPAGAR` — linhas 974-1010). **Não migrar** — usa tabela legacy não-canônica.

### 11.5 `ServicesFinanceiro_AtualizaSaldo` / `AtualizaSaldoCompleto`

Recalcula `FINANCEIRO_SALDO` por conta/dia. `AtualizaSaldoCompleto` reseta tudo (operação custosa).

### 11.6 `ConciliarContas` (Controller.Financeiro.pas:626-662)

Multi-seleção no grid → marca `DT_CONCILIADO` em lote. Transação por linha.

## 12. Configurações por business

Não há `InitializeConfig` explícito em Controller.Financeiro.pas — defaults espalhados em UI e Service. Configs relevantes (inferidas de uso):

| Config | Descrição |
|--------|-----------|
| `FINANCEIRO_BLOQUEAR_LANCAMENTO_CAIXA_FECHADO` | bloqueio em caixa fechado (linha 870-876) |
| `FINANCEIRO_DT_COMPETENCIA_PADRAO` | fonte padrão competência (`pagto`/`emissao`/`vencto`) |
| `FINANCEIRO_CALC_JUROS_AUTOMATICO` | calcula juros automático se atraso |

## 13. Tabelas relacionadas (17+ no ecossistema Financeiro)

| Tabela | Propósito |
|--------|-----------|
| `FINANCEIRO` | mestre — 80+ colunas |
| `FINANCEIRO_HISTORICO` | append-only audit log |
| `FINANCEIRO_SALDO` | snapshots saldo diário por conta |
| `FINANCEIRO_GRUPO` | agrupa parcelas relacionadas (mesmo `CODFINANCEIRO_GRUPO`) |
| `FINANCEIRO_BOLETO` | tracking boleto bancário |
| `FINANCEIRO_BOLETO_HISTORICO` | histórico estados boleto |
| `CONTAS` | contas bancárias/caixa |
| `CONTAS_PLANOCONTAS` | plano de contas (hierárquico) |
| `CENTRO_CUSTO` | centros de custo (hierárquico) |
| `CONDICAOPAGTO` | condições parcelamento |
| `TIPOPAGTO` | métodos pagamento (boleto/dinheiro/PIX/etc) |
| `CHEQUES` | cheques recebidos/emitidos |
| `CAIXA` | abertura/fechamento caixa diário |
| `CAIXA_ABERTURA` / `CAIXA_FECHAMENTO` | sessões caixa |
| `RECEBIMENTO` | recebimentos AR (1 recebimento → N parcelas baixadas) |
| `RECEBIMENTO_FINANCEIRO` | N:N entre RECEBIMENTO e FINANCEIRO |
| `BOLETO_RETORNO_WS` | logs webservice banco |

## 14. UI da Lista Financeiro — colunas típicas (inferidas do código)

| Coluna | Campo Delphi | Mapeamento Laravel |
|--------|--------------|---------------------|
| Código | `F.CODIGO` | `id` |
| Tipo | `F.TIPO` | `type` enum |
| Razão Social | `F.RAZAOSOCIAL` (denorm) | via JOIN contact |
| Documento | `F.DOCUMENTO` | `document_no` |
| Histórico | `F.HISTORICO` | `description` |
| Plano Contas | via JOIN `PC.DESCRICAO` | via JOIN |
| Centro Custo | via JOIN `CC.DESCRICAO` | via JOIN |
| Conta | via JOIN `C.DESCRICAO` | via JOIN |
| Valor | `F.VALOR` | `amount` |
| Juros | `F.JUROS` | `interest_amount` |
| Desconto | `F.DESCONTO` | `discount_amount` |
| Emissão | `F.EMISSAO` | `issued_at` |
| Vencimento | `F.VENCTO` | `due_date` |
| Data Pagto | `F.DATAPAGTO` | `paid_at` |
| Competência | `F.DT_COMPETENCIA` | `competence_date` |
| Conciliado | `F.DT_CONCILIADO` | `reconciled_at` |
| Parcela | `F.PARCELA` (`1/3`) | `installment_label` |
| Status | `F.STATUS` | derivado |
| NF | `F.NOTAFISCAL` | `nfe_number` |
| Usuário | `U.USUARIO` (lançador) via JOIN | via JOIN |
| Empresa | `E.RAZAOSOCIAL` via JOIN | (via business_id) |
| Boleto | `F.BOLETO_NOSSO_NR` | `boleto_our_number` |

## 15. Recomendações de implementação na ordem

| Ordem | Etapa | O que mudar |
|-------|-------|-------------|
| 1 | Schema | Avaliar se manter modelo UltimatePOS `transaction_payments` (vinculado a transactions) ou criar **`financial_entries` standalone** equivalente ao FINANCEIRO Delphi. **Decisão crítica** — afeta toda arquitetura |
| 2 | (P0) View "A Receber" + "A Pagar" | Filtro por TIPO + paginação obrigatória (não fazer `select *` em 59k rows) |
| 3 | (P0) Filtros 4-checkbox | A Receber / Recebida / A Pagar / Paga (multi) |
| 4 | (P0) Datas dropdown header | 6 datas filtráveis: emissão / vencimento / pagamento / competência / NF / conciliação |
| 5 | (P0) Agrupamento dinâmico | groupBy por: Plano Contas, Centro Custo, Conta, Usuário Conta, Pessoa |
| 6 | (P0) Service `RegisterFinancialEntry` | Espelha `ServicesFinanceiro_Lancamento_Financeiro` — 30 params, regra cascata `competence_date` |
| 7 | (P0) Conciliação bulk | Multi-select grid → marca `reconciled_at` lote |
| 8 | (P1) Saldo diário | Tabela `account_balance_daily` + job recalc — análogo `FINANCEIRO_SALDO` |
| 9 | (P1) Validação caixa aberto | Block lançamento `paid_at` em data com caixa fechado |
| 10 | (P2) Boleto WS | Trazido por `Modules/RecurringBilling` |
| 11 | (P2) Cheque | Cadastro + tracking cheques recebidos/emitidos |
| 12 | (P2) Histórico append-only | `financial_entries_history` (audit log) |

## 16. Erros corrigidos por este mapping

| Erro anterior | Causa | Correção |
|---------------|-------|----------|
| FINANCEIRO é apenas AR | UI separa AR/AP | É **single ledger** com `TIPO IN (A RECEBER, RECEBIDA, A PAGAR, PAGA)` — mesma tabela |
| Subclasse AReceber tem SQL diferente | Esperar JOIN específico | É **`select B.* from FINANCEIRO B`** — todas colunas, filtro só por TIPO em runtime |
| Boleto é tabela separada | OO mindset | Boleto = **campos extras em FINANCEIRO** + tabela auxiliar `FINANCEIRO_BOLETO` pra workflow WS |
| `DT_COMPETENCIA` é obrigatório | Contabilidade | É **opcional** — cascata: explícito → DataPagto → Emissão (linha 949-954) |
| `RAZAOSOCIAL` é só FK | Normalizado | **Denormalizado** no Delphi — campo VARCHAR copiado pra evitar JOIN em listagens. Em Laravel JOIN é trivial, não denormalizar |
| UltimatePOS `transaction_payments` é equivalente | Naming similar | `transaction_payments` é **vinculado a transaction**; Delphi FINANCEIRO é **standalone ledger**. Diferença arquitetural fundamental — decisão a tomar |

## 17. Refs

- [TELA-LISTA-VENDAS.md](TELA-LISTA-VENDAS.md) — referência de formato
- [TELA-COMPRA.md](TELA-COMPRA.md) — fonte de AP via Finalização
- [TELA-PESSOAS.md](TELA-PESSOAS.md) — fornecedor (AP) e cliente (AR)
- `Modules/Financeiro/` (oimpresso) — visão unificada AR/AP já em produção
- `Modules/RecurringBilling/` — boletos + assinaturas
- UltimatePOS schema: `transaction_payments`, `account_transactions`, `accounts`, `expense_categories`

---
**Última atualização:** 2026-05-11 — mapping canônico via Controllers Delphi (source-first). Cliente grande tem 59k+ linhas em FINANCEIRO — modelo single-ledger é decisão arquitetural não trivial pra migração Laravel.
