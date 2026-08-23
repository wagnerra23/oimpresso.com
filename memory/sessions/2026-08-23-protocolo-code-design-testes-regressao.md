---
date: "2026-08-23"
hour: "10:51 BRT"
duration: "2h"
topic: "Testes de regressão do protocolo Code ↔ Design e cache _ds"
authors: [W, C]
outcomes:
  - "Retorno §10.2 passou a exigir e validar os três canais canônicos"
  - "Preview _ds ganhou validação de path e materialização atômica"
  - "Assinatura, seleção e transporte HTTP de handoffs ganharam cobertura hermética"
prs: [6147]
us: []
related_adrs:
  - "0315-design-sync-claude-design-vs-cowork-charter"
  - "0336-gates-design-promocao-por-mordida-provada-emenda-0314"
---

# Sessão — regressões do protocolo Code ↔ Design

## TL;DR

O workflow pós-merge tinha falso-verde: uma alteração de UI acompanhada apenas de
`SYNC_LOG.md` era aceita, embora o PROTOCOL §10.2 exija também `DS_ADOCAO_INDICE.md` e
`HANDOFF.md`. A regra saiu do YAML e virou um verificador único, exercitado por testes e pelo
workflow. A lane permaneceu advisory, sem promoção silenciosa contrária à ADR 0336.

O preview `_ds` também tinha dois riscos não cobertos: traversal por referências do shell/CSS e
escrita parcial antes de descobrir bundle/fonte inválida. O plano agora recusa paths inseguros,
fecha `@import` recursivo com proteção de ciclos e publica por troca de diretório depois de validar
o lote inteiro.

## Entregas

- `design-return-check.mjs`: Pages, Components, Layouts, CSS, UI modular e Cowork; push inteiro
  `before..after`; três canais exatos; conteúdo mínimo do worklist, append do SYNC_LOG e campos
  agora/próximo/restante do HANDOFF;
- bite/release do retorno integrado ao `gate-selftest` consolidado;
- `handoff-select.mjs`: push multi-commit, base zero, added/modified, espaços, deduplicação,
  dispatch validado e falha inconclusiva para base inexistente;
- assinatura PHP ampliada para frontmatter ausente/malformado, defaults, Unicode, aspas, corpo
  vazio e escopo correto do HMAC;
- transporte HTTP hermético: 200, `isError`, JSON-RPC error, 401/403/422/500, JSON vazio/inválido,
  falha de rede, falha do assinador, múltiplos handoffs e não-vazamento de segredo/token;
- `_ds`: traversal direto, percent-encoded, `url()` e `@import`; bytes WOFF2; sucesso,
  idempotência, atualização, remoção de órfãos e preservação byte a byte do cache bom em falha.
- a clarificação [W] de que Officeimpresso/Superadmin e outras superfícies vivem dentro de
  `Modules/*` expôs duas cegueiras adicionais no caminho de aplicação: `prototipo-readiness`
  só enumerava a raiz core, e `detectar-telas` tratava `mockup → Page` como relação 1:1;
- `prototipo-readiness` passou a consumir `raizesDePages()` (core + módulos), preservar
  `modulo`/`arquivo` na fila e calcular scorecard pelo namespace Inertia;
- `detectar-telas` passou a ler charters/Pages modulares e a emitir todos os alvos 1:N. No
  corpus real, `superadmin-page.jsx` agora lista Dashboard, Negócios, Assinaturas e Pacotes;
  `officeimpresso-page.jsx` lista Logs/Index e Logs/Timeline.

## Validação

- 9 suítes Node relacionadas: todas verdes;
- `gate-selftest`: 38 catracas × good/bad = 76/76;
- `protocolo.config.mjs --selftest`: verde, inclusive snapshot versionado e `_ds` não rastreado;
- PHP + Bash/HTTP no WSL: três suítes verdes, sem rede real;
- `workflow-pipe-rc.test.mjs` e `reguas-workflow.test.mjs`: verdes;
- fixtures novas provaram core + Superadmin + Officeimpresso e o caso 1 mockup → 2 Pages;
- o relatório de prontidão deixou de contar 44 e passou a contar **54** telas com protótipo
  real: as 10 que estavam invisíveis moram em módulos. As 4 de Superadmin e 2 de
  Officeimpresso aparecem como `1-ciclo` por falta de scorecard, não mais como ausência;
- o detector real passou a emitir 33 alvos semânticos (antes colapsava os irmãos), 6 TSX
  diffáveis alterados, 15 mockups órfãos e 9 registrados a criar. Os órfãos continuam
  fail-closed; a correção não os maquiou;
- `selftest-registry-check --check` confirmou zero `.test.mjs` órfão, mas continuou vermelho por
  dívida preexistente e fora deste diff: `.claude/hooks/block-sonda-que-mente.mjs --selftest` sem
  invocador de workflow.

## Estado

Alterações foram publicadas na PR #6147 pela branch `codex/design-protocol-regressions`. Nenhum
check foi promovido a required. MCP de memória não estava disponível nesta sessão; foi usado o
fallback versionado do repositório.
