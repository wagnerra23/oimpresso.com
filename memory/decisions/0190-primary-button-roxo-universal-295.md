---
slug: 0190-primary-button-roxo-universal-295
number: 190
title: "Primary button interno = roxo médio universal oklch(0.55 0.15 295) — hue per grupo APENAS pra agrupamento visual sidebar"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-25"
accepted_at: "2026-05-25"
accepted_via: "Wagner aprovou em sessão `frosty-greider-83ab2f` 2026-05-25 — comando exato: 'deixe os grupos como estão, internos são 295 roxo médio'"
module: _DesignSystem
quarter: 2026-Q2
tags: [design-system, primary-button, roxo, hue-per-grupo, sidebar, page-header, reconciliacao]
supersedes: []
supersedes_partially:
  - "0182-pageheadertabs-canon-pattern-telas"
  - "0189-pageheader-canon-v3-1-cadastro-roxo"
amends:
  - "0182-pageheadertabs-canon-pattern-telas"
  - "0189-pageheader-canon-v3-1-cadastro-roxo"
superseded_by: []
related:
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0180-sidebar-v3-5-grupos-ghosts-header"
  - "0182-pageheadertabs-canon-pattern-telas"
  - "0189-pageheader-canon-v3-1-cadastro-roxo"
pii: false
review_triggers:
  - Larissa biz=4 testar 7d e relatar saturação visual com roxo em todas telas
  - 2+ clientes piloto reportarem preferência por hue per grupo no primary
  - Telemetria mostrar drop em CTA click rate após universal
---

# ADR 0190 — Primary button interno = roxo médio universal · hue per grupo APENAS pra agrupamento visual sidebar

## Contexto

Sessão 2026-05-25 (`frosty-greider-83ab2f`) ao reconciliar conflitos de memória do PageHeader canon. Auditoria revelou **4 fontes conflitantes** sobre hue per grupo:

| Fonte | Define hue de | Status |
|---|---|---|
| Código `cockpit/shared.ts SIDEBAR_GROUP_HUE` | 11 grupos (cadastro=202, vender=55, financas=145, pessoas=88, sistema=245, fiscal=175, estoque=315, producao=8, ia=215, atendimento=30, equipe=275) | ✅ canon real validado |
| Skill `pageheader-canon/SKILL.md` linha 44 | pessoas=295, vender=60, operar=350, sistema=200 (5 hues) | ❌ DIVERGENTE |
| Matriz `pageheader-matriz-diferencas.md` F7 | Mesmo conjunto da skill (7 hues) | ❌ DIVERGENTE |
| [ADR 0189](0189-pageheader-canon-v3-1-cadastro-roxo.md) | cadastro=295 (roxo médio) — supersede parcial 0182 | ⚠️ parcial |

A ambiguidade core: **hue per grupo aplica AO QUÊ?**
- (a) só sidebar (header de grupo · ícones · decorativo)?
- (b) só primary button interno das telas?
- (c) ambos (cor "vasa" do sidebar pro botão)?

ADR 0182 tinha implícito (c) — `FinanceiroPrimaryButton` usa verde 145, `PontoPrimaryButton` usa hue 88 limão, etc. Mas em prática:
- Wagner sessão 2026-05-24 escolheu B "Modern SaaS" pra família visual + roxo médio 295 pra primary do Cliente
- Wagner sessão 2026-05-25 reiterou: *"deixe os grupos como estão, internos são 295 roxo médio"*

Esclarecimento: **primary INTERNO das telas é cor de AÇÃO** (criar registro, executar), **NÃO de identificação de domínio**. Pattern Linear/Notion/Vercel: sidebar tem cores variadas por seção (decorativo), mas CTAs são SEMPRE 1 cor única (consistência funcional).

## Decisão

**REGRA CANON ÚNICA:**

1. **Hue per grupo (SIDEBAR_GROUP_HUE no `shared.ts`)** continua exatamente como está — define **APENAS** coloração de **agrupamento visual no sidebar** (header de grupo, ícones, dot indicator).
   - Não mexer no código `shared.ts`
   - Variedade de cores no sidebar é DECORATIVA: ajuda usuário a identificar seção
   - 11 hues atuais (cadastro=202, vender=55, financas=145, pessoas=88, sistema=245, fiscal=175, estoque=315, producao=8, ia=215, atendimento=30, equipe=275) PERMANECEM

2. **Primary button INTERNO das telas é SEMPRE roxo médio universal**:
   - `background: oklch(0.55 0.15 295)`
   - `border: oklch(0.45 0.15 295)`
   - `color: oklch(0.99 0 0)` (branco)
   - **Independente** do grupo do módulo (Financeiro/Cadastro/Vendas/etc — TODOS usam mesmo roxo)
   - **Independente** do tipo de ação (Novo/Criar/Importar/Pagar — TODOS usam mesmo roxo)

3. **Componentes legacy hue-per-grupo são DEPRECATED:**
   - `FinanceiroPrimaryButton.tsx` (hue 145 verde) → DEPRECATED · migrar pra usar `oklch(0.55 0.15 295)`
   - `JanaPrimaryButton.tsx` (hue 215 azul) → DEPRECATED · migrar
   - `PontoPrimaryButton.tsx` (hue 88 limão) → DEPRECATED · migrar
   - Próximo PR: refactor pra `<PageHeaderPrimary>` componente único universal roxo 295

4. **Underline de tab ativa, KPI card ativo, contador de tab ativo** = também roxo 295 universal (alinhado com primary)

5. **Excecões permitidas:**
   - Botões `Aprovar` (verde semântico), `Rejeitar` (vermelho semântico), `Cancelar` (cinza) — semântica vence universal
   - Badge `Atraso` (vermelho), `Vencido` (vermelho), `Pago` (verde) — semântica vence universal

## Justificativa

**Por que primary universal e não hue per grupo:**

1. **Consistência funcional** — usuário aprende UMA cor de "ação principal" em todo app. Mudar cor por módulo aumenta carga cognitiva (Hick's Law)
2. **Pattern reconhecido world-class** — Linear, Notion, Vercel, Stripe Dashboard: primary CTA sempre única cor; sidebar/header sim varia
3. **Roxo se diferencia em BR-ERP** — Bling/Tiny/Omie/Conta Azul são azul. Oimpresso roxo = identidade visual diferenciada
4. **Hue per grupo no sidebar continua dando contexto** — usuário não perde o "estou em Finanças vs Cadastro" porque o GRUPO no sidebar mantém a cor
5. **Manutenção simplificada** — 1 token CSS canon (`--primary: oklch(0.55 0.15 295)`) em vez de 11 componentes wrapper

**Por que NÃO mudar SIDEBAR_GROUP_HUE no código:**

- Funciona, validado em prod
- Wagner sessão 2026-05-25: *"deixe os grupos como estão"*
- Mudar 11 hues quebra identidade visual já estabelecida na sidebar
- Trade-off: skill `pageheader-canon` ainda menciona "hue per grupo" pro primary — vira anti-padrão a partir desta ADR

**Por que skill `pageheader-canon` e matriz `pageheader-matriz-diferencas.md` tinham hues diferentes do código:**

- Foram escritas com SIDEBAR_GROUP_HUE de uma versão ANTERIOR (provavelmente da era ADR 0182 sketch inicial)
- Código evoluiu (cadastro adicionado, hues recalibrados pra distinção visual ≥25° no círculo cromático)
- Skill/matriz nunca foram atualizadas — gap de workflow

**Quando faz sentido reabrir esta ADR:**

- Larissa biz=4 testar 7d com tudo roxo e relatar "muito uniforme, perdi identidade visual de módulo"
- 2+ clientes piloto preferirem hue per grupo no primary
- Telemetria mostrar drop > 15% em CTA click rate após mudança (causalidade reversa: roxo confunde?)
- Acessibilidade reportar contraste insuficiente do roxo 295 em algum bg específico

## Consequências

**Positivas:**
- **Reconcilia 4 fontes conflitantes** em 1 regra única
- **Simplifica manutenção** — 1 token CSS em vez de 11 wrappers `<XPrimaryButton>`
- **Identidade visual diferenciada** — roxo vs azul dos concorrentes BR
- **Pattern world-class** — Linear/Notion/Vercel/Stripe alignment
- **Sidebar mantém variedade** decorativa preservando contexto de módulo

**Negativas / Trade-offs:**
- **Refactor de 3 componentes legacy** (`FinanceiroPrimaryButton`, `JanaPrimaryButton`, `PontoPrimaryButton`) — depreciar + migrar
- **Migrar telas que usam esses componentes** — buscar imports + substituir
- **Quebra paridade visual sidebar↔primary** — antes (em Financeiro) sidebar verde + botão verde "casavam". Agora sidebar verde + botão roxo = decoupling intencional
- **Wagner trade-off:** menos "personalização visual por módulo", mais "consistência funcional global"

**Riscos mitigados:**
- Larissa biz=4 piloto testando antes de migração massa
- Cliente/Index (PR #1457) já validado com roxo 295 — referência
- Se Larissa rejeitar, rollback é simples (1 token CSS)

## Referências

- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0180](0180-sidebar-v3-5-grupos-ghosts-header.md) — Sidebar v3 (5 grupos canônicos)
- [ADR 0182](0182-pageheadertabs-canon-pattern-telas.md) — PageHeaderTabs canon (superseded parcial)
- [ADR 0189](0189-pageheader-canon-v3-1-cadastro-roxo.md) — PageHeader v3.1 (cadastro roxo — agora universal via 0190)
- [SPEC PageHeader-canon-v3-1.md](../requisitos/_DesignSystem/templates/PageHeader-canon-v3-1.md) — template oficial (atualizado nesta ADR)
- [LEARNINGS PageHeader sessão 2026-05-25](../requisitos/_DesignSystem/templates/PageHeader-LEARNINGS.md) — postmortem
- [Skill `pageheader-canon`](../../.claude/skills/pageheader-canon/SKILL.md) — atualizada nesta ADR
- [Matriz `pageheader-matriz-diferencas.md`](../requisitos/_DesignSystem/pageheader-matriz-diferencas.md) — F8 atualizado nesta ADR
- PRs precedentes desta jornada: #1453, #1454, #1455, #1456, #1457, #1458, #1459, #1460
