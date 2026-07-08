---
status: proposal
title: Trava de âncora no compare-time do fingerprint — enforcement na MÁQUINA quando a superfície é não-hookável
proposed_by: Wagner + Claude
proposed_at: 2026-07-08
relates_to:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-catraca-sentinela-gate-cadencia
---

# PROPOSAL — trava de âncora no `--compare` do `style-fingerprint`

> **Status:** `proposal`. Wagner aprovou a implementação (2026-07-08) sabendo que **reduz + torna auditável, mas NÃO blinda o Chrome**. Promover a ADR canon se o padrão "enforcement na máquina" se provar em ≥1 reincidência evitada.

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
   - bate → passa, loga `âncora ✓ <path>`.
3. `resolverAncora` chama `ancora.mjs` por **subprocesso, não import** (mesma lição do `block-ancora-no-olho`: import quebrado = fail-open; subprocesso que falha = **RECUSA**, fail-closed).

Selftest hermético cobre os 3 vereditos (sem-declaração/podre/bate). O hook `block-ancora-no-olho` **continua** (cobre o vetor Read-png) mas deixa de ser a **única** defesa.

## Resíduo HONESTO (declarado, não escondido)

- A declaração de âncora é um **CLAIM**: `--snippet <tela>` assa a âncora certa, mas **quem cola pode colar na página errada** — o `--compare` vê "declarou financeiro-page.jsx" e passa, mesmo se o DOM capturado for o shell. **O browser é não-hookável; não há oráculo formal acima do charter.**
- Fonte-da-captura = **URL/DOM**; âncora = **arquivo** (`.jsx`). Não há link limpo máquina-verificável entre "este DOM renderizado" e "é o financeiro-page.jsx".
- Portanto **"resolver" aqui = converter skip silencioso → recusa/override auditável**, e mover a trava pra onde **uma tool roda de fato** (compare-time). **Não é bloqueio físico.** É o mesmo teto honesto que o `block-ancora-no-olho` já confessa.

## Meta-princípio (o que generalizar)

> **Quando a superfície do erro é não-hookável (Chrome, paste, ação humana), o enforcement tem que viver DENTRO da máquina que produz o artefato — como input obrigatório fail-closed — não num PreToolUse que vigia tools.** A máquina passa a exigir a âncora no próprio ponto de entrada, independente de hook.

## Alternativas descartadas

- **Só endurecer o hook** (mais matchers) — não cobre Chrome/paste (o próprio hook diz isso). Teatro.
- **Fingerprint-harness fail-closed** (só) — o caminho primário é o **snippet colado na mão**, que contorna a harness (foi exatamente o que aconteceu). A trava tem que ser no `--compare`, que toda captura atravessa.
- **Só tornar o protocolo mais alto** (skill mais barulhenta) — o skill JÁ mandava rodar `ancora.mjs`; o agente pulou. Re-inflar processo é a armadilha que a `proibicoes` cataloga.

## Gate de promoção a ADR canon
≥1 reincidência de "âncora podre" **evitada** pela trava (log de RECUSA no `--compare`) OU adoção pelo time MCP sem atrito. Até lá, `proposal`.
