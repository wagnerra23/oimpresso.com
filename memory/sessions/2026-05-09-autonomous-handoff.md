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

**Perfil ICP refinado:** dono de gráfica 1-10 funcionários · faturamento R$ 30-200k/mês · em SP capital ou Grande SP · hoje em **Bling+planilha** OU **Calcme/Mubisys frustrado pós-trial**.

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
| `06-pricing-tiers.md` | **Você precisa validar números** — Starter R$ 299 / Pro R$ 599+2.500 / Enterprise R$ 1.499+5.000 |
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
| **Calcme** | Pro | R$ 2.500 | R$ 499 | 1 sábado + paralelo 7d | 60d |
| **Mubisys** | Enterprise | R$ 5.000+ | R$ 1.499 | Faseado 4-6 sem | 90d |

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

---

# 🔁🔁 Rodada 3 — execução autônoma adicional (3ª onda)

> Wagner aprovou rodada 3 logo em seguida. Mais 6 agents paralelos. ~10min wallclock.
> **Foco**: ROI/canais/outbound/case/pricing/ADR — assets que destravam decisões reais ao invés de só material de venda.

## 🎯 Decisões propostas (proposed — Wagner valida)

### Decisão #1 — DAM nativo: **WAITING-LIST (Cenário D)** com fallback Híbrido
`memory/decisions/proposals/dam-roi-mubisys-decision.md`

- **Construir só após 3 contratos Enterprise pagos** (R$ 15k+ em setup fees comprometidos)
- Lucro 12m: R$ 95.480 (D) vs R$ 100.740 (A) = só **5% de diferença** em troca de eliminar risco capex queimado
- **Cláusula contratual "DAM em 90d ou desconto 30%"** vira selling point (governança formal vira compromisso auditável)
- Payback ~30 dias após 1º contrato
- Risco: cláusula percebida como red flag → 0 contratos em 90d → **mitigação**: testar em 2 discoveries reais antes de formalizar
- **Alinhado ADR 0105** (cliente sinal qualificado) — Cenário A viola explicitamente

### Decisão #2 — mwart-gate HARD: **HÍBRIDO #4** (HARD canônico, SOFT satélites)
`memory/decisions/proposals/proposta-mwart-gate-hard.md` (provável ID 0120 quando aceita)

- HARD apenas em `resources/js/Pages/<Mod>/<Tela>.tsx` canônico
- SOFT em `_components/`, `_Showcase/`, `Components/shared/`
- Warm-up 14d (D+0 → D+14, 2026-05-23)
- **Justificativa decisiva (1 número)**: 100% das regressões silenciosas dos últimos 30d foram em path canônico, **0 em satélites**
- **KPI sucesso D+30**: ≥95% PRs com 9/9 artefatos (baseline ~30%) + queda velocidade ≤15% + ≤2 overrides/cycle
- Rollback <5min via revert + remover required-check
- Reverter parcial se conformidade <70% OU velocidade -40%

### Decisão #3 — Pricing oimpresso: **3 ajustes urgentes baseados em pesquisa real**
`memory/research/2026-05-prospeccao/05-pricing-real-concorrentes-horizontais.md`

| # | Mudança | Razão (1 número decisivo) |
|---|---------|---------------------------|
| 1 | Setup Pro R$ 2.500 → **R$ 999 default** (R$ 2.500 só pra migração complexa) | Bling/Tiny/Conta Azul/Omie cobram **R$ 0 setup** — atrito #1 na conversão |
| 2 | **Trial 14d sem cartão** Starter+Pro | Bling/Tiny/Omie têm 30d, Conta Azul 3d — sem trial = "compre cego" |
| 3 | **Anual "12 meses paga 10"** + setup grátis no anual | Padrão de mercado SMB — Starter R$ 2.990, Pro R$ 5.990, Enterprise R$ 14.990 |

**Stack atual real da gráfica média (4-10 func, ~500 vendas/m, ~1k boletos/m): R$ 2.180–3.617/mês**:
- Bling Titânio T2 anual ~R$ 200/m
- Asaas: 1.000 boletos × R$ 1,99 + cartão = **R$ 2.937 em taxas inevitáveis**
- Conta Azul Avançado R$ 400/m
- WhatsApp Business API ~R$ 80/m

→ Use no ROI calculator: oimpresso Pro R$ 599/m substitui Bling+Conta Azul (~R$ 600/m) e tudo integrado, com economia real em horas/mês não-Frankenstein.

## 📊 Canais setoriais — top 3 priorizados

`memory/sales/2026-05/12-canais-associacoes-setoriais.md`

| # | Associação | Fit | Tática | Janela |
|---|-----------|-----|--------|--------|
| 1 | **ABICOMV** (Abigraf div. com.visual) | 5/5 | Criada 22/jan/2025, conselho ainda formando — **antes Zênite/Mubisys travarem parceria** | 12 meses |
| 2 | **AFACOM** (Judah Adonai, 20k empresas em 4 países) | 5/5 | Mubisys tem AFACOM+ — entrar como "Tech Partner opção 2" pra cliente em crescimento | 6-12m |
| 3 | **Singrafs/Assingrafs** (ABCDMRP+Baixada Santista) | 4/5 | Zênite parceiro desde 2016 — atacar de flanco com evento "NFe automatizada" no mesmo auditório | aberta |

**Investimento ano 1**: R$ 45-80k caixa + ~80h Wagner
**KPI 90d**: 3 prospects qualificados (1 por canal) + 1 parceria oficial + 1 case publicado em revista institucional
**KPI ano 1**: 1 cliente fechado via canal = break-even (LTV R$ 60-100k > investimento R$ 45-80k)

**🎉 Cruzamento estratégico raro detectado**: Diretor técnico ABICOMV = **Judah Adonai** = fundador AFACOM. **Conquistar 1 = aproximar do 2.**

## 🔗 LinkedIn outbound playbook

`memory/sales/2026-05/13-linkedin-outbound-playbook.md`

5 personas calibradas com connect-request + DM-1/2/3:
- A. Dono(a) 1-10 func ativo no LI
- B. Dono(a) 10-30 em modo expansão
- C. Gerente operações
- D. CFO/financeiro Mubisys-frustrado
- **E. Herdeiro recém-empossado** ⭐ ← maior probabilidade de trial em 90d

Razões persona E ganha: janela decisão aberta (12-18m após assumir), modernização alinhada com narrativa, menor amarra emocional ao legado, nativo digital 25-40.

**Cadência conservadora**: 10 invites/dia, pausa dominical, personalização 100%. Mitigação LinkedIn jail.

**Risco operacional**: Wagner único operador → semana intensa de produto = flywheel quebra. Mitigação: delegar comentários/posts agendados pra Eliana[E] ou Felipe, **mas DMs precisam ser do Wagner (autenticidade fundador)**.

**Métricas 30/60/90d**:
- 30d: 200 conexões, 60 DMs, 5 calls, 1 trial
- 60d: 400/120/12/3 trials/1 contrato
- 90d: 600/200/25/8 trials/3 contratos

## 📰 Case study ROTA LIVRE anonimizado

`memory/sales/2026-05/14-case-study-rotalivre-anonimizado.md`

3 versões: long-form 1500p (blog/site) · 1-pager 300p (email/LinkedIn) · 5-bullets 50p (slide deck).

**Anonimização**: "uma gráfica de comunicação visual em São Paulo" + "a gestora". NÃO citou "99% volume" no texto público (identificável).

**Frase sugerida pra Larissa (ela escolhe palavras dela)**:
> *"Antes eu trabalhava duas vezes pra cada venda — uma pra fazer e outra pra lembrar. Com oimpresso a nota sai sozinha quando o cliente paga, e quando eu pergunto pra Jana 'quanto faturei essa semana?', ela responde no celular."*

**🚨 Mitigações obrigatórias antes de publicar**:
1. Consentimento escrito da Larissa
2. Wagner aprova versão final
3. Métricas em **faixa** (não pontuais) — concorrente local pode triangular: "gráfica SP + com.visual completa + balcão diário + horário pico 14-17h + Jana com memória" deixa universo pequeno

## 📋 Top 10 ações pós-Rodada-3 (re-priorizadas)

| # | Ação | Tempo | ROI |
|---|------|-------|-----|
| 1 | Bug #1 (wipe DB público) + #2 (octane/mcp em require) | 15min | ⭐⭐⭐⭐⭐ |
| 2 | Tests órfãos Ponto+ADS no phpunit.xml + adr-lint required | 10min | ⭐⭐⭐⭐⭐ |
| 3 | Setar NCM default + executar smoke SEFAZ biz=1 | 30min | ⭐⭐⭐⭐⭐ |
| 4 | **Aceitar pricing ajustes #1+#2+#3 (setup R$ 999, trial 14d, anual 12 paga 10)** | 15min decisão | ⭐⭐⭐⭐⭐ |
| 5 | Disparar 3 cold emails personalizados (New Signs Campinas, SP Sign, Sandice) | 30min | ⭐⭐⭐⭐⭐ |
| 6 | **Aceitar ADR mwart-gate HÍBRIDO** (PR separado, ID 0120) | 1h impl | ⭐⭐⭐⭐⭐ |
| 7 | **Aceitar decisão DAM = waiting-list** + redigir cláusula contratual | 1h | ⭐⭐⭐⭐ |
| 8 | Email pra `associativismo@abigraf.org.br` + LinkedIn Judah Adonai (ABICOMV/AFACOM) | 30min | ⭐⭐⭐⭐ |
| 9 | Reservar agenda ExpoPrint 24-28/mar/2026 (estande ou visitor) | 5min | ⭐⭐⭐⭐ |
| 10 | Preparar 1ª salva LinkedIn outbound — 10 conexões persona E | 1h | ⭐⭐⭐ |

## 📈 Stats consolidados Rodada 1+2+3

- **Wallclock total**: ~95min wallclock (18 agents · ~21h trabalho equiv sequencial — eficiência ~13x)
- **Artefatos**: 39 arquivos (19 R1 + 12 R2 + 8 R3)
- **Universo prospects**: 83 gráficas (SP 42 + RS 19 + PR 22)
- **Cold emails prontos**: 13 templates + personalizados
- **Decisões propostas**: 3 (DAM, mwart-gate, pricing) com payback/KPIs claros
- **Canais setoriais**: 3 priorizados com investimento R$ 45-80k/ano = break-even em 1 cliente
- **ADR drafts**: 1 (mwart-gate provável 0120)
- **Tokens consumidos**: ~1.6M sub-agents
- **Cota Claude Design**: continua preservada

---

**Branch:** `claude/auto-2026-05-09-mercado` (sem push). 3 commits agora. `git log -3` + `git diff main`.

---

# 🔁🔁🔁 Rodada 4 — escopo expandido (10 frentes)

> Wagner aprovou aumentar escopo. 10 agents paralelos. ~25min wallclock.
> Foco: assets que destravam decisões + ecosistema com.visual completo.

## 🚨 Achados críticos da Rodada 4

### #1 — API docs MVP: **70% JÁ FEITO mas DORMENTE**
`memory/decisions/proposals/api-docs-mvp-mubisys.md`

- `knuckleswtf/scribe ^5.0` em `composer.json` mas **nunca rodou** (`public/docs/` vazio)
- `laravel/passport ^13.0` ativo
- `Modules/Connector/Http/Controllers/Api/*` tem **~300 anotações Scribe** já escritas + 12 Resources + OAuth2 ativo
- 12 endpoints MVP escolhidos (Tier 1 leitura: GET /sell, /sell/{id}, /contactapi, /product, /business-details · Tier 2 escrita: POST /contactapi, **POST /sell**, PUT /sell/{id}, POST /contactapi-payment · Tier 3: GET /payment-methods, /business-location, POST /webhooks/asaas/{businessId})
- **Estimativa real: 20h Felipe IA-pair**, 50h pra "API enterprise completa"
- **🚨 BLOCKER pré-launch**: rotas `/connector/api/*` NÃO têm `/v1/` — incidente garantido se publicar sem versionar (+2h)

### #2 — Audit skills: 29 skills (vs 20 em 06/maio) — Tier A inflou 6→8
`memory/audits/2026-05-pre-sales/05-skills-audit-2026-05-09.md`

- **3 skills com drift name vs pasta**: `mcp-first`, `jana-arch`, `jana-recall-flow` (frontmatter desatualizado pós-rename) — 15min Wagner
- Rebaixar `mwart-process` + `mwart-comparative` Tier A→B salva **~8kb/sessão em 95% das sessões não-MWART** — 1h+ADR
- Total liberado: ~10-13kb/sessão = 12-15% redução tokens
- Gap: criar `pii-redactor-check` Tier A (Tier 0 LGPD sem hook hoje), `pest-fixture-builder`, `cliente-rotalivre-context`

### #3 — Vagas LinkedIn: 5 prospects aquecidos esta semana
`memory/research/2026-05-prospeccao/06-vagas-linkedin-graficas-sinal-aquecido.md`

- **Crealle (SJP/PR)** — vaga Coordenador PCP aberta há 12 dias = **janela ouro abordar antes do PCP entrar**
- Padrão dominante: vaga **instalação/montagem em campo** (51 SP + 41 BR + cluster ABC) = confirma tese "OS sem rastreamento na ponta = dor #1"
- Volume mercado: 688 vagas Comunicação Visual BR + cluster Curitiba (170)
- Loop mensal: ROI R$ 600-1.200/mês recorrente vs ~4h/mês investidas

## 📊 Decisões propostas adicionais (proposed — Wagner valida)

### Decisão #4 — Cláusula DAM 90d: **Versão B equilibrada** (recomendada)
`memory/decisions/proposals/clausula-dam-90d-rascunho.md`

- A conservadora (R$ 2.700/contrato exposição) — atratividade fraca
- **B equilibrada** (R$ 5.396/contrato — desconto 30% retroativo + cap 12m) ← skin-in-the-game sem risco existencial
- C agressiva (R$ 13.994/contrato — risco quebrar caixa se 3 atrasarem juntos = R$ 42k)
- **3 riscos jurídicos pra advogado validar**: cláusula penal CC arts. 408-416 + cumulação perdas e danos, CDC B2B atividade-fim STJ oscilante, LGPD operadora/controlador (DAM armazena arte cliente final)

### Decisão #5 — Roadmap técnico 12m: 6 milestones jun/26 → mai/27
`memory/decisions/proposals/roadmap-tecnico-12m-2026-2027.md`

| M | Período | Goal | Risco |
|---|---------|------|-------|
| M1 | jun/26 | Smoke fiscal + saúde tech | médio |
| M2 | jul/26 | 2º cliente + API docs Swagger | médio |
| M3 | ago-set/26 | 3º + 4º cliente | alto |
| M4 | out-nov/26 | Hardening + ABICOMV + decisão DAM | médio |
| M5 | dez/26-jan/27 | DAM nativo OU 5º cliente | **MUITO ALTO** ⚠️ |
| M6 | fev-mai/27 | Endorsement + 7º ativo | alto |

**M5 alto risco**: dependência dupla (3 contratos Enterprise pagos + recesso dezembro). Mitigação: decisão GO/NO-GO formal em M4 cycle 34 com critério explícito.

**Top 3 ações pré-M1 (antes de 16/jun/26)**:
1. **Smoke SEFAZ NFC-e biz=1 em homologação** — destrava M1+M2+M3 inteiros
2. **Fechar ADR DAM = waiting-list formal** com critério "3 contratos = GO"
3. **Pipeline ≥5 leads quentes** com discovery agendado

### Decisão #6 — Programa afiliados: **Bronze 1º (jun/26)**, Silver 2º (ago/26), Gold só com candidato real
`memory/sales/2026-05/15-programa-afiliados.md`

- Bronze 10% MRR/12m · Silver 20% + 10% setup + bônus 30% · Gold 30% off pricing
- **ROI 6m com 30 afiliados Bronze**: ~5 clientes (17% conversion) → R$ 2.395 MRR adicional → **R$ 25.866 líquido em 12m**, payback mês 2
- Custo dev MVP Bronze: ~8h IA-pair = R$ 0 marginal

## 🤝 Partnerships e canais

### Top 3 fornecedores máquinas (R27)
`memory/sales/2026-05/16-partnerships-fornecedores-maquinas.md`

1. **HP Latex Partner First** — único self-service estruturado (formulário web, tier Silver/Gold/Platinum)
2. **Roland DG via ICC** — distribuidor master histórico BR, base concentrada ICP
3. **Mimaki Brasil (Cotia/SP)** — sem intermediário, cultura japonesa exige presença

Investimento ano 1: R$ 18-25k + 80-120h Wagner. KPI 12m: 1 partnership oficial + 5-8 leads + **1-2 clientes via canal**.

### Top 5 ecossistema com.visual (R29) — gap "comunicação visual" que você pediu
`memory/sales/2026-05/17-ecosistema-com-visual-software-insumos.md`

1. **CorelDraw Tech Partner + plugin VBA** (~80% gráficas BR) — esforço 4-6 sprints
2. **ONYX RIP ISV Connect** (~40% large format premium)
3. **3M / Avery Dennison portais B2B vinil** — comissão 5-15% sobre GMV
4. **SAi (Flexi + PhotoPRINT)** — cobre RIP entry + cutting universal com 1 partnership
5. **Heytex / Endutex lonas** — greenfield BR, zero concorrente

🎯 **Stack ideal de gráfica BR (oimpresso como hub central)**: CorelDraw + ONYX/SAi + SAi Flexi + 3M/Avery + Heytex/Endutex. Posicionamento: **"ERP que aceita o stack que você já tem, não pede pra trocar nada."**

Investimento ano 1: R$ 25-40k + 120-180h Wagner+dev. Risco: software gráfico legacy sem API moderna → mitigação: hot folder universal primeiro, API onde ROI claro.

## 🔌 Integrações estratégicas (R28)
`memory/decisions/proposals/integracoes-estrategicas-12m.md`

**Top 5 P0 (3 meses)**:
1. WhatsApp Business API oficial Meta — universal, substitui Z-API/Baileys
2. NFSe + MDF-e via TecnoSpeed PlugNotas — 50% prospects Mubisys pedem
3. Bling sync — destrava migração híbrida 6m (30% prospects Mubisys bloqueiam contrato sem)
4. Conta Azul sync financeiro — 70% contadores aceitam recomendar
5. Mercado Livre publish + receive order

**Esforço total**: 248h IA-pair = ~50h wallclock = 6-7 dias úteis Felipe (10-14 semanas relógio real com KYC + canary). Custo: ~R$ 19.840. **Receita marginal 12m: R$ 75-150k ARR**. Payback: 1 contrato Enterprise.

🚨 **Dependência crítica que atrasa TUDO**: API docs MVP Swagger (R23). Sem `/api/docs` publicado, cada integração reinventa contrato. Felipe precisa fechar 16-24h ANTES de qualquer P0.

## 📝 Conteúdo público gerado

### Blog SEO 30 posts (R22)
`memory/sales/2026-05/blog/01-plano-editorial-30-posts.md`

5 pillars: Margem por OS · Substituir WhatsApp · NFC-e automática · oimpresso vs Bling/Conta Azul/planilha · IA conversacional. Top 3 maior tráfego: #19 (170/m), #6 (130/m), #17 (130/m). Investimento ~37,5h Wagner.

### Pós-mortem PR #349 (R24)
`memory/sales/2026-05/blog/02-pos-mortem-pr-349-mwart-gate.md`

3 versões: blog 1100p · LinkedIn 300p · Twitter thread 8 tweets. **LinkedIn maior potencial viralizar** (pós-mortem técnico-honesto raro no setor). 🚨 Risco: ROTA LIVRE/Larissa interpretar "5 PRs follow-up" como bug em prod. **Mitigação no doc**: parágrafo explícito "cliente atual não foi afetado" + Wagner manda WhatsApp pra Larissa antes de publicar. URLs reais: github.com/wagnerra23/oimpresso.com.

## 📋 Top 10 ações pós-Rodada-4 (re-priorizadas)

| # | Ação | Tempo | ROI |
|---|------|-------|-----|
| 1 | Bug #1 wipe-DB + #2 octane/mcp em require | 15min | ⭐⭐⭐⭐⭐ |
| 2 | Tests órfãos Ponto+ADS no phpunit.xml + adr-lint required + 3 drifts skills | 25min | ⭐⭐⭐⭐⭐ |
| 3 | NCM default + smoke SEFAZ biz=1 (destrava M1+M2+M3) | 30min | ⭐⭐⭐⭐⭐ |
| 4 | **Aceitar 3 ajustes pricing** + cláusula DAM versão B | 30min | ⭐⭐⭐⭐⭐ |
| 5 | Disparar 3 cold emails Tier 1 (New Signs Campinas, SP Sign, Sandice) + Crealle (vaga PCP janela ouro) | 1h | ⭐⭐⭐⭐⭐ |
| 6 | **Felipe inicia API docs Swagger** (20h) — destrava 5 P0 integrações + R$ 75-150k ARR | 20h dev | ⭐⭐⭐⭐⭐ |
| 7 | **Aceitar ADR mwart-gate HÍBRIDO** (PR ID 0120) + decisão DAM = waiting-list | 1h impl | ⭐⭐⭐⭐⭐ |
| 8 | Email associativismo@abigraf.org.br + LinkedIn Judah Adonai (ABICOMV/AFACOM) | 30min | ⭐⭐⭐⭐ |
| 9 | Reservar agenda ExpoPrint 24-28/mar/2026 + Future Print jul/26 | 5min | ⭐⭐⭐⭐ |
| 10 | Lançar programa afiliados Bronze 2026-06-15 (8h dev) | 8h dev | ⭐⭐⭐⭐ |

## 📈 Stats consolidados R1+R2+R3+R4

- **Wallclock total**: ~120min (28 agents · ~32h equiv sequencial — eficiência ~16x)
- **Artefatos**: **53 arquivos**
- **Universo prospects**: 83 gráficas + 11 vagas-aquecidas
- **Decisões propostas**: 6 (DAM, mwart-gate, pricing, cláusula DAM 90d, roadmap 12m, afiliados)
- **Canais mapeados**: 3 setoriais + 3 fornecedores máquinas + 5 ecossistema com.visual + 5 integrações P0
- **ADR drafts**: 1 (mwart-gate ID 0120)
- **Tokens consumidos**: ~2.4M sub-agents
- **Cota Claude Design**: preservada
- **Commits**: 4 (3 atuais + 1 desta rodada agora)

---

**Branch:** `claude/auto-2026-05-09-mercado` (sem push). 4 commits. `git log -4` + `git diff main`.

---

# 🔁🔁🔁🔁 Rodadas 5+6 — vertical auto + sinais qualificados reais + análise financeira interna

> Wagner reportou sinais qualificados REAIS durante rodada: Martinho Mecânica trocando R$ 830/m + Gold Comunicação→Mubisys R$ 850/m + pediu análise direta no banco financeiro (Firebird ServidorWR2).
>
> Total Rodada 5+6: 10 agents adicionais + 1 análise direta no banco com Python. ~30min wallclock.

## 🚨 Achado MAIS importante de toda execução autônoma

### Análise financeira real (banco Firebird ServidorWR2 — Python firebird-driver)

`memory/research/2026-05-receitas-officeimpresso/README.md` (relatórios completos gitignored — confidencial)

**Números validados (3 meses consecutivos):**
- **MRR**: ~R$ 40.500/mês (mar/abr/mai 2026) — 119-120 cobranças/mês
- **ARR projetado**: ~R$ 487k/ano
- **Receita 12m recebida**: R$ 457k–517k
- **Histórico 11 anos acumulado**: ~R$ 7,15M
- **Clientes pagantes 12m**: 144 · pagantes 24m: 188 (≈23% churn anual)

**Concentração SAUDÁVEL** (oposto do que parecia):
- Top 1 = 3.9% · Top 3 = 9.9% · Top 10 = 24.6% · Long tail = 75.4%
- ROTA LIVRE 99% volume é APENAS no oimpresso.com novo. OfficeImpresso legacy é diversificado.

**Distribuição ticket mensalidade:**
- 30.6% pagam <R$ 200
- **54.9% pagam R$ 200-499** ← sweet spot
- 9.0% pagam R$ 500-799
- 4.9% pagam R$ 800-1.499 (Pro Plus zona)
- 0.7% pagam R$ 1.500+ (Enterprise zona)

🚨 **Realidade vs meta R$ 5M/ano (ADR 0022):**
- Receita atual = **~10% da meta** (não 89% — erro inicial corrigido)
- Migração base atual NÃO chega na meta (mesmo migrando 100% × upsell, ARR ~R$ 700k)
- **Crescimento exige aquisição agressiva**: ~10x em 24m = ~50 clientes novos/ano ticket alto OU 10 enterprise

### Vertical auto histórico — 6 oficinas churnaram 2009-2013
- XERO CAR · EDI AUTO MECÂNICA · MECÂNICA 2 RODAS · MECÂNICA XERO · MECÂNICA LIMA · Mecânica Janones
- **Tentativa não-sustentou-se há 13+ anos.** Reforça MUITO recomendação STAY-FOCUSED.

### Martinho identificado na base
- **MARTINHO CAÇAMBAS LTDA** — ATIVO, 4 pgtos jan-abr/2026, ticket R$ 710/m (próximo dos R$ 830 mencionados — pode ser desconto)
- (não-mecânica clássica — caçambas/entulho com mecânica de frota interna)
- Reter = simbólico (1.7% do MRR), não move ponteiro estratégico

### Pico 2021 não-explicado
R39 detectou **R$ 1,07M em 2021 vs ~R$ 540k anos vizinhos**. Wagner deveria investigar — pode ser playbook replicável.

## 📊 Rodada 5 — Vertical oficinas auto (6 agents)

**Recomendação consolidada: STAY-FOCUSED (Cenário 4) — NÃO expandir**, com gatilho de retorno explícito.

### R30 Mercado oficinas auto BR
- Universo formal BR ~133-150k oficinas (Sindirepa) — **30x maior que gráfica (~5k)**
- Top 5 dores **idênticas** às de gráfica → Modules/Repair cobre ~70% esqueleto
- ICP refinado: 5-15 func, geração 2, R$ 50-200k/m, SP+interior

### R31 Concorrentes ERPs auto BR
- Top 5: **Ultracar** (BH/MG, 2.200+ clientes, RA reclama NFS-e travada 1+ ano), **Soften**, **Oficina Integrada** (Mundomidia, RA "boleto pago não libera dias"), **Oficina Inteligente** (premium novato), **WSoft** (R$ 29-79/m disruptor preço)
- TAM: R$ 128bi/ano, 121k oficinas, 32% SP
- 3 calcanhares pra atacar identificados
- IS2 Automotive: licença vitalícia desktop sem suporte = base envelhecida quebra com SEFAZ

### R32 Gap Repair vs ERP oficina
- **Cobertura atual: 55-60%** ← Repair Kanban drag-drop entrega ponta-a-ponta
- ⚠️ **Vocabulário automotivo já vazou pra produção**: `placa`, `vehicle`, `brand`, `km`, `box`, `mecanico` em código frontend
- Gaps duros: entidade Veículo, CRLV, NFS-e, WhatsApp PIN, tabela tempária, OEM, garantia, comissão mecânico
- MVP 12-essenciais: ~38h IA-pair × 2x margem = ~10 dias Felipe
- ERP completo 20/20: ~70d Felipe + 30d wallclock humano-limitado (SEFAZ NFS-e + SerPro)

### R33 Pricing ERPs auto BR
- Pricing real: R$ 70-599/m, mediana **R$ 200-340/m**
- Setup quase universalmente zero
- Briefing original (R$ 199/399/999) estava **caro pro mercado**
- Recalibração: **Auto Starter R$ 149/m · Auto Pro R$ 349/m + setup R$ 0 default · Auto Premium R$ 699/m + setup R$ 1.500**
- Ticket auto **42% menor que gráfica** (R$ 349 vs R$ 599)
- Universo 30x maior compensa volume mas exige escala — **não chega R$ 5M sozinho**

### R34 Persona "João Mecânico"
- 42 anos, Sorocaba/SP, 6-8 mecânicos, R$ 80-150k/m
- Caderno + WhatsApp + Bling + planilha de domingo
- WhatsApp >> telefone >> email; LinkedIn zero
- Top 3 objeções difíceis: "já tomei no peito com sistema", "você nunca trabalhou em oficina", "mecânico não vai usar"

### R35 Estratégia auto (decisão proposta)
**Cenário 4 STAY-FOCUSED escolhido** — 3 razões:
1. ADR 0105 violado por C1/C2/C3 — zero oficinas pagaram piloto = ADR feature-wish
2. ROI 12m de C4 (R$ 100k+ via roadmap M1-M6) supera todos em 2-5x
3. Capacidade time só comporta C4 (5 pessoas, Wagner WIP máx 2)

**Gatilho pra mudar pra C3 Spin-off**: 1 dono ICP-refinado aceitar **piloto pago R$ 199-399/m por 6m com aceite formal**.

**Análise honesta shiny object syndrome**: parcialmente sim (ROTA LIVRE 99%, 2º com.visual não fechado, justificativa "30x maior"). **Se Wagner re-questionar em 30-60d sem novos dados = sinal mais forte de shiny object.**

## 📨 Rodada 6 — Sinais qualificados reais (4 agents)

### R36 Post-mortem Gold Comunicação→Mubisys
**Diagnóstico cru**: *"Perda da Gold não foi perda de produto — foi ausência de canal de descoberta."*

3 hipóteses do "por que perderam":
1. Sistema Frankenstein virou inviável (volume passou ~10 OS/dia)
2. Mubisys SDR/AFACOM+ ativo no interior — oimpresso digital-only sem comercial físico
3. Boca-a-boca regional: 1.800+ clientes Mubisys × 13 anos = "vizinho usa"

**3 falas-killer pro próximo prospect Mubisys** documentadas.

🚨 **Ação prioritária 30d**: **inscrever oimpresso na ABICOMV ESSA SEMANA** (criada jan/2025, janela igualada vs AFACOM+).

### R37 Pricing recalibrado — Pro Plus R$ 899/m
**Cenário C escolhido** — adicionar tier intermediário sem queimar entry:
- Pro segue R$ 599 · Pro Plus **R$ 899/m + setup R$ 1.999** · Enterprise R$ 1.499
- Pro Plus features: Jana ilimitada, multi-business até 2, API full+webhooks, suporte WhatsApp 8h
- Reversível (se conversão <30% em 90d, descontinua silenciosamente)
- Receita marginal +R$ 300/m por novo cliente
- ROTA LIVRE preservada em Pro v1 (sem renegociar legacy)

### R38 Martinho Mecânica
- Pesquisa pública não identificou "Martinho Mecânica" único — confirmou hipótese: **cliente interno OfficeImpresso, sem presença web**
- Análise direta no banco confirmou: **MARTINHO CAÇAMBAS LTDA, ativo, R$ 710/m, 4 pgtos recentes**
- Abordagem recomendada: **WhatsApp acolhedor "26 anos juntos, eu te ajudo na migração"** + proposta pioneer R$ 599/m (economia R$ 230/m vs atual) + cláusula DAM-style 12 essenciais auto em 90d
- 🚨 **Risco crítico**: cascata reputacional se atrasar (queima 26 anos relação + grupos WhatsApp regionais)
- Mitigação: ringfence como **piloto experimental, não vertical inteira**

### R39 Análise financeira interna (Python firebird-driver direto)
**Já documentado no topo desta seção** — MRR real ~R$ 40k/m, ARR ~R$ 487k.

## 🎯 Top 10 ações finais re-priorizadas (pós-todas as rodadas)

| # | Ação | Tempo | ROI | Por quê |
|---|------|-------|-----|---------|
| 1 | Bug crítico #1 (wipe-DB-via-HTTP) + #2 (octane/mcp em require) | 15min | ⭐⭐⭐⭐⭐ | Reputação + segurança |
| 2 | Tests órfãos + adr-lint required + 3 drifts skills | 25min | ⭐⭐⭐⭐⭐ | Governança CI |
| 3 | NCM default + smoke SEFAZ biz=1 | 30min | ⭐⭐⭐⭐⭐ | Destrava M1+M2+M3 |
| 4 | **Reter Martinho Caçambas** com WhatsApp acolhedor + proposta pioneer R$ 599/m | 1h | ⭐⭐⭐⭐⭐ | Sinal qualificado real |
| 5 | **Plano anti-churn na base atual** (23% churn anual = R$ 110k/ano perdendo) | 4h | ⭐⭐⭐⭐⭐ | Receita marginal > aquisição |
| 6 | **Investigar pico 2021** (R$ 1,07M vs R$ 540k anos vizinhos) | 2h | ⭐⭐⭐⭐⭐ | Playbook replicável? |
| 7 | Aceitar pricing Pro Plus R$ 899 + cláusula DAM versão B | 30min | ⭐⭐⭐⭐ | Captura zona 800-1.499 |
| 8 | Inscrever ABICOMV essa semana (janela jan/2025 ainda aberta) | 30min | ⭐⭐⭐⭐ | Canal antes Mubisys/Zênite |
| 9 | API docs Swagger MVP (70% feito dormente, 20h Felipe) | 20h dev | ⭐⭐⭐⭐ | Destrava 5 P0 integrações |
| 10 | NÃO expandir vertical auto agora — manter ADR feature-wish | — | preserva foco | 6 churns 2009-2013 + sem sinal |

## 📈 Stats finais consolidados R1+R2+R3+R4+R5+R6

- **Wallclock total**: ~150min (29 agents + 1 análise direta · ~36h equiv sequencial — eficiência ~14x)
- **Artefatos**: **47 arquivos** (39 anteriores + 7 vertical auto + análise financeira gitignored + README receitas)
- **Universo prospects mapeados**: 83 gráficas (com.visual)
- **Decisões propostas**: 7 (DAM, mwart-gate, pricing 3 ajustes, cláusula DAM 90d, roadmap 12m, afiliados, pricing Pro Plus, vertical auto STAY-FOCUSED)
- **ADR drafts**: 1 (mwart-gate ID 0120)
- **Análise financeira interna**: ARR REAL R$ 487k (validado), 144 clientes pagantes, distribuição ticket conhecida
- **Sinais qualificados reais identificados**: 1 (Martinho Caçambas LTDA, R$ 710/m ativo)
- **Tokens consumidos**: ~3.3M sub-agents
- **Cota Claude Design**: preservada
- **Commits**: 4 + 1 desta rodada (5 total)

---

**Branch:** `claude/auto-2026-05-09-mercado` (sem push). 5 commits. `git log -5` + `git diff main`.
