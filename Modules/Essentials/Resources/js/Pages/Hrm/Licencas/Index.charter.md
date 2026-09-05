---
id: resources-js-pages-hrm-licencas-index-charter
page: /hrm/leave
component: Modules/Essentials/Resources/js/Pages/Hrm/Licencas/Index.tsx
related_prototype: n/a (herda PT-01 Lista + drawer PT-02; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-09-04"
parent_module: Essentials
related_adrs: [93, 104, 114, 264, 286, 358]
tier: B
charter_version: 1
---

# Page Charter — /hrm/leave · Licenças (HRM) (DRAFT)

> **Status:** draft. A `.tsx` **ainda não existe** — este charter aterrissa no PR-1 do
> [`PEDIDO-CL-hrm.md`](../../../../../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md)
> (onda HRM-O5, "trio de prontidão") e a Page vem no PR-9 (onda HRM-O7). [W] aprova
> **Non-Goals + Anti-hooks** antes de virar `status: live`.
>
> Backend hoje: `EssentialsLeaveController` (Blade + datatables), rotas `Route::prefix('hrm')`
> em [`Modules/Essentials/Routes/web.php`](../../../../../Routes/web.php) linhas 60-64.
>
> **Sobre `related_prototype`:** o build F1 do Cowork existe versionado em
> [`prototipo-ui/cowork/hrm-page.jsx`](../../../../../../../prototipo-ui/cowork/hrm-page.jsx)
> (`Licencas`) + `hrm-forms.jsx` (`FormLicenca`), mas o cabeçalho dele se declara *"Espelha o
> topnav de nav_hrm.blade"* — é um dos hubs de **porte reverso do código vivo** que a lápide
> §5 2026-08-28 proíbe promover a âncora de design em leva. Fica citado como build F1 (é o que
> é), e a âncora declarada é o Padrão de Tela.

---

## Mission
Fila de pedidos de licença do negócio, com filtro por colaborador, situação e tipo; aprovar ou
cancelar em linha ou em lote; drawer com o pedido, o saldo do tipo, conflitos no período e o
histórico; aba de saldo por tipo; aba de cadastro de tipos.

---

## Goals — Features (faz)
- Lista as licenças do negócio com filtro por colaborador, situação, tipo e período.
- Aprova/cancela em linha e em lote, dizendo **quantas** e que o colaborador é notificado.
- Drawer (PT-02) com pedido, saldo do tipo, conflitos no período e histórico.
- Aba "Saldo por tipo" com limite, aprovado, em análise e marca de risco de estouro.
- Aba "Tipos de licença" para cadastro.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não calcula férias proporcionais nem gera aviso de férias.
- ❌ Não integra com folha — dias de licença **não** descontam automaticamente.
- ❌ Não substitui atestado (o anexo vive em Documentos, outro grupo de telas).
- ❌ Não tem aprovação em dois níveis nem hierárquica.
- ❌ Não tem cota por colaborador — a cota é **por tipo** (`max_leave_count`).
- ❌ Não oferece editar licença: `update()` do controller está vazio (R9 é lei até [W] mudar).
- ❌ Não tem calendário arrastável nem export PDF nesta onda.

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
  `year`|`month`) — **informativos hoje** (ver A3).
- **R9** `update()` do controller está vazio: licença criada **não pode ser editada**, só ter a
  situação trocada.
- **R10** `show()` e `edit()` retornam `essentials::show`/`essentials::edit`, views que não
  existem → 500. As rotas do resource precisam sair (HRM-O8).
- **R11** O resumo por colaborador (`getUserLeaveSummary`) usa `is_admin` para escolher o
  `user_id`: gerente com `approve_leave` que não seja admin vê o **próprio** resumo, não o do filtro.
- **R12** O gate de entrada do `store()` é `superadmin` **ou** assinatura com `essentials_module`
  — e, ao contrário do `SalesTargetController`, **não** há escape pela permissão granular.
  Medido em `EssentialsLeaveController::store` vs `SalesTargetController` (2026-09-04).

---

## Achados (viram teste vermelho — PR-1)
- **A2** `store()` não tem FormRequest: fim antes do início, tipo de outro tenant e motivo vazio
  passam. Medido: o método faz `$request->only([...])` e devolve **array** — o `try/catch`
  transforma erro em HTTP **200** com `success: false`.
- **A3** `max_leave_count` nunca é aplicado — nem no pedido, nem na aprovação.
- **A4** `EssentialsLeaveTypeController::destroy` é **corpo vazio**: a rota responde sem apagar.
- **A1** A tradução PT do módulo chama licença de "Sair" e a lista de "Todas as folhas" (PR-8).

---

## Automation hooks
- Trio de tela: este charter + [`Index.casos.md`](Index.casos.md) +
  [`HrmLicencaTest.php`](../../../../../Tests/Feature/HrmLicencaTest.php) (ADR 0264 G-1/G-2).
- Contrato visual: `hrm-licencas.contract.json` fica na **caixa de entrada**
  (`prototipo-ui/design-docs/cowork-inbox/hrm/`) até a Page existir — é a doutrina do próprio
  gate (`scripts/contrato-de-tela.mjs` linhas 125-135: contrato só é *vigente* quando aplicado
  a uma tela real). Vai pra `prototipo-ui/contrato/` no PR-9, junto das âncoras `data-contract`.

## Anti-hooks
- ⛔ Não derivar UC do `.tsx` — os casos derivam deste charter e do controller (§5 2026-06-05).
- ⛔ Não marcar `Status: ✅` à mão em `Index.casos.md`: o veredito vem do manifesto
  `scripts/casos-test-results.json`, derivado do JUnit (G-7).
- ⛔ Não duplicar o que `MultiTenantLeaveTest` já prova (isolamento em **query** Eloquent:
  listagem, show, update, destroy scoped). O teste desta tela cobre o eixo que falta —
  o **write HTTP** com id cru de outro tenant (LC-19: estender o dono, não abrir paralelo).

---

## Pendências antes de `status: live`
1. **D3** do HRM-O0 (licença aprovada bloqueia marcação de presença?) — decisão [W].
2. PR-2 (validação A2) e PR-3 (limite A3) no verde.
3. PR-9 cria a `Index.tsx`; PR-8 aplica o vocabulário PT antes do screenshot [W].
