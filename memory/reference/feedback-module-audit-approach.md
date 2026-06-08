---
name: Auditoria de completude de módulo — skill module-completeness-audit
description: Skill module-completeness-audit v0.1.0 Tier B advise (8 dims). Complementa /comparativo (gaps mercado). Hook bloqueador é P1 quando ROI provado.
type: feedback
---
Wagner aprovou approach combinado **(1) rodar `/comparativo <Mod>` quando precisa + (3) criar skill nova `module-completeness-audit` Tier B** pra automatizar detecção de gaps de completude em módulos do oimpresso. Recusou (2) cron mensal — ROI baixo até ter histórico do (1).

**Skill — checklist 8-10 dimensões que toda US precisa cobrir antes de `status:review→done`:**
- Multi-instance scope (1 entidade por business OU N por business?)
- Permissions: middleware `can:*` existe E tem UI dedicada (em `/admin/roles` OU dentro do módulo)
- Charter `*.charter.md` ao lado do `.tsx`
- RUNBOOK em `memory/requisitos/<Mod>/`
- Pest cobre golden path + edge case
- AuditLog write em mutações
- Multi-tenant `business_id` global scope
- Browser MCP smoke salvo

Auto-trigger antes de `tasks-update status:review→done`. Bloqueia se faltar item.

**Why:** Wagner detectou 2 gaps em `/whatsapp/settings` em prod (falta scope per-phone-number + falta UI permissões dedicada do módulo) que `US-WA-040 [doing, sprint 4]` cobre só parcialmente. `/comparativo Whatsapp` teria flageado isso sozinho — mas só roda manual hoje. Skill `module-completeness-audit` fecharia o loop "métrica" da Constituição v2 §4 (loop fechado por métrica).

**How to apply:** Quando Wagner perguntar de novo "como automatizar verificações", "como garantir que módulo está completo", "auditoria automática de módulo", lembrar:
- (a) `/comparativo <Mod>` já existe e detecta gaps vs Capterra/mercado;
- (b) skill nova `module-completeness-audit` Tier B é o caminho aprovado — gaps internos de governança (rota tem `can:*` mas sem tela de gestão);
- (c) começar pelo (1)+(3), pular cron mensal por enquanto.

**Não esquecer:** Wagner explicitamente pediu pra anotar essa decisão (2026-05-10) pra futuro me-memorar. Antes de propor outra coisa, conferir se ele já validou esse approach.

**Status (2026-05-10):** Skill `module-completeness-audit` v0.1.0 CRIADA em `.claude/skills/module-completeness-audit/SKILL.md` — Tier B, 8 dimensões. É **advise** (não bloqueia técnico). Hook bloqueador `block-incomplete-us-close.ps1` é P1, criar quando `metrics.audits_run >= 5` + `gaps_fixed/detected > 0.6`. Próxima vez que Wagner perguntar "automatizar verificação completude", responder "já tem skill, basta usar `/module-audit {Mod}`".

**Audits rodados 2026-05-10:** Whatsapp (PR #497, +12 US WA-041..052), NfeBrasil + RecurringBilling + Jana (PR #498 skill + audit blocks; PR #500 recriou 9 US-fix), Charters NFe (PR #499 entregou US-NFE-061). Total 21 US-fix em backlog.

**Pegadinha crítica (registrada após bug 2026-05-10):** A tool MCP `tasks-create` **NÃO escreve direto no SPEC.md** quando o servidor não tem write access ao working tree local. Ela retorna o markdown pra colar manualmente — e diz "criada e adicionada" mesmo assim, o que enganou esta sessão. O webhook GitHub→MCP sincroniza SPEC.md → DB e **sobrescreve o cache temporário do MCP**, fazendo as US "criadas" sumirem. Bug que custou um PR adicional (#500) pra recriar 9 US perdidas.

**Como evitar:** Após cada chamada `tasks-create`, **sempre** rodar `git status` ou `git diff memory/requisitos/<Mod>/SPEC.md` pra confirmar que o arquivo foi modificado. Se NÃO foi (working tree limpo), apender manualmente o markdown retornado via Edit/Write antes de seguir. Skill `module-completeness-audit` deve incluir este check no workflow obrigatório (passo 8.5: confirmar git diff após tasks-create).
