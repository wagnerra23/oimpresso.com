---
id: resources-js-pages-ponto-intercorrencias-edit-charter
page: /ponto/intercorrencias/{id}/edit
component: resources/js/Pages/Ponto/Intercorrencias/Edit.tsx
related_prototype: prototipo-ui/cowork/ponto-telas.jsx
owner: wagner
status: draft
last_validated: "2026-08-28"
parent_module: Ponto
related_us: [US-PONT-001]
related_adrs: [114, 104, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/intercorrencias/{id}/edit (DRAFT)

> **Status:** draft criado em 2026-08-28 na migração desta tela de Blade para Inertia.
> Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> **Origem:** esta era a **última tela Blade viva do módulo**. O SDD §5.4 item 1 mediu a
> dívida — dos 21 renders dos controllers do Ponto, 20 eram Inertia e **1** era Blade
> (`view('pontowr2::intercorrencias.edit')`). O operador que clicava "editar" num rascunho
> saía do shell React e caía no AdminLTE. Decisão [W] 2026-08-28: **a tela fica** (a
> alternativa avaliada era derrubar a rota e deixar o fluxo ser cancelar + recriar), então
> ela migrou.
>
> Backend: `IntercorrenciaController@edit` (form) + `@update` (rota
> `ponto.intercorrencias.update`, `IntercorrenciaRequest`). Middleware `ponto.access`.
>
> **A Blade legada NÃO foi apagada** — vira fóssil como as outras 25 do módulo e segue
> sendo o **contrato de paridade** desta tela.

---

## Mission
Editar uma intercorrência **enquanto ela é rascunho**. Mesma superfície de campos do
`Create`, sem a assistência de IA, com o estado atual carregado. Depois de submetida, a
ocorrência entra na trilha de aprovação e deixa de ser editável.

---

## Goals — Features (faz)
- Carrega o rascunho existente em todos os campos: colaborador, tipo, data, prioridade,
  dia-todo ou intervalo, justificativa, flags "impacta apuração" e "descontar banco de horas".
- Submit via `useForm().put` → `update`; a validação é o `IntercorrenciaRequest`, o mesmo do
  `store` (a tela não tem regra própria).
- Esconde os campos de horário quando "dia todo" está marcado — espelha o
  `required_unless:dia_todo,true` do FormRequest.
- Aviso explícito de que **só rascunho é editável**, mostrado ANTES do operador digitar.
- Lista de colaboradores ativos (`controla_ponto`, não desligados) escopada por `business_id`.

---

## Non-Goals — Features (NÃO faz)
- ❌ **Não edita intercorrência fora de `RASCUNHO`** — o backend responde 403
  (`abort_unless`), e isso é âncora do `CU-PONTO-05`, não zelo opcional.
- ❌ **Não submete nem aprova** — submeter é ação separada (`Show`), aprovar é do RH
  (`Aprovacoes/Index`).
- ❌ **Não oferece classificação por IA.** O `aiClassify` existe para virar texto livre em
  campos na **criação**; num rascunho já preenchido ele reescreveria escolha do operador.
  _(Inferência minha a partir do propósito do endpoint — pendente de [W].)_
- ❌ Não aplica efeito na apuração nem no banco de horas: as flags são intenção, aplicadas
  depois do fluxo de aprovação.
- ❌ Não edita intercorrência de outro tenant — o carregamento é escopado por `business_id`
  (global scope `HasBusinessScope`).

---

## UX targets
- p95 < 1500ms (admin) · cabe em 1280px (ROTA LIVRE) · AppShellV2 + `os-page-h` canon
  (ADR 0182) · mesmos componentes do DS que o `Create` (paridade visual).

---

## Automation hooks (faz)
- `update` delega ao Eloquent com o payload validado pelo `IntercorrenciaRequest`.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Salvar **não** submete para aprovação nem notifica o RH.
- ❌ Salvar **não** muda o estado da intercorrência — ela continua `RASCUNHO`.
- ❌ Não recalcula apuração nem banco de horas.
- ❌ Não faz autosave: a edição só persiste quando o operador salva.
  _(Inferência minha a partir do comportamento do form — pendente de [W].)_

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks (em especial os **dois marcados como inferência**)
- [ ] Smoke visual 1280/1440 (screenshot) contra o Blade legado — paridade de campos
- [ ] Decidir se o `anexo` (PDF/JPG/PNG, presente no `_form.blade.php` e no
      `IntercorrenciaRequest`) entra nesta tela — **não migrei**, porque nem o `Create.tsx`
      o oferece hoje; declarado aqui em vez de omitido
