---
sessao: "_saida-10"
thread: "10 · Rodar UC-CONN-12 na lane MySQL (pulado em SQLite)"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main ce42054202
---
# _saida-10

## Resultado
UC-CONN-12 **já roda e passa na lane MySQL**. A premissa da thread (vinda do `_saida-02`, de
2026-10-01 de manhã) caducou no mesmo dia: o PR #8382 criou a lane `connector-pest.yml`
("Connector · Pest (MySQL)"), e `ApiClientsPanelTest.php` está na lista de arquivos dela desde
o nascimento. Não havia allowlist a editar, e nenhum arquivo de código ou de CI foi alterado
nesta thread — este recibo é a entrega inteira.

## Provas (medidas em 2026-10-05)

**Antes — SQLite, `modules-pest.yml`, run [36871040472](https://github.com/wagnerra23/oimpresso.com/actions/runs/36871040472)**
(push em `main`, `1bab6d319a`), job `Pest Connector`:

```
WARN  Modules\Connector\Tests\Feature\ApiClientsPanelTest
- destroy revoga tokens do client → SQLite-incompatível: Passport + schema UltimatePOS exigem MySQL (ADR 0358)
```

**Depois — MySQL, `connector-pest.yml`, run [36868265003](https://github.com/wagnerra23/oimpresso.com/actions/runs/36868265003)**
(push em `main`, `32f86c84b0`, #8405 — o Pest executou: o paths-filter casou e a lista de
arquivos alterados inclui `ApiClientsPanelTest.php`):

```
PASS  Modules\Connector\Tests\Feature\ApiClientsPanelTest
  ✓ destroy revoga tokens do client                     0.29s
  ✓ destroy revoga refresh e devolve a contagem         0.30s
  ✓ destroy de client alheio nao revoga os tokens dele  0.29s
Tests:    194 passed (725 assertions)
```

Sumário JUnit do mesmo run, arquivo `ApiClientsPanel`: **26 tests · 26 passed · 0 failed ·
0 errors · 0 skipped · 228 assertions**.

| | lane | ApiClientsPanelTest | UC-CONN-12 |
|---|---|---|---|
| antes | `modules-pest` (SQLite) | skip no `setUp` | skipped (0 assertions) |
| depois | `connector-pest` (MySQL) | 26 passed · 228 assertions | 3 casos ✓ PASS |

Lane inteira no 1º push (`36839128865`, #8382): 152 passed (581 assertions); no run citado:
194 passed (725 assertions).

**O run citado vale para o `main` de hoje:** `git log 32f86c84b0..origin/main -- Modules/Connector
tests/Feature/Connector .github/workflows/connector-pest.yml` devolve vazio. Nada que a lane
executa mudou depois dele.

## Por que não usei um run de PR
Os runs de `pull_request` desta lane (os 8 mais recentes, todos `success`) são de PRs que não
tocam o Connector: o `dorny/paths-filter` dá `false` e o job sai pelo skip-as-pass sem rodar o
Pest. `success` ali não é medição. O run citado é de `push` com o filtro casando e o step do Pest
executado, com o `--check-assertions` armado.

## Ressalva
A lane é **advisory** (nasceu assim no #8382). Isto prova que UC-CONN-12 passa no MySQL; não
afirma que bloqueia merge.

## Placar
`10 [feito]` pela prova do json: UC-CONN-12 verde na lane MySQL, run citado acima.
O `00-INDICE.md` não foi editado.
