# [W]→[CL] · Forja › aba MCP — superfície do handoff (Fase 1 do ADR 0283)

> **Cole UMA vez no Claude Code do repo `wagnerra23/oimpresso.com`.** Não refaz nada da Fase 0
> (já mergeada: #2904/2905/2906/2908). Isto é só **mostrar na Forja** os handoffs que já existem
> em `cowork_handoffs`. Cirúrgico: 1 controller method + 1 componente + 2 linhas no contrato.
> **§10.4:** valide contra o `main` fresco; onde divergir, **main vence**.

## Premissa (✓ lido @main)
- Backend pronto: `cowork_handoffs` (entity `CoworkHandoff`), tools `HandoffPendingTool`/`HandoffAckTool`,
  `HandoffStaleAlertCommand`, `McpIngestHeartbeat`.
- **Hoje a aba MCP é MOCK:** `ForjaController@mcp` faz `renderTab('mcp')` (sem defer); `ForjaMcp.tsx`
  só tem contrato/tokens/auditoria estáticos. **Nenhum handoff aparece.**
- **Design de referência (F1, Cowork):** `forja-mcp.jsx` → componente `HandoffPanel` (layout, filtros,
  levers, drill, heartbeat) + `.fj-ho*` em `forja-page.css`. Traduzir pro DS v6 do `ForjaMcp.tsx`
  (mesmos tokens semânticos das outras seções — não copiar CSS cru).

## Status REAIS (do schema — NÃO usar o vocabulário do protótipo)
O protótipo Cowork usava `merged`/`blocked`. **Errado.** A coluna `status` é:
`pending · applied · rejected · stale · superseded`. Mapa correto:
| protótipo (ignore) | real (`cowork_handoffs.status`) | sentido |
|---|---|---|
| pending | `pending` | aguarda o Code puxar |
| applied | `applied` | ack fechou (PR aberto/aplicado — **não há auto-merge**, 0283) |
| blocked | `rejected` | ack rejeitou (gate vermelho) — volta pro [CC] |
| merged | — (não existe) | sem auto-merge na Fase 0 |
| stale | `stale` | pending velho (cron já marca) |
| — | `superseded` | versão antiga (revisão criou nova) — ocultar por padrão |

## PR-A — `ForjaController@mcp` projeta `cowork_handoffs`
Trocar o `renderTab('mcp')` por `Inertia::render('team-mcp/Forja/Cockpit', merge(tabPayload('mcp'), [...]))`
com **`Inertia::defer`** (espelha `triagem()`/`quadro()`):
1. **`handoffs`** = `CoworkHandoff` mais recente por `slug` (maior `version`), **excluindo `superseded`**,
   `orderByDesc(created_at)`, limit 200. Serializar:
   `slug · version · tela · status · arquivos = count(files_json) · pr_url · created_at(humano) · created_by · gate_status(json) · resumo` (1ª linha do `body_md`).
2. **`stale` derivado na LEITURA** (robusto, não espera o cron): se `status='pending' AND now-created_at > 3d` → exibir como `stale`.
3. **`heartbeat`** = último `McpIngestHeartbeat` (`last_ingest_at`) → pro empty-state distinguir
   "ocioso" de "transporte sem sinal".
4. **Gate badge** = ler `gate_status` json (`{conformance, critique_score, a11y}`). Verde se os 3 ok;
   vermelho se algum falhou; "rodando" se `applied` sem `gate_status` ainda.
   - **Conflito (opcional, follow-up):** se quiser o A3 do adversário 100% fechado, comparar `gate_status`
     do ack com o status real dos required checks do PR (GitHub API via `config/services.php` token).
     Se divergir → badge `conflito`. Se a leitura de CI não estiver à mão, **deixar como TODO** — não inventar verde.
- Permissão: mantém `can:copiloto.mcp.usage.all` (já no construtor). Tier 0 repo-wide (sem business_id).

## PR-B — `ForjaMcp.tsx` ganha a seção Handoffs (ACIMA de contrato/tokens/auditoria)
Nova seção `data-testid="forja-mcp-handoffs"` no topo, espelhando `HandoffPanel` do `forja-mcp.jsx`:
- **Título** "Handoffs F1 → F3 · Cowork → Code" + **filtros por status** com contagem
  (`todos · pendente · aplicado · rejeitado · parado`). `superseded` fora do filtro padrão.
- **Cada item:** `slug` `vN` · tela · resumo · **⚿ sig** (já validada no ingest) · `N arq` ·
  **gate** (verde/vermelho/rodando, do `gate_status`) · `pr_url ↗` (link real) · badge de status · idade.
- **Levers (call MCP auditada — roteamento, não [W] operando):**
  `stale` → **re-disparar** · `rejected` → **devolver ao [CC]** (nota) · qualquer pending/applied → **supersede**.
  **SEM botão de merge** (Tier 0 / 0283 — o merge é o 1-clique do [W] no GitHub).
  Cada lever chama a tool MCP correspondente; se ainda não houver endpoint, deixar o botão disparando
  um POST stub documentado como TODO (não simular sucesso).
- **Drill:** `pr_url` e o gate vermelho linkam pro PR / pro check que falhou.
- **Empty-state = heartbeat:** "último ingest há Xmin"; se o sync estiver mudo > N → vira **alerta**
  ("transporte sem sinal"), não "tudo calmo".
- DS v6: tokens semânticos (success/warning/destructive/info/muted), `tabular-nums`, `inline-flex/grid`,
  `data-testid`, máx `rounded-lg` — igual ao resto do `ForjaMcp.tsx`.

## PR-C — 2 linhas no contrato de ferramentas (array `TOOLS`)
Adicionar ao `TOOLS` do `ForjaMcp.tsx`:
```
{ ferramenta: 'handoff-pending', acao: 'puxar handoff F1→F3', permissao: 'PERMITIDO', detalhe: 'assinado' },
{ ferramenta: 'handoff-ack',     acao: 'confirmar aplicado + gate', permissao: 'PROPÕE', detalhe: '422 sem gate verde' },
```

## Sinal no Quadro (se trivial; senão vira task)
`ForjaQuadroService`: contador de handoffs `pending` na aresta **F1→F3** do board.

## NÃO FAZER
- ❌ Recriar tabela/tools/cron — Fase 0 já mergeada.
- ❌ Usar `merged`/`blocked` — os status reais são `pending/applied/rejected/stale/superseded`.
- ❌ Auto-merge ou botão de merge (0283 — 1-clique do [W]).
- ❌ Pintar gate verde sem ler `gate_status`/CI real.

## Pronto quando
A aba MCP da Forja lista os handoffs reais de `cowork_handoffs` (status correto + gate do `gate_status` +
sig + levers + drill), empty-state mostra heartbeat, e o contrato lista `handoff-pending`/`-ack`.
Ao terminar: `[PROCESSADO AAAA-MM-DD]` + retorno em `CODE_NOTES.md`. Cowork read-only no git.
