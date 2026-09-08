---
date: "2026-09-05"
time: "0036 BRT"
slug: "uc-id-escopo-medido-e-reprovado"
tldr: "Chip de medição fechado: escopar o UC-id por módulo nas 3 camadas do casos-gate tem 100% de FP e captura 0 bugs — virou lápide §5, não gate. A pergunta VIZINHA (unicidade de UC-id) tem FP 0% medido e fica aberta, não armada (ADR 0344). PR #6828 aberto, CI em execução no fechamento."
decided_by: []
cycle: null
prs: [6828]
us: []
next_steps:
  - "Acompanhar o CI do #6828 até verde e pedir merge a [W]"
  - "Coordenar ordem de merge com o #6812 (irmão, mesmos 3 arquivos, conflito textual garantido)"
  - "[W] decide se unicidade de UC-id vira máquina na 2ª ocorrência (dono a estender: scripts/qa/uc-id-lint.mjs)"
related_adrs: ["0130-handoff-append-only-mcp-first", "0264-governanca-executavel-trio-dominio-e2e", "0344-two-strikes-cobre-processo"]
---

# Handoff 2026-09-05 00:36 BRT — UC-id: escopo de módulo medido e reprovado (100% FP)

## TL;DR

O chip pedia medir o FP de escopar o UC-id por módulo antes de mexer nas 3 camadas do `casos-gate`. Medido: **100% de falso-positivo, 0 bugs capturados** → virou **lápide §5**, não gate. A premissa do chip tinha dois erros que a lápide corrige (denominador errado; `pageNamespacePath` não é resolvedor de módulo). A pergunta **vizinha** — unicidade de UC-id — tem FP **0%** medido e **fica aberta**, não armada.

> `decided_by: []` é literal: **nenhuma decisão humana** foi tomada nesta sessão — não houve input do [W]. As escolhas técnicas abaixo são do agente, dentro do escopo que o chip já autorizava; as soberanas (armar máquina, mergear) seguem pendentes.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| ~21:30 | Base stale detectada (120 commits atrás); branch nova de `origin/main` fresco |
| ~21:45 | Denominador medido: guard vê 129 casos.md / 875 ids, não 187/1020 — `prototipo-ui` fora |
| ~22:10 | Controle positivo: sonda acha os 4 `UC-APROV` em `c41df55b98^`, 0 em `HEAD` |
| ~22:40 | FP medido em 6 heurísticas nos dois corpora; população do bug = 0 |
| ~23:00 | `ciclo-adversary` despachado com a medição para refutação |
| ~23:30 | Alternativa medida: unicidade tem FP 0% em 8 pontos históricos |
| ~00:05 | Adversário devolve **REVIEW** — 3 imprecisões, 3 eixos novos fechados (todos 0) |
| ~00:15 | Colisão descoberta com o **#6812** — que delega esta medição a este chip |
| ~00:30 | Lápide + ledger + derive; rebase sobre main (+2 commits); PR #6828 |

## Estado atual dos artefatos

### Entregue nesta sessão

| Arquivo | Status | Δ | Notas |
|---|---|---|---|
| `memory/licoes-rejeitadas.md` | ✅ | +9 | Lápide 2026-09-05 (fonte append-only) |
| `memory/proibicoes.md` | ✅ | +10 | §5 **derivado** por `sec5-derive --write` — nunca editar à mão |
| `memory/LICOES_CODE.md` | ✅ | +2/−1 | LC-08 → **139** (contado: 139 recibos = 139) |
| `memory/sessions/2026-09-05-uc-id-*.md` | ✅ | novo | Session log (schema validado) |

### PRs

| PR | Status | Conteúdo |
|---|---|---|
| [#6828](https://github.com/wagnerra23/oimpresso.com/pull/6828) | **aberto — CI em execução** | esta medição |
| [#6812](https://github.com/wagnerra23/oimpresso.com/pull/6812) | aberto (irmão) | LC-11: o defeito que este chip mediu |

## Decisões tomadas (pelo agente, dentro do escopo do chip)

| Pergunta | Decisão | Justificativa |
|---|---|---|
| Escopar o UC-id por módulo? | **Não** | 100% de FP medido; `pageNamespacePath` resolve namespace de tela, não módulo |
| Armar unicidade de UC-id? | **Não agora** | FP 0% medido, mas 1ª ocorrência — ADR 0344 manda consertar, não codificar. Armar é [W] |
| Resolver o conflito do contador LC-08 | Alinhar ao main (138) **+1** = 139 | O #6788 adotou "Ocorrências = recibos contados"; manter meu 131 regrediria o trabalho deles |

## Bloqueios / pendências

- [ ] CI do #6828 ainda **não verde** no fechamento — 108 `queued`, 1 `success`, 3 `skipped`, **0 falhas** até aqui — owner: C
- [ ] Merge do #6828 e do #6812 conflita nos 3 arquivos; quem for depois mantém **as duas** lápides e re-roda `sec5-derive --write` — owner: quem mergear
- [ ] Decisão de armar unicidade de UC-id fica com **[W]** (2ª ocorrência já nasce com o trabalho pronto)

## Próximos passos (ordem)

1. Acompanhar o CI do #6828 até verde; **só então** propor merge a [W]
2. Combinar a ordem de merge com o #6812 (irmão)
3. Se a colisão de UC-id reincidir, estender `scripts/qa/uc-id-lint.mjs` — nunca abrir régua paralela

## Estado MCP no momento do fechamento

> **Nota honesta:** este worktree **não tem MCP conectado** — as tools (`cycles-active`, `my-work`, `sessions-recent`, `decisions-search`) não foram alcançáveis daqui. O que segue é o **fallback filesystem/git declarado** em `how-trabalhar.md` §Fallback, mais o `brief-fetch` que o hook `SessionStart` injetou. Declarado como fallback — não apresentado como saída de tool que não rodou.

### brief-fetch (via hook SessionStart, gerado há ~2h)
```
Cycle: — · Mission focus: — · HITL pending Wagner: 5 · Brain B hoje: 0% (0/50)
Flags: US não atribuída 682 (527 sem dono) · SDD composta 40,6 (Δ-1,2) · 12/13 vivas
Migration aging / PRs aguardando review / Visual regression CI: nada crítico
```

### sessions-recent (fallback: `ls memory/sessions/`)
```
2026-09-04-fiscal-onda10-sped-goals-cowork.md
2026-09-05-uc-id-escopo-de-modulo-medido-e-reprovado.md  (esta sessão)
21 session logs em setembro/2026
```

### whats-active (fallback: `gh pr list` cruzado com os arquivos que toquei)
```
COLISÃO REAL detectada e tratada:
  #6812 claude/ledger-lc11-casos-guard (OPEN, 03:08Z)
    -> toca memory/licoes-rejeitadas.md, memory/proibicoes.md, memory/LICOES_CODE.md
    -> é o IRMÃO deste trabalho; delega nominalmente esta medição a este chip
  Demais 41 PRs abertos: nenhum toca os 3 arquivos
```

### decisions-search (fallback: `git log origin/main -- memory/decisions/`)
```
Nenhuma ADR nova consumida por este trabalho. ADRs citadas: 0264 (trio/gates),
0344 (two-strikes), 0093 (multi-tenant, via o UC [T0] do caso de origem).
```

## Referências

- Session log: [2026-09-05-uc-id-escopo-de-modulo-medido-e-reprovado.md](../sessions/2026-09-05-uc-id-escopo-de-modulo-medido-e-reprovado.md)
- Handoff anterior: [2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals.md](2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals.md)
- ADR 0130: [Handoff append-only + MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
