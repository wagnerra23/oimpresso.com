---
page: /tarefas
component: resources/js/Pages/Tarefas/Index.tsx
related_prototype: n/a (stub visual — cockpit master/detail; ainda não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Tarefas
related_adrs: [39, 114, 101]
tier: C
charter_version: 1
---

# Page Charter — /tarefas (DRAFT · STUB)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. ⚠️ **A tela é um STUB visual** (`MOCK_TASKS: Task[] = []`) — o backend de tarefas (TaskProvider/TaskRegistry, Fase 4 ADR 0039) **ainda não existe**. Este charter documenta a intenção/placeholder, não uma capacidade viva. Wagner decide se vale manter charter de stub ou fechar este PR.
>
> Layout: `tasks.jsx` canon Cowork 2026-04-27 (master/detail interno). ADR 0039 (Cockpit), UI-0008, UI-0011.

---

## Mission

Placeholder da futura Central de Tarefas (cockpit master/detail): lista de tarefas à esquerda, detalhe à direita. Hoje é só o esqueleto visual com dados mock — a intenção é o hub cross-módulo de tarefas quando o TaskProvider/TaskRegistry (Fase 4) existir.

---

## Goals — Features (faz)

- Esqueleto visual master/detail (lista `TasksList` + `TaskCard` + painel de detalhe)
- Layout cockpit canon (UI-0008/UI-0011)

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO tem backend real — `MOCK_TASKS` vazio; nada persiste
- ❌ NÃO cria/edita/conclui tarefa de verdade (sem TaskProvider ainda)
- ❌ NÃO integra com tarefas de outros módulos ainda (é a promessa da Fase 4)
- ❌ NÃO deve ser tratada como capacidade viva em produção

---

## UX targets

- (quando sair de stub) master/detail responsivo, cabe em 1280px
- Placeholder honesto — não simular dados que não existem

---

## Automation hooks (faz)

- Nenhum ainda (stub) — hooks reais chegam com TaskProvider/TaskRegistry (Fase 4)

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO dispara nada (sem backend)
- ❌ NÃO grava nada

---

## Pendências antes de `status: live`

- [ ] Wagner decide: manter charter de stub OU fechar este PR até a Fase 4 existir
- [ ] Backend TaskProvider/TaskRegistry (ADR 0039 Fase 4) implementado
- [ ] Reescrever Mission/Goals/Non-Goals quando a capacidade for real
