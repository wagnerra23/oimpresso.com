---
sessao: "17"
titulo: Nota com os itens reais (fase 2B) — recibo
autor: "[CL]"
data: 2026-10-06
base: wagnerra23/oimpresso.com@main a8e0624504 (pós-#8806)
prefixo_tocado: Modules/NfeBrasil/Services/NfeService.php · Modules/NfeBrasil/Tests/Feature/NfeEmissaoPorItemTest.php (novo) · este arquivo
---
# _saida-17 · Nota com os itens reais

**Resposta curta:** `emitirParaTransaction` (usado pela NF-e 55 do balcão **e** pela NFC-e 65) monta agora um `det` por linha de `transaction_sell_lines`, com o NCM do produto, e chama o `MotorTributarioService` por item. **O total da nota não muda** (`vNF` = `final_total`, como antes). O que muda é a decomposição: o total dos produtos, o desconto ou acréscimo e os impostos, que agora somam item a item. `emitirParaInvoice` (cobrança recorrente) ficou como está, como a thread manda.

## 1 · Feito

| item | onde |
|---|---|
| Linhas da venda com o produto, filtradas por `products.business_id` (Tier 0) | `NfeService::linhasDaVenda` |
| Itens + rateio + motor por item, sem acesso a banco (testável) | `NfeService::montarItensNfe` · `ratearCentavos` |
| `metadata.itens_ncm_padrao` gravado na emissão | `emitirInterno` → `NfeEmissao::create` |
| `vOutro` por item e no total; `vUnCom`/`vUnTrib` com até 10 casas só quando o item pede (`dec_vun`) | `adicionarItem` · `buildXml` (a cobrança recorrente gera o mesmo XML de antes) |
| Venda sem linha legível volta ao item genérico de antes, com log `nfe_itens_ausentes` | `emitirParaTransaction` |
| Teste | `Modules/NfeBrasil/Tests/Feature/NfeEmissaoPorItemTest.php` (9 casos) |

### Regra do valor (REGRA MESTRE)
```
vProd_i = round(qCom × vUnCom, 2)          vUnCom = unit_price_inc_tax da linha
Δ = final_total − Σ vProd_i
Δ < 0 → vDesc, rateado
Δ > 0 → vFrete (até shipping_charges), o resto em vOutro, rateados
base_i = vProd_i − vDesc_i + vFrete_i + vOutro_i      Σ base_i = vNF, exato em centavos
```
O rateio é feito em centavos pelo método do maior resto. Cada item recebe no máximo 1 centavo acima da parte proporcional dele, e o desconto nunca passa o `vProd` do item. Os impostos são calculados por item sobre `base_i`, e o total é a soma dos itens já arredondados.

### Prova por dois caminhos (motor de teste: ICMS 18% · PIS 1,65% · COFINS 7,6%)
**Caminho 1:** uma calculadora independente (Python `Decimal`, rateio de Hamilton por ordenação de frações), sem nada do código PHP. **Caminho 2:** o código PHP real (`montarItensNfe`) rodado no CT 100, resultado na §1-bis.

| cenário | campo | antes (2A) | depois (2B) |
|---|---|---|---|
| **S1** 3 linhas (2×10,00 · 1×25,50 · 3×3,3333 sem NCM), venda fechou em 50,00 | itens | 1 | **3** |
| | vProd | 50,00 | **55,50** |
| | vDesc | 0,00 | **5,50** (1,98 · 2,53 · 0,99) |
| | **vNF** | **50,00** | **50,00** |
| | bases do motor | 50,00 | 18,02 · 22,97 · 9,01 |
| | ICMS | 9,00 | **8,99** (−0,01, arredondamento por item) |
| | PIS / COFINS | 0,83 / 3,80 | 0,83 / 3,80 |
| **S2** 2×10,00 + frete 5,00 = 25,00 | vProd · vFrete · vNF | 25,00 · 0 · 25,00 | 20,00 · **5,00** · 25,00 |
| | impostos | 4,50 · 0,41 · 1,90 | 4,50 · 0,41 · 1,90 |
| **S3** 2×10,00, venda 21,00, sem frete | vProd · vOutro · vNF | 21,00 · 0 · 21,00 | 20,00 · **1,00** · 21,00 |
| **S4** controle: 1 linha 150,00 | tudo | 150,00 · 27,00 · 2,48 · 11,40 | **idêntico** |

**O que muda para o cliente e para o caixa:** nada. O valor da nota e o pago continuam sendo o `final_total`. O que muda é o que a SEFAZ e o contador veem: produto por produto, com o NCM real, e o desconto declarado. O imposto destacado pode diferir em **centavos** por nota, porque cada item é arredondado separado. É o que a SEFAZ valida (a soma dos itens precisa bater com o total).

### 1-bis · Resultado no CT 100 (2026-10-06, container `oimpresso-staging`, MySQL)
O checkout do container estava em `e57b78bf5`, com 14 arquivos sujos de outra sessão, e **não foi tocado**: a classe nova e os testes rodaram de cópias em `/tmp/t17`, com `require_once` da classe nova antes de qualquer uso. Ao fim o diretório foi apagado e o checkout seguia com os mesmos 14 sujos.

| rodada | resultado |
|---|---|
| **Teste novo contra a classe ANTIGA** (o teste morde?) | **9 failed**, porque o método não existe. |
| **Teste novo contra a classe nova** | **9 passed (34 assertions)**, zero skip. O 025c (MySQL) rodou. Os números da tabela §1 batem com o caminho 1, centavo por centavo. |
| Guardas verdes com a classe nova | `NfeServiceIdempotenciaRetryTest` 7/7 · `NfeServiceEmitirParaTransactionTest` 3/3 · `NfeServiceRetransmitirTest` 3/3 · `NfeServiceCancelarTest` 5/5 · `ContingenciaEmissaoTest` 7/7 · `MotorTributarioServiceTest` 16/16 |
| Guardas com falha | `NfeServiceTest` 3 falhas · `NfeServiceDoubleWriteTest` 3 · `EmitirNfceJobTest` 4. **Controle:** as mesmas 3/3/4 falhas, com os mesmos erros, rodando a classe **antiga** do container. São **herdadas** do ambiente e do checkout atrasado, não desta mudança. |

A 1ª rodada achou um defeito real e ele foi consertado no código: `int / 100` em PHP devolve **int** quando a divisão é exata (`2500/100 = 25`), então o tipo do retorno variava com o valor. Os valores estavam certos; agora a divisão é `/ 100.0` e o retorno é sempre float.

## 2 · Não feito, e por quê

| UC | o que falta | por quê |
|---|---|---|
| **R-NFE-025, parte N1** | O override por produto ainda não alcança a nota | **`products` não tem `fiscal_rule_override_id`** (`git grep` no repo inteiro: zero migrations; a coluna só existe no DTO e no motor). Criar a coluna é migration de schema 🔴 e fica fora deste prefixo. Hoje o código passa `null` explícito, comentado. |
| **R-NFE-025b, parte "CST da regra"** | O CST de PIS/COFINS ainda é `07` fixo, agora com log `pis_cofins_cst_fallback` | **`nfe_fiscal_rules` não guarda CST de PIS/COFINS** e o `TributoCalculado` não devolve esse campo. Precisa de migration + motor, a lane 🔴 da D-MOTOR. ⚠️ Não usei `products.cst_pis`/`cst_cofins`: o default do schema é `'49'`, e isso mudaria em silêncio todo produto do Simples de 07 para 49. |
| R-NFE-023 "o XML tem 3 `det`" | O teste prova os `dets` montados, não o XML | `buildXml` é privado e precisa de business, cidade e schema da Reforma no banco. O caminho `dets → XML` (`adicionarItem`) é o de antes, mais os campos `voutro`/`dec_vun`. |
| `linhasDaVenda` (a consulta) | Sem teste próprio | Exige fixture de produto + linha de venda no tenant 98. |
| Teste na lane de CI | **O arquivo novo não roda no CI** | A lane `nfebrasil-pest.yml` usa **allowlist** de arquivos, e o workflow está fora do meu prefixo. Ver §3. |
| UCs no SPEC NfeBrasil | Não colados | O SPEC está fora do prefixo. O texto está no `17-emissao-por-item.md`, pronto. |

## 3 · Pedido literal

**[W]:** a nota muda de forma (itens reais, desconto declarado) e o imposto destacado pode variar em centavos (tabela §1). **O merge depende do seu ok.**

**[CL] (dono de `.github/workflows/nfebrasil-pest.yml`):** acrescentar à allowlist do step "Run Pest (NfeBrasil · MySQL)":
```
Modules/NfeBrasil/Tests/Feature/NfeEmissaoPorItemTest.php
```

**[CL] (SPEC NfeBrasil):** colar R-NFE-023 · 024 · 025 · 025b · 025c, do `17-emissao-por-item.md`, com a rastreabilidade apontando para `NfeEmissaoPorItemTest`. 025 e 025b vão com o status **parcial** da §2.

## 4 · Descobertas que mudam outra thread

- **06 / 07 / 25 / 26:** agora o motor recebe o NCM real e a base de cada item. A **N1 (override por produto) continua inalcançável** até alguém criar `products.fiscal_rule_override_id`. Quem for fazer a 25 (benefício) ou a 07 (operação) deve incluir essa coluna ou registrar que não vai usar a N1.
- **14 (saúde fiscal):** `nfe_emissoes.metadata.itens_ncm_padrao` existe a partir deste PR: `[{sell_line_id, product_id}]`. Produto com `ncm = '0'` (o default do schema) conta como sem NCM.
- **D-MOTOR:** o CST de PIS/COFINS por regra precisa de coluna em `nfe_fiscal_rules` + campo no `TributoCalculado`. Sem isso o R-NFE-025b fica só no fallback logado.
- **20 (devolução):** pode copiar os `dets` da emissão de origem; o `vDesc` por item agora é explícito.

## 5 · Prefixo tocado
`Modules/NfeBrasil/Services/NfeService.php` · `Modules/NfeBrasil/Tests/Feature/NfeEmissaoPorItemTest.php` · `_saida-17.md`. O `MotorTributarioService` **não** foi tocado (só é chamado, por item).
