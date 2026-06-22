---
slug: separar-cliente-deprecar-crm-pipeline
title: "Separar Cliente (cadastro) do CRM (pipeline) e deprecar o pipeline"
type: adr
status: proposed
kind: decision
decided_by: [W]
proposed_at: "2026-06-22"
module: Crm
tags: [cliente, crm, deprecação, separação, spec-anchored, contacts]
related: ["0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios", "0105-cliente-como-sinal-guiar-sem-mandar", "0179-cliente-drawer-760px-substitui-show-fullpage", "0273-anchor-spec-codigo-formato-canonico-fluxo-novo"]
pii: false
---

# ADR (proposta) — Separar Cliente (cadastro) do CRM (pipeline) e deprecar o pipeline

> **Status:** proposed — aguarda aprovação Wagner (caminho "ADR canon"). Origem: Wagner 2026-06-22, *"contacts não é o crm"* + escolha "separar e deprecar o CRM".

## Contexto

`Modules/Crm` (herança UltimatePOS) mistura **duas coisas distintas**:

- **(A) Cadastro de Cliente / contatos** — tela viva da Larissa (biz=4): `App\Contact`/`App\ContactAddress`, drawer 760px ([ADR 0179](../0179-cliente-drawer-760px-substitui-show-fullpage.md)), dados fiscais BR, controllers `Cliente*Controller`. **Mantido e evoluído.**
- **(B) Pipeline CRM pré-venda** — leads/funil/propostas/campanhas/follow-ups/deals. Silenciado em 2026-06-08 ("não faz sentido pro negócio gráfica/vestuário"). Código congelado (1 commit/90d). SPEC.md de B era draft de expansão **nunca aprovado**.

O canon anterior ([crm-e-o-modulo-de-cliente.md](../../reference/crm-e-o-modulo-de-cliente.md), 2026-06-01) afirmava "Crm = Cliente, são o mesmo". Inventário de código (2026-06-22, agente `deprecar-modulo`) confirma que **A e B são tecnicamente desacoplados** (controllers `/cliente/*` têm zero referências ao pipeline), com 3 acoplamentos de borda resolvíveis (tabela `contacts` compartilhada via `type='lead'`; API externa `Modules/Connector`; `users.crm_contact_id` que é do portal/A).

Problema secundário (governança): a spec viva do cadastro estava em `Crm/SPEC-us-063-078.md`, que o `anchor-lint.mjs` ([ADR 0273](../0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md)) **não lê** (só varre `SPEC.md`). A máquina lia o SPEC errado (o pipeline silenciado).

## Decisão

1. **Cliente ≠ CRM** no canon. O cadastro de Cliente é concern próprio; o pipeline CRM é outro, **em depreciação**.
2. **Canon do cadastro → `memory/requisitos/Cliente/SPEC.md`** (movido de `Crm/SPEC-us-063-078.md`). Efeito: `anchor-lint` passa a lê-lo (máquina ligada para o cadastro) e o `memory-schema-gate` valida o frontmatter. Âncoras ADR 0273 nas 15 US (0 path-morto).
3. **Deprecar o pipeline CRM (B)** conforme [`memory/requisitos/Crm/DEPRECATION-PLAN-pipeline.md`](../../requisitos/Crm/DEPRECATION-PLAN-pipeline.md) — 6 etapas reversíveis com gate Wagner por etapa, preservando 100% o cadastro (A). Tabela `contacts` e colunas `crm_*` ficam **PRESERVE in-place** (dormentes). DROP de qualquer `crm_*` está **BLOQUEADO** até verificação de row count por business + auditoria do consumidor externo Connector.
4. **Sem rename de módulo agora.** O código (A e B) permanece fisicamente em `Modules/Crm/` — renomear o módulo `Modules/Crm` para `Cliente` fica fora deste escopo (esforço grande, módulo vivo). Não há um módulo `Cliente` separado.

## Consequências

- (+) A máquina de fidelidade passa a cobrir o cadastro (degrau 2, ADR 0273) e a parar de tratar o pipeline silenciado como o SPEC canônico.
- (+) Caminho claro e reversível pra remover ~12 controllers / 9 entities / 9 tabelas de superfície zumbi, sem risco pro cadastro.
- (+) Desfaz a confusão recorrente "Cliente = CRM" que incomodava o Wagner.
- (−) Estado intermediário: docs do cadastro (RUNBOOKs/UI-CATALOG/BRIEFING) ainda em `Crm/` (links `../Crm/`), a migrar pra `Cliente/` na execução do plano.
- (−) Colunas `contacts.crm_*` ficam dormentes (não removidas, por segurança Tier 0) — débito cosmético aceito.
- (−) `Modules/Connector` CRM API precisa de auditoria/coordenação antes de remover entidades (BLOQUEIO E4).
- (⚠️) Torna obsoleto o draft `proposals/drafts/crm-pre-venda-pipeline.md` e o `Crm/SPEC.md` (US-CRM-001..062).

## Alternativas consideradas

1. **Renomear `SPEC-us-063-078.md` → `Crm/SPEC.md`** (recomendação inicial): rejeitada — re-grudaria o cadastro na identidade "Crm", contra "contacts não é o crm".
2. **Ensinar `anchor-lint` a varrer `SPEC*.md`**: rejeitada agora — mexe em tooling compartilhado (afeta todos os módulos + baseline) e mantém o cadastro sob a pasta `Crm`. Exigiria emenda à ADR 0273.
3. **Rename do módulo `Modules/Crm` para `Cliente` já**: adiada — blast radius alto (namespaces, rotas, testes) num módulo vivo; não necessária pra ligar a máquina.

## Refs

- [Relatório de alinhamento Cliente](../../requisitos/Cliente/audits/ALINHAMENTO-cliente-2026-06-22.md) · [SPEC Cliente](../../requisitos/Cliente/SPEC.md) · [DEPRECATION-PLAN pipeline](../../requisitos/Crm/DEPRECATION-PLAN-pipeline.md) · [desambiguação Cliente≠CRM](../../reference/crm-e-o-modulo-de-cliente.md)
- ADR 0273 (âncoras), 0179 (drawer), 0093 (Tier 0), 0105 (cliente como sinal)
