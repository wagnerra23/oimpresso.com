# LIGAR O LOOP — runbook (Fase 1→2 · ADR 0283)

> Um documento, de cima a baixo, liga o loop de handoff. **Parte A é só [W]** (a chave soberana —
> nunca um agente). **Parte B cola no Claude Code.** Siga na ordem.

---

## PARTE A — só [W], no SEU terminal (a chave soberana) · UM PASTE
> **Por que não é prompt pro Code:** se o Code gerar o `HANDOFF_SECRET`, ele fica no log/sessão do
> agente = chave comprometida. A soberania do 0283 é literal: **só você gera, só você guarda.**
> **Não dá pra deletar essa etapa:** sem o segredo não há HMAC, e o A1 do adversário (injeção no seu
> repo) reabre. O que dá é fazer **um paste só**, que instala nos dois lados sem nunca imprimir o valor.

**Use o script `setup-handoff-secret.sh`** (no `prototipo-ui-patch/`):
1. Abra-o e edite os **3 valores** do bloco `EDITE UMA VEZ` (SSH do servidor, caminho do `.env`, repo).
2. Rode:
```bash
bash setup-handoff-secret.sh
```
Ele **gera** a chave, **instala no `.env` do servidor** (via SSH, roda `config:clear`) e **seta o repo
secret** (via `gh`) — tudo de uma vez, **mesmo valor nos dois lados, nunca impresso na tela**.

**Pré-requisito:** `ssh` configurado pro app server + `gh auth status` ok (o Code já usa o `gh`, então está autenticado).
**Se não tiver SSH:** faça o passo do servidor pela hora do painel da Hostinger (adicione `HANDOFF_SECRET=<valor>` no `.env` e `php artisan config:clear`), e rode só o `gh secret set` do script.

---

## PARTE B — cola no Claude Code (tudo que NÃO é a chave)
> Cole o bloco abaixo na conversa do Code do repo. Ele faz o PR-6 (sync) + a verificação
> end-to-end + o teste do alarme da sentinela. **§10.4: valide contra o `main`; main vence.**

```
Você é o Claude Code [CL] no repo wagnerra23/oimpresso.com, base main. Execute, em ondas (1 PR cada),
validando contra o main antes. NÃO gere nem leia o HANDOFF_SECRET (é soberania do [W]); assuma que ele
já está no .env do servidor e como repo secret.

ONDA 1 — PR-6 (sync on-push). Implemente conforme:
curl -sL "<COLE_AQUI_A_URL_DO_PR6>"
Resumo: handoff-submit assinado (reusa validação HMAC do HandoffIngestCommand) + Action on-push em
prototipo-ui/handoffs/*.md que assina com o repo secret HANDOFF_SECRET e dispara o submit/ingest.
SEM auto-merge (0283). Pronto quando: um .md commitado vira pending sem [W] colar/assinar nada.

ONDA 2 — Teste do alarme da sentinela (fecha o lado não-provado).
Force deployed_commit.txt ≠ main HEAD uma vez e confirme que a sentinela ALARMA (não só "no drift").
Reverta. Registre o resultado em CODE_NOTES.md. Sem isso, a sentinela está provada só no lado feliz.

ONDA 3 — Verificação end-to-end do loop (com a chave já setada pelo [W]).
Ingira um handoff de teste assinado, confirme status=pending em cowork_handoffs, e que ele APARECE na
aba MCP da Forja (ForjaMcpService) com heartbeat pulsando. Se a chave não bater, o ingest aborta no sig
— reporte (não invente sucesso).

ONDA 4 (opcional) — Fase 2 da superfície: ligar as levers (re-disparar/devolver/supersede) nas tools
MCP (hoje disabled "em breve") + gate "conflito" (comparar gate_status com os required checks reais do PR).

Ao fim de cada onda: [PROCESSADO AAAA-MM-DD] + retorno em CODE_NOTES.md. Cowork read-only no git.
```

---

## Ordem certa (importante)
1. **Parte A primeiro** (a chave). Sem ela, a Onda 3 do Code aborta no `sig`.
2. **Parte B** depois. O Code faz o resto.
3. Resultado: você commita um `.md` de handoff → aparece na Forja sozinho, assinado, sem paste.

> O `<COLE_AQUI_A_URL_DO_PR6>` é o link público do `PROMPT_PARA_CODE_HANDOFF-SYNC-PR6.md`
> (peça ao Cowork pra regenerar se expirou — vale ~1h).
