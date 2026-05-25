---
slug: 0113-integracao-delphi-laravel-ads-3-caminhos
number: 113
title: "Integração Delphi WR Comercial ↔ Laravel oimpresso ↔ ADs em 3 caminhos aditivos"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-09"
module: null
quarter: 2026-Q2
tags: [integracao, delphi, ads, connector, multi-system]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0021-officeimpresso-contrato-api-delphi", "0015-connector-api-gateway", "0096-arq-0001-ads"]
pii: false
review_triggers:
  - cliente Delphi for recompilado e redistribuído
  - ADs ganhar use-case que exija dado em tempo real do desktop
  - SLA de polling sync (ADR 0021) ficar insuficiente
---

# ADR 0113 — Integração Delphi WR Comercial ↔ Laravel oimpresso ↔ ADs em 3 caminhos aditivos

## Contexto

Wagner perguntou em sessão 2026-05-09: *"tenho dois domínios Delphi e Laravel. As ADs criadas no servidor são só do Laravel. Dá para unir sem perder contexto?"*

Hoje existem **2 sistemas com ciclos de vida muito diferentes**:

1. **Delphi WR Comercial** — cliente desktop em produção há 3 anos, distribuído pra ~N máquinas clientes (gráficas/printers usuárias do ecossistema oimpresso). Repo SVN em `D:/Programas/WR Comercial/`. **Não é recompilado** ([auto-mem `feedback_delphi_contrato_imutavel`](../claude/feedback_delphi_contrato_imutavel.md)) — qualquer mudança de contrato API quebra cliente em prod permanentemente. Endpoints conhecidos:
   - `POST /oauth/token` (Passport password grant)
   - `POST /connector/api/processa-dados-cliente` (response **string** `'S;...'`/`'N;...'`, não JSON)
   - `POST /connector/api/salvar-cliente`
   - `POST /connector/api/salvar-equipamento/{business_id}`
   - `GET/POST /connector/api/{tabela}/sync-get|sync-post` (sync genérico — [ADR 0021](0021-officeimpresso-contrato-api-delphi.md))

2. **Laravel oimpresso** — ERP web ([oimpresso.com](https://oimpresso.com)) com módulos Modules/* (Jana, NfeBrasil, Repair, ADs, etc), evolui rápido. **`Modules/ADS`** (Adaptive Decision System — dual-brain, ADRs ARQ-0001..0011) **existe somente no Laravel** — Delphi não conhece, não chama, não consome.

A pergunta é: **dá pra unir os 2 sistemas e fazer ADs operar sobre dados que vêm do Delphi também, sem quebrar o contrato imutável?**

A resposta é **sim**, mas exige usar caminhos *aditivos* (não-disruptivos) ao contrato existente. 3 padrões de integração foram identificados, cada um com fitness diferente para tipos de use-case.

## Decisão

Adotar **3 caminhos de integração aditivos**, escolhíveis caso-a-caso conforme o use-case do ADs:

### Caminho A — Endpoints novos no Connector API (Delphi pull voluntário)

**Padrão:** adicionar `POST /connector/api/ads/{operacao}` etc no [Connector](0015-connector-api-gateway.md) sem tocar nos 5 endpoints existentes do Delphi.

**Como funciona:**
- ADs publica resultado de decisão (recomendação, alerta, score) numa rota nova
- Cliente Delphi atual **ignora** (não chama)
- Quando Wagner recompilar Delphi (algum dia), passa a chamar o endpoint novo
- Tradicional REST request/response, sem mudança de paradigma

**Quando usar:** decisões ADs que **podem esperar pelo próximo recompile do Delphi** (anos potencialmente). Use-case típico: telemetria adicional, recomendações que aparecem em telas Delphi novas.

**Risco:** zero ao Delphi atual. Endpoint fica órfão até recompile, mas backend evolui livre.

### Caminho B — Hooks server-side (ADs lê dados do Delphi via Connector existente)

**Padrão:** ADs lê dados que o Delphi já manda pro Connector via os 5 endpoints existentes. Cada chamada do Delphi dispara um hook server-side (Laravel Listener) que consome o payload e roda lógica ADs.

**Como funciona:**
- Delphi continua chamando `/connector/api/processa-dados-cliente` (sem mudança)
- Listener `ProcessarDadosClienteListener` (server-side) escuta o evento `DadosClienteProcessados`
- Listener invoca `ADs::evaluate(payload)` → grava decisão em `ads_decisions` table
- Delphi recebe response `'S;...'` normal — não fica sabendo que ADs rodou

**Quando usar:** decisões ADs que **só dependem de dados que o Delphi já envia** (registro de licença, dados de cliente, estado de equipamento). Não exige mudança de contrato porque ADs apenas observa o tráfego.

**Risco:** baixo. Hook server-side é Laravel puro — se ADs falhar (timeout, erro de inferência), o try/catch evita quebrar a response do Connector. Telemetria + alerta interno se taxa de falha subir.

### Caminho C — ADs escreve em fila, Delphi sincroniza via polling genérico

**Padrão:** ADs gera comandos/recomendações pro Delphi (ex: "atualize cliente X com tag Y", "verifique equipamento Z"). Escreve numa tabela de fila (`ads_delphi_commands`). Delphi pega via `GET /connector/api/ads_delphi_commands/sync-get?date=...` ([ADR 0021](0021-officeimpresso-contrato-api-delphi.md)) no próximo polling.

**Como funciona:**
- ADs `Brain B` decide ação que requer execução no desktop → INSERT em `ads_delphi_commands` (campos: `id, business_id, command_type, payload_json, created_at, processed_at`)
- Delphi já faz polling sync genérico de N tabelas (ADR 0021 Geração 2). Adicionar `ads_delphi_commands` à lista de tabelas que ele sincroniza
- Quando Delphi sync-get pega comandos novos, processa localmente e marca via `POST sync-post` com `processed_at` preenchido
- ADs detecta confirmação por polling reverso ou job cron que lê `processed_at`

**Quando usar:** decisões ADs que **precisam acionar ação no desktop** (ex: revogar licença, forçar re-auth, push notification de alerta crítico). Útil quando o desktop é canal único pra atingir o user em prod.

**Risco:** médio. Requer:
- Schema da fila (`ads_delphi_commands`) — migration nova multi-tenant Tier 0 (`business_id` global scope, [ADR 0093](0093-multi-tenant-isolation-tier-0.md))
- Polling latency (Delphi sync intervalo configurável; típico 30-60s)
- Idempotência: Delphi pode sincronizar mesmo comando 2× se sync-post falhar — schema precisa de `idempotency_key`
- Retry/timeout: ADs precisa decidir o que fazer se comando ficar `processed_at IS NULL` por X horas (escalar pra HITL)

## Justificativa

### Por que não 1 caminho único?

Cada caminho serve um padrão de comunicação diferente:
- A: **request/response síncrono** (Delphi chama, recebe ADs)
- B: **observador passivo** (ADs lê tráfego sem interferir)
- C: **comando assíncrono** (ADs decide, Delphi executa quando puder)

Um único caminho forçaria adaptar use-cases que não cabem (ex: usar A pra "revogar licença" não funciona — Delphi não tá te perguntando "posso revogar?", ele só registra-se).

### Por que não recompilar o Delphi?

Auto-mem `feedback_delphi_contrato_imutavel`:
> "Delphi é código legado sem pipeline de build ativo. Mesmo se houvesse, demanda redistribuir binário pra N máquinas clientes = alto custo operacional."

Wagner explicitamente vetou recompile como dependência de qualquer feature Laravel. Caminho B (hooks server-side) e C (fila polling) não exigem recompile — os 2 caminhos primários portanto.

### Por que não API gateway novo (RabbitMQ, gRPC, etc)?

- Delphi não suporta MQ nativamente; biblioteca = recompile
- gRPC idem
- O sync genérico ADR 0021 já é uma fila implícita via tabelas — reutilizar > inventar nova infra

### Quando reabrir esta decisão

- Cliente Delphi **for recompilado e redistribuído** (ex: novo binário com biblioteca MQ embutida) → caminhos novos viram possíveis (push direto, websocket, etc)
- ADs ganhar use-case que **exija dado em tempo real do desktop** que não passa por nenhum dos 5 endpoints atuais → revisar se vale criar endpoint Connector novo
- SLA de polling Delphi (ADR 0021) ficar insuficiente (ex: ADs decisões críticas precisam <5s e polling é 60s) → considerar mudar para webhook reverso (Delphi acaba calling endpoint novo)

## Consequências

**Positivas:**
- ADs evolui livremente em Laravel sem nunca tocar no contrato Delphi
- Cada use-case escolhe o caminho que faz sentido (request/response síncrono, observação passiva, ou comando assíncrono)
- Schema multi-tenant Tier 0 preservado nos 3 caminhos (todos passam por `business_id`)
- Sem dependência crítica em recompile do Delphi
- Backward-compatible: cliente Delphi atual continua funcionando exatamente igual

**Negativas / Trade-offs:**
- 3 caminhos = 3 superfícies de manutenção (testes, observabilidade, runbooks por caminho)
- Caminho C (fila polling) tem latência inerente (30-60s) — não cabe pra decisões com SLA <10s
- Telemetria de "ADs rodou no payload do Delphi" precisa ser explícita pra auditoria (caminho B é silencioso por design)
- Se Delphi futuro mudar contrato significativamente, possivelmente os 3 caminhos ficam parcialmente obsoletos
- Caminho A fica órfão até recompile — investimento que demora pra render

**Riscos mitigados:**
- **Quebra do contrato Delphi:** ZERO em todos os 3 caminhos por design (são aditivos)
- **Vazamento cross-tenant:** todos os 3 caminhos passam por filtro `business_id` (caminho B no Listener, caminho C na fila, caminho A no controller novo)
- **Falha do ADs travando Delphi:** caminho B usa try/catch + fallback noop; resposta do Connector nunca espera ADs concluir
- **Comandos perdidos (caminho C):** schema com `idempotency_key` + retry com escalada pra HITL após timeout

## Referências

- [ADR 0015 — Connector API Gateway](0015-connector-api-gateway.md) — base do canal Delphi↔Laravel
- [ADR 0021 — Contrato API Delphi (Geração 2 sync genérico)](0021-officeimpresso-contrato-api-delphi.md) — habilita Caminho C
- [auto-mem `feedback_delphi_contrato_imutavel`](../claude/feedback_delphi_contrato_imutavel.md) — restrição mãe (não recompilar)
- [auto-mem `reference_delphi_wr_comercial`](../claude/reference_delphi_wr_comercial.md) — setup técnico do Delphi
- [Modules/ADS adr/arq/ARQ-0001..ARQ-0011](../requisitos/ADS/adr/arq/) — arquitetura dual-brain ADs (paper, decision flow, escalation, etc)
- [ADR 0093 — Multi-tenant isolation Tier 0](0093-multi-tenant-isolation-tier-0.md) — `business_id` global scope obrigatório nos 3 caminhos

## Pendências de implementação

Esta ADR formaliza os 3 caminhos. Implementação concreta vira RUNBOOK + tasks separadas:

- [ ] **Caminho A — RUNBOOK** `memory/requisitos/Connector/RUNBOOK-novos-endpoints-aditivos.md` com pattern pra adicionar novas rotas sem quebrar Delphi
- [ ] **Caminho B — primeiro Listener piloto** (sugestão: `ProcessarDadosClienteListener` que registra observação ADs sobre cada licença ativada) — viaja em PR separado com testes
- [ ] **Caminho C — schema `ads_delphi_commands` + integração no sync genérico** (multi-tenant Tier 0, idempotency_key obrigatório) — exige migration + ADR de schema dedicada se necessário
- [ ] **Telemetria caminho B** — pra cada chamada Connector que aciona hook ADs, log estruturado com `business_id` + `decision_id` + `latency_ms`
- [ ] **Smoke test integração Delphi-Laravel** — cenário fim-a-fim: Delphi `processa-dados-cliente` → Laravel response S;... → ADs grava decisão → smoke valida que Delphi continuou funcionando

## Use-cases imediatos (matching com caminhos)

| Use-case ADs | Caminho recomendado | Razão |
|---|---|---|
| Recomendar ação preventiva ao operador na tela do Delphi | A (futuro) | Precisa que Delphi consulte; espera recompile |
| Avaliar score de confiança de licença ativada (real-time) | B | Hook no `processa-dados-cliente` — passivo |
| Detectar padrão anômalo em `salvar-equipamento` (HD novo, IP suspeito) | B | Mesmo: observação passiva |
| Revogar licença em massa (ex: cliente inadimplente) | C | Comando assíncrono — Delphi pega no próximo sync |
| Push de alerta crítico ao desktop (ex: Sefaz fora do ar) | C | Fila + sync; latência aceitável |
| Sincronização cross-tabela quando ADs decide regra nova | C | Reuso do sync genérico ADR 0021 |
