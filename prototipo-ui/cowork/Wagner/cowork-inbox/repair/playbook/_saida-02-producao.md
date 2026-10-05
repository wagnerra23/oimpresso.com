---
sessao: "_saida-02-producao"
thread: "02 · Título 24→22px em Repair/Index e JobSheet/Index"
dono: "[CL]"
data: 2026-10-05
complementa: _saida-02.md (lado produção da prova D4, que lá ficou pendente)
---
# _saida-02 · lado produção

> Arquivo separado de propósito: o `_saida-02.md` já foi verificado contra o Cowork, e editá-lo arma o check
> `espelho — mexeu depois de verificar` até ele subir de novo. Este complemento é novo e não reabre aquele.

## Medido — 2026-10-05 ([CL], sessão da thread Connector/06)

Medido em `https://oimpresso.com` logado, depois do deploy do #8531, com `getComputedStyle` no seletor que
este recibo prescreve (`header[role=banner] h1`), duas leituras com 1,5 s de intervalo:

| tela | título | `fontSize` | `fontWeight` | leituras iguais |
|---|---|---|---|---|
| `/repair/repair` | Ordens de Serviço | **22px** | 600 | sim |
| `/repair/job-sheet` | Ordens de serviço | **22px** | 600 | sim |

Controle positivo da sonda: o subtítulo do mesmo header em `/repair/job-sheet` mede 12px, então ela distingue
tamanhos. Com o lado design já medido acima (22px nas duas rotas), o eixo D4 do título fica **22 × 22**.

**NÃO RODEI** o `design-diff.mjs --compare … --check` completo: ele pede o `design.json` das rotas `rep-reparos`
e `rep-folhas` servidas do espelho, que não foi gerado nesta sessão. O que está aqui é o eixo D4 do título,
medido nos dois lados com a mesma propriedade computada. A avaliação do recibo segue com o [W].
