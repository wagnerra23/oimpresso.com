# Runbook · Project

## Problema: Gantt não atualiza após mudar task

**Sintoma**: Dev muda duração/dependência, Gantt continua mostrando antigo.

**Causa**: Cache do GanttService.

**Correção**:
```bash
php artisan cache:forget "gantt.project.{$projectId}"
# Ou forçar recompute
php artisan tinker
>>> app(GanttService::class)->compute($projectId)
```

## Problema: Time log não pode ser deletado

**Sintoma**: Usuário errou horário, tenta deletar, recebe erro.

**Causa**: Append-only (ADR ARQ-0002).

**Correção**: criar novo log tipo `correction` com nova marcação. UI deve oferecer "Corrigir" em vez de "Deletar".

## Problema: Progresso do parent não reflete filhos

**Sintoma**: Todos 5 sub-tasks marcados 100%, parent ainda mostra 80%.

**Causa**: Trigger de recálculo não disparou.

**Correção**: `php artisan project:recalc-progress --project={$id}`.

## Comandos úteis

```bash
# Recalcular Gantt de um projeto
php artisan project:gantt-recompute --project=42

# Audit Project
php artisan docvault:audit-module Project --save
```
