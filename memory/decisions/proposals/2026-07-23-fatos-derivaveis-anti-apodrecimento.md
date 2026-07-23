---
status: proposal
title: "Fatos deriváveis restateados à mão — o menor conjunto de máquinas que os cobre (estender Check T + apontar pro dono gerado)"
proposed_by: Claude (grade estado-da-arte anti-apodrecimento, item 4) — pendente [W]
proposed_at: 2026-07-23
relates_to:
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0275-calendario-promocao-gates-sdd
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0062-separacao-runtime-hostinger-ct100
---

# Fatos deriváveis restateados à mão — menor conjunto de máquinas

> **Escopo:** só PROPOSTA. Nada implementado. Não fazer codemod em massa de legado
> (proibicoes §5 2026-07-12 "big-bang de backfill = descartado"). O conceito chega
> **forward-only + oportunístico**.
>
> **Regra deste doc:** toda afirmação numérica abaixo vem com o **comando que a mede**.
> Não restateio número — seria cair no próprio erro que descrevo (proibicoes §5
> 2026-07-17 "documento canônico não repete número que outro sistema sabe melhor").

## 1. O problema (a raiz do apodrecimento)

A raiz do apodrecimento não é link quebrado — é **fato numérico/estrutural escrito à
mão que descreve um passado que já mudou**. A doutrina interna já nomeia a cura: ADR
0256 (*"derivado+enforçado sobrevive; escrito+lembrado apodrece"*) + lápide §5
2026-07-17 (*"aponta pro dono ou carrega recibo datado"*). O que falta é **fechar o
gap dos fatos que ainda são digitados** em vez de derivados/apontados.

Ordem de grandeza (recibo):

```bash
# docs de memory/ com authority: generated (nunca apodrecem)
grep -rl '^authority: generated' memory | wc -l          # → 41
find memory -name '*.md' | wc -l                          # → 3235
```

≈1% do corpus é gerado; os outros 99% são escritos à mão e **podem** driftar. A maior
parte disso é prosa legítima (feedback, lápides, sessões — fósseis datados). O
subconjunto **perigoso** é o fato **derivável** restateado como estado-atual.

## 2. Inventário medido — fatos deriváveis restateados à mão

Varredura dos docs front-facing enxutos (raiz + `memory/*.md` + `memory/reference/*.md`;
`sessions/`·`handoffs/`·`decisions/` excluídos — lá número velho é fóssil legítimo).
Cada linha traz o comando que a mede.

### 2a. Já FALSOS (o número já contradiz a fonte) — os mais acionáveis

| Arquivo:linha | Afirma | Fonte-de-verdade (comando) | Real |
|---|---|---|---|
| `README.md:86` | "CLAUDE.md ≤100 linhas" | `wc -l CLAUDE.md` | **125** |
| `CLAUDE.md:5` | "enxuto ~110 linhas" | `wc -l CLAUDE.md` | **125** |
| `CLAUDE.md:24` | "LICOES_CC hoje até L-27" | `grep -oE 'L-[0-9]+' memory/LICOES_CC.md \| sort -u \| tail -1` | **L-28** |
| `memory/INDEX.md:70` | "~220 ADRs" | `ls memory/decisions/[0-9][0-9][0-9][0-9]-*.md \| wc -l` | **352** |
| `memory/what-oimpresso.md:21` | "4 Agents próprios em `Modules/Jana/Ai/Agents/`" | `ls Modules/Jana/Ai/Agents/*Agent.php \| wc -l` | **14** |

### 2b. Ainda corretos, mas vão apodrecer (deriváveis, digitados à mão)

| Arquivo:linha | Afirma | Fonte (comando) |
|---|---|---|
| `README.md:89` | "30+ skills" | `ls -d .claude/skills/*/SKILL.md \| wc -l` → 75 |
| `README.md:52` / `03-architecture.md:3` | "30+ / 36 módulos" | `ls -d Modules/*/ \| wc -l` → 36 |
| `README.md:27` / `what-oimpresso.md:7` | "React 19 · Inertia v3 · Tailwind 4" | `package.json` react `^19`, `@inertiajs/react ^3`, tailwind `^4` |
| `README.md:30` / `what-oimpresso.md:8` | "Pest v4 · PHPUnit v12" | `composer.json` pest `^4`, phpunit `^12` |
| `what-oimpresso.md:10` | "nWidart/laravel-modules ^10" | `composer.json` `nwidart/laravel-modules ^10.0` |
| `what-oimpresso.md:20` | "laravel/ai ^0.6.3" | `composer.json` `laravel/ai ^0.6.3` |
| `README.md:31` | "laravel/mcp ^0.7" | `composer.json` `laravel/mcp ^0.7.0` |
| `what-oimpresso.md:9` | "spatie/laravel-html ^3.13" | `composer.json` `spatie/laravel-html ^3.13` |
| `what-oimpresso.md:5` / `README.md:26` | "Laravel 13.6 · PHP 8.4" | ⚠️ ver §6 (floor ≠ runtime) |
| `regras-time.md:1` | "Time interno (5 pessoas)" | contar linhas da tabela logo abaixo → 5 |
| `GUIA-DO-SISTEMA.md:141` / `CLAUDE.md:111` | "5 checks SQL" | enumera os 5 nomes na própria linha; fonte `app/Console/Kernel.php` |

### 2c. Padrões que emergem

1. **Major-versions de stack copiados à mão** (README + what-oimpresso). É o que o Check
   T já ataca pra React/Laravel — falta cobrir o resto.
2. **Contagens "N+" que ficaram pra trás** (skills 30+, módulos 30+, ADRs ~220→352).
   Estas **um gerador já possui** (ver §3).
3. **Line-count auto-referencial** (CLAUDE.md "~110 linhas" → 125) — vaidade que não
   serve leitor nenhum.

## 3. O que JÁ existe — não duplicar

Há **duas máquinas complementares** já em produção. A proposta anda **dentro** delas.

### GERAR (o fato vive num doc gerado que nunca apodrece)

- **`scripts/governance/system-map.mjs`** (409 linhas) → gera `memory/reference/PAINEL-SISTEMA.md`
  + `ONBOARDING-AGENTE-GERADO.md`. Já emite **módulos** (lista + frescor git) e **ADRs**
  (total curado). Recibo: `ONBOARDING-AGENTE-GERADO.md:46` diz "36 módulos · 352 ADRs" —
  regenerado hoje (2026-07-23).
- **`scripts/governance/skills-index-generate.mjs`** → `.claude/skills/_SKILLS-INDEX.md`
  com marcadores `AUTO:SKILLS`. Já emite **"75 skills · Tier A 6 · Tier B 59 · Tier C 10"**
  a partir do frontmatter (fonte única).

**Prova de por que NÃO re-derivar um count que um gerador já possui:**

```bash
ls memory/decisions/*.md | wc -l                          # → 359  (ingênuo)
ls memory/decisions/[0-9][0-9][0-9][0-9]-*.md | wc -l      # → 352  (curado = o que measureAdrs conta)
```

O gerado é **352** (só `^\d{4}-.*\.md`); o `ls *.md` cru dá **359** (inclui `_INDEX`,
`README`…). Se uma segunda máquina re-derivasse "ADRs" com um glob diferente, criaria um
**segundo "verdadeiro" que conflita com o dono**. Regra: **um fato = uma definição = um
dono.** Count que um gerador possui → **apontar pro dono** (§4 Tier 1), nunca re-medir.

### DETECTAR (flagra prosa que CONTRADIZ a fonte)

- **`scripts/governance/memory-health.mjs` Check T (fact-anchor)** — ancora o FATO afirmado
  numa fonte versionada (`package.json`/`composer.json`/árvore `Modules/`) e flagra
  CONTRADIÇÃO. Advisory (ADR 0275). Escopo: 6 docs `CURRENT_STATE_DOCS` (README, CLAUDE,
  GUIA-DO-SISTEMA, what-/why-/how-). **Hoje cobre só:** versão de React + Laravel
  (major-only) e `Modules/<Nome>` inexistente. **Não cobre** o "4 Agents" (não há regra de
  contagem) nem o resto do stack.

## 4. Proposta — menor conjunto de máquinas (3 tiers, do melhor pro mecânico)

### Tier 1 — APONTAR PRO DONO (melhor; editorial; forward-only; ZERO máquina nova)

Para fatos que um **gerador já possui** (módulos, skills, ADRs) ou que são pura vaidade
(line-count, "até L-NN"): a prosa **não deve carregar o número** — aponta pro dono gerado
ou vira "lista viva". É a aplicação literal da lápide §5 2026-07-17 (*"aponta pro dono"*).

- "30+ módulos" / "36 módulos" → "ver [`PAINEL-SISTEMA.md`](reference/PAINEL-SISTEMA.md)"
- "30+ skills" → "ver [`_SKILLS-INDEX.md`](../.claude/skills/_SKILLS-INDEX.md)"
- "~220 ADRs" → "ver PAINEL-SISTEMA (contagem viva)"
- "CLAUDE.md ≤100/~110 linhas" e "LICOES_CC até L-27" → **remover o número** (não serve leitor)

**Como, sem virar codemod (respeita §5 2026-07-12):** só quando o doc já for tocado por
trabalho real — **oportunístico**, nunca varredura big-bang. O `README.md:86` / `CLAUDE.md:24`
/ `INDEX.md:70` (já falsos) são os 3 primeiros candidatos, pendentes de [W].

### Tier 2 — ESTENDER O Check T (mecânico; advisory; a máquina do item)

Nada de motor novo (proibicoes §5 mata "duplica régua consolidada"). Duas extensões
**dentro** do Check T existente:

**(A) Ampliar a tabela `VERSIONS`** — hoje 2 linhas (React, Laravel). Adicionar as
constraints major que a prosa **efetivamente restateia nos 6 docs in-scope**: Inertia (3),
Tailwind (4), Pest (4), PHPUnit (12), nWidart (10). **Mesmo padrão já em produção** (regex +
`majorFrom(constraint)`, comparação **major-only**), FP ≈ 0. É literalmente **mais linhas
numa tabela que já shippa e morde.** Cobre o grosso do §2b (README:27-31 + what-oimpresso:7-10).

> **Emendas do adversário (2026-07-23 — §9):**
> - **E1 (regex `v?`):** a prosa mistura `"Pest v4"`/`"Inertia v3"`/`"PHPUnit v12"` (com `v`)
>   e `"React 19"`/`"Tailwind 4"` (sem). O regex atual (`/React\s+(\d+)/`) **não casaria
>   "Pest v4"** → regra existiria mas nunca dispararia (gate que não morde). Corrigir pra
>   `v?` e **as fixtures-ruins usam os tokens `v`-prefixados reais**.
> - **E2 (só o restateado; cortar dormentes):** **cortar Vite/TypeScript** — não aparecem em
>   doc in-scope (regra especulativa = peso morto). `laravel/ai ^0.6.3` e `laravel/mcp ^0.7`
>   têm **major 0** → `majorFrom`="0", inútil → residual §6. `spatie/laravel-html ^3.13` e
>   `nWidart ^10` são **constraint copiada** (`^N`), não `"Nome Major"` → residual §6 até a
>   prosa virar texto (ou deixar como "ver `composer.json`").

**(B) Nova tabela `COUNT_FACTS` — ⏸️ DIFERIDA (emenda E3).** Sibling de `VERSIONS`, para fatos
que **nenhum gerador possui** e que **precisam** viver como número em prosa editorial.
**Seed candidato:**

| nome | regex (prosa) | verdade (glob determinístico) |
|---|---|---|
| Jana Agents | `/(\d+)\s+Agents?\s+próprios/i` **exigindo** `Modules/Jana/Ai/Agents` na mesma linha | `Modules/Jana/Ai/Agents/*Agent.php` (14) |

Regras de honestidade da tabela COUNT (anti-FP, ancoradas nas lápides):

- **Auto-ancorada:** só dispara quando a frase **nomeia o próprio caminho** (o doc diz
  onde contar). Sem path nomeado, não toca.
- **Relação-aware** (não flagra "N+" honesto): `= N` → viola se `count ≠ N`; `≤ N`/`até N`
  → viola se `count > N`; `≥ N`/`N+` → viola se `count < N`; `~N`/`≈` → **pula** (aproximado
  por construção). Assim "30+ módulos"=36 **não** falsifica (é lower-bound honesto), mas
  "≤100 linhas"=125 falsifica.
- **Exata** (counts são exatos, ≠ versão que é floor). **Advisory** + escopo
  `CURRENT_STATE_DOCS`. Forward-only.
- **Não re-derivar count de dono-gerado** (módulos/skills/ADRs ficam no Tier 1) — evita o
  split 352-vs-359 do §3.

**Por que DIFERIR (E3):** o COUNT_FACTS constrói um motor relação-aware (`=`/`≤`/`≥`/`~`) +
harness de teste do zero **para uma população de UM** (Jana Agents). E esse número **não é
load-bearing**: de-numberficar a frase ("os Agents próprios em `Modules/Jana/Ai/Agents/`")
resolve com **zero máquina** (Tier 1) e o argumento "Vizra REJEITADA" sobrevive intacto. A
população realista de count-facts *in-scope + glob limpo + não-possuída-por-gerador + número
load-bearing* é ≈1 ("5 checks SQL" é enumeração frágil; "5 pessoas" está fora de escopo;
"11 stages"/"162 vendas" são runtime-DB, §6). Construir o motor agora seria "máquina à frente
da necessidade". **Recomendação: adiar** — guardar o design relação-aware no papel e só
construir COUNT_FACTS quando aparecer um **2º** count-fact real que atenda os 4 critérios.

**Fixtures obrigatórias (quando o Tier 2B for construído):** hoje **não existe** teste do
Check T / `memory-health.mjs`:

```bash
ls scripts/governance/memory-health*.test.mjs 2>/dev/null    # → (vazio)
```

Toda regra nova nasce com fixture **boa** (não flagra) + **ruim** (flagra) — o
controle-negativo que prova que o gate morde (proibicoes §5 2026-07-09 "fixture boa/ruim").
A fixture-ruim precisa cobrir o caso *"entrou um `BaseAgent.php` e inflou o glob"* (o glob
conta **arquivos**, não "conceito de agente"). Sem isso a extensão é rejeitada por construção.

### (opcional) Tier 3 — emitir Jana Agents no `system-map.mjs`

Alternativa ao Tier 2(B): fazer o `measureModules`/novo `measureJanaAgents` emitir a
contagem no PAINEL-SISTEMA, e o texto do what-oimpresso apontar pra lá (vira Tier 1).
**Trade-off:** o "4 Agents próprios ... Vizra REJEITADA" é **prosa editorial** (o número
sustenta o argumento da camada B) — não dá pra máquina reescrever a frase. Por isso a
**recomendação é o Tier 2(B) DETECTAR**, não gerar. Registro o Tier 3 só como opção
explícita pra [W].

## 5. O que NÃO fazer (rejeições ancoradas — pra o adversário não re-propor)

- **Motor novo de staleness/fatos** → proibicoes §5 2026-07-09 "duplica régua consolidada".
  Tudo anda em Check T + system-map + skills-index.
- **Ancorar MINOR de versão via composer** (ex.: "Laravel 13.**6**", "^3.**13**") → §6.
  O major-only atual é **correto** e deve ser preservado.
- **Ancorar count sem path nomeado** ("36 módulos" solto) → denominador ambíguo (quais
  módulos? verticais? com `module.json`?) → FP. Fica no Tier 1 (aponta pro dono).
- **Gate de presença** ("§ recibo presente") → proibicoes §5 2026-07-09/2026-07-16
  (presença ≠ correção). Check T mede **contradição de valor**, não presença de campo.
- **Promover a required agora** → advisory primeiro (ADR 0275); required só via emenda
  explícita + flip [W] (padrão ADR 0327).
- **Codemod em massa dos ~3200 docs à mão** → proibicoes §5 2026-07-12.

## 6. Residuais honestos (não-deriváveis; a máquina não alcança)

- **"PHP 8.4" / "Laravel 13.6" (minor):** `composer.json` diz `^8.1` / `^13.0` (floor, não
  runtime). O major-only do Check T já trata isso certo (8==8, 13==13 → não falso-positiva).
  A verdade do minor vive em `composer.lock`/env de deploy — **fora** do que Check T lê. Não
  ancorar o minor; se incomodar, apontar pro `composer.lock`.
- **Constraints minor copiadas à mão** ("^3.13", "^0.6.3", "^0.7"): major-only não pega bump
  minor. Melhor **não copiar a constraint** pra prosa (dizer "ver `composer.json`").
- **Serviços do CT 100** ("FrankenPHP + Centrifugo + Meilisearch + …", what-oimpresso:15):
  runtime **separado** (ADR 0062), **não** está neste filesystem → não-derivável daqui →
  **não ancorável.** Residual perene.
- **"22 containers"** (`reference/infra-proxmox-ct100.md`): mede via `docker ps` **remoto no
  CT 100**, e já vem **carimbado com data** ("verificado 2026-06-12") = recibo datado
  legítimo (não é estado-atrimporal). Fora do alcance de Check T; ok como está.
- **"~6.4k chamadas Blade", "162 vendas biz=1", "11 stages × 21 actions":** dados de
  seed/runtime do banco — não deriváveis de FS/git/composer. Fora de escopo.

## 7. Plano de adoção (revisado pós-adversário — se [W] aprovar)

1. **PR-1 (mecânico, baixo risco) — Tier 2A:** ampliar `VERSIONS` com Inertia/Tailwind/Pest/
   PHPUnit/nWidart, **regex `v?`** (E1), + **construir o 1º harness de teste** de
   `memory-health.mjs` com fixtures boa/ruim usando os tokens `v`-prefixados reais. Advisory.
   ⚠️ Esforço: **não** é "1 arquivo + 1 teste" — o harness não existe e `checkFactAnchor` lê
   `package.json`/`composer.json`/FS reais → precisa de inputs injetáveis ou docs-fixture (E4).
2. **PR-2 (editorial, oportunístico) — Tier 1:** de-numberficar/ponteirizar, **um por vez,
   só quando o doc for tocado**: os 3 já-falsos (`README:86`, `CLAUDE:24`, `INDEX:70`) **+ o
   "4 Agents"** de `what-oimpresso.md:21` (E3 — número não é load-bearing).
3. **Tier 2B: NÃO construir agora.** Reabrir só quando surgir um 2º count-fact real (guardar
   o design relação-aware neste doc).
4. **Watch:** Check T ampliado roda advisory ~2 semanas; se FP ≈ 0 e pegar drift real,
   considerar promoção via emenda ADR 0275/0327 (decisão [W], não automática).

## 8. Decisão pendente [W]

- [ ] Aprovar **Tier 2A** (ampliar VERSIONS, regex `v?`, + harness de teste) — recomendado (risco ~0)?
- [ ] Autorizar **Tier 1** forward-only: de-numberficar os 3 já-falsos **+ "4 Agents"** (quando tocados)?
- [ ] Confirmar **adiar Tier 2B** (COUNT_FACTS) até haver ≥2 count-facts reais?

## 9. Veredito adversarial (2026-07-23)

Rodado um adversário cético read-only contra esta proposta (exigência §5 — refutar antes de
adotar). **Veredito: ADOTAR COM EMENDAS.** Todos os números da proposta bateram contra o repo
vivo (sem auto-refutação irônica): 14 Agents, 125 linhas CLAUDE.md, 36 módulos, 352/359 ADRs,
L-28, 75 skills, 0 testes de memory-health. Confirmou também: os 14 `*Agent.php` são **todos
classes concretas `implements Agent`** (nenhuma abstract inflando o glob) e "4" **não** é
constante arquitetural → 14 é a verdade certa (sem FP). `what-oimpresso.md` **está** em
`CURRENT_STATE_DOCS` (o "4 Agents" seria lido); `INDEX`/`03-arch`/`regras-time` **não** estão,
mas a proposta os roteia corretamente pro Tier 1 (sem chokepoint fantasma). Nenhuma violação
do §5.

**Emendas incorporadas acima:** E1 (regex `v?` + fixtures reais), E2 (cortar Vite/TS dormentes;
`^N`-constraints e major-0 → residual §6), **E3 (diferir Tier 2B; de-numberficar "4 Agents" no
Tier 1 — é o próprio anti-"máquina à frente da necessidade")**, E4 (estimativa PR-1 inclui
construir o harness). **Resultado: o menor conjunto de máquinas de verdade = Tier 1 + Tier 2A
já; Tier 2B adiado até haver demanda de ≥2 fatos.**
