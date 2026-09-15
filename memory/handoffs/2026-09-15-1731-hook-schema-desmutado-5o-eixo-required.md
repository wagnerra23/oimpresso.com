---
date: "2026-09-15"
time: "1731 BRT"
slug: "hook-schema-desmutado-5o-eixo-required"
tldr: "O memory-schema-guard falhava aberto em 100% das invocações locais porque as 3 deps do validador nunca entraram no package.json — declaradas, o hook voltou a negar. No caminho, o handoff-integrity apareceu quebrado (herdado do #7224) e a promoção dele a required renomeou o job, órfanando 11 de 12 PRs abertos: destravei 6 com update-branch e armei o 5º eixo que detecta a classe. [W] decidiu manter o eixo advisory até ter mordida."
decided_by: [W]
cycle: null
prs: [7284, 7303, 7306, 7307, 7308, 7310]
related_adrs: ["0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0336-gates-design-promocao-por-mordida-provada-emenda-0314"]
next_steps:
  - "Coletar a 2ª mordida da DR-2 do eixo Required rename (hoje 1/2) — `gh run list --workflow=required-always-run.yml` filtrando job rename + failure; o --scan do design-gate-bites NÃO serve (detecta violação que persiste no main, e esta é transitória)"
  - "Quando o flip acontecer: rodar `hooks-manifest-generate --write` no MESMO PR — o rename do job mexe no _HOOKS-INDEX e reprova o governance-script-tests"
  - "5 PRs em conflito (#7167 #7138 #7073 #6427 #6425) seguem sem emitir o required do handoff-integrity; destravam quando o dono resolver o conflito"
---

## Estado MCP no momento do fechamento

- Handoff irmão de hoje: `2026-09-15-1220-handoff-19-ciclo-ponto.md` (outra sessão, tema Ponto — sem sobreposição)
- ADRs tocadas hoje no main por terceiros: 0399 (ondas module-grade), 0400 (handoff integrity a required)
- Meus PRs: 5 MERGED (#7284 #7303 #7306 #7307 #7308) · 1 CLOSED por decisão [W] (#7310)
- `main` ao fechar: `handoff integrity` verde · 48 contexts emitidos vs 47 required · eixo novo advisory

## O que aconteceu

**O pedido era um:** desmutar o `.claude/hooks/memory-schema-guard.mjs`, hook PreToolUse `deny`-by-default sobre `memory/**` que falhava aberto em **toda** invocação local. Causa medida: `await import('ajv/dist/2020.js')` lançava `ERR_MODULE_NOT_FOUND` (o ajv hoistado era **6.15.0**, transitivo do `eslint@9`) e caía no `catch { process.exit(0) }`. As 3 deps não estavam em **nenhum** dos 4 campos do `package.json` — o CI as instala com `--no-save`, então nenhum `npm ci` local jamais as produzia. Mudez **permanente**, não transitória.

O risco que justificou PR próprio foi medido e não se materializou: o npm aninhou `ajv@6.15.0` sob `eslint` e `@eslint/eslintrc` (confirmado pelo resolvedor real, não por leitura de path), e os **dois** comandos da lane `eslint-gate` saíram com output **byte a byte idêntico** ao baseline.

**O resto veio do CI.** O `handoff integrity` reprovou meu PR; medi a autoria em vez de supor e achei dívida herdada do #7224: três sites meio atualizados, sendo o pior o `HANDOFF_DIR` apontando pro path velho — o guard via `files: 0` e acusava como "ref morta" **5 arquivos que existiam**, com o eixo órfão cego por construção. Chip aberto; outra sessão consertou e ainda promoveu o gate a required.

Essa promoção **renomeou o job na mesma leva** (tirou o `advisory ·`), e aí o required passou a exigir um nome que os PRs abertos não emitiam: **11 de 12** ficaram `BLOCKED` com 0 falhas e 0 pendentes. Destravei 6 com `gh pr update-branch`. É a §5 2026-08-08, 2ª ocorrência — a lápide previa *"a 2ª nasce com o trabalho pronto"*, e [W] mandou criar.

## Artefatos gerados

| artefato | onde |
|---|---|
| 3 deps em `devDependencies` | `package.json` +3/0 (#7284) |
| 5º eixo `--check-rename` + 6 asserts | `scripts/governance/required-always-run.mjs` +192/−2 (#7306) |
| job advisory `rename` | `.github/workflows/required-always-run.yml` +30/0 (#7306) |
| lápide §5 (fonte + derivado) | `licoes-rejeitadas.md` +9/0 · `proibicoes.md` +4/0 regerado (#7308) |
| 2 session logs conformes | `2026-09-02-visreg-*` (#7303 frontmatter, #7307 TL;DR) |

## Decisões [W]

1. **"deixa advisory, coleta mordida primeiro"** — o flip (#7310) foi fechado. Nada tocado na branch protection; `main` segue com 47 required e o job com sufixo `(advisory)` honesto.
2. **"deixa com esta"** — o predicado compara contra `origin/main`, não `pull_request.base.sha`. A medição inverteu minha recomendação anterior: com checkout de merge ref, o desenho atual não tem o FP que declarei, e `base.sha` **introduziria** o da §5 2026-09-15 (tip congelado × merge ref).

## Lições catalogadas

- **Lápide §5 nova** (#7308): emenda da 2026-08-08 — o par candidato foi armado, com FP 0 em 60 commits e bite-test pelo CLI.
- **Erros meus, corrigidos por medição, não por releitura:** (a) afirmei que o monitor me avisaria do CI — ele usava `jq`, ausente neste shell, e sairia mudo (§5 2026-08-11); (b) afirmei que um session log seguia fora do schema quando já estava consertado; (c) declarei um FP estrutural que não existe no CI, por presumir head cru onde o checkout entrega merge ref. Os três vieram de afirmar sobre premissa não medida.
- **Achado de processo:** o `design-gate-bites --scan` não consegue registrar mordida deste eixo — ele detecta violação que **persiste** no `main`, e esta é transitória (só existe com o PR aberto).

## Pointers

- §5 `memory/proibicoes.md` → `2026-09-15 — EMENDA da lápide 2026-08-08` (fonte em `licoes-rejeitadas.md`)
- `scripts/governance/required-always-run.mjs` — docblock do 5º eixo traz o FP medido e o incidente
- `governance/required-checks-baseline.json` `_meta.promocoes` — a promoção do `handoff integrity` (0400) feita por outra sessão
