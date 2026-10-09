---
sessao: "_saida-08"
thread: "08 · Defeitos achados pelos scorecards da 04"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
entregue_em: "PR-a (este PR) · PR-b a seguir"
base_lida: wagnerra23/oimpresso.com@main b7715dda8f
---
# _saida-08

## PR-a · Tier 0, autoria falsa na trilha (entregue neste PR)
Reli as duas linhas antes de mexer. Elas batiam com o achado da `_saida-04`:
`Tasks/Index.tsx` mandava `author: 'wagner'` no PATCH de status, e
`TasksAdminController@updateStatus` gravava `$request->input('author', 'wagner')`.

- **Controller:** o autor passa a ser o usuário logado (`username`, ou `user#<id>` se vazio,
  cortado em 60, a largura de `mcp_task_events.author`). O `author` do body é ignorado.
- **Tela:** o PATCH manda só `{ status }`.
- **Teste:** `Modules/Forja/Tests/Feature/ForjaGapsScorecardTasksAutorTest.php`, 3 casos citando
  **UC-TSK-07** (novo em `Tasks/Index.casos.md`): usuário B move a tarefa com o autor de A
  forjado no body e o evento sai com autor B; sem `author`, o evento não cai em `wagner`; a
  tela não manda mais autor fixo. Os dois primeiros têm âncora positiva (o evento existe), para
  não passarem por vácuo.
- **Lane:** entrou na lista do `forja-pest.yml` (MySQL). Pest local é proibido. A prova é o run
  dessa lane no PR, não uma execução minha.

## PR-b · estados de erro/vazio (não entregue neste PR)
Team `r.ok` nos 4 fetch · CcSessions "Limpar" zera `from`/`to` · Scorecard com 0 checks e
erro do defer. Fica para um PR separado, como a ficha pede (2 PRs).

## O que não está provado
- Ninguém viu o caso (a) vermelho no `main`. Pela leitura, ele cairia: o código antigo gravava
  o autor do body.
- Eventos antigos em `mcp_task_events` continuam com autor `wagner`. A tabela é append-only
  e este PR não os corrige.
