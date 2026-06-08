# Session 2026-05-11 — JANA Pro Sprint A foundation + modo Concierge MVP + pegadinha NTFS

> Companion narrativo do [handoff 2026-05-11 22:30](../handoffs/2026-05-11-2230-jana-pro-foundation-concierge-pegadinha.md). Aqui conta **o trabalho**; o handoff conta **o estado pro próximo**.

## Quem trabalhou

- **[W]** Wagner — operador solo durante sessão noite
- **[C]** Claude Code Max — pair IA

## Modo

Sessão linear, sem paralelismo (subagents falharam em sessões anteriores — ver handoff 2026-05-11 18:30). Worktrees isoladas pra cada PR:
- `.claude/worktrees/jana-pro-foundation` (PR #597, removida no fim)
- `.claude/worktrees/jana-agent-claude-pattern` (PR #600, removida no fim → **causou pegadinha junction**)
- `.claude/worktrees/jana-concierge-skill` (PR #602, removida sem -Force ok)

## Timeline

### 17h-19h BRT — Continuação CYCLE-05 prep
- PR #598 US-WA-072 merged + migration Hostinger
- Snapshot biz=1 do `BriefDiarioService` validado em prod

### 19h-20h — Wagner descobre potencial comercial
- Snapshot expôe sinais reais (NFe 100% reject, cliente sumido 322d, ticket comercial)
- Decisão: **monetizar como SaaS** → ADR 0140 aceita
- Plano: 32 US × 4 sprints × 90 dias

### 20h-21h — Refactor cognição "estilo Claude Code"
- Wagner rejeita Vizra (já rejeitada por ADR 0048, mas confirma)
- Direção: agents com tool use loop, igual Claude Code
- `laravel/ai` ^0.6.3 já suporta nativo (HasTools + Tool)
- **ADR 0141 aceita** — pattern Camada B v2
- PR #600 implementa BriefDiarioAgent + 5 Tools + Pest 5/5 PASS

### 21h-22h — Decisão "sem $"
- Wagner pergunta sobre Claude Code como engine
- Aviso ToS Anthropic + 3 alternativas
- Wagner escolhe **caminho B Concierge** ("eu executo manualmente quando tiver trabalhando")
- PR #602 cria skill + RUNBOOK operacional

### 22h-22h30 — Acidente + recovery + pegadinha
- Junction NTFS no worktree pra rodar Pest
- `git worktree remove --force` esvazia main vendor (318MB → 0B)
- `composer install` recovery ~5min
- PR #603 documenta pegadinha como proibição Tier 0
- **Vazamento OPENAI_API_KEY** detectado e avisado (rotação pendente Wagner)

### 22h30+ — Memory sync
- Wagner pediu "guarde essas memórias"
- Handoff + session log + atualização índice

## Métricas

| Métrica | Valor |
|---|---|
| Duração | ~5h ininterruptas |
| PRs merged | 5 desta sessão + 1 colateral Wagner (#604) |
| ADRs novas | 2 (0140, 0141) |
| Linhas adicionadas | ~1500 (PHP + tests + skill + ADRs + RUNBOOK + pegadinha) |
| Pest tests novos | 12 (5 BriefDiarioAgent + 7 BriefDiarioService regression) |
| Pest tests passando | 12/12 (100% local) |
| Skills novas | 1 (jana-brief-concierge v0.1.0 Tier B) |
| Pegadinhas documentadas | 1 (junction NTFS) |
| Custo direto sessão | R$ 0 (Max + Hostinger já pagos) |
| Custo API LLM produto | R$ 0 (Concierge mode) |

## Output qualitativo

### O que funcionou bem
- Sequência incremental sem subagents (lições da paralelização frustrada de 18h30 aplicadas)
- ADRs criadas inline com código (não depois) — força clareza arquitetural
- Skill como espelho dev → agent operacional: zero retrabalho de prompt entre modos
- Honestidade técnica sobre ToS/arquitetura Claude Code Max evitou expectativa errada

### O que correu mal
- Junction NTFS = bug nunca antes catalogado, 318MB de damage potencial
- Vazamento `OPENAI_API_KEY` no chat = grep mal-escrito (auto-mem `feedback_nunca_publicar_credenciais_no_chat` ignorada)
- Re-keying múltiplos arquivos sem cache MCP fez sessão demorar mais que necessário

### Lições incorporadas
1. **Junction NTFS é proibição Tier 0** (now documented)
2. **grep no .env sempre com `-c` ou pattern que NÃO captura valor** — credencial nunca echoed
3. **Snapshot real (não fixture) é critério de PMF** — apenas rodar o pipeline em prod já mostra se tem produto

## Tasks MCP afetadas

- US-WA-072 (`p1`) — **DONE** (PR #598 merged + migration prod)
- US-COPI-201 (`p0`) — **DONE implícito** (PR #597 já merged sessão anterior, validado nesta)
- **US-COPI-202** (`p0`) — **DONE** (PR #600 merged, agent dormente)
- **US-COPI-202b** (`p1`) — **DONE** (PR #602 merged, skill Concierge ativa)
- US-COPI-203/204/205 — **pending** (Sprint A continuação, ficam fora CYCLE-05)

> US-COPI-202 e 202b foram **criadas durante esta sessão** sem MCP task formal — Wagner pediu inline. Vale registrar via `tasks-create` retroativo se quiser tracking formal.

## Referências cruzadas

- **Handoff irmão:** [2026-05-11 18:30 paralelização omnichannel frustrada](../handoffs/2026-05-11-1830-paralelizacao-omnichannel-frustrada.md) — lição "não spawn agents de worktree filha" aplicada nesta sessão
- **Handoff irmão:** [2026-05-11 17:30 5 agents paralelos OficinaAuto](../handoffs/2026-05-11-1730-sells-grade-oficinaauto-paralelizacao.md) — contraste de modo
- **Próximo:** quando Wagner retomar, executar US-RB-048 (p0 do cycle ativo CYCLE-05) OU promover JANA Pro pra cycle dedicado

---

**Status final:** ✅ sessão registrada, foundation merged + dormente, modo Concierge ativo, time alertado sobre pegadinha NTFS, OPENAI_API_KEY pendente rotação.
