# [W]→[CL] · PR-6 — Sync Cowork→repo (o último passo pro zero-paste · ADR 0283)

> **Cole UMA vez no Claude Code do repo.** Fecha o Gap 1 do adversário: hoje o handoff só entra
> se um `.md` aterrissar em `prototipo-ui/handoffs/`. Quem põe lá ainda é o [W]. Este PR torna
> esse hop **automático e assinado** — sem o [W] computar HMAC nem colar na pasta à mão.
> **§10.4:** valide contra o `main` fresco; onde divergir, **main vence**.

## A verdade arquitetural (ler antes — não é pessimismo, é o contorno)
O **Cowork é read-only no GitHub** — não dá push. Então **o primeiro hop** (artefato do Cowork →
repo/servidor) **sempre** precisa de um gatilho fora do Cowork. PR-6 torna esse gatilho o mais fino,
seguro e automático possível — mas não inventa um push que o Cowork não tem. O honesto:
**PR-6 leva de "1 paste + assinar à mão" para "1 commit de arquivo, todo o resto automático"**, e
abre o caminho pra zero-toque real quando existir um publisher do lado Cowork.

## Premissa (✓ lido @main)
- `HandoffIngestCommand` lê `prototipo-ui/handoffs/*.md`, valida `sig = HMAC(body, HANDOFF_SECRET)`,
  insere `pending` (idempotente via `source_hash`).
- `cowork_handoffs` + tools + cron + **Forja surface (Fase 1)** já no `main`.
- Falta: o que **assina e dispara** sem o [W] no meio.

## Decisão de design — duas peças

### PR-6a · `handoff-submit` (landing pad assinado, sem depender do filesystem)
Tool/endpoint MCP que **recebe o handoff por HTTP** (não só por arquivo) e insere `pending`,
**reusando a validação do `HandoffIngestCommand`** (mesma checagem HMAC, mesmo `source_hash`,
mesmo append-only de versão). Assim o transporte não precisa de SSH no CT 100 nem de commit no repo.
- Auth: `mcp.auth` + scope `handoff.submit` (novo) — emitir só pro ator-transporte.
- Body: `{ slug, version, tela, files_json, body_md, sig, created_by }`. `sig` obrigatório; inválido → 401.
- Idempotente: `source_hash` igual → 200 no-op (não duplica).
- Audita em `mcp_audit_log` (tool, ator, slug, outcome).
- **Não muta nada além de inserir pending.** Sem auto-merge (0283).

### PR-6b · Transporte: **GitHub Action on-push** (recomendado)
Action no repo (tem o `HANDOFF_SECRET` como **repo secret**), dispara em push de
`prototipo-ui/handoffs/*.md`:
1. Para cada handoff novo/alterado **sem `sig` válida**, computa `sig = HMAC-SHA256(body, HANDOFF_SECRET)`.
   (O [W] nunca computa HMAC; o segredo vive só no Action e no servidor.)
2. Chama `handoff-submit` (PR-6a) com o payload assinado **ou** roda `php artisan handoff:ingest`
   no deploy. → insere `pending` → a Forja mostra na hora.
3. Grava/atualiza o heartbeat (`mcp_ingest_heartbeat`) — o empty-state da Forja já lê isso.
- **Resultado:** o "paste na pasta" vira "**um arquivo commitado**". Tudo depois é automático: assina,
  ingere, aparece na Forja, heartbeat pulsa.

> **Por que não cron-polling do Cowork (zero-toque puro)?** Precisaria de um endpoint **estável** do
> Cowork listando os handoffs pendentes. O Cowork não expõe isso (as URLs públicas são por-arquivo e
> efêmeras ~1h). Enquanto não houver um **publisher Cowork→repo**, o on-push é o piso real. Deixar
> como **chip de follow-up**: "publisher Cowork→repo (zero-toque)".

## O que [W] faz UMA VEZ
- `HANDOFF_SECRET` no **repo secret** do Action (o mesmo do `.env` do servidor — HMAC tem que bater).
- Emitir token scope `handoff.submit` pro ator-transporte (se usar PR-6a).

## Junto, fechar os 2 resíduos da Fase 1 (se trivial; senão = chips)
- **Levers (Gap 3):** ligar `re-disparar`/`devolver`/`supersede` nas tools MCP (hoje `disabled "em breve"`).
- **Gate `conflito` (Gap 2):** comparar `gate_status` do ack com os required checks reais do PR
  (GitHub API via `config/services.php`). Divergiu → badge `conflito`. Senão, segue `gate_status`.

## NÃO FAZER
- ❌ Auto-merge (0283 — merge é o 1-clique do [W]). ❌ `HANDOFF_SECRET` no Cowork/no Code/versionado.
- ❌ Prometer zero-toque sem o publisher Cowork→repo — é on-push até lá.
- ❌ Recriar ingest/tabela/tools — só ADICIONAR o submit + a Action.

## Pronto quando
Um `.md` commitado em `prototipo-ui/handoffs/` é assinado pela Action, vira `pending` (via
`handoff-submit`/`ingest`) e **aparece na Forja sem o [W] colar nada nem assinar nada**. Heartbeat pulsa.
Ao terminar: `[PROCESSADO AAAA-MM-DD]` + `CODE_NOTES.md`. Cowork read-only no git.
