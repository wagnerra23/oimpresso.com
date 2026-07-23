---
id: research-clientes-legacy-officeimpresso-mapping-tela-lista-vendas
title: Mapping canônico — Tela "Lista de Vendas" Delphi → oimpresso.com
status: live
date: 2026-05-11
audience: dev migrando OfficeImpresso → oimpresso.com novo
source_files:
  - "D:/Programas/WR Comercial/app/Controller/Controller.Venda.pas (4010 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Venda.Venda.pas (165 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Mestre.pas (3444 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Venda.Definicoes.pas (254 LOC)"
method: source-first (skill officeimpresso-source-analysis)
---

# Mapping canônico — Tela "Lista de Vendas" Delphi

> **Fonte autoritativa:** Controllers Delphi reais. Substitui inferências por evidência. Cumpre [skill officeimpresso-source-analysis](../../../.claude/skills/officeimpresso-source-analysis/SKILL.md).
> **Cliente alvo do screenshot inicial Wagner 2026-05-11:** tela `Caption := 'Venda'` (não Orçamento, NF, Pedido ou PDV — embora compartilhem mesmo Controller pai).

## 1. Cadeia de herança

```
TObject
  └─ TControllerMestre              (Controller.Mestre.pas, 3444 LOC)
      └─ TControllerVenda           (Controller.Venda.pas, 4010 LOC) — SQL base + JOIN
          └─ TControllerVendaVenda  (Controller.Venda.Venda.pas, 165 LOC) — filtros/grupos da TELA "Venda"
          └─ TControllerVendaOrcamento
          └─ TControllerNotaFiscal
          └─ TControllerVendaPedido
          └─ TControllerVendaPDV
```

`Path` no menu = `PathVENDA` (constante em `wrConstantes.pas`).

## 2. SQL base (DEFINITIVO — extraído do constructor)

```sql
SELECT
  P.CODIGO, P.SEQUENCIA, P.PESSOA_RESPONSAVEL_CODIGO, P.RAZAOSOCIAL,
  P.TOTAL, P.PESSOA_RESPONSAVEL_SEQUENCIA,
  P.DT_EMISSAO, P.TELEFONE, P.VENDA_TIPO, P.DT_FATURAMENTO,
  P.NOTAFISCAL, P.CONDICAOPAGTO, P.ATIVO, P.RESPONSAVEL_UF,
  P.STATUS, P.PROJETO_DT_FIM, P.CONTATO, P.MOTORISTA_DOCUMENTO_NUMERO,
  P.NF_DT_EMISSAO, P.SITUACAOFINANCEIRA, P.VENDA_ESTAGIO, P.IS_PDV,
  P.PEDIDO_REP, P.CODVENDA, P.SITUACAO, P.IS_VENDA, P.IS_NOTAFISCAL,
  P.IS_ORCAMENTO, P.DT_COMPETENCIA, P.CODVENDA_VINCULADA,
  P.FATURA_PREVISAO, P.IS_FATURAMENTO_CANCELADO,
  P.CODVENDA_PRE_VENDA, P.OBSERVACAO,
  P.PESSOA_FUNCIONARIO_CODIGO, P.PESSOA_REPRESENTANTE_CODIGO,
  P.PESSOA_AGENCIA_CODIGO, P.VDESC, P.VOUTRO, P.NF_VFRETE,
  P.CODIGOVENDA, P.TOTAL_FATURA, P.PEDIDO_COMPRA, P.FATURAMENTO_DT_ENVIO,

  -- JOINs essenciais (LEFT JOIN — todas opcionais)
  EV.PLACA, EV.CHASSI, EV.PLACA2, EV.CHASSI2,    -- veículo (oficina)
  PG.DESCRICAO  as CLIENTE_GRUPO,                 -- grupo do cliente
  PR.RAZAOSOCIAL as REPRESENTANTE,                -- vendedor representante
  PA.RAZAOSOCIAL as AGENCIA,                      -- agência intermediadora
  PJ.DESCRICAO  AS PROJETO,                       -- projeto (obra/serviço)
  PV.RAZAOSOCIAL as FUNCIONARIO,                  -- funcionário responsável
  C.CNPJCPF, C.FANTASIA, C.FATURA_PREVISAO AS PREVISAO_CLIENTE  -- cliente principal
FROM VENDA P
LEFT JOIN EQUIPAMENTO_VEICULO EV ON (EV.CODIGO = P.PLACA)         -- nota: P.PLACA é FK pra EQUIPAMENTO!
LEFT JOIN PESSOAS C  ON (P.PESSOA_RESPONSAVEL_CODIGO = C.CODIGO)
LEFT JOIN PESSOAS_GRUPO PG ON (C.CODPESSOAS_GRUPO = PG.CODIGO)
LEFT JOIN PESSOAS PV ON (P.PESSOA_FUNCIONARIO_CODIGO = PV.CODIGO)
LEFT JOIN PESSOAS PR ON (P.PESSOA_REPRESENTANTE_CODIGO = PR.CODIGO)
LEFT JOIN PESSOAS PA ON (P.PESSOA_AGENCIA_CODIGO = PA.CODIGO)
LEFT JOIN VENDA_TIPO VT ON (VT.DESCRICAO = P.VENDA_TIPO)
LEFT JOIN PROJETO PJ ON (PJ.CODIGO = P.CODPROJETO)
```

**Total: 47 colunas selecionadas, 8 JOINs** — todas LEFT JOIN, nenhum INNER.

🎯 **Descoberta crítica:** `P.PLACA` é **FK pra `EQUIPAMENTO_VEICULO.CODIGO`**, não a string da placa em si. O texto da placa vem via JOIN. Isso explica por que probes diretos em VENDA não retornavam placa — preciso ler via JOIN com EQUIPAMENTO_VEICULO.

## 3. Filtros padrão (6 — `InitializeFiltros` em `TControllerVendaVenda`)

| Botão UI | Cláusula SQL |
|----------|--------------|
| **Retirar filtros** (default) | `P.STATUS LIKE 'ATIVO%'` |
| **Arquivados** | `Not (P.STATUS LIKE 'ATIVO%')` |
| **Vendas sem Nota** | `not(P.DT_FATURAMENTO is null) and (P.NOTAFISCAL is null) and (P.STATUS LIKE 'ATIVO%')` |
| **Vendas A Receber** | `(P.SITUACAOFINANCEIRA = 'Em Aberto') and (P.STATUS LIKE 'ATIVO%')` |
| **Vendas Faturadas** | `(not P.DT_FATURAMENTO is null) and (P.STATUS LIKE 'ATIVO%')` |
| **A Faturar** | `(P.DT_FATURAMENTO is null) and (P.STATUS LIKE 'ATIVO%')` |

> O filtro "Retirar filtros" é counter-intuitive — não significa "sem filtro algum", significa "voltar ao default que é `STATUS LIKE 'ATIVO%'`". A semântica é "ativas + comportamento normal".

## 4. Agrupadores padrão (6 — `InitializeAgrupadores`)

| Botão UI | Campo SQL | Tipo |
|----------|-----------|------|
| **Retirar agrupadores** | (vazio) | — |
| **Situação** | `SITUACAO` | editável simples |
| **Funcionário** | `FUNCIONARIO` (via JOIN) | editável simples |
| **Razão Social** | `RAZAOSOCIAL` | editável simples |
| **Tipo de Venda** | `VENDA_TIPO` | editável simples |
| **Situação Financeira** | `SITUACAOFINANCEIRA` | **visual** (badge colorido) |

Agrupador "Situação Financeira" tem `kmVisual` — render diferente (cor/badge), os outros são `kmEditavelSimples` (texto inline).

## 5. Datas filtráveis (7 — `InitializeDatasNaConsulta`)

Esta é a lista do dropdown "Personalizado · Data" do screenshot. **Corrigida 2026-05-11 com fonte autoritativa:**

| Label UI | Campo SQL | Descoberta heatmap (% preenchido em 5 bancos) |
|----------|-----------|------|
| **Última Alteração** | `DT_ALTERACAO` | WR2 92% · Vargas 99.9% · Extreme 100% · Gold 100% (universal) |
| **Emissão NF** | `NF_DT_EMISSAO` | WR2 8.7% · Vargas **24.7%** · Extreme 0.2% · Gold 0.6% · Martinho **50.7%** |
| **Emissão** | `DT_EMISSAO` | universal ~100% |
| **Dt. Faturamento** | `DT_FATURAMENTO` | WR2 52.7% · Vargas 35.0% · Extreme **92.9%** · Gold **92.4%** · Martinho 47.4% |
| **Dt Env. Faturamento** | `FATURAMENTO_DT_ENVIO` | WR2 49.6% · Vargas 0.0% · Extreme **89.1%** · Gold 0.0% · Martinho 47.4% |
| **Dt. Competência** | `DT_COMPETENCIA` | WR2 18.6% · Vargas **100%** · Extreme 75.3% · Gold 7.5% |
| **Dt. Prometido** | **`PROJETO_DT_FIM`** ⚠️ | WR2 8.0% · Vargas 0.4% · **Extreme 91.4%** · Gold 6.2% · Martinho 0.0% |

🚨 **Correções de heatmaps v1/v2/v3:**
1. "Dt. Prometido" é **`PROJETO_DT_FIM`** (não `DT_PROMETIDO`). Coluna existe em todos clientes, não só em Gold como inferi antes.
2. **Extreme é o cliente que usa "Dt. Prometido" massivamente (91.4%)** — não Gold. Inversão completa da conclusão anterior.
3. Cada cliente OfficeImpresso tem perfil **distinto** de qual data importa:
   - Vargas (recapagem caminhão): Competência 100% (obrigação contábil) + Emissão NF 24.7%
   - Extreme (gráfica industrial PCP): Prometido 91.4% + Faturamento 92.9% + Envio Faturamento 89.1% — fluxo completo industrial
   - Gold (comunicação visual): Faturamento 92.4% — fluxo simples
   - Martinho (caçambas): Emissão NF 50.7% + Faturamento 47.4% — fluxo balanceado
   - WR2: misto, fraco (toy)

## 6. Permissões aplicadas (`VerificaPermissoesUsuarioConsulta`)

WHERE adicional aplicado dinamicamente baseado no usuário logado:

```pascal
// 1. Filtra tipos de venda que o usuário NÃO pode ver
if FSQLFiltroNegarTipoVendaUsuario <> '' then
  ASQLWhere.AddAnd('not (P.VENDA_TIPO in (' + FSQLFiltroNegarTipoVendaUsuario + '))');

// 2. Se usuário NÃO tem permissão "visualizar todas empresas",
//    só vê vendas dele
if not PermissaoVisualizarTodasEmpresas then
  ASQLWhere.AddAnd('P.PESSOA_FUNCIONARIO_CODIGO = ' + QuotedStr(Usuario.CodigoFuncionario));

// 3. Se modo MultiEmpresa ativo + usuário não privilegiado,
//    filtra por empresa via sufixo no CODIGO
if MultiEmpresa and not PodeVisualizarTodasEmpresas then
  // empresa 1: vê suas + as sem sufixo (legado)
  // empresas 2+: vê só as do sufixo
```

→ Lógica equivalente no Laravel oimpresso.com:
- `business_id` global scope (já existe — Tier 0 ADR 0093)
- Permission `sell.view_all` vs `sell.view_own`
- (Não há equivalente direto pra "VENDA_TIPO negar" — é granularidade extra que precisa modelar)

## 7. Valores padrão (`Controller.Venda.Definicoes.pas` — gerado auto)

Em INSERT de nova venda:

| Campo | Default | Equivalente Laravel |
|-------|---------|---------------------|
| `ATIVO` | `'S'` | (não usar — vira soft delete via `deleted_at`) |
| `INTERVALO_MENSAL` | `'N'` | descartar (feature legacy) |
| `PESSOA_RESPONSAVEL_TIPO` | `'CLI'` | `contacts.type = 'customer'` |
| `STATUS` | `'ATIVO'` | `transactions.status = 'final'` |
| `DT_EMISSAO` | **`@SERVIDOR`** (timestamp servidor) | `transactions.transaction_date = now()` |
| `PROJETO_DT_INICIO` | `@DATA` | descartar (não usar projeto direto) |
| `DT_COMPETENCIA` | `@DATA` | `transactions.competence_date` (nullable) |
| `OPERACAO` | `'EM VENDA'` | `transactions.type = 'sell'` |
| `PODE_RATEAR_FRETE_DESC_OUTRO` | `'S'` | feature avançada — descartar V1 |
| `PODE_ATUALIZAR_CADASTRO` | `'S'` | feature avançada — descartar V1 |
| `NF_OBSERVACAO_PADRAO` | `'S'` | `nfe_emissoes.use_default_obs` |
| `NF_NUMERO` | `1` | calculado por NFeBrasil |
| `NF_FRETEPORCONTA` | `0` | `nfe.frete_modalidade` |
| `SERVICO_NOTA_PADRAO` | `'S'` | NFSeBrasil |
| `EMITE_NFE` | `'N'` | `business.emite_nfe` ✓ |
| `EMITE_NFSE` | `'N'` | `business.emite_nfse` ✓ |
| `REGIME_TRIBUTARIO` | `'1'` (Simples Nacional) | `business.regime_tributario` ✓ |
| `CRT` | `'1'` | (mesmo) |

## 8. Validações principais

### 8.1 Sempre

- `RAZAOSOCIAL` — obrigatório

### 8.2 Quando `EMITE_NFE = 'S'`

- NFE_CERTIFICADO obrigatório
- NFE_SERIE obrigatório
- NFE_AMBIENTE deve ser 1 ou 2
- NFE_PATH obrigatório

### 8.3 Quando `EMITE_NFSE = 'S'`

- NFSE_CERTIFICADO obrigatório
- INSCRICAO_MUNICIPAL obrigatório
- CODIGO_TRIBUTACAO_MUNICIPIO obrigatório (regex `^[0-9]{1,20}$`)
- CODIGO_IBGE_MUNICIPIO obrigatório (7 dígitos)
- CNAE_PRINCIPAL obrigatório (7 dígitos)
- REGIME_TRIBUTARIO entre 1-4
- NFSE_REGIME_ESPECIAL entre 0-6
- NFSE_INCENTIVO_FISCAL 1 ou 2
- NFSE_EXIGIBILIDADE_ISS entre 1-7
- NFSE_AMBIENTE 1 ou 2
- NFSE_PATH obrigatório
- NFSE_SERIE obrigatório

### 8.4 Quando EMITE_NFSE=S + tem produto/serviço

- ALIQUOTA_ISS obrigatório, range 0-5%
- CODIGO_SERVICO_LC116 obrigatório, até 5 dígitos

→ `Modules/NfeBrasil` e futuro `Modules/NFSe` já cobrem boa parte. Faltam: regime especial, incentivo fiscal, exigibilidade ISS (campos pendentes em `nfe_certificados` ou `business_settings`).

## 9. Mapping de campos Delphi → Laravel/oimpresso

### 9.1 Core (sempre migrar)

| Delphi `VENDA.*` | Laravel | Notas |
|-------------------|---------|-------|
| `CODIGO` (varchar) | `transactions.id` (bigint AI) | preservar Delphi original em `legacy_id` |
| `DT_EMISSAO` | `transaction_date` | obrigatório |
| `DT_ALTERACAO` | `updated_at` | timestamps Laravel |
| `RAZAOSOCIAL` | `contacts.name` (via FK) | denormalização Delphi vs FK Laravel |
| `PESSOA_RESPONSAVEL_CODIGO` | `contact_id` (FK) | — |
| `TOTAL` | `final_total` | — |
| `VENDA_TIPO` | `transaction_type` ou `type` | mapeamento de strings → enum |
| `STATUS` (string `'ATIVO'`/`'INATIVO'`) | `status` enum + soft delete | semântica dual |
| `SITUACAO` | `production_status` (novo campo) | usado em Gold/Martinho |
| `SITUACAOFINANCEIRA` | derivado de `payment_status` | `'Em Aberto'`, `'Quitada'`, `'A Fatura'` |
| `IS_PDV` / `IS_VENDA` / `IS_NOTAFISCAL` / `IS_ORCAMENTO` | enum `transactions.subtype` | flags exclusivos |
| `CODVENDA_VINCULADA` | `parent_transaction_id` | venda agrupada (US-SELL-024) |
| `CODVENDA_PRINCIPAL` | mesmo conceito | usado em parcial |
| `OBSERVACAO` | `notes` (text) | — |
| `IS_FATURAMENTO_CANCELADO` | `invoice_cancelled` (bool) | — |

### 9.2 Datas (7 campos — US-SELL-021)

| Delphi | Laravel | Migração |
|--------|---------|----------|
| `DT_EMISSAO` | `transaction_date` | obrigatório |
| `DT_ALTERACAO` | `updated_at` | nativo Laravel |
| `NF_DT_EMISSAO` | `nfe_emissoes.issued_at` | FK |
| `DT_FATURAMENTO` | `invoiced_at` (novo) | nullable |
| `FATURAMENTO_DT_ENVIO` | `invoice_sent_at` (novo) | nullable |
| `DT_COMPETENCIA` | `competence_date` (novo) | nullable, default = transaction_date |
| **`PROJETO_DT_FIM`** ⚠️ | **`due_date`** (novo) | nullable — semântica = "Dt. Prometido" |

### 9.3 Comercial

| Delphi | Laravel |
|--------|---------|
| `PESSOA_FUNCIONARIO_CODIGO` | `salesman_id` (FK users) |
| `PESSOA_REPRESENTANTE_CODIGO` | `representative_id` |
| `PESSOA_AGENCIA_CODIGO` | `agency_id` |
| `CONDICAOPAGTO` | `payment_terms_id` |
| `VDESC` (valor desconto) | `discount_amount` |
| `VOUTRO` (outros desconto) | `other_discount` |
| `NF_VFRETE` | `shipping_amount` |
| `PEDIDO_COMPRA` | `purchase_order_ref` (string) |
| `MOTORISTA_DOCUMENTO_NUMERO` | `driver_document_number` (carrego) |

### 9.4 Oficina (EQUIPAMENTO_VEICULO via JOIN)

| Delphi `EQUIPAMENTO_VEICULO.*` | Laravel `vehicles.*` | Notas |
|--------------------------------|----------------------|-------|
| `CODIGO` | `id` | — |
| `PLACA` | `plate` | obrigatório quando vertical=oficina |
| `PLACA2` | `secondary_plate` | nullable — cavalo+reboque (Vargas) |
| `CHASSI` | `chassis` | nullable |
| `CHASSI2` | `secondary_chassis` | nullable — cavalo+reboque |
| `ANO_FABRICACAO`/`ANO_MODELO` | `manufacture_year`/`model_year` | — |
| `RENAVAN` | `renavam` | — |
| `MOTOR` | `engine` | — |
| `KM` | `mileage_at_entry` | snapshot momento OS |

**FK em VENDA:** `P.PLACA` é integer (FK pra `EQUIPAMENTO_VEICULO.CODIGO`), não string da placa. → Laravel: `transactions.vehicle_id` FK pra `vehicles`.

### 9.5 Fiscal (NFe/NFSe)

Já coberto por `Modules/NfeBrasil` em boa parte. Gaps:
- `NF_NATUREZA_OPERACAO` (texto) + `NF_CODNATUREZA_OPERACAO` (FK)
- Validações regime especial / incentivo fiscal / exigibilidade ISS (NFSe)

### 9.6 Multi-empresa (escopo cross-business)

Delphi tem padrão de **sufixo no CODIGO** (`V-1`, `V-2`, etc) pra distinguir empresas no mesmo banco. → Laravel: usar `business_id` global scope ([ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md)) — pattern moderno bem superior.

## 10. UI da Lista de Vendas — colunas visíveis (do screenshot Wagner)

Coluna por coluna do grid Delphi mostrado:

| Coluna | Campo Delphi | Vem do SQL base? | Migração Laravel |
|--------|--------------|------------------|------------------|
| (checkbox) | (UI multi-select) | n/a | nova feature |
| Código | `P.CODIGO` | ✓ | `id` |
| Pedido Principal | `P.CODVENDA_PRINCIPAL` (não no SELECT — buscar) | ❌ adicionar | `parent_transaction_id` |
| Emissão | `P.DT_EMISSAO` | ✓ | `transaction_date` |
| Nota Fiscal | `P.NOTAFISCAL` | ✓ | `nfe_emissoes.numero` |
| Dt. Faturamento | `P.DT_FATURAMENTO` | ✓ | `invoiced_at` |
| Codigo Original | (não claro qual campo) | a investigar | — |
| Razão Social | `P.RAZAOSOCIAL` | ✓ | `contact.name` |
| Fantasia | `C.FANTASIA` (via JOIN) | ✓ | `contact.fantasy_name` |
| Telefone | `P.TELEFONE` | ✓ | `contact.phone` |
| Situação Financeira | `P.SITUACAOFINANCEIRA` | ✓ | derivado `payment_status` |
| R$ Total | `P.TOTAL` | ✓ | `final_total` |
| Tipo | `P.VENDA_TIPO` | ✓ | `transaction_type` |
| Status | `P.STATUS` | ✓ | `status` enum |
| Condição de Pagamento | `P.CONDICAOPAGTO` | ✓ | `payment_terms` |
| Dt. Prometido | **`P.PROJETO_DT_FIM`** | ✓ | `due_date` |
| Placa | `EV.PLACA` (via JOIN) | ✓ | `vehicle.plate` |
| Placa 2 | `EV.PLACA2` | ✓ | `vehicle.secondary_plate` |
| Chassi | `EV.CHASSI` | ✓ | `vehicle.chassis` |
| Chassi 2 | `EV.CHASSI2` | ✓ | `vehicle.secondary_chassis` |
| Funcionário | `PV.RAZAOSOCIAL as FUNCIONARIO` | ✓ | `salesman.name` |
| Contato | `P.CONTATO` | ✓ | `transaction.contact_name` (denormalized) |
| Pedido Representante | `P.PEDIDO_REP` | ✓ | `representative_order` |
| Representante | `PR.RAZAOSOCIAL as REPRESENTANTE` | ✓ | `representative.name` |
| Pedido de Compra | `P.PEDIDO_COMPRA` | ✓ | `purchase_order_ref` |
| CNPJ/CPF | `C.CNPJCPF` | ✓ | `contact.cnpj` ou `contact.cpf` |
| Situação | `P.SITUACAO` | ✓ | `production_status` (novo) |
| Referente a | (não claro) | a investigar | — |
| Codigo Original | (FK do projeto?) | a investigar | — |

→ Total: **30+ colunas** no grid. Grade Avançada (US-SELL-015) já cobre. **US-SELL-027 schema discovery** precisa detectar quais dessas colunas o cliente USA (CONFIGURACOES_GRID — investigar em outro mapping).

## 11. Sub-totalizador rodapé (`r$ 16.763.317,54` no screenshot)

Delphi calcula em runtime no grid via `cxGridDBTableView` sum aggregation. Quando filtrar, atualiza. **Não é SQL** — é UI client-side.

→ Laravel: query `SELECT SUM(final_total), COUNT(*) FROM transactions WHERE <filtros>` cobre. Já vai na **US-SELL-017 (totalizador rodapé)** P0.

## 12. Produtos sub-linha (visível em algumas OS no screenshot)

Vem de `VENDA_PRODUTO` (filha de VENDA). 17 tabelas relacionadas:
- `VENDA_PRODUTO` (itens da venda)
- `VENDA_PRODUTO_ETAPA` (PCP — quais etapas o item passa)
- `VENDA_PRODUTO_CENTRO_TRABALHO` (qual máquina/centro)
- `VENDA_PRODUTO_FORNECEDOR`
- `VENDA_PRODUTO_BAIXA_AUTOMATICA`
- ... (12 outras)

→ **US-SELL-022 sub-linha produtos** = expandir linha → mostra produtos via JOIN. P2 atualmente (média 1.3-3 itens/venda).

## 13. Bridge oimpresso.com (Delphi → cloud sync)

Delphi tem `Controller.OImpresso.pas` com `SincronizarVendas(Sender)` que POST as VENDAS pra `/api/oimpresso/vendas` no oimpresso.com novo. **Migration model "Asaas-like"** viável (cliente continua usando Delphi + ganha cloud em paralelo).

→ ADR futura formalizando esse padrão.

## 14. Recomendações de implementação na ordem

| Ordem | US | O que mudar |
|-------|----|-----|
| 1 | US-SELL-021 (P0) | Header dropdown 7 datas: campos exatos acima. Default = `transaction_date` |
| 2 | US-SELL-015 (P0) | Toggle Lista/Grade — 30+ colunas mapeadas acima |
| 3 | US-SELL-027 (P0/P1) | Discovery: ler `CONFIGURACOES_GRID` Firebird + popular `business.legacy_origin_features` |
| 4 | US-SELL-017 (P0) | Totalizador rodapé — SUM(final_total) por filtro |
| 5 | US-SELL-016 (P0) | Multiseleção + bulk print |
| 6 | US-SELL-019 + 024 (P1) | Agrupamento — 5 agrupadores default acima |
| 7 | US-SELL-023 (P1) | Badge status produção (SITUACAO + SITUACAOFINANCEIRA distintos) |
| 8 | US-SELL-018 (P1) | Filtros multi-data: 6 filtros default acima |
| 9 | US-SELL-028 (P1) | Modelo Veiculo multi-placa (Vargas + Martinho) |
| 10 | US-SELL-022 (P2) | Sub-linha produtos via JOIN VENDA_PRODUTO |

## 15. Refs

- [.claude/skills/officeimpresso-source-analysis/SKILL.md](../../../../.claude/skills/officeimpresso-source-analysis/SKILL.md) — método usado
- [memory/research/clientes-legacy-officeimpresso/_COMO-ANALISAR.md](../_COMO-ANALISAR.md) — metodologia 3 camadas
- [memory/requisitos/Sells/SPEC.md](../../../requisitos/Sells/SPEC.md) — US-SELL-015..028
- [memory/decisions/0136-sells-grade-avancada-modo-toggle.md](../../../decisions/0136-sells-grade-avancada-modo-toggle.md) — split Lista/Grade
- [memory/research/2026-05-sells-grade-heatmap/HEATMAP-CONSOLIDADO.md](../../2026-05-sells-grade-heatmap/HEATMAP-CONSOLIDADO.md) — agora **corrigir** com sinais reais Dt.Prometido (Extreme 91.4%, não Gold 6.2%)

## 16. Erros corrigidos por este mapping

| Erro anterior | Causa | Correção |
|---------------|-------|----------|
| "DT_PROMETIDO" só em Gold | Heatmap buscou coluna inexistente em VENDA | É **`PROJETO_DT_FIM`** — universal |
| Extreme não usa Dt. Prometido | Mesmo motivo | Extreme **usa 91.4%** — caso paradigmático de gráfica industrial com prazo |
| Gold é cliente comvis com prazo | Inferi por DT_PROMETIDO; mas Gold tem só 6.2% PROJETO_DT_FIM | Gold **não controla prazo** estruturalmente — fluxo simples |
| Vargas é gráfica + frota | Sinal multi-vertical mal interpretado | Já corrigido v3 — é oficina recapagem (CHASSI2/PLACA2 = cavalo+reboque dos caminhões dos clientes) |
| `P.PLACA` é string da placa | Assumi coluna texto direta | É **FK integer pra EQUIPAMENTO_VEICULO.CODIGO** — texto via JOIN |

---

**Última atualização:** 2026-05-11 noite — mapping canônico via Controllers Delphi (source-first). Substitui inferências anteriores. Próximo passo: ler `Controller.Pessoas.pas`, `Controller.Compra.pas`, `Controller.Financeiro.pas` pelo mesmo método pra mapping completo de migração.
