---
date: "2026-08-08"
topic: "Grade de réguas modo delta — 24 fraquezas re-medidas, 15 claims re-vereditadas, e o guard de âncora que faltava na composição"
authors: [C]
---

# Grade de réguas — rodada DELTA 2026-08-08, e a âncora que faltou

> Rodada do ciclo MEDIR (skill `reguas-do-sistema`), modo `delta`, base `353487e48ec` (`origin/main`
> do dia, clone completo). Run `wf_32c91912-fca`: **43 agentes, 0 erro, 10,9M tokens, ~36 min**.
> Último retrato-base: `full` de 2026-07-26.

## O que a rodada rendeu

- **24 fraquezas re-verificadas** no repo vivo, com evidência nova (`fraquezas.json`, `data: 2026-08-08`).
- **15 claims re-vereditadas** por TTL vencido (`claims.json`). Transições **medidas do ledger**, não
  da prosa: **13 `EMPATADO`→`REFUTADO`** · 1 `REFUTADO`→`REFUTADO` · **1 sobreviveu `EMPATADO`**.
  A sobrevivente é `anti-tautologia-armada` — nenhum peer publicado arma **recusa de escrita do
  baseline dentro do próprio instrumento**.
- **11 achados `existia-mas-invisível`** na fila de indexação (`reguas-indexar.mjs`) — o chip mais barato.

## O defeito: a composição saiu vazia depois de gastar tudo

O agente `delta-scan` devolveu `{dims_delta, claims_vencidas, fraquezas}` e **omitiu `ultimo_retrato`**.
No schema `SCAN` o campo era **opcional** (`required` listava só os outros três), então a validação
passou. A composição determinística faz:

```js
const notasAntigas = (scan.ultimo_retrato && scan.ultimo_retrato.notas) || {}
for (const k of Object.keys(notasAntigas)) { /* ... */ }
```

Zero iterações → `notas: {}`, `proveniencia: {}`, `eixos_medidos: []`. E o checkpoint gravou esse
retrato vazio no topo do ledger — **depois** dos 39 agentes de Verificar/Refutar. O trabalho caro
não se perdeu (está em `fraquezas.json`/`claims.json`); só a composição falhou.

Dano colateral do mesmo bug: a prosa do workflow imprimiu *"REFUTADO_TB acumulado: {} — vazio"*,
porque `integHist` veio do mesmo objeto ausente. **É falso** — o `integ_hist` real é
`104 vereditos · 1 REFUTADO_TB · 9 runs` (o arquivo está certo; a narrativa é que mentiu).
Disclosure correto da regra 17: esse acumulado é **herdado** do full de 07-26 — delta não roda
Integração, e nada aqui diz que o braço negativo "agora discrimina".

**Por que o selftest não pegou:** o bloco `[6]` de [`reguas-workflow.test.mjs`](../../scripts/governance/reguas-workflow.test.mjs)
já tinha um caso com `notas: {}` — mas com **0 commits e 0 claims**, então ele saía antes pelo
`nada_a_medir`, por outro motivo. Nunca chegava na composição.

### Conserto (duas travas, porque uma não bastava)

1. `ultimo_retrato` virou **`required`** no schema `SCAN`, com `required: ['data','notas']` aninhado
   — força o campo a vir (validação → retry do modelo).
2. **Guard de âncora** logo após o scan, **antes da fase Verificar**: se `ultimo_retrato.notas` vier
   vazio, aborta com erro nomeado e `acao: 'rodar full'`. Cobre o caso que o schema não pega —
   presente-porém-vazio — e, principalmente, **aborta antes de gastar 10,9M tokens**.
   É a lápide §5 2026-07-29 (*instrumento que não conseguiu medir não afirma*) aplicada a esta máquina.

**Mordida provada:** bite-test no bloco `[8]` do selftest — a forma exata que aconteceu (campo
ausente) + o caso presente-porém-vazio + **4 controles negativos** (âncora boa compõe normal;
checkpoint roda; heartbeat `nada_a_medir` não virou erro; guard não virou bloqueio geral).
Mutação que remove o guard → **6 asserções caem**. Selftest total: **45 ok**.
Ele é invocado em [`governance-script-tests.yml:606`](../../.github/workflows/governance-script-tests.yml) (advisory por desenho, ADR 0314).

## As notas — e por que o Δ NÃO se lê como capacidade

Recompostas à mão com a **mesma fórmula** do script (média 1-decimal das fraquezas re-verificadas
por dimensão) aplicada ao ledger. Entrada conferida por **dois caminhos independentes**: o multiset
das 24 notas **e** dos 24 vereditos é idêntico entre o journal do run e `fraquezas.json`.

| dimensão | 07-26 | 08-08 | proveniência |
|---|---|---|---|
| spec-governanca | 7,5 | **7,5** | re-medida (3) |
| design-to-code | 7,0 | **7,2** | re-medida (3) |
| memoria-conhecimento | 6,8 | **7,7** | re-medida (3) |
| orquestracao-adversarial | 6,5 | **6,7** | re-medida (3) |
| observabilidade-agente | 6,3 | **7,8** | re-medida (2) ⚠️ denominador |
| qualidade-drift-ia-producao | 6,0 | **5,5** | re-medida (3) ⚠️ denominador |
| seguranca-do-agente | 6,0 | **6,0** | re-medida (3) ⚠️ denominador |
| custo-eficiencia | 8,0 | **5,3** | re-medida (2) ⚠️ denominador |
| erp-ia-produto | 5,0 | **4,5** | re-medida (2) |
| catalogo-modulo-opiniao-codigo · evals-outcome · inteligencia-de-negocio | 7,5 · 6,0 · 4,5 | idem | herdadas (sem Δ material) |

⚠️ **Regra 12 em vigor.** Em 4 dimensões o Δ é **mudança de denominador**, não capacidade: cinco
fraquezas passaram de `nota: null` para número nesta rodada (`obs-p50-p95`, `drift-juiz-calibracao`,
`drift-sentinel-tautologico`, `seg-egresso-unguarded`, `custo-forecast-cap`), então a média mudou de
base. O caso extremo é **`custo-eficiencia` 8,0 → 5,3**, que é quase inteiramente a entrada de
`custo-forecast-cap` (3,5) na média — **não é regressão**. Somado a isso, as entradas re-medidas
tinham data anterior **2026-07-18/19**, não 07-26: parte do Δ é correção de ledger stale.

## Achados de conteúdo (das 24 verificações)

- **`spec-governanca`** — o crosswalk regulatório (EU AI Act / ISO 42001 / NIST) que o ledger dava
  como ausente **já existia** desde 19/07 em `memory/governance/ENFORCEMENT.md` §7. Fraqueza fechada
  por evidência, não por trabalho novo — caso clássico de `existia-mas-invisível`.
- **`orquestracao-adversarial`** — avançou em dois eixos, mas **regrediu no braço always-on**: o
  `pr-critic` dispara em todo PR e produz **zero** adversarial, ficando verde. Gate mudo, exatamente
  o padrão que o §5 cataloga em 4 lápides.
- **`qualidade-drift`** — eval ligada em tráfego real (`enabled => true`, [W] 29/07), mas o juiz
  segue com **0 rótulos humanos**: a dívida de calibração cresceu junto com o escopo.
- **Regressões medidas** — `memoria-conhecimento` (bi-temporal com Δ zero), `erp-ia-produto` (captura
  documental sem fechar ciclo), `design-to-code` (Code Connect não injeta em tempo de geração).

## Próximo degrau, do mais barato pro mais caro

1. **Fazer o `pr-critic` produzir ou falhar** — única regressão medida da rodada; custo de configuração,
   não de máquina nova.
2. **Indexar os 11 `existia-mas-invisível`** (`reguas-indexar.mjs --marcar <ids>`) — cada um já vem com
   destino exato. Nenhum pede gate novo.
3. **Primeiros rótulos humanos do juiz** — a eval já roda em tráfego real; falta o denominador.

## Pendências declaradas (não resolvidas aqui)

- As **3 violações V1** do `reguas-ledger-check` são **pré-existentes** (rodadas 07-26 e 07-18:
  `placar` discorda de `claims.json`). Não vieram desta rodada e não foram tocadas.
- O `reguas-ledger-check` **não detecta** retrato com `notas` vazio — o guard novo impede *escrever*,
  mas não há detecção de um já gravado. Candidato a estender o dono do tema (nunca régua paralela);
  **não armado** — two-strikes, 1ª ocorrência conserta, não codifica.
- A **fase Integração não rodou** (delta, por desenho): `integ_hist` é herdado, não medido.
- Eixo **`servir-o-negocio` não foi medido** (1 commit, abaixo do mínimo) — declarado na `cobertura`.
