---
id: requisitos-cliente-briefing
module: Cliente
status: producao
updated_at: "2026-09-06"
distilled_at: "2026-09-06"
distilled_by: jana:distill-module-truth
---

# BRIEFING — Cliente (verdade destilada)

## Estado atual
Cadastro de clientes (pessoas físicas e jurídicas) com validações brasileiras. Cliente ≠ CRM (decisão Wagner 2026-06-22, recibo em `audits/ALINHAMENTO-cliente-2026-06-22.md`): não existe módulo nWidart próprio de Cliente (nenhuma pasta com esse nome em `Modules/`); o código vive em `Modules/Crm/` (parte A, cadastro) e nas Pages `resources/js/Pages/Cliente/` (Index, Create, Edit, Show, Import, Ledger, Map — `Show.tsx` é legado dual-render). Em produção para biz=4 (ROTA LIVRE) e demais tenants; a superfície viva é o drawer de 760px do Index (dados em `App\Contact`/`App\ContactAddress` → `contacts`/`contact_addresses`). O SPEC declara as US do cadastro; a contagem viva de implementadas sai de `node scripts/governance/requisitos-status.mjs Cliente` (`_STATUS-GENERATED.md`) — não se copia aqui. Contrato de tela: `SDD-cadastro-cliente-v1.0.md` (ADR 0351) + `casos.md` por tela.

## Capacidades
- Cadastro PF/PJ com validação de CPF/CNPJ (US-CRM-072/076).
- Lookup de CEP (ViaCEP) e CNPJ (BrasilAPI) via `BrLookupService`.
- Drawer de 760px com abas e autosave (ADR 0179).
- Auditoria LGPD por cliente (o cadastro não dispara hook WhatsApp/e-mail — Non-Goal LGPD; a única menção a canal no `ClienteAutosaveController` é o enum `CANAIS` de preferência de contato; export CSV que nunca leva `tax_number` em claro; o activity log exclui PII — `Contact::logOnly` sem `tax_number_1`, guard `ContactPiiLogsActivityTest`; o export PDF do ledger, ao contrário, sai com PII completa). `cpf_cnpj`/`ie_rg` são formatados, não redigidos — censura real é decisão pendente de [W]; só `bank_account_number` tem redação real (`UC-CSHW-03`).
- Multi-tenant Tier 0 por `where('business_id')` manual (cross-tenant → 404, ADR 0093); `App\Contact` não usa global scope — US-CRM-080 aberta.
- Múltiplos endereços por contato (`ContactAddress`, US-CRM-078 PR1/PR2, com Pest cross-tenant) e aba IA com score de risco determinístico no mesmo drawer.
- Mapa de clientes em OpenStreetMap (US-CRM-091, Onda 3 da paridade — o SPEC ainda marca `_pendente_`; SPEC atrás do código).

## Gaps
- Dropdown de endereço do cliente na venda (US-CRM-078 PR3): `Sells/Create.tsx` ainda usa `shipping_address` como texto livre.
- Migrar RUNBOOKs, UI-CATALOG e ARCHITECTURE de `memory/requisitos/Crm/` para `Cliente/`.
- Backlog secundário do SPEC (§3-bis) com âncoras `_pendente_`.
- Fidelidade visual prod×protótipo: a última medição (2026-08-26) não cobriu listagem, Import e Map — donos datados do veredito: `Cliente/clientes-gap.md`, `Crm/clientes-gap.md` (#6294) e `prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md`. Há baseline de pixel para `Cliente/Import` (#5937) e `Cliente/Map` (#6303), mas baseline não é fidelidade ao protótipo (âncora do Map = `prototipo-ui/cowork/cliente-mapa.jsx`, `Show` declara `n/a` e herda PT-03; resolver por `node prototipo-ui/ancora.mjs Cliente/<Tela>`; ondas em `PARIDADE-area-cliente-diagnostico-e-ondas.md`). O veredito "tela viva à frente" de 2026-08-26 vale só pro drawer 760, não pra listagem, Import e Map.

## Última mudança
2026-09-06 (#6910) — `data-contract` inerte no Index e o sha do protótipo atualizado; antes, sai a copy visível "Copiloto" da aba IA e do rail (#6344, 2026-08-27). Mudança de capacidade mais recente: o Mapa sai do Google e vai pro OSM (#6303, 2026-08-26, US-CRM-091 Onda 3, 1ª mudança visível ao cliente); antes, Import com drag-and-drop (#5937, 2026-08-20) e a âncora do protótipo do `Map` religada (#5938, 2026-08-18).

## Proveniência (destilado de)

- audit `requisitos/Cliente/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- audit `requisitos/Cliente/CAPTERRA-INVENTARIO.md` — CAPTERRA-INVENTARIO.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r2.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r2.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r4.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r4.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r6.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r6.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897-r7.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897-r7.md
- session `sessions/2026-09-06-refutacao-gt-g5-lote-6897.md` (2026-09-06) — 2026-09-06-refutacao-gt-g5-lote-6897.md
- session `sessions/2026-08-14-censo-redacao-brl-em-codigo.md` (2026-08-14) — 2026-08-14-censo-redacao-brl-em-codigo.md
- session `sessions/2026-08-11-consulta-clientes-v3-dv-medido-e-smoke.md` (2026-08-11) — 2026-08-11-consulta-clientes-v3-dv-medido-e-smoke.md
- handoff `handoffs/2026-08-11-1811-consulta-clientes-v3-e-o-cnpj-que-era-real.md` (2026-08-11) — 2026-08-11-1811-consulta-clientes-v3-e-o-cnpj-que-era-real.md
