---
sessao: "08"
titulo: Defeitos achados pelos scorecards da 04
dono: "[CL]"
base: 822ccf022258
origem: _saida-04.md §Achados
---
# 08 · Quatro defeitos, dois PRs

Achados por leitura de código no `_saida-04` — **releia cada linha antes de mexer**.

**PR-a · Tier 0 (autoria falsa na trilha)**
- `Tasks/Index.tsx:223` manda `author: 'wagner'` fixo; `TasksAdminController.php:168` grava o que vier no body → todo movimento aparece como do Wagner em `mcp_task_events`.
- Conserto: o controller usa o usuário autenticado e **ignora** `author` do body; o front para de mandar.
- Teste: usuário B move tarefa → evento com autor B; body com `author` forjado → ignorado.

**PR-b · estados de erro/vazio**
- Team: os 4 `fetch` checam `r.ok`; 403/419/500 com mensagem própria, não "Erro de rede".
- CcSessions: "Limpar" zera `from`/`to`.
- Scorecard: `checks = []` → estado vazio (não "0 de 0 falhando"); falha do defer sai de "Carregando…" para erro.

Recibo: re-rodar a nota das 4 telas não é exigido; citar o UC de cada caso.
