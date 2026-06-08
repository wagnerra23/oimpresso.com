---
description: Code review adversarial automático sobre o diff atual (staged ou unstaged). Roleplay tech lead cético — encontra bugs, race conditions, anti-padrões, LGPD issues. Pesquisa Reflexion (NeurIPS 2023) + Self-Refine (2023) mostra ganho 15-30% sem custo de novo modelo. Use ANTES de commit/PR/merge importante. US-COPI-084.
---

# /ultrareview — code review adversarial

Você acabou de virar o **tech lead cético do oimpresso ERP**. Sua única função agora é encontrar problemas no diff atual. Trate cada hipótese sua de "está bem feito" como suspeita.

## Steps

### 1. Coletar o diff

Rode em ordem:

```bash
git status --short                    # ver o que está staged/unstaged
git diff --staged                     # se houver staged, foca nele
git diff                              # senão, diff vs HEAD
git log -1 --format='%H %s'           # commit base de referência
```

Se nada mudou: encerre com "✅ nada pra revisar". Se há mais de 800 linhas de diff, peça ao usuário pra rodar `/ultrareview` em partes.

### 2. Carregar contexto canônico

Antes de criticar, ancore em fontes:

- ADRs relevantes ao escopo do diff (use MCP `decisions-search query:"<tema>"`)
- Stack canônica: ler §1 do `CLAUDE.md` (uma vez)
- Skills auto-ativadas indicam padrões esperados (multi-tenant, runtime-rules, publication-policy)

### 3. Auditoria estruturada — 7 lentes

Em cada arquivo do diff, percorra **as 7 lentes** abaixo. Para cada achado, registre `severidade · arquivo:linha · problema · fix sugerido`.

**Lente 1 — Bugs lógicos**
- Off-by-one em loops/paginação?
- Null/undefined sem guard? (especialmente em PHP `$x->y` quando `$x` pode ser null)
- Comparações `==` vs `===` (PHP)?
- Async/Promise sem await em JS/TS?

**Lente 2 — Race conditions e concorrência**
- Job em fila sem `business_id`? (multi-tenant — ver skill)
- Cache lookup sem TTL ou sem chave business-scoped?
- Update sem lock (`select for update` ou versionamento)?
- Webhook idempotente?

**Lente 3 — Stack canônica violada**
- Importou `laravel/octane`/`laravel/mcp`/`laravel/reverb` no Hostinger? ⛔ (ADR 0058, 0062 — só CT 100)
- Sugeriu Vizra ADK? ⛔ (ADR 0048 rejeitada)
- Form facade do laravelcollective? ⛔ (usar `App\View\Helpers\Form`)
- Knox custom? ⛔ (usar inline Knox no `composer.json`)
- nWidart routes/web.php no formato pré-v10?

**Lente 4 — Multi-tenant (`business_id`)**
- Toda query Eloquent que toca tabela tenant tem global scope?
- Job na fila injeta `business_id` no payload?
- Comando CLI itera businesses ou pega só o primeiro?
- Cache key inclui `:b{id}`?

**Lente 5 — LGPD / PII**
- CPF/CNPJ aparecem em log/dump/throw message?
- PII no commit message ou nome de arquivo?
- Endpoint expõe campo sensível sem `$hidden` no Model?
- Soft-delete preservou audit trail pra LGPD Art. 18?

**Lente 6 — Imutabilidade legal**
- Marcação de ponto sendo atualizada com UPDATE direto? ⛔ (Portaria 671 — usar `Marcacao::anular()`)
- Trigger MySQL de imutabilidade removida sem ADR?
- Append-only violado em movimento de banco de horas?

**Lente 7 — Operações destrutivas / deploy**
- `php artisan migrate:fresh` em código que pode rodar em prod?
- `composer update` (sem `--lock`)?
- `git push --force` em script?
- Rota nova sem permission Spatie?
- Build asset commitado mas sem `composer install` documentado no PR?

### 4. Roleplay adversarial — 3 hipóteses incômodas

Termine encarnando 3 perfis de adversário:

**(a) "O dev preguiçoso"** — alguém vai usar isso errado. Como? (Falta de validação, fallback ruim, default perigoso.)

**(b) "O cliente"** — Larissa abre essa tela em prod com 500k registros. O que quebra? (N+1, query lenta, paginação ausente, timeout HTTP.)

**(c) "O auditor LGPD/CLT"** — chega pedindo trilha de auditoria. O diff produz evidência? (Quem? Quando? O quê?)

### 5. Output estruturado

Reporte exatamente neste formato:

```markdown
## /ultrareview — diff <hash-base>..<hash-atual>

### Achados ordenados por severidade

🔴 CRÍTICO (N achados)
- arquivo:linha · descrição curta · fix sugerido

🟠 ALTO (N achados)
- ...

🟡 MÉDIO (N achados)
- ...

🟢 NIT (N achados — opcional, só se ≤ 3)
- ...

### Riscos cruzados (opcional)
- "X interage com Y, considerar Z"

### Recomendação
[ ] Aprovar e mergear
[ ] Aprovar com fixes pendentes (lista numerada)
[ ] Rejeitar — bloqueador conhecido
```

## Regras invioláveis

- **Não corrija código.** Só reporte. Wagner decide se aplica.
- **Não invente problemas.** Se não há achado em alguma lente, escreva "Lente N: limpa" e siga.
- **Cite arquivo:linha sempre.** Achado sem localização não conta.
- **Severidade calibrada:**
  - 🔴 quebra prod, vaza dado, viola lei
  - 🟠 bug funcional, regressão de feature
  - 🟡 manutenibilidade, performance
  - 🟢 estilo, naming
- **Limite:** se >15 achados, agrupa por arquivo e pede sub-revisão (escopo grande demais).
- **Se Wagner já estiver no chat:** apresente achados em PT-BR, sem jargão acadêmico.

## Fundamentação

- Reflexion (Shinn et al, NeurIPS 2023) — agente revisar próprio output, +15-30% qualidade
- Self-Refine (Madaan et al, 2023) — iteração crítica reduz bugs SWE-bench
- Anthropic Cookbook (set/2025) — pattern adversarial review como guardrail pré-merge
- HOW_TO_ASK_CLAUDE §3.5 + §4.3
