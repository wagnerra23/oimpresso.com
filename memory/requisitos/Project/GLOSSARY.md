# Glossário · Project

## Dependency
Relação entre tasks — "task B só pode começar quando A termina" (finish-to-start). Usado no Gantt.

## Gantt chart
Visualização temporal de tasks com barras horizontais + dependências. Ver ADR TECH-0001.

## Milestone
Marco do projeto — task sem duração, só representa data crítica (ex: "entrega final").

## Progress
% conclusão de uma task. Manual ou calculado a partir de sub-tasks.

## Project
Empreendimento com início, fim, responsável e tasks. Vive em `projects`.

## Sprint
Ciclo curto (1-2 semanas) com subset de tasks. Aplicável em projetos ágeis.

## Subtask
Task filha de outra task. Herda escopo, contribui pro progresso do pai.

## Task
Unidade de trabalho dentro de um projeto. Tem assignee, duração, prazo, status, deps.

## Time log
Registro de horas trabalhadas numa task. Append-only (ADR ARQ-0002).

## Watcher
Usuário que recebe notificações de atualizações na task sem ser assignee.
