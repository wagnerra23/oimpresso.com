---
sessao: "_saida-10a"
thread: "10 · Folha — passo 2 do §9 da ADR-mãe (casos-verdade externos)"
dono: "[C]"
data: 2026-10-14
prefixo_tocado: tests/fixtures/folha-casos-verdade/ · tests/Feature/Calculo/FolhaCasosVerdadeFixturesTest.php · .github/workflows/essentials-pest.yml · este recibo
base_lida: wagnerra23/oimpresso.com@main 9daa342926
---
# _saida-10a

## O que entrou
Passo 2 do §9 da ADR-mãe da folha, aberto por D-FOLHA-PASSO2 ([W] 14/10). Quatro fixtures JSON, todas
de fonte oficial e com competência declarada. Nenhum motor, nenhuma Page, nenhuma gravação de valor.

| Fixture | Fonte | Competência | Casos |
|---|---|---|---|
| `inss-2025.json` | Portaria Interministerial MPS/MF nº 6/2025, Anexo II | 2025-01 a 2025-12 | 4 |
| `inss-2026.json` | Página do INSS (valores da Portaria MPS/MF nº 13/2026) | desde 2026-01 | 0 |
| `irrf-2026.json` | RFB: tabela 2026 + exemplos da Lei 15.270/2025 | 2026-01 | 5 |
| `fgts.json` | Lei 8.036/1990, art. 15 | 2025-12 | 3 |

O teste valida só estrutura e soma interna (base = rendimento − dedução, imposto = base × alíquota −
parcela, devido = imposto − redução). Tem controle negativo. Rodado no CT 100: 7 testes, 41 asserções.

## Achados das fontes
- **Erro de conta da RFB.** No Exemplo 1, a RFB escreve uma base 80 centavos menor que a subtração
  que ela mesma mostra. As duas caem na faixa isenta. A fixture guarda as duas.
- **Duas competências no mesmo holerite.** Os exemplos da RFB são de pagamento em 01/2026. O IRRF usa a
  tabela de 2026, mas o INSS informado só bate com a tabela de 2025, ou seja, com a apuração de 12/2025.
  É o par `competencia_pagamento` × `competencia_apuracao` da ADR-mãe §2.5. A competência dos casos de
  INSS é inferência minha, conferida pela tabela, e não declaração da fonte.
- **Arredondamento do INSS.** Em 2 dos 4 casos da RFB, o valor só fecha se as faixas forem somadas sem
  arredondar e o arredondamento for feito uma vez, no fim. Arredondar faixa a faixa dá 1 centavo a menos.
  É uma fonte só, e a RFB não declara o método. Fica como hipótese para o passo 7 (contador).

## Sem fonte externa: decisão do [W]
Não achei exemplo oficial resolvido destes casos:
1. INSS de empregado em 2026 (só existe a tabela).
2. IRRF com dedução por dependente (só existe o valor da dedução).
3. IRRF do 13º, e se a redução da Lei 15.270 vale para ele.
4. Férias: 1/3 constitucional, abono e IRRF em base própria.
5. FGTS com arredondamento.

A fonte admissível que falta é um **holerite conferido por contador** (anonimizado, sem nome nem CPF).
Pergunta ao [W]: consegue holerites do contador do biz=1 cobrindo esses 5 casos? Sem eles, o passo 5
(motor) nasce sem caso-verdade para 13º, férias e dependente.

## Pedido ao índice do Cowork (esta sessão não o edita)
- Thread 10: registrar "passo 2 do §9 entregue (casos-verdade externos, 12 casos); 5 lacunas aguardam
  holerite do contador".
