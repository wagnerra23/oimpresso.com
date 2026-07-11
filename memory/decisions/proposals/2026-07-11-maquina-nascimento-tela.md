# PROPOSTA · Máquina de nascimento de tela — toda tela nasce carimbada do Padrão de Tela + ciclo obrigatório

> **Status:** PROPOSTA. **NÃO é lei, NÃO é ADR numerado.** [CC] rascunha; **[W] decide, numera e aprova**.
> **Build sobre:** [ADR UI-0013](../../requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) (Constituição UI v2 — camadas Fundações→Shell→**Padrão de Tela**→Módulo; herança de padrão, NÃO bespoke) · [ADR 0264](../0264-governanca-executavel-trio-dominio-e2e.md) (trio-de-tela G-1/G-2 executável) · [ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md) (catraca + selftest anti-fantasma) · [ADR 0314](../0314-poda-gates-onda-2-lei-fusoes.md) / [ADR 0271](../0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) (required = só Tier-0; gate novo nasce advisory) · [ADR 0298](../0298-teto-de-governanca-anti-proliferacao-gates.md) (teto de governança — todo gate no registry).
> **Origem:** Wagner 2026-07-11 — *"fazer na mão é sorteio e não garante funcionamento"*. Constata que tela feita à mão nasce inconsistente (cada dev inventa a estrutura) e frequentemente fora do padrão; os gates `pt-conformance`/`design-coverage`/`casos-gate` já existem mas cobram DEPOIS, sem uma forma de a tela **nascer certa**.

---

## 1. Contexto — por que os gates existentes não bastam

O repo já tem, no main (2026-07-11), as peças de **verificação**:

| Peça | O que faz | Limite |
|---|---|---|
| `scripts/governance/pt-conformance.mjs` | verifica que uma tela que DECLARA "herda PT-0X" tem a assinatura estrutural do arquétipo (falsificável) | só age em quem já declara; não faz a tela nascer conforme |
| `scripts/qa/design-coverage.mjs` | catraca: quantas telas DECLARAM a fonte de design (UI-0013) | mede declaração, não o ciclo inteiro |
| `scripts/casos-coverage-guard.mjs` | trio-de-tela (charter+casos) G-1 + rastreabilidade caso↔teste G-2 | não olha PT declarado nem se o golden do PT está live |

Todas **reagem** ao que já existe. Falta a **peça generativa** — a tela feita à mão nasce sem esses artefatos e cada um vira débito que algum gate cobra num PR futuro. O resultado é o "sorteio" que o Wagner descreveu: às vezes a tela nasce ok, às vezes não, e o custo de descobrir é empurrado pra frente.

## 2. Decisão — inverter a lógica: nascer-certo em vez de fazer-e-torcer

Adotar uma **máquina de nascimento de tela** em dois lados que se fecham:

### Lado A — GERADOR `criar-tela.mjs` (a tela nasce carimbada do PT)

`node scripts/governance/criar-tela.mjs <Mod/Tela> <PT-0X>` carimba, a partir do golden do Padrão de Tela escolhido, o **conjunto completo do ciclo**:

- **(a)** `<Tela>.tsx` — esqueleto do arquétipo já importando os componentes canônicos (PT-01→DataTable+PageHeader; PT-02→useForm+FormSection+FormGrid; PT-03→seções+FsmActionPanel; PT-04→KpiGrid+KpiCard; PT-05→KanbanDndProvider/BoardColumn). **Passa no `pt-conformance` POR CONSTRUÇÃO** — a assinatura vem da MESMA fonte única `scripts/governance/lib/pt-signatures.mjs` que o verificador consome (zero drift entre gerador e gate).
- **(b)** `<Tela>.charter.md` — `component:` + `related_prototype: n/a (herda PT-0X; segue o Padrão de Tela)` + stub Mission/Goals/Non-Goals, nascendo `status: draft` (exige screenshot Wagner pra virar live).
- **(c)** `<Tela>.casos.md` — stub de UC (o contrato de teste, ADR 0264 G-1) com `owner`/`last_run` + Status por UC.
- **(d)** stub de teste E2E `e2e/<mod>-<tela>.spec.ts` (`test.fixme`, não quebra CI) citando o UC — satisfaz a rastreabilidade G-2.

### Lado B — GATE `ciclo-completo.mjs` (o ciclo continua garantido)

Pra toda `resources/js/Pages/**/*.tsx` roteada, enforça o **conjunto obrigatório**: charter + **PT declarado** + **pt-conforme** (consome `pt-conformance --json`, não reimplementa) + casos.md + ref de teste + **golden do PT `live`**. Faltou algum → tela "incompleta".

- **ADVISORY de nascença** (ADR 0314/0271 — required = só Tier-0; cobertura de ciclo é *quality*, não dinheiro/PII/multi-tenant/fiscal) + **catraca** (o nº de telas completas só sobe; os 276/279 do débito legado são absorvidos no baseline, sem quebrar o repo).
- Registrado no `gates-registry.json` (terminal=advisory + anchor + promote_by) e no `governance-script-tests.yml`; **selftest bite/release** (fixtures herméticas provam que o gate morde a incompleta e solta a completa — anti-fantasma ADR 0256).

### Lado C — GOLDEN-LIVE (o Design fecha o ciclo)

Uma tela **não fecha o ciclo** se o golden do PT que ela herda ainda é `draft`. Hoje **PT-01 está live** e **PT-02..05 estão draft**. Isso vira pressão mecânica e visível (o relatório do `ciclo-completo` mostra quantas telas fechariam o ciclo se cada golden virasse live) pro Design **terminar os 4 goldens draft**. É o lado Design do ciclo — sem ele, o "carimbo" herda de um molde inacabado.

## 3. Não-duplicação (Tier 0)

- O gerador e o verificador compartilham `lib/pt-signatures.mjs` (fonte única das assinaturas) — o `pt-conformance.mjs` foi refatorado pra IMPORTAR de lá (comportamento idêntico, selftest verde).
- O `ciclo-completo` **consome** `pt-conformance --json` (padrão já usado pelo `design-coverage` que consome `ancora --json`) e **ecoa** o trio do `casos-guard` numa visão por-tela, adicionando só a dimensão que ninguém cobria: **PT declarado** + **golden-live**. Não reimplementa nenhum gate existente.

## 4. Consequências

**Positivas:** tela nasce consistente e completa (fim do "sorteio"); o custo de conformidade vai pro momento da criação (barato) em vez de PRs corretivos (caro); o Design ganha uma fila priorizada (goldens draft que travam telas reais).

**Custos / limites honestos:** o gerador produz *stubs* (o dev ainda preenche Mission/Goals reais, UC reais, wiring); o `pt-conformance` é heurístico por assinatura (regex, v1) — o carimbo garante a assinatura, não a qualidade fina; o gate é advisory (não bloqueia merge — a política 0314 reserva required pra Tier-0). A adoção depende de o time **usar o gerador** em vez do editor em branco — a skill/atalho `tela:criar` reduz o atrito.

## 5. Alternativas descartadas

- **Só mais um gate** (sem gerador): mantém o "fazer-e-torcer" — cobra depois, não faz nascer certo. Rejeitado pela origem do pedido.
- **Gate required** pra forçar o ciclo: viola ADR 0314 (required = só Tier-0) e quebraria o repo (276 telas legadas). Rejeitado.
- **Roadmap/doc paralelo** de "padronização de telas": viola o gate T6 (1 tema = 1 doc; evoluir o que existe). A máquina ESTENDE UI-0013, não abre canon paralelo.

## 6. O que Wagner decide

1. Aceitar a máquina como canon (numerar esta ADR).
2. Confirmar o gate **advisory** (não required) — coerente com 0314.
3. Priorizar o **Lado C**: terminar os goldens PT-02..05 (draft→live) pra destravar o fechamento de telas reais.
