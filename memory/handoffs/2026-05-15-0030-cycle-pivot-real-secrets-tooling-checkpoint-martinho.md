# Handoff 2026-05-15 00:30 BRT — Pivot CYCLE-05→06 executado + ferramenta secrets + checkpoint Martinho

> **Sessão:** worktree principal `wizardly-kalam-438c28` · sucessora do handoff [2026-05-14 21:00](2026-05-14-2100-cycle-pivot-05-to-06-martinho-fsm-jana.md) que preparou papelada.
> **Owner próximo:** Wagner segunda 19/maio.
> **Estado:** 3 branches Claude pushed origin, 2 PRs pendentes UI por timeout `api.github.com:443` lado rede Wagner.

## TL;DR

- ✅ CYCLE-05 **fechado de verdade no DB** (não só papelada) — cycles-close com retro 3-campos (sucessos/falhas/lição)
- ✅ CYCLE-06 **active no DB** — id=7, 4 goals, ordem reordenada (Martinho · Inter PJ · FSM · Jana V2)
- ✅ G2 Inter PJ promovido de "opcional" pra goal core — métrica realista (smoke biz=1 → cobrança biz=4)
- ✅ COPI-23 cancelled (com comment audit trail); CMS-1 mantida (decisão Wagner)
- ✅ Ferramenta `scripts/inter-credentials/` — SCP+SHA-256 fingerprint, Claude nunca vê valor
- ✅ Protocolo Secrets canônico em [memory/reference/protocolo-secrets-fingerprint.md](../reference/protocolo-secrets-fingerprint.md)
- ✅ ADR proposal `secrets-handling-fingerprint-scp` em `proposals/` — Wagner promove pra 0145 quando aprovar
- ✅ Feedback Asaas refinado: **blueprint educacional, não remover** (sessão 14/05 dizia "remover"; refinement 15/05)
- ✅ Checkpoint protetivo branch `claude/wip-martinho-canary-2026-05-14` (93 arquivos da maratona 14/05 maratona Wagner)
- 🟡 PR #853 + PR feat-secrets-fingerprint **aguardam merge UI** (rede `gh` CLI Wagner timeout)

## Estado MCP no momento do fechamento

```
cycles-active (CYCLE-06 id=7):
  Goals:
    🔲 G1: Martinho Caçambas em produção paga · ≥1
    🔲 G2: Inter PJ ao vivo — smoke biz=1 + 1ª cobrança biz=4 (Asaas blueprint, não ativar)
    🔲 G3: FSM rollout 162 vendas biz=1 · 14d verde
    🔲 G4: Jana V2 demo navegável · 1 piloto
  Duração: 2026-05-14 → 2026-05-28 (13d restantes)

my-work (snapshot anterior):
  DOING: 2 (US-WA-040 múltiplos números, US-COPI-100 NarrarSaudeEco)
  BLOCKED: 8 (CMS-1 mantida · 6 NFE Gold dormentes · FIN-4 · US-NFE-048) — COPI-23 cancelled
  TODO: 19 (3 P0: SELL-009, MWART-001, INFRA-001)
  Total: 29 ativas

my-inbox: 10 ASSIGNED (US-SELL-015/016/017/018/019/023/024/029/030 + US-WA-002 — atrasos
  notification de 2-5d). Não confundir com HITL pending: HITL=BLOCKED ativas (4 sem dormentes).

decisions-search since:2026-05-14:
  ADR 0143 LIVE (FSM canon, anterior) · ADR 0144 accepted (DB canon SPEC template)
  Nenhuma ADR nova proposta foi aceita nesta sessão; proposal secrets-handling pendente Wagner.

git log nas 3 branches Claude desta sessão:
  claude/musing-wilbur-3da897 → PR #853 (governance papelada — handoff 2026-05-14 21:00)
  claude/feat-secrets-fingerprint → e2efddffc feat(secrets) + 067145505 docs(feedback)
  claude/wip-martinho-canary-2026-05-14 → 1 commit checkpoint 93 arquivos Martinho
```

## O que esta sessão fez DEPOIS do handoff 21:00

Sessão 21:00 (worktree filho — deletado pelo sistema):
- Preparou 5 blocos copy-paste pra Wagner colar no harness principal
- Criou papelada governance (session log + handoff + índice update)

Sessão atual 00:30 (worktree principal, tools MCP carregadas via ToolSearch):
1. **Executou cycles-close + cycles-create de verdade** — não mais papelada
2. **Identificou bug DB**: 0 tasks rolaram automaticamente (cycle_id NULL em tasks BLOCKED/DOING — drift estrutural, não só nominal)
3. **Refinou G2 Inter PJ via SSH tinker** (sem cycles-update tool MCP — UPDATE direto)
4. **Recebeu feedback Wagner**: "Financeiro é o Meu do inter, podemos fazer agora · Asaas é insistência sua não minha"
5. **Salvou feedback canônico** [feedback-inter-pj-nao-asaas.md](../reference/feedback-inter-pj-nao-asaas.md) — duas versões (inicial 14/05 + refinement 15/05 "Asaas é blueprint")
6. **Criou ferramenta SCP+fingerprint** após Wagner pedir "Python pra copiar dos campos destinados sem você enxergar"
7. **Formalizou protocolo** em reference + ADR proposal
8. **Trancou git**: 3 branches pushed, working tree limpo

## Pendências Wagner segunda 19/maio

### Operacional Inter PJ (CYCLE-06 G2)
1. **Cadastrar credenciais Inter biz=1** via `scripts/inter-credentials/install-biz.py` (com `--apply`)
2. Me avisar "cadastrei biz=1" → eu disparo smoke saldo (RUNBOOK §3)
3. Smoke 2 extrato D-7 (RUNBOOK §4)
4. Smoke 3 PIX cob (RUNBOOK §5)
5. Canary 7d biz=1 com monitoramento
6. **Só depois** cadastrar biz=4 e ativar 1ª cobrança ROTA LIVRE (FIN-4) via Inter

### Governança
7. **Mergear PR #853** governance pivot (UI navegador — `gh` CLI timeout)
8. **Criar PR** [feat-secrets-fingerprint](https://github.com/wagnerra23/oimpresso.com/pull/new/claude/feat-secrets-fingerprint) via UI
9. **Decidir sobre proposal secrets-handling-fingerprint-scp**: aceitar como ADR 0145 OU refinar OU manter como proposal

### Maratona Martinho (do handoff 2026-05-14 18:00)
10. **Fatiar `claude/wip-martinho-canary-2026-05-14` em PRs A-F** (plano já escrito):
    - PR A: MWART /contacts (10 arquivos)
    - PR B: Sidebar customizada (6)
    - PR C: Bug fix 3 importers (3)
    - PR D: Hotfix /sells/create biz=164 (1)
    - PR E: Sidebar config Martinho estoque visível (1)
    - PR F: ADR proposal dual-sync (1)
11. **Migrate + Seed prod** Hostinger (Wave A já feita, faltam ajustes)
12. **Treinar Lara + Dani** (sessão remota 1h cada)

### HITL pendentes
13. **CMS-1 mantida** (não cancelar — sua decisão 15/05)
14. **US-NFE-048** + 6 NFE Gold dormentes — manter blocked, aguarda cliente Gold acordar
15. **FIN-4 ROTA LIVRE** — agora ligada a G2 Inter PJ (não Asaas)

## 3 branches Claude origin (foto final)

| Branch | Commits | PRs | Não-merge |
|---|---|---|---|
| `claude/musing-wilbur-3da897` | 1 (3 arquivos governance) | #853 | aguarda merge UI |
| `claude/feat-secrets-fingerprint` | 2 (8 arquivos secrets) | pendente criação UI | — |
| `claude/wip-martinho-canary-2026-05-14` | 1 (93 arquivos maratona) | NÃO criar PR | checkpoint protetivo — fatiar em PRs A-F segunda |

## Refs

- [Handoff anterior 2026-05-14 21:00](2026-05-14-2100-cycle-pivot-05-to-06-martinho-fsm-jana.md) — papelada que esta sessão executou
- [Session log pivot foco](../sessions/2026-05-14-cycle-05-close-cycle-06-pivot-foco.md) — narrativa session 1
- [feedback Inter PJ não Asaas](../reference/feedback-inter-pj-nao-asaas.md) — preferência refinada
- [Protocolo Secrets](../reference/protocolo-secrets-fingerprint.md) — operacional canônico
- [ADR proposal secrets](../decisions/proposals/secrets-handling-fingerprint-scp.md) — pendente Wagner
- [Handoff Martinho canary prep 18:00](2026-05-14-1800-martinho-canary-prep-jair-endossou.md) — Wave A em prod biz=164
- ADR 0030 (credenciais não git) · ADR 0093 (Tier 0) · ADR 0101 (biz=4 não testes) · ADR 0105 (sinal qualificado) · ADR 0130 (handoff append-only) · ADR 0143 (FSM canon)

## Lições da sessão

1. **MCP tools nem sempre carregam no harness** — worktree filho não tinha mcp__Oimpresso__*, só vieram via ToolSearch quando worktree foi deletada. Pra worktrees Claude, melhor verificar tools antes de prometer execução.
2. **`cycles-close --rollover_to`** funcionou mas moveu 0 tasks porque cycle_id era NULL — drift estrutural escondido atrás do drift nominal.
3. **`gh pr merge` falha em `api.github.com:443`** mas `git push` em `github.com:443` passou — rotas DNS/firewall diferentes. UI navegador deve passar.
4. **Wagner valoriza "não vou enxergar credenciais"** mais que velocidade — ferramenta SCP+fingerprint vale o overhead vs tinker manual.
5. **Asaas como blueprint educacional** (insight Wagner 15/05) — não remover código antigo que serve de referência, só não propor pra cliente.
