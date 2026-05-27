---
title: Migração Financeiro Delphi/Firebird → oimpresso — Checklist canônico por cliente
status: canon
date: 2026-05-21
audience: time MCP (Maiara suporte / Felipe dev / Wagner aprovação)
purpose: Formulário canônico — 1 cópia preenchida por cliente legacy antes de abrir importer Python
related:
  - memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md
  - memory/reference/migracao-officeimpresso-pattern.md
  - memory/requisitos/Financeiro/MAIARA-GUIA-COLETA-CLIENTE.md
---

# Migração Financeiro Legacy → oimpresso — Checklist por cliente

> 1 cliente legacy = 1 checklist preenchido. Sem ele, importer Python aborta no pre-flight. Wagner aprova antes do cutover.

## Cabeçalho do cliente

| Campo | Valor |
|---|---|
| Nome cliente | _(preencher)_ |
| `business_id` oimpresso | _(preencher — ver `php artisan tinker` ou `business` table)_ |
| Path Firebird | _(ex `servidor-crm:D:\DadosClientes\<Cliente>\Dados\BANCO.FDB`)_ |
| Vertical | ☐ gráfica ☐ comércio ☐ oficina ☐ outro: _____ |
| Volume estimado `FINANCEIRO` | _(executar query 3.1 do schema doc)_ |
| Data target cutover | _(dd/mm/aaaa)_ |
| Responsável cliente | _(nome + telefone/email)_ |

---

## Tabela mestre — `FINANCEIRO` (Firebird) → `fin_titulos` + `fin_titulo_baixas`

A `FINANCEIRO` Delphi mistura 4 conceitos no mesmo registro. No oimpresso vira **2 tabelas**: `fin_titulos` (a-receber/a-pagar) + `fin_titulo_baixas` (recebido/pago).

| # | Campo Firebird | Vai pra (oimpresso) | Transformação | Dúvida pendente |
|---|---|---|---|---|
| 1 | `CODIGO` (PK) | `accounts_legacy_map.legacy_id` | `legacy_source='wr-comercial-delphi'` | — (default canon) |
| 2 | `RAZAOSOCIAL` PII | `fin_titulos.cliente_descricao` (fallback) | JOIN tenta `contacts.legacy_id`; sem match → string | Se nome diverge de `PESSOAS`, qual vence? |
| 3 | `VALOR` | `fin_titulos.valor_total` (22,4) | direto | Há `VALOR<=0` ou `NULL`? Descartar ou tratar como estorno? |
| 4 | `TIPO` | `fin_titulos.tipo` + gera-baixa-ou-não | `RECEBIDA`→titulo `receber`+baixa; `A RECEBER`→só titulo; `PAGA`→`pagar`+baixa; `A PAGAR`→só titulo | Outros valores além desses 4? |
| 5 | `STATUS` | filtro de import | só `'ATIVO'`; `'INATIVO'`→audit JSON | `INATIVO` = excluído pelo usuário? Confirmar |
| 6 | `EMISSAO` | `fin_titulos.emissao` | direto | NULL permitido? Fallback (`VENCTO - pay_term`)? |
| 7 | `VENCTO` | `fin_titulos.vencimento` | direto | NULL permitido? Como tratar? |
| 8 | `DATAPAGTO` | `fin_titulo_baixas.data_baixa` | só se TIPO RECEBIDA/PAGA | TIPO=RECEBIDA + DATAPAGTO NULL — bug ou regra? |
| 9 | `DOCUMENTO`+`NOTAFISCAL` | `fin_titulos.metadata` JSON | concat ou JSON | Cliente usa pra quê? Qual prioridade na UI? |
| 10 | `HISTORICO` | `fin_titulos.observacoes` | direto + PII redact se necessário | Texto livre tem CPF/telefone de terceiro? |
| 11 | `JUROS`/`MULTA`/`DESCONTO` | `fin_titulo_baixas.{juros,multa,desconto}` | só na baixa | Cliente pré-calcula juros antes do pagamento? |
| 12 | `CODPLANOCONTAS` | `fin_titulos.plano_conta_id` | requer migração `PLANO_CONTAS` antes | **Manter plano atual ou adotar padrão oimpresso (47 contas DCASP)?** |
| 13 | `CODCONDICAOPAGTO` | descartar / `metadata.condicao_pagto_legacy` | — | Preservar histórico por título ou só vigente? |
| 14 | `CODTIPOPAGTO` | `fin_titulo_baixas.meio_pagamento` (enum 9) | mapping int→enum | **Mapping CODTIPOPAGTO→enum por cliente — obrigatório** |
| 15 | `PARCELA` | `fin_titulos.parcela_numero` | direto | Há `PARCELA>1` órfãos sem pai? |
| 16 | `CODFINANCEIRO_GRUPO` | `fin_titulos.titulo_pai_id` | via legacy_map self-FK | Confirmar = parcelas mesma operação |
| 17 | `BOLETO_NOSSO_NR` | `transaction_boletos.*` (separado) | — | Migrar boletos liquidados ou só vivos? |
| 18 | `BOLETO_OCORENCIA` | `transaction_boletos.*` | — | Histórico CNAB ou só posição atual? |
| 19 | `CODNF_ENTRADA` | `fin_titulos.metadata.nf_entrada_id` | não-FK | NFs entrada migram pro Compras ou só referência? |
| 20 | `MOTIVO_EXCLUSAO`+`DT_EXCLUSAO` | descartar | — | Confirmar descarte (LGPD: cliente exige trilha?) |
| 21 | `PROVISORIO` | filtro de import | `'S'` descartar | Confirmar = previsão/orçamento não-confirmado |

---

## `MENSALIDADE_FINANCEIRO` → `fin_titulos` (origem='recurring')

| Campo | Vai pra | Dúvida |
|---|---|---|
| `CODIGO` | `accounts_legacy_map` | — |
| `CODMENSALIDADE` | `fin_titulos.metadata.contrato_id` | Modules/FinanceiroAvancado tem contrato recorrente? Confirmar Wagner |
| `VALOR` / `DT_VENCTO` / `DT_EMISSAO` | direto | — |
| `TIPOPAGTO` (string `'BOLETO'`/`'PIX'`) | `fin_titulo_baixas.meio_pagamento` | Valores que cliente usa? |
| `PESSOA_RESPONSAVEL_CODIGO` | `fin_titulos.cliente_id` via JOIN | — |
| `PLACA`/`MARCAMODELO`/`ANO` | descartar (vazamento schema auto) | Confirmar: vertical não usa |

---

## `CONTRATO` → `Modules/FinanceiroAvancado.subscription_contracts`

| Campo | Vai pra | Dúvida |
|---|---|---|
| `ATIVO` | filtro `'S'` | Importar `'N'` pra dashboard churn? |
| `VALOR` | `valor_mensal` | **62 contratos VALOR=NULL** — cliente preenche ou fallback? |
| `DT_INICIO`/`DT_FIM` | direto | `DT_FIM` NULL = indeterminado? |
| `CODPESSOA` | FK via legacy_map | — |

---

## `BOLETOS` → `transaction_boletos` + `fin_boleto_remessas`

| Campo | Vai pra | Dúvida |
|---|---|---|
| `CODBANCO` | `fin_contas_bancarias.banco_codigo` | **Lista bancos cliente + mapping pra `accounts` oimpresso** |
| `CARTEIRA` | `fin_contas_bancarias.carteira` | Carteira CNAB atual? Mudou recente? |
| `SITUACAO`/`OCORENCIA` | `fin_boleto_remessas.status` | CNAB 240 ou 400? |
| `DT_REMESSA`/`DT_RETORNO` | direto | Migrar boletos liquidados ou só vivos? |

---

## `PESSOAS` (329 cols) → `contacts` (core UltimatePOS)

Mapeamento campo-a-campo já validado em [migracao-officeimpresso-pattern.md](../../reference/migracao-officeimpresso-pattern.md) Fase 1. Dúvidas restantes:

| Campo | Vai pra | Dúvida |
|---|---|---|
| `TIPO` (1 char) | `contacts.type` | **Cada cliente legacy interpreta `'C'`/`'F'`/`'T'` diferente** — amostra obrigatória |
| `CNPJCPF` | `contacts.tax_number` | Normalizar (só dígitos) ou preservar formatação? |
| `BLOQUEADO='S'` | `contacts.is_active=0`? | Importar bloqueados com tag ou descartar? |
| `LIMITECREDITO` | `contacts.custom_field_1` ou ignorar | Cliente quer preservar limite ou recadastrar? |
| `CODCONDICAOPAGTO` | `pay_term_number`+`pay_term_type` | Mapping códigos→dias |
| `FONE1`/`FONE2`/`EMAIL` | `mobile`/`alternate_number`/`email` | Múltiplos contatos por pessoa (`PESSOAS_CONTATOS`)? |
| `ENDERECO` (livre) | parse pra `address_line_1`+`number` | Delphi não separa número — cliente normaliza antes? |

---

## Blocos de dúvidas consolidadas

### Bloco A — Plano de contas e categorização (cliente decide)
- [ ] Manter plano atual ou adotar padrão oimpresso (47 contas DCASP)?
- [ ] Mapping `CODTIPOPAGTO` legacy → enum oimpresso preenchido?
- [ ] Mapping `CODCONDICAOPAGTO` legacy → dias preenchido?
- [ ] Mapping `CODBANCO` legacy → `fin_contas_bancarias` preenchido?

### Bloco B — Dados sujos / lixo histórico (cliente decide)
- [ ] Confirmar descarte `STATUS='INATIVO'`?
- [ ] Confirmar descarte `PROVISORIO='S'`?
- [ ] CONTRATO com VALOR=NULL — cliente preenche ou usa fallback?
- [ ] Cleanup-first: títulos `VENCTO>365d sem boleto sem movimento`→write-off audit. Cliente concorda?

### Bloco C — Escopo temporal (cliente decide)
- [ ] Migrar TODO histórico FINANCEIRO ou últimos N meses?
- [ ] Migrar boletos liquidados (histórico) ou só vivos?
- [ ] Migrar CONTRATO `ATIVO='N'` pra dashboard churn ou descartar?

### Bloco D — Multi-conceito (Maiara confirma via amostra Firebird)
- [ ] `RAZAOSOCIAL` em FINANCEIRO sobrescreve JOIN com PESSOAS?
- [ ] `CODFINANCEIRO_GRUPO` = parcelas mesma operação?
- [ ] `TIPO` só assume os 4 valores documentados?

### Bloco E — Dependências (Wagner+Felipe decidem)
- [ ] Vertical do cliente: oficina (precisa Fase 2 vehicles), gráfica (skip vehicles), comércio puro?
- [ ] NFs entrada (`CODNF_ENTRADA`) migram pro `Modules/Compras` ou só referência string?
- [ ] Cliente usa `Modules/FinanceiroAvancado` (recurring/contratos) ou `Modules/Financeiro` básico?

### Bloco F — LGPD / segurança
- [x] **Autorização LGPD: LIBERADA por Wagner 2026-05-21** — dados PII (CPF/CNPJ/email/HISTORICO) podem migrar do Firebird on-prem pro MySQL Hostinger
- [ ] Senhas em `EMPRESA.CERTIFICADO_SENHA`/`WEB_SERVICE_SENHA`/`NFCE_*_CSC` — cliente recadastra no Vaultwarden (não migra automatic)
- [ ] Confirmar PII redaction em `metadata.delphi_legacy` no audit JSON (sempre obrigatório — ADR 0120)

---

## Reconciliation pós-import (Wagner valida campo-a-campo)

Antes do cutover, importer roda em modo `--dry-run` e gera relatório side-by-side:

| Métrica | Firebird (origem) | MySQL (destino) | Diff | Status |
|---|---|---|---|---|
| Total `FINANCEIRO STATUS=ATIVO` | N | N | 0 | ✅ |
| `SUM(VALOR) TIPO=RECEBIDA` 12m | R$ X | R$ X | 0,00 | ✅ |
| `SUM(VALOR) TIPO=A RECEBER` vivo | R$ X | R$ X | 0,00 | ✅ |
| `SUM(VALOR) TIPO=PAGA` 12m | R$ X | R$ X | 0,00 | ✅ |
| `SUM(VALOR) TIPO=A PAGAR` vivo | R$ X | R$ X | 0,00 | ✅ |
| Total BOLETOS vivos | N | N | 0 | ✅ |
| Total CONTRATO ATIVO='S' | N | N | 0 | ✅ |
| Total PESSOAS TIPO=C | N | N | 0 | ✅ |
| Total PESSOAS TIPO=F | N | N | 0 | ✅ |
| Amostra 30 registros random — diff campo-a-campo | — | — | — | ✅/❌ por registro |

Wagner aprova ✅ → cutover live (1 cliente por vez, paralelo 30d).

---

## Próximos passos

1. Maiara segue [MAIARA-GUIA-COLETA-CLIENTE.md](MAIARA-GUIA-COLETA-CLIENTE.md) — 1 cliente por vez
2. Maiara devolve este arquivo preenchido + 3 mapping CSVs (planocontas / tipopagto / banco)
3. Felipe roda importer Python `--dry-run` com mapping anexado
4. Wagner valida tabela de reconciliation acima
5. Cutover live + audit JSON arquivado

---

## Histórico

- **2026-05-21**: criado por Wagner. LGPD liberado. Maiara designada coleta.
