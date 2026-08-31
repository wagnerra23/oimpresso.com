---
slug: 0386-ambar-da-oficina-revogado-roxo-e-a-unica-identidade
number: 386
title: "Âmbar da Oficina revogado — o roxo canon é a única identidade de chrome; supersede parcial da 0244 (decisão 4)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-31"
accepted_at: "2026-08-31"
accepted_via: "Wagner 2026-08-31, em duas rodadas na mesma sessão: perguntado se queria âmbar na Oficina no repo respondeu 'acho que não' e, ao ser dito que hesitação não é revogação, fechou com 'ambar não'."
module: design-system
quarter: 2026-Q3
tags: [design-system, identidade-visual, cor, accent, oficina, roxo-canon, supersede-parcial, revogacao]
supersedes: []
supersedes_partially:
  - "0244-ds-v5-canon-oficina-padrao"
amends: []
superseded_by: []
related:
  - 0190-primary-button-roxo-universal-295
  - 0235-ds-v4-accent-roxo-universal
  - 0244-ds-v5-canon-oficina-padrao
  - 0263-identidade-cor-gate-bloqueante
  - 0314-poda-gates-onda-2-lei-fusoes
pii: false
review_triggers:
  - "Cliente piloto da Oficina (Martinho biz=164) pedir explicitamente distinção cromática por vertical — sinal qualificado, ADR 0105"
  - "Vertical nova entrar com identidade de marca própria contratada"
---

# ADR 0386 — Âmbar da Oficina revogado; o roxo canon é a única identidade de chrome

**Supersede parcial:** [ADR 0244](0244-ds-v5-canon-oficina-padrao.md) — **somente a decisão 4**. As decisões 1, 2 e 3 (DS v5 único ativo · Oficina = tela-padrão/semente · Inbox 9.75 = régua congelada) seguem **intactas e vigentes**.

## Contexto

A [ADR 0244](0244-ds-v5-canon-oficina-padrao.md) (2026-06-02) decidiu, no item 4, que o **âmbar da Oficina** seria um *accent escopado* (`.oficina-scope{ --accent: … }`), preservando o roxo canon fora do escopo.

Seis dias depois, a [ADR 0263](0263-identidade-cor-gate-bloqueante.md) (2026-06-08) decidiu o oposto no eixo geral — *"Nenhum módulo redefine `--accent` com cor própria"* — e instalou gate **required** com o invariante `--accent*` em hue 250–330. As duas ficaram `aceito`/`ativo`, e **nenhuma citava a outra**.

Auditado em 2026-08-31 contra `origin/main` @ `debcf599ee`, com o predicado real do gate (função `accentHueViolations` de `scripts/conformance-gate.mjs`):

| fixture | veredito |
|---|---|
| `.oficina-scope { --accent: oklch(0.769 0.155 70) }` (âmbar) | **2 violações** · `conformance` rc=1 → job `DS gate` (**required**) rc=1 → merge bloqueado |
| `.oficina-scope { --accent: oklch(0.55 0.15 295) }` (roxo) | 0 violações — o gate não acusa inocente |

E o conflito **nunca chegou a se materializar**: `git grep` no repo inteiro devolve **uma única ocorrência** de `.oficina-scope` — a própria ADR 0244. Nunca houve implementação, nem PR vermelho. O que existia era contradição **de texto** entre duas ADRs vivas.

Levada a decisão a [W], a resposta foi revogar.

## Decisão

1. **A decisão 4 da [ADR 0244](0244-ds-v5-canon-oficina-padrao.md) fica revogada.** Não há accent âmbar escopado para a Oficina. Não há accent de cor própria para vertical nenhuma.
2. **O roxo canon `oklch(0.55 0.15 295)` é a única identidade de chrome** — botão, foco, link, estado ativo, primary. Reafirma [0190](0190-primary-button-roxo-universal-295.md) / [0235](0235-ds-v4-accent-roxo-universal.md) e remove a exceção que a 0244 abrira.
3. **A camada semântica não é afetada.** Status (ok/atenção/erro/info), `--origin-*` e `--stage-*` continuam variando de propósito — é wayfinding, não decoração. A distinção das duas camadas é da [0263](0263-identidade-cor-gate-bloqueante.md) e segue valendo inteira.
4. **O gate da [0263](0263-identidade-cor-gate-bloqueante.md) deixa de ter exceção pendente.** O invariante `--accent*` em hue 250–330 vale sem ressalva de módulo.

## O que esta ADR **não** faz

**Não edita o protótipo Cowork.** Se houver âmbar em `prototipo-ui/`, a mudança nasce no Cowork e desce pelo loop — não é conserto de git. Duas razões independentes, e cada uma basta:

- a [ADR UI-0029](../requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) (ratificada 2026-08-31) torna o protótipo **soberano na FORMA**;
- o §5 de [`proibicoes.md`](../proibicoes.md) (2026-08-13) proíbe nominalmente *"podar a fonte de design para fazer cumprir regra cujo sujeito é a tela"*.

Esta ADR governa o **canon do repo** — o que uma tela pode declarar em `resources/css/`. O que o protótipo mostra é outra jurisdição, e ela tem dono.

**Não mexe em gate required.** Duas ressalvas do `conformance-gate` foram medidas nesta auditoria e ficam **declaradas, não consertadas** (mudança em required = PR de governança próprio):

1. o sweep usa `readdirSync` **não-recursivo** — âmbar em `resources/css/tokens/` passa com `rc=0`, e o gate ainda imprime *"todos os `resources/css/*.css` em roxo ✅"*, afirmando um superlativo cujo denominador não percorreu;
2. a 0263 declara **3** gates required; hoje são **2** — `UI architecture` não está na união `classic_protection ∪ rulesets` (45 contexts).

## Consequências

**Positivas.** Some a única exceção conhecida ao invariante de cor. A pergunta *"posso dar cor própria ao meu módulo?"* passa a ter uma resposta só, e ela já é a que o gate enforça — canon e máquina deixam de discordar. Sessão futura que ler a 0244 encontra o ponteiro para cá (errata no mesmo PR).

**Negativas, honestas.** Perde-se o recurso de wayfinding por vertical no chrome: com N verticais, nada no accent diz em qual o usuário está. Se isso vier a doer, o caminho é ADR nova com **sinal qualificado** ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)) — cliente pedindo, não hipótese — e o lugar natural seria a camada semântica (`--origin-*`/`--stage-*`), que já existe para isso, não o `--accent`.

## Alternativas consideradas

- **Manter as duas ADRs e só declarar a fronteira por errata** (*"a decisão 4 vale no Cowork, não no repo"*) — era o caminho em curso quando [W] decidiu. Rejeitada: descreve honestamente o estado, mas deixa viva uma autorização que ninguém quer usar, e a próxima sessão gasta a mesma auditoria de novo.
- **Abrir exceção escopada dentro da 0263** (permitir `--accent` fora de 250–330 sob seletor `.{modulo}-scope`) — rejeitada: abre exatamente a porta que a 0263 existe para fechar, e custa mexer em gate required com FP medido e bite-test.
- **Revogar a 0244 inteira** — rejeitada: as decisões 1-3 são boas, vigentes e não têm relação com cor.

## Refs

[0190](0190-primary-button-roxo-universal-295.md) · [0235](0235-ds-v4-accent-roxo-universal.md) · [0244](0244-ds-v5-canon-oficina-padrao.md) · [0263](0263-identidade-cor-gate-bloqueante.md) · [0314](0314-poda-gates-onda-2-lei-fusoes.md) §85 · [UI-0029](../requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)
