---
slug: 0410-ratificacao-zero-baseline-no-funil-design
number: 410
title: "Ratificação do zero baseline no funil de design e validação exclusiva em produção"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-21"
module: governance
tags: [governanca, design, baseline, ci, smoke, producao, prova]
supersedes: []
supersedes_partially:
  - 0390-emenda-0384-smoke-em-ambiente-controlado
related:
  - 0384-design-sync-recibos-executaveis-por-tela
  - 0409-zero-baseline-de-tolerancia-conformidade-absoluta
pii: false
---

# ADR 0410 — Ratificação do zero baseline no funil de design

## Contexto

A ADR 0409 propôs eliminar baselines de tolerância e devolver significado absoluto aos
gates. Na auditoria do caminho protótipo até produção, três telas Fiscal apareciam como
`validated` embora a única evidência fosse um smoke executado em CI. Um mapa de tela já
apagada também produzia 13 erros permanentes, depois neutralizados por `continue-on-error`,
e o drift do espelho do Design System aceitava divergências registradas num baseline.

Wagner confirmou a decisão em 2026-09-21 e autorizou aplicar todos os saneamentos do funil.
Como ADR canônica é append-only, esta ratificação registra o aceite sem alterar a ADR 0409
nem reescrever a ADR 0390.

## Decisão

1. O funil de design não usa baseline de tolerância para conceder conformidade. O
   `ds-mirror-drift --enforce` exige divergência zero; as opções antigas de criar ou ler
   baseline são recusadas.
2. Smoke de CI e de staging gera, respectivamente, `smoked-ci` e `smoked-staging`. Apenas
   recibo com `host: producao` e hashes atuais pode gerar `validated`.
3. Cada tela carrega uma cadeia de prova com hashes de bundle, fonte, mapa, alvo, saídas de
   teste, deploy e screenshots. Ausência de prova de produção permanece visível.
4. O `design-code-map-check --check --strict` reprova a lane. Mapa histórico sem alvo vivo
   sai do conjunto ativo e permanece identificado como aposentado, sem waiver.
5. Todos os workflows do funil que validam um PR reexecutam em `pull_request.synchronize`,
   para que um novo SHA não herde o resultado do anterior.
6. Os gates entram no medidor de mordidas já existente; não nasce um segundo painel.

Esta decisão substitui somente o trecho da ADR 0390 que permitia a smoke de ambiente
controlado conceder `validated`. Os recibos de CI e staging continuam válidos como evidência,
mas não representam produção.

## Consequências

- O estado inicial medido passou de três telas chamadas de validadas para três
  `smoked-ci` e zero `validated`.
- Um verde do funil passa a afirmar conformidade no escopo executado, sem desconto por erro
  histórico conhecido.
- A promoção para produção depende de evidência do próprio ambiente implantado e do SHA
  correspondente.
- Alterações posteriores no PR disparam nova execução dos validadores do funil.

## Evidência de implementação

Na implementação desta decisão, o mapa ModuleGrades aposentado deixou o conjunto ativo e o
strict passou de 13 acusações permanentes para zero. O baseline de `ds-mirror-drift` foi
apagado, dez workflows receberam `synchronize`, a cadeia de prova passou a ser emitida por
tela e os dois gates foram incorporados ao `design-gate-bites`. Os selftests, o inventário
de máquinas e os checks de schema validaram a mudança antes da publicação.
