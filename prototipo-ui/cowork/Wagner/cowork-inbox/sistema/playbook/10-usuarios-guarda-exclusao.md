---
sessao: "10"
titulo: Guarda de exclusão de usuário
dono: "[CL]"
base: ff43e08461d9
---
# 10 · Não excluir quem tem venda/OS no nome

[W] 07/10: conta como "no nome" **os três** — criou a venda/OS, vendedor da venda e comissionado. `destroy()` recusa (422 com motivo em PT-BR) se qualquer um existir; a tela mostra o motivo no lugar do botão. Teste: um caso por vínculo + controle sem vínculo (exclui). UC novo no `Index.casos.md`. Ler os nomes reais das colunas (`created_by`, `res_waiter_id`/vendedor, `commission_agent`) no schema — não inventar.
