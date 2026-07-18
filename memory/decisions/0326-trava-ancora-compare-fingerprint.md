---
slug: 0326-trava-ancora-compare-fingerprint
number: 326
title: "Trava de âncora no compare-time do fingerprint — enforcement na MÁQUINA quando a superfície é não-hookável"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-08"
module: governance
tags: [design, fidelidade, ancora, fingerprint, enforcement, hook, cowork, aplicar-prototipo]
supersedes: []
superseded_by: []
related:
  - 0327-anchor-content-required-emenda-0314
  - 0264-governanca-executavel-trio-dominio-e2e
---

# ADR 0326 — trava de âncora no `--compare` do `style-fingerprint`

> **Status:** `aceito` (2026-07-08, Wagner "promova"). Implementada, testada ponta-a-ponta contra captura REAL, e o gate irmão (`anchor-content-check`) foi promovido a **required** ([ADR 0327](0327-anchor-content-required-emenda-0314.md)). Promovida de proposta porque o padrão "enforcement na máquina" se provou: fecha a reincidência 07-06→07-08 no ponto de uso.

## Contexto — o incidente que a expôs

Rodando o `style-fingerprint` proto×prod do Financeiro/Unificado (2026-07-08), o agente comparou a prod contra o **shell `oimpresso.com.html`** — a **âncora podre** que o Wagner **já tinha pego em 2026-07-06** (o charter foi corrigido pra `related_prototype: prototipo-ui/cowork/financeiro-page.jsx`). O agente **repetiu o erro** e gastou ~1h servindo/bootando o shell errado. **Nenhum mecanismo mecânico barrou** até o Wagner dizer "a âncora incorreta".

Wagner, palavras textuais: *"isso mostra que as máquinas não estão funcionando em conjunto com os hooks"*.

## Causa-raiz — por que a máquina e o hook não engataram

| Peça | O que faz | Por que não pegou |
|---|---|---|
| Hook `block-ancora-no-olho` | PreToolUse(`Read`) bloqueia `Read` de **png** de auditoria | O anchoring errado foi via **Chrome** (curl/servir/navegar/colar snippet) — superfície **não-hookável**. O próprio hook confessa: *"o guard só vê Read (Chrome/paste escapam)"*. |
| `ancora.mjs` | Resolve a âncora certa do charter (`related_prototype`) | Era **advisory** — nada obrigava a rodá-lo ANTES. É o mal que o fingerprint existe pra matar ("o que o agente não lembra de medir, não é medido") aplicado à **âncora**. |
| `style-fingerprint --compare` | Compara 2 capturas | Aceitava **qualquer** JSON — não sabia se o proto era o `related_prototype` do charter. Fingerprint e resolvedor-de-âncora **não se falavam**. |

O erro aconteceu numa superfície que **nenhum hook vigia**, com a máquina certa **desconectada** do ponto de uso.

## Decisão

**A cola vive na MÁQUINA, não no hook** (hooks vigiam tools; o desvio foi no browser). No `prototipo-ui/style-fingerprint.mjs`:

1. **`--snippet <Mod/Tela>`** — resolve a âncora via `ancora.mjs` (subprocesso) e **assa** `window.__ANCORA__=<related_prototype>` num preâmbulo ANTES do snippet. A captura passa a **declarar** contra o que ela é comparável. Sem `<Mod/Tela>` → captura fica com `ancora:null`.
2. **`--compare proto.json prod.json`** agora é **fail-closed**: exige `--tela <Mod/Tela>` (verifica a captura contra o charter via `ancora.mjs`) **OU** `--sem-ancora <razão>` (opt-out **explícito e logado**). Sem nenhum dos dois → **RECUSA (exit 3)**.
   - captura sem `ancora` declarada → RECUSA;
   - `ancora` declarada ≠ `related_prototype` do charter → RECUSA (pega a âncora podre);
   - bate → passa.
3. `resolverAncora` chama `ancora.mjs` por **subprocesso, não import** (mesma lição do `block-ancora-no-olho`: import quebrado = fail-open; subprocesso que falha = **RECUSA**, fail-closed).
4. **Check de CONTEÚDO (F5, `overlapConteudo`)** — porque a declaração de âncora é só um CLAIM (assado do argumento, não do DOM), o `--compare` também extrai os RÓTULOS distintivos do `.jsx` da âncora e mede quantos aparecem no texto da captura; overlap baixo ⇒ o DOM não veio daquele arquivo → **RECUSA**. Calibrado contra captura REAL (financeiro renderizado ~17-20% presente; shell ~0%; threshold 8%). Extração filtra código/className (senão inflava o denominador → falso-refuse — pego no teste-do-processo). Reduz o teto do claim; não elimina.

Selftest hermético cobre os vereditos (sem-declaração/podre/bate + conteúdo certo/shell/anti-poluição). O hook `block-ancora-no-olho` **continua** (cobre o vetor Read-png) mas deixa de ser a **única** defesa.

## Resíduo HONESTO (declarado, não escondido)

- A declaração de âncora é um **CLAIM**: `--snippet <tela>` assa a âncora certa, mas quem cola pode colar na página errada. O F5 (overlap de texto) reduz isso a "evidência fraca-porém-real", mas **o browser é não-hookável; não há oráculo formal acima do charter.**
- Fonte-da-captura = **URL/DOM**; âncora = **arquivo** (`.jsx`). Não há link limpo máquina-verificável entre "este DOM renderizado" e "é o financeiro-page.jsx" — só overlap de texto, não fidelidade visual.
- Portanto **"resolver" aqui = converter skip silencioso → recusa/override auditável**, movendo a trava pra onde **uma tool roda de fato** (compare-time). **Não é bloqueio físico.** É o mesmo teto honesto que o `block-ancora-no-olho` já confessa.

## Meta-princípio (o que generalizar)

> **Quando a superfície do erro é não-hookável (Chrome, paste, ação humana), o enforcement tem que viver DENTRO da máquina que produz o artefato — como input obrigatório fail-closed — não num PreToolUse que vigia tools. E essa máquina precisa ser required** ([ADR 0327](0327-anchor-content-required-emenda-0314.md)), senão continua sendo lembrança.

## Alternativas descartadas

- **Só endurecer o hook** (mais matchers) — não cobre Chrome/paste (o próprio hook diz isso). Teatro.
- **Fingerprint-harness fail-closed** (só) — o caminho primário é o **snippet colado na mão**, que contorna a harness (foi exatamente o que aconteceu). A trava tem que ser no `--compare`, que toda captura atravessa.
- **Só tornar o protocolo mais alto** (skill mais barulhenta) — o skill JÁ mandava rodar `ancora.mjs`; o agente pulou. Re-inflar processo é a armadilha que a `proibicoes` cataloga.

## Implementação

PRs #3967 (trava) · #3971 (F5 conteúdo) · #3973 (F5 fix extração, pego pelo teste-do-processo). RUNBOOK do fluxo ponta-a-ponta: [`prototipo-ui/RUNBOOK-fidelidade-fingerprint.md`](../../prototipo-ui/RUNBOOK-fidelidade-fingerprint.md).
