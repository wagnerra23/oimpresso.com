---
sessao: "_saida-12"
thread: "12 · Roadmap: esconder épico cancelado + colunas em ordem cronológica (D8 · D9)"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main ff67c8356
---
# _saida-12

## Antes: o placar dizia "sem recibo", e a prova estava errada
O placar de 2026-10-09 marcava a 12 como `proximo (sem recibo)`: a prova do índice
(`Index.casos.md` contém "cronológica") já era verdadeira **antes** de qualquer entrega,
porque a palavra estava na linha `[BACKLOG] Ordem das colunas entre anos`, que registra
o achado, não a correção. A thread **não tinha sido entregue**; foi executada nesta sessão.
Volta ao Cowork: a prova que separa entregue de não-entregue é o id do UC novo
(`UC-RQV-05`), não a palavra.

## Entregue
- **D9 (cronológica, [W] 2026-10-07):** `RoadmapController` trocou o `ksort` (ordem de
  texto, que punha `Q1-2027` antes de `Q2-2026`) por ordem cronológica. Aceita os dois
  formatos que existem no dado — `Qn-AAAA` (comentário do schema) e `AAAA-Qn` (exemplo do
  `epics-update` na própria tela). Texto não parseável vem depois, em ordem de texto;
  "Sem quarter" fica sempre por último.
- **D8 (esconder, [CC] 2026-10-07 — pergunta pulada pelo [W]):** o controller **já**
  filtrava `planning/active/done`, então o cancelado já não aparecia. O que faltava era o
  contrato: agora há UC e teste que reprovam se alguém incluir `cancelled` no filtro.
- `Index.casos.md`: **UC-RQV-04** e **UC-RQV-05** novos; as duas linhas `[BACKLOG]` que
  perguntavam isso saíram (a pergunta foi respondida).

## Provas
Dois casos novos em `Modules/Forja/Tests/Feature/Roadmap/RoadmapQuarterViewContratoTest.php`,
que já está na allowlist da lane MySQL `forja-pest.yml`:
- UC-RQV-04: epic ativo + cancelado no mesmo quarter → só o ativo chega e `total_epics = 1`
  (o ativo é o controle positivo: o sumiço é filtro, não payload vazio).
- UC-RQV-05: `Q1-2032`, nulo, `Q4-2031`, `2031-Q2` inseridos fora de ordem → colunas
  `2031-Q2 · Q4-2031 · Q1-2032 · Sem quarter`. Discriminante: com o `ksort` antigo o
  resultado seria `2031-Q2 · Q1-2032 · Q4-2031 · Sem quarter`, e o teste reprova.
Pest local é proibido: o veredito é o CI dessa lane.

## Fora do prefixo, declarado
O prefixo listava `Index.tsx`, `Index.casos.md` e `ForjaRoadmap*`. A ordem é decidida no
`RoadmapController` (é ali que o `ksort` morava), e só um teste do payload consegue provar
a ordem; ordenar no `.tsx` deixaria o contrato sem teste. Toquei o controller — que não
está no `nao_toca` — e **não** toquei o `Index.tsx`. O teste ficou no arquivo de contrato
que já existia (`Roadmap/RoadmapQuarterViewContratoTest.php`), não num `ForjaRoadmap*` novo,
para não duplicar fixture.
