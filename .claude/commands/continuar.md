---
description: Retomar a sessão de onde parou — chama tools MCP (cycles-active + my-work) + lê handoff e último session log, abre os arquivos do próximo passo, mostra resumo curto e pede confirmação antes de agir.
---

Você está retomando uma sessão anterior. Siga estes passos exatamente, **sem desviar**:

## 1. Lê o estado vivo (nessa ordem)

Não pula. Não re-explora o resto do repo nessa fase.

1. **Tool MCP** `cycles-active` — cycle vigente + goals + métricas
2. **Tool MCP** `my-work` — minhas tasks ativas (status: doing/review) + due_date
3. **Tool MCP** `my-inbox` — @mentions / assignments / review requests pendentes
4. @memory/08-handoff.md — handoff narrativo (estado canônico mais recente)
5. O **último** arquivo em `memory/sessions/` — pega o nome via `ls -t memory/sessions/ | head -1` e abre

> ⚠️ **CURRENT.md/TASKS.md foram REMOVIDOS em 2026-05-04** ([ADR 0070](memory/decisions/0070-jira-style-task-management-current-md-removed.md)). Estado vivo é 100% via tools MCP.

## 2. Abre o que importa pra retomar

Olhando a task ativa do `my-work` ou o "próximo passo" do handoff, abre **só** os arquivos diretamente envolvidos. Tipicamente:

- 1-3 arquivos de código que estavam sendo trabalhados (controller / service / page React)
- 1 ADR ou SPEC se a tarefa referenciar uma (use `decisions-fetch` ou `tasks-detail`)

Não abre o repo inteiro. Não roda `find` / `glob` exploratório. Não relê ADRs já citados no handoff.

## 3. Resume em 3-5 linhas

Texto curto pro Wagner, em PT-BR:

- 1 linha: o que estava sendo feito (cycle + task ID + branch)
- 1 linha: estado real (código pronto / aguardando validação / bloqueado)
- 1 linha: próximo passo concreto
- 1 linha (opcional): se houver bloqueio ou ambiguidade que precisa ser resolvida antes de continuar

## 4. PEDE confirmação antes de agir

Termina perguntando: **"Confirma que retomo daqui? Ou tem mudança de escopo desde o último handoff?"**

**NÃO faça nada destes:**
- Re-explorar o codebase com Glob / Grep / Agent além do que está nas etapas 1 e 2.
- Refazer trabalho que o handoff diz "completed" ou tarefa já está `status:done` no MCP.
- Mudar escopo, sugerir refactor adjacente, ou empilhar trabalho extra.
- Commitar, fazer push, abrir PR, ou rodar `npm run build` / migrations sem o Wagner pedir.
- Atualizar handoff/tasks ainda — espera o trabalho da sessão acontecer primeiro.

Aguarda a resposta dele antes de tocar em qualquer arquivo.
