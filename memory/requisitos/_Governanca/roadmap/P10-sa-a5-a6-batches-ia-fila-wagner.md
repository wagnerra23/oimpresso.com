---
roadmap_item: P10
slug: sa-a5-a6-batches-ia-fila-wagner
onda: 4
status: proposed
depende_de: [P09]
destrava: [P13]
related_adrs: [273, 275]
esforco_estimado: "~3-4d codável + IA-pair (motor + 56 batches) · relógio real dominado pela fila Wagner A6 (~2-8h humano em N janelas) + janela de promoção required (1/semana, ADR 0275 §5)"
---
# P10 · SA-A5/A6: batches IA restantes + fila Wagner + ledger-check a --enforce

> ⚠️ **2026-07-04:** o trabalho está majoritariamente ENTREGUE (waves 1-3 + lotes A/B/C + BALDE D), mas o DoD **não fechou** (itens 1 e 5) — ver seção **«Estado 2026-07-04 (reconciliação)»** no fim do doc. Os números abaixo (7% · `sem_campo` 728 · 823 US) são o retrato de 2026-06-12 — histórico preservado append-only, NÃO o estado vivo.

## Problema (o que está quebrado, em 2-3 frases)
A frente SA-A5 (backfill IA de anchors SPEC↔código) rodou **só o batch piloto** (1 de ~57 módulos): `anchor_coverage` está em **7%** vs 100% exigido. A fila humana de ambíguos (SA-A6, `_ANCHOR-REVIEW-QUEUE.md`) **nunca foi materializada** — não existe no repo. E o gate que deveria impedir lote IA não-verificado de entrar (`ledger-check.mjs`) roda **advisory + continue-on-error** no umbrella, então um lote sem refutação adversarial passa verde.

## Causa-raiz (evidência VERIFICADA — file:line reais que confirmei)
1. **Só batch1 rodou.** `governance/sdd-verification-ledger.json` tem **4 entries** no total; a única SA é `SA-A5-batch1-pg-pmg-kb` (PR #2970, `tipo: anchors`, `itens_verificados: 16`, `veredito: aprovado`). As outras 3 são da frente KL (E2/E3). Confirmado via parse do JSON.
2. **anchor_coverage = 7%.** `governance/sdd-scorecard.json` métrica `anchor_coverage`: `value: 7`, `target: 100`, `detail.specs_total: 57`, `detail.us_total: 823`, `by_state.anchored_ok: 35`, `anchored_dead: 15`, `sem_campo: 728`, `placeholder: 22`, `pendente: 23`. Fonte única = `anchor-lint.mjs --json .anchor_coverage_pct` (ADR 0273 §2).
3. **ledger-check é ADVISORY no umbrella.** `.github/workflows/governance-gate-umbrella.yml:52-55` — step `ledger-check — refutação de lote IA (advisory · GT-G5)` roda `node scripts/governance/ledger-check.mjs --pr ... --base ... --head HEAD` **sem `--enforce`** e com **`continue-on-error: true`** (linha 55). O próprio script: `scripts/governance/ledger-check.mjs:129` → `process.exit(ok || !ENFORCE ? 0 : 1)` — sem `--enforce`, **sempre exit 0**.
4. **O gate MORDE em fixture, mas só com `--enforce`.** `scripts/governance/gate-selftest.mjs:97-103` invoca `ledger-check` com `--enforce` sobre fixtures `tests/governance-fixtures/ledger-check/{good,bad}/ledger.json` (+ `files.txt` com 11 paths > threshold 10) e exige `good: /entry valida no ledger/` e `bad: /FAIL ledger-check/`. Ou seja: a lógica refuta de verdade (rank refutador≥gerador em `:63-67`, `error_rate_pct < 2` em `:58-60`, `pii_scan/pii_hits` em `:61-62`); o que NÃO morde é a chamada no umbrella.
5. **SA-A6 (fila Wagner) inexistente.** Busca `find . -iname "*anchor-review-queue*"` (fora de node_modules/worktrees) = **zero**. O artefato é só citado como destino-de-saída no motor (`.claude/workflows/sdd-fase-2.js:63` e `:162 gated_wagner: 'skim ... fila A6'`) e no plano-mãe (`memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md:29,79`). O batch1 confirmou 23 anchors / 0 mentira / 0 incerto, logo **nunca disparou o gatilho de ambíguo** → fila nunca nasceu.
6. **Risco §103 confirmado.** `memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md:103`: "Fila A6 subdimensionada (se ambiguidade real for 20-25% e não 9%) → ... se fila >150, parar e melhorar evidência." A taxa de ambiguidade ainda NÃO foi publicada (o piloto deu 0 ambíguos em 23, amostra pequena demais p/ extrapolar).

## Estado atual no repo (o que achei ao verificar agora)
- Motor existe e funciona: `.claude/workflows/sdd-fase-2.js` contém o prompt SA-A5 piloto (`:59`), o refutador G5 adversarial (`:131`), e já referencia `_ANCHOR-REVIEW-QUEUE.md` e `gated_wagner` (`:162`). **Mas o prompt está hard-coded p/ "5 PRIMEIROS módulos / piloto"** — não há loop/batch sobre os 56 restantes.
- `scripts/governance/anchor-lint.mjs` existe (11.450 bytes). Regex de heading US: `:42 US_HEAD_RE = /^(#{2,4})\s+.*\bUS-[A-Z][A-Za-z0-9]*-\d/` — explica por que os 7 `PMG-00x` confirmados pelo piloto ficaram FORA do lote (heading `PMG-` ≠ `US-`, não-canônico). **Divergência menor:** isso significa que parte do universo de 823 US pode ter IDs não-`US-` que o anchor-lint nem conta como coberto — precisa ser mapeado no batch.
- `_ANCHOR-REVIEW-QUEUE.md`: **não encontrado no repo.**
- `memory/requisitos/_Governanca/roadmap/`: dir recém-criado, vazio (este é o primeiro item de roadmap a aterrissar aqui).
- ADR 0275 §5 (`memory/decisions/0275-...md:72-79`): calendário de promoções — **máx 1 promoção/semana**, vaga não acumula, toda promoção exige critérios objetivos + evidência linkada (runs) + PR atualizando `required-checks-baseline.json` citando o ADR.
- **Divergência de número:** o prompt do motor (`sdd-fase-2.js:19`) cita `anchor_coverage 2.8%` como referência de Fase 1; o scorecard atual mede **7%** pós-batch1. Não é erro — é a régua subindo (2.8→5.4→7.5% registrado na evidência do ledger). O plano re-deriva do scorecard vivo, não do prompt.

## Objetivo / DoD (critério de pronto OBJETIVO e checável)
1. **anchor_coverage do scorecard ≥ alvo de ondas** — não necessariamente 100% (telas nunca construídas viram `_pendente_`, que JÁ conta como coberto por `anchor-lint.mjs:19`). DoD objetivo: `by_state.sem_campo` (hoje 728) → próximo de 0; `anchored_dead` (hoje 15) **não cresce**; cada lote tem 1 entry no ledger.
2. **1 entry de ledger por lote IA**, todas `veredito: aprovado`, `error_rate_pct < 2`, `pii_hits: 0`, refutador rank ≥ gerador, `tipo: anchors` com `amostra_pct: 100` (exigência de `ledger-check.mjs:68-69` p/ anchors).
3. **`_ANCHOR-REVIEW-QUEUE.md` materializado** com os ambíguos (formato do motor `:63`: módulo · US · candidatos · evidência conflitante · decisão pendente) — OU prova de que a fila ficou vazia (taxa de ambiguidade ≈0 publicada com universo ≥5 módulos reais, não só o piloto).
4. **Taxa de ambiguidade publicada** após cada bloco de ~5 módulos (gatilho §103: se >20-25% ou fila projetada >150, PARAR e melhorar evidência do prompt antes de continuar).
5. **`ledger-check` promovido a `--enforce` + required** seguindo ADR 0275 §5 (1 promoção/semana): remover `continue-on-error: true` de `governance-gate-umbrella.yml:55`, adicionar `--enforce` na linha 54, e adicionar o check context a `required-checks-baseline.json` via PR citando ADR 0275/0273.

## Passos (ordenados, concretos)
1. **Generalizar o motor.** Em `.claude/workflows/sdd-fase-2.js`, extrair o prompt SA-A5 piloto (`:59-68`) p/ um loop sobre lista de módulos derivada de `anchor-lint.mjs --json` (todos com `sem_campo > 0`), batches de ~5 módulos. Cada batch: gerador → refutador G5 (`:131`) → 1 entry no ledger (lote_id `SA-A5-batchN-...`) → publica taxa de ambiguidade.
2. **Re-derivar o universo do main vivo** (regra anti-stale §107): `node scripts/governance/anchor-lint.mjs --json` → lista de SPECs com `sem_campo`/`placeholder` > 0. NÃO usar os números do prompt (2.8%) nem deste plano (7%) como verdade — re-medir.
3. **Rodar batches em ordem, com gatilho §103.** Após cada bloco de 5 módulos: se taxa de ambiguidade ≤ ~9% (hipótese do plano), seguir; se 20-25% ou fila projetada >150, PARAR, melhorar evidência do prompt (charter + git log + árvore Pages), re-rodar o bloco.
4. **Materializar `_ANCHOR-REVIEW-QUEUE.md`** acumulando os ambíguos de cada batch (estado de 1ª classe — não inventar anchor na dúvida; `sdd-fase-2.js:63`).
5. **Partição anti-colisão (DAG §57):** garantir que SA-A5 (adiciona campo AUSENTE) nunca toca o mesmo SPEC.md que SA-A4 (edita campo EXISTENTE) no mesmo PR — checagem do refutador-orquestrador (`sdd-fase-2.js:146`).
6. **PII scan diff-only** em cada lote (repo público — `ledger-check.mjs:61-62` exige `pii_scan: true`, `pii_hits: 0`).
7. **Fila Wagner A6 (humano):** Wagner decide a fila em batches de 20 (~30min/batch, plano `:97`). Para cada decisão: promover candidato a anchor estrito OU `_pendente_` definitivo. Re-roda anchor-lint após.
8. **Promover ledger-check a enforce (ADR 0275 §5):** após ≥2 semanas civis com o gate verde advisory + critérios objetivos linkados → PR único: (a) `governance-gate-umbrella.yml:54` ganha `--enforce`, (b) remove `:55 continue-on-error: true`, (c) `required-checks-baseline.json` ganha o context, citando ADR 0275/0273. Máx 1 promoção/semana.

## Arquivos a tocar (lista real)
- `.claude/workflows/sdd-fase-2.js` — generalizar prompt piloto → loop de batches (foco do esforço codável).
- `governance/sdd-verification-ledger.json` — append-only: 1 entry por batch (NÃO editar entries antigas; `_meta.regra`).
- `memory/requisitos/**/SPEC.md` — adicionar campo `**Implementado em:**` nas US `sem_campo` (728 hoje) — gerado pelo motor, NÃO à mão.
- `memory/requisitos/_ANCHOR-REVIEW-QUEUE.md` — **criar** (fila A6, inexistente).
- `.github/workflows/governance-gate-umbrella.yml` — passo 8: `:54` +`--enforce`, remover `:55 continue-on-error`.
- `required-checks-baseline.json` — passo 8: registrar o context (raiz; verificar path exato no PR — referenciado por ADR 0275 §5 / GT-G4).
- (NÃO tocar) `scripts/governance/ledger-check.mjs` — já morde; só muda a forma de invocá-lo.

## Gate / counterfactual (COMO provo que o gate MORDE)
**O gate já existe e já é testado** — o que falta é ligá-lo. Counterfactual em 2 níveis:
1. **Script (já provado):** `gate-selftest.mjs:97-103` roda `ledger-check --enforce` na fixture `bad/ledger.json` e exige saída `/FAIL ledger-check/`. Esse selftest está no CI (`.github/workflows/gate-selftest.yml:37`). Prova viva de que a lógica refuta. Diff que deve dar **exit 1** hoje: abrir PR de lote (>10 arquivos em `memory/requisitos/`) SEM entry no ledger → `ledger-check --enforce` retorna exit 1 (`ledger-check.mjs:99,129`).
2. **Workflow (o que este item entrega):** após o passo 8, abrir um PR-canário que adiciona 11+ arquivos em `memory/requisitos/` sem entry de ledger correspondente. **DoD do gate: o check `Governance Gate (umbrella)` fica VERMELHO e bloqueia o merge** (hoje fica verde por `continue-on-error`). Reverter o canário = verde. Se ficar verde com 11 arquivos sem ledger → o enforce não pegou, reabrir.
3. **Anti-stale:** rodar `anchor-lint --json` antes/depois de cada batch e colar `anchor_coverage_pct` (regra do motor `sdd-fase-2.js:67`). `anchored_dead` não pode subir (anchor novo apontando p/ path inexistente = mentira).

## Dependências (e por que)
- **P09 (depende_de):** este item assume que a infra MEDIR→GOVERNAR está fechada — o write-side do scorecard/floor precisa aterrissar no tree de main (risco-mãe do contexto global: floor publica em branch órfã `governance/nightly-floor`, read-side lê do main). Promover ledger-check a required SEM o scorecard publicando no main é prematuro. P09 destrava o elo.
- **ADR 0273:** gramática do anchor (formato `**Implementado em:**`, sentinelas `_pendente_`/`_parcial_`, regex US) — o motor gera nesse formato; afrouxar exige novo ADR.
- **ADR 0275 §5:** o calendário de promoções É a dependência de relógio do passo 8 (1/semana, evidência linkada).
- **Destrava P13:** com a fila A6 materializada e o ledger-check mordendo, P13 (provável fechamento da onda / promoção de mais gates SDD a required) ganha o precedente de "gate SDD required que morde de verdade" — hoje são 0 dos ~18 required.

## Esforço (recalibrado ADR 0106)
**Codável (10x + margem 2x, IA-pair):**
- Generalizar o motor (loop de batches) + PII scan + orquestração: ~0.5-1d.
- Rodar os ~56 batches restantes (gerador+refutador, centavos de Haiku/Sonnet por módulo; plano `:79`): ~1.5-2.5d de relógio de execução de agentes (paralelizável, mas cada batch precisa do refutador G5 em sessão fresca — serialização parcial).
- Materializar `_ANCHOR-REVIEW-QUEUE.md` + entries de ledger: incluído nos batches.
- Promover ledger-check a enforce (editar 2 linhas YAML + baseline): ~0.25d.
- **Subtotal codável: ~3-4d.**

**Humano-limitado (relógio do mundo real, NÃO comprime):**
- **Fila Wagner A6:** se ambiguidade real ≈9% sobre 823 US → ~75 itens → ~4 batches de 20 × 30min ≈ 2h. Se for 20-25% (risco §103) → ~150-200 itens → ~5-10 batches → **estoura, e o plano manda PARAR**. Distribuído em N janelas de Wagner, dias de calendário, não horas contíguas.
- **Promoção a required:** ADR 0275 §5 = máx 1/semana + ≥2 semanas advisory limpo antes → **relógio fixo de ~2-3 semanas civis** independente de quão rápido o código fica pronto.
- skim Wagner dos lotes IA (10min/lote, plano `:97`).

## Kill-criteria / risco (quando parar ou reabrir)
- **PARAR (gatilho §103):** taxa de ambiguidade > ~20-25% após qualquer bloco de 5 módulos, OU fila A6 projetada > 150 itens → não continuar batches; melhorar evidência do prompt (charter+commit+árvore Pages) e re-rodar o bloco. Estourar a fila Wagner é regressão de throughput, não progresso.
- **NÃO promover a enforce se:** P09 não fechou o elo MEDIR→GOVERNAR (scorecard não publica no main), OU o gate teve qualquer vermelho-por-bug nas 2 semanas de janela (precedente visual-regression, ADR 0275 §5 / `:95`), OU `required-checks-baseline.json` não pôde ser atualizado no mesmo PR.
- **REABRIR se:** após enforce, um lote IA real entrar no main com anchor mentiroso (`anchored_dead` sobe sem PR que o justifique) — sinal de que o refutador G5 está sendo burlado (sessão não-fresca, rank invertido). Auditar a entry e o `sessao_fresca`.
- **Risco de identidade (gated em E1/KL):** SPECs de pastas marcadas FUNDIR/MATAR em `_TRIAGEM-IDENTIDADE-2026-06.md` NÃO devem receber anchor (`sdd-fase-2.js:52`) — aguardam decisão Wagner da trilha E. Backfillar anchor num módulo que vai morrer é retrabalho.

## Ordem de prioridade dos batches (valor de negócio × buraco) — sugestão 2026-06-21

Derivado de `anchor-lint.mjs --json` (estado pós-#3176): coverage **8.9%** · `sem_campo` 751/847. Ordem recomendada pro loop de batches do motor (Passo 1), priorizando **valor de negócio × nº de US sem anchor** — ancorar primeiro onde a confiança rende mais:

| # | Módulo | US | cov% | sem anchor | racional |
|---|---|---:|---:|---:|---|
| 1 | **Sells** | 47 | 0% | 47 | núcleo de receita (POS/vendas), **zerado** — maior ROI de confiança por US |
| 2 | **Financeiro** | 51 | 25.5% | 38 | dinheiro; já começou (4 ok) → terminar |
| 3 | **Whatsapp** | 72 | 0% | 72 | maior buraco absoluto, core de operação |
| 4 | **OficinaAuto** | 48 | 0% | 48 | vertical com cliente pagante ativo |
| 5 | **NfeBrasil** | 34 | 29.4% | 24 | fiscal, meio-caminho |
| 6+ | RecurringBilling (37), Inventory (25), Crm (23), ComunicacaoVisual (18), Essentials (11), Compras (10) | — | 0–10% | — | cauda |

**Molde de "como ancorar bem"** (copiar o padrão): Vestuario (9 ok, 42.9%), KB (85.7%), ProjectMgmt (77.8%).
**Regras:** `anchored_ok` exige path existente + carimbo `verificado@<sha>`; tela não-construída = `_pendente_` (já conta como coberto); **nunca inventar path** (vira `anchored_dead` = mentira). Re-rodar `anchor-lint --json` antes/depois de cada batch; `anchored_dead` não pode subir.

_(Fonte: auditoria de saúde/integridade 2026-06-21 — priorização derivada do ranking por uso. Sells tem SPEC própria em `memory/requisitos/Sells/`.)_

---

## Estado 2026-07-04 (reconciliação)

> **Por que o frontmatter segue `status: proposed`:** o vocabulário dos irmãos deste roadmap é binário (`proposed`/`executed` — ver P01/P03/P09) e o DoD deste item **não fechou** (itens 1 e 5 abaixo). O [_ROADMAP.md](_ROADMAP.md) marca P10 como 🟡 **em curso** — este é o estado honesto. `executed` só quando o residual e a promoção do `ledger-check` aterrissarem. Seção append-only: nada acima foi reescrito.

### O que já LANDOU (verificado em origin/main + ledger + fila A6, 2026-07-04)

- **Waves 1-3 + lotes A/B/C + BALDE D (2026-07-02) mergeados:** batch1 Sells (#3483) · Financeiro (#3539) · Jana (#3543) · OficinaAuto (#3541) · Whatsapp (#3546) · wave 2 (#3571-3577, #3580) · lote A Ponto/Marketplaces/Infra (#3630-3632) · wave 3 lote B NFSe/Autopecas/ComunicacaoVisual (#3627/#3628/#3638) · charters (#3633-3636) · LOTE C 13 módulos (#3642) · **BALDE D** AssetManagement/Auditoria/ConsultaOs/Arquivos (#3661/#3662/#3663/#3664).
- **Ledger (DoD 2 ✅):** `governance/sdd-verification-ledger.json` tem **48 entries, 41 do tipo `anchors`** (era 1 quando este doc nasceu) — 1 entry por lote, refutador G5 tier-superior (Fable), lotes reprovados na 1ª rodada registrados e re-aprovados a 0%.
- **Fila A6 materializada (DoD 3 ✅ + DoD 4 ✅):** [`memory/requisitos/_ANCHOR-REVIEW-QUEUE.md`](../../_ANCHOR-REVIEW-QUEUE.md) existe com taxa de ambiguidade publicada por lote (§1): **agregada <1% ≪ gatilho 20-25% do §103** sobre 29 módulos reais — a fila §2 de ambíguas está vazia por prova, não por omissão. Inventory parkeado honesto via `_pendente_` (FUNDIR na triagem de identidade).
- **Motor generalizado (passos 1-4 ✅):** o loop de batches rodou por waves — o prompt não é mais só o piloto de 5 módulos.

### Estado LIVE (`node scripts/governance/anchor-lint.mjs --json`, 2026-07-04, árvore = origin/main)

| Métrica | Plano (06-12) | Sugestão (06-21) | Roadmap (07-02) | **LIVE 07-04** |
|---|---:|---:|---:|---:|
| `anchor_coverage_pct` global | 7% | 8.9% | 88.9% | **85.6%** |
| `sem_campo` | 728 | 751 | ~100 | **136** |
| universo (US / SPECs) | 823 / 57 | 847 | — | **946 / 59** |
| `anchored_dead` | 15 | — | 0 | **0** |
| `placeholder` | 22 | — | 0 | **0** |
| `anchored_ok` / `parcial` / `pendente` | 35 / — / 23 | — | — | **322 / 119 / 369** |
| módulos a 100% | — | — | — | **33 de 59** |

**A "queda" 88.9% → 85.6% NÃO é regressão de anchor** (`anchored_dead=0` estável, `anchored_ok=322`): é o denominador crescendo — 946 US hoje vs 823 do plano; US nova nasce `sem_campo` até o lote seguinte. Cobertura é razão viva sobre universo vivo, não catraca deste doc (a catraca é `anchored_dead` não subir — mantida).

### Residual REAL (por que NÃO é `executed`)

1. **DoD 1 aberto — 136 US `sem_campo` em 19 módulos.** Maiores: Governance 33 · TaskRegistry 16 · Vestuario 12 · Compras 10 · Essentials 10 · EvolutionAgent 7 · NFSe 7 · Produto 7 · Accounting 6 · SRS 6 · LaravelAI 5 · cauda ≤4 (PaymentGateway/Sells/Fiscal/MemoriaAutonoma/ProjectMgmt/Financeiro/KB/RecurringBilling). **~36 dessas US estão GATED na trilha E (identidade)** — TaskRegistry/EvolutionAgent/LaravelAI/MemoriaAutonoma (+SRS zumbi) aguardam decisão Wagner FUNDIR/MATAR; ancorar antes é retrabalho (regra §"Risco de identidade" acima). Restam **~100 US ancoráveis** nos módulos não-gated.
2. **DoD 5 aberto — `ledger-check` segue ADVISORY no umbrella.** `.github/workflows/governance-gate-umbrella.yml:52-55` ainda roda SEM `--enforce` e COM `continue-on-error: true` (verificado 2026-07-04); o context não está no `required-checks-baseline.json`. A promoção segue o passo 8 (ADR 0275 §5 — 1/semana, flip Wagner, nunca no calado).

### Próximo passo deste item

(a) lotes finais dos ~100 US não-gated (Governance/Vestuario/Compras/Essentials/NFSe/Produto/Accounting/cauda); (b) trilha E decide os ~36 gated; (c) promoção do `ledger-check` a `--enforce`+required (passo 8). Só então `status: executed`.
