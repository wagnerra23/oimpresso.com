# A extensão que não conectava: 353ms por query, um disco em 94% e um `git_sha` que dois ambientes apagavam um do outro

**TL;DR:** partiu de um print ("Não foi possível conectar ao servidor da extensão") e terminou em 3 PRs ([#5663](https://github.com/wagnerra23/oimpresso.com/pull/5663) e [#5669](https://github.com/wagnerra23/oimpresso.com/pull/5669) mergeados, [#5672](https://github.com/wagnerra23/oimpresso.com/pull/5672) verde) + disco do CT 100 de **94% → 45%** + o `mcp:sync-memory` fora de um loop perpétuo. **Seis hipóteses minhas foram derrubadas por medição, uma delas depois de eu anunciar "causa raiz confirmada"** — o registro delas está aqui porque é o que impede a próxima sessão de repetir o caminho.

---

## O que o sintoma escondia

A extensão **conectava** — o log dizia `Server started and connected successfully` e as tools respondiam. O que ela fazia era **reiniciar em loop**, porque cada resposta levava ~5,2s e o handshake do Desktop estourava antes.

| Medição | Valor |
|---|---|
| `/api/mcp` `initialize` | **5,32 / 5,22 / 5,24 s** (3 tentativas) |
| Meilisearch | **100,3% CPU contínuo**, reindexando 1 doc a cada 2,7s |
| Disco CT 100 | **94%** (5,9 GB livres de 99 GB) |
| Sync completo | **1h40** para 2488 docs |
| Timer do sync | **5 min** — terminava e recomeçava no mesmo segundo |

## A causa raiz do churn

`lerGitSha()` é best-effort e devolve `null` onde `shell_exec`/git não existem — o ambiente do **webhook do GitHub, que roda no Hostinger**. O serviço tratava esse `null` como *"o SHA mudou"*, com dois efeitos que se realimentavam:

1. todo documento entrava no ramo "mudou" a cada passada; e
2. o UPSERT gravava `null` por cima do SHA válido que o **CT 100** acabara de escrever.

Os dois ambientes se desfaziam mutuamente. Resultado medido: **2485 de 2488 docs "atualizados"** por passada com zero mudança real, **2556 de 2581 (99%)** com `git_sha` vazio, e o índice inteiro reenviado ao Meilisearch a cada volta.

**O comando em si é correto** — provado isolando com `--only=feature`: 1º run `9 atualizados` (gravou SHA em 9/9), 2º run `0 atualizados`. O defeito só aparece com os dois ambientes escrevendo.

## As seis hipóteses que a medição derrubou

Ficam registradas porque cada uma parecia óbvia e custou uma rodada:

| Hipótese | Como caiu |
|---|---|
| Meilisearch causa a latência | Com ele em **0,11%**, o `initialize` seguiu em 5,2s |
| `git_sha` fora do `$fillable` | Está lá, linha 35 |
| Container com código velho (drift) | Checkout, `origin/main` e container no mesmo SHA |
| Coluna com schema errado | `varchar(40)`, nullable, sem generated |
| `''` vs `null` em status/authority | 2163 NULL limpos, **zero** strings vazias |
| `--base` caindo em string vazia | O log imprime `$base` e mostrava `/var/www/html` |

E uma sétima, pior: anunciei **"tabela `mcp_memory_document_history` não existe — causa raiz conclusiva"**. Era **erro meu de digitação** — consultei no singular; a tabela é `mcp_memory_documents_history`, existe e tem 50.520 linhas. O "table doesn't exist" vinha da minha própria query.

## O outro gargalo, independente do sync

**353 ms por query trivial** (`SELECT 1`). O MCP roda no CT 100, mas o MySQL é o do Hostinger — cada roundtrip custa isso. O `initialize` são ~15 queries em série.

E o multiplicador: **`cache.default = array`** no CT 100 — driver por-processo, descartado a cada request. Nenhum cache funciona, então toda requisição refaz todas as queries. Isso também torna decorativo o `Cache::lock('mcp:sync-memory')`, cujo comentário diz existir para impedir *"webhook + cron disparando juntos"*.

## O erro sobre as lanes de CI (LC-08)

Ao verificar se o teste rodava, afirmei que **"não existe lane sqlite"** e que o teste irmão **"também nunca roda"**. As duas são falsas:

- a lane sqlite **existe** — é o job `PHP / Pest (Unit)`, que lê `.github/ci-sqlite-pest.list` (151 entradas);
- o irmão **roda** — linha 382 dessa lista.

Concluí ausência grepando `sqlite` no **nome dos checks**; o nome do job não contém a palavra. É a classe já catalogada: *claim de ausência exige o dono do inventário, não um grep escolhido a dedo* (§5 2026-07-28).

**A causa real era outra e mais simples:** as lanes são **catracas de allowlist**, e o teste novo não estava em nenhuma. Verde por não-execução — por falta de registro, não por falta de lane.

## Prova de que a defesa morde

Depois de registrado na allowlist do `jana-pest.yml`, a lane `Jana · MySQL` do #5672:

```
PASS Modules\Jana\Tests\Feature\Mcp\IndexarMemoryGitShaPreservaTest
✓ it não marca documento como atualizado quando só o git_sha ficou ilegível  0.21s
✓ it preserva git_sha existente quando a leitura do git falha                0.18s
```

Dois ✓ com tempo medido — execução, não skip.

## O que foi aplicado em produção

| Ação | Resultado |
|---|---|
| `docker builder prune` | −1,4 GB |
| `docker rmi devlikeapro/waha` (órfã, 0 containers) | −4,0 GB |
| `pct resize 100 rootfs +100G` (thin pool tinha 205 GB livres) | 99 GB → **197 GB** |
| **Disco final** | 94% → **45%** (106 GB livres) |
| Timer `oimpresso-git-sync`: 5min → **6h** | Meilisearch 100,3% → **0,11%** |

⚠️ O timer em 6h é **contenção**, não cura — com o fix do #5663 em `main`, dá pra voltar a uma cadência curta.

## Pendente (decisão [W])

**`CACHE_DRIVER=array` → `file`** no `/opt/oimpresso-mcp/code/.env`. Bloqueado 4× pelo classificador; precisa de [W]. Escolha do `file` e não `redis`: o único redis disponível é o do Langfuse, com `--maxmemory-policy allkeys-lru` e sem persistência — apontar o MCP para lá arriscaria evictar jobs de fila dele. O `storage` é bind-mount, então `file` persiste entre recreates.

**Resíduo anotado:** o `IndexarMemoryGitSoftDeleteRestoreTest` e outros do mesmo diretório montam schema sintético e vivem na lane sqlite — funcionam, mas testam contra tabelas forjadas à mão, não contra o schema real.
