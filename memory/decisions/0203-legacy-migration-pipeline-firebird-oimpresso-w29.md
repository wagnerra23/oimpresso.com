---
slug: 0203-legacy-migration-pipeline-firebird-oimpresso-w29
number: 203
title: "Pipeline legacy-migration Firebird → oimpresso completo (Wave 29-1)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-26"
module: officeimpresso
quarter: 2026-Q2
tags: [legacy-migration, officeimpresso, martinho, firebird, idempotence]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0019-officeimpresso-delphi-nao-autentica", "0093-multi-tenant-isolation-tier-0", "0118-segregacao-dominios-externos-clientes-legacy"]
pii: false
review_triggers: []
---

# ADR 0203 — Pipeline legacy-migration Firebird → oimpresso completo (Wave 29-1)

## Contexto

CYCLE-06 (Martinho prod) exigiu migração end-to-end Delphi WR Comercial →
oimpresso MySQL pro cliente Martinho Caçambas (biz=164). O pipeline
`scripts/legacy-migration/` em estado anterior cobria só CONTAS + EMPRESA +
CONTACTS + VENDAS + FINANCEIRO. Faltava PRODUTOS, VENDA_PRODUTO
(transaction_sell_lines), NOTA_FISCAL (nfe_emissoes) e enrich de campos
ricos (CUSTO, ESTOQUE, MARGEM, categorias, tabelas de preço).

Servidor `servidor-crm` (Martinho online) é Firebird 3.0.12 com WireCrypt
configurado de forma incompatível com `firebird-driver` Python 2.0.3 — driver
moderno falha com "Unable to complete network request". Solução existente
(`fdb` legacy) funciona mas precisava ser default formal.

PHP `OfficeimpressoImporterService` (Wave 28-4) cobria 4 tabelas core (sem
FINANCEIRO/NFe). Pra paridade com o pipeline Python e suporte futuro a
import via Laravel command, precisava extensão.

## Decisão

Wave 29-1 entrega o pipeline completo:

1. **Novos importers Python** (idempotência via legacy_id ou UK + INSERT IGNORE):
   - `import-produtos.py` — PRODUTO → products (UK natural `sku`=CODIGO Delphi)
   - `import-venda-itens.py` — VENDA_PRODUTO → transaction_sell_lines (UK
     `uk_tsl_dup_prevent (transaction_id, product_id, variation_id)` criada)
   - `import-notas-fiscais.py` — NOTA_FISCAL → nfe_emissoes (UK fiscal
     `(biz, modelo, serie, numero)` + `(biz, transaction_id)`)
   - `enrich-produtos.py` — UPDATE retroativo variations (preços, margem),
     variation_location_details (estoque), products (alert_quantity)
   - `enrich-produtos-completo.py` — categorias (PRODUTO_GRUPO →
     categories), tabelas de preço (PRODUTO_PRECO → selling_price_groups +
     variation_group_prices), flags (not_for_selling)

2. **`migrar-tudo.py` orquestra 8 steps** em ordem de dependência FK:
   contas → empresas → contacts → produtos → vendas → venda-itens →
   financeiro → notas-fiscais. Flags `--skip-*` por step.

3. **`lib/firebird_reader.py`** auto-detecta driver `fdb` (default — compat
   FB 2.5/3.0) → fallback `firebird-driver`. Auto-aponta `fb_client_library`
   pra DLL 3.0 oficial + auto-set env `FIREBIRD` pra
   `fbclient-config/firebird.conf` custom (WireCrypt=Disabled +
   AuthClient=Srp256,Srp,Legacy_Auth) — resolve incompat servidor 3.0.12.

4. **PHP `OfficeimpressoImporterService`** ampliado com `importFinanceiros`
   + `importNotasFiscais` (paridade com pipeline Python; idempotência via
   metadata.legacy.codigo + UK; mock fallback CI sem ext pdo_firebird).

5. **Anti-duplicação** instituída em prod biz=164 após detecção de 8.832
   contacts dups (formatos de CNPJ diferentes coexistindo — `XX.XXX.XXX/YYYY-ZZ`
   com máscara vs `XXXXXXXXYYYYZZ` digits-only):
   - `UPDATE contacts SET tax_number = REGEXP_REPLACE(tax_number, '[^0-9]', '')`
   - Remap FK transactions.contact_id + fin_titulos.cliente_id antes do DELETE
   - UK `uk_contacts_biz_tax (business_id, tax_number)` criada

## Justificativa

- **`fdb` como default** (não `firebird-driver`): empiricamente o único que
  conecta no servidor Martinho 3.0.12 sem reconfigurar WireCrypt; suporta
  toda a base FB 2.5+ → 4.0. Override via `FIREBIRD_PY_DRIVER=firebird-driver`
  pra quem quiser API moderna.
- **firebird.conf custom em vez de modificar instalação global**: não requer
  permissão admin no Windows + facilmente versionado no repo.
- **UK `uk_tsl_dup_prevent`** em `transaction_sell_lines` (não tinha UK
  prévia): permite `INSERT IGNORE` idempotente sem SELECT prévio em cada
  row — escala melhor em batches de 1000+.
- **UK `uk_contacts_biz_tax`**: previne recorrência do problema de 2
  formatos coexistindo (legacy migration antiga sem normalizar vs
  import-contacts-from-venda.py com digits-only).

## Consequências

**Positivas:**
- Pipeline COMPLETO Delphi → oimpresso (8 tabelas core)
- Idempotência garantida por UK em todas as tabelas críticas
- Conexão Firebird funciona zero-config em qualquer cliente Martinho-like
- Validado em prod biz=164: 9.938 contacts (após dedup 8.832), 3.809
  products + 3.794 variations, 44.018 transactions, 83.044 fin_titulos +
  71.675 baixas, 176+ nfe_emissoes

**Negativas / Trade-offs:**
- `fdb` é driver legacy oficial mas não recebe features novas — quando o
  oimpresso virar 100% FB 4.0+, migrar pra `firebird-driver`
- UK `uk_tsl_dup_prevent` impede 2 linhas de items pra mesma (tx, prod,
  variation) — se um caso de uso futuro precisar disso (ex: items em
  parcelas), terá que dropar UK + criar variations distintas
- enrich-produtos-completo hardcoda nomes "MECÂNICA" e "FABRICANTE" pra
  PRODUTO_PRECO.TIPO 1 e 2 (padrão Martinho v1404) — outros clientes
  precisam adapter

**Riscos mitigados:**
- Cross-tenant leak (ADR 0093): todos INSERTs setam `business_id`
- Re-run duplicação: UKs + INSERT IGNORE + UPSERT via legacy_id
- One-way bridge (ADR 0019): FirebirdConnector + driver Python = read-only

## Referências

- ADR 0019 — Officeimpresso/Delphi NÃO autentica (one-way bridge)
- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0118 — Segregação domínios externos (accounts_legacy_map)
- CYCLE-06 — Martinho prod
- PR #1765 — feat(legacy-migration): pipeline COMPLETO Firebird → oimpresso
