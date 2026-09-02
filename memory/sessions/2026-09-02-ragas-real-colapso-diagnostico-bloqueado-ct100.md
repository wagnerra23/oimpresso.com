---
date: "2026-09-02"
topic: "Diagnóstico do colapso do RAGAS real — BLOQUEADO no pré-requisito (CT 100 fora); o que foi medido sem ele e a bateria pronta"
authors: [C]
related_adrs:
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
  - 0062-separacao-runtime-hostinger-ct100
us: [US-COPI-133, US-COPI-136, US-COPI-140]
---

# Colapso do RAGAS real — diagnóstico BLOQUEADO, e o que dá pra afirmar sem o CT 100

## TL;DR

- **Pré-requisito NÃO satisfeito.** O CT 100 estava fora no momento desta sessão, e com ele
  **o host inteiro**: `ct100-mcp`, `pve-empresa` (o Proxmox) e `recorder` apareceram os três
  `offline, last seen 39m ago` no `tailscale status`. Ping ao IP do tailnet: 3/3 perdidos.
  Consistente com queda de energia/rede no site, não com falha do container.
- **Nenhuma medição de índice, nenhuma bissecção.** Teste e query rodam no CT 100 (ADR 0062).
  Não substituí por medição local nem por leitura de log.
- **Causa NÃO nomeada** — segue sem recibo, como manda o §5 2026-07-15.
- O que esta sessão **acrescenta** ao que a [session de 2026-08-31](2026-08-31-ragas-obra-parada-veredito-b.md)
  já tinha achado: três fatos medidos do repo que **estreitam** o espaço de causas, e uma bateria
  de medição escrita contra o mecanismo real (não contra o palpite).

## 1. O bloqueio, medido

```
tailscale status
100.99.207.66   ct100-mcp     tagged-devices  linux  active; relay "sao"; offline, last seen 39m ago, tx 4524 rx 0
100.116.24.69   pve-empresa   wagnerra@       linux   offline, last seen 39m ago
100.78.127.87   recorder      tagged-devices  linux   offline, last seen 39m ago

ping 100.99.207.66  ->  Pacotes: Enviados = 3, Recebidos = 0, Perdidos = 3 (100% de perda)
tailscale ssh root@ct100-mcp  ->  502 Bad Gateway / dial tcp 100.99.207.66:22: timeout
```

`tx 4524 rx 0` no ct100-mcp: saiu tráfego nosso, não voltou nada. Os três nós do site caíram
na mesma janela — por isso o diagnóstico do bloqueio é "site", não "container".

Reconferido ~40min depois, no fim da sessão: o `ct100-mcp` passou a `last seen 7m ago` com
`rx 2568` (voltou a receber), enquanto o `pve-empresa` seguia `offline, last seen 47m ago`. O
SSH, porém, **continuou falhando** com o mesmo 502/timeout. Ou seja: presença no tailnet não é
disponibilidade — o nó pisca no relay antes de o serviço subir.

⚠️ **Armadilha medida, para quem for automatizar o check de retorno:** o
`tailscale ssh root@ct100-mcp` **saiu com `rc=0` mesmo tendo falhado** (a mensagem de erro vai
para a saída, não para o código). Um watchdog que teste retorno por exit code vai ler a queda
como sucesso. O oráculo aqui é a **saída** (ou a consequência: um `echo` sentinela que precisa
aparecer), nunca o `rc`. Mesma família do §5 2026-07-31 e 2026-08-01.

## 2. Três fatos medidos que estreitam o espaço de causas

### 2.1 Não é só o recall — as TRÊS métricas colapsaram juntas

Lido de `governance/ragas-real-trend.json` na órfã `governance/ragas-real-trend`
(último commit `79953d6311`, 2026-08-23). A coluna `relevancy` não aparecia no enunciado:

| semana | status | n_eval | no_context | faithfulness | relevancy | context_recall |
|---|---|---|---|---|---|---|
| 2026-06-28 | pass | 51 | 0 | 0,7145 | 0,8745 | 0,3951 |
| 2026-07-19 | pass | 51 | 0 | 0,7127 | 0,8255 | 0,4016 |
| 2026-07-26 | fail | 51 | 0 | 0,6865 | 0,8294 | 0,3461 |
| 2026-08-02 | skipped | 0 | **51** | — | — | — |
| 2026-08-09 | fail | 50 | 1 | 0,303 | **0,412** | 0,043 |
| 2026-08-16 | fail | 50 | 1 | 0,2748 | **0,372** | 0,0314 |
| 2026-08-23 | skipped | 0 | **51** | — | — | — |

Por que importa: `answer_relevancy` é julgado **sem o contexto** —
`scoreAnswerRelevancy($question, $answer)`, dois argumentos, na FASE 3 de
`JanaRagasRealEvalCommand`. Uma falha isolada de *retrieval* deprimiria `context_recall` e
arrastaria `faithfulness`, mas não teria por que derrubar `relevancy` de 0,83 para 0,41 —
a menos que **a resposta sintetizada também tenha degradado**, que é o que acontece quando a
síntese recebe contexto vazio ou inútil e responde "não encontrei". Isso é compatível com
corpus/contexto degradado a montante. Não prova qual.

### 2.2 Duas semanas ausentes ANTES do colapso, não mencionadas em lugar nenhum

`first_scheduled: 2026-07-05`, mas a série pula **2026-07-05 e 2026-07-12**. O invocador
(cron dom 06:00, #4426) foi instalado em 2026-07-17, e a errata `_errata_2026_08_31_invocador_existe`
do baseline registra que o eval passou a rodar sozinho a partir de 07-19 — as duas ausentes são
anteriores ao invocador e têm explicação. Fica registrado porque a série tem **7 pontos, não 9**,
e qualquer contagem de "N semanas seguidas" precisa saber disso.

### 2.3 Os dois suspeitos citados entraram DEPOIS do primeiro skip

Os runs saem 06:00 BRT = **09:00Z**. Datas de merge pelo `gh`:

| item | merged (UTC) | posição vs run de 2026-08-02 09:00Z |
|---|---|---|
| run 2026-08-02 (`no_context=51`) | — | — |
| #5169 `redactor apagava run id do GitHub achando que era CPF` | 2026-08-02T19:43Z | **+10h41 depois** |
| #5193 `redactor do INDEXADOR ainda apagava run id — #5169 não alcançava o sync` | 2026-08-03T03:29Z | **+18h26 depois** |
| #6525 `gold-set citava 3 caminhos que não existem` | 2026-09-01T21:04Z | +30 dias depois |

Leitura honesta, e ela corta nos dois sentidos:

- **#5169/#5193 não explicam o skip de 08-02** — ele já tinha acontecido quando o primeiro
  mergeou. E os dois são **conserto** do redactor, não sua introdução: se o redactor danificou
  corpus, o dano é de **antes** deles.
- **Mas isso não os inocenta do que veio depois.** Um fix no redactor **não reindexa
  retroativamente**: doc já gravado com corpo apagado continua apagado até re-sync. Isso é
  compatível com 08-09/08-16 seguirem no chão *depois* do conserto. Compatível não é provado.
- **#6525 está fora da janela inteira** (2026-09-01). Não pode ter causado nada entre 07-26 e
  08-23; só poderia afetar runs futuras.

## 3. Por que a hipótese é mensurável por SQL (e não precisa de adivinhação)

O caminho que produz `no_context` é curto — `KbAnswerService::retrieve()`, cujo final é
FULLTEXT em MySQL:

```sql
MATCH(title, content_md) AGAINST(? IN NATURAL LANGUAGE MODE)
```

com `porStatusAtivo(false)` antes, que só deixa passar `metadata.status` NULL ou em
(`aceito`, `accepted`, `accepted-historical`, `recusado`).

Isso dá **três** superfícies que produzem exatamente o sintoma observado, cada uma com sua
consulta:

1. **`content_md` esvaziado** — o MATCH passa a depender só de `title`, então cai tanto o
   número de hits (leva a `no_context`) quanto a qualidade do contexto quando há hit (leva às
   três métricas). A tabela tem a coluna **`pii_redactions_count`**, que torna isso contável.
2. **`metadata.status` fora da allowlist** — docs somem do corpus efetivo sem nada ser apagado.
   Um carimbo em massa de status produz o mesmo sintoma.
3. **Índice FULLTEXT ausente/quebrado**, ou tabela vazia — `no_context` para tudo.

Âncora de comparação: **1153 docs** em `mcp_memory_documents` no CT 100 staging em 2026-07-01
(`_meta.ambiente` do baseline).

## 4. Bateria pronta (rodar quando o CT 100 voltar, nesta ordem)

Ordem escolhida por custo: as quatro primeiras não gastam LLM; a comparação de reports vem
depois. Abrir a sessão uma vez e rodar dentro dela evita o quoting aninhado que já quebrou uma
escrita nesta sessão (§5 2026-08-19).

```bash
tailscale status | grep ct100-mcp
tailscale ssh root@ct100-mcp
```

**4.1 — o corpus existe, e o corpo dos docs está lá?** (decide as hipóteses 1 e 2 de uma vez)

```sql
SELECT COUNT(*) AS total,
       SUM(content_md IS NULL OR CHAR_LENGTH(content_md) < 200) AS corpo_curto,
       SUM(pii_redactions_count > 0) AS com_redacao,
       MAX(pii_redactions_count)     AS max_redacao
FROM mcp_memory_documents;

SELECT JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.status')) AS status, COUNT(*) AS n
FROM mcp_memory_documents GROUP BY status ORDER BY n DESC;
```

Comparar `total` com **1153**. `corpo_curto` alto aponta a hipótese 1; muitos docs com status
fora da allowlist apontam a hipótese 2.

**4.2 — o índice FULLTEXT existe?**

```sql
SHOW INDEX FROM mcp_memory_documents WHERE Index_type = 'FULLTEXT';
```

**4.3 — o retriever devolve algo para uma pergunta REAL do gold-set?**

Mede o mecanismo (o mesmo `retrieve` que o eval chama), não uma query inventada. Gravar como
`/tmp/sonda.php` no container e rodar com `php artisan tinker < /tmp/sonda.php`:

```php
$gold = json_decode(file_get_contents(base_path('Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json')), true);
$svc = app(Modules\Jana\Services\Kb\KbAnswerService::class);
foreach (array_slice($gold['questions'], 0, 3) as $q) {
    $status = null;
    $docs = $svc->retrieve(null, $q['question'], 'all', '', 10, $status);
    echo PHP_EOL . 'P: ' . mb_substr($q['question'], 0, 80) . PHP_EOL;
    echo '   status=' . $status . ' n_docs=' . $docs->count() . PHP_EOL;
    foreach ($docs as $d) {
        echo '   - ' . $d->slug . ' len(content_md)=' . strlen((string) $d->content_md) . PHP_EOL;
    }
}
```

`status = fulltext_degradado` indica que o hybrid/Meilisearch caiu. `len(content_md)=0`
confirma a hipótese 1. `n_docs=0` com corpus cheio joga o eixo para 4.2/4.4.

**4.4 — Meilisearch e embedder vivos?**

```bash
curl -s localhost:7700/indexes/mcp_memory_documents/stats
curl -s localhost:11434/api/tags | head -c 400
```

**4.5 — a flag do hybrid está ligada?** (perguntar ao runtime, não ao `.env` — §5 2026-07-17)

```bash
docker exec oimpresso-staging php artisan tinker --execute="var_dump(config('copiloto.mcp_search.docs_pipeline'));"
```

**4.6 — só então comparar os reports semanais já gravados** (barato, sem LLM)

```bash
docker exec oimpresso-staging ls -la storage/app/governance/ | grep ragas
```

## 5. O que NÃO foi feito, e por quê

- **Não medi o índice nem bissectei** — o CT 100 é o único lugar autorizado (ADR 0062), e ele
  estava fora. Não inventei substituto local.
- **Não nomeei causa.** As três hipóteses da §3 estão ordenadas por quanto explicam do padrão
  observado, não por confiança. Nenhuma tem recibo.
- **Não toquei `thresholds_regressao`.** Os pisos seguem 0,65 / 0,75 / 0,36 — o vermelho é o achado.
- **Não abri PR de conserto.** Sai depois da medição, separado, com antes→depois do eval.

## 6. Próximo passo

Quando o chip do retorno do CT 100 fechar: rodar a bateria da §4 na ordem, e só então nomear
causa — com o número ao lado. Se a §4.1 devolver `total` perto de 1153 e `corpo_curto` baixo,
as hipóteses 1 e 2 caem juntas e o eixo passa a ser Meilisearch/embedder (§4.4).
