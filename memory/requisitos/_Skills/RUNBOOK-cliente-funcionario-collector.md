---
title: RUNBOOK uso — skill cliente-funcionario-collector
status: live
tipo: runbook
skill: cliente-funcionario-collector
adr: 0144 proposed
criado: 2026-05-14
autor: Wagner + Claude (worktree naughty-euclid-2ab744)
---

# RUNBOOK uso — skill `cliente-funcionario-collector`

> Skill Tier A bloqueador. Auto-trigger sem Wagner pedir. Origem em [proposta ADR 0144](../../decisions/proposals/cliente-funcionario-perfis-coleta-sistematica.md).

## 1) Quando a skill dispara

Dispara em qualquer um dos triggers abaixo (matchers em [SKILL.md](../../../.claude/skills/cliente-funcionario-collector/SKILL.md) §"Quando ativa"):

| # | Trigger | Exemplo |
|---|---|---|
| 1 | `business_id=N` onde N ≠ {1, 4, 99} | `"vamos importar dados pro biz=164 do Martinho"` |
| 2 | Nome próprio + role operacional | `"Lara cuida do estoque do Martinho"` |
| 3 | Decisão arquitetural envolvendo cliente | ADR/proposal citando razão social real ou business_id |
| 4 | Marco datável | `"Jair endossou 14/maio"` / `"Larissa reclamou"` / `"canary começa"` |
| 5 | Status mudança | `qualificado → piloto-ativo` no frontmatter |
| 6 | Palavra-chave Wagner | `"salve no perfil"` · `"anota no cliente X"` · `"isso é ouro"` |

**NÃO dispara em:** Wagner próprio (biz=1) · ROTA LIVRE biz=4 sem novidade · sandbox biz=99 · hipotético (ADR 0105) · time interno (Wagner/Felipe/Maiara/Luiz/Eliana[E]) · Eliana(WR2) cliente externo.

## 2) O que ela faz automaticamente

Sequência fixa (sem Wagner pedir):

```
1. Determinar slug alvo (cliente: kebab(razao_social); funcionário: lowercase(first_name))
2. Glob memory/reference/clientes/<slug>.md OU funcionarios/<cliente>/<slug>.md
3. Bifurcação:
   - Existe: append entrada datada em ## Histórico + bump `ultima_atualizacao`
   - NÃO existe: carregar _TEMPLATE.md + criar stub + frontmatter populado
4. Cross-link bidirecional (cliente lista funcionários; funcionário aponta cliente_slug)
5. Scan PII (CPF/email pessoal/telefone) — se match → BLOQUEAR commit + alerta LGPD
6. Confirmar com Wagner antes de commit se ambíguo
```

## 3) O que ela PEDE Wagner confirmar

Skill NÃO commita silenciosamente. Pede confirmação humana em:

1. **Criação de stub NOVO cliente** — perguntar se há signal real (ADR 0105: paga + reporta OU métrica detecta drift). Sem signal → não criar.
2. **Slug ambíguo** — duas opções plausíveis (ex: `vargas-recapagem` ou `recapagem-vargas`).
3. **Homônimos funcionário** — `Lara` filha Jair (estoque) vs `Lara` outro cliente (financeiro). Skill propõe sufixo (`lara-financeiro`).
4. **PII real detectada** — alerta + propõe `pii_vault_ref: vault://<cliente>/<funcionario>` em vez de escrever PII em git.
5. **Status crítico** (`qualificado → churned` ou `producao → churned`) — sempre pedir motivo no `## Histórico`.

## 4) Como Wagner desliga manualmente

Em sessões raras (debug · refactor de templates · sessão exploratória sem signal):

```
/no-collector <razão curta>
```

Skill respeita o flag pelo turno corrente. Próximo turno volta a operar normal.

## 5) Exemplos reais

### Caso ROTA LIVRE (biz=4) — atualizar histórico

Wagner: *"Larissa reclamou da lentidão da listagem hoje, preciso investigar"*

Skill faz:
1. Detecta trigger 4 (`reclamou`) + biz=4 implícito (Larissa = dona ROTA LIVRE)
2. NÃO cria stub novo (perfil maduro existe)
3. Append em `memory/reference/clientes/rotalivre.md` `## Histórico`:
   ```markdown
   - **2026-05-14:** Larissa reportou lentidão na listagem (investigação aberta — ref session XX)
   ```
4. Append em `memory/reference/funcionarios/rotalivre/larissa.md` `## Histórico de interações`:
   ```markdown
   - **2026-05-14:** reclamou de lentidão na listagem (vendas)
   ```
5. Bumpa `ultima_atualizacao: 2026-05-14` em ambos
6. Cross-link com session log do dia

### Caso Martinho (biz=164) — criar stub funcionário

Wagner: *"A Lara cuida do estoque do Martinho, vai ser champion canary"*

Skill faz:
1. Detecta trigger 2 (`Lara` + `cuida do`)
2. Cliente `martinho-cacambas` existe (perfil maduro) → cross-link
3. Funcionário `lara` NÃO existe → carregar template
4. Pergunta Wagner: *"Confirmar criar `funcionarios/martinho-cacambas/lara.md`? Signal: cliente piloto-ativo + champion canary."*
5. Após confirmação → criar stub com frontmatter:
   ```yaml
   slug: lara
   cliente_slug: martinho-cacambas
   first_name: Lara
   role_operacional: responsável estoque
   papel_canary: champion-oimpresso
   pii_vault_ref: vault://martinho-cacambas/lara
   ultima_atualizacao: 2026-05-14
   ```
6. Append em `martinho-cacambas.md` `## Stakeholders` referência à Lara
7. PII guard: se Wagner colou CPF/email pessoal → bloquear commit + alertar

### Caso PII bloqueada

Wagner: *"O CPF da Lara é 123.456.789-00, ela trabalha no Martinho"*

Skill faz:
1. Detecta trigger 2 (Lara + trabalha em) → ATIVA
2. Detecta CPF formato pontuado (regex `\d{3}\.\d{3}\.\d{3}-\d{2}`) → **ALERTA LGPD**
3. Bloqueia escrita em git canônico
4. Propõe: *"CPF detectado. Salvar em Vaultwarden `vault://martinho-cacambas/lara` e referenciar via `pii_vault_ref`?"*
5. Após Wagner aprovar → registrar no Vaultwarden e escrever só o `pii_vault_ref` no git

## 6) Métricas (telemetria)

Meta 90 dias pós-aprovação ADR 0144:

| Métrica | Meta |
|---|---:|
| Clientes piloto+ com perfil completo | 100% |
| Funcionários champion com perfil | 100% |
| PII real em git canônico | 0 |
| Wagner pedidos manuais "salve no perfil" | 0/semana |
| Drift `_INDEX` vs `<slug>.md` | 0 |

## 7) Pegadinhas conhecidas

- **Nome do funcionário capitalizado mal** (`lara` minúsculo na frase) → regex não casa. Reformular: `"Lara"` capitalizado.
- **Eliana ambíguo** — `Eliana[E]` esposa Wagner ≠ `Eliana(WR2)` cliente externo. Skill SEMPRE pergunta desambiguação.
- **Slug pré-existente em pasta diferente** — `cliente-rotalivre.md` (legacy) vs `clientes/rotalivre.md` (novo). Skill prefere o novo, deixa redirect 90d no legacy.
- **PII em conversa "rascunho"** — Claude pode receber CPF/email durante coleta inicial; SKILL.md regex sempre escaneia diff ANTES de write, bloqueia.

## 8) Referências

- [SKILL.md](../../../.claude/skills/cliente-funcionario-collector/SKILL.md) — fonte canônica
- [Proposal ADR 0144](../../decisions/proposals/cliente-funcionario-perfis-coleta-sistematica.md) — contexto completo (15 seções)
- [Pest test matcher](../../../tests/Feature/Skills/ClienteFuncionarioCollectorMatcherTest.php) — 10 casos cobertos
- [Smoke standalone](../../../tests/Feature/Skills/smoke-cliente-funcionario-collector-matcher.php) — rodar sem vendor
- [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Zero auto-mem privada
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0131](../../decisions/0131-tiering-memoria-canonico-local-segredo.md) — Tiering memória (vault/git/local)
- Matriz Tier A: [`memory/sprints/s3-constituicao/03-skills-audit.md`](../../sprints/s3-constituicao/03-skills-audit.md) Bloco A
