---
page: /sells/create-v3
component: resources/js/Pages/Sells/CreateV3.tsx
owner: luiz
status: draft
last_validated: "2026-08-07"
parent_module: Sells
related_us: [US-SELL-058]
related_adrs: [253, 104, 93, 62]
tier: C
charter_version: 1
---

# Page Charter — /sells/create-v3

> **Status:** draft — **preview de design, não é tela de produção.** Dono: **[L] Luiz**, que assumiu o cadastro de venda em 2026-08-06.
> Irmão: [`CreateV3.casos.md`](CreateV3.casos.md) (contrato) · RUNBOOK: [`RUNBOOK-create-v3.md`](../../../../memory/requisitos/Sells/RUNBOOK-create-v3.md) (F1 PLAN).

---

## Mission

Ensaiar o redesenho do cadastro de venda **sem tocar na tela que o cliente opera**.

A tela viva é `Sells/Create.tsx` (`/pos/create`), e quem a usa é a **ROTA LIVRE** (`business_id=4` — Larissa dona, Guilherme retaguarda), 99% do volume de vendas. Restrição de negócio declarada por [L] em 2026-08-06, textual:

> *"Tela do Guilherme e da Larissa não pode ser alterada de forma alguma, se não eles quebram contrato e perdemos dinheiro."*

**A feature flag não protege.** Medido em `origin/main`: `FeatureFlagService::$fallbackDefaults['useV2SellsCreate'] = true` desde 2026-05-27 (*"Wagner: ative para todos pronto"*). A V2 React está ligada por padrão pra todos — editar `Create.tsx` e deployar atinge o cliente direto. Daí **arquivo novo + rota nova**, e não branch de flag.

---

## Goals — o que a tela faz

- `AppShellV2` como layout persistente (mesma convenção de `Sells/Create.tsx`).
- Faixa âmbar permanente no topo: quem abrir por engano sabe em 1 segundo que não é produção.
- 3 passos numerados — **1 Cliente · 2 Itens · 3 Fechamento** — espelhando a âncora de design.
- Coluna de fechamento com *plate* escuro para o total (único bloco de peso visual da tela).
- Grid de itens **somente leitura**.
- Botão "Finalizar venda" renderizado **`disabled`** por construção.
- Layout composto por primitivos (`Stack`/`Inline`/`Grid` de `@/Components/layout`) — [ADR 0253](../../../../memory/decisions/0253-primitivos-layout.md).

---

## Non-Goals — o que a tela NÃO faz

> ⚠️ Os dois primeiros são **declaração literal de [L]**, não inferência minha. O restante desta seção fica **pendente de [W]/[L]** — a skill `charter-write` é proibida de inferir Non-Goal, e anti-padrão inventado no charter é pior que ausente porque parece canon.

- ❌ **Não calcula.** Subtotal, desconto, imposto, acréscimo, frete e total chegam prontos do controller como dados de cena. Cálculo de valor/estoque é território `[V0]` (REGRA MESTRE, `memory/proibicoes.md`) e não entra em tela de preview — foi assim que nasceu o incidente `num_uf` de 2026-06-05 (`final_total` inflado ~×100.000 em 16 vendas do `biz=4`).
- ❌ **Não grava.** Sem `store()`, sem POST, sem rota de escrita, sem migration.
- ❌ **Não substitui `/pos/create`.** Não há cutover previsto (RUNBOOK §F5).
- _pendente [W]/[L]_ — demais Non-Goals.

---

## Fronteira — o que este trabalho não pode tocar

| ⛔ Proibido | Por quê |
|---|---|
| Editar `Pages/Sells/Create.tsx` | é a tela deles |
| Editar `SellPosController@create` | serve a tela deles |
| Editar componente compartilhado que `Create.tsx` importa | a alteração vaza pra tela deles pelo import |

Precisando de variação de um componente existente, **nasce cópia local** em `Pages/Sells/_components/` — nunca edição do original.

---

## Automation Anti-hooks

> _pendente [W]_ — só [W] preenche esta seção (cada item vira Pest GUARD no CI). Não infiro.

---

## Contrato visual

> _pendente [W]/[L]_ — copy literal e ordem dos blocos. Enquanto vazio, a âncora de design abaixo é a referência.

**Âncora de design:** `prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx` — cockpit "Venda — Guia de Produção", importado do projeto de design Oimpresso `019e2365` em 2026-08-06. Roda local em `http://localhost:5570` (config `cockpit-vendas` do `.claude/launch.json`).

---

## Pest GUARD

> Nenhum teste declarado. **Esta seção não promete teste que não existe** — charter que promete GUARD inexistente é revogável (`how-trabalhar.md`, regra dura da perna Charter).
