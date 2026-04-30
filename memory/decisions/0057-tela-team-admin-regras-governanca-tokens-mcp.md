# ADR 0057 — Tela `/copiloto/admin/team`: regras de governança de tokens MCP e distribuição via `.dxt`

**Status:** Aceita
**Data:** 2026-04-30
**Decisores:** Wagner [W], Claude
**Tags:** mcp · governança · ui · distribuição · lgpd

Relacionada: [ADR 0053](0053-mcp-server-governanca-como-produto.md), [ADR 0055](0055-self-host-team-plan-equivalente-anthropic.md), [ADR 0056](0056-mcp-fonte-unica-memoria-copiloto-claude-code.md), [ADR 0040](0040-policy-publicacao-claude-supervisiona.md).

---

## Contexto

A tela `/copiloto/admin/team` é o **único ponto de gestão** de quem tem acesso ao MCP server `mcp.oimpresso.com` (CT 100/FrankenPHP). Ela faz:

1. **Listar** todos os users do business com tokens ativos, custo hoje/mês, top tools, último uso, quota.
2. **Gerar** token MCP raw (`mcp_<64-hex>`) — mostrado **1× só**, raw descartado, hash SHA256 gravado.
3. **Gerar `.dxt`** (Desktop Extension Anthropic) com token **embutido no `manifest.json`**, pronto pro dev arrastar no Claude Desktop.
4. **Revogar** token (soft-delete: `revoked_at = now()` + `expires_at = now()`).
5. **Editar quota** daily/monthly em BRL (block_on_exceed default true).
6. **Exportar** CSV de `mcp_audit_log` (todo histórico de calls MCP do business).

A tela toca infra crítica: **token vazado = acesso a 107 documentos de memória + 56 ADRs + sessões + chat Copiloto**. Por isso este ADR cataloga as regras que protegem o fluxo.

---

## Decisão

### 1. Permissão

Apenas users com permission Spatie **`copiloto.mcp.usage.all`** podem ver/usar a tela. Hoje atribuída só ao Wagner [W] e role superadmin. Felipe [F] e demais **não** acessam (eles consomem MCP, não geram tokens).

### 2. Identidade do token

Cada token é registrado em `mcp_tokens` com:
- `user_id` — owner (1 user pode ter N tokens, ex.: laptop + desktop)
- `name` — identificador human-readable (`"DXT — Felipe (gerado 30/04/2026 14:32)"`)
- `sha256_token` — SHA256 do raw (UNIQUE; **claro nunca armazenado**)
- `expires_at` / `revoked_at` — null = vivo
- `last_used_at` / `last_used_ip` / `user_agent` — auditoria de uso

Geração canônica via `McpToken::gerar(int $userId, string $name)` — **nunca** chamar `McpToken::create()` direto (foi causa do bug UNIQUE corrigido em 8d1aff79, 2026-04-30).

### 3. Dois fluxos de distribuição

| Fluxo | Caso de uso | Token visível pro humano? |
|---|---|---|
| **`+ Token`** (raw) | Setup manual em Claude Code CLI / scripts / CI | ✅ 1× no modal (depois descartado) |
| **`+ DXT`** (arquivo) | Onboarding rápido de dev no Claude Desktop | ❌ Embutido no `.dxt`, Wagner não vê |

Ambos chamam `McpToken::gerar()`. Diferem apenas no **invólucro de entrega**.

### 4. Regras do `.dxt`

- **ZIP archive** com extensão `.dxt` (spec [github.com/anthropics/dxt](https://github.com/anthropics/dxt) v0.1).
- Conteúdo: **apenas** `manifest.json` (não embute código de servidor — é HTTP remote MCP).
- `manifest.json` contém:
  ```json
  {
    "dxt_version": "0.1",
    "name": "oimpresso-mcp-{slug}",
    "display_name": "Oimpresso MCP — {nome}",
    "version": "1.0.0",
    "server": {
      "type": "http",
      "url": "https://mcp.oimpresso.com/api/mcp",
      "headers": { "Authorization": "Bearer mcp_..." }
    }
  }
  ```
- **1 arquivo = 1 dev = 1 token.** Nunca compartilhar entre devs.
- Headers HTTP do download: `Cache-Control: no-store` (impede cache em proxy).
- Filename: `oimpresso-mcp-{slug-nome}-{user_id}.dxt`.

### 5. Distribuição segura do `.dxt`

**Canais aceitos:**
- ✅ Vaultwarden secure note (`vault.oimpresso.com`)
- ✅ Sinal/WhatsApp criptografado (E2E)

**Canais proibidos:**
- ❌ Email plain (passa em vários servers)
- ❌ Slack público / GitHub Issues / qualquer canal que indexa
- ❌ SMS

Se Wagner usar canal proibido por engano: **revogar imediatamente** + gerar `.dxt` novo.

### 6. Revogação

- Soft-delete: marca `revoked_at = now()` + `expires_at = now()` na linha. Linha **não é apagada** (preserva auditoria).
- `mcp_audit_log` continua queryável após revogação — sabemos quem usou o token enquanto estava vivo.
- Wagner revoga em **1 clique** na tela (botão lixeira ao lado do contador de tokens). UX prioriza essa speed: vazamento → revogação em <30s.

### 7. Quotas

- Por user: `period ∈ {daily, monthly}`, `kind = brl`, `limit` em R$, `block_on_exceed` boolean.
- Quando atinge:
  - 50%: log warning
  - 80%: notificação dashboard
  - 100% (com block=true): MCP server retorna **HTTP 429** + descrição
- Reset: `daily` em 00:00 BRT, `monthly` em dia 1.
- Wagner pode editar a qualquer momento. Default proposto pra novo dev: **R$ 5/dia, R$ 50/mês**.

### 8. Auditoria obrigatória

Toda call MCP autenticada gera 1 linha em `mcp_audit_log`:
- `ts`, `user_id`, `token_id`, `endpoint`, `tool_or_resource`, `status`, `tokens_in`, `tokens_out`, `custo_brl`, `duration_ms`, `ip`, `user_agent`

CSV-exportável pela tela. Retenção: **365 dias** (LGPD compliance, ver `COPILOTO_MCP_AUDIT_RETENTION_DAYS`).

### 9. Cross-tenant safety

Token de user em `business_id=A` **só** vê dados de `business_id=A`. Garantido por:
- Global scopes Eloquent (skill `multi-tenant-patterns`).
- `McpAuthMiddleware` injeta `business_id` no Request scope antes de qualquer tool rodar.
- Tools que fazem JOIN sem global scope são proibidas (revisão em PR obrigatória).

### 10. PII e LGPD

- Token raw: nunca em git, log, screenshot, transcript, slack history.
- `mcp_audit_log` pode conter conteúdo de chat — auditoria diária (script) faz scrub de CPF/CNPJ via PII redactor BR (sprint A3 do Cycle 01).
- Hard-delete (LGPD "esquecer-me") implementa em Cycle 02 — `php artisan copiloto:lgpd:esquecer --user-email=...` apaga `mcp_tokens` + `mcp_audit_log` + memórias.

---

## Alternativas consideradas

### A. `.dxt` com prompt no install (sem token embutido)

Spec DXT 0.1+ suporta `user_config.token` que o Claude Desktop pede no setup.

**Por que rejeitada:** quebra a UX que Wagner pediu ("clica e configura"). Adiciona 1 passo manual. Quando Wagner gera `.dxt`, ele já decidiu que aquele dev tem acesso — não faz sentido o dev "confirmar" colando token.

**Trade-off aceito:** se `.dxt` vazar, vaza junto com o token. Mitigado por revogação rápida + canal de entrega seguro + ciclo de troca semestral.

### B. Mostrar `.dxt` inline em modal (sem download)

Em vez de baixar arquivo, exibir conteúdo em textarea pra dev copiar.

**Por que rejeitada:** `.dxt` é binário (ZIP). Encoding base64 pra exibir + decodificar do lado dev complica. Download direto é nativo do browser e do Claude Desktop.

### C. Tornar a tela self-service (cada dev gera o próprio token)

User logado clicaria "gerar meu token" ele mesmo, sem passar por Wagner.

**Por que rejeitada (hoje):** governança requer aprovação humana. Cycle 02 pode introduzir auto-approve com quota baixa default (R$ 1/dia) pra desenvolvedores júnior; hoje Wagner controla manualmente.

### D. Token via OAuth/SSO (Google/Microsoft)

Em vez de Bearer estático, fluxo OAuth com refresh token e expiração curta.

**Por que rejeitada (hoje):** complexidade infra (OAuth provider). Bearer estático + revogação rápida + auditoria cobre o caso de uso. Reavaliar se onboard >20 devs externos.

---

## Consequências

### Positivas

- **Onboarding em 30 segundos** com `.dxt` (vs 5 min com setup manual).
- **Wagner controla acesso** com 2 cliques (gerar) ou 1 clique (revogar).
- **Auditoria completa**: nenhum dev consegue usar MCP sem deixar rastro.
- **LGPD-aware**: hash-only no DB, retenção 365 dias, hard-delete em Cycle 02.
- **Cross-tenant safe**: global scopes + middleware garantem isolamento por `business_id`.

### Negativas / aceitas

- **Token embutido no `.dxt`**: vazamento do arquivo = vazamento do token. Mitigado por canal seguro + revogação rápida.
- **`.dxt` é Claude Desktop only** — devs em Claude Code CLI ainda usam `+ Token` + setup manual.
- **Wagner é gargalo** pra novos acessos (no good — é uma feature de governança).
- **Token raw mostrado 1× só**: se Wagner não copiar, regenera. Aceita pra evitar leak via DB dump.

### Pegadinhas operacionais

- **Bug UNIQUE em `gerarToken`** (corrigido 8d1aff79 / 2026-04-30): nunca chamar `McpToken::create()` com `token_hash`/`note` — campos NÃO existem em `$fillable`. Sempre `McpToken::gerar()`.
- **`.dxt` precisa rebuild de assets pra UI atualizar**: `npm run build:inertia` + scp pro Hostinger. `npm run build:inertia` no projeto principal, não na worktree.
- **CSRF token** — POST `/admin/team/{user}/dxt` exige `X-CSRF-TOKEN` header (UI já faz via fetch).

---

## Fluxos canônicos (referência rápida)

### Gerar `.dxt` pra novo dev
```
Wagner → /copiloto/admin/team → linha do dev → 📦 + DXT
   ↓ confirm
Backend: McpToken::gerar() + ZipArchive(manifest.json) → .dxt
   ↓ Content-Disposition: attachment
Browser baixa: oimpresso-mcp-{slug}-{id}.dxt
   ↓
Wagner → Vaultwarden secure note → entrega pro dev
   ↓
Dev → arrasta .dxt no Claude Desktop → instalado
   ↓
Dev → 1ª chamada MCP → HTTP 200 + audit log entry
```

### Revogar acesso de dev
```
Wagner → /copiloto/admin/team → linha do dev → contador "X ativos" → X
   ↓ confirm "revogar todos"
Backend: UPDATE mcp_tokens SET revoked_at=now(), expires_at=now() WHERE user_id=...
   ↓
Próxima chamada MCP do dev → HTTP 401 invalid_token
```

### Auditoria mensal (Wagner)
```
Wagner → /copiloto/admin/team → 📊 Export CSV → escolhe período
   ↓
Browser baixa: oimpresso-team-usage-{YYYYMMDD}.csv
   ↓
Wagner revisa: spike de custo? tool inesperada? IP estranho?
   ↓ se anomalia
Wagner → revoga + investiga
```

---

## Métricas de sucesso (revisitar em 30/60/90 dias)

- **30d**: 5 devs onboarded via `.dxt` em <2 min cada. Zero leak.
- **60d**: 100% das calls MCP têm `mcp_audit_log` row. Zero quota busts não-detectados.
- **90d**: 1ª revogação real testada (simulação ou caso real). Tempo: <30s do alerta à conta morta.

Se algum falhar → ADR follow-up + ajuste.

---

**Última atualização:** 2026-04-30
