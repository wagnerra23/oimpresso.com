---
id: requisitos-essentials-tipos-gap
tela: Essentials/Tipos (/hrm/leave-type)
prototipo: prototipo-ui/cowork/Wagner/hrm-page.jsx
tela_viva: resources/js/Pages/Essentials/Tipos.tsx
gerado_em: 2026-09-29
---

# GAP-SPEC — Essentials/Tipos

> **Em 2026-09-06, a única das 11 telas com `related_prototype` DIRETO no charter** — nas outras 7 do Essentials o vínculo
> era o `bundle_source`, e o `related_prototype` **4 declaravam `n/a` explícito** (Documents · Messages ·
> Todo · Settings) enquanto **3 não declaravam campo nenhum** (Knowledge · Reminders · Holidays). **Re-contado 2026-09-29:**
> não é mais a única — dos 17 `*.charter.md` sob `resources/js/Pages/Essentials/`, 8 declaram `related_prototype` com
> caminho (Documents/Index · Knowledge/Index · Messages/Index · Metas · Painel · Reminders/Index · Tipos · Todo/Index),
> 8 declaram `n/a` e 1 não declara o campo (Holidays/Index). Aqui o charter aponta
> `prototipo-ui/cowork/Wagner/hrm-page.jsx (subview "tipos" · copy literal)` — a subview vive em `hrm-page.jsx:283-300`.
> **Fase 1 = PARIDADE:** `hrm-page.jsx:2` declara o porte reverso de `nav_hrm.blade`.
> Dono da copy: [`essentials-tipos.contract.json`](../../../governance/design/contracts/essentials-tipos.contract.json),
> gerado e preenchido no MESMO PR da tela (HRM-O7 PR-9), com a copy **literal do protótipo**.
> Playbook: `03-tipos-licenca.md` (`prototipo-ui/cowork/Wagner/cowork-inbox/hrm/playbook/03-tipos-licenca.md`).
> Esta tela **já tem `data-contract`** nas 3 seções — âncora estável, não range de linha.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | **Paridade literal, ancorada.** `Tipos.tsx:135` (`<header data-contract="cabecalho">`); a copy do contrato é *"Tipos de licença"*. O comentário `:132-133` registra que o `data-contract` é a âncora do contrato e **não deve ser removido**. | Nada — paridade. |
| Toolbar | **Paridade literal, ancorada.** `Tipos.tsx:149` (`data-contract="toolbar"`), com o botão *"Novo tipo"* em `:162` — a copy exata do contrato. | Nada — paridade. ⚠️ O contrato registra um `_pendente_w`: a contagem *"N tipos"* é interpolada em runtime, então **não** entra como string literal; travar a forma exata exigiria `data-testid`. Segue com [W], e é do contrato, não deste gap. |
| Lista — colunas | **Paridade literal, ancorada.** `Tipos.tsx:169` (`data-contract="lista"`); as 4 colunas em `:185-188`: **Tipo** · **Limite** · **Intervalo** · **Pedidos no ano** — exatamente a `copy` da seção `lista` do contrato, e a ordem do alvo no playbook §A. O comentário `:14` documenta que "Limite" existe de fato em `essentials_leave_types.max_leave_count`. | Nada — paridade. |
| Estado vazio | **Paridade literal.** `Tipos.tsx:174`: *"Nenhum tipo de licença cadastrado"* — a 1ª string de `estados.vazio` do contrato. | Nada — paridade. ⚠️ 2º `_pendente_w` do contrato: o botão *"Cadastrar o primeiro tipo"* do protótipo **não desceu** porque o `create` segue no Blade legado (coexistência F5 do `RUNBOOK-tipos.md`). É ausência **declarada**, não gap. |
| Exclusão bloqueada por uso | **Paridade, com o caso difícil resolvido.** `Tipos.tsx:112-118` trata o 422 lendo `blocked_by.leaves` (`:116`); o comentário `:81-83` explica a pegadinha real — *"o Inertia interpreta 422 como erro de VALIDAÇÃO e só expõe `errors`; `msg` e `blocked_by` se perdem"* — e por isso a chamada é feita fora do Inertia. A copy de erro do contrato (*"Não dá para excluir este tipo"*) tem contraparte. O playbook §Não-toca é explícito: `@destroy` **fechado no #6789**, não mexer. | Nada — paridade. |
| Contagem "em uso" | **Paridade de rótulo; a fonte do número NÃO é a do `destroy` (medido 2026-09-29).** O rótulo hoje é **"Pedidos no ano"** nos três: contrato, vivo (`Tipos.tsx:188`) e protótipo (`hrm-page.jsx:289` — em 2026-09-06 o protótipo grafava *"Em uso"*). O playbook §B manda **reusar** o cálculo que o `destroy` faz em `blocked_by`; o vivo **não** reusa: a coluna mostra `t.leaves_count` (`Tipos.tsx:198`), que vem de uma query agrupada própria, só do ano corrente (`EssentialsLeaveTypeController.php:104-108`, `buildTiposPayload`), enquanto o `destroy` conta todas as licenças do tipo, sem janela (`:281-283`). O `blocked_by.leaves` só é lido no diálogo de exclusão (`Tipos.tsx:116`). | **Decidir.** O rótulo é paridade, o número não: o vivo faz query própria só do ano corrente, o que contraria o playbook (`03-tipos-licenca.md:18` — *"reusar, não recontar"* — e o PARAR SE de `:36`), e o protótipo (`hrm-page.jsx:295`) conta todas, como o `destroy`. Ou reusar o cálculo do `destroy` (e o rótulo deixa de ser "no ano"), ou registrar por escrito a exceção. |
| Nota "destroy está vazio" do protótipo | **Protótipo ATRASADO, vivo à frente.** O `_nota_fonte` do contrato registra: a `Nota` do protótipo dizendo *"EssentialsLeaveTypeController::destroy está vazio — o cadastro só cresce"* **caducou** com o [#6789](https://github.com/wagnerra23/oimpresso.com/pull/6789), que implementou o método com 422 + `blocked_by`. | Nada — vivo à frente. **Não** portar essa nota para o vivo: é fato datado do protótipo, já superado. |
