# 02 — Tool MCP `charter-fetch`

> **Spec da ferramenta que carrega Page Charter pra IA em vez de CLAUDE.md inteiro.**
> Roda no MCP server canônico (CT 100, `mcp.oimpresso.com`).
> Trade: ~500 tok charter vs ~30k tok CLAUDE.md = -90% por sessão de tela.

---

## Contrato

```
charter-fetch(page: string, version?: int) → CharterContent | CharterError
```

### Input

| Param | Tipo | Obrigatório | Default | Descrição |
|---|---|---|---|---|
| `page` | string | sim | — | Rota canônica (`/repair/dashboard`) ou path do component |
| `version` | int | não | latest | Versão do charter; default = última (file com maior `charter_version`) |

### Output (sucesso)

```json
{
  "page": "/repair/dashboard",
  "component": "resources/js/Pages/Repair/Dashboard/Index.tsx",
  "owner": "wagner",
  "tier": "A",
  "charter_version": 1,
  "last_validated": "2026-05-07",
  "stale": false,
  "stale_days": 0,
  "frontmatter": { /* parsed yaml */ },
  "sections": {
    "mission": "string",
    "goals": ["array"],
    "non_goals": ["array"],
    "ux_targets": ["array"],
    "ux_anti_patterns": ["array"],
    "automation_hooks": ["array"],
    "automation_anti_hooks": ["array"],
    "metrics": ["ClassTest::method"]
  },
  "raw_md": "string"
}
```

### Output (erro estrutural)

```json
{ "error": "charter_not_found" | "frontmatter_invalid" | "sections_missing", "details": "..." }
```

Nunca lança exception — erro é dado.

### Drift signal

`stale: true` quando `now - last_validated > 30 dias` (configurável por tier — A=30d, B=60d, C=90d).

---

## Resolução de path

1. `page` começa com `/` → busca por frontmatter `page:` que bata
2. `page` parece path → resolve direto
3. Múltiplos arquivos `*.charter.md` no dir → escolhe maior `charter_version`

---

## Backend

Lê de `mcp_memory_documents` (já sync via webhook GitHub→MCP). Filtro:
- `path LIKE '%.charter.md'`
- Index FULLTEXT em `body` pra match rápido
- Cache 5min por `(page, version)`

Sem code novo do lado MCP — extensão do schema existente.

---

## Telemetria

Cada chamada incrementa em `mcp_audit_log`:
- `charter_fetch_calls_total{page, hit_or_miss}`
- `charter_fetch_token_estimate{page}` (tokens economizados vs ler CLAUDE.md)

Métrica M1 (Token Economy, F4) lê desses contadores.

---

## Integração com skill `charter-first`

Skill (Tier A dormente até esta tool subir):
1. Hook `PreToolUse` em `Edit|Write` quando `file_path` casa `*.tsx`
2. Identifica path → chama `charter-fetch <page>` antes do edit
3. Injeta `## Charter desta tela` no contexto da edição
4. Bloqueia edit se charter `tier: A` está stale + owner ≠ usuário

---

## Critério de aceite F1

- [ ] Tool implementada no MCP server (CT 100)
- [ ] Smoke test: `charter-fetch /repair/dashboard` retorna charter atual em <100ms
- [ ] Cache de 5min funciona (1ª chamada miss, 2ª hit)
- [ ] Drift signal correto pra charter com `last_validated` > 30d
- [ ] Erro estrutural pra `page` inexistente (não throws)
