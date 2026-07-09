---
slug: 0329-doutrina-documentacao-de-processo-executavel
number: 329
title: "Doutrina de documentação de processo: executável, fonte-única, ligada-ao-gate, cross-plataforma, auto-fresca"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-07-09"
module: governance
quarter: 2026-Q3
tags: [governanca, documentacao, gate, conhecimento, doutrina]
supersedes: []
superseded_by: []
related: [0256-knowledge-survival-meia-vida-catraca-sentinela, 0257-adr-status-lifecycle-kind-modelo-canonico, 0264-governanca-executavel-trio-dominio-e2e, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura, 0299-figma-nao-e-fonte-de-design]
pii: false
review_triggers: []
---

# ADR 0329 — Doutrina de documentação de processo: executável, fonte-única, ligada-ao-gate, cross-plataforma, auto-fresca

> **Status:** `proposto` (2026-07-09, redação [CC]). Aguarda ratificação de Wagner. Nasce da sessão de teste ponta-a-ponta do protocolo de aplicação de protótipo (loop Cowork→Code) + auditoria adversarial de 57 agentes sobre a memória do processo. Não cria gate `required` novo (respeita a política "required = só Tier-0" da [0314]); **rege como o conhecimento do processo é documentado.**

## Contexto

Nesta sessão testamos o protocolo de aplicação de protótipo ponta a ponta e fechamos três furos, cada um o mesmo tipo de erro: um mecanismo que existia mas não estava travado por máquina, dependendo de alguém lembrar de rodá-lo.

- **#3999** — o `style-fingerprint --selftest` existia e passava, mas não rodava em CI (selftest órfão).
- **#4001** — os IDs dos dois projetos Cowork viviam só em prosa em 10 docs; o agente adivinhava e confundiu os projetos 3× (2026-07-06). Virou a fonte única `protocolo.config.mjs`.
- **#4002** — o `<tela>-visual-comparison.md` não tinha sentinela de frescor; 14 comparativos estavam stale 51-53 dias, invisíveis. Virou `visual-comparison-staleness.mjs`.

A auditoria adversarial de 57 agentes sobre a memória do processo destilou o **meta-padrão raiz**, mais fundo que "faltou lembrar": o conhecimento **e** os mecanismos existem e estão corretos — o que falta é a **trava**. O enforcement está sistematicamente desacoplado do merge: advisory por política, opt-in (o comparador `--compare` prova que sabe morder via `--selftest`, mas nunca é *invocado* no PR), proposto-mas-nunca-construído, Windows-only (43/60 hooks `.ps1` somem pro time MCP em Mac/Linux), ou construído-mas-desarmado. E o próprio canon que deveria lembrar o agente está stale, citando gates já deletados (`mwart-gate.yml`), sem sentinela de drift doc↔realidade.

O núcleo: **corrigir o mecanismo ≠ invocá-lo**, e a fidelidade de design ficou fora do `required` por decisão deliberada (0314). Este ADR fixa a regra que impede a classe inteira de reincidir.

## Decisão

**Regra-mestra:** documente cada fato do processo na forma mais *auto-defensável* possível. Se uma máquina pode verificá-lo, ele vira código executável — não prosa. Prosa fica só para o *porquê*, que máquina nenhuma verifica, e vive em ADR append-only.

Toda documentação de processo nasce satisfazendo **5 propriedades**:

1. **Executável, não declarativo.** Se dá pra virar código que roda no CI, vira. Constante → fonte única (`const` importável); invariante → selftest com fixture boa/ruim; frescor → sentinela de staleness. Prosa não trava nada.
2. **Fonte única (SSOT).** 1 fato = 1 lugar. Os outros são **ponteiros, nunca cópias** — cópia é drift garantido.
3. **Ligada ao gate de decisão.** Não basta ser código correto: tem que ser **invocado no PR** e **bloquear onde é Tier-0**. Um selftest que prova que o comparador sabe morder, num job advisory que não bloqueia, é meia-defesa.
4. **Cross-plataforma.** O mecanismo roda em qualquer OS (Node, não `.ps1`-only) — senão some pro time em Mac/Linux e sobra só a regra escrita.
5. **Auto-fresca.** O doc/ADR que descreve o mecanismo tem sentinela doc↔realidade; aponta para mecanismo vivo, não fantasma.

O **porquê** (contexto, trade-off, decisão) permanece em prosa — mas em ADR **append-only**, nunca em comentário que some no rewrite.

**Teste ácido** — a qualquer conhecimento do processo, pergunte:
- *"Se o time esquecer disso, o que acontece?"* — se a resposta é "o dono pega no olho", está mal documentado (cultural). Se é "o CI quebra / a sentinela avisa", está bem documentado (mecânico).
- *"Isso pode ficar velho sem ninguém saber?"* — se sim, falta sentinela de frescor.
- *"Esse fato existe em mais de um lugar?"* — se sim, vai driftar; consolide numa fonte + ponteiros.

**Camadas** (a superior aponta para a inferior, nunca duplica):

| Camada | O quê | Forma |
|---|---|---|
| 0 | Constantes / fonte-única | executável, importável (`const`) |
| 1 | Mecanismos (scripts/gates/selftests) | mordem no CI |
| 2 | ADRs | append-only — o *porquê* |
| 3 | Runbooks / skills | *como* operar → apontam para 0 e 1 |
| 4 | Lições / proibições §5 | o que já deu errado → idealmente com hook |

Prosa (3-4) **referencia** código (0-1), não recopia.

## Justificativa

Cada propriedade sai direto de um furo real:

- **Executável** e **fonte-única** vinham da sessão: o ID em prosa (drift, #4001), o comparador em código (#3999). Provadas.
- **Ligada ao gate**, **cross-plataforma** e **auto-fresca** a auditoria revelou como *igualmente obrigatórias*: sem elas, um mecanismo correto ainda falha — advisory que não bloqueia, `.ps1` que some pro time, ou canon que aponta pra gate deletado.

**A doutrina se aplica a si mesma.** No Passo 1 desta sessão (#4003), ao reconciliar o canon com a realidade, a disciplina de *verificar cada afirmação contra o repo antes de editar* pegou **3 falsos-positivos em 7 itens** do próprio relatório adversarial (ex.: o `ragas-gate` "deletado" existia como `jana-ragas-gate.yml`). Corolário perene: **nenhum achado — nem de auditoria adversarial — é lei até ser verificado contra o repositório vivo.** É a Propriedade 5 aplicada ao próprio processo de decidir.

Esta doutrina consolida e nomeia o que [0256] (sobrevivência do conhecimento: catraca+sentinela+gate+cadência) e [0264] (governança executável) já praticavam em casos isolados. Não inventa mecanismo novo; dá a régua única de *qual forma* cada tipo de conhecimento assume.

## Consequências

**Positivas**
- Todo conhecimento de processo novo nasce com um guarda-corpo; o dono para de ser o gate humano de última instância.
- O teste ácido dá um critério objetivo de "está bem documentado?" — some o debate caso-a-caso.
- As camadas matam a duplicação: 1 fato, 1 fonte, ponteiros.

**Negativas / trade-offs**
- Escrever a forma executável custa mais que escrever prosa. É intencional: o custo é pago uma vez, na fonte, em vez de N vezes no colo de quem esquece.
- Nem todo conhecimento é mecanizável — fidelidade visual subjetiva (cor/densidade) permanece olho-humano no screenshot ([0114]); perseguir automação ali reabre a 0290. A doutrina distingue o mecanizável do que não é, não força tudo.

**Riscos mitigados**
- Reincidência da classe "existe mas não é invocado" (o meta-padrão da sessão).
- Canon apontando para mecanismo-fantasma (Propriedade 5).
- Regra Tier-0 defendida só por prosa (Propriedades 1+3).

**Fora de escopo (permanece com Wagner, por item):** ratificar a [0314] e a [0299] (proposto→aceito) e anotar forward-pointers em ADRs aceitos via `adr-supersede.mjs` — são decisão soberana e append-only, não decorrem deste ADR.

## Referências

- **Canon relacionado:** [0256] knowledge-survival · [0257] modelo lifecycle/status/kind · [0264] governança executável (trio) · [0271] revisão de gates CI (required real + subtração segura) · [0299] figma não é fonte.
- **Exemplo-de-drift (a evidência viva da doutrina):** a `0320-programa-ondas-regua-correcao` está `status: aceito` — **lei aceita** — mas parada em `memory/decisions/proposals/`, logo **não indexada pelo MCP** (`decisions-search` não a acha). A `0314` idem. Prova da Propriedade 5: um doc que não está no local que a máquina varre é letra morta, por mais aceito que esteja. *(Este ADR nasceu top-level `memory/decisions/` justamente por isso.)*
- **Prova de aplicação (esta sessão):** #3999 (selftest → CI) · #4001 (fonte-única `protocolo.config`) · #4002 (sentinela `visual-comparison-staleness`) · #4003 (reconciliar canon; verificar-antes-de-editar). Os 5 chips subsequentes (P26/P10/P25/P34/P16) aplicam a doutrina por-área, cada um com "verifique a premissa antes de agir".
