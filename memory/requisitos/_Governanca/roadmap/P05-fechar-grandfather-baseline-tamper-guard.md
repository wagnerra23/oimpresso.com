---
roadmap_item: P05
slug: fechar-grandfather-baseline-tamper-guard
onda: 1
status: proposed
depende_de: []
destrava: [P11, P13]
related_adrs: [275, 271, 256, 258]
esforco_estimado: "0.5d codável + IA-pair (margem 2x = 1d) · zero relógio humano-limitado"
---

# P05 · Fechar o buraco grandfather do baseline-tamper-guard (vetor #2848)

## Problema (o que está quebrado, em 2-3 frases)
O `baseline-tamper-guard` é o ÚNICO meta-gate que pega o vetor #2848 ("afroxar baseline + shippar o código que ele deveria pegar, no MESMO PR → entra verde"). Mas hoje ele guarda **apenas 1 dos 4 baselines-ratchet do repo** (`scripts/governance/.memory-health-baseline.json`), e seu workflow só dispara quando o PR toca **exatamente** esse arquivo. Os outros três baselines armados — SDD scorecard, knowledge-ghosts (24 módulos), required-checks — ficam fora do guarda-meta: um PR pode afrouxar qualquer um deles + meter código no mesmo commit e o tamper-guard nem roda. O caso real #2848 (ghost 14→16, baseline subido no mesmo PR) é exatamente essa classe e segue ABERTO pros 3 baselines não-cobertos.

## Causa-raiz (evidência VERIFICADA — file:line reais confirmados)

**1. O mapa `GUARDED` cobre só 1 baseline.**
`scripts/governance/baseline-tamper-guard.mjs:55-57`:
```js
const GUARDED = {
  'scripts/governance/.memory-health-baseline.json': detectMemoryHealth,
};
```
Há só um detector (`detectMemoryHealth`, linhas 39-50). Os outros baselines não têm entrada nem detector.

**2. O trigger do workflow é estreito (path-scoped a 1 baseline).**
`.github/workflows/baseline-tamper-guard.yml:15-18` — `paths:` lista apenas `scripts/governance/.memory-health-baseline.json`, o próprio `.mjs` e o próprio `.yml`. Um PR que afrouxe `governance/sdd-scorecard-baseline.json` (e nada de memory-health) **não dispara o job**.

**3. Os 3 baselines não-cobertos EXISTEM no repo (armados), confirmado on-disk:**
- `governance/sdd-scorecard-baseline.json` (4952 bytes, 10 métricas com `value`/`direction`/`armed` por métrica — schema confirmado via `node -e`; ratchet próprio em `scripts/governance/sdd-scorecard.mjs:293-311`).
- `governance/knowledge-ghosts-baseline/` — 24 arquivos `<Mod>.json`, cada um `{"module":"X","ghosts":[...]}` (ex.: `MemCofre.json` = `{"module":"MemCofre","ghosts":["Chat","DocVault","MemCofre","PontoWr2"]}`); catraca própria em `scripts/governance/knowledge-drift.mjs:71,90,118-140`.
- `governance/required-checks-baseline.json` (2013 bytes — `classic_protection.contexts[]` 18 entradas + `rulesets.contexts[]`; consumido por `scripts/governance/protection-drift.mjs`).

**4. Tamper-guard NÃO está no gate-selftest.** `scripts/governance/gate-selftest.mjs:82-115` tem 5 catracas (`knowledge-drift`, `foundation-ratchet`, `ledger-check`, `sdd-scorecard`, `memory-health`). `baseline-tamper-guard` não aparece — ou seja, ninguém prova que ele MORDE. (Confirma o GLOBAL: "0 gates SDD são required" e a régua L3 sem counterfactual.)

**5. Tamper-guard NÃO é required.** `scripts/governance/gates-registry.json` classifica `baseline-tamper-guard.yml` como `"classe":"gate"` mas o check não está na lista de required de `governance/required-checks-baseline.json` (nenhum contexto "tamper" lá). Roda advisory.

## Estado atual no repo (o que achei ao verificar agora)
- **Divergência de path corrigida:** o Glob inicial não achou os 3 baselines porque o cwd default da sessão é o worktree `frosty-greider-83ab2f`, não `D:\oimpresso.com`. Rodando `ls` na raiz REAL, os três existem e estão armados (datas Jun 18-20). A evidência do briefing está **correta**; o "não encontrado" foi artefato de cwd.
- Os 3 consumidores (`knowledge-ghost-gate.yml`, `protection-drift.yml`, `sdd-scorecard.yml`) **já disparam no próprio baseline** (paths confirmados) e cada um tem seu ratchet de dimensão. **Mas:** (a) rodam advisory/`continue-on-error` (GLOBAL), e (b) cada um só vê SUA dimensão — nenhum implementa a regra cross-cutting "afroxou baseline E tocou código no mesmo PR sem `BASELINE-ABSORB`". Essa regra meta é exclusiva do tamper-guard. Logo estender o tamper-guard **não duplica** os ratchets; adiciona a camada meta que falta pros outros 3.
- O escape-hatch `BASELINE-ABSORB` (linhas 96-105, 110-117) já é genérico — funciona pra qualquer baseline novo sem mudança.
- ADRs citados existem: `0275-scorecard-sdd-canonico-...md` e `0271-revisao-gates-ci-estado-real-...md` confirmados em `memory/decisions/`.

## Objetivo / DoD (critério de pronto OBJETIVO e checável)
1. `GUARDED` em `baseline-tamper-guard.mjs` cobre os **4** baselines (memory-health já presente + sdd-scorecard + knowledge-ghosts + required-checks), cada um com detector de afrouxamento específico do seu schema.
2. O `paths:` do workflow `baseline-tamper-guard.yml` inclui os 4 baselines (e o diretório `governance/knowledge-ghosts-baseline/**`).
3. `gate-selftest.mjs` ganha a catraca `baseline-tamper-guard` com par de fixtures **good** (baseline afrouxado isolado OU com `BASELINE-ABSORB` → exit 0) e **bad** (baseline afrouxado + código tocado, sem marcador → exit 1 pela acusação certa).
4. `node scripts/governance/gate-selftest.mjs` passa com **6 catracas × 2 fixtures = 12/12** verdes (good passa, bad MORDE).
5. **Counterfactual provado:** um diff que afrouxe `governance/sdd-scorecard-baseline.json` (ex.: `armed:true→false` numa métrica armada, ou `value` movido na direção errada) + qualquer arquivo de código no mesmo PR, sem `BASELINE-ABSORB`, dá `exit 1`. Reverter o afrouxamento → `exit 0`.

## Passos (ordenados, concretos)

1. **Escrever 3 detectores novos** em `baseline-tamper-guard.mjs` (espelhando `detectMemoryHealth`, recebem `(base, head)` parseados, devolvem lista de descrições de afrouxamento):
   - `detectSddScorecard(base, head)`: pra cada métrica em `head.metrics`, comparar com `base.metrics[name]`. Afrouxamento = (a) `value` regrediu na direção contrária a `direction` (`down`⇒subiu, `up`⇒desceu) numa métrica `armed:true` na base; OU (b) métrica que era `armed:true` virou `armed:false` (desarmar = afrouxar); OU (c) `_meta` removeu métrica armada. Reusar a lógica de "worse" de `sdd-scorecard.mjs:300`.
   - `detectKnowledgeGhosts(base, head)`: aqui o "baseline" é um **diretório** de `<Mod>.json`, não 1 arquivo. Tratar via entrada especial (ver passo 2). Por módulo: ghost presente em `head.ghosts` e ausente em `base.ghosts` = "grandfatherou ghost novo: `<Mod>/<ghost>`". (É exatamente o vetor #2848: ghost 14→16.)
   - `detectRequiredChecks(base, head)`: contexto presente em `base.classic_protection.contexts` (ou `base.rulesets.contexts`) e ausente em `head` = "required REMOVIDO (demoção): `<ctx>`". Demover required = afrouxar.

2. **Estender `GUARDED`** (linha 55-57). Para os 3 arquivos-únicos, mapa path→detector direto. Para knowledge-ghosts (diretório), adicionar suporte a glob/dir: ou (a) varrer `governance/knowledge-ghosts-baseline/*.json` e comparar cada um com `git show BASE:<path>` (preferido — mantém a engine de diff por-arquivo intacta), ou (b) tratar o dir como pseudo-path com loop interno. Decidir por (a): menos mudança na engine principal (linhas 78-85), só o loop precisa expandir diretório em arquivos.

3. **Ampliar trigger** em `baseline-tamper-guard.yml:15-18` — adicionar:
   ```yaml
   - 'governance/sdd-scorecard-baseline.json'
   - 'governance/knowledge-ghosts-baseline/**'
   - 'governance/required-checks-baseline.json'
   ```

4. **Criar fixtures** em `tests/governance-fixtures/baseline-tamper-guard/{good,bad}/`. Como o gate depende de **história git** (diff BASE..HEAD), não de cwd como os outros — usar o hook `--base` que o script já aceita (linha 28) ou montar um sandbox git temp no selftest (mkdtemp + git init + 2 commits). Sandbox é o caminho honesto: commit base com baseline "apertado", commit head com baseline afrouxado **+** um arquivo de código dummy. `good` = afrouxamento isolado (head sem código) ou com `BASELINE-ABSORB` no commit msg.

5. **Adicionar a catraca ao `gate-selftest.mjs`** (array `CATRACAS`, linhas 82-115). `run(kind)` monta o sandbox git, roda `baseline-tamper-guard.mjs --base <base-sha>` no sandbox. `expect`: `good: /nenhum baseline guardado afrouxado|afrouxamento (isolado|justificado)/`, `bad: /baseline AFROUXADO no mesmo PR que toca código/`.

6. **Rodar** `node scripts/governance/gate-selftest.mjs` → confirmar 12/12. Rodar a prova do DoD §5 manualmente num branch descartável.

## Arquivos a tocar (lista real)
- `scripts/governance/baseline-tamper-guard.mjs` — +3 detectores, estender `GUARDED`, expandir loop pra diretório de ghosts.
- `.github/workflows/baseline-tamper-guard.yml` — +3 paths no trigger.
- `scripts/governance/gate-selftest.mjs` — +1 catraca `baseline-tamper-guard` + runner com sandbox git.
- `tests/governance-fixtures/baseline-tamper-guard/{good,bad}/**` — fixtures novas (dados + README, padrão dos vizinhos em `tests/governance-fixtures/`).
- (opcional, NÃO neste PR) `governance/required-checks-baseline.json` — promover `baseline-tamper-guard` a required é trabalho de P11/P13, não daqui (evita 1-PR-2-intents).

## Gate / counterfactual (COMO provo que o gate MORDE)
**Counterfactual primário (DoD §5):** num branch de teste, editar `governance/sdd-scorecard-baseline.json` mudando uma métrica armada de `armed:true`→`armed:false` (ou `value` na direção de afrouxamento), e tocar um `.php`/`.mjs` qualquer no mesmo commit, **sem** `BASELINE-ABSORB`. Rodar `node scripts/governance/baseline-tamper-guard.mjs --base <sha-antes>` → DEVE dar **exit 1** com a mensagem `baseline AFROUXADO no mesmo PR que toca código`. Reverter só a linha do baseline → **exit 0**.

**Counterfactual no selftest (auto-verificável, vira CI):** a fixture `bad` da nova catraca É esse diff, congelado. `gate-selftest.mjs` exige que ela dê exit 1 pela acusação certa — se alguém um dia neutralizar o tamper-guard, o selftest avermelha (mensagem `CATRACA PAROU DE MORDER`, linha 140). Sanity-check do próprio selftest: `node scripts/governance/gate-selftest.mjs --script baseline-tamper-guard=<cópia-temp-com-exit-1-neutralizado>` deve fazer o selftest falhar (prova que o selftest pega a quebra).

**Por que isso não é teatro:** os 3 ratchets-consumidores já existem mas (a) rodam advisory e (b) cobrem só sua dimensão isolada. O afrouxamento + código-no-mesmo-PR é regra cross-cutting que nenhum deles implementa. A fixture bad prova que o tamper-guard a implementa pros 4 baselines, não só pro memory-health.

## Dependências (e por que)
- `depende_de: []` — tudo que P05 precisa já existe no repo (os 4 baselines, a engine do tamper-guard, o framework de fixtures do selftest). Trabalho self-contained.
- `destrava: [P11, P13]` — promover qualquer gate SDD a **required** (o gap-mãe L3 do GLOBAL) só faz sentido depois que o gate prova que MORDE via selftest. P05 entrega o counterfactual que P11/P13 vão precisar pra justificar o flip a required em `governance/required-checks-baseline.json` sem promover um gate-fantasma.

## Esforço (recalibrado ADR 0106)
- **Codável + IA-pair (10x + margem 2x):** 3 detectores são funções puras pequenas espelhando `detectMemoryHealth` (~10 linhas cada); o runner de sandbox git no selftest é o pedaço com mais atrito (git init + commits programáticos via `spawnSync`). Estimo **~0.5 dia codável**, com margem 2x = **~1 dia**.
- **Relógio humano-limitado:** **zero**. Não depende de secret do Wagner, nem de janela de catraca (14d), nem de N nightlies, nem de aprovação de batch. É código determinístico + fixtures locais. Pode fechar numa sessão.
- A promoção a required (P11/P13) é que terá relógio humano (decisão Wagner de flip + ADR), mas isso está fora de P05.

## Kill-criteria / risco (quando parar ou reabrir)
- **Risco de falso-positivo no detector de required-checks:** o `_meta.limitacao` de `required-checks-baseline.json` avisa que o resumo público da protection clássica não expõe `strict`; o detector deve comparar SÓ `contexts[]` (entrar/sair de contexto), nunca inferir flags ausentes. Se gerar ruído, restringir `detectRequiredChecks` a contextos removidos e parar aí.
- **Risco do sandbox git no selftest:** se montar git temp por fixture ficar frágil em CI (ubuntu), fallback = fixtures como **dois arquivos JSON** (base/head) + flag `--base-file`/`--head-file` no `.mjs` que pula a engine git e injeta os dois lados direto (modo teste). Menos fiel mas determinístico. Decidir no passo 4; não bloquear o PR por causa do sandbox.
- **Kill se:** descobrir que os 3 ratchets-consumidores JÁ viram required E já implementam a regra cross-cutting "afroxou + tocou código" (não vistos no repo hoje — eles só ratcheteam dimensão isolada e rodam advisory). Se isso mudar antes de P05 rodar, reabrir o escopo: pode bastar promover os consumidores a required em vez de estender o tamper-guard.
- **Reabrir se:** um 5º baseline-ratchet nascer (ex.: `module-grades-baseline.json` virar armado-com-ratchet) — o padrão de extensão (detector + GUARDED + trigger + fixture) é o mesmo; documentar isso no header do `.mjs` (linhas 52-54 já apontam "Estender = adicionar...").
