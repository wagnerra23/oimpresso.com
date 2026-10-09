---
sessao: "12"
titulo: Tier 0 — edit/update com id de outro negócio — saída
playbook: sistema
thread: "12"
dono: "[CL]"
data: "2026-10-09"
tipo: recibo-retroativo
entregue_em: "#9055"
base: wagnerra23/oimpresso.com@main b7715dda
---
# _saida-12 · Tier 0 — edit/update com id de outro negócio (recibo retroativo)

Recibo escrito depois do merge. O PR que entregou a thread não escreveu o `_saida`, e não havia
`_saida-12.md` apagado no histórico para restaurar.

## Entregue
O [#9055](https://github.com/wagnerra23/oimpresso.com/pull/9055) foi mergeado em 2026-10-08 17:56 UTC
(`3ad8fe8dcf`).

- `PrinterController::edit`, `BarcodeController::edit` e `BusinessLocationController::edit` usavam
  `where('business_id')->find($id)`. Com id de outro negócio o model virava `null` e a tela dava 500.
  Passam a usar `findOrFail`, que responde 404.
- `BusinessLocationController::update` com id de outro negócio não gravava nada, mas respondia
  `success: true`. Passa a responder 404. A busca fica fora do `try`, senão o `catch` transformaria
  o 404 em `success: false`.
- O `edit` do Local não estava na thread. Tinha o mesmo `find()` e o mesmo 500, e entrou junto.
- O `LocaisBaselineTest` travava o comportamento antigo (200 com `success: true`). Foi corrigido no
  mesmo PR para esperar 404; a asserção de que nada foi gravado continua.

## Provas
- `tests/Feature/Configuracoes/TenantEditUpdateTest.php` (tenant 98 × 99, contraprova com o próprio
  id) nasceu no próprio #9055. O índice ganhou a thread 12 no #9017 (2026-10-07), antes do PR, então
  a prova mede a entrega.
- O PR registra o teste vermelho antes (4 de 4) e verde depois no CT 100, e que desfazer cada uma
  das 4 correções derruba o seu teste e só ele.
- `node scripts/governance/test-lane-coverage.mjs` não lista o teste entre os que ficam fora do PR.
- `node scripts/qa/placar.mjs --indice … --thread 12` passa a dizer `feito` com este recibo.

## Fora do escopo
Nada novo. Este PR só acrescenta o recibo.
