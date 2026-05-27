---
slug: 0195-feedback-relevance-scoring-decay-adaptativo
number: 195
title: "Feedback indexing — relevance scoring + decay adaptativo + signature dedup"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-27"
accepted_at: "2026-05-27"
accepted_via: "Wagner aprovou via AskUserQuestion 3-decisões 2026-05-27 sessão `frosty-greider-83ab2f` — decay 60d, archive OR (score<30 OR closed>=90d), sequencial Fase A→B"
module: whatsapp
quarter: 2026-Q2
tags: [feedback, voice-of-customer, memoria, lgpd, tier-0, scoring, dedup, decay, append-only]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0131-tiering-memoria-canonico-local-segredo
authors:
  - W
  - C
---

# 0195 — Feedback indexing: relevance scoring + decay adaptativo + signature dedup

## Contexto

Wagner 2026-05-27, após PR #1711/#1712/#1713 entregar captura Voice of Customer in-app
no WhatsApp inbox: "crie índice de feedback, como checksum pra ficar na memória
apenas as mais importantes e ir retirando da memória com o tempo como nas outras
memorias, otimize, acho que tu sabe fazer melhor que eu".

Sem otimização, `clients_feedbacks` cresce linearmente. Mesma reclamação (ex: "erro
emissão NF-e SEFAZ timeout") repetida por 5 clientes ao longo de 60d vira 5 registros
distintos no INDEX agregado — sinal real fica diluído. Pior: feedbacks
resolvidos há 6 meses continuam carregados em todo `feedback-search` consumindo
contexto Claude sem agregar valor.

## Decisão

Implementar **sistema de relevância adaptativa** com 3 mecanismos:

### 1. Signature (checksum) pra dedup

```
signature = sha1(business_id | persona_slug | modulo | acao | literal_normalized)

literal_normalized:
  lowercase + strip punctuation
  → split em palavras
  → filtrar palavras < 3 chars + stopwords PT-BR
  → 5 primeiras palavras significativas
  → join " "
```

Quando captura novo feedback com mesma signature nos últimos 90d:
- `recorrente_count++` no existente
- `severity = MAX(antigo, novo)`
- `pattern_emergente = true` se recorrente_count ≥ 3
- `last_seen_at = now()`
- **NÃO cria registro novo** — atualiza existente (idempotência HTTP)

### 2. Relevance score (0-100)

```
score = 40 * severity/4                              # peso 1 — dor real
      + 25 * log10(recorrente+1) / log10(5+1)         # peso 2 — recorrência (plateau em 5)
      + 15 * (cliente_pagante ? 1.0 : 0.2)            # peso 3 — ADR 0105 sinal qualificado
      + 10 * persona_priority                         # primary=1 / secondary=0.6 / outras=0.3
      + 10 * exp(-days_since_last_seen / 60)          # decay meia-vida 60d
```

Recomputado pelo Observer `creating/updating` + job semanal `feedback:reindex`.

### 3. 4 camadas (tiering ADR 0131)

| Camada | Critério | Onde | Carregamento Claude |
|--------|----------|------|---------------------|
| **HOT** | score ≥ 70 | DB + `memory/feedback/INDEX.md` (top 20) | Sempre (~2k tokens) |
| **WARM** | 30 ≤ score < 70 | DB only | Sob demanda via tool |
| **COLD** | score < 30 OU closed/resolved ≥ 90d | DB + `memory/feedback/archive/YYYY-QN.md` (digest agregado) | Apenas com query explícita |
| **FROZEN** | resolved ≥ 365d | DB soft-deleted | LGPD retention apenas |

### Decay window: 60 dias meia-vida

Wagner aprovou explicitamente 2026-05-27 default (vs 30d agressivo / 90d
conservador). 60d sinaliza recente mas não esquece patterns trimestrais
(fechamento mensal NFe, sazonal vestuário).

### Archive condition: OR (não AND)

`score < 30` OU `closed ≥ 90d` arquiva. Wagner aprovou explicitamente 2026-05-27
(vs AND mais conservador). Mantém INDEX enxuto; closed antigo sai do hot mesmo
se score residual alto.

## Schema (migration 2026_05_27_240000)

```sql
ALTER TABLE clients_feedbacks ADD:
  signature CHAR(40) NULL                  AFTER cliente_slug
  relevance_score DECIMAL(5,2) DEFAULT 0   AFTER signature
  relevance_score_at TIMESTAMP NULL        AFTER relevance_score
  last_seen_at TIMESTAMP NULL              AFTER relevance_score_at

  INDEX idx_biz_signature (business_id, signature)
  INDEX idx_biz_relevance (business_id, relevance_score)
  INDEX idx_biz_last_seen (business_id, last_seen_at)
```

## Componentes (Fase A — esta ADR)

1. **Migration** `2026_05_27_240000_add_signature_relevance_to_clients_feedbacks`
2. **Service** `Modules/Whatsapp/Services/FeedbackRelevanceService` —
   `computeSignature()` + `computeScore()` + `findDuplicateWithin90d()` + `classify()`
3. **Observer** `Modules/Whatsapp/Observers/ClientFeedbackObserver` —
   `creating` (sig + score inicial), `updating` (rescore se severity/recorrente mudou)
4. **Controller** `ClientFeedbackController::capture()` — dedup branch usando service
5. **Model** scopes `hot()` / `warm()` / `cold()` + fillable + casts atualizados

## Componentes (Fase B — próxima ADR ou seguimento)

1. **Command** `php artisan feedback:reindex {--business=}` — rescore todos +
   gera `memory/feedback/INDEX.md` (top 20 HOT) + `memory/feedback/archive/YYYY-QN.md`
2. **Schedule** semanal domingo 03:00 BRT (Kernel.php)
3. **Pest tests E2E** scoring + decay + archive

## Consequências

### Positivas

- **Sinal qualificado**: cliente reclama 5× mesmo problema → 1 feedback com
  `recorrente_count=5, pattern_emergente=true, score≈85`. Antes: 5 linhas no INDEX.
- **Contexto Claude leve**: INDEX.md auto-loaded só carrega top 20 HOT
  (~2k tokens) em vez de N feedbacks históricos.
- **ADR 0105 reforçado**: score weighting `cliente_pagante` peso 15 favorece sinal
  de quem paga. Lead vira ruído de fundo.
- **Pattern detection automático**: `pattern_emergente=true` quando recorrente
  ≥ 3 → triagem prioriza.
- **LGPD retention**: FROZEN tier formaliza soft-delete após 365d.

### Negativas

- **Dedup window de 90d é arbitrário**: cliente que reclama mesma coisa após
  6 meses cria registro novo. Mitigação: `pattern_emergente` em FeedbackRelevanceService
  pode olhar histórico além de 90d se for caso edge.
- **Score recomputado semanal**: pode ter drift de até 7d entre escala real
  e cached. Mitigação: Observer já rescore on-update (severity/recorrente_count).
- **Stopwords PT-BR hardcoded**: vocabulário regional ("tô", "trampar") pode
  variar — lista atual cobre 80% casos comuns. Mitigação: extender via
  config quando aparecer.
- **personas-por-modulo hardcoded em Service**: evita I/O em hot path, custa
  duplicação vs `memory/requisitos/_DesignSystem/personas-por-modulo.yml`.
  Mitigação: documentado pra atualizar manualmente quando expandir.

### Neutras

- Schema cresce 4 colunas + 3 índices em `clients_feedbacks`. Custo storage
  desprezível (< 100 bytes/row). Custo write desprezível (Observer em-process).
- Backfill dos registros existentes (já criados sem signature) precisa rodar
  `feedback:reindex --backfill` 1× — comando entrega na Fase B.

## Métrica de saúde

`feedback_index_dedup_rate_7d` = `dedup_hits / total_captures` na janela 7d.

- Target esperado: 5-15% (1 em cada 7-20 capturas é repetição)
- Alert se > 40% (atendentes capturando o mesmo problema sem triagem) OU
  < 1% (dedup quebrado, signature instável)

## Referências

- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
  (HasBusinessScope no ClientFeedback)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal
  qualificado (peso 15 do score)
- [ADR 0131](0131-tiering-memoria-canonico-local-segredo.md) — tiering memória
  canonico/local/segredo (mesma filosofia HOT/WARM/COLD)
- PR #1711 — captura Voice of Customer in-app inbox
- PR #1713 — abrir chamado dev direto da conversa + guard rails ADR 0105
- Voice of Customer (Six Sigma + NN/g) — fundamento metodológico
- NN/g 1995 — severity 0-4 scale (Nielsen Heuristics)
- Mom Test (Rob Fitzpatrick) — JTBD reverse capture
