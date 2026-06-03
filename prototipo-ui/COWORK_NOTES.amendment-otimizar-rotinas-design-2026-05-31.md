# COWORK_NOTES.amendment — Otimizar as ROTINAS de design · 2026-05-31

> **De:** [CC] · **Pra:** [CL] (Claude Code) · **Tipo:** PROPOSTA §10.4 + disparo F0 (auditoria-mapa).
> **Append em:** `prototipo-ui/COWORK_NOTES.md` → seção "Pendentes" (topo, append-only).
> **Mãe:** ADR 0114 (loop) · PROTOCOL §6 (health-check) · §10 (loop fechado) · §10.4 (gate valida-prompt).
>
> ⚠️ **Salvo verbatim no git** por [CL] em 2026-05-31 (URL `claudeusercontent.com` expira ~1h — git é o SSOT, PROTOCOL §10.1). Não editar este arquivo: é o pedido original imutável. Processamento (gate §10.4 + F0) vive em [`AUDITORIA_ROTINAS_DESIGN.md`](AUDITORIA_ROTINAS_DESIGN.md).

## Origem
[W] pediu: "quais otimizações nas rotinas de design? agrupe e gere o que precisa otimizar — tem que ensinar o Code a ser mais otimizado no design". Diagnóstico do [CC] lendo as rotinas reais no `main`: `PROTOCOL.md` (F1.5/F3.5), `.claude/runbooks/design-sync.md`, skills `mwart-comparative`, `design-deep-analysis`, `comparativo-do-modulo`, `cowork-prototype-replication`, `cockpit-runbook`, `charter-first/write`, e o trio de retorno §10.2 (`ds:report` + `DS_ADOCAO_INDICE` + `SYNC_LOG`/`HANDOFF`).

## As 6 otimizações (agrupadas por causa-raiz)

**G1 · Rotinas redundantes — UM motor de score, não 4.** `mwart-comparative`, `design-deep-analysis`, o gate F1.5 do PROTOCOL e o §A.3 do design-sync **todos** rodam as mesmas 5 skills `design:*` (critique+system+ux-copy+accessibility-review+research-synthesis) e pontuam as mesmas 15 dimensões → as 5 skills rodam 3-4× por tela. **Otimização:** skill canônica `design-score` (motor único do framework 15-dim) parametrizada por `{persona?, gate:F1.5|deep|sync}`; as outras **chamam** ela e leem o cache. Ensina: não invocar `design:*` em 3 lugares — 1 `design-report.json` reusado.

**G2 · Artefatos de gate dispersos — UM schema.** `critique-score.json` + `a11y-report.md` + `<tela>-visual-comparison.md` + `COMPARISON.md` separados; `jana:health-check` (§6) e a dimensão "Adoção DS" do GovernanceV4 leem fontes diferentes. **Otimização:** consolidar em `prototipos/<tela>/design-report.json` (score 15-dim + a11y severity + ds/* restante + critique categórico) como fonte única machine-readable.

**G3 · Dobrar os gates-ferry no produtor.** F1.5 [CD] e F3.5 [CA] viram **auto-check** de quem produz, mantendo a trava numérica (≥80 / WCAG AA). 7 hops → 4. Depende de G1 (se há 1 motor, o auto-check é só rodá-lo antes de entregar).

**G4 · Retorno automático, não manual — a dívida real.** §10 já diagnosticou (HANDOFF 15d stale, [W] virou carteiro). Conserto: Code **executa `npm run ds:report:write` + append `SYNC_LOG` + sobrescreve `HANDOFF` a CADA PR mergeado**, idealmente via hook pós-merge. Ensina: o loop só fecha quando o estado está **commitado** (é o que o [CC] lê via MCP, §10.3).

**G5 · Guard de drift = ratchet, não conselho.** Proibições visuais são advisory e não seguram (drift azul-220→roxo vazou). **Otimização:** ligar ESLint `ds/*` + Stylelint `.css` (spec pronta em `REGRAS_DS_LINT.md` + `REGRAS_STYLELINT_CSS.md`) com baseline + gate falha em delta>0.

**G6 · Code não regenera o já-feito.** Estende "só faz o que está ☐" (§10.2 worklist) de "adoção DS" pra **todas** as rotinas: `design-report.json` (G2) alimenta checklist por tela × gate; Code só roda o gate pendente.

**Sequência proposta (barata→cara, cada uma destrava a próxima):** G4 → G5 → G2 → G1 → G6 → G3.

## Disparo F0 — o que o [CL] entrega ANTES de qualquer consolidação (medir antes de mexer)

Auditoria-mapa das próprias rotinas de design. **NÃO mudar `PROTOCOL.md` nem skill** — só o mapa:
1. **Quais disparam de fato vs letra morta** — pra cada skill `design-*` / `mwart-comparative` / runbook `design-sync` / gates F1.5+F3.5: última vez que rodou (grep de artefatos gerados em `prototipos/*/` + `memory/sessions/`), tem trigger vivo?
2. **Sobreposição** — onde 2+ rotinas pontuam as mesmas 15 dimensões / invocam as mesmas `design:*`. Tabela rotina × (skills que invoca) × (artefato que gera).
3. **Custo real** — hops manuais por tela + nº de vezes que as `design:*` re-rodam por tela.
4. Devolve em `prototipo-ui/AUDITORIA_ROTINAS_DESIGN.md` + linha no `SYNC_LOG.md`.

## Natureza / gate §10.4
Proposta de processo+governança. [CL] valida contra o `main` **sozinho** (§10.4) antes de tocar qualquer coisa: cruzar se `ds:report`/lint specs **já existem** (não recriar), não duplicar skill que já faz o score, não cunhar número de ADR (soberania [W], ADR 0238). Pode abrir **ADR de evolução do loop** (mãe ADR 0114) como rascunho, mas **não mergeia** sem OK de [W]. A ordem de consolidação (G4→…→G3) é proposta [CC] — [W] decide.

## Changelog
| Versão | Data | Mudança |
|---|---|---|
| v1.0 | 2026-05-31 | Proposta de otimização das rotinas de design (G1–G6) + disparo F0 auditoria-mapa. |
