---
id: requisitos-ads-adr-arq-arq-0005-confidence-engine
slug: ARQ-0005-confidence-engine
title: "Confidence Engine — Fórmula, inicialização e peso de modificação humana"
status: accepted
date: 2026-05-03
deciders: [Wagner]
category: arq
module: ADS
relates_to: [ARQ-0003, ARQ-0004, ARQ-0007]
---

# ARQ-0005 — Confidence Engine: como mede e como calibra

## Contexto

O Risk Engine mede o perigo da ação. O Confidence Engine mede o quão confiável é o agente
para aquele tipo específico de ação naquele domínio.

São complementares: uma ação de baixo risco executada por um agente com histórico ruim naquele
domínio ainda deve escalar. Uma ação de risco médio executada por um agente com 95% de acertos
naquele domínio pode não precisar de revisão humana.

## Decisão

### Dimensões de confiança

Confiança não é global — é específica por `(domínio × tipo_tarefa)`:

```
confiança[domínio][tipo] = f(outcomes_passados, similaridade_contexto, estabilidade)
```

Exemplos de pares `(domínio, tipo)`:
- `(Financeiro, service_layer_refactor)` → pode ter 0.90 de confiança
- `(NFSe, db_schema_change)` → pode ter 0.40 (domínio novo, poucas execuções)
- `(Copiloto, lang_file_pt_br)` → 0.95 (trivial, muitas execuções)

### Fórmula canônica

```
confiança = média_ponderada(outcomes) × similaridade_contexto × fator_estabilidade
```

**média_ponderada(outcomes):**
```
Pesos por tipo de outcome:
  success              → +1.0
  fail                 → -1.5  (penalidade maior por assimetria de custo)
  wagner_modified      → -0.5  (Wagner aceitou mas ajustou — sinal de ruído)
  wagner_rejected      → -2.0
  cancelled            → 0.0   (neutro)

Janela: últimas 20 execuções do par (domínio × tipo)
Decaimento temporal: execuções >90 dias têm peso × 0.5
```

**similaridade_contexto:**
```
Quão parecido o evento atual é com os eventos históricos bem-sucedidos?
Calculado por Brain A com embedding simples (cosine similarity dos metadados)
Range: 0.5–1.0 (nunca penaliza muito por contexto diferente, só atenua)
```

**fator_estabilidade:**
```
Se os últimos 5 outcomes são consistentes (todos success ou todos fail) → 1.0
Se alternando success/fail nos últimos 5 → 0.7 (contexto instável)
```

### Inicialização

Todo par `(domínio, tipo)` novo começa em `confiança = 0.5`.
Isso garante que novos domínios não são tratados como confiáveis nem como incompetentes.
Com 0.5, o Decision Router roteia para Brain B (abaixo do gate de Brain A que é 0.7).

### Peso especial de modificação humana

Quando Wagner modifica a saída de um agente, o sinal tem peso 3× maior que um `fail` comum:

```
wagner_modified com diff significativo (>30% do output) → penalidade = -1.5
wagner_modified com diff pequeno (<30% do output)       → penalidade = -0.3
wagner_rejected completamente                           → penalidade = -3.0
```

**Razão:** modificação humana carrega informação mais rica que uma falha técnica. Um teste que
falha pode ser bug de ambiente. Uma modificação humana significa que a IA entendeu errado o problema.

### Tabela de persistência

```sql
mcp_confidence_scores (
  domain          VARCHAR(50),
  task_type       VARCHAR(50),
  score           DECIMAL(4,3),   -- 0.000 a 1.000
  sample_size     INT,            -- quantas execuções na janela
  last_outcome    ENUM(success, fail, wagner_modified, wagner_rejected),
  updated_at      TIMESTAMP,
  PRIMARY KEY (domain, task_type)
)
```

### Quando confiança sobe/desce automaticamente

| Evento | Impacto |
|---|---|
| PR aberto por agente mergeado sem modificação | +0.05 |
| PR mergeado com modificação pequena Wagner | -0.03 |
| PR rejeitado por Wagner | -0.15 |
| Teste Pest falhou após execução agente | -0.20 |
| Pest verde + PR mergeado + 0 modificações | +0.08 |

## Consequências

**Positivas:**
- Autonomia aumenta organicamente com o tempo sem intervenção manual
- Domínios perigosos (NFSe, Billing) ficam em Brain B mais tempo por design
- Wagner que modifica frequentemente a saída é o principal sinal de treinamento

**Negativas:**
- Primeiras semanas: quase tudo vai para Brain B (confiança inicial 0.5 < gate 0.7)
  → esperado, o sistema ainda não tem histórico
- Se Wagner raramente rejeita/modifica (valida tudo por pressa), o sistema cresce confiança
  artificialmente. Mitigação: Wagner deve usar [Rejeitar + motivo] quando output estiver ruim,
  mesmo que não queira consertar agora
