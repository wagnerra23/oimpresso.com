# PROTOCOL-F3-COWORK-CODE.md — protocolo cirúrgico de implementação de design Cowork → Inertia

> **Versão:** 1.0
> **Documento mãe:** [ADR 0114 prototipo-ui Cowork loop](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) + [ADR 0107 visual-comparison gate F3](../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md) + [ADR 0104 MWART canônico](../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
> **Extensão de:** [`PROTOCOL.md`](PROTOCOL.md) §2 fase F3
> **Origem:** Sessão Wagner 2026-05-16 — implementação Cobrança Recorrente teve 2 rejeições antes de chegar à abordagem cirúrgica. Wagner pediu: *"crie um agente especialista nessa integração. e crie protocolo estado da arte."*
> **Agente associado:** [`.claude/agents/cowork-to-inertia.md`](../.claude/agents/cowork-to-inertia.md) — ativa automaticamente quando user manda design pra implementar.

---

## 1. Por que este protocolo existe

`PROTOCOL.md` §2 define F3 (CODE) como `[CL] Claude Code traduz protótipo aprovado pra Inertia/React real`. **1 linha. Insuficiente.** A sessão 2026-05-16 mostrou que sem detalhe operacional, F3 vira:

- Adivinhação visual (Claude implementa "parecido" sem validar pixel-perfect)
- Implementação parcial sem declarar o que vai quebrar
- PRs gigantes (>2k linhas) mesclando canon + código + docs
- Onda única que tenta entregar 9,75/10 sem cirurgia

**Resultado:** Wagner rejeita 2-3x antes de chegar à versão correta = ~30k tokens desperdiçados + frustração.

Este documento formaliza F3 em **7 sub-fases cirúrgicas** com gates obrigatórios + ferramentas concretas + entregáveis verificáveis. É o "como" do "o que" definido em `PROTOCOL.md`.

---

## 2. As 7 sub-fases (ordem obrigatória)

```
F3.0 RECEIVE         bundle Cowork chegou (chat/api/path)
                      ↓
F3.1 EXTRACT         auto-detect formato + cópia canon pra prototipo-ui/prototipos/<tela>/
                      ↓
F3.2 RENDER          Preview server + Chrome MCP screenshot do rendering local
                      ↓
F3.3 VALIDATE        pixel-perfect diff vs screenshots Wagner — GATE OBRIGATÓRIO
                      ↓
F3.4 DECOMPOSE       Index-visual-comparison.md (15 dim + schema gap + N ondas ≤300 ln)
                      ↓
F3.5 DECLARE         tabela "o que vai quebrar" + Wagner autoriza "go"
                      ↓
F3.6 IMPLEMENT       onda-a-onda; 1 PR por onda; aguarda merge antes próxima
                      ↓
F3.7 SMOKE+HANDOFF   smoke biz=1 + session log + tasks-update + handoff append-only
```

Sem fase pulada. Sem "vou só fazer esse pedacinho rápido". Cada gate é binário (✅ passou / ❌ STOP).

---

## 3. F3.0 — RECEIVE

**Trigger:** Wagner cita design (`tela X`, `mockup Y`, `desse HTML`, `as screenshots`) OU anexa bundle Cowork OU passa URL `api.anthropic.com/v1/design/...`.

**Você (Claude Code):**

1. Identifica formato:
   - **Bundle tar.gz** (Cowork export via design.anthropic.com API) → `WebFetch` retorna binário → `tar -xzf` em `/tmp/design-pkg/`
   - **HTML local** (path Windows tipo `C:\Users\wagne\Downloads\X.html`) → `Read` direto
   - **Screenshots inline** (Wagner colou imagem no chat) → você só tem a screenshot, sem fonte
   - **Path repo** (ex: `prototipo-ui/prototipos/X/`) → fonte já está versionada

2. Se bundle → extrai + lê `README.md` do bundle (instruções Cowork pro coding agent) + lê chat transcripts (`chats/*.md`) pra identificar onde Wagner LANDOU (último arquivo iterado)

3. **Anti-pattern detection:** se o arquivo recebido é um **strategy doc** (`Diagnóstico KB-9.75.html`, `Auditoria.html`, `Plano por Tela.html`) — NÃO é mockup pra implementar. É roadmap. Você lê pra contexto e busca o `.jsx` referenciado (sessão 2026-05-16 caiu nessa armadilha — `Diagnóstico KB-9.75.html` descrevia o plano mas o canon era `recurring-page.jsx` no mesmo bundle).

**Saída esperada:** mensagem curta a Wagner identificando "achei: `prototipo-ui/prototipos/<tela>/<arquivo>.jsx` (NNNN linhas) + `<data>.jsx` + `<icons>.jsx`. Próximo: F3.1 EXTRACT pra `prototipo-ui/`."

---

## 4. F3.1 — EXTRACT

Cópia da fonte canônica pra estrutura do repo seguindo [ADR 0114](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md):

```
prototipo-ui/
├── prototipos/<tela>/              ← canon promovido (versionado, sobrevive)
│   ├── <tela>-page.jsx             ← IIFE expondo window.<Tela>Page
│   ├── <tela>-data.jsx             ← mock data (vai virar Controller query depois)
│   ├── <tela>-icons.jsx            ← icons window.<TELA>_I (vai virar Lucide React depois)
│   └── (opcional) <tela>-page.css  ← se houver CSS dedicado
└── cowork-snapshot/                ← snapshot completo Cowork (transitório, dev only)
    ├── Oimpresso ERP - Chat.html   ← shell pra rodar local
    ├── app.jsx, sidebar.jsx, ...   ← todas dependências pra render
    └── (gitignore opcional — só pra debug)
```

**Auto-detect formato canônico:**

| Sinal | Formato | Próximo passo |
|---|---|---|
| `(() => { ... window.XxxPage = ... })()` | IIFE Cockpit V2 (padrão atual) | OK, segue F3.2 |
| `<!doctype html>` standalone com `<style>` inline | HTML clássico Cowork | Extrai CSS pra `<tela>-page.css`, JS pra `<tela>-page.jsx` |
| Só screenshots | sem fonte | STOP, pergunta Wagner se há .jsx ou se você infere do padrão (sub-ótimo) |

**Saída esperada:** mensagem curta confirmando arquivos copiados + `git status --short` mostrando files novos em `prototipo-ui/prototipos/<tela>/`.

---

## 5. F3.2 — RENDER

Sobe o snapshot Cowork localmente pra renderizar de verdade.

**Setup obrigatório:**

```jsonc
// .claude/launch.json (na raiz do worktree)
{
  "version": "0.0.1",
  "configurations": [
    {
      "name": "cowork-<tela>",
      "runtimeExecutable": "python",
      "runtimeArgs": ["-m", "http.server", "5550",
                      "--directory", "D:\\oimpresso.com\\prototipo-ui\\cowork-snapshot"],
      "port": 5550
    }
  ]
}
```

**Workflow:**

```
mcp__Claude_Preview__preview_start     name=cowork-<tela>
mcp__Claude_Preview__preview_resize    width=1600 height=900   # ou 1280 (Larissa)
mcp__Claude_Preview__preview_eval      "localStorage.setItem('oimpresso.route', '<tela>'); location.reload()"
sleep 3                                 # aguarda Babel compile + render
mcp__Claude_Preview__preview_screenshot
```

**Anti-pattern catalogado:** rota Cowork não responde a `#hash` URL — controlada via `localStorage.oimpresso.route`. Setar via `preview_eval` + reload.

**Saída esperada:** screenshot na mensagem + comparação visual breve "header, KPIs, 3-col, drawer todos presentes — bate com screenshots Wagner".

---

## 6. F3.3 — VALIDATE (gate obrigatório)

**Você NÃO pode escrever 1 byte de código produtivo sem completar este gate.**

Compara visualmente:

| Elemento | Screenshot Wagner | Screenshot Preview | OK? |
|---|---|---|---|
| Header title + subtitle | "Cobrança recorrente · 13 ATIVAS · MRR R$ 8.420" | (verifica) | ✅/❌ |
| Top tabs + badges | Assinaturas 1 · Planos 2 · Faturas 3 · Configurações 4 | (verifica) | ✅/❌ |
| 4 KPI cards | MRR (dark) + CHURN + PRÓXIMA + RETENTADO | (verifica) | ✅/❌ |
| Layout 3-col | filtros 220px · lista flex · drawer 340px | (verifica) | ✅/❌ |
| Sidebar filtros | PRÓXIMA COBRANÇA + STATUS + PLANO sections | (verifica) | ✅/❌ |
| Drawer detalhe | avatar+CNPJ + próxima card + grid kv + nota pin + NFE + JANA IA | (verifica) | ✅/❌ |

**Tolerância:** diff visual > 5% em qualquer elemento = STOP. Wagner aprova ou design canon muda.

**Se BATE** → segue F3.4 e tem permissão pra começar a planejar código produtivo.
**Se NÃO BATE** → escreve session log com diff documentado, pergunta Wagner.

**Validação extra:** `mcp__Claude_Preview__preview_inspect` em elementos críticos pra confirmar tokens CSS (não pode confiar só em screenshot pra cores/spacing).

---

## 7. F3.4 — DECOMPOSE

Escreve `memory/requisitos/<Modulo>/<Tela>-visual-comparison.md` cirúrgico:

### Estrutura obrigatória:

1. **Header** — status (validado/rejeitado), destino (X,X/10 Método KB-9.75), fonte canônica (path), snapshot vivo (URL local)
2. **Arquitetura visual** — N sub-rotas + URLs alvo + conteúdo de cada
3. **Decomposição cirúrgica por tela** — para cada tela, lista cada seção visual (header, KPIs, sidebar, lista, drawer) com elementos detalhados
4. **Schema gap vs estado atual** — tabelas/colunas que precisam ser criadas:
   ```
   Tabelas novas: rb_subscription_notes, rb_subscription_favorites, rb_subscription_events
   Colunas adicionar em rb_subscriptions: payment_method, last_jobsheet_id, total_paid_cached, ...
   ```
5. **Cross-module deps** — link a `Modules/<Outro>`, FK soft vs hard, fallback graceful se módulo não tem o endpoint
6. **Plano de N ondas** — tabela com onda → conteúdo → estimate
   ```
   Onda 1 (4h): migration aditiva + Models + Pest
   Onda 2 (2h): Observer cached cols + comando backfill
   ...
   ```
7. **Validação realizada na Onda 0** — checklist do que foi validado em F3.2/F3.3

### Anti-pattern catalogado:

- ❌ Visual-comparison vago "tem KPIs em cima e lista no meio" — INSUFICIENTE
- ✅ Visual-comparison cirúrgico "4 KPI grid 4col gap=10px, primeiro card bg `var(--text)` dark + sparkline verde + valor `R$ 8.420,00` mono 18px"

**Saída esperada:** confirma escrita + git status mostrando o arquivo novo.

---

## 8. F3.5 — DECLARE BREAKAGE

**ANTES** de qualquer Edit/Write em `Modules/<X>/` ou `resources/js/Pages/`, lista pra Wagner:

### Template obrigatório:

```markdown
# O que VAI QUEBRAR que já existe

| Item legacy | Acontece | Risco | Mitigação |
|---|---|---|---|
| Resources/views/index.blade.php | Deletado, Inertia substitui | Baixo | — |
| Route::resource('xxx') placeholder | Removida, /recurring novo | Médio | Redirect 301 |
| Permissions hoje só X | +4 permissions seeder | Alto se prod sem atribuir | Seeder auto-atribui Admin#{biz} |
| Schema rb_subscriptions | +3 colunas (nullable) | Baixo, idempotente | — |
| Cross-module Modules/Repair link | Soft FK (sem constraint) | Médio Tier 0 SoC | Soft link nullable |

# O que NÃO toca (intocado)

- InterBankingClient · jobs · NfeBrasil/Listeners · Financeiro extrato · FSM Pipeline

# Tamanho estimado

- 10 ondas ≤300 linhas cada
- Fator 10x IA-pair ADR 0106 ≈ 3h por onda
- Total: ~30h IA-pair

# Aguardando seu "go" pra começar Onda 1. Sem mexer em código até você responder.
```

**Aguarda Wagner dizer "go" explicitamente.** Sem isso = STOP.

---

## 9. F3.6 — IMPLEMENT (onda-a-onda)

Cada onda segue ciclo cirúrgico:

### Por onda:

1. **PRE-FLIGHT** (skill `preflight-modulo` Tier A) — lê SPEC.md + CAPTERRA*.md + RUNBOOK*.md + ADRs do módulo
2. **Criar US no MCP** — `mcp__Oimpresso_MCP___Wagner__tasks-create` com title específico + DoD + refs
3. **Branch** — `git checkout -b claude/us-<modulo>-<NNN>-<slug-curto>` baseada em `main` (ou onda anterior se hard dependency)
4. **Write/Edit** arquivos planejados — nunca além do escopo da onda
5. **Pest local** cobrindo cross-tenant biz=1 vs biz=99 (skill `multi-tenant-patterns`)
6. **Commit conventional** + `Refs: US-<X>-NNN` + `Co-Authored-By` Claude
7. **Push** + `gh pr create` com test plan checkboxes
8. **AGUARDA Wagner mergear** antes de iniciar próxima onda

### Regras duras:

- **Se onda virou >300 linhas** → divide em sub-ondas (canon em 1a, código em 1b)
- **Se descobriu cross-module no meio** → STOP, escreve em DECLARE BREAKAGE+1, pergunta
- **Se Pest falha local** → corrige antes de push (CI não é desculpa)
- **Se PR conflicta com main** → rebase, force-push, re-validate

### Ordem canônica das ondas (template):

1. **Onda 0 — canon snapshot + visual-comparison** (chore, zero código produtivo)
2. **Onda 1 — schema aditivo** (migrations + Models novos + Pest)
3. **Onda 2 — Observer + cached cols + backfill command** (se aplicável)
4. **Onda 3-N — Backend controllers** (1 controller por onda)
5. **Onda N+1...M — Frontend Pages** (1 tela por onda)
6. **Onda M+1 — Cross-module integrations** (com fallback graceful)
7. **Onda última — cutover** (sidebar entry + redirect 301 + Permissions seeder + smoke prod)

---

## 10. F3.7 — SMOKE + HANDOFF

Após cutover:

### Smoke prod (Hostinger):

```bash
# Warm-up SSH (skill how-trabalhar §SSH)
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done

# Smoke /recurring biz=1
ssh -4 -o ConnectTimeout=900 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd /home/u906587222/public_html && php artisan tinker --execute="echo route(\"recurring.index\");"'
```

### Health check:

```bash
php artisan jana:health-check    # 5 checks SQL canon
```

### Handoff append-only (skill `memory-sync` + ADR 0130):

1. `memory/handoffs/YYYY-MM-DD-HHMM-cowork-to-inertia-<modulo>.md` (arquivo novo, NUNCA editar handoff antigo)
2. Atualiza `memory/08-handoff.md` adicionando 1 linha no topo
3. `mcp__Oimpresso_MCP___Wagner__tasks-update` US-<X>-NNN `status:done`
4. (opcional) skill `brief-update` auto-ativa pra atualizar `memory/requisitos/<Modulo>/BRIEFING.md`

---

## 11. Tools obrigatórias

| Tool | Onde usado |
|---|---|
| `mcp__Claude_Preview__preview_start` | F3.2 RENDER |
| `mcp__Claude_Preview__preview_eval` | F3.2 setar localStorage rota |
| `mcp__Claude_Preview__preview_screenshot` | F3.2 + F3.3 + F3.7 SMOKE |
| `mcp__Claude_Preview__preview_inspect` | F3.3 validar tokens CSS (cores, spacing) |
| `mcp__Oimpresso_MCP___Wagner__decisions-search` | F3.6 PRE-FLIGHT ADRs |
| `mcp__Oimpresso_MCP___Wagner__tasks-create` | F3.6 criar US-<X>-NNN |
| `mcp__Oimpresso_MCP___Wagner__tasks-update` | F3.6 marcar doing/done |
| `Bash` | F3.0/F3.1 tar/cp · F3.6 git/gh · F3.7 ssh smoke |
| `Read/Write/Edit` | F3.4/F3.6 docs + código |
| `Glob/Grep` | F3.6 PRE-FLIGHT mapeamento |
| `WebFetch` | F3.0 RECEIVE bundle via API design.anthropic.com |

---

## 12. Anti-patterns catalogados (sessão 2026-05-16)

| Anti-pattern | Sinal | Mitigação |
|---|---|---|
| Adivinhação visual | Implementar sem rodar F3.2 RENDER | F3.3 gate é obrigatório |
| Confundir strategy doc com mockup | "Diagnóstico KB-9.75.html é o que vou implementar" | F3.0 reconhece tipo doc; lê `.jsx` real referenciado |
| Pular F3.5 DECLARE | "Vou só fazer essa onda 1 rápido" | Wagner sempre quer saber o que vai quebrar antes |
| PR gigante (>2k ln) | "É só 1 PR, vai mesclar canon + código + docs" | Split em sub-ondas; canon em 1a (chore), código em 1b (feat) |
| Cross-module silencioso | Mexer em Modules/Repair sem avisar | F3.5 lista TODAS cross-module deps |
| Re-inflar proposta após corte | "Vou refinar e propor v2" | CLAUDE.md proibição: após Wagner cortar 1x, PARAR e perguntar |

---

## 13. Override autorizado

Wagner pode pular gates específicos via comentário no chat:

| Comando | Pula | Quando usar |
|---|---|---|
| `/render-override <razão>` | F3.2 RENDER + F3.3 VALIDATE | Wagner já viu visual no Cowork, confirma sem rodar local |
| `/declare-override <razão>` | F3.5 DECLARE BREAKAGE | Onda trivial sem risco (ex: doc, asset) |
| `/wait-override <razão>` | aguardar merge entre ondas | Wagner já aprovou todas as ondas pra rodar em série |

Cada override vira nota em `memory/sessions/YYYY-MM-DD-cowork-<modulo>-override.md`.

---

## 14. Próximo passo após este protocolo

Quando este protocolo entrar em produção (PR mergeado):

1. **ADR aceita** — promove deste doc pra ADR 0156 (próximo número) com `status: accepted`, `lifecycle: canonical`
2. **Skill complementar** — opcionalmente cria skill `cowork-to-inertia` Tier B com description que ativa quando user diz "implementa essa tela" / "vou te mandar design"
3. **Update PROTOCOL.md §2** — adiciona link pra este doc na linha F3
4. **Hook P2 dormente** — bloqueia Write/Edit em `resources/js/Pages/<X>/` se `<X>-visual-comparison.md` não existir em `memory/requisitos/<Modulo>/`

---

**Última atualização:** 2026-05-16 — criado pós-Onda 1 RecurringBilling v9,75 a pedido explícito Wagner ("crie um agente especialista nessa integração. e crie protocolo estado da arte"). Validado contra caso real (PR #968 canon + #970 schema).
