---
name: "Mapa de Artefatos — a porta única da arquitetura de arquivos"
description: "Índice-ponteiro: quais artefatos de conhecimento existem por módulo e por tela, qual pergunta cada um responde, e qual máquina o mantém vivo. Aponta pros donos canônicos — NÃO os recopia."
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-07-23"
related_adrs: [0345-topicos-vivos-aprendizado-por-critica-revisada, 0256-knowledge-survival-meia-vida-catraca-sentinela, 0264-governanca-executavel-trio-dominio-e2e]
---

# 🗺️ Mapa de Artefatos — a porta única da arquitetura de arquivos

> **Por que este doc é curto (e por que TEM que ser):** ele é um **índice que APONTA**,
> nunca uma cópia. A lei mora nos donos linkados; os números vivos moram nos comandos.
> Se você quiser um fato daqui, **siga o ponteiro ou rode o comando** — não confie em
> número escrito à mão. Fundamento: [ADR 0345](../../decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md)
> (*"fato duplicado entre dois docs = bug de taxonomia"*) + [ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)
> (*derivado + enforçado sobrevive; escrito + lembrado apodrece*).
>
> **Esta é a única porta.** Quer ensinar a IA ou alguém novo do time como o projeto
> guarda conhecimento? Mande esta página. Não crie um segundo manual — seria o próprio
> bug que a ADR 0345 proíbe.

---

## 1. O modelo em uma frase

**1 arquivo = 1 propósito = 1 pergunta que ele responde.** Nunca escreva o mesmo fato em
dois lugares; se ele é derivável, deixe a máquina derivar. Há duas granularidades — **por
módulo** e **por tela** — e um princípio que atravessa as duas: o *mapa* de "quais arquivos
existem" **não é um arquivo, é um comando** (§3).

As máquinas que sustentam isso se dividem em **três funções**:

- **Geradores** — produzem o artefato (você não escreve à mão): `module-surface`, `criar-tela`, `screen-grade-seed`, `catalog-graph`.
- **Validadores** — quebram o CI se algo diverge: `casos-guard`, `screen-coverage`, `anchor-lint`, `memory-schema`.
- **Sentinelas de frescor** — medem apodrecimento: `casos-gate G-6`, `briefing-code-staleness`, `module-surface --check`.

---

## 2. A tabela-mãe — cada artefato, a pergunta que responde, e o dono canônico

**Não recopie nada daqui.** A coluna "Dono canônico" é onde a verdade vive; esta tabela só te leva até lá.

| Artefato | Pergunta que responde | Camada | Dono canônico (a verdade) | Como é mantido |
|---|---|---|---|---|
| `Modules/<X>/SCOPE.md` | "o que é / não é meu?" (fronteira, ownership, tabelas) | módulo | [ADR 0085](../../decisions/0085-fase-3-4-scope-md-completo-actor-resolver-pii-redactor.md) | escrito à mão, validado |
| `<X>/SUPERFICIE.md` | "**quais arquivos** esse módulo tem?" | módulo | [exemplo: Sells](../Sells/SUPERFICIE.md) | **gerado** por máquina |
| `<X>/BRIEFING.md` | "qual o **estado** hoje?" (resumo, 1 pág) | módulo | [taxonomia §2](../../decisions/proposals/2026-07-21-taxonomia-arquivos-modulo.md) | índice que aponta |
| `<X>/SPEC.md` | "qual o **requisito**?" (US) | módulo | schema em [`scripts/memory-schemas/`](../../../scripts/memory-schemas) | escrito, ancorado no código |
| `<Tela>.charter.md` | "qual a **lei** da tela?" (Non-Goals → Pest GUARD) | tela | [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) | escrito (só o dono preenche Non-Goals) |
| `<Tela>.casos.md` | "qual o **contrato de comportamento** (UC)?" | tela | [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) · [exemplo](../../../resources/js/Pages/Sells/Index.casos.md) | escrito, cada UC citado por ≥1 teste |
| scorecard (`governance/scorecards/screens/*.yaml`) | "qual a **nota** da tela?" | tela | mapa de cobertura (abaixo) | gerado, nota = juízo |

**A lei-mãe da taxonomia** (o que decide isto tudo): a proposta
[taxonomia-arquivos-modulo](../../decisions/proposals/2026-07-21-taxonomia-arquivos-modulo.md)
(promovida à [ADR 0345](../../decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md)),
dentro do programa-mãe [estrutura-canon-memoria](../../decisions/proposals/estrutura-canon-memoria.md)
(schema-dono por família de arquivo).

---

## 3. O mapa não é arquivo — é comando

A pergunta "quais artefatos cada tela tem (charter? casos? teste? scorecard?)" **NÃO é um `.md`**.
Um mapa escrito à mão apodrece no mesmo dia (ADR 0256). O mapa é **derivado, recalculado da árvore**
por estas portas vivas:

| Quer saber… | Rode | Fonte da verdade |
|---|---|---|
| charter / e2e / scorecard / a11y por tela | `npm run screen-coverage:report` | `scripts/qa/screen-coverage-map.mjs` |
| casos / trio (UC ↔ teste) | `npm run casos:report` | `scripts/casos-coverage-guard.mjs` |
| superfície de código de um módulo | `node scripts/governance/module-surface.mjs <Mod> --check` | árvore `Modules/<Mod>` ∪ `Pages/<Mod>` |

> ⚠️ Se um número datado num session log ou handoff te incomodar, **re-rode o comando** —
> não edite o número. Session logs são fósseis datados, não "o mapa atual".

---

## 4. As máquinas que mantêm isto vivo — por artefato

<!-- MAQUINAS:INICIO (gerado por mapa-artefatos.mjs — NAO editar a mao) -->

> ⚙️ **Seção gerada** por `scripts/governance/mapa-artefatos.mjs` a partir de
> [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json).
> NÃO edite à mão. Regenerar: `node scripts/governance/mapa-artefatos.mjs --write`.
> A coluna **Gate** deriva do baseline — se um gate é promovido/demovido, re-rode.

### `SCOPE.md`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `scope-guard (bin/check-scope.php)` | valida | ⚠️ advisory (sem gate de CI próprio) | `php bin/check-scope.php --strict` |

### `SUPERFICIE.md`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `module-surface.mjs` | GERA + frescor | ⚠️ advisory (sem gate de CI próprio) | `node scripts/governance/module-surface.mjs <Mod> --write · --all --check` |

### `BRIEFING.md`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `briefing-code-staleness (cobertura)` | valida existência | 🔒 **required** — `Modulo backend com BRIEFING (cobertura)` | `node scripts/governance/briefing-code-staleness.mjs --strict-coverage` |
| `briefing-code-staleness (frescor)` | frescor | ⚠️ advisory (sem gate de CI próprio) | `node scripts/governance/briefing-code-staleness.mjs --strict` |
| `memory-schema (briefing.schema.json)` | valida forma | ⚠️ advisory (sem gate de CI próprio) | `bash scripts/validate-memory-schema.sh briefing` |

### `SPEC.md`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `memory-schema (spec.schema.json)` | valida forma | 🔒 **required** — `SPEC (memory/requisitos/*/SPEC.md)` | `bash scripts/validate-memory-schema.sh spec` |
| `anchor-lint.mjs` | valida âncora spec↔código | 🔒 **required** — `anchor-lint ADR 0273` | `node scripts/governance/anchor-lint.mjs --check <SPEC>` |
| `anchor entry/covers` | valida âncora | 🔒 **required** — `anchor entry/covers gate` | `node scripts/governance/anchor-lint.mjs --json` |
| `doneness-lint.mjs` | valida status×âncora | 🔒 **required** — `doneness-lint ADR 0302` | `node scripts/governance/doneness-lint.mjs --check <SPEC>` |

### `charter`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `memory-schema (charter.schema.json)` | valida forma | 🔒 **required** — `Charter (resources/js/Pages/**/*.charter.md)` | `bash scripts/validate-memory-schema.sh charter` |
| `charter-live-signal.mjs` | valida honestidade | 🔒 **required** — `charter status:live precisa de sinal de prod` | `node scripts/governance/charter-live-signal.mjs --check <charter>` |
| `anchor-content-check.mjs` | valida âncora de design | 🔒 **required** — `Ancora de design nao-shell (F2/F6 required)` | `node scripts/governance/anchor-content-check.mjs --check` |
| `charter-refs.mjs` | valida refs | ⚠️ advisory (sem gate de CI próprio) | `node scripts/governance/charter-refs.mjs --check` |

### `casos`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `casos-coverage-guard.mjs` | valida trio + UC↔teste + frescor | 🔒 **required** — `Casos-coverage · ratchet (trio + rastreabilidade)` | `npm run casos:check · npm run casos:report` |

### `scorecard`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `screen-grades-ratchet.mjs` | valida (nota não desce) | ⚠️ advisory (sem gate de CI próprio) | `node scripts/qa/screen-grades-ratchet.mjs` |

### `mapa de tela`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `screen-coverage-map.mjs` | GERA mapa + catraca | 🔒 **required** — `screen-coverage-gate` | `npm run screen-coverage:report · --check` |

### `catalog.json (grafo IDP)`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `catalog-graph.mjs` | GERA + frescor | ⚠️ advisory (sem gate de CI próprio) | `node scripts/governance/catalog-graph.mjs --write · --check` |

### `dicionário de domínio`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `domain-dict-guard.mjs` | valida enum↔dicionário | 🔒 **required** — `Dominio-dict · ratchet (enum ⇔ dicionário)` | `npm run dominio:check` |

### `qualquer .md canon`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `deadlink-gate.mjs` | valida links doc↔doc | 🔒 **required** — `deadlink-gate (ratchet · integridade referencial)` | `node scripts/governance/deadlink-gate.mjs` |

### `trio de tela NOVA`

| Máquina | Faz | Gate (do baseline) | Como rodar |
|---|---|---|---|
| `criar-tela.mjs` | GERA (.tsx+charter+casos+e2e) | ⚠️ advisory (sem gate de CI próprio) | `node scripts/governance/criar-tela.mjs <Mod/Tela> <PT-0X>` |

**Resumo:** 12 das 20 máquinas catalogadas têm gate 🔒 required (bloqueia merge); o resto é ⚠️ advisory (avisa, não bloqueia).

<!-- MAQUINAS:FIM -->

---

## 5. Estado vivo — rode, não leia número escrito

Estes comandos são a única fonte fresca. **Nenhum número de cobertura é escrito neste doc de propósito** (§ regra de ouro):

```bash
npm run screen-coverage:report      # cobertura de tela (charter/e2e/scorecard/a11y)
```
```bash
npm run casos:report                # cobertura de casos + UCs órfãos + trio
```
```bash
node scripts/governance/module-surface.mjs --all --check   # drift de SUPERFICIE
```
```bash
node scripts/governance/mapa-artefatos.mjs --check          # este doc está fresco?
```

O que é **required** (bloqueia merge) é ditado por [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) — a §4 acima deriva dele.

---

## 6. Grade de maturidade — retrato datado (2026-07-23)

> ⚠️ **Retrato datado, com recibo.** Os números abaixo foram medidos em **2026-07-23** pelos
> comandos da §5. Não são atemporais — **re-meça** antes de citar. A leitura *qualitativa*
> (esqueleto maduro, cobertura de comportamento fina) é o que dura.

| Artefato | Cobertura (2026-07-23) | Enforcement | Nota |
|---|---|---|---|
| mapa / portas vivas | — | required | **9/10** exemplar (é comando) |
| charter | 235/235 telas | schema + live + âncora **required** | **8.5/10** |
| SPEC | 59/79 dirs | schema + anchor + doneness **required** | **8/10** |
| SCOPE | 36/36 módulos | advisory (só `contains[]`) | **7/10** |
| SUPERFICIE | 39/39 mód-com-código, sem drift | advisory | **7/10** |
| BRIEFING | 78/79 | existência required; conteúdo grace | **6.5/10** |
| scorecard | 223/235 | existência required; nota advisory | **6/10** |
| **casos** | **40 telas (~241 sem)**; UC provado 29/154 | casos-gate required | **4/10 — gargalo** |
| **E2E / A11Y** | **9/235 · 3/235** | advisory | **2/10** |

**Leitura:** o **esqueleto e o sistema imunológico são maduros** (leis, schemas, portas vivas,
36/36 SCOPE, 235/235 charter). O buraco não é estrutura — é **prova de comportamento por tela**:
a maioria das telas não tem `casos.md`, e só ~1/5 dos casos declarados têm teste que passa.
Charter a 100% é "contrato afirmado"; casos/e2e é "comportamento provado" — essa é a metade fina.

---

## 7. Como a IA e a equipe usam isto (o fluxo)

1. **Pergunta "o que é/não é do módulo X?"** → `SCOPE.md`.
2. **Pergunta "quais arquivos o módulo X tem?"** → `SUPERFICIE.md` (ou rode `module-surface`).
3. **Pergunta "qual o estado do módulo X?"** → `BRIEFING.md`.
4. **Pergunta "qual o requisito?"** → `SPEC.md`.
5. **Vai mexer numa tela** → leia o `charter.md` (lei) + `casos.md` (comportamento) ao lado do `.tsx`.
6. **Vai criar tela nova** → `node scripts/governance/criar-tela.mjs <Mod/Tela> <PT-0X>` carimba o trio.
7. **Quer o mapa de cobertura** → rode as portas vivas da §3; nunca um `.md` de mapa.

---

## 8. Onde NÃO duplicar

- **Não** crie um segundo manual da arquitetura de arquivos — esta é a porta única.
- **Não** escreva número de cobertura/contagem à mão em doc canônico — aponte pro comando (§3/§5).
- **Não** recopie a tabela de máquinas — ela é **gerada** (§4) do baseline.
- **Não** repita entre `BRIEFING` e `SCOPE`/`SPEC`/`SUPERFICIE` — o BRIEFING aponta, não copia (ADR 0345).
