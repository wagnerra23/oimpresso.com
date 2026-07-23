---
id: requisitos-jana-retrieval-gotchas
---

# Retrieval Gotchas — armadilhas que custaram horas (Sprint 9)

> **Lê este doc ANTES de mexer em retrieval/Meilisearch/Ollama/Scout neste projeto.**
> Cada item abaixo já queimou ≥30min de debugging. Não repita.

---

## 1. Modelo de embedding tem idioma dominante — verificar SEMPRE

**Sintoma:** cosine similarity ~0.97 uniforme entre TODOS os documentos em queries PT-BR.
**Causa:** `nomic-embed-text` é treinado predominantemente em inglês — projeta PT-BR num
cluster denso indistinguível. NÃO é bug de configuração.
**Custou:** Sprint 9 inteiro (score 0.66 → 0.158).

### Como evitar

Antes de instalar qualquer embedding model, verificar **MTEB Multilingual** ou **MMTEB**.
Se PT-BR não está explicitamente listado, **assumir que não funciona**.

Modelos validados PT-BR (mai/2026):
- ✅ `qwen3-embedding:4b` (#1 MTEB multilingual, 100+ langs com PT-BR explícito)
- ✅ `multilingual-e5-large-instruct` (#1 MMTEB ICLR 2025)
- ❌ `nomic-embed-text`, `mxbai-embed-large`, `bge-m3` (PT-BR fraco/zero)

### Teste rápido pós-instalação

```bash
# 2 docs MUITO diferentes devem ter cosine < 0.9
# Se cosine > 0.95 entre docs aleatórios = modelo não funciona pro idioma
```

---

## 2. Scout observer dispara em **qualquer** `model->update()`

**Sintoma:** Ollama recebe 383 requests de embedding a cada `mcp:sync-memory`,
mesmo quando NENHUM arquivo mudou.
**Causa:** `$doc->update(['indexed_at' => now()])` no branch "sem mudança" do
`IndexarMemoryGitParaDb` dispara evento Eloquent `updated` → Scout observer
captura → re-embedding.
**Custo:** muito CPU/VRAM no Ollama, lentidão geral, custo zero financeiro mas
cache miss desnecessário.

### Como evitar

Para updates de campos de controle (`indexed_at`, métricas, contadores) que não
afetam o índice de busca, sempre wrappar em `withoutSyncingToSearch`:

```php
// ❌ ERRADO — dispara Scout observer
$doc->update(['indexed_at' => now()]);

// ✅ CORRETO — não dispara Scout
McpMemoryDocument::withoutSyncingToSearch(fn () => $doc->update(['indexed_at' => now()]));
```

---

## 3. Meilisearch v1.43+ tem SSRF protection — bloqueia IPs privados

**Sintoma:** embedder Meilisearch falha com `"bad uri: Rejected URI"` quando URL
aponta pra `172.x`, `192.168.x`, `10.x`, ou hostnames Docker.
**Causa:** SSRF protection adicionada em versões recentes do Meilisearch.

### Como evitar

Adicionar no `compose.yml` do Meilisearch:
```yaml
environment:
  MEILI_EXPERIMENTAL_ALLOWED_IP_NETWORKS: "172.16.0.0/12,192.168.0.0/16,10.0.0.0/8"
```

Após isso, hostnames Docker (`http://ollama-embedder:11434`) funcionam.

---

## 4. Hybrid com `semanticRatio=0.0` ≠ keyword puro

**Sintoma:** mesmo com `semanticRatio=0.0`, Meilisearch ranqueia diferente
de quando o param `hybrid` é omitido.
**Causa:** quando `hybrid` está presente, Meilisearch usa pipeline de RRF mesmo
com ratio 0 — o score é normalizado de forma diferente do BM25 puro.

### Como evitar

Se semanticRatio for muito baixo (< 0.25), **não enviar param `hybrid`**:
```php
// Pra ratio < 0.25, considerar pular Meilisearch e usar MySQL FT direto
// (ver EvalRagasBaselineCommand::retrieveKbContext linha ~210)
```

---

## 5. Meilisearch BM25 sem stopwords PT-BR perde pra MySQL FT NATURAL LANGUAGE

**Sintoma:** Meilisearch keyword retorna documento errado (ex.: CHANGELOG longo)
antes do documento certo (ex.: ADR específica).
**Causa:** BM25 do Meilisearch satura com alta frequência de termos. CHANGELOG
acumula "format_date", "shift", etc. de muitas entradas. MySQL FT NATURAL
LANGUAGE usa IDF puro — raridade de termo importa mais.

### Como evitar

Antes de comparar Meilisearch keyword vs MySQL FT, **configurar Meilisearch pra PT-BR**:
1. Stopwords PT-BR (lista canônica em [`RETRIEVAL-ESTADO-ARTE-2026-05.md`](./RETRIEVAL-ESTADO-ARTE-2026-05.md))
2. `localizedAttributes` com `locales: ["por"]`
3. Verificar `searchableAttributes` — ordem importa (primeiro tem peso maior)

Sem isso, "que", "o", "em", "para" entram no ranking BM25 e poluem.

---

## 6. `scout:import` re-embeda TUDO — usar `mcp:sync-memory` (incremental)

**Sintoma:** `php artisan scout:import` manda 383 docs pro Ollama de uma vez,
mesmo que nada tenha mudado.
**Causa:** `scout:import` é bulk reimport, não tem checksum.

### Como evitar

- **Setup inicial OU mudança de embedder/model**: usar `scout:import` (intencional)
- **Sync rotineiro git→DB→Meilisearch**: usar `php artisan mcp:sync-memory`
  - Já implementa checksum via `git_sha`
  - Pós Sprint 9: também usa `withoutSyncingToSearch` pra atualizar `indexed_at`
    sem disparar re-embedding

---

## 7. Jump de Meilisearch > 5 versões = dump+wipe obrigatório

**Sintoma:** Meilisearch v1.43.0 não inicia depois de upgrade direto de v1.10.3
(formato DB incompatível).
**Causa:** Meilisearch garante backward compat só por algumas versões.

### Como evitar

Pra jumps grandes:
1. `POST /dumps` (safety net) — opcional se dados podem ser re-importados
2. Parar container
3. **Apagar volume** de dados (`docker volume rm meilisearch-data`)
4. Subir nova versão (volume novo)
5. Re-aplicar settings (embedder, filterable, searchable, stopwords...)
6. `php artisan scout:import` pra reimportar

Pra incremental upgrades (1-2 versões), `--upgrade` flag normalmente funciona.

---

## 8. `content_excerpt` deve strippar frontmatter YAML antes do `mb_substr`

**Sintoma:** todos os ADRs geram embeddings semanticamente similares
(cosine alto entre eles).
**Causa:** ADRs começam com 200-400 chars de frontmatter YAML
(`---\nslug:...\n---`). Se o `content_excerpt` é só `mb_substr($content_md, 0, 400)`,
o excerpt vira só YAML estrutural — sem semântica do documento.

### Como evitar

Em `toSearchableArray()`:
```php
$body    = preg_replace('/^\s*---\n.*?\n---\n?/s', '', $this->content_md ?? '');
$excerpt = mb_substr(trim($body), 0, 400);
```

---

## 9. `--category=` empty string é falsy em PHP

**Sintoma:** `php artisan eval:ragas-baseline --category=adr` não filtra,
roda todas as 30 perguntas.
**Causa:** `if ($cat = $this->option('category'))` — empty string `""` é falsy,
então o filtro é pulado.

### Como evitar

- Sempre `category:` no frontmatter da pergunta no `golden-questions.yaml`
- Verificar com `--category=adr --question=format-date-shift` antes de eval em massa

---

## 10. Meilisearch filter — aspas simples dentro de double-quoted PHP string

**Sintoma:** `ApiException` ou parse error ao filtrar `status NOT IN [...]`.
**Causa:** Meilisearch espera aspas simples ou duplas pra strings no filter,
mas o PHP precisa escapar.

### Como evitar

```php
// ❌ ERRADO — aspas duplas em double-quoted PHP
$params['filter'] = "status NOT IN [\"superseded\", \"deprecated\"]";

// ✅ CORRETO — aspas simples por dentro
$params['filter'] = "status NOT IN ['superseded', 'deprecated', 'rascunho']";
```

---

## 11. Telescope errors floodam stdout/stderr — separar saída

**Sintoma:** ao rodar `php artisan` no CT 100, output da tabela RAGAS fica
sufocado por stack traces de `telescope_entries` table missing.
**Causa:** `telescope_entries` é tabela do Hostinger (CT 100 não tem), mas
Telescope tenta inserir em background.

### Como evitar

```bash
# Salvar saída em arquivo dentro do container
php artisan eval:ragas-baseline ... > /tmp/eval.txt 2>/dev/null
# Depois ler:
docker exec oimpresso-mcp grep -E '(table-row-pattern)' /tmp/eval.txt
```

OU desabilitar Telescope no `.env` do container:
```
TELESCOPE_ENABLED=false
```

---

## 12. CT 100 — caminho do código e SSH key

**Sintoma:** `bash: php: command not found`, ou `Permission denied (publickey)`.
**Causa:** PHP só existe DENTRO do container `oimpresso-mcp`. Host CT 100
não tem PHP. SSH key correta é `id_ed25519_oimpresso` (sem sufixo).

### Como evitar

```bash
# ✅ Acesso correto:
ssh -i ~/.ssh/id_ed25519_oimpresso root@192.168.0.50

# Bind mount: host /opt/oimpresso-mcp/code ↔ container /var/www/html
# Editar arquivos: git pull no host (afeta container automaticamente)
# Rodar artisan: docker exec oimpresso-mcp php artisan ...
```

---

## 13. Tasks Meilisearch travadas em SSRF retry loop

**Sintoma:** novas tasks (settings updates, document additions) ficam pendentes
indefinidamente.
**Causa:** task antiga falhou mas Meilisearch retry-a infinitamente, bloqueando
toda a queue.

### Como evitar

```bash
# Stop Meilisearch
docker stop meilisearch
# Apagar diretório de tasks (volume bind ou Alpine container)
docker run --rm -v meilisearch-data:/data alpine rm -rf /data/tasks
# Restart
docker start meilisearch
```

Aplicar **depois** de corrigir a causa-raiz da falha (ex.: SSRF, embedder URL inválida).

---

## Checklist pré-mudança em retrieval

Antes de aplicar qualquer mudança em retrieval (modelo, ratio, settings):

- [ ] RAGAS baseline atual está medido e documentado
- [ ] Modelo de embedding tem PT-BR documentado em MTEB/MMTEB?
- [ ] Updates de controle (`indexed_at`, etc.) estão em `withoutSyncingToSearch`?
- [ ] Stopwords PT-BR + `localizedAttributes` aplicados no Meilisearch?
- [ ] Settings JSON salvo em backup antes de PATCH (rollback)
- [ ] Test 1-pergunta antes de eval completo: `--question=X`
- [ ] Logs do Ollama checados pra confirmar requests de embedding chegando
