# 08 — Handoff (índice)

> **Este arquivo é índice, não narrativa.** Cada sessão de fechamento cria handoff próprio em `memory/handoffs/` (append-only — nunca editado depois).
>
> **Estado VIVO** (cycle ativo, tasks DOING/REVIEW, métricas, ADRs aceitas) está nas **tools MCP** — chame `brief-fetch` primeiro. Este índice só aponta pra narrativa interpretativa de cada sessão.
>
> Convenção fixada em **[ADR 0130](decisions/0130-handoff-append-only-mcp-first.md)** (2026-05-10).

---

## Últimos handoffs

- [2026-05-12 18:10 — Omnichannel Wave 1+2 paralelização (11 PRs · família ADR 0142 completa)](handoffs/2026-05-12-1810-omnichannel-wave1-wave2-paralelizacao-11-prs.md) (PRs #625/#630/#632/#641/#644/#648/#649/#655/#657/#658/#659 · ADR 0142 aceita · framework SlashCommandParser + 4 slash commands `/lembrar`/`/corrigir`/`/lembrete`/`/config bot=off` · canal=fila ACL `channel_user_access` · Mídia inbound+outbound + Whisper transcrição · notas internas MVP Tier 0 · 6 agents paralelos Wave 1+2 em worktrees isoladas · 4 rebases manuais resolvendo conflitos InboxController+ServiceProvider compartilhados · ~8.000 linhas net · CYCLE-05 WhatsApp 11 US fechadas)
- [2026-05-12 14:30 — **MARCO** FSM Pipeline Canônico LIVE prod biz=1 (50 PRs em ~10h)](handoffs/2026-05-12-1430-fsm-pipeline-canon-live-50prs.md) (ADR 0143 marco · pipeline Sells 11 stages + Repair 13 stages × ~21+15 actions per-business · cancelamento em cascade real NFe SEFAZ + Asaas/Inter refund/cancel + Whatsapp/email + LGPD consent + UI drawer dinâmico + fsm:scan-drift daily 03h BRT · 4 hotfixes prod detectados em <30min: roles suffix #{biz}, observer recursion, Eloquent property dinâmica vira coluna SQL, action_id nullable · proibicoes.md ganhou 8 regras novas §FSM Pipeline · paralelização 3 waves × 4-5 agents validada)
- [2026-05-12 08:48 — JANA Pro Brief Diário FUNCIONAL em prod (US-COPI-203 completa)](handoffs/2026-05-12-0848-jana-pro-brief-funcional-prod.md) (5 PRs #608/#609/#611/#612/#616 · OpenAI gpt-4o-mini ligado · BriefDiarioChatTrigger 9 regex unicode · ReactMarkdown+remarkGfm pra GFM tables · TypingIndicator durante isRunning · 2 bugs URL legacy /copiloto descobertos em prod e fixados · Wagner aprovou screenshot "ficou ótimo")
- [2026-05-11 22:30 — JANA Pro Sprint A foundation + modo Concierge MVP + pegadinha junction NTFS](handoffs/2026-05-11-2230-jana-pro-foundation-concierge-pegadinha.md) (5 PRs #597/#598/#600/#602/#603 · ADR 0140 JANA Pro SaaS + ADR 0141 Camada B v2 estilo Claude Code · caminho B Concierge MVP escolhido · pegadinha junction NTFS proibida Tier 0 · OPENAI_API_KEY rotação pendente)
- [2026-05-11 20:40 — Design fix v2 + Pest mock smoke + cleanup merges paralelos](handoffs/2026-05-11-2040-design-fix-v2-mock-smoke-merge-cleanup.md) (3 PRs #583/#585/#586 · lição: revisão visual em wide screen pega o que design-critique isolado não vê · mock CnabDirectStrategy provado funcional · dívida SCOPE.md OficinaAuto/Vestuario detectada e task spawned)
- [2026-05-11 19:55 — Financeiro sidebar/topnav canônico + design ConfigurarBoletoSheet + tentativa Inter API](handoffs/2026-05-11-1955-financeiro-sidebar-topnav-design-boleto-inter.md) (4 PRs #565/#568→reverted/#569/#579 · lição: AppShellV2 já tem topnav nativo via topnav.php · Inter API bloqueado lado Inter "Aplicações não existe")
- [2026-05-11 19:45 — Fix drift ADS Brain B (site nunca quebrou; 7 colunas restauradas)](handoffs/2026-05-11-1945-ads-dual-brain-drift-fix.md) (2 PRs #574/#576 · migration idempotente · Brain B autônomo restaurado · pattern reusável de drift fix)
- [2026-05-11 18:30 — Paralelização Omnichannel frustrada (subagents mortos com worktree)](handoffs/2026-05-11-1830-paralelizacao-omnichannel-frustrada.md) (1 PR #551 SPEC · US-WA-058..061 cadastradas · lição: não spawn agents de worktree filha)
- [2026-05-11 17:30 — Sells Grade Avançada + Modules/OficinaAuto qualificada + 5 agents paralelos](handoffs/2026-05-11-1730-sells-grade-oficinaauto-paralelizacao.md) (11 PRs · ADR 0136 + 0137 · 5 agents paralelos · pivot estratégico OfficeImpresso legacy)
- [2026-05-10 23:40 — Audit adversarial pós-Langfuse + Runbooks infra canônicos](handoffs/2026-05-10-2340-audit-adversarial-runbooks-infra.md) (3 PRs + 4 ADRs + 7 tasks MCP) — modo especialista SRE adversarial

> Handoffs anteriores a 2026-05-10 22:30 viviam em `memory/sessions/` (era pré-ADR-0130). Consultar `ls -t memory/sessions/ | head -10` pra histórico narrativo.

---

## Como retomar uma sessão

1. **`brief-fetch`** (Tier A always-on — Skill `brief-first`) → estado consolidado ~3k tokens
2. **`my-work`** → tasks DOING/REVIEW reais do owner autenticado
3. **`my-inbox`** → mentions/assignments pendentes
4. Ler **handoff mais recente** acima
5. (se suspeita de sessão paralela ativa) **`whats-active`** ([ADR 0119](decisions/0119-paralelismo-sessoes-whats-active-tier-1.md))
6. Skill **`continuar`** automatiza esses 5 passos + pede confirmação antes de agir

---

## Como fechar uma sessão (ANTES de criar handoff novo)

1. **MCP-first OBRIGATÓRIO** — chamar nessa ordem e capturar resultado:
   - `cycles-active` (cycle + goals + drift)
   - `my-work` (tasks reais)
   - `sessions-recent limit:3` (handoffs/sessions irmãs)
   - `decisions-search since:<data-último-handoff>` (ADRs aceitas no intervalo)
2. Criar **arquivo novo** em `memory/handoffs/YYYY-MM-DD-HHMM-<slug-kebab>.md`
   - Slug curto descritivo: `cycle-higiene-pivot-fsm`, `us-sell-011-fsm-tabelas`, etc
   - Incluir seção `## Estado MCP no momento do fechamento` com snapshot do passo 1 (prova de consulta)
3. Adicionar linha no topo da lista "Últimos handoffs" acima — apontando pro novo arquivo
4. **NUNCA editar handoffs antigos** (append-only enforced culturalmente; hook P2 dormente — ativa se houver reincidência)

Detalhes da skill em [`.claude/skills/memory-sync/SKILL.md`](../.claude/skills/memory-sync/SKILL.md). Detalhes do protocolo em [`memory/how-trabalhar.md`](how-trabalhar.md) §"Ao terminar uma sessão".

---

**Última atualização do índice:** 2026-05-12 08:48 — handoff JANA Pro Brief Diário FUNCIONAL em prod (US-COPI-203 completa) adicionado.
