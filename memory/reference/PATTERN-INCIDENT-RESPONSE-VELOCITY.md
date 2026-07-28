---
id: reference-pattern-incident-response-velocity
name: PATTERN-INCIDENT-RESPONSE-VELOCITY
description: Padrão de resposta a incident em prod oimpresso — método 4-DRFV + 6 multiplicadores de velocidade. Catalogado pós-sessão 2026-05-28 (10 PRs em 1 dia: 6 bugs whatsmeow + 2 infra + 2 governança).
type: reference
created: 2026-05-28
owners: [wagner, claude]
status: ativo
---

# Padrão de resposta a incident em prod — DRFV + 6 multiplicadores

> **Quando aplicar:** Wagner reporta bug em prod ("não funciona X") OU agente detecta sintoma via cron/log/audit. Funciona pra sintomas crônicos (45.819 mídias órfãs) e agudos (tela branca click conv).
>
> **ROI:** Sessão 2026-05-28 fechou 10 PRs (6 bugs P0 whatsmeow + 2 infra + 2 governança DS) em ~6h wall clock. Sem este padrão, cada bug levaria 1 dia → ~10 dias estimados.

---

## Método DRFV — Diagnose → Reproduce → Fix → Validate

Cada passo **OBRIGATÓRIO** antes do próximo. Pular = retrabalho + Wagner perde confiança ("tu sabe testar?").

### 1. **Diagnose com EVIDÊNCIA real** (não adivinhação, não memória)

| Camada | Ferramenta | Exemplo prático sessão 2026-05-28 |
|---|---|---|
| **Backend prod** | SSH + MySQL queries direto | `SELECT type, COUNT(*) FROM messages WHERE business_id=1 GROUP BY type` → 45.819 mídias pending |
| **Logs prod** | `tail -n 500 storage/logs/laravel.log \| grep -iE 'whatsmeow\|webhook'` | Achou `centrifugo.publish.success` AUSENTE → mismatch canal |
| **DB schema** | `mysql -e "DESCRIBE table; SHOW INDEX FROM table"` | UNIQUE `conv_biz_ch_ext_uniq` em `(business_id, channel_id, customer_external_id)` |
| **Frontend prod** | Chrome MCP `javascript_tool` — `window.onerror` + `console.error` override | Capturou `TypeError display_name` em CustomerMemoryBlock-B76hJ4zY.js exato |
| **Realtime/WS** | Chrome MCP — WebSocket monkeypatch — `window.WebSocket = new Proxy(...)` | Centrifugo erro 102 `unknown channel` invisível em DevTools sem patch |
| **Network** | Chrome MCP — `window.fetch` proxy + URL pattern filter | Confirmou request `/atendimento/customer/14628809617558%40lid/profile` 422 |
| **Código** | Grep com termo EXATO do erro/log + linha:contexto | "Envio só implementado pra Baileys" → InboxController:851 |

**Regra de ouro:** ZERO chute. Cada hipótese precisa evidência rastreável.

**Anti-padrão evitado:** "Acho que é X" → "Não. Vou medir."

### 2. **Reproduce de fato** (smoke real, não suposição)

- Chrome MCP click real → screenshot + medir latência
- SSH PHP inline (heredoc) pra publish sintético + medir tempo até DOM
- INSERT row + observar tela → confirma pipeline ponta-a-ponta
- **NÃO confie em "deveria funcionar"** — só vale "vi funcionar agora"

**Exemplo sessão:**
- Centrifugo: 567ms publish HTTP + Browser detectou em <200ms = **1.2s ponta-a-ponta** (antes era 60s)
- Outbound texto whatsmeow: SQL após click mostrou `status=failed reason='Envio só pra Baileys'` → confirmou bug

### 3. **Fix mínimo + Pest test anti-regressão**

**Default:** 1 PR = 1 intent ≤300 linhas (skill `commit-discipline`).

**Quando batchar:** Wagner explicitamente aprova OU 100% certeza que bugs compartilham causa raiz (ex: hardcoded Baileys em 3 lugares).

**Pest test obrigatório quando:**
- Bug envolve persistência/schema (ex: `customer_external_id` NOT NULL)
- Bug é regressão silenciosa (ex: `status@broadcast` sem filtro)
- Multi-tenant Tier 0 (sempre)

**Pest test dispensável:**
- Fix puramente cosmético/CSS (PR #1836 scroll)
- Config change (cron, namespace Centrifugo)

### 4. **Validate em prod com smoke real**

**Checklist obrigatório antes de declarar "está pronto":**

- [ ] Chrome MCP navegou pra URL real
- [ ] Click/upload/envio reproduzido
- [ ] DB query confirma estado esperado (`status=sent`, `media_url != NULL`)
- [ ] Log estruturado novo aparece (`publish.success`, `daemon ok`)
- [ ] Zero erro JS no browser
- [ ] Latência medida (não estimada)

**Se algum item falha:** NÃO reporte sucesso. Volte ao passo 1 com evidência específica.

**Anti-padrão evitado** (errado em PR #1825): testar só DB + dizer "está pronto" sem clicar na tela.

---

## 6 Multiplicadores de velocidade

### A) Paralelizar em background

**Pattern:** Spawn `Agent(subagent_type: audit-research-expert, run_in_background: true)` ENQUANTO trabalha foreground.

**Caso real sessão:**
- 4 audits paralelos: whatsmeow daemon (58% maturidade), realtime webhook→UI, mídia+outbound (48%), canon-fix biz=1
- Cada um ~15-25min wall clock
- Total: 25min em vez de 100min sequencial

**Quando usar:** task é PESQUISA (não execução). Fix de código fica foreground porque depende de decisão Wagner.

### B) Pesquisar ESTADO-DA-ARTE antes de inventar

**Pattern:** Spawn audit-research com mínimo 6 WebSearches sobre best-of-class (Chatwoot, Front, Intercom, Twilio Conversations, Take Blip).

**Caso real:**
- Audit mídia descobriu MIME whitelist Tier 0 sólido + Frontend cap 25MB ≠ BE 16MB
- Audit realtime achou Centrifugo namespace ausente + token TTL 1h sem refresh
- Diagnóstico foi 100% direcionado: comparativo achou 5 bugs P0 em 12 minutos

**ROI:** Achar bug que ninguém viu por 6 meses em 12min. Vale o paralelismo.

### C) Evidência > Memória

**Pattern:** ANTES de aplicar fato como base de decisão, executar 1 query/grep que valida.

**Caso real DOLOROSO:** Repeti 5× "biz=1 = ROTA LIVRE Larissa". Vinha do BRIEFING.md errado. Wagner cortou: "biz=1 é WR2 Sistemas". 30s SQL `SELECT id, name FROM business WHERE id IN (1,4)` resolveu.

**Skill associado:** `mcp-first` Tier A — tools MCP antes de Read/Glob filesystem.

**Anti-padrão:** confiar em auto-mem ou cache. SEMPRE verificar.

### D) Chain de PRs pequenos > 1 PR enorme

**Pattern:** 10 PRs hoje, cada um focado, cada um deploy independente. Permite **bisection rápida** se algo quebra.

**Quando batchar (sessão real M2+M4+M5):**
- Wagner aprovou explícito "batch tudo"
- Bugs compartilham auditoria + fix pattern (hardcoded Baileys em 3 lugares)
- Single deploy economiza Vite rebuild 26s × 3 = 78s

**Risco batch:** PR enorme esconde bug. Mitigação: commit body detalhado por bug + Pest test por bug.

### E) Browser MCP como debugger real

**Pattern:** Antes de ação que pode crashar, instalar handlers:

```js
window.__errs = [];
window.addEventListener('error', (e) => {
  window.__errs.push({msg:e.message, file:e.filename?.split('/').pop(), line:e.lineno, stack:e.error?.stack?.substring(0,1500)});
}, true);
window.addEventListener('unhandledrejection', (e) => {
  window.__errs.push({type:'rej', reason:String(e.reason).substring(0,500), stack:e.reason?.stack?.substring(0,1500)});
});
const origCE = console.error;
console.error = function(...args) { window.__errs.push({type:'ce', m: args.map(a => typeof a === 'object' ? JSON.stringify(a).substring(0,300) : String(a))?.join(' | ')}); return origCE.apply(this, args); };
```

**Caso real:** CustomerMemoryBlock TypeError só apareceu via stack trace capturado. Sem isso, tela branca sem pista. Achou em 30s.

**Monkeypatch WebSocket:**
```js
const origWS = window.WebSocket;
window.WebSocket = function(...a){
  const w = new origWS(...a);
  w.addEventListener('message', (e) => window.__wsLog.push({t:'msg', data: String(e.data).substring(0,300), at: Date.now()}));
  return w;
};
```

Centrifugo erro 102 só visível assim.

### F) Honestidade sobre limites

**Pattern:** Quando Wagner cobra ("tu sabe?"), NÃO defenda. Admita gap + spawn agent pra fechar.

**Caso real:** Wagner cobrou "imagens videos midias?". Eu admiti: "Auditoria DB confirma 45.819 órfãs". Spawn `audit-research-expert` 12min + executei batch. Não tentei adivinhar.

**Anti-padrão custoso:** prometer "tudo funcionando" sem validar (4 ciclos vai-volta hoje antes de eu aprender).

### G) Autonomia operacional — NUNCA escala automatizável pro Wagner

**Pattern:** Antes de pedir Wagner abrir hPanel/UI/copy-paste secret, tentar mínimo **6 paths** de automação.

**Caso real falha 2026-05-28 17:55:** Tentei 2 paths pra token Hostinger (.env + scripts/), não achei, pedi Wagner criar DNS A record no hPanel. Wagner alertou: "não me pergunte". Isso é **terceirização de fricção** — Wagner já tinha alertado 3× na mesma sessão.

**Caminho correto:**
1. Skill `hostinger-dns-autonomy` Tier A lista 6 paths (canon, container env, vaultwarden API, bw CLI, etc)
2. Se TODOS falharem, registra Tier 0 gap em `memory/proibicoes.md` + propõe ADR pra unblock estrutural
3. NÃO escala — Wagner NÃO é helpdesk

**Anti-padrão custoso:** assumir "Wagner faz em 5 segundos" como atalho. Cada pedido é (a) interrupção do flow dele, (b) sinal de que minhas regras estão furadas, (c) fricção repetitiva no longo prazo. Skill `hostinger-dns-autonomy` codifica.

**Aprendizado meta:** quando bater fricção (token não em path óbvio), default mental tem que virar "investigar 5 paths a mais" em vez de "pedir Wagner ajuda 30s". Skill bloqueante força esse default.

---

## Anti-padrões formais (referenciar em PRE-MERGE-UI / governance)

- **NÃO** declarar "funcionando" sem Chrome MCP click + DB query + log estruturado
- **NÃO** confiar em memória/canon — verificar com 1 query antes de aplicar
- **NÃO** spawn agent foreground se task é pesquisa (use `run_in_background: true`)
- **NÃO** rebuildar Vite no Hostinger SEM `RAYON_NUM_THREADS=1` (panic Rust destrói `public/build-inertia/`)
- **NÃO** assumir cache cleared (Wagner pode estar em bundle velho — sempre `Ctrl+Shift+R`)
- **NÃO** prometer realtime <1s sem testar end-to-end (Centrifugo namespace pode estar ausente daemon)

## Checklist 1-página pra próxima incident

```
[ ] 1. SSH prod 6 queries (driver, channel, conv, msg, failed_jobs, log tail)
[ ] 2. Chrome MCP navigate + screenshot + JS error handlers
[ ] 3. (Se realtime) WebSocket monkeypatch + publish sintético + medir
[ ] 4. Grep código com termo EXATO do erro/log
[ ] 5. (Se complexo) Spawn audit-research-expert background
[ ] 6. Fix mínimo + Pest test (a menos que puro CSS/config)
[ ] 7. PR + merge admin + deploy SSH + npm build se FE (RAYON_NUM_THREADS=1)
[ ] 8. Smoke validate Chrome MCP — click real, NÃO suposição
[ ] 9. Reportar com evidência (screenshot + log + latência medida)
```

## Refs

- Sessão fundadora: 2026-05-28 (10 PRs em 6h, fechou cadeia incident whatsmeow)
- Skill `mcp-first` Tier A — fonte de verdade git/MCP antes de adivinhação
- Skill `commit-discipline` Tier A — 1 PR = 1 intent ≤300 linhas
- Skill `wagner-understand` — refinar pedido vago antes de codar
- [PRE-MERGE-UI.md](../requisitos/_DesignSystem/PRE-MERGE-UI.md) AP9 + AP10 (anti-padrões scroll/HTML5 codificados pós-incident)
- 3 auditorias geradas em `memory/requisitos/Whatsapp/AUDITORIA-*-2026-05-28.md`:
  - AUDITORIA-WHATSMEOW-DAEMON (58%)
  - AUDITORIA-REALTIME-WEBHOOK-UI (top 3 P0)
  - AUDITORIA-MIDIA-OUTBOUND (48%)

## Lições internas (admitidas honestamente)

1. **biz=1 = WR2 Sistemas**, NÃO ROTA LIVRE (Larissa é biz=4). Eu propaguei erro do BRIEFING 5×. PR #1827 corrige 12 arquivos canon.
2. **Tela branca não é só "erro" — pode ser Vite manifest stale após deploy**. Sempre `npm run build:inertia` quando FE muda.
3. **Centrifugo `success` no log ≠ entregue ao browser**. Daemon retorna HTTP 200 com `{error: 102}` no body — `$response->successful()` engana. Validar `response.body.error` também.
4. **Queue worker silencioso é o pior bug**: jobs acumulam SEM failed_jobs aparecer. Auditoria periódica `SELECT queue, COUNT(*) FROM jobs GROUP BY queue` deveria ser cron.
5. **`<main>` aninhado é HTML5 inválido + quebra scroll silenciosamente**. AP9 codificado.
