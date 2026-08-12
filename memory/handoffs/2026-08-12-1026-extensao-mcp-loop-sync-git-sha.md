---
date: "2026-08-12"
time: "10:26 BRT"
slug: extensao-mcp-loop-sync-git-sha
tldr: "Um print de 'extensão não conecta' terminou em: disco do CT 100 de 94% para 45%, o mcp:sync-memory fora de um loop perpétuo e a causa raiz do churn (git_sha que Hostinger e CT 100 apagavam um do outro). 3 PRs — #5663 e #5669 MERGED, #5672 verde aguardando. Seis hipóteses minhas caíram por medição, uma delas depois de eu anunciar 'causa raiz confirmada'. PENDENTE [W]: CACHE_DRIVER=array no CT 100, bloqueado 4x pelo classificador."
prs: [5663, 5669, 5672]
decided_by: [W]
related_adrs: [0053-mcp-server-governanca-como-produto, 0062-separacao-runtime-hostinger-ct100, 0358-doutrina-de-teste-tenant-98-supersede-0101]
next_steps:
  - "[W] trocar CACHE_DRIVER=array por file em /opt/oimpresso-mcp/code/.env + docker restart (bloqueado 4x pelo classificador; é o maior suspeito dos 5,2s do initialize)"
  - "[W] mergear #5672 (105 checks verdes) — sem ele o teste do git_sha não roda em lane nenhuma"
  - "Depois do #5663 estabilizar: voltar o timer oimpresso-git-sync de 6h pra cadência curta (6h é contenção, não cura)"
  - "Reativar a extensão MCP no Desktop e medir: se o initialize continuar em 5,2s, o gargalo é o overhead do endpoint, não mais o Meilisearch"
---

# A extensão que não conectava: 353ms por query e um `git_sha` que dois ambientes apagavam

Origem: print do Claude Desktop — *"Não foi possível conectar ao servidor da extensão"* — mais um toast do Windows-MCP caindo junto.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `sessions-recent limit:4` → índice com lag (devolve sessions de jul/ago indexadas hoje; nenhuma desta sessão)
- `gh pr view` → **#5663 MERGED** (12:40Z), **#5669 MERGED** (12:53Z), **#5672 OPEN** com 105 pass / 0 fail / 0 pending
- Base: branch nova de `origin/main` fresco a cada PR (o worktree estava 12 commits atrás no início)

## O diagnóstico, em ordem

A extensão **conectava** — o log dizia `Server started and connected successfully`. Ela **reiniciava em loop** porque cada resposta levava ~5,2s e o handshake estourava.

Três problemas independentes, que eu inicialmente tratei como um:

| # | Problema | Estado |
|---|---|---|
| 1 | Disco do CT 100 em **94%** (5,9 GB livres) | ✅ resolvido → **45%** (106 GB) |
| 2 | `mcp:sync-memory` em loop perpétuo (timer 5min × run de 1h40) | ✅ contido (timer 6h) + fix em `main` |
| 3 | `initialize` em 5,2s | ⚠️ **não resolvido** — ver "o que ficou" |

## Causa raiz do #2

`lerGitSha()` devolve `null` onde não há git — o **Hostinger, onde o webhook roda**. O código tratava isso como *"o SHA mudou"* e ainda gravava `null` sobre o SHA válido do CT 100. Os dois ambientes se desfaziam mutuamente: **2485 de 2488 docs "atualizados"** por passada sem mudança real, **99% com `git_sha` vazio**, índice inteiro reenviado ao Meilisearch a cada volta (100% de CPU contínuo).

Provado que o comando é correto isolando com `--only=feature`: 1º run 9 atualizados, 2º run **0**.

## Onde eu errei (para a próxima sessão não repetir)

Seis hipóteses derrubadas por medição — Meilisearch como causa da latência, `fillable`, drift de código, schema da coluna, `''` vs `null`, `--base` vazio. E duas afirmações erradas que precisaram de PR para corrigir:

1. **"tabela `mcp_memory_document_history` não existe — causa raiz conclusiva"** — era erro meu de digitação (singular); a tabela é `..._documents_history`, existe, 50.520 linhas.
2. **"não existe lane sqlite no CI"** — existe: é o job `PHP / Pest (Unit)`, que lê `.github/ci-sqlite-pest.list` (151 entradas). Grepei `sqlite` no **nome dos checks** e concluí ausência. É a classe §5 2026-07-28: *claim de ausência exige o dono do inventário*.

A causa real do teste não rodar era outra: **as lanes são catracas de allowlist** e ele não estava em nenhuma.

## O gargalo que sobrou (#3)

**353 ms por query trivial** — o MCP roda no CT 100, o MySQL é o do Hostinger. O `initialize` são ~15 queries em série. E **`cache.default = array`** (driver por-processo) faz toda requisição refazer tudo, além de tornar decorativo o `Cache::lock('mcp:sync-memory')`.

Trocar para `file` está bloqueado 4× pelo classificador — é decisão/execução de [W]. Não usar `redis`: o único disponível é o do Langfuse, com `allkeys-lru` e sem persistência.

## Prova de que a defesa morde

Lane `Jana · MySQL` do #5672, depois do registro na allowlist:

```
PASS Modules\Jana\Tests\Feature\Mcp\IndexarMemoryGitShaPreservaTest
✓ it não marca documento como atualizado quando só o git_sha ficou ilegível  0.21s
✓ it preserva git_sha existente quando a leitura do git falha                0.18s
```

Detalhe completo em [`memory/sessions/2026-08-12-extensao-mcp-loop-sync-git-sha.md`](../sessions/2026-08-12-extensao-mcp-loop-sync-git-sha.md).
