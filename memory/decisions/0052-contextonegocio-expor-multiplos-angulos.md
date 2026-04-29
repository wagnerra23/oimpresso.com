# ADR 0052 — `ContextoNegocio` deve expor múltiplos ângulos por métrica (não 1 número)

**Status:** Aceito
**Data:** 2026-04-29
**Decidido por:** Wagner (após validação real Larissa expor o gap)
**Origem:** Validação real da Larissa (ROTA LIVRE biz=4) com 3 perguntas distintas, 1 número idêntico.

---

## Contexto

Após MEM-HOT-2 (ADR 0046+0047 — `ContextoNegocio` injetado no system prompt do `ChatCopilotoAgent`), Larissa testou em produção com **3 perguntas diferentes**:

| Pergunta da Larissa | Resposta do Copiloto (errada/genérica) |
|---|---|
| "Quanto vendi?" (bruto) | R$ 31.513,29 ✅ correto |
| "Faturamento líquido" | R$ 31.513,29 ❌ deveria descontar devoluções |
| "Quanto entrou no caixa?" | R$ 31.513,29 ❌ deveria mostrar pagamentos recebidos |

**Causa-raiz**: O `ContextoNegocio.faturamento90d` original só expunha 1 valor por mês:

```php
['mes' => '2026-03', 'valor' => 38215.07]
```

O LLM **não tinha como saber** que "líquido" e "caixa" eram números diferentes — o modelo escolheu o único disponível e respondeu igual pras 3 perguntas. Pior: a resposta da segunda pergunta inclusive **definiu corretamente** "faturamento líquido = bruto - devoluções", mas continuou citando o bruto, porque era o único número que tinha.

Isso é uma falha de **modelagem de contexto**, não do LLM. O modelo se comportou exatamente como o esperado dado os dados que recebeu.

---

## Decisão

**Princípio geral**: quando uma métrica de negócio admite **múltiplos recortes** legítimos que o usuário pode pedir, o `ContextoNegocio` deve **expor todos os recortes simultaneamente**, com glossário claro no system prompt. Nunca confiar que o LLM vai derivar um do outro com matemática que ele não tem como fazer.

**Aplicação imediata** — campo `faturamento90d` agora carrega **3 ângulos por mês**:

```php
[
    'mes'     => '2026-03',
    'valor'   => 38215.07,    // alias legado do bruto (BC com BriefingAgent)
    'bruto'   => 38215.07,    // SUM(sell.final.final_total)
    'liquido' => 37518.47,    // bruto - SUM(sell_return.final.final_total)
    'caixa'   => 35440.25,    // SUM(transaction_payments.amount via paid_on)
]
```

System prompt ganha **glossário inline** definindo cada métrica:

```
FATURAMENTO ÚLTIMOS 90 DIAS (3 ângulos por mês):
  - BRUTO    = total vendido (somar sell.final, ignora devoluções)
  - LÍQUIDO  = bruto menos devoluções (sell_return)
  - CAIXA    = pagamentos efetivamente recebidos no mês (transaction_payments)
  2026-03: bruto R$ 38.215,07 · líquido R$ 37.518,47 · caixa R$ 35.440,25
```

LLM passa a ter os 3 valores **e** a definição de qual usar pra qual pergunta — sem inventar matemática.

---

## Justificativa

- **LLMs não fazem matemática confiável** sobre dados que não estão explícitos. Confiar que `gpt-4o-mini` vai subtrair devoluções "se a pergunta for líquido" é uma aposta perdida.
- **Custo de tokens é baixo** — adicionar 3 valores + 3 linhas de glossário = ~+30 tokens/mês × 4 meses = **~120 tokens** no system prompt total. ContextoNegocio Larissa passou de 164 → ~280 tokens, ainda dentro do orçamento.
- **Reduz alucinação por omissão** — quando o LLM não tem o número certo, ele tende a parafrasear o número errado em vez de admitir lacuna. Múltiplos ângulos eliminam o problema antes dele acontecer.
- **Glossário inline ensina o modelo qual termo usar pra qual pergunta** — não depende do LLM "saber" terminologia contábil brasileira.

---

## Princípios derivados (aplicar a outras métricas)

Padrão que outras dimensões do `ContextoNegocio` devem seguir quando admitem recortes:

| Métrica | Recortes a expor (quando aplicável) |
|---|---|
| **Faturamento** | bruto · líquido · caixa (✅ MEM-FAT-1) |
| **Custos / despesas** | comprometido · pago · em aberto |
| **Lucro** | bruto · operacional · líquido |
| **Estoque** | unidades · custo · valor de venda |
| **Inadimplência** | a vencer · vencido · negociado |
| **Clientes** | ativos no mês · ativos 12 meses · totais |
| **Metas** | alvo · realizado · % ataque · dias restantes |

Checklist antes de expor uma métrica nova:

1. **A pergunta admite mais de um recorte legítimo?** Se sim → expor todos.
2. **O usuário usa termos diferentes pra cada recorte?** Se sim → glossário inline.
3. **O recorte exige cálculo SQL não-óbvio?** (join com outra tabela, sinal condicional) → não confie no LLM, pré-calcule.
4. **O custo em tokens é maior que o ganho?** Geralmente não — 50 tokens extras eliminam horas de retrabalho de spec.

---

## Consequências

**Positivas:**
- Larissa (e outros usuários) recebem o número correto pra cada pergunta sem ambiguidade.
- Glossário no prompt **ensina o usuário** a vocabulário contábil correto via respostas do Copiloto.
- Reduz necessidade de tools/function-calling pra pergunta básica de faturamento (Caminho B do ADR 0046 fica adiável).
- Cria padrão replicável pra próximas métricas (custos, lucro, inadimplência).

**Negativas / Trade-offs:**
- ~120 tokens extras por system prompt (ainda <300, target era 150-250).
- Cache do `ContextSnapshotService` precisa ser invalidado quando código muda (TTL 10 min mitiga).
- Migrations de schema do `ContextoNegocio` exigem cuidado com BC (resolvido com fallback `$m['valor'] ?? $m['bruto']`).
- Mais código no `ContextSnapshotService` (2 queries em vez de 1 — ainda performático, ambas indexadas em `transaction_date`/`paid_on`).

---

## Validação

Critério pra considerar MEM-FAT-1 bem-sucedido:

1. ✅ Suite Copiloto: 79 passed (era 77, +2 testes cobrindo 3 ângulos + BC-compat).
2. ⏳ Smoke prod: cache forget + chamar `ContextSnapshotService::paraBusiness(4)` retorna shape novo.
3. ⏳ **Larissa repete as 3 perguntas em prod**, recebe 3 números diferentes:
   - "Quanto vendi?" → bruto
   - "Faturamento líquido" → líquido
   - "Quanto entrou no caixa?" → caixa

Se 3/3 batem na repetição → MEM-FAT-1 e este ADR são considerados validados. Se ≤2/3, voltar pra investigar (provavelmente glossário precisa ser mais explícito).

---

## Aprendizado meta (sobre validação)

> **Smoke teste técnico passa muito antes de o produto estar correto.** O Copiloto retornava o número certo em prompt smoke (164 tokens, 4 meses faturamento, baseline correto) — mas só com **usuário real fazendo perguntas reais** o gap apareceu.

Implicação direta: A4 (validar Larissa) **não é uma formalidade** do Cycle 01 — é o único filtro que detecta esse tipo de bug de modelagem. Sem ele, MEM-HOT-2 estaria "fechada" no commit `2be9930c` com bug semântico latente em prod.

**Métrica de eval futura (golden set MEM-P2-1)**: pelo menos 5 das 50 perguntas devem ser variações de uma mesma métrica com termos diferentes (ex: "faturamento", "vendas", "receita", "líquido", "caixa") para detectar regressões deste tipo.

---

## Referências

- ADR 0046 — Gap ChatCopilotoAgent (origem do MEM-HOT-2)
- ADR 0047 — Wagner solo + sprint memória (define MEM-HOT-2)
- ADR 0049 — 6 camadas de memória + gate Recall@3>0.80
- ADR 0050 — 8 métricas obrigatórias + tabela `copiloto_memoria_metricas`
- ADR 0051 — Schema próprio + adapter + OTel GenAI
- Commit `fac96a19` — implementação MEM-FAT-1
- Smoke validação em prod: aguardando Larissa repetir 3 perguntas (pendente)
