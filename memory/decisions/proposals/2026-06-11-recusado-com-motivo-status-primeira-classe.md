---
title: "Recusado com motivo — status de primeira classe pra pedidos negados (rastreabilidade do NÃO)"
status: proposed
date: "2026-06-11"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
origem: "Wagner 2026-06-11: 'tem que ter rastreabilidade e segurança do que já foi aprovado e RECUSADO... conciliar pedidos da equipe interna e de clientes sem inventar ou se perder com pedidos indevidos' → análise apontou: o NÃO é a única decisão sem registro canônico único"
---

# Recusado com motivo — status de primeira classe pra pedidos negados

## Contexto

O ciclo de vida de pedidos cobre bem o caminho do SIM: ADR 0105 filtra entrada (só com
sinal de cliente pagante ou métrica), proposals aguardam aceite textual do Wagner, ADRs
aceitas são append-only, `✅` exige prova de teste (G-7), Zelador reconcilia diariamente.

O caminho do **NÃO está espalhado e não-consultável**: recusa vira Non-Goal de charter,
proposal que nunca foi aceita (sem distinção de "aguardando" vs "negada"), comentário de
PR, ou review visual reprovado em chat. Consequência prática: quando um cliente (ou o
time, com Felipe/Maiara/Eliana/Luiz entrando via MCP) pedir de novo algo já recusado, o
sistema não responde *"recusado em DATA, por MOTIVO, por QUEM — ver link"* — e a
re-discussão (ou pior, a implementação indevida) recomeça do zero. É o anti-requisito do
Wagner: "sem inventar ou se perder com pedidos indevidos internos e de clientes".

O vocabulário ADR é fonte única em `scripts/memory-schemas/adr.schema.json` (ADR 0271) —
o enum `status` hoje: `rascunho · proposto · aceito · deprecated · superseded`. Não há
estado terminal pro NÃO.

## Decisão proposta

### 1. `recusado` entra no enum de `status` (fonte única)

`adr.schema.json` → `status: [rascunho, proposto, aceito, recusado, deprecated, superseded]`
+ espelho gerado `_schema.json`. Vale pra ADRs, proposals e feature-wishes (kind:
`feature-wish` da ADR 0105 ganha desfecho rastreável).

### 2. Recusa exige 3 campos (sem eles, gate de schema falha)

```yaml
status: recusado
rejected_at: "YYYY-MM-DD"
rejected_via: "Wagner DATA no chat: '<palavras textuais>'"   # mesmo rito do accepted_via
rejected_reason: "1-3 frases: por que NÃO — e o que faria reabrir (sinal que faltou)"
```

`rejected_reason` carrega o **critério de reabertura** — recusa não é eterna, é
condicional ao sinal (coerente com ADR 0105: "hipótese sem sinal" pode ganhar sinal).

### 3. Recusado é terminal-mas-visível

- Índice gerado (`adr-index-generate.mjs`) ganha seção **"Recusadas"** (hoje só ativos/
  supersedidos) — `decisions-search` passa a responder "por que não temos X?".
- Append-only vale igual: reabrir = ADR/proposal NOVA referenciando a recusada
  (`related`), nunca editar a antiga.
- Pedido novo que repete recusa: agente responde com o link + `rejected_reason` ANTES de
  escalar pra Wagner (corta re-discussão na entrada — skill `wagner-understand` e triage
  MCP consultam).

### 4. O que NÃO muda

- Non-Goals de charter continuam (recusa de escopo POR TELA fica na tela).
- Review visual reprovado continua no PR (é iteração, não decisão de produto).
- Esta proposal NÃO migra recusas históricas — só o fluxo daqui pra frente
  (retro-catalogação é passada opcional do Zelador, 1 emenda).

## Implementação (após aceite — 1 PR, ~1h)

1. enum + 3 campos condicionais no `adr.schema.json` (+ espelho `_schema.json` gerado)
2. seção "Recusadas" no `adr-index-generate.mjs`
3. nota no `.claude/rules/` ou skill `wagner-request-refiner`: pedido repetido → checar recusadas primeiro

## Consequências

- **Positiva:** o NÃO vira ativo consultável — proteção contra re-litígio e contra
  implementação indevida por agente/time que não viveu a decisão original.
- **Positiva:** recusa condicional documenta o que destravaria (vira funil de sinal, não
  cemitério).
- **Negativa:** mais um rito (3 campos) — mitigado: só se aplica quando Wagner JÁ
  decidiu negar; o custo é copiar a frase dele, igual `accepted_via`.

### Aprovação

Wagner aprova → vira ADR numerada + PR de implementação.
