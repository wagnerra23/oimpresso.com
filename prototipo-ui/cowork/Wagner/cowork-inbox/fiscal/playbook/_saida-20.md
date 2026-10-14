---
sessao: "20"
titulo: Devolução de venda copia base e alíquotas da nota de origem — recibo
autor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main 2d71fa90e
prefixo_tocado: Modules/NfeBrasil/Services/DevolucaoService.php (novo) · Modules/NfeBrasil/Services/NfeService.php (buildXml) · Modules/NfeBrasil/Tests/Feature/NfeDevolucaoTest.php (novo) · memory/requisitos/NfeBrasil/SPEC.md (US-NFE-064 + R-NFE-026/027) · SUPERFICIE.md (regenerada) · .github/workflows/nfebrasil-pest.yml (allowlist) · este arquivo
---
# _saida-20 · Devolução de venda

**Resposta curta:** existe `DevolucaoService::emitirDevolucao(biz, origemId, [nItem => qtd])`. Ele
lê o **XML que a SEFAZ autorizou** (`nfe_emissoes.xml_path`) e devolve a fração de cada linha com a
base e as alíquotas de lá. **O motor não entra**: o serviço nem recebe o `MotorTributarioService`.
A nota sai modelo 55, `finNFe=4`, `tpNF=0` (entrada), `refNFe` = chave da origem, CFOP espelho e
pagamento `tPag 90` (sem pagamento). Vendas e as demais notas **não mudam** (prova no §3).

## 1 · Pré-requisitos conferidos (não refeitos)
- **07** entregue no #8941: `nfe_operacoes_fiscais` existe com `finalidade` (o 4 = devolução). A
  devolução não passa pelo motor, então não precisou de operação cadastrada. A prova do índice
  procura `valida_de` no arquivo errado — ver `_ERRATA-INDICE-2026-10-13.md`.
- **17** entregue (`_saida-17.md`): a nota de origem tem um `det` por linha, e é deles que a
  devolução lê.

## 2 · Decisões de técnica
| decisão | por quê |
|---|---|
| ler o XML autorizado, não reconstruir da venda | é o documento que gerou o crédito/débito; a regra de hoje pode ter mudado (R-NFE-019) e a venda pode ter sido editada |
| rateio pela quantidade **acumulada** em centavos: `round(c×(já+agora)/q) − round(c×já/q)` | a soma de todas as devoluções de uma linha bate exata com a origem (4/10 + 6/10 = 10/10, sem centavo sobrando) |
| alíquota copiada como texto da origem | "copia", não "recalcula": se a origem tem um defeito de formato, a devolução tem o mesmo, e o estorno bate |
| CFOP espelho por tabela fechada (101→201, 102→202, 401→410, 403/405→411; 5/6/7 → 1/2/3) | fora da tabela **recusa** em vez de inventar — o contador decide o caso raro |
| saldo derivado das devoluções gravadas (`metadata.devolucao`), contando só status vivos | rejeitada/inutilizada não declarou número, então não consome saldo; sem tabela nova |
| sem `transaction_id` na devolução | a UNIQUE `(business_id, transaction_id)` já está ocupada pela nota de origem |
| `buildXml` ganhou `fin_nfe`, `tp_nf`, `ref_nfe` com default 1/1/vazio | sem as chaves, o XML de qualquer outra nota é o mesmo de antes (controle no teste) |

## 3 · Prova (REGRA MESTRE — valor)

**O que muda:** só passa a existir a NF-e de devolução. Nenhuma venda, cobrança recorrente ou
NFC-e muda: o `buildXml` só lê as chaves novas quando a devolução as passa. O controle está no teste
(`finNFe=1`, `tpNF=1`, sem `<NFref>` na venda).

**Dois caminhos.** Caminho 1: o PHP real (`montarDevolucao`) no CT 100. Caminho 2: calculadora
independente em Python `Decimal` (proporção direta sobre a origem, `ROUND_HALF_UP`), sem nada do
código PHP.

| cenário (origem) | devolve | campo | caminho 1 (PHP) | caminho 2 (Decimal) |
|---|---|---|---|---|
| 2 × 50,00 · ICMS 18% (18,00) · PIS 1,65 | 2 | vProd · ICMS · PIS | 100,00 · 18,00 · 1,65 | 100,00 · 18,00 · 1,65 |
| idem | 1 | vProd · ICMS | 50,00 · 9,00 | 50,00 · 9,00 · (PIS 0,83) |
| 10 × 9,99 · ICMS 18% (17,98) · PIS 1,65 | 4 | vProd · ICMS · PIS | 39,96 · 7,19 · 0,66 | 39,96 · 7,19 · 0,66 |
| idem, 4 já devolvidas | 6 | vProd · ICMS · PIS | 59,94 · 10,79 · 0,99 | 59,94 · 10,79 · 0,99 |
| idem, 4 já devolvidas | 7 | — | recusado (saldo 6) | 10 − 4 = 6 |
| regra trocada para 12%, venda nova de 100,00 | — | ICMS | 12,00 (pelo motor) | 12,00 |

A soma das duas devoluções parciais é a linha: 39,96 + 59,94 = 99,90 · 7,19 + 10,79 = 17,98.

**Antes → depois, por nota:** nenhuma nota existente muda. Antes não havia devolução fiscal; depois,
a devolução estorna exatamente o ICMS/PIS/COFINS destacado na origem, na proporção devolvida.

### 3-bis · Resultado no CT 100 (2026-10-14, container `oimpresso-staging`, MySQL)
O checkout do container (`e57b78bf5`, 15 arquivos sujos de outra sessão) **não foi tocado**. Rodei
num worktree isolado `/tmp/wt-t20` com `vendor` copiado (link simbólico faz o autoload ler o
checkout principal — conferido por `ReflectionClass::getFileName()`).

| rodada | resultado |
|---|---|
| `NfeDevolucaoTest` | **6 passed (58 assertions)**, zero skip |
| M1 · `NfeService` do `main` (sem finNFe/tpNF/refNFe) | 1 failed (R-NFE-026 · copia a origem) |
| M2 · sem a trava de saldo | 1 failed (R-NFE-027 · parcial com saldo) |
| M3 · alíquota fixa 12 (regra de hoje) em vez da origem | 3 failed |
| M4 · CFOP copiado sem espelho | 1 failed |
| restaurado | 0 arquivos alterados · 6 passed |
| guardas | `NfeEmissaoPorItemTest` · `CnpjAlfanumericoTest` · `SerializacaoIbsCbsTest` verdes; `NfeServiceIdempotenciaRetryTest` 6/7 |

`NfeServiceIdempotenciaRetryTest` 1 falha é **herdada**: o `main` puro (`2d71fa90e`), no mesmo
banco, cai igual — `Duplicate entry '1-65-1-555005'`, uma linha que outra rodada deixou no staging
persistente. Não apaguei: é biz=1 e não é minha.

## 4 · Não feito, e por quê
| o quê | por quê |
|---|---|
| tela/rota para pedir a devolução (aba Devoluções, UC-TRB-13) | fora do prefixo desta thread (serviço + teste); o serviço está pronto para ser chamado |
| ligar ao registro de devolução de venda (`SellReturn`) | é decisão de fluxo (devolução de estoque × nota fiscal) e toca Vendas |
| trava de concorrência do saldo | duas devoluções da mesma nota ao mesmo tempo podem passar o saldo: o saldo é lido antes da reserva do número. Residual declarado |
| origem NFC-e sem destinatário | recusa com mensagem; quem chama passa `destOverride` com CPF/CNPJ |

## 5 · Achado lateral (não consertado — é valor, precisa de [W])
Na emissão de venda, `montarItensNfe` passa `picms = $tributo->aliquota_icms`, e o motor guarda a
alíquota como **fração** (`valor_icms = valor × aliquota_icms`). O `adicionarItem` escreve isso em
`pICMS`, que o leiaute espera em **percentual** — uma regra de 18% sairia `pICMS 0.1800` — e o `vBC`
do item sai 0. Só afeta CRT 3 (Simples com CSOSN 102 não leva `pICMS`). Hipótese medida pela
leitura do código, não por nota real em produção. A devolução copia o que a origem tiver, então o
estorno bate com a origem nos dois casos.

## 6 · Pedido literal
**[W]:** nota fiscal nova (devolução); nenhuma nota existente muda (§3). **O merge depende do seu ok.**
