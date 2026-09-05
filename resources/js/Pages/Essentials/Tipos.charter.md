---
page: /hrm/leave-type
component: resources/js/Pages/Essentials/Tipos.tsx
owner: wagner
status: draft
parent_module: Essentials
related_prototype: prototipo-ui/cowork/hrm-page.jsx (subview "tipos" · copy literal)
related_runbook: memory/requisitos/Essentials/RUNBOOK-tipos.md
related_us: [US-ESS-005, US-ESS-010]
related_adrs: [104, 93, 264]
alcance:
  rota: /hrm/leave-type
  rota_nome: (Route::resource — `leave-type.index`)
  permission: essentials.crud_leave_type
  menu_hook: Modules/Essentials/Resources/views/layouts/nav_hrm.blade.php
  pacote: essentials_module
tier: B
charter_version: 1
---

# Page Charter — Essentials/Tipos (DRAFT)

> Cadastro de **tipos de licença** do HRM. Nascida do **PT-01 Lista** via `criar-tela.mjs`
> (UI-0013 — herança de padrão, não bespoke). É o **PR-9 (HRM-O7)** do pedido
> [`PEDIDO-CL-hrm.md`](../../../../prototipo-ui/design-docs/cowork-inbox/hrm/PEDIDO-CL-hrm.md).
> Processo MWART em [`RUNBOOK-tipos.md`](../../../../memory/requisitos/Essentials/RUNBOOK-tipos.md).

## Mission

Larissa precisa saber **quais licenças existem para pedir** e conseguir manter esse cadastro —
sem ele, ninguém pede licença nenhuma. E, quando um tipo não puder ser excluído, precisa saber
**por quê**, com número, não com "erro".

## Relação com as US declaradas

Nenhuma US do [SPEC](../../../../memory/requisitos/Essentials/SPEC.md) descreve o cadastro de
tipos, e isto registra a relação real em vez de forçar uma:

- **US-ESS-005** (Solicitação de Leave) — esta tela é **pré-requisito** dela, não a implementa.
  Sem tipo cadastrado não existe licença a pedir; é literalmente o que o estado vazio diz.
- **US-ESS-010** (Isolamento Tier 0) — quatro UCs desta tela a defendem (03, 04, 08), incluindo
  a contagem agregada, que é onde o vazamento passaria despercebido.

Se [W] quiser uma US própria para o cadastro de tipos, ela nasce no SPEC e substitui a primeira
linha acima — não é decisão que eu tome sozinho aqui.

## Decisões registradas

**Tela própria, não subview.** No protótipo (`hrm-page.jsx`) "Tipos" é a 3ª subview de um `Seg`
dentro de Licenças (`Licenças · Saldo por tipo · Tipos`). Aqui ela nasce **tela própria**, e a
razão é o backend, não o gosto: a rota é um `Route::resource` independente (`/hrm/leave-type`), a
permission é própria (`essentials.crud_leave_type`, distinta de `crud_all_leave`), e o menu já a
lista como item separado. O próprio export do Cowork a trata como onda com Page própria
(`EXPORT-HRM-2026-09-04.md`, onda 3, 4 arquivos). Fundir numa subview colapsaria três gates de
alcance distintos num só.

**Path flat** (`Pages/Essentials/Tipos.tsx`, não `Essentials/Hrm/Tipos/Index.tsx`): o gerador
canônico só emite `<Mod>/<Tela>.tsx` e é ele que garante `pt-conformance` por construção.
Divergimos das 9 Pages aninhadas do módulo de propósito — a garantia vale mais que a simetria de
pasta. Registrado também no RUNBOOK.

## Goals — Features (faz)

- Lista os tipos de licença do business, ordenados por nome
- Mostra o **Limite** real (`max_leave_count` + `leave_count_interval`) e, quando não há limite
  cadastrado, diz "sem limite" — nunca `0`, que significaria "zero dias permitidos"
- Mostra **Pedidos no ano**: quantas licenças do tenant usaram cada tipo no ano corrente
- **Explica a exclusão bloqueada**: o servidor devolve `422` com `blocked_by.leaves`; o diálogo
  permanece aberto dizendo **quantas licenças** travam a exclusão
- PT-BR em todo label, placeholder e mensagem

## Non-Goals — Features (NÃO faz)

- ❌ Não valida limite no pedido de licença — o limite é **informativo** hoje; a validação é o
  PR-3 do pedido (`LeaveBalance`), noutra superfície
- ❌ Não repete a nota do protótipo *"destroy está vazio — o cadastro só cresce"*: isso caducou
  com o [#6789](https://github.com/wagnerra23/oimpresso.com/pull/6789). É protótipo atrasado, não
  bug de produção
- ❌ Não cria/edita inline — `create` e `edit` seguem no Blade legado (coexistência F5). Fazer o
  formulário aqui é outro PR, com o seu próprio charter
- ❌ Não remove o ramo `request()->ajax()` do controller: o datatable Blade ainda o consome
- ❌ Não usa `router.delete` para excluir — o Inertia converte 422 em `errors` e engoliria o
  `blocked_by`, deixando o usuário com um "erro" genérico

## Automation Anti-hooks

- ❌ **Não** troque o `fetch` da exclusão por `router.delete`/`useForm.delete` "para padronizar":
  o motivo do bloqueio é o conteúdo da tela e se perderia no caminho
- ❌ **Não** faça o servidor devolver `0` no lugar de `null` em `max_leave_count`

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE)
- `tipos` via `Inertia::defer` — a tela pinta antes da query com agregação

## Refs

- Padrão de Tela: [PT-01 Lista](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)
- Constituição UI v2: [UI-0013](../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
- MWART: [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) · Tier 0: [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- Contrato do 422: `EssentialsLeaveTypeController::destroy` + `HrmExclusaoGuardaTest.php`
