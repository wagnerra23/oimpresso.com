---
sessao: "28"
titulo: Fechamento do mês para o contador — 10 conferências no link de revisão (UC-NFTR-26 · 27 · 28)
autor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main dffa1d7bbe
prefixo_tocado: Services/Tributacao/FechamentoContadorService.php (novo) · RevisaoContadorLinkController · views/contador/revisao.blade.php · DanfeService (obterXmlContents vira público) · config/fiscal.php (flag `fechamento_contador_enabled`, junto das outras flags do NfeBrasil/Fiscal) · Tests/Feature/FechamentoContadorTest.php (novo) · Index.casos.md · nfebrasil-pest.yml · SUPERFICIE (regenerada) · este arquivo
---
# _saida-28 · Fechamento do mês para o contador

**Resposta curta:** o link de revisão do contador (thread 15b) ganhou a seção "Fechamento MM/AAAA" com as
10 conferências, só leitura e só agregados (D-FECHAMENTO): nenhuma venda, cliente, número ou valor de nota
individual. Nada bloqueia o fechamento (D-REJEITADA). A seção nasce **desligada**
(`NFEBRASIL_FECHAMENTO_CONTADOR=false`). Mês = `?mes=AAAA-MM` ou o anterior ao de hoje.

## 1 · As 10 conferências e a fonte de cada uma

| # | fonte | situação possível |
|---|---|---|
| CT01 notas fecham | `nfe_emissoes` do mês por status (rejeitada sem `emitido_em` conta pelo `created_at`) | ok · pendente se há nota sem resposta da SEFAZ |
| CT02 rejeitadas pendentes | idem | ok · pendente com "Resolver ou inutilizar a numeração" |
| CT03 sublimite | receita 12m = mesma definição do `FatorRService::apurar()` (vendas − devoluções finalizadas), janela até o fim da competência | ok · atenção ≥ 90% · pendente > R$ 3,6 mi |
| CT04 segregação | itens dos XML autorizados do mês (CFOP + CSOSN/CST, vProd − vDesc) + NFS-e emitidas, contra a receita do mês no Financeiro | ok · atenção se a soma difere ou se falta XML |
| CT05 Fator R | `FatorRService::apurar()` da competência | atenção quando a folha é só a do ERP (§ 24) · sem fonte sem receita |
| CT06 DIFAL de compra | `nfe_dfe_recebidos` do mês, UF do emitente pela chave × UF da empresa | ok · atenção (classificar) |
| CT07 entradas sem manifestação | `nfe_dfe_recebidos.status_manifestacao` | ok · pendente |
| CT08 devolução sem nota de origem | — | **sem fonte**: o fechamento ainda não cruza devolução de venda com NF-e de devolução. Na base `dffa1d7bbe` a NF-e sai com finalidade 1; o #9355 (thread 20, mergeado em `7fb086425a`) emite a devolução com `refNFe` pelo `DevolucaoService`; o próximo passo é o CT08 contar `sell_return` do mês sem NF-e finalidade 4 |
| CT09 retenção NFS-e × financeiro | conta NFS-e com retenção; o Financeiro não registra a retenção | **sem fonte** |
| CT10 regras sem aceite | `nfe_revisoes_contador` pendente/ajuste pedido de regra vigente no mês | ok · pendente |

"Sem fonte" foi escolhido em vez de "ok": uma conferência que não tem o que medir não pode parecer passada.
CT06/CT07 com zero entradas no mês também não dizem ok: avisam conferir se a consulta à SEFAZ está ativa
(em produção `nfe_dfe_recebidos` = 0, medido pela thread 11).

## 2 · Decisões de técnica

| decisão | por quê |
|---|---|
| service em `Modules/NfeBrasil/Services/Tributacao`, não em `Modules/Fiscal/Services` | a página do link é do NfeBrasil; chamar o Fiscal daqui criaria dependência NfeBrasil → Fiscal (hoje só existe Fiscal → NfeBrasil). As fontes são todas tabelas do NfeBrasil, do core e de `nfse_emissoes`. |
| contagem por query direta em `nfe_emissoes`, não pelo `NotasUnifiedService` | o playbook cita o `NotasUnifiedService` como fonte, mas ele tem teto de 50 notas e depende de sessão; a página do contador não tem sessão. |
| segregação pelo XML autorizado | a emissão não grava CSOSN por item em lugar nenhum além do XML. A leitura usa a mesma porta do DANFE (`DanfeService::obterXmlContents`, agora público), em vez de uma segunda cópia. |
| centavos inteiros em toda comparação | fronteira de 90% e soma da segregação sem float. |
| lei citada no docblock | LC 123/2006 art. 13-A, art. 18 § 4º e § 4º-A, I, lidos em planalto.gov.br em 2026-10-14. ⚠️ O playbook cita "art. 18 §4º" para a ST; a segregação da ST está no **§ 4º-A, I**. |

## 3 · Regra de valor (números fiscais exibidos)

O service não grava nem muda nenhum valor. Ele **mostra** receita de 12 meses, percentual do sublimite e
as somas da segregação, por isso a conta foi provada por dois caminhos.

| caso | caminho 1 · `FechamentoContadorService` (Pest, CT 100) | caminho 2 · node, fórmulas do protótipo |
|---|---|---|
| receita 12m 3.480.000 / 3.600.000 | "96,7%" · atenção | 96,7 · alerta true · passou false |
| fronteira 90% | 3.240.000,00 atenção · 3.239.999,99 ok | true · false |
| segregação 612 + (200 − 16) + 1.428 + 386 | linhas 612,00 / 184,00 / 1.428,00 / 386,00 · total 2.610,00 · ok | soma 2610.00 == 2610 |
| notas 398 + 9 + 1 + 4 | "= 412 emitidas" | 412 |

**Antes → depois em produção:** antes, o link mostrava só a revisão de regras. Depois, com a flag
desligada (padrão), **nada muda**. Com a flag ligada, o link passa a mostrar a seção de fechamento.
Nenhuma venda, nota, título ou regra muda de valor.

## 4 · Prova (CT 100, worktree isolado `/tmp/wt28`, vendor copiado para o autoload apontar para o worktree)

| rodada | resultado |
|---|---|
| `FechamentoContadorTest` (migrações `2026_10_07_000001` e `000005` aplicadas e revertidas) | **12 passed · 52 assertions** |
| mutação M1: sem `business_id` na contagem de notas | cai (1 failed: `conta as notas do mês do próprio business`) |
| mutação M2: aviso a 91% | cai |
| mutação M3: CSOSN 500 não separa | cai |
| PHPStan nos 3 arquivos de produção | sem erros |
| staging depois | `2026_10_07_000001` e `000005` de volta a Pending |

A 1ª rodada do M1 **não aplicou** a mutação (o `grep -c` deu 0) e o "12 passed" dela foi descartado; refeito
por número de linha.

`DanfeServicePrefersArquivosTest` tem 3 vermelhos no staging (`Unknown column 'filename'` em `arquivos`):
schema do staging, não desta mudança, que só trocou `private` por `public`.

## 5 · Não feito, e por quê

- **R-FISC-042 inteiro.** O ERP não registra a destinação da entrada (revenda × uso/consumo/ativo), então
  o CT06 pede classificação em vez de afirmar DIFAL. Depende da ADR de entrada por XML (thread 11,
  `proposto`; D-ENTRADA manda código só depois da ratificação). O UC-NFTR-28 está marcado **parcial**.
- **CT08 e CT09** ficam "sem fonte": o CT08 até o fechamento (a devolução da thread 20, #9355, já entrou) passar a
  cruzar `sell_return` com ela; o CT09 até o Financeiro registrar a retenção.
- **Complemento da folha (§ 24)** para o Fator R: segue sem lugar para o contador informar. O CT05 avisa.
- **Aba "Contador" na Tributação do ERP** (a do protótipo) e o drawer "Enviar p/ contabilidade" do cockpit:
  não feitos. Esta thread pôs o fechamento só no link, como pede a D-FECHAMENTO.
- **Teste da flag na página.** O teste cobre o service; não há caso que abra a página do link com a flag
  ligada e desligada.
- **NFS-e no CT01.** Só NF-e/NFC-e entram em "notas fecham"; NFS-e entra só na segregação.

## 6 · Pedido literal pro [W]

```
1. Ligar NFEBRASIL_FECHAMENTO_CONTADOR quando quiser que o contador veja o fechamento.
2. SPEC NfeBrasil: registrar R-FISC-040/041 (must) e R-FISC-042 (parcial) com
   **Implementado em:** Modules/NfeBrasil/Services/Tributacao/FechamentoContadorService.php.
3. Índice do playbook (Cowork): a fonte do CT04 no protótipo cita "art. 18 §4º"; a ST é o § 4º-A, I.
```
