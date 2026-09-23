---
sessao: "_saida-01"
thread: "01 · Charter + casos do Programa"
dono: "[CL]"
data: 2026-09-23
base_lida: wagnerra23/oimpresso.com@main 1061dbf2e
---
# _saida-01

## Entregue
- `resources/js/Pages/Documentacao/Programa.charter.md` — `status: draft`. Non-Goals e Anti-hooks ficaram **vazios, com a marca `_pendente [W]_`**.
- `resources/js/Pages/Documentacao/Programa.casos.md` — 5 casos derivados do `PLANO-MESTRE.md` § Trilha D, **como `[BACKLOG]`, sem `## UC-`**.

## O que mudou em relação à proposta do Cowork (medido no main, não copiado)
1. **A rota já existe e é Blade.** `routes/web.php` → `DocumentacaoController::programa()` → `resources/views/documentacao/programa.blade.php`, defendida por `tests/Feature/DocumentacaoRouteTest.php`. O `01-trio.md` dizia "nenhum arquivo" porque só buscou o `.tsx`.
2. **As seções do plano mudaram de número.** Hoje: D.2 estados · D.3 ondas · D.4 ciclo · D.5 caminhos · D.6 batimento · D.7 DoD. A proposta citava D.5 ondas / D.8 DoD.
3. **Dois ADRs errados.** Existem dois arquivos `0294-*`; o do "1 plano = 1 registro" é o `0294-metodo-dual-track-shapeup-catraca`, citado por slug. O `0286` é `channel-health`, não o contrato de tela — saiu do `related_adrs`, e o casos aponta pro `RUNBOOK-contrato-de-tela.md`.
4. `related_prototype` corrigido para `prototipo-ui/cowork/Wagner/programa-doc-page.jsx` (a proposta apontava `prototipo-ui/cowork/`, que não existe). O `ancora.mjs Documentacao/Programa` resolve.
5. `last_run: null` → data real (o G-5 reprovaria `null`).

## Por que os casos não viraram UC
O casos-gate (G-2) exige que todo `## UC-` seja citado por um teste em `tests/`, `Modules/`, `app/` ou `e2e/`. O prefixo desta thread é só `resources/js/Pages/Documentacao/`, e ali teste não conta. Declarar os UC agora criaria 5 órfãos num gate required, ou stubs `fixme` que só provam presença (LC-11). Os ids `UC-PROGDOC-01..05` ficam reservados. A thread 02 os declara junto com os testes.

## Para [W] (o charter lista em "Perguntas abertas")
1. **De onde vem o estado de execução da onda.** A D.2 do plano diz *tasks MCP*; o `## Status vivo` do mesmo plano diz "D0 em execução"; a Blade lê o segundo e um teste verde trava isso. A thread 02 precisa desta resposta antes de nascer.
2. Placement do porte: Inertia própria ou manter a Blade, que já lê o plano em runtime.
3. Non-Goals + Anti-hooks do charter.

## Provas
- `node scripts/qa/placar.mjs --indice …/programa-doc/playbook/00-INDICE.md --thread 01` — os dois arquivos do JSON existem.
- `node scripts/casos-coverage-guard.mjs` → sem violações novas (rc 0).
- `node scripts/memory-schemas/validate.mjs …/Programa.charter.md` → conforme ao `charter.schema.json`.
- `node scripts/qa/uc-id-lint.mjs` → 0 fora do formato.
- `node scripts/qa/screen-coverage-map.mjs --check` → catraca ok.
