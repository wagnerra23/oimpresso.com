---
date: "2026-06-23"
topic: "Estado-da-arte de âncora spec↔código — rubrica de uma âncora boa, SOTA × oimpresso, formato proposto e pegadinha do backfill"
authors: [C]
type: session
---

# Âncora spec↔código: o que torna uma âncora ROBUSTA (estado-da-arte × oimpresso)

## TL;DR

A âncora `**Implementado em:**` (ADR 0273/0302/0303) é a fonte-única de done-ness do oimpresso. Hoje ela prova **3 coisas** de forma determinística: (1) o path **existe** no disco, (2) a Page está **viva** no roteador (wired-check anti-zumbi), (3) o teste citado **existe** como arquivo. Isso já é mais forte que o SOTA mainstream de Spec-Driven Development (spec-kit/Kiro **não verificam nada** — geram markdown e confiam em review humano). Onde a âncora ainda é **fraca/gameável**: prova existência do teste, não que ele **passa** nem que **cobre a US certa** (`Testado em: \`SpatiePermissionsTest\`` é aceito sem ligar ao comportamento da US); a âncora é por **path-string**, frágil a rename (link-rot silencioso vira `anchored_dead`); o carimbo `verificado@sha7` é **manual e envelhece** sem ninguém re-verificar; e símbolo (`Controller@metodo`) é só advisory (grep), nunca bloqueia. O paper **ReqToCode (arXiv 2603.13999)** mostra o teto: âncora como **dependência compilável** (Traceable enum), "broken trace = broken build", não-gameável porque você não consegue referenciar um requisito inexistente. O oimpresso não tem compilador no meio (markdown↔PHP/TSX), mas pode subir 1-2 níveis baratos. **Os 3 maiores gaps:** (G1) âncora não prova teste **verde** — só que o arquivo existe; (G2) âncora por path-string quebra no rename sem aviso até o lint rodar; (G3) o backfill de ~751 US sem campo (não 90 — os 90 são *contradições* status×âncora) vai criar âncoras `_pendente_` em massa, e a tentação é promover a `anchored_ok` só com `existsSync` true sem provar comportamento.

**Números reais de hoje** (`origin/main` via `_salvage-ap`, `node anchor-lint.mjs --json`): 864 US · **10.6% anchor_coverage** · **751 sem_campo** (87%) · 53 anchored_ok · 6 parcial · 33 pendente · 9 dead · 0 zombie · **62 dead_tests** · doneness: **90 conflitos** (75 done-sem-âncora + 15 aberto-com-âncora) + 364 zona-cinza.

---

## 1. PESQUISA — estado-da-arte (SOTA 2026)

Pesquisa limpa (WebSearch/WebFetch, sem ler oimpresso antes). 5 referências:

| Referência | Como liga spec↔código↔teste (mecanismo concreto) | Por que é referência |
|---|---|---|
| **ReqToCode** (arXiv 2603.13999, 2026) | Gera **Traceables** — elementos de código nativo (enum/constante tipada) a partir do requisito autoritativo. Impl e teste referenciam via `@TracesSWR(SWR_101)` / `@VerifiesSWR(SWR_101)`. **Verificado pelo compilador**: requisito inexistente = erro de compilação. Ciclo deprecation→removal (warning antes de quebrar build). | Teto teórico de não-gameabilidade: o trace é **propriedade estrutural do código**, não string em doc. "Broken trace = broken build." |
| **DO-178C / ISO 26262** (trace bidirecional) | RTM com links **forward+backward** entre necessidade→requisito→código→teste→resultado. Relatório de auditoria: requisitos **sem filho** (sem verificação) e código **órfão** (sem requisito). Mudança em qualquer elo → análise de impacto nos dois sentidos. | Padrão-ouro de safety-critical. Define o que é "trace forte": completo, bidirecional, prova que **toda** req tem evidência de verificação e **nenhum** código órfão escapa. |
| **Pact / contract testing** | Contrato "por exemplo": não é schema estático (todos estados possíveis) — é **coleção de testes executáveis**, cada um um par request/response concreto. Prova que o comportamento **real** do provider honra o que o consumer usa. | Distinção-chave do oimpresso: *"schema válido" é mais fraco que "compatível"*. Contrato = comportamento provado por execução, não forma declarada. |
| **OpenAPI executável** (Specmatic/Speakeasy) | Spec OpenAPI vira **contrato executável**: o mesmo arquivo que documenta é rodado contra a implementação (mock + verificação bidirecional). Drift spec↔code = teste vermelho. | Spec deixa de ser doc que mente: é executada. Ataca a doença "a spec mente" na raiz (a mesma que originou o ADR 0273). |
| **spec-kit / Kiro / Tessl** (Fowler, sdd-3-tools 2026) | 3 níveis: spec-first (descarta) → **spec-anchored** (spec vive e evolui) → spec-as-source (humano edita só spec, código é `// GENERATED — DO NOT EDIT`). Kiro liga tasks→requisito-número; Tessl é o único spec-anchored real. | **Achado honesto:** *nenhum dos três verifica que a US foi implementada e testada* — Fowler nota que agentes "ignoram notas", review humano de markdown verboso é o único gate. **O oimpresso já passou disso.** |

**Insight transversal:** a força de um trace cresce nesta escada — (a) **comentário/string** (gameável, link-rot) → (b) **string verificada por script contra o disco** (existe) → (c) **prova de que está vivo** (wired no roteador) → (d) **prova de comportamento** (teste verde que referencia a req) → (e) **dependência compilável** (ReqToCode: impossível mentir). O oimpresso está sólido em (b)+(c), tem (d) pela metade, e (e) é inalcançável no par markdown↔PHP/TSX sem tooling pesado.

---

## 2. COMPARA — rubrica de âncora BOA × o que o oimpresso faz hoje

**Rubrica (8 propriedades).** Cada uma com o porquê em 1 linha:

1. **Verificável-por-máquina** — humano não audita 864 US; só script determinístico escala.
2. **Prova-existência** — o alvo (arquivo/símbolo) tem que existir; senão é mentira.
3. **Prova-está-vivo** — existir no disco ≠ estar wired; tela deprecada atrás de 301 é zumbi (lição do caso Financeiro).
4. **Prova-comportamento** — o passo mais forte: não basta o código existir, o **teste tem que passar e cobrir a US** (Pact/DO-178C).
5. **Bidirecional** — da US→código **e** código→US; pega órfãos (código sem req) e requisitos sem verificação.
6. **Sobrevive-refactor/rename** — link por símbolo estável, não path-string; rename não deve quebrar trace em silêncio.
7. **Não-gameável** — humano/IA com pressa não consegue marcar "feito" sem prova estrutural (ReqToCode: requisito inexistente = build quebrado).
8. **Barata-de-manter + grão-certo** — 1 linha por US, sem tooling que ninguém roda; grão = 1 US ↔ 1 entrega verificável.

**Tabela rubrica × oimpresso** (evidência file:line de `_salvage-ap` = origin/main):

| # Propriedade | SOTA faz assim | oimpresso hoje | Veredito + evidência |
|---|---|---|---|
| 1 Verificável-máquina | RTM/lint, compilador | `anchor-lint.mjs` parseia gramática regex canônica | ✅ **Forte.** `GRAMMAR_RE` em `anchor-lint.mjs:51`; classify determinístico `:191`. Sem IA no runtime. |
| 2 Prova-existência | existsSync / compilador | `existsSync(p.abs)` por path | ✅ **Forte.** `classify` → `anchored_dead` se path falta (`anchor-lint.mjs:196-198`). 9 dead detectados hoje. |
| 3 Prova-está-vivo | route:list / wiring | **wired-check anti-zumbi** | ✅ **Forte e raro.** `pageZombie()`+`renderGraph()` (`anchor-lint.mjs:81-126`): Page só viva se controller **referenciado nas rotas** a renderiza. ADR 0303. Isso o SOTA mainstream **não tem**. |
| 4 **Prova-comportamento** | Pact: teste executado; DO-178C: evidência de verificação | `Testado em:` só checa que o **arquivo de teste existe** | 🟡→❌ **Fraco/gameável.** `deadTestRefs` (`anchor-lint.mjs:144-162`) valida basename existe, NÃO que passa nem cobre a US. `Testado em: \`SpatiePermissionsTest\`` (NfeBrasil/SPEC.md:281) é aceito sem ligar ao comportamento. 62 dead_tests hoje — mas os "vivos" só provam existência. |
| 5 Bidirecional | RTM forward+backward; órfão/dead-code report | Só **forward** (US→código). Reverso parcial: zumbi acha código-sem-rota-viva | 🟡 **Médio.** Sem report "código sem US" (órfão). O wired-check (`:119`) é meia-volta do reverso, mas não fecha. |
| 6 **Sobrevive-rename** | ReqToCode: símbolo compilável; AST | Âncora por **path-string** + símbolo advisory | ❌ **Fraco.** Rename de `.tsx`/`.php` → `anchored_dead` silencioso até o lint rodar. ADR 0273 reconhece: "âncora AST estilo Fiberplane Drift… fica como `anchor_format: v2`" (0273:125). Símbolo `Controller@metodo` é só grep advisory (0273:72). |
| 7 Não-gameável | Compilador rejeita req inexistente | Path real exigido + proveniência `verificado@sha7` | 🟡 **Médio.** Forte contra "path inventado" (`:197` exige ≥1 path). **Gameável** em: (a) carimbar `verificado@sha7` à mão sem re-verificar; (b) apontar teste que existe mas não cobre nada; (c) `_pendente_` conta como coberto — IA com pressa marca tudo `_pendente_` e a cobertura "sobe". |
| 8 Barata + grão | 1 linha/req | 1 linha `**Implementado em:**` por US | ✅ **Forte.** Grão = 1 US. Sentinelas `_pendente_`/`_parcial_` de 1ª classe (0273:84-93) evitam âncora-falsa pra tela não-construída. Custo: 1 linha. |

**Veredito honesto:** o oimpresso **supera o SOTA mainstream de SDD** (spec-kit/Kiro/Tessl não verificam nada — Fowler confirma) em verificabilidade (#1,2,3,8). O wired-check anti-zumbi (#3) é genuinamente à frente. Onde fica **atrás do safety-critical**: prova-de-comportamento (#4) e sobrevive-rename (#6). E tem 1 vetor de gameabilidade próprio: o carimbo manual e o `_pendente_`-em-massa (#7).

---

## 3. AVALIA — gaps rankeados (impacto × esforço IA-pair, ADR 0106 fator 10x)

| Gap | Impacto | Esforço IA-pair | Pré-req? |
|---|---|---|---|
| **G1 — `Testado em:` não prova teste verde** | **alto** — é a diferença entre "código existe" e "comportamento implementado e vivo" (o medo do Wagner). Hoje 62 dead_tests só medem existência | **médio** (~3-4h): ligar `Testado em:` ao **resultado** do Pest. Opção barata: lint exige que o teste **referencie a US-ID** num atributo/comentário (`#[CoversUS('US-WA-001')]`), grep-checável. Opção cara: parsear junit.xml do CI por US | não bloqueante; casa com `gate-selftest` GT-G6 |
| **G2 — âncora por path-string quebra no rename** | **médio** — link-rot vira `anchored_dead` em massa em qualquer refactor de pasta; ruído que mascara mentira real | **alto** (1-2 dias): `anchor_format: v2` por símbolo/AST (já previsto 0273:125). Mitigação barata agora: `--fix` re-verifica + re-carimba sha7 quando path move via `git log --follow` | depende de decidir v2; não fazer no backfill |
| **G3 — `_pendente_`-em-massa infla cobertura** | **médio** — IA com pressa marca 751 US `_pendente_`; cobertura "sobe" sem código. `_pendente_` conta como coberto (0273:90) | **baixo** (~1h): exigir **justificativa não-vazia** em `_pendente_` + cap: `_pendente_` > X% do módulo = 🟡 no scorecard | não bloqueante |
| **G4 — proveniência `verificado@sha7` envelhece** | baixo-médio — sha vira stale quando código move; ninguém re-verifica | **baixo** (~1-2h): `anchor-lint --reverify` re-roda existsSync+wired e re-carimba sha7/data do HEAD; CI nightly | não bloqueante |
| **G5 — sem report de código órfão (bidirecional)** | baixo (P2) — código sem US não é P0 num ERP brownfield; mas fecha o trace | **médio** (~4h): inverter o grafo — Pages/Controllers renderizados sem nenhuma US apontando | não bloqueante |

**Nota Tier 0:** nenhum gap aqui vaza tenant — âncora é metadado de doc, não toca `business_id`. Os testes que a âncora *deveria* exigir (`MultiTenantIsolationTest` já citado em NfeBrasil/SPEC.md:270) são justamente onde G1 paga: forçar que **toda US com escrita** aponte um teste de isolamento **verde** seria a defesa multi-tenant via âncora.

---

## 4. A PEGADINHA DO BACKFILL

Os números corrigem o prompt: **não são 90 âncoras faltantes — são ~751 US `sem_campo`** (87% de 864). Os **90 são contradições** status×âncora (doneness-lint). O backfill mecânico (estilo SA-A4) vai tocar centenas de US. O risco: **encher de âncora ruim no atacado** — promover a `anchored_ok` só com `existsSync` true, gerando "verdade cara que mente" em escala.

**Regra de aceite por âncora (o que exigir pra ela VALER):**

1. **Promoção a `anchored_ok` exige os 3 provas atuais juntas** — path existe (existsSync) **E** Page viva (wired-check) **E**, se a US tem critério verificável, `Testado em:` aponta teste **que existe**. Faltou qualquer uma → `_parcial_` com o que falta explícito, nunca `anchored_ok`.
2. **`verificado@sha7` só do commit onde o script REALMENTE rodou existsSync** — nunca carimbo copiado. O backfill mecânico carimba o sha do HEAD da verificação (0273:73). Carimbo à mão sem rodar lint = proibido.
3. **`_pendente_` exige justificativa não-vazia** — "tela X não construída (US planejada)", não `_pendente_` pelado. Senão vira lixeira que infla cobertura (G3).
4. **Backfill é diff-aware, não full-tree de uma vez** — reconciliar 1 módulo por PR (como foi o Financeiro: 8 zumbis + 31 fantasmas num PR). 751 US num PR = nova fonte de erro em massa (anti-padrão que o ADR 0302 §alt-3 já rejeitou).
5. **Não inventar path** — se o backfill não acha o código da US, é `_pendente_`, não um path chutado. O lint pega path falso (`anchored_dead`), mas path *plausível-mas-errado* (aponta o controller vizinho) passa — só humano/IA cuidadoso evita. Por isso: backfill mecânico só promove o que o `existsSync`+wired confirmam; o resto fica `_pendente_` pra revisão.
6. **Toda US com escrita (POST/PUT/DELETE) backfillada deve apontar — ou abrir dívida explícita de — um teste de isolamento multi-tenant** (Tier 0). É o ponto onde G1 vira defesa de segurança, não só higiene.

**Em uma frase:** backfill bom não maximiza cobertura — maximiza **âncoras que provam as 3 coisas**; o resto vira `_pendente_` honesto, que é melhor que `anchored_ok` mentiroso.

---

## Recomendação final

**Comece por G1 (âncora prova teste, não só existência) — alto-impacto, esforço médio, sem pré-req bloqueante.** É exatamente o medo do Wagner ("prova comportamento implementado e vivo, não só arquivo existe") e é o único gap que o SOTA safety-critical resolve e o oimpresso ainda não. A versão **barata** não precisa de junit-parsing: exigir que o teste citado em `Testado em:` **referencie a US-ID** (via atributo Pest `#[CoversUS('US-WA-001')]` ou comentário grep-checável), e que o `anchor-lint` valide essa ligação bidirecional além do basename. Isso fecha a brecha de `Testado em: \`SpatiePermissionsTest\`` (NfeBrasil/SPEC.md:281) — um teste real que não prova nada sobre a US.

**Próxima ação hoje:** rascunhar a emenda ao ADR 0273/0303 (novo `anchor_format` ou §estendido) que define o atributo `#[CoversUS]` + a regra de lint "Testado em: deve apontar teste que (a) existe E (b) declara a US que cobre", provada por fixture good/bad no `gate-selftest.mjs` (GT-G6) **antes** de armar — doutrina Onda 0 "cada brick prova que armou". NÃO mexer no backfill das 751 até G1 estar armado: senão backfilla 751 âncoras na regra fraca e refaz tudo depois.
