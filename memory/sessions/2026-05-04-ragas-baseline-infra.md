---
slug: 2026-05-04-ragas-baseline-infra
type: session
tags: [ragas, eval, sprint-7, cycle-01, copiloto, us-copi-081]
related_us: [US-COPI-081]
related_adr: [0037, 0064, 0065, 0066]
---

# Sessão 2026-05-04 — RAGAS Sprint 7 infraestrutura (US-COPI-081)

## Contexto

US-COPI-081 é gate quantitativo do Cycle 01: provar que memória/RAG funciona com métrica reproduzível. Sprint 7 do roadmap Tier 7-9 da ADR 0037.

Esta sessão entrega **infraestrutura completa** mas NÃO roda baseline real ainda — falta `ANTHROPIC_API_KEY` configurada localmente + validação manual do golden set Larissa-style com Wagner.

## Entregue

### Golden set expandido (8 → 30 perguntas)

`tests/eval/golden-questions.yaml` agora tem 2 categorias:

**Categoria A — ADRs canônicas (8 perguntas):**
- format-date-shift, permission-registry, usuario-360-location, split-modular,
  kb-mora, governance-criar, vizra-rejeitada, reverb-status

**Categoria B — Larissa-style operacional (22 perguntas):**
- `larissa-faturamento` (5): faturamento mês bruto/líquido/caixa, 3 ângulos distintos, ano YTD
- `larissa-metas` (3): cumprimento, projeção, saldo pendente
- `larissa-vendas` (5): top produtos, ticket médio, dia pico, cliente top, hora pico
- `larissa-comparacao` (2): mês anterior, ano anterior
- `larissa-financeiro` (3): contas pagar vencidas, receber em atraso, forma pagamento
- `larissa-custos` (2): custo IA, top 5 despesas
- `larissa-estoque` (1): produtos baixo estoque
- `larissa-vendas extra` (1): venda média/dia

**Estrutura YAML padrão**:
```yaml
- id: larissa-faturamento-bruto
  category: larissa-faturamento
  question: "Quanto faturei em março de 2026?"
  must_contain: ["R$", "março"]
  must_not_contain: ["não tenho dados"]
  expected_source: "ContextoNegocio bruto"
```

### Comando `eval:ragas-baseline`

Arquivo: `app/Console/Commands/EvalRagasBaselineCommand.php`

Implementa **3 metrics RAGAS via LLM-as-judge** (Sonnet):

1. **faithfulness** — claims na resposta são suportados pelo contexto recuperado?
2. **answer_relevancy** — resposta endereça a pergunta?
3. **context_precision** — contexto recuperado é útil pra responder?

Cada metric vira 1 chamada Sonnet com prompt específico extraindo score 0-1. RAGAS score = média das 3.

**2 modos de pipeline**:

```bash
# Modo ADR (eval barato — valida qualidade da KB com retrieval grep)
php artisan eval:ragas-baseline --pipeline=adr

# Modo Copiloto (eval end-to-end — chama produto real)
php artisan eval:ragas-baseline --pipeline=copiloto
# requer COPILOTO_EVAL_ENDPOINT no .env
```

**Filtros**:

```bash
# Roda só perguntas Larissa de faturamento
php artisan eval:ragas-baseline --category=larissa-faturamento

# Roda 1 pergunta específica
php artisan eval:ragas-baseline --question=faturamento-mes-bruto
```

**Output**:

- Tabela CLI com per-question + médias dos 3 metrics + RAGAS score
- JSON estruturado em `tests/eval/results/ragas-YYYY-MM-DD-HHMMSS.json` (histórico)
- Exit code 1 se score médio < threshold (default 0.7) — gate CI

**Custo estimado**:
- 30 perguntas × 4 chamadas Sonnet (1 resposta + 3 metrics) = 120 calls
- ~500 tokens cada = ~$0.60/run com Sonnet 4.6
- Pode rodar nightly cron

## NÃO entregue (próximas etapas)

### Validação manual do golden set Larissa-style

Os 22 perguntas Larissa têm `must_contain` genéricos (ex: "R$", "março"). Wagner precisa:

1. Rodar query SQL no DB prod pra obter respostas-padrão (ex: faturamento março = R$ X.XXX,XX)
2. Atualizar YAML com valores reais em `must_contain`
3. Validar perguntas fazem sentido pro Larissa real

Sem isso, o eval valida só formato (resposta menciona valor monetário), não correctness.

### Baseline real registrado

Quando Wagner setar `ANTHROPIC_API_KEY` localmente:

```bash
export ANTHROPIC_API_KEY=sk-ant-...
php artisan eval:ragas-baseline --pipeline=adr
# Lê output JSON, anota baseline numérico aqui:
#   avg_ragas_score: 0.??
#   avg_faithfulness: 0.??
#   avg_relevancy: 0.??
#   avg_ctx_precision: 0.??
```

E pra eval end-to-end do produto:

```bash
export COPILOTO_EVAL_ENDPOINT=https://oimpresso.com/api/copiloto/eval
php artisan eval:ragas-baseline --pipeline=copiloto --category=larissa-faturamento
```

(Endpoint `COPILOTO_EVAL_ENDPOINT` não existe ainda — precisa criar API endpoint que recebe `{question, business_id}` e retorna `{answer, context}`. Vira ADR pra futuro.)

## Avisos

### Drift composer.json/lock

Vendor local quebrou durante esta sessão devido a drift pre-existente: `composer.json` referencia `nfse-nacional/nfse-php: ^1.19` mas `composer.lock` não tem o package (commit US-NFSE não rodou `composer require`). Sintoma:

```
Required package "nfse-nacional/nfse-php" is not present in the lock file.
```

`composer install` falha e `dump-autoload` reduz autoload classmap pra ~2270 classes (esperado: ~18000). Smoke local do `eval:ragas-baseline` não foi executado por isso.

**Pra restaurar**:

```bash
composer require nfse-nacional/nfse-php:^1.19 --update-with-dependencies
# ou (se package não existe no packagist sob esse nome):
# remover linha do composer.json + comitar fix
```

Em produção (Hostinger) vendor já existia antes do drift — `composer install` lá apenas refresca diff, não reverifica lock contra json. Mas próximo deploy fresh vai falhar. **Recomendo Wagner resolver lock antes de novo deploy**.

### Sentinela CI

Quando vendor local for restaurado, rodar smoke:

```bash
php artisan eval:ragas-baseline --question=format-date-shift
# (sem ANTHROPIC_API_KEY → graceful exit 0)
# (com API_KEY → roda e gera JSON)
```

## Status US-COPI-081

- ✅ Golden set 30 perguntas (objetivo era 50; 30 é MVP)
- ✅ Pipeline RAGAS 3 metrics implementado
- ✅ Comando configurável (modo ADR ou Copiloto)
- ✅ Output JSON estruturado pra histórico
- ⏸️ **Pendente**: Wagner valida golden set Larissa + roda baseline real (depende de API key + DB queries)
- ⏸️ **Pendente**: endpoint `/api/copiloto/eval` pra modo `--pipeline=copiloto` end-to-end

Status: **80% done — infra pronta, falta validação humana e execução real.**

## Próxima sessão

Wagner pode:

1. **Setar ANTHROPIC_API_KEY** + rodar `eval:ragas-baseline --pipeline=adr` → baseline numérico para Cycle 01
2. **Validar golden set Larissa** com queries SQL prod (8h estimadas, 22 perguntas × ~20min cada)
3. **Criar endpoint `/api/copiloto/eval`** se quiser pipeline end-to-end (ADR + ~3h)
4. **Resolver composer drift** (1 PR rápido removendo nfse-nacional ou adicionando ao lock corretamente)

## Referências

- ADR 0037 — Tier 7-9 RAG roadmap (RAGAS é Sprint 7)
- ADR 0064/0065/0066 — alvos das golden questions categoria ADR
- US-COPI-081 (memory/requisitos/Copiloto/SPEC.md) — registro da task
- `tests/eval/golden-questions.yaml` — golden set
- `app/Console/Commands/EvalRagasBaselineCommand.php` — pipeline + metrics
- `tests/eval/results/` — histórico de runs (gitignored? confirmar)

Co-author: Claude Sonnet 4.6 (sessão pareada com Wagner)
