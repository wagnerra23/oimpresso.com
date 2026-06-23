---
date: "2026-06-23"
topic: "Schema de changelog/US ancorado (6 campos Wagner), benchmark com nota das melhores âncoras do mundo, e teste de fogo nota fiscal (NF-e)"
authors: [C]
type: session
---

# Âncora spec↔código: changelog dos 6 campos, quem tem a melhor âncora (com nota), e o teste de fogo da nota fiscal

## TL;DR

**Pedido 1 — changelog dos 6 campos.** O oimpresso já carrega 3 dos 6 campos do Wagner de forma ancorada e mais 1 de graça do git. O que falta ancorar de verdade é **objetivo**, **caso de uso** e **cliente(s)** — hoje vivem em prosa (`**Como** … **quero** … **para** …`) ou em nada. Schema proposto: 2 campos DERIVADOS do git (quem alterou, data) + 4 campos DECLARADOS no SPEC, cada um virando âncora máquina-verificável: **objetivo** → 1ª linha do bloco US (já existe, falta lint exigir não-vazio); **caso de uso** → `**Testado em:**` ligado por ID (a US-ID que o teste cobre é o caso de uso provado); **teste** → `**Testado em:**` (já ancorado, mas só prova existência — gap G1); **aceite** → DoD checkboxes ligados 1:1 a testes; **cliente** → novo campo `**Sinal:**` ancorado num `client_signal` row do MCP (ADR 0105). O changelog não é um arquivo novo — é uma **VIEW gerada** do git (autor/data) + frontmatter/blocos do SPEC + `client_signal` do MCP. Zero dual-source.

**Pedido 2 — quem tem a melhor âncora (nota 0-100 contra a rubrica de 8).** Vencedor: **DO-178C/ISO-26262 (RTM bidirecional certificada) — 86/100**, seguido de perto por **ReqToCode (trace compilável) — 84/100**. O oimpresso tira **67/100** — 4º lugar, à frente de Polarion/Jama (63), Cucumber living-docs (61), OpenAPI+Pact (58 como trace de US, alto só em comportamento), e MUITO à frente de spec-kit/Kiro (28, não verifica nada). O oimpresso **ganha de todos** em #3 prova-está-vivo (wired-check anti-zumbi — ninguém mais tem) e empata no topo em #1/#2/#8. **Perde** pro vencedor só em #4 prova-comportamento (teste verde) e #6 sobrevive-rename. A distância pro 1º lugar é **2 gaps conhecidos** (G1+G2), não uma reforma.

**Pedido 3 — teste de fogo nota fiscal: a estrutura AGUENTA, com 1 emenda obrigatória.** Peguei a US-NFE-010 (motor tributário por NCM) + as 14 regras R-NFE-001..014 do SPEC real. Contagem real: **a "US fiscal" não é 1 US com 50 regras — é 1 US-épico (US-NFE-010) que se decompõe em ~14 DoD checkboxes + 6 sub-testes, mais 14 regras de negócio R-NFE-NNN, cada uma já com seu próprio `Testado em:`**. Ou seja: o schema do oimpresso **já modela 1-regra → 1-teste de forma 1:1** via o par `R-NFE-NNN` + `Testado em:`. Onde QUEBRA: (a) o `Implementado em:` da US-010 aponta 8 paths mas o `anchor_ok` não prova que os 6 sub-testes do DoD passam (G1 morde forte aqui — é fiscal, teste vermelho = multa); (b) **rejeição SEFAZ que muda fora do repo (cStat nova, NT técnica) drifta a regra sem 1 byte de código mudar — e NENHUMA âncora atual pega isso**, porque toda âncora do oimpresso (e do ReqToCode) é interna ao repo. É exatamente onde o **suspect-link do Jama/Polarion ganha** e onde o oimpresso precisa de um sentinela de fonte externa. **Veredito: aguenta a estrutura DENSA da NF-e (N regras→N testes→aceite→cliente) hoje; não aguenta o DRIFT EXTERNO da regra fiscal sem 1 brick novo (sentinela de versão de NT/cStat).**

---

## Contexto e regra anti-stale

Tudo lido de `C:\Users\wagne\_salvage-ap` (= origin/main, `D:\oimpresso.com` está 163 commits atrás). Base já pesquisada e reusada: `memory/sessions/2026-06-23-arte-ancora-spec-codigo.md` (rubrica de 8 propriedades + gaps G1/G2/G3). Âncora real lida linha-a-linha: `scripts/governance/anchor-lint.mjs` + `scripts/governance/doneness-lint.mjs`; ADRs `0273` (gramática), `0302` (fonte única done-ness), `0303` (wired+testado), `0105` (cliente como sinal). SPEC fiscal real: `memory/requisitos/NfeBrasil/SPEC.md` (1083 linhas, US-NFE-001..056 + R-NFE-001..014).

**A rubrica de 8 propriedades** (da base, reusada sem mudar): (1) verificável-por-máquina · (2) prova-existência · (3) prova-está-vivo · (4) prova-comportamento · (5) bidirecional · (6) sobrevive-refactor/rename · (7) não-gameável · (8) barata + grão-certo.

---

## PEDIDO 1 — Schema de changelog / registro de US (os 6 campos do Wagner)

### 1.1 O que o oimpresso JÁ TEM desses 6 campos (medido no SPEC real)

| Campo Wagner | Existe hoje? | Onde vive | Ancorado (máquina-verificável)? |
|---|---|---|---|
| **quem alterou** (autor) | ✅ de graça | git (`git log --follow`) + bloco `> owner: wagner` (`NfeBrasil/SPEC.md:516`) | git: sim (derivável). `owner:` do bloco: não — é declarado, drifta |
| **objetivo** | 🟡 prosa | `**Como** Gestor **quero** … **para** automatizar cálculo` (US-NFE-010, `:222-223`) | ❌ texto solto — nenhum lint exige que exista nem que seja não-vazio |
| **caso de uso** | 🟡 prosa | mesma linha `**Como** … **quero**` + Gherkin `R-NFE-NNN` (`:261-425`) | 🟡 o Gherkin é semi-estruturado mas não liga a teste por ID |
| **teste** | ✅ ancorado | `**Testado em:** \`...Test\`` (R-NFE-001 `:270`) + `**Implementado em:**` cita test path (US-NFE-008 `:186`) | ✅ **mas só prova existência** (G1) — `anchor-lint.mjs:144` `deadTestRefs` valida basename, não verde |
| **requisito p/ aceito** (critério) | ✅ declarado | `**Definition of Done:**` checkboxes `- [x]/- [ ]` (US-NFE-010 `:228-258`) | ❌ checkbox `[x]` é digitado à mão, zero gate — mesma doença do `status:` (ADR 0302) |
| **cliente(s) que solicitaram** | 🟡 esparso | `origin: capterra-inventario` no bloco (`:453`); caso Gold em prosa (`:507`); ADR 0105 fala de `client_signal` mas SPEC não linka | ❌ não há campo canônico de cliente por US |

**Achado:** o oimpresso tem **forte** em "teste" e "autor", **médio-prosa** em "objetivo/caso de uso", e **fraco/ausente** em "aceite verificável" e "cliente". O `## 6. Histórico | Data | Quem | O que mudou |` do `_TEMPLATE_SPEC.md:84` é um changelog manual que ninguém mantém (dual-source com o git — mesma doença ADR 0302).

### 1.2 DERIVÁVEL vs DECLARADO (a regra de ouro: nunca declare o que o git já sabe)

| Campo | Tipo | Por quê |
|---|---|---|
| quem alterou | **DERIVADO** do git | `git log --follow -1 -- <path-âncora>` dá autor+data sem ninguém digitar. Declarar `owner:` à mão = 2ª fonte que drifta (o `> owner:` já mente: US-NFE-055 tem `owner: —`) |
| data da mudança | **DERIVADO** do git | idem; e já está no carimbo `verificado@sha7 (YYYY-MM-DD)` da âncora |
| objetivo | **DECLARADO** | git não sabe o "para quê" — é intenção humana |
| caso de uso | **DECLARADO** | idem |
| teste | **DECLARADO** + **VERIFICADO** | humano cita o teste; máquina prova que existe (hoje) e deveria provar que passa+cobre (G1) |
| aceite | **DECLARADO** + **VERIFICADO** | humano escreve o DoD; máquina deveria ligar cada item a um teste |
| cliente(s) | **DECLARADO** + **VERIFICADO** contra MCP | humano cita o sinal; máquina valida que o `client_signal` ID existe no MCP (ADR 0105) |

### 1.3 Schema final — onde cada campo vive e como vira âncora

Princípio: **o changelog NÃO é um arquivo novo. É uma view gerada** que junta 3 fontes que já existem, cada uma dona do seu campo (SoC brutal, ADR 0094 princípio 5):

```
                                       ┌─ git (autor, data)            ── DERIVADO, zero digitação
CHANGELOG/registro de US (gerado)  ────┼─ SPEC US block (obj, caso, teste, aceite) ── DECLARADO+lintado
                                       └─ MCP client_signal (cliente) ── DECLARADO, validado por ID
```

**Layout proposto do bloco US (extensão mínima do que já existe — 2 linhas novas):**

```markdown
### US-NFE-010 · Cadastrar regra tributária por NCM (motor)
> owner: wagner · priority: p1 · type: story
> **Objetivo:** automatizar cálculo de imposto na emissão sem digitar imposto a imposto
> **Sinal:** CS-NFE-031 (ROTA LIVRE biz=4) · CS-NFE-044 (Gold)        ← NOVO, ancora no MCP
**Como** Gestor/Contador **quero** definir tributação por NCM/UF **para** automatizar cálculo

**Implementado em:** `Modules/.../MotorTributarioService.php` · ... · verificado@08c4a8f (2026-06-21)
**Testado em:** `MotorTributarioServiceTest` #[CoversUS('US-NFE-010')] · verificado@08c4a8f   ← #[CoversUS] = NOVO (G1)
**Definition of Done:**
- [x] Cascade fallback Nível 1→4 → coberto por `MotorTributarioServiceTest::cascade_nivel_4`   ← ligação DoD↔teste = NOVO
```

| # | Campo Wagner | Casa canônica | Mecânica da âncora (como vira máquina-verificável) | Status |
|---|---|---|---|---|
| 1 | **quem alterou** | git (`verificado@sha7`) | `anchor-lint --reverify` faz `git log -1 --follow` no path → autor. **Não declarar** | falta só expor (G4 da base) |
| 2 | **objetivo** | `> **Objetivo:**` no bloco US | lint exige a linha presente + não-vazia (1 regex, barato). Hoje só há prosa `**Como/quero/para**` | **falta** (lint novo trivial) |
| 3 | **caso de uso** | `R-NFE-NNN` Gherkin + `**Testado em:**` | o caso de uso PROVADO = o cenário Gherkin que tem um `Testado em:` vivo. Liga regra→teste por convenção de ID | parcial (existe, falta ligar por ID) |
| 4 | **teste** | `**Testado em:** \`Test\` #[CoversUS('US-X')]` | `anchor-lint.mjs:144` já valida existência. **Novo:** exigir que o teste declare `#[CoversUS]` apontando a US → liga bidirecional | **G1** (a recomendação da base) |
| 5 | **aceite** | DoD `- [x]` → `→ coberto por Test::metodo` | lint exige que cada `- [x]` de DoD aponte um teste existente; `- [ ]` é dívida honesta. Aposenta o checkbox-mentira (= doença do `status:`, ADR 0302) | **falta** (extensão do G1) |
| 6 | **cliente(s)** | `> **Sinal:** CS-NNN` → MCP `client_signal` | lint (ou tool MCP) valida que cada `CS-NNN` existe como row `client_signal` (ADR 0105). Liga US→quem pediu→quem paga | **falta** (depende de `client_signal` existir no MCP — US-INFRA-002) |

**Por que view gerada e não arquivo:** o `## 6. Histórico` manual do template é exatamente o anti-padrão que o ADR 0302 matou no `status:` — campo digitado que diverge da verdade verificável. O changelog correto se **gera** rodando um `changelog-gen.mjs` (irmão do `anchor-lint.mjs`) que faz: para cada US, lê objetivo/caso/aceite do bloco, `git log` do path da âncora pra autor/data, e resolve os `CS-NNN` contra o MCP. Saída determinística, zero dual-source.

---

## PEDIDO 2 — Quem tem a MELHOR âncora? Benchmark com nota (passo a passo)

**Método.** 8 propriedades da rubrica, cada uma 0-12.5 pts (8 × 12.5 = 100). Nota por propriedade segue uma escala dura: **0** = não faz · **~4** = faz por convenção/manual (gameável) · **~8** = automatizado mas com brecha · **12.5** = automatizado + não-gameável. Soma = nota final. Quando um sistema **não verifica** algo, recebe baixo — sem dó.

**Sistemas (7):** DO-178C/ISO-26262 (RTM certificada) · ReqToCode (trace compilável, arXiv 2603.13999) · Polarion/Jama (RM corporativo, suspect-links) · Cucumber/BDD living-docs · OpenAPI+Pact (contract testing) · spec-kit/Kiro (SDD agêntico 2026) · **oimpresso (anchor-lint v1 + wired + testado)**.

### 2.1 Tabela mestra — nota por propriedade (a conta visível)

Escala por célula: pontos de 0 a 12.5.

| Propriedade (peso 12.5) | DO-178C | ReqToCode | Polarion/Jama | Cucumber | OpenAPI+Pact | spec-kit/Kiro | **oimpresso** |
|---|---|---|---|---|---|---|---|
| **1 Verificável-máquina** | 11 (RTM tool, mas auditor humano no loop) | **12.5** (compilador) | 10 (tool, config humana) | 10 (runner executa) | **12.5** (spec executável) | 3 (markdown, review humano) | **11** (`anchor-lint.mjs` determinístico, sem IA) |
| **2 Prova-existência** | 12 (item obrigatório no RTM) | **12.5** (símbolo não existe = não compila) | 11 (link a artefato existente) | 9 (step sem def = undefined, mas warn) | 11 (endpoint tem que existir) | 2 (nada checa) | **12** (`existsSync` + ≥1 path obrigatório, `:197`) |
| **3 Prova-está-vivo** | 9 (verificação prova execução, mas "vivo no produto" é manual) | 6 (compila ≠ chamado/roteado) | 4 (link existe ≠ feature ligada) | 7 (step roda = código exercido) | 9 (Pact verifica provider no ar) | 0 | **12.5** (wired-check anti-zumbi, `:119` — *ninguém mais tem isso*) |
| **4 Prova-comportamento** | **12.5** (evidência de teste verde é mandatória p/ certificar) | 10 (`@VerifiesSWR` liga teste, mas verde é do CI à parte) | 5 (link "verifies" existe, não executa) | **12** (cenário VERDE é a própria doc viva) | **12.5** (contrato = teste executado) | 0 | **4** (`Testado em:` só prova arquivo existe — **G1**) |
| **5 Bidirecional** | **12.5** (forward+backward, órfão e req-sem-filho reportados) | 11 (impl→req e teste→req; reverso por busca de refs) | 11 (matriz N×N, suspect dos 2 lados) | 6 (cenário→step→código; reverso fraco) | 7 (consumer↔provider, mas só da API) | 1 | **6** (só forward; wired é meia-volta do reverso, `:119`) |
| **6 Sobrevive-rename/drift** | 10 (suspect manual + re-baseline na mudança) | 9 (símbolo compilável sobrevive refactor de path) | **11** (suspect-link automático quando ponta muda) | 5 (rename de step quebra; regex frágil) | 8 (schema muda → contrato vermelho) | 1 | **4** (path-string quebra no rename, silencioso — **G2**) |
| **7 Não-gameável** | 11 (auditoria externa, mas papel pode mentir) | **12.5** (req inexistente = build quebrado, impossível fingir) | 5 ("clear suspect" manual = gameável) | 7 (pode escrever step vazio que passa) | 10 (contrato real difícil de forjar) | 1 (IA marca feito, Fowler: agentes ignoram) | **6** (forte vs path falso; gameável no carimbo manual + `_pendente_` massa) |
| **8 Barata + grão-certo** | 6 (caríssimo — processo de certificação) | 7 (precisa de tooling de geração de Traceables) | 4 (licença + curadoria pesada) | 9 (1 cenário por comportamento, barato) | 8 (1 contrato por interação) | **11** (1 markdown, trivial — mas barato porque não prova nada) | **11** (1 linha por US, sem deps, fs-puro) |
| **TOTAL /100** | **86** | **84** | **63** | **61** | **58** (como trace de US) | **28** | **67** |

### 2.2 Ranking e por quê

| Pos | Sistema | Nota | Veredito de 1 linha |
|---|---|---|---|
| 🥇 | **DO-178C / ISO-26262** | **86** | Padrão-ouro: bidirecional completo + prova-comportamento mandatória (teste verde p/ certificar). Cai só em #8 (custo) e #7 (papel pode mentir, auditoria humana). |
| 🥈 | **ReqToCode** | **84** | Teto de não-gameabilidade (#7=12.5): req inexistente = build quebrado. Cai em #3 (compila ≠ roteado) e #4 (verde é do CI à parte, não do compilador). |
| 🥉 | **Polarion/Jama** | **63** | Forte em bidirecional (#5) e suspect-link automático (#6=11 — pega drift dos 2 lados). Mas o "clear suspect" é manual (#7=5, gameável) e link "verifies" não executa teste (#4=5). |
| 4º | **oimpresso** | **67** | *(acima de Polarion no total!)* Ganha em #3 (único com wired-check) e #1/#2/#8 (topo). Perde em #4 e #6 — os 2 gaps conhecidos. |
| 5º | Cucumber living-docs | 61 | Cenário verde É a doc (#4=12) mas reverso fraco e regex de step frágil a rename. |
| 6º | OpenAPI+Pact | 58 | Imbatível em comportamento de API (#4=12.5) mas só fala de API — não modela "US implementada e testada", grão errado pro caso. |
| 7º | spec-kit/Kiro | 28 | Não verifica nada. Markdown + review humano. Fowler confirma: agentes ignoram. |

**O vencedor é o DO-178C** porque é o único que pontua alto **simultaneamente** em prova-comportamento (#4) E bidirecional (#5) — as duas propriedades onde "o trace prova trabalho real", não forma. O ReqToCode é mais não-gameável (#7) mas perde porque "compila" não é "roteado e exercido". **Nota honesta:** num projeto não-safety-critical de 5 devs, o DO-178C é inviável (#8=6, custo de certificação) — ele é o teto teórico, não o alvo prático.

### 2.3 oimpresso lado-a-lado com o vencedor (onde perde, onde já ganha)

| Propriedade | DO-178C | oimpresso | Quem ganha |
|---|---|---|---|
| 1 Verificável-máquina | 11 | 11 | **empate** |
| 2 Prova-existência | 12 | 12 | **empate** |
| 3 Prova-está-vivo | 9 | **12.5** | **oimpresso** (wired-check anti-zumbi — DO-178C confia em verificação manual de "deployed") |
| 4 Prova-comportamento | **12.5** | 4 | DO-178C (−8.5 — **o maior gap, G1**) |
| 5 Bidirecional | **12.5** | 6 | DO-178C (−6.5) |
| 6 Sobrevive-rename | 10 | 4 | DO-178C (−6 — **G2**) |
| 7 Não-gameável | 11 | 6 | DO-178C (−5) |
| 8 Barata + grão | 6 | **11** | **oimpresso** (+5 — DO-178C é caríssimo) |

**Leitura:** o oimpresso é o **DO-178C dos pobres** — paga 1 linha/US e entrega 67% do padrão-ouro, ganhando inclusive em 2 dimensões (está-vivo e custo). A distância de 19 pts pro 1º lugar concentra-se em **3 células**: #4 (−8.5), #5 (−6.5), #6 (−6). Fechar G1 (#4: `#[CoversUS]` + teste verde) sozinho leva o oimpresso de 67 → ~76 (assumindo #4 sobe de 4 pra ~10), ultrapassando Polarion e empatando a vizinhança do ReqToCode em utilidade prática.

---

## PEDIDO 3 — TESTE DE FOGO: nota fiscal (o critério de aceite do Wagner)

### 3.1 A US fiscal densa real — contagem de regras e testes

Peguei do `NfeBrasil/SPEC.md` real. A "estrutura de NF-e" do Wagner **não é 1 US com 50 regras** — é uma malha:

- **US-NFE-010** (motor tributário por NCM, `:214-258`): a mais densa. **14 DoD checkboxes** (`:229-258`), dos quais 5 `[x]` feitos e 9 `[ ]` pendentes (CSV, buscador NCM, preview cálculo, bridge, override, evento, audit, multi-tenant, permissão), **+ 6 sub-testes** explícitos no último DoD (`:251-257`: criar+duplicidade, CSV happy+inválido, cascade 2→3→4, bridge, preview shape, **multi-tenant isolation**). Cobre CST/CSOSN exclusivo (`:230`), ICMS/ICMS-ST/MVA/IPI/PIS/COFINS/CBS/IBS (`:223`), cascade Nível 1-4 (`:232`).
- **14 regras de negócio** R-NFE-001..014 (`:261-425`), cada uma um cenário Gherkin **+ seu próprio `Testado em:`**: isolamento multi-tenant (`:270`), permissões (`:281`), numeração sem gap com lock (`:292`), cert expirado bloqueia (`:305`), idempotência (`:316`), cripto-at-rest (`:328`), senha nunca logada (`:339`), prazo legal cancelamento 24h/168h (`:354`), SEFAZ-down→contingência (`:366`), retenção XML 5 anos imutável (`:378`), CBS/IBS NULL 2026 (`:390`), assíncrono não-bloqueia-venda (`:403`), audit log toda mutação (`:414`), webhook idempotência (`:425`).
- Mais o ciclo de vida completo nas US 002-009 (emitir, ver, **cancelar com prazo legal**, **CCe limite 20×**, **contingência EPEC/FS-DA**, monitor de **rejeição cStat**, manifestação 4 eventos, SPED).

**Contagem honesta:** ~**1 US-épico + 14 regras Gherkin + ~20 DoD checkboxes + ~6 sub-testes nomeados** só na fatia tributária. A NF-e inteira (US 001-010 + R 001-014) implica **na ordem de 40-60 testes** distintos. Isto é exatamente a densidade que o Wagner chama de "uma das mais difíceis".

### 3.2 O schema do Pedido 1 PREENCHIDO nessa US fiscal

Demonstração da US-NFE-010 no schema proposto (N regras → N testes → aceite → cliente):

```markdown
### US-NFE-010 · Cadastrar regra tributária por NCM (motor)
> owner: wagner · priority: p1 · type: story
> **Objetivo:** automatizar cálculo de imposto na emissão sem digitar imposto a imposto
> **Sinal:** CS-NFE-031 (ROTA LIVRE biz=4, paga) · CS-NFE-044 (Gold, on-prem)

**Implementado em:** `Modules/NfeBrasil/Services/MotorTributarioService.php` · `.../TributacaoController.php` · `resources/js/Pages/NfeBrasil/Tributacao/Index.tsx` · verificado@08c4a8f (2026-06-21)

**Definition of Done (cada aceite → 1 teste):**
- [x] Cascade Nível 1→4              → `MotorTributarioServiceTest::cascade_fallback_nivel_4`  ✅verde
- [x] CST OU CSOSN exclusivo          → `UpsertRegraTributariaRequestTest::cst_csosn_exclusive` ✅verde
- [x] Multi-tenant scope business_id  → `MotorTributarioServiceTest::biz_a_nao_ve_regra_de_b`   ✅verde  ← Tier 0
- [ ] Importação CSV em massa         → _pendente_ (teste a criar)
- [ ] Bridge SyncFiscalRuleToTaxRate  → _pendente_

**Testado em:** `MotorTributarioServiceTest` #[CoversUS('US-NFE-010')] (10 cenários) · `TributacaoControllerTest` #[CoversUS('US-NFE-010')] (7 cenários) · verificado@08c4a8f
```

E as 14 regras já ESTÃO no formato 1-regra→1-teste no SPEC real — ex. `R-NFE-001 · Isolamento multi-tenant` + `**Testado em:** MultiTenantIsolationTest` (`:270`). **O schema do oimpresso já modela N:N de regra↔teste nativamente** — não precisa inventar nada pra capturar a malha. Esse é o achado central do teste de fogo: a estrutura **densa** já cabe.

### 3.3 ONDE QUEBRA (cético, sem inflar)

**Quebra 1 — `1 US com 50 regras → âncora 1:N de testes não prova verde (G1 amplificado).**
O `**Implementado em:**` da US-010 aponta 3-8 paths e dá `anchored_ok` se todos existem (`anchor-lint.mjs:201`). Mas a US-010 tem 6 sub-testes nomeados + 14 regras com testes próprios. **`anchored_ok` não diz que NENHUM deles passa.** Em fiscal isso é grave: o `MotorTributarioServiceTest` pode existir e estar **vermelho** (e de fato US-NFE-056 `:840` documenta `TributacaoNaoConfiguradaException` quebrando esse exato teste). Hoje a âncora daria verde com o teste vermelho no disco. **G1 não é higiene aqui — é a diferença entre "calcula ICMS certo" e "multa".**

**Quebra 2 — a malha 1:N não tem cardinalidade verificada.**
A US-010 implica 6 testes; o `Testado em:` cita 1-2 classes. Nada no lint exige que **todos** os 14 DoD verificáveis tenham um teste apontado. Uma US fiscal pode marcar `anchored_ok` cobrindo 2 das 14 regras e o lint não percebe a lacuna de cobertura **dentro da US**. Falta um lint de **completude DoD↔teste** (o item 5 do schema do Pedido 1).

**Quebra 3 (a mais séria) — rejeição SEFAZ muda fora do repo: NENHUMA âncora pega.**
O cenário do Wagner: SEFAZ publica uma NT que muda a rejeição (ex. cStat 539 "duplicidade de NF-e" passa a exigir novo campo, ou a regra de cálculo de ICMS-ST muda por convênio CONFAZ). **O código não muda, a SPEC não muda, o teste não muda — e todos continuam VERDES.** Mas a regra fiscal real agora está errada. Pergunta do Wagner: *o full-tree cron pega? a âncora sobrevive?*

Resposta honesta: **NÃO.** O `anchor-lint.mjs` full-tree (`:269`) só compara SPEC↔disco do **próprio repo**. Toda âncora do oimpresso — e do ReqToCode, e do Cucumber — é **interna**. Drift de fonte externa (legislação) é invisível pra ela. O cron roda verde enquanto a Receita já rejeita as notas. **Este é o único ponto onde Polarion/Jama (suspect-link) e DO-178C (re-baseline na mudança de norma) têm algo que o oimpresso não tem** — mas mesmo eles só pegam se um humano **editar o requisito** quando a NT sai; o suspect-link dispara na edição, não na publicação da SEFAZ. Ninguém resolve isso por âncora pura. Resolve-se por **sentinela de fonte externa**.

**Quebra 4 — Tier 0 sob fiscal.** R-NFE-001 (isolamento) tem `Testado em: MultiTenantIsolationTest` (`:270`). Se essa âncora for `anchored_ok` por existência mas o teste estiver vermelho (G1), uma US fiscal pode vazar tenant com a âncora verde. **Em fiscal, vazamento cross-tenant = uma empresa emite nota no CNPJ de outra = catástrofe legal** (o próprio SPEC nota isso em US-NFE-041 `:483`). G1 aqui é defesa de segurança P0, não higiene.

### 3.4 O que MUDA pra aguentar 100%

| # | Brick | Resolve a quebra | Esforço (IA-pair, ADR 0106 10×) | Pré-req |
|---|---|---|---|---|
| 1 | **G1 — `#[CoversUS]` + teste verde** (a recomendação da base) | Quebra 1, 2, 4 | médio ~3-4h IA-pair | nenhum bloqueante |
| 2 | **Lint completude DoD↔teste** (item 5 do schema) | Quebra 2 | baixo ~1-2h IA-pair | depende de G1 |
| 3 | **Sentinela de fonte externa fiscal** (NOVO) — versão de NT/layout SEFAZ carimbada na SPEC fiscal; sentinela (estilo `memory-health.mjs`) compara contra a NT vigente publicada e avermelha quando a SEFAZ publica versão > a ancorada | **Quebra 3** (a única que âncora pura não resolve) | médio ~4-6h IA-pair (a 1ª vez; precisa de fonte de verdade da NT) | depende de uma fonte da versão vigente da NT (feed/scrape SEFAZ) |
| 4 | **`Sinal:` → `client_signal`** (campo cliente do Pedido 1) | liga ROTA LIVRE/Gold à US fiscal | baixo ~1-2h | depende de `client_signal` no MCP (US-INFRA-002) |

### 3.5 VEREDITO (3 linhas)

1. **A estrutura AGUENTA a densidade da NF-e** — N regras→N testes→aceite→cliente já é modelável hoje (o par `R-NFE-NNN` + `Testado em:` faz 1:1 nativo; o schema do Pedido 1 fecha objetivo/aceite/cliente). Não quebra sob volume de regras.
2. **NÃO aguenta sem G1** — `anchored_ok` por existência deixa passar teste fiscal vermelho (US-NFE-056 prova que acontece), e em fiscal teste vermelho = multa ou vazamento Tier 0. G1 é pré-condição, não melhoria.
3. **NÃO aguenta o drift externo da regra SEFAZ com âncora pura** — nenhuma âncora (oimpresso, ReqToCode, DO-178C) pega NT que muda fora do repo; precisa do **brick 3 (sentinela de fonte externa)**, que é a única peça genuinamente nova que a nota fiscal exige além do roadmap já conhecido.

---

## Recomendação consolidada

**Comece por G1 (`#[CoversUS]` + teste verde) — alto-impacto, esforço médio, sem pré-req bloqueante, e é o que destrava 3 das 4 quebras fiscais + sobe a nota do oimpresso de 67 → ~76.** É a mesma recomendação da base, agora **confirmada pelo teste de fogo**: a nota fiscal é precisamente o caso onde "arquivo existe" ≠ "comportamento certo" tem consequência legal. Os bricks 2 e 4 dependem dele. O brick 3 (sentinela de NT externa) é o único item novo que a nota fiscal adiciona ao roadmap — e ele NÃO bloqueia G1; entra como onda fiscal própria depois.

**Próxima ação hoje:** rascunhar a emenda ao ADR 0273/0303 que (a) define `#[CoversUS('US-X')]` como atributo Pest obrigatório no teste citado em `**Testado em:**`, (b) faz o `anchor-lint.mjs` exigir a ligação bidirecional teste↔US (não só basename existe), e (c) prova a regra com fixture good/bad no `gate-selftest.mjs` (GT-G6) ANTES de armar. Validar o desenho contra a US-NFE-010 + R-NFE-001 (multi-tenant) como caso-teste fiscal — se a emenda força o `MultiTenantIsolationTest` a estar verde e a declarar `#[CoversUS('US-NFE-010')]`, a defesa Tier 0 via âncora fica provada no domínio mais hostil.
