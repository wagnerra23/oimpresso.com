---
name: Cursor — outra IA que Wagner usa em paralelo
description: Cursor também atua neste repo e deixa suas sessões documentadas em memory/sessions/NN.md; integrar com nossa memória
type: reference
---
Wagner alterna entre Claude Code (este agente) e Cursor como IDE+IA. Cursor escreve/modifica código e documenta cada sessão em `memory/sessions/YYYY-MM-DD-session-NN.md` dentro do repo.

## Onde procurar o trabalho do Cursor

- `memory/sessions/*.md` — cada arquivo é uma sessão: contexto, decisões, o que foi feito, verificações, pendências
- `memory/08-handoff.md` — Cursor também atualiza handoff geral

## Padrão observado

- Cursor tende a deixar **mudanças uncommitted** ao fim de uma sessão (session 11+12 de 2026-04-23 deixaram o upgrade L10→L11 aplicado mas não commitado)
- Antes de começar trabalho técnico no worktree principal (D:/oimpresso.com), rodar `git status` e `git log`/`git diff` pra detectar trabalho em andamento do Cursor
- Session memories são detalhadas o suficiente pra reconstituir o raciocínio — ler antes de tocar nos mesmos arquivos

## Como colaborar

- Commitar trabalho pendente do Cursor quando estiver testado/verde (já fiz em aeb2179f pra L11 upgrade)
- Continuar o padrão de documentar sessões relevantes em `memory/sessions/` quando fizer mudanças grandes que beneficiam o próximo agente (seja humano, Claude ou Cursor)
- **Não competir**: se Cursor começou algo específico, respeitar a direção já tomada — mas atenção: Vizra ADK foi REJEITADA (ADR 0048); se Cursor referenciar Vizra/Prisma como motor de IA, isso é stale — stack canônica atual é `laravel/ai` + Agents próprios (ADR 0035)
