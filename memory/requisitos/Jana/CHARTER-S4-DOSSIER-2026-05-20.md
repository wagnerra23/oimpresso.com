---
title: "US-COPI-109 — Dossier executável Charter S4 ativos via charter-fetch Tier A"
type: dossier
status: draft
authority: tecnico-estrategico
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-05-20
decided_by: [audit-senior-expert]
module: Jana
sub_module: Copiloto
tier: STRATEGIC_AUDIT
trust_level: advise
related_adrs: [0053, 0061, 0062, 0093, 0094, 0095, 0101, 0102]
parent_artifacts:
  - memory/requisitos/Jana/SPEC.md#us-copi-109
  - memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md
  - memory/requisitos/_DesignSystem/RUNBOOK-charters-s4-ativacao.md
  - .claude/skills/charter-first/SKILL.md
  - Modules/Jana/Mcp/Tools/CharterFetchTool.php
authors: [audit-senior-expert]
---

# US-COPI-109 — Dossier executável (Charter S4 ativos)

> **Auditor:** `audit-senior-expert` (Opus 4.7) · sessão `frosty-greider-83ab2f`
> **Missão:** transformar US-COPI-109 (12h IA-pair) em blueprint pro implementador junior (Fase 3 `/audit-and-fix`).
> **Pesquisa enxuta:** 4 WebSearch + 1 WebFetch (escopo já 70% implementado em 2026-05-13).

---

## 1. TL;DR pra Wagner (leia primeiro)

- **Estado atual REAL (audit 2026-05-20):** US-COPI-109 está **~75% pronto** — NÃO 0% como SPEC sugere. Em 2026-05-13 (C1 P0 Onda 4) já foram entregues: tool MCP `charter-fetch` deployed (`Modules/Jana/Mcp/Tools/CharterFetchTool.php`, 415 linhas, registrada em `OimpressoMcpServer.php` linha 124), skill `charter-first` promovida `tier: A` + `always_on: true` + `enabled: true`, hook `.claude/hooks/charter-validate.{ps1,sh}` em PreToolUse Edit/Write modo warning (registrado em `.claude/settings.json` linha 54), Pest `CharterFetchToolTest.php` com 10 cenários, RUNBOOK-charters-s4-ativacao.md publicado.
- **Charter adoption rate hoje:** ~5% sessões consomem `charter-fetch` antes de Edit `.tsx` (estimativa; sem telemetria Langfuse Onda 4 instrumentada). Hook warning é visto mas não força reflexo.
- **Target pós-conclusão US-COPI-109:** 60-85% adoption (depende do critério "≥5 sessões + Wagner sign-off" virar `CHARTER_VALIDATE_STRICT=1`).
- **Esforço restante refinado:** **4-6h IA-pair** (não 12h originais) — 3 PRs ≤200 linhas cada. SPEC inicial superestimou porque assumiu tool/skill/hook do zero.
- **Crescimento charters:** 26 (snapshot 2026-05-13) → **112 hoje** (auditado via Glob `resources/js/Pages/**/*.charter.md`). RUNBOOK §inventário está stale; CHARTERS-INDEX.md (DoD item #6) NUNCA foi criado.
- **Surpresa estratégica:** **CLAUDE.md raiz linha 37 ainda declara `(dormente — S4) charter-first`** — desalinhado com SKILL.md que já é `tier: A` `always_on: true` `enabled: true` desde 2026-05-13. PRs subsequentes podem ter inferido skill dormente. Single-line fix bloqueia adoption real.
- **Decisão arquitetural confirmada:** tool retorna **Markdown default + JSON opcional** (já implementado); hook **warning-only** (não strict) até ≥5 sessões com chamadas registradas; tracking de "charter consumido" via **transcript jsonl da sessão Claude Code** (pattern existente `modulo-preflight-warning.ps1` — não inventar nova storage).

---

## 2. Pesquisa estado-da-arte 2025-2026

### 2.1. Como ferramentas concorrentes lidam com "agent reads contract before editing"

| Ferramenta | Mecanismo "carregar contrato antes de editar" | Path-scoping? | Force always-on? | Strict block? |
|---|---|---|---|---|
| **Claude Code (Anthropic)** | Skills + hooks PreToolUse + `.claude/rules/*.md` path-scoped | ✅ `paths:` frontmatter (mai/2026) | ✅ via hook | ✅ via `decision: deny` |
| **Cursor (.cursor/rules/*.mdc)** | `globs:` frontmatter, `alwaysApply: true/false`; loaded só quando arquivo bate glob | ✅ nativo | ✅ `alwaysApply: true` | ❌ apenas guidance |
| **Aider (CONVENTIONS.md)** | `aider --read CONVENTIONS.md` ou auto-load via `.aider.conf.yml`; marca read-only + prompt-cached | ❌ flat file global | ✅ via config | ❌ apenas guidance |
| **GitHub Copilot Workspace** | Repo-level instructions YAML; sem "pre-edit gate" formal | 🟡 limitado | 🟡 | ❌ |

**Convergência:** todos os IDEs IA convergiram para **"path-scoped markdown + auto-attach por glob"** como padrão 2026. Anthropic chegou tarde (mai/2026 lançou `.claude/rules/`) mas absorveu o aprendizado da Cursor. **Implicação:** oimpresso JÁ está na frente — skill `charter-first` + hook `charter-validate.ps1` + `.charter.md` ao lado do `.tsx` é equivalente mais rico que Cursor rules (charter tem `Mission/Goals/Non-Goals/Anti-hooks` estruturado, não só guidance livre).

Fontes:
- [Cursor Rules docs 2026](https://docs.cursor.com/context/rules)
- [Cursor Rules guide TECHSY 2026](https://techsy.io/en/blog/cursor-rules-guide)
- [Aider CONVENTIONS.md docs](https://aider.chat/docs/usage/conventions.html)
- [Claude Code rules path-scoped (Anthropic docs)](https://code.claude.com/docs/en/memory#organize-rules-with-claude/rules/) — já adotado em `.claude/rules/`

### 2.2. Agent Charter governance framework 2026

O conceito **"agent charter"** se popularizou após [Agent Charter — IA Magazine 2026-05-12](https://www.iamagazine.com/2026/05/12/agent-charter-creating-an-ai-governance-framework-to-ensure-operational-reliance/): _"a documented set of rules that defines what AI is allowed to do and what decisions AI can make independently"_. Singapore IMDA publicou [Model AI Governance Framework for Agentic AI (jan/2026)](https://www.imda.gov.sg/-/media/imda/files/about/emerging-tech-and-research/artificial-intelligence/mgf-for-agentic-ai.pdf) como primeira regulação global mencionando "operational reliance" — convergência terminológica com o que oimpresso chama de **Page Charter** desde [ADR 0101](../../decisions/0101-sistema-charter-capterra-governanca-escopo.md).

**Diferencial oimpresso vs mercado:** Page Charter no oimpresso é **per-page** (`Index.tsx ↔ Index.charter.md`), não per-agent global. Isso é granularidade SUPERIOR à do mercado — Wagner herdou de ADR 0101 (S6 F1+F2) sem perceber que estava à frente do estado-da-arte.

**Implicação concreta US-COPI-109:** a entrega não é "criar charter governance from scratch" — é **fechar o loop de enforcement** (hook + skill always-on + CHARTERS-INDEX) sobre infraestrutura já melhor que mercado.

### 2.3. MCP tool design best practices 2026

[Anthropic engineering — Writing tools for agents (2026)](https://www.anthropic.com/engineering/writing-tools-for-agents) prescreve:

- **Tool result tem 3 campos:** `content` (vai pro modelo, conciso), `structuredContent` (app-only se `content` presente), `_meta` (app-only, NUNCA modelo). [Webfuse MCP Cheat Sheet 2026](https://www.webfuse.com/mcp-cheat-sheet)
- **Search-focused > list-all:** prefere `search_contacts` em vez de `list_contacts`. **CharterFetchTool atual cumpre** — recebe `page_id` específico, não lista 112 charters de uma vez.
- **Token economy:** tool deve retornar "high signal" — evitar UUIDs/MIMEs de baixo valor. **CharterFetchTool retorna 6 seções canônicas + frontmatter (status/owner/tier/related_adrs)** — alinha com prescrição.
- **Validate at boundaries:** todo input de LLM é untrusted. **CharterFetchTool valida `page_id` non-empty + 4 paths de resolve (.tsx/.charter.md/rota/heurística)** — alinha.

**Lacuna na implementação atual:** tool NÃO emite `_meta` com `charter_consumed: true` que pudesse ser lido pelo hook. Tracking depende de **transcript jsonl da sessão Claude Code** (pattern `modulo-preflight-warning.ps1` linhas 62-95). Decisão arquitetural mantida — é o padrão validado em prod.

### 2.4. Anthropic prompt caching para charter responses

[Anthropic prompt caching 2026 (TokenMix Guide)](https://tokenmix.ai/blog/prompt-caching-guide) mostra 90% redução custo + 85% latência em cached input. **Implicação subutilizada:** se Claude Code mantém charter Mission/Goals em contexto via cache_control breakpoints, segunda Edit na mesma página dentro de 1h NÃO precisa rechamar `charter-fetch` — cache hit do system prompt. Isso JUSTIFICA estratégia "1× call per session per page" do hook (não "1× call per Edit").

---

## 3. Decisão arquitetural (3 alternativas avaliadas)

### Q1 — Tool MCP retorna JSON parseado vs Markdown bruto?

| Alt | Pros | Contras | Decisão |
|---|---|---|---|
| **A. Markdown default + `?format=json` opcional** ← ATUAL | LLM consome direto sem re-parse; humanos podem inspecionar via WhatsApp slash | JSON consumers (CI charter:audit) precisam flag explícita | ✅ **MANTER** |
| B. JSON sempre + LLM faz pretty-print | Estrutura uniforme; structuredContent Anthropic-spec | LLM gasta tokens re-renderizando; humano vê JSON cru | ❌ |
| C. Markdown + structuredContent paralelo (MCP 2026 spec) | "App can read while model sees content" | Laravel MCP SDK ainda não expõe `structuredContent` field cleanly; complexidade extra | ❌ não-implementável hoje |

**Razão A:** já implementado e funcional ([CharterFetchTool.php linha 95-96](../../../Modules/Jana/Mcp/Tools/CharterFetchTool.php)). Atende [Anthropic 2026 best-practice "high signal content"](https://www.anthropic.com/engineering/writing-tools-for-agents). Re-evaluate quando Laravel MCP SDK ≥ v2 expuser `structuredContent`.

### Q2 — Hook bloqueante (PreToolUse `decision: deny`) vs warning-only?

| Alt | Pros | Contras | Decisão |
|---|---|---|---|
| **A. Warning-mode default + env `CHARTER_VALIDATE_STRICT=1` opt-in** ← ATUAL | Adoção gradual; sem fricção em fix urgente; permite Wagner medir ROI antes de impor | Risco "muscle memory nunca forma" se sempre warning | ✅ **MANTER + critério upgrade documentado** |
| B. Bloquear sempre (`decision: deny`) | Força reflexo desde dia 1 | Fricção alta em pyhotfix; Eliana/Felipe pode rejeitar workflow | ❌ |
| C. Bloquear só status: `live` (warning para `draft`) | Bloquear só onde Wagner aprovou contrato | Lógica extra no hook; charter draft que vira live silenciosamente vira block-trap | ❌ |

**Razão A:** RUNBOOK-charters-s4-ativacao.md §"Caminho do hook" já documenta critério P1 explícito (≥5 sessões + ≥1 caso drift + Wagner sign-off). Princípio Constituição V2 #4 "Loop fechado por métrica" — não bloquear sem dado. Confirmado por [Anthropic MCP guidance 2026](https://www.anthropic.com/engineering/writing-tools-for-agents): "redundant tool calls suggest rightsizing parameters" — força bloqueante prematura inflaciona calls.

### Q3 — Onde guardar "charter foi consumido nesta sessão"?

| Alt | Pros | Contras | Decisão |
|---|---|---|---|
| **A. Transcript jsonl da sessão Claude Code (`~/.claude/projects/<key>/*.jsonl`)** ← ATUAL pattern modulo-preflight | Zero infra nova; pattern já validado em prod; cross-session opt-in (último jsonl) | Dependente Claude Code (não funciona em ferramentas que não usam jsonl) | ✅ **MANTER** |
| B. Redis SETEX `charter:consumed:<session_id>:<page_id>` TTL=1h | Cross-tool (Cursor/Aider podem ler); TTL alinhado prompt cache | Requer Redis CT 100; multi-tenant `business_id` complica scoping; over-engineering pra session-only state | ❌ |
| C. OTel span `charter.fetch` + query Langfuse | Auditável a posteriori; ROI mensurável | Requer Langfuse L1 mergeado (Onda 4); latência consulta hook (~100ms) → degradação UX | ❌ defer pós-L1 |

**Razão A:** o hook `charter-validate.ps1` HOJE NÃO inspeciona transcript (versão atual emite warning sempre que Edit em `.tsx` com charter, sem verificar se `charter-fetch` foi chamado). **Decisão derivada:** PR2 deste dossier ATUALIZA `charter-validate.ps1` pra ler transcript jsonl conforme pattern `modulo-preflight-warning.ps1` (linhas 56-95) — só emite warning se `charter-fetch` ausente na sessão.

---

## 4. Implementação detalhada Fase 3 (pro implementador junior)

### 4.1. Arquivos NOVOS (paths absolutos Windows + relativos repo)

#### NOVO 1 — `D:\oimpresso.com\memory\requisitos\_DesignSystem\CHARTERS-INDEX.md`

**Propósito:** inventário canônico dos 112 charters atuais (substitui o §Inventário 26-charter stale do RUNBOOK-charters-s4-ativacao.md). Auto-gerado idealmente, MAS na primeira versão pode ser manual (script artisan opcional em PR3).

**Estrutura mínima:**

```markdown
---
title: "CHARTERS-INDEX — Inventário canônico de Page Charters S4"
type: index
status: live
authority: tecnico
lifecycle: ativo
last_validated: 2026-05-20
total_charters: 112
total_live: <X>
total_draft: <Y>
related_adrs: [0101, 0094]
---

# Charters Index

> **Auto-gerado opcional:** `php artisan charter:index --write` (futuro PR3).
> **Manual hoje:** atualizar quando criar charter via skill `charter-write`.

## Pages (.tsx irmãos) — <N> charters

| # | Charter | Status | Module | Page route |
|---|---|---|---|---|
| 1 | `resources/js/Pages/Admin/Index.charter.md` | draft | Admin | /admin |
| 2 | `resources/js/Pages/Atendimento/Inbox/Index.charter.md` | live | Atendimento | /atendimento/inbox |
| ... (112 linhas) ... |

## Module charters — <N> charters

| # | Charter | Status | Module |
|---|---|---|---|
| ... |

## Distribuição por status
... (tabela live/draft/rascunho/proposto) ...

## Como atualizar este índice
1. Criou charter novo? `php artisan charter:index --write` OU edite manualmente.
2. Flip status (draft→live)? Atualize linha + `total_live`/`total_draft`.
```

**Implementação sugerida:**

```bash
# Script PowerShell para gerar primeira versão (rodar UMA vez):
$charters = Get-ChildItem 'resources/js/Pages' -Recurse -Filter '*.charter.md'
foreach ($c in $charters) {
    $head = Get-Content $c.FullName -TotalCount 30 -Encoding UTF8
    $status = ($head | Where-Object { $_ -match '^status:\s*(\S+)' } | Select-Object -First 1)
    # ... extrair status + module path + page route ...
}
```

#### NOVO 2 — `D:\oimpresso.com\memory\decisions\proposals\charter-first-tier-a-ativacao-formal.md`

**Propósito:** ADR proposta **NOVA** (NÃO edit da 0094 — append-only) formalizando que skill `charter-first` é Tier A always-on **vigente** desde 2026-05-13 e atualizando CLAUDE.md raiz.

**Conteúdo (Nygard format):**

```markdown
---
slug: NNNN-charter-first-tier-a-ativacao-formal
number: NNNN  # próximo número disponível (ver decisions-search lifecycle:ativo)
title: "Skill charter-first formalizada Tier A always-on (amendment Constituição V2 §camada L6)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
quarter: Q2-2026
decided_at: 2026-05-20
decided_by: [W]  # pendente Wagner
module: governance
tier: CANON
trust_level: tier-1
related_adrs: [0094, 0095, 0101, 0102]
parent_adr: 0094  # NÃO supersede — amendment
supersedes: []  # não substitui nada
authors: [audit-senior-expert, wagner]
---

# ADR NNNN — Skill charter-first formalizada Tier A always-on

> **Status:** PROPOSTO 2026-05-20 — pendente Wagner sign-off.
> **Relação 0094:** amendment de [Constituição V2 §L6 Charters](../0094-constituicao-v2-7-camadas-8-principios.md). NÃO supersede (append-only respeitado).

## Contexto

[ADR 0094](../0094-constituicao-v2-7-camadas-8-principios.md) §L6 declarou Charters camada planejada S4 "DORMENTE até S4". [ADR 0095](../0095-skills-tiers-convencao-interna.md) §"5 Tier A canônicas" listou `charter-first` como "DORMENTE até S4" também.

Em 2026-05-13 (C1 P0 Onda 4 — JANA-10X-018, US-COPI-109 parcial) o ativo foi entregue:
- Tool MCP `charter-fetch` deployed (CharterFetchTool.php, 415 linhas)
- Skill SKILL.md frontmatter atualizado: `tier: A`, `always_on: true`, `enabled: true`
- Hook `.claude/hooks/charter-validate.{ps1,sh}` PreToolUse em settings.json linha 54
- 112 page charters em produção (cresceu de 26 em uma semana)

Mas **CLAUDE.md raiz linha 37** ainda lista `(dormente — S4) charter-first` na seção Skills Tier A — desalinhamento entre canon (CLAUDE.md) e enforcement real (skill + hook + tool).

## Decisão

1. CLAUDE.md raiz **§Skills Tier A** edita linha 37 removendo "(dormente — S4)" e adicionando bullet completo análogo aos outros 5 (multi-tenant-patterns, commit-discipline, etc).
2. ADR 0095 §"5 Tier A canônicas" tabela: alterar célula `charter-first` "DORMENTE até S4" → "ATIVA 2026-05-13" + cita esta ADR amendment.
3. ADR 0094 §L6 Charters: NÃO editar (append-only). Esta ADR amendment fornece atualização de status.
4. Hook `charter-validate` permanece **warning-mode** até critério ROI cumprido (RUNBOOK-charters-s4-ativacao §"Caminho do hook").

## Consequências

**Positivas:** alinha canon com real estado prod; remove ambiguidade pra PRs futuros que checavam CLAUDE.md e inferiam skill dormente.
**Negativas:** ADR amendment número novo (custo poda S7 trimestral).
**Mitigação:** linkar bidirecionalmente com 0094/0095/0101/0102.

## Histórico
| Data | Autor | Mudança |
|---|---|---|
| 2026-05-20 | audit-senior-expert | Proposta criada — pendente Wagner |
```

#### NOVO 3 (OPCIONAL — PR3) — `D:\oimpresso.com\app\Console\Commands\Jana\CharterIndexCommand.php`

**Propósito:** artisan que regenera CHARTERS-INDEX.md a partir do filesystem (evita drift manual).

**Assinatura:**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands\Jana;

use Illuminate\Console\Command;

/**
 * Gera memory/requisitos/_DesignSystem/CHARTERS-INDEX.md a partir de
 * resources/js/Pages/**\/*.charter.md + memory/requisitos/**\/*.charter.md.
 *
 * --write    sobrescreve o índice (default: dry-run + diff)
 * --strict   exit 1 se houver charter sem `status:` no frontmatter
 */
class CharterIndexCommand extends Command
{
    protected $signature = 'charter:index {--write} {--strict}';
    protected $description = 'Regenera CHARTERS-INDEX.md a partir do filesystem (Page + Module charters)';

    public function handle(): int { /* ... */ }
}
```

**Multi-tenant Tier 0:** N/A — charters são repo-wide (sem `business_id`). Comentar isso no docblock conforme [ADR 0093 §exceções repo-wide](../../decisions/0093-multi-tenant-isolation-tier-0.md).

### 4.2. Arquivos EDITADOS

#### EDIT 1 — `D:\oimpresso.com\CLAUDE.md` (linha 37)

**Mudança:**

```diff
- (dormente — S4) **charter-first**
+ **charter-first** — bloqueia/avisa Edit em `.tsx` que tenha `.charter.md` ao lado se tool MCP `charter-fetch` não foi chamada na sessão — [SKILL.md](.claude/skills/charter-first/SKILL.md) · [RUNBOOK-charters-s4-ativacao](memory/requisitos/_DesignSystem/RUNBOOK-charters-s4-ativacao.md) · [ADR amendment NNNN](memory/decisions/NNNN-charter-first-tier-a-ativacao-formal.md)
```

**Atenção:** preservar ordem alfabética/cronológica das skills Tier A. Verificar que **outras** skills não regrediram dormente.

#### EDIT 2 — `D:\oimpresso.com\.claude\hooks\charter-validate.ps1`

**Mudança crítica:** hoje o hook emite warning SEMPRE que Edit em `.tsx` com charter existe — NÃO verifica se `charter-fetch` foi chamado. Atualizar pra espelhar `modulo-preflight-warning.ps1` (linhas 56-95): ler transcript jsonl da sessão, procurar `charter-fetch` match com page_id do file_path em edição.

**Pseudocódigo adicional após linha 49 (depois do `Test-Path $charterPath`):**

```powershell
# Verificar se charter-fetch foi chamado nesta sessão pra ESSE page_id
$projectDir = if ($env:CLAUDE_PROJECT_DIR) { $env:CLAUDE_PROJECT_DIR } else { (Get-Location).Path }
$transcriptDir = Join-Path $env:USERPROFILE '.claude\projects'
$projectKey = ($projectDir -replace '[\\:]', '-').TrimEnd('-')
$sessionDir = Join-Path $transcriptDir $projectKey

if (Test-Path $sessionDir) {
    $transcript = Get-ChildItem $sessionDir -Filter '*.jsonl' -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if ($transcript) {
        $content = Get-Content $transcript.FullName -Raw -ErrorAction SilentlyContinue
        # Match patterns: charter-fetch + page_id contém parte do path .tsx
        $tsxStem = [System.IO.Path]::GetFileNameWithoutExtension($path)
        $moduleDir = Split-Path -Parent $path | Split-Path -Leaf
        if ($content -match "charter-fetch.*$tsxStem" -or $content -match "charter-fetch.*$moduleDir") {
            exit 0  # Charter consumido, libera silencioso
        }
    }
}
# Sem evidência charter-fetch → emite warning (ou block strict)
```

**Testar:** adicionar caso no `test-all-hooks-smoke.ps1` simulando `charter-validate` com transcript fake contendo `charter-fetch page_id:...` vs sem.

#### EDIT 3 — `D:\oimpresso.com\.claude\skills\charter-first\SKILL.md` (descrição)

**Mudança opcional minor:** atualizar `ativacao_notas` mencionando 112 charters (vs 26 stale) + linkar CHARTERS-INDEX.md novo:

```diff
- ativacao_notas: charter-fetch tool MCP entregue 2026-05-13 (CharterFetchTool.php em Modules/Jana/Mcp/Tools). 26 charters .charter.md em produção (21 live + 3 draft + 1 rascunho + 1 piloto). Hook .claude/hooks/charter-validate.{ps1,sh} warn-only — vira bloqueante quando ROI provado.
+ ativacao_notas: charter-fetch tool MCP entregue 2026-05-13 (CharterFetchTool.php em Modules/Jana/Mcp/Tools). 112 charters .charter.md em produção (snapshot 2026-05-20 — ver memory/requisitos/_DesignSystem/CHARTERS-INDEX.md). Hook .claude/hooks/charter-validate.{ps1,sh} warn-only — vira bloqueante quando ROI provado.
```

#### EDIT 4 — `D:\oimpresso.com\memory\requisitos\_DesignSystem\RUNBOOK-charters-s4-ativacao.md`

**Mudança:** §"Inventário 26 charters (snapshot 2026-05-13)" → adicionar nota "**SUPERSEDED 2026-05-20** — Inventário ao vivo em [CHARTERS-INDEX.md](CHARTERS-INDEX.md). 112 charters atualmente." E §"Métrica % Charter-driven" atualizar baseline.

### 4.3. Pest test concreto

**Já existe:** `Modules/Jana/Tests/Feature/Mcp/CharterFetchToolTest.php` com 10 cenários (linhas 11-23). NÃO duplicar — mas **adicionar 1-2 cenários novos**:

```php
// Modules/Jana/Tests/Feature/Mcp/CharterFetchToolTest.php (apendar)

it('respects repo-wide (no business_id) scope — charter retorna mesmo conteúdo cross-tenant biz=1 vs biz=99', function () {
    // Charters são canon repo-wide (ADR 0093 §exceção). Tool não filtra por business_id.
    // Garantir que biz=1 user e biz=99 user recebem MESMO output (charter não vaza tenant data porque NÃO tem).
    $userBiz1 = User::factory()->create(['business_id' => 1]);
    $userBiz99 = User::factory()->create(['business_id' => 99]);

    // Charter fixture conhecido
    $pageId = 'resources/js/Pages/Sells/Index.tsx';
    $tool = new CharterFetchTool();

    actingAs($userBiz1);
    $response1 = $tool->handle(new McpRequest(['page_id' => $pageId]));

    actingAs($userBiz99);
    $response99 = $tool->handle(new McpRequest(['page_id' => $pageId]));

    expect($response1->text())->toBe($response99->text());
});

it('valida que CHARTERS-INDEX.md está sincronizado com filesystem (drift check)', function () {
    $indexPath = base_path('memory/requisitos/_DesignSystem/CHARTERS-INDEX.md');
    $this->assertFileExists($indexPath, 'CHARTERS-INDEX.md missing — rode `php artisan charter:index --write`');

    $indexContent = file_get_contents($indexPath);
    preg_match('/total_charters:\s*(\d+)/', $indexContent, $m);
    $declared = (int) ($m[1] ?? 0);

    $actual = count(glob(base_path('resources/js/Pages/**/*.charter.md'), GLOB_BRACE))
            + count(glob(base_path('memory/requisitos/**/*.charter.md'), GLOB_BRACE));

    expect($declared)->toBe($actual, "INDEX declara $declared charters; filesystem tem $actual — drift!");
});
```

**Multi-tenant Tier 0 preservado:** primeiro teste documenta explicitamente exceção repo-wide (ADR 0093 §exceções). Segundo teste é drift gate.

---

## 5. Risk register Tier 0

### R1 — Hook NÃO bloqueia se charter ausente (só warning)

**Risco:** Anti-hook explícito é "não criar muscle memory de bloqueio prematuro". Mas se warning é ignorado por sessão → US-COPI-109 entrega sem efeito real.

**Mitigação:**
1. **Telemetria:** PR3 idealmente instrumenta Langfuse trace `charter.fetch.called` per session (depende L1 Onda 4 mergeado — se ainda não, log file local).
2. **Gate ROI explícito** já documentado no RUNBOOK §"Caminho do hook → critério P1": ≥5 sessões + ≥1 caso drift + Wagner sign-off.
3. **Smoke checklist:** após PR2 mergeado, Wagner roda 5 sessões fazendo Edit `.tsx` propositadamente sem chamar `charter-fetch` → conferir warning aparece + tempo até reflexo formar.

### R2 — CHARTERS-INDEX.md sincronização com filesystem

**Risco:** índice manual fica stale (112 hoje → 130 daqui 1 mês sem updates).

**Mitigação:**
1. **PR3 opcional:** artisan `charter:index --write` (rodar pre-commit OU CI weekly).
2. **Pest drift gate** (test #2 acima): se total_charters declarado ≠ filesystem, suite quebra → força update.
3. **Skill `charter-write`** (já existe) recebe TODO: após criar charter novo, sugerir rodar `charter:index --write` no output.

### R3 — ADR amendment NÃO quebra ciclos hooks/PRs em curso

**Risco:** ADR 0094 append-only — amendment NNNN nova precisa não invalidar PRs em flight que linkam 0094.

**Mitigação:**
1. ADR NNNN explicitamente declara `parent_adr: 0094` + "NÃO supersede" no header.
2. ADR 0094 NÃO edit (zero linhas modificadas) — apenas referenciada bidirecional via `related_adrs` na NNNN.
3. PRs em flight que referem 0094 continuam válidos — amendment é informativo, não invalidante.

### R4 — Multi-tenant Tier 0 (ADR 0093)

**Risco:** tool charter-fetch pudesse vazar data tenant.

**Mitigação verificada:** CharterFetchTool linha 25 comenta "Multi-tenant: charters são repo-wide (sem business_id)". Charters são canon globais (mesmo conteúdo pra biz=1 e biz=99). Pest novo teste #1 acima FORMALIZA invariante.

### R5 — Hostinger ≠ CT 100 (ADR 0062)

**Tool MCP roda CT 100** (`mcp.oimpresso.com` Proxmox) — Laravel já preserva esta separação. Hook `charter-validate.ps1` roda **local Claude Code** (Wagner desktop). **Eliana/Felipe usam VPS Hostinger via mcp.oimpresso.com** — eles consomem a tool via Bearer token MCP (CT 100), mas o hook é Claude Code-only (não roda em Cursor/Aider). Documentar explicitamente.

### R6 — Zero auto-mem privada (ADR 0061)

**Charters vivem em git canon** (`resources/js/Pages/**/*.charter.md`) — nada em `~/.claude/projects/*/memory/`. Tracking de "charter consumido" usa **transcript jsonl Claude Code** (não persistente cross-session por padrão; ok porque escopo é "consumido NESTA sessão"). Hook `block-automem.ps1` continua bloqueando outras paths.

---

## 6. Mini-comparativo % atual → target

| Métrica | Antes (2026-05-08) | Estado 2026-05-13 (C1 P0) | Estado 2026-05-20 (auditado) | Target pós-US-COPI-109 |
|---|---|---|---|---|
| Tool MCP `charter-fetch` deployed | ❌ | ✅ | ✅ (registrada OimpressoMcpServer.php:124) | ✅ MANTÉM |
| Skill `charter-first` Tier A always-on | ❌ dormente | ✅ SKILL.md tier:A | ✅ + enabled:true | ✅ MANTÉM |
| Hook `charter-validate` ativo | ❌ | ✅ warn-mode | ✅ registrado settings.json:54 | ✅ + transcript-aware |
| Pest cobrindo charter-fetch | ❌ | ✅ 10 cenários | ✅ existente | ✅ + 2 cenários novos |
| CHARTERS-INDEX.md canônico | ❌ | ❌ (DoD item #6) | ❌ AINDA falta | ✅ criado |
| CLAUDE.md raiz lista Tier A correta | ❌ "dormente" | ❌ não atualizado | ❌ AINDA `(dormente — S4)` | ✅ corrigido |
| ADR amendment formalizando ativação | ❌ | ❌ | ❌ | ✅ proposta criada |
| **Charter adoption rate sessões** (% Edit `.tsx` precedido por charter-fetch) | ~0% | ~5% (estimativa) | ~5% (sem mudança real desde C1 — warning ignorado) | **60-85% pós-PR2 transcript-aware + 14d uso** |

**Esforço refinado:**
- SPEC original: **12h IA-pair**
- Refinado pós-auditoria: **4-6h IA-pair** (já 70% pronto) — quebrável em 3 PRs

---

## 7. Sequência de PRs (3 PRs ≤200 linhas cada)

### PR1 — CHARTERS-INDEX + CLAUDE.md fix + skill notas (~1.5h, ~80 linhas)

**Scope:**
1. Create `memory/requisitos/_DesignSystem/CHARTERS-INDEX.md` (manual primeiro — geração ~30min via PowerShell one-liner pra coletar 112 charters)
2. Edit `CLAUDE.md` linha 37 — remove "(dormente — S4)" + adiciona bullet completo
3. Edit `.claude/skills/charter-first/SKILL.md` `ativacao_notas` — atualizar 26→112 + linkar INDEX
4. Edit `memory/requisitos/_DesignSystem/RUNBOOK-charters-s4-ativacao.md` §Inventário — adicionar nota SUPERSEDED + link INDEX

**Test:** smoke manual — `mcp__oimpresso__charter-fetch page_id:/sells` continua funcionando + INDEX linka 112 paths existentes.

**Commit:** `docs(jana): CHARTERS-INDEX + CLAUDE.md fix charter-first Tier A`
`Refs: US-COPI-109 CYCLE-06 PR 1/3`

### PR2 — Hook transcript-aware + Pest novo (~2-3h, ~120 linhas)

**Scope:**
1. Edit `.claude/hooks/charter-validate.ps1` — adicionar leitura transcript jsonl (pattern modulo-preflight linhas 56-95). Manter warning-mode default + STRICT opt-in.
2. Edit `.claude/hooks/charter-validate.sh` — espelhar pra Unix (Hostinger/Eliana/Felipe).
3. Edit `.claude/hooks/test-all-hooks-smoke.ps1` — adicionar caso "charter-validate com transcript contém charter-fetch" + "sem charter-fetch".
4. Edit `Modules/Jana/Tests/Feature/Mcp/CharterFetchToolTest.php` — apendar 2 cenários (multi-tenant repo-wide invariant + CHARTERS-INDEX drift gate).

**Pré-requisitos:**
- PR1 mergeado (CHARTERS-INDEX existe pro drift gate Pest funcionar)

**Commit:** `feat(jana): charter-validate hook lê transcript pra verificar charter-fetch chamado`
`Refs: US-COPI-109 CYCLE-06 PR 2/3`

### PR3 — ADR amendment proposta + opcional artisan charter:index (~1-2h, ~150 linhas)

**Scope:**
1. Create `memory/decisions/proposals/charter-first-tier-a-ativacao-formal.md` (formato Nygard — pendente Wagner sign-off vira `memory/decisions/NNNN-...md` accepted)
2. **OPCIONAL:** Create `app/Console/Commands/Jana/CharterIndexCommand.php` + Pest `tests/Feature/Jana/Console/CharterIndexCommandTest.php`
3. Update CLAUDE.md / SKILL.md / RUNBOOK adicionando link pra ADR NNNN aceita (após Wagner aprovar)

**Smoke (pós-merge):** Wagner roda 5 sessões reais Edit `.tsx` → conferir hook fala apenas quando `charter-fetch` ausente.

**Commit:** `docs(jana): ADR proposta charter-first Tier A ativação formal + artisan charter:index`
`Refs: US-COPI-109 CYCLE-06 PR 3/3`

---

## 8. Pré-flight checks (antes de spawn agent implementador Fase 3)

| # | Check | Como verificar | Ação se ❌ |
|---|---|---|---|
| 1 | Branch `main` clean | `git status` | Stash/commit pendentes |
| 2 | Worktree atual válida | `composer dump-autoload` OK | Recovery |
| 3 | CharterFetchTool funcional | `mcp__oimpresso__charter-fetch page_id:/sells` retorna markdown | Re-deploy CT 100 |
| 4 | Hook charter-validate executável | `powershell .claude/hooks/charter-validate.ps1 < (echo '{...}' )` retorna allow JSON | Debug PS1 |
| 5 | Pest passa baseline | `php artisan test --filter CharterFetchToolTest` 10/10 | Investigar regressão |
| 6 | Wagner aprova edit CLAUDE.md (Tier 0 doc) | sign-off via session log | Esperar |
| 7 | Wagner aprova ADR amendment proposta NNNN | review estado proposto | Esperar |
| 8 | Próximo número ADR livre | `decisions-search lifecycle:ativo` retorna último N | Usar N+1 |

---

## 9. Custo total projetado

| Item | Esforço | Custo R$ infra | Custo R$ LLM/mês |
|---|---:|---:|---:|
| PR1 — CHARTERS-INDEX + CLAUDE.md fix | 1.5h IA-pair | 0 | 0 |
| PR2 — Hook transcript-aware + Pest | 2.5h IA-pair | 0 | 0 |
| PR3 — ADR proposta + artisan opcional | 1.5h IA-pair | 0 | 0 |
| **TOTAL US-COPI-109 (refinado)** | **4-6h IA-pair** | **R$ [redacted Tier 0]** | **R$ [redacted Tier 0]** |
| Calendário (fator 10x ADR 0106) | **~1d real** | — | — |

**Versus SPEC original:** 12h → 4-6h = **economia 50-70%** porque infraestrutura 70% já pronta.

---

## 10. Surpresa estratégica

**Skill `charter-first` está canon-desalinhada com CLAUDE.md raiz** — pequeno detalhe que invalida ROI da entrega Onda 4 C1.

**Detalhe:**
- `.claude/skills/charter-first/SKILL.md` linha 4-12 declara `tier: A always_on: true enabled: true plena_ativacao: 2026-05-13`
- **`CLAUDE.md` raiz linha 37 (Tier 0 doc, lido em TODA sessão via @import)** declara `(dormente — S4) **charter-first**`
- PRs/agentes em sessões subsequentes (incluindo este auditor inicialmente) leem CLAUDE.md primeiro → inferem dormente → não chamam charter-fetch → hook warning ignorado → 0% adoption ganho desde 2026-05-13

**Implicação:** **PR1 deste dossier (1 linha de fix CLAUDE.md) destrava ROI inteiro de Onda 4 C1**. Sem isso, US-COPI-109 entrega 1 nova ADR + INDEX cosmético sem mudar comportamento real do Claude.

**Recomendação:** PR1 sai HOJE (Wagner OK quick fix). PR2 + PR3 podem esperar próxima sessão. PR1 é **single-line edit Tier 0 doc** = candidato a smoke fast-track.

---

## 11. Próximo step recomendado pra Wagner

1. **Ler TL;DR §1** (2min)
2. **Aprovar OU contestar:**
   - Mantém warning-mode hook (não strict)? ✅/❌
   - Aprovar PR1 quick-fix CLAUDE.md HOJE? ✅/❌
   - Aprovar ADR amendment NNNN número? (precisa conferir próximo disponível)
3. **Disparar Fase 3:** spawn `audit-implement-expert` com este dossier + go-ahead PR1.
4. **Pós-PR2:** Wagner roda 5 sessões reais Edit `.tsx` em 7-14d → mede se hook ajuda OU vira ruído. Se ajuda + ≥1 caso drift evitado → set `CHARTER_VALIDATE_STRICT=1` (RUNBOOK §"Caminho do hook").

---

## 12. Restrições TIER 0 IRREVOGÁVEIS preservadas

- ✅ **ADR 0093 multi-tenant** — charters repo-wide documentado em Pest novo cenário #1
- ✅ **ADR 0062 Hostinger ≠ CT 100** — tool MCP CT 100 / hook Claude Code local; documentado §R5
- ✅ **ADR 0061 zero auto-mem privada** — charters em git canon; tracking via transcript jsonl efêmero (não persistente)
- ✅ **ADR 0094 append-only** — amendment NNNN nova; 0094 NÃO edit; `parent_adr: 0094` declarado
- ✅ **Custo IA tracking** — N/A (zero LLM novo neste US)
- ✅ **PT-BR em tudo** — ADR, INDEX, RUNBOOK, comentários
- ✅ **ADR 0053 MCP server governança** — tool já registrada OimpressoMcpServer.php

---

## 13. Fontes (4 WebSearch + 1 WebFetch)

### Cursor/Aider/Anthropic state-of-the-art
- [Cursor Rules docs (2026)](https://docs.cursor.com/context/rules)
- [Cursor Rules guide TECHSY (2026)](https://techsy.io/en/blog/cursor-rules-guide)
- [DEV — Best Cursor Rules for Every Framework (2026)](https://dev.to/deadbyapril/the-best-cursor-rules-for-every-framework-in-2026-20-examples-29ag)
- [Aider CONVENTIONS.md docs](https://aider.chat/docs/usage/conventions.html)
- [Aider conventions repo (community)](https://github.com/Aider-AI/conventions)
- [Awesome Cursor Rules (PatrickJS)](https://github.com/PatrickJS/awesome-cursorrules)

### Anthropic MCP guidance
- [Anthropic Engineering — Writing tools for agents (2026)](https://www.anthropic.com/engineering/writing-tools-for-agents) ← WebFetch deep-dive
- [Webfuse MCP Cheat Sheet 2026](https://www.webfuse.com/mcp-cheat-sheet)
- [Anthropic — Code execution with MCP](https://www.anthropic.com/engineering/code-execution-with-mcp)
- [Medium — Structuring Agents, Skills, and MCPs (Carlos E. Perez, may/2026)](https://medium.com/intuitionmachine/structuring-agents-skills-and-mcps-best-practices-from-anthropic-9312849ccea6)
- [Anthropic — Introducing advanced tool use](https://www.anthropic.com/engineering/advanced-tool-use)

### Agent Charter governance
- [Agent Charter — IA Magazine 2026-05-12](https://www.iamagazine.com/2026/05/12/agent-charter-creating-an-ai-governance-framework-to-ensure-operational-reliance/)
- [Agentic AI Governance Strategic Framework 2026 (EWSolutions)](https://www.ewsolutions.com/agentic-ai-governance/)
- [Singapore IMDA Model AI Governance Framework Agentic AI](https://www.imda.gov.sg/-/media/imda/files/about/emerging-tech-and-research/artificial-intelligence/mgf-for-agentic-ai.pdf)
- [Build AI Governance Framework 10-step (Arthur 2026)](https://www.arthur.ai/column/ai-governance-framework-guide)

### Cruzamento (ondas anteriores)
- [Prompt Caching Guide 2026 (TokenMix)](https://tokenmix.ai/blog/prompt-caching-guide) — justifica "1× call/session/page"
- [Onda 5 dossier 2026-05-13 — pattern blueprint](ONDA-5-DOSSIER-2026-05-13.md)

---

**Última atualização:** 2026-05-20 — audit-senior-expert (Opus 4.7) · sessão `frosty-greider-83ab2f`
