---
sessao: "17"
titulo: Nota com os itens reais (fase 2B) — o NCM do produto passa a entrar na nota
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 794f606dcecc (2026-10-06 13:42)
prefixo: Modules/NfeBrasil/Services/NfeService.php (emitirParaTransaction · emitirParaInvoice) · Modules/NfeBrasil/Tests/Feature/<NfeEmissaoPorItemTest novo>
nao_toca: MotorTributarioService.php (só é chamado, por item) · resources/js/
depende: — (D-MOTOR/D-SUPORTE). 🔴 sozinho no PR. Pré-requisito de 06 · 07 · 14 · 16.
decisao: _DECISOES-W-2026-10-06.md
implementa: R-NFE-023 · R-NFE-024 · R-NFE-025 · R-NFE-025b · R-NFE-025c
us: US-NFE-002 critério aberto "[ ] Tributação calculada por MotorTributarioService baseada em produto.NCM" · US-FISCAL-020 resíduos abertos "[ ] Items reais via JOIN `transactions_sell_lines`" e "[ ] COD_MUN IBGE via `business->city_id`". **A NF-e 55 do balcão usa a mesma função** (`NfeEmissaoController::emitir` → `emitirParaTransaction($tx, $modelo)`), então a 17 vale para 55 **e** 65.
---
# 17 · Nota com os itens reais

## O que está no código (lido 2026-10-06)
- `emitirParaTransaction` (NFC-e): `dets` com **um** item `cprod = 'PDV-' . $tx->id`, `xprod = "Venda PDV #…"`, `ncm = $ncmDefault`; o motor é chamado **uma vez**, com o NCM padrão e o total da venda. O docblock chama isso de "Limitações fase 2A (refinar em fase 2B)".
- `emitirParaInvoice`: mesmo desenho, um item "Cobrança recorrente".
- Fixos no XML: PIS/COFINS `cst '07'`, `ind_ie_dest '9'`, `cod_municipio '9999999'` (o próprio comentário diz *"SEFAZ rejeita em prod"*), `tpag '01'`/`'99'`.

## Escopo (só NFC-e nesta thread; NF-e de cobrança é a mesma forma em outra)
1. Um `det` por `transaction_sell_lines`: código, descrição, unidade, quantidade, valor e **NCM do produto**. Produto sem NCM usa o `ncmDefault` e grava a linha em `metadata.itens_ncm_padrao` (alimenta a saúde fiscal, 14).
2. `MotorTributarioService::calcular` **por item**, com `fiscal_rule_override_id` do produto quando houver (N1 hoje é inalcançável pela emissão).
3. Totais = soma dos itens (`vProd`, `vICMS`, PIS, COFINS, IBS/CBS); arredondamento por item, como a SEFAZ valida.
4. PIS/COFINS: o CST vem do motor quando a regra tiver; `07` só como fallback, logado.
5. **Fora:** `cod_municipio`, CPF do consumidor, forma de pagamento real. São defeitos verdadeiros, mas cada um é uma thread (≤300 linhas).

## Casos de uso que esta thread implementa
Formato do `main` (rastreabilidade + Dado/Quando/Então + controle positivo + teste que cita o UC). **Os números são provisórios:** confirmar o próximo livre no destino no seu turno. Colar cada UC no destino indicado, com a linha na tabela de rastreabilidade (G-2: UC só entra com o teste que o cita).

| UC | Título | Prioridade | Destino |
|---|---|---|---|
| R-NFE-023 | Um item na nota por linha da venda, com o NCM de cada produto | `must` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-024 | Linha sem NCM usa o padrão e fica marcada pra revisão | `must` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-025 | Totais da nota = soma dos itens; exceção do produto alcança a nota | `must` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-025b | PIS/COFINS com o CST da regra; 07 só como fallback logado | `must` `[fiscal]` | SPEC NfeBrasil |
| R-NFE-025c | Idempotência da emissão continua valendo com itens reais | `must` `[T0]` `[fiscal]` | SPEC NfeBrasil (guarda do R-NFE-005) |

### R-NFE-023 · Um item na nota por linha da venda, com o NCM de cada produto · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** toda venda com NF-e 55 ou NFC-e 65
- **Aceite:** Dado venda com 3 linhas de NCMs diferentes · Quando emito (55 **e** 65) · Então o XML tem 3 `det`, cada um com o NCM do produto, e o motor foi chamado 3 vezes. Controle positivo: venda de 1 linha gera 1 `det` igual ao de hoje, exceto descrição e NCM reais.
- **Teste:** `NfeEmissaoPorItemTest` — `R-NFE-023 · um det por linha com NCM do produto`
- **Contrato:** US-NFE-002 · US-FISCAL-020 (resíduo items reais)
- **Regressão que defende:** a nota sai como "Venda PDV #X" e ignora toda a tributação por produto.

### R-NFE-024 · Linha sem NCM usa o padrão e fica marcada pra revisão · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** produto mal cadastrado
- **Aceite:** Dado uma linha de produto sem NCM · Quando emito · Então o `det` usa o NCM padrão e `metadata.itens_ncm_padrao` registra a linha. Controle positivo: sem NCM padrão válido, cai no R-NFE-022.
- **Teste:** `NfeEmissaoPorItemTest` — `R-NFE-024 · linha sem NCM usa padrão e marca`
- **Contrato:** D-SUPORTE
- **Regressão que defende:** NCM errado silencioso em toda nota.

### R-NFE-025 · Totais da nota = soma dos itens; exceção do produto alcança a nota · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** —
- **Aceite:** Dado itens com valores e um produto com `fiscal_rule_override_id` · Quando emito · Então `vProd`/`vICMS`/`vPIS`/`vCOFINS`/IBS-CBS são a soma dos itens (arredondamento por item) e o item do override usa a regra N1. Controle positivo: sem override, o item usa N2/N3.
- **Teste:** `NfeEmissaoPorItemTest` — `R-NFE-025 · totais somam itens e N1 alcançável`
- **Contrato:** ADR ARQ-0006 (N1)
- **Regressão que defende:** total da nota divergente da soma (rejeição) e exceção por produto que nunca vale.

### R-NFE-025b · PIS/COFINS com o CST da regra; 07 só como fallback logado · `must` `[fiscal]`
- **Destino:** SPEC NfeBrasil
- **Persona:** empresa de regime normal
- **Aceite:** Dado regra com CST de PIS/COFINS · Quando emito · Então o `det` usa esse CST. Sem CST na regra · Então `07` e um log `pis_cofins_cst_fallback`. Controle positivo: Simples sem regra continua `07`, como hoje.
- **Teste:** `NfeEmissaoPorItemTest` — `R-NFE-025b · CST PIS/COFINS da regra`
- **Contrato:** `NfeService` (hoje `cst => '07'` fixo)
- **Regressão que defende:** Lucro presumido emitindo PIS/COFINS como isento.

### R-NFE-025c · Idempotência da emissão continua valendo com itens reais · `must` `[T0]` `[fiscal]`
- **Destino:** SPEC NfeBrasil (guarda do R-NFE-005)
- **Persona:** reenvio da mesma venda
- **Aceite:** Dado uma venda já emitida (autorizada/pendente) · Quando emito de novo · Então devolve a mesma emissão, sem novo número. Controle positivo: venda rejeitada permite retransmitir com número novo.
- **Teste:** `NfeServiceIdempotenciaRetryTest` (existente, deve seguir verde) + caso novo com 3 itens
- **Contrato:** R-NFE-005
- **Regressão que defende:** refactor dos itens quebrar a idempotência.

## Prova
`NfeEmissaoPorItemTest`: venda com 3 linhas de NCMs diferentes → XML com 3 `det` e o NCM de cada produto · linha sem NCM → NCM padrão + `metadata.itens_ncm_padrao` · produto com override → regra N1 · soma dos itens = `vNF`. Guardas: os testes atuais de `NfeService` e `MotorTributarioServiceTest` continuam verdes.

Antes de editar: confirmar as linhas no seu turno (C12). Terminou: `_saida-17.md`. Pare.
