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
> **O PR-1 rodou e PROVOU o que devia — depois cedeu o arquivo.** O
> `HrmLicencaTest.php` foi escrito, entrou na allowlist e rodou no CI
> ([#6800](https://github.com/wagnerra23/oimpresso.com/pull/6800), run 33940153426):
> **canário verde + 6 casos vermelhos** com `Failed asserting that 200 is identical to 422` —
> a prova de que A2/A3/A4 eram reais e de que o vermelho era achado, não ambiente.
>
> **Mas três sessões paralelas atacaram o mesmo pedido na mesma noite**, e duas chegaram mais
> longe: [#6789](https://github.com/wagnerra23/oimpresso.com/pull/6789) (PR-5, **já mergeado** —
> `EssentialsLeaveTypeController::destroy` agora devolve **422** com `blocked_by`, então **A4
> está FECHADO**) e [#6797](https://github.com/wagnerra23/oimpresso.com/pull/6797) (PR-2/PR-3,
> aberto — traz a validação **e** um `HrmLicencaTest.php` próprio, de 342 linhas, cobrindo
> UC-HRM-02/03/05/09/15/19, que nasce **verde** porque vem com a correção junto).
> O #6800 **cedeu** o `HrmLicencaTest.php` e a linha da allowlist: dois testes com o mesmo nome
> é conflito garantido, e entre "vermelho esperando conserto" e "correção + verde no mesmo PR",
> o segundo serve mais. **O #6797 é o canônico do teste.**
>
> ⚠️ **Causa da colisão, registrada:** ninguém rodou `whats-active` antes de abrir (§5
> 2026-08-13 — sintoma acusado por máquina compartilhada é o caso de maior probabilidade de
> colisão entre sessões). Quem pegar o PR-9 ou o PR-6/7: **rode `whats-active` primeiro.**
>
> **NÃO aterrissou (vai no PR-9, junto da `Index.tsx`):** os 3 charters e o `Index.casos.md`.
> Medido: a catraca `charter_refs_broken` tem **teto 0** e trata `component:` apontando pra
> `.tsx` inexistente como ref quebrada — e conta o repo INTEIRO, então 3 charters sem tela
> deixariam esse gate vermelho **para todo PR** até o PR-9 (o mesmo dano coletivo que já
> segurou o contrato). O gerador canônico [`criar-tela.mjs`](../../../../scripts/governance/criar-tela.mjs)
> carimba `.tsx` + charter + casos + e2e + contrato **juntos**: *charter sem tela não é estado
> suportado neste repo*. Os textos revisados dos 3 charters + casos estão no commit
> **`dbfc75fbcf`** do PR [#6800](https://github.com/wagnerra23/oimpresso.com/pull/6800) —
> reaproveitar, não reescrever.
>
> ⚠️ **Como buscar (o branch foi DELETADO no merge).** Num clone fresco `git show dbfc75fbcf`
> **falha** — o commit ficou fora de qualquer branch. Ele sobrevive porque é ancestral de
> `refs/pull/6800/head` (medido 2026-09-05: a ref existe no remoto e o `merge-base
> --is-ancestor` confirma). Receita:
> ```bash
> git fetch origin refs/pull/6800/head
> git show dbfc75fbcf:Modules/Essentials/Resources/js/Pages/Hrm/Licencas/Index.charter.md
> git show dbfc75fbcf:Modules/Essentials/Resources/js/Pages/Hrm/Licencas/Index.casos.md
> git show dbfc75fbcf:Modules/Essentials/Resources/js/Pages/Hrm/Presenca/Index.charter.md
> git show dbfc75fbcf:Modules/Essentials/Resources/js/Pages/Hrm/Folha/Index.charter.md
> ```
> (Sem o `fetch` da ref do PR, os quatro falham. Citar um sha sem dizer como alcançá-lo é
> ponteiro que apodrece — foi o defeito desta própria nota, corrigido no mesmo dia.)
>
> **`hrm-licencas.contract.json` fica aqui**, corrigido pro schema do repo (usava
> `sections`/`screen`/`route`; o schema exige `alvo`+`secoes`). Contrato só é *vigente* quando
> aplicado a uma tela real (`scripts/contrato-de-tela.mjs` §125-135).
>
> **Lição pro PR-9:** o "trio de prontidão ANTES da tela" desenhado neste pedido colide com a
> régua do repo. O PR-9 deve começar por `criar-tela.mjs <Mod/Tela> PT-01` e então substituir o
> conteúdo carimbado pelos textos já revisados.
>
> **Não refazer a conferência do PR-1** — os 4 artefatos deste pacote tinham **6 divergências**
> contra o `main`, todas medidas e corrigidas: (1) contrato em outro schema (`sections`/`screen`
> vs `alvo`/`secoes` — o gate dava exit 1); (2) o teste **não rodaria**, porque a lane tem
> allowlist de 1 arquivo; (3) usava `RefreshDatabase`, que a lane proíbe (dropa o schema e limpa
> o seed das 16 lanes); (4) `private function admin(): User {}` com corpo vazio ⇒ **`TypeError`**
> — os casos morreriam pelo motivo errado; (5) `EssentialsLeaveType::factory()` não existe
> (`Database/factories/` só tem `.gitkeep`); (6) charters sem frontmatter, e o gate `Charter` é
> **required**. Bônus: a data literal `21/09/2026` é **mês 21** no `date_format` default `m/d/Y`.
>
> Próximo da fila: **PR-8** (lang PT, isolado, sem bloqueio) e **PR-6/PR-7** (presença) — o
> PR-2/PR-3 está no #6797 e o PR-4 no #6799.

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

---

## ⛔ Emenda 2026-09-05 [W] — D1, D2 e D3 RESPONDIDAS. Duas mudam o desenho do módulo.

> **Leia isto antes de pegar qualquer onda deste pedido.** Duas das respostas invertem o que
> as ondas HRM-O7/O8 assumiam, e uma reabre o que o próprio pedido declarou fora de escopo.

| # | Resposta [W] | Efeito |
|---|---|---|
| **D1** | **A presença web CEDE LUGAR ao Ponto.** | O HRM deixa de ter tela de presença. O `Modules/Ponto` vira dono único da jornada. |
| **D2** | **Folha COMPLETA, com encargos** (INSS/IRRF/FGTS/13º/férias). | Reabre o que a seção "Fora de escopo" deste pedido excluía. |
| **D3** | **Licença aprovada NÃO bloqueia — sinaliza divergência** e sai da conta de ausência. | Nenhuma origem é recusada. A marcação entra marcada, e o gestor trata. |

> ⚠️ **D3 foi emendada pelo próprio [W] no mesmo dia, depois de medir.** A primeira resposta foi
> *"bloqueia e sai da conta de ausência"*. O plano de integração então mediu que o Ponto recebe
> batidas de origens diferentes — `REP_P` e `AFD` são **leitura do que já aconteceu** (equipamento
> físico e arquivo fiscal), enquanto `MANUAL` e `INTEGRACAO` são criação. Recusar uma batida vinda
> do relógio apagaria do sistema um registro que **existe no equipamento e no arquivo fiscal**, e a
> auditoria do MTE compara os dois: quem tem batida no REP-P e não tem no sistema explica a falta.
> Diante disso [W] escolheu **aceitar todas e só sinalizar**. A metade "bloqueia" da D3 **cai**;
> a metade "sai da conta de ausência" **fica**. O registro anterior está preservado nesta nota
> porque foi verdade na mesma data — o que muda é o ponteiro, não o fato.

**A direção, nas palavras do [W] (2026-09-05):** *"acho que pode integrar mais sim o sistema e
deixar o ponto decidir, vincular com outros módulos seria muito melhor, hoje está tudo separado."*
Não é só quem é dono da jornada — é **integrar em vez de manter silos**.

### O que isso faz com as ondas

- **Ondas 4 e 5 do export (Presença + Espelho do mês) saem do HRM.** Cedem 11 rotas de
  `Routes/web.php` e 14 métodos públicos do `AttendanceController`.
- **O `#6798` (import de presença endurecido, mergeado hoje) fica numa superfície que vai ceder.**
  O trabalho não se perde — o dado precisa migrar de qualquer forma —, mas o destino mudou.
- **A folha passa a ler o Ponto** (`ponto_apuracao_dia`, `ponto_banco_horas`), não
  `essentials_attendances`.
- **PR-7 vira sinalização, não bloqueio** (ver a emenda de D3 acima). O chokepoint é único e está
  contado: `MarcacaoService.php:62` — fora de `Tests/` não existe outro `Marcacao::create`.
  `ponto_marcacoes` segue append-only por força da Portaria MTP 671/2021, então a divergência é
  um atributo que nasce com o registro, nunca um UPDATE posterior.

### Três achados do plano de integração, medidos e confirmados aqui

1. **`pos:autoClockOutUser` FABRICA saídas.** Agendado `everyThirtyMinutes` em
   `EssentialsServiceProvider.php:108`, ele grava `clock_out_time = agora` para quem ficou sem
   saída (`Console/AutoClockOutUser.php:115`). Em `essentials_attendances` isso é higiene
   operacional aceitável; **levar esse dado para `ponto_marcacoes` seria outra coisa** — a base é
   append-only, com hash encadeado, NSR sequencial e valor probatório perante o MTE. Por isso a
   decisão do plano: **`essentials_attendances` CONGELA, não migra** — e o cron tem de ser
   desagendado no MESMO PR que congelar, senão segue escrevendo em tabela morta.
2. **A única referência de código Ponto→Essentials é um ponteiro morto.**
   `Modules/Ponto/Config/config.php:136` aponta `EssentialsUserShiftHistory::class` — **0 hits no
   repo inteiro**, silenciado em `phpstan-baseline.neon` como `not found`. Ou seja: não há
   integração viva a preservar, há um ponteiro quebrado a limpar.
3. **O esquema CONTRADIZ a ADR 0014, não apenas a ignora.** A 0014 §1 diz que `escala_atual_id` é
   "FK para Shift"; a FK real é `REFERENCES ponto_escalas(id)`, e a validação é
   `exists:ponto_escalas,id`. A ADR sucessora precisa reconciliar isso explicitamente.

**Plug-points estreitos (bom sinal):** a folha inteira entra por **duas funções**
(`EssentialsUtil.php:26` e `:293`, com os 5 sites chamadores em `PayrollController` intactos), e a
sinalização por **um** chokepoint. Sete ondas, ~20h — e a ordem importa: a onda em que a folha
passa a ler apuração é **Tier 0 de valor** e não pode ser a primeira.

### O dono do tema já existe, e ficou no papel

**[ADR 0014 `essentials-pontowr2-integracao`](../../../../memory/decisions/0014-essentials-pontowr2-integracao.md)**
(2026-04-21, [E]) **já desenha essa integração**: Shift como fonte do horário contratual, Ponto
dono das batidas, Payroll alimentado pelo Ponto, `EssentialsHoliday` lido pelo Ponto,
`EssentialsLeave` respeitado como Intercorrência. Está `lifecycle: arquivado`.

**Medido em 2026-09-05 — o desenho nunca saiu do papel:** `escala_atual_id` aparece só em 2
migrations e 1 seeder (nenhum Service ou Controller usa); o Ponto referencia o Essentials
**apenas** em `Modules/Ponto/Config/config.php`; e **a folha não lê o Ponto** — zero ocorrência
de `ponto_apuracao`/`ponto_banco_horas`/`BancoHoras` em `Modules/Essentials`. A 0014 **não tem
sucessora**: nenhuma outra ADR a cita.

⚠️ **Não abra ADR paralela.** A saída é emendar ou superseder a 0014 — ela é o dono. Abrir uma
nova seria a LC-19 que custou 3 colisões neste mesmo pedido em 04/09.

### O tamanho de D2, dito uma vez

Encargos no Brasil são INSS progressivo, IRRF com deduções e teto, FGTS, 13º e férias com 1/3 —
com **tabela legal que muda por ato do governo**, o que cria manutenção perpétua, e esbarra em
eSocial. Some-se a regra mestre de [`proibicoes.md`](../../../../memory/proibicoes.md): mexer em
VALOR exige dupla prova por caminhos independentes **e** impacto antes→depois aprovado antes de
aplicar. **Isto é projeto com ADR própria, não uma onda deste pedido.** A decisão é do [W] e está
tomada; o registro aqui é do custo, não uma objeção.

### Em curso (2026-09-05)

Os dois planos **já foram entregues** — leia antes de pegar qualquer onda:

- **Integração Ponto↔HRM** — `memory/sessions/2026-09-05-como-integrar-ponto-hrm.md` (7 ondas,
  ~20h). Veredito: *desenho existe, código é zero* — não se re-desenha nada, constrói-se seguindo
  a 0014, por ADR **sucessora** (`supersedes: [14]`), nunca paralela.
- **Estado da arte da folha** — `memory/sessions/2026-09-05-arte-folha-encargos-br.md`. Veredito:
  o `PayrollController` (1.188 linhas) **não é folha, é planilha com persistência**; verbas em 2
  blobs JSON sem tipo/incidência/vigência; `inss|irrf|fgts|esocial` = **0 ocorrências** no módulo.
  Tamanho da D2: **110-180h (2 a 4 meses) + 40-80h/ano perpétuas**. eSocial: **calcular sim,
  operar não** (guia só sai pelo FGTS Digital).

**Decisão [W] sobre por onde começar a folha (2026-09-05): ADR mãe primeiro** — fixar
rubrica × incidência × vigência **antes** de escrever motor, porque é a única peça cujo erro
obriga a reescrever todo o resto. O motor precisa nascer **reentrante** (retroativo calcula na
alíquota da competência original — `infoPerApur`/`infoPerAnt`), o que não é refactor posterior.

**Achado que corre em paralelo, sem esperar ADR:** o total de cada contracheque é somado no
navegador e gravado **cru** — `PayrollController::store` L342 copia `final_total` do formulário
sem `num_uf` e sem recálculo, enquanto as linhas vizinhas 327 e 343 normalizam. É Tier 0 de valor
e tem chip próprio.

Quem pegar onda deste pedido: **rode `whats-active`** — e, enquanto o MCP estiver fora
(medido 2026-09-05: HTTP 000), o substituto é `gh pr list --state open` cruzado com os arquivos
que você vai tocar. A colisão de 04/09 saiu exatamente de ninguém ter feito isso.

### O que segue válido sem tocar em nada disso

`PR-8` (feito, [#6778](https://github.com/wagnerra23/oimpresso.com/pull/6778)), `PR-2/3`
([#6797](https://github.com/wagnerra23/oimpresso.com/pull/6797)), `PR-4`
([#6799](https://github.com/wagnerra23/oimpresso.com/pull/6799)), `PR-5`
([#6789](https://github.com/wagnerra23/oimpresso.com/pull/6789)) — todos mergeados e **não
afetados** por D1/D2/D3, porque tratam de licença, meta e exclusão, não de jornada.
