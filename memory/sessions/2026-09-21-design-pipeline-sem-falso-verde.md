---
date: "2026-09-21"
hour: "14:31 BRT"
topic: "Importação do protótipo sem falso verde de CI, mapa morto ou baseline de drift"
authors: [C]
outcomes:
  - "validated passou a exigir smoke de produção; CI e staging têm estados próprios"
  - "13 alarmes permanentes do mapa ModuleGrades aposentado foram eliminados sem waiver"
  - "baseline do ds-mirror-drift foi removido e dez workflows de design ganharam synchronize"
related_adrs:
  - 0384-design-sync-recibos-executaveis-por-tela
  - 0390-emenda-0384-smoke-em-ambiente-controlado
  - 0409-zero-baseline-de-tolerancia-conformidade-absoluta
  - 0410-ratificacao-zero-baseline-no-funil-design
---

# Importação do protótipo sem falso verde

## TL;DR

O funil passou a distinguir smoke de CI, staging e produção, eliminou o baseline de drift
do DS e tornou dois gates antes mascarados observáveis. A promoção para `validated`
permanece pendente até existir recibo do ambiente de produção para o SHA implantado.

## Contexto

Wagner pediu a conferência do funil Bubble/protótipo até produção, alertou que baseline já
causou prejuízo e autorizou corrigir todos os pontos encontrados. A auditoria mediu duas
afirmações falsas: três telas eram chamadas de `validated` apenas com screenshot de CI, e um
mapa de tela deletada mantinha 13 falhas permanentes mascaradas por `continue-on-error`.

## Mudanças

- `validated` passou a exigir recibo `host: producao`; CI e staging produzem `smoked-ci` e
  `smoked-staging`.
- Cada tela passou a publicar `applicationEvidence.proofChain` com hashes do bundle, fonte,
  mapa, alvo, saída de teste, deploy e screenshot.
- O relatório vivo foi recalculado: 164 pares, 3 `smoked-ci`, 0 `validated`, 0 bloqueadas.
- O mapa ModuleGrades foi preservado como `.map.retired.json`; o gap declara `map_json: n/a`.
  O strict passou de 13 problemas para zero, sem baseline nem waiver.
- O step real de `design-code-map-check --strict` deixou de mascarar falhas.
- Dez workflows da cadeia de design passaram a reexecutar em `pull_request.synchronize`.
- `ds-mirror-drift` deixou de ler/gravar baseline. `--enforce` exige zero divergência e as
  flags antigas de baseline saem 2.
- O medidor existente `design-gate-bites` passou a executar `ds-mirror-drift --enforce` e
  `design-code-map-check --strict`; nenhuma régua paralela foi criada.
- A ADR 0410 registrou, de forma aditiva, o aceite da proposta 0409 e substituiu parcialmente
  o ponto da ADR 0390 em que CI concedia `validated`; nenhuma ADR anterior foi editada.

## Provas

- `bundle-transaction.test.mjs`, `status.test.mjs` e `smoke-consumir --selftest`: verdes;
- `design-code-map-check --check --strict`: 66/66 mapas ativos, zero drift;
- `ds-mirror-drift --enforce`: zero divergência; selftest prova bite, não-medição e recusa
  de baseline;
- `design-memory-gate.test.mjs`: os dez workflows têm `synchronize` e o map-check não tem
  `continue-on-error`;
- `design-gate-bites --scan --dry-run`: zero mordidas e zero gates pulados;
- `gate-selftest.mjs`: 82/82 controles, 41 catracas;
- `maquinas-inventario --check`: 606 máquinas, zero faltando e zero ghost;
- `git diff --check`: verde.

PHP/Pest/PHPStan não rodaram localmente, conforme ADR 0062; este lote altera apenas Node,
workflows, JSON e documentação.
