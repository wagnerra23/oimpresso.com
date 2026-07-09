---
date: "2026-07-08"
topic: "Fidelidade de âncora do fingerprint — SOTA + Ondas 1/2/3a + aplicação Financeiro + âncora podre → trava fail-closed na máquina + revisão adversarial + fixes F1-F7 + ADRs 0326/0327"
authors: [C, W]
related_adrs: [0326-trava-ancora-compare-fingerprint, 0327-anchor-content-required-emenda-0314]
---

# Sessão 2026-07-08 — Fidelidade de âncora: enforcement na MÁQUINA

> **TL;DR:** começou como estado-da-arte do `style-fingerprint` (nota ~58/72), virou o aprofundamento do vetor (Ondas 1/2/3a), a aplicação ao Financeiro ao vivo — e aí a lição-mãe: comparei contra o **shell errado** (âncora podre, o MESMO erro que o Wagner pegou em 07-06). *"As máquinas não estão funcionando em conjunto com os hooks."* Fechou com a **cola na máquina** (trava fail-closed no `--compare`), uma **revisão adversarial** (7 furos), o **fix de todos**, e a promoção a canon (**ADRs 0326/0327**), incluindo tornar `anchor-content-check` **required**.

## O arco (o que aconteceu)

1. **Estado-da-arte** — benchmark do `style-fingerprint.mjs` vs os líderes de visual-diff (Applitools/Percy/Chromatic/Playwright/Lost Pixel + paper arXiv "Beyond Pixel Diffs"). Nota ~58 (rubrica cheia) / ~72 (recorte do trabalho real). Ganhamos na técnica (semântico + sem baseline + portátil + auto-provado), perdemos em cobertura de eixos. Doc `sessions/2026-07-08-arte-fingerprint-vs-sota.md`.

2. **Ondas 1/2/3a** — aprofundei o vetor de **14→25 campos** (Onda 1: box-shadow/bgProprio/padding/tipografia-fina/ynorm/bg-image + furo 4/5 + captioning-lite), **container-causa + compostos** (Onda 2), e o **harness Playwright** responsivo (Onda 3a). Selftest 18→33. 3 PRs mergeados.

3. **Aplicação ao Financeiro (ao vivo)** — resolvi a âncora, renderizei `financeiro-page.jsx`, capturei proto×prod. Os eixos novos acharam o que o tool antigo (só cor) não via: **6/7 painéis sem elevação, faixa herda o fundo, padding zerado, vazamento branco no dark**.

4. **A âncora podre (a lição-mãe)** — ANTES de acertar, comparei contra o **shell `oimpresso.com.html`** em vez de `financeiro-page.jsx` — a **âncora podre que o Wagner já tinha pego em 07-06**. Repeti o erro; nenhum mecanismo barrou até o Wagner dizer "âncora incorreta". Diagnóstico dele: *"as máquinas não estão funcionando em conjunto com os hooks"* (o hook `block-ancora-no-olho` só vê `Read` de png; o erro foi no browser, não-hookável; `ancora.mjs` era advisory).

5. **A cola na MÁQUINA** — trava fail-closed no `--compare`: exige `--tela` (verifica a captura contra o `related_prototype` do charter via `ancora.mjs` subprocesso) OU `--sem-ancora <razão>` logado. Sem isso → RECUSA exit 3. **ADR 0326**.

6. **Revisão adversarial** (agente cético, histórico + hooks + workflows) — 7 furos: F1 browser não-hookável · **F2/F6** nada da máquina de fidelidade era required · F3 `--compare` "lembre de rodar" · **F4 BUG** (matcher `Claude_in_Chrome` capitalizado nunca casava a tool minúscula → hook de UI-smoke morto) · **F5** a trava passava um CLAIM · F7 ref morta a `mwart-gate.yml`. Veredito: *teatro no ponto de enforcement*.

7. **Fix de todos** — F4/F7 (matchers/ref) · F5 (`overlapConteudo` — rótulos `.jsx`×captura; **o teste-do-processo pegou um bug de extração poluída por código**, corrigido e validado contra a captura real: 20% real vs 0% shell) · **F2/F6** `anchor-content-check` promovido a **required** + **flip do branch protection (23→24)** + **emenda 0314** (exceção consciente à "required = só Tier-0", Wagner autorizou). F3 segurado com justificativa (ruído; dominado pelo F2).

8. **Promoção a canon** — as propostas viraram **ADRs 0326 (trava) + 0327 (emenda-required)** aceitas; as propostas conflitantes **revogadas** (removidas na promoção).

## Meta-princípio (o que generalizar)
> **Superfície de erro não-hookável (Chrome/paste/humano) → o enforcement vive DENTRO da máquina que produz o artefato, como input obrigatório fail-closed — E essa máquina precisa ser required.** Senão continua sendo lembrança.

## Resíduo honesto (declarado, não escondido)
A trava confere um **CLAIM** (a âncora é assada do argumento, não do DOM); o F5 reduz isso a evidência-fraca (overlap de texto), mas o browser é não-hookável e não há oráculo formal acima do charter. Reduz + torna auditável; **não blinda o Chrome**.

## Números
- **~15 PRs mergeados** (arte-doc, Ondas 1/2/3a, RUNBOOK, trava, F4/F7, F5+fix, F2/F6+flip, ADRs 0326/0327, handoff #3989).
- **2 ADRs aceitas:** 0326, 0327. **RUNBOOK novo:** `prototipo-ui/RUNBOOK-fidelidade-fingerprint.md` (loop 0→6 + os 7 furos).
- Fingerprint selftest 18→40. Required checks main 23→24.

## Lições catalogadas
- **Repeti a âncora podre** (07-06→07-08) — escolhi a âncora no olho em vez de `node prototipo-ui/ancora.mjs <Mod/Tela>`. Fix mecânico: a trava + o gate required.
- **O teste-do-processo pegou um bug real** (F5 extração poluída) — "verifique se funciona" ≠ rodar o selftest; é exercitar contra dado real.
- **Outage do classificador de ferramentas** (opus-4-8) no fim bloqueou Bash/PowerShell/browser/MCP por igual (só leitura de arquivo passou) — intermitente; empurrei o handoff numa janela.

## Pointers
- ADRs: [0326](../decisions/0326-trava-ancora-compare-fingerprint.md) · [0327](../decisions/0327-anchor-content-required-emenda-0314.md)
- RUNBOOK: [`prototipo-ui/RUNBOOK-fidelidade-fingerprint.md`](../../prototipo-ui/RUNBOOK-fidelidade-fingerprint.md) (loop 0→6 + furos F1-F7)
- Handoff: [2026-07-08-1845](../handoffs/2026-07-08-1845-fidelidade-ancora-fingerprint-enforcement-maquina.md)
- Arte: [2026-07-08-arte-fingerprint-vs-sota](2026-07-08-arte-fingerprint-vs-sota.md)
