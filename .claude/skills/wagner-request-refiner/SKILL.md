---
name: wagner-request-refiner
description: ATIVAR quando Wagner manda múltiplos pedidos curtos não-estruturados num mesmo turno (ex: lista com 3+ items, "todo: a) b) c)", bullets numerados, screenshots com várias anotações simultâneas, ou texto corrido com várias intenções misturadas). Decompõe em tasks atômicas, infere owner/priority/module/estimate cruzando com SPEC.md + MCP, propõe estrutura ANTES de criar via MCP ou editar código. Anti-pattern: pegar tudo de uma vez e implementar sem cruzar dependências. Wagner valoriza economia de crédito + escopo confirmado antes de execução massiva.
tier: B
---

# wagner-request-refiner — refina pedidos vagos em US estruturadas

## Quando ativar (gatilhos)

1. **Lista bullet `[]` ou `-` ou `1) 2)`** com 2+ itens no mesmo turno
2. **Screenshot com várias anotações** (ex: setas + texto em pontos diferentes)
3. **Pedido genérico** como "melhore", "harmonize", "adicione features" sem definir quais
4. **Mix de bug-report + feature-request + meta-task** no mesmo prompt
5. **Wagner usa abreviações ou referências ambíguas** ("aquele que falamos", "o de ontem")

## Comportamento

Quando ativar, **antes de qualquer Edit ou tasks-create**, processar nesta ordem:

### 1. Decompor

Listar cada item identificado em formato:

```
ITEM N: <título curto imperativo>
  fonte: <screenshot / texto / inferência>
  tipo: <bug | feature | melhoria-UX | governança | meta>
  arquivo provável: <caminho ou "?">
  evidência: <citação literal do Wagner ou anchor do screenshot>
```

### 2. Inferir metadados

Pra cada item, propor:
- **module**: cruzar com `Modules/*/` ou `memory/requisitos/*/`
- **priority**: p0 (quebra prod) | p1 (cliente reportou) | p2 (melhoria) | p3 (feature wish)
- **owner**: Wagner default, mas avaliar `regras-time.md` matriz (Eliana se LGPD, Felipe se Pest, Maiara se suporte)
- **estimate_h**: 1h (fix CSS) | 2-4h (UI completo) | 8h+ (epic — quebrar)
- **sprint**: cycle ativo (`cycles-active`)

### 3. Cruzar com existentes (evita duplicação)

Pra cada item, rodar:
- `tasks-list module:X` → buscar US com título similar
- `Grep` em SPEC.md → placeholders já reservados
- Decisões/charters relacionados via `decisions-search`

Se encontrar similar:
- ✅ **Idêntico** → reusar US existente, comentar atualização
- 🟡 **Parcial** → propor adicionar checkpoint ao DoD existente
- ❌ **Nada** → criar nova

### 4. Apresentar PRIMEIRO, executar DEPOIS

Devolver pro Wagner em formato compacto:

```markdown
## Triagem de N pedidos

| # | Item | Status | Proposta |
|---|---|---|---|
| 1 | <título> | 🆕 nova | criar US-XX-NNN p2 owner:X est:1h |
| 2 | <título> | ♻️ existe | reusar US-YY-MMM, adicionar comentário |
| 3 | <título> | ❓ vago | precisa clareza: "<pergunta>" |

**Implementação batch:** N fixes em 1 PR (M arquivos)
**Bloqueador:** algum item precisa confirmação?
```

Aguardar **"ok" / "vai" / "pode fazer"** ANTES de:
- `tasks-create` (gasta IDs)
- `Edit`/`Write` em código
- `git commit` ou `gh pr create`

### 5. Anti-padrões a evitar

- ❌ Implementar tudo silenciosamente e mandar "feito"
- ❌ Criar 5 tasks novas sem cruzar com existentes (duplicação)
- ❌ Inferir owner sem checar matriz `regras-time.md`
- ❌ Estimate "5min" sem base — usar fator 10× IA-pair ([ADR 0106](memory/decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))
- ❌ Marcar prioridade alta sem evidência de cliente reportando

### 6. Caso o pedido seja claro e atômico

Se já é uma instrução única e clara (ex: "merge PR #527"), **não ativar a skill** — executa direto. A skill é pra quando o input é uma sopa de intenções que precisa de structure-first.

## Exemplos

### Exemplo bom (skill ativada corretamente)

Wagner manda:
```
todo:
[] adicionar arquivos
[] avaliar servidor s3 consultar infra
[] no rodapé falta algumas features?
[] sistema de permissão de usuário
```

Claude (com skill):
1. Triagem 4 items
2. Cruza: itens 1+4 já existem (US-WA-042/043 e US-WA-044) — não duplica
3. Cria 2 tasks novas (S3 + composer audit)
4. Apresenta tabela ANTES de criar
5. Wagner aprova → cria

### Exemplo ruim (sem skill — duplicação)

Claude:
1. Cria 4 tasks novas sem cruzar
2. Implementa sem perguntar
3. Resultado: 2 duplicadas, 1 fora de escopo, 1 ok
4. Wagner irritado

## Por que existe

Wagner valoriza: economia de crédito, escopo confirmado antes de execução massiva, anti-presunção. Esta skill formaliza o padrão "decompor + apresentar + aguardar OK" que ele já demonstrou preferir em sessões anteriores (handoffs `memory/handoffs/2026-05-10-*.md`).

## Tier

**Tier B (auto-trigger por description)** — não always-on. Ativa só quando padrão de pedido bate critérios acima.

Referências:
- [ADR 0094](memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) Princípio 4: loop fechado por métrica
- [ADR 0105](memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) Cliente como sinal — não criar US sem sinal
- [ADR 0106](memory/decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) Estimates com fator 10×
- `memory/proibicoes.md` "Não assumir completude" + "confirme escopo com perguntas curtas"
