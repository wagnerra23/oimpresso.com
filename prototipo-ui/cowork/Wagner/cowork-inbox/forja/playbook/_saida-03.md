---
sessao: "_saida-03"
thread: "03 · UCs órfãos do Cockpit e do Scorecard"
dono: "[CL]"
data: 2026-10-07
tipo: recibo
entregue_em: "este PR"
base_lida: wagnerra23/oimpresso.com@main 03f5d9d822
---
# _saida-03

## O que o playbook pedia × o que medi
O playbook (base `836619f`) listava como órfãos `UC-FORJA-03 · 08 · 09 · 10` e `UC-SC-02 · 06`.
Medido no `origin/main` fresco (`03f5d9d822`) antes de qualquer edição: **os mesmos 6**, nenhum a
mais nem a menos. Ids conferidos únicos no repo (`grep` de cabeçalho `## UC-…` em todo `*.casos.md`,
1 ocorrência cada; nenhuma cópia no espelho `prototipo-ui`).

## Entregue
| UC | Destino | Onde |
|---|---|---|
| UC-FORJA-03 | teste novo; nasceu vermelho de propósito, e com o conserto do #8949 no main o veredito vem do CI deste PR (achado abaixo) + redação reconciliada com a decisão [W] de 2026-09-08 (#7038) | `Modules/Forja/Tests/Feature/ForjaSidebarEntradaContratoTest.php` |
| UC-FORJA-08 | teste novo | `Modules/Forja/Tests/Feature/ForjaTriagemContratoTest.php` |
| UC-FORJA-09 | 4 testes novos (dossiê · aprovar · rejeitar · fundir) | idem |
| UC-FORJA-10 | 2 testes novos (dossiê não escreve · ações POST-only). A perna do `AlertDialog` é UI e segue manual | idem |
| UC-SC-02 | 3 testes de render (jsdom) | `tests/js/forja-scorecard-semaforo.test.tsx` |
| UC-SC-06 | **rebaixado a prosa**, sem teste | `team-mcp/Scorecard/Index.casos.md` |

Lanes: os 2 arquivos Pest entraram na allowlist de `forja-pest.yml` (com `app/Utils/ModuleUtil.php` e
`app/Http/Middleware/AdminSidebarMenu.php` no gatilho, que são o código sob teste do UC-FORJA-03); o
spec entrou em `forja-jsdom-gate.yml` (com o `Index.tsx` do Scorecard no gatilho).

Quem provou: o spec jsdom rodou **local** (vitest não é Pest): `3 passed`. Os Pest **não rodaram
local nem no CT 100** — a prova é o CI deste PR (lane `PHP / Pest (Forja · MySQL)`).

## UC removido: UC-SC-06 (DS v6, sem cor crua)
Razão: conformidade não é caso de uso — o critério de aceite era "eslint `ds/*` = 0 e conformance
verde", e um teste reafirmando gate é régua paralela (§5 2026-07-09). Mesmo destino do gêmeo
`UC-FORJA-06`, rebaixado em 2026-07-27 (#4879); o `SDD-tela-hub-team-mcp-v1.0.md` §9.3 registrou a
incoerência entre os dois e a deixou "pra corrida que tratar o Scorecard". Medido antes: 0 cor
crua, 0 `rounded-xl`, e 1 `ds/no-db-jargon-in-ui` (linha 168, nome de tabela no aviso), já na
catraca `config/eslint-baseline.json` — a redação antiga "= 0 `ds/*`" era falsa por causa dele.
Virou item `[BACKLOG]` no casos.md.

## Achado — a entry "Forja" não aparece na sidebar em produção
`AdminSidebarMenu` chama `modifyAdminMenu` só de módulo instalado (`ModuleUtil::getModuleData` →
`isModuleInstalled`), que procura `system.forja_version`. O `InstallController` da Forja grava
`projectmgmt_version` (fachada legacy, ADR 0088). Medido em produção, 2026-10-07, HEAD
`6b0cd71f8f`, `php artisan tinker` só leitura: `projectmgmt_version=0.1`, sem `forja_version`;
`isModuleInstalled("Forja") = false` (controle: `Jana = true`). O `DataController` da Forja nunca
roda — nem a entry da #7038 nem o checkbox `brief.access` chegam. O teste do UC-FORJA-03 monta esse
mesmo estado e ficava vermelho. Correção = decisão [W]. **Desfecho (2026-10-07):** [W] escolheu aceitar o
nome antigo sem gravar no banco; o #8949 fez o `isModuleInstalled` aceitar `projectmgmt_version` para a
Forja e entrou no main antes deste PR.

## `--screen` depois (árvore deste PR)
```
=== team-mcp/Forja/Cockpit.tsx ===
  UC ↔ teste: ✓ UC-FORJA-01 · 02 · 14 · 15 · 03 · 05 · 07 · 08 · 09 · 10 · 12 · 13 · 18 · 16 · 17 · 19
  VEREDITO:
    trio completo: ✓
    ✓ sem declaração quebrada, ambiguidade de nome, nem UC órfão

=== team-mcp/Scorecard/Index.tsx ===
  UC ↔ teste: ✓ UC-SC-01 · 02 · 03 · 04 · 05 · 07 · 08
  VEREDITO:
    trio completo: ✓
    ✓ sem declaração quebrada, ambiguidade de nome, nem UC órfão
```
⚠️ "não órfão" = algum teste cita o id. Não é veredito de verde: o `UC-FORJA-03` cita e reprova, e o
`ScorecardContratoTest` (UC-SC-01/03/04/05/07/08) ainda não roda em lane nenhuma — fora do escopo
desta thread, nota no fim do `Scorecard/Index.casos.md`.
