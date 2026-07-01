---
date: "2026-07-01"
hour: "≈16:00–18:14 BRT"
topic: "Máquina de revisão de ADR — camada de detecção (Check O, EVENTO-prosa, Check R, G6) + relabel 0078"
authors: [wagner, opus]
prs: [3514, 3517, 3518, 3519, 3522]
related_adrs:
  - 0317-maquina-revisao-adr-quando-rever-gatilhos
  - 0257-adr-status-lifecycle-kind-modelo-canonico
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0120-reverse-supersession-metadata-housekeeping
---

# Sessão 2026-07-01 — Máquina de revisão de ADR: camada de detecção

## TL;DR

Continuei a fila do handoff-mãe 1316 e construí a **camada de detecção** inteira da máquina de revisão de ADR ([ADR 0317](../decisions/0317-maquina-revisao-adr-quando-rever-gatilhos.md)): os 3 gatilhos (evento/inconsistência/tempo) + o auto-canário generalizado, todos 🟡 sentinela determinística sem bloquear merge. Mais o relabel 0078. **5 PRs mergeados**. Falta só a camada de surfacing (M3 AdrReviewBriefLineService) e a triagem (Onda 4).

## O que foi feito (ordem cronológica)

1. **0078 viva-parcial ([#3514](https://github.com/wagnerra23/oimpresso.com/pull/3514)).** Wagner confirmou 0094 como herdeiro da parte "constituição=1 frase". Modelado como supersede parcial (meta-skill sobrevive). Colidiu com a ADR 0120 (que blessou full) → refinado sob a 0317. Precisou migrar frontmatter legacy (number leading-zero + decided_at não-quotado) sob a exceção 0297 (`adr-legacy-schema-migration`, corpo byte-idêntico) — o AJV strict reprovava por o arquivo virar "changed".

2. **Check O — morta-mas-canon ([#3517](https://github.com/wagnerra23/oimpresso.com/pull/3517)).** Detector em memory-health.mjs. Calibração empírica: 11 (ingênuo) → 5 (curado + só-corpo + negação-de-contexto + aceito≠morta). Achou 0190/0136 = morta-mas-canon reais (dead ADR citada como token/decisão viva em SPEC).

3. **EVENTO-prosa ([#3518](https://github.com/wagnerra23/oimpresso.com/pull/3518)).** No gerador adr-index. Furo 0097. 64 (corpo inteiro, ruído) → 2 (título+status-note, por-cláusula, guarda de voz passiva). Canal separado do gate duro (nunca bloqueia).

4. **Check R — revisão vencida ([#3519](https://github.com/wagnerra23/oimpresso.com/pull/3519)).** Classe TEMPO. De decided_at imutável. 16 proposals stale >30d.

5. **G6 — watchdog dos crons ([#3522](https://github.com/wagnerra23/oimpresso.com/pull/3522)).** Generaliza o auto-canário. Descoberta dinâmica dos 13 crons, threshold por cadência. Testado local + CI contra runs reais (13/13 vivos).

## Achados / lições

- Precisão de sentinela é **iterativa e empírica** — rodar→medir→filtrar bate adivinhar o filtro.
- `status: aceito` nunca é "morta" (mesmo arquivado) — falso-positivo por construção.
- `execSync` no Windows = cmd.exe → aspas simples de `--jq` quebram; filtrar JSON em JS resolve.
- Job novo em workflow já registrado não dispara Check G/M — caminho barato pra gate advisory.

## Estado / o que falta

- **Fechado**: detecção completa (O/R/prosa/G6) + 0078 + recall-golden (verificado, #3511).
- **Falta**: M3 AdrReviewBriefLineService + quarterlyOn (Onda 3 pt.2, surfacing no brief); Onda 4 (triagem das filas: 5 do O, 2 do prosa, 16 do R — humano+adversarial).
- Detalhe de retomada no handoff [2026-07-01-1814-maquina-revisao-o-r-evento-prosa-g6](../handoffs/2026-07-01-1814-maquina-revisao-o-r-evento-prosa-g6.md).
