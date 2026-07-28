---
id: requisitos-ads-adr-arq-arq-0003-decision-router-algoritmo
slug: ARQ-0003-decision-router-algoritmo
title: "Decision Router — Algoritmo de roteamento e hierarquia de precedência"
status: accepted
date: 2026-05-03
deciders: [Wagner]
category: arq
module: ADS
relates_to: [ARQ-0001, ARQ-0002, ARQ-0004, ARQ-0005, ARQ-0006]
---

# ARQ-0003 — Decision Router: algoritmo e hierarquia de precedência

## Contexto

Sem um roteador central, cada agente decide sozinho se deve executar, escalar ou bloquear.
Isso cria três problemas:
1. Race conditions: dois agentes recebem o mesmo evento e agem em paralelo
2. Falta de auditoria: não há registro de "quem decidiu rotear para onde"
3. Inconsistência: Brain A pode agir em algo que Policy Engine proibiria

## Decisão

O Decision Router é código determinístico (sem LLM) que implementa o algoritmo abaixo.
Ele é a única entrada de eventos no ADS. Nenhum agente age sem passar por ele.

### Algoritmo (pseudocódigo canônico)

```
função rotear(evento):

  # Passo 1: Policy Engine — veto absoluto
  regra = PolicyEngine.verificar(evento)
  se regra == BLOCK_ALWAYS:
    gravar Decision Memory (bloqueado por policy)
    retornar BLOCKED

  # Passo 2: Serialização — evita race condition
  se ArquivoMutex.ocupado(evento.arquivos_afetados):
    enfileirar(evento, prioridade=normal)
    retornar QUEUED

  # Passo 3: Calcular scores
  risco      = RiskEngine.calcular(evento)
  confiança  = ConfidenceEngine.consultar(evento.dominio, evento.tipo)

  # Passo 4: Roteamento por threshold
  se risco < 0.3 E confiança > 0.7 E regra == ALLOW_BRAIN_A:
    gravar Decision Memory (roteado Brain A, HiTL-0)
    retornar BRAIN_A

  se risco < 0.7 E regra != REQUIRE_REVIEW:
    gravar Decision Memory (roteado Brain B, HiTL-1 ou HiTL-2)
    retornar BRAIN_B

  # Passo 5: Escalação humana
  gravar Decision Memory (escalado Wagner, HiTL-3)
  criar task pendente Wagner via TaskRegistry
  retornar PENDING_HUMAN
```

### Hierarquia de precedência (resolve conflitos)

```
1. Policy Engine          → veto absoluto, código hardcoded no git
2. Veto humano explícito  → Wagner reject gravado em Decision Memory
3. Brain B (Claude API)   → override Brain A se os dois receberam mesmo evento
4. Thresholds calibrados  → aprovados por Wagner via Meta Learning L3
5. Brain A (Ollama)       → baseline, menor autoridade
```

Quando dois sistemas chegam a conclusões diferentes para o mesmo evento, a hierarquia acima
define quem vence. O perdedor tem seu raciocínio gravado em Decision Memory para auditoria.

### Mutex por arquivo

Para evitar que dois agentes modifiquem o mesmo arquivo simultaneamente:

```
mcp_file_locks (file_path, locked_by, locked_at, expires_at)
```

- Lock adquirido ao iniciar execução, liberado ao commitar ou falhar
- Expiração: 30 minutos (evita deadlock por crash)
- Segundo agente enfileirado, não descartado

### Thresholds configuráveis

Os thresholds `0.3` (Brain A gate) e `0.7` (Brain B gate) são o estado inicial.
O Learning Loop L3 pode propor ajustes, mas somente após Wagner aprovar via task pendente.
Os valores atuais são gravados em `mcp_decision_thresholds` com `approved_by` e `approved_at`.

### Endpoint

```
POST /api/ads/route
Body: { event_type, domain, files_affected, metadata }
Auth: Bearer ADS_API_KEY (interno, não exposto)
Response: { destination, risk_score, confidence_score, policy_applied, decision_id }
```

## Consequências

**Positivas:**
- Ponto único de entrada garante auditoria 100% dos eventos
- Algoritmo determinístico é testável com testes unitários sem LLM
- Mutex previne corrupção por concorrência

**Negativas:**
- Ponto único de falha: se o Router cair, nenhum agente age. Mitigação: health check
  com fallback para "tudo vira task pendente Wagner" em modo degradado
- Fila pode crescer durante alta atividade. Mitigação: prioridade por domínio crítico
