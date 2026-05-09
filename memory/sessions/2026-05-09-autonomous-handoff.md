---
title: Master report — execução autônoma 16h Opus 4.7
date: 2026-05-09
type: session
branch: claude/auto-2026-05-09-mercado
status: handoff
---

# Master report — execução autônoma 2026-05-09

> **Wagner**: 6 agents paralelos rodaram em ~70min wallclock. 18 artefatos gerados, branch `claude/auto-2026-05-09-mercado` (sem push). Este doc é seu painel de retomada — leia top-down.

## 🚨 Atenção CRÍTICA (faça antes de qualquer demo a prospect)

### Bug #1 — Wipe DB via HTTP público (Critical, 5min fix)
- **Onde:** `routes/install_r.php:19` → `InstallController.php:265`
- **O que faz:** `POST /install/install-alternate` roda `migrate:fresh --force` sem auth. Qualquer um na internet apaga a DB de produção.
- **Fix:** wrap rota em middleware `['auth', 'superadmin']` + flag `APP_ENV !== 'production'`.

### Bug #2 — Governança quebrada visível (Critical reputacional, 10min fix)
- **Onde:** `composer.json:26-27`
- **O que faz:** `laravel/octane` e `laravel/mcp` em `require` (não require-dev) violam [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md) Tier 0 IRREVOGÁVEL. Hostinger não roda daemons — esses pacotes só pertencem ao CT 100.
- **Fix:** mover pra `require-dev` ou criar `composer-ct100.json` separado.
- **Por que importa em venda:** prospect técnico abre composer.json, vê contradição com discurso de "governança formal Constituição v2".

### Bug crítico SEFAZ (bloqueia smoke há 2 dias parado)
- **Onde:** 11 templates tributários em `Modules/NfeBrasil/database/seeders/templates/*.php`
- **O que faz:** salvam `tributacao_default` SEM `ncm_default`. `NfeService::emitirParaTransaction` exige NCM 8 dígitos e dá throw.
- **Mitigação rápida:** setar manualmente NCM em `/nfe-brasil/tributacao/config-default` antes de criar venda biz=1.
- **Fix definitivo:** 1 linha em cada template (~15min total).

## 📋 Top 10 ações que SÓ você pode fazer (priorizadas por ROI)

| # | Ação | Tempo | Por quê | ROI |
|---|------|-------|---------|-----|
| 1 | Fix bug #1 + #2 acima | 15min | Reputação + segurança real | ⭐⭐⭐⭐⭐ |
| 2 | Validar pricing tier proposto (`memory/sales/2026-05/06-pricing-tiers.md`) | 30min | Sem isso, sales material trava | ⭐⭐⭐⭐⭐ |
| 3 | Setar NCM default + executar smoke SEFAZ biz=1 | 12-20min | Destrava goal CYCLE-02 #5 (parado desde 2026-05-07) | ⭐⭐⭐⭐⭐ |
| 4 | Revisar lista 10 Tier 1 (`memory/research/2026-05-prospeccao/01-*.md`) e marcar 3 alvos da semana | 20min | Sair da concentração ROTA LIVRE | ⭐⭐⭐⭐⭐ |
| 5 | Disparar cold email versão A (NFe-de-boleto-pago) pros 3 alvos | 30min | Sales material está pronto, só personalizar nome | ⭐⭐⭐⭐⭐ |
| 6 | Revisar Audit findings #3-5 e decidir se vai corrigir agora ou backlog | 15min | LGPD/WCAG são "soft no" mas pegam audit corporativo | ⭐⭐⭐⭐ |
| 7 | Aprovar/cortar/editar one-pagers Financeiro/NFe/Jana/Repair | 45min | Material direto-pra-prospect | ⭐⭐⭐⭐ |
| 8 | Setar canal AFACOM+ ou Singrafs (endorsement setorial) | 1h pesquisa | Ambos concorrentes têm, oimpresso não | ⭐⭐⭐⭐ |
| 9 | Decidir: app mobile (gap real vs Mubisys) ou ficar web-responsivo? | 30min | ADR de feature wish — sem decidir, fica perdendo prospect | ⭐⭐⭐ |
| 10 | Decidir: DAM (gap vs MubiDrive) — vale construir ou tercerizar? | 30min | Mesmo padrão | ⭐⭐⭐ |

## 🎯 Inteligência de mercado consolidada

### Wedge recomendado (síntese das 3 frentes de pesquisa)

**Perfil ICP refinado:** dono de gráfica 1-10 funcionários · faturamento R$ [redacted Tier 0]-200k/mês · em SP capital ou Grande SP · hoje em **Bling+planilha** OU **Calcme/Mubisys frustrado pós-trial**.

**3 ângulos de ataque (ordem de prioridade testada):**

1. **NFe automática a partir de boleto pago** — único diferencial onde Bling/Tiny/Zênite/Mubisys/Calcme/Alfa **literalmente não têm** equivalente. Dor mensurável (dono sabe quantas NFes saem com atraso/mês).
2. **Bulk-update via Jana** — "Jana, aumenta 5% em toda lona MMHL150" — bate dor universal #3 ("abrir cada papel um por um").
3. **Transparência radical** — case ROTA LIVRE público clicável vs trial gating dos concorrentes (bate dor #1 universal: "trial promete mais que entrega").

### Top 3 alvos cold approach (semana 1)

| # | Empresa | Cidade | Ângulo |
|---|---------|--------|--------|
| 1 | **SP Sign** | Vila Romana, SP | "OS rastreável vs WhatsApp do encarregado" — 30 anos, maquinário top, dor multi-etapa |
| 2 | **New Signs Campinas** | Campinas | "departamentos próprios" confessados no marketing — dor multi-etapa quase auto-confessa |
| 3 | **Sandice** | Moema, SP | Único com B2B portal já — tech-educado, conquistar = case |

Lista completa de 42 gráficas em `memory/research/2026-05-prospeccao/01-graficas-sp-capital-grande-sp.md`.

### Concorrentes — onde oimpresso ataca, onde concorrente ataca

**Calcanhares pra atacar primeiro:**
- **Zênite**: instabilidade fim-de-semana documentada em Reclame Aqui (gráfica com balcão Sex/Sáb sente)
- **Mubisys**: cliente público postou *"engessado, sem integração, bom só pra pequeno porte"* — literal quote pro discurso "quando você dobra, Mubisys trava"
- **Calcme**: 4 reclamações públicas explícitas "trial promete mais que entrega" sem reembolso

**Riscos reversos (gaps oimpresso a fechar):**
- Mubisys tem **MubiDrive (DAM 150+ TB)** — gráfica trabalha com arquivo pesado
- Zênite + Mubisys têm **app mobile iOS+Android** nativo — oimpresso só web responsivo
- Zênite tem **coleta IoT máquinas** (produção real-time)
- Ambos têm **endorsement setorial** (Singrafs/Assingrafs/AFACOM+)

### Top 5 dores universais do setor (rankeadas)

1. Trial promete mais que entrega — sem reembolso, só crédito
2. Suporte lento + treinamento ineficiente pós-contrato
3. Bulk update de preço/material é manual ("abrir cada item")
4. Sem relatório de margem por OS (só Calcgraf tem pós-cálculo formal)
5. Importação de produto não via XML NFe (cliente espera, descobre que insere 1-a-1)

## 📦 Pacote sales material (11 arquivos prontos)

Em `memory/sales/2026-05/`:

| Arquivo | Uso |
|---------|-----|
| `01-cold-emails.md` | 3 versões A/B/C — recomendo começar com **A (NFe-de-boleto-pago)** |
| `02-cold-call-script.md` | 90s + tabela 8 objeções |
| `03-linkedin-dm.md` | 3 perfis: dono ativo, gerente ops, dono que postou dor |
| `04-roi-calculator.md` | Fórmulas Excel + 3 cenários base/worst/best |
| `05-demo-script.md` | 15min ato-a-ato + trapas a evitar + plano B |
| `06-pricing-tiers.md` | **Você precisa validar números** — Starter R$ [redacted Tier 0] / Pro R$ [redacted Tier 0]+2.500 / Enterprise R$ [redacted Tier 0]+5.000 |
| `07-faq-objecoes.md` | 20 objeções B2B avançadas |
| `onepager-financeiro.md` | Visão Unificada AR/AP, fluxo Asaas |
| `onepager-nfebrasil.md` | NFe automática boleto pago, 11 UFs |
| `onepager-jana-ia.md` | Chat com memória, recall hybrid |
| `onepager-repair.md` | Produção oficina drag-drop |

## 🔬 Auditoria pre-sales (`memory/audits/2026-05-pre-sales/`)

3 arquivos com findings detalhados:
- `01-onboarding-break-test.md` — break-test do fluxo signup→primeira venda
- `02-wcag-manual-5-telas.md` — WCAG 2.1 AA das 5 telas demo
- `03-security-review-quick.md` — security review estático

**Findings críticos #1 e #2 já destacados no topo deste doc.** Findings #3-5 listados acima na tabela de ações.

## 📐 SEFAZ pre-flight (`memory/sessions/2026-05-09-smoke-sefaz-preflight.md`)

- 7/9 checks de código passam ✅
- 1 dependente do servidor (cert .pfx)
- 1 ❌ gap CRÍTICO `ncm_default` ausente nos templates → bloqueia smoke
- Tempo estimado pra você executar: **~12 min** copy-paste do runbook (após corrigir NCM)

## 🚫 Restrições respeitadas

- ❌ Zero git push
- ❌ Zero PR/merge
- ❌ Zero edição de código (apenas leitura + análise)
- ❌ Zero toque em multi-tenant scope (feedback Wagner 2026-05-09)
- ❌ Zero disparo SEFAZ real
- ✅ Branch local `claude/auto-2026-05-09-mercado` com 18 artefatos
- ✅ Apenas dados públicos no mapeamento de prospects (zero PII pessoal)
- ✅ Cita fontes (URL) em afirmações não-óbvias

## 🔮 Backlog gerado (não criei tasks no MCP — você decide)

**Hipóteses sem sinal qualificado** ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — vira ADR feature wish, não US ativa, até prospect pedir):
- App mobile nativo iOS+Android (gap vs Zênite/Mubisys)
- DAM nativo (gap vs MubiDrive)
- Coleta IoT máquinas (gap vs Zênite)
- Endorsement Singrafs/Assingrafs/AFACOM+ (canal)

**Bugs com sinal qualificado** (criar task quando aprovar):
- 5 bugs auditoria (ver tabela acima)
- 3 bugs SEFAZ (ncm_default templates, NfeService:115 ordem var, .env.example NFEBRASIL_*)

## 📊 Métricas de execução

- **Wallclock**: ~70min (6 agents paralelos vs ~7h sequencial)
- **Tokens consumidos**: ~640k pelos sub-agents (cabe na cota Max 20x)
- **Artefatos**: 18 arquivos
- **Cota Claude Design preservada**: skill esgotada não foi tocada (auditoria WCAG manual)

## 🎬 Próximos passos sugeridos quando você voltar

**Cenário "tenho 2h":**
1. Corrigir bugs #1 + #2 (15min)
2. Validar pricing tiers (30min)
3. Marcar 3 alvos da lista Tier 1 (15min)
4. Disparar 3 cold emails versão A (30min)
5. Setar NCM default + smoke SEFAZ biz=1 (15-30min)

**Cenário "tenho a tarde toda":**
- Acima + revisar 4 one-pagers + decidir mobile/DAM/endorsement

**Cenário "quero acelerar":**
- Spawno mais 6 agents domingo pra: (a) outras cidades (RS/PR), (b) personalização individual dos cold emails pros 10 Tier 1, (c) deck investidor, (d) plano de migração concreto pra 1 cliente Calcme frustrado, (e) script de demo gravado em vídeo, (f) audit das skills/CI dos PRs últimos 30 dias.

---

# 🔁 Rodada 2 — execução autônoma adicional (mesma sessão)

> Wagner aprovou rodada 2 logo em seguida. Mais 6 agents paralelos. ~15min wallclock total. **12 novos artefatos**.

## 🚨 Achados críticos que apareceram

### Bug #4 (audit CI) — Tests órfãos violando proibição CLAUDE.md
- **Onde:** `Modules/Ponto/Tests` (11 tests) e `Modules/ADS/Tests` (7 tests) NÃO estão registrados em `phpunit.xml`
- **Impacto:** 18 tests produzem falsa cobertura — nunca rodam em CI
- **Fix:** registrar em `phpunit.xml` (5min). Proibição explícita do CLAUDE.md.

### Bug #5 (audit CI) — `adr-lint.yml` não é required-check
- **Impacto:** PR #357 mergeou com adr-lint falhando, falha persiste em main. **ADRs com frontmatter inválido chegam silenciosamente.**
- **Fix:** adicionar como required em branch protection (5min).

### Bug #6 (audit CI) — `mwart-gate.yml` soft permitiu regressão massiva
- **Onde:** PR #349 mergeou com gate comentando "❌ Violações detectadas"
- **Custo já pago:** 5 PRs follow-up (#355, #358, #359, #361)
- **Cobertura artefatos atual:** visual-comparison **4/127 Pages (3%)** · charter **13/127 (10%)** · RUNBOOK **22/127 (17%)**
- **Decisão pendente:** declarar HARD ou manter SOFT com SLA de backfill. ADR nova.

### Bug #7 (audit CI) — `visual-regression.yml` é placeholder não-funcional
- **Onde:** `.github/workflows/visual-regression.yml`
- **Impacto:** 3 steps com `continue-on-error: true` — sempre retorna "success" sem rodar test real

### Gap #1 (playbook migração) — DAM nativo bloqueia contratos Mubisys
- **Sinal qualificado** ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)): Mubisys tem MubiDrive 150+ TB. Cliente Mubisys com >100GB de arte arquivada **não migra sem isso**.
- **Estimativa:** ~80h dev pra MVP S3-wrap

### Gap #2 (playbook migração) — API docs públicas
- **Sinal qualificado:** literal RA Mubisys fev/2023 "engessado sem integração" — selling point #1 contra Mubisys
- **Estimativa:** 16-24h Felipe [F]. Pré-requisito do 1º contrato Mubisys.

## 📊 Inteligência de mercado expandida (RS + PR)

**41 gráficas adicionais mapeadas** (RS: 19 · PR: 22) → universo total: **83 prospects mapeados** entre SP+RS+PR.

### Padrões regionais distintos
- **RS** — RMPA dominante (Canoas+POA), longevidade alta (Centeno 1970, Ideograf 1986, VSO 1950) → **base legada pesada, dor real de modernização**. Clientes enterprise concentrados (Petrobras/Braskem/Sicredi/Zaffari/Panvel).
- **PR** — diferença chave: 3 capitais regionais ativas (Curitiba + Londrina + Maringá) + cluster Cascavel oeste. Maquinário mais explícito (Fava: MIG+CNC+router 5×2m+500m²/dia) → **dor produção complexa coordenada**.
- **vs SP** — SP tem mais ecommerce próprio + B2B portal; RS tem mais legado offset; PR tem mais multi-cidade real.

### Top 6 alvos cold approach RS+PR
- **RS**: Centeno (Canoas) · Difachini (POA — portal próprio substituível) · Cia do Letreiro (Caxias)
- **PR**: Fava (Curitiba — produção multi-tech) · VSO (Londrina/Maringá — multi-loja 75 anos) · Crealle (SJP — marketing maduro vs gap backoffice)

**Bonus**: Sul10 (matriz RS, filial Curitiba) = ponte de venda cruzada possível.

## ✉️ 10 cold emails personalizados individualmente (Tier 1 SP)

Em `memory/sales/2026-05/personalizados/`:

| # | Empresa | Ângulo | Probabilidade resposta |
|---|---------|--------|------------------------|
| 09 | New Signs Campinas | Multi-etapa (literal-confessada) | ⭐⭐⭐⭐⭐ |
| 04 | SP Sign | Multi-etapa (gmail.com = TI simples) | ⭐⭐⭐⭐⭐ |
| 01 | Sandice | Backend pro portal existente | ⭐⭐⭐⭐ |
| 02-08, 10 | demais Tier 1 | Mix NFe / Jana / multi-etapa | ⭐⭐⭐ |

**Ângulo dominante**: multi-etapa (4) > NFe (3) > Jana (2) > backend (1).
**INDEX CRM-leve**: `00-INDEX.md` na pasta — status, ângulo, último contato.

## 📊 Deck empresa 15 slides

`memory/sales/2026-05/08-deck-empresa.md`. Multipropósito (prospect/canal/investidor) com ordens de slides recomendadas por audiência no rodapé.

- 🏆 **Slide mais forte**: #7 Mapa competitivo (resiste a "vocês são só mais um")
- ⚠️ **Slide mais fraco**: #11 TAM/SAM/SOM (todos placeholders) — bloquear até Singrafs/Sebrae OU trocar por slide qualitativo
- Slides 4, 8, 11 precisam validação numérica antes de uso comercial

## 🎬 Roteiro vídeo demo 3min

`memory/sales/2026-05/11-roteiro-video-demo.md`. 5 cenas timeline-stamped, biz=99 obrigatório.

**Catch defensivo do agent**: substituiu wow #3 (bulk update Jana) por **Repair Kanban drag-drop** — bulk update Jana **não está confirmado em prod**, Kanban entregue 2026-05-09 (PR #363).

- 1º vídeo: ~25min (10min setup + 30s personalização + 4min take + 6min retakes + 3min edição + 2min upload)
- Lote de 5 num dia: ~1h45min total
- **Recomendação**: gravar versão A primeiro (NFe), B/C reaproveitam 80%

## 🔄 Playbooks migração Calcme + Mubisys

`memory/sales/2026-05/09-playbook-migracao-calcme.md` + `10-playbook-migracao-mubisys.md`.

| Playbook | Tier | Setup | Mensal | Cutover | Garantia |
|---------|------|-------|--------|---------|----------|
| **Calcme** | Pro | R$ [redacted Tier 0] | R$ [redacted Tier 0] | 1 sábado + paralelo 7d | 60d |
| **Mubisys** | Enterprise | R$ [redacted Tier 0]+ | R$ [redacted Tier 0] | Faseado 4-6 sem | 90d |

**🎯 Recomendação clara: ATACAR CALCME PRIMEIRO**. Razões:
1. Cliente menor migra rápido
2. NFCe + Asaas + Visão Unificada já cobrem 90% do escopo Calcme
3. 4 RA literais "não cumpre" = dor pública explícita
4. ROTA LIVRE/Larissa = case real comparável em porte
5. **Sem dependência de DAM/NFSe/MDFe não-entregues** (gaps #1, #3, #5 só aparecem com Mubisys)

Mubisys exige 3-4 gaps construídos antes do 1º contrato pra entregar com honestidade.

## 🔬 Audit CI dos PRs últimos 30 dias

`memory/audits/2026-05-pre-sales/04-ci-pr-audit-30d.md`.

### Métricas de CI
- **p50: ~2m30s | p95: ~3m30s** por PR
- Gargalo: `ci.yml/php` step (~1m55s — vendor install + boot + Pest Form shim)
- ~90s envelope sobrando — sub-utilizado
- **CI rápido NÃO é o problema; cobertura real é**

### Workflows quebrados/flaky
- `visual-regression.yml` — placeholder
- `quick-sync.yml` — ~13% fail rate
- `cowork-inbox.yml` — 3 falhas seguidas em janela
- **Zero `--no-verify` detectado** — disciplina manual OK; gap é **institucional** (gates soft + checks não-required)

### Zero-cobertura em controllers críticos
- Grow (142 controllers / 0 tests)
- Connector (30/0)
- Crm (21/0)
- Superadmin (14/0)
- Accounting (12/0)
- Officeimpresso (7/0)
- **Total >200 controllers sem nenhum Pest**

## 📋 Top 10 ações pós-Rodada-2 (re-priorizadas)

| # | Ação | Tempo | ROI |
|---|------|-------|-----|
| 1 | Fechar bug #1 (wipe DB público) + #2 (octane/mcp em require) — Rodada 1 | 15min | ⭐⭐⭐⭐⭐ |
| 2 | Registrar tests órfãos Ponto+ADS em phpunit.xml + adr-lint required | 10min | ⭐⭐⭐⭐⭐ |
| 3 | Setar NCM default + smoke SEFAZ biz=1 | 15-30min | ⭐⭐⭐⭐⭐ |
| 4 | Validar pricing tiers (Starter/Pro/Enterprise) | 30min | ⭐⭐⭐⭐⭐ |
| 5 | Disparar 3 cold emails personalizados (New Signs Campinas, SP Sign, Sandice) | 30min | ⭐⭐⭐⭐⭐ |
| 6 | Construir API docs públicas (Swagger) — Felipe [F] | 16-24h | ⭐⭐⭐⭐⭐ pré-Mubisys |
| 7 | Decidir mwart-gate HARD vs SOFT+SLA backfill (ADR nova) | 1d ADR | ⭐⭐⭐⭐ |
| 8 | Buscar números TAM/SAM/SOM Singrafs/Sebrae OU trocar slide 11 do deck | 2-4h | ⭐⭐⭐⭐ |
| 9 | Estender Pest CI step pra Modules/*/Tests/Feature | 2-3h | ⭐⭐⭐ |
| 10 | Decidir DAM (construir 80h vs adiar Mubisys até pedido) | 30min decisão | ⭐⭐⭐ |

## 📈 Stats consolidados Rodada 1+2

- **Wallclock total**: ~85min wall (12 agents · ~14h trabalho equiv sequencial — eficiência ~10x)
- **Artefatos**: 31 arquivos (19 Rodada 1 + 12 Rodada 2)
- **Universo prospects mapeados**: 83 gráficas (SP 42 + RS 19 + PR 22)
- **Cold emails prontos**: 3 templates + 10 personalizados = 13 emails copy-paste
- **Tokens consumidos**: ~1.1M sub-agents
- **Cota Claude Design**: continua preservada (auditoria WCAG manual)
- **Commits**: 1 commit em `claude/auto-2026-05-09-mercado` (Rodada 1) + 1 a fazer agora

---

**Branch:** `claude/auto-2026-05-09-mercado` (sem push). Faça `git log -2` pra ver os 2 commits, `git diff main` pra revisar tudo de uma vez.
