---
page: /hrm/leave
component: resources/js/Pages/Essentials/Licencas/Index.tsx
runbook: memory/requisitos/Essentials/RUNBOOK-licencas.md
owner: wagner
status: draft
last_validated: "2026-09-05"
parent_module: Essentials
related_prototype: n/a (herda PT-01 Lista + drawer PT-02; segue o Padrão de Tela)
related_adrs: [93, 104, 114, 264, 286, 358]
alcance:
  rota: /hrm/leave
  rota_nome: essentials.licencas        # name() da rota — é o que o guard procura
  permission: essentials.crud_all_leave # o gate real do controller (ver R3)
  menu_hook: Modules/Essentials/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: essentials_module              # superadmin_package
tier: B
charter_version: 2
---

# Page Charter — /hrm/leave · Licenças (HRM) (DRAFT)

> **Status:** draft. A `.tsx` **existe** desde o PR-9 da onda HRM-O7
> ([`PEDIDO-CL-hrm.md`](../../../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md));
> [W] aprova o screenshot antes de virar `status: live`.
>
> **Origem do texto:** este charter foi escrito e revisado no commit `dbfc75fbcf`
> (PR [#6800](https://github.com/wagnerra23/oimpresso.com/pull/6800), que não aterrissou os
> artefatos — a catraca `charter_refs_broken` tem teto 0 e um `component:` apontando pra `.tsx`
> inexistente conta como ref quebrada no repo inteiro). Ele desce aqui **junto da tela**, que é
> o único estado suportado pelo gerador canônico `criar-tela.mjs`.
>
> **Alcance:** a rota `/hrm/leave` já existia (resource em
> [`Modules/Essentials/Routes/web.php`](../../../../../Modules/Essentials/Routes/web.php) linha 61)
> e o item de menu também — esta tela não nasce órfã, ela **substitui** o Blade na mesma URL.
>
> **Sobre `related_prototype`:** o build F1 do Cowork existe versionado em
> [`prototipo-ui/cowork/hrm-page.jsx`](../../../../../prototipo-ui/cowork/hrm-page.jsx)
> (`Licencas`) + `hrm-forms.jsx` (`FormLicenca`), mas o cabeçalho dele se declara *"Espelha o
> topnav de nav_hrm.blade"* — é um dos hubs de **porte reverso do código vivo** que a lápide
> §5 2026-08-28 proíbe promover a âncora de design em leva. Fica citado como build F1 (é o que
> é), e a âncora declarada é o Padrão de Tela. O alvo por seção usado na implementação está
> medido em [`EXPORT-HRM-2026-09-04.md`](../../../../../prototipo-ui/design-docs/cowork-inbox/hrm/EXPORT-HRM-2026-09-04.md) §3.

---

## Mission
Fila de pedidos de licença do negócio, com filtro por colaborador, situação e tipo; aprovar ou
cancelar em linha ou em lote; drawer com o pedido, o saldo do tipo, conflitos no período e o
histórico; aba de saldo por tipo.

---

## Goals — Features (faz)
- Lista as licenças do negócio com busca e filtro por colaborador, situação, tipo e período.
- Aprova/cancela em linha e em lote, dizendo **quantas** e que o colaborador é notificado.
- Drawer (PT-02) com pedido, saldo do tipo, conflitos no período e histórico.
- Aba "Saldo por tipo" com limite, aprovado, em análise, consumo e marca de risco de estouro.
- Formulário "Pedir licença" (para si ou, com `crud_all_leave`, para um colaborador).

---

## Non-Goals — Features (NÃO faz)
- ❌ Não calcula férias proporcionais nem gera aviso de férias.
- ❌ Não integra com folha — dias de licença **não** descontam automaticamente.
- ❌ Não substitui atestado (o anexo vive em Documentos, outro grupo de telas).
- ❌ Não tem aprovação em dois níveis nem hierárquica.
- ❌ Não tem cota por colaborador — a cota é **por tipo** (`max_leave_count`).
- ❌ Não oferece editar licença: `update()` do controller está vazio (R9 é lei até [W] mudar).
- ❌ Não tem calendário arrastável nem export PDF nesta onda.
- ❌ Não cadastra tipo de licença: a aba "Tipos de licença" é **link** para `/hrm/leave-type`,
  que tem controller e tela próprios. Duplicar o CRUD aqui criaria um segundo dono do tema.

---

## Regras de domínio
- **R1** Situação é uma de três: `pending` · `approved` · `cancelled`. Não existe "rejeitada"
  separada de "cancelada".
- **R2** Referência é gerada no servidor com o prefixo de
  `essentials_settings.leave_ref_no_prefix` (`setAndGetReferenceCount('leave')`) — a UI nunca
  deixa digitar.
- **R3** Quem tem `essentials.crud_all_leave` vê e cria para qualquer colaborador; quem só tem
  `essentials.crud_own_leave` vê e cria **apenas o próprio** (o filtro é do controller, não da UI).
- **R4** Trocar situação exige `essentials.approve_leave`; a troca notifica o colaborador
  (`LeaveStatusNotification`) e grava no activitylog.
- **R5** Criar notifica **todos os administradores** do negócio (`NewLeaveNotification`), um
  e-mail por licença criada — criar para 7 pessoas dispara 7 notificações por admin.
- **R6** Dias do período = `diffInDays + 1` (inclusivo nas duas pontas).
- **R7** Excluir licença exige `essentials.crud_all_leave` e é **hard delete**.
- **R8** Tipo de licença tem limite (`max_leave_count`) e intervalo (`leave_count_interval` =
  `year`|`month`), **aplicados desde o PR [#6797](https://github.com/wagnerra23/oimpresso.com/pull/6797)**
  pelo `LeaveBalanceService`. Limite `0` significa **sem limite** (UC-HRM-19), nunca "zero dias".
- **R9** `update()` do controller está vazio: licença criada **não pode ser editada**, só ter a
  situação trocada.
- **R10** `show()` e `edit()` retornavam `essentials::show`/`essentials::edit`, views que não
  existem → 500. **Fechado nesta onda:** os dois redirecionam para `/hrm/leave`, como o
  `EssentialsHolidayController` irmão já fazia. As rotas do resource podem sair no HRM-O8.
- **R11** O resumo por colaborador (`getUserLeaveSummary`) usa `is_admin` para escolher o
  `user_id`: gerente com `approve_leave` que não seja admin vê o **próprio** resumo, não o do filtro.
- **R12** O gate de entrada do `store()` é `superadmin` **ou** assinatura com `essentials_module`
  — e, ao contrário do `SalesTargetController`, **não** há escape pela permissão granular.
  Medido em `EssentialsLeaveController::store` vs `SalesTargetController` (2026-09-04).
- **R13** As datas do formulário trafegam no **formato do negócio** (`business.date_format`),
  não em ISO: o servidor grava com `ModuleUtil::uf_date()`, e o `StoreLeaveRequest` valida
  chamando *o mesmo conversor* de propósito. Ver [RUNBOOK §4](../../../../../memory/requisitos/Essentials/RUNBOOK-licencas.md).

---

## Achados (estado em 2026-09-05)
- **A1** A tradução PT do módulo chamava licença de "Sair" e a lista de "Todas as folhas" —
  **fechado** no PR [#6778](https://github.com/wagnerra23/oimpresso.com/pull/6778) (PR-8).
- **A2** `store()` sem FormRequest (fim antes do início, tipo de outro tenant, motivo vazio) —
  **fechado** pelo `StoreLeaveRequest` no PR [#6797](https://github.com/wagnerra23/oimpresso.com/pull/6797).
- **A3** `max_leave_count` nunca aplicado — **fechado** pelo `LeaveBalanceService` no mesmo #6797,
  no `store()` **e** no `changeStatus()`.
- **A4** `EssentialsLeaveTypeController::destroy` com corpo vazio — **fechado** no PR
  [#6789](https://github.com/wagnerra23/oimpresso.com/pull/6789) (devolve 422 com `blocked_by`).

Os quatro nasceram como teste vermelho e estão verdes: o veredito vem do manifesto derivado do
JUnit, não desta lista (G-7). Se algum voltar a falhar, é aqui que a redação está errada.

---

## Automation hooks
- Trio de tela: este charter + [`Index.casos.md`](Index.casos.md) +
  [`HrmLicencaTest.php`](../../../../../Modules/Essentials/Tests/Feature/HrmLicencaTest.php)
  (ADR 0264 G-1/G-2).
- Contrato visual: [`essentials-licencas.contract.json`](../../../../../prototipo-ui/contrato/essentials-licencas.contract.json)
  — agora **vigente**, porque a tela existe (`scripts/contrato-de-tela.mjs` §125-135: contrato só
  vale quando aplicado a uma tela real). As âncoras `data-contract` estão no `.tsx`.
- F1 PLAN do MWART: [`RUNBOOK-licencas.md`](../../../../../memory/requisitos/Essentials/RUNBOOK-licencas.md).

## Anti-hooks
- ⛔ Não derivar UC do `.tsx` — os casos derivam deste charter e do controller (§5 2026-06-05).
- ⛔ Não marcar `Status: ✅` à mão em `Index.casos.md`: o veredito vem do manifesto
  `scripts/casos-test-results.json`, derivado do JUnit (G-7).
- ⛔ Não duplicar o que `MultiTenantLeaveTest` já prova (isolamento em **query** Eloquent:
  listagem, show, update, destroy scoped). O teste desta tela cobre o eixo que falta —
  o **write HTTP** com id cru de outro tenant (LC-19: estender o dono, não abrir paralelo).
- ⛔ Não remover o ramo `request()->ajax()` do `index()` enquanto a blade
  `Resources/views/leave/index.blade.php` existir — ela é o consumidor dele (medido 2026-09-05).
  Sai junto das blades no HRM-O8.
- ⛔ Não fazer o endpoint aceitar data ISO "pra simplificar o front" (R13): criaria uma segunda
  convenção de data divergente da que grava, que é exatamente o que o `StoreLeaveRequest`
  documenta ter evitado.

---

## Pendências antes de `status: live`
1. Screenshot aprovado por [W] (gate visual F1.5).
2. Lane `PHP / Pest (Essentials · MySQL)` verde com os UC de tela.

**D3 do HRM-O0 saiu do caminho desta tela:** a
[emenda de 2026-09-05](../../../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md)
responde que licença aprovada bloqueia a marcação — mas, como D1 passou a jornada para o
`Modules/Ponto`, **o guard nasce no Ponto**, não aqui. Esta tela não depende dele.
