---
sessao: "26"
titulo: Filial em outro estado — a UF de origem vem do emitente — recibo
autor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main e301138136 (já com o #9365 da thread 25)
prefixo_tocado: migração 2026_10_14_000002 (nova) · Models/NfeLocalEmissor.php (novo) · Models/NfeEmissao.php (fillable) · Services/NfeService.php · Tests/Feature/NfeFilialNumeracaoTest.php (novo) · Tests/Feature/MotorTributarioServiceTest.php · SPEC (US-NFE-067 + R-NFE-031/032) · SUPERFICIE (regenerada) · .github/workflows/nfebrasil-pest.yml (allowlist) · este arquivo
---
# _saida-26 · Filial em outro estado

**Resposta curta:** um `business_locations` pode ser marcado como **local emissor** na tabela-ponte
nova `nfe_locais_emissores`. A venda feita nesse local (`transactions.location_id`) sai com a UF, o
CNPJ, a IE e o endereço dele, e com **série própria**. O motor passa a receber a UF da filial como
`uf_origem`. **Empresa sem local emissor cadastrado emite exatamente como antes**: nenhuma empresa
de hoje tem linha na tabela nova.

## 1 · Onde mora a UF da filial (confirmado no turno)
`business_locations` (core UltimatePOS) tem `state`, `cnpj`, `razao_social`, `nome_fantasia`,
`inscricao_estadual` (migração `2026_04_24_100000`). **Não tem** rua, número, bairro nem código
IBGE, e o `zip_code` é `char(7)` (o CEP tem 8 dígitos). Esses 5 campos foram para a tabela-ponte
(regra: não mexer em tabela core sem bridge). Hoje `resolverUF` pegava o **primeiro** local da
empresa para qualquer venda — essa era a causa de a filial emitir "como SP".

## 2 · Decisões de técnica
| decisão | por quê |
|---|---|
| opt-in por local (linha em `nfe_locais_emissores`) | o comportamento de hoje fica intacto para toda empresa que não cadastrar filial |
| série própria e **única na empresa** (UNIQUE na tabela) | a UNIQUE existente `nfe_emissoes_biz_seq_unique` (business + modelo + série + número) passa a impedir número repetido entre matriz e filial sem mudar o índice |
| série da filial igual à padrão do business = recusa | dividiria o contador com a matriz |
| `business.ultimo_numero_nfe` só vale e só é escrito na **série padrão** | antes, qualquer série herdava esse número e qualquer autorização o sobrescrevia: a filial começaria do número da matriz e empurraria o contador dela. Hoje todas as emissões usam a série padrão, então nada muda para elas |
| filial incompleta (sem UF, CNPJ, IE, endereço, CEP, IBGE ou série) = recusa com o que falta | completar com dado da matriz emitiria com o emitente errado |
| o local é lido com `business_id` da empresa (Tier 0) | cadastro apontando local de outra empresa não emite |
| `nfe_emissoes.location_id` gravado só quando a nota é de filial | registra de onde saiu; as notas de antes e as do business ficam nulas |

## 3 · Prova (REGRA MESTRE — valor)

**Dois caminhos.** Caminho 1: motor e `NfeService` reais no CT 100. Caminho 2: a conta à mão em
Python `Decimal`.

| caso | caminho 1 (PHP) | caminho 2 |
|---|---|---|
| filial PR, NCM com exceção só para SP, item 1.000,00 | Nível 4 · ICMS 18,60 (padrão 1,86%) | 1.000 × 0,0186 = 18,60 |
| matriz SP, mesmo item (controle) | Nível 3 · ICMS 180,00 (exceção 18%) | 1.000 × 0,18 = 180,00 |
| interestadual do par (tabela do Senado semeada) | PR→SC 0,12 · PR→BA 0,07 · SP→BA 0,07 | Resolução 22/1989 |
| contador legado 995.999, matriz e filial alternadas | matriz 996.000 · 996.001 · 996.002 · filial 1 · 2 · 3 | 995.999 + 1, + 2, + 3 |

**Antes → depois, por nota:** nenhuma nota de hoje muda (ninguém tem local emissor cadastrado, e
todas usam a série padrão). Daqui em diante, a venda de um local cadastrado sai com o emitente e a
série dele; o imposto passa a seguir as regras da UF da filial em vez das da matriz.

### 3-bis · Resultado no CT 100 (2026-10-14, container `oimpresso-staging`, MySQL)
Worktree isolado `/tmp/wt-t26` com `vendor` copiado; o checkout do container (15 sujos de outra
sessão) não foi tocado. Apliquei as migrações que o staging não tinha (07: `000001`, `000002`,
`000003`; 25: `2026_10_14_000001`) mais a nova, rodei e reverti as cinco em ordem inversa.
Conferido no fim: nenhuma tabela/coluna das cinco, nenhuma entrada delas em `migrations`, nenhuma
emissão nem local de teste sobrando no tenant 98, contador dele de volta ao valor de antes.

| rodada | resultado |
|---|---|
| 10 arquivos (`NfeFilialNumeracaoTest`, `MotorTributarioServiceTest`, `NfeEmissaoPorItemTest`, `NfeDevolucaoTest`, `SerializacaoIbsCbsTest`, `CnpjAlfanumericoTest`, `TributacaoControllerTest`, `OperacaoFiscalTenantTest`, `IcmsUfTest`, `NfeServiceEmitirParaTransactionTest`) | **77 passed (1829 assertions)**, duas rodadas em ordem aleatória, zero skip |
| M1 · emitente sempre o business | 2 failed |
| M2 · contador legado em qualquer série | 1 failed |
| M3 · autorização escreve o contador em qualquer série | 1 failed |
| M4 · local lido sem o `business_id` da empresa | 1 failed (Tier 0) |
| restaurado | 0 arquivos alterados · 5 passed |

**Um erro meu, pego pelo próprio teste:** a 1ª rodada falhou no R-NFE-032 porque eu esperava a
matriz em 996.001 (o certo é 996.000: contador 995.999 + 1). A falha deixou a nota 996.000 no
banco persistente e as rodadas seguintes passaram **por causa da sobra**. Achei pela contagem de
emissões do tenant 98, corrigi o esperado e a limpeza (faixa inteira), e as duas rodadas finais
começaram com o banco limpo.

## 4 · Não feito, e por quê
| o quê | por quê |
|---|---|
| certificado por local | a emissão assina com o certificado do business. Filial com outra raiz de CNPJ precisaria de certificado próprio — é decisão de cadastro/segurança e não tem aceite nesta thread |
| motor ler a interestadual da tabela | é o fallback da thread 09 ("o motor ainda não lê a tabela"); aqui a UF de origem passa a ser a certa, e o par está provado na tabela |
| `emitirParaInvoice` (cobrança recorrente) | a fatura não tem local; segue emitindo pelo business |
| tela para cadastrar o local emissor (UC-TRB-27) | `resources/js/` está fora do prefixo |
| destinatário em outra UF na NF-e de balcão | `emitirParaTransaction` ainda monta o destinatário como consumidor final na mesma UF (fase 2A) |

## 5 · Pedido literal
**[W]:** muda quem é o emitente para vendas de local cadastrado como filial (§3). Nenhuma nota de
hoje muda. **O merge depende do seu ok.**
