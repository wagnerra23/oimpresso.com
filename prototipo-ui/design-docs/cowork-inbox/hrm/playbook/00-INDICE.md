---
sessao: "00"
titulo: SINCRONIZAR Hrm — índice do playbook
autor: "[CC]"
criado: 2026-09-05
base: wagnerra23/oimpresso.com@main (tree 159e572dd448 · lida 2026-09-05 21:44–22:01 UTC)
destino_no_main: prototipo-ui/design-docs/cowork-inbox/hrm/playbook/
regra: este índice é PEDIDO (lista de threads a executar, com sha), não inventário. A coluna prova é só-leitura durante a execução (Lei 2 de ponte/03). Nunca em prototipo-ui/cowork/ (guard R1).
---

# SINCRONIZAR Hrm — playbook

> **Absorve, não duplica:** `cowork-inbox/hrm/PEDIDO-CL-hrm.md` (D1/D2/D3 **respondidas por [W] em 2026-09-05**) + `cowork-inbox/hrm/EXPORT-HRM-2026-09-04.md` (alvo medido · a11y do alvo). Onde os dois divergem, **a emenda [W] manda**: Presença sai do HRM (Ponto é dono da jornada); Folha vira projeto com ADR própria. Quatro das nove ondas do export de 04/09 estão mortas — as threads 09 e 10 existem para o Code **não** executá-las.

## 1 · LEVANTAR — quadro por estado (rota-first · 4 sinais · 1 sha)

Denominador 1 = `Modules/Essentials/Routes/web.php` `prefix('hrm')` · Denominador 2 = `Modules/Essentials/Resources/views/layouts/nav_hrm.blade.php` (itens que apontam pra controller do **core**) · Denominador 3 = `TABS` de `hrm-page.jsx` (8 abas). Dicionário: **HRM = frente RH do `Modules/Essentials`**; a frente escritório (todo · KB · mensagens · documentos · lembretes) tem 7 Pages em `resources/js/Pages/Essentials/**` e fica fora.

| rota `/hrm/…` | Page `.tsx` | blade | em curso (caminho verificado) | estado | thread |
|---|---|---|---|---|---|
| `/dashboard` | — | `dashboard/hrm_dashboard.blade.php` | — | legado blade | 06 |
| `/leave` · `/change-status` · `/leave/activity/{id}` · `/user-leave-summary` | — | `leave/*` (5) | #6797 mergeado (validação + `Tests/Feature/HrmLicencaTest.php`) · charter/casos no commit `dbfc75fbcf` (fora de branch — `refs/pull/6800/head`) | **em implementação** | 02 |
| `/leave-type` (resource) | — | `leave_type/*` (3) | #6789 mergeado (`destroy` → 422 `blocked_by`) | em implementação | 03 |
| `/sales-target` · `/set-sales-target/{id}` · `/save-sales-target` | — | `sales_targets/*` (citado no PEDIDO; não listei) | #6799 mergeado (faixas) | em implementação | 04 |
| `/shift` (resource) · `/shift/assign-users` | — | `attendance/{shift_modal,add_shift_users,avail_shifts}` | — | legado blade | 05 |
| `/settings` GET+POST | `resources/js/Pages/Essentials/Settings/Index.tsx` | `settings/partials/*` | — | **produção React** 🔵 | 07 (puxar) |
| `/holiday` (resource) | `resources/js/Pages/Essentials/Holidays/Index.tsx` + `Index.charter.md` | `dashboard/holidays` | — | **produção React** 🔵 | 08 (puxar) |
| `/attendance` (resource) + 10 rotas | — | `attendance/*` (14) | #6798 mergeado (import) · **[W] D1: cede ao Ponto** | **sai do HRM** | 09 |
| `/payroll` (resource) + 9 rotas · `essentials/allowance-deduction` | — | `payroll/*` (14) | **[W] D2: folha completa com encargos → ADR própria** | **bloqueada** | 10 |
| nav → `TaxonomyController?type=hrm_department` · `hrm_designation` | — | core | — | **sem aba no protótipo** — achado do sentido rota→protótipo | 01 |
| — (protótipo tem aba **Turnos**; `nav_hrm` não lista shift, que vive dentro de `attendance/index`) | | | | divergência declarada | 05 |

Também medido nesta sha: `lang/pt` corrigido (#6778) · `.claude/commands/onda.md` **não existe** (PR-A7 não feito → abertura colada à mão, §3) · `Modules/Essentials/Resources/js/` **não existe** (os caminhos do #6800 não aterrissaram) · os arquivos `memory/sessions/2026-09-05-{como-integrar-ponto-hrm,arte-folha-encargos-br}.md` citados na emenda [W] **não existem** nesta sha — "em curso" citado, não encontrado · `criar-tela.mjs`/`whats-active`: citados pelo PEDIDO-CL; **não abri** (a listagem não expõe `.mjs`).

## 2 · Threads — ordem · dono · prefixo (Lei 1) · dependência

| # | thread | dono | prefixo que escreve | depende de | vaga |
|---|---|---|---|---|---|
| 01 | Build: TABS do HRM (−Presença · +Departamentos/Cargos) | [CC] | `hrm-page.jsx` · `hrm-data.jsx` · `oimpresso.com.html` (bump) | RESÍDUO 2 | 1 |
| 02 | Licenças — Page (lista + saldo por tipo) | [CL] | `<PAGES>/Hrm/Licencas/**` · `EssentialsLeaveController@index,@getUserLeaveSummary` · `prototipo-ui/contrato/hrm-licencas.contract.json` | — | 1 |
| 03 | Tipos de licença — Page | [CL] | `<PAGES>/Hrm/Tipos/**` · `EssentialsLeaveTypeController@index` | — | 1 |
| 04 | Metas de venda — Page | [CL] | `<PAGES>/Hrm/Metas/**` · `SalesTargetController@index` | — | 1 |
| 05 | Turnos — Page | [CL] | `<PAGES>/Hrm/Turnos/**` · `ShiftController` | ADR 0014 emendada · RESÍDUO 3 | 2 |
| 06 | Painel — Page | [CL] | `<PAGES>/Hrm/Painel/**` · `DashboardController@hrmDashboard` | 09 (cards de presença apontam pro Ponto) | 2 |
| 07 | Configurações — PUXAR (12 campos × 10 chaves) | [CC] read-only → [CL] se gap | nada; se gap, `Pages/Essentials/Settings/Index.tsx` | — | 1 |
| 08 | Feriados — PUXAR (ler `Holidays/Index.tsx`) | [CC] read-only | nada; se gap, build daqui | — | 1 |
| 09 | Presença SAI do HRM → pedido no Ponto | [W] + [CL] | `memory/decisions/0014-essentials-pontowr2-integracao.md` (emenda/supersede) · `Routes/web.php` (11 rotas cedem) | D1 | 2 |
| 10 | Folha — BLOQUEADA (projeto com ADR própria) | [W] | ADR nova, fora deste playbook | D2 | — |
| 11 | Fim do topnav Blade + limpeza O8 | [CL] | `layouts/nav_hrm.blade.php` · `partials/sidebar_hrm.blade.php` · blades `leave/* leave_type/* sales_targets/*` | 02–06 + screenshot [W2] | 3 |

**Vaga 1** (paralelo, prefixos disjuntos): 01 ∥ 02 ∥ 03 ∥ 04 ∥ 07 ∥ 08 · **Vaga 2:** 05 ∥ 06 ∥ 09 · **Vaga 3:** 11. Entre vagas, S0 consolida (ponte/03 §S0).
**`<PAGES>`** = o caminho que `criar-tela.mjs` gerar. Dois precedentes no `main`: `resources/js/Pages/Essentials/**` (7 irmãs) e `Modules/Essentials/Resources/js/Pages/Hrm/**` (tentativa do #6800). Se o gerador não resolver, **[W] decide uma vez e vale para 02–06** (RESÍDUO 1) — nenhuma thread escolhe sozinha.

## 2-bis · ESTADO — a única tabela que diz o que falta (o Code lê ESTA)

> **Fonte = `playbook.json` (ao lado) + o repo. Esta tabela é um RENDER de 2026-09-05 — não a edite: rode** `node cowork-inbox/_scripts/placar-indice.mjs --indice prototipo-ui/design-docs/cowork-inbox/hrm/playbook/playbook.json --root . --proximo` **e o estado sai calculado** (`feito` · `em curso` · `proximo` · `pendente` · `bloqueada`), com `PRÓXIMO:` = o que o Code pode abrir agora. Testado com repo simulado (7 casos, incl. T5: apagar 1 prova derruba o placar nomeando a thread e o arquivo).
> **Regra de leitura para o Code:** `_saida-NN.md` presente nesta pasta **e** todas as `prova:` verdes = `feito`. Sem `_saida` = **não feito**, mesmo com PR mergeado. `bloqueada` **não é pendência do Code** — é de [W]. Ninguém escreve estado: ele é derivado (Lei 2 por construção).

| # | thread | estado | `_saida` | o que falta para virar `feito` |
|---|---|---|---|---|
| 01 | Build TABS | `pendente` (executável já na parte −Presença) | — | RESÍDUO 2 para +Departamentos/Cargos |
| 02 | Licenças | `pendente` | — | RESÍDUO 1 (`<PAGES>`) |
| 03 | Tipos de licença | `pendente` | — | RESÍDUO 1 |
| 04 | Metas de venda | `pendente` | — | RESÍDUO 1 |
| 05 | Turnos | `pendente · vaga 2` | — | RESÍDUO 1 · RESÍDUO 3 · 0014 emendada (09) |
| 06 | Painel | `pendente · vaga 2` | — | RESÍDUO 1 · 09 fechada |
| 07 | Configurações (puxar) | `pendente` (read-only) | — | nada — pode abrir agora |
| 08 | Feriados (puxar) | `pendente` (read-only) | — | nada — pode abrir agora |
| 09 | Presença sai | `pendente · [W]` | — | ratificar emenda da 0014 |
| 10 | Folha | **`bloqueada` (D2 → ADR)** | — | ADR nova — fora deste playbook |
| 11 | Topnav legado | `pendente · vaga 3` | — | 02–06 `feito` + screenshot [W2] |

**Contagem (sha `159e572d`, 2026-09-05, saída do script):** `Hrm: entregue 0 de 11 · próximo 4 · em curso 0 · pendente 6 · bloqueada 1`. **PRÓXIMO agora, sem decisão nenhuma: 01 · 07 · 08 · 09.** Uma decisão (RESÍDUO 1 → preenche `variaveis.PAGES`) acrescenta 02 · 03 · 04.

### Fluxo consolidado (o que cada thread faz, em 6 passos, sempre iguais)
```
1 ABRIR    sessão limpa · whats-active · colar o bloco §3 · ler NN-*.md e a âncora no main (sha no _saida)
2 MEDIR    (só threads de Page) T1 duas leituras iguais → alvo do NN (contagem · ORDEM · tokens) — read-only
3 GERAR    criar-tela.mjs <Mod/Tela> PT-0X → substituir charter/casos pelos textos já revisados (nunca charter sem tela)
4 APLICAR  1–3 arquivos do prefixo · reusar átomos/serviços listados · PARAR SE do NN vale mais que terminar
5 PROVAR   cada prova: do NN verde (arquivo · teste · contract.json no schema · placar no corpo do PR)
6 FECHAR   _saida-NN.md (feito · não feito e por quê · pedido literal · descobertas · prefixo tocado) → parar
```
Entre vagas: **S0** lê os `_saida`, roda o script e re-renderiza esta tabela (nunca a edita à mão). **T7** (`design-diff --compare --check`, prod deployada) é o único que afirma paridade — e não roda daqui.

## 3 · Abertura de thread (colar como 1ª mensagem — sessão limpa)
```
Sessão fresca. Rode `whats-active` ANTES de abrir (colisão de 04/09: três sessões no mesmo pedido).
Leia nesta ordem, do main, nunca de cópia local:
1. prototipo-ui/design-docs/cowork-inbox/ponte/03-REGRAS-DE-PARALELISMO.md   ← Leis 1–4
2. prototipo-ui/design-docs/cowork-inbox/hrm/playbook/00-INDICE.md           ← §1 estados · §2 seu prefixo
3. prototipo-ui/design-docs/cowork-inbox/hrm/playbook/NN-<sua-thread>.md     ← escopo · alvo · dado · prova
4. prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md §"Emenda 2026-09-05 [W]"  ← D1/D2/D3
5. prototipo-ui/PRE-FLIGHT-TELA.md · memory/proibicoes.md · memory/LICOES_CC.md
6. os arquivos da âncora listados na sua thread
Você escreve SOMENTE no seu prefixo e no seu _saida-NN.md. Não edita este índice, github.md nem memory/**.
Terminou: escreva _saida-NN.md (feito · não feito e por quê · pedido literal · descobertas · prefixo tocado) e pare.
```

## 4 · VERIFICAR — placar da lista
Uma thread conta como **entregue** só com (a) `_saida-NN.md` com os 5 itens **e** (b) todas as `prova:` da thread verdes lendo o `main`. Page mergeada sem `_saida` **não conta**. `PLACAR Hrm` = [CC] lê o `main` no turno e confere cada prova → `entregue X de 11 · ausentes <thread> por <motivo>`. **T7** (`design-diff --compare --check` nos dois renders, prod deployada) e CI verde **não são visíveis daqui** — o placar afirma "arquivos verdes", nunca "paridade".

**PLACAR Hrm em 2026-09-05 (sha 159e572d): entregue 0 de 11.** Parciais já no `main` que as threads reusam (não recriar): #6778 lang PT · #6797 validação + `HrmLicencaTest` · #6799 faixas de meta · #6789 `leave-type destroy` 422 · #6798 import de presença (superfície que vai ceder). Nenhuma Page, nenhum `_saida`.

## 5 · Revisão 3× por passo — o que reprovou e foi corrigido antes de você ler
| passo | R1 · fonte | R2 · falsificação | R3 · frescor / passagem pro próximo |
|---|---|---|---|
| **LEVANTAR** | rota-first só em `Routes/web.php` **perde** itens do nav que apontam pro core (Departamentos/Cargos → `TaxonomyController`). Corrigido: o nav blade é denominador 2 | protótipo × nav nos dois sentidos achou 2 abas faltando e 1 sobrando (Turnos) — o método reprova de fato | a sha mudou durante a sessão (`e0f2b79c`→`159e572d`); a emenda [W] cita 2 arquivos que não existem nesta sha → "em curso" só conta com caminho existente |
| **PUXAR** | 2 telas 🔵 (Settings lida em 04/09 · Holidays **não lida**) → threads 07/08 nascem read-only | puxar Settings às cegas apagaria 2 campos do protótipo; `Presenca.charter` P1 mostra que 1 deles saiu das settings **de propósito** → medir campo a campo antes | PUXAR traz átomos/aria/dados, nunca o layout (inércia da produção) |
| **REACT** | régua do repo: `criar-tela.mjs` carimba tsx+charter+casos+e2e+contrato **juntos**; charter sem tela derruba `charter_refs_broken` (teto 0) → toda thread de Page **começa** pelo gerador | caminho da Page tem 2 precedentes → `PARAR SE`, [W] decide 1× | D1/D2 matam 4 das 9 ondas do EXPORT de 04/09 → threads 09/10 existem para o Code não executar onda morta |
| **PLAYBOOK** | Lei 1: as 2 threads de build (−Presença · +Departamentos) tocavam o mesmo `hrm-page.jsx` → fundidas na 01 | teste do estranho: 06 (Painel) não tem dado lido → a thread diz **onde** ler e o que vira `—` | `whats-active` entrou na abertura; `.claude/commands/onda.md` não existe → abertura colada à mão |
| **VERIFICAR** | prova = **caminho**, não nome (`HrmLicencaTest.php` canônico é o do #6797, não o do #6800) | Page mergeada sem `_saida` não conta — é o que impede omissão grátis | T7/CI não visíveis daqui → placar nunca diz "paridade" |

## 6 · RESÍDUO Hrm — fila de decisão [W]
1. **`<PAGES>`**: `resources/js/Pages/Hrm/` ou `Modules/Essentials/Resources/js/Pages/Hrm/`? Uma decisão, vale para 5 threads.
2. **Departamentos e Cargos** viram abas do HRM no protótipo (o nav de produção tem, com `crud_department`/`crud_designation`) — confirma?
3. **`ShiftController::destroy`**: #6789 fechou só `leave-type`; turno com vínculo ainda responde 200 sem apagar? (a 05 para se sim)
4. **`DataTablePro` do DS** — 3º módulo com `th` sem `scope`/semântica: pedido de DS próprio em vez de repetir por módulo?
