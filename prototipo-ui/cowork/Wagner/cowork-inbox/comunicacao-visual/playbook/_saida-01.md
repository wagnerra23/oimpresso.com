---
sessao: "_saida-01"
thread: "01 · Charter fora da realidade (R7) + refinos R1–R6"
dono: "[C]"
data: 2026-09-23
base_lida: wagnerra23/oimpresso.com@main 1061dbf2e
---
# _saida-01

## Entregue
Placar: **entregue 4 de 7** (R7 · R2 · R3 · R6) · ausentes **R1** e **R5** por prefixo · **R4** por regra de valor.

| refino | reconferido no main 1061dbf2e | feito |
|---|---|---|
| **R7** charter fora da realidade | sim — `status: draft` + "Stub Sprint 2" + 3 widgets inexistentes | Objetivo/Estado/Fase reescritos pro que a tela faz; os 3 widgets foram pra "Próximo" (UC-CV-11, US-COMVIS-002/003/004). Anti-padrões intocados. `status: draft` mantido: a promoção draft→live é do `charter-promote-signal` (sinal de prod), não desta thread |
| **R2** `crypto.randomUUID` | sim — ainda em `novoItem()` | id local por contador (`i1`, `i2`…) |
| **R3** `some` × `every` | sim | `every(itemOk)`; a linha que segura o botão ganha `aria-invalid` no R$/m² (só quando há outra linha pronta) |
| **R6** campos sem nome em ≥ md | sim | `id` + `htmlFor` + `aria-label` em descrição/largura/altura/qtd/R$/m²; faixa de cabeçalho `aria-hidden` |
| **R1** catálogo nunca chega | sim — a rota ainda entrega só `bizName` | **não feito:** o conserto é em `Modules/ComunicacaoVisual/Routes/web.php`, que é `nao_toca` desta thread. O charter agora declara o defeito (UC-CV-07 vermelho esperado) |
| **R5** `CalcularOrcamentoRequest` divergente | não reconferido | **não feito:** arquivo em `Modules/…/Http/Requests/`, fora do prefixo |
| **R4** total da tela ≠ servidor | sim — `Math.max(0, …)` e sem round na prévia | **não feito:** mexe no total exibido → REGRA MESTRE de valor (dupla prova + antes→depois + aprovação [W]). Não se mistura num PR de charter/a11y; merece PR próprio |

## Prova
`nao_contem "Stub Sprint 2"` em `resources/js/Pages/ComunicacaoVisual/Index.charter.md` — o placar confere.

## Não verificado aqui
Sem `node_modules` nesta máquina: typecheck/eslint ficam com o CI. Nenhum Pest foi alterado; os
testes que leem esses arquivos (`Wave25SaturationTest`) procuram `Persona-alvo`, `Anti-padrões` e
`Index.charter.md`, que continuam lá.
