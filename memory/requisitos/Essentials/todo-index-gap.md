---
id: requisitos-essentials-todo-index-gap
tela: Essentials/Todo/Index (/essentials/todo)
prototipo: prototipo-ui/cowork/essenciais-page.jsx
tela_viva: resources/js/Pages/Essentials/Todo/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Essentials/Todo/Index

> **Fase 1 = PARIDADE, não wishlist.** `essenciais-page.jsx:1-3` declara: *"Importado do blade do
> main: Modules/Essentials/Resources/views/*"* — **porte reverso**, logo o protótipo é retrato do
> vivo. Região: `Tarefas` (`:33-231`).
> Contrato do intake: [`cowork-inbox/essenciais/contrato/tarefas.contract.json`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/tarefas.contract.json)
> (6 seções · 9 colunas · copy literal). Charter: [`Tarefas.charter.md`](../../../prototipo-ui/design-docs/cowork-inbox/essenciais/Tarefas.charter.md).
> ⚠️ O charter do intake declara **3 itens fora de escopo esperando [W]** (vínculo tarefa ↔ OS/cliente ·
> versionamento de documento · canal de notificação) — este gap **não os reabre**.

> ⚠️ **O contrato citado ainda NÃO é gate ativo.** Ele vive em
> `prototipo-ui/design-docs/cowork-inbox/essenciais/contrato/`, **não** em `prototipo-ui/contrato/`
> (medido com o critério do próprio gate — `git ls-files "prototipo-ui/contrato/*.contract.json"` sem o EXEMPLO, como `scripts/contrato-de-tela.mjs` faz: **28** contratos ativos, que incluem `essentials-tipos`, `essentials-licencas` e
> `essentials-metas` — nenhum dos 5 dos essenciais). Ele é **proposta de contrato**: descreve a
> copy literal pretendida e serve de âncora para esta comparação, mas **não trava merge hoje**.
> Por isso as divergências de copy abaixo saem como `Decidir.`, nunca como "quebra de gate".

| Parte | Estado no vivo | Ação |
|---|---|---|
| Toolbar de filtros | **Paridade de capacidade.** Vivo `Todo/Index.tsx:219-224` (cabeçalho "Filtros" + botão "Limpar") e `:228-...` com 5 selects em grid: Status (`:233`), Prioridade (`:245`), Atribuído a (`:258`). Protótipo: `hrm-toolbar` em `:165`, com Situação (`:176-177`) e o botão "Adicionar" (`:180`). | Nada — paridade. |
| Filtro por período de início | **EXISTE nos dois lados — e a divergência é de semântica, não de ausência.** Vivo `Todo/Index.tsx:267-283`: `<Input id="start_date" type="date">` (rótulo **De**) e `<Input id="end_date" type="date">` (**Até**), com o range aplicado em `ToDoController.php:106-108`. Protótipo: 2ª toolbar `ess-toolbar2` em `:182`, rótulo *"Período de início"* (`:183`). A diferença medida: o vivo só filtra quando **os dois** campos vêm preenchidos (`$request->filled('start_date') && $request->filled('end_date')`); o protótipo aceita **um só** (basta `de` **ou** `ate`). | **Decidir.** A capacidade está entregue; o que falta decidir é a **semântica do range aberto** — "de 01/09 em diante", com só o campo De preenchido, hoje não filtra nada e a tela não avisa. É 1 linha de controller, mas muda o resultado que o usuário vê. |
| Colunas da tabela | **Diverge: 8 no vivo × 9 no contrato, e 2 divergem só no rótulo.** Vivo `:302-309`: Código · Tarefa · Status · Prioridade · Início · Fim · Atribuído a · Ações. Contrato/protótipo (`:119-128`): Criado em · ID da tarefa · Tarefa · Situação · Início · Fim · Horas est. · Atribuído por · Atribuído a. **Renomeações** (não ausências — §5 2026-07-15): `ID da tarefa`→`Código`, `Situação`→`Status`. **Ausentes de fato:** `Criado em` · `Horas est.` · `Atribuído por`. **Só no vivo:** `Prioridade` · `Ações`. | **Decidir.** ⚠️ O dado dos 3 ausentes **já chega no payload**: `Todo/Index.tsx:65` (`estimated_hours`), `:66` (`assigned_by`), `:68` (`created_at_human`). Varredura contada: `estimated_hours` e `assigned_by` aparecem **só na interface TypeScript, em nenhum ponto do JSX** (2 hits cada, ambos em `:65-66`); `created_at_human` é renderizado, mas como sub-linha *"criada {…}"* (`:320-322`), não como coluna. Ou seja: é **decisão de exibição**, não capacidade faltando — barato, e por isso mesmo precisa de [W] decidir a forma (coluna × sub-linha) antes de mexer. |
| Seleção em lote (bulkbar) | **Ausente.** Protótipo: `Bulk n={selecionadas.length} rotulo="tarefas selecionadas"` (`:214`) com ações Concluir/Excluir (`:218`), alimentado por `selLinhas` (`:45`). Varredura contada no vivo: os termos `Checkbox` e `onCheckedChange` em `Todo/Index.tsx` = **0 ocorrência de cada um**. | **Decidir.** Comportamento, e o contrato tem `bulkbar` como seção. Mas é **mutação em lote** — precisa da regra de permissão (`essentials.assign_todos`) e de teste antes da UI. Decisão de [W] sobre escopo, não sobre desejo. |
| Modal de mudança de situação | **Paridade.** Vivo `:424-431`: `<Dialog>` "Atualizar status" citando a tarefa (`:430`). Protótipo: `X.ModalStatus` em `:157` e `:225`. | Nada — paridade. |
| Drawer de detalhe | **Existe como PÁGINA, não como drawer.** Protótipo: `DetalheTarefa` (`:232-281`), painel lateral com histórico, comentários e documentos. Vivo: rota própria `/essentials/todo/{id}` — `resources/js/Pages/Essentials/Todo/Show.tsx`, linkada em `Index.tsx:317`; o `ancora.mjs --list` a descreve como *"dados da tarefa + tabs comentários/anexos/atividades"*. No `Index` há só `Dialog`/`AlertDialog` (`:27-45`) para status e exclusão. | **Decidir.** Drawer × página é decisão de forma (eixo FORMA: protótipo soberano, [UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)), **não** de capacidade — comentários e anexos já estão entregues no `Show`. ⚠️ Nada disso está entre os 3 itens que aguardam [W] no `LEIA-ME.md` (vínculo tarefa ↔ OS/cliente · versionamento de documento · canal de notificação); só o **vínculo com OS** seria bloqueio, e ele não é pré-requisito do drawer. |
| Estado vazio | **Paridade de intenção, copy diferente.** Vivo `:295`: *"Nenhuma tarefa com esses filtros."* — que é o caso **filtrado**. Contrato: `vazio_primeira` = *"Nenhuma tarefa por aqui"*, o caso **primeira vez**; o protótipo distingue os dois (`:206` traz a frase longa de onboarding citando `essentials.assign_todos`). | **Decidir.** São dois estados, não um: hoje o vivo mostra a frase de filtro mesmo quando a lista nunca teve tarefa. Copy de contrato é soberania de [W]. |
| Marca "Atrasada" | **Ausente.** Contrato: `copy.atrasada` = "Atrasada", e o protótipo marca a linha via `X.atrasada(t)` → `state:"urgent"` (`:131`). No vivo, `grep -n "Atrasada"` em `Todo/Index.tsx` = **0**. | **Decidir.** É sinal derivado de `end_date` vs hoje — dado que a tela já tem (coluna Fim, `:307`). Cabe junto da decisão de colunas acima, no mesmo PR. |
