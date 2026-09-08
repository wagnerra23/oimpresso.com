---
sessao: "00"
titulo: SINCRONIZAR Hrm — índice do playbook (fonte da máquina embutida em §7)
autor: "[CC]"
criado: 2026-09-05
revisado: 2026-09-05 rev.2 — após medição do [CL] contra origin/main a88c66a (4 defeitos corrigidos, ver §5 R4)
base: wagnerra23/oimpresso.com@main (tree 45e63465d2e4 · lida 2026-09-05 22:26 UTC)
destino_no_main: prototipo-ui/design-docs/cowork-inbox/hrm/playbook/
regra: este índice é PEDIDO (lista de threads a executar, com sha), não inventário. Ninguém escreve estado — ele é derivado (§2-bis). Nunca em prototipo-ui/cowork/ (guard R1).
---

# SINCRONIZAR Hrm — playbook

> **Absorve, não duplica:** `cowork-inbox/hrm/PEDIDO-CL-hrm.md` (D1/D2/D3 respondidas por [W] em 2026-09-05) + `cowork-inbox/hrm/EXPORT-HRM-2026-09-04.md`. Onde divergem, **a emenda [W] manda**: Presença sai do HRM; Folha vira projeto com ADR própria. **Metas já está em produção (#6869)** — a onda 9 do export está feita.

## 0 · Landing — como esta pasta desce (resposta ao [CL], 2026-09-05)
- **A unidade é a PASTA inteira** (`00-INDICE.md` + 11 `NN-*.md`): índice sozinho aponta pra arquivos inexistentes — o mesmo defeito do item 3 do §5. Rota: DesignSync `get_file` de cada `.md` → `--export-from <dir>`; `.md` roteia pra `prototipo-ui/design-docs/cowork-inbox/hrm/playbook/`.
- **Só `.md` roteia.** Por isso a fonte da máquina (`playbook.json`) **não é arquivo**: é o primeiro bloco ```json deste índice (§7). O schema e o script viajam como anexos de `COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md` (PR-A8) e o [CL] os cria nos paths lá declarados. Nada `.json`/`.mjs` solto neste pacote.
- **`.md` em `prototipo-ui/cowork/` é proibido** (guard R1) — se o roteador mandar pra lá, é erro de rota, não exceção a pedir.

## 1 · LEVANTAR — quadro por estado (rota-first · **4 denominadores** · 4 sinais · 1 sha)

Denominadores: **D1** `Modules/Essentials/Routes/web.php` `prefix('hrm')` · **D2** `layouts/nav_hrm.blade.php` (itens que apontam pro core) · **D3** `TABS` de `hrm-page.jsx` · **D4 (novo — faltava, e por isso Metas escapou)** `Inertia::render(` nos controllers do módulo = **a rota já tem Page React?** Medido: 14 renders em `Modules/Essentials/Http/Controllers/**`; do HRM: `Essentials/Metas` · `Essentials/Holidays/Index` · `Essentials/Settings/Index`. Convenção viva das Pages: **`resources/js/Pages/Essentials/`** (14 `.tsx`; flat `Metas.tsx` **ou** pasta `<Sub>/Index.tsx` — quem decide é `criar-tela.mjs`). `resources/js/Pages/Hrm/` e `Modules/Essentials/Resources/js/` **não existem**.

| rota `/hrm/…` | D4 `Inertia::render` | Page `.tsx` | blade | em curso (caminho verificado) | estado | thread |
|---|---|---|---|---|---|---|
| `/dashboard` | — | — | `dashboard/hrm_dashboard.blade.php` | — | legado blade | 06 |
| `/leave` · `/change-status` · `/leave/activity/{id}` · `/user-leave-summary` | — | — | `leave/*` (5) | #6797 mergeado (validação + `Tests/Feature/HrmLicencaTest.php`) · charter/casos em `dbfc75fbcf` (fora de branch — `refs/pull/6800/head`) | em implementação | 02 |
| `/leave-type` (resource) | — | — | `leave_type/*` (3) | #6789 mergeado (`destroy` → 422 `blocked_by`; `HrmExclusaoGuardaTest.php`) | em implementação | 03 |
| `/sales-target` · `/set-sales-target/{id}` · `/save-sales-target` | **`Essentials/Metas`** (`SalesTargetController.php:80`) | **`Essentials/Metas.tsx`** + `Metas.charter.md` + `Metas.casos.md` | `sales_targets/index` (ramo ajax mantido até O8) | **#6869 mergeado** — pacote completo: contrato `essentials-metas.contract.json` · `HrmMetasTest.php` · `e2e/essentials-metas.spec.ts` · RUNBOOK · lane `essentials-pest.yml` | **produção React 🔵** | 04 (puxar) |
| `/shift` (resource) · `/shift/assign-users` | — | — | `attendance/{shift_modal,add_shift_users,avail_shifts}` | — | legado blade | 05 |
| `/settings` GET+POST | `Essentials/Settings/Index` | `Essentials/Settings/Index.tsx` | `settings/partials/*` | — | produção React 🔵 | 07 (puxar) |
| `/holiday` (resource) | `Essentials/Holidays/Index` | `Essentials/Holidays/Index.tsx` + charter | `dashboard/holidays` | — | produção React 🔵 | 08 (puxar) |
| `/attendance` (resource) + 10 rotas | — | — | `attendance/*` (14) | #6798 mergeado · **[W] D1: cede ao Ponto** · cron `pos:autoClockOutUser` em `EssentialsServiceProvider.php:108` · ponteiro `Modules/Ponto/Config/config.php:136` | **sai do HRM** | 09 |
| `/payroll` (resource) + 9 rotas · `essentials/allowance-deduction` | — | — | `payroll/*` (14) | **[W] D2: folha completa → ADR própria** | **bloqueada** | 10 |
| nav → `TaxonomyController?type=hrm_department` · `hrm_designation` | (core) | — | core | — | **sem aba no protótipo** | 01 |
| — (protótipo tem aba **Turnos**; `nav_hrm` não a lista) | | | | | divergência declarada | 05 |

Também medido nesta sha: `.claude/commands/onda.md` **não existe** (PR-A7 não feito → abertura colada à mão) · os planos `memory/sessions/2026-09-05-{como-integrar-ponto-hrm,arte-folha-encargos-br}.md` citados pela emenda [W] e reafirmados no #6876 **não existem** (1 hit no repo inteiro = o próprio PEDIDO se citando; [CL] confirmou em a88c66a) · `whats-active` **morto** (HTTP 000 medido pelo [CL] em 05/09) — substituto: `gh pr list --state open` cruzado com os arquivos a tocar.

## 2 · Threads — ordem · dono · prefixo (Lei 1) · dependência

| # | thread | dono | prefixo que escreve | depende de | vaga |
|---|---|---|---|---|---|
| 01 | Build: TABS do HRM (−Presença · +Departamentos/Cargos) | [CC] | `hrm-page.jsx` · `hrm-data.jsx` · `oimpresso.com.html` (bump) | RESÍDUO 2 só para a parte + | 1 |
| 02 | Licenças — Page | [CL] | `Pages/Essentials/Licencas{.tsx,/Index.tsx}` + charter/casos · `EssentialsLeaveController@index,@getUserLeaveSummary` · `contrato/essentials-licencas.contract.json` · `Tests/Feature/HrmLicencaTest.php` (estender) · `e2e/essentials-licencas.spec.ts` · lane `essentials-pest.yml` | — | 1 |
| 03 | Tipos de licença — Page | [CL] | `Pages/Essentials/Tipos{.tsx,/Index.tsx}` + trio · `EssentialsLeaveTypeController@index` · `contrato/essentials-tipos.contract.json` · `e2e/essentials-tipos.spec.ts` | — | 1 |
| 04 | Metas — **PUXAR** (produção à frente, #6869) | [CC] read-only → build | nada no `main`; se gap, `hrm-extras.jsx` (`Metas`) | — | 1 |
| 05 | Turnos — Page | [CL] | `Pages/Essentials/Turnos{.tsx,/Index.tsx}` + trio · `ShiftController` · `contrato/essentials-turnos.contract.json` · `e2e/essentials-turnos.spec.ts` | 09 · RESÍDUO 3 | 2 |
| 06 | Painel — Page | [CL] | `Pages/Essentials/Painel{.tsx,/Index.tsx}` + trio · `DashboardController@hrmDashboard` · `contrato/essentials-painel.contract.json` | 09 | 2 |
| 07 | Configurações — PUXAR (12 campos × 10 chaves) | [CC] read-only → [CL] se gap | nada; se gap, `Pages/Essentials/Settings/Index.tsx` | — | 1 |
| 08 | Feriados — PUXAR (ler `Holidays/Index.tsx`) | [CC] read-only | nada; se gap, build daqui | — | 1 |
| 09 | Presença SAI do HRM → Ponto dono da jornada | [W] + [CL] | `memory/decisions/0014-*.md` (emenda) · `Routes/web.php` (11 rotas) · **`Providers/EssentialsServiceProvider.php` (:108 desagendar cron)** · **`Modules/Ponto/Config/config.php` (:136 ponteiro morto)** | D1 · D3 | 2 |
| 10 | Folha — BLOQUEADA (ADR própria) | [W] | fora deste playbook | D2 | — |
| 11 | Fim do topnav Blade + limpeza O8 | [CL] | `layouts/nav_hrm.blade.php` · `partials/sidebar_hrm.blade.php` · blades `leave/* leave_type/* sales_targets/* dashboard/hrm_dashboard` | 02 · 03 · 05 · 06 + screenshot [W2] | 3 |

**Âncora de implementação para toda Page nova = a irmã golden `resources/js/Pages/Essentials/Metas.tsx` (#6869)** — mesmo pacote de 8 peças (tsx · charter com frontmatter `component:`/`runbook:` · casos · `contrato/essentials-<tela>.contract.json` gerado pelo `criar-tela.mjs` · Pest `Hrm<Tela>Test.php` · `e2e/essentials-<tela>.spec.ts` · `RUNBOOK-<tela>.md` · lane em `essentials-pest.yml`). Alvo de layout continua o protótipo medido (`prototipo-ui/cowork/hrm-*.jsx`, campo `related_prototype` do charter).
**Vaga 1:** 01 ∥ 02 ∥ 03 ∥ 04 ∥ 07 ∥ 08 · **Vaga 2:** 05 ∥ 06 ∥ 09 · **Vaga 3:** 11. Entre vagas, S0 consolida.

## 2-bis · ESTADO — derivado, nunca escrito (o Code lê ESTA)

> **Fonte = bloco ```json do §7 + o repo.** Rode `node scripts/qa/placar-indice.mjs --indice prototipo-ui/design-docs/cowork-inbox/hrm/playbook/00-INDICE.md --root . --proximo`. Regra: `_saida-NN.md` presente **e** provas verdes = `feito`; sem `_saida` = não feito mesmo com PR mergeado; `bloqueada` é fila de [W], não do Code. `PRÓXIMO:` = deps de thread feitas + decisões respondidas + nenhuma variável nula.

**Render 2026-09-05 rev.2 (saída do script contra repo simulado = `main` 45e63465):** `Hrm: entregue 0 de 11 · próximo 6 · em curso 0 · pendente 4 · bloqueada 1` — **PRÓXIMO: 01 · 02 · 03 · 07 · 08 · 09.** Presos: 04 (RESÍDUO 5) · 05 (09 + RESÍDUO 3) · 06 (09) · 11 (02·03·05·06). O RESÍDUO 1 (`<PAGES>`) **deixou de existir** — a árvore respondeu. Testado: entrega flat **ou** pasta conta; Page criada sem `Inertia::render` no controller fica "em curso" nomeando o controller (D4 é prova); 09 com o cron vivo fica "em curso" nomeando `EssentialsServiceProvider.php:108`.

### Fluxo (6 passos, iguais para toda thread)
```
1 ABRIR    sessão limpa · gh pr list --state open × arquivos a tocar (whats-active está morto) · colar §3 · ler NN-*.md + âncora no main (sha no _saida)
2 MEDIR    (Pages) T1 duas leituras iguais → alvo do NN (contagem · ORDEM · tokens) — read-only
3 GERAR    criar-tela.mjs Essentials/<Tela> PT-0X → carimba tsx+charter+casos+e2e+contrato JUNTOS; substituir pelos textos já revisados
4 APLICAR  1–3 arquivos do prefixo · reusar átomos/serviços listados · PARAR SE vale mais que terminar
5 PROVAR   provas do NN verdes · placar no corpo do PR · lane Pest verde
6 FECHAR   _saida-NN.md (feito · não feito e por quê · pedido literal · descobertas · prefixo tocado) → parar
```

## 3 · Abertura de thread (colar como 1ª mensagem — sessão limpa)
```
Sessão fresca. ANTES de abrir: `gh pr list --state open` e cruze com os arquivos do seu prefixo (whats-active está morto — HTTP 000; colisão de 04/09 veio de ninguém checar).
Leia nesta ordem, do main, nunca de cópia local:
1. prototipo-ui/design-docs/cowork-inbox/ponte/03-REGRAS-DE-PARALELISMO.md   ← Leis 1–4
2. prototipo-ui/design-docs/cowork-inbox/hrm/playbook/00-INDICE.md           ← §1 estados · §2 seu prefixo · §7 fonte
3. prototipo-ui/design-docs/cowork-inbox/hrm/playbook/NN-<sua-thread>.md     ← escopo · alvo · dado · prova
4. prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md §"Emenda 2026-09-05 [W]" ← D1/D2/D3.
   AVISO: os 2 planos de memory/sessions/ que ela manda ler NÃO EXISTEM no main (medido 159e572d e a88c66a). Não bloqueie neles.
5. resources/js/Pages/Essentials/Metas.tsx + Metas.charter.md                ← a irmã golden: o pacote que sua Page tem de repetir
6. prototipo-ui/PRE-FLIGHT-TELA.md · memory/proibicoes.md · memory/LICOES_CC.md
7. os arquivos da âncora listados na sua thread
Você escreve SOMENTE no seu prefixo e no seu _saida-NN.md. Não edita este índice, github.md nem memory/**.
Terminou: escreva _saida-NN.md e pare.
```

## 4 · VERIFICAR — placar da lista
Thread `feito` = `_saida-NN.md` com os 5 itens **e** provas verdes lendo o `main`. `PLACAR Hrm` = rodar o script (ou [CC] lendo o `main` no turno, se o script ainda não aterrissou). **T7** (`design-diff --compare --check` nos dois renders, prod deployada) e CI verde não são visíveis daqui — o placar afirma "arquivos verdes", nunca "paridade". Parciais já no `main` que as threads **reusam** (não recriar): #6778 lang PT · #6797 validação + `HrmLicencaTest` · #6799 faixas · #6789 `destroy` 422 · #6798 import · **#6869 Metas completa**.

## 5 · Revisão 3× por passo — o que reprovou e foi corrigido
| passo | R1 · fonte | R2 · falsificação | R3 · frescor | **R4 · medição do [CL] (a88c66a)** |
|---|---|---|---|---|
| **LEVANTAR** | rota-first perdia itens do nav (Departamentos/Cargos) → nav = D2 | protótipo × nav nos dois sentidos achou 2 abas faltando e 1 sobrando | sha mudou na sessão; "em curso" só com caminho existente | **faltava o denominador de runtime (`Inertia::render`)**: Metas estava feita **32 s antes** da base declarada e a rev.1 mandava reconstruí-la. D4 acrescentado; 04 virou PUXAR |
| **PUXAR** | 2 telas 🔵 → threads read-only | puxar às cegas apagaria campo que saiu de propósito | traz átomos/aria/dados, nunca layout | Metas 🔵 tem **5 colunas de apuração fora por caminho de VALOR** (Non-Goals do charter) — o protótipo as mostra: divergência a declarar no build, não pedido |
| **REACT** | `criar-tela.mjs` carimba trio junto | 2 precedentes de caminho → RESÍDUO 1 | D1/D2 matam 4 ondas do export | **RESÍDUO 1 não era decisão**: a árvore já responde (`Pages/Essentials/`, 14 .tsx). Removido; flat × pasta = gerador. Pacote-padrão = 8 peças da Metas, não trio |
| **PLAYBOOK** | 2 threads de build no mesmo arquivo → fundidas | teste do estranho na 06 | `onda.md` não existe → abertura à mão | **09 sem o cron (:108) nem o ponteiro do Ponto (:136) no prefixo** → acrescentados. `whats-active` morto → `gh pr list`. Emenda cita planos inexistentes → aviso na abertura |
| **VERIFICAR** | prova = caminho, não nome | Page sem `_saida` não conta | T7 não visível | **Landing:** só `.md` roteia → JSON embutido (§7), schema/script como anexos do AUTOMACAO; pasta inteira é a unidade |

## 6 · RESÍDUO Hrm — fila de decisão [W]
1. ~~`<PAGES>`~~ → **respondido pela árvore**, não é decisão.
2. **Departamentos e Cargos** viram abas do HRM no protótipo (o nav de produção tem, com `crud_department`/`crud_designation`) — confirma?
3. **`ShiftController::destroy`**: #6789 fechou `leave-type` (e `HrmExclusaoGuardaTest` existe) — turno com vínculo ainda responde 200? A 05 para se sim.
4. **`DataTablePro` do DS** — 3º módulo com `th` sem `scope`/semântica: pedido de DS próprio?
5. **Metas no protótipo mostra apuração (mês anterior/atual, faixa atingida, progresso, R$)** que a produção excluiu por caminho de VALOR. Tirar do protótipo ou manter com selo "fora desta onda"?

## 7 · Fonte da máquina (playbook.json embutido — primeiro bloco json deste arquivo; schema em `_schema/playbook.schema.json`)
```json
{
  "modulo": "Hrm",
  "sha": "45e63465d2e4",
  "gerado": "2026-09-05",
  "absorve": ["prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md", "prototipo-ui/design-docs/cowork-inbox/hrm/EXPORT-HRM-2026-09-04.md"],
  "variaveis": { "PAGES": "resources/js/Pages/Essentials" },
  "decisoes": [
    { "id": "RESIDUO-2", "pergunta": "Departamentos e Cargos (nav_hrm → TaxonomyController) viram abas do HRM no protótipo?", "respondida": false, "destrava": ["01"] },
    { "id": "RESIDUO-3", "pergunta": "ShiftController::destroy ainda responde 200 sem apagar turno com vínculo?", "respondida": false, "destrava": ["05"] },
    { "id": "RESIDUO-4", "pergunta": "DataTablePro do DS (th sem scope/semântica em 3 módulos): pedido de DS próprio?", "respondida": false },
    { "id": "RESIDUO-5", "pergunta": "Metas no protótipo mostra apuração excluída da produção por caminho de VALOR: tirar ou selar 'fora desta onda'?", "respondida": false, "destrava": ["04"] },
    { "id": "D1", "pergunta": "Presença web × Ponto", "respondida": true, "resposta": "cede ao Ponto — dono único da jornada (2026-09-05)", "destrava": ["09"] },
    { "id": "D2", "pergunta": "Folha gerencial × completa", "respondida": true, "resposta": "completa com encargos → projeto com ADR própria (2026-09-05)" },
    { "id": "D3", "pergunta": "Licença aprovada bloqueia marcação?", "respondida": true, "resposta": "sim; guard nasce no Ponto; bloquear = impedir criação (append-only)" }
  ],
  "threads": [
    { "id": "01", "titulo": "Build: TABS do HRM (−Presença · +Departamentos/Cargos)", "dono": "CC", "vaga": 1, "arquivo": "01-build-tabs-hrm.md",
      "prefixo": ["prototipo-ui/cowork/hrm-page.jsx", "prototipo-ui/cowork/hrm-data.jsx", "prototipo-ui/cowork/oimpresso.com.html"],
      "nao_toca": ["prototipo-ui/cowork/hrm-extras.jsx", "prototipo-ui/cowork/hrm-forms.jsx", "prototipo-ui/cowork/app.jsx"],
      "provas": [ { "tipo": "nao_contem", "path": "prototipo-ui/cowork/hrm-page.jsx", "padrao": "id:\"hrm-presenca\"", "nota": "aba Presença fora do TABS (D1); a parte +Departamentos só com RESIDUO-2" } ] },
    { "id": "02", "titulo": "Licenças — Page", "dono": "CL", "vaga": 1, "arquivo": "02-licencas.md",
      "prefixo": ["${PAGES}/Licencas.tsx", "${PAGES}/Licencas/", "Modules/Essentials/Http/Controllers/EssentialsLeaveController.php", "prototipo-ui/contrato/essentials-licencas.contract.json", "Modules/Essentials/Tests/Feature/HrmLicencaTest.php", "e2e/essentials-licencas.spec.ts", ".github/workflows/essentials-pest.yml"],
      "nao_toca": ["Modules/Essentials/Services/LeaveRequestService.php", "${PAGES}/Metas.tsx", "${PAGES}/Settings/", "${PAGES}/Holidays/", "${PAGES}/Todo/", "${PAGES}/Knowledge/", "${PAGES}/Documents/", "${PAGES}/Messages/", "${PAGES}/Reminders/"],
      "provas": [
        { "tipo": "um_de", "paths": ["${PAGES}/Licencas.tsx", "${PAGES}/Licencas/Index.tsx"] },
        { "tipo": "um_de", "paths": ["${PAGES}/Licencas.charter.md", "${PAGES}/Licencas/Index.charter.md"] },
        { "tipo": "um_de", "paths": ["${PAGES}/Licencas.casos.md", "${PAGES}/Licencas/Index.casos.md"] },
        { "tipo": "json_com_chaves", "path": "prototipo-ui/contrato/essentials-licencas.contract.json", "chaves": ["alvo", "secoes"], "nota": "nome segue a irmã (essentials-metas); o hrm-licencas.contract.json do cowork-inbox é insumo, o gerador carimba" },
        { "tipo": "arquivo", "path": "e2e/essentials-licencas.spec.ts" },
        { "tipo": "contem", "path": "Modules/Essentials/Http/Controllers/EssentialsLeaveController.php", "padrao": "Inertia::render('Essentials/Licencas", "nota": "D4: a rota passa a ter Page" }
      ] },
    { "id": "03", "titulo": "Tipos de licença — Page", "dono": "CL", "vaga": 1, "arquivo": "03-tipos-licenca.md",
      "prefixo": ["${PAGES}/Tipos.tsx", "${PAGES}/Tipos/", "Modules/Essentials/Http/Controllers/EssentialsLeaveTypeController.php", "prototipo-ui/contrato/essentials-tipos.contract.json", "e2e/essentials-tipos.spec.ts"],
      "nao_toca": ["Modules/Essentials/Http/Controllers/EssentialsLeaveController.php", "Modules/Essentials/Tests/Feature/HrmExclusaoGuardaTest.php"],
      "provas": [
        { "tipo": "um_de", "paths": ["${PAGES}/Tipos.tsx", "${PAGES}/Tipos/Index.tsx"] },
        { "tipo": "um_de", "paths": ["${PAGES}/Tipos.charter.md", "${PAGES}/Tipos/Index.charter.md"] },
        { "tipo": "um_de", "paths": ["${PAGES}/Tipos.casos.md", "${PAGES}/Tipos/Index.casos.md"] },
        { "tipo": "json_com_chaves", "path": "prototipo-ui/contrato/essentials-tipos.contract.json", "chaves": ["alvo", "secoes"] },
        { "tipo": "contem", "path": "Modules/Essentials/Http/Controllers/EssentialsLeaveTypeController.php", "padrao": "Inertia::render('Essentials/Tipos" }
      ] },
    { "id": "04", "titulo": "Metas — PUXAR (produção à frente, #6869)", "dono": "CC", "vaga": 1, "arquivo": "04-metas-venda.md",
      "prefixo": ["prototipo-ui/cowork/hrm-extras.jsx"], "nao_toca": ["${PAGES}/Metas.tsx", "Modules/Essentials/Http/Controllers/SalesTargetController.php"],
      "depende_decisoes": ["RESIDUO-5"],
      "provas": [], "nota_provas": "read-only + build: prova = _saida-04.md com o diff nos dois sentidos (Metas.tsx × hrm-extras.jsx Metas) e a divergência de VALOR declarada" },
    { "id": "05", "titulo": "Turnos — Page", "dono": "CL", "vaga": 2, "arquivo": "05-turnos.md",
      "prefixo": ["${PAGES}/Turnos.tsx", "${PAGES}/Turnos/", "Modules/Essentials/Http/Controllers/ShiftController.php", "prototipo-ui/contrato/essentials-turnos.contract.json", "e2e/essentials-turnos.spec.ts"],
      "nao_toca": ["Modules/Essentials/Http/Controllers/AttendanceController.php"],
      "depende_threads": ["09"], "depende_decisoes": ["RESIDUO-3"],
      "provas": [
        { "tipo": "um_de", "paths": ["${PAGES}/Turnos.tsx", "${PAGES}/Turnos/Index.tsx"] },
        { "tipo": "um_de", "paths": ["${PAGES}/Turnos.charter.md", "${PAGES}/Turnos/Index.charter.md"] },
        { "tipo": "um_de", "paths": ["${PAGES}/Turnos.casos.md", "${PAGES}/Turnos/Index.casos.md"] },
        { "tipo": "json_com_chaves", "path": "prototipo-ui/contrato/essentials-turnos.contract.json", "chaves": ["alvo", "secoes"] },
        { "tipo": "contem", "path": "Modules/Essentials/Http/Controllers/ShiftController.php", "padrao": "Inertia::render('Essentials/Turnos" }
      ] },
    { "id": "06", "titulo": "Painel — Page", "dono": "CL", "vaga": 2, "arquivo": "06-painel.md",
      "prefixo": ["${PAGES}/Painel.tsx", "${PAGES}/Painel/", "Modules/Essentials/Http/Controllers/DashboardController.php", "prototipo-ui/contrato/essentials-painel.contract.json"],
      "nao_toca": ["Modules/Essentials/Http/Controllers/AttendanceController.php"],
      "depende_threads": ["09"],
      "provas": [
        { "tipo": "um_de", "paths": ["${PAGES}/Painel.tsx", "${PAGES}/Painel/Index.tsx"] },
        { "tipo": "um_de", "paths": ["${PAGES}/Painel.charter.md", "${PAGES}/Painel/Index.charter.md"] },
        { "tipo": "um_de", "paths": ["${PAGES}/Painel.casos.md", "${PAGES}/Painel/Index.casos.md"] },
        { "tipo": "contem", "path": "Modules/Essentials/Http/Controllers/DashboardController.php", "padrao": "Inertia::render('Essentials/Painel" }
      ] },
    { "id": "07", "titulo": "Configurações — PUXAR (12 campos × 10 chaves)", "dono": "CC->CL", "vaga": 1, "arquivo": "07-configuracoes-puxar.md",
      "prefixo": [], "nao_toca": ["Modules/Essentials/Http/Controllers/EssentialsSettingsController.php"],
      "provas": [], "nota_provas": "read-only: prova = _saida-07.md com as 12 linhas classificadas" },
    { "id": "08", "titulo": "Feriados — PUXAR (ler Holidays/Index.tsx)", "dono": "CC", "vaga": 1, "arquivo": "08-feriados-puxar.md",
      "prefixo": [], "nao_toca": ["${PAGES}/Holidays/Index.tsx"],
      "provas": [], "nota_provas": "read-only: prova = _saida-08.md com o diff nos dois sentidos" },
    { "id": "09", "titulo": "Presença SAI do HRM → Ponto dono da jornada", "dono": "W+CL", "vaga": 2, "arquivo": "09-presenca-sai.md",
      "prefixo": ["memory/decisions/0014-essentials-pontowr2-integracao.md", "Modules/Essentials/Routes/web.php", "Modules/Essentials/Providers/EssentialsServiceProvider.php", "Modules/Ponto/Config/config.php"],
      "nao_toca": ["Modules/Ponto/Http/", "Modules/Ponto/Database/", "Modules/Essentials/Http/Controllers/AttendanceController.php"],
      "depende_decisoes": ["D1", "D3"],
      "provas": [
        { "tipo": "contem", "path": "memory/decisions/0014-essentials-pontowr2-integracao.md", "padrao": "2026-09-05", "nota": "emenda datada — nunca ADR paralela (LC-19)" },
        { "tipo": "nao_contem", "path": "memory/decisions/0014-essentials-pontowr2-integracao.md", "padrao": "lifecycle: arquivado" },
        { "tipo": "nao_contem", "path": "Modules/Essentials/Providers/EssentialsServiceProvider.php", "padrao": "command('pos:autoClockOutUser')", "nota": "cron desagendado no MESMO PR que congela essentials_attendances (emenda [W])" },
        { "tipo": "nao_contem", "path": "Modules/Ponto/Config/config.php", "padrao": "EssentialsUserShiftHistory", "nota": "ponteiro morto :136 limpo" }
      ] },
    { "id": "10", "titulo": "Folha — BLOQUEADA (D2 → projeto com ADR própria)", "dono": "W", "arquivo": "10-folha-bloqueada.md",
      "prefixo": [], "nao_toca": ["Modules/Essentials/Http/Controllers/PayrollController.php"],
      "bloqueio": "D2: folha completa com encargos exige ADR própria e gate de VALOR (proibicoes.md); nenhuma Page até a ADR existir",
      "provas": [], "nota_provas": "quando a ADR nova existir, entra aqui {tipo:contem, path:<ADR>, padrao:'0014'}" },
    { "id": "11", "titulo": "Fim do topnav Blade + limpeza O8", "dono": "CL", "vaga": 3, "arquivo": "11-topnav-legado.md",
      "prefixo": ["Modules/Essentials/Resources/views/layouts/nav_hrm.blade.php", "Modules/Essentials/Resources/views/layouts/partials/sidebar_hrm.blade.php", "Modules/Essentials/Resources/views/leave/", "Modules/Essentials/Resources/views/leave_type/", "Modules/Essentials/Resources/views/sales_targets/", "Modules/Essentials/Resources/views/dashboard/hrm_dashboard.blade.php"],
      "nao_toca": ["Modules/Essentials/Resources/views/attendance/", "Modules/Essentials/Resources/views/payroll/", "Modules/Essentials/Routes/web.php"],
      "depende_threads": ["02", "03", "05", "06"],
      "provas": [
        { "tipo": "ausente", "path": "Modules/Essentials/Resources/views/layouts/nav_hrm.blade.php" },
        { "tipo": "ausente", "path": "Modules/Essentials/Resources/views/layouts/partials/sidebar_hrm.blade.php" },
        { "tipo": "ausente", "path": "Modules/Essentials/Resources/views/dashboard/hrm_dashboard.blade.php" }
      ] }
  ]
}
```
