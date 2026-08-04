---
proposal_id: templates-dos-8-artefatos-e-onde-mora-o-contador
status: proposto
created: 2026-08-04
proposed_by: claude-code
decided_by: _pendente — merge [W] = ratificação_
parent_adr: 0345 (taxonomia de arquivos-tema — define QUAIS arquivos; esta define COMO cada um nasce)
related_adrs: [0261, 0264, 0273, 0302, 0336, 0341, 0345]
type: arquitetura-de-conhecimento
supersedes: []
anexo: 2026-08-04-templates-8-artefatos-ANEXO.md
---

# Template dos 8 artefatos — e por que o contador NÃO mora dentro do arquivo

- **Status:** proposto em 2026-08-04 por [CC], a pedido de [W]. Merge = ratificação.
- **Origem:** [W] 2026-08-04 — *"como deve ser o template de cada arquivo exigido? quero que em cada arquivo tenha a grade pontuando, referente às dimensões"* → depois *"acho que cada documento deve ter sua própria dimensão de classificação"*.
- **Método:** 8 desenhos independentes (um por artefato) + 8 críticos adversariais de contexto-zero + síntese. **0 de 8 aprovados na 1ª passada · 75 violações**. Os 8 templates corrigidos estão no [ANEXO](2026-08-04-templates-8-artefatos-ANEXO.md).
- **Irmã:** [ciclo-completo-responsabilidade-por-maquina](2026-08-04-ciclo-completo-responsabilidade-por-maquina.md) — aquela responde *quem cobra cada etapa*; esta, *como cada arquivo nasce e o que se mede nele*.

## Procedência

Worktree `claude/jana-fronteira`, **5 atrás / 3 à frente de `origin/main`**, 2026-08-04. Não é `main` fresco.

## A pergunta de [W] e a resposta medida

> *"quero que em cada arquivo tenha a grade pontuando"*

**A parte "própria de cada documento" está certa e foi adotada** — forçar `density`/`discoverability` (dimensões de **tela**) num `SPEC.md` não mede nada. Cada artefato ganhou dimensões derivadas da pergunta que ele responde.

**A parte "dentro do arquivo" não passa** — e o motivo é técnico, não de gosto.

## D-1 · Nenhum dos 8 qualifica para bloco gerado dentro do arquivo

A regra que permitiria isso é o precedente de `SUPERFICIE.md`, `plans-index --check` e `adr-index-generate --check`: bloco gerado, marcadores, `--check` comparando gerado × commitado. **Os três são herméticos** — fonte e gerado no MESMO PR.

O candidato mais forte era o bloco dentro do `casos.md`. Cai por medição: **5 das 8 dimensões** leem `scripts/casos-test-results.json`, escrito por **auto-PR (~1×/dia)**, e o corpus de testes muda em ~25% dos commits em 60d. Bloco commitado com fonte fora do PR = o **deadlock que a [ADR 0261](../0261-fatos-derivados-nao-viram-gate-de-merge.md) proíbe**: advisory vira vermelho permanente; required trava o `main` quando o auto-PR landa.

**Regra:** o contador mora no `--json`/relatório do **dono**. Quando precisar de casa versionada, vai num arquivo que o dono **já gera** (`_STATUS-GENERATED.md`, `catalog.json`, baseline em `governance/`). **Nunca dentro do artefato curado.**

## D-2 · Dois artefatos não têm contador nenhum

`plan.md` e `tasks.md`. Não por omissão — os desenhos **testaram e rejeitaram**, e o `plan.md` registra no anexo as 4 dimensões medidas e reprovadas.

O padrão: documento cuja função é **decidir** (plan) ou **ordenar** (tasks) não se pontua pelos eixos de um que **produz conteúdo**. O mesmo vale para o `BRIEFING`, cuja virtude é apontar — a dimensão dele é praticamente o inverso das outras: *fatos duplicados* (quanto menos, melhor).

## D-3 · Placeholder de frontmatter **sempre entre aspas** — a violação mais cara do lote

`{{X}}` sem aspas **não é string em YAML**, é *flow mapping*. Medido com `js-yaml` em 2026-08-04:

| entrada | resultado |
|---|---|
| `module: {{NomeModulo}}` | vira **objeto** — `{"[object Object]": null}` |
| `permission_prefix: {{modulo}}.*` | **THROW** `YAMLException: bad indentation` |
| `module: "{{NomeModulo}}"` | string ✓ |

Consequências reais, não teóricas:

1. Um `SCOPE.md` com `permission_prefix: {{modulo}}.*` **lança**, e o `DriftAlertService` faz `return []` no catch → **a detecção de drift desliga em silêncio** (fail-open).
2. O template de charter proposto **reprovaria no gate required** `Charter (...*.charter.md)` — `page_id: {{x}}` nem string é.

**Regra:** todo placeholder de frontmatter nasce `"{{...}}"` **entre aspas**; a detecção é por **conteúdo de string** (`/\{\{[^}\n]+\}\}/`), nunca por tipo YAML. O legado `<...>` migra para `{{...}}` citado.

⚠️ Isto corrige uma afirmação minha anterior: eu vendi *"placeholder não curado"* como técnica de custo zero e FP zero. Ela tem uma armadilha de sintaxe que quebra consumidor em fail-open.

## D-4 · A bandeira `n/a` é obrigatória quando o denominador é 0

Três desenhos tinham **falso-verde por população vazia** — um contador que dá 0 porque *não há sujeito* apresentado como saúde:

| caso | reportado | denominador real |
|---|---|---|
| SPEC com ids fora de `US-[A-Z]{2,8}-\d{3,4}` | 🟢 (0 violações) | **9 dos 59 SPEC** são invisíveis ao `anchor-lint` |
| `tela_sem_casos` no requirements | "0 em 3/3" | **0 de 0** — nenhuma feature cita `.tsx` |
| `charter_pointer_missing` | 0 | **156 charters** pulados em silêncio pelo regex |

**Regra:** todo contador publica o **denominador ao lado**, e a bandeira é **`n/a`** — nunca 🟢 — quando o denominador é 0. Sem isso, *"renomear ids"* e *"parar de citar"* viram caminhos 🔴→🟢.

## D-5 · Vocabulário — "grade/nota" fica reservado

Os 8 desenhos deram **6 nomes** para a mesma coisa. Unificação:

| Conceito | Vence | Por quê |
|---|---|---|
| alvo declarado × existência real | **referência que resolve** | única técnica presente nos 8 |
| `{{}}` sobrevivente | **`placeholder-nao-curado`** | é o código literal que o `feature-lint` já emite |
| contar arestas entre 2 arquivos | **cardinalidade de relação** | o adjetivo é obrigatório: *"cardinalidade de presença"* (≥1 item, seção não-vazia) é **presence-gate proibido**, e 3 desenhos confundiram os dois |
| declarado × fonte que o autor não escreve | **frescor contra fonte externa** | com a trava: **as duas pontas lidas por máquina**. G-6 (data-git) e G-7 (JUnit) qualificam; `last_validated` **não** |
| a saída | **contador** + **bandeira** | *"grade/nota"* fica reservado aos 3 donos que já agregam: scorecard de tela (16 dims), `module:grade` (D1-D9), `sdd-scorecard` (13 métricas) |

## D-6 · Não mudar o idioma de um baseline existente

Dois idiomas convivem: **lista de violações nomeadas** (`casos-coverage-baseline.json`, `deadlink-baseline.json`) e **ceiling numérico de topo** (`charter-refs-baseline.json`). Medido: o `baseline-tamper-guard::detectCountRatchet` faz `if (typeof n !== 'number') continue` — logo **trocar `ceiling` por `ceilings: {}` aninhado desliga a vigilância em silêncio**. Se o charter precisar de 4 números, são **4 chaves numéricas de topo**.

## D-7 · Tocar template acorda gate required

`governance/deadlink-baseline.json` já registra dívida em 4 dos templates:

```
_DesignSystem/BRIEFING-TEMPLATE.md : 1
_TEMPLATE_FEATURE/requirements.md  : 3
_TEMPLATE_SPEC.md                  : 1
_TEMPLATE_capterra_ficha.md        : 1
```

`deadlink-gate` é **required** (contexto #34). O PR que landar os templates roda `deadlink-gate.mjs --scan` **antes** e confirma que nenhuma contagem sobe. É a emenda §5 2026-07-27 aplicada: enumerar **todos** os globs que o arquivo casa e medir **cada** um.

## D-8 · 35 required = 34 classic + 1 ruleset

Dois críticos se contradisseram e **os dois estavam certos e parciais**: `classic_protection.contexts` = **34**; `rulesets.contexts` = **1**. São duas camadas no mesmo JSON. É a lápide §5 2026-07-17 (*"varri o arquivo ≠ varri o sistema"*) acontecendo **dentro de um único arquivo**.

**Regra:** nenhum template, script ou comentário afirma o próprio enforcement. Quem precisar falar disso **aponta para `governance/required-checks-baseline.json` e diz qual chave leu** (§5 2026-07-16).

## Consequências

**Positivo:** cada artefato passa a ter template com placeholder citado, dimensões próprias e derivadas, e o contador com denominador declarado. A pergunta *"este documento está preenchido?"* deixa de ser opinião.

**Negativo, declarado:** o PR que landar isto paga a dívida de `deadlink` de 4 templates. E dois artefatos ficam **sem** contador — quem quiser painel completo vai achar buraco; o buraco é honesto.

**Não muda:** nenhum gate vira required. Nenhum baseline muda de idioma. Nenhuma ADR é revogada.

## Gate de reversão

- Se a regra do placeholder citado quebrar o `feature:init` (o gerador substitui `{{...}}` por valor — precisa remover as aspas ao curar) → o gerador é o dono, conserta lá, não afrouxa a regra.
- Se algum contador com `n/a` ficar `n/a` por >60 dias, o denominador nunca existiu → a dimensão sai.
- Se o `deadlink-gate` subir contagem no PR de aplicação → **não force**; a dívida é pré-existente e o forward-only manda deixar como está.

## O que é decisão [W]

1. Ratificar as 8 regras transversais (D-1 a D-8)
2. Aplicar os 8 templates — **e em que ordem**, dado que 4 acordam o `deadlink-gate`
3. `plan.md` e `tasks.md` sem contador: aceitar ou pedir dimensão
4. Criar gerador para `SPEC.md` e `SCOPE.md` (hoje são cópia à mão — os únicos dois sem `*:init`)
5. `SCOPE.md` sem schema JSON e sem check required: corrigir ou aceitar

## Rollback

Proposta em `proposals/`. Se [W] recusar, `status: rejected` (append-only). Os 8 templates são independentes — aplicar um não obriga os outros.
