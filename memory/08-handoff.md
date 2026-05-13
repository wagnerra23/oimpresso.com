# 08 — Handoff (índice)

> **Este arquivo é índice, não narrativa.** Cada sessão de fechamento cria handoff próprio em `memory/handoffs/` (append-only — nunca editado depois).
>
> **Estado VIVO** (cycle ativo, tasks DOING/REVIEW, métricas, ADRs aceitas) está nas **tools MCP** — chame `brief-fetch` primeiro. Este índice só aponta pra narrativa interpretativa de cada sessão.
>
> Convenção fixada em **[ADR 0130](decisions/0130-handoff-append-only-mcp-first.md)** (2026-05-10).

---

## Últimos handoffs

- [2026-05-13 17:50 — ComVis revert + brief-fetch hook + disciplina TETO/ADR 0105](handoffs/2026-05-13-1750-comvis-revert-brief-hook-disciplina-teto.md) (PRs #804 closed sem merge / #805 closed wrong-base / **#806 aberto aguardando review** · 4 US ComVis-001/002/006/009 revertidas review→cancelled+feature-wish via tasks-update MCP · auto-config sistêmica brief-fetch-curl.ps1 no SessionStart pra worktrees filhos · sessão começou pulando brief Tier A → violação Tier 0 catalogada → snapshot Gold revelou sistema parado 10/03 → Wagner cortou implementação Fase 2 → reverteu tudo + criou hook proteção · 0 PRs merged, 2 closed disciplina, 1 aberto auto-config · lição: brief-fetch é literalmente o primeiro passo, sem ele ressuscita feature-wish)
- [2026-05-13 11:30 — Sessão recorde 30 PRs · 70% → ~98% maturidade · TETO atingido](handoffs/2026-05-13-1130-sessao-recorde-30prs-98pct-teto.md) (PRs #745-#795 em 5 ondas · 28 agents Opus paralelos · 117/117 Pest passed · pattern `/audit-and-fix` 3-tier criado (junior research + sênior dossier + junior implement) · 4 auditorias canônicas + 1 dossier executável · TETO pragmático 97-98% atingido em 1 dia · Onda 6 bloqueada via ADR 0105 até cliente reportar dor · custo IA adicional ~R$ [redacted Tier 0]/mês · 3 surpresas mundo-classe mantidas (Constituição v2 + Brief 6×/dia + `/audit-and-fix`) · evolucao-memoria-2026-05-13 documenta motivos)
- [2026-05-13 09:40 — Módulos não instaláveis (cascata Auditoria 5 PRs em 1h25)](handoffs/2026-05-13-0940-modulos-cascata-auditoria-instalacao.md) (PRs #750/#751/#752/#756/#760/#762 · 6 módulos diagnosticados em /manage-modules · kebab `moduleSystemKey` fix global + Pest convention test + 4 bugs latentes Auditoria expostos ao habilitar · skill `criar-modulo` ganhou pegadinha #8 "ativar e fumigar antes do merge" · 5 agents paralelos PR #750 + pattern "Disabled módulo = bugs invisíveis no CI" catalogado)
- [2026-05-13 09:00 — 5 PRs MCP bugs + 3 auditorias estado-da-arte + Onda 2 spawned (formato curto experimental ~80 lin)](handoffs/2026-05-13-0900-mcp-bugs-5prs-3auditorias-onda2-spawned.md) (PRs #745/#746/#747/#748/#749 · 4 bugs sync fechados + ADR 0144 accepted · 3 artefatos canônicos: COMPARATIVO-MCP 62% · AUDITORIA-KNOWLEDGE 73% · AUDITORIA-SESSION-HANDOFF 74% · score weighted 70% global · 6 agents Opus paralelos · lição alucinação Write → exigir `ls -la` no reporte · 5 Onda 2 agents spawned: G1 migrar 53 auto-mem, G2 INDEX.md navegável, G3 tool kb-answer, G4 tool handoff-fetch-summarized, G5 colapsar sessions/)
- [2026-05-12 23:00 — Sessão massiva Sells P0/P1 + revert/restore + fix re-render loop + prep Martinho 13/maio (14 PRs · 18 US done · 4 waves 11 agents · 1 incident recovery)](handoffs/2026-05-12-2300-massive-sells-session-revert-fix-martinho-prep.md) (PRs #667/#689/#690/#691/#693/#694/#697/#703/#704/#705/#706/#712-revert/#713-restore/#717-fix · root cause re-render loop por refs não-cacheadas em SellsGradeAvancada+Index · 3 memory feedbacks salvos · materiais Martinho prontos em `memory/requisitos/OficinaAuto/demo-martinho-2026-05-13/` (mockup HTML standalone + demo-script 15min + charter 1-pager))
- [2026-05-12 17:00 — Wave A+B consolidação + ComVis V0 LIVE + bloqueios mapeados (6 PRs · ~6.226 linhas)](handoffs/2026-05-12-1700-wave-ab-consolidacao-bloqueios.md) (PRs #670/#671/#672/#673/#674/#676 · Wave A 5 PRs mergeadas: Inventory F1 Kits/BOM CODE + 4 docs estratégicos CRM/PCP/Comissão/FinanceiroAvancado · Wave B 1/4 disparada: ComVis V0 scaffold mergeado · 3/4 bloqueados Wagner: Garantia D1-D6, OficinaAuto rename+ROI top 5, Dashboard defer · lições: pattern paralelização N agents na mesma worktree validado + prompt agent com regra "comparar-não-duplicar" Tier 0 economizou ~6 entregas duplicadas · how-trabalhar.md ganhou §Paralelização agents)
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

**Última atualização do índice:** 2026-05-13 17:50 — handoff ComVis revert + brief-fetch hook + disciplina TETO/ADR 0105 adicionado.
