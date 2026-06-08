---
name: smoke-prod-evidence
description: ATIVAR antes de declarar "funcionando", "smoke OK", "deploy ok", "está rodando" no oimpresso. Trigger por (a) user pergunta "está funcionando?" / "tá rodando?" / "verifica em prod" / "testa pra mim" / "smoke check"; (b) PR/commit acaba de ser mergeado e deploy SSH foi feito; (c) Edit em path crítico de runtime (`.htaccess`, `app/Http/Middleware/`, `app/Http/Kernel.php`, `routes/web.php`, `app/Providers/AppServiceProvider.php`); (d) trabalho envolve redirect, middleware, routing, cache, asset bundle, ou comportamento de servidor web (LiteSpeed/Apache/Nginx). Força evidência inequívoca via `curl -sv` (status code literal de cada hop) em vez de consequência observável compatível. Bane declarações guarda-chuva "✅ funcionando". Tier B auto-trigger por description. Origem: sessão 2026-05-17 — 3 PRs em cascata (#1024 #1026 #1028) causados por declarações precoces sem `curl -sv` real em prod.
trust_level: L1
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: ""
tier: B
parent_adr: 0095
---

# Smoke prod evidence — verificar com curl `-sv` antes de declarar

## Quando ativa

A skill dispara em **declarações de completude funcional**:

1. **User pergunta de estado**: "está funcionando?", "tá rodando?", "tá no ar?", "smoke", "verifica em prod", "testa pra mim"
2. **Pós-deploy**: você acabou de fazer `git pull` ou `git reset --hard` em Hostinger / CT 100 + `optimize:clear`. Antes de responder ao Wagner, **rode o protocolo**.
3. **Edit em path crítico de runtime**:
   - `.htaccess` (raiz ou `public/`)
   - `app/Http/Middleware/*.php`
   - `app/Http/Kernel.php` (middleware stack)
   - `routes/web.php` ou `routes/api.php`
   - `app/Providers/*ServiceProvider.php` (`URL::forceRootUrl`, `URL::forceScheme`)
   - `bootstrap/app.php`
4. **Domínio**: redirect, middleware, routing, cache, asset bundle, comportamento de servidor web (LiteSpeed/Apache/Nginx), OPcache, CSP, CORS.

## Regra de ouro

> **Status code literal de cada hop, no ambiente onde o fix vive. Sem `< HTTP/1.1 NNN` literal no output, é "indício compatível, não verificado".**

## Protocolo obrigatório (5 passos)

### 1. Lista discreta de casos antes do primeiro curl

Em vez de "vou rodar smoke", escreva primeiro a checklist do que **deve** acontecer:

```
[ ] /public/admin     → 301 com Location: /admin
[ ] /public bare      → 301 com Location: /
[ ] /publicly-foo     → NÃO 301 (não é match)
[ ] /admin canon      → 302 auth → /login (sem regressão)
[ ] /build-inertia/.. → 200 (assets não quebraram)
[ ] login flow real   → POST /login com cookie persiste
```

Sem essa lista, você vai rodar 1 curl e generalizar — exatamente o que causou os 3 PRs em cascata 2026-05-17.

### 2. `curl -sv` mostrando status de cada hop literal

**Errado** (consequência compatível, não evidência):

```bash
curl -s -o /dev/null -w "HTTP %{http_code} | redirects %{num_redirects} | final %{url_effective}\n" -L URL
# Output: HTTP 200 | redirects 1 | final /login
# Interpretação ENGANOSA: "minha 301 funcionando"
# Realidade possível: 302 Laravel auth, 301 .htaccess, OU 200 direto — todos compatíveis
```

**Certo** (status literal de cada hop):

```bash
curl -sv URL 2>&1 | grep '^< HTTP\|^< Location'
# Output exigido:
# < HTTP/1.1 301 Moved Permanently
# < Location: https://oimpresso.com/admin
```

Procure literalmente o número que você espera (`301`, `302`, `200`, `403`, `404`).

### 3. Ambiente correto (não confundir local com prod)

Diferenças conhecidas Hostinger-só:

| Diferença | Local Herd | Prod Hostinger |
|---|---|---|
| Servidor | Nginx | LiteSpeed |
| `.htaccess` | **ignorado** | aplicado |
| SCRIPT_NAME | vazio (ou `/index.php`) | `/public/index.php` |
| `Request::path()` | retorna URI completa | **strip basePath `/public`** |
| OPcache | desabilitado/permissivo | `validate_timestamps=1`, `revalidate_freq=2` |
| LSCache | n/a | pode servir HTML cacheado |

**Regra**: Pest verde + smoke Herd **não prova prod**. Toda decisão "funciona" exige `curl -sv https://oimpresso.com/...`.

**Test inválido conhecido**: `Request::create('https://...')` em Pest reproduz `path()` sem SCRIPT_NAME — falso positivo pra fix de middleware que olha path. Use `getRequestUri()` em código de middleware.

### 4. Regressão adjacente

Para cada path que MUDOU, teste 1-2 paths similares que NÃO devem ter mudado:

- Mudou `/public/*` → teste `/admin` (canon), `/publicly-*` (palavra parecida), `/login` (path comum)
- Mudou `/api/v1/*` → teste `/api/v2/*` (versão adjacente), `/web/*` (grupo diferente)
- Mudou Controller X → teste rotas Y, Z do mesmo módulo

Sem teste adjacente você pode quebrar features adjacentes e só descobrir via Wagner reclamar.

### 5. Frase honesta quando faltar evidência

Banidas:
- "✅ funcionando"
- "smoke OK"
- "tá rodando"
- "Sim" (resposta de uma palavra a "está funcionando?")
- "Pronto" sem cole de output

Permitidas:
- "Indício compatível com fix, prod não verificado ainda. Rodando smoke agora..."
- "Pest verde + smoke local. Prod pendente."
- Bloco discreto de casos com **status code colado** + 1 frase de conclusão

## Template de resposta canon

Após Edit/deploy em path crítico, **resposta ao user** deve conter:

```markdown
## Validação prod

\`\`\`
$ curl -sv https://oimpresso.com/public/admin 2>&1 | grep '^< HTTP\|^< Location'
< HTTP/1.1 301 Moved Permanently
< Location: https://oimpresso.com/admin

$ curl -sv https://oimpresso.com/publicly-foo 2>&1 | grep '^< HTTP'
< HTTP/1.1 404 Not Found

$ curl -sv https://oimpresso.com/admin 2>&1 | grep '^< HTTP\|^< Location'
< HTTP/1.1 302 Found
< Location: https://oimpresso.com/login
\`\`\`

| Caso | Esperado | Observado | OK |
|---|---|---|---|
| /public/admin | 301 → /admin | 301 → /admin | ✅ |
| /publicly-foo | 404 (negative) | 404 | ✅ |
| /admin canon | 302 → /login | 302 → /login | ✅ |

Resumo: fix aplicado, 3/3 casos verificados em prod.
```

## Anti-patterns catalogados (sessão 2026-05-17)

### Anti #1 — Interpretar `redirects > 0` como "minha regra disparou"

PR #1024 declarou `.htaccess` 301 funcionando porque `curl -L` retornou `redirects=1, final=/login`. Era 302 Laravel auth o tempo todo. Custou PR #1026.

**Fix**: status code literal de cada hop, não só do final.

### Anti #2 — Pest verde + smoke local = "funciona em prod"

PR #1026 mergeou com Pest 7/7 + smoke Herd. Em prod, middleware nunca disparou (SCRIPT_NAME strip). Custou PR #1028.

**Fix**: `Request::create()` em Pest não reproduz SCRIPT_NAME prod. Smoke prod **separado e obrigatório** após deploy.

### Anti #3 — Resposta de 1 palavra a "está funcionando?"

User pergunta meta sobre processo. Resposta "Sim" + 1 curl mascarado é guarda-chuva. Não tem cobertura nem evidência colada.

**Fix**: lista de N casos + bloco `curl -sv` colado + tabela.

## Como saber se está aplicado

- Sua última resposta contém `< HTTP/1.1` literal colado? Se não, falhou.
- Você listou pelo menos 3 casos (feliz + negativo + regressão) antes do primeiro curl? Se não, falhou.
- Você disse "funcionando" sem mostrar status code dos casos? Falhou.

## Refs

- Origem: sessão 2026-05-17 (PRs [#1024](https://github.com/wagnerra23/oimpresso.com/pull/1024) → [#1026](https://github.com/wagnerra23/oimpresso.com/pull/1026) → [#1028](https://github.com/wagnerra23/oimpresso.com/pull/1028))
- [proibicoes.md §Comportamento Claude](../../memory/proibicoes.md) — "Não usar tom inflado falso-confiante"
- [how-trabalhar.md §Reconhecer degradação de sessão](../../memory/how-trabalhar.md)
- Relacionada: skill `tela-smoke-pos-merge` (smoke visual UI), `commit-discipline` (1 PR = 1 intent)
