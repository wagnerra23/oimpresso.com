---
date: "2026-07-16"
time: "20:35 BRT"
slug: grade-qualidade-ds-flip-3-gates-selftest-plumbing
tldr: "Auditoria adversarial da grade de 15 dimensões de qualidade visual/DS: minhas notas caíram de ~76 pra ~53 (estrutural). 5 PRs MERGED — flip de 3 gates DS a required (24→27) + self-test das 3 catracas (64/64) + plumbing do manifesto por-UC (exec_backed_pct 10%→17%). 2 pilotos meus mortos por adversário antes de gastar; 3 estimativas minhas derrubadas por medição."
decided_by: [W]
prs: [4301, 4307, 4311, 4318, 4377]
us: [US-GOV-054]
related_adrs:
  - 0339-promocao-soberana-3-gates-ratchet-ds-required-emenda-0336
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0290-fidelity-lock-v0-recusado
next_steps:
  - "US-GOV-054 (bite-log DR-2a): fecha o desvio da 0336 registrado na ADR 0339 — se um dos 3 gates não acumular ≥2 mordidas reais em ~4-6 semanas, reconsiderar demoção (gh api re-remove)"
  - "Validar o 1º cron do casos-results-publish (07:30 BRT) — auto-PR deve aterrissar o manifesto sozinho; se fail>0, NÃO auto-mergeia (por design)"
---

# Handoff — grade de qualidade DS: flip + self-test + plumbing (5 PRs)

## Estado MCP no momento

⚠️ **MCP desconectado nesta sessão** (`Oimpresso MCP — Wagner` caiu; brief-fetch em fallback curl exit 28). Checklist MCP-first do R12 **não pôde rodar** — snapshot via `git`/`gh`:
- PRs: **#4301, #4307, #4311, #4318, #4377 — todos MERGED**.
- Branch protection `main`: **28 required** (era 24 no início).
- US-GOV-054 criada no MCP antes da queda + persistida no `SPEC.md` via #4311.

## O que aconteceu

Wagner trouxe uma grade própria de 15 dimensões de qualidade visual/DS e pediu investigação. O trabalho virou 3 ondas, cada uma cortada por um adversário:

**1. Auditoria adversarial (5 subagentes, 11 dimensões).** Minhas notas estavam infladas em ~15-18pp. Causa-raiz comum: **eu creditava "o gate existe/virou required" como "a coisa está coberta"**. Estrutural honesto: **~53**, não ~76. Achados: `foundation-ratchet` estava catalogado como "type ramp" mas **mede quarentena de Pest, não tipografia** (a máquina certa é `conformance-gate::fontRamp`, já required); cor-crua-CSS só escaneia a família **Sells** (Financeiro/Repair/Cockpit são falsos-limpos por cegueira de escopo); `pageheader` cai de 85→38 (só conta import, 97 telas congeladas, ~19% migrado).

**2. Flip dos 3 gates DS a required (24→27).** `layout-primitives` · `stylelint` · `eslint`. Bloqueador pego **antes** de tocar a proteção: os 3 usavam `paths:` no trigger → required travaria todo PR fora do path ("Expected — waiting"). #4301 converteu pra always-run + skip-as-pass (padrão `e2e-gate`). Ao registrar, achei a **ADR 0336** (aceita por [W] em 11/jul) que exige bite-log de ≥2 PRs contrafactuais — **não coletei**. Trouxe à mesa; [W] manteve por soberania (0238). **ADR 0339 registra como DESVIO, não cumprimento.**

**3. Self-test das 3 catracas (#4318).** Elas não estavam no `gate-selftest` (required) — required que pode apodrecer verde. 3 chips paralelos + consolidação minha. Fail no CI: o `gate-selftest` roda **Node puro sem `npm ci`** (por design) e `stylelint`/`eslint` precisam do linter. Fix: modo `--counts-from` (prova o **comparador ratchet**, sem deps) + `stylelint` vira import lazy. **64/64 catracas mordem.**

**4. Adversário do próximo passo** matou minhas duas propostas: *reduzir baseline* (~6600 mexidas, ~6000 pixel-gated → gargalo no olho do [W]; dívida que já parou de crescer) e *ampliar escopo* (**+591 hits medidos**, 6 arquivos estouram o teto → **travaria o merge do time**; redundante com stylelint). Apontou o self-test como ROI #1 — foi o que fizemos.

**5. Adversário do piloto de cobertura** matou o piloto e destapou a causa real: **os charters são descrição da implementação versionada junto** (`Financeiro/Unificado` em `charter_version: 22`, changelog de PRs) → derivar UC deles é tautologia com passo extra. **Os UCs que pegaram regressão vieram de incidente / paridade Blade / decisão [W], zero de charter.** E a aritmética: declarar UC **sobe o denominador** → o piloto **baixaria** `exec_backed_pct`.

**6. Plumbing (#4377).** A descoberta: **`exec_backed_pct` media cadência de bookkeeping**, não qualidade. As 7 lanes Pest + e2e já emitem `--log-junit`, mas o XML subia como artifact e morria; o manifesto só mudava se um dev rodasse `casos:results` à mão. `UC-F04`/`UC-F05` (Pest verde em lane **required**) valiam **0**. Novo workflow colhe → coleta → auto-PR. **Provado rodando: 15 → 25 UCs (10% → 17%).**

## Artefatos gerados

| PR | O quê |
|---|---|
| #4301 | 3 gates DS → always-run + skip-as-pass (require-safe) |
| #4307 | `required-checks-baseline.json` (24→27) + **ADR 0339** (desvio da 0336 registrado) |
| #4311 | **US-GOV-054** no `SPEC.md` (bite-log DR-2a) |
| #4318 | Self-test das 3 catracas: 6 fixtures + `--counts-from` nos 2 scripts + 3 entradas no `CATRACAS` (64/64) |
| #4377 | `casos-results-publish.yml` + censo de gates + 1ª aterrissagem (15→25 UCs) |

Flip do vivo aplicado via `gh api PATCH` (24→27, `enforce_admins` intacto, 24 originais preservados).

## Persistência

- **git canon:** 5 PRs mergeados + ADR 0339 + índice de ADRs regenerado.
- **MCP:** US-GOV-054 criada (servidor caiu depois; SPEC.md tem o bloco).
- **BRIEFING:** não aplicável (nenhum módulo de negócio tocado — só governança/CI).

## Próximos passos pra retomar

```
gh pr list --repo wagnerra23/oimpresso.com --search "casos-results-manifesto"   # o cron aterrissa sozinho 07:30 BRT
gh run list --workflow=casos-results-publish.yml --limit 3                      # 1º cron: 2026-07-17
```

## Lições catalogadas

1. **"O gate virou required" ≠ "a coisa ficou coberta".** Minha inflação-mãe da sessão, pega pelo adversário. Congelar **0,4% de a11y** (1/234) e **3,4% de E2E** (dos quais 6/8 são só foto de pixel) vale o *mecanismo*, não o *número*. O próprio doc de promoção do `screen-coverage` avisa isso — e eu creditei o que o canon mandou não creditar.
2. **Creditei um fix com o sinal trocado.** O #4344 (`casos-gate` truncava `UC-KBV2-01`→`UC-KBV2`) **corrigiu uma mentira pró-nota**: subiu o denominador 135→143 e expôs 3 órfãos → a cobertura honesta **caiu**. Fix de integridade ≠ ganho de cobertura.
3. **Medir > estimar (3×).** `exec_backed_pct` do plumbing: **38%** (adversário, usou critério do G-2) → **27%** (meu, contei UC-no-título) → **17% real** (rodei o coletor). O coletor pareia pelo id no **título** do `<testcase>`; o G-2 aceita o id em qualquer lugar do corpo.
4. **Local × CI:** o `gate-selftest` é **Node puro sem `npm ci`** por design. Catraca que precisa de dep não entra lá — use contagens pré-computadas (`--counts-from`) e prove o comparador.
5. **Charter não é âncora de contrato** — é descrição da implementação, versionada junto (`charter_version: 22` = changelog). UC bom nasce de **incidente / paridade legado / decisão [W]** (e ganha counterfactual **grátis**: o comportamento já sumiu uma vez no mundo real).
6. **EOL:** `gates-registry.json` é **CRLF**; reescrever com LF infla o diff de 6 → 103 linhas. Detectar o EOL antes de escrever (2ª vez na sessão — o `required-checks-baseline.json` é LF).
7. **`memory-health` Check G mordeu certo:** workflow novo exige registro no censo de gates **no mesmo PR** (+ `terminal`/`anchor` pelo Check M / ADR 0298).

## Pointers detalhados

- [ADR 0339](../decisions/0339-promocao-soberana-3-gates-ratchet-ds-required-emenda-0336.md) — o desvio da 0336, por escrito
- [ADR 0336 DR-2](../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) — bite-log ≥2 PRs contrafactuais (o que não cumpri)
- `scripts/governance/gate-selftest.mjs` §CATRACAS — as 3 entradas novas + o porquê do `--counts-from`
- `.github/workflows/casos-results-publish.yml` — cabeçalho traz a medição que motivou (15→25)
