---
id: requisitos-cliente-briefing
module: Cliente
status: producao
updated_at: "2026-08-26"
distilled_at: "2026-08-26"
distilled_by: "manual [C] — redestilação PARCIAL: só a seção 'Âncora de design' (revisão do veredito de paridade + correção da âncora do Map, que o texto dava como ausente). O resto do corpo NÃO foi re-lido; valem a redestilação de 2026-08-18 (Onda 0, PR #5924) e a de 2026-07-27 (SDD + contratos, PR #4870)."
---

# BRIEFING — Cliente (cadastro de clientes / contatos)

> **Última atualização:** 2026-06-22 · **Owner:** Wagner · **Status produção:** ✅ usado por biz=4 (ROTA LIVRE — Larissa) e demais tenants.
> 🪪 **Cliente ≠ CRM:** este é o **cadastro de Cliente/contatos** — coisa separada do *pipeline CRM* (leads/propostas/campanhas), que está em **depreciação** (ver [plano](../Crm/DEPRECATION-PLAN-pipeline.md)). Decisão Wagner 2026-06-22 ("contacts não é o crm").

## O que é

Cadastro de clientes **PF e PJ** com canon fiscal BR completo (CPF/CNPJ com validação mod-11, IE/RG, regime, endereço, contato) e tela de detalhe rica via **drawer 760px lateral** (8 abas cadastrais) aberto da listagem ([ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)). Inclui múltiplos endereços por contato, lookup CEP (ViaCEP) e CNPJ (BrasilAPI), tab IA (Copiloto) e auditoria LGPD.

## Estado atual (verificado @origin/main)

- **15 US declaradas** na [SPEC.md](SPEC.md) — 14 com código verificado (`anchor_coverage 100%`, ADR 0273), 1 parcial (US-078 PR3: seletor de endereço salvo na venda).
- **Telas Inertia:** `resources/js/Pages/Cliente/{Index,Create,Edit,Show,Import,Ledger,Map}.tsx`. Superfície de detalhe viva = drawer (Index); `Show.tsx` é legado dual-render.
- **Multi-tenant Tier 0:** `App\Contact` com global scope `business_id`; cross-tenant → 404 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- **LGPD:** `cpf_cnpj`/`ie_rg`/`bank_account` mascarados antes dos props; activity log exclui PII; sem hook WhatsApp/email no cadastro.

## Onde está

- **Requisitos (canon):** aqui em `memory/requisitos/Cliente/` — [SPEC.md](SPEC.md) + [relatório de alinhamento](audits/ALINHAMENTO-cliente-2026-06-22.md). RUNBOOKs/visual-comparisons ainda em `../Crm/` (a mover).
- **Código:** `Modules/Crm/` (controllers `Cliente*Controller`, `ContactAddressController`) — rename de módulo não feito; **não há um módulo `Cliente` separado** (o código fica em `Modules/Crm`).
- **Dados:** core `App\Contact` + `App\ContactAddress` (tabelas `contacts`/`contact_addresses`).

## Falta / próximos

- US-078 PR3 — dropdown de endereço salvo na tela de venda (`Sells/Create`; hoje `shipping_address` é texto livre), ~3h.
- Migrar RUNBOOKs/UI-CATALOG/ARCHITECTURE de `Crm/` → `Cliente/` (execução do plano de separação).
- Backlog secundário em [SPEC.md §5](SPEC.md).

## Contrato de tela (SDD)

O módulo passou a ter **SDD** em [`SDD-cadastro-cliente-v1.0.md`](SDD-cadastro-cliente-v1.0.md) — §5 fluxos + §6 casos de uso — e `casos.md` por tela,
gerados pelo chip `sdd-from-source` ([ADR 0351](../../decisions/0351-sdd-from-source.md), PR #4870).

> **Contagem viva — não copiada aqui** (CU · UC · telas cobertas · onde a cadeia quebra):
> `node scripts/governance/requisitos-status.mjs Cliente`
>
> O painel derivado fica em [`_STATUS-GENERATED.md`](_STATUS-GENERATED.md). Número escrito à mão apodrece —
> este doc aponta para o dono, não restateia (proibições §5, 2026-07-17).

## Âncora de design (protótipo)

As telas passaram a declarar `related_prototype` no charter — antes a ligação vivia em `bundle_source`/`mwart_pattern_reuse`, campos que o resolvedor só lê com `--staging`. `Show` declara `n/a` (herda PT-03; o drawer 760 substituiu o fullpage). **`Map` TEM âncora** (`prototipo-ui/cowork/cliente-mapa.jsx`, religada em [#5938](https://github.com/wagnerra23/oimpresso.com/pull/5938) 2026-08-18) — a frase anterior, que dizia ficar sem âncora por decisão da [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md), apodreceu no mesmo mês; verificado 2026-08-26 por `node prototipo-ui/ancora.mjs Cliente/Map`.

**Revisão de 2026-08-26 — o veredito "tela viva À FRENTE" era de 23/06 e tinha 3 donos.** Ele foi medido contra `prototipo-ui/prototipos/clientes/` (hoje com **0 arquivos versionados**) e numa janela em que o espelho tinha metade do arquivo vivo (58.331 vs 112.096 bytes, corpo do [#5743](https://github.com/wagnerra23/oimpresso.com/pull/5743)). Segue verdade para o **drawer 760** — cujo protótipo foi derivado *da produção*. **Não vale** para listagem/Import/Map. Os 3 donos foram datados e reapontados: `Crm/clientes-gap.md` ([#6294](https://github.com/wagnerra23/oimpresso.com/pull/6294)), `Cliente/clientes-gap.md` e `prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md`. **Fidelidade visual segue NÃO MEDIDA** (Onda 6): nenhum comparador roda sem browser, e Cliente não tem baseline visual.

> **Estado vivo — não copiado aqui:** `node prototipo-ui/ancora.mjs Cliente/<Tela>`
>
> Diagnóstico medido, pareamento protótipo↔tela e ondas: [`PARIDADE-area-cliente-diagnostico-e-ondas.md`](PARIDADE-area-cliente-diagnostico-e-ondas.md).
