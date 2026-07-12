---
page: /ponto/colaboradores/{id}/editar
component: resources/js/Pages/Ponto/Colaboradores/Edit.tsx
related_prototype: n/a (herda PT-02 Formulário; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-004]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/colaboradores/{id}/editar (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/ColaboradorController@edit` / `@update` (rotas `ponto.colaboradores.edit` / `.update`, permissão `ponto.access`). Configura os campos de ponto de um colaborador (nome/email vêm do HRM UltimatePOS).

---

## Mission
O gestor configura os parâmetros de ponto de um colaborador já cadastrado no HRM: matrícula, CPF/PIS, datas de admissão/desligamento, escala vigente e flags de controle de ponto e uso de banco de horas. Identidade (nome/email) é herdada do core e não é editada aqui.

---

## Goals — Features (faz)
- Formulário de edição dos campos de ponto (`PUT /ponto/colaboradores/{id}`).
- Campos: matrícula, CPF, PIS, admissão (obrigatória), desligamento, escala (select).
- Switches: "controla ponto" (participa da apuração CLT) e "usa banco de horas".
- Validação de campos com feedback inline; toast de sucesso/erro.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita nome/email — vêm do HRM (UltimatePOS core); mudar lá.
- ❌ Não cria colaborador do zero — cadastro é no HRM; esta tela só configura ponto.
- ❌ Não edita colaborador de outro business — escopado por `business_id` (Tier 0 multi-tenant).
- ❌ Não gerencia turnos da escala — isso é na tela de Escalas.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- Submit via `useForm.put` com validação server-side (`desligamento > admissao`, `escala exists`).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não salva sozinho — mudança só persiste no submit explícito.
- ❌ Não desliga/reativa apuração retroativa ao mudar `controla_ponto` — efeito é prospectivo (confirmar com backend).
- ❌ Não expõe CPF/PIS em log — PII de colaborador (LGPD); tratar como sensível.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar efeito retroativo (ou não) de alternar `controla_ponto`/`usa_banco_horas`
