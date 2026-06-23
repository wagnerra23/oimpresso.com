# [AH] O Adversário do Handoff — crítica da spec `handoff-pending` (2026-06-17)

> **Papel:** Adversário de Protocolo de Handoff `[AH]`. Entende MCP (servers/tools/resources,
> superfície de prompt-injection via conteúdo retornado), handoff multi-agente (context-dump
> fallacy, feedback-void, re-inferência) e a constituição deste projeto (append-only ADR 0003,
> soberania de [W], gates F1.5/F2/F4). **Função:** atacar a spec até ela aguentar — não elogiar.
> **Alvo:** `memory/sprints/s3-handoff-mcp/01-tool-handoff-pending.md`.
> **Método:** assume má-fé + Murphy. Cada achado tem severidade e correção obrigatória.
> **Regra do tribunal:** P0 aberto = **não shippa**. P1 = shippa com dívida registrada.

---

## Veredito: 🔴 **NÃO SHIPPAR como está** — 3 P0 abertos.
A spec é boa de *encanamento* (espelha `brief-fetch`, tem `ack`, é DB-backed). Mas trata o handoff
como transporte de dado benigno. **Ele não é benigno: o `body_md` é instrução que o Code executa
no repo de produção.** Isso muda a categoria de risco. Os 3 P0 são sobre isso.

---

## Achados

| # | Sev | Ataque / falha | Por quê dói | Correção obrigatória |
|---|---|---|---|---|
| A1 | **P0** | **Prompt-injection pela porta da frente.** `body_md` é buscado pelo Code e ele age sobre o repo. Quem escrever em `prototipo-ui/handoffs/` (ou na fonte do sync) injeta comando que o Code roda — exfiltração, backdoor no PR, `rm`. O "trate como dado" no hint é torcida, não controle. | O canal vira RCE assistida. Pior que o paste manual — que ao menos tinha um humano olhando. | (a) **Proveniência assinada:** `ingest` só aceita arquivo com HMAC de uma chave que só o pipeline [CC]/[W] tem; rejeita o resto. (b) **Escopo duro:** o PR resultante só pode tocar arquivos listados em `files_json`; CI bloqueia diff fora do escopo. (c) **F4 humano segue obrigatório** — `ack=applied` **nunca** auto-mergeia. |
| A2 | **P0** | **`Cache::flush()` no `ack`** zera o cache **inteiro** do app (brief, sessions, tudo). | Um ack derruba performance de todo o ERP. Bug de produção clássico. | `Cache::forget()` só das chaves `handoffs.pending.*` (ou cache tag). Nunca `flush()` global. |
| A3 | **P0** | **R3 é teatro aqui.** `ack=applied`+`pr_url` **não prova** que o gate passou. O PR pode estar vermelho. | A "definition of done" automatizada que justifica a ADR não está amarrada — o loop fecha mentindo "pronto". | `ack=applied` exige `gate_status` (conformance-gate + critique-score ≥80) **ou** o servidor consulta o status do PR (GitHub checks) antes de aceitar. Sem verde, fica `pending` com flag. |
| A4 | P1 | **Drift detectado tarde demais.** Só no `ack`, depois do Code já ter feito o trabalho em cima de um `main` que andou. | Desperdiça um ciclo inteiro + retrabalho — exatamente a dor que a ADR queria matar. | `handoff-pending` compara `audited_against` com o HEAD atual do main **na resposta**; se divergiu nos `files_json`, devolve `stale_warning` **antes** do Code começar. |
| A5 | P1 | **Conflito entre handoffs.** Dois pendentes tocam o mesmo arquivo (`files_json`); aplicação em paralelo se atropela. | Clobber silencioso; o segundo PR desfaz o primeiro. | `pending` calcula interseção de `files_json` entre pendentes e marca `conflicts_with: [slug]`; Code serializa ou pede decisão. |
| A6 | P1 | **Revisão silenciosamente engolida.** `ingest` faz `updateOrInsert` por slug e não rebaixa `applied→pending`. Se [CC] corrige um handoff já aplicado, a correção **some**. | Append-only (ADR 0003) ferido + correção perdida. | Versionar: `slug@v2` cria registro novo `pending` que **supersedes** o anterior (lápide, não delete). Histórico preservado. |
| A7 | P1 | **Authz frouxa no `ack`.** Qualquer token com acesso marca handoff dos outros como applied/rejected. | Um agente fecha o trabalho de outro; audit aponta o culpado errado. | Scope `handoff.ack` + bind ao `mcp_actors`; só agente-Code (não [CC], não humano comum) acka. |
| A8 | P2 | **Custo de contexto.** Code pode sempre mandar `include_body=true` e puxar todos os corpos. Body sem teto. | Context-dump fallacy: enche o Code de ruído, degrada raciocínio. | Forçar list-then-fetch (body só com `slug` específico) + teto de tamanho por body (ex. 8k tokens) + `body_truncated`. |
| A9 | P2 | **"Zero-paste" é miragem — e talvez indesejável.** O sync Cowork→repo não existe, e quando existir é um **write path desassistido** = superfície de ataque maior que o paste humano. | O paste manual é, de fato, um gate humano. Removê-lo troca segurança por conveniência. | Decidir conscientemente (decisão de [W]): manter paste-único como **feature de segurança**, ou construir o sync **com** A1 (assinatura) obrigatório. |

---

## O que o adversário concede (pra ser justo)
- Espelhar `brief-fetch` é certo — reusa auth/throttle/audit já provados.
- Ter `handoff-ack` ataca o **feedback-void** de verdade (a maioria dos frameworks não tem).
- DB-backed + `source_hash` dá idempotência de ingestão.
- O drift guard **existe** — só está no lugar errado (tarde).

---

## Gate pra liberar implementação
1. **Fechar A1, A2, A3 (P0)** — sem isso, é canal de RCE com gate de mentira. Não shippa.
2. A4–A7 (P1) entram no mesmo PR ou viram dívida **registrada** (issue linkada), nunca esquecida.
3. A8–A9 (P2) podem ir pra ronda 2, mas A9 é **decisão consciente de [W]**, não default silencioso.

## Pergunta dura final pra [W]
A ADR quer tirar você do meio. Mas o paste manual é o **único humano olhando** antes do Code mexer
no repo de produção. **Você quer mesmo zero-paste, ou quer paste-de-1-clique com assinatura?**
Conveniência total aqui custa o último gate humano. Decida com os olhos abertos — não por inércia.

---

---

## Re-veredito v2 (2026-06-17) — após [W] escolher zero-paste + hardening
A spec foi reescrita (`01` v2). Confiro os P0:
- **A1** ✅ assinatura HMAC na ingestão + scope-guard (PR só toca `files_json`) + auto-merge só com gates verdes. Injeção exige a chave **e** passar 3 gates **e** ficar no escopo.
- **A2** ✅ `forget` cirúrgico; `Cache::flush()` proibido por regra.
- **A3** ✅ `ack=applied` exige `gate_status` verde (422 sem) — R3 deixou de ser teatro.
- **A4/A5** ✅ `stale_warning` + `conflicts_with` agora no `pending` (antes do trabalho).
- **A6** ✅ revisão = nova `version` + `superseded` (append-only).
- **A7** ✅ scope `handoff.ack` só pro ator-Code. **A8** ✅ list-then-fetch + teto 32k.

**Veredito v2: 🟢 LIBERA implementação** (PR-3 — escopo+gates — é pré-requisito do zero-paste em produção).
**A9 (resolvido por decisão de [W]):** zero-paste autorizado conscientemente; o gate humano por-handoff
foi **substituído** por assinatura+escopo+gates, não removido. Novo ponto de controle de [W] = **calibrar o gate**, não revisar PR.

---

*Adversário invocável em qualquer spec de protocolo. Não cunha ADR, não edita lei — ataca e pontua. [W] decide o que vira regra.*
