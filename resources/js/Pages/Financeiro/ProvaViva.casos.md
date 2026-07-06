---
casos: Prova viva (primitivos) · /financeiro/prova-viva
irmaos: ProvaViva.charter.md (lei) · RUNBOOK-prova-viva.md
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-07-06"
---

# Casos de uso — /financeiro/prova-viva (pilot ADR 0253)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> **Contexto:** tela-piloto que fecha o critério de pronto da [ADR 0253](../../../../memory/decisions/0253-primitivos-layout.md)
> (Financeiro densa 100% pelos primitivos de layout). **Dados são MOCK no `.tsx`** — é prova de
> LAYOUT, não a Visão Unificada de produção. Persona: Eliana [E] (financeiro) + Larissa (1280px).
> Âncoras: charter (Mission/Goals/Non-Goals/UX Targets) + ADR 0253 + ADR 0093 (Tier 0).

## UC-PV-01 — A tela abre atrás do guard de leitura do Financeiro
Status: ✅ (ProvaVivaControllerTest — "renderiza Inertia component Financeiro/ProvaViva")
Eliana com `financeiro.dashboard.view` abre `/financeiro/prova-viva`. A rota responde **200** com
header `X-Inertia` e o component Inertia é exatamente `Financeiro/ProvaViva`. Acesso direto por URL
(não há link na sidebar — é pilot). Âncora: RUNBOOK "Como acessar" + charter status live.
**Pronto quando:** GET responde 200, `X-Inertia` presente e `component == 'Financeiro/ProvaViva'`.

## UC-PV-02 — Sem a permissão, a tela nega (403)
Status: ✅ (ProvaVivaControllerTest — "bloqueia acesso sem a permissão financeiro.dashboard.view")
Usuário sem `financeiro.dashboard.view` recebe **403** — a tela fica atrás do mesmo guard de leitura
da Fluxo/DRE (`can:financeiro.dashboard.view`). Âncora: Controller `middleware('can:...')` + RUNBOOK "Guard".
**Pronto quando:** GET de user sem a permissão responde 403 (e não 200).

## UC-PV-03 — Read-only: o payload NÃO carrega dado de tenant (Tier 0 por construção)
Status: 🧪 (ProvaVivaContractTest C1 — payload sem props de negócio)
Non-Goal do charter: "Consultar DB / dado de tenant — read-only sem query de negócio". O Controller
faz `Inertia::render('Financeiro/ProvaViva')` **sem props** — os lançamentos são mock no `.tsx`.
O isolamento multi-tenant (ADR 0093) é trivialmente preservado porque nada de negócio é lido. Este UC
BLINDA essa promessa: se alguém no futuro passar props de negócio (títulos, KPIs reais) sem aplicar
global scope, o teste QUEBRA e força a revisão Tier 0.
**Pronto quando:** o payload Inertia não expõe nenhuma prop de dado de tenant (títulos/kpis/rows) —
nenhuma chave de negócio no `page.props` além do baseline compartilhado do AppShell.

## UC-PV-04 — Não é a landing de produção (Non-Goal)
Status: ⬜ (manual/doc — Non-Goal do charter; a landing é Financeiro/Unificado/Index)
A tela NÃO substitui a Visão Unificada (`Financeiro/Unificado/Index`) e não há link na sidebar pra
ela. Conciliação/fiscal/cobrança no drawer são **casca de domínio** (mock), não ligadas a DB — não
declarar "está feito". Âncora: charter Non-Goals.
**Pronto quando:** documentado e sem link de navegação primária; drawer permanece mock.

## UC-PV-05 — Critério ADR 0253: 100% primitivos, zero flex/css solto
Status: 🧪 (ProvaVivaContractTest C2 — grep estático do critério de pronto)
Goal-mãe da ADR 0253: todo arranjo via `Box/Stack/Inline/Grid/Container/Text`, sem `<div className="flex">`
solto e sem `.css` de tela. Exceções toleradas pelo RUNBOOK (helpers de layout dos próprios primitivos):
`inline-flex`, `flex-1`, `flex-col`, `place-items`. Âncora: RUNBOOK "Como validar (gates)" + charter Goals.
**Pronto quando:** o `.tsx` não contém `flex` solto (fora das exceções do RUNBOOK) nem `import`/link de
`.css` de tela — asserção estática sobre o fonte, replicando o gate do RUNBOOK.

## UC-PV-06 — Cabe em 1280px + zero erro JS no console (UX Targets)
Status: ⬜ (E2E/visual — axe + screenshot 1280/1440, ainda não automatizado)
Larissa (1280px) vê a tela sem scroll horizontal (grade reflua `sm:`/`xl:`), h1 26px, KPI na type-scale
token. **0 erros JS no console.** Âncora: charter "UX Targets". Fecha via Pest Browser + `@axe-core`
(zero violação crítica WCAG) nos viewports 1280 e 1440 — pendência honesta (não há E2E Browser desta tela).
**Pronto quando:** E2E Browser renderiza sem scroll-x em 1280, console limpo e axe sem violação crítica.

---

> **Adendo MV batch 2026-07-06 (piloto Módulo Vivo — screen-qa Financeiro/ProvaViva).**
> A auditoria do contrato mostrou que o `ProvaVivaControllerTest` cobria só rota+guard (UC-PV-01/02)
> — o **Non-Goal mais importante** desta tela (read-only, zero dado de tenant — a razão de o Tier 0
> ser "trivial por construção") NÃO tinha teste que mordesse. UC-PV-03 e UC-PV-05 fecham o gap com
> asserção derivada do charter/ADR 0253 (payload vazio de negócio + critério estático de primitivos),
> não do código. UC-PV-06 (axe/console 1280/1440) fica como pendência de E2E Browser, honestamente marcada.
