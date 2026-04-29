# ADR 0056 — MCP server como fonte única de memória pro Copiloto chat + Claude Code

**Status:** Aceito
**Data:** 2026-04-29 (sessão noite final)
**Decidido por:** Wagner [W] — *"o claude code é a IA, os conhecimentos que ele tem que ter acesso. o mcp fornece. quero entrar com o copiloto no laravel e buscar os conhecimentos"*
**Estende:** [ADR 0053](0053-mcp-server-governanca-como-produto.md), [ADR 0055](0055-self-host-team-plan-equivalente-anthropic.md)

---

## Contexto

Antes desta decisão, **Copiloto chat** (Laravel `/copiloto`) e **Claude Code** (CLI Anthropic) usavam fontes de memória **separadas**:

```
ANTES:
  Copiloto chat → MeilisearchDriver direto → meilisearch
  Claude Code → MCP server → MeilisearchDriver → meilisearch
```

Problemas:

1. **Duplicação de configuração** — drivers diferentes, mesmas tabelas
2. **Audit fragmentado** — chat Larissa só ficava em `copiloto-ai.log`, Claude Code só em `mcp_audit_log`
3. **Quotas duplas** — quota MCP só atingia Claude Code, não chat
4. **Sem trilha unificada de governança** — incidentes precisavam correlacionar 2 sistemas
5. **Lock-in arquitetural** — vender Copiloto pra Larissa exigiria duplicar audit

Wagner clareou: *"diferente do copiloto. tem outra finalidade. quero entrar com o copiloto no laravel e buscar os conhecimentos [via MCP]"*.

## Decisão

Refatorar Copiloto chat pra **consumir MCP server** em vez de bater Meilisearch direto. MCP vira a **camada única de memória** pra qualquer cliente IA (Claude Code, Copiloto chat, futuros bots).

```
DEPOIS:
  Claude Code (Wagner programando) ──┐
                                      ├──→ MCP server → Meilisearch (fonte de verdade)
  Copiloto chat (Larissa, etc) ──────┘
```

### Separação de responsabilidades canônica

| Peça | Função | Quem usa |
|---|---|---|
| **Claude Code** | IA de programação CLI/IDE | Wagner + time dev |
| **MCP server** | Camada única de conhecimento (data layer) | Claude Code + Copiloto chat |
| **Copiloto chat** | IA de negócio web | Larissa + clientes finais |

### Implementação

1. **Tool MCP `memoria-search`** (`Mcp/Tools/MemoriaSearchTool.php`):
   - Busca em `copiloto_memoria_facts` por business + query (FULLTEXT)
   - Cross-tenant safety: assert business do user-do-token === business da query
   - Exception: superadmin pode passar qualquer business_id explícito

2. **Driver `McpMemoriaDriver`** (`Services/Memoria/McpMemoriaDriver.php`):
   - Implementa `MemoriaContrato` (mesma interface, plug-and-play)
   - Cliente HTTP JSON-RPC 2.0 → `mcp.oimpresso.com/api/mcp`
   - Bearer token: `COPILOTO_MCP_SYSTEM_TOKEN` (system, não user-bound)
   - Parse markdown response → `MemoriaPersistida[]`
   - **Fallback automático**: se MCP indisponível (timeout/5xx), degrada
     pra MeilisearchDriver direto (configurado via DI)

3. **Binding switch** em `CopilotoServiceProvider`:
   ```php
   if ($driver === 'mcp') {
       $fallback = $app->make(MeilisearchDriver::class);
       return new McpMemoriaDriver($fallback);
   }
   ```

4. **Token system** gerado via cmd `copiloto:mcp:system-token`:
   - Cria `mcp_token` ligado a Wagner (user #1)
   - Sem TTL — vive até revogar manualmente
   - Wagner copia raw 1× e adiciona ao `.env` Hostinger

5. **Config canônica** em `Modules/Copiloto/Config/config.php`:
   ```php
   'mcp' => [
       'url'              => env('COPILOTO_MCP_URL', 'https://mcp.oimpresso.com/api/mcp'),
       'system_token'     => env('COPILOTO_MCP_SYSTEM_TOKEN', ''),
       'timeout_seconds'  => env('COPILOTO_MCP_TIMEOUT', 5),
   ],
   ```

6. **Ativação** via `.env`:
   ```
   COPILOTO_MEMORIA_DRIVER=mcp
   COPILOTO_MCP_SYSTEM_TOKEN=mcp_<gerado>
   ```

## Consequências

### Positivas

- **Audit unificado** — todo recall (chat OU programação) fica em `mcp_audit_log`
- **Quotas aplicam às duas pontas** — Wagner setta R$ 5/dia/user, vale tanto pro chat quanto pro Claude Code
- **RBAC unificado** — mesma matriz Spatie pra Claude Code e Copiloto chat
- **Ponto único de evolução** — quando ativar HyDE/Reranker no MCP, Copiloto chat e Claude Code ganham automático
- **Compliance LGPD** — 1 trilha de auditoria pra demonstrar a reguladores
- **Multi-tenant** — vender Copiloto pra cliente externo (Larissa) já vem com governança via MCP
- **Resilience** — MCP server pode escalar independente do app web (CT 100 vs Hostinger)

### Negativas / Trade-offs

- **+1 hop network** — chat Larissa agora faz Hostinger → mcp.oimpresso.com → Hostinger MySQL → response. ~50-100ms a mais por recall.
- **MCP indisponível = chat degradado** — mitigado via fallback driver (MeilisearchDriver direto se 5xx). Custo: 5s timeout default.
- **Token system gerenciado** — precisa rotacionar periodicamente (TODO doc: rotation policy 6 meses)
- **MCP server vira ponto crítico** — falhas têm blast radius maior. Mitigação: monitoring + Octane workers + Traefik resiliente (já temos)

### Migração

- **Default permanece `auto`** (= `meilisearch`) → backwards-compat 100%
- Wagner ativa via env `COPILOTO_MEMORIA_DRIVER=mcp` quando quiser testar
- Roll-back: `unset COPILOTO_MEMORIA_DRIVER` → volta MeilisearchDriver

## Pré-requisitos pra ativar em prod

1. ✅ MCP server vivo (`mcp.oimpresso.com`)
2. ✅ Tool `memoria-search` deployada no MCP server (CT 100)
3. ✅ McpMemoriaDriver no Laravel app (Hostinger)
4. ✅ Binding switch no CopilotoServiceProvider
5. 🔲 Token system gerado via `copiloto:mcp:system-token`
6. 🔲 `.env` Hostinger com `COPILOTO_MEMORIA_DRIVER=mcp` + token
7. 🔲 Smoke: Larissa pergunta no chat → recall via MCP → fact retorna
8. 🔲 Audit log mostra request `tool=memoria-search` no `mcp_audit_log`

## Não faz parte deste ADR

- **Write via MCP** (`lembrar`, `atualizar`, `esquecer`) — por enquanto McpMemoriaDriver delega pra fallback Meilisearch direto. Tool MCP `memoria-write` futura (próximo cycle)
- **`listar` via MCP** — same: delega pro fallback. Tool MCP `memoria-list` futura
- **Embeddings cross-driver** — MCP usa MySQL FULLTEXT direto na MemoriaSearchTool; tools com embedding (vector search) ficam pro RRF tuning Sprint 9 (ADR 0054)

## Métricas pra validar (após ativar)

| Métrica | Como medir | Alvo |
|---|---|---|
| Latência adicional | comparar `responderChat` antes/depois | < +100ms |
| Recall preservado | rodar `copiloto:eval` antes/depois | sem regressão |
| Calls auditadas | `mcp_audit_log` filtrado por `tool=memoria-search` | ≥ 1 por turno chat |
| Quota cap funciona | criar quota, fazer N calls, ver 429 | pass |
| Fallback degrada | derrubar MCP, ver chat continuar | pass |

## Referências

- [ADR 0053 — MCP server governança como produto](0053-mcp-server-governanca-como-produto.md)
- [ADR 0055 — Self-host equivalente Anthropic Team plan](0055-self-host-team-plan-equivalente-anthropic.md)
- Session log 2026-04-29-pacote-enterprise-memoria-evolucao
