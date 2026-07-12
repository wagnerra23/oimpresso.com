---
page: /nfe-brasil/tributacao/regras/create
component: resources/js/Pages/NfeBrasil/Tributacao/RegraForm.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: NfeBrasil
related_us: [US-NFE-010]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /nfe-brasil/tributacao/regras/create (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/NfeBrasil/Http/Controllers/TributacaoController@create|store|edit|update` (rotas `nfe-brasil.tributacao.regras.*`, permissão `nfe.tributacao.manage` validada no FormRequest). Form dual create/edit de uma regra tributária NCM (`nfe_fiscal_rules`), Nível 2/3 do cascade ARQ-0006. US-NFE-010 fase 2.

---

## Mission
Permitir ao responsável fiscal cadastrar ou ajustar uma regra tributária por NCM: identificação (NCM, UF origem, UF destino, CFOP, regime CSOSN/CST) e alíquotas (ICMS, PIS, COFINS, IPI + MVA/FCP opcionais). É onde se afina a tributação que alimenta a emissão de NF-e.

---

## Goals — Features (faz)
- Form único `useForm` que serve create (`regra=null`) e edit (`regra` populada), decidindo método `post`/`put` e URL pela presença de `regra.id`.
- Máscaras client-side: NCM (8 dígitos), CFOP (4 dígitos), código tributário (3 dígitos), tudo numérico.
- Seletor de regime (Simples=CSOSN / Normal=CST) que troca o campo exibido e limpa o campo não usado no submit (exclusividade).
- UF origem obrigatória; UF destino com opção "Todas (Nível 3)" que envia vazio (cascade Nível 2 vs 3).
- Alíquotas em decimal (0.18 = 18%) via `FieldDecimal` (number, step 0.0001, 0–1), MVA/FCP opcionais.
- Toasts de sucesso/erro; erros de campo do FormRequest exibidos inline; cancelar/voltar pra `/nfe-brasil/tributacao`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não faz import em massa (isso é o `ImportCsv`).
- ❌ Não lista nem exclui regras — só cria/edita uma (listagem/destroy ficam no índice).
- ❌ Não calcula automaticamente alíquotas a partir do NCM (entrada é manual/curada).
- ❌ Não grava em outro tenant — `store` injeta `business_id` da sessão e `edit`/`update` escopam por `business_id` (Tier 0, ADR 0093); `firstOrFail` bloqueia acesso a regra de outro negócio.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; container `max-w-3xl` ; layout de form em cards.

---

## Automation hooks (faz)
- `store`/`update` gravam `activity('nfe.tributacao')` (log de auditoria com business_id + NCM/UF) via Spatie activitylog.
- Limpeza automática do campo tributário não usado (csosn/cst) no submit conforme o regime selecionado.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não salva sem submit explícito do usuário (sem auto-save de rascunho).
- ❌ Não deriva/preenche alíquotas por lookup externo automático.
- ❌ Não aplica a regra retroativamente a notas já emitidas — só afeta emissões futuras (inferência pendente de Wagner).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — validar create e edit, troca de regime CSOSN↔CST
- [ ] Confirmar validação de unicidade da chave (NCM + UF origem + UF destino) no FormRequest
