---
date: "2026-07-22"
hour: "13:40 BRT"
duration: "1h"
topic: "Construção do adversário read-only para planos de classificação, movimento e relink documental"
authors: [W, C]
outcomes:
  - "Validador determinístico e agente semântico read-only implementados."
  - "Quinze contraprovas bite/release e smoke real executados sem mover documentos."
  - "PR #4675 aberta sem merge automático."
prs: [4675]
us: []
related_adrs:
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
  - "0314-poda-gates-onda-2-lei-fusoes"
---

# Sessão — adversário da realocação documental

**Data:** 2026-07-22
**Objetivo:** construir um adversário que valide planos de classificação, movimento e relink antes de qualquer alteração física na documentação.

## TL;DR

Foi construído e publicado na PR #4675 o adversário read-only anterior ao executor documental. Ele passou 15/15 contraprovas e um smoke real sem mover arquivos; classificação e execução permanecem entregas futuras separadas.

## O que foi feito

1. Reutilizado o padrão existente de adversário com controle negativo que realmente morde, sem criar novo baseline ou gate required.
2. Criado o validador Node puro `document-relocation-adversary.mjs`, read-only e pinado ao SHA da classificação.
3. Criado o agente semântico `document-relocation-adversary`, também read-only.
4. Registrado o selftest no workflow advisory já existente de scripts de governança e adicionados comandos npm de uso explícito.
5. Corrigidos durante a própria contraprova dois pontos cegos encontrados no smoke:
   - code-spans em `memory/**` frequentemente apontam para paths da raiz, não relativos ao arquivo;
   - mover um documento quebra também os links relativos que saem dele, não só backlinks de entrada.

## Verificação

- 15/15 canários bite/release.
- Smoke real de CLI aprovado sem executar movimento.
- `memory-health` 0 fail; 38/38 testes do memory-health.
- ciclo documental e registry de selftests verdes; 0 órfãos.

## Estado final

O adversário está implementado. A máquina classificadora e o executor transacional ainda não existem; nenhum documento foi realocado nesta sessão.

Handoff: [`memory/handoffs/2026-07-22-1340-adversario-realocacao-documental.md`](../handoffs/2026-07-22-1340-adversario-realocacao-documental.md)
