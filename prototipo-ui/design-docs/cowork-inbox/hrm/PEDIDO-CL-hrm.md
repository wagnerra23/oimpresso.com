# HRM (Essentials `/hrm`) — pedido pro [CL] · ondas HRM-O0 e O5–O8

**Origem:** F1 do Cowork em `hrm-page.jsx` · `hrm-extras.jsx` · `hrm-forms.jsx` · `hrm-ui.jsx` · `hrm-data.jsx` · `hrm-page.css` (app único, rota `hrm` + ghosts).
**Lido no `main`** (tree `b719732f3188`, 2026-08-21): `Modules/Essentials/Routes/web.php`, `layouts/nav_hrm.blade.php`, `partials/sidebar_hrm.blade.php`, `dashboard/hrm_dashboard.blade.php`, `leave/index`, `leave_type/index`, `holiday/index`, `sales_targets/index` e os controllers EssentialsLeave · EssentialsLeaveType · EssentialsHoliday · EssentialsSettings · Attendance · Shift · SalesTarget. `PayrollController` (60 KB) lido no espelho local — **não** no main.
**Não commitado:** as tools de GitHub do Cowork são read-only. Ponte = cola zero-toque ou Issue `cowork-intake`.

---

## HRM-O0 — 3 decisões [W] antes de codar

| # | Decisão | Por que não posso decidir | Recomendação |
|---|---|---|---|
| **D1** | HRM e **Ponto WR2** convivem ou a presença web cede lugar ao ponto legal? | Hoje há **dois registros de jornada** no mesmo negócio (`essentials_attendances` e as tabelas do Ponto) sem nenhuma ligação. A folha usa `getTotalWorkDuration` do Essentials; o espelho legal vem do Ponto. | Presença do Essentials fica como **apontamento operacional** (quem está no balcão agora) e a jornada legal é sempre do Ponto WR2. A folha passa a ler o Ponto — senão a hora paga divergirá do espelho fiscal. |
| **D2** | A folha do Essentials é **gerencial** ou vira título no Financeiro? | `PayrollController::store` grava `Transaction type=payroll` como despesa; não existe encargo (INSS/IRRF/FGTS/13º/férias). Publicar isso como "folha" com o Financeiro ao lado gera expectativa de guia. | Rotular na UI como **"folha gerencial"** (como já feito no DRE) e, quando fechada, gerar título a pagar no Financeiro com a data de pagamento — sem prometer cálculo de encargo. |
| **D3** | Licença aprovada **bloqueia** marcação de presença no período? | Hoje conviver é possível: aprovar férias não impede clock-in, e o relatório "por data" conta quem está de licença como **ausente**. | Bloquear a marcação com motivo ("você está de licença aprovada até dd/mm") e tirar licença/feriado da conta de ausência. |

Sem D1 e D3 respondidas, **HRM-O6 não fecha** (a guarda de conflito depende do dono da jornada).

---

> 📍 **Estado de aterrissagem (2026-09-04) — HRM-O5/PR-1 EXECUTADO PELA METADE, e a metade
> que ficou tem motivo medido.**
>
> **Aterrissou:** `Modules/Essentials/Tests/Feature/HrmLicencaTest.php` (+ entrada na allowlist
> do `essentials-pest.yml`). O CI confirmou o desenho: **canário verde, 6 casos vermelhos** com
> `Failed asserting that 200 is identical to 422` — os achados A2/A3/A4, não ambiente.
>
> **NÃO aterrissou (vai no PR-9, junto da `Index.tsx`):** os 3 charters e o `Index.casos.md`.
> Medido: a catraca `charter_refs_broken` tem **teto 0** e trata `component:` apontando pra
> `.tsx` inexistente como ref quebrada — e conta o repo INTEIRO, então 3 charters sem tela
> deixariam esse gate vermelho **para todo PR** até o PR-9 (o mesmo dano coletivo que já
> segurou o contrato). O gerador canônico [`criar-tela.mjs`](../../../../scripts/governance/criar-tela.mjs)
> carimba `.tsx` + charter + casos + e2e + contrato **juntos**: *charter sem tela não é estado
> suportado neste repo*. Os textos revisados dos 3 charters + casos estão no PR
> [#6800](https://github.com/wagnerra23/oimpresso.com/pull/6800) (commit inicial) — reaproveitar
> lá, não reescrever.
>
> **`hrm-licencas.contract.json` fica aqui**, corrigido pro schema do repo (usava
> `sections`/`screen`/`route`; o schema exige `alvo`+`secoes`). Contrato só é *vigente* quando
> aplicado a uma tela real (`scripts/contrato-de-tela.mjs` §125-135).
>
> **Lição pro PR-9:** o "trio de prontidão ANTES da tela" desenhado neste pedido colide com a
> régua do repo. O PR-9 deve começar por `criar-tela.mjs <Mod/Tela> PT-01` e então substituir o
> conteúdo carimbado pelos textos já revisados.
>
> **Não refazer a conferência do PR-1** (6 divergências achadas e corrigidas: formato do
> contrato, allowlist da lane, `RefreshDatabase` proibido, `admin()` com `TypeError`, factories
> inexistentes, charter sem frontmatter). Próximo da fila: PR-8 (lang PT, isolado) ou
> PR-2/PR-3 (que fecham os 6 vermelhos).

## HRM-O5 — prova mínima (trio + contrato)

**PR-1 · trio de prontidão**
- `Modules/Essentials/Resources/js/Pages/Hrm/Licencas/Index.charter.md` + `Index.casos.md` (neste pacote: `Licencas.charter.md`, `Licencas.casos.md`).
- `.../Hrm/Presenca/Index.charter.md` e `.../Hrm/Folha/Index.charter.md` (neste pacote: `Presenca.charter.md`, `Folha.charter.md`).
- `prototipo-ui/contrato/hrm-licencas.contract.json` (ADR 0286) — 9 seções, copy literal, estados e 8 proibições. Âncoras `data-contract` a instrumentar no F3 (o F1 já usa os mesmos ids).
- `Modules/Essentials/Tests/Feature/HrmLicencaTest.php` — **nasce vermelho** em UC-HRM-02/03/05/09 (é a prova dos achados A2/A3/A5).

Comandos: `php artisan test --filter=HrmLicenca` · `npm run contrato:check` · `npm run ciclo-completo`.

## HRM-O6 — verdade e segurança da ação (os achados)

**PR-2 · validação de licença (A2)** — `StoreLeaveRequest`: `essentials_leave_type_id` `required|exists` **escopado no business**, `start_date`/`end_date` `required|date`, `end_date >= start_date`, `reason required|max:2000`, `employees.*` obrigados a ser do tenant (mesmo gate Tier 0 que `SalesTargetController` já tem).
**PR-3 · limite do tipo (A3)** — regra `LeaveBalance`: soma dias aprovados + em análise no intervalo do tipo (`year`/`month`) e recusa o que estoura, com mensagem que diz o saldo. Aplica no `store` **e** no `changeStatus` (aprovar também estoura).
**PR-4 · faixas de meta (A5)** — validar sobreposição e faixa invertida em `saveSalesTarget`; `target_end > target_start`; `commission_percent` 0–100.
**PR-5 · exclusão que não existe (A4)** — implementar `ShiftController::destroy` e `EssentialsLeaveTypeController::destroy` com guarda de uso (turno com vínculo ou marcação / tipo com licença ⇒ 422 dizendo quantos registros travam), ou remover a rota do resource — hoje a rota existe e responde 200 sem fazer nada.
**PR-6 · import de presença (A7)** — reusar a checagem de `validateClockInClockOut` linha a linha; relatório de linhas recusadas em vez de rollback total; tirar `ini_set('max_execution_time', 0)` em favor de fila.
**PR-7 · conflito licença × presença (depende de D3)** — bloquear clock-in em período de licença aprovada e excluir licença/feriado da conta de ausência do `getAttendanceByDate`.

## HRM-O7 — tradução Inertia

**PR-8 · lang PT (A1, pode ir sozinho e já)** — `Resources/lang/pt/lang.php`: `leave` "Sair" → **"Licença"**, `leaves`/`all_leaves` "Folhas"/"Todas as folhas" → **"Licenças"**, `attendance` "Comparecimento" → **"Presença"**, `holidays` "Férias" → **"Feriados"**, `clock_in/out` → **"Entrada"/"Saída"**, `shift` "Mudança" → **"Turno"**, `employee` "Empregado" → **"Colaborador"**. O vocabulário certo está no F1 e no charter.
**PR-9 · páginas** — `Hrm/Painel`, `Hrm/Licencas/Index`, `Hrm/Tipos/Index`, `Hrm/Presenca/Index`, `Hrm/Turnos/Index`, `Hrm/Folha/Index`, `Hrm/Metas/Index` (Holidays e Settings **já são Inertia**). Cada uma com charter ao lado, como o módulo Cms faz.
**PR-10 · fim do topnav Blade** — `layouts/nav_hrm.blade.php` e `partials/sidebar_hrm.blade.php` saem; a navegação passa a ser a do shell (o F1 já mostra as 8 abas na ordem do nav_hrm).

## HRM-O8 — limpeza do legado
Só **depois** do screenshot [W2]: as ~50 blades do HRM (`leave/*`, `leave_type/*`, `attendance/*`, `payroll/*`, `sales_targets/*`, `dashboard/hrm_dashboard`, `settings/partials/*`), as chaves de lang mortas nos 16 idiomas e os `show()`/`edit()` que retornam `essentials::show`/`essentials::edit` (views que **não existem** → 500 se alguém chamar a rota do resource).

---

## Ordem sugerida
PR-8 (lang, isolado) → PR-1 (trio+testes vermelhos) → PR-2/3/4 (verdade) → PR-5/6 → **D1/D3** → PR-7 → PR-9/10 → O8.

## Fora de escopo deste pedido
Encargos trabalhistas, eSocial, DIRF, ponto legal (é o módulo Ponto), documentos/tarefas/mensagens/base de conhecimento do Essentials (outro grupo de telas), e qualquer migration de schema novo — nada aqui exige coluna nova.

---

## Emenda 2026-09-04 [CL] — chegou um export novo; este pedido segue o dono

Desceu um segundo pacote do Cowork para o mesmo módulo, aterrissado ao lado em
[`EXPORT-HRM-2026-09-04.md`](EXPORT-HRM-2026-09-04.md). Ele **não substitui** este pedido — foi
escrito sem citá-lo, afirmando que não existia `PEDIDO-*hrm*` (a busca casou só a raiz do
`cowork-inbox/`, e este arquivo está uma pasta abaixo). A reconciliação onda-a-onda está na
seção 2 daquele arquivo.

**O que o export acrescenta e este pedido não tinha:** a11y do alvo medida com método corrigido
(a sonda por `cursor:pointer` reprova neste módulo), o aviso de que o skeleton falseia a contagem
de nós em ~23%, o alvo por seção (contagem e ordem dos filhos) e a leitura de que o `DataTablePro`
do DS acumula o mesmo defeito em 3 módulos.

**O que segue valendo só aqui:** D1/D2/D3 (que bloqueiam as ondas de Painel, Presença e Folha),
PR-8 lang PT, PR-10 fim do topnav Blade e HRM-O8. O export não os menciona.

**Estado medido no `main` em 2026-09-04 (`ac7e5e417c`): este pedido está com execução zero.**
Não existe `Pages/Hrm/`; `HrmLicencaTest.php` e `hrm-licencas.contract.json` nunca saíram desta
pasta; e o lang PT segue com `leave` = "Sair" e `leaves` = "Folhas".
