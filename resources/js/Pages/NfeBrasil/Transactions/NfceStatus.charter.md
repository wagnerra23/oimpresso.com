---
id: resources-js-pages-nfe-brasil-transactions-nfce-status-charter
page: /nfe-brasil/transactions/{tx}/status
component: resources/js/Pages/NfeBrasil/Transactions/NfceStatus.tsx
related_prototype: n/a (herda PT-03 Detalhe; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-05-16"
parent_module: NfeBrasil
related_adrs: [29, 58, 62, 93, 94, 143]
related_us: [US-NFE-002]
tier: A
charter_version: 1
---

# Page Charter — /nfe-brasil/transactions/{tx}/status

> **Status:** draft em 2026-05-16. Charter criado pelo Wave M boost (auditoria NfeBrasil 71→82, gap D3.c charters 30%). Non-Goals + Anti-hooks aguardam aprovação Wagner antes de promover pra `status:live`.

---

## Mission

Acompanhar o **status fiscal pós-venda de TODOS os documentos gerados** para uma `Transaction` individual — NF-e (modelo 55), NFC-e (modelo 65), NFS-e e demais documentos fiscais da venda. É a tela onde o operador POS (Larissa-caixa) consulta o resultado da emissão SEFAZ após clicar "Finalizar venda" no `/sells/create`. Polling 2s até cStat final (100=autorizado / rejeitado / pendente), com fallback Centrifugo CT 100 quando broadcast vier.

> **Emenda de Mission — [W] 2026-07-28.** Palavras textuais: *"a tela deve exibir nfe e nfse [nfce] e todos os documentos gerados. isso deve"*. A Mission anterior escopava a tela a **NFC-e apenas** ("status fiscal pós-venda NFC-e"), e era essa Mission — não um bug — que sustentava o filtro `modelo 65`. Decisão de produto do dono; transcrita, não inferida.
>
> **O diagnóstico técnico, e a errata da minha 1ª redação.** O [SDD de emissão fiscal](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md) **§5.3 F3 já continha o diagnóstico correto e completo**, textual: *"O `NfeEmissaoController@listar` existe justamente para cobrir os dois modelos, e o docblock dele diz 'Substituiu o GET nfe-status que retornava só modelo 65' — mas a **tela `NfceStatus` continua no endpoint antigo**."*
>
> ⚠️ **A 1ª versão desta emenda afirmou que o SDD estava "errado nas duas metades". FALSO — e fica registrado, não apagado.** Eu li a linha-resumo do §9 (*"a tela só enxerga NFC-e 65"*) e o corpo do [#4913](https://github.com/wagnerra23/oimpresso.com/pull/4913), e **não li o §5.3**, que é onde o corpo do documento estava certo. Classe **LC-08** (afirmar a partir da fonte errada) — cometida dentro de uma sessão que catalogava essa mesma classe. O que sobrevive da crítica é só metade, e nem era do SDD: o rótulo **A4 do corpo do #4913** (*"o teste fixa o defeito como correto — catraca contra o conserto"*) está errado, porque migrar a tela pro endpoint largo **não exige tocar** `NfeStatusControllerTest::'modelo 55 é ignorado pelo endpoint NFC-e'` — esse teste é o contrato correto do endpoint estreito de polling do POS (US-NFE-002).
>
> Os fatos medidos em `origin/main` 2026-07-28 (esses seguem valendo):
> - `NfeEmissaoController::listar` (`GET transactions/{tx}/emissoes`) **não filtra modelo nenhum** — já devolve 55/65/67, e o enum da tabela é `['55','65','67']` desde a migration original.
> - `useEmissoesPorTransaction.ts` declara no cabeçalho *"substitui useNfceStatus (que só pegava modelo 65)"* e já roda em `Sells/FiscalSection` + `Sells/Index`. **Esta tela é a que não migrou** (`NfceStatus.tsx:59 (verificado@d4afe95)`).
>
> **Consequência de implementação** (medida, não estimada): trocar `useNfceStatus` → `useEmissoesPorTransaction` cobre 55/65/67 **sem tocar backend**. Já a **NFS-e vive em outra tabela e outro módulo** (`nfse_emissoes`, `Modules/NFSe/Models/NfseEmissao`), então exige uma 2ª fonte — e o precedente canônico a imitar ([ADR 0011](../../../../../memory/decisions/0011-alinhamento-padrao-jana.md)) é o `Modules/Fiscal/Http/Controllers/CockpitController.php`, que já importa `NfeEmissao` **e** `NfseEmissao` no mesmo controller.
>
> **Não promovido a `status: live`** por esta emenda — o gate `charter status:live precisa de sinal de prod` exige evidência de produção, e a tela ainda não foi reimplementada.

---

## Goals — Features (faz)

- AppShellV2 + Head `Status fiscal — Venda #{tx}` (PT-BR) — _era `Status NFC-e`; alargado pela emenda de Mission [W] 2026-07-28_
- **Lista os documentos fiscais da venda** (NF-e 55 · NFC-e 65 · 67 · NFS-e), cada um com seu próprio status/badge — não um documento só. _Goal novo pela emenda [W] 2026-07-28; a implementação segue pendente (a tela hoje renderiza um card singular)._
- `NfceStatusBadge` por documento, recebendo a emissão — centraliza polling/transport (separação de UI vs transport — ADR 0058)
- Polling default 2s via `useEmissoesPorTransaction` até cStat final de todos — _era `useNfceStatus` (estreito, só modelo 65); a troca é a implementação da emenda_
- Transport-agnostic: hoje HTTP fetch; mañana Centrifugo broadcast (Page não muda, só hook)
- Link "Voltar para vendas" → `/sells` (navegação clara)
- Texto explicativo curto (~1 linha) sobre o que a página faz
- Cores semânticas Cockpit V2 ADR 0110: emerald (cStat 100 autorizado), amber (pendente <30s), red (rejeitado)
- Multi-tenant Tier 0: query `Transaction::where('business_id', $businessId)` global scope
- Polling para automaticamente após cStat final (sem loop infinito)
- Read-only — não emite, não cancela, não modifica

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test.

- ❌ Reemissão NFC-e (cancelar + emitir nova é fluxo Sells + ADR 0143 FSM, não aqui)
- ❌ Cancelamento direto desta tela (vai pelo FSM `CancelarVendaCascade` ADR 0143 via `/sells/{tx}` drawer)
- ❌ Edição de dados da venda (Transaction é read-only no contexto fiscal pós-emissão)
- ❌ Download direto DANFE (link existe mas é em outra view; aqui é só status)
- ❌ Polling cross-tenant (cada Transaction tem business_id próprio — ADR 0093)
- ❌ Histórico de status anteriores (audit via `activity_log`, não UI aqui)
- ❌ Broadcast Centrifugo no Hostinger (CT 100 only — ADR 0062)
- ❌ Re-tentativa SEFAZ manual (job background `EmitirNfceJob` cuida disso)

---

## UX Targets

- p95 first-paint < 1200ms (Page é simples — só badge + título)
- Polling 2000ms ± 200ms (jitter aceitável)
- Status final em até 30s (SEFAZ-SP típico autoriza <10s)
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal (canon ROTA LIVRE biz=4)
- Tipografia canon ADR 0110: header 22px, body 13px
- Cores semânticas: emerald (autorizado cStat 100), amber (pendente), red (rejeitado)
- maxWidth 720px (Page contida — não precisa full-width)
- Polling para após cStat ∈ {100, 101, 102, 110, 135, 150, ...rejeitados}
- Link voltar acessível por teclado (`a` tag nativo)

---

## UX Anti-patterns

- ❌ Loop polling infinito sem cap de tentativas (canon = para após cStat final)
- ❌ Toast spam a cada poll (canon = badge atualiza inline, toast só em mudança de estado)
- ❌ Modal pra mostrar status (canon = inline na Page)
- ❌ Cor crua `bg-(green|red)-N` (canon = emerald/amber/red ADR 0110)
- ❌ Reload full após status final (canon = badge atualiza, sem reload)
- ❌ Polling acelerado <1s (canon = 2s default — não martelar SEFAZ)
- ❌ Auto-redirect pra `/sells` após autorizar (canon = usuário decide)
- ❌ Spinner permanente sem timeout (canon = cap 60s então erro UI)

---

## Automation Hooks

- `GET /nfe-brasil/transactions/{tx}/status` → `NfeStatusController::showPage` (Inertia render só com `transaction_id`)
- `GET /nfe-brasil/api/transactions/{tx}/nfe-status` → JSON polling endpoint (status, cstat, motivo, chave_44, numero/serie quando autorizado)
- Hook `useNfceStatus(transactionId)` interno do `NfceStatusBadge` — polling 2s + abort controller no unmount
- Multi-tenant: `Transaction` usa `HasBusinessScope` (ADR 0093) — cross-tenant retorna 404
- Job upstream: `EmitirNfceJob` dispara após `Sells::store` finalizar (background queue), grava status em `nfe_emissoes.cstat` + `chave_acesso`
- Quem lê o estado é o próprio `NfeStatusController::show`, consultando `NfeEmissao` direto (`where business_id + transaction_id + modelo 65`, `orderByDesc('id')`) — **não** existe `NfeService::consultarStatusEmissao`
- Transport futuro: Centrifugo channel `nfce.business.{biz}.tx.{tx}` (ADR 0058 — CT 100 only)
- Audit: leitura pura, sem activity log (read-only não precisa)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir (read-only)
- ❌ Não dispara emails ao receber autorização (notificação cliente vai por fluxo Sells + ADR 0143 cascade, não aqui)
- ❌ Não escreve no banco no render (read pure — só consulta `NfeEmissao`)
- ❌ Não chama SEFAZ no render (consulta DB local; SEFAZ é polled pelo `EmitirNfceJob` background)
- ❌ Não acessa Transaction de outro `business_id` (multi-tenant Tier 0 + global scope)
- ❌ Não dispara re-emissão automática (UI read-only; retry é decisão humana via Sells)
- ❌ Não cancela NFC-e direto (cancelamento passa pelo FSM `CancelarVendaCascade` ADR 0143)
- ❌ Não loga PII (chave_acesso 44 dígitos sai limpo, mas razão_social/CPF cliente nunca em log)
- ❌ Não roda daemon polling no Hostinger (Centrifugo broadcast = CT 100 only ADR 0062)

---

## Métricas vivas (Pest GUARD — a escrever em F1.5)

> ⚠️ **Correção factual 2026-07-28** (agent `sdd-from-source`, Fase 2.6 — fato, não intenção):
> o arquivo abaixo **nunca existiu**. `find Modules/NfeBrasil/Tests -iname "*Charter*"` devolve
> **0** e o diretório `Tests/Charters/` não existe. Os testes **reais** desta tela hoje são
> [`Modules/NfeBrasil/Tests/Feature/NfeStatusControllerTest.php`](../../../../../Modules/NfeBrasil/Tests/Feature/NfeStatusControllerTest.php)
> (forma do payload) e
> [`Modules/NfeBrasil/Tests/Feature/NfeStatusContratoTest.php`](../../../../../Modules/NfeBrasil/Tests/Feature/NfeStatusContratoTest.php)
> (contrato — `UC-NFST-01..05`, ver [`NfceStatus.casos.md`](NfceStatus.casos.md)), mais o
> characterization `tests/Browser/NfeBrasil/NfceStatusTest.php`. A lista abaixo fica como
> **desejo de cobertura**, não como inventário.

```php
// DESEJADO (não existe): Modules/NfeBrasil/Tests/Charters/NfceStatusCharterTest.php

it('renders under 1200ms p95 with badge + title only')
it('does not emit emails on render or status change')
it('does not call SEFAZ on render (only background EmitirNfceJob does)')
it('does not write to DB on render (read-only)')
it('isolates Transaction by business_id (cross-tenant 404)')
it('stops polling when cStat reaches final state (100 / rejected)')
it('respects 2s polling interval with abort on unmount')
it('renders at 1280px without horizontal scroll')
it('does not log PII (CPF/razao_social stripped from any Log entry)')
it('does not redirect after authorization (user decides)')
```

---

## Refs

- [US-NFE-002](../../../../../memory/requisitos/NfeBrasil/SPEC.md) — Emissão NFC-e + status pós-venda
- [ADR 0029](../../../../../memory/decisions/0029-padrao-inertia-react-ultimatepos.md) — Inertia + UltimatePOS
- [ADR 0058](../../../../../memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md) — Centrifugo CT 100 (transport futuro)
- [ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md) — Hostinger sem daemons
- [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0143](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline (cancel cascade flow)
- [BRIEFING.md](../../../../../memory/requisitos/NfeBrasil/BRIEFING.md) — estado consolidado módulo

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-16 | [CC] Wave M boost | Draft criado pelo Wave M auditoria (NfeBrasil 71→82, gap D3.c charters 30%). Non-Goals + Anti-hooks aguardam aprovação Wagner. |
| 2026-07-28 | [C] `sdd-from-source` | **Só correção factual** (Fase 2.6 — fato sim, intenção não): classe `NfceStatusController`→`NfeStatusController::showPage`; rota de polling `/transactions/{tx}/nfce/status`→`/api/transactions/{tx}/nfe-status`; removida a referência a `NfeService::consultarStatusEmissao`, método inexistente (`grep` no repo: 1 ocorrência, esta linha); §Métricas vivas marcada como desejo, não inventário. **Nenhum Non-Goal, Anti-hook, Goal, persona ou escopo tocado** — o `status: draft` segue, e a divergência entre os Non-Goals `❌ Reemissão`/`❌ Download DANFE` e o que o `.tsx` faz está registrada como decisão [W] em [`NfceStatus.casos.md`](NfceStatus.casos.md) §Backlog + [SDD §6.6](../../../../../memory/requisitos/NfeBrasil/SDD-emissao-fiscal-v1.0.md). |
