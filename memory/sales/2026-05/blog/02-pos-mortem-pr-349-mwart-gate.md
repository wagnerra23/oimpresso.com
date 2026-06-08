# Pós-mortem público — quando o gate de qualidade falha — 2026-05-08

> **TL;DR**: Mergeamos um PR sem 4 dos 9 artefatos exigidos pelo nosso processo MWART. Custou 5 PRs follow-up retroativos, ~12-16h dev desperdiçadas. Aprendizado: gate soft não previne; gate hard com escopo correto sim.
>
> **Tom**: técnico-honesto, sem desculpas defensivas, sem hype. Quem assina embaixo é Wagner — aprovação final foi minha, responsabilidade final é minha.

> **Repo público:** [github.com/wagnerra23/oimpresso.com](https://github.com/wagnerra23/oimpresso.com)
> **PR do incidente:** [#349 Visão Unificada Cockpit V2](https://github.com/wagnerra23/oimpresso.com/pull/349)
> **Pós-mortem como código:** este arquivo vive em `memory/sales/2026-05/blog/02-pos-mortem-pr-349-mwart-gate.md` no repo público.

---

## O que aconteceu (timeline)

| Quando | Evento |
|---|---|
| **D-2 (2026-05-07)** | PR [#349](https://github.com/wagnerra23/oimpresso.com/pull/349) abre — "Visão Unificada Cockpit V2" no `Modules/Financeiro` |
| **D-1** | Workflow `mwart-gate.yml` roda e comenta no PR: **"❌ Violações detectadas"** — faltavam 4 dos 9 artefatos obrigatórios |
| **D-1** | Revisor humano (eu, Wagner) vê o comentário, decide mergear: "vamos arrumar depois". Intenção boa, execução errada |
| **D-day (2026-05-08)** | `Squash and merge` direto. Prod recebe a Visão Unificada |
| **D+1 a D+5** | 5 PRs follow-up só pra arrumar o débito retroativo: [#355](https://github.com/wagnerra23/oimpresso.com/pull/355) (charter), [#358](https://github.com/wagnerra23/oimpresso.com/pull/358) (RUNBOOK), [#359](https://github.com/wagnerra23/oimpresso.com/pull/359) (Pest GUARD), [#361](https://github.com/wagnerra23/oimpresso.com/pull/361) (visual-comparison + ADR ui/0003) e mais um de cleanup |

Os artefatos faltando eram:
- `Index.charter.md` ao lado do `.tsx` (exigido por [ADR 0104](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) §F3)
- `UnificadoControllerTest.php` (Pest GUARD multi-tenant — [ADR 0093](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0093-multi-tenant-isolation-tier-0.md) Tier 0)
- `financeiro-unificado-visual-comparison.md` ([ADR 0107](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) §F1.5)
- `RUNBOOK-unificado.md` (ADR 0104 §F1)

## Por que aconteceu (root cause sem rodeio)

O workflow `mwart-gate.yml` tinha `continue-on-error: true` no step de validação. Tradução prática: o gate **comenta** no PR, mas a `conclusion` final é `success`. Branch protection deixa passar. Merge libera.

A decisão original (no [ADR 0104](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0104-processo-mwart-canonico-unico-caminho.md), processo MWART canônico) foi propositadamente **soft**: não queríamos atrapalhar velocidade greenfield enquanto o time aprendia o novo processo de migração Blade→Inertia. Em N PRs anteriores funcionou — porque a intenção do time era alta e o contexto era simples.

Falhou neste por uma combinação banal: sprint cheia, cliente esperando a Visão Unificada, intenção honesta de "depois consertar". Som familiar? É exatamente o som que toda equipe técnica conhece quando um soft gate vira sugestão.

A métrica do audit dos últimos 30 dias é dura:

| Artefato | Cobertura | Total Pages |
|---|---|---|
| `*-visual-comparison.md` | 4 | 127 (~3%) |
| `*.charter.md` | 13 | 127 (~10%) |
| `RUNBOOK-*.md` | 22 | 127 (~17%) |

Soft mode produziu **3 anos de débito técnico em 30 dias**.

## O que aprendemos (em ordem de utilidade pra outras empresas)

1. **Soft gates educam, hard gates protegem.** Os dois servem. O erro foi escolher o errado pro escopo errado.
2. **Disciplina não escala sozinha.** O audit confirmou **zero `--no-verify` em 30 dias** — ninguém burlou nada manualmente. O gap não era cultural, era institucional. Quando o sistema permite, o sistema é a causa.
3. **"Vamos arrumar depois" sempre custa 3-5x mais.** PRs follow-up retroativos exigem reconstruir contexto que estaria fresco no momento original.
4. **A pessoa com poder de aprovar é a única que pode fazer o gate falhar.** Esse pós-mortem existe porque eu (Wagner) aprovei o merge. Ninguém burlou nada — eu autorizei. Hard gate me protege de mim mesmo.

## Decisão tomada (ADR proposta — pendente aceite)

`mwart-gate.yml` vira **HÍBRIDO**:

- **HARD (bloqueia merge)** quando o PR toca `resources/js/Pages/<Mod>/<Tela>.tsx` canônica
- **SOFT (só comenta)** quando toca apenas paths satélites: `_components/`, `_Showcase/`, `Components/shared/`, `Layouts/`
- **Override** segue válido: `/mwart-override <razão>` em comentário do PR vira ADR per-tela `lifecycle: historical`
- **Warm-up de 14 dias** (2026-05-09 → 2026-05-23) em dry-run pra time backfillar PRs em vôo sem trauma

Justificativa decisiva, em uma linha: **100% das regressões silenciosas dos últimos 30 dias foram em `Pages/<Mod>/<Tela>.tsx` canônica. 0 em paths satélites. HARD com escopo restrito bloqueia exatamente o vetor real do bug, sem afetar PRs de polish UI ou helpers.**

ADR proposta completa, com 5 alternativas avaliadas e plano de rollback: [`memory/decisions/proposals/proposta-mwart-gate-hard.md`](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/proposals/proposta-mwart-gate-hard.md).

## KPIs de sucesso D+30 (auto-monitoramento público)

Esses números vão ser publicados aqui mesmo, no mesmo arquivo, em 2026-06-08. Sem caneta vermelha:

- **% PRs MWART canônicos com 9/9 artefatos:** baseline ~30% → meta ≥95%
- **Cobertura `*-visual-comparison.md`:** 4/127 (3%) → meta D+90 ≥ 32/127 (25%)
- **PRs follow-up retroativos:** 5 nos últimos 30d → meta 0
- **Queda de velocidade aceitável:** ≤15% (~7 PRs MWART/dia → ≥6)
- **Overrides `/mwart-override`/cycle:** baseline 0 → threshold alerta >2

**Rollback automático** se conformidade <70% ou velocidade -40% por 7 dias consecutivos. Tempo de revert: <5min (1 linha em `continue-on-error`).

## Princípio do Constituição v2 que falhou

[ADR 0094 — Constituição v2](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4 diz textualmente: **"loop fechado por métrica"** — toda regra crítica precisa de enforcement automático. Soft mode é métrica aberta — detecta mas não fecha o loop. Violou nosso próprio princípio.

Quando ADR canônica falha, o caminho é criar nova ADR (com `amends:`/`supersedes:`), não pretender que não falhou. Foi o que fizemos.

## O que outras empresas (especialmente ERPs concorrentes) podem aprender

1. **Soft gates educam, hard gates protegem.** Use ambos no escopo certo.
2. **Pós-mortem público é diferencial.** A maioria das empresas esconde. Esconder treina o time pra esconder. Publicar treina o time pra notar.
3. **ADR vivo > documento morto.** Quando uma ADR canônica falha, criar nova ADR (com supersedes/amends) é processo, não fracasso.
4. **Dono que assina embaixo > revisor que culpa terceiro.** Eu aprovei o merge. Não foi o time, não foi o gate, não foi o cliente. Foi minha decisão. Hard gate existe pra me proteger da próxima vez.

## Notas de transparência

- Este pós-mortem foi escrito **1 dia depois** do incidente (2026-05-09)
- A ADR proposta está [no GitHub público](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/proposals/proposta-mwart-gate-hard.md), aguardando aceite
- O PR #349 e os 5 follow-up estão linkados acima
- O processo MWART canônico ([ADR 0104](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)) e a [Constituição v2 (ADR 0094)](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) seguem como conhecimento canônico, append-only, no repo público
- Seu cliente atual (se você é cliente) **não foi afetado em produção** — o débito era de processo/qualidade interna, não bug funcional. A Visão Unificada está rodando bem. O custo foi tempo dev nosso, não tempo seu

## CTAs

- Gostou da abordagem? Veja como construímos no público em [github.com/wagnerra23/oimpresso.com](https://github.com/wagnerra23/oimpresso.com)
- É dono(a) de gráfica e quer ERP que aprende rápido? Trial 14d sem cartão (oimpresso.com)
- Curte governança formal? Leia a [Constituição v2](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — 8 princípios duros, 7 camadas, append-only

---

# Variante 1 — LinkedIn (post Wagner, ~300 palavras)

**Pós-mortem público: a vez que mergeamos um PR sem 4 dos 9 artefatos que nosso próprio processo exige.**

Dia 8/maio nosso CI comentou no PR #349 (Visão Unificada Cockpit V2): "❌ Violações detectadas — faltam charter, Pest test, visual-comparison, RUNBOOK".

Eu aprovei o merge mesmo assim. "Vamos arrumar depois."

Custo do "vamos arrumar depois": **5 PRs follow-up retroativos**, ~12-16h dev. PRs #355, #358, #359, #361 e mais um de cleanup, todos só pra reconstituir contexto que estava fresco no momento original.

Por que aconteceu? Nosso gate `mwart-gate.yml` estava em modo SOFT (`continue-on-error: true` no GitHub Actions). Tradução: comentava o erro mas deixava mergear. Em 30 dias, soft mode produziu **3 anos de débito técnico** — cobertura de visual-comparison.md saiu de 0% e parou em 3% (4/127 telas).

Audit confirmou que **zero commits dos últimos 30 dias usaram `--no-verify`**. Ninguém burlou nada. O gap era institucional, não cultural — quando o sistema permite, o sistema é a causa.

Decisão: gate vira HÍBRIDO. HARD em `Pages/<Mod>/<Tela>.tsx` canônica (vetor real de 100% das regressões), SOFT em paths satélites (`_components/`, `Layouts/`). Warm-up 14 dias em dry-run pra time backfillar sem trauma. Override `/mwart-override` segue válido pra emergências, mas vira ADR auditável.

Métrica D+30: ≥95% PRs canônicos com 9/9 artefatos (baseline ~30%). Rollback automático se cair <70%.

Tudo isso vive em git público (github.com/wagnerra23/oimpresso.com), no mesmo nível dos commits de feature. ADR proposta linkável. Pós-mortem completo abaixo nos comentários.

**Pergunta sincera pra debate:** quem aqui já mergeou PR com gate vermelho "vamos arrumar depois"? Como o "depois" tratou vocês?

#engenharia #governanca #ERP #posmortem

---

# Variante 2 — Twitter/X (8-thread)

**1/8** Pós-mortem público: a vez que mergeamos um PR sem 4 dos 9 artefatos que nosso processo exige.

Custo: 5 PRs follow-up, ~12-16h dev desperdiçadas.

Quem aprovou o merge? Eu. Quem assina embaixo? Eu. Vai com fio.

**2/8** Cenário: PR #349 (Visão Unificada Cockpit V2) abre dia 7/maio.

CI comenta "❌ Violações detectadas — faltam charter, Pest test, visual-comparison, RUNBOOK".

Aprovei mesmo assim. "Vamos arrumar depois." Som familiar?

**3/8** Por que o CI deixou mergear se o gate falhou?

Porque o gate estava em modo SOFT: `continue-on-error: true` no GitHub Actions. Comentava o erro mas a conclusion final era `success`. Branch protection liberava.

Funcionou em N PRs anteriores. Falhou neste.

**4/8** Audit dos últimos 30d:

- 127 telas
- 4 com visual-comparison.md (3%)
- 13 com charter.md (10%)
- 22 com RUNBOOK (17%)

Soft mode produziu 3 anos de débito técnico em 30 dias. Não foi falha cultural — zero commits com `--no-verify`. Ninguém burlou.

**5/8** Diagnóstico: quando o sistema permite, o sistema é a causa.

Disciplina não escala sozinha. "Depois conserto" sempre custa 3-5x mais. A pessoa com poder de aprovar é a única que pode fazer o gate falhar.

Hard gate existe pra proteger o aprovador de si mesmo.

**6/8** Decisão (ADR proposta — pendente aceite):

`mwart-gate.yml` vira HÍBRIDO.

- HARD em `Pages/<Mod>/<Tela>.tsx` canônica (vetor real de 100% das regressões)
- SOFT em satélites (`_components/`, `Layouts/`)
- Warm-up 14d em dry-run
- Override `/mwart-override` vira ADR auditável

**7/8** KPI D+30:
- ≥95% PRs canônicos com 9/9 artefatos (baseline ~30%)
- Cobertura visual-comparison.md de 3% → 25% em D+90
- Velocidade -15% no máximo
- Rollback automático se cair <70%

Vou publicar o resultado real aqui em 2026-06-08.

**8/8** Tudo isso vive em git público:
github.com/wagnerra23/oimpresso.com

ADR proposta com 5 alternativas avaliadas + plano de rollback: `memory/decisions/proposals/proposta-mwart-gate-hard.md`

Diferencial vs ERP grande não é tamanho. É publicar o erro antes do concorrente publicar a feature.

---

# Variante 3 — Blog longa (1500 palavras, técnica)

## Pós-mortem público: quando o gate de qualidade vira sugestão

**Por Wagner Ramos · 2026-05-09 · oimpresso.com**

No dia 7 de maio de 2026, o pull request #349 do nosso ERP (Visão Unificada do módulo Financeiro) chegou pronto pra merge. O nosso CI rodou o workflow `mwart-gate.yml` e comentou no PR, em letras claras: **"❌ Violações detectadas"**. Faltavam 4 dos 9 artefatos que nosso processo MWART (Module Web App React Transition — migração de telas Blade legacy pra Inertia/React) exige.

Eu aprovei o merge mesmo assim. "Vamos arrumar depois."

Custo do "vamos arrumar depois": **5 pull requests follow-up retroativos** entre os dias 8 e 12 de maio, com cerca de 12 a 16 horas de tempo dev desperdiçadas reconstruindo contexto que estaria fresco se feito no PR original.

Este pós-mortem documenta o incidente, o root cause, a decisão tomada, e — talvez mais importante — por que a gente publica esse tipo de coisa em git público enquanto a maioria dos ERPs concorrentes esconde.

### O incidente em fatos secos

O PR #349 entregou a "Visão Unificada Cockpit V2" — uma tela que junta Contas a Pagar, Contas a Receber, Fluxo de Caixa Previsto e Conciliação Bancária num único cockpit, seguindo nosso padrão arquitetural Cockpit Pattern V2 (ADR 0110). Tela boa, cliente esperando, sprint apertada.

O nosso processo de migração MWART, formalizado na ADR 0104, exige 9 artefatos pra cada tela canônica em `resources/js/Pages/<Mod>/<Tela>.tsx`:

1. RUNBOOK em `memory/requisitos/<Mod>/RUNBOOK-<tela>.md`
2. Entrada de SPEC.md com user story declarada
3. Charter (`*.charter.md`) ao lado do `.tsx`
4. Pest test do Controller (multi-tenant GUARD — Tier 0)
5. `*-visual-comparison.md` com 15 dimensões (ADR 0107)
6. ADR de UI quando aplicável
7. Hooks documentados
8. Permissões registradas
9. Telemetria mínima

No PR #349, faltavam: 1, 3, 4 e 5.

O `mwart-gate.yml` detectou e comentou. Eu li, aprovei, mergeou via `Squash and merge`. Os PRs follow-up vieram nos dias seguintes:

- [#355](https://github.com/wagnerra23/oimpresso.com/pull/355) (charter)
- [#358](https://github.com/wagnerra23/oimpresso.com/pull/358) (RUNBOOK + SPEC append)
- [#359](https://github.com/wagnerra23/oimpresso.com/pull/359) (Pest GUARD multi-tenant + charter expandido)
- [#361](https://github.com/wagnerra23/oimpresso.com/pull/361) (visual-comparison + ADR ui/0003 amends 0002)
- Mais um de cleanup

### Por que o gate deixou passar

A pergunta certa não é "por que o gate falhou?" — é "por que o gate **não bloqueava merge desde o início**?".

A resposta é uma linha de YAML. O step de validação tinha `continue-on-error: true`:

```yaml
- name: Verify RUNBOOK + SPEC presence
  id: gate
  if: steps.detect.outputs.count != '0' && steps.override.outputs.active != 'true'
  continue-on-error: true   # ← causa raiz do soft mode
  run: |
```

Tradução prática: o gate comenta no PR, mas a `conclusion` final do job é `success`. Branch protection (que confia em `conclusion`) deixa passar. O nome do workflow no header já confessava: **"MWART Gate (soft)"**.

Por que estava soft? Porque a ADR 0104 original (processo MWART canônico) foi propositadamente conservadora. A gente queria que o time aprendesse o processo sem trauma de bloqueio inesperado. Soft mode educa.

Funcionou em N PRs anteriores. Quando funcionou, foi porque a intenção do time era alta e o contexto era simples — todo mundo encarava o gate como prioridade. Falhou no #349 numa combinação banal: sprint cheia, cliente esperando, intenção honesta de "depois conserto".

### O que o audit revelou

Nosso audit interno de CI/PRs dos últimos 30 dias ([`memory/audits/2026-05-pre-sales/04-ci-pr-audit-30d.md`](https://github.com/wagnerra23/oimpresso.com/tree/main/memory/audits)) trouxe a métrica dura:

| Artefato | Cobertura | Total Pages |
|---|---|---|
| `*-visual-comparison.md` | 4 | 127 (~3%) |
| `*.charter.md` | 13 | 127 (~10%) |
| `RUNBOOK-*.md` | 22 | 127 (~17%) |

Trinta dias de soft mode produziram **três anos de débito técnico**. E o detalhe que descarta a hipótese cultural: **zero commits dos últimos 30 dias usaram `--no-verify`**. Ninguém burlou nada manualmente. O gap era 100% institucional. Quando o sistema permite, o sistema é a causa.

### A decisão (ADR proposta — pendente aceite)

A nova ADR — proposta em [`memory/decisions/proposals/proposta-mwart-gate-hard.md`](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/proposals/proposta-mwart-gate-hard.md) — endurece o gate em escopo cirúrgico:

- **HARD (bloqueia merge)** quando o PR toca `resources/js/Pages/<Mod>/<Tela>.tsx` canônica que não seja exempta (helpers `_*`, `App.tsx`, `Layout.tsx`, `_components/`, `_Showcase/`)
- **SOFT (só comenta)** quando toca apenas paths satélites
- **Override** segue válido: `/mwart-override <razão>` em comentário de PR vira ADR per-tela com `lifecycle: historical` (auditável, não casual)
- **Warm-up de 14 dias** (2026-05-09 → 2026-05-23) em dry-run, pro time backfillar PRs em vôo sem trauma

A justificativa quantitativa que decide é uma linha: **100% das regressões silenciosas dos últimos 30 dias foram em `Pages/<Mod>/<Tela>.tsx` canônica. 0 em paths satélites.** HARD com escopo restrito bloqueia exatamente o vetor real do bug, sem afetar PRs de polish UI, helpers ou refactor de `_components/`.

A ADR proposta avalia 5 alternativas (HARD imediato total, SOFT com SLA backfill 7d, SOFT com dashboard público, HÍBRIDO, status quo) com prós/contras/custos/riscos pra cada. O HÍBRIDO ganhou por reversibilidade: rollback é uma linha de YAML em <5 minutos.

### KPIs de sucesso e rollback automático

A própria ADR proposta define os números que vão decidir manter ou reverter em D+30 (08/junho/2026):

- **% PRs MWART canônicos com 9/9 artefatos:** baseline ~30% → meta ≥95%
- **Cobertura `*-visual-comparison.md`:** 4/127 (3%) → meta D+90 ≥ 32/127 (25%)
- **PRs follow-up retroativos:** 5 nos últimos 30d → meta 0
- **Velocidade:** queda ≤15% aceitável (baseline ~7 PRs MWART/dia)
- **Overrides `/mwart-override`/cycle:** baseline 0 → threshold alerta >2

**Rollback automático** se conformidade <70% ou velocidade -40% por 7 dias consecutivos. Não há "vamos esperar mais um pouquinho" — métrica decide.

Esses números vão ser publicados nesse mesmo arquivo, em junho/2026. Sem caneta vermelha — git é append-only e público.

### Por que isso aqui é público

A pergunta honesta de cliente que lê esse pós-mortem é: "Vocês admitem ter bug em produção? Que confiança eu tenho na sua plataforma?".

A resposta honesta é: o débito do PR #349 era de **processo e qualidade interna**, não bug funcional pro usuário final. A Visão Unificada está rodando em ROTA LIVRE (nosso cliente piloto, 99% do volume) sem incidente. O custo do erro foi nosso tempo dev, não o tempo do cliente.

Mas a pergunta mais profunda é: por que publicar?

Porque ERP enterprise grande **esconde** esse tipo de pós-mortem. Esconder treina o time a esconder. Quando um bug grande aparece, o time esconde por reflexo, e o bug vira incidente. Publicar treina o time a notar — é mais barato consertar processo do que consertar reputação.

Nossa Constituição v2 ([ADR 0094](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)) tem 8 princípios duros. O quarto é **"loop fechado por métrica"**: toda regra crítica precisa de enforcement automático, com métrica que detecta drift e fecha o loop. Soft mode era métrica aberta — detectava mas não fechava. Violou nosso próprio princípio.

Quando ADR canônica falha, o caminho não é pretender que não falhou. É criar nova ADR, com `amends:` ou `supersedes:` apontando pro ancestral, mantendo o histórico append-only no git. Foi o que fizemos. ADR proposta `proposta-mwart-gate-hard.md` faz `amends: ADR 0104` explicitamente.

### O que outras empresas (especialmente ERPs concorrentes) podem aprender

1. **Soft gates educam, hard gates protegem.** Use ambos no escopo certo. Nosso erro foi escolher soft pro escopo crítico.
2. **Disciplina não escala sozinha.** Se o time é bom (zero `--no-verify` em 30 dias prova), o problema é institucional. Mude o sistema, não o time.
3. **"Vamos arrumar depois" sempre custa 3-5x mais.** PRs follow-up retroativos exigem reconstruir contexto que estaria fresco no momento original.
4. **A pessoa com poder de aprovar é a única que pode fazer o gate falhar.** Hard gate existe pra proteger o aprovador de si mesmo.
5. **Pós-mortem público é diferencial de mercado.** A maioria das empresas esconde. Em ERP, onde governança e maturidade pesam mais que feature count, publicar é vantagem competitiva.
6. **ADR vivo > documento morto.** Quando ADR canônica falha, criar nova ADR é processo, não fracasso. Fracasso é fingir que a ADR original ainda funciona.

### Próximos passos

A ADR proposta aguarda meu aceite formal (W aprova final). Se aceita, vira `accepted` em PR separado, ganha ID (provavelmente 0120-mwart-gate-hybrid-hard.md), e o plano de implementação roda em 1h dev + 14 dias warm-up.

Em D+30 (08/junho/2026) eu volto aqui e atualizo este mesmo arquivo com os KPIs reais — bate ou não bate. Sem caneta vermelha.

**Repo público:** [github.com/wagnerra23/oimpresso.com](https://github.com/wagnerra23/oimpresso.com)
**ADR proposta:** [`memory/decisions/proposals/proposta-mwart-gate-hard.md`](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/proposals/proposta-mwart-gate-hard.md)
**Constituição v2:** [ADR 0094](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)
**Processo MWART:** [ADR 0104](https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)

— Wagner

---

**Última atualização:** 2026-05-09 (D+1 do incidente). Próxima atualização programada: 2026-06-08 (D+30 com KPIs reais).
