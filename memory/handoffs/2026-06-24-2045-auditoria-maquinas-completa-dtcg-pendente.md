---
date: 2026-06-24
hour: "20:45 BRT (estimado)"
topic: Conserto do protocolo aplicar-prototipo (prosa→mecanismo) + auditoria COMPLETA das máquinas (4 ondas) + DTCG pendente
duration: ~6h (épico)
authors: [CL]
---

# Auditoria das máquinas completa (A·B·C·D) + DTCG pra próxima sessão

> **Cumprindo R12 PROTOCOLO via skill `encerrar-sessao` (ativação lazy).** Sessão grande demais; Wagner: *"outra sessão vai fechar o css essa esta muito grande"*.

## Estado MCP no momento do fechamento
- **Cycle:** CYCLE-08 Receita Onda A · 86% · **4d restantes** (28/jun). Goals: pricing público, 5 migrações-demo, MRR, ComVis V1, Agrosys de-riscado.
- **my-work @wagner:** 30 tasks (7 REVIEW · 8 BLOCKED · 15 TODO). **Nenhuma é deste trabalho** — auditoria de governança é off-cycle (drift conhecido: 0% commits alinhados ao CYCLE-08).
- **Último handoff irmão:** 2026-06-24 14:17 (reconciliação SDD Cliente). **ADRs aceitas no intervalo: 0** (criei 1 *proposta* — eixos-de-órfão — aguarda [W] cunhar número).

## O que aconteceu
Começou com Wagner colando o **zip do protótipo ComVis** ("prototipo") → import via skill `aplicar-prototipo`. Wagner cortou: *"o protocolo ainda tem erros... precisa fazer o diff... o que faltou?"* → **fio 1: consertar o protocolo de prosa→mecanismo.** A detecção de telas era prosa (charter=índice) e **perdia a tela Venda 2×** (Sells/Create sem charter no bundle). Criei `detectar-telas.mjs` (gate Fase 0/0.5: reconciliação bidirecional bundle→repo, 6 estratégias, falha se órfão). **Wagner: "quero um adversário"** → red-team pegou um **P0** (charter=índice ainda perdia 7/24 mockups) → virou mecanismo de verdade + fixture + `--selftest`.

Wagner então: *"auditar todas as máquinas e planos runbooks skills já está na hora"* → **fio 2: auditoria COMPLETA** (workflow 10 auditores + adversário). Respondeu 3 perguntas dele (PHP de correspondência existe = `CharterHealthChecker.php`; índice da SPEC-viva já existe fragmentado; painel já tem esqueleto = `governance-audit.mjs`). Depois "pode fazer todos" → **4 ondas de follow-up** A·B·C-mín·D, cada uma pesquisada+verificada por adversário.

Wagner pivotou pro **DTCG** (link fresco do parcial de tokens) → salvei o parcial, mapeei o que falta pra integrar → mas a sessão ficou grande demais → **DTCG vai pra sessão fresca.**

## Artefatos (6 PRs abertos + base já no main)
| Onda | PR | conteúdo |
|---|---|---|
| (fio1) | **#3350/#3351/#3352** ✅merged | `detectar-telas.mjs` gate + RUNBOOK/skill `aplicar-prototipo` (charter=índice + Fase 0.5 diff/manifesto) + auditoria-doc + deleta dup `plans-index-generate` + fix drift `perfil` no ssot-guard |
| A | **#3353 #3354** | arma `charter-us-lint` (advisory + censo) · ADR-proposta dos 7 eixos-de-órfão |
| B | **#3355 #3357** | `detectar-telas.test.mjs` (anti-drift ALIAS) + good/bad no gate-selftest · **aposenta `reincidencia-guard`** (handoff-integrity é superset; C5 doc pendente-[W]) |
| C-mín | **#3362** | `anchor-coverage` + `sdd-scorecard` no painel `governance-audit` (read-only) |
| D | **#3359** | **censo dos 207 comandos artisan** + **2 P0 chips** |

## Persistência
- **git:** 6 PRs abertos (todos verdes/0-fail), 4 já merged. Webhook GitHub→MCP propaga ~2min após merge.
- **session logs (no main/PRs):** `memory/sessions/2026-06-24-audit-maquinas-planos-runbooks-skills.md` (#3351) + `2026-06-24-censo-artisan.md` (#3359).
- **chips:** 2 P0 (`task_cc21f93c` webhook ghost · `task_4ec2f86d` autoClockOut Tier 0).

## Próximos passos pra retomar (sessão DTCG)
1. **DTCG (prioridade):** Wagner manda os **8 CSS espelhos + a proposta** (+ ideal DTCG com `$extensions.com.oimpresso.source` por token). Parcial salvo em `~/Downloads/_cowork-dtcg/_PARCIAL-domain-semantic.tokens.json` (6 famílias `domain`: frescor/kind/kpi-feature/vip/sla/canal). Mesclar grupo `domain` NOVO em `resources/css/tokens/semantic.tokens.json` (sem tocar cockpit pos/neg/warn nem roxo 295), resolver 2 aliases (`sla.paid`→`cockpit.surface.text-mute` ✓, `paid-soft`→`bg-2` ✓), **`source` por token** (o gate `dtcg-equivalence` exige) → provar `node scripts/governance/dtcg-equivalence.mjs` verde antes do PR. **Bloqueio:** sem os CSS espelhos o gate reprova (token DTCG sem CSS var).
2. **Mergear** os 6 PRs (decisão [W]).
3. **C-UI** opcional (página `/copiloto/admin/saude`) — feature gated (screenshot-[W]).
4. **2 P0 chips** (1 clique pra spinar).

## Lições catalogadas
- **prosa perde tela** — detecção por charter/diretório em prosa perdeu Sells/Create **2×**; só virou confiável como **gate executável** (mecanismo>prosa, §5 código).
- **adversário é o ativo** (de novo) — pegou o P0 do charter=índice E os erros do ADR (E1/E6 não-required, E4 required). Toda onda passou por red-team antes de PR.
- **censo achou bug de PROD que tocava VALOR** — `paymentgateway:retry-orphan-webhooks` ghost (cliente paga, título fica aberto) + `pos:autoClockOutUser` cross-tenant Tier 0. Auditar cron tem que varrer `*ServiceProvider.php`, não só Kernel.
- **workflow novo exige censo** — `gates-registry.json` + `.memory-health-baseline.json` (Check G/M) reprovam se esquecer.
- **sessão épica** — 6 PRs numa sessão + pivot DTCG = grande demais; Wagner cortou pra fechar.

## Pointers (on-demand, não duplicar)
- Auditoria completa + overlap_matrix 9 famílias: `memory/sessions/2026-06-24-audit-maquinas-...md`
- Censo 207 comandos: `memory/sessions/2026-06-24-censo-artisan.md`
- Protocolo de import: `prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md` + skill `aplicar-prototipo`
- ADR-proposta eixos-de-órfão: `memory/decisions/proposals/2026-06-24-eixos-de-orfao.md`
