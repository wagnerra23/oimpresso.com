# ACIONAMENTO ZERO-TOQUE — Construir o Loop de Handoff via MCP (s3)

> **[W]→[CL]** · Cole este bloco UMA VEZ na conversa do Claude Code plugada em
> `wagnerra23/oimpresso.com`. Ele puxa as specs por curl e implementa o loop que tira
> [W] do transporte. Decisão de [W] registrada: **sair do meio; o sistema vira o gate.**
> URLs assinadas (~1h de validade) — se expirar, peça ao Cowork pra regenerar.

---

## Passo 0 — Puxe as specs (fonte da verdade)

```bash
mkdir -p _handoff_spec
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/PROMPT_PARA_CODE_HANDOFF-LOOP-V2.md?t=20f2de5d9d3f4906903b7c17909fdc4727b7152c44a82a162a9dd27fe03d27ff.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781709919.fp&direct=1" -o _handoff_spec/LOOP-V2.md
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/memory/sprints/s3-handoff-mcp/01-tool-handoff-pending.md?t=996e0046cfa190205f75d80cd7e163bb501187eaa56ce3553b8eba25ec2e8fdb.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781709920.fp&direct=1" -o _handoff_spec/01-tool.md
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/memory/sprints/s3-handoff-mcp/02-adversario-handoff.md?t=1c694908ac624390adb70525089a23910f45fdcaabde685f334783a9017c604d.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781709921.fp&direct=1" -o _handoff_spec/02-adversario.md
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/memory/sprints/s3-handoff-mcp/03-ORDENS-handoff-loop.md?t=1b2ad0651a9e6b3c00c4c7cd953c025b1c1d831dd75ea94317c54a581e929905.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781709920.fp&direct=1" -o _handoff_spec/03-ORDENS.md
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/PROMPT_PARA_CODE_HANDOFF-INTEGRITY-GATE.md?t=0042d48c0cdc928f089be2d873d059b32d8dffcbf4f92053c283fe7978b6de33.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781709921.fp&direct=1" -o _handoff_spec/INTEGRITY-GATE.md
echo "--- specs baixadas:" && wc -l _handoff_spec/*.md
```

---

## Passo 1 — Leia o `main` ANTES (não confie na minha memória)
- `git fetch origin && git switch main && git pull`
- Confirme contra o repo real: `Modules/TeamMcp/Http/routes.php`, `Mcp/CcIngestController.php`,
  `Mcp/SyncMemoryWebhookController.php`, `McpTokenIssuer`, padrão do `brief-fetch`.
- **Se a spec contradiz o `main`, o `main` vence.** Reporte a divergência no PR, não force a spec.

## Passo 2 — Implemente o `LOOP-V2.md` em **4 PRs** (um por onda, nesta ordem)
1. **PR-1 — Persistência + ingestão assinada**: tabela `cowork_handoffs`, command `handoff:ingest`
   com verificação **HMAC** (fecha A1/RCE do adversário). SECRET vem de `config/teammcp.handoff_secret` (env) — nunca versionar.
2. **PR-2 — Tools MCP**: `handoff-pending` + `handoff-ack` no `routes.php` do TeamMcp, **espelhando o `brief-fetch`** (throttle, scope, audit_log). `ack` usa `Cache::forget` cirúrgico, **nunca** `Cache::flush()` (fecha A2).
3. **PR-3 — Escopo + auto-merge** (pré-requisito do zero-paste): `scope-guard` rejeita PR que toque arquivo fora de `files_json`; auto-merge **só** com assinatura válida + 3 gates verdes (conformance + critique≥80 + a11y AA). F4 humano nunca é pulado em Tier 0.
4. **PR-4 — Anti feedback-void**: `handoff-ack` exige `gate_status` verde + `pr_url`; `applied` sem gate verde → **422** (fecha A3, o "gate de mentira").

> Use o adversário (`02-adversario.md`) como checklist de aceite: cada P0/P1 dele tem uma asserção a passar.
> `INTEGRITY-GATE.md` detalha o scope-guard/assinatura — trate como anexo do PR-3.

## Passo 3 — Provas (Pest GUARD, sem isso não mergeia)
- `handoff:ingest` rejeita payload sem `sig` HMAC válida (teste negativo).
- PR de handoff que toca arquivo fora de `files_json` é barrado pelo scope-guard.
- `handoff-ack` com `status=applied` e `gate_status` não-verde retorna 422.
- `ack` não chama `Cache::flush()` em lugar nenhum (grep no diff).

## NÃO FAZER (anti-drift)
- ❌ Recriar TeamMcp/tokens/scopes/audit — **já existem**, só adicione as 2 tools.
- ❌ SECRET no Cowork ou no Code — vive só no env do servidor e no secret do pipeline.
- ❌ Auto-merge sem PR-3 verde — não ligue o zero-paste em produção antes disso.
- ❌ [CC] numerar ADR — [CL] numera sempre, sob OK de [W].

## O que [W] faz UMA VEZ pra ligar (depois dos 4 PRs verdes)
1. Gerar o **SECRET** → env do servidor (`teammcp.handoff_secret`) + secret do pipeline de export do Cowork.
2. Emitir **token MCP** scope `handoff.pending`+`handoff.ack` pro ator-Code (via `McpTokenIssuer`).
3. Confirmar limiares dos gates (default: conformance ratchet · critique≥80 · a11y AA).
4. (Norte) decidir o job de **sync Cowork→repo** — a peça que zera o último paste.

> Quando os 4 PRs estiverem verdes e o checklist de [W] feito: o handoff passa a fluir por
> `handoff-pending`/`handoff-ack` sem paste. Até lá, o transporte segue manual.
> NADA aqui está commitado — o Code resolve com este prompt.
