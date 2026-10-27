---
last_validated: "2026-10-27"
slug: runbook-entrada-xml
title: "RUNBOOK — /compras/entrada-xml (conferência da NF-e do fornecedor)"
type: runbook
module: Compras
page: /compras/entrada-xml
component: resources/js/Pages/Compras/EntradaXml.tsx
status: rascunho
updated_at: "2026-10-27"
version: "0.1"
owner: W
---

# RUNBOOK — `/compras/entrada-xml`

> US-COM-003 · ADR `proposals/2026-10-06-entrada-xml-compra.md` (fase 4) · desenho: aba Entradas de
> `prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx` (`TrEntradas`, UC-TRB-18). Tela nova, não é
> migração de Blade.

## 1. Quando esta tela quebra (sintomas)

- `/compras/entrada-xml` responde **404** → a flag `nfebrasil.entrada_xml_enabled` (`NFEBRASIL_ENTRADA_XML`) está desligada. É o padrão.
- **403** → usuário sem a permissão `compras.import_xml` (em `/roles/{id}/edit`).
- Lista vazia → nenhuma nota com `nfe_dfe_recebidos.transaction_id` nulo na empresa. A busca por NSU depende do CNPJ e do certificado (#9873); o envio do arquivo não depende.
- "Esta nota foi emitida para outro CNPJ" → o destinatário do XML não é o CNPJ da empresa (`CnpjDoEmitente`). Recusa de propósito (Tier 0).
- "Vincule estes itens…" → item sem variação vinculada nem lembrada em `compras_produto_fornecedor`.
- "Os totais da nota não fecham" → Σ vProd − vDesc + vFrete + IPI/ST/seguro/outras ≠ vNF.
- "No regime normal…" → `nfe_business_configs.regime` fora de simples/mei; regra de crédito ainda não existe.

## 2. Fluxo

1. Envia o XML (ou a nota chega pela SEFAZ) → `ImportarXmlEntradaService` grava em `nfe_dfe_recebidos` com os itens.
2. Clica a nota na lista → a conferência mostra os itens no desenho do protótipo (CFOP de entrada proposto, CST com "ST retida", ICMS destacado, produto vinculado ou campo para vincular; sugestão por EAN igual ao SKU, nunca automática).
3. Escolhe o local e clica **Gerar compra** → `ImportarDfeComoCompraService` cria a compra recebida pelas peças do `/purchases` e grava o vínculo.

## 3. O que esta tela NÃO faz (ainda)

- Não cria fornecedor nem produto (R-COM-202 e "produto quando não existe" seguem pendentes na US-COM-003).
- Não avisa NCM divergente: o produto não guarda NCM hoje.
- Não grava o CFOP de entrada; ele é só exibido.

## 4. Teste

- Contrato: `Modules/Compras/Tests/Feature/EntradaXmlTelaContratoTest.php` (lane `compras-pest`).
- Casos: `resources/js/Pages/Compras/EntradaXml.casos.md`.
