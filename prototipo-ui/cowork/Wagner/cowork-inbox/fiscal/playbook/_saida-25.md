---
sessao: "25"
titulo: Benefício fiscal na regra (redução de base + cBenef) — recibo
autor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main 7fb086425a (já com o #9355 da thread 20)
prefixo_tocado: migração 2026_10_14_000001 (nova) · Models/NfeFiscalRule.php · Services/Tributacao/TributoCalculado.php · Services/MotorTributarioService.php · Services/NfeService.php · Http/Requests/UpsertRegraTributariaRequest.php · Tests/Feature/MotorTributarioServiceTest.php · SPEC (US-NFE-066 + R-NFE-030) · SUPERFICIE (regenerada) · este arquivo
---
# _saida-25 · Benefício fiscal na regra

**Resposta curta:** a regra tributária ganhou `reducao_base` (decimal, 0.3333 = 33,33%), `c_benef`
(o código da tabela da UF) e `beneficio_fundamento` (o ato legal citado pelo contador). Com
redução, o motor calcula o ICMS sobre a base reduzida e o item da nota leva `<cBenef>`, `<pRedBC>`
e a `<vBC>` reduzida, e o total soma essa base. **Regra sem redução calcula e gera XML exatamente
como antes** (prova no §3). Nenhuma regra existente tem as colunas novas preenchidas, então
nenhuma nota de hoje muda.

## 1 · Pré-requisitos conferidos
- **07** entregue (#8941): `nfe_fiscal_rules` tem versões; a versão nova copia as colunas novas
  pelo `replicate()` do `novaVersao`, sem código a mais.
- **17** entregue: a nota tem um `det` por item, e o benefício chega item a item.

## 2 · Decisões de técnica
| decisão | por quê |
|---|---|
| sem redução, a fórmula antiga intacta (`valor × alíquota`) | arredondar a base antes mudaria o centavo de valores com mais de 2 casas; o caminho sem benefício fica byte a byte o de antes |
| com redução: `base = round(valor × (1 − red), 2)` e `ICMS = round(base × alíq, 2)` | é a base que vai na `<vBC>`; o ICMS destacado tem de bater com base × alíquota |
| `beneficio_fundamento` obrigatório junto da redução ou do código (FormRequest) | lei 4 do módulo: sem número fiscal sem lei citada. O código não traz tabela de benefícios, quem cita o ato é o contador |
| `c_benef` só formato (até 10, A-Z0-9) | o leiaute e as tabelas da UF variam; não inventei regex por UF |
| a mesma chave vale para a NF-e da venda e a da cobrança recorrente | os dois montam o `det` do mesmo `TributoCalculado` |

## 3 · Prova (REGRA MESTRE — valor)

**Dois caminhos.** Caminho 1: motor + `montarItensNfe` + `buildXml` reais no CT 100. Caminho 2:
calculadora Python `Decimal` independente (`ROUND_HALF_UP`).

| valor · redução · ICMS | base (motor) | ICMS (motor) | base · ICMS (Decimal) |
|---|---|---|---|
| 600,00 · 33,33% · 18% (bateria C23) | 400,02 | 72,00 | 400,02 · 72,00 |
| 600,00 · sem redução · 18% (controle) | base cheia, `vBC` 0 como antes | 108,00 | 600,00 · 108,00 |
| 1.000,00 · 41,67% · 12% | — | — | 583,30 · 70,00 |
| 99,90 · 33,33% · 18% | — | — | 66,60 · 11,99 |

As duas últimas linhas são do caminho 2 só, para quem for conferir outros casos à mão.

**Antes → depois, por nota:** nenhuma nota existente muda (as 3 colunas nascem nulas). Daqui em
diante, um item de regra **com** redução sai com o ICMS menor (600,00: 108,00 → 72,00) e com o
`cBenef`; o resto da nota é igual.

### 3-bis · Resultado no CT 100 (2026-10-14, container `oimpresso-staging`, MySQL)
Worktree isolado `/tmp/wt-t25` com `vendor` copiado; o checkout do container (`e57b78bf5`, 15
sujos de outra sessão) não foi tocado. O staging **não tinha** as migrações da thread 07 (revertidas
depois da prova dela): apliquei a `2026_10_07_000001`/`000002` e a nova, rodei, e reverti as três.
Conferido no fim: sem `reducao_base`, sem `valida_de`, sem `nfe_operacoes_fiscais`, nenhuma entrada
das três em `migrations` — o estado de antes.

| rodada | resultado |
|---|---|
| `MotorTributarioServiceTest` + `NfeEmissaoPorItemTest` + `NfeDevolucaoTest` + `SerializacaoIbsCbsTest` + `CnpjAlfanumericoTest` + `TributacaoControllerTest` + `OperacaoFiscalTenantTest` | **65 passed (317 assertions)**, zero skip |
| M1 · motor ignora a redução | R-NFE-030 failed |
| M2 · XML sem `<cBenef>` | R-NFE-030 failed |
| M3 · XML sem `<pRedBC>` | R-NFE-030 failed |
| M4 · total da nota com `vBC` 0 | R-NFE-030 failed |
| restaurado | 0 arquivos alterados · passed |

O `SerializacaoIbsCbsTest` inclui o caso "modo legacy → XML byte-idêntico ao de hoje", verde com a
mudança. Uma 1ª tentativa da M3 não casou o `sed` (contagem 0) e foi descartada antes de virar prova;
a refeita casou 1 vez e mordeu.

## 4 · Não feito, e por quê
| o quê | por quê |
|---|---|
| pendência na Saúde fiscal "UF exige cBenef e a regra não tem" | precisa da lista das UFs que exigem o código, com o ato de cada uma (lei 4). Não está no repo e não inventei. Com a tabela, é um item a mais no `SaudeFiscalService` |
| ST e DIFAL sobre a base reduzida | o aceite fala da base do ICMS próprio. ST/DIFAL com benefício variam por UF e por convênio; seguem sobre a base cheia, como antes |
| tela de cadastro (aba Benefícios, UC-TRB-26) | `resources/js/` está fora do prefixo (tela é outra thread) |
| `metadata` com o benefício usado na nota | não pedido; o XML guardado já registra cBenef e pRedBC |

## 5 · Pedido literal
**[W]:** o ICMS de item com benefício passa a ser calculado sobre a base reduzida (§3). Nenhuma
nota existente muda. **O merge depende do seu ok.**
