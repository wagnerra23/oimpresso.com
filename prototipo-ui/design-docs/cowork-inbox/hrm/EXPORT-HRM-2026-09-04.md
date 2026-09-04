# EXPORT HRM (Essentials · RH) — pouso reconciliado · 2026-09-04

> **Este export NÃO é o doc único do módulo.** O dono do tema HRM é
> [`PEDIDO-CL-hrm.md`](PEDIDO-CL-hrm.md) (21/ago, [#6132](https://github.com/wagnerra23/oimpresso.com/pull/6132)),
> que já decompôs o módulo em HRM-O0 (3 decisões [W]) + O5–O8 (PR-1..PR-10) e deixou
> **7 artefatos prontos nesta mesma pasta**. O export de hoje **complementa** — não substitui.
>
> Medido no `main` em 2026-09-04 (`ac7e5e417c`). O anexo preserva o export como veio (recibo
> datado); as três frases refutadas ficam marcadas no lugar, não apagadas.

---

## 0 · ERRATA — 3 claims do export refutadas no `main`

| # | O export afirma | Medido no `main` | Recibo |
|---|---|---|---|
| **E1** | "não havia `COLAR-NO-CODE-*hrm*` nem `cowork-inbox/PEDIDO-*hrm*` (procurei). Este é o doc único do módulo" | **Existe** `cowork-inbox/hrm/` com **7 arquivos**, incluindo `PEDIDO-CL-hrm.md`. A busca casou só a **raiz** do `cowork-inbox/`; a convenção é `<modulo>/PEDIDO-CL-<modulo>.md`, uma pasta abaixo | `git ls-tree -r origin/main --name-only` filtrado por `hrm` devolve 17 hits, 7 deles em `cowork-inbox/hrm/` |
| **E2** | "`Modules/Essentials/**` … **4 testes Feature**" | **14 testes** (mais o `.gitkeep`). Dois cobrem exatamente as ondas do export: `MultiTenantLeaveTest` (5 casos cross-tenant de licença) e `SalesTargetShiftCrossTenantTest` (4 casos de meta e turno) | `git ls-tree -r origin/main --name-only Modules/Essentials/Tests` |
| **E3** | Pages novas em `resources/js/Pages/Hrm/**` (9 telas) | O `app.tsx` **documenta as duas convenções**, e o `PEDIDO-CL-hrm` já escolheu a de módulo — `Modules/Essentials/Resources/js/Pages/Hrm/**`, "como o módulo Cms faz". O Cms de fato mora lá. O export abre uma terceira convenção sem citar a escolha | `git show origin/main:resources/js/app.tsx` linhas 100-114 e a árvore `Modules/Cms/Resources/js/Pages/Site/` |

**E2 tem consequência de escopo:** o export lista a guarda multi-tenant como "invariante a preservar"
e o gate do `SalesTargetController` como achado. Os dois **já estão provados por teste no `main`** —
não são trabalho a fazer, são trabalho a **não quebrar**.

---

## 1 · Estado real do dono: parado, execução zero

O `PEDIDO-CL-hrm` está no repo há 14 dias e **nada dele foi executado**. Medido hoje:

| Peça do pedido | Estado no `main` | Recibo |
|---|---|---|
| PR-9 · páginas `Hrm/**` | ❌ não existe `Pages/Hrm/` em lugar nenhum | árvore filtrada por `Pages/Hrm` devolve vazio |
| PR-1 · `HrmLicencaTest.php` | ❌ só no `cowork-inbox/`, nunca aterrissou em `Modules/Essentials/Tests/` | filtro `HrmLicenca` na árvore devolve 1 hit, no inbox |
| PR-1 · `hrm-licencas.contract.json` | ❌ não aterrissou em `prototipo-ui/contrato/` | `git ls-tree origin/main prototipo-ui/contrato` sem hit de `hrm` |
| PR-8 · lang PT ("pode ir sozinho e já") | ❌ intacto: `leave` segue "Sair", `attendance` "Comparecimento", `shift` "Mudança", `employee` "Empregado", `holidays` "Férias", `leaves` "Folhas" | `Modules/Essentials/Resources/lang/pt/lang.php` linhas 62-216 |

**O caminho mais curto pro primeiro valor não é a Onda 1 do export.** É o PR-8: uma tabela de tradução
já escrita, sem dependência de decisão, sem tela, e que hoje faz o menu do RH dizer "Sair" para *licença*
e "Folhas" para *licenças*.

---

## 2 · Reconciliação — 9 ondas do export contra O5–O8 do pedido

| Export (04/set) | Pedido (21/ago) | Veredito |
|---|---|---|
| Onda 1 Painel | PR-9 `Hrm/Painel` | mesma tela. O export acrescenta o alvo medido (1007 nós, 8 Cards); o pedido acrescenta que o dado depende de **D1** |
| Onda 2 Licenças + saldo | PR-1 trio + PR-2/PR-3 | **o pedido está à frente**: charter, casos, contrato e teste já escritos nesta pasta |
| Onda 3 Tipos de licença | PR-5 (`destroy` que não existe) | complementares — o export desenha, o pedido diz que a rota responde 200 sem fazer nada |
| Onda 4 Presença + Onda 5 Espelho | PR-6 (import A7) + PR-7 (conflito, depende de **D3**) | **bloqueado por D3** no pedido; o export não menciona o bloqueio |
| Onda 6 Turnos | PR-5 (`ShiftController::destroy`) | complementares |
| Ondas 7-8 Folha | PR-9 + **D2** (folha gerencial ou título no Financeiro) | **bloqueado por D2**. Ambos declaram `PayrollController` (60 KB) **não lido** |
| Onda 9 Metas de venda | PR-4 (faixas A5) | complementares |
| — | **PR-8 lang PT** | **ausente do export** — e é o único isolado, sem bloqueio |
| — | **PR-10 fim do topnav Blade** | ausente do export |
| — | **HRM-O8 limpeza (cerca de 50 blades)** | ausente do export |

**Contagem de arquivos:** os 37 do export (30 novos + 7 controllers) contam só as ondas de tela.
Não incluem PR-8, PR-10, O8, nem os 4 artefatos já prontos que só precisam ser movidos para cá.

---

## 3 · O que o export acrescenta de fato (e o pedido não tinha)

Estas quatro peças são novas, medidas, e sobrevivem à errata:

1. **Bateria de a11y do alvo** com o método corrigido — a sonda por `cursor:pointer` **reprova**
   neste módulo (`button.os-btn` tem `cursor: default`) e produziu um falso negativo na primeira
   rodada. O veredito foi refeito por `onclick`/`role`/`tabIndex`. Corrigido no build: 60 `th`
   ganharam `scope="col"`, o `svg` da busca ganhou `aria-hidden`.
2. **T1 é armadilha aqui** — o módulo tem skeleton de carga: a primeira leitura do Painel dá 771 nós
   e a estável 1007. Medir sem esperar duas leituras iguais erra por cerca de 23%.
3. **Alvo medido por seção** (contagem e ordem dos filhos) — é o insumo do DoD de cada onda.
4. **`DataTablePro` do DS**: `TH` ordenável sem semântica e `th` sem `scope`. **Terceiro módulo**
   onde isso é medido (CRM, Repair, HRM) — passou de achado a dívida do DS.

---

## 4 · Fila de decisão [W] — unificada (as do pedido primeiro, porque bloqueiam)

| # | Decisão | Origem | Bloqueia |
|---|---|---|---|
| **D1** | HRM e Ponto WR2 convivem, ou a presença web cede lugar ao ponto legal? | pedido 21/ago | Ondas 1, 4, 5 e a folha |
| **D2** | Folha do Essentials é gerencial, ou vira título no Financeiro? | pedido 21/ago | Ondas 7-8 |
| **D3** | Licença aprovada bloqueia marcação de presença? | pedido 21/ago | Ondas 4-5 (PR-7) |
| **D4** | Validação de licença (fim antes do início, limite do tipo) entra **antes** da tela, ou a tela desce com o bug conhecido? | export 04/set (A2/A3) | Onda 2 |
| **D5** | Abro pedido de DS para o `DataTablePro` em vez de repetir o achado a cada módulo? | export 04/set | nada — é higiene |

**Ordem recomendada** (minha, não menu): **PR-8 lang PT** (isolado, sem bloqueio, valor imediato) →
**PR-1** movendo os 4 artefatos prontos desta pasta para o repo produtivo (o teste nasce vermelho, que é
a prova de D4) → responder D1/D2/D3 → ondas de tela na ordem do pedido.

**Não recomendo** começar pelo Painel (Onda 1 do export): ele depende de D1 e de um método
(`DashboardController@hrmDashboard`, 11 KB) que nenhum dos dois docs leu.

---

## 5 · Não medido nesta sessão

- **Não abri** os 6 arquivos restantes de `cowork-inbox/hrm/` (`Licencas.charter.md`, `Licencas.casos.md`,
  `Presenca.charter.md`, `Folha.charter.md`, `HrmLicencaTest.php`, `hrm-licencas.contract.json`).
  Sei que existem e o tamanho de cada um; **não** conferi se o conteúdo deles bate com o alvo medido
  no export de hoje. Essa conferência é o primeiro passo do PR-1.
- **Não li** `DashboardController@hrmDashboard`, `AttendanceController`, `ShiftController`,
  `PayrollController` — os dois docs concordam nesse buraco.
- **Não rodei** teste nenhum (é CT 100, não local).
- A a11y do anexo foi medida **no protótipo**, não em produção, em cerca de 841px e não em 1280px.

---
---

# ANEXO — export do Cowork, como veio (2026-09-04)

> Preservado íntegro por ser recibo datado da medição do alvo. As três frases refutadas na seção 0
> estão marcadas com ERRATA ao lado; o texto original **não** foi alterado.

> **Resposta curta à pergunta "quantos arquivos?": 37 arquivos em 9 ondas** — 30 novos (9 Pages + 3 componentes + 9 charters + 9 `casos.md`) e 7 controllers editados. Duas das 8 telas do protótipo **não entram** (a produção já tem Page). Detalhe e contagem por onda no bloco 6.
> **Ponte, não canon.** Destino no `main`: `prototipo-ui/` (root). Não escrevo no git: desce por `cowork-inbox`/Issue → PR, ou [W] cola 1×.
> **Anti-scatter:** não havia `COLAR-NO-CODE-*hrm*` nem `cowork-inbox/PEDIDO-*hrm*` (procurei). Este é o doc único do módulo — próximas ondas **reescrevem este arquivo**.
> ⚠️ **ERRATA E1 (seção 0):** falso. `cowork-inbox/hrm/PEDIDO-CL-hrm.md` existe desde 21/ago com mais 6 artefatos. A busca casou só a raiz do `cowork-inbox/`. Este arquivo **não** é o doc único do módulo e **não** deve ser reescrito por cima do dono.

---

## Arquivos lidos no `main` NESTE turno (6 + 2 árvores)

| # | arquivo | o que me disse |
|---|---|---|
| 1 | **`Modules/Essentials/Routes/web.php`** | as rotas reais do HRM, todas sob `prefix('hrm')`: `/dashboard` · `/leave` (resource) + `/change-status` + `/leave/activity/{id}` + `/user-leave-summary` · `/leave-type` (resource) · `/settings` (GET+POST) · `/attendance` (resource) + `import-attendance` + `clock-in-clock-out` + `validate-clock-in-clock-out` + `get-attendance-by-shift` + `get-attendance-by-date` + `get-attendance-row/{user_id}` + `user-attendance-summary` · `/payroll` (resource) + 8 rotas de payroll-group/pagamento + `/my-payrolls` + `/location-employees` · `/holiday` (resource) · `/shift` (resource) + `shift/assign-users` · `/sales-target` + `set-sales-target/{id}` + `save-sales-target` |
| 2 | **`resources/js/Pages/Essentials/Settings/Index.tsx`** | **é a tela `/hrm/settings` viva** (docvault: `status: implementada`, teste `SettingsIndexTest`): `useForm` + 4 Cards + `Label htmlFor` em todos os campos + `toast` sonner. As 10 chaves reais: `leave_ref_no_prefix` · `leave_instructions` · `payroll_ref_no_prefix` · `essentials_todos_prefix` · `grace_before_checkin` · `grace_after_checkin` · `grace_before_checkout` · `grace_after_checkout` · `is_location_required` · `calculate_sales_target_commission_without_tax` |
| 3 | **`Modules/Essentials/Http/Controllers/EssentialsLeaveController.php`** | dado real da Onda 2: `essentials_leaves` join `users` + `essentials_leave_types`; colunas `ref_no, user, leave_type, start_date, end_date, status, reason, status_note`; filtros `user_id, status, leave_type, start_date/end_date`; permissões `essentials.crud_all_leave` / `crud_own_leave` / `approve_leave`; `changeStatus` grava `status` + `status_note` e **notifica o colaborador**; `getUserLeaveSummary` devolve saldo por tipo; `ref_no` gerado com o prefixo de `essentials_settings` |
| 4 | **`Modules/Essentials/Http/Controllers/SalesTargetController.php`** | dado real da Onda 7: `essentials_user_sales_targets` (`target_start`, `target_end`, `commission_percent`) por usuário; permissão `essentials.access_sales_target`; **gate Tier 0 explícito** (`User::where('business_id',…)->findOrFail($request->user_id)`) contra IDOR cross-tenant (follow-up #4474) |
| 5 | árvore `resources/js/Pages/**` (filtro `essentials\|hrm\|attendance\|payroll\|leave\|holiday\|shift\|todo\|knowledge`) — **26 arquivos** | existe `Pages/Essentials/{Documents,Holidays,Knowledge,Messages,Reminders,Settings,Todo}`; **não existe** nada de `attendance`, `leave`, `shift`, `payroll`, `sales-target`, nem `Pages/Hrm/` |
| 6 | árvore `Modules/Essentials/**` | 19 controllers · 18 entities · 36 migrations · 4 services · **4 testes Feature** (`AutoClockOutMultiTenant`, `CrossTenantTodoLeave`, `EssentialsBladeT1InertiaSmoke`, `EssentialsTestCase`) · `Resources/views/` tem **só** `index.blade.php` na raiz (as views do HRM vêm do tema legado) |

> ⚠️ **ERRATA E2 (seção 0), linha 6 desta tabela:** são **14** testes Feature no `main`, não 4. Os ausentes da contagem incluem `MultiTenantLeaveTest` e `SalesTargetShiftCrossTenantTest`, que já provam o isolamento cross-tenant de licença, meta e turno.

**Ancoragem dupla:** alvo de layout = protótipo medido (abaixo); âncora de implementação = os arquivos 1–4 + o padrão das 7 Pages irmãs de `Pages/Essentials/**` que já existem.

---

## 0 · Leis que não se renegociam

1. **HRM ≠ Essentials-escritório.** O mesmo `Modules/Essentials` serve duas frentes: RH (`prefix('hrm')`) e escritório (`prefix('essentials')`: todo, KB, mensagens, documentos, lembretes). O protótipo `hrm-page.jsx` é **só a frente RH**; a frente escritório já tem 7 Pages e **não** está neste pedido.
2. **Multi-tenant Tier 0 (ADR 0093):** todo `user_id` que chega do body é validado contra `business_id` **antes** de escrever — o `SalesTargetController` já faz isso e o comentário no código explica por quê (o backstop só filtra SELECT, não INSERT). Qualquer Page nova preserva esse gate.
3. **Permissão nega antes de renderizar:** `essentials_module` na assinatura + as granulares (`crud_all_leave`, `crud_own_leave`, `approve_leave`, `access_sales_target`, …). O protótipo já modela isso em `A.pode(...)`.
4. **Mudança de status de licença notifica o colaborador** (`LeaveStatusNotification`) — não é ação silenciosa.
5. **Autoridade de token:** `TabBar` do DS → protótipo → produção. Medido: `NAV.ds-tabbar` com **8 abas**, **8 de 8 com `aria-selected`**. Zero cor crua no build do módulo.
6. **T1 antes de tudo:** esta tela tem **skeleton de carga** (`useCarga`) — a primeira leitura deu **771 nós** e a estável **1007**. Medir durante o skeleton produz alvo falso (ver bloco 8).

---

## 1 · Ordem das ondas + âncora (MAPA colhido do DOM)

Raiz: `DIV.os-page.hrm-page`. Abas do módulo (`TABS` em `hrm-page.jsx`): Painel · Licenças · Presença · Turnos · Folha de pagamento · Feriados · Metas de venda · Configurações.

| # | rota (host) | tela | medido (T1 estável) | âncora no `main` | frescor | vira pedido? |
|---|---|---|---|---|---|---|
| **1** | `hrm` | Painel | **1007 nós** · `.hrm-grid` com 8 Cards · fila de pendências derivada do dado · 13 botões · 0 tabela | `DashboardController@hrmDashboard` (Blade) | 🟠 **atrás** | ✅ |
| **2** | `hrm-licencas` | Licenças | **1009 nós** · `Seg` de 3 subviews (Licenças · Saldo por tipo · Tipos) · grade do DS 8 col · 14 campos | `EssentialsLeaveController` (+`EssentialsLeaveTypeController`) | 🟠 atrás | ✅ (2 ondas) |
| **3** | `hrm-presenca` | Presença | **1072 nós** · grade do DS 8 col + espelho do mês (`hrm-esp-h`, 1 `th` por dia) | `AttendanceController` (7 endpoints) | 🟠 atrás | ✅ (2 ondas) |
| **4** | `hrm-turnos` | Turnos | **840 nós** · `os-table` 7 col · 3 linhas | `ShiftController` + `shift/assign-users` | 🟠 atrás | ✅ |
| **5** | `hrm-folha` | Folha de pagamento | **851 nós** · `os-table` 8 col · 2 campos | `PayrollController` (60 KB — **não verifiquei**) | 🟠 atrás | ✅ (2 ondas) |
| — | `hrm-feriados` | Feriados | **882 nós** · `os-table` 7 col · `th` ordenável **com `<button class="mod-sort">`** | **`Pages/Essentials/Holidays/Index.tsx`** (14.768 B) + `Index.charter.md` **existem** | 🔵 **à frente** | ❌ |
| **6** | `hrm-metas` | Metas de venda | **896 nós** · `os-table` 7 col | `SalesTargetController` | 🟠 atrás | ✅ |
| — | `hrm-config` | Configurações | **840 nós** · 12 campos | **`Pages/Essentials/Settings/Index.tsx`** = `/hrm/settings`, **lida neste turno** | 🔵 **à frente** | ❌ |

**Receita do MAPA (reexecutável):** `document.querySelector('.hrm-page')` → `.hrm-grid`/`.hrm-body`; abas = `.ds-tabbar > *`; **esperar duas leituras iguais** de `querySelectorAll('*').length` (skeleton).

---

## 1-bis · Instrução de execução (ondas 1 e 2 — as duas primeiras, na forma padrão)

> ⚠️ **ERRATA E3 (seção 0):** os paths `resources/js/Pages/Hrm/**` abaixo divergem da escolha já registrada pelo `PEDIDO-CL-hrm` (`Modules/Essentials/Resources/js/Pages/Hrm/**`, "como o módulo Cms faz"). As duas convenções funcionam; a do pedido prevalece até decisão [W] em contrário.

```
ONDA 1 — Painel do HRM (.hrm-grid)
  ARQUIVOS A EDITAR   : resources/js/Pages/Hrm/Dashboard/Index.tsx            (CRIAR)
                        Modules/Essentials/Http/Controllers/DashboardController.php@hrmDashboard
  REUSAR (não recriar): @/Layouts/AppShellV2 · @/Components/shared/{PageHeader,KpiCard,KpiGrid,EmptyState}
                        o padrão de gráfico ACESSÍVEL de Pages/Repair/Dashboard/Index.tsx
                          (role=img + <title> + coluna textual + resumo sr-only) — já resolvido lá
                        Inertia::defer para as séries (mesmo padrão de Repair/Dashboard e DeviceModels)
  CRIAR               : só a Page. Rota /hrm/dashboard já existe e já tem name('hrmDashboard').
  NÃO TOCAR           : Pages/Essentials/** (as 7 telas da frente escritório)
                        Pages/Essentials/Settings/Index.tsx e Holidays/Index.tsx (JÁ prontas)
                        AttendanceController::clockInClockOut (o ponto do colaborador — outra onda)
                        AppShellV2, sidebar, tokens do DS (fundação)
  PASSO A PASSO       : 1) Inertia::render em hrmDashboard com os agregados que o método já calcula
                        2) montar a "fila do que fazer primeiro" a partir dos MESMOS agregados
                           (licença pendente · marcação sem saída · folha em rascunho · meta faltando)
                        3) KPIs no topo, 8 Cards no grid, na ordem do alvo (bloco 3)
  DADO                : NÃO VERIFIQUEI o corpo de DashboardController@hrmDashboard (11 KB) —
                        o pedido da Onda 1 só fecha depois de ler esse método. Sem ele, cada
                        número do painel é ausência declarada ("—"), não invenção.
  PARAR SE            : (a) o agregado não existir no método → "—" + linha no PR
                        (b) precisar de query nova só pra encher card → para: card sai do escopo

ONDA 2 — Licenças: lista + saldo por tipo (.hrm-toolbar + grade + Seg)
  ARQUIVOS A EDITAR   : resources/js/Pages/Hrm/Leave/Index.tsx                        (CRIAR)
                        resources/js/Pages/Hrm/Leave/_components/SaldoPorTipo.tsx     (CRIAR)
                        Modules/Essentials/Http/Controllers/EssentialsLeaveController.php
                          (@index → Inertia::render preservando o ramo request()->ajax() do DataTables;
                           @getUserLeaveSummary → devolver JSON em vez de view parcial)
  REUSAR (não recriar): o mapa de status do LeaveRequestService (statusMap) — não recriar rótulo
                        @/Components/ui/{select,input,badge,button} · shared/{PageHeader,EmptyState}
                        o padrão de partial reload only:[...] de Pages/Repair/Index.tsx
                        as notificações que JÁ existem (NewLeaveNotification, LeaveStatusNotification)
  CRIAR               : as 2 Pages/componentes acima. Nenhuma rota nova.
  NÃO TOCAR           : /hrm/change-status (contrato de gravação e notificação intactos)
                        EssentialsLeaveController@destroy · @activity
                        Modules/Essentials/Services/LeaveRequestService.php
  PASSO A PASSO       : 1) Inertia::render com leave_statuses + users + leave_types (já no @index)
                        2) grade com as 8 colunas do alvo, na ordem
                        3) aprovar/cancelar chamando POST /hrm/change-status (status + status_note)
                        4) subview "Saldo por tipo" consumindo getUserLeaveSummary
  DADO                : essentials_leaves.{ref_no,user_id,essentials_leave_type_id,start_date,
                        end_date,status,reason,status_note} · essentials_leave_types.leave_type
                        filtros: user_id · status · leave_type · start_date/end_date
                        permissões: essentials.crud_all_leave · crud_own_leave · approve_leave
  PARAR SE            : (a) **limite por tipo de licença**: o protótipo mostra "Limite" por tipo e o
                            controller NÃO valida limite nem data (achados A2/A3 do próprio protótipo).
                            Coluna "Limite" só entra se existir em essentials_leave_types — senão "—".
                        (b) o ramo ajax() do DataTables ainda é consumido por alguma tela legada →
                            preservar; não trocar por Inertia-only sem confirmar
```

As ondas 3–9 seguem a mesma forma; a instrução de cada uma nasce **na sessão limpa daquela onda** (regra §2-quater), não aqui.

---

## 2 · Onda 0a — a11y do ALVO (o que falhou foi corrigido AQUI)

Bateria no protótipo servido, dark, **após estabilizar** (skeleton).

| # | item | medido | veredito | ação |
|---|---|---|---|---|
| T5 | **sanidade da sonda** | o método "cursor:pointer" **reprovou**: `BUTTON.os-btn` tem `cursor: default` neste módulo. A sonda de A1 por pointer daria **falso negativo** (e na 1ª rodada deu: "0 de 8" no painel) | ⚠️ método | **A1 remedido** por `onclick`/`role`/`tabIndex`, não por cursor |
| A1 | falso interativo | `TH` ordenável **sem semântica**: 5 em Licenças · 5 em Presença · **0** em Feriados/Turnos/Folha/Metas | 🟠 **DS** | os 5+5 são da grade do DS (`DataTablePro`, estilo inline); as minhas tabelas usam `<button class="mod-sort">` dentro do `th` — **o meu está certo** |
| A1b | linha da grade | **8 de 8** `tbody tr` com `role`/`tabindex` | ✅ | o DS já resolve |
| A3 | ícone sem nome | painel 1 de 1 · licenças 3 de 3 · presença 6 de 6 · turnos 1 de 1 | 🔴 → 🟠 | **corrigido no build** o que é meu: o `svg` da busca (`Busca` em `hrm-ui.jsx`) ganhou `aria-hidden="true"`. Os demais são do `Alert`/`TabBar` do DS → bloco 7 |
| A5 | ARIA nas abas | **8 de 8** `aria-selected` | ✅ | TabBar do DS |
| A7 | alvo <24px | **0** de 13/21/24 botões | ✅ | o HRM é o único módulo medido que passa aqui |
| A10 | `aria-live` | **1** em 7 das 8 views (`hrm-toast-host` com `role=status aria-live=polite aria-atomic=true`) · **0 no Painel** | ✅/⚪ | o Painel **não escreve nada** (todos os botões navegam via `window.__go`) — região viva sem mensagem seria decoração. **Declarado, não "corrigido"** |
| — | `th scope` | **0 de 8/7/8/7/7** nas tabelas escritas à mão (`os-table`) — o átomo `Tabela` de `hrm-ui.jsx` já tinha `scope="col"` | 🔴 → ✅ | **corrigido no build**: **60 `th`** ganharam `scope="col"` (`hrm-page.jsx` 18 · `hrm-extras.jsx` 42, inclusive o `th` por dia do espelho de ponto) |
| — | campo sem rótulo | **0** em todas as 8 views (a busca já passava `aria-label={placeholder}`; os `select` têm `aria-label`) | ✅ | — |
| — | `th aria-sort` | 1 de 8 (só a coluna ordenada, na grade do DS) | ✅ | `aria-sort` só na coluna ativa é o correto |

**Build alterado neste ciclo:** `hrm-page.jsx` · `hrm-extras.jsx` · `hrm-ui.jsx` · `oimpresso.com.html` (bump `?v=hrm9a11y`). Zero mudança de layout.

---

## 3 · ALVO medido por seção (read-only, dark)

**Shell:** `DIV.os-page.hrm-page` · `NAV.ds-tabbar` com **8 abas** (`BUTTON`, `aria-selected` 8/8) · contadores mono nas abas Licenças/Presença/Turnos/Feriados.

| seção | alvo |
|---|---|
| **Painel** (`.hrm-grid`) | 8 `Card` · 1º card "O que fazer primeiro" com `.hrm-list` de `.hrm-row` (`.hrm-row-l` → `.hrm-row-t`+`.hrm-row-s` · `.hrm-row-v` → `button.os-btn.ghost`), linha urgente ganha `.urg` + `i.hrm-dot` · vazio = `Vazio variante="done"` · 13 botões · 1007 nós |
| **Licenças** (`.hrm-toolbar` + grade) | toolbar: busca + 2 `select.hrm-sel` (`aria-label` "Filtrar por situação"/"por tipo") + `.usr-count` "N de M" + `.hrm-spacer` + `.hrm-kbd` (`/` buscar · `n` novo) + `button.os-btn.primary` "Pedir licença" · grade do DS **8 colunas** (ref · tipo · quem{primary+sub} · período{primary+sub} · motivo · status · ação) · linha `state` urgent quando `pending` e início ≤ hoje, `archived` quando `cancelled` · ação inline com `stopPropagation` |
| **Saldo por tipo** | `os-table` 6 col: Tipo · Limite · Aprovado · Em análise · Consumo · Risco |
| **Presença** | grade do DS 8 col + espelho do mês: 1 `th.hrm-esp-h` **por dia** (`d.slice(-2)`) · 1072 nós |
| **Turnos / Folha / Metas / Feriados** | `os-table` com 7 · 8 · 7 · 7 colunas; Feriados ordena por `nome`/`ini`/`dias` com `button.mod-sort` dentro do `th` |

---

## 4 · Comportamento + invariantes (Onda 2 — EARS)

| elemento | TAG | estados | gatilho | efeito | persistência | reversível | prova |
|---|---|---|---|---|---|---|---|
| busca (`.usr-search input`) | `INPUT` | vazio · com texto · foco | `digitação` · `tecla /` | **QUANDO** digitado **O SISTEMA DEVE** filtrar por ref/colaborador/tipo/motivo | não persiste (querystring na produção) | sim (✕ limpa) | contagem `.usr-count` muda |
| `select` situação / tipo | `SELECT` | default · aplicado | escolha | **QUANDO** escolhido **O SISTEMA DEVE** filtrar server-side (`status`, `leave_type`) | querystring | sim ("Todas as situações") | teste: filtro aplicado reduz o total |
| linha da grade | `TR role=button` | default · hover · urgent · archived · selected | `clique` | **QUANDO** clicada **O SISTEMA DEVE** abrir o drawer da licença | — | sim (esc fecha 1 nível) | sonda: drawer monta |
| "Aprovar" / "Cancelar" (na célula) | `BUTTON` | visível só com `approve_leave` | `clique-no-filho` (**declara `stopPropagation`**) | **QUANDO** clicado **O SISTEMA DEVE** POSTar `/hrm/change-status` com `status`+`status_note` e **notificar o colaborador** | grava em `essentials_leaves` | não (é escrita; reverter = novo status) | Pest: status muda + `LeaveStatusNotification` enviada |
| seleção em lote + `Bulk` | `BUTTON`s | 0 · N selecionadas | `clique` na checkbox | **QUANDO** há seleção **O SISTEMA DEVE** anunciar em `role=status` e oferecer aprovar/cancelar em lote | — | sim (fechar limpa) | região live recebe "N selecionadas" |
| "Pedir licença" | `BUTTON` | default · **disabled em demonstração** | `clique` · `tecla n` | **QUANDO** enviado **O SISTEMA DEVE** criar com `status=pending`, `ref_no` com prefixo de `essentials_settings` e notificar os admins | grava | não | Pest: `NewLeaveNotification` + `ref_no` com prefixo |

**Invariantes:** 1) permissão nega antes de renderizar · 2) `user_id` do body validado por tenant antes de escrever · 3) filtro é reversível · 4) clique aninhado declara `stopPropagation` · 5) `esc` fecha um nível · 6) teclado escopado (`/` busca, `n` novo) · 7) estado vazio diz por que e o que fazer, com ação "Limpar busca e filtros" · 8) sem número inventado ⇒ `—` + linha no PR.

---

## 5 · Não inventar

- **CSS:** Tailwind + tokens do DS, como as 7 Pages irmãs de `Pages/Essentials/**`. Zero hex cru.
- **Componentes:** `@/Components/ui/*` + `@/Components/shared/*` (REGISTRY) + o `BarChartCard` acessível que **já existe** em `Pages/Repair/Dashboard/Index.tsx` — copiar o padrão, não reinventar gráfico.
- **Dados:** só os nomes lidos nos controllers (blocos 1-bis). O que eu não li (`DashboardController@hrmDashboard`, `AttendanceController`, `ShiftController`, `PayrollController`) **não tem dado declarado neste pacote** — a onda correspondente lê no turno dela.
- **Copy:** literal do protótipo, PT-BR, sentence case, vocabulário do domínio (**marcação**, **licença/afastamento**, **banco de horas**, **colaborador**). Sem emoji.

---

## 6 · DoD + PLACAR + **contagem de arquivos**

**PLACAR HRM — ciclo 2026-09-04:** 8 telas mapeadas · **2 não viram pedido** (Feriados e Configurações já têm Page em produção) · **6 telas → 9 ondas** (3 telas se dividem por tamanho: Licenças, Presença e Folha).

### Quantos arquivos o Code precisa (a resposta)

| onda | tela | Page `.tsx` | componentes | `charter.md` | `casos.md` | controller editado | **total** |
|---|---|---|---|---|---|---|---|
| 1 | Painel | `Hrm/Dashboard/Index.tsx` | — | 1 | 1 | `DashboardController@hrmDashboard` | **4** |
| 2 | Licenças (lista + saldo) | `Hrm/Leave/Index.tsx` | `_components/SaldoPorTipo.tsx` | 1 | 1 | `EssentialsLeaveController` | **5** |
| 3 | Tipos de licença | `Hrm/LeaveTypes/Index.tsx` | — | 1 | 1 | `EssentialsLeaveTypeController` | **4** |
| 4 | Presença (dia) | `Hrm/Attendance/Index.tsx` | — | 1 | 1 | `AttendanceController` | **4** |
| 5 | Espelho do mês | `Hrm/Attendance/Espelho.tsx` | — | 1 | 1 | (mesmo `AttendanceController`) | **3** |
| 6 | Turnos | `Hrm/Shift/Index.tsx` | `_components/AtribuirUsuarios.tsx` | 1 | 1 | `ShiftController` | **5** |
| 7 | Folha — lotes | `Hrm/Payroll/Index.tsx` | — | 1 | 1 | `PayrollController` | **4** |
| 8 | Folha — lote/contracheque | `Hrm/Payroll/Show.tsx` | — | 1 | 1 | (mesmo `PayrollController`) | **3** |
| 9 | Metas de venda | `Hrm/SalesTarget/Index.tsx` | `_components/DefinirMeta.tsx` | 1 | 1 | `SalesTargetController` | **5** |
| | **soma** | **9 Pages** | **3 componentes** | **9** | **9** | **7 controllers** | **37** |

**37 arquivos = 30 novos + 7 editados**, em **9 PRs** (1 onda = 1 PR ≤300 linhas). Zero rota nova, zero migration, zero componente de DS novo.
**Margem declarada:** a contagem da Folha (ondas 7–8) é a menos confiável — `PayrollController` tem **60 KB** e **não foi lido**; se os grupos de folha e o fluxo de pagamento exigirem telas próprias, sobem 2–4 arquivos. As ondas 1–6 e 9 têm âncora lida ou rota lida.

**DoD por onda (recibo do PR):** 1) contagem **e ordem** dos filhos = alvo do bloco 3 · 2) cada linha de comportamento com o teste que a prova · 3) `design-diff --compare --check` → 0 `DIVERGE(bug)` na seção (**T7, exige deploy**) · 4) screenshot prod autenticado · dark · 1280px · 5) `casos.md` com ≥1 UC citado por teste, MESMO PR · 6) **PLACAR no corpo do PR** · 7) contrato destilado no charter, MESMO PR · 8) `github.md` com a linha do ciclo + `bundle regenerado`.

---

## 7 · O que a ancoragem NÃO resolve

| # | item | natureza | dono |
|---|---|---|---|
| 1 | **`DashboardController@hrmDashboard` não lido** (11 KB) — a Onda 1 não fecha sem ele; sem leitura, todo número do painel é ausência declarada | dado não verificado | Onda 1 (ler no turno) |
| 2 | **`PayrollController` (60 KB) não lido** — 8 rotas de payroll-group/pagamento sem mapa; a contagem das ondas 7–8 é estimativa | dado não verificado | Onda 7 |
| 3 | **O protótipo declara 2 achados do próprio `main`** (A2/A3, escritos no card "Achados desta leitura do main"): pedir licença **não valida** fim-antes-do-início nem limite do tipo no servidor. Isso é **bug de backend**, não de tela — a Page não deve mascarar | correção de backend | [W] prioriza |
| 4 | **`hrm-config` × `Settings/Index.tsx`:** meu protótipo tem **12 campos**, a Page viva tem **10 chaves**. Não medi campo-a-campo qual é a diferença → **não afirmo** que há gap; se houver, é onda de 1 arquivo | paridade não medida | próxima sessão |
| 5 | **`Pages/Essentials/Holidays/Index.tsx` existe mas eu não li o conteúdo** — trato Feriados como 🔵 pela existência de Page+charter, não por paridade medida | verificação pendente | próxima sessão |
| 6 | **`DataTablePro` do DS:** `TH` ordenável sem `role`/`tabindex`, `th` sem `scope`, svg do gatilho anônimo — **terceiro módulo** onde eu meço isso (CRM, Repair, HRM). Já passa de achado a dívida sistêmica | dívida do DS | pedido DS próprio |
| 7 | **Frente escritório do Essentials** (todo/KB/mensagens/documentos/lembretes) tem 7 Pages e **nenhum protótipo meu equivalente** nesta rodada — não é gap, é escopo | fora de escopo | — |
| 8 | **Zero `<main>` no documento** (AP9) · **rota do `app.jsx` sem componente (C6)** | fundação / cobertura declarada | fundação |

---

## 8 · Não medido, declarado

- **T1 é armadilha aqui:** o módulo tem skeleton de carga. Primeira leitura do Painel: **771 nós**; estável: **1007**. Qualquer número medido sem esperar duas leituras iguais está errado por ~23%.
- **A sonda de A1 por `cursor:pointer` não vale neste módulo** — `button.os-btn` tem `cursor: default`. O caso de sanidade pegou; o veredito de A1 foi refeito por `onclick`/`role`/`tabIndex`.
- **Contraste (A8):** não medido (exige OKLCH→sRGB com caso de sanidade).
- **A2 (foco):** meus números de `outline:none` × `:focus-visible` são globais do documento, não por seção do HRM.
- **Largura:** medido em ~841px (janela do preview), não em 1280px.
- **Não verifiquei (não lidos neste turno):** `DashboardController` · `AttendanceController` · `ShiftController` · `PayrollController` · `EssentialsLeaveTypeController` · `EssentialsHolidayController` · `EssentialsSettingsController` · `EssentialsUtil` · os 4 Services · `Pages/Essentials/Holidays/Index.tsx` e os outros 6 conjuntos de `Pages/Essentials/**` · `memory/requisitos/Essentials/{SPEC,SUPERFICIE,BRIEFING}.md` · os 13 scorecards `essentials-*.yaml` · `LICOES_CC.md` · `proibicoes.md` · `REGISTRY_DS_COMPONENTES.md`.

---

## 9 · Recibo

- **Build alterado (só a11y):** `hrm-page.jsx` · `hrm-extras.jsx` · `hrm-ui.jsx` · `oimpresso.com.html`.
- **Ponte:** este arquivo (doc único do módulo).
- **Charter/casos:** nada a destilar agora — o contrato destilado de cada seção nasce no PR da onda dela.
- **Pacote (regra de saída):** **não regenerado** — o gerador exige os arquivos em disco e não roda do meu lado (ADR 0374). O ciclo fecha **sem pacote**; o comando é:

  ```
  node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
  ```

---

## RESÍDUO HRM — fila de decisão de [W]

> ⚠️ **Reconciliado na seção 4 deste pouso**: as 3 perguntas abaixo passam a ser D4/D5 e a ordem, na fila unificada com D1/D2/D3 do `PEDIDO-CL-hrm`, que **bloqueiam** as ondas 1, 4, 5, 7 e 8.

1. **Ordem das 9 ondas:** sugiro Licenças → Presença → Turnos → Metas → Painel → Folha (a Folha é a mais caras e a menos lida). Confirma?
2. **Validação de licença no servidor** (fim antes do início · limite do tipo): entra como correção de backend antes da Page, ou a Page desce com o bug conhecido?
3. **Grade do DS (3º módulo com o mesmo defeito):** abro um pedido de DS para `DataTablePro` (`th scope`, `TH` ordenável semântico) em vez de repetir o achado por módulo?
