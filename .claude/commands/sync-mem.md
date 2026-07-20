---
description: Commita+pushea arquivos pendentes em memory/ e governança (MEMORY/TEAM/CLAUDE/DESIGN/INFRA/MANUAL_CLAUDE_CODE/HOW_TO_ASK_CLAUDE) pra propagar pro MCP server via webhook GitHub. Uso quando criar/alterar SPEC.md, ADR, session log, ou qualquer doc canônico. Tasks → tools MCP `tasks-*` (ADR 0070), nunca markdown.
---

# /sync-mem — propaga memória pro team via MCP

**O que faz:**
1. `git status` filtrado pra `memory/` + arquivos canônicos da raiz
2. Se nada pendente → mensagem "tudo sincronizado, nada a fazer"
3. Se houver pendências:
   - Mostra a lista pro usuário (qtd + paths)
   - Pergunta título do commit (1 linha) com sugestão automática baseada no diff
   - `git add` só dos paths memória/governança (NUNCA `git add -A`)
   - Cria commit com Co-Authored-By: Claude Opus 4.7
   - `git push origin main`
   - Aguarda ~5s e confirma que webhook propagou (opcional: rodar `decisions-search` pra ver doc novo)

**Paths considerados memória/governança:**
- `memory/**` (recursivo)
- Arquivos raiz: `MEMORY.md`, `TEAM.md`, `CLAUDE.md`, `DESIGN.md`, `INFRA.md`, `MANUAL_CLAUDE_CODE.md`, `CLAUDE_DESIGN.md`, `HOW_TO_ASK_CLAUDE.md`
- ⚠️ `CURRENT.md`/`TASKS.md` REMOVIDOS em 2026-05-04 (ADR 0070) — não devem ser recriados; tasks vão pra tools MCP `tasks-*`/`cycles-*`

**NÃO inclui:** código (`Modules/`, `app/`, `resources/`), build assets (`public/`), composer/package locks. Estes seguem fluxo PR normal.

**Quando rodar:**
- Após criar SPEC/ADR/session log
- Após salvar comparativo competitivo, runbook, audit
- Quando hook `memory-pending.mjs` avisar no fim de turno
- Antes de chamar a outra Claude/agente que vai ler via MCP (Eliana/Felipe)

**Comportamento:**
1. Rode `git status --porcelain` filtrado pelos paths acima
2. Se vazio: "✅ memória já sincronizada"
3. Caso contrário, gere título do commit baseado nos arquivos novos/modificados:
   - `memory/decisions/NNNN-*.md` novo → `docs(adr): NNNN <slug>`
   - `memory/sessions/YYYY-MM-DD-*.md` novo → `docs(session): YYYY-MM-DD <slug>`
   - `memory/requisitos/<Mod>/SPEC.md` modificado → `docs(<mod>): atualizar SPEC`
   - Múltiplos → `docs: <descrição agregada>`
4. Mostre o título sugerido + lista de arquivos
5. Se aprovado pelo usuário (ou autoaprova se rodando autônomo): `git add <paths> && git commit -m "..." && git push origin main`
6. Confirme com `git log -1 --oneline` o hash novo
7. Mensagem final: "✅ <hash> propagado. Webhook GitHub→MCP em <60s. Time já enxerga via tools MCP."

**Erros possíveis:**
- Push rejeitado (out of sync): rode `git pull --rebase origin main` antes
- Pre-commit hook falha (gitleaks etc): pare, mostre erro, não force `--no-verify`
- Conflito: pare, peça ao usuário pra resolver

**NUNCA:**
- Usar `git add -A` ou `git add .` (pode pegar código não relacionado)
- Usar `--no-verify` ou skipar hooks (gitleaks protege segredos)
- Force push
- Commitar `Modules/Copiloto/Entities/MemoriaFato.php` ou outros arquivos código não-mem
- Recriar `CURRENT.md`/`TASKS.md` (foram removidos pelo ADR 0070)
