# [CC]→[CL] · Implementar o Loop de Handoff Zero-Paste (MCP) — v2

## ▶ COMO USAR
Cole UMA vez no Claude Code. Auto-contido. Spec completa (PHP integral) = commitar junto
`memory/sprints/s3-handoff-mcp/01-tool-handoff-pending.md` no repo como referência.

> **Autorizado por [W] 2026-06-17.** Objetivo: tirar [W] do transporte/revisão por-handoff **sem**
> virar canal de RCE. Fecha os P0/P1 do adversário (`02-adversario-handoff.md`).
> **§10.4/L-26:** LER o `main` real antes de citar arquivo (`Modules/TeamMcp`, `mcp.auth`,
> `McpTokenIssuer`, `mcp_audit_log` — já existem). Onde divergir, **repo vence**.

## Invariante a implementar
Nenhuma mudança de UI entra no `main` sem **(1) assinatura válida + (2) diff dentro do escopo +
(3) conformance + critique≥80 + a11y verdes**. Os 3 automáticos. Falhou um → PR aberto + alerta. Sem exceção.

---

## PR-1 — Persistência + ingestão assinada
1. **Migration `cowork_handoffs`** (schema exato na spec §"Fonte da verdade"): inclui `version`,
   `sig`, `gate_status`, `UNIQUE(slug,version)`, índice parcial `status='pending'`.
2. **`config/teammcp.php`** → `'handoff_secret' => env('HANDOFF_SECRET')`. SECRET **só** no env do
   servidor + no secret do pipeline de export. Nunca versionar.
3. **`php artisan handoff:ingest`** (`Modules/TeamMcp/Console/HandoffIngestCommand`): parseia
   `prototipo-ui/handoffs/*.md`, **valida `sig = HMAC-SHA256(body, HANDOFF_SECRET)`** — rejeita
   inválido (loga, não insere). Revisão de slug já aplicado = **nova `version` pending + anterior
   `superseded`** (append-only, nunca delete). Código integral na spec §Ingestão.
4. **GitHub Action** on-push em `prototipo-ui/handoffs/**` → roda `handoff:ingest`.
- **Pronto quando:** push de handoff assinado cria `pending`; sem `sig`/forjado → rejeitado e logado; revisão vira v2 + v1 `superseded`.

## PR-2 — Tools MCP `handoff-pending` + `handoff-ack`
1. **`HandoffController`** (`Modules/TeamMcp/Http/Controllers`) — métodos `pending` e `ack`,
   código integral na spec §Handler. Pontos não-negociáveis:
   - `pending`: sem `slug` → só metadados; com `slug` → corpo (teto 32k + `body_truncated`).
     Calcula `stale_warning` (main mudou nos `files_json` vs `audited_against`) e `conflicts_with`
     (outros pendentes nos mesmos arquivos) **na resposta**.
   - `ack`: exige scope `handoff.ack`; `applied` só com `gate_status` verde (conformance &&
     critique≥80 && a11y) senão **422**; ack em não-pendente → **409**; invalida cache com
     `forget` cirúrgico (**`Cache::flush()` proibido**).
2. **`GitMainResolver`** (service novo): `headSha('main')` + `filesChangedBetween($a,$b,$files)`
   (via API GitHub ou git local do Code). Usado pelo drift/stale guard.
3. **Rotas** em `routes/api.php` sob `['mcp.auth','throttle:60,1']` prefix `mcp` (espelha `brief-fetch`).
4. **Scope**: registrar `handoff.pending` + `handoff.ack` em `mcp_scopes`; emitir token pro ator-Code via `McpTokenIssuer`.
- **Pronto quando:** `mcp__oimpresso__handoff-pending`/`-ack` aparecem no Code; curls da spec §Teste passam; ack sem gate verde = 422; ack repetido = 409.

## PR-3 — Escopo + auto-merge só com gates verdes (o coração da segurança)
1. **scope-guard** (GitHub Action no PR de handoff): identifica o handoff (slug no branch/título),
   lê `files_json`, **falha o PR** se o diff tocar arquivo fora da lista.
2. **Gates como required checks**: `conformance-gate.mjs` (já existe) + critique-score (F1.5) +
   a11y (F3.5). Marcar os 4 (scope-guard incluso) como **required** na branch protection do `main`.
3. **Auto-merge**: habilitar GitHub auto-merge que dispara **só** com os 4 required verdes. Vermelho
   → PR fica aberto.
- **Pronto quando:** PR fora do escopo é barrado; PR com qualquer gate vermelho **não** mergeia; PR 4-verdes mergeia sozinho.

## PR-4 — Anti feedback-void + auditoria
1. Cron: handoff `pending` há > **3 dias** → alerta no MCP inbox channel `ops`.
2. `mcp_audit_log` grava toda call (`handoff-pending`/`-ack`: tool, agente, slug, outcome, drift).
- **Pronto quando:** pendente velho alerta; toda call auditada.

## PR-5 — Superfície do handoff na Forja (a janela do [W] pro loop) · HARDENED
> Sem isto, o loop roda **invisível** — [W] não vê os handoffs fluindo. A casa é a aba **MCP** do
> cockpit Forja (`Modules/TeamMcp` · `team-mcp/Forja/Cockpit`, prop `tab=mcp`), hoje MOCKADA.
> **Design de referência (F1, verificado no Cowork):** `forja-mcp.jsx` (`HandoffPanel`) + `forja-data.jsx` (`FORJA_HANDOFFS`) + `.fj-ho*` em `forja-page.css`.
> **O adversário fechou 4 furos aqui — ler `Adversário do Handoff na Forja` antes:**
1. **`ForjaController@mcp`**: trocar o mock por `Inertia::defer` que projeta `cowork_handoffs`
   (tabela do PR-1) — lista com filtro por estado: `pending · applied · merged · blocked · stale`.
2. **Gate = verdade do CI, não auto-relato (Gap 2 · BLOQUEADOR).** O badge de gate lê o **status real
   dos required checks do PR** (GitHub API: conformance + critique + a11y + scope-guard), **NÃO** o
   campo `gate_status` que o `handoff-ack` gravou. Se divergirem → badge <code>conflito</code> vermelho.
   O ack é pista; o CI é verdade.
3. **Levers pra travados (Gap 3 · BLOQUEADOR).** Cada item dá ação conforme o estado, **toda call MCP
   auditada** (não é [W] operando no detalhe — é roteamento): `stale` → **re-disparar** (notifica Code);
   `blocked` → **devolver pro [CC]** com nota (vira novo handoff F1); qualquer → **supersede** (marca a
   versão velha, não deleta — ADR append-only). Sem botão de **merge manual** (Tier 0 — só auto-merge do PR-3).
4. **`stale` derivado na LEITURA, não por cron (Gap 5).** A query calcula
   `status='pending' AND now-created_at > 3d` → `stale`, sem depender do cron do PR-4 ter rodado.
   (O cron do PR-4 só dispara o **alerta**; a Forja não espera por ele pra pintar o estado.)
5. **Drill-through (Gap 6).** `pr_url` é link pro PR; o gate vermelho linka pro check que falhou
   (critique-score.json / a11y-report.md). Um clique do sintoma à causa.
6. **Empty-state = heartbeat, não calmaria (Gap 7).** Mostrar "último ingest há Xmin" (do PR-6). Se o
   sync não roda há > N → empty-state vira **alerta** ("transporte sem sinal"), não "tudo calmo".
7. **Sinal no Quadro** (`ForjaQuadroService`): contador de handoffs **pendentes** na aresta **F1→F3**
   — o handoff É a transição F1→F3.
- **Estados = `cowork_handoffs.status`** (`pending/applied/merged/blocked/stale`) — não inventar.
- **Schema (Gap 4 · pré-req):** o painel mostra `screen` + `onda` — esses campos **têm que existir**
   no handoff. Ver "Reconciliação de schema" abaixo. Não codar PR-5 antes de mapear 1:1.
- **Pronto quando:** aba MCP lista handoffs reais com estado + **gate do CI real** + assinatura + levers;
   Quadro mostra pendentes na borda F1→F3; empty-state mostra heartbeat; nenhum dado é mock.
   Permissão `copiloto.mcp.usage.all` (igual às outras abas).
- **Não fazer:** ❌ botão de merge manual (Tier 0). ❌ painel de auditoria completo (vive no Governance). ❌ confiar no `gate_status` do ack. ❌ duplicar a tabela.

## PR-6 — Sync Cowork→repo (o matador-de-paste · Gap 1 · BLOQUEADOR)
> **Este é o vão real.** Os PR-1..5 montam o cano; o handoff só entra se **aterrissar** em
> `prototipo-ui/handoffs/`. Hoje quem põe lá é o paste do [W]. Este PR remove o último toque.
1. **Fonte:** o export público do projeto Cowork (mesmo mecanismo das URLs `get_public_file_url`),
   ou um endpoint que o Cowork popula. O artefato é um `.md` com front-matter
   (`slug, version, screen, onda, files_json, body, sig`).
2. **Transporte:** GitHub Action agendada (cron, ex. 15/15min) **ou** webhook — baixa os handoffs
   novos e os grava em `prototipo-ui/handoffs/` num commit de serviço (bot), **assinando** com o SECRET
   (HMAC do PR-1). Nada entra sem `sig` válida.
3. **Idempotência:** `source_hash` por arquivo — re-rodar não duplica; revisão de slug = nova `version`.
4. **Heartbeat:** cada run grava `last_ingest_at` (lido pelo empty-state do PR-5, Gap 7). Sync mudo > N → alerta `ops`.
5. **Ligação:** após gravar, dispara `handoff:ingest` (PR-1) → popula `cowork_handoffs` → a Forja vê.
- **Pronto quando:** [W] **não cola nada**; um handoff produzido no Cowork aparece na Forja em ≤ 1 ciclo
   do sync, assinado, sem toque humano. **Este é o critério de "Wagner fora do loop".**
- **Honesto:** é a única peça genuinamente nova de infra. Até o PR-6 verde, o loop roda com **1 paste**
   (cola em `prototipo-ui/handoffs/`); depois, **zero**.

## Reconciliação de schema (Gap 4 · pré-req do PR-5)
O protótipo (`FORJA_HANDOFFS`) usa campos que precisam existir no handoff/tabela. Mapa canônico:

| protótipo | cowork_handoffs / front-matter | origem |
|---|---|---|
| `slug` | `slug` | front-matter |
| `v` | `version` | front-matter (UNIQUE(slug,version)) |
| `tela` | `screen` | **adicionar** ao front-matter (o Cowork já sabe a tela) |
| `onda` | `onda` | **adicionar** ao front-matter |
| `arquivos` | `count(files_json)` | derivado |
| `sig` | `sig` (válida?) | HMAC do PR-1 |
| `gate` | **status real do PR** (não `gate_status`) | GitHub API (PR-5 §2) |
| `pr` | `pr_url` | gravado no `handoff-ack` |
| `estado` | `status` (+ `stale` derivado) | PR-5 §4 |
| `autor` | `author` | front-matter (default `CC`) |
| `nota` | `summary` | front-matter |

---

## NÃO TOCAR / NÃO FAZER
- ❌ Auto-merge sem os 4 required verdes. ❌ Aceitar handoff sem `sig`. ❌ SECRET versionado/no Code.
- ❌ `Cache::flush()`. ❌ Deletar handoff (use `superseded`). ❌ Numerar ADR sem [W].
- ❌ Reescrever `brief-fetch`/TeamMcp existentes — só **adicionar** as 2 tools + tabela + actions.

## Ordem sugerida
PR-1 → PR-2 → PR-3 → PR-4 → PR-5 → **PR-6**. PR-3 dá a segurança (não liberar zero-paste antes dele
verde). PR-5 (Forja) depende de PR-1+PR-2 (pode paralelizar com PR-4). **PR-6 é o que zera o paste** —
até ele, o loop roda com 1 paste em `prototipo-ui/handoffs/`. Sem PR-6, "Wagner fora do loop" não fecha.

Ao terminar cada PR: `[PROCESSADO AAAA-MM-DD]` aqui + retorno em `CODE_NOTES.md`. Cowork read-only no git.
