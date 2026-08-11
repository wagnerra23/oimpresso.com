---
proposal_id: templates-8-artefatos-anexo
status: accepted
decided_by: wagner
decided_at: "2026-08-11"
created: 2026-08-04
parent_proposal: templates-dos-8-artefatos-e-onde-mora-o-contador
type: anexo
---

> **Anexo** de [templates-dos-8-artefatos-e-onde-mora-o-contador](2026-08-04-templates-dos-8-artefatos-e-onde-mora-o-contador.md).
> Contém os 8 templates completos + as 7 decisões transversais com a medição de cada uma.
> Gerado por workflow adversarial (8 desenhos + 8 críticos + síntese) em 2026-08-04.

## Os 8 templates

> **Escopo e recibo desta síntese.** Worktree `D:/oimpresso.com/.claude/worktrees/forja-req`, branch `claude/jana-fronteira` — **5 commits atrás / 3 à frente de `origin/main`**. Todo número abaixo vale para ESTA árvore. Não rodei git ops nem teste PHP. Re-medi com `find`/`node` os fatos que decidem o desenho (recibos no §Anexo A); o que herdei das 8 críticas sem re-medir está marcado **[herdado, não re-medido]**; o que ninguém mediu está marcado **[não medido]**.
>
> **Veredito global: 0 dos 8 templates recebe NOTA. 4 recebem CONTADORES; 4 não recebem nada.** Nenhuma catraca nova é armada por esta proposta.

---

### §0 — Decisões transversais (valem para os 8)

#### D-T1 · Placeholder em frontmatter **sempre entre aspas** — medido, e é a violação mais cara do lote

`{{X}}` sem aspas **não é string em YAML** — é *flow mapping*. Medido (`node -e` com `js-yaml`, 2026-08-04):

| entrada | resultado |
|---|---|
| `module: {{NomeModulo}}` | **OK, `typeof === "object"`** → `{"module":{"[object Object]":null}}` |
| `trust_required: {{L0\|L1}}` | **OK, `typeof === "object"`** |
| `permission_prefix: {{modulo}}.*` | **THROW** `YAMLException: bad indentation of a mapping entry (1:30)` |
| `module: "{{NomeModulo}}"` | OK, `typeof === "string"` |
| `module: <NomeModulo>` | OK, `typeof === "string"` |

Três consequências que atingem 4 dos 8 desenhos:

1. **O recibo AJV do desenho BRIEFING está errado** e a crítica V8 está certa: `module` **recusa** — e recusa por `must be string`, não por falta de `pattern`. A recusa vem do **tipo YAML**, não da escolha de campos com enum. Logo a tese "pus os placeholders nos campos que o schema já recusa" é um diagnóstico errado de um efeito real.
2. **O frontmatter do desenho SCOPE quebra um consumidor em fail-open** (V7): `permission_prefix: {{modulo}}.*` lança, e `DriftAlertService` faz `return []` no catch — a detecção de drift desliga em silêncio.
3. **O desenho charter reprova no gate REQUIRED** `Charter (resources/js/Pages/**/*.charter.md)` (confirmei que está na lista de required, §D-T7): `page_id: {{x}}` nem string é.

**Regra unificada:** todo placeholder de frontmatter nasce `"{{...}}"` **entre aspas**. A detecção de placeholder é por **conteúdo de string** (`/\{\{[^}\n]+\}\}/`), nunca por tipo YAML. O legado `<...>` (BRIEFING-TEMPLATE atual) migra para `{{...}}` citado — mesma técnica, um só vocabulário.

#### D-T2 · Vocabulário unificado — o nome do **dono** vence; a **técnica** tem nome único

Os 8 desenhos deram 6 nomes diferentes para a mesma coisa. Unificação:

| Conceito | Nomes que apareceram | Vence | Por quê |
|---|---|---|---|
| **Técnica**: alvo declarado × existência real | "referência que resolve" (1,2,4,5,7,8), "ancora_que_resolve" (6), "portas_ausentes" (8) | **referência que resolve** (técnica) | é a única técnica presente nos 8; o *nome da métrica* continua sendo o do dono (`charter_refs_broken`, `anchored_dead`, `orfaos`…) |
| **Técnica**: `{{}}` sobrevivente | "placeholder não curado" (todos), "S1" (7) | **`placeholder-nao-curado`** | é o código literal já emitido pelo `feature-lint`; nome existente vence nome novo |
| **Técnica**: contar arestas entre 2 arquivos | "cardinalidade" (2,4,6,7) | **cardinalidade de relação** | precisa do adjetivo: "cardinalidade de presença" (≥1 item, seção não-vazia) é **presence-gate PROIBIDO**, e 3 desenhos confundiram os dois |
| **Técnica**: declarado × fonte que o autor não escreve | "frescor contra fonte externa" (2,6,7,8) | **frescor contra fonte externa**, com a trava: **as DUAS pontas lidas por máquina** | G-6 (data-git do `.tsx`) e G-7 (verdict do JUnit) qualificam; `last_validated`/`verificado_em`/`N/A` declarado **não** |
| **Saída** | "grade", "nota", "painel", "scorecard" | **contador** + **bandeira** | "grade/nota" fica reservado aos 3 donos que já agregam: scorecard de tela (16 dims), `module:grade` (D1–D9), `sdd-scorecard` (10 métricas) |
| **Bandeira** | 🟢/🟡/🔴 (1,5) | 🟢/🟡/🔴 **+ `n/a`** | vocabulário que `anchor-lint:911` e `doneness-lint:263` já emitem, **mais** o `n/a` — ver D-T3 |

#### D-T3 · A bandeira `n/a` é obrigatória quando o denominador é 0 — três desenhos tinham falso-verde por população vazia

Padrão medido nos 8: um contador que dá 0 porque **não há sujeito** foi apresentado como saúde.

| caso | "0" reportado | denominador real | medido por |
|---|---|---|---|
| SPEC com ids fora de `US-[A-Z]{2,8}-\d{3,4}` | G1..G5 = 0 → 🟢 | 9 dos 59 SPEC são invisíveis ao `anchor-lint` | V1 [herdado] |
| `tela_sem_casos` no requirements | "0 em 3/3" | **0 de 0** — nenhuma feature cita `.tsx` | V2 [herdado] |
| `charter_pointer_missing` (D-R4) | 0 | 156 charters têm valor com espaço → **pulados em silêncio** pelo regex | V5 [herdado]; confirmei o regex (§Anexo A.4) |

**Regra:** todo contador publica o **denominador ao lado**, e a bandeira é `n/a` — nunca 🟢 — quando o denominador é 0. Sem isso, "renomear ids" e "parar de citar" são caminhos 🔴→🟢.

#### D-T4 · Onde a contagem mora: **fora do arquivo**, no dono. O único candidato a bloco gerado caiu por não-hermeticidade

A regra 6 permite bloco gerado com marcadores + `--check`. **Nenhum dos 8 qualifica**, e o motivo é o mesmo dos 3 precedentes citados (`SUPERFICIE.md`, `plans-index --check`, `adr-index-generate --check`): eles são **herméticos** — fonte e gerado no MESMO PR.

O candidato mais forte era o bloco dentro do `casos.md`. Ele cai por medição de V6, e o argumento é decisivo: 5 das 8 dimensões leem `scripts/casos-test-results.json` (escrito por **auto-PR**, ~1/dia) e o corpus inteiro de testes (25% dos commits em 60d). Bloco commitado + fonte fora do PR = **o deadlock que a ADR 0261 proíbe** e que o próprio `casos-coverage-guard` declara evitar. Se advisory, é vermelho permanente; se required, trava o main quando o auto-PR do manifesto landa.

**Regra:** contador mora no `--json`/relatório do dono e, quando precisar de casa versionada, num **arquivo já gerado que o dono já mantém** (`_STATUS-GENERATED.md`, `catalog.json`, baseline em `governance/`). Nunca dentro do artefato curado.

#### D-T5 · Catraca: dois idiomas no repo, e qual vence onde

| idioma | forma | quando usar | trava anti-afrouxamento |
|---|---|---|---|
| **lista de violações nomeadas** | `casos-coverage-baseline.json`, `deadlink-baseline.json` | quando a unidade é nomeável (permite grandfather item-a-item) | `baseline-tamper-guard::detectViolationList` |
| **ceiling numérico de topo** | `charter-refs-baseline.json` (`{"ceiling": 0, "metric": ...}`) | só onde o dono **já** usa | `detectCountRatchet` — e V5 mediu que ele faz `if (typeof n !== 'number') continue`, logo **trocar `ceiling` por `ceilings: {}` aninhado desliga a vigilância em silêncio** |

**Regra:** não mudar o idioma de um baseline existente. Se o charter precisar de 4 números, são **4 chaves numéricas de topo**, nunca um objeto aninhado.

#### D-T6 · Tocar template acorda gate — os 4 templates já têm dívida registrada no `deadlink-gate` (required)

Medido em `governance/deadlink-baseline.json`:

```
memory/requisitos/_DesignSystem/BRIEFING-TEMPLATE.md : 1
memory/requisitos/_TEMPLATE_FEATURE/requirements.md  : 3
memory/requisitos/_TEMPLATE_SPEC.md                  : 1
memory/requisitos/_TEMPLATE_capterra_ficha.md        : 1
```

O `deadlink-gate` é **required** (contexto #34). O PR que landar estes templates tem que rodar `node scripts/governance/deadlink-gate.mjs --scan` **antes** e confirmar que nenhuma dessas contagens sobe. Aplicação direta da emenda §5 2026-07-27 (a classe tem 3 eixos): enumerar **todos** os globs que o arquivo casa e medir **cada** um.

#### D-T7 · Reconciliação obrigatória: V1 e V8 se contradizem sobre "quantos contexts são required" — os dois estão certos e parciais

Medido (`governance/required-checks-baseline.json`, `enforcement_level: "everyone"`, `branch: main`):

- `classic_protection.contexts` = **34** (o que V1 leu)
- `rulesets.contexts` = **1** — `Governance Gate (índice + memory-health + meta-teste)` (o que V8 leu)

São **duas camadas**, 35 contextos no total. Citar uma só subdeclara. É a lápide §5 2026-07-17 (*"varri o arquivo ≠ varri o sistema"*) acontecendo **dentro de um único JSON**. Fatos que dependem disso e ficam fixados:

- `SPEC (memory/requisitos/*/SPEC.md)` **é** required (#22) · `Schema SPEC.md (frontmatter + seções)` **não é** — V1 certo.
- `Charter (...*.charter.md)` #7 · `Casos-coverage · ratchet` #6 · `Modulo backend com BRIEFING (cobertura)` #13 · `deadlink-gate` #34 · `anchor-lint ADR 0273` #28 · `anchor entry/covers gate` #27 · `doneness-lint ADR 0302` #30 — todos required.
- Família **BRIEFING do memory-schema-gate**: ausente da lista → **grace, warn-only** — V8 certo.

**Regra que fica:** nenhum template, script ou comentário afirma o próprio enforcement. Quem precisar falar disso **aponta para `governance/required-checks-baseline.json` e diz qual chave leu** (§5 2026-07-16).

---

### 1 · `memory/requisitos/<Mod>/SPEC.md`

**Template canônico:** `memory/requisitos/_TEMPLATE_SPEC.md` (substitui o atual).

#### Frontmatter

```yaml
---
# REQUIRED por máquina: AJV spec.schema.json → module · version · last_updated
#   (job REQUIRED "SPEC (memory/requisitos/*/SPEC.md)" — quem responde "é required?"
#    é governance/required-checks-baseline.json, chave classic_protection.contexts)
# + validate_spec (bash): owner|owners, module PascalCase, version ^v?N.N(.N)?$
module: "{{PascalCase}}"
version: "0.1.0"
last_updated: "{{YYYY-MM-DD}}"
owners: ["{{W}}"]            # enum W|F|M|L|E
status: rascunho             # rascunho|ativo|arquivado|historical
project: COPI                # ⚠️ NASCE CURADO, não é placeholder. Sem ele o
                             # TaskParserService::resolveCycleId/resolveEpicId devolve NULL
                             # e a US nunca entra no roadmap (US-INFRA-045). Trocar por
                             # sigla própria SÓ se o projeto existir no MCP.
related_adrs: []             # slugs ^[0-9]{4}-[a-z0-9-]+$
anchor_format: "v1"
---
```

**Removidos do desenho original, com motivo medido:**

- ⛔ `us_count` / `us_list` — **saem**. Zero consumidores fora do schema; adoção 1/59 e 2/59; e o único `us_list` real (Forja) já está drifado (8 declarados × 18 headings). É o oráculo errado (§5 2026-07-17) escrito no mesmo template cuja blockquote proíbe restatear número. O dono é `anchor-lint --json → .summary.us_total`.

#### Seções

`# Especificação funcional — {{Modulo}}` · blockquote de convenção (**aponta pro dono, não restateia número**) · `## 1. Glossário` · `## 2. Personas` · **`## 3. US ativas`** (obrigatória — `validate_spec` aceita este título) · `## 4. Backlog` · `## 5. Tabelas DB` · `## 6. Integrações` · `## 7. Histórico` · `## 8. Referências` · `## 9. Como este SPEC é medido` (ponteiro + comandos, nunca cópia).

**Forma de cada US — a linha que quase virou gate mudo:**

```markdown
### US-{{MOD}}-001 · {{título curto}}

> owner: {{W}} · status: todo · type: story · priority: p2
```

> ⚠️ **O `>` tem que ser o PRIMEIRO caractere da linha.** Medido: `doneness-lint.mjs:65` é `const STATUS_RE = /^>\s*.*\bstatus:\s*(...)/` — ancorado em `^>`. Indentar (`  > owner: ...`, como o desenho original escreveu) faz a US **sumir inteira** do `doneness-lint` (gate #30, required) e do contador G5, sem erro nenhum. Sintoma já visível: `us_with_status = 516` de `us_total = 944` [herdado de V1, não re-medido] — 45% das US são invisíveis ao gate.

Campos por US, na grafia **exata** que os gates reconhecem: `**Rota:**` · `**Controller:**` · `**Permissão Spatie:**` · **`**DoD:**`** (um dos 5 marcadores do `DOD_RE`; qualquer outra palavra = `req_sem_aceite` no required) · **`**Implementado em:** _pendente_`** (gramática ADR 0273 — nunca `_[TODO]_`/`(a criar)`) · **`**Testado em:** _pendente_`** (o teste citado precisa declarar `// @covers-us US-...`).

#### Contadores — **RELATO, sem catraca nova**

| id | nome (do dono) | direção | comando |
|---|---|---|---|
| G1 | `anchored_dead + anchored_zombie + placeholder + dead_tests` | ↓ | `anchor-lint <SPEC.md> --json` |
| G2 | `conflito_done_sem_ancora + dod_conflicts` | ↓ | `doneness-lint <SPEC.md> --json` |
| G3 | `req_sem_aceite + req_sem_covering_test` | ↓ | `anchor-lint … --json` |
| G4 | `req_sem_lane` (verde estruturalmente inalcançável) | ↓ | `anchor-lint … --json` |
| G5 | `usSemContrato` (US entregue que nenhum `casos.md` cita como âncora) | ↓ | `requisitos-status.mjs <Mod>` |
| — | **denominador**: `us_total`, `by_state` completo (incl. `sem_campo`), `us_com_status`, `ids_fora_do_padrao` | publicar | idem |

**Comando com o caminho de acesso CERTO** (o do desenho não resolve — `modules` é **array**, não objeto):

```bash
node scripts/governance/anchor-lint.mjs memory/requisitos/<Mod>/SPEC.md --json \
  | jq '.modules[] | select(.module=="<Mod>") | {counts, dead_tests, req_sem_aceite, req_sem_covering_test, req_sem_lane}'
```

**Três correções que mudam o desenho:**

1. ⛔ **A catraca `--check-grade-shrink` SAI.** G1/G2/G3/G4 são exatamente o que 3 jobs **required** já julgam (#28, #27, #30) — e julgam **diff-aware com baseline**, mordendo só mentira nova, por desenho (ADR 0275/0303/0314). A catraca proposta era módulo-inteiro sem grandfather: uma 2ª porta para o vermelho no ponto que o canon escolheu grandfatherar. É §5 2026-07-09 (*"catraca redundante com régua consolidada"*). Se algum eixo estiver frouxo, aperta-se o baseline do **dono** por flip [W] com mordida provada.
2. ⛔ **`conflito_aberto_com_ancora` sai de G2.** `doneness-lint:120-128` classifica `status: doing` + âncora viva como conflito, e os 10 conflitos ativos do repo são 100% desse tipo [herdado de V1]. Sob catraca só-desce, começar a implementar (atualizar a âncora com a US ainda `doing`) **reprovaria** — empurrando para flipar `done` cedo ou segurar a âncora em `_pendente_`, os dois anti-padrões que a ADR 0273/0302 existe para matar.
3. ⚠️ **G6 `anchor_stale` fica FORA e explicitamente [não medido].** O valor é mecânico (`git log`) mas o conserto é reescrever o sha — ato auto-declarado (família `last_validated`, §5 2026-07-01/09). Entra como número observado, com `unknown` explícito em checkout raso, nunca como dívida que "fecha" escrevendo.

**Onde mora:** seção `## Grade do SPEC` do `memory/requisitos/<Mod>/_STATUS-GENERATED.md` — que já é `authority: generated`, já tem `--write`/`--check`. **Não** dentro do `SPEC.md`: `sdd-scorecard.mjs:295` exclui do `gitNewestModuleDocDate` apenas `BRIEFING.md` e `SUPERFICIE.md`, então regenerar bloco dentro do SPEC empurraria `distiller_freshness` (métrica ARMADA, catraca **required** GT-G3) sem conhecimento novo — é o modo de falha do PR #4156 reproduzido por robô. **Companion obrigatório** [herdado de V1]: estender a linha 295 para excluir também `_STATUS-GENERATED.md` antes de tornar a regeneração rotineira.

---

### 2 · `memory/requisitos/<Mod>/features/<slug>/requirements.md`

#### Frontmatter — o envelope é **obrigatório** (omiti-lo quebra o gerador)

```markdown
---
id: requisitos-template-feature-requirements
---

<!--
  TEMPLATE CANÔNICO — gerado só por `npm run feature:init`; nunca copie à mão.
  Este bloco e o frontmatter acima são o ENVELOPE: stripTemplateEnvelope() os remove
  na geração. Removê-los daqui faz o strip comer o frontmatter VIVO.
-->
---
feature: "{{slug-kebab}}"
module: "{{PascalCase — igual à pasta memory/requisitos/<Mod>/}}"
us: ["US-{{MOD}}-{{NNN}}"]
parent_plan: "{{parent-plan}}"
created: "{{YYYY-MM-DD}}"
---
```

**Medido no código (§Anexo A.3):** `stripTemplateEnvelope` (feature-lint.mjs:176-183) remove o **primeiro** bloco `---...---` **e** o comentário HTML seguinte; `renderFeatureTemplate:208` faz `out.replace(/^---\r?\n/, ...)` para carimbar o `id:`. Sem envelope, o strip come o frontmatter vivo, o `id:` nunca é carimbado, `parseFrontmatter` devolve `{us:[]}` e **toda feature gerada dispara `sem-us`** — reprovando os selftests `feature-lint.test.mjs:98-99`, que rodam em CI.

**Correção do bullet de Clarifications:** `renderFeatureTemplate:214` faz `replaceAll('{{YYYY-MM-DD}}', values.date)` no arquivo **inteiro** — a data da clarificação nasce carimbada com a data de **geração**, plausível-porém-falsa, e o `placeholder-nao-curado` nunca a cobra. Trocar o token do bullet por `{{DATA-DA-CLARIFICACAO}}` (não substituído).

**Não entram:** `tier:`/`toca_valor:` (auto-declarados, e a seção condicionada viraria presence-gate) · `last_validated`/`reviewed_at` (o frescor da feature é a âncora `verificado@<sha7>` da US no SPEC).

**Correção de fato, para o comentário não mentir:** dos 5 campos, `parseFrontmatter` lê **3** (`us`, `feature`, `module`); `parent_plan` e `created` são registro histórico sem consumidor no lint. Não removê-los (forward-only), mas não os vender como "campos que o parser sabe ler".

#### Seções

`# Requirements` + linha `> **US-mãe:** … · **Sinal (ADR 0105):** …` · `## User story` · `## Clarifications` (com `_nenhuma — pedido não-ambíguo_` como cura legítima) · **`## Exemplo dourado (CONDICIONAL)`** · `## Acceptance criteria (EARS)` · `## Fora de escopo` · `## Referências`.

> **`## Exemplo dourado` ganha escape literal** — `_não se aplica — esta feature não toca valor nem estoque_`. Sem ele restam duas saídas ruins: nascer com `{{}}` → ERRO em toda feature que não toca valor/estoque; ou nascer sem placeholder → prosa inerte. O escape é o mesmo padrão do `_nenhuma_` das Clarifications.

#### Contadores — **um só, rotulado honestamente, sem baseline**

| contador | o que é | o que **não** é |
|---|---|---|
| `ac_sem_task` | arestas AC-N ↔ `covers:` do `T-NN` irmão que não fecham | **cobertura DECLARADA, não provada** |

**Correções obrigatórias:**

1. ⛔ **`tela_sem_casos` SAI.** O fato já é o `trio:missing-casos:` do `casos-coverage-baseline.json`, sob o required #6, já anti-grandfatherado. Fica como **nudge informativo** do lint (aponta pro dono), fora de `violations` e fora de qualquer baseline.
2. ⛔ **`task_sem_covers` funde-se em `ac_sem_task`.** São a mesma aresta vista dos dois lados — contar como 2 dimensões é double-count. E `task-sem-covers` isolado é presence-gate (o campo existe/não está vazio).
3. ⚠️ **O rótulo é obrigatório**: os dois lados são prosa do MESMO autor no MESMO PR — acrescentar `AC-3` na string `covers:` zera o contador sem escrever código. Isso é o critério com que o próprio desenho rejeitou `clarify_pendente`. O contraste honesto: o G-2 do casos-gate cruza com o **corpus de teste** (artefato independente que precisa rodar); aqui não.
4. ⛔ **Sem baseline, sem catraca.** Full-tree hoje: **3 features, 0 erros, 0 avisos**. Catraca sobre superfície com 0 mordidas é `foundation-ratchet` (§5 2026-07-01).
5. **Pré-requisito de honestidade:** `governance-script-tests.yml:441` roda `feature-lint.mjs` **sem `--check`** — os 14 códigos de ERRO **hoje não bloqueiam merge nenhum**. Qualquer conversa sobre catraca aqui começa por wirar o `--check`, senão é gate mudo.

**Recibo corrigido (o comando publicado no desenho não reproduz):** `grep -rnE '\bAC-[0-9]' tests/ Modules/*/Tests/` = **0**. Sem `\b` o mesmo grep devolve 29 — todos artefato léxico (`ACAC-`, `HMAC-`, `BCAC-`). A conclusão (`ac_provado_por_teste` não é derivável) é verdadeira; o comando é que estava errado. [herdado de V2, não re-medido]

---

### 3 · `memory/requisitos/<Mod>/features/<slug>/plan.md` — **SEM contadores**

#### Frontmatter

Idêntico ao vigente (envelope + `feature:` + `module:`), com os placeholders **citados** (D-T1). Ausências deliberadas mantidas: `us:` (vive no SPEC e no requirements irmão — 3º registro seria dual-source), `parent_plan:` (vive no corpo, no bloco `## Status vivo`, que é onde `plan-health.mjs:153` lê), `status:` (idem).

#### Seções

`## Status vivo` (contrato parseável, com os campos que `plans-index` e `plan-health` leem) · `## Decisões técnicas` (ids `D1..DN`, **sem hífen** — é a gramática do corpus) · `## Plug-points` — **aceitando a variante com coluna de ação e marcador `**novo**`**, que é o único delta real deste template · `## Design (a/b/c, opcionais)` · `## Riscos Tier-0` · `## Alternativas descartadas`.

> **Declaração de honestidade:** fora do `**novo**` nos Plug-points, este template **é** o que já está commitado. Frontmatter, as 6 seções, a ordem e os 26 placeholders são idênticos ao arquivo em disco. Registrar isso é aplicar §5 2026-07-27 (*creditar o que já shipou*) na direção inversa — não creditar-se por desenho preexistente.

#### Por que **não** tem contadores

| candidata | veredito | recibo |
|---|---|---|
| `decisao-sem-task` (D-P1) | **não derivável** | exige convenção NOVA (`decide: D-N` no blockquote do tasks). Hoje acenderia 17/18. Armar assim = vermelho permanente |
| `risco-tier0-contradito` (D-P2) | **auto-declarada pelo gatilho** | o alarme dispara na string `N/A`, que o autor escolhe escrever — trocar por "sem impacto" o apaga. Pune quem obedece o template e premia prosa vaga |
| `plano-zumbi` (D-P3) | **100% FP no único caso que dispara** | acende 1 de 3 hoje (Financeiro: US-FIN-003 com `verificado@6f8f1cc` × plano `proposto`) e esse 1 é FP — a feature é um **delta** sobre a US-mãe. Pior, é FP **estrutural**: `_parcial_` carrega `verificado@` por gramática (`anchor-lint:186`), e detalhar US parcial é o caso canônico do trio. [herdado de V3] |
| `plug-point resolve` | **100% FP medido** | 30 paths → 24 resolvem, 3 `**novo**`, 3 não resolvem — os 3 são FP. O plan.md **fala do futuro por construção** |
| qualquer coisa do `## Status vivo` | **dois donos já existem** | `plans-index.mjs` (`--check`) + `plan-health.mjs` (enum/frescor/órfão, com selftest BITE+RELEASE) |

Três candidatas não-deriváveis + uma auto-declarada + uma com FP estrutural = **0 dimensões prontas**. Somar mais um juiz aqui é §5 2026-07-09.

---

### 4 · `memory/requisitos/<Mod>/features/<slug>/tasks.md` — **SEM contadores**

#### Frontmatter

```markdown
---
id: requisitos-template-feature-tasks
---

<!--
  TEMPLATE CANÔNICO — envelope removido por stripTemplateEnvelope() na geração.
  NÃO ENTRAM neste frontmatter (decisões, não esquecimentos):
    status:  — ADR 0302: done-ness se LÊ da âncora da US no SPEC, nunca se digita aqui.
    us:      — já vive no requirements.md e no blockquote de cada T-NN.
    nota/%:  — campo de nota escrito pelo autor é a família last_validated (morta 07-01/07-09).
-->
---
feature: "{{slug-kebab}}"
module: "{{PascalCase}}"
---
```

**Duas correções fatais sobre o desenho:**

1. `id:` **não** vai no frontmatter vivo — é carimbado por `renderFeatureTemplate:208`. Escrevê-lo à mão produz `id:` **duplicado** e mantém `{{modulo-kebab}}` vivo (medido: esse token **não está** na lista de substituições), disparando `placeholder-nao-curado` no arquivo recém-gerado, no campo que o comentário manda não curar.
2. O bloco "NÃO ENTRAM" vai em **`<!-- -->`**, não em linhas `#`. Fora do bloco `---`, cada `#` vira **H1** e o arquivo gerado passa a começar por um título espúrio antes do H1 real.

#### Seções

Blockquote de contrato (estado vivo no MCP, ADR 0070) · `### T-NN · {{título}}` · blockquote de metadados (`> blocked_by: … · covers: … · us: … · estimate: …`) como **primeira** linha · corpo 1-3 linhas · `**DoD:**` · última task obrigatória `Fechar o loop — âncora da US + smoke real`.

#### Por que **não** tem contadores

A dimensão proposta (`ref-de-artefato-que-resolve`) cai por cinco razões independentes, todas medidas por V4 [herdado]:

- **Gameável por deleção, e a defesa aritmética está errada**: ref morta está só no denominador — 9/10 = 90% vira 9/9 = 100% apagando-a. O incentivo ao fechar a US é **apagar a rastreabilidade**.
- **A guarda não separa feature fechada de feature em andamento**: `anchor-lint` devolve o estado da **US-mãe**; por contrato do trio a feature detalha US preexistente. Caso real: US-FIN-003 `anchored_ok` em 27/07 × feature criada em 03/08 com tudo por fazer.
- **Superfície ~10%**: 77 code-spans, só 8 path-shaped; 90% são **símbolo** (`BaixaService`, `StoreBaixaRequest`) — e é lá que moram as duas promessas não cumpridas.
- **Denominador n=1** depois de excluir boilerplate carimbado pelo template e diretórios que existem trivialmente.
- **Duplica o dono** `deadlink-gate` (required, com baseline per-arquivo, tombstone, rename-map e `existsCaseSensitive`) — e `existsSync` no Windows é **case-insensitive**, reintroduzindo a regressão `kb/`×`KB`.

Se o tema voltar, a forma canônica é **um extrator de code-span dentro do `deadlink-gate`**, não um segundo medidor.

---

### 5 · `resources/js/Pages/<Mod>/<Tela>.charter.md`

**Template vivo:** `charterTemplate()` em `scripts/governance/criar-tela.mjs:206`. O `CHARTER-TEMPLATE.md` de `_DesignSystem/` **não é lido pelo gerador** — fica como prosa explicativa; não se abre um terceiro.

#### Frontmatter — regra nova: **o gerador preenche o que sabe e OMITE o resto**

```yaml
---
# REQUIRED pelo schema (3): page · component · status. Gate REQUIRED #7.
page: /{{rota-real-registrada-em-routes}}
component: resources/js/Pages/{{Mod}}/{{Tela}}.tsx
status: draft
page_id: {{mod-tela-em-kebab}}
parent_module: {{Mod}}
charter_version: 1        # INTEIRO NU. Medido: `1.0` PASSA (js-yaml → Number 1);
                          # quem reprova é a STRING "1.0" (must be integer) e decimal ≠ inteiro.
last_validated: "{{AAAA-MM-DD}}"
related_prototype: nao-aplicavel   # sentinela NÃO-path (ver abaixo)
---
```

**Cinco correções que decidem se o template é utilizável:**

1. ⛔ **Campo tipado que o gerador não sabe preencher fica AUSENTE, nunca com placeholder.** Medido por V5 com o AJV canônico: o frontmatter do desenho reprova em **7 campos** (`owner`, `tier`, `last_validated`, `related_us[0]`, `related_adrs[0]`, `page_id`, `parent_module`) no gate **required** #7 — que hoje mede 0 violações, e foi esse custo-zero que justificou a promoção (ADR 0341). **Ausência é honesta; placeholder é violação.**
2. ⛔ **`states:` NÃO é emitido.** `visreg-states-lint` regra 4: charter que declara `states:` sem entrada no manifesto quebra um step **enforcing** dentro do job required `visual-regression`. Só 6 de 209 charters declaram — e são exatamente as telas do manifesto.
3. ⚠️ **Sentinela `n/a` é proibida.** Reproduzi o mecanismo (§Anexo A.4): `charter-refs.mjs:78` é `isRepoRelative = (p) => !/^https?:\/\//.test(p) && /^[A-Za-z0-9._-]+\//.test(p)` — `n/a` casa `n/` → **vira BROKEN**. A sentinela canônica passa a ser **`nao-aplicavel`** (não-path por construção).
4. ✅ **Comentário do `charter_version` corrigido** — a explicação do desenho ("`1.0` é float e derruba") está errada e ensina o diagnóstico errado.
5. 🔵 **`guards:` (array `{regra, teste}`) é decisão [W]** — campo novo, ver §Separação.

#### Seções

`# Page Charter` · blockquote de **precedência** literal (*teste verde > casos > charter > SPEC*) · `## Mission` (medido 182/209 [herdado]) · `## Goals` · `## Non-Goals` (bullets `❌` numerados `NG-n`) · `## UX Targets` · `## UX Anti-patterns` · `## Automation Hooks` · `## Automation Anti-hooks` (`⛔`, `AH-n`) · `## Sub-components` · `## Pendências antes de status: live` · `## Refs` · `## Histórico` (append-only, com `⚠️ REVOGADO` para afirmação que o CI provou falsa).

> ⛔ **`## Guards` como "tabela gerada" SAI** enquanto não houver gerador nomeado + marcadores + `--check`. Sem isso é duplicata do frontmatter mantida à mão, e a coluna `resolve?` vira nota auto-declarada (viola a regra 6). O `guards:` do frontmatter já é a fonte; o detalhe sai no `--list` do gate.

#### Contadores

| id | contador | estado | recibo desta sessão |
|---|---|---|---|
| D-R1 | `charter_refs_broken` | **já existe, mantém** (ceiling **0**, chave numérica de topo) | `governance/charter-refs-baseline.json` lido: `{"ceiling": 0, "metric": "charter_refs_broken", ...}` |
| D-R3 | `charter_us_orfa` | **entra como RELATO**, valor a fixar no dia da implementação | **medi 2**, não 15 — ver abaixo |
| D-R2 | `charter_guard_fantasma` | **RELATO, fora de catraca** | gameável por deleção |
| D-R4 | `charter_pointer_missing` | **[não medido]** — bloqueado | semântica de extração indefinida |

**D-R3 — divergência que preciso declarar.** Medi com dois critérios independentes de "US existe" (menção em qualquer lugar do SPEC × heading `##..####`): **166 refs, 2 órfãs** (`Financeiro/Unificado/Index → US-FIN-050`, `Home/Index → US-DASH-006`). V5 reportou **15 de 176**. **Não reproduzi o 15.** Rodei o dono para calibrar: `node scripts/governance/charter-us-lint.mjs` → *"209 charters · 114 com related_us · 95 sem (cobertura 54.5%)"* — e ele **não valida existência**, só shape, confirmando que a dimensão é nova. Consequência prática: D-R3 é derivável e barata (ceiling ≈ 2), mas **discrimina pouco**; entra como relato forward-only, nunca como catraca.

**D-R2 — por que sai da catraca.** 176 das 181 citações `it(`/`test(` vivem dentro de bloco ``` [herdado de V5]. Apagar o bloco derruba 138→0 sem escrever teste, e a catraca só-desce **trava o teto embaixo permanentemente**. A frase do desenho — *"o único jeito de melhorar é o teste passar a existir"* — é falsa. Se um dia virar catraca, o denominador tem que ser **independente do autor** (ex.: bullets `❌`/`⛔` ancorados nos ids), não o texto que ele controla.

**D-R4 — o "0" é 0-por-silêncio.** Confirmei o regex do dono (§Anexo A.4): `^key:\s*["']?(\S+?)["']?\s*$` — `\S+?` **não atravessa espaço**, então `related_prototype: n/a (herda PT-01 Lista; …)` (valor de 156 charters [herdado]) é **pulado sem erro**. E `related_proto_baseline` tem 0 declarações. Antes de qualquer número: decidir a semântica de extração e **re-medir** — o resultado oscila entre 0 e ~156. Também confirmei que `charter-refs` checa só `['component','runbook','parent_capterra']`, logo **`related_runbook` (15 charters) não tem dono** — se D-R4 for adiante, entra junto.

**Onde mora:** `governance/charter-refs-baseline.json`, mantendo **4 chaves numéricas de topo** (D-T5). **`--json` não existe** no `charter-refs` (só `--check`/`--fix`/`--list`) — a saída humana é `--list`.

---

### 6 · `resources/js/Pages/<Mod>/<Tela>.casos.md`

**Template canônico** a materializar em `_DesignSystem/CASOS-TEMPLATE.md` **e** a substituir o literal inline de `criar-tela.mjs::casosTemplate` — os dois, para não drifarem como já drifou o charter.

#### Frontmatter — ordem corrigida (os 6 primeiros são os 85/85)

```yaml
---
# id: DERIVADO do path — regra: slugify do caminho sob resources/js/, sem extensão
#     (ex.: resources-js-pages-cliente-create-casos). O criar-tela.mjs carimba na geração;
#     doc-id-stamp.mjs NÃO atende este path (está em TOXIC_PREFIXES) — não mandar rodá-lo.
id: resources-js-pages-{{mod}}-{{tela}}-casos
casos: "{{Tela em PT-BR}} · /{{rota-real}}"
irmaos: "{{Tela}}.charter.md (lei) · {{Tela}}.tsx (código)"
tecnica: "Caso de uso = narrativa do operador + critério de aceite verificável"
owner: "{{wagner|felipe|maiara|luiz|eliana}}"
last_run: "{{AAAA-MM-DD}}"
# ── abaixo: presentes na maioria, não em todos ──
por_que: "{{o que NÃO muda quando o layout mudar}}"
related_cu: []     # OPCIONAL. Sem cláusula de prosa: não existe exigência de "a §Âncora
                   # tem que dizer, com o comando, que o módulo não tem SDD" — isso só
                   # seria enforçável como presence-gate.
related_us: []
last_run_ci: "{{run id + lane + o que CADA UC devolveu}}"
---
```

#### Seções

`# Casos de Uso & Aceite` · `> **Âncora:**` (ordem de fonte canônica; os UC derivam do **contrato**, nunca do `.tsx`) · `> **Status:**` (legenda ✅/🧪/⬜/🔶/❌ — só `✅` é afirmação e exige prova no manifesto, G-7) · `## Rastreabilidade` · blocos `## UC-<PREFIXO>-NN` · `## Backlog de casos` (`[BACKLOG]`, sem id) · `## Trilha do tempo`.

> ⚠️ **REGRA LITERAL DO PREFIXO — a metade que faltava no desenho.** O prefixo é **1 letra + até 5 alfanuméricos (máximo 6 caracteres)**. `UC-ESTOQUE-01` e `UC-PRODUTO-01` **não são reconhecidos** e ficam invisíveis aos gates G-2/G-5/G-7 (required #6).
>
> **Recibo (rodei nesta sessão, §Anexo A.2):** 85 `casos.md` · **435 UC declarados, 435 distintos** · **12 headings `## UC-*` invisíveis**, em 3 arquivos: `Ponto/Espelho/Show` (5), `Ponto/Importacoes/Show` (4), `Ponto/Intercorrencias/Show` (3) — prefixos `ESPSHOW`/`IMPSHOW`/`INTSHOW`, 7 caracteres.
>
> **Recibo do meu próprio erro, que vale mais que o número:** na 1ª tentativa reimplementei o regex à mão e obtive **0 visíveis / 447 invisíveis**. O cabeçalho do `scripts/lib/uc-regex.mjs` documenta essa exata armadilha (*"4 regex que deviam ser iguais e drifaram"*). Só ao importar `ucsDeclaredInCasos` — a porta do dono — obtive 435/12. É LC-08 acontecendo dentro da síntese que a cataloga.

#### Contadores — **fora do arquivo, no `casos:report`**

⛔ **O bloco `<!-- AUTO:CASOS-GRADE-BEGIN -->` dentro do arquivo NÃO ENTRA** (D-T4): entrada não-hermética → deadlock ADR 0261 se required, vermelho permanente se advisory. Os 3 precedentes invocados (skills-index, plans-index, adr-index) são herméticos; este não é.

O que fica, tudo já computado pelo dono `casos-coverage-guard`, publicado em `npm run casos:report`: `ucs_declared` (denominador) · `orfaos` (G-2) · `prova_por_execucao` · `teatro_string_match` · `teto_de_prova` · `status_confrontado` (G-7) · `frescor_vs_tsx` (G-6). **Nenhuma agregação** — os vereditos são incomensuráveis: um `❌` num UC `[must]` **é o achado**, não defeito do arquivo; uma nota puniria a honestidade (386 dos 435 UC estão em 🧪 [herdado de V6]) e seria maximizável apagando UC vermelho.

**Uma violação nova, barata e derivável — e é onde o esforço rende:** `uc-invisivel` — linha que casa `/^##\s+UC/` e **não** casa `ucHeadRe()`. População medida hoje = **12**, em 3 arquivos. Entra no baseline como dívida congelada, forward-only. Não é gate novo: é o dono do tema deixando de ser cego.

**Rebaixamento de prosa obrigatório:** `ancora_que_resolve` mede **integridade referencial** (o id de contrato existe no repo) — **não** defende contra o UC tautológico. Resolver um id prova que o CU existe, não que o UC derivou dele; um UC escrito olhando o `.tsx` e carimbado depois passa igual. O predicado real é semântico e a defesa é **cultural** (`how-trabalhar.md` §ordem-de-fonte + §5 2026-06-05) + revisão adversarial.

**Premissa a declarar junto de qualquer número por-arquivo:** `execBackedMetric` e `tetoDeProva` deduplicam por UC-id, então a soma por-arquivo só bate com o agregado enquanto não houver UC repetido. **Medi: 435 declarações, 435 distintos, 0 repetidos (2026-08-04).**

---

### 7 · `memory/requisitos/<X>/SCOPE.md`

#### Frontmatter — **tudo citado** (D-T1), e três campos mudam de status

```yaml
---
module: "{{NomeDoModulo}}"
purpose: "{{o que este módulo É e o que ele DELEGA}}"
contains:
  - "{{XxxController — o que faz}}"
not_contains:
  - "{{Assunto vizinho → Modules/{{Outro}}}}"
trust_required: "{{L3}}"
permission_prefix: "{{modulo}}.*"
url_prefixes:
  - "/{{modulo}}/*"
# ── opcionais: OMITA a chave se não se aplica ──
# charter_adr: "{{NNNN}}"     ← opcional POR DESIGN (ver abaixo)
# related_adrs: []
# db_tables_owned: []
# migracao_ui: "bloqueado-escopo — {{motivo}}"   ← só este valor; o resto é DERIVADO
---
```

**Quatro mudanças com recibo:**

1. ⛔ **`owner:` SAI.** Medido: 32/32 dizem `wagner`; nenhum consumidor o trata como autoritativo (`catalog-graph` só o copia). É o mesmo boilerplate podre que `TEAM.md §3.1` já denuncia no `owner:` de SPEC — e a matriz de ownership é **plural** (15 das 24 linhas têm 2+ ✅), logo não cabe num escalar. Os 32 legados ficam (forward-only).
2. ✅ **`charter_adr` vira opcional.** Módulo novo normalmente não tem ADR mãe no merge; hoje o autor não tem saída (inventar o número acende S2; deixar `{{}}` acende S1 e quebra o YAML). Omitir é a resposta; `S2` só valida quando presente.
3. ✅ **`migracao_ui` reduzido a `bloqueado-escopo`.** Medido [herdado de V7]: 24 de 32 valores são 100% deriváveis por `module-surface --migracao` (migrado→concluido 12/12, parcial→pendente 11/11, sem-ui→nao-aplica 1/1). O único bit não-derivável é `bloqueado-escopo` × `pendente` em 8 módulos — decisão [W]. Manter o campo inteiro é **restatear número que outro sistema sabe melhor** (§5 2026-07-17), e S4 existiria só para policiar transcrição.
4. ✅ **Consumidores: são 5, não 3** — `bin/check-scope.php` · `catalog-graph.mjs` · `module-surface.mjs --migracao` · **`Modules/Governance/Services/DriftAlertService.php:136-176`** (runtime) · **`.github/workflows/governance-gate.yml:285-380` job `scope-md-drift`** (dentro do job required de `rulesets`). Comando para o leitor re-derivar: `grep -rn 'SCOPE.md' --include=*.mjs --include=*.php --include=*.yml . | grep -v node_modules`.

#### Seções

`# Modules/<X>` · `## Missão` · `## Trust level` · `## Quando NÃO é tocado` · `## Multi-tenant` (obrigatória se o módulo cria tabela própria) · `## Estado atual / Roadmap` (opcional) · changelog de rodapé (append-only, muda a **fronteira**, não o código).

#### Contadores

| id | contador | estado |
|---|---|---|
| S1 | `placeholder-nao-curado` (grep `{{`) | **entra** — medido hoje: **0 de 32** arquivos com `{{`, FP zero |
| S2 | aresta que resolve (`charter_adr`/`related_adrs` → ADR existe; `→ Modules/Y` → módulo existe) | **já existe** em `catalog-graph --check` — a proposta **cita**, não recalcula |
| S3 | `contains ≥1 && not_contains ≥1` | ⛔ **SAI — presence-gate literal** |
| S4 | `migracao_ui` × Blade servido | ⛔ **SAI** — resolvido por subtração (derivar o campo) |
| S5 | `db_tables_owned` × `Schema::create` | 🔵 **decisão [W]**, com classificador como pré-requisito |

**S3 sai** porque "lista presente porém vazia é erro" é exatamente a variante rejeitada em §5 2026-07-09 (*"seção presente-mas-vazia vira erro, seção presente-com-lixo passa"*), e o poder discriminante é **1 de 32** (medi: `not_contains` falta em 1; `contains` em 0).

**S5 fica bloqueada por três defeitos, não um:**
- **Chokepoint fantasma**: `catalog-graph.yml` não dispara em `Modules/**/Database/Migrations/**` — o job fica mudo exatamente quando deveria morder.
- **Direção não uniforme** — e este é o argumento que mata a catraca: das 221 tabelas, **17 são casos em que a declaração está certa e a árvore é histórica** (Jana cria `mcp_tasks`/`mcp_cycles`, o dono legítimo é Forja — padrão de extração de módulo). Pior: o conserto que S5 prescreve dispara o alarme de **co-ownership** de `catalog-graph.mjs:502`, que alimenta S2. **S5 e S2 mandam fazer o oposto nas mesmas 17 tabelas.** Contar direções incomensuráveis e catracar é a lápide C9. [herdado de V7, não re-medi as 221]
- **Cego a Classe B** (Sells, Produto — migrations em `database/migrations/`).

Critério de reabertura: existir um classificador que separe *dívida real* × *dono legítimo é outro* × *core/Classe B*. Caminho barato já disponível: excluir toda tabela já declarada em `db_tables_owned` de **outro** SCOPE.md.

---

### 8 · `memory/requisitos/<X>/BRIEFING.md`

#### Frontmatter

```yaml
---
# id: DERIVADO — slugify do path sob memory/ (regra: scripts/governance/doc-id-stamp.mjs).
#     Medido: 77 dos 78 BRIEFINGs já seguem (_Geral→requisitos-geral-briefing;
#     PaymentGateway→requisitos-payment-gateway-briefing). Hoje é à mão: o path está
#     em TOXIC_PREFIXES do stamper.
id: requisitos-{{modulo-kebab}}-briefing
module: "{{NomeModulo}}"
status: "{{producao|piloto|em-construcao|parcial|backlog|shared-infra|meta|deprecated}}"
status_nota: "{{o que o enum não diz — curto e verificável}}"
updated_at: "{{YYYY-MM-DD}}"
owner: "{{W}}"
# ── opcionais: REMOVA a chave se não se aplica ──
# related_adrs: ["{{NNNN-slug}}"]
# piloto: "{{cliente ou biz=N}}"
# lifecycle: "{{ativo|arquivado}}"
---
```

**Recibo AJV corrigido** (o do desenho está errado — D-T1): recusam **5 campos**, e `module` recusa por `must be string` (o `{{}}` não-citado vira objeto), não por falta de `pattern`. Com os placeholders **citados**, a recusa passa a vir de enum/pattern, que é o que se quer.

**Enforcement, com o dono apontado:** a família BRIEFING do `memory-schema-gate` está em **grace, warn-only** — não consta em `classic_protection.contexts` nem em `rulesets.contexts`. O template **não pode** dizer "a máquina cobra" sem dizer isso.

**`related_adrs` entra na lista de opcionais** — hoje ficava fora, então módulo sem ADR ou deixava placeholder podre ou adivinhava.

**Argumento corrigido sobre `module == basename(dir)`:** o motivo real de não checar é que **42 dos 78 nem têm a chave** e ligar isso acordaria legado em massa (§5 2026-07-12) — **não** que o `required` do schema já cubra. `required` é presença; `module: Jana` num BRIEFING de Financeiro passa hoje.

#### Seções

`# BRIEFING — {{NomeModulo}}` + bloco-citação (função única: resumo + índice; aponta, não recopia) · `## O que é` · `## Estado atual` (toda métrica com o comando re-executável ao lado) · **`## Portas canônicas`** (a razão de existir do arquivo) · `## Tópicos` (só se existir `topicos/`) · `## Decisões e riscos` · `## Próxima ação verificável` · `## Regra de manutenção`.

> ⚠️ A coluna "Revisão" da tabela de Tópicos **é lida do frontmatter de `topicos/<slug>.md`** (dono: `topico.schema.json`, família required #26) ou é omitida — nunca escrita à mão no índice.

#### Contadores

**`portas_ausentes`** — para cada dono que **existe**, o índice aponta? 4 sub-alvos: `SUPERFICIE.md` · `SPEC.md` · `SCOPE.md` · `topicos/`.

**Recibo (rodei nesta sessão, §Anexo A.5):**

```
BRIEFING não-arquivados = 74 | DENTRO do domínio do scan() = 45 | FORA = 29
com ≥1 porta ausente = 40 | distribuição 0..4 = {0:34, 1:12, 2:18, 3:10, 4:0}
ausências por alvo = {SUPERFICIE:32, SPEC:19, SCOPE:26, topicos:1} | total = 78
dívida que o host NÃO enxerga = 8
```

**Quatro travas antes de qualquer catraca:**

1. ⚠️ **Denominador declarado ≠ denominador do host.** O `briefing-code-staleness.mjs::scan()` tem `if (!moduleCodeExists) continue` — **45 dos 74** entram. **8 dos 40 com dívida ficam invisíveis** ao host escolhido. Publicar a distribuição de 74 e implementar sobre 46 é denominador inventado (§5 2026-07-27). Escolher e declarar: (a) manter o host e publicar 45; ou (b) alargar `scan()` e declarar o blast radius (`isBriefingCoverageGap` alimenta o job **required** #13, e `documentation-loop.mjs:37` importa `scan`).
2. ⚠️ **O recorte de "arquivado" é auto-declarado** (`status: deprecated` / `lifecycle: arquivado`). Um BRIEFING com 3 ausências some da conta escrevendo uma linha de YAML. Conserto: **não sumir com eles** — contar à parte e publicar **nominalmente** (medi 4 excluídos), para que ganhar isenção fique visível.
3. ⚠️ **`topicos/` tem população 2** (1 dentro do domínio do host). É forward-only sem poder discriminante hoje — não é ¼ do sinal.
4. ⚠️ **`--json` não tem linha por módulo** (emite `stale[]`, `noDoor[]`, `coverageGaps[]`…), logo não há "onde anexar": é **contrato novo** com consumidor. E o selftest roda como **primeiro step de job required** — caso novo torto trava o merge do repo inteiro.

**Duas dimensões medidas e rejeitadas** (registro para ninguém re-propor): `BRIEFING recopia a superfície` → FP ≈ 100% (ponteiro com significado é indistinguível de lista recopiada); `número solto sem recibo` → 3.874 hits, com acertos que são justamente o texto que **recusa** o número. Ambas são a família dos guards sintáticos com 4 lápides.

**Nome que passa a mentir:** `gates-registry.json:81` descreve `briefing-code-staleness.yml` como *"6 eixos"*. Um 7º sem atualizar a string deixa o censo errado — **atualizar no mesmo PR**.

---

## Quais artefatos NÃO devem ter contadores — e por quê

| Artefato | Motivo (medido) |
|---|---|
| **`plan.md`** | 5 candidatas, 5 mortas: 1 exige convenção inexistente (17/18 acenderiam), 1 é auto-declarada pelo gatilho (`N/A`), 1 tem **FP estrutural** (`_parcial_` carrega `verificado@` por gramática; detalhar US parcial é o caso canônico), 1 tem **100% FP** (o plano fala do futuro por construção), e o resto **já tem 2 donos** (`plans-index` + `plan-health`) |
| **`tasks.md`** | única candidata é **gameável por deleção** (ref morta só entra no denominador), tem **guarda que não separa** feature fechada de em-andamento, cobre **10% da superfície** (90% é símbolo, não path), **duplica o `deadlink-gate`** required e usa `existsSync` case-insensitive. Denominador n=1 |
| **`casos.md`** (contador **dentro** do arquivo) | entrada **não-hermética** — 5 de 8 dimensões dependem de auto-PR diário e do corpus de testes. Bloco commitado + fonte fora do PR = deadlock ADR 0261. Os contadores existem e ficam no `casos:report` |
| **`SPEC.md`** (catraca) | os 4 eixos já são julgados por **3 jobs required**, diff-aware e com baseline. 2ª porta para o vermelho no ponto que o canon grandfatherou |
| **Qualquer um deles** (nota única) | vereditos incomensuráveis. Em 5 dos 8 a maximização **aponta para o lado errado**: apagar a promessa, apagar o UC vermelho, escrever menos AC, parar de citar path, apagar a linha `**Implementado em:**` |

**E o corolário que fecha o SPEC**, porque é o vetor mais barato do lote e o desenho não o listou: deletar a linha `**Implementado em:**` + flipar `status: done`→`todo` **baixa G1, G3, G4 e G5 simultaneamente**, com zero mudança de código, mexendo em dois campos que o autor escreve. Por isso o denominador (`us_total`, `by_state` incluindo `sem_campo`, `us_com_status`) é **parte obrigatória do contador**, e por isso a catraca não entra.

---

## Separação: implementável agora × decisão [W]

### A · Implementável agora — só template + script existente estendido, tudo advisory

| # | Entrega | Onde |
|---|---|---|
| A1 | Citar **todos** os placeholders de frontmatter nos 8 templates | os 8 arquivos |
| A2 | Restaurar o **envelope** nos 3 templates de feature + `<!-- -->` no bloco explicativo do `tasks.md` + `{{DATA-DA-CLARIFICACAO}}` no bullet de Clarifications | `_TEMPLATE_FEATURE/*` |
| A3 | `>` na coluna 0 na linha de metadata da US + comentário explicando o `STATUS_RE` | `_TEMPLATE_SPEC.md` |
| A4 | Remover `us_count`/`us_list`; `project: COPI` **curado** com a razão (US-INFRA-045) | `_TEMPLATE_SPEC.md` |
| A5 | Regra literal do **prefixo ≤6 chars** + ordem de frontmatter corrigida + regra de derivação do `id:` | `CASOS-TEMPLATE.md` + `criar-tela.mjs::casosTemplate` |
| A6 | Gerador de charter **omite** campo tipado que não sabe; **não** emite `states:`; sentinela `nao-aplicavel`; comentário do `charter_version` corrigido | `criar-tela.mjs::charterTemplate` |
| A7 | `SCOPE.md`: remover `owner:`, tornar `charter_adr` opcional, reduzir `migracao_ui` a `bloqueado-escopo`, corrigir a lista de consumidores (5) | `_TEMPLATE_SCOPE.md` |
| A8 | `BRIEFING.md`: recibo AJV corrigido, `related_adrs` nos opcionais, regra do `id:`, "grace/warn-only" declarado apontando pro dono | `BRIEFING-TEMPLATE.md` |
| A9 | Seção `## Grade do SPEC` (relato G1–G5 + denominador + bandeira `n/a`) no `_STATUS-GENERATED.md` | `requisitos-status.mjs` (compõe `anchor-lint`+`doneness-lint` via `jq '.modules[]'`) |
| A10 | Violação `uc-invisivel` (12 medidos) no baseline existente | `casos-coverage-guard.mjs` |
| A11 | `ac_sem_task` fundido (1 contador), `tela_sem_casos` rebaixado a nudge, rótulo "cobertura DECLARADA" | `feature-lint.mjs` |
| A12 | Antes de abrir o PR: `deadlink-gate --scan` confirmando que as 4 entradas de template não sobem | — |

**Pré-requisito de A9:** estender `sdd-scorecard.mjs:295` para excluir `_STATUS-GENERATED.md` do `gitNewestModuleDocDate` (uma linha, mesmo precedente já escrito ali para `SUPERFICIE.md`) — senão a regeneração empurra `distiller_freshness`, métrica **armada** sob catraca required.

### B · Decisão [W] — campo novo em schema, script novo, catraca, ou wiring de CI

| # | Item | O que decidir |
|---|---|---|
| B1 | Campo **`guards:`** no `charter.schema.json` | campo novo. O schema é `additionalProperties: true`, logo já é *válido* hoje — mas metade do par não é resolvível: `regra: NG-1` é texto livre e verificar "NG-1 existe" seria presence-gate. Ou o `regra:` cita o **texto literal** do bullet (aí resolve por igualdade), ou o campo aponta só o teste |
| B2 | Wirar `feature-lint --check` no CI | hoje roda **sem `--check`** — os 14 códigos de ERRO não bloqueiam nada. Sem isso, qualquer catraca de feature é gate mudo |
| B3 | `D-R3`/`D-R4` como métricas no `charter-refs-baseline.json` | exige **4 chaves numéricas de topo** (aninhar desliga o `baseline-tamper-guard` em silêncio) + decidir a semântica de extração de D-R4 + incluir `related_runbook` (hoje sem dono) |
| B4 | `portas_ausentes` no `briefing-code-staleness` | contrato novo no `--json` (consumido por `documentation-loop.mjs`) + selftest em **lane required** + escolher o denominador (45 do host × 74 do corpus) + publicar nominalmente os excluídos |
| B5 | `S5` (`db_tables_owned` × migrations) | precisa de **classificador de 3 categorias** + `paths:` do `catalog-graph.yml` + controle-negativo provando invocação. Sem isso, conflita com S2 |
| B6 | `D-P3` (plano-zumbi) no `feature-lint` | FP estrutural conhecido (US `_parcial_`). Se for, consome o veredito **classificado** do `anchor-lint` e exclui `parcial` — nunca regex própria sobre `verificado@` |
| B7 | Qualquer **catraca** | nenhuma é recomendada agora. ADR 0336 DR-2 exige ≥2 mordidas reais, e a infra de bite-log não cobre estes eixos |

---

## Anexo A — recibos desta sessão (comando + resultado, 2026-08-04)

**A.1 · Corpus** (`find`, worktree `forja-req`): SPEC.md **59** · BRIEFING.md **78** · SUPERFICIE.md **37** · SCOPE.md **32** · `*.charter.md` sob `Pages/` **209** (repo todo, excl. `node_modules`: **227**; os 18 extras são charters de **módulo** em `memory/requisitos/`, fora do gate #7) · `*.casos.md` **85** · features com `plan.md` **3** · dirs `topicos/` **2**.

**A.2 · UC** (`node`, importando `ucsDeclaredInCasos` do dono): **435 declarados, 435 distintos**; **12 headings `## UC-*` invisíveis** ao `ucHeadRe()`, em `Ponto/{Espelho,Importacoes,Intercorrencias}/Show.casos.md`. `UC_CORE = 'UC-(?:[A-Z][A-Z0-9]{0,5})?-?\d{1,3}[a-zA-Z]?'` → prefixo máx. 6. *(1ª tentativa, reimplementando o regex: 0/447 — erro meu, registrado.)*

**A.3 · Gerador de feature** (leitura de `feature-lint.mjs:176-220`): `stripTemplateEnvelope` remove `^---…---` **+** o comentário HTML; `renderFeatureTemplate:208` carimba `id:` reinserindo em `^---`; `replaceAll('{{YYYY-MM-DD}}', date)` atinge o arquivo inteiro; `{{modulo-kebab}}` **não** está na lista de substituições. Os 3 templates vigentes têm o envelope.

**A.4 · charter-refs** (leitura): checa só `['component','runbook','parent_capterra']` com `^key:\s*["']?(\S+?)["']?\s*$`; `isRepoRelative = p => !/^https?:\/\//.test(p) && /^[A-Za-z0-9._-]+\//.test(p)`; modos = `--check|--fix|--list` (**sem `--json`**); baseline = `{"ceiling": 0, "metric": "charter_refs_broken"}`.

**A.5 · BRIEFING portas ausentes** (`node`): 74 não-arquivados · 45 dentro do domínio do `scan()` · 40 com ≥1 ausência · distribuição `{0:34, 1:12, 2:18, 3:10, 4:0}` · por alvo `{SUPERFICIE:32, SPEC:19, SCOPE:26, topicos:1}` = **78** · **8** com dívida invisível ao host.

**A.6 · charter related_us** (`node`, 2 critérios) : 166 refs, **2 órfãs** (`US-FIN-050`, `US-DASH-006`). Dono para calibrar (`charter-us-lint`): *209 charters · 114 com `related_us` · 95 sem (54,5%)* — e ele **não** valida existência.

**A.7 · SCOPE** (`grep`): **0 de 32** com `{{`; presença de campos idêntica à do desenho (`db_tables_owned` 6/32).

**A.8 · required** (`node` sobre `required-checks-baseline.json`): `classic_protection.contexts` = **34**, `rulesets.contexts` = **1**, `enforcement_level: "everyone"`.

**A.9 · YAML** (`node` + `js-yaml`): `{{X}}` não-citado → **objeto**; com sufixo → **THROW**; citado → string.

## Anexo B — o que ficou **[não medido]**

`anchor_stale` (G6) · `charter_pointer_missing` (D-R4 — semântica de extração indefinida; oscila entre 0 e ~156) · as 221 tabelas de S5 e as 17 de dono-alheio (herdado de V7) · os 138 guards fantasma e as 176/181 citações em fence (herdado de V5) · `us_with_status = 516/944` (herdado de V1) · comportamento do parser `Symfony\Yaml` (PHP proibido nesta sessão) · execução do `casos-coverage-guard` completo · reprodução das "15 órfãs" de V5 (**medi 2 e não reproduzi 15**).