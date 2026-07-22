---
status: ativo
last_reviewed: "2026-07-21"
next_review: "2026-10-21"
related_adrs: [0093, 0256, 0264, 0155, 0230]
---

# FUNCAO-SCORECARD — método de PARECER por função (ancorado em rubrica)

> **O que é:** o `screen-grade` ([SCREEN-GRADE-METODO](../_DesignSystem/SCREEN-GRADE-METODO.md)) aplicado **por função relevante** — mas de PARECER, não de nota. Emite `concordo | discordo | incerto | n/a` **por critério**, ancorado em evidência do código e numa intenção externa ao código.
> **Linhagem:** module-grade (ADR 0155) → screen-grade (por tela) → **[novo] por função**. Score-as-code estilo Backstage Soundcheck / Cortex (a opinião é COMPILADA em regra, nunca prosa livre).
> **Origem:** pedido [W] 2026-07-21 — *"derive do código e escreva se concorda ou não com a função... o lado positivo e negativo do que fazer e não fazer."* Pesquisa que ancora o desenho: [session 2026-07-21 arte-catálogo (PR #4611)](https://github.com/wagnerra23/oimpresso.com/pull/4611). **Status: PILOTO** — só vale depois de passar o bite-test (§5).

## §0 Princípios duros (leia ANTES de qualquer veredito)

1. **A opinião livre da IA é CARIMBO.** Uma IA que lê uma função e diz "concordo com ela" tende a concordar com quase tudo que lê — sicofância é propriedade estrutural do RLHF (Anthropic 2024; leniência; auto-preferência). Portanto: **um veredito só existe julgado contra uma RUBRICA EXTERNA declarada ANTES de ler a função** (este doc), nunca contra o gosto do juiz. É o §5 de [proibicoes.md](../../proibicoes.md) 2026-06-05 ("teste tautológico derivado do código") aplicado à OPINIÃO.
2. **PLUGAR, NÃO FUNDIR** (igual SCREEN-GRADE §3-bis): **NÃO existe nota agregada.** Os 11 vereditos (C1–C6, C7a–C7d, C8) ficam LADO A LADO + contagem (`totals`). Fundir critérios ortogonais numa nota recria a superfície de sicofância (uma nota alta "perdoa" um critério vermelho) e convida pressão de catraca antes do juiz estar provado.
3. **Veredito é PARECER, não ACHADO.** Um `discordo` **NÃO autoriza** um fix, nem cria task. Ele é candidato a US via aprovação humana. Qualquer correção segue o protocolo §5 2026-07-15: **varredura contada de TODOS os consumidores + âncora de contrato citada + teste vermelho** — e, se tocar valor/estoque, a REGRA MESTRE (dupla confirmação + impacto antes→depois). Ver §6.
4. **`n/a` honesto é esperado, não fraqueza.** Um critério que não se aplica à função (ex.: C2 numa função de string pura) = `n/a`. **Esticar** um critério pra "achar" algo é o modo de falha — tão ruim quanto carimbar.
5. **`incerto` é obrigatório quando falta intenção externa ou evidência suficiente.** Código prova o que existe; sozinho, não prova que o comportamento é desejado. Proibido converter ausência de contexto em `discordo`.
6. **Escopo por risco, não censo de funções.** Persiste parecer apenas para função que toca valor/estoque, tenant, escrita em banco, API pública, segurança/compliance ou tem alto fan-in. Getter, formatter e wrapper triviais ficam fora salvo incidente concreto.

## §1 Os critérios (a rubrica — geral, não retro-derivada de nenhuma função)

Cada critério é **best-practice/canon**, derivável só do canon (não da função julgada — senão a rubrica vaza o veredito, "rubric artifact"). `como-medir` é binário.

> **rubric v1.1 (2026-07-21) — C7 desdobrado em C7a–C7d.** No test-retest (§5) o C7 monolítico **flipou** (`getProductDiscount`: 2 discordo, 1 concordo) porque seu `como-medir` misturava 3 perguntas independentes — (a) docblock bate com o retorno? (b) retorno polimórfico ambíguo? (c) nullabilidade/falha silenciosa? Um único veredito forçava o juiz a pesar as três de uma vez → subjetivo. Regra §5 ("critério que flipa ⇒ endurecer pra binário") aplicada: cada pergunta virou um sub-critério **ortogonal + binário**, emitindo seu próprio veredito. Total agora: **11 vereditos** (C1–C6, **C7a–C7d**, C8). Lineage do flip real ancorada em `app/Utils/ProductUtil.php:1532-1591` (`@return obj discount` vago + `first()` que devolve `?Discount` sob assinatura sem tipo — o pedaço "docblock vago" vira C7a, o "null não-tipado" vira C7c, e C7b/C7d ficam `n/a`; a fusão sumiu, o flip com ela).

| id | critério | âncora canon | como-medir (binário) | quando `n/a` |
|---|---|---|---|---|
| **C1** | **Escopo multi-tenant** — toda query em tabela tenant-owned filtra `business_id` (direto ou via relação escopada); nenhum `Model::find($idDoRequest)` cru em dado de negócio | [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) | há query a Model de negócio SEM `business_id`/global scope? | função não toca DB |
| **C2** | **Defensibilidade de VALOR/ESTOQUE** — função que calcula/muta valor ou quantidade tem prova golden/property/2 caminhos; parsing é julgado pelo contrato real da entrada, não pelo nome do helper | [proibicoes REGRA MESTRE](../../proibicoes.md) + incidente num_uf 2026-06-05 | mexe em valor/estoque E não há prova, ou o contrato da entrada demonstra ambiguidade? | função não toca valor/estoque |
| **C3** | **Contrato de dado-ausente (SENTINELA não-null)** — retorno pra 0-row/entrada vazia é explícito, não um **sentinela não-null** (`''`/`false`/`0`/`-1`) que o consumidor precisa adivinhar. **O caso null é de C7c** (partição, não sobreposição — ver Fronteira abaixo) | proibicoes §5 2026-07-15 + US-PROD-027 | há caminho 0-row que retorna um **sentinela não-null** (`''`/`false`/`0`) indistinguível de dado real? | função sempre retorna dado presente, OU o retorno de ausência é `null` (→ C7c) |
| **C4** | **Atomicidade** — mutação multi-escrita (estoque, linhas de pedido) roda em `DB::transaction` OU declara que o caller envolve | proibicoes (estoque) + Laravel | há N escritas que deveriam ser atômicas fora de transaction? | função não escreve, ou 1 escrita só |
| **C5** | **N+1 / query-em-loop** — nenhuma query por iteração quando há forma set-based | `AUDITORIA-PERFORMANCE-2026-07` + Eloquent | há query dentro de `foreach`/`map` evitável? | função sem loop sobre coleção |
| **C6** | **Higiene de SQL cru** — `whereRaw`/`DB::raw` só com bindings, nunca interpolação de variável | Laravel security + espírito ADR 0093 | há `Raw` com `"...$var..."` interpolado? | função sem SQL cru |
| **C7a** | **Docblock/tipo ⟷ retorno real** — o `@return`/return-type declarado descreve os tipos REALMENTE devolvidos nos caminhos que retornam valor (posto de lado null → C7c e multi-shape → C7b) | família §5 2026-07-15 (achado/contrato) | (i) o tipo declarado CONTRADIZ um caminho de return real **não-null** (classe/escalar/objeto errado); **OU** (ii) o docblock usa um **pseudo-tipo genérico** (`obj`/`object`/`mixed`/`array` sem shape) quando a função retorna um **único tipo concreto conhecível** (ex.: `@return obj` de um `first()` que devolve `Discount`)? | sem return-type **e** sem `@return` (nada declarado a contradizer) |
| **C7b** | **Retorno polimórfico ambíguo (NÃO-declarado)** — não devolve tipos incompatíveis em caminhos diferentes (`false`\|array\|string) **sem declarar o union**, forçando o caller a type-check às cegas. Simétrico ao C7c: union **declarado** (`int\|string`) é honesto (`concordo`), como o `?T` declarado é OK em C7c | família §5 2026-07-15 (achado/contrato) | há ≥2 caminhos de return com tipos incompatíveis load-bearing **sem `int\|string` union type nem `@return A\|B` declarado** (excluído o nullable puro `?T` → C7c)? | retorno de tipo único (incl. uniformemente `?T`), OU o union é **declarado** na assinatura/`@return` |
| **C7c** | **Nullabilidade TIPADA (não silenciosa)** — quando há caminho de ausência/falha que devolve null, a assinatura/docblock declara `?T`/`@return T\|null` (contrato explícito), não null cru sob tipo não-nullable/`mixed`/ausente | proibicoes §5 2026-07-15 + fronteira com **C3** | há caminho que retorna null SEM `?T`/`T\|null` declarado (null silencioso)? | função nunca retorna null/ausência |
| **C7d** | **Falha observável (supressão MECÂNICA de erro)** — exceção/erro não é engolido em silêncio; é throw/Log/rethrow/retorno-de-erro tipado | família §5 2026-07-15 (falha) | há uma dessas formas mecânicas: `catch` **vazio**; `catch` que retorna/segue **sem** `Log`/rethrow; operador de supressão `@`? (o caso "retorno default benigno que esconde ausência" NÃO é C7d — é dado-ausente → C3) | função sem `try/catch`, sem `@`, sem caminho de erro |
| **C8** | **Cobertura contada** — Nº de testes em lane CI que **invocam diretamente** a função + Nº de consumidores, ambos declarados como NÚMERO (`git grep` sem `head_limit`; comentário/menção não conta como teste) | [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) + SDD/CU | a função tem 0 teste direto OU o nº de consumidores não foi contado? | — (sempre aplica) |

> **Fronteira C3 ↔ C7c — PARTIÇÃO por caminho de ausência (não é dupla-punição, não é sobreposição).** O retorno de um caminho de ausência é OU um sentinela não-null OU null — nunca os dois. Cada caso tem UM dono, então o mesmo defeito nunca conta 2× no `totals.discordo`:
> - retorna **sentinela não-null** (`''`/`false`/`0`/`-1`) → **C3** julga (distinguível? em geral `discordo`; se a intenção do `0`/`''` não é decidível sem SPEC, `incerto`). **C7c = `n/a`** (não retorna null).
> - retorna **null** → **C7c** julga (declarado `?T`/`T\|null`? `concordo`; null cru sob tipo não-nullable/`mixed`/ausente? `discordo`). **C3 = `n/a`** (o caso null é de C7c).
> - retorna **valor explícito tipado** para a ausência (ex.: `Collection` vazia, DTO "não encontrado") → **C3 `concordo`** (distinguível), **C7c `n/a`**.
> Logo um `?T` bem tipado dá **C7c `concordo` + C3 `n/a`** (não "concordo nos dois" — isso re-somaria a mesma coisa que a partição existe pra separar). Carimbar um `?T` declarado como violação é o modo de falha que o t11 vigia.

> **Escopo honesto do desdobramento (não trate os 4 como flip-provados).** O flip real (`getProductDiscount`) forçou só a separação **C7a (docblock vago) + C7c (null não-tipado)** — eram as duas perguntas que colidiam num veredito. **C7b e C7d são adições de completude proativa** (ambos `n/a` para `getProductDiscount`); valem por cobrir a família "verdade de tipos/falha", mas não foram provados por um flip — foram calibrados por fixture sintética (§5). **Residual conhecido, deixado FORA dos 4 de propósito (anti over-fit, §5 2026-06-05):** polimorfismo *dentro* de um supertipo honestamente declarado — ex.: `@return iterable` que às vezes é `array`, às vezes generator (um `count()`/rewind no caller quebra). C7a é honesto (iterable é verdade), C7b vê "tipo declarado" (`n/a`), C7c/C7d `n/a` — cai na fresta. Catalogado como limite do PILOTO, não forçado pra dentro da rubrica.

## §2 Regra de evidência (o que torna o parecer não-carimbo)

- Todo `concordo`/`discordo` **exige uma citação extrativa** — um trecho literal do arquivo julgado + o intervalo de linhas. **Veredito sem citação = INVÁLIDO** (o consumidor do YAML rejeita).
- A citação prova que o juiz LEU aquele ponto — é a defesa contra "concordo genérico".
- `concordo`/`discordo` também exige **âncora de intenção externa**: SPEC, charter, ADR, regra do dono, teste golden aprovado ou evidência de runtime. Se só houver código, use `incerto`.
- **NUNCA colar valor monetário (R$)** de comentário/fixture numa citação (regra BRL de proibicoes) — citar código é ok, valores não.

## §3 Formato YAML (score-as-code — 1 por ARQUIVO)

1 YAML por arquivo (não por função; 38 funções = 1 arquivo), em `memory/governance/scorecards/funcoes/<path-slug>.yaml`. **Sem nota agregada** (§0.2). Shape:

```yaml
schema_version: 1
file: app/Utils/ProductUtil.php
module: Produto
rubric: memory/requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md
rubric_version: "1.1"            # 1.1 = C7 desdobrado em C7a–C7d (2026-07-21)
judged_at: "2026-07-21"
judge: "claude-<model>"          # test-retest exige registrar o juiz
source: bite-test-2026-07        # ou full-sweep-<data>
totals: { concordo: 0, discordo: 0, incerto: 0, na: 0 }
functions:
  - function: getVariationGroupPrice
    line: 1050
    vereditos:
      C1: { status: incerto, evidencia: { quote: "VariationGroupPrice::where('variation_id', $variation_id)", linhas: "1052-1055" }, ancora: "0093", nota: "confirmar ownership/global scope antes de concluir" }
      C2: { status: concordo, evidencia: { quote: "$price_inc_tax = $this->calc_percentage(...) ", linhas: "1067" }, ancora: "golden CalculoValorProdutoTest", nota: "calcula preço e possui prova" }
      # C3..C6
      C7a: { status: concordo, evidencia: { quote: "@return float", linhas: "1048" }, ancora: "família 2026-07-15", nota: "docblock bate com o retorno não-null" }
      C7b: { status: na, nota: "retorno de tipo único (não polimórfico)" }
      C7c: { status: discordo, evidencia: { quote: "return $row?->price ?? null;", linhas: "1071" }, ancora: "família 2026-07-15", nota: "null sob assinatura sem ?T — nullabilidade silenciosa" }
      C7d: { status: na, nota: "sem caminho de erro/exceção" }
      # C8
pendencias: []                   # discordos → candidatos a US (aprovação humana, NÃO fix automático)
```

> **Migração de scorecards v1.0 → v1.1:** YAML antigo com a chave única `C7` continua legível (histórico); ao **re-julgar**, o `C7` é substituído pelas 4 chaves `C7a`/`C7b`/`C7c`/`C7d`. Nenhum scorecard v1.0 é reescrito à mão (fóssil datado); a divisão vale forward-only, a partir de `rubric_version: "1.1"`.

## §4 Protocolo do juiz (ordem é load-bearing)

1. **Lê ESTA rubrica primeiro** — nunca julga de cabeça.
2. Lê a função-alvo + o contexto ao redor no arquivo.
3. **Varreduras contadas** (§5 2026-07-15): `git grep -n "<fn>" -- '*.php'` **sem `head_limit`**; declara "N consumidores" e "N testes" como NÚMERO pra C8.
4. Por critério: `concordo | discordo | incerto | n/a` + citação extrativa + âncora de intenção. Sem base suficiente ⇒ `incerto`, nunca conclusão forçada.
5. Vocabulário: **"parecer"**. Proibido propor edit de código ou criar task (§0.3 / §6).

## §5 Bite-test (o gate de confiança — análogo ao SCREEN-GRADE §7)

O método **não vale para expansão/catraca** até o juiz provar que DISCRIMINA. `ProductUtil.php` serviu como piloto exploratório, não como benchmark. A prova seguinte deve rodar em fixture imutável própria:

- **T2 · Validade discriminante:** usar fixture imutável com mutações sintéticas e gabarito fora da sessão-juiz. O juiz deve identificar **≥2 de 3 famílias** com linha/mecanismo certos, marcar como `incerto` o caso sem intenção suficiente e produzir zero `discordo` no controle limpo. Código de produção não pode ser chamado de “defeito plantado”.
- **T1 · Test-retest:** 3 sessões frescas (só rubrica+código) → concordância por-critério **≥90%** (≤3 flips em ~32 vereditos). Critério que flipa `concordo↔discordo` ⇒ `como-medir` subjetivo ⇒ endurecer pra binário + 1 re-run.
- **Contaminação conhecida:** `proibicoes.md` é contexto always-on, então o juiz pode "saber" que há defeitos neste ecossistema. Por isso T2 mede **linha + mecanismo exatos** (não "existe um problema"), e a função-controle limpa é a metade NÃO-contaminada.
- **Correção do piloto inicial:** `calculateInvoiceTotal()` tem teste golden contra inflação e `num_uf()` foi endurecida; portanto, C2 não possui gabarito `discordo` pré-declarado. O veredito deve emergir de contrato+evidência, ou ser `incerto`.
- **Se FALHAR:** o piloto **FALHA e a gente diz isso** — registra no session log + a proposta vira `parked/rejected` (cultura lápide). YAML não shippa.

### Ledger do bite-test

**2026-07-21 · `app/Utils/ProductUtil.php` · 3 juízes frescos · INVALIDADO pela revisão central.**

- A corrida inicial registrou 31/32 concordâncias entre juízes, mas isso mediu **repetibilidade**, não correção: todos receberam a mesma rubrica com o mesmo gabarito pré-declarado.
- T2 foi circular ao chamar código de produção de “defeito plantado” e antecipar `calculateInvoiceTotal C2`. A função possui golden direto contra inflação (`CalculoValorSellsTest:137`, invocação na linha 144) e `num_uf()` possui testes específicos; o uso do helper não prova ambiguidade.
- C8 informou 5 testes para `calculateInvoiceTotal`; a varredura de invocações encontrou 1 teste direto. Menções em comentários/arquivos da família não contam como execução da função.
- Os pareceres C1/C3/C7 de `getVariationGroupPrice`, C6 de `getProductDiscount` e C1/C3/C7 de `calculateInvoiceTotal` permaneceram hipóteses úteis ancoradas; a invalidação recaiu sobre o claim “bite-test passou”, não sobre cada linha do scorecard.
- Próxima prova válida: fixture imutável com mutações sintéticas + controle limpo + gabarito fora da sessão. Até lá, `validation_status: invalidado` no scorecard e nenhuma catraca/expansão automática.

**2026-07-21 (rodada 2) — a fixture não-circular · o INSTRUMENTO passou.**
- Fixture: [`tests/governance-fixtures/funcao-scorecard/`](../../../tests/governance-fixtures/funcao-scorecard/) — twins **sintéticos** (código fabricado, `Widget`/`Gadget`/... não existem no repo → o juiz não pode saber a resposta do contexto), rótulo = a **mutação** (objetivo, `label_source: mutation`, estilo CodeJudgeBench/SWE-bench), gabarito SELADO, juiz **cego** (roda `--pack` manifest-free; nunca abre o selado).
- **3 juízes frescos, todos CALIBRADO:** T2 discriminante = 4/4 famílias (C1/C2/C3/C6) com o critério certo · **0 discordo no controle limpo** (t07) · **incerto no sem-âncora** (t08) · 0 falso-discordo nos bons. **T1 = 100% de concordância** (0 flips/20) — e aqui o 100% mede correção+repetibilidade (o rótulo é objetivo+externo), não só repetibilidade como a rodada 1 circular.
- Runner: `scripts/governance/funcao-scorecard-calibracao.mjs` (`--pack`/`--score`/`--selftest`) + self-test 5/5 (juiz-carimbo FALHA, juiz-perfeito PASSA).
- **Fronteira honesta:** isto calibra o **INSTRUMENTO** (o juiz discrimina defeito mecânico não-circularmente). NÃO re-valida os vereditos da função REAL (`ProductUtil`) — esses seguem o review central do 4617 + a âncora de intenção por-função do tópico. Fixture é **complementar**, NÃO estende, o ledger `tipo:"juiz"` (humano-só de propósito). Grade 2026-07-21 dimensão validação-não-circular 4/10 → o gargalo agora tem prova.

**2026-07-21 (rodada 3 — "arrume", twins DIFÍCEIS + κ) — os 3 juízes cegos passaram nas armadilhas.**
- Adicionados 3 twins DIFÍCEIS (onde um juiz preguiçoso erra): `t09` escopa por `location_id` (parece escopado, não é business_id) → C1 discordo · `t10` cita golden que cobre outra operação → C2 discordo (o golden tem que cobrir O VETOR) · `t11` retorno `?Coupon` tipado → C3 **concordo** (nullable tipado é contrato, não o empty-string do t05). 100% em caso óbvio prova pouco; o valor está nas armadilhas.
- **3 juízes frescos, todos CALIBRADO:** **6/6 famílias** (incl. as 2 difíceis) com o critério certo · **κ (Cohen, chance-corrected) = 1,0** ≥ 0,6 · 0 over-flag no controle · incerto certo · **0 falso-discordo no `t11`** (não carimbaram o nullable tipado). **T1 = 100%** (0 flips/10) no set difícil.
- Runner ganhou **κ** + bar ≥80% famílias; self-test 5/5. Evidência reprodutível: `calibracao-2026-07-21/judge-hard-a{1,2,3}.json`.
- Grade re-pontuada: validação-não-circular **6,5 → 7,5** (armadilhas + κ passaram). Falta pra 8-9: braço-incidente com função REAL (não sintética), κ vs gold HUMANO (hoje é κ vs rótulo objetivo), N maior, braço-vazado rodado (baixo valor em fixture sintética — a cegueira é por construção).

**2026-07-21 (rodada 4 — braço-incidente + C4/C5/C7 · N 11→20) — 3 juízes cegos frescos passaram no set expandido.**
- **Braço-incidente (t12/t13/t14):** 3 twins que MODELAM defeitos REAIS já catalogados, com o **rótulo ancorado no TESTE DE REGRESSÃO real** (não na minha mutação, não em opinião): `t12` num_uf-inflação (desconto % → float 5 casas lido como milhar) ancorado em [`IncidentValorInfladoNumUfTest`](../../../tests/Unit/Utils/IncidentValorInfladoNumUfTest.php) → C2 discordo · `t13` IDOR escrita cross-tenant (`findOrFail` sem global scope + update sem `business_id`) ancorado em [`UpdateCrossTenantIdorTest`](../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php) → C1 discordo · `t14` Radix empty-value (distinct com membro vazio silencioso que derruba o consumidor) ancorado em [`SafeSelectItem.tsx`](../../../resources/js/Components/ui/SafeSelectItem.tsx) + proibicoes §5 2026-06-29 → C3 discordo. **Código segue sintético** (não colei o repo — não-circular por construção); o que muda é a FONTE do rótulo: agora 3 famílias têm ground-truth num teste que roda no CI, independente da sessão-juiz.
- **Critérios extra (t15–t20):** pares bom/ruim pra **C4** (atomicidade: 2 escritas fora de transaction × 2 escritas que DECLARAM caller-wraps), **C5** (N+1: query-em-loop × `foreach` sobre relação eager-loaded), **C7** (tipos: retorno polimórfico `false|string|array` com docblock mentindo × `?int` tipado+documentado). Cada bad tem a armadilha "parece-ruim-mas-é-ok" (t16/t18/t20) — a régua da discriminação está em NÃO carimbar essas. Cobertura do discriminante subiu de C1/C2/C3/C6 → **C1..C7** (C8 é contagem, `n/a` em código isolado).
- **3 juízes frescos, todos CALIBRADO:** **12/12 famílias** · **κ = 1,0** ≥ 0,6 · 0 over-flag no controle · incerto certo · **0 falso-discordo** nas 6 armadilhas boas (t01/t03/t11/t16/t18/t20). **T1 = 100%** (0 flips/20 saliente; 32/32 células emitidas pelos 3). Sinal de cegueira: cada juiz usou **1 tool call** (só o `--pack`) e os 3 INDEPENDENTEMENTE adicionaram `C2:discordo` ao t13 (mutação em dinheiro) que o SELADO **não lista** — se tivessem lido o manifesto, teriam espelhado só o C1. Evidência: `calibracao-2026-07-21/judge-ext-b{1,2,3}.json`.
- Runner: `--selftest` segue mordendo com 20 twins (juiz-perfeito PASSA, juiz-carimbo FALHA: overflag=1, incertoOk=false). `minFamilias = ceil(12·0,8) = 10`.
- **Grade re-pontuada: validação-não-circular 7,5 → 8,5.** Fechou 3 dos gaps que a rodada 3 nomeou: **N 11→20**, **C4/C5/C7 cobertos**, **braço-incidente ancorado em teste de regressão REAL**. **Ainda falta pra 9-10 (honesto, não inflado):** κ vs gold HUMANO (hoje é κ vs rótulo objetivo — defensável pra defeito mecânico, mas a barra nomeou humano); diversidade de modelo (os 3 juízes são a mesma família Opus); e o κ=1 de novo pode significar que os twins, mesmo mais difíceis, ainda estão dentro do alcance de um juiz competente — um teste mais forte procuraria a FRONTEIRA de erro do juiz, não só confirmaria acerto.

**2026-07-21 (rodada 5 — desdobramento C7 → C7a–C7d + de-comment, sobre o set mesclado de 25 twins) — refutação adversarial absorvida; 3 juízes cegos passaram.**
- **Motivo:** o C7 monolítico flipou no T1 (`getProductDiscount`: 2 discordo × 1 concordo) por misturar 3 perguntas num único veredito (docblock? polimórfico? nullabilidade/falha?). Regra §5 aplicada ("critério que flipa ⇒ endurecer pra binário") → C7 desdobrado em 4 sub-critérios **ortogonais + binários**: C7a docblock/tipo · C7b polimórfico não-declarado · C7c nullabilidade tipada · C7d falha observável (§1 v1.1, 11 vereditos). Rótulos re-integrados no set da rodada 4: `t11` C3→C7c · `t19` C7→C7b · `t20` C7→C7c; **+5 twins novos** (`t21` C7a-puro · `t22` C7c-discordo · `t23` C7d · `t24` C3-concordo não-nullable · `t25` C7b-concordo union-declarado). **N 20→25.**
- **Loop de crítica (sessão fresca REFUTA):** um crítico adversarial achou 4 buracos reais, 2 blockers — **todos absorvidos**:
  - **[blocker] comentário vazando:** o `--pack` emitia os `//` dos twins verbatim, e vários **nomeavam o veredito** → κ media "transcrever comentário", não "discriminar". Fix: `pack()` roda `stripTells()` (remove `//` e `/* */`, **preserva** `/** */` de contrato); fatos de schema migraram pra docblock. **Consequência honesta: o κ das rodadas 2-4 fica parcialmente INFLADO** por prosa; ESTA (5) é a medição de-commentada e válida do desdobramento. **Residual honesto:** os docblocks `/** */` de narração do braço-incidente/extra (t12-t14/t16/t19/t20) ainda descrevem o defeito — o `stripTells` só tira `//`; de-narrar docblocks é trabalho futuro.
  - **[blocker] t12 impuro:** `value()` de 0-row devolvia null → tinha C7c além de C7a (2 juízes flagraram). Virou `t21`, cálculo `int` puro (sem DB, sem null) = C7a isolado.
  - **[C3↔C7c dupla-contagem]:** o texto de C3 ainda reivindicava "null" (dono é C7c). Fix: C3 vira **SENTINELA não-null** (`''`/`false`/`0`); **partição por caminho de ausência** (sentinela→C3, null→C7c, nunca os dois).
  - **[C7a vago · C7d subjetivo · C7b assimétrico]:** C7a ganhou cláusula (ii) pseudo-tipo genérico (`obj`/`mixed`) = discordo (fecha o defeito REAL do `getProductDiscount`); C7d restrito à supressão **mecânica** (catch vazio/`@`); C7b simétrico ao C7c (union **declarado** = concordo, +twin `t25`). Residual iterable catalogado na Fronteira.
- **Resultado (pack de-commentado · 25 twins · 15 famílias · rubric v1.1):** **3/3 juízes cegos CALIBRADO** — famílias **15/15** (min 12) · **κ = 1,0** (≥0,6) · 0 over-flag no controle (t07) · **incerto certo** no t08 (SEM o comentário) · **0 falso-discordo nos bons** (t11/t16/t18/t20/t24/t25). **T1 = 100%** (0 flips/25 salientes). Os 3 rotularam `t19` como **C7b + C7a** (mixed-return conflaciona os dois — por isso o `t21` isola C7a puro). **κ=1,0 SOBREVIVE ao de-comment** ⇒ a discriminação vem da ESTRUTURA + contrato, não de prosa — refuta o blocker na raiz. `--selftest` 5/5 · unit test 5/5.
- **Fronteira honesta (mantida):** calibra o **INSTRUMENTO** (juiz discrimina defeito mecânico não-circularmente), NÃO re-valida vereditos de função REAL. C7b/C7d são **completude proativa** (`n/a` no flip real), não flip-provados. Evidência reprodutível: `calibracao-2026-07-21/judge-v12-merged-{a,b,c}.json`. É mudança de **canon** → merge [W] = ratificação.

**2026-07-21 (rodada 6 — blind-por-label + de-narração + κ inter-família + set-fronteira · 8,5 → 9,0) — os DOIS leaks residuais achados+fechados; 4 famílias de modelo; a fronteira achou o erro.**

Fecha os 2 gaps que a rodada 4 nomeou (diversidade de modelo · fronteira de erro) e ataca a raiz da não-circularidade que as rodadas 2-5 tinham DEIXADO PASSAR. Tudo reprodutível do git: `calibracao-2026-07-21/judge-r6-{opus,sonnet,fable,haiku}-{main,frontier}.json` + runner.

- **2 leaks de circularidade residuais ACHADOS e fechados (o mais importante — é a própria dimensão):**
  1. **ID auto-documentado no cabeçalho do pack** — o `## t15-atomicidade-bad`, `## t02-unscoped-find`, `## t16-...-ok` NOMEAVA o veredito ANTES do juiz ler uma linha. Presente em TODAS as rodadas 2-5. Fix: `--blind` emite rótulos OPACOS `L01..` em ordem de **hash sha256(id)** (some o tell do id E a adjacência dos pares bom/ruim). O runner recomputa a ordem determinística pra pontuar (`translateBlind`); nenhum mapa gravado perto dos twins.
  2. **Docblock `/** */` narrando o veredito** — o README da rodada 5 já admitia como "trabalho futuro"; era material: t12/t13/t14 tinham docblocks tipo *"usuário do business A muta o lançamento do business B"* (= o C1 discordo, escrito). Fix: de-narração dos docblocks — mantido só o contrato genuíno (`@return` de tipo, `@covered-by`, `@transactional`, schema nullable), deletada a prosa que nomeia o defeito.
- **κ HONESTO pós-leaks:** sobre os **25 twins** o κ vs selado caiu de 1,0 (inflado) → **0,83** por família — e a queda foi **inteiramente** 2 twins (t08, t14). Sobre os **23 válidos** (t08/t14 aposentados, ver abaixo) o κ volta a **1,0** nas 4 famílias.
- **Gap #2 · diversidade de modelo (RESOLVIDO):** 4 famílias julgaram cegas — **Opus 4.8 · Sonnet 5 · Fable 5 · Haiku 4.5**. **κ INTER-FAMÍLIA = 1,0 nos 6 pares (22/22 acordo)** no salient mecânico. Refuta "concordou porque é o mesmo modelo": famílias distintas concordam perfeitamente no defeito mecânico. (Haiku, o menor, teve **1 falso-positivo no controle** t07 → único ❌ de calibração; Opus/Sonnet/Fable limpos.)
- **Gap #3 · fronteira de erro (RESOLVIDO):** set `frontier/` com 10 twins deliberadamente difíceis. A fronteira **achou** onde o juiz erra, não confirmou acerto:
  - **fr05 (golden-lull):** golden que cobre só o vetor inteiro, função parseia string ambígua do request → **Opus e Haiku ACEITARAM (miss C2); Fable e Sonnet pegaram.** Modo de erro REAL e perigoso (juiz carimba "tem golden = ok" sem checar O VETOR).
  - **fr10 (incerto-de-INTENÇÃO):** `?? 1.0` legítimo-ou-silencioso → Opus concordo · Sonnet discordo · Fable discordo · Haiku incerto = **1/4 correto**.
  - **fr08 (incerto-ESTRUTURAL):** eager-load desconhecido → **4/4 incerto correto.**
  - **Falso-positivo nas iscas: 0/20** (nenhuma família carimbou discordo numa isca) — propriedade de segurança forte.
  - κ inter-família na fronteira cai pra **~0,60** (75% acordo) — famílias concordam no fácil, **divergem no difícil** por construção.
- **Achado estrutural (o mais valioso p/ o braço humano):** `incerto` se PARTE em dois — **estrutural** (a incerteza está no código: eager-load desconhecido → fr08 4/4) é encodável e o juiz acerta; **de-intenção** (a ambiguidade está fora do código: "1.0 é default legítimo ou ausência?" → t08/fr10) **NÃO é encodável não-circularmente** num twin de mutação e o juiz RESOLVE em vez de deferir. Logo o incerto-de-intenção é do braço **gold HUMANO (#4626)** — a rodada 6 provou empiricamente POR QUE aquele braço é necessário, não opcional.
- **2 twins quebrados achados+aposentados (integridade da fixture):** `t08` (incerto-de-intenção, 4/4 erraram — migra pro braço humano) e `t14` (rótulo C3 ERRADO desde a rodada 4: o defeito é elemento-de-array/tipo = C7a, não retorno-sentinela = C3; 2 famílias acharam C7a independentemente). `retired: true` no manifesto — ficam na ORDEM cega (labels estáveis, r6 reprodutível) mas FORA das métricas.
- **Runner ganhou:** `--blind` (labels opacos hash-order) · `translateBlind` · `--kappa-inter` (κ juiz-A × juiz-B) · `--set frontier` · skip de `retired` · `incertoOk` conta N twins (não 1) e `null`=satisfeito. `--selftest` 5/5 · unit test 5/5.
- **Re-grade proposta: validação-não-circular 8,5 → 9,0.** Prova: os 2 leaks fechados (não-circularidade genuína), κ=1 inter-família em 4 famílias, fronteira com modo-de-erro real. **Falta pra 9,5-10 (honesto):** (1) **gap #1 κ vs gold HUMANO** segue bloqueado em [W] rotular a folha-cega (#4626) — é o ground-truth que casa com SWE-bench **Verified**, e a rodada 6 mostrou que o incerto-de-intenção SÓ ele resolve; (2) endereçar o modo fr05 (golden-lull) — rubrica ou nota; (3) reincidência-zero do FP de controle do Haiku + acúmulo de rodadas humanas. **NÃO é 10** — merge [W] = ratificação (R10).
- O braço humano já tem scorer próprio (`funcao-scorecard-humano.mjs`): exige 9/9 rótulos cegos de `[W]`, calcula **K/9 + Cohen κ** e só abre o selado no momento da pontuação. O mecanismo está pronto; os rótulos humanos continuam sendo a pendência empírica, sem fabricação automática.

**2026-07-21 (rodada 7 — RODADA HUMANA fechada · gap #1 ATIVADO · 9,0 → 9,2).** [W] rotulou às cegas 9 funções REAIS de risco (C1/C2/C3/C6/C7 — `num_uf`, `format_date`, `getVariationGroupPrice`, `calculateInvoiceTotal`, `getProductDiscount`, `KbAutoClassifier`, `FsmAuthorizationFlag`, `generateProductSku`), todos `(canon)`. Scorer `funcao-scorecard-humano.mjs --score` (abre o selado só na pontuação).

- **K/9 = 7/9 (77,8%) · Cohen κ = 0,591** (moderate, no limiar de "substantial"). **1º denominador HUMANO** do juiz — a entry `tipo:juiz` (rotulador `[W]`, `cego:true`) tira o chip C10 do zero (`ledger-check --juiz-report` = 1 rodada). É **medição, não portão** (o ledger agrega rodadas).
- **2 divergências, tipadas:** **#5** `calculateInvoiceTotal` C3 — **miss-de-lookup** (juiz `incerto` × [W] `discordo (canon)`: o juiz deferiu por não achar o tópico canônico que registra o `false|array` como problemático). **#8** `FsmAuthorizationFlag` C7 — **miss-de-direção/over-reach** (juiz `discordo` × [W] `concordo (canon)`: o juiz enfiou a LETRA do claim "reset no Octane" — lifecycle/infra — num veredito de tipo/falha; [W] lê o `bool` fail-secure como honesto, Octane = concern separado a verificar).
- **Achado (confiança calibrada 2/2):** as 2 divergências caíram nos 2 itens que o juiz auto-marcou como os menos firmes no gabarito selado (#5 "o menos firme"; #8 "[W] pode ler como concordo com ressalva"). A incerteza do juiz **previu** onde ele ia divergir — 7/9 cru, mas calibração de confiança perfeita.
- **Evidência reprodutível:** `memory/reguas/2026-07-21-calibracao-funcao-scorecard-humano/rotulos-W.json` + `funcao-scorecard-humano.mjs`. Session: `memory/sessions/2026-07-21-funcao-scorecard-rodada-humana-gap1.md`. Complementa (NÃO estende) a fixture de mutação — dois ground-truths.
- **Re-grade: validação-não-circular 9,0 → 9,2.** Os 3 gaps nomeados estão agora endereçados: κ vs humano (esta), diversidade de modelo (rodada 6), fronteira (rodada 6). **Falta pra 9,5-10:** acumular rodadas humanas (κ moderate → substantial) + endereçar as 2 divergências (lookup do tópico no #5; escopar o C7 pra não engolir claim de infra no #8). Merge [W] = ratificação (R10).

## §6 O que um `discordo` NÃO autoriza

- ❌ NÃO edita código. ❌ NÃO cria task automática. ❌ NÃO é "achado" (é parecer).
- ✅ Vira **candidato a US** via aprovação humana. A correção, se aprovada, segue: varredura contada de TODOS os consumidores + âncora de contrato + teste vermelho (§5 2026-07-15); + se valor/estoque, REGRA MESTRE (dupla confirmação + impacto antes→depois).
