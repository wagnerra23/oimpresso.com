---
date: "2026-07-24"
hour: "18:30 BRT"
duration: "1.0h"
topic: "Verificação medida do transcript 'gestão de documentação' — 7 afirmações confirmadas, 6 erradas, e o P0 do plano já existe como ADR 0302 + doneness-lint required"
authors: [F, C]
outcomes:
  - "P0 do plano proposto (A0 'uma fonte por fato' + A1 'status: não-digitável') REFUTADO como trabalho novo — já é ADR 0302 (aceita 22/06) + doneness-lint REQUIRED desde 30/06"
  - "6 números do transcript corrigidos com recibo (casos.md 4/8 não 2/8; casos-gate 5 gates não 7; distiller 11/78 carimbadas não 0/76; grandfathered 647 não 655)"
  - "Distiller: métrica distiller_freshness=0 é verde-que-não-pode-ficar-vermelho — 67 das 78 portas não têm carimbo pra poder ficar stale"
  - "B1 (rodar sdd-from-source no Produto) sobrevive intacto como próximo passo"
  - "ERRATA §2: as datas de criação dos casos.md na 1ª redação vieram de clone RASO (piso 23/07) — corrigidas pós --unshallow (57 → 5.682 commits); LC-08 cometido por mim dentro do log que o denuncia"
  - "Viabilidade medida (§7): Produto faltam 4 telas (~1-2 semanas); os 240 restantes = ~2 anos no ritmo atual (2-3/sem, caindo do pico 15) — e o gargalo é a prova (32/159 UCs execução-backed), não o markdown"
prs: []
us: ["US-PROD-020", "US-PROD-023"]
related_adrs: ["0302-fonte-unica-doneness-anchor-aposenta-status-spec", "0351-sdd-from-source", "0352-errata-0351-venue-distiller-citacao-taxonomia", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo"]
---

# Session log 2026-07-24 — Verificação do transcript "gestão de documentação"

## TL;DR

[F] trouxe o transcript integral de uma sessão sobre gestão de documentação (grade de
mercado → crítica dos artefatos do oimpresso → plano de aplicação) e pediu "verifique o
projeto". Verifiquei **cada afirmação checável** contra `origin/main` usando as **portas
vivas**, não leitura.

**Resultado:** 7 afirmações confirmadas, 6 erradas ou imprecisas, e **o P0 do plano já
existe e já é lei há um mês** — construí-lo seria duplicar régua consolidada (§5
`proibicoes.md`).

> **Regime de medição.** Tudo abaixo foi medido em **2026-07-24** contra `origin/main`
> (checkout local estava −6 commits; leitura via `git show origin/main:<path>` +
> execução dos scripts, que são Node puro sem deps). Cada número carrega o **comando que
> o produziu** — são medições datadas, não afirmações atemporais. Se um número incomodar,
> **re-rode o comando; não edite o número** (lápide §5 2026-07-17 "oráculo errado").

---

## 1. Confirmado (com recibo)

| Afirmação do transcript | Medido | Comando |
|---|---|---|
| `ANTI-REGRESSAO-cadastro-produto-legacy.md` = 476 linhas | **476** | `git show origin/main:memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md \| wc -l` |
| `BRIEFING.md` do Produto = 138 linhas | **138** | idem sobre `BRIEFING.md` |
| `US-PROD-020` está `status: todo` no SPEC | **✔ (L55)** | `git show origin/main:memory/requisitos/Produto/SPEC.md` |
| Headline anchor 84,1% × ~34% real | `anchor_coverage 84.1%` vs `anchored_ok 334/981` = **34,0%** | `node scripts/governance/anchor-lint.mjs --check` |
| Anchor do Produto = 11,1% | **11.1** (exato) | idem, linha `🟡 Produto` |
| `ANTI-REGRESSAO` sem sensor de CI | **zero hits** | `git grep -l 'ANTI-REGRESSAO' origin/main -- 'scripts/**' '.github/workflows/**'` |
| PRs #4759/60/61/65/66/67/70 + ADR 0351/0352 (`aceito`, [W], 24/07) | **todos existem**, descrições batem | `git log origin/main --grep="(#N)"` |

**Não verificável nesta sessão:** o lado *"`done` no board desde 14 e 17/07"* das
US-PROD-020/023 — MCP indisponível (hook `brief-fetch` caiu em fallback). Registrado como
**lacuna honesta**, não como confirmação nem como refutação.

---

## 2. Errado ou impreciso (6)

1. **"só 2 das 8 telas têm `casos.md`"** (Resposta 2) e **"casos 3/7"** (Resposta 6) →
   real **4 de 8**: `Create` (4 UCs), `Edit` (4), `SellingPrices` (4), `StockHistory` (3).
   Charter é **8/8**. O denominador é **8 telas**, não 7.
   *Recibo:* `git ls-tree -r --name-only origin/main resources/js/Pages/Produto/` +
   `grep -cE '^## UC-'` por arquivo. Datas reais de criação (medidas em clone COMPLETO —
   ver errata abaixo): `SellingPrices` 15/07 (#4300) · `Create` 19/07 (#4417, US-PROD-020) ·
   `StockHistory` 22/07 (#4658) · `Edit` 24/07 (#4767, piloto). Ou seja, **3 dos 4 já
   existiam antes** do transcript ser escrito — "2 de 8" nasceu stale.

   > ⚠️ **ERRATA (mesma sessão, 2026-07-24 · LC-08 cometido por mim).** A 1ª redação
   > desta linha dizia *"os outros 3 em 23/07 (#4719)"*. **Falso, e por medir com
   > instrumento quebrado:** o clone desta sessão era **raso** (`--depth`, 57 commits
   > alcançáveis, piso em 2026-07-23). O `git log --diff-filter=A` reportava como
   > "criação" o **fundo do clone**, não a criação real — e o commit-piso `13ec1e06`
   > aparecia como root com 16.081 arquivos "adicionados", que é a assinatura do
   > artefato. Corrigido rodando `git fetch --unshallow` (57 → **5.682** commits) e
   > re-medindo. **A conclusão não muda** (fica mais forte: 3 dos 4 existiam desde 22/07);
   > o recibo é que estava errado — e recibo errado é exatamente o que este log condena.
   >
   > **Corolário perene:** antes de citar data de `git log` como recibo, **conferir
   > `git rev-parse --is-shallow-repository`**. Num clone raso, toda data de
   > `--diff-filter=A` é o piso do clone, não a história. Mesma família da lápide §5
   > 2026-07-17 ("oráculo errado") e de §5 2026-07-17 ("`crontab -l` num host onde o
   > binário não existe") — medir a fonte errada e chamar de medido.
   >
   > **Retratação junto:** na mesma sessão dispensei um *"checkout local estava −5678
   > commits"* de um plano de [F] como número implausível. **Era correto** — aquela
   > sessão tinha o mesmo clone raso, e −5678 é exatamente a distância pro histórico
   > completo. A objeção era minha, não do plano.

2. **"`casos-gate.yml` com 7 gates G-1..G-7"** → o workflow implementa **5**
   (G-1/G-2/G-5/G-6/G-7). **G-3** é o E2E Playwright e **G-4** é o `dominio-gate` —
   workflows separados. (O `CLAUDE.md` já registra isso corretamente.)
   *Recibo:* `git show origin/main:.github/workflows/casos-gate.yml | grep -oE 'G-[0-9]+'`.

3. **"distiller kill-switched (0/76 portas)"** → **leitura invertida do número**. O `0` é a
   contagem de portas **stale**, não de portas rodadas. Real:
   `portas: 78 · carimbadas: 11 · sem_carimbo: 67 · stale: 0`.
   É **pior** que o descrito, e de um jeito mais interessante: a métrica marca **0
   (perfeito)** justamente porque **67 das 78 portas não têm carimbo pra poder ficar
   stale**. É um **verde que não pode ficar vermelho** — mesma família do
   `foundation-ratchet` ("0 failures em 300+ runs") e do drift-sentinel tautológico
   (§5 2026-07-17).
   *Recibo:* `governance/sdd-scorecard.json` → `.metrics.distiller_freshness.detail`.

4. **"655 grandfathered"** → **647** (981 − 334). Tese correta, número não.

5. **"copiar o padrão do `mcp-drift-sentinel.mjs` (mesma classe de sentinela)"** → esse
   sentinela vigia **drift de deploy** do MCP server (SHA servido em
   `/api/mcp/version` × HEAD do main), não doc↔board. Analogia frouxa; o `governance-
   backlog-sync.mjs` é o parente próximo de verdade (memory-health → propõe task).

6. **"SDD composta 55,4 (Δ-8,6)"** → não verificável aqui (vem do brief/MCP). E o próprio
   `sdd-scorecard.json` declara no `_meta` que a composta **não é calculada enquanto
   houver `not_yet_measured`** — e há um: `recall_eval_violations`.

---

## 3. O achado que muda o plano — P0 já é lei

O plano da Resposta 7 propõe como Fase 0:

- **A0** — "ADR curta *uma fonte por fato*"
- **A1** — "`status:` do SPEC deixa de ser digitável; Check no `memory-health` falha PR se
  reaparecer à mão; `_BACKLOG-GENERATED.md` puxa do MCP"

**As três peças já existem:**

| Peça proposta | O que já existe | Desde |
|---|---|---|
| A0 (ADR uma-fonte-por-fato) | **[ADR 0302](../decisions/0302-fonte-unica-doneness-anchor-aposenta-status-spec.md)** — *"a âncora decide, o `status:` é aposentado"*. Vai além: estado de workflow **não é assunto de SPEC** (mora no MCP, ADR 0070) | aceita [W] **22/06/2026** |
| A1 (gate de status) | **`scripts/governance/doneness-lint.mjs`** — e é **REQUIRED** na branch protection (consta nos 34 contexts de `governance/required-checks-baseline.json`) | required **30/06/2026** |
| `_BACKLOG-GENERATED.md` | **`memory/requisitos/_BACKLOG-GENERATED.md`** existe | — |

**Rodado ao vivo hoje** (`node scripts/governance/doneness-lint.mjs --check` → **exit 1**):

```
US: 981 total · 484 com status: (superfície do dual-source) · 497 sem status:
CONFLITOS (mordem em --check): 17  = 8 done-sem-âncora + 9 aberto-com-âncora
Zona-cinza (advisory): 107 aberto-sem-âncora
DONE×DoD aberto (advisory, morde só com --dod): 61
Consistentes: 194 done+âncora · 164 aberto+pendente
```

Construir A0/A1 seria **duplicar régua consolidada** — padrão explicitamente banido no §5
de [`proibicoes.md`](../proibicoes.md) (entradas 2026-07-09 "dobrar casos+contrato numa
catraca nova" e "frescor por `verificado_em`").

### O resíduo real — muito menor, e a correção é outra

O `doneness-lint` compara `status:` × **âncora**, **não** `status:` × **board MCP**. A
queixa específica do transcript (`US-PROD-020` `todo` no arquivo × `done` no board) cai
nesse eixo descoberto.

**Mas a correção certa não é construir sync com o board.** Pela ADR 0302 §2, estado de
workflow (`todo`/`doing`/`blocked`) **não pertence ao SPEC** — US nova nasce **sem**
`status:`. Logo o conserto é **remover o token `status:`** dessas US, não vigiá-lo.
É uma linha por US, não um gate.

⚠️ **Não feito neste PR, de propósito:** tocar `memory/requisitos/Produto/SPEC.md`
**acorda os gates diff-aware** que hoje protegem esse arquivo por grandfather — e o anchor
do Produto está em **11,1%** (lápide §5 2026-07-12: *"tocar um arquivo legado ACORDA os
gates que o protegiam"*). O jeito certo é fazer isso **junto** do trabalho que paga a
dívida de âncora do módulo — ou seja, **dentro do B1**, não antes dele.

---

## 4. O que sobrevive do plano

**B1 — rodar o `sdd-from-source` de verdade no Produto** continua sendo o primeiro passo
certo, e agora com dois motivos medidos:

- a [errata 0352](../decisions/0352-errata-0351-venue-distiller-citacao-taxonomia.md)
  admite que o piloto foi **feito à mão** (o agent nunca rodou);
- o anchor do Produto está em **11,1%** — é o módulo mais travado entre os grandes
  (Financeiro 93,1 · Sells 92,2 · Compras 52,4).

Se o agent funcionar, resolve os dois de uma vez (prova a ferramenta **e** destrava o
piloto) — e é dentro dele que o `status:` legado das US-PROD sai sem acordar gate à toa.

---

## 5. Observações menores (medidas, sem ação proposta)

- **Denominadores divergentes entre portas vivas:** `casos:report` conta **280** páginas
  roteadas; `screen-coverage:report` conta **235** telas. Definições diferentes de "tela"
  (a primeira varre `Pages/**` excl. `_components/`; a segunda tem seu próprio recorte).
  Não é bug — é ambiguidade de vocabulário que vale saber antes de citar "N telas".
- **"32 UCs · 32 pass" do #4765** é **32 de 159 UCs declarados (20%)** — o resto é
  string-match ou sem prova. O próprio `casos:report` já rotula isso honestamente como
  advisory (G-2 fase 1); só a manchete do PR omite o denominador.
- **Débito global de casos** (`node scripts/casos-coverage-guard.mjs --report`):
  280 páginas · 41 `casos.md` · 159 UCs · **240 telas sem `casos.md`** · 45 sem charter ·
  28 UCs órfãos · **total 313 violações** (fotografadas no baseline, não-bloqueantes).

---

## 6. Método (por que este log existe)

O alarme `two-strikes` do SessionStart aponta **LC-08 — "afirmar/derivar/medir a partir da
fonte ou medida errada"** com **7 ocorrências e sem gate**. Esta verificação é uma
instância da mesma classe pega **de fora**: o transcript não alucinou — ele **leu números
reais**, mas 3 deles vieram da **fonte errada** (contagem stale de `casos.md`, o `0` do
distiller que media outra coisa, o "7 gates" lido da ADR e não do workflow).

O antídoto aplicado aqui foi o canônico: **rodar a porta viva** em vez de olhar a árvore
(`casos:report`, `screen-coverage:report`, `anchor-lint`, `doneness-lint`), e **pendurar o
recibo** em cada número.

**E a ironia que fecha o método:** cometi a MESMA classe dentro do log que a denuncia (ver
a errata do §2 item 1 — clone raso). Rodei a porta viva certa, mas o `git log` estava
medindo um clone truncado. Fica como o exemplo mais honesto do ponto: *rodar a máquina não
basta se a máquina está apontada pro lugar errado.*

---

## 7. Viabilidade — "dá pra fazer todo o Produto? e todos?" ([F], mesma sessão)

Medido em clone **completo** (pós-`--unshallow`), 2026-07-24.

### Produto — viável e curto

Faltam **4 telas** (`BulkEdit`, `Index`, `Show`, `Unificado/Index`). As 4 que têm casos
nasceram em **9 dias**:

| Tela | Data | PR |
|---|---|---|
| `SellingPrices` | 2026-07-15 | #4300 |
| `Create` | 2026-07-19 | #4417 (US-PROD-020) |
| `StockHistory` | 2026-07-22 | #4658 |
| `Edit` | 2026-07-24 | #4767 (piloto sdd-from-source) |

O anchor de 11,1% também é pequeno em valor absoluto: das **9 US** do Produto, só a
**US-PROD-028** carrega `**Implementado em:**`. Chegar a ≥50% = ancorar ~4 US.
*Recibo:* `git show origin/main:memory/requisitos/Produto/SPEC.md | grep -E '^### US-PROD|^\*\*Implementado em'`.

### Todos os módulos — não no ritmo atual

Taxa real de criação de `casos.md` por semana ISO, desde a 1ª (2026-06-10):

```
W24: 6 · W25: 2 · W26: 3 · W27: 15 · W28: 10 · W29: 3 · W30: 2     (total 41)
```

Pico em W27, **caindo a 2-3/semana** nas últimas 3. Restam **240 telas sem `casos.md`**:

| Ritmo | Projeção pras 240 |
|---|---|
| atual (2-3/sem) | **~2 anos** |
| média histórica (6,3/sem) | ~9 meses |
| pico (15/sem) | ~4 meses |

**Mas o gargalo não é o arquivo — é a prova.** Só **32 dos 159 UCs (20%)** têm execução
por trás; os outros 127 são string-match. "Fazer todos" de verdade = 240 `casos.md` +
~940 UCs (média 3,9/tela) + um teste por UC rodando no CT100 ([ADR 0062]). Não é escrever
markdown, é escrever suíte.

**Contraste que explica a diferença:** `charter` já está em **100% (235/235)** — porque é
batch-ável (nasceu de lote mecânico). `casos`+teste não é. Daí a assimetria entre as
dimensões do trio.

### Consequência pro plano

Isso dá a **B1** um peso que ele não tinha: rodar o `sdd-from-source` de verdade não é só
"validar a ferramenta" — é **a medição que decide se 'todos' custa 4 meses ou 2 anos**,
porque o que o agent muda é o custo marginal por tela. Enquanto ele não roda num caso
real, a projeção honesta é a de cima (2 anos), não a esperança.
