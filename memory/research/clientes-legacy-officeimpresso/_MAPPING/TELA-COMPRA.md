---
id: research-clientes-legacy-officeimpresso-mapping-tela-compra
title: Mapping canônico — Tela "Compra / Nota de Entrada" Delphi → oimpresso.com
status: live
date: 2026-05-11
audience: dev migrando OfficeImpresso → oimpresso.com novo
source_files:
  - "D:/Programas/WR Comercial/app/Controller/Controller.Compra.pas (187 LOC — wrapper vazio com lógica histórica em comentários)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.NF_Entrada.pas (1474 LOC — controller real)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.NF_Entrada.Definicoes.pas (52 LOC — auto-gerado)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Compra.Estoque.pas (42 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Compra.Manifestacao.pas (125 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.NF_Entrada_Produtos.pas (itens)"
method: source-first (skill officeimpresso-source-analysis)
---

# Mapping canônico — Tela "Compra / Nota de Entrada" Delphi

> **Fonte autoritativa:** Controllers Delphi reais. Substitui inferências.
> **Descoberta importante:** `Controller.Compra.pas` é **wrapper vazio** — toda lógica fica em `Controller.NF_Entrada.pas`. A tabela mestre é **`NF_ENTRADA`**, não "COMPRA".

## 1. Cadeia de herança

```
TObject
  └─ TControllerMestre              (Controller.Mestre.pas)
      └─ TControllerNf_Entrada      (Controller.NF_Entrada.pas, 1474 LOC) — SQL base + finalização + cancelamento + manifestação SEFAZ
          ├─ Controller.Compra.Estoque       (apenas redirect tela)
          ├─ Controller.Compra.Manifestacao  (manifestação destinatário NFe)
          └─ Controller.NF_Entrada_Manifesto (3 satélites: MDe, NSU, Requisição)
```

`Caption := 'Compra'`, `Tabela := 'NF_ENTRADA'`, `Path := PathNF_ENTRADA`, `Modulo := MODULO_COMPRAS`.

🎯 **Naming confuso:** UI fala "Compra", tabela diz "NF_ENTRADA", arquivo wrapper diz "Compra". Em Laravel manter **um nome só**: `purchases` (UltimatePOS já usa).

## 2. SQL base (DEFINITIVO — `TControllerNf_Entrada.Create` linhas 121-131)

```sql
SELECT
  N.TIPO, N.NF_CHAVE, N.DATA, N.CODIGO, N.NUN_NF, N.TOTAL,
  N.DT_NOTA, N.ATUALIZA_ESTOQUE, N.GERA_FINANCEIRO, N.QUANTIDADE,
  N.ATIVO, N.DT_FATURAMENTO, N.OBSERVACAO, N.STATUS, N.SITUACAO,
  N.PESSOA_RESPONSAVEL_CODIGO, P.RAZAOSOCIAL as RESPONSAVEL,
  N.CODUSUARIO, U.USUARIO AS USUARIO,
  N.CODEMPRESA, E.RAZAOSOCIAL AS EMPRESA,
  N.CODUSUARIO_ESTOQUE, U2.USUARIO as USUARIO_ESTOQUE
FROM NF_ENTRADA N
LEFT JOIN PESSOAS P ON (N.PESSOA_RESPONSAVEL_CODIGO = P.CODIGO)
LEFT JOIN USUARIO U  ON (N.CODUSUARIO = U.CODIGO)
LEFT JOIN EMPRESA E  ON (N.CODEMPRESA = E.CODIGO)
LEFT JOIN USUARIO U2 ON (N.CODUSUARIO_ESTOQUE = U2.codigo)
```

**Total: ~22 colunas, 4 JOINs (todos LEFT).**
"P" alias = fornecedor; "U" = usuário lançador; "U2" = usuário responsável por movimentar estoque; "E" = empresa.

## 3. Filtros padrão (`InitializeFiltros` linhas 221-227 — DESABILITADO em runtime, herda do Mestre)

```pascal
procedure TControllerNf_Entrada.InitializeFiltros;
begin
  inherited;
  // Default herdado de TControllerMestre:
  //   '-Filtros Rápidos-' / 'ATIVO = ''S'''
  //   'Arquivados' / 'Not (ATIVO = ''S'')'
end;
```

Filtros adicionais aplicados em `ConsultaGetFiltros`:

```pascal
// Multi-empresa (linhas 390-403)
if (ComboEmpresa.Text <> '-Selecione uma empresa-') then
begin
  ACondicao := '(N.CODIGO like ''%-'+IntToStr(CodEmpresa)+''')';
  if Empresa.CODIGO = 1 then
    ACondicao := '(' + ACondicao + ' or not (N.CODIGO like ''%-%''))';
  SQLWhere.AddAnd(ACondicao);
end;
```

Padrão: empresa 1 vê suas + legado sem sufixo; empresas 2+ veem só por sufixo `CODIGO like '%-N'`. **Idêntico ao mesmo padrão de VENDA** — multi-empresa via sufixo em `CODIGO`.

## 4. Datas filtráveis (3 — `InitializeDatasNaConsulta` linhas 213-219)

| Label UI | Campo SQL |
|----------|-----------|
| **Data** | `DATA` — data do lançamento no sistema |
| **Dt. Nota** | `DT_NOTA` — data da NFe emitida pelo fornecedor |
| **Dt. Faturamento** | `DT_FATURAMENTO` — data que a compra foi finalizada/contabilizada |

> Apenas 3 datas (vs 7 na Venda) — fluxo mais simples. Compra **não tem competência separada** no Delphi, só DATA + DT_NOTA + DT_FATURAMENTO.

## 5. Valores padrão (`Controller.NF_Entrada.Definicoes.pas`)

```pascal
.AdicionarValorPadrao('ATIVO', 'S')
.AdicionarValorPadrao('GERA_FINANCEIRO', 'N')          // default NÃO gera AP automático
.AdicionarValorPadrao('ATUALIZA_ESTOQUE', 'N')         // default NÃO atualiza estoque
.AdicionarValorPadrao('TIPO', 'NOTA FISCAL')
.AdicionarValorPadrao('DATA', '@DATA')                  // data atual
.AdicionarValorPadrao('DT_NOTA', '@DATA')               // data atual
.AdicionarValorPadrao('ENVIA_FINANCEIRO', 'S')         // toggle UI — diferente de GERA_FINANCEIRO!
.AdicionarValorPadrao('ENVIA_ESTOQUE', 'S')
.AdicionarValorPadrao('ENVIA_PRECO', 'S')
.AdicionarValorPadrao('PODE_RATEAR_FRETE_DESC_OUTRO', 'N')
```

🎯 **Descoberta sutil:** existem 2 pares de flags duplicados:
- `GERA_FINANCEIRO` (N) vs `ENVIA_FINANCEIRO` (S) — sugere que `GERA_*` é estado pós-finalização e `ENVIA_*` é toggle UI ("vai mandar pro financeiro quando finalizar?")
- `ATUALIZA_ESTOQUE` (N) vs `ENVIA_ESTOQUE` (S) — mesmo padrão

Migração: pode unificar em um campo só (`will_generate_payable`, `will_update_stock`) + `dt_faturamento NOT NULL` indica "já gerou".

## 6. Validações (`Controller.NF_Entrada.Definicoes.pas` linhas 43-46)

| Campo | Regra | Mensagem |
|-------|-------|----------|
| `RAZAOSOCIAL` | obrigatorio | Razão Social é obrigatória |
| `ATIVO` | obrigatorio | Ativo é obrigatório |

Validações mínimas — a maior parte da lógica fica em `Service_FinalizarCompra` (linhas 168-185 do Controller.Compra.pas comentado, replicado em Controller.NF_Entrada.pas) executada no **botão Finalizar**:

```pascal
// ValidaRegras_FinalizacaoPelaCompra
if not ANF_Entrada.FieldByName('DT_FATURAMENTO').IsNull then
  raise EWRException.Create('A Compra já está Finalizada');

if AAtualizaFinanceiro and (ANF_Entrada_Parcelas.RecordCount = 0) then
  raise EWRException.Create('Você ainda não gerou as parcelas. Selecione a Condição de Pagamento correta e clique em "Gerar Parcelas".');

if AAtualizaEstoque and (ANF_Entrada_Produtos.RecordCount = 0) then
  raise EWRException.Create('Não há itens para serem enviados ao estoque');

// Total da NF deve bater com soma das parcelas (com tolerância 0.02)
if AAtualizaFinanceiro and (RoundTo(N.TOTAL,-2) <> RoundTo(SomaParcelas,-2)) then
  raise EWRException.Create('Valor da nota fiscal é "Superior/Inferior" a soma das parcelas');

// Caixa aberto + situação conta banco válida
TControllerContas.ValidarSituacaoContaBanco(ANF_Entrada_Parcelas, AQuerConta);
TControllerContas.VerificaAberturaDeCaixa(ANF_Entrada_Parcelas);
```

## 7. Geração de chave primária (`GeraChavePrimaria` linhas 135-141)

```pascal
ACadastro.FieldByname('CODIGO').Value :=
  Trunc(GetProximoCodigoGen('CR_NF_ENTRADA' + EmpresaAtiva));
// Comentado: ToString + '-'+ EmpresaAtiva
```

⚠️ **Inconsistência vs Venda/Pessoas:** o sufixo `-empresa` está **comentado** aqui — em compras o CODIGO é só o sequencial puro, sem sufixo. Mas o filtro multi-empresa (linha 399) procura `CODIGO like '%-N'` — sugere que **alguns clientes têm sufixo, outros não**, e o filtro funciona por presença/ausência. Verificar dado real antes de migrar.

`NUN_NF` (linha 267) = `CODIGO.Split('-')[0]` — número da NFe do fornecedor, parte antes do hífen do CODIGO.

## 8. Importação XML NFe (`ImportarXML` linhas 148-190)

Fluxo crítico — usuário escolhe XML, sistema:

1. Cria componente `TACBrNFe` (lib brasileira open-source)
2. `LoadFromFile(XML)` valida estrutura NFe
3. Abre `TFrmNF_Entrada` com Cadastro vazio
4. `DM_NFEntradaImportarNota(AACBrNFe)` preenche campos da NF + tenta criar/encontrar fornecedor (via `BuscaEImportaFornecedor` em `Controller.Pessoas.Fornecedor.pas`)

→ Laravel: usar `nfephp-org/sped-nfe` ou similar pra parse XML; service `ImportPurchaseFromNFeXml`. Reaproveita 80% do fluxo de **import NFe entrada** que `Modules/NfeBrasil` deve cobrir.

## 9. Mapping de campos Delphi → Laravel/oimpresso (`NF_ENTRADA` → `purchases`)

### 9.1 Core (sempre migrar)

| Delphi `NF_ENTRADA.*` | Laravel `transactions.*` (UltimatePOS) | Notas |
|------------------------|-------------------------------------------|-------|
| `CODIGO` | `id` (bigint AI) + `legacy_id` | preservar string original |
| `TIPO` (`NOTA FISCAL`/`COTACAO`/`PEDIDO_COMPRA`) | `type = 'purchase'` + `subtype` | UltimatePOS usa `type` único; precisa coluna `purchase_subtype` |
| `NF_CHAVE` (44 dígitos) | `nfe_access_key` (varchar 44) | chave acesso NFe fornecedor |
| `NUN_NF` | `ref_no` ou `invoice_no` | número NFe fornecedor |
| `DATA` | `transaction_date` | data lançamento sistema |
| `DT_NOTA` | `invoice_date` ou `supplier_invoice_date` (novo) | data emissão NFe fornecedor |
| `DT_FATURAMENTO` | `finalized_at` (novo) | timestamp finalização — NULL = rascunho |
| `TOTAL` | `final_total` | valor total NF |
| `QUANTIDADE` | (calculado via `purchase_lines.quantity` sum) | redundante |
| `OBSERVACAO` | `additional_notes` | text |
| `STATUS` (`ATIVO`/`ATIVO PRINCIPAL`/`INATIVO`) | `status` enum | inclui caso de cotação convertida |
| `SITUACAO` (`CONCLUÍDA`/`ABERTA`/etc) | `purchase_situation` (novo) | string livre |
| `ATIVO` (`S`/`N`) | soft delete via `deleted_at` | semântica dual |

### 9.2 Flags de comportamento

| Delphi | Laravel | Notas |
|--------|---------|-------|
| `ATUALIZA_ESTOQUE` (`S`/`N`) | `updates_stock` (bool) | estado pós-finalização |
| `GERA_FINANCEIRO` (`S`/`N`) | `generates_payable` (bool) | estado pós-finalização |
| `ENVIA_ESTOQUE` (`S`/`N`) | (UI flag — não persiste) | toggle pre-finalização |
| `ENVIA_FINANCEIRO` (`S`/`N`) | (UI flag) | toggle pre-finalização |
| `ENVIA_PRECO` (`S`/`N`) | `updates_product_price` (bool) | se atualiza tabela de preços baseado no custo da NF |
| `PODE_RATEAR_FRETE_DESC_OUTRO` (`S`/`N`) | `can_distribute_shipping_discount` (bool) | distribui frete/desc nos itens |

### 9.3 Relacionamentos

| Delphi | Laravel |
|--------|---------|
| `PESSOA_RESPONSAVEL_CODIGO` | `contact_id` (FK `contacts` — fornecedor) |
| `CODUSUARIO` | `created_by` (FK `users`) |
| `CODUSUARIO_ESTOQUE` | `stock_finalized_by` (novo, FK `users`) |
| `CODEMPRESA` | `business_id` (multi-tenant Tier 0) |
| `CODNF_ENTRADA` (auto-FK para vinculação) | `parent_purchase_id` | cotação convertida vincula original |

### 9.4 Tabelas filhas (1-N a partir de NF_ENTRADA)

| Tabela Delphi | Laravel | Propósito |
|---------------|---------|-----------|
| `NF_ENTRADA_PRODUTOS` | `purchase_lines` | itens da compra |
| `NF_ENTRADA_PARCELAS` | `transaction_payments` (com type=AP) | parcelas a pagar geradas |
| `NF_ENTRADA_PRODUTOS_AFETADOS` | (transient — calculado on-the-fly) | produtos que tiveram preço/custo alterado |
| `NF_ENTRADA_TABELA_PRECO` | (vínculo `selling_price_groups`) | tabelas de preço atualizadas |
| `NF_ENTRADA_MANIFESTACAO` | `nfe_manifestations` (novo) | manifestação SEFAZ destinatário |

### 9.5 Configurações por business (`InitializeConfig`)

```pascal
NF_VENCIMENTO_PARCELAS_COM_BASE_DATA_NOTA (checkbox)
  → vencimento parcelas conta a partir de DT_NOTA (não DATA)
ATUALIZA_PRODUTO_FINAL (checkbox)
  → após compra, propaga custo pro preço de venda
NF_ENTRADA_ULTIMO_NSU (text)
  → último NSU SEFAZ baixado em manifesto destinatário
DIR_NOTA_DE_IMPORTACAO (windows directory)
  → pasta de NF-e XMLs pra importação batch
```

→ Laravel: `business_settings.purchase_due_date_basis` (`note_date`/`entry_date`), `business_settings.purchase_updates_sell_price` (bool), `business_settings.last_nsu_downloaded` (string), `business_settings.nfe_import_directory` (path).

## 10. Service_FinalizarCompra (fluxo de negócio)

Procedure exposed em `Controller.NF_Entrada.pas` (após linha 56). Quando o usuário clica "Finalizar":

1. Validações (item 6)
2. **Cria parcelas no FINANCEIRO** via `InsertFinanceiro_CriaPacoteDeParcelas` — gera N rows em FINANCEIRO com `TIPO='A PAGAR'`, `CODNF_ENTRADA=<id>`, vinculadas ao fornecedor
3. **Atualiza estoque** via `DoAtualizaEstoque` — gera movimentações em `ESTOQUE_MOVIMENTACAO` (cada produto, qtd, custo unitário)
4. **Atualiza preços** via `DM_ProdutoCriaOuAtualizaCadastro` — se `ENVIA_PRECO='S'`, recalcula preço de venda baseado em fórmula (custo + margem + impostos)
5. **Preenche `DT_FATURAMENTO = current_timestamp`** — marca como finalizada (idempotência)
6. **Solicita requisição** se houver produtos pendentes via `Controller_SolicitaRequisicaoPelaCompra`

**Cancelamento** (`Service_CancelarFinalizacaoCompra`): inverte tudo — `DoCancelaFinanceiro` apaga rows FINANCEIRO, `DoCancelarEstoque` zera movimentações, limpa `DT_FATURAMENTO`.

→ Laravel: services `FinalizePurchaseService` + `CancelPurchaseFinalizationService` em `Modules/<Mod>`. Usa transação DB explícita. Idempotência via `finalized_at IS NULL` check.

## 11. Bridge com módulos correlatos

| Módulo Delphi (Controller.X.pas) | Função |
|----------------------------------|--------|
| `Controller.Requisicao.Compra` | Gera requisição de compra quando produto está com saldo baixo |
| `Controller.Compra.Estoque` | Listagem filtrada compras que afetam estoque |
| `Controller.Compra.Manifestacao` | Painel manifestação SEFAZ destinatário (4 estados: ciente / confirmar / desconhecimento / não realizar) |
| `Controller.Financeiro` | Gera contas a pagar (1-N parcelas) |
| `Controller.Estoque` | Atualiza saldo estoque pós-finalização |
| `Controller.Produto` | Recalcula custo médio ponderado |

→ Laravel equivalents (criar se não existir):
- `Modules/NfeBrasil/Services/ManifestSefazService`
- `Modules/Financeiro/Services/GenerateAccountsPayableFromPurchase`
- existing `app/Utils/PurchaseUtil` (UltimatePOS) cobre estoque + custo médio

## 12. Recomendações de implementação na ordem

| Ordem | US | O que mudar |
|-------|----|-------------|
| 1 | (novo) Schema | Adicionar `purchase_subtype` enum (NF/COTACAO/PEDIDO), `nfe_access_key`, `supplier_invoice_date`, `finalized_at`, `purchase_situation`, `parent_purchase_id`, `updates_product_price` (bool), `can_distribute_shipping_discount` (bool) |
| 2 | (novo) Filtros padrão | Filtro toggle "Pendentes finalização" (`finalized_at IS NULL`) + "Finalizadas" (`finalized_at IS NOT NULL`) — equivalente Delphi |
| 3 | (novo) Datas dropdown | 3 datas filtráveis: `transaction_date`, `supplier_invoice_date`, `finalized_at` |
| 4 | (novo) Import XML | Service `ImportPurchaseFromNFeXml` usando `nfephp-org/sped-nfe`; cria fornecedor se não existir |
| 5 | (novo) Finalização | `FinalizePurchaseService` com transação DB; gera AP + atualiza estoque + opcionalmente atualiza preços |
| 6 | (novo) Cancelamento | `CancelPurchaseFinalizationService` — inverso |
| 7 | (P2) Manifestação SEFAZ | painel separado com 4 ações + NSU tracking |
| 8 | (P2) Cotação → Compra | `ConvertQuotationToPurchaseService` — `Controller_ConverterCotacaoCompra` linhas 429-505 |

## 13. Erros corrigidos por este mapping

| Erro anterior | Causa | Correção |
|---------------|-------|----------|
| `Controller.Compra.pas` é o entrypoint | Nome do arquivo | É **wrapper vazio** — toda lógica em `Controller.NF_Entrada.pas` (1474 LOC) |
| Tabela = `COMPRA` | UI fala "Compra" | Tabela é **`NF_ENTRADA`** — manter `purchases` em Laravel pra consistência UltimatePOS |
| `GERA_FINANCEIRO` controla geração de AP | flag óbvia | Não — controla **estado final**. O comportamento desejado é controlado por `ENVIA_FINANCEIRO` (toggle UI) |
| Cotação é tabela separada | naming "COTACAO" | É **mesmo NF_ENTRADA + TIPO='COTACAO'** + conversão muda STATUS='ATIVO PRINCIPAL' |
| `NUN_NF` é número customer-facing | inferi sequencial | É a **parte antes do hífen do CODIGO** (`CODIGO.Split('-')[0]`) — número da NFe fornecedor |

## 14. Refs

- [TELA-LISTA-VENDAS.md](TELA-LISTA-VENDAS.md) — referência de formato
- [TELA-PESSOAS.md](TELA-PESSOAS.md) — Fornecedor é subclasse de Pessoa
- [TELA-FINANCEIRO.md](TELA-FINANCEIRO.md) — AP gerado pós-finalização
- UltimatePOS schema: `transactions` (type=purchase), `purchase_lines`, `transaction_payments`

---
**Última atualização:** 2026-05-11 — mapping canônico via Controllers Delphi (source-first). Confirma que Compra/NF_Entrada compartilha quase 100% do fluxo Venda invertido (geração AP em vez de AR; atualiza estoque entrada em vez de saída).
