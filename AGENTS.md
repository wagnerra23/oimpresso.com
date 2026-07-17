# AGENTS.md

Este repositório segue o padrão emergente de agentes de IA ([agents.md](https://agents.md/) spec). Para instruções completas, leia `CLAUDE.md` na raiz — ele é canônico.

@CLAUDE.md

> **G7 (2026-05-15):** import `@CLAUDE.md` adicionado pra agents compatíveis com Anthropic memory spec (Claude Code, Cursor agents.md, Codex). Compat retroativa preservada — agentes que só leem markdown puro continuam vendo o resumo abaixo.

> ⚠️ **Se você não expande `@` (Codex, Cursor, markdown puro): esta página é TODO o corpus que você recebe.** A linha `@CLAUDE.md` acima é sintaxe da spec Anthropic — quem não a expande vê só este arquivo. Por isso os ponteiros Tier 0 estão logo abaixo: **abra-os você mesmo antes de editar qualquer coisa.**

## Tier 0 — abra ANTES de editar (ponteiros, não cópia)

Esta seção é **só ponteiro por decisão de projeto**. Entre 2026-04-29 (quando a [ADR 0048](memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md) rejeitou a Vizra ADK) e 2026-07-09 (quando uma auditoria humana pegou, #4017), este arquivo serviu stack **rejeitada** — **porque restatava o canon em vez de apontar pra ele**. Cópia apodrece; ponteiro não. Resuma o mínimo, aponte o resto.

| Abra isto | Onde | Por que é Tier 0 |
|---|---|---|
| **Proibições** (o doc mais carregado do repo) | [`memory/proibicoes.md`](memory/proibicoes.md) | REGRA ZERO (protocolo do dono) · "mexeu, registra" · **cálculo de VALOR/ESTOQUE exige dupla confirmação + impacto antes→depois** · precedência quando artefatos discordam |
| **Ideias já testadas e REPROVADAS** (§5) | [`memory/proibicoes.md`](memory/proibicoes.md) §"Ideias avaliadas e DESCARTADAS" | Registro append-only de abordagens mortas. **Confira aqui antes de propor mecanismo** — re-propor ideia morta é regressão, e o §5 existe pra você mesmo se barrar citando a entrada |
| **Multi-tenant `business_id`** | [ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md) | **IRREVOGÁVEL.** Global scope em toda Model de negócio; job assíncrono passa `$businessId` no constructor (`session()` não existe em fila); vazar dado entre tenants é o pior bug possível aqui |
| **Runtime separado** | [ADR 0062](memory/decisions/0062-separacao-runtime-hostinger-ct100.md) | Hostinger (shared) ≠ CT 100. Zero daemon no Hostinger; **testes/PHPStan rodam só no CT 100** |
| **Idioma** | — | **PT-BR** em texto/commit/comentário/PR. Código em inglês; domínio de negócio em PT |

Regras de escopo desta página (não afrouxe sem ADR): **PT-BR**, ponteiro > prosa, e todo fato datado em **passado** ("promovido em 2026-06-24"), nunca afirmado em **presente** ("segue advisory") — afirmação em presente apodrece no primeiro flip ([proibicoes §5 2026-07-16](memory/proibicoes.md)).

> ⚠️ **O resumo abaixo é HISTÓRICO (2026-04) e continha stack REJEITADA declarada como "verdade canônica"** (Vizra ADK — rejeitada pela [ADR 0048](memory/decisions/0048-framework-agentes-laravel-ai-vizra-rejeitada.md); Mem0Rest nunca virou default). Corrigido na auditoria 2026-07-09. **A verdade viva está no `CLAUDE.md` + `memory/what-oimpresso.md`** — agente que só lê markdown puro: confie no bloco abaixo JÁ CORRIGIDO, mas prefira o CLAUDE.md.

Resumo operacional (corrigido 2026-07-09):

- Idioma: PT-BR (cliente brasileiro)
- Stack: Laravel 13.6 + PHP 8.4 + nWidart/laravel-modules ^10 + MySQL 8 + Inertia v3 + React 19 + Tailwind 4
- Módulos: núcleo comum + `Modules/<Vertical>` (Vestuario em prod, ComunicacaoVisual em construção) + Jana/Financeiro/NfeBrasil/RecurringBilling/Repair — ver `memory/what-oimpresso.md`
- Conformidade: Portaria MTP 671/2021, CLT, LGPD
- Sistema de memória: `memory/` (`CLAUDE.md` é canônico)
- **Stack de IA (ADR 0035 + 0048):** `laravel/ai` (camada A) + **agents próprios** em `Modules/Jana/Ai/Agents/` via `LaravelAiSdkDriver` (camada B — **Vizra ADK REJEITADA**, ADR 0048) + `MemoriaContrato` com **`MeilisearchDriver` default** e `NullDriver` dev (camada C)

Ao iniciar uma tarefa:
1. Leia `CLAUDE.md`
2. Leia `memory/08-handoff.md` (estado atual)
3. Leia o session log mais recente em `memory/sessions/`

Ao encerrar:
1. Atualize `memory/08-handoff.md`
2. Crie `memory/sessions/YYYY-MM-DD-session-NN.md`
3. Se decidiu algo arquitetural, crie ADR em `memory/decisions/`

---

**Atualizado:** 2026-07-17

> Este carimbo é **lido por máquina**: [`scripts/governance/agents-md-staleness.mjs`](scripts/governance/agents-md-staleness.mjs) (advisory, 5º eixo do workflow `briefing-code-staleness.yml`) compara esta data com a data-git de `CLAUDE.md` ∪ seus `@imports` e alerta quando o canon anda ≥30d à frente desta porta.
>
> **Quem editar esta página: refresque o conteúdo E bumpe a data.** Bumpar sem refrescar é teatro (o carimbo é auto-declarado — o sentinela mede tempo, não verdade). Esquecer de bumpar deixa a porta velha e o sentinela **acusa** — que é o lado seguro do erro. Foi exatamente o que faltou no caso Vizra: os toques de 2026-06-08 foram mecânicos (restore de squash + MapaTelas gerado), então a data-git dizia 06-08 enquanto o texto era de 04-26. Os números dessa comparação (e a margem de 1 dia com que o fallback escapa) vivem no header e no self-test do sentinela — **não os repita aqui**: [`agents-md-staleness.mjs`](scripts/governance/agents-md-staleness.mjs) é o dono, e re-derivar é `node scripts/governance/agents-md-staleness.mjs --selftest`.
