---
slug: onboarding-eliana-financeiro
title: "Onboarding Eliana[E] — Financeiro + LGPD em estudo"
type: onboarding
authority: canonical
lifecycle: ativo
owner: wagner
target_persona: eliana
trust_level: L3
last_updated: 2026-05-15
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
  - 0081-identity-mesh-mcp-actors
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

# Eliana[E] — Onboarding

> **2 Elianas no projeto:** `Eliana[E]` (esposa Wagner, time interno) ≠ `Eliana(WR2)` (cliente externa PontoWr2). Este doc é da **Eliana[E]**.

## Quem você é aqui

- **Tier:** L3 VERTICAL (especialista financeiro)
- **Papel:** Advogada + financeiro + dev IA. Cuida de Financeiro/NFe/NFSe/Accounting/RecurringBilling. **NÃO é DPO formal** — Wagner decidiu 2026-05-09 que você estuda LGPD com calma primeiro, sem pressão.
- **Wagner é seu L0 supervisor** (e marido — viés inevitável reconhecido). Todo PR seu Wagner aprova.
- **Você NÃO é L1 governance** — não toca Constituição/SRS/policies (mesmo sendo advogada).

## O que você pode tocar (modules_write)

- `Modules/Financeiro/` — visão unificada AR/AP
- `Modules/FinanceiroAvancado/` — features avançadas
- `Modules/NfeBrasil/` — NFe/NFC-e
- `Modules/NFSe/` — NFSe Sistema Nacional (LC 214/2025)
- `Modules/Accounting/` — contábil
- `Modules/RecurringBilling/` — assinaturas + boletos
- Suporte read-only em qualquer Modules/<X>/ pra parecer jurídico
- Charters `*.charter.md` dos seus módulos

## O que você NÃO pode tocar (modules_blocked)

- `Modules/Connector` + `Modules/Superadmin` — L0 only (Wagner)
- `Modules/Governance` + `Modules/ADS` + `Modules/TeamMcp` — L1 governance (Wagner; mesmo sendo advogada, governance interna é L1 administrativa, não jurídica)
- `Modules/Jana` — L2 product (Wagner ou Felipe)
- `Modules/Officeimpresso`, `Modules/OficinaAuto`, `Modules/ComunicacaoVisual` — Felipe (Delphi)
- `Modules/Mobile` (quando Luiz criar) — Luiz
- `Modules/Crm`, `Sells`, `Repair`, `Inventory`, `Purchase` — Maiara
- `memory/decisions/NNNN-*.md` existentes — append-only IRREVOGÁVEL. Você pode CRIAR ADR nova com `supersedes: [NNNN]`
- `memory/governance/CONSTITUTION.md` — supremo, só Wagner via ADR + version bump
- `memory/proibicoes.md`, `memory/regras-time.md` — canon Tier 0, só Wagner

## Por que você não é DPO formal ainda (estado 2026-05-15)

Wagner decidiu 2026-05-09 que não vai te pressionar pra assumir DPO formal antes de você se sentir confortável com LGPD. Você está **estudando**. Quando/se decidir assumir, retomamos a conversa. Counsel LGPD externo segue necessário pra Pilares 1-4 do oimpresso Insights (Pilar 5 DaaS externo foi descartado pelo Wagner).

**Implicações práticas:**
- Você pode dar opinião jurídica em PRs/ADRs (autônoma)
- Wagner não te dá tarefas "DPO" — você não responde por compliance LGPD legalmente
- Quando estiver pronta, criamos ADR formalizando função DPO

## Skills auto-load esperadas (Tier A always-on no seu Claude Code)

- `brief-first` — chama `brief-fetch` PRIMEIRO em toda sessão
- `mcp-first` — tools MCP antes de Read/Glob filesystem
- `multi-tenant-patterns` — `business_id` Tier 0 IRREVOGÁVEL
- `commit-discipline` — 1 PR = 1 intent, ≤300 linhas, sem PII
- `preflight-modulo` — pré-flight leitura SPEC/RUNBOOK/CAPTERRA antes de Edit em `Modules/<X>/`

## Skills auto-trigger por description (Tier B) que você verá

- `como-integrar` — antes de implementar feature, mapeia o que já existe (especialmente em fiscal — muito código legacy UltimatePOS)
- `multi-tenant-patterns` — TODO Eloquent Model precisa `business_id` global scope
- `inertia-defer-default` — toda prop pesada em Controller usa `Inertia::defer()`
- `oimpresso-team-onboarding` — primeira vez configurando o Claude Code

## Primeiro dia — checklist (ordem)

1. ☐ Aceitar token MCP de Wagner (Vaultwarden — Wagner instala via Vault próprio, NUNCA email)
2. ☐ Configurar Claude Code (skill `oimpresso-team-onboarding` te guia)
3. ☐ Rodar `brief-fetch` — primeiro comando de toda sessão
4. ☐ Ler [CLAUDE.md](../../../CLAUDE.md) + [why](../../why-oimpresso.md) + [what](../../what-oimpresso.md) + [how-trabalhar](../../how-trabalhar.md) + [proibicoes](../../proibicoes.md)
5. ☐ Ler [governance/CONSTITUTION.md](../../governance/CONSTITUTION.md) — especialmente **Art. 4 Compliance** (LGPD + Portaria 671 + NFe + NFSe)
6. ☐ Ler [governance/TRUST-TIERS.md](../../governance/TRUST-TIERS.md) — entender seu L3
7. ☐ Ler [requisitos/Financeiro/SPEC.md](../../requisitos/Financeiro/SPEC.md) + [NfeBrasil/SPEC.md](../../requisitos/NfeBrasil/SPEC.md) + [NFSe/SPEC.md](../../requisitos/NFSe/SPEC.md) + [Accounting/SPEC.md](../../requisitos/Accounting/SPEC.md)
8. ☐ Rodar `my-work` — tasks atribuídas
9. ☐ Estudo LGPD (paralelo, sem prazo) — Wagner não te pressiona
10. ☐ Primeiro PR: ≤100 linhas, fix/feature financeiro. Wagner revisa sync.

## Workflow Tier 0 — 3 fases (IRREVOGÁVEL)

Cada vez que mexer em `Modules/<X>/`:

**FASE 1 PRÉ-FLIGHT** — ANTES de qualquer Edit:
- Ler `memory/requisitos/<X>/SPEC.md` (US-XXX-NNN relevante)
- Ler `memory/requisitos/<X>/RUNBOOK*.md` (se MWART Blade→React)
- Ler `memory/requisitos/<X>/CAPTERRA*.md` (gap mercado)
- Ler charter `<Tela>.charter.md` ao lado do `.tsx` (se for Page)
- Rodar `decisions-search query:"<tema>"` pra ADRs relacionadas

**FASE 2 DURING** — mexendo no código:
- Commit incremental por step lógico
- `git push` WIP a cada ~30min (não acumular trabalho local)
- NUNCA `git checkout` outra branch sem `stash` ou `commit` antes

**FASE 3 POST** — terminou:
- PR no git → CI verde → Wagner aprova → merge
- Atualizar `BRIEFING.md` do módulo (skill `brief-update` Tier B auto-trigger)
- Se decisão arquitetural nova: criar ADR em `memory/decisions/NNNN-slug.md`
- Handoff em `memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md` (append-only)

> **Regra primária Tier 0 IRREVOGÁVEL** (proibicoes.md): "mexeu, REGISTRA". Drift entre prod e git canônico é o vetor nº 1 de incidentes. Sem "ajuste rápido", sem "depois eu commito".

## Vetores de drift catalogados que VOCÊ pode causar (cuidado especial fiscal)

| Vetor | Como acontece | Defesa |
|---|---|---|
| **NFe rejeitada por validação local frouxa** | Eloquent salva NFe sem validar XML SEFAZ → SEFAZ rejeita em prod | Pest tests obrigatórios + skill `multi-tenant-patterns` + escalação Wagner em qualquer dúvida fiscal |
| **`forceDelete()` em `nfe_emissoes` cancelada** | Tenta "limpar" NFe cancelada → CONFAZ SINIEF 07/2005 Art. 14 PROÍBE | PROIBIDO Tier 0 IRREVOGÁVEL (proibicoes.md). NFe cancelada vira `status=cancelada`, NUNCA hard delete. |
| **Mexe em `ponto_marcacoes` (Portaria 671)** | Tenta UPDATE/DELETE marcação → trigger MySQL nega | Append-only por lei. Use `Marcacao::anular()`. |
| **PII em código/log/commit** | CPF cliente vaza em mensagem de erro | CI `pii-scan` bloqueia. Use `[REDACTED]` ou `PiiRedactor`. |
| **Drift de Module Charter** | Cria Controller fora `Modules/Financeiro/SCOPE.md.contains[]` | Hook `block-module-drift` warn (depois block) + CI gate |
| **Refund Asaas sem flag** | `RefundCobrancaAsaasJob` sem `ASAAS_REFUND_ENABLED=true` em prod | Wagner ativa manual `.env` após validação homolog (proibicoes.md) |
| **DDL direto em prod** (`ALTER TABLE` via phpMyAdmin) | Atalho pra "ajustar coluna fiscal rápido" | PROIBIDO. Sempre migration PHP. Check `procedure_drift` em `jana:health-check` detecta. |
| **`Mail::raw` sem checar opt-in LGPD** | Notifica cliente sobre boleto sem verificar `Contact::canReceiveEmailNotification()` | LGPD opt-in. NULL=permite (back-compat); FALSE=bloqueia. Mesma regra `canReceiveWhatsappNotification()`. |

## Quando escalar pro Wagner

- Toda decisão arquitetural nova (não há ADR cobrindo)
- Bug fiscal em prod afetando ROTA LIVRE (biz=4) — imediato
- Merge de PR (sempre Wagner aprova)
- Mudança em compliance LGPD (você ainda está estudando — Wagner decide via ADR)
- Cliente reclamando de NFe/NFSe rejeitada — investigação SEFAZ
- Qualquer pedido de `withoutGlobalScopes` (bypass `business_id`)
- DDL produção (`ALTER`, `CREATE PROCEDURE`)

## Domínio jurídico/financeiro — onde sua expertise importa

- **Counsel** em ADRs que tocam compliance: revisar texto LGPD/Portaria/Fiscal antes de aceitar
- **Texto cliente-facing**: política de privacidade, ToS, contratos — você revisa
- **Casos disputados**: cliente alegando uso indevido de dado, NFe rejeitada — você dá parecer interno
- **Revisão fiscal**: CONFAZ SINIEF, LC 214/2025 (NFSe), regras CFOP/CST — você é a referência humana

## Princípios duros (Constituição v2 — ADR 0094)

1. Context as a product
2. Tiered cost
3. Charter > Spec
4. Loop fechado por métrica
5. SoC brutal
6. **Multi-tenant Tier 0 IRREVOGÁVEL**
7. Transparência
8. Confiabilidade com fallback

## Recursos pra você

- **Vaultwarden** (`vault.oimpresso.com`) — credenciais
- **MCP server** (`mcp.oimpresso.com`) — tools `brief-fetch`, `my-work`, `decisions-search query:"lgpd"`, `tasks-create`
- **GitHub** (repo privado oimpresso)
- **SEFAZ** — homolog/prod NFe (Wagner dá acesso quando precisar)
- **Suporte:** Wagner (sync), Felipe (par dev), Maiara (suporte ops)

## Histórico

- **v1.0** (2026-05-15) — onboarding inicial pré-entrada Eliana[E] no MCP. DPO formal pendente sua decisão (Wagner não pressiona).
