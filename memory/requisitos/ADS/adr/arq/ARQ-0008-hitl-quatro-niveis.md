---
id: requisitos-ads-adr-arq-arq-0008-hitl-quatro-niveis
slug: ARQ-0008-hitl-quatro-niveis
title: "Human-in-the-Loop — 4 níveis de intervenção humana adaptativa"
status: accepted
date: 2026-05-03
deciders: [Wagner]
category: arq
module: ADS
relates_to: [ARQ-0003, ARQ-0007, ARQ-0010]
---

# ARQ-0008 — Human-in-the-Loop: 4 níveis adaptativos

## Contexto

Revisão humana não é binária. "Revisar ou não revisar" é a abstração mais pobre possível.

O custo real de revisão humana tem duas dimensões:
1. Tempo de atenção de Wagner
2. Velocidade de execução do sistema

Um sistema que pede revisão para tudo desperdiça o tempo de Wagner e elimina o ROI do ADS.
Um sistema que não pede revisão para nada eventualmente comete erros irreversíveis.

A solução é graduar a intervenção humana por intensidade, com critérios objetivos para cada nível.

## Decisão

### Definição dos 4 níveis

**HiTL-0 — Autônomo**

```
Wagner não é notificado. Agente age e registra em Decision Memory.
Wagner pode auditar depois via /copiloto/admin/decisoes histórico.
```

Critérios obrigatórios:
- Risk score < 0.2
- Confidence score > 0.85
- Policy = ALLOW_BRAIN_A
- Arquivo não está em mutex lock

Exemplos: fix frontmatter ADR, atualizar lang file, reindexar MCP sync.

---

**HiTL-1 — Notificação**

```
Wagner vê no feed do /copiloto/admin/decisoes (badge de contador).
Não precisa agir. Pode ignorar. Pode cancelar se viu algo errado.
Agente já começou a executar.
```

Critérios:
- Risk score 0.2–0.4
- Confidence score > 0.7
- Policy = ALLOW_BRAIN_A ou REQUIRE_BRAIN_B com Brain B aprovado

Janela de cancelamento: 10 minutos após notificação. Depois disso, execução confirmada.

Exemplos: Brain B corrigiu bug em Service layer de baixo impacto, test ajustado.

---

**HiTL-2 — Revisão assistida**

```
Agente PARA antes de executar.
Brain B prepara: resumo do que vai fazer, riscos identificados, recomendação.
Wagner lê o resumo (2 min) e clica [Aprovar] [Modificar instrução] [Rejeitar].
```

Critérios:
- Risk score 0.4–0.7, OU
- Confidence score 0.5–0.7, OU
- Policy = REQUIRE_BRAIN_B

**Estrutura do resumo Brain B:**
```
O QUE: [ação em 1 linha]
ONDE: [arquivo(s), linha(s)]
POR QUÊ: [contexto do problema detectado]
RISCO IDENTIFICADO: [o que pode dar errado]
RECOMENDAÇÃO: [Brain B recomenda executar / sugestão de ajuste]
REVERSÃO: [como desfazer se necessário]
```

SLA esperado: Wagner responde em até 4 horas. Após 24h sem resposta, tarefa expira e
reentra na fila com flag `sla_expirado = true` visível no feed.

Exemplos: refactor de Service com impacto moderado, nova migration de coluna.

---

**HiTL-3 — Decisão humana**

```
Agente não age. Wagner decide tudo.
IA só apresenta contexto e opções (não recomenda).
```

Critérios:
- Risk score > 0.7, OU
- Policy = BLOCK_ALWAYS ou REQUIRE_HUMAN_REVIEW, OU
- Meta Learning L3 propondo mudança estrutural

**Estrutura da apresentação:**
```
SITUAÇÃO: [o que o sistema detectou]
OPÇÃO A: [ação mais conservadora] — implicações
OPÇÃO B: [ação mais agressiva] — implicações
OPÇÃO C: não fazer nada agora — implicações
DADOS RELEVANTES: [ADRs, histórico, código atual]
```

Deliberadamente sem recomendação — Wagner decide sem viés do agente.

---

### Progressão de nível ao longo do tempo

O nível de HiTL para um par `(domínio, tipo)` começa em HiTL-2 e migra conforme o histórico:

```
HiTL-2 (padrão) 
  → HiTL-1 quando: ≥5 aprovações consecutivas sem modificação
  → HiTL-0 quando: ≥10 aprovações consecutivas sem modificação + risk < 0.3
  → volta para HiTL-2 quando: 1 rejeição ou modificação significativa por Wagner
  → vai para HiTL-3 quando: Policy muda para REQUIRE_HUMAN_REVIEW
```

Esta progressão é registrada em `mcp_confidence_scores` como campo `hitl_level` e é
visualizável na tela `/copiloto/admin/decisoes` por domínio.

### O que Wagner vê na interface por nível

| Nível | Onde aparece | Urgência visual | Ação necessária |
|---|---|---|---|
| HiTL-0 | Aba "Histórico" | Cinza | Nenhuma |
| HiTL-1 | Badge no menu | Azul | Opcional (cancelar em 10min) |
| HiTL-2 | Inbox principal | Amarelo | Sim — aprovar ou rejeitar |
| HiTL-3 | Inbox prioritário | Vermelho | Sim — decidir entre opções |

## Consequências

**Positivas:**
- Wagner passa de revisor passivo para supervisor ativo apenas quando importa
- HiTL-2 com resumo Brain B reduz tempo médio de revisão de ~15min para ~2min
- Progressão automática elimina a necessidade de Wagner configurar manualmente o que é rotineiro

**Negativas:**
- HiTL-1 com janela de 10 minutos pode causar execução indesejada se Wagner não viu a notificação.
  Mitigação: configuração por domínio de "janela de cancelamento" (padrão 10min, pode ser 0=off
  para domínios críticos que Wagner prefere revisar sempre)
