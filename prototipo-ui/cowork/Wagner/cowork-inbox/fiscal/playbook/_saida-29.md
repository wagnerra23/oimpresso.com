---
sessao: "29"
titulo: Natureza ICMS × ISS por produto e DIFAL do Simples por UF — recibo
autor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main f43be5f858 (já com o #9380 da thread 26)
prefixo_tocado: migração 2026_10_14_000003 (nova) · Models/NfeNaturezaProduto.php (novo) · Services/Tributacao/NaturezaFiscalService.php (novo) · Services/Tributacao/SaudeFiscalService.php · Services/Tributacao/TributoCalculado.php · Services/MotorTributarioService.php · Services/NfeService.php · Tests/Feature/NaturezaFiscalTest.php (novo) · Tests/Feature/MotorTributarioServiceTest.php · SPEC (US-NFE-068 + R-NFE-034/035) · SUPERFICIE (regenerada) · .github/workflows/nfebrasil-pest.yml (allowlist) · este arquivo
---
# _saida-29 · Natureza ICMS × ISS e DIFAL do Simples por UF

**Resposta curta:** dois casos em que a lei não dá resposta única, e o sistema passou a **não
decidir sozinho**:
1. **Natureza do produto** (tabela-ponte `nfe_naturezas_produto`): mercadoria · serviço · pendente.
   Só usuário da empresa com `nfe.tributacao.manage` **decide**, com fonte obrigatória (e item da
   LC 116 para serviço); autor e data ficam gravados e a decisão vai para `activity('nfe.tributacao')`.
   Pendente emite pelo padrão da UF e aparece na Saúde fiscal. Serviço trava a NF-e/NFC-e da venda
   com a saída "emita NFS-e".
2. **DIFAL do Simples** (`nfe_icms_uf.cobra_difal_simples`, nasce false): empresa do Simples/MEI
   não recolhe DIFAL a consumidor de outra UF, com aviso citando a ADI 5.464, **salvo** na UF que o
   contador marcar.

Nenhuma nota de hoje muda (§3).

## 1 · Decisões (D-NATUREZA e D-DIFAL-UF, [W] 2026-10-06, "ok nas 4") e técnica
| decisão | por quê |
|---|---|
| produto sem linha = mercadoria | é o comportamento de hoje; ninguém precisa cadastrar nada para continuar emitindo |
| `marcarPendente` aberto, `decidir` só com a permissão | a Jana (ou qualquer fluxo) pode **apontar** o caso; quem decide é o contador. Não existe `decidir` sem usuário |
| pendente nunca desfaz uma decisão | o aceite: "produto já decidido não volta pra fila" |
| serviço **recusa a nota inteira**, não tira o item | o total da nota é o `final_total` da venda (REGRA MESTRE); tirar o item mudaria o valor |
| corte do DIFAL só com regime `simples`/`mei` em `nfe_business_configs` | empresa sem regime cadastrado ou fora do Simples segue o R-NFE-017 de antes |
| marcação por par UF (`nfe_icms_uf`) | é a tabela por UF curada da thread 09 (D-UF), onde já mora a interna do destino |
| `products` intocado | tabela core UltimatePOS; a natureza vai numa ponte com `business_id` (ADR 0093) |

Leitura de "vale para os produtos iguais": a decisão é **por produto** (vale para todas as vendas e
variações dele). Não estendi por NCM: seria o sistema decidindo para um produto que o contador não
olhou — o que a D-NATUREZA proíbe.

## 2 · Fontes citadas no código (lei 4)
- `NaturezaFiscalService`: Súmula 156 do STJ, citada literal; SEFAZ-SP Consulta 10406/2016.
- `MotorTributarioService::calcularStFcpDifal`: STF ADI 5.464 (cláusula nona do Convênio ICMS
  93/2015); SEFAZ-SP Consulta 32359/2025; STF Tema 1284. Sem citação literal do texto das decisões
  do STF — não tenho o texto no repo e não o inventei; ficou a referência.

## 3 · Prova (REGRA MESTRE — valor)

**Dois caminhos.** Caminho 1: motor real no CT 100. Caminho 2: conta à mão em Python `Decimal`.

| caso (Simples, SP → consumidor RJ, regra 12%, interna 20%, item 1.000,00) | DIFAL (motor) | conta |
|---|---|---|
| RJ não marcado | 0,00 + aviso ADI 5.464 | — |
| RJ marcado | 80,00 | 1.000 × (0,20 − 0,12) = 80,00 |
| contribuinte (controle) | 0,00 | — |
| empresa lucro presumido, RJ desmarcado (controle) | 80,00 | 80,00 |
| ICMS próprio em todos | 120,00 | 1.000 × 0,12 = 120,00 |

**Antes → depois, por nota:** nenhuma. (a) Ninguém tem natureza cadastrada, então nenhum item vira
serviço. (b) O DIFAL só existe quando o caller informa a alíquota interna do destino, e **nenhum
caller da emissão informa hoje** (thread 06) — o corte do Simples muda o cálculo, não uma nota
emitida. Daqui em diante: item decidido como serviço trava a NF-e da venda, e o DIFAL de empresa do
Simples sai 0 nas UFs não marcadas.

### 3-bis · Resultado no CT 100 (2026-10-14, container `oimpresso-staging`, MySQL)
Worktree isolado `/tmp/wt-t29` com `vendor` copiado; o checkout do container não foi tocado.
Apliquei as migrações que o staging não tinha (07 ×3, 25, 26) mais a nova, rodei e reverti as seis
em ordem inversa. Conferido no fim: nenhuma tabela/coluna delas, nenhuma entrada em `migrations`,
nenhum produto ou natureza de teste sobrando.

| rodada | resultado |
|---|---|
| 11 arquivos (`NaturezaFiscalTest`, `MotorTributarioServiceTest`, `SaudeFiscalTest`, `NfeFilialNumeracaoTest`, `NfeEmissaoPorItemTest`, `NfeDevolucaoTest`, `SerializacaoIbsCbsTest`, `TributacaoControllerTest`, `IcmsUfTest`, `NfeServiceEmitirParaTransactionTest`, `TributacaoSimuladorTest`) | **74 passed (1877 assertions)**, duas rodadas, zero skip |
| PHPStan nos 5 arquivos de código tocados | `[OK] No errors` |
| M1 · sem o corte do Simples | R-NFE-035 failed |
| M2 · corte ignorando a marcação da UF | R-NFE-035 failed |
| M3 · `decidir` sem checar permissão | R-NFE-034 failed |
| M4 · NF-e aceita item de serviço | R-NFE-034 failed |
| M5 · pendente desfaz a decisão | R-NFE-034 failed |
| restaurado | 0 arquivos alterados · 74 passed |

A 1ª tentativa da M2 era equivalente ao código original (`! false && …`) e passou; descartei e
refiz trocando a consulta da marcação por "não marcada", que mordeu.

## 4 · Não feito, e por quê
| o quê | por quê |
|---|---|
| emitir a NFS-e do item de serviço a partir da venda | NFS-e fica fora deste playbook (rev.6: consolidação NFSe × NfeBrasil é decisão [W] pendente). Aqui a NF-e recusa com a saída |
| guia (GNRE) do DIFAL | não há emissão de guia no módulo; o aceite pede o cálculo |
| DIFAL chegar à nota | a emissão ainda não passa a interna do destino ao motor (thread 06/09) |
| tela de decisão de natureza e de marcação de UF | `resources/js/` fora do prefixo |
| sugestão da Jana marcando pendente | a porta existe (`marcarPendente`); ligar a sugestão a ela é da thread 10 |

## 5 · Pedido literal
**[W]:** item decidido como serviço passa a travar a NF-e/NFC-e da venda, e o DIFAL de empresa do
Simples sai 0 nas UFs não marcadas (§3). Nenhuma nota de hoje muda. **O merge depende do seu ok.**
