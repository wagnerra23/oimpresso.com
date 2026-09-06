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
> (medido: os 31 contratos ativos incluem `essentials-tipos`, `essentials-licencas` e
> `essentials-metas` — nenhum dos 5 dos essenciais). Ele é **proposta de contrato**: descreve a
> copy literal pretendida e serve de âncora para esta comparação, mas **não trava merge hoje**.
> Por isso as divergências de copy abaixo saem como `Decidir.`, nunca como "quebra de gate".

| Parte | Estado no vivo | Ação |
|---|---|---|
| Toolbar de filtros | **Paridade de capacidade.** Vivo `Todo/Index.tsx:219-224` (cabeçalho "Filtros" + botão "Limpar") e `:228-...` com 5 selects em grid: Status (`:233`), Prioridade (`:245`), Atribuído a (`:258`). Protótipo: `hrm-toolbar` em `:165`, com Situação (`:176-177`) e o botão "Adicionar" (`:180`). | Nada — paridade. |
| Filtro por período de início | **Ausente.** Protótipo: 2ª toolbar `ess-toolbar2` em `:182`, rótulo *"Período de início"* (`:183`). No vivo, os 5 filtros são de enum/pessoa; os termos `date_from`, `date_to` e `periodo` em `Todo/Index.tsx` = **0 de cada**. | **Decidir.** É **comportamento** (filtrar), logo vira pedido pela régua do playbook (`08-feriados-puxar.md` §3). O contrato lista `filtros-periodo` como seção própria. Custo: 2 campos + range no `EssentialsTodoController` — não é de passagem. |
| Colunas da tabela | **Diverge: 8 no vivo × 9 no contrato, e 2 divergem só no rótulo.** Vivo `:302-309`: Código · Tarefa · Status · Prioridade · Início · Fim · Atribuído a · Ações. Contrato/protótipo (`:119-128`): Criado em · ID da tarefa · Tarefa · Situação · Início · Fim · Horas est. · Atribuído por · Atribuído a. **Renomeações** (não ausências — §5 2026-07-15): `ID da tarefa`→`Código`, `Situação`→`Status`. **Ausentes de fato:** `Criado em` · `Horas est.` · `Atribuído por`. **Só no vivo:** `Prioridade` · `Ações`. | **Decidir.** ⚠️ O dado dos 3 ausentes **já chega no payload**: `Todo/Index.tsx:65` (`estimated_hours`), `:66` (`assigned_by`), `:68` (`created_at_human`). Varredura contada: `estimated_hours` e `assigned_by` aparecem **só na interface TypeScript, em nenhum ponto do JSX** (2 hits cada, ambos em `:65-66`); `created_at_human` é renderizado, mas como sub-linha *"criada {…}"* (`:320-322`), não como coluna. Ou seja: é **decisão de exibição**, não capacidade faltando — barato, e por isso mesmo precisa de [W] decidir a forma (coluna × sub-linha) antes de mexer. |
| Seleção em lote (bulkbar) | **Ausente.** Protótipo: `Bulk n={selecionadas.length} rotulo="tarefas selecionadas"` (`:214`) com ações Concluir/Excluir (`:218`), alimentado por `selLinhas` (`:45`). Varredura contada no vivo: os termos `Checkbox` e `onCheckedChange` em `Todo/Index.tsx` = **0 ocorrência de cada um**. | **Decidir.** Comportamento, e o contrato tem `bulkbar` como seção. Mas é **mutação em lote** — precisa da regra de permissão (`essentials.assign_todos`) e de teste antes da UI. Decisão de [W] sobre escopo, não sobre desejo. |
| Modal de mudança de situação | **Paridade.** Vivo `:424-431`: `<Dialog>` "Atualizar status" citando a tarefa (`:430`). Protótipo: `X.ModalStatus` em `:157` e `:225`. | Nada — paridade. |
| Drawer de detalhe | **Ausente como drawer.** Protótipo: `DetalheTarefa` (`:232-281`) com histórico, comentários, documentos da tarefa. No vivo há `Dialog`/`AlertDialog` (`:27-45`) para status e exclusão, mas nenhum painel de detalhe. | **Decidir.** O charter do intake liga o detalhe aos 3 itens **fora de escopo** que aguardam [W] (comentário, documentos da tarefa, vínculo com OS). Abrir o drawer sem eles entrega uma casca — e inventar o vínculo é o que o charter proíbe. |
| Estado vazio | **Paridade de intenção, copy diferente.** Vivo `:295`: *"Nenhuma tarefa com esses filtros."* — que é o caso **filtrado**. Contrato: `vazio_primeira` = *"Nenhuma tarefa por aqui"*, o caso **primeira vez**; o protótipo distingue os dois (`:174` traz a frase longa de onboarding citando `essentials.assign_todos`). | **Decidir.** São dois estados, não um: hoje o vivo mostra a frase de filtro mesmo quando a lista nunca teve tarefa. Copy de contrato é soberania de [W]. |
| Marca "Atrasada" | **Ausente.** Contrato: `copy.atrasada` = "Atrasada", e o protótipo marca a linha via `X.atrasada(t)` → `state:"urgent"` (`:131`). No vivo, `grep -n "Atrasada"` em `Todo/Index.tsx` = **0**. | **Decidir.** É sinal derivado de `end_date` vs hoje — dado que a tela já tem (coluna Fim, `:307`). Cabe junto da decisão de colunas acima, no mesmo PR. |
