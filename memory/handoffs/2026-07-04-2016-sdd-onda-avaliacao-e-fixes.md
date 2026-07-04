---
date: "2026-07-04"
hour: "20:16"
slug: sdd-onda-avaliacao-e-fixes
tldr: "Avaliacao adversarial SDD deu composto 70/100 e achou 2 riscos-mae latentes — ambos fechados (#3782 flush anchor-lint node22, #3783 ledger-check --enforce). 6 PRs merged. Onda de anchoring gerada mas NAO mergeada: os 4 refutadores G5 morreram no session limit e a regra do trio proibe merge de lote IA sem refutacao. Retomar painel G5 + P04 burn-down."
authors: [C, F]
type: handoff
cycle: off-cycle
prs_merged: [3781, 3782, 3783, 3785, 3788, 3789]
branches_pendentes: [claude/sdd-charters-related-us]
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0130-handoff-append-only-mcp-first
---

# Handoff — Avaliação adversarial SDD + fixes dos 2 riscos-mãe + onda de anchoring (parcial)

## O que rodou (sessão remota claude.ai/code, Felipe [F])

Gatilho: "como está o plano SDD? avalie" → `/sdd-avaliar` (workflow `sdd-avaliador-processo`, 8 agents, ~1.06M tokens) → **composto 70/100** (streams GT 81 · SA 80 · Fase2b 75 · KL 72 · Charters 67 · FV 66 · Sem4-6 38). Session log em `memory/sessions/2026-07-03-sdd-avaliacao-adversarial.md`.

Depois "o que posso melhorar?" → "pode fazer" → "aprovado" → onda de execução dos itens acionáveis deste ambiente (sem CT100/ssh).

## PRs MERGED (6) — todos com CI verde antes do merge, nenhum forçado

| PR | Item | Efeito |
|---|---|---|
| #3781 | Session log da avaliação | registro do scorecard 70/100 |
| #3782 | **Fix flush `anchor-lint.mjs`** (risco sistêmico nº1) | mata truncamento sob pipe em node ≥22 nos 2 sites `--json`/`--emit-baseline`; espinha GT (G2/G3/G7/G8) protegida de deadlock de merge |
| #3783 | **`ledger-check` GT-G5 com `--enforce`** (risco nº2) | refutador de lote IA >10 arquivos agora BLOQUEIA merge em vez de só anotar (era `continue-on-error`) |
| #3785 | Higiene bookkeeping | P10 reconciliado (seção append-only, cov live 85.6%), skill `memory-schema-preflight` corrigida (anchor-lint é required), `validate-memory-schema.sh` agora pega `topic`>250 (o incidente do #3781) |
| #3788 | Errata proposta ADR 0273 | `proposals/2026-07-04-errata-0273-*.md` reconcilia §Status stale + F1 advisory→required (ADR canon é append-only; aceite = rito Wagner) |
| #3789 | P04 preparação executável | append no roadmap: self-heal #3507 já landou, **H1 central** = cópia `/opt` do CT100 desatualizada vs canônico pós-merges 07-02 (3 noites de floor morto) — pacote de 4 fases pra próxima sessão CT100 |

Risco sistêmico nº1 e nº2 da avaliação = **fechados**.

## PENDENTE — lotes de anchoring gerados mas NÃO-REFUTADOS (não mergear sem G5)

A onda gerou lotes de backfill de âncoras/charters em paralelo. **O session limit (reset 8:10pm UTC) matou os 4 refutadores G5 + o gerador da cauda.** A regra do trio imunológico SDD e o próprio risco nº2 da avaliação proíbem mergear lote IA de anchoring sem refutação — então PAREI.

| Lote | Onde | Estado | Bloqueio |
|---|---|---|---|
| **Charters `related_us`** (40 charters, 38.4%→65.8%) | branch `claude/sdd-charters-related-us` (commit `082a9a0c`, PUSHED, **sem PR**) | gerado, `charter-us-lint` ok=true | aguarda refutação G5 |
| **Governance anchors** (33 US sem_campo→0) | worktree `agent-af992f5397589e43a`, **não commitado** | gerado, `--check` exit 0 | aguarda refutação + push |
| **Vestuario/Compras/Essentials** (32 US) | worktree `agent-ada53c4916791da77`, **não commitado** | gerado, `--check` exit 0 | **BUG conhecido**: Essentials tem 5 US viradas `placeholder` porque o regex `PLACEHOLDER_RE` casa "ToDo" em `ToDoController.php` (limitação do `anchor-lint.mjs`, não gaming) |
| **Cauda (10 módulos)** | worktree `agent-a8b3b9d00f028cd1c` (locked) | **INCOMPLETO** (morreu no limit) | **BUG conhecido**: justificativa com backtick-path (`Modules/Accounting/` que não existe) faz o classify parsear como `anchored_dead` — cuidado ao commitar |

## Próxima ação (retomar após reset 8:10pm ou próxima sessão)

1. **Rodar o painel de refutação G5** (3 lentes: paths+gramática / semântica US↔código / inflação `_pendente_`) sobre os lotes prontos (Governance + charters + Vestuario/Compras/Essentials). Reprovado → corrigir → re-rodar até err=0. Registrar no `governance/sdd-verification-ledger.json` (agora que GT-G5 é `--enforce`, PR de lote >10 arquivos SEM entry no ledger fica vermelho).
2. Só então commitar/PR os lotes refutados. Antes de commitar Essentials: decidir o bug do regex `ToDo`→placeholder (fix pontual no `anchor-lint.mjs` word-boundary, ou âncora todo-free honesta).
3. Cauda (10 módulos) foi incompleta — regerar do zero, com a lição do backtick-path na justificativa.
4. **P04 burn-down** (alavanca nº1, a pedra grande) segue pra sessão CT100 dedicada — começar pela H1 (diff `/opt` vs canônico) antes de qualquer fan-out.

## Estado MCP no momento do fechamento

Ambiente remoto sem MCP oimpresso conectado (brief-fetch/cycles-active indisponíveis) — checklist MCP-first não executável aqui; estado vivo de tasks continua no MCP server. Base guard avisou checkout stale durante a onda; todos os commits produzidos a partir de `origin/main` fresco via worktree/checkout -B (guard §10.4 respeitado). Vigilância de PR: trigger de check-in horário armado (`send_later`), cobre a onda até MERGED/CLOSED.

## Caveats

- Refutação G5 é **pré-condição** dos lotes de anchoring — não pular (é literalmente o risco nº2 que fechamos no #3783).
- Worktrees dos agentes (`agent-*`) têm trabalho não-commitado — não `git worktree prune` antes de coletar/descartar conscientemente.
- Cumprindo R12 PROTOCOLO via skill encerrar-sessao (ativação lazy via hook UserPromptSubmit).
