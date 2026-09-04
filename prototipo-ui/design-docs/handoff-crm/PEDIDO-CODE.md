# EXPORT Crm — pacote de export (10 blocos) · 2026-09-04

> **Reescreve** este mesmo arquivo (anti-scatter §2-ter). A versão anterior (2026-08-24) pedia **criar `resources/js/Pages/Crm/`** com o trio de Painel/Leads/Acompanhamentos/Portal — **esse pedido está RETIRADO neste documento**: contradiz a [ADR 0301](../../../memory/decisions/0301-separar-cliente-deprecar-crm-pipeline.md) (aceita, [W] 2026-06-22), lida no `main` **neste turno**.
> **Ponte, não canon.** Eu não escrevo no git: desce por `cowork-inbox`/Issue → PR, ou [W] cola 1×.
> **Veredito deste ciclo: 0 ondas exportáveis.** Detalhe no bloco 6 (placar) e 7 (o que a ancoragem não resolve).

---

## Leitura do `main` NESTE turno (o que eu li — o resto é "não verifiquei")

| # | arquivo lido no `main` (2026-09-04) | para quê |
|---|---|---|
| 1 | `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` | read-order, rotina, regra de saída (pacote) |
| 2 | `prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md` | frescor por tela (Cliente 🟠 revisado 26/08; Crm-pipeline não consta) |
| 3 | `prototipo-ui/PRE-FLIGHT-TELA.md` | blocos A–D dos pré-requisitos |
| 4 | **`memory/decisions/0301-separar-cliente-deprecar-crm-pipeline.md`** | **decisão que trava este export** |
| 5 | `memory/reference/crm-e-o-modulo-de-cliente.md` | desambiguação Cliente (cadastro) ≠ CRM (pipeline) |
| 6 | **`memory/requisitos/Crm/DEPRECATION-PLAN-pipeline.md`** | classificação A/B por controller + zona cinza do portal + gates E1–E6 |
| 7 | `Modules/Crm/Http/Controllers/CrmDashboardController.php` | dado real por slot do Painel (o único controller de B que li) |
| 8 | árvore `resources/js/Pages/**` (filtro `crm\|cliente\|lead`) + árvore `Modules/Crm/**` + busca `Crm` em `routes/` (**0 casos**) | onde a seção cairia — **`resources/js/Pages/Crm/` não existe** |

**Âncora de implementação (o que existe hoje, lido):** `Modules/Crm/**` é **Blade legado** (`Resources/views/index.blade.php`, `crm.js`), sem Page Inertia. O único React vivo do módulo é o **cadastro (A)**: `resources/js/Pages/Cliente/{Index,Show,Edit,Create,Import,Ledger,Map}.tsx` + `_drawer/` (10) + `_show/` (13) + `_form/` (5) + `_components/` (4).

---

## 0 · Leis que não se renegociam

1. **Cliente (cadastro) ≠ CRM (pipeline)** — ADR 0301. Falar "CRM" aqui = pipeline pré-venda.
2. **O pipeline CRM está em depreciação** (ADR 0301 §Decisão 3 + `DEPRECATION-PLAN-pipeline.md`, 6 etapas com gate [W] por etapa, **nada executado**). Construir tela React nova para B = criar superfície que o plano manda remover.
3. **`Modules/Crm/Routes/web.php:24-80` (`/crm/*`) é alvo de silenciamento na etapa E2.** Pedir Page nova nessa rota é pedir para o Code trabalhar contra o plano.
4. **Portal do contato (`/contact/*`) é ZONA CINZA declarada** ("NÃO é pré-venda · fora do escopo · Wagner decide separado"). Sem decisão [W], não vira onda.
5. **Autoridade de token:** `TabBar` do DS → protótipo → produção. Zero cor crua: `crm-blade.css` tem **0** `#hex`/`rgb()`/`oklch()` literal (medido); cor só via `var(--*)`.
6. **Medir e aplicar são passos separados**; o que a a11y reprovar no alvo **corrige-se aqui**, não vira pedido.

---

## 1 · Ordem das ondas + âncora por onda

**Não há onda 1.** O MAPA foi colhido do DOM (não de lembrança) e classificado contra o `DEPRECATION-PLAN` lido no turno:

| # | rota (host) | view (`CRM_VIEW` em `app.jsx`) | seções (filhos de `.pb-body`) | lado | âncora no `main` | exportável? |
|---|---|---|---|---|---|---|
| 1 | `crm` / `crm-painel` | painel | `NAV.ds-tabbar.cb-nav` + 8 blocos (`.pb-widget`×8) | **B** | `CrmDashboardController` (Blade) | ❌ depreciação |
| 2 | `crm-leads` | leads | tabbar + `.pb-widget`×2 (filtros · toolbar+grade 16 col) | **B** | `LeadController` (Blade) | ❌ depreciação |
| 3 | `crm-followups` | acompanhamentos | tabbar + `.pb-widget`×2 | **B** | `ScheduleController` (Blade) | ❌ depreciação |
| 4 | `crm-campanhas` | campanhas | tabbar + `.pb-widget`×2 | **B** | `CampaignController` | ❌ depreciação |
| 5 | `crm-propostas` | propostas | tabbar + `.pb-widget`×1 | **B** | `ProposalController` | ❌ depreciação |
| 6 | `crm-modelo` | modelo | (form de `crm_proposal_templates`) | **B** | `ProposalTemplateController` | ❌ depreciação |
| 7 | `crm-chamadas` | chamadas | tabbar + `.pb-widget`×2 | **B** | `CallLogController` | ❌ depreciação · PII alta (`crm_call_logs`, retention 365d) |
| 8 | `crm-relatorios` | relatorios | tabbar + `.pb-widget`×4 | **B** | `ReportController` | ❌ depreciação |
| 9 | `crm-marketplace` | marketplace | tabbar + `DIV` + `.pb-widget` | **B** | `CrmMarketplaceController` | ❌ depreciação ("uso real desconhecido") |
| 10 | `crm-comissoes` | comissoes | tabbar + `.pb-widget`×2 | **B** | `crm_contact_person_commissions` (ARCHIVE→DROP) | ❌ depreciação |
| 11 | `crm-taxonomias` | taxonomias | (sources / life_stages) | **B** | `Category` `source`/`life_stage` · perm `crm.access_sources` (seed cleanup E5) | ❌ depreciação |
| 12 | `crm-config` | config | tabbar + `.pb-widget`×1 | **B** | `CrmSettingsController` · `business.crm_settings` (PRESERVE) | ❌ depreciação |
| 13 | `crm-ficha` | (`window.CrmFicha`) | `DIV.crmf` (4 filhos) | **B** | ficha de **lead** — sem receptor | ❌ depreciação |
| 14 | `crm-logins` | logins | tabbar + `.pb-widget`×2 | **zona cinza** | `ContactLoginController` · perm `crm.access_contact_login` | ⛔ decisão [W] |
| 15 | `crm-pedidos` | pedidos | tabbar + `.pb-widget`×2 | **zona cinza** | `OrderRequestController` (`sales_order`) | ⛔ decisão [W] |
| 16 | `crm-portal` | (`window.CrmPortalPage`) | header + `.pb-body` (4) | **zona cinza** | portal `/contact/*` · `users.crm_contact_id` (KEEP, é de A) | ⛔ decisão [W] |

**Receita do MAPA (reexecutável, não congelar):** `document.querySelector('.cb-root')` → filhos → `.pb-body` → filhos diretos; tabbar = `NAV.ds-tabbar.cb-nav`.

---

## 1-bis · Instrução de execução (a forma padrão, §4-ter)

```
ONDA — nenhuma neste ciclo
  ARQUIVOS A EDITAR   : nenhum no main. (Único ato pedido: RETIRAR o pedido de 2026-08-24
                        de criar resources/js/Pages/Crm/{Painel,Leads,Acompanhamentos,Portal}.charter.md)
  REUSAR (não recriar): resources/js/Pages/Cliente/** — o cadastro (A) é o React vivo do módulo:
                        _drawer/ (10 abas) · _show/ (13) · _form/ (5) · _components/ (4).
                        Se algum dia houver onda de CRM, ela ancora nesses átomos, não em markup novo.
  CRIAR               : nada. Pages/Crm/ NÃO se cria (ADR 0301 + plano E2 silencia /crm/*).
  NÃO TOCAR           : Modules/Crm/Routes/web.php:101-285 (/cliente/*) · /contact/* (portal) ·
                        tabela contacts e colunas crm_* (Tier 0, PRESERVE in-place) ·
                        users.crm_contact_id · BrLookupService (provável dono = A) ·
                        Modules/Connector (API externa lê o pipeline — BLOQUEIO E4) ·
                        prototipo-ui/cowork/ do lado do git (espelho read-only).
  PASSO A PASSO       : 1) confirmar no main que ADR 0301 segue `aceito`
                        2) marcar o pedido anterior como retirado (este doc é o registro)
                        3) responder em CODE_NOTES.md se alguma etapa E1–E6 avançou desde 22/06
  DADO                : nenhum novo. O dado de B é o do CrmDashboardController/LeadController (Blade).
  PARAR SE            : (a) a superfície pertence ao pipeline B → depreciação · (b) portal /contact/*
                        → decisão [W] aberta · (c) qualquer DROP/DML — os 3 BLOQUEIOS do plano
                        (row count por business, auditoria do consumidor Connector/Delphi,
                        confirmar BrLookupService=A) seguem abertos.
```

---

## 2 · Onda 0a — a11y do ALVO (o que falhou **foi corrigido aqui**, não virou pedido)

Bateria rodada no protótipo servido, tema **dark**, após `__oiLazyDone`, com **duas leituras iguais** de `querySelectorAll('*').length` por view (T1 estável em 16/16: painel 743 · leads 879 · acompanhamentos 851 · campanhas 553 · propostas 537 · chamadas 656 · relatórios 688 · marketplace 484 · pedidos 571 · config 454 · logins 554 · comissões 572 · ficha 672 · portal 471).

| # | item | medido no alvo | veredito | ação |
|---|---|---|---|---|
| A1 | falso interativo | **T5 aplicado à sonda:** a 1ª leitura acusou "278 de 318" — era `cursor:pointer` **herdado**. Refeita pela **origem** do pointer (pai sem pointer) + caso de sanidade (`BUTTON` → pointer ✔): **2 falsos**, ambos `TH` ordenável | 🟠 **DS** | `DataGrid` do DS: `TH` ordenável sem `role`/`tabindex` e **0 de 16 `aria-sort`**. Não remendo bundle do DS — bloco 7 |
| A1b | linha da grade | `tbody tr` = `role="button"` + `tabindex=0` (10 de 11) | ✅ | o DS já está certo aqui |
| A3 | ícone sem nome | **15 de 15** (leads) · 19 de 19 (acomp.) · 21 de 21 (ficha) svg sem `aria-hidden` nem nome | 🔴 → 🟠 | **corrigido no build**: `Ic` de `crm-blade.jsx`, `crm-portal.jsx`, `crm-blade-telas.jsx`, `crm-blade-forms.jsx` envolve em `aria-hidden="true"` (ou `role="img" aria-label` via `rotulo`); 10 svg inline de `crm-ficha.jsx` ganharam `aria-hidden`. **Remedido após a correção: 10 de 15** — os 10 restantes são o gatilho `.pb-kebab` do produto-blade (átomo compartilhado), e o `BUTTON` dele **tem** nome acessível, então o svg anônimo dentro dele é 🟠, não 🔴 → bloco 7 |
| A3b | botão sem nome | 0 de 35 | ✅ | — |
| A5 | ARIA de estado nas abas | **14 de 14** `aria-selected` (TabBar do DS) | ✅ | autoridade de token/semântica confirmada no DS |
| A7 | alvo de toque <24px | 1 de 35 (`Atualizado hh:mm`, 115×22) | ⚪ | decisão [W] pendente (ERP denso 1280) — mesma linha da Forja |
| A10 | `aria-live` | **0 no documento** | 🔴 | **não corrijo aqui**: o aviso vem de `ModuloPadrao.useAviso` (arquivo compartilhado por todos os módulos) → **fundação**, bloco 7 |
| A12 | estado vazio | `EmptyState variant="no-results"` com **por que** + **o que fazer** ("Limpe um filtro ou amplie o período") | ✅ | — |
| — | `th scope` | **0 de 18** (painel) · 0 de 16 (leads) | 🔴 → ✅/🟠 | **corrigido no build** nas tabelas minhas (`Mini` + `crm-portal`/`telas`/`forms`: 15 `th` com `scope="col"`); os `th` da grade do DS seguem sem `scope` → bloco 7 |
| — | campo sem rótulo | 2 de 16 (leads): busca da toolbar + `select` da grade do DS | 🔴 → parcial | **corrigido no build**: `aria-label` na busca (`.pb-busca input`). O `select` é do DS → bloco 7 |

**Arquivos do build alterados neste ciclo (só a11y, zero mudança visual):** `crm-blade.jsx`, `crm-blade-telas.jsx`, `crm-blade-forms.jsx`, `crm-portal.jsx`, `crm-ficha.jsx` (+ bump de `?v=` no host `oimpresso.com.html`).

---

## 3 · ALVO medido por seção (read-only, tema dark)

Serve de piso se algum dia [W] reabrir alguma dessas telas. Não é pedido.

**`--accent` resolvido (dark):** `oklch(0.70 0.15 295)` — não o `0.55` do light. Fundo do shell: `oklch(0.26 0.006 240)`.

| seção | alvo |
|---|---|
| `NAV.ds-tabbar.cb-nav` | **14 filhos**, todos `BUTTON` · `gap 0px` · borda inferior `1px solid oklch(0.34 0.008 240)` · aba ativa 13px, `aria-selected="true"` (14 de 14 com estado) |
| `.pb-toolbar` (leads) | **5 filhos nesta ordem**: `.pb-busca` · `.sp` · segmented densidade · segmented visão · `button.os-btn.sm.primary` · `gap 6px` · `padding 8px 12px` |
| grade de leads (DS `DataTablePro`) | **16 colunas** · 10 linhas · altura de linha **45px** (confortável) · corpo 12.5px · cabeçalho **10px** · 2 `.pb-widget` na view |
| `.pb-body` do painel | **8 seções** · `.pb-widget`×8 · `.pb-tbl`×6 (tabelas chave/valor) · 43 nós `.mono` |
| `.crmf` (ficha) | 4 filhos · 3 abas com estado ARIA (3 de 3) · 12 botões, nenhum <24px |
| largura medida | `.cb-root` = 841px na janela do preview (não é 1280 — ver bloco 8) |

---

## 4 · Comportamento + invariantes

Tabela de comportamento **não emitida** — pedido sem onda não carrega contrato de comportamento, e emitir tabela de uma seção que não desce é dívida com selo. As invariantes que o módulo já respeita e que qualquer onda futura herda:

1. Filtro é reversível (Filtros recolhe/expande; `Sel` tem opção "Todos").
2. Permissão nega antes de renderizar (`PERM_DE` → `SemPermissao`), com os nomes **literais** do legado (`crm.access_all_leads`, `crm.access_all_schedule`, `crm.view_all_call_log`, `crm.access_contact_login`, …).
3. Conversão de lead **navega para o cadastro** (`window.__selectRoute("clientes")`) — o pipeline nunca vira dono do cliente.
4. Estado vazio é conteúdo (A12 ✅).
5. Sem número inventado: sem fonte ⇒ `—` (`fmtBRL(null)` → `—`).

---

## 5 · Não inventar (CSS · átomos · dados · copy)

- **CSS:** `crm-blade.css` (classes `cb-`) + `pb-*` do produto-blade. **Zero cor crua medida** (0 `#hex`/`rgb()`/`oklch()` literal no arquivo). Cor só `var(--accent|--pos|--warn|--neg|--text-mute|--bg-2)`.
- **Componentes:** DS (`TabBar`, `DataTablePro`, `KpiCard`, `Chart`, `Drawer`, `Modal`, `StatusBadge`, `EmptyState`) + `PBUI` (`Widget`, `Fld`, `Sel`, `Kebab`). Nada hand-roll.
- **Dados:** o Painel usa os nomes reais do `CrmDashboardController` lido no turno (`total_customers`, `total_leads`, `leads_by_life_stage`, `my_follow_ups`, `todays_followups`, `my_leads`, `my_conversion`, `todays_birthdays`/`upcoming_birthdays`, `contacts_count_by_source`) — mock com a forma certa, não campo inventado.
- **Copy:** PT-BR, sentence case, vocabulário do legado traduzido (marcação/acompanhamento/lead), sem emoji no app.

---

## 6 · DoD + PLACAR

**PLACAR Crm — ciclo 2026-09-04:** entregue **0 de 16** telas mapeadas.
Os 16 ausentes, com motivo:
- **13 por depreciação declarada** (pipeline B — ADR 0301 + `DEPRECATION-PLAN`): painel · leads · acompanhamentos · campanhas · propostas · modelo · chamadas · relatórios · marketplace · comissões · taxonomias · config · ficha-de-lead.
- **3 por decisão [W] aberta** (zona cinza do portal do contato): logins · pedidos · portal.

**Cobertura cumulativa do módulo: 0/16 — e é o número certo.** Subir esse número exige decisão de [W], não trabalho de design.

**DoD deste pacote** (é um pacote de recusa fundamentada, então o recibo é outro):
1. ✅ 16 telas mapeadas do DOM, com T1 estável em cada uma.
2. ✅ Bateria a11y rodada no alvo; o que era meu foi corrigido no build (A3, `th scope`, rótulo da busca).
3. ✅ Sonda A1 refeita com caso de sanidade — o primeiro número (278) era falso.
4. ✅ Pedido anterior contraditório com a ADR 0301 retirado por escrito.
5. ⛔ T7 (`design-diff --compare --check`) não se aplica: não há tela de produção para comparar.

---

## 7 · O que a ancoragem NÃO resolve

| # | item | natureza | dono |
|---|---|---|---|
| 1 | **Não existe receptor React para B.** `resources/js/Pages/Crm/` não existe e `routes/` não tem nada de `Crm` (busca no turno: 0 casos). O módulo vivo é Blade | superfície sem receptor | [W] (e o plano diz para silenciar, não criar) |
| 2 | **Portal do contato** (`/contact/*`, `ContactLoginController`, `OrderRequestController`, comissões de pessoa de contato) — o plano o declara **fora do escopo, "Wagner decide separado"** | decisão [W] aberta | **[W]** |
| 3 | **Etapas E1–E6 do plano de depreciação**: nada executado; 3 BLOQUEIOS abertos (row count por business, auditoria do consumidor externo Connector/Delphi, confirmar `BrLookupService` = A) | verificação bloqueada | [CL] + [W] |
| 4 | **`DataGrid`/`DataTablePro` do DS**: `TH` ordenável sem `role`/`tabindex`, `aria-sort` 0 de 16, `th` sem `scope`, `select` de página sem rótulo | dívida do DS (não do CRM) | DS — pedido próprio, não este |
| 5 | **`aria-live` = 0** no documento: o aviso/toast vem de `ModuloPadrao.useAviso`, compartilhado por todos os módulos | fundação (shell) | PR de fundação sequencial |
| 6 | **Alvo de toque <24px** (1 botão) | decisão [W] (ERP denso) | **[W]** |
| 7 | **Zero `<main>` no documento** do protótipo (AP9 pede um por documento) | fundação (shell/`app.jsx`) | PR de fundação |
| 8 | **`.pb-kebab` do produto-blade** (átomo compartilhado com venda/produto): svg do gatilho sem `aria-hidden` **e** rótulo fixo **"Ações do produto"** — na grade de leads o nome acessível anuncia "produto". Rótulo tem de ser parametrizável | dívida de átomo compartilhado | PR de fundação (produto-blade), não este pedido |
| 9 | **Rota do `app.jsx` sem componente (C6)** — sem dono no repo hoje (§11 do protocolo) | cobertura declarada | — |

---

## 8 · Não medido, declarado

- **Contraste (A8):** não medido. Exige conversão OKLCH→OKLab→sRGB **com caso de sanidade**; sem isso a sonda inventa bug ou absolve defeito. Risco nomeado: cabeçalho de grade a **10px** e `.pb-help`.
- **A2 (foco):** contei **140** regras com `outline: none/0` × **57** com `:focus-visible` no documento inteiro (shell + todos os módulos) — número **global**, não por seção do CRM. Não é veredito.
- **Largura:** medi em **841px** (janela do preview), não nos 1280px da Larissa. Qualquer número dependente de largura (colunas visíveis, quebra da tabbar) é provisório.
- **`Modules/Crm/Routes/web.php`** (20 KB), `LeadController`, `ScheduleController`, `Pages/Cliente/Index.tsx`+charter, `memory/requisitos/Cliente/SPEC.md`, `PARIDADE-area-cliente-*`, `LICOES_CC.md`, `proibicoes.md`, `REGISTRY_DS_COMPONENTES.md`, `contrato/*.contract.json`: **não verifiquei** (não lidos neste turno).
- **Estado das etapas E1–E6 após 2026-06-22:** não verifiquei.

---

## 9 · Recibo

- **Build alterado (só a11y):** `crm-blade.jsx`, `crm-blade-telas.jsx`, `crm-blade-forms.jsx`, `crm-portal.jsx`, `crm-ficha.jsx`, `oimpresso.com.html` (bump `?v=`).
- **Ponte:** este arquivo (reescrito, sem doc novo — anti-scatter).
- **Charter/casos:** nada a destilar — sem onda, sem contrato de seção. **Não** crio `Pages/Crm/*.charter.md` (era o pedido retirado).
- **Pacote (regra de saída):** **não regenerado** — `gerar-payload-partes.mjs` exige os arquivos em disco e não roda do meu lado (escrever pelo contexto do agente é transcrição, ADR 0374). O ciclo fecha **sem pacote**; o comando é:

  ```
  node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
  ```

  e a linha do recibo no `github.md` (`bundle regenerado (<data> · N arquivos)`) só se escreve **depois** de rodar.

---

## RESÍDUO Crm — fila de decisão de [W] (3 itens, nesta ordem)

1. **O pipeline CRM continua em depreciação?** Se sim, o protótipo `crm-blade*` é **referência de legado**, não alvo de export — e eu paro de tratá-lo como tela a exportar.
2. **Portal do contato: fica ou sai?** É a única parte do módulo com valor operacional plausível (pedido do cliente, login, comissão). Se fica, ela sai da zona cinza e vira `Pages/Portal/*` (nome a decidir) — e então há onda: 3 telas, ancoradas nos átomos de `Pages/Cliente/**`.
3. **Alvo de toque em 1280 denso:** mínimo WCAG 24×24 ou exceção declarada? (mesma pergunta aberta da Forja — responder uma vez, vale para o ERP todo).

---

## Resposta do Claude Code (2026-09-04)

> Landeado por [W] colando 1× (rota sancionada — ADR 0389). As 3 verificações do PASSO A PASSO
> foram executadas contra `origin/main` fresco; o veredito de cada uma está no
> [`CODE_NOTES.md`](../../../CODE_NOTES.md) (canal Code→Cowork), entrada `[PROCESSADO 2026-09-04]`.
>
> Resumo: **(1)** ADR 0301 segue `status: aceito` · `lifecycle: ativo`. **(2)** Pedido de 2026-08-24
> retirado — este arquivo é o registro. **(3)** **Nenhuma etapa E1–E6 avançou desde 22/06**, medido
> pela consequência (41 rotas `/crm/*` ativas, cron `everyMinute` de pé, API Connector exposta,
> SPEC `rascunho`, BRIEFING `producao`). Bônus: o BLOQUEIO 3 do plano (`BrLookupService` pertence
> a A?) foi **fechado por varredura contada** — pertence a A.
