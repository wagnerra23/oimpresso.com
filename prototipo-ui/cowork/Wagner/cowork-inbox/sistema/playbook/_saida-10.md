---
sessao: "10"
titulo: Guarda de exclusão de usuário — saída
playbook: sistema
thread: "10"
dono: "[CL]"
data: "2026-10-09"
base: wagnerra23/oimpresso.com@main b7715dda8f (lido 2026-10-09)
---

# _saida-10 · Não excluir quem tem venda/OS no nome

## 1 · Feito
- `ManageUserController::destroy()` recusa a exclusão com **422** e `{success: false, msg}` em PT-BR quando o
  usuário tem venda ou OS "no nome", na definição de [W] 2026-10-07 (D-USU-NOME): **criou** a venda/OS, é o
  **vendedor** da venda ou o **comissionado**. A conferência vem antes do `activityLog`/`delete()`.
- Colunas lidas do schema (`database/schema/mysql-schema.sql`), não inventadas:
  `transactions.created_by`, `transactions.res_waiter_id`, `transactions.commission_agent` (só `type = sell`) e
  `repair_job_sheets.created_by` (só quando a tabela existe — módulo Repair).
- Tier 0: as duas contagens filtram pelo `business_id` da sessão; venda de outro negócio que cita o usuário não bloqueia.
- O motivo traz a contagem ("1 venda", "2 vendas", "1 OS") e sugere desativar em vez de excluir.
- UC-USUA-05 no `resources/js/Pages/Usuarios/Index.casos.md`; o item de backlog ficou só com a parte de tela.

## 2 · Prova
- `tests/Feature/Users/UsuariosExclusaoVinculoTest.php` — um caso por vínculo (criador da venda, vendedor,
  comissionado com 2 vendas, criador da OS), controle sem vínculo (exclui) e controle Tier 0 (venda de outro
  negócio não bloqueia). Cada recusa confere status, `success`, o motivo com a contagem e que o usuário segue
  não-excluído.
- Lane `acessos-pest.yml` (MySQL): roda o diretório `tests/Feature/Users/` inteiro, e o
  `ManageUserController.php` já está no gatilho dela. Pest não rodado local (regra do repo); o veredito é o CI.

## 3 · Não feito e por quê
- **A tela trocar o botão pelo motivo** (pedido da thread): o `Index.tsx` está no `nao_toca` deste índice. Hoje a
  tela já mostra o `msg` da recusa no aviso depois do clique (`excluirUsuario` lê o JSON também no 422). Trocar o
  botão antes do clique precisa de prop nova e fica para outra thread.
- A Blade legada (`manage_user`) trata 422 no callback de erro do `$.ajax` e não mostra o motivo; a exclusão
  continua bloqueada lá também.
