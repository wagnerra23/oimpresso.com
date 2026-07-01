---
date: "2026-07-01"
time: "13:16 BRT"
slug: maquina-revisao-adr-esquecimento-g3
tldr: "Construída a máquina de revisão de ADR (0316 esquecimento real + 0317 gatilhos quando-rever + auto-canário), endurecido o gate append-only (G1 supersedes_partially na allowlist + G5 delete-exige-lápide) e revividas 9 ADRs mortas-por-erro (G3: 0005 Tier-0, 0044 cofre, 0001/0002/0088, 0031/0033/0036/0054). 9 PRs mergeados. Falta o detector Check O (G4) + 0078 (sinal Wagner) + Onda 2/3."
prs: [3479, 3480, 3487, 3490, 3491, 3496, 3497, 3502, 3504]
decided_by: [W]
related_adrs:
  - 0316-esquecimento-real-adr-morta-tombstone-git-auditoria
  - 0317-maquina-revisao-adr-quando-rever-gatilhos
  - 0274-referencia-adr-por-slug-alias-map-13-colisoes
  - 0257-adr-status-lifecycle-kind-modelo-canonico
next_steps:
  - "G4: implementar Check O (morta-mas-canon) em memory-health.mjs — o detector que faz a máquina pegar o próximo 0035 sozinha; calibrar baseline (não ref-count bruto = 11 falsos/dia)"
  - "0078: emendar (reencadeamento triplo) — PENDENTE sinal Wagner: 0094 é o herdeiro certo da parte 'constituição=1 frase'?"
  - "G6 watchdog dos 13 crons de governança + M1 EVENTO-prosa + M2 Check R (TTL de decided_at) + M3 AdrReviewBriefLineService (Onda 2/3)"
  - "Atualizar tests/eval/recall-golden.yaml (advisory recall-eval ficou vermelho: golden set tinha expectativas no estado mal-rotulado; corrigir p/ estado revivido, NÃO reverter relabels)"
---

# Handoff — Máquina de revisão de ADR + esquecimento real + G3 relabels

## Estado MCP no momento do fechamento
Sessão de governança-código (não tocou tasks MCP). Prova = estado git/PR:
**9/9 PRs MERGED** em `main`: #3479 #3480 #3487 #3490 #3491 #3496 #3497 #3502 #3504.
`adr-index-generate.mjs --check` verde (322 ADRs · **292 ativos** · 14 colisões grandfathered · 0 alertas de supersessão). memory-health exit 0.

## O que aconteceu
Wagner pediu "resolver os ADR em conflito". A investigação (3 agentes + workflows adversariais) achou que **renumerar é proibido (0274) e desnecessário** — e que a dor real era rótulo que mente. Construímos a máquina que faltava e consertamos o dano:

1. **Fundação da máquina** (0316 esquecimento real via git-rm + tombstone ledger + git-history-auditoria; 0317 "quando rever" = 3 gatilhos evento/inconsistência/tempo + auto-canário do cron; repoint dos gates de colisão pro baseline JSON; fecha falsa-cobertura do AdrNumberCollisionTest).
2. **Gate append-only endurecido** (G1: `supersedes_partially` entrou na allowlist de normalização; G5: `git rm` de ADR agora exige entrada no tombstone — antes `^D` passava verde).
3. **G3 — os mal-rótulos**: verificação adversarial das 28 ADRs marcadas mortas → **18 morte real, 10 o rótulo MENTIA**. Revividas as 9 confirmadas (0078 ficou p/ sinal Wagner): 3 por colisão de número (0005 Tier-0-por-lei, 0044 cofre, 0001), 5 por supersede parcial (0002, 0031, 0033, 0036, 0054), 1 por escopo-de-seção (0088). Cada `supersede` falso reconciliado (removido mislink OU movido pra `supersedes_partially`).
4. **Pesquisa estratégica** ("tem algo maduro?"): não é "podre" — é fronteira (P4 rótulo-verdadeiro / P6 decaimento / P7 recall-IA sobre corpus git-canônico, sem turnkey de ninguém) + 2 pés mecânicos reinventáveis com maduro (`lychee` link-check, `Spectral` frontmatter) + P1 (colisão de número) auto-criado pela numeração sequencial. Recomendação: NÃO adotar Backstage (viola "git é canônico" Tier 0); colar lychee+Spectral+vocabulário PEP; manter bespoke o miolo.

## Persistência
- **git canon:** 9 PRs em main (webhook→MCP propaga em ~2min).
- **Este handoff** + índice 08-handoff.md.
- ADRs 0316/0317 são a documentação viva da máquina.

## Próximos passos pra retomar
`/continuar` → foco: **G4 (Check O)** em `scripts/governance/memory-health.mjs` (o detector morta-mas-canon; ver ADR 0317 §1 + o design endurecido no workflow `maquina-revisao-adr-design`). Depois 0078 (precisa sinal Wagner) + Onda 2/3.

## Lições catalogadas
- **Rótulo de ADR mente nos dois sentidos** (0035 vivo-marcado-morto; 0005 morto por colisão-mislink pra 0172). Nunca relabelar pela lista de auditoria — verificar cada um (corpo + supersede real + citação canon). A verificação salvou de enterrar 18 mortes reais + de reviver errado.
- **Relabel arrasta dívida legada + ripple referencial**: tocar ADR legado aciona o schema-gate advisory (título `!!binary`, `related` não-slug) e quebra o `recall-golden.yaml` advisory — ambos pré-existentes/downstream, não regressão. Fica como follow-up (não reverter o conserto).
- **numbersFrom do gerador não lê path-form nem section-scope** (`0088#db`) → usar `supersedes_partially` (slug) pra parcial, não notação `#`.

## Pointers detalhados (on-demand)
- Design endurecido da máquina + crítica adversarial: workflow `maquina-revisao-adr-design` (session 2026-07-01).
- Verificação das 28 mortas: workflow `g3-verificar-mortas-mal-rotuladas`.
- Pesquisa adotar-vs-build: workflow `adr-governance-existe-maduro`.
