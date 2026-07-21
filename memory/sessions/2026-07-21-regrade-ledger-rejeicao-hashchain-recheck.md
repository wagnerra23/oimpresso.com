---
date: "2026-07-21"
hour: "23:59 BRT"
topic: "Re-grade da sub-dimensão 'Humano-no-loop + ledger de rejeição' (8,0): hash-chain tamper-evidente + proveniência pinada + re-check de frescor das lápides §5"
authors: ["C"]
tags: [grade, reguas, ledger, rekor, transparency-log, hash-chain, proveniencia, lapides, secao5, proibicoes, adr-0344, adr-0345, append-only]
outcomes:
  - "Gap (a) fechado: transparency-log Rekor-style (governance/ledger-checkpoints.json + ledger-hash-chain.mjs) torna adulteração RETROATIVA de entry selada detectável — SEM editar o ledger append-only (sidecar de checkpoints, não prev_hash por registro)."
  - "Gap (b) parcial→mecanizado: lapide-recheck.mjs re-verifica âncoras das lápides §5 contra o repo vivo (advisory, nunca apaga, não presence-gate). Real §5 hoje: 36 lápides · 28 âncoras intactas · 0 drift · 8 sem-âncora (blind spot honesto)."
  - "Proveniência pinada (crítico + prompt + hash-da-rubrica) por checkpoint — o que o ledger (só veredito) não carregava. Genesis selou 53 entries; smoke real provou que flipar 1 reprovado→aprovado é pego (exit 1)."
  - "Tudo advisory-de-nascença (ADR 0314/0275); promoção a required = decisão [W] futura. §5 e ledger seguem append-only Tier 0 intocados."
---

# Re-grade — "Humano-no-loop + ledger de rejeição" (8,0): hash-chain + proveniência + re-check das lápides

## TL;DR

A sub-dimensão mais forte da grade (8,0, barra = Dosu/Mintlify/Rekor) tinha 2 gaps nomeados pro topo. Os dois viraram **máquina**, sem violar o append-only Tier 0:

- **(a) Hash-chain à prova de adulteração + pino de proveniência.** Um **transparency-log Rekor-style** ([`governance/ledger-checkpoints.json`](../../governance/ledger-checkpoints.json), gerado por [`scripts/governance/ledger-hash-chain.mjs`](../../scripts/governance/ledger-hash-chain.mjs)) sela ranges de entries com uma raiz **cumulativa** encadeada ao checkpoint anterior (`prev_checkpoint_hash`), e **pina a proveniência imutável** (crítico + prompt + `rubrica_sha256`) por lote — o que o ledger, que só guarda o **veredito**, nunca carregou.
- **(b) Re-check de rejeitadas.** [`scripts/governance/lapide-recheck.mjs`](../../scripts/governance/lapide-recheck.mjs) re-verifica se as âncoras que cada lápide §5 cita ainda resolvem no repo vivo — **expondo** potencial staleness pra revisão humana, **sem apagar** (§5 append-only), **sem** virar presence-gate nem catraca de campo auto-declarado.

Todo o mecanismo é **advisory de nascença** (ADR 0314/0275: gate novo nasce advisory; required = só Tier-0 + emenda + flip [W]).

## Os 2 gaps e por que a solução não podia ser ingênua

O ledger `governance/sdd-verification-ledger.json` é **append-only Tier 0** (proibicoes.md · [PROTOCOLO-REFUTADOR-BACKFILL §2.7](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md)): *corrigir = nova entry, NUNCA editar a antiga*. Isso mata o hash-chain "clássico" (cada registro guarda `prev_hash`), que exigiria **reescrever as 53 entries existentes** pra inserir o campo — violação direta do append-only.

**Rekor/Sigstore resolve exatamente isso:** as *entries* do log ficam intocadas; um arquivo **separado de checkpoints** (signed tree heads) commita, num ponto no tempo, a **raiz** de toda a árvore. Adulterar uma entry já selada muda a raiz recomputada e o checkpoint pinado não bate → tamper evidente. Foi o desenho adotado:

| Peça | O quê |
|---|---|
| `entries_root` (por checkpoint) | fold cumulativo `sha256(root_{i-1} + entryHash_i)` sobre `entries[0..cobre_ate)` — a "tree head" |
| `prev_checkpoint_hash` | o **encadeamento** pedido: hash do registro (checkpoint) anterior; genesis = `""` |
| `checkpoint_hash` | auto-hash sobre o conteúdo canônico do próprio checkpoint (detecta adulteração do checkpoint) |
| `provenancia` | `critico` + `rubrica_ref`/`rubrica_sha256` + `prompt_ref`/`prompt_sha256` — o pino de proveniência (gap 2) |

Serialização **canônica** (chaves ordenadas) → hash independe da ordem das chaves (as entries do ledger têm ordens diferentes: `pr` primeiro numas, `tipo` primeiro noutras). **Zero `Date.now`/`Math.random`**: `--data` é obrigatório no build (a data do checkpoint vem do operador, nunca é gerada) — a regra "sem timestamp em script de governança" virou fail-fast (exit 2 sem `--data` válida).

### Garantia HONESTA (a mesma do Rekor)

Só o que está **selado** por um checkpoint é tamper-evidente. Entries novas ficam "não-pinadas" até o próximo build selá-las (reportado, nunca escondido). O **genesis selou toda a história atual (53 entries) de uma vez**; adulteração retroativa de qualquer uma delas agora é pega.

## Evidência (smoke real, não narração — R1)

```
$ node scripts/governance/ledger-hash-chain.mjs --build --data 2026-07-21 --genesis
✓ checkpoint seq=0 sela entries [0..53) (53 lote(s))
  entries_root=43b271d07932de50…  checkpoint_hash=d683cc9da18758bd…  prev=(genesis)
  proveniência: crítico="múltiplos — ver entries[].refutador" · rubrica=PROTOCOLO-REFUTADOR-BACKFILL.md (a7121be814af…)

$ node scripts/governance/ledger-hash-chain.mjs --verify
  entries: 53 · selados: 53 · não-pinados: 0 · checkpoints: 1
  🟢 íntegro — a raiz de cada checkpoint bate com o ledger vivo e a corrente está intacta.

# TAMPER (cópia): flipar 1 entry reprovado→aprovado (a fraude exata que §6 do protocolo teme)
$ node scripts/governance/ledger-hash-chain.mjs --verify --check --ledger <cópia-adulterada>
  🔴 [seq 0 · entries_root] entries_root recomputado != pinado — entry adulterada desde a selagem
  exit=1  ✔ (morde)
```

O selftest hermético ([`ledger-hash-chain.test.mjs`](../../scripts/governance/ledger-hash-chain.test.mjs), 24 checks) prova que a corrente morde **cada** vetor: adulterar entry selada, adulterar conteúdo de checkpoint, quebrar a corrente, remover entry; e que **append legítimo na cauda NÃO falha** (só o selado é tamper-evidente).

## Gap (b) — re-check das lápides §5: honesto e à prova das próprias lápides

O registro de rejeição (§5 do `proibicoes.md`, "Ideias avaliadas e DESCARTADAS") também apodrece: uma lápide mata uma ideia citando âncoras concretas (um script, um gate, "agora é máquina via X"); se X é deletado, a premissa **pode** ter mudado — e ninguém re-lê.

`lapide-recheck.mjs` extrai as âncoras de arquivo de cada lápide e re-verifica se resolvem no repo vivo. O desenho respeita **as próprias lápides §5** que ele poderia virar:

- **NÃO apaga/edita** (§5 append-only Tier 0 — só surfaça pra revisão humana).
- **NÃO é presence-gate** (resolve o CONTEÚDO citado, não "a seção existe" — lápides §5 07-01/07-09/07-16).
- **NÃO é catraca de campo auto-declarado** (não grava `verificado_em`/`last_validated` — lápides §5 07-01/07-09; re-deriva do repo a cada corrida).
- **NÃO bloqueia** (report-only, exit 0 sempre — a tarefa proíbe virar gate).
- **NÃO duplica** o `briefing-code-staleness` (corpus e sinal diferentes: âncoras do §5 vs BRIEFING↔mtime).

### A armadilha do falso-positivo (pega e morta na 1ª corrida)

A 1ª versão do resolver acusou **6 lápides** com "âncora sumida". Verifiquei uma a uma: **5 eram falso-positivo** — a exata doença que o §5 (lápide 06-30 "guard sintático que barra o legítimo") condena:

| Citação | Realidade |
|---|---|
| `_DesignSystem/SAFE-SELECT-ITEM.md` (shorthand de backtick) | existe em `memory/requisitos/_DesignSystem/…` |
| `Sells/Index.casos.md`, `Sells/_components/CustomerSearchAutocomplete.tsx`, `kb/Index.v2.charter.md` | existem sob `resources/js/Pages/…` |
| `../../.claude/workflows/reguas-do-sistema.js` (link com `../` errado) | existe em `.claude/workflows/…` |
| `decisions/0275-calendario-promocao-gates-sdd.md` | ADR **0275 existe** — só o **slug** driftou (`0275-scorecard-sdd-…`) |

Resolver endurecido pra matar cada classe de FP (todas testadas hermeticamente): **strip de `../` líder** (link com profundidade errada), **suffix-match no `git ls-files`** (shorthand de subdir), e **ADR por NÚMERO** (slug-drift ≠ premissa-drift — ADR é endereçado por número, e superseded continua no disco). Resultado no §5 vivo:

```
§5 tem 36 lápide(s) · âncoras intactas: 28 · sem âncora de arquivo: 8 · REVISAR (drift): 0
🟢 nenhuma lápide com âncora driftada — as premissas ancoradas resolvem no repo vivo.
```

Verde **honesto**: toda âncora citada resolve hoje. Não é teatro — o selftest prova que uma âncora **de fato deletada** dispara `revisar`. É um check **vivo** que morde quando um gate/script que uma lápide reivindica for removido.

## Rótulo honesto (senão o próprio re-check vira o LC-08 "afirmar-sem-medir")

A **detecção** do drift é mecânica; o **julgamento** ("a premissa ainda vale?") é **humano** — espelha o auto-feed §5↔ledger ([`licoes-code-two-strikes.mjs`](../../.claude/hooks/licoes-code-two-strikes.mjs)): a máquina surfaça, o humano decide. Uma âncora sumida NÃO declara a lápide "stale" — marca `revisar`, pra re-leitura. Se a premissa mudou, o caminho é **nova lápide/emenda (ADR)**, nunca editar/apagar a antiga.

## Residuais honestos (o que NÃO foi resolvido)

- **8 lápides §5 sem âncora de arquivo** = blind spot do re-check (premissa pura-prosa sem arquivo citado não tem sinal mecânico). Reportado como categoria própria, não escondido.
- **Cauda não-pinada:** entries adicionadas após o último checkpoint só ficam tamper-evidentes quando o próximo `--build` as selar (propriedade inerente do Rekor; reportada).
- **Genesis não reconstrói proveniência por-lote histórica:** as 53 entries anteciparam o pino; o genesis pina a rubrica ATUAL como "em vigor na selagem" (`historico:true`) + `critico="ver entries[].refutador"`. Proveniência por-lote **real** começa nos checkpoints seguintes (`--build --critico … --rubrica … --prompt …` por lote).
- **Nada disso é required.** Promover `--verify --check` a gate bloqueante do ledger é decisão [W] (emenda ADR 0314 + flip), não deste PR.

## Delta na sub-dimensão (re-grade)

Antes: humano-no-loop forte, mas o ledger era **confiável por convenção** (append-only "revisado como código no diff") e a proveniência da rubrica/prompt **não existia**; o registro de rejeição não tinha frescor. Depois: adulteração retroativa é **criptograficamente detectável**, a proveniência por-lote é **pinável e imutável**, e o registro de rejeição tem um **re-check vivo**. Os 3 pontos que a barra (Rekor/transparency-log) exige do topo passaram de "cultura" pra "máquina" — mantendo o append-only Tier 0 e sem cair em presence-gate/catraca-auto-declarada.

Uma **re-grade adversarial completa** (workflow `reguas-do-sistema`) segue como follow-up disparável por [W] — este log documenta o delta desta leva, não substitui o juiz adversarial.

## Arquivos

- `scripts/governance/ledger-hash-chain.mjs` (+ `.test.mjs`) — transparency-log: build/verify/proveniência.
- `governance/ledger-checkpoints.json` — sidecar append-only (genesis selando 53 entries).
- `scripts/governance/lapide-recheck.mjs` (+ `.test.mjs`) — re-check de frescor do §5.
- `.github/workflows/governance-script-tests.yml` — 4 steps novos (2 selftests + `--verify` advisory + relatório advisory).
