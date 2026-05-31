---
date: 2026-05-29
hour: "21:40 BRT"
topic: "PR-B (guard ds/* + ratchet) ja estava mergeado — verifiquei, extrai drift por regra/area, doc PR #1980"
duration: "~1h (continuacao frosty-greider-83ab2f, pos handoff 2045 DS v4 roxo)"
authors: ["Claude Opus 4.8 (1M)", "Wagner"]
---

# DS → PR-B: guard ds/* ja mergeado + numeros do drift (PR #1980)

## Estado MCP no momento
- **Cycle CYCLE-07** "Fundacoes pos-4.8 ... DS v3 enforced" (13d restantes, 7% decorrido). Goal direto: *"DS v3 + MWART camada 2+3 enforced (hook + CI bloqueiam violacao) · alvo 90"* — o guard ds/* (#1979) avanca esse goal.
- my-work @wagner: 30 tasks (5 REVIEW, 6 BLOCKED dormentes Gold, 19 TODO). Nenhuma task ds/* dedicada.

## O que aconteceu
Wagner colou a cola "DS → PR-B · Guard ds/* + ratchet" (Cowork [CC]). **O PR-B ja estava 100% mergeado** — `origin/main` HEAD = `fe9a182d6 feat(ds): guard ds/* + ratchet (#1979)`, autorado hoje 18:12 BRT por sessao irma, **"Aprovado por Claude Design"**. Nao dupliquei — teria switchado a branch da arvore principal: este "worktree" `frosty-*` e **subdir git-ignored** de `D:/oimpresso.com` (em `feat/staging-ct100`), nao worktree real (`git rev-parse --show-toplevel` → D:/oimpresso.com).

Verifiquei #1979 bate 1:1 com a cola: 6 seletores `no-restricted-syntax` (scoped `Pages/**`+`Modules/**`, fora de `Components/ui/**`+`_Showcase/**`, severidade warn), 4 specs em `prototipo-ui/`, baseline absorveu 639 ds/* (total 1455). Aceite: `grep -c ds/no-native-radio` = 1; CI gate verde; baseline gerado do mesmo commit → delta 0.

**Entreguei o que a cola pedia ("contagem por regra")**: rodei ESLint num worktree descartavel em `origin/main` (`Pages/**`), reproduzi **639/197 exato**, e extrai o split por seletor (que NAO esta no JSON — ratchet agrega tudo sob `no-restricted-syntax`).

### Drift por regra (639 total)
| regra | n | % |
|---|--:|--:|
| no-adhoc-status-text | 410 | 64.2% |
| no-native-select | 103 | 16.1% |
| no-rounded-xl | 66 | 10.3% |
| no-native-checkbox | 53 | 8.3% |
| no-native-radio | 7 | 1.1% |
| no-arbitrary-color | 0 | 0% |

### Drift por area (top)
Financeiro 107 · Cliente 77 · RecurringBilling 58 · OficinaAuto 44 · Sells 42 · Repair 35 · Purchase 31 · Admin 26 …

**Correcao de rota:** P0 da Matriz e **Cliente+Sells** (nao Financeiro, que lidera so por volume). Medicao confirmou **Cliente/Create+Edit ja zerados** (PR-A). Proximo real do PR-C: **Sells Create(6)+Edit(12)=18**, depois Cliente/Index(12). Nuance: dos 410 `no-adhoc-status-text`, Tipo 1 (form `<FieldError>`) migra primeiro; Tipo 2 (badge `STATUS_STYLE`) por ultimo.

Wagner pediu (a) repassar numeros + (b) persistir + (c) comecar PR-C → respondeu "sim", depois **dismissou** a pergunta de qual tela do PR-C → (c) pausado.

## Artefatos gerados
- **PR #1980** (mergeado via --admin, CI verde, doc-only): `prototipo-ui/DS_ADOCAO_INDICE.md` +37 linhas — secao "Baseline medido — drift inicial (2026-05-29)" com tabela por regra + por area + correcao de rota P0. E o "drift base por regra" pro Claude Design conferir (le via MCP sync) + insumo da dimensao "Adocao DS" do GovernanceV4 (defer PR-B2).
- Este handoff + linha de indice.

## Persistencia (3 canais)
- **git:** #1979 (guard) + #1980 (numeros) em main; este handoff em `docs/handoff-ds-drift-2026-05-29`.
- **MCP:** webhook GitHub→MCP propaga ~2min pos-merge (Claude Design enxerga DS_ADOCAO_INDICE atualizado).
- **BRIEFING:** n/a (nao tocou `Modules/<X>`; e governanca DS).

## Proximos passos pra retomar
`brief-fetch` → se for tocar PR-C: escolher tela (**Sells C2=18** / Cliente Index=12 / piloto Tipo-1) → faz em branch + **gate visual Wagner ANTES de mergear** (zona `LICOES_F3_FINANCEIRO_REJEITADO.md`). Meta `ds:report` → 0; cada PR-C abaixa o baseline.

## Licoes catalogadas
- **Cola pode chegar stale:** trabalho ja mergeado por sessao irma (repo 150+ worktrees paralelos). SEMPRE `git fetch` + checar `origin/main` HEAD antes de executar cola — evitou PR duplicado + branch-switch destrutivo.
- **"worktree" frosty-* nao e worktree real** (`show-toplevel`→D:/oimpresso.com em staging); git ops daqui afetam a arvore principal. Pra trabalho isolado: `git worktree add --detach origin/main` descartavel (remover com `--force` no fim).
- **Ratchet agrega ds/* sob `no-restricted-syntax`** → split por seletor exige re-rodar ESLint e agrupar pela mensagem `ds/…`; nao esta no baseline JSON.

## Pointers detalhados
- Guard: `eslint.config.js` (bloco "DS guard") · `scripts/eslint-baseline.mjs` · `config/eslint-baseline.json` (#1979)
- Specs: `prototipo-ui/{REGRAS_DS_LINT,REGISTRY_DS_COMPONENTES,DS_ADOCAO_INDICE,MATRIZ_MIGRACAO_DS}.md`
- ADR 0209 (eslint-9-flat-config) · PR-A `9d28f56a0` (Onda F componentes `@/Components/ui`)
