---
sessao: "04"
titulo: Arquivos — simular retenção em dry-run (PR-8, D4) (recibo)
autor: "[CL]"
base: origin/main cd650b65c (2026-10-01, já com as threads 02 #8338 e 03 #8353)
---

# _saida-04 · Simular retenção em dry-run (PR-8 · D4 = sem purge pela UI)

## O que entreguei

| arquivo | o quê |
|---|---|
| `Modules/Arquivos/Routes/web.php` | `POST arquivos/retencao/simular` (`can:arquivos.governanca`), no grupo da tela |
| `Modules/Arquivos/Http/Controllers/RetencaoSimulacaoController.php` (novo) | recusa `purge` (erro na sessão, nada despachado); **força `dry_run = true`**; business da **sessão**; despacha o job com `dispatchAfterResponse()` |
| `Modules/Arquivos/Jobs/SimularRetencaoJob.php` (novo) | `run()` (dry-run) + `scanExpired()` + `report()`; recusa sozinho `dry_run=false`/`purge` (LogicException); relatório no cache 24h por business: por arquivo só `id`, data e motivo |
| `Modules/Arquivos/Http/Controllers/DataController.php` | **declara** `arquivos.restore` e `arquivos.governanca` (default `false`) — tarefa extra da sessão-mãe |
| `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` | 6 `it()` UC-INDEX-09, incl. **canário** e **cross-tenant 98 × 99** |
| `Index.casos.md` / `Index.charter.md` | UC-INDEX-09; charter registra a declaração de `arquivos.restore` e o PR-8 |

A simulação **não escreve** em `arquivos` nem em `arquivos_audit_log`: o teste confere que
nenhuma linha ganha `deleted_at` e que a trilha não muda de tamanho. O único efeito é o
relatório no cache e a linha de log do `report()`.

## Decisões técnicas (minhas, com o porquê)
- **Controller separado.** O `ArquivosAdminController` tem assert (UC-INDEX-01) que proíbe
  `dispatch(` no arquivo inteiro. Em vez de afrouxar o assert, a simulação mora num controller
  próprio, e um teste novo confere que o do acervo segue sem citar o job.
- **`dispatchAfterResponse()`, não fila `database`.** A fila `default` só é drenada pelo worker
  de backlog, atrás de flag (`app/Console/Kernel.php`, medido 2026-10-01); um job ali ficaria
  parado. Fila dedicada exige worker novo no Kernel, fora do prefixo. Assim a simulação roda no
  mesmo processo, depois da resposta — nunca no request.
- **Canário:** trocar o `true` do controller por `false` faz o `assertDispatchedAfterResponse`
  (que exige `dryRun === true`) reprovar.

## Tarefa extra — permissões declaradas
`arquivos.restore` (pedida pela `RestoreArquivoRequest` desde a thread 03) e `arquivos.governanca`
(desta thread) entram no `user_permissions()` do `DataController`, mesmo formato da
`arquivos.access`. **Nenhuma migration concede**: declarar só faz a permissão aparecer em
`/roles/{id}/edit`. O papel `Admin#{biz}` já passa pelo `Gate::before` e as enxerga.

## Pendente, e por quê
- **Botão na tela:** a vista de retenção segue leitura pura (UC-INDEX-04 diz "sem botão que
  execute"). Desenhar o botão "Simular" e a leitura do relatório do cache é UI nova, sem
  protótipo para essa interação no espelho que eu tenha conferido — fica para a próxima onda.
- **Quem recebe `arquivos.restore` / `arquivos.governanca`:** decisão [W] (aqui só declarei).
- **Fila dedicada** (se o relatório precisar de worker): exige entrada no `Kernel.php`.
- **Errata do prefixo:** nenhuma — os 2 arquivos novos estão sob `Http/Controllers/` e `Jobs/`.

## Provas do json
- `contem` `Modules/Arquivos/Routes/web.php` ⊇ `retencao/simular` — conferido pelo `placar.mjs`.
- `node scripts/casos-coverage-guard.mjs` → sem violações novas deste PR.
- Pest: NÃO rodei local (regra do projeto; `php` nem está no PATH desta máquina). A prova é o
  CI do PR (lane `PHP / Pest (Arquivos · MySQL)`, que já roda este arquivo de teste).

## PR
Branch `claude/arquivos-thread-04` (número no corpo do PR).
