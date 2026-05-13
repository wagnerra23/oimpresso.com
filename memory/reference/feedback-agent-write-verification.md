---
name: Agent pode alucinar Write/Edit — sempre verificar com ls
description: Subagent reporta ter criado/editado arquivo mas o disk não tem. Verificar `ls -la <path>` + `wc -l` antes de confiar. Descoberto 2026-05-13.
type: feedback
---
## A regra

Quando spawnar `Agent(subagent_type: ...)` com prompt pedindo Write/Edit de arquivos, **incluir no prompt instrução irrevogável**:

> "APÓS chamar Write, IMEDIATAMENTE rode `Bash: ls -la <path-EXATO> && wc -l <path>` pra CONFIRMAR que o arquivo existe no disk com tamanho >XKB e >N linhas. Se ls falhar, o Write falhou — refaça. Inclua o output dessa confirmação no seu reporte final como prova."

**Why:** subagents podem reportar sucesso enganoso quando Write retorna error silencioso ou path divergiu. Sem prova de `ls`, parent agent não tem como detectar.

**How to apply:**
- Toda missão de agent que envolve criar/editar arquivos canônicos (artefatos `memory/`, ADRs, código)
- Especialmente quando o trabalho é longo (>10min) — agent pode confundir intent com execução real
- Quando o parent vai consumir o output downstream (PR, commit), erro de alucinação custa tempo dobrado
- Independente do modelo (Sonnet/Opus) — observação foi com Opus 4.7 também

## Caso real 2026-05-13 (sessão `nervous-mayer-3ff0da`)

`knowledge-architecture-expert` 1ª tentativa reportou:

> "**5. Caminho do artefato:** `memory/requisitos/Jana/AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13.md`"

Mas `find -newer .claude/agents/mcp-quality-expert.md` mostrou só 1 arquivo criado (o outro agent). Re-spawn com instrução irrevogável + prova obrigatória no reporte: **2ª tentativa entregou arquivo real (26KB, 349 linhas, ls + wc no reporte).**

Tempo perdido na 1ª tentativa: ~7min + 124k tokens.

## Mitigação geral

- Em prompts de agent → sempre exigir verificação no final
- Pós-reporte: parent valida via `Bash` antes de confiar
- Quando suspeitar (relato muito sucinto, sem detalhe de quanto escreveu) → `Bash: ls -la <path>` antes de seguir
