# Writebot — módulo NÃO encontrado neste branch

## Status: AUSENTE

Busca no branch `claude/tests-batch-7-legados` (base `main`) em
2026-04-27 não encontrou:

- diretório `Modules/Writebot` (nem variantes case-insensitive)
- referências `writebot` em `*.php`, `*.json`, `*.md` (`grep -ri "writebot"`)
- entradas em `composer.json`, `package.json`, `module.json` de outros módulos

## Possibilidades

1. **Nunca foi criado neste repo** (escopo planejado e descartado).
2. **Vive em outro branch** que ainda não foi mergeado em `main` ou
   `6.7-bootstrap`.
3. **Confundido com outro nome** (Writesonic? Bot interno? Copiloto?).
   Nenhum match achado.

## Cobertura de testes (batch 7)

Nenhuma. Não há código a testar.

## Recomendação

**DEPRECAR / REMOVER DA LISTA.** Se o módulo não existe na base
canônica, retirá-lo das listas de prioridade evita confusão em batches
futuros. Se há intenção real de criá-lo, abrir uma ADR descrevendo
escopo antes de mexer (per CLAUDE.md §5: "Não crie novas tecnologias
sem registrar uma ADR").

Caso este arquivo se torne irrelevante (ex.: módulo Writebot foi
encontrado em outro branch e mergeado), pode ser removido livremente.
