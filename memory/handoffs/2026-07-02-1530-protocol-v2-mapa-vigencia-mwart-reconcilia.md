---
date: "2026-07-02"
time: "15:30 BRT"
slug: protocol-v2-mapa-vigencia-mwart-reconcilia
tldr: "PROTOCOL.md ganhou §0 Mapa de vigência (leitura vigente da v2 sem mover/renumerar §N — âncoras são API de PHP prod/CI/hook/ADRs) + skill mwart-comparative V4.1 reconciliada com Protocolo v2 (gate síncrono morto → CI, guardas 0283/0104 preservadas). 2 PRs mergeados. Plano passou por refutação adversarial que matou a variante move-blocos."
prs: [3650, 3651]
decided_by: [W]
related_adrs: [0282-protocolo-v2-colapso-ratificacao, 0241-loop-design-cowork-code-autonomo-zero-humano, 0283-handoff-loop-zero-paste, 0314-poda-gates-onda-2-lei-fusoes]
next_steps: ["nada bloqueante — melhoria editorial fechada; se quiser, F-C (parser bundle Claude Design) segue reativo ao 'Send to Code' real"]
---

# Handoff — PROTOCOL.md §0 Mapa de vigência + mwart-comparative V4.1

## Estado MCP no momento do fechamento
- **Cycle:** sem cycle ativo no brief (#298, gerado há ~4h).
- **HITL pending Wagner:** 2 (FIN-004 cobrança ROTA LIVRE · runbook on-prem pós-Gold) — não tocados nesta sessão.
- **ADRs 24h (brief):** 0316/0317/0318 (esquecimento real · máquina de revisão ADR · RAGAS eval real).
- **Base do checkout desta sessão:** branch `claude/adoring-tu-a338f4` estava −4607 vs origin/main → TODA validação de canon foi via `git show origin/main:` (§10.4 Passo 0). Handoff/PRs produzidos em worktree fresco de origin/main.

## O que aconteceu
Pergunta do Wagner: "analise e compare o protocolo com o Claude Design, foi simplificado recentemente?". Diagnóstico: **sim** — Protocolo v2 "colapso" ([ADR 0282](../decisions/0282-protocolo-v2-colapso-ratificacao.md), 2026-06-17) simplificou o *processo* (6→2 papéis, 7→3 fases, gates=CI, intake=Issues), mas a *leitura* do `prototipo-ui/PROTOCOL.md` ficou em 5 camadas (corpo v1 + overlay §2 + banner + ADR externa + nota na AUTOMACAO). Comparação com Claude Design oficial (Anthropic) já existia no dossiê [2026-06-06-arte-claude-design-handoff](../sessions/2026-06-06-arte-claude-design-handoff.md): somos superset governado; eles ganham só no formato do bundle.

Wagner pediu plano de adaptação + **adversário antes de concluir**. O adversário (agente cético, tudo verificado vs origin/main + gh api live) **matou a variante inicial** (mover/renumerar seções): os §N são API pública citada por PHP em prod (`CharterHealthChecker`/`jana:health-check` → §6), CI (`design-return-gate.yml` → §10.2), hook (`git-base-freshness-guard.mjs` → §10.4) e ADRs append-only (0114/0241/0247/0255); e o v1 é intercalado vivo/morto (§6 roda hoje). Executei a variante aditiva que ele validou, com as guardas dele.

## Artefatos gerados (2 PRs, ambos MERGED)
- **[PR #3650](https://github.com/wagnerra23/oimpresso.com/pull/3650)** (merge `4f0f9b8391`) — `prototipo-ui/PROTOCOL.md` (+25 linhas) `§0 Mapa de vigência` (status por seção, §6 marcado VIGENTE) + overlay §2 atualizado (intake Issue/`cowork-inbox`, write-paths ADR 0283, `gh --admin` MORTO) + gates como **cache datado do gh api** (23 required, `enforce_admins:true`; a11y-axe/PR UI Judge advisory pós-[ADR 0314](../decisions/proposals/0314-poda-gates-onda-2-lei-fusoes.md) — corrige claim stale da 0282). README aponta pro §0. **Zero renumeração.**
- **[PR #3651](https://github.com/wagnerra23/oimpresso.com/pull/3651)** (merge `dad0b113b6`) — `.claude/skills/mwart-comparative/SKILL.md` V4→**V4.1** + CLAUDE.md: gate "Wagner aprova SCREENSHOT síncrono" → gate CI ([ADR 0241](../decisions/0241-loop-design-cowork-code-autonomo-zero-humano.md) `amends: [0107]`). Guardas preservadas: artefato 15-dim obrigatório · travas ≥80/WCAG AA · merge `.tsx` humano ([ADR 0283](../decisions/0283-handoff-loop-zero-paste.md)) · F5 CUTOVER humano (ADR 0104) · Wagner pode sempre pedir revisão síncrona. Fix drift `tier: B` vs "Tier A".

## Persistência
- **git:** ambos merged em origin/main (webhook→MCP propaga em ~2min).
- **Charters:** confirmado que os 12 que citam o loop apontam por *identidade* da skill ("V4") e *artefato* (`visual-comparison.md`), nunca por §N — por isso não bumpei pra V5 nem movi âncoras; todos os links resolvem.

## Próximos passos pra retomar
Nada bloqueante — melhoria editorial fechada. Se o "Send to Claude Code" real do Claude Design chegar, F-B/F-C do dossiê 2026-06-06 (parser do bundle estruturado → mata mapeamento CSS→Tailwind manual) seguem reativos.

## Lições catalogadas
- **Âncoras §N de doc-lei são API.** Antes de reestruturar PROTOCOL.md/qualquer doc citado por código, `git grep "§N"` em `Modules/`, `.github/workflows/`, `scripts/*.mjs`, hooks e ADRs. Renumerar quebra ponteiros que ADRs append-only **não podem** corrigir. O adversário pegou isso; a variante aditiva (mapa de vigência) entrega ~90% do valor com ~10% do risco.
- **Doc vivo ≠ append-only.** PROTOCOL.md tem 15 edições in-place — pode editar; ADRs/handoffs não. Confirmar o tipo antes de assumir.

## Pointers detalhados
- Diagnóstico + comparação Claude Design: [memory/sessions/2026-06-06-arte-claude-design-handoff.md](../sessions/2026-06-06-arte-claude-design-handoff.md)
- Protocolo v2 mãe: [ADR 0282](../decisions/0282-protocolo-v2-colapso-ratificacao.md)
- Estado vivo de merge/gates: [prototipo-ui/AUTOMACAO-LOOP-AUTONOMO.md](../../prototipo-ui/AUTOMACAO-LOOP-AUTONOMO.md) §2–§3
