# Como perguntar pro Claude Code economizando contexto

> **Pra quem:** Wagner (e qualquer dev do time).
> **Por quê:** sessão deste documento ultrapassou 970K tokens em ~6h. Custo alto + qualidade cai (Claude esquece o início). Este guia mostra o padrão certo.

---

## TL;DR — 3 regras de ouro

1. **Uma sessão = um escopo.** Terminou? `/clear`. Não acumule.
2. **Sempre ancore na fonte canônica:** `CURRENT.md` + `TASKS.md` + ADR. Claude lê esses 3 e tem 90% do contexto.
3. **Pergunta curta + arquivo + linha + o que mudar.** Não delegue entendimento.

---

## ❌ Padrões que estouram contexto

### Anti-padrão 1: Conversa-rio (o que aconteceu nesta sessão)
```
> "construa o que falta"
> "agora cria isso"
> "agora aquilo"
> ...6h depois...
> "remova X, faça Y"
```
**Problema:** Claude carrega 970K tokens de histórico. Cada mensagem nova lê tudo. Custo: $$$, qualidade ↓.

**Em vez disso:** quebra em **N sessões focadas**, cada uma com `/clear` no fim.

### Anti-padrão 2: Pergunta aberta sem âncora
```
> "como tá o projeto?"
> "o que tem que fazer?"
```
**Problema:** Claude vai explorar repo (5-15 tool calls) só pra te responder "leia CURRENT.md".

**Em vez disso:** *você* lê CURRENT.md primeiro. Pergunte sobre o item específico.

### Anti-padrão 3: "implemente X completo"
```
> "implemente cache semântico, summarizer, distiller, e auto-promote"
```
**Problema:** vira sessão monstro de 4h. Difícil reverter parte. Difícil revisar.

**Em vez disso:** **uma feature por sessão**. Comita. `/clear`. Próxima.

---

## ✅ Padrões que economizam contexto

### Padrão 1: `/continuar` no início
```
/continuar
```
Slash command lê **só** `CURRENT.md` + `memory/08-handoff.md` + último session log + abre **só** os arquivos do próximo passo. ~2K tokens em vez de 50K de exploração.

### Padrão 2: Pergunta cirúrgica
```
> "fix migração mcp_cc_blobs em prod — Blueprint::mediumBlob() não existe em L13.
>  arquivo: Modules/Copiloto/Database/Migrations/2026_04_29_300003_create_mcp_cc_blobs_table.php:35
>  o que mudar: $t->mediumBlob('compressed_data') → $t->binary('compressed_data')"
```
**Por quê funciona:** Claude não precisa explorar. Vai direto, edita, commita.

### Padrão 3: Plan mode pra mudanças não-triviais
- Aperta `Shift+Tab` 2× pra entrar em **Plan mode**
- Claude planeja **antes** de tocar arquivo
- Você revisa o plano
- Aprova → executa

Plan errado = 2K tokens desperdiçados. Plan errado executado = 50K tokens + revert.

### Padrão 4: `/compact` após cada feature
```
[merge feature X]
/compact
[próxima feature]
```
Comprime histórico mantendo só o essencial. Pode reduzir 100K → 8K tokens.

### Padrão 5: `/clear` ao trocar de escopo
```
[terminei Copiloto chat]
/clear
[agora vou mexer em Ponto]
```
Começa limpo. Claude carrega só skills auto-ativáveis + CLAUDE.md.

---

## Receita por tipo de pedido

### Pedido = bug fix
**Formato ideal:**
```
fix: [bug descrição curta]
arquivo: [caminho:linha]
sintoma: [o que tá acontecendo]
hipótese: [opcional, se você suspeitar de causa]
```
Sessão típica: **5-15 min, ~10K tokens.**

### Pedido = feature nova pequena (<2h)
**Formato ideal:**
```
feature: [nome]
módulo: [Copiloto/Ponto/etc]
ADR de referência: [memory/decisions/00NN-xxx.md, se houver]
escopo: [3-5 bullets do que precisa]
fora de escopo: [o que NÃO fazer]
```
Sessão típica: **1-2h, ~30-50K tokens. Comita no fim. `/clear`.**

### Pedido = feature grande (>4h, vira ADR)
**Formato ideal:**
1. Primeira sessão: **só** SPEC + ADR. `/clear`.
2. Segunda sessão: implementação Fase 1. `/clear`.
3. Terceira sessão: implementação Fase 2. `/clear`.
4. ...

Cada sessão **lê o ADR** como fonte canônica. Não precisa lembrar do que conversou antes.

### Pedido = pesquisa / exploração
**Formato ideal:**
```
pesquisa: [pergunta]
output esperado: [breve relatório / ADR / lista de opções]
restrição: [PT-BR, no max 500 palavras, etc]
```
Use **subagents** (`general-purpose` ou `Plan`) — eles rodam em contexto separado e te devolvem só o resumo. Tua sessão não enche.

---

## Mapa: quando usar qual sistema

| Sistema | Pra quê | Tamanho típico |
|---|---|---|
| **CURRENT.md** | Estado vivo do cycle | 500-2K linhas |
| **TASKS.md** | Backlog organizado por módulo | 1K-3K linhas |
| **memory/decisions/** | ADRs (decisões com justificativa) | 200-500 linhas/ADR |
| **memory/sessions/** | Log cronológico (1 arquivo por sessão grande) | 100-300 linhas |
| **memory/08-handoff.md** | Estado canônico narrativo | 50-150 linhas |
| **MEMORY.md** (auto-memória) | Quirks, preferências, refs voláteis | 1 linha por item |
| **CLAUDE.md** | Primer permanente do projeto | 200-300 linhas |

**Regra:** o que cabe em 1 linha de auto-memória, **não** vai pra ADR. ADR é decisão arquitetural com trade-off.

---

## Sinais de que tá na hora de `/clear`

- 🚨 Indicador "context: 80%+" no rodapé
- 🚨 Claude começou a esquecer arquivos que viu há 1h
- 🚨 Você teve que repetir uma instrução que já deu
- 🚨 Claude tá fazendo round-trip pra `Read` arquivo que ele acabou de escrever
- 🚨 Sessão passou de 2 horas

**Antes de `/clear`:** garante que tudo importante tá num desses 3 lugares:
1. Commit no git (código)
2. ADR (decisão)
3. CURRENT.md / TASKS.md (estado / próximo passo)

Se tá só "na cabeça do Claude" → vai pro `/clear` e some.

---

## Receita meta: começo + fim de toda sessão

### Começo
```
/continuar
```
ou, se for tarefa específica:
```
[descrição cirúrgica conforme padrões acima]
```

### Fim
```
/persistir
```
(slash command que: atualiza `CURRENT.md`, apenda em `memory/08-handoff.md`, cria session log em `memory/sessions/YYYY-MM-DD-*.md`)

Aí `/clear` fica seguro.

---

## Exemplo: como esta sessão DEVERIA ter sido

### O que aconteceu (anti-padrão)
1 sessão de 6h:
- Octane + FrankenPHP migration
- Governance dashboard
- Chat streaming SSE
- Chat enterprise assistant-ui
- 50 perguntas Larissa
- Sprint 8 (cache + summarizer + distiller)
- Sprint 10 (HyDE + reranker)
- Anthropic Team plan equivalent (8 features)
- ADR 0054, 0055, 0056
- MCP-as-memory-source
- Onboarding skill
- Watcher skill
- Migrations cc_*

**Token spend:** ~970K. **Resultado:** funciona, mas 30% das features merece revisão depois.

### O que deveria ser (padrão certo)
- Sessão 1 (Octane perf): 1.5h, 60K tokens. Comita. `/clear`.
- Sessão 2 (governança dashboard): 1h, 40K tokens. Comita. `/clear`.
- Sessão 3 (chat streaming): 1h, 30K tokens. `/clear`.
- Sessão 4 (chat assistant-ui): 1h, 40K tokens. `/clear`.
- ...
- Sessão N (skill onboarding): 30min, 20K tokens. `/clear`.

**Token spend:** ~400K total (-58%). **Resultado:** cada sessão tem foco, fácil revisar, fácil reverter.

---

## Última regra (a mais importante)

> **Se você não consegue resumir o pedido em 3 frases, ele tá grande demais. Quebra antes de pedir.**

---

**Última atualização:** 2026-04-29 (criado após sessão 970K)
