---
name: Trigger "guarde no cofre" — comando reservado de Wagner
description: Quando Wagner disser "guarde no cofre", "salve no cofre" ou variações, salvar evidência/decisão no MemCofre (módulo de Cofre de Memórias). Padrão de comando para virar parte do fluxo natural.
type: workflow
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
## O comando

Quando Wagner pedir **"guarde no cofre"**, **"salve no cofre"**, **"deposita no cofre"** ou similar:

1. **Identificar o conteúdo** da mensagem ou contexto recente que ele quer guardar (decisão técnica, snippet, regra, ideia, screenshot, link)
2. **Classificar** o tipo:
   - `decision` → ADR em `memory/requisitos/{Modulo}/adr/`
   - `rule` → regra Gherkin em `SPEC.md`
   - `quote` / `flow` / `bug` / `screenshot` → evidência em `docs_sources` + `docs_evidences`
   - `requirement` → user story em `SPEC.md`
3. **Criar registro** apropriado no MemCofre:
   - Se é decisão estável: criar ADR em arquivo `.md` numerado
   - Se é evidência rascunho: criar via endpoint `/memcofre/ingest` (ou direto na tabela `docs_evidences` com status=pending)
4. **Confirmar** ao Wagner com link curto pro arquivo/registro criado

## Por que existe esse trigger

Wagner está construindo MemCofre como o Cofre de Memórias do projeto (estilo Obsidian Vault). Ele quer que conversas com IA acabem em **artefato persistente versionado** — não em chat efêmero. O comando "guarde no cofre" é a interface natural pra esse handoff.

## Onde salvar (decisão por contexto)

| Contexto | Local |
|---|---|
| Decisão arquitetural cross-módulo | `memory/requisitos/_DesignSystem/adr/{categoria}/NNNN-...md` |
| Decisão de um módulo específico | `memory/requisitos/{Modulo}/adr/{categoria}/NNNN-...md` |
| Regra Gherkin nova | `memory/requisitos/{Modulo}/SPEC.md` (seção "Regras") |
| User story nova | `memory/requisitos/{Modulo}/SPEC.md` (seção "User stories") |
| Bug pattern / lição operacional | Auto-memória (`feedback_*.md` em `~/.claude/projects/.../memory/`) |
| Conhecimento volátil/rascunho | Tabela `docs_evidences` via `/memcofre/ingest` |
| Trigger word, preferência pessoal | Auto-memória (este arquivo é exemplo) |

## Como Wagner pode usar

```
"guarde no cofre que vendas precisam ter Carbon::parse no format_date 
quando os dados históricos forem migrados"

→ Cria entrada em memory/requisitos/PontoWr2/adr/tech/NNNN-format-date-historico.md
  com status=proposed, refs ao feedback_carbon_timezone_bug.md
```

```
"guarde no cofre essa preferência: sempre commitar com co-author Claude"

→ Cria preference_*.md na auto-memória
```

```
"guarde no cofre essa ideia"  [após uma discussão de feature]

→ Cria ideia_*.md na auto-memória OU evidência tipo "decision" no docs_evidences
```

## Variações reconhecidas

- "guarde no cofre"
- "salve no cofre"
- "deposita no cofre"
- "joga no cofre"
- "isso vai pro cofre"
- "registra no MemCofre"
- "memória cofre" (mais ambíguo, pedir clarificação)

## Sintaxe sugerida (não obrigatória)

Wagner pode ser explícito sobre:
- **Tipo**: "guarde no cofre como decisão" / "...como regra" / "...como evidência"
- **Módulo**: "guarde no cofre do Ponto" / "...do MemCofre" / "...sistema-wide"
- **Status**: "guarde no cofre como rascunho" / "...como aprovado"

Se ele não especificar, classifique pela intuição do conteúdo e confirme.

## Conexão com o resto

- Módulo: `Modules/MemCofre` (Cofre de Memórias)
- Comandos: `memcofre:audit-module`, `memcofre:sync-pages`, etc
- URL: `/memcofre/*`
- Tabelas: `docs_*` (mantidas com prefixo legado)
