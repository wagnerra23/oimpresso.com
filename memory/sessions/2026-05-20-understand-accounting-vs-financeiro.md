---
slug: 2026-05-20-understand-accounting-vs-financeiro
title: "wagner-understand — Accounting conflita com Financeiro? o que fazer, como migrar, como perguntar?"
type: understand-decode
date: 2026-05-20
session: wagner-understand-spawn
spawned_by: claude-pai
status: ready-for-execution
---

# Decodificação

## Pedido cru de Wagner (texto exato)
> "Acho que o módulo Accounting conflita com o financeiro? o que fazer? e como migrar? o que pode acontecer? como devo perguntar?"

## Decodificação refinada

- **Objetivo principal:** Wagner quer saber se há trabalho duplicado/risco entre `Modules/Accounting` (UltimatePOS legacy) e `Modules/Financeiro` (novo BR, Ondas 12-23), e o que decidir.
- **Sub-objetivos:**
  1. Confirmar se há conflito REAL ou se são COMPLEMENTARES
  2. Saber se Accounting é zumbi ou ativo
  3. Caminho de ação: deprecar? migrar? manter paralelo? consolidar?
  4. Risco se migrar/deprecar (Larissa biz=4 e outros consumidores)
  5. META: aprender a refinar a própria pergunta (pediu "como devo perguntar?")
- **Critério de pronto:** Wagner sai sabendo (a) que decisão canon já existe, (b) qual perguntar refinar com base no inventário real, (c) próximo passo concreto.
- **Persona alvo:** Wagner (dev/arquiteto) — não é pedido de Eliana nem Larissa.
- **Implícitos detectados:** Wagner viu 2 módulos com nomes parecidos ao navegar `Modules/`, NÃO leu `memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md` (decisão de 2026-04-24, ~1 mês atrás).
- **Ambiguidades a confirmar:**
  - "Migrar" significa quê? Mover dados? Mover features? Deprecar Accounting?
  - Wagner quer DECIDIR agora ou só ENTENDER o estado?

## Regras protocolo aplicáveis (R1-R10)

| Regra | Relevância | O que exige aqui |
|---|---|---|
| **R3 Workflow 3 fases** | ALTA | Antes de qualquer Edit em `Modules/Accounting` ou `Modules/Financeiro` ler SPEC + RUNBOOK + BRIEFING + ADRs canon — já lidos neste dossier |
| **R4 Multi-tenant** | ALTA se mexer | Accounting tem GAP D1 documentado (filtro via JOIN `business_locations.business_id`, sem `BusinessScope` global em 70 Entities). Qualquer mudança preserva isolamento Tier 0 |
| **R9 Zero auto-mem** | ALTA | Decisão (manter paralelo / deprecar / fusionar) tem que ser nova ADR canon em `memory/decisions/NNNN-*.md`, não auto-mem privada |
| **R10 Aprovação humana** | ALTA | Wagner está PERGUNTANDO. Nada de commitar/criar task antes do "sim pode" |
| **R5 PT-BR + economia** | MÉDIA | Antes de gerar ADR de superseção em batch, confirmar com Wagner qual caminho ele escolheu |

Não aplicam neste turno: R1 (sem deploy), R2 (sem screenshot), R6 (sem Pest agora), R7 (sem Edit `.tsx`), R8 (worktree mas só leitura).

## Inventário no projeto

| O que procurei | Onde achei | Status |
|---|---|---|
| **ADR canon decidindo a relação** | `memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md` (2026-04-24, accepted) | **DECISÃO JÁ EXISTE — paralelos, sem cross-coupling, bridge opt-in futura** |
| ADR Accounting irmã | `memory/requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md` (2026-04-22) | accepted |
| Cross-imports `use Modules\Accounting` em Financeiro | grep | **ZERO** |
| Cross-imports `use Modules\Financeiro` em Accounting | grep | **ZERO** |
| BRIEFING Accounting | `memory/requisitos/Accounting/BRIEFING.md` | "candidato a `lifecycle: historical` se Vestuario/Financeiro encapsular relatórios próprios" (linha 50) |
| Charters `.tsx` Accounting | grep `resources/js/Pages/Accounting/*.charter.md` | **ZERO** (UI 100% Blade legacy) |
| Charters `.tsx` Financeiro | `resources/js/Pages/Financeiro/**/Index.charter.md` | 5+ charters ativos |
| Pages `.tsx` Accounting | grep | **ZERO** (12 Controllers chamam `view(...)`, zero `Inertia::render`) |
| Pages `.tsx` Financeiro | grep | 20+ telas Inertia/React |
| Migrations Accounting | `Modules/Accounting/Database/Migrations/` | 21 arquivos · datas **2019-2022** apenas (zombi de fato) |
| Migrations Financeiro | `Modules/Financeiro/Database/Migrations/` | 20 arquivos · datas **2026_04 → 2026_05_20** (hiperativo) |
| Total PHPs | find | Accounting 292 · Financeiro 146 |
| Commits Accounting últimos | `git log -- Modules/Accounting` | 86fbb42b4 housekeeping/markers + governance MEGA waves (sem feature nova) |
| Commits Financeiro últimos | `git log -- Modules/Financeiro` | Onda 23 OCR boleto · Onda 31 Portal Advisor · Onda 22 Anexos/Aprovação (intenso) |
| Tabelas DB Accounting | grep `Schema::create` | `chart_of_accounts`, `journal_entries`, `payment_types`, `budgets`, `transfers`, `countries`, `payment_details`, `account_subtypes`, `account_detail_types`, `branch_capital` (sem prefixo — **drift vs ADR ARQ-0005 linha 14** que afirma `accounting_*`) |
| Tabelas DB Financeiro | grep `Schema::create` | `fin_titulos`, `fin_titulo_baixas`, `fin_titulo_comments`, `fin_titulo_anexos`, `fin_planos_conta`, `fin_categorias`, `fin_contas_bancarias`, `fin_caixa_movimentos`, `fin_boleto_remessas`, `fin_extrato_lancamentos`, `fin_bank_statement_lines`, `advisors`, `advisor_business_access`, `ai_usage_log`, `accounts_legacy_map` (prefixo `fin_*`) |
| **Overlap nominal de tabelas** | comparação | **ZERO** — nomes disjuntos. Mas `accounts_legacy_map` (Financeiro) **referencia** Accounting (mapa legacy → novo) |
| Sidebar/menu | `app/Services/ShellMenuBuilder.php` | nem `accounting` nem `financeiro` aparecem aqui (menus por outro caminho) |
| Routes Accounting | `Modules/Accounting/Http/routes.php` | **82 routes Route::** ativas com `prefix=accounting` + middleware `AdminSidebarMenu` (UltimatePOS sidebar Blade) |
| modules_statuses.json | `D:/oimpresso.com/modules_statuses.json` | Accounting:true · Financeiro:true (**ambos ativos**) |
| Cliente piloto Larissa biz=4 | `memory/requisitos/Accounting/BRIEFING.md` linha 25 | "usa Accounting de forma **transparente** via Sells/Compras (JournalEntry gerado automaticamente). **Não acessa a UI Accounting diretamente** — consome via relatórios financeiros" |

**SE descobrir que já está 80%+ feito, PARE.** → SIM, decisão JÁ existe (ADR ARQ-0005 2026-04-24). Não é nova feature — é Wagner não lembrar/ainda não ter lido. Caminho recomendado: ler a ADR + opcional decidir agora se quer mudar curso (deprecar Accounting agora, em vez de quando Vestuario encapsular).

## Diagnóstico de conflito

- **NÃO É conflito de duplicação** — são **complementares com responsabilidades separadas**:
  - **Accounting** = contabilidade formal (partida dobrada, JournalEntry, SPED Contábil, contador externo, < 5% dos tenants) — UI Blade legacy UltimatePOS
  - **Financeiro** = operacional caixa do dia-a-dia (Título→Baixa→Movimento, AR/AP, fluxo, OFX, boletos BR, > 80% dos tenants) — UI Inertia/React moderna, persona Eliana [E]
- **Acoplamento real:** ZERO `use Modules\Accounting` em Financeiro e ZERO `use Modules\Financeiro` em Accounting. Bridge ainda não existe (era opt-in futuro na ADR ARQ-0005).
- **Drift catalogado:** ADR ARQ-0005 linha 14 diz `accounting_*` prefixo, código real usa nomes nus (`chart_of_accounts`, `journal_entries`...). Tem `accounts_legacy_map` em Financeiro que sugere uma migração parcial já começou.
- **Risco "número não bate":** ADR ARQ-0005 § Consequências Negativas já listou — dois lugares pra debug em incidente. Mitigação documentada: UI explicita "Financeiro = caixa / Accounting = livros formais".

## Pegadinhas conhecidas

- **[ADR ARQ-0005 §"Quando reavaliar"]** Critério canon pra reabrir decisão: ">30% dos tenants ativarem Accounting bridge" OU "upstream UltimatePOS deprecar Accounting" OU "cliente exigir DRE formal no Financeiro MVP". Nada disso aconteceu ainda — Wagner reabrir por **estética / confusão de nomes** vai contra a ADR.
- **[Tier 0 multi-tenant]** Accounting tem GAP D1 documentado: `BusinessScope` global ausente em 70 Entities — filtro via JOIN frágil. Qualquer migração tem que preservar/melhorar, não regredir.
- **[Append-only ADR canon]** Não EDITAR ADR ARQ-0005. Se decidir deprecar → nova ADR com `supersedes: [Financeiro/arq/0005]` ([ADR 0094 Constituição v2](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) princípio 7 transparência).
- **[Cliente piloto Larissa biz=4]** Consome Accounting TRANSPARENTEMENTE via Sells/Compras (JournalEntry automático em vendas pagas). Deprecar Accounting sem alternativa quebra os relatórios financeiros que ela consome.
- **[Dependências transitivas]** BRIEFING Accounting linha 21: "espinha dorsal pra **Vestuario**, **Financeiro**, **NfeBrasil**, **RecurringBilling**". Não é só Larissa — é todo CNAE consumidor.
- **[F3 frontend rejeitado lição]** Se decidir Inertia-rizar Accounting, ler `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` primeiro (6 meta-anti-padrões + 15 técnicos catalogados — Wagner rejeitou Financeiro F3 inteiro 2026-05-09).
- **[accounts_legacy_map já existe]** Há infraestrutura de mapping em `Modules/Financeiro/Database/Migrations/2026_05_09_210000_create_accounts_legacy_map_table.php` — possível embrião de migração parcial que ninguém terminou.
- **[Routes 82 ativas]** Deprecar Accounting precisa redirect/410 em 82 URLs `/accounting/*` (impacto SEO interno + bookmarks de admins).

## Como Wagner deveria perguntar (refino da pergunta original)

3 versões refinadas, do mais amplo ao mais cirúrgico — Wagner escolhe qual responde a intenção real:

### Versão A (estratégica — "estou perdido, me oriente")
> "Já existe decisão canon sobre relação Accounting × Financeiro? Se sim, qual é, e o que mudou desde então que justifique reabrir?"

→ Resposta direta: **sim, ADR ARQ-0005 (2026-04-24, accepted) — manter paralelos sem cross-coupling, bridge opt-in futura. Nada mudou no canon. Critérios de reabertura ainda não foram atingidos.**

### Versão B (operacional — "quero limpar a casa")
> "Accounting tem 21 migrations 2019-2022 e zero feature nova em 2026 — está zumbi de fato? Vale marcar `lifecycle: historical` agora, ou esperar Vestuario/Financeiro encapsular os relatórios contábeis primeiro?"

→ Resposta direta: **BRIEFING já marca como "candidato a `lifecycle: historical`". Pré-condição é Vestuario/Financeiro encapsular relatórios próprios — que ainda não aconteceu. Marcar agora seria prematuro e quebra consumidores (Larissa via JournalEntry automático).**

### Versão C (cirúrgica — "vou agir, qual o ADR mãe?")
> "Quero criar ADR de DEPRECAÇÃO PROGRAMADA Accounting com prazo + plano: (1) Financeiro absorve plano de contas BR (já em `fin_planos_conta`), (2) Vestuario absorve JournalEntry automático, (3) `/accounting/*` vira 410 Gone com redirect 90d. Faz sentido? Quais dependências quebro?"

→ Resposta direta: **caminho viável, mas é PR de superseção (`supersedes: [Financeiro/arq/0005]`). Bloqueadores: (a) reescrever JournalEntry trigger fora de Accounting, (b) migrar `accounts_legacy_map` data, (c) confirmar com Eliana se ela precisa SPED Contábil (Larissa não usa direto), (d) 82 routes precisam stub 410, (e) BRIEFING Vestuario/NfeBrasil/RecurringBilling precisam confirmar não-dependência runtime. Estimate ~6-8h IA-pair fator 10x (não 2 dias).**

### Versão D (meta — "como vou aprender a perguntar?")
> "Antes de eu perguntar sobre 2 módulos que parecem conflitar, qual checklist eu rodo na minha cabeça?"

→ Resposta direta: **checklist 5 passos:**
1. `git log --oneline -10 -- Modules/<X>` — feature ativa ou zumbi?
2. `find Modules/<X>/Database/Migrations -name "*.php"` — datas das migrations (legacy 2019 vs novo 2026?)
3. `cat Modules/<X>/SCOPE.md` — purpose declarado
4. `find memory/requisitos/<X>/adr` — ADRs canon do próprio módulo
5. `grep -r "use Modules\\\\<X>" Modules/<Y>` — cross-import = acoplamento real?

Cinco comandos = 30s = decisão fundamentada antes de escrever a pergunta.

## Plug-points (se decisão for deprecar/migrar/fusionar)

| Camada | Arquivo | Mudança hipotética |
|---|---|---|
| **ADR canon nova** | `memory/decisions/NNNN-deprecar-accounting-em-favor-financeiro.md` | Status `proposed` → Wagner aprova → `accepted`. `supersedes: [Financeiro/arq/0005, Accounting/arq/0001]` |
| **Plano deprecação** | `memory/requisitos/Accounting/DEPRECATION-PLAN.md` (NEW) | Fases: encapsular JournalEntry · migrar dados · routes 410 · remove provider |
| **module.json Accounting** | `Modules/Accounting/module.json` | `active: 0` quando todos consumidores migrarem |
| **modules_statuses.json** | `D:/oimpresso.com/modules_statuses.json` | `"Accounting": false` na fase final |
| **JournalEntry trigger** | hoje em `Modules/Sells/.../*Observer.php` (consumir transparente) | mover lógica pra Financeiro `TitulobaixaObserver` OU NfeBrasil-específico |
| **Routes redirect** | `Modules/Accounting/Http/routes.php` linha 6 (82 routes) | substituir por `Route::any('{any?}', fn() => abort(410))->where('any', '.*')` |
| **Sidebar UltimatePOS** | `Modules/Accounting/Resources/views/layouts/partials/sidebar.blade.php` + view composer | esconder entradas |
| **CHANGELOG** | `Modules/Accounting/CHANGELOG.md` | Entry "DEPRECATED: ver ADR NNNN" |
| **Migração dados** | nova migration em Financeiro | importar `chart_of_accounts` → `fin_planos_conta` via `accounts_legacy_map` (infra já existe) |

## Tasks atômicas + estimate (fator 10x ADR 0106)

| # | Task | Quem decide | Estimate | Bloqueia? |
|---|---|---|---|---|
| 1 | Ler `memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md` integral | **Wagner** | 5 min leitura | — (pré-requisito) |
| 2 | Decidir intenção: A (entender) / B (lifecycle: historical) / C (deprecação programada) / D (manter paralelo + zerar drift ADR linha 14) | **Wagner** estratégica | 15 min reflexão | bloqueia tudo |
| 3 | Se C: rascunhar ADR de superseção `memory/decisions/NNNN-deprecar-accounting...md` | Claude executa | ~30 min | depende #2 |
| 4 | Se C: rascunhar `DEPRECATION-PLAN.md` com fases | Claude executa | ~45 min | depende #3 |
| 5 | Se B: PR ajustar `Modules/Accounting/module.json` `grade.lifecycle="historical"` + entry CHANGELOG | Claude executa | ~10 min | depende #2 |
| 6 | Se D: PR errata ADR ARQ-0005 line 14 atualizando prefixo (drift `accounting_*` vs nomes nus) | Claude executa | ~15 min | depende #2 |
| 7 | Aprovação Wagner ("sim pode commit") | **Wagner** | ~5 min review | obrigatório R10 |

## Recomendação pro Claude pai

**Caminho recomendado:** mostrar pro Wagner ANTES de qualquer ação:
1. A ADR canon ARQ-0005 EXISTE e responde a pergunta dele há ~1 mês.
2. As 3 versões refinadas (A/B/C) da pergunta acima — deixar ele escolher intenção real.
3. NÃO escrever ADR nova nem deprecar sem ele dizer qual caminho (R10).

**O que confirmar com Wagner ANTES de codar:**
- Você leu ADR ARQ-0005? Se não, leia e me diga se concorda ainda em 2026-05-20.
- Sua intenção é: (A) entender / (B) marcar historical / (C) deprecar programado / (D) zerar drift ADR?
- Se (C): você confirma que Larissa não precisa de SPED Contábil formal? E os outros consumidores (Vestuario/NfeBrasil/RecurringBilling) — checou cada BRIEFING?

**Skills que DEVEM ativar:**
- `wagner-protocol-enforce` Tier A (regras R3 + R9 + R10 deste turno)
- `mcp-first` Tier A (já usei Read/Grep/Glob filesystem direto — Wagner pode pedir `decisions-search "accounting"` via MCP pra ele mesmo ver)
- `brief-first` Tier A (rodar `brief-fetch` se sessão nova)

**ADRs canon relacionadas:**
- `memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md` (mãe da decisão)
- `memory/requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md` (irmã)
- [ADR 0093 multi-tenant Tier 0](../decisions/0093-multi-tenant-isolation-tier-0.md) (Accounting tem GAP D1)
- [ADR 0094 Constituição v2](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) (append-only ADR)
- [ADR 0106 Recalibração fator 10x](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) (estimates acima)
- [ADR 0121 modular vertical](../decisions/0121-modular-vertical-...) (relevante se for deprecação)
- [ADR 0153 module-grade rubrica v1](../decisions/0153-module-grade-rubrica-v1.md) (`lifecycle: historical`)
- [ADR 0160 governance-v4 scoped scorecards](../decisions/0160-governance-v4-scoped-scorecards-buckets.md) (bucket `functional_horizontal` Accounting)
