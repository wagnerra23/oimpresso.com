---
id: requisitos-ads-adr-arq-arq-0010-governance-conflito-hierarquia
slug: ARQ-0010-governance-conflito-hierarquia
title: "Governance — Hierarquia de autoridade e resolução de conflitos entre agentes"
status: accepted
date: 2026-05-03
deciders: [Wagner]
category: arq
module: ADS
relates_to: [ARQ-0001, ARQ-0003, ARQ-0006, ARQ-0008]
---

# ARQ-0010 — Governance: hierarquia de autoridade e resolução de conflitos

## Contexto

Um sistema multi-agente com aprendizado contínuo cria, inevitavelmente, situações onde dois
ou mais sistemas chegam a conclusões contraditórias para o mesmo evento. Sem uma hierarquia
formal, o sistema mais persistente (ou o último a escrever) vence — o que pode resultar em
comportamento não intencional e difícil de auditar.

Este ADR define quem tem autoridade sobre quem, e como cada tipo de conflito é resolvido.

## Decisão

### Hierarquia de autoridade (imutável)

```
Nível 1: Policy Engine          — veto absoluto, código no git
Nível 2: Veto humano explícito  — Wagner reject gravado em Decision Memory
Nível 3: Brain B (Claude API)   — override Brain A no mesmo evento
Nível 4: Learning Loop L3       — thresholds aprovados por Wagner
Nível 5: Brain A (Ollama)       — baseline, menor autoridade
```

A hierarquia é unidirecional. Um nível inferior nunca pode override um nível superior.
Um nível superior nunca precisa justificar para um nível inferior — só para Wagner e para
o registro em Decision Memory.

### Cenários de conflito e resolução

**Cenário 1: Brain A diz "low risk" mas Policy diz "BLOCK_ALWAYS"**
```
Resolução: Policy vence imediatamente.
Registro: Decision Memory com destination=blocked, policy_applied=BLOCK_ALWAYS.
Brain A não é notificado do motivo — não há loop de feedback que possa contornar.
```

**Cenário 2: Brain B aprova execução mas Wagner rejeita**
```
Resolução: Veto humano vence. Execução para.
Registro: outcome=wagner_rejected, resolved_by=wagner.
Confidence Engine penaliza Brain B para esse (domínio × tipo): -2.0.
Se Wagner forneceu motivo: motivo vai para mcp_decision_patterns como aprendizado.
```

**Cenário 3: Brain A e Brain B recebem o mesmo evento em paralelo (race)**
```
Resolução: Decision Router usa mutex. Primeiro a adquirir lock processa.
O segundo recebe QUEUED e aguarda o primeiro terminar.
Se o primeiro foi Brain A e conclui com sucesso, Brain B não é acionado.
Se o primeiro foi Brain A e falhou, Brain B é acionado automaticamente (escalação).
```

**Cenário 4: Meta Learning L3 propõe baixar threshold, Policy tem a regra como BLOCK**
```
Resolução: Policy vence.
L3 pode propor, Wagner pode aceitar, mas a mudança exige alterar PolicyEngine.php via PR.
L3 não pode contornar Policy ajustando apenas mcp_decision_thresholds.
```

**Cenário 5: Confidence History diz 0.90 para (NFSe, service_layer_refactor) mas ADR
           NFSe-ARQ-0003 diz "toda mudança em lógica fiscal exige Brain B"**
```
Resolução: ADR formalizada em Policy (REQUIRE_BRAIN_B) vence sobre Confidence.
Confidence pode reduzir HiTL de 2 para 1 (notificação em vez de revisão ativa),
mas Brain B ainda é obrigatório para análise — não é removido.
```

**Cenário 6: Dois agentes criam tasks duplicadas para o mesmo problema**
```
Resolução: TaskRegistry detecta por hash do evento_type + files_affected + created_at window.
Task duplicada dentro de 1h do mesmo (event_type, domain) é descartada silenciosamente.
Registro: mcp_task_events com event=duplicate_suppressed.
```

**Cenário 7: Wagner aprova HiTL-2, mas durante execução Brain A detecta mudança de contexto
            que eleva o risco (outro commit modificou os mesmos arquivos)**
```
Resolução: Brain A pausa execução, escala para HiTL-3.
Wagner recebe notificação: "Contexto mudou desde sua aprovação. Risco subiu de 0.45 para 0.71.
Confirma execução?" com diff do novo contexto.
```

### Conflito entre ADRs

ADRs são fonte da verdade humana. Quando dois ADRs contradizem entre si:

```
Regra: ADR mais recente vence sobre ADR mais antiga no mesmo escopo.
Exceção: ADR com status=superseded não vence nunca — está explicitamente aposentada.
Edge case: dois ADRs do mesmo módulo com datas próximas contradizendo → task pendente Wagner
           com os dois ADRs listados para ele resolver manualmente.
```

O campo `relates_to` em ADRs deve sempre listar ADRs que o novo ADR modifica ou supersede.

### Conflito entre aprendizado e regras humanas

O sistema pode aprender que "Wagner sempre aprova edições de Service layer no Financeiro".
Isso não significa que a regra REQUIRE_BRAIN_B para `service_layer_refactor` pode ser removida.

**Princípio:** aprendizado calibra intensidade (qual nível HiTL, qual brain), não permissão.
A permissão de existência de uma regra é sempre humana.

```
Aprendizado pode:
  - Mudar HiTL-2 → HiTL-1 para um domínio confiável
  - Elevar gate de Brain A de 0.3 → 0.4 para um tipo muito calibrado
  - Sugerir remoção de regra REQUIRE_BRAIN_B para tipo com 50+ sucessos

Aprendizado nunca pode:
  - Remover uma regra BLOCK_ALWAYS
  - Ignorar veto humano anterior para o mesmo tipo de evento
  - Reduzir o peso de modificação humana em Decision Memory
```

### Observabilidade de conflitos

Toda resolução de conflito é registrada em `mcp_dual_brain_decisions` com campo
`conflict_type` (nullable) preenchido. Isso permite:

```sql
SELECT conflict_type, COUNT(*), outcome
FROM mcp_dual_brain_decisions
WHERE conflict_type IS NOT NULL
GROUP BY conflict_type, outcome
```

Wagner pode ver no dashboard `/copiloto/admin/decisoes` aba "Conflitos" quantas vezes
cada tipo de conflito ocorreu e como foi resolvido.

## Consequências

**Positivas:**
- Comportamento previsível: dado um evento e o estado atual dos engines, é possível prever
  exatamente qual agente vai agir e com qual autoridade
- Auditoria completa: todo conflito gravado com resolução e motivo
- Sem "winner by default" silencioso: empate sempre escala para Wagner

**Negativas:**
- Hierarquia rígida pode ser frustrante quando Wagner quer dar mais autonomia a Brain A
  em domínio específico. Mitigação: a progressão HiTL é o mecanismo correto para isso —
  não é necessário alterar a hierarquia, só calibrar os thresholds
