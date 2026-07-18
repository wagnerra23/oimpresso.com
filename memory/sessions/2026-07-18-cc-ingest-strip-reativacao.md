---
date: "2026-07-18"
hour: "20:50 BRT"
topic: "MEM-CC-1: fix do validate-strip do /api/cc/ingest + reativação fresh-only do watcher + enrichment model (G5) + alarme anti-SPOF"
authors: [C, W]
prs: [4503, 4517, 4519]
outcomes:
  - "strip fix live-provado em prod (rows deixam de nascer skeleton)"
  - "watcher reativado fresh-only (441 skip / 404 msgs / 0 erro) + persistência no logon"
  - "coluna model + watcher envia model (input do G5 pricing)"
  - "IngestLivenessChecker fecha o loop anti-SPOF no governance:audit"
---

# MEM-CC-1: o pipe que nunca funcionou

## O gatilho

Task spawnada durante o spec da cura do G5 (custo-por-PR): smoke na DB prod mostrou `mcp_cc_messages` congelado em **30/abr** (17.686 rows, todos `user_id=1`, 0 nos últimos 7 dias). O produto do MEM-CC-1 (`cc-search` cross-dev) servia dado de ~2,5 meses atrás e ninguém percebeu.

## A investigação (e o crivo que a salvou)

Diagnóstico inicial foi **de leitura** — e Wagner respondeu à minha proposta de reativar com **"adversário"**: rodar o imunológico do §5 sobre as MINHAS conclusões antes de agir. Spawneei 3 céticos contexto-zero (lente servidor / lente dados / lente causa-raiz). **Corrigiram 4 erros**, todos re-verificados firsthand depois:

1. Olhei o watcher da *skill* (`.cc-watcher/`), não o **commitado** (`scripts/cc-watcher/index.js`) — que aponta pra **Hostinger**, não mcp.oimpresso.com.
2. Limpei o servidor "está ok" pelo motivo errado (os 404 eram de mcp.oimpresso.com, alvo errado).
3. O buraco central: o endpoint **stripava todos os campos de mensagem** via `$request->validate()` (`excludeUnvalidatedArrayKeys`). Provado na DB: **content_text=0, content_json=0, tokens=0 em TODAS as 17.686 rows** → `cc-search` FULLTEXT nunca teve conteúdo. **MEM-CC-1 nunca funcionou de verdade.**
4. Re-backfill não recupera (dedup + strip + fontes de abril rotacionadas).

O freeze do watcher também foi provado firsthand: os 17.686 rows entraram numa **janela de 88 segundos** (min_ts→max_ts) = backfill one-shot, nunca daemon.

## As entregas

- **#4503** — fix do strip (lê `input('messages')` cru) + regressão era-sqlite + lane sqlite. Mergeado, deployado, e **smoke autenticado em prod provou live** (row com content_text + tokens). Ironia: o deploy ficou **vermelho** (passo OPcache-reset flaky, pré-existente), mas o fix subiu via revalidação-por-timestamp do LiteSpeed — a smoke provou, não a cor do deploy.
- **Reativação fresh-only** (aprovada por [W]): seed do checkpoint marca 444 arquivos como já-vistos (o 1,3 GB de backlog fica fora); `--once` confirmou **441 skip / 404 msgs frescas / 0 erro**; daemon `--watch` + launcher no Startup (Task Scheduler deu Acesso Negado sem elevação).
- **#4517** — coluna `model` + watcher envia `row.message.model`: o G5 precisa do modelo pra precificar tokens (antes só sobrevivia em content_json, que era stripado).
- **#4519** — `IngestLivenessChecker`: o heartbeat (ADR 0278) e o reader já existiam, mas nada ALARMAVA quando o watcher morria. Agora o `governance:audit` grita quando há hosts mas nenhum fresco (reusa `IngestLivenessService`, não duplica a régua; blind≠dead não cria lobo).

## O que sobrou

- **mcp.oimpresso.com 404** — 2ª incidência achada de passagem: Traefik perdeu o router pro `oimpresso-mcp` (app 200 internamente). Infra CT100. É por isso que brief-fetch/MCP tools falharam a sessão toda.
- **Deploy OPcache-reset flaky** (ADR 0269) — falha ~metade dos deploys.
- **Deploy #4517 pending** no fechamento — confirmar que a migration da coluna `model` aplicou no Hostinger.

## Lições

- O §5 "achado de leitura ≠ fato" (2026-07-15) **mordeu a meu favor**: só declarei conclusão depois de varredura contada + verificação firsthand na DB/curl/git. O crivo adversarial pagou 4 correções.
- Teste era-sqlite: registrar em `ci-sqlite-pest.list` (senão skip silencioso = falsa cobertura) + `SoftDeletes` exige `deleted_at` na tabela sintética. PHPStan pega `?? 0` em array-de-chaves-fixas.
- Ferramentas fora do worktree: editei `scripts/cc-watcher/index.js` no repo MAIN por engano (path absoluto) — revertido + refeito no worktree (R8).
