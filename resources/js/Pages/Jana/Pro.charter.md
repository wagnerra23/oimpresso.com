---
page: /ia/pro
component: resources/js/Pages/Jana/Pro.tsx
owner: wagner
status: draft
last_validated: "2026-08-18"
smoke: "2026-08-18 — render prod OK (Chrome MCP, sessao WR2 Sistemas, tema escuro): hero, card de prova, comparacao 6 linhas, preco e footer sticky com a CTA. DOIS defeitos CONFIRMADOS ao vivo, ambos antes so inferidos por leitura: (1) a comparacao de preco renderiza o sentinela de redacao no lugar dos numeros dos concorrentes; (2) 'Voltar ao chat' leva a /ia — titulo 'Jana — Dashboard', aba Painel ativa —, nao a Conversa. O (2) esta corrigido no PR #5892; o (1) aguarda decisao [W]."
parent_module: Jana
related_adrs: [140, 110, 190, 93]
related_us: [US-COPI-148, US-COPI-118]
related_runbook: memory/requisitos/Jana/RUNBOOK-pro.md
related_visual_comparison: memory/requisitos/Jana/Pro-visual-comparison.md
related_casos:
  - resources/js/Pages/Jana/Pro.casos.md
tier: B
charter_version: 2
---

# Page Charter — /ia/pro

> **Status:** novo. Tradução F3 (Cowork → Inertia/React) do design aprovado
> `Jana Pro - Paywall CC.html` (gate F1.5 **PASS 90** — ver
> `prototipos/jana-pro/critique-score.json` + `COMPARISON.md` no bundle Cowork).
> Charter criado junto com a tela pra fixar escopo e evitar virar Christmas tree.

---

## Mission

Converter o usuário do plano **Grátis** pro **Jana Pro** numa única tela de
decisão (estilo checkout Stripe): mostrar o valor com **prova ao vivo** (a Jana
lendo dados reais do ERP), comparar Grátis vs Pro, preço honesto + sinais de
confiança, e uma só ação primária — **Ativar Jana Pro**. Persona-alvo: Larissa
(ROTA LIVRE, biz=4, 1280px), decisão rápida.

---

## Goals — Features (faz)

- Shell `AppShellV2` (sidebar dark Cockpit V2) — herança de fundações/shell.
- **Modo FOCO** (sem `JanaSubNav` de ghosts): página de decisão, não de navegação
  — análoga a Edit/Create do `pageheader-canon`. Header só: breadcrumb
  `Jana · Plano` + título `Jana Pro` (tag `UPGRADE`) + `Voltar ao chat`.
- **Hero** 2 colunas: pitch ("Ela conhece o seu negócio. O Pro tira as amarras.")
  + **card de prova** dark com bolhas de chat e 3 ângulos de faturamento
  (Bruto/Líquido/Caixa) — diferencial não-replicável "ERP nativo" (BRIEFING §4.1).
- **Comparação Grátis vs Pro** (6 linhas, lidera com vitórias Pro: Brief 06h /
  Análises / Cockpit Saúde; "chat dados reais" fecha como base dos dois planos).
- **Preço honesto**: R$ [redacted Tier 0]/mês posicionado vs Numia R$ [redacted Tier 0] / Copilot R$ [redacted Tier 0]
- **Confiança**: isolamento por empresa (Tier 0), LGPD por padrão, hospedado no BR.
- Footer sticky com resumo + ação secundária "Falar com a Jana" + CTA primária.
- CTA `Ativar`: estado `idle → Ativando… → Pro ativo · 14 dias grátis` (fecha o
  loop de feedback, dim. 5 Estados).
- Atalhos teclado (Larissa = teclado): `⌘/Ctrl+Enter` ativa · `Esc` volta ao chat.
- Tokens canon: `bg-primary` roxo `oklch(0.55 0.15 295)` (ADR 0190), `text-success`
  pra "incluído/positivo", zero `blue-*`/emoji, `rounded-md/lg`, `shadow-sm`.

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item viraria GUARD se houvesse Pest desta tela.

- ❌ Billing real (assinatura Asaas, gateway, cobrança) — é **Sprint JANA-B**
  (ADR 0140, US-COPI-211/212). A CTA hoje é mock client-side fiel ao protótipo.
- ❌ Downgrade/gestão de assinatura (cancelar, trocar cartão) — backlog Sprint B.
- ❌ WhatsApp como canal de contato ("Falar com a Jana" → `/ia`, nunca WA — proibição).
- ❌ Modal full-screen, emoji, cor crua de status (`text-emerald/rose`) — usa tokens.
- ❌ Comparar mais de 2 planos (Enterprise R$ [redacted Tier 0] entra só em GA — Sprint JANA-C).
- ❌ Escrever no banco no render (tela de leitura + 1 ação; sem efeito colateral).
- ❌ Mostrar dados de outro `business_id` (Tier 0 — businessId sempre da sessão).

---

## UX Targets

- Cabe em 1280px (Larissa) com a comparação + preço + confiança **sem rolar muito**.
- 1 única ação primária roxa (footer); secundárias nunca competem em cor.
- p95 first-paint < 800ms (render leve — props pequenos, sem query pesada).
- 0 erros JS console · 0 erros TS/ESLint (`text-success` evita lint R1).
- Foco visível (`:focus-visible` outline) em todo interativo; CTA tabável.
- Card de prova legível a ~1m (números mono grandes, `tabular-nums`).

---

## Automation Hooks

- `ProController::index()` (rota `jana.pro.index`, grupo `/ia` auth) renderiza
  `Jana/Pro` com `plan`, `pricing`, `proof`, `business` (businessId da sessão).
- `proof` hoje = valores representativos; **Onda B** liga em
  `BriefDiarioService::snapshot()` (faturamento real do mês corrente).

## Automation Anti-hooks

- ❌ Não dispara email/SMS/WhatsApp ao abrir nem ao clicar (CTA é mock até Sprint B).
- ❌ Não chama LLM/Brain B no render.
- ❌ Não persiste nada no client além do estado efêmero da CTA (sem localStorage).
- ❌ Não cobra nem cria assinatura (billing é Sprint JANA-B, gated por Wagner).

---

## Refs

- Design Cowork aprovado: `Jana Pro - Paywall CC.html` + `prototipos/jana-pro/`
  (`COMPARISON.md` 15 dimensões, `critique-score.json` gate 90).
- [ADR 0140](../../../../memory/decisions/0140-jana-pro-produto-comercial-saas.md) — Jana Pro SaaS / pricing / roadmap (Sprint A-D).
- [ADR 0110](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md) — Cockpit V2.
- [ADR 0190](../../../../memory/decisions/0190-primary-button-roxo-universal-295.md) — primary roxo universal.
- [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0.

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-06-01 | [CL] (Claude Code) | Tela criada — F3 do design `Jana Pro - Paywall CC` (gate Cowork PASS 90). Controller + rota + página + charter. Billing real fica pra Sprint JANA-B. |
| 2026-08-18 | [C] (Claude Code) | **v2 — a F1 que faltava.** A tela shipou em 2026-06-01 **sem RUNBOOK**, e a consequência era mecânica: o hook `block-mwart-violation` **bloqueava** todo Edit em `Pro.tsx` (`exit 2`, medido), porque não achava `RUNBOOK-pro.md` nem `RUNBOOK-jana-pro.md` — o `RUNBOOK-jana-pro-**concierge**.md` é de outra capacidade e não casa. Nasce [`RUNBOOK-pro.md`](../../../../memory/requisitos/Jana/RUNBOOK-pro.md) e os artefatos passam a ser **declarados**, não resolvidos por nome: o `screen-coverage --screen Jana/Pro` acusava `RUNBOOK ⚠ AMBÍGUO (2)` justamente por escolher no escuro entre dois candidatos parecidos. **`related_us` declarado**: `US-COPI-148` (a onda de fusão) e `US-COPI-118`. A 148 é a dona do join — foi ela que moveu o chat pra `/ia/conversa` e **declarou o resíduo desta tela por escrito** (SPEC §Onda 3: *"`Pro.tsx` mantém `voltar → /ia` (agora o Painel, não o chat) … consertar exige `RUNBOOK-pro.md`, que não existe"*). A 118 (`_pendente_`) é as cores cruas do card de prova — as mesmas 4 warnings `no-inline-raw-color` que o ESLint acusa aqui. **`US-COPI-211`** ("Pricing page"), citada no ADR 0140, **NÃO entra**: ela existe na ADR mas **não existe no SPEC** (0 hits, medido) — seria id fantasma, o mesmo erro que a v2 do `Memoria.charter.md` corrigiu. **`related_prototype` segue ausente de propósito** — o design desta tela (`Jana Pro - Paywall CC.html`) existe **só** no Cowork, sob `_arquivo/`, e declarar um path que o repo não tem criaria o ponteiro podre que o [`Pro-visual-comparison.md`](../../../../memory/requisitos/Jana/Pro-visual-comparison.md) denuncia. Trazer o arquivo ou declarar `n/a` é decisão [W]. |
