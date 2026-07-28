---
id: requisitos-jana-runbook-jana-advisor-proativo
title: "Jana Advisor (Modo Consultor) — Metade B: próxima-melhor-pergunta proativa"
module: Jana
owner: W
status: rascunho
last_validated: "2026-06-02"
related_adrs:
  - 0245-jana-advisor-modo-consultor-clarify
preconditions:
  - "copiloto.advisor_questions.enabled=true (default OFF — via config runtime)"
  - "Brief diário funcionando (BriefDiarioService + BriefDiarioChatTrigger)"
steps:
  - "Ligar a flag (config runtime) em homolog"
  - "Pedir o brief no chat da Jana (/brief) e ver a seção 'Perguntas que você deveria fazer agora'"
  - "Conferir por persona + honestidade (persona sem sinal é omitida)"
  - "Medir advisor_questions_event no log copiloto-ai"
---

# RUNBOOK — Jana Advisor (Modo Consultor) · Metade B: próxima-melhor-pergunta proativa

> **ADR 0245** · Metade B do Advisor. Default OFF. Estende o **brief diário** (não recria).

## O que é

O salto de *"ferramenta que responde"* → *"consultor que pauta"*. Dado o **estado real do
negócio** (snapshot do `BriefDiarioService`), a Jana surfa — **por persona** — as perguntas que
[W]/a equipe **deveriam estar fazendo agora**, já com a **resposta** pronta. Critério: **maior
valor destravado / maior ganho de informação pro momento** (Active Task Disambiguation, ICLR 2025).

## Como funciona

`BriefDiarioChatTrigger::gerar()` → (após o brief) → `ProximaPerguntaService::gerarBloco()`:

1. **Snapshot** do estado real (reusa `BriefDiarioService`: vendas/inadimplência/tickets/nfe/oportunidades).
2. **Resumo compacto** (números + nomes-chave — os mesmos que o brief já mostra).
3. **`ProximaPerguntaAgent`** (frontier, structured output) gera, por persona, as perguntas de
   maior valor — já respondidas.
4. **Bloco markdown** `## 🔮 Perguntas que você deveria fazer agora` anexado ao brief.

Personas (config `copiloto.advisor_questions.personas`): **larissa** (balcão/velocidade) ·
**eliana** (fiscal/financeiro) · **tecnico** (operação/oficina) · **gestor** (visão geral).
Cada uma recebe a pergunta do **trabalho dela**.

Garantias:
- **Default-OFF** → o brief sai idêntico ao atual.
- **Fail-open** → erro/snapshot-vazio → brief sem o bloco (nunca quebra).
- **Honestidade** → persona sem sinal de alto valor é **omitida**; se nenhuma tem, sem bloco
  (não inventa pergunta genérica).
- Roda **1×/dia por business** (junto do brief) → custo frontier trivial.

## Como ligar

```php
config(['copiloto.advisor_questions.enabled' => true]);      // default false
// opcionais:
config(['copiloto.advisor_questions.model' => 'gpt-4o']);    // frontier (1×/dia)
config(['copiloto.advisor_questions.max_por_persona' => 2]);
```

As personas e o `model` vivem no bloco `advisor_questions` de `Modules/Jana/Config/config.php`
(valores diretos — sem `env()`, mesma razão do `peso_real`/Larastan).

## Como medir

Log `copiloto-ai` → `advisor_questions_event` (personas_com_pergunta, total_perguntas):

```bash
tail -f storage/logs/copiloto-ai.log | grep advisor_questions_event
```

Sinal de valor real (pendência, igual Metade A): **pergunta→ação** precisa de hook no frontend
(registrar se [W]/persona agiu sobre a pergunta surfada).

## Testes

- `Modules/Jana/Tests/Feature/Ai/Advisor/ProximaPerguntaServiceTest.php` (6: flag-off, snapshot
  vazio, happy path, honestidade, max_por_persona, fail-open).
- `Modules/Jana/Tests/Feature/Ai/Advisor/ProximaPerguntaAgentTest.php` (3: routing, instructions, grounding).
