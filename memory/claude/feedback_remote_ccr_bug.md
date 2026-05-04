---
name: Remote CCR pode falhar silencioso — preferir local quando Wagner está ativo
description: 2026-04-25 trigger remoto Anthropic CCR teve action=run com HTTP 200 mas boot nunca aconteceu; nada gastou em tokens, branch nunca apareceu no GitHub
type: feedback
originSessionId: 3d07367c-170f-4f24-a317-c57ccf4fe557
---
# Remote CCR (Claude Code routine) — modo de falha silencioso

**Regra:** se o usuário está acompanhando ao vivo e o remote CCR não mostra progresso em ~3 minutos (branch não aparece no GitHub, painel em branco), **assumir que o boot falhou** e oferecer rollback pra execução local. Não insistir em retentativas além de uma.

**Why:** 2026-04-25 — Wagner agendou agente remoto pro upgrade Inertia v3. `RemoteTrigger action=run` retornou HTTP 200, painel ficou em branco, polling 5 min no GitHub não mostrou branch. Re-run idem. Cancelado, executado local em ~30 min — funcionou de primeira. Custo do remoto: zero tokens (não bootou) — mas custo de oportunidade alto (frustração + tempo perdido).

**How to apply:**
- Para tarefas urgentes ou com Wagner online: priorizar execução local quando dá pra fazer ali (caso do upgrade Inertia, que era ~30 min).
- Para overnight / "rode amanhã às 6h": remote CCR ainda vale, mas avisar que boot pode falhar e ele precisa checar de manhã.
- Sintomas de boot falhado: branch nova nunca aparece no GitHub do repo configurado dentro de 3-5 min do `action=run` (ou do `run_once_at`).
- Não tentar mais de uma re-run; se a primeira falhou silenciosa, pular pra rollback local sem queimar tempo.
