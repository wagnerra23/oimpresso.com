---
title: "Entrada por XML de compra — de onde vem o XML, quem é dono, de-para lembrado e o que é fiscal"
status: proposto
date: "2026-10-06"
owners: [W]
proposed_by: Claude Code (playbook Fiscal, thread 11 · decisão [W] D-ENTRADA)
parent_module: NfeBrasil
related_adrs: [93, 116, 143]
related_specs:
  - memory/requisitos/NfeBrasil/SPEC.md (US-NFE-049 · US-NFE-051)
  - memory/requisitos/Compras/SPEC.md (US-COM-003 · R-COM-201..206)
  - prototipo-ui/cowork/Wagner/fiscal-tributacao.casos.md (UC-TRB-18 · UC-TRB-12)
related_charters: []
---

# Entrada por XML de compra

> **Status: `proposto`.** Não muda código. Ratificação é do [W] (D-ENTRADA, `_DECISOES-W-2026-10-06.md`:
> *"projeto com ADR primeiro — o código vem depois da ratificação"*). Implementa **UC-TRB-18**
> (entrada por XML) e **UC-TRB-12** (importação com dólar), ambos `[proposta]` em
> `prototipo-ui/cowork/Wagner/fiscal-tributacao.casos.md`.

## 1 · O que existe hoje (medido em 2026-10-06, `origin/main` `a8e0624504`)

| peça | onde | estado |
|---|---|---|
| Contador "Entrada (XML)" na tela de notas | `resources/js/Pages/Fiscal/Nfe.tsx:196` | **literal `0`**; a aba mostra um vazio com *"Backlog F2 · depende de Modules/NfeBrasil expor o endpoint de importação"* (`:200-208`) |
| Busca de XML na SEFAZ por NSU | `Modules/NfeBrasil/Services/Manifestacao/DistribuicaoDfeService.php` (US-NFE-051) | existe; o parser de `procNFe` grava em `nfe_dfe_itens` só `ncm · cfop · descricao · quantidade · valor_unitario · valor_total` (`:311-326`) |
| Itens da DF-e | `Modules/NfeBrasil/Database/Migrations/2026_05_09_100001_create_nfe_dfe_itens_table.php` | **sem** `produto_id_match`, apesar do aceite da US-NFE-049 prometê-lo; sem código do item do fornecedor (`cProd`), sem `cEAN`, sem unidade, sem CST/CSOSN, sem ICMS destacado |
| Ponte DF-e → compra | US-COM-003 (`memory/requisitos/Compras/SPEC.md:115-130`) — `ImportarDfeComoCompraService`, `nfe_dfe_recebidos.transaction_id`, permissão `compras.import_xml` | **`_pendente_`**; nem a coluna nem o serviço existem |
| Importador de XML legado | `app/Http/Controllers/PurchaseXmlController.php` · `app/Http/Controllers/NfeEntradaController.php` | **sem rota** (`git grep` em `routes/` e `Modules/*/Routes`: 0 ocorrência). Código morto; não ressuscitar |

**Produção (Hostinger, só `COUNT`, 2026-10-06 17:44 pelo relógio do MySQL):**
`nfe_dfe_recebidos` = **0** · `nfe_dfe_itens` = **0** · `nfe_dfe_nsu_state` = **1** (um business com cursor) ·
coluna `nfe_dfe_recebidos.transaction_id` = **não existe** · compras (`transactions.type='purchase'`) nos últimos 90 dias = **23**.

Leitura honesta: **a fonte automática (DF-e) nunca entregou um documento em produção.** A entrada por XML
não tem dado acumulado esperando; ela começa do zero. Por que o NSU não trouxe nada **não foi medido**
aqui (fora do escopo desta thread) e precisa de dono antes da fase 2 — ver §6.

## 2 · Prior art (fora do repo)

- **Bling** — *Estoque › Notas fiscais de entrada › Importar XML NF-e*; para lançar estoque *"o produto da
  nota precisa estar vinculado ao produto do sistema"*, e o vínculo produto↔fornecedor é cadastro
  próprio (importável por planilha). ([importar XML](https://ajuda.bling.com.br/hc/pt-br/articles/360036460513-Como-importar-o-XML-de-nota-de-entrada) ·
  [conciliar produtos](https://ajuda.bling.com.br/hc/pt-br/articles/360044990973-Como-conciliar-os-produtos-da-nota-com-produtos-do-meu-estoque) ·
  [fornecedores vinculados](https://ajuda.bling.com.br/hc/pt-br/articles/1500004346181-Importar-fornecedores-vinculados-aos-produtos))
- **Odoo** — o mesmo conceito se chama *vendor pricelist* / `product.supplierinfo`, com o campo
  **Vendor Product Code** por fornecedor. ([módulo de import](https://apps.odoo.com/apps/modules/12.0/sh_import_supplier_info))

O nome da técnica, portanto, é **cadastro produto × fornecedor (código do fornecedor)**. O de-para desta
ADR é esse cadastro, alimentado na primeira vez que o item aparece — não um mecanismo novo.

## 3 · Decisão proposta

### D1 · Dono: Compras cria a compra, NfeBrasil lê o XML
- **NfeBrasil** é dono de **ler** o XML (DF-e por NSU e upload passam pelo **mesmo parser**), gravar
  em `nfe_dfe_recebidos`/`nfe_dfe_itens` e fazer as **conversões fiscais** (CFOP, CST, crédito).
- **Compras** é dono de **transformar em compra**: é a US-COM-003 que já existe. A compra nasce como
  `transactions.type='purchase'` pelo fluxo de `/purchases` (convergência C1), e estoque e
  título a pagar vêm dos mecanismos que já cuidam disso hoje — esta ADR não cria caminho paralelo.
- A aba "Entrada (XML)" do Fiscal (`Nfe.tsx:191-208`) passa a contar `nfe_dfe_recebidos` do business
  (fim do literal `0`) e a apontar para a importação em Compras.
- Os dois controllers legados sem rota **não voltam**.

### D2 · Fonte do XML: DF-e manifestado **e** upload
- **DF-e**: o que já chega por NSU.
- **Upload** de arquivo `.xml`: entra pelo mesmo parser e grava em `nfe_dfe_recebidos` com a origem
  marcada. Aceita só quando **o CNPJ do destinatário do XML é o do business** e a chave de 44 dígitos
  não existe ainda para aquele business (o UNIQUE `(business_id, chave_44)` já existe). XML de outro
  CNPJ é recusado — é a regra Tier 0 do multi-tenant (ADR 0093) aplicada à entrada.

### D3 · De-para lembrado, por business e fornecedor
- Tabela nova com `business_id` (indexado + FK), CNPJ do fornecedor, **código do item no fornecedor**
  (`cProd`) e o **`variation_id`** do produto (não `product_id`: no UltimatePOS o estoque é por variação).
  Único por `(business_id, cnpj_fornecedor, codigo_fornecedor)`.
- `nfe_dfe_itens` ganha as colunas que faltam para o de-para e para o fiscal: `cProd`, `cEAN`, unidade,
  CST/CSOSN, ICMS destacado, e o `variation_id` casado (o `produto_id_match` prometido pela US-NFE-049,
  com o tipo certo).
- Item sem vínculo **não vira compra**: pede o de-para uma vez; a próxima nota do mesmo fornecedor já
  vem vinculada. Sugestão por `cEAN` igual a um código de barras cadastrado é permitida; vínculo
  automático silencioso não.

### D4 · Conversões fiscais — a máquina propõe, a pessoa confirma
- **CFOP**: tabela de espelho saída → entrada (5→1 · 6→2 · 5405→1403 · importação 3102), guardada no
  NfeBrasil, **cada linha com o dispositivo legal citado literal** (lei 4 do módulo). A citação é
  trabalho da implementação; esta proposta não afirma o artigo.
- **NCM divergente** (XML × produto): avisa na linha; **não troca** o NCM do produto.
- **CST 060 / ST retida**: marca o item da entrada; mudar o produto para sair com **CSOSN 500** é
  **sugestão com confirmação**, nunca automático (mesma postura do NCM e da Jana, D-IA).
- **Custo × crédito por regime**: no Simples, o ICMS destacado vai para o **custo** (sem crédito); no
  regime normal, o ICMS recuperável fica **fora** do custo. Isto mexe em valor → a implementação cai na
  **regra mestre de cálculo de valor/estoque** (`memory/proibicoes.md`): dupla prova + antes→depois + [W].

### D5 · Importação (UC-TRB-12) entra nesta ADR, mas é outro fluxo
- Na importação **não há XML de fornecedor**: quem emite a nota de entrada (CFOP 3102) é o próprio
  importador. É **emissão**, logo depende da lane 🔴 do motor (D-MOTOR, threads 06/07) e da emissão por
  item (thread 17).
- Regras, como o protótipo (aba Importação) e o UC-TRB-12 dizem: valor aduaneiro = (FOB + frete +
  seguro) × câmbio **da data de registro da DI/DUIMP**; II, IPI, PIS/COFINS-importação e ICMS "por
  dentro"; o **câmbio fica gravado na nota**; variação cambial posterior vai ao financeiro e **nunca
  recalcula o imposto**. Os mesmos requisitos de lei citada (lei 4) e de valor (regra mestre) valem.

## 4 · Fases (cada uma é PR próprio; 🔴 vai sozinho)

| fase | entrega | depende |
|---|---|---|
| 0 | Descobrir por que o NSU nunca trouxe documento em produção (medição, sem código) | — |
| 1 🔴 | Migration: colunas em `nfe_dfe_itens` + tabela de de-para + `nfe_dfe_recebidos.transaction_id` (R-COM-205) e origem; Pest de isolamento biz | ratificação |
| 2 | Parser grava as colunas novas; upload de XML com a recusa por CNPJ do destinatário | 1 |
| 3 🔴 | `ImportarDfeComoCompraService` (US-COM-003): de-para, CFOP, custo × crédito, compra via fluxo existente; dupla prova de valor | 2 · tabela CFOP com lei |
| 4 | Tela: aba Entradas (alvo `fiscal-tributacao.jsx` `TrEntradas`) + contador do Fiscal derivado | 3 |
| 5 🔴 | Importação 3102 | 3 · threads 06 · 07 · 17 |

## 5 · O que esta ADR **não** decide
Manifestação (ciência/confirmação) continua da US-NFE-050; SPED de entradas (Bloco C de entrada) fica
para depois; NFS-e tomada e CT-e de frete ficam fora.

## 6 · Decisões que são do [W]

1. **D1** — dono Compras (cria compra) + NfeBrasil (lê e converte), reaproveitando a US-COM-003. Sim/não?
2. **D2** — aceitar **upload** além do DF-e. Recomendo sim: com 0 documento vindo pelo NSU, o upload é
   hoje a única fonte que funciona.
3. **D4** — CSOSN 500 só como **sugestão confirmada**. Recomendo sim.
4. **D5** — importação nesta ADR, como fase 5 dependente do motor. Ou ADR própria?
5. **Fase 0 tem dono?** Sem ela, a fonte DF-e continua seca e o recurso dependerá só do upload.
