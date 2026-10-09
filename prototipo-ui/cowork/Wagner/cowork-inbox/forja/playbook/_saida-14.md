---
sessao: "_saida-14"
thread: "14 · Tarefas: transição proibida pelo FSM → 422 (D13)"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main b7715dda8f
---
# _saida-14

## Entregue
Decisão [W] D13 (2026-10-07): *"responder 422"*.

- `TasksAdminController::updateStatus` resolve a task antes de chamar o `TaskCrudService`. Task que não existe → **404** "Task não encontrada." Transição que `McpTask::TRANSITIONS` proíbe (ex.: `todo → done`) → **422** com o motivo em PT-BR: *"Transição não permitida: de todo para done. De todo dá para mover para: doing, blocked, cancelled."*, mais `de`, `para` e `permitidas` no JSON.
- Mesmo status (no-op) segue 200, como o serviço já fazia. Outra recusa de regra de domínio do serviço (ex.: recusa sem motivo, ADR 0368 §5) passa a 422 em vez de 404. Erro de infra não é mais engolido como 404: sobe 500.
- `Index.casos.md` ganhou o **UC-TSK-07**; o "Achado registrado" virou resolvido, com o fato de 2026-10-07 preservado.

## Provas
Teste novo `Modules/Forja/Tests/Feature/TasksFsmTransicaoTest.php` (3 casos, todos citam UC-TSK-07): `todo → done` = 422, a task segue `todo` e nenhum evento nasce; task inexistente = 404; controle `review → done` = 200. Discriminante: no `main`, o primeiro caso dava 404. Entrou na lane MySQL `forja-pest.yml` (allowlist do passo do Pest). Pest local é proibido: o veredito é o CI dessa lane.

## Resíduo — não feito, declarado
**"A tela mostra o motivo"** não foi entregue: `Index.tsx` estava em `nao_toca` desta thread, e hoje ele mostra *"Falha ao atualizar status."* para qualquer resposta que não seja 403, sem ler o `error` do corpo. O backend já entrega o motivo; falta uma thread (ou ampliar o prefixo) para a tela exibi-lo.
