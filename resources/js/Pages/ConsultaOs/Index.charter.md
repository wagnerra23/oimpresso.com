---
id: resources-js-pages-consulta-os-index-charter
page: /consulta-os
component: resources/js/Pages/ConsultaOs/Index.tsx
related_prototype: n/a (página pública de acompanhamento — bespoke; não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-10-02"
parent_module: ConsultaOs
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /consulta-os (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ConsultaOs/Http/Controllers/ConsultaOsController@index` (rota **pública** `consulta-os.index`) + `@buscar` (`consulta-os.buscar`). É uma página pública — NÃO usa AppShellV2/Sidebar do ERP; layout limpo pro cliente final.
>
> **2026-10-02 — decisão [W] "Ligar o ConsultaOs ao Repair" (US-CONSULTA-001):** a busca lê as folhas de OS reais do `Modules/Repair` e esta é a tela do portal do cliente do Repair — o antigo `/repair-status` redireciona pra cá. Registro: `prototipo-ui/cowork/Wagner/cowork-inbox/repair/playbook/_DECISOES-W-2026-10-02.md` (D-PORTAL).

---

## Mission

Página pública onde o cliente final informa o nº da OS, o nº da venda ou o celular pra acompanhar o status do seu reparo, sem login no ERP. Layout enxuto e centralizado (sem shell administrativo). É a porta de auto-atendimento — reduz ligação/WhatsApp perguntando "e a minha OS?".

---

## Goals — Features (faz)

- Busca por nº da OS, nº da venda ou celular (celular só se `repair.enable_repair_check_using_mobile_num` ligar), com nº de série opcional que só estreita (`consulta-os.buscar`)
- Exibe, por OS encontrada, **só**: nº da OS, marca, aparelho, modelo, nº de série, status com cor, previsão de entrega e atividades (data, ação, quem, nota) — a mesma lista do `/repair-status`
- Estado "OS não encontrada" claro quando o número não bate
- Layout público limpo (sem AppShellV2, sem sidebar do ERP), tokens DS

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO expõe dados internos/financeiros da OS ao público (só status de acompanhamento): sem custo/preço, senha do aparelho, defeitos, checklist, notas internas, `business_id`
- ❌ NÃO expõe dados do cliente (nome, CPF/CNPJ, endereço, e-mail) — nem pra quem buscou pelo celular
- ❌ NÃO exige login — é rota pública intencional
- ❌ NÃO lista todas as OS (só as que casam com o critério informado; no máximo 20)
- ❌ NÃO permite editar/cancelar a OS (read-only pro cliente)
- ❌ NÃO usa o shell administrativo (AppShellV2/Sidebar)

---

## UX targets

- Carrega rápido em conexão móvel (página pública, cliente final)
- Legível em mobile e 1280px
- Mensagem de "não encontrada" sem jargão técnico

---

## Automation hooks (faz)

- Busca consulta o status via `consulta-os.buscar` sob demanda (ação do usuário)

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO indexa/expõe OS por enumeração (proteção de token público — ver testes de segurança do módulo)
- ❌ NÃO dispara notificação ao cliente ao consultar
- ❌ NÃO grava nada em GET

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [x] O que é exposto = paridade com o `/repair-status` (D-PORTAL, 2026-10-02)
- [ ] Identificar a empresa do cliente — hoje um nº válido é procurado em todas as empresas (decisão [W], `_saida-04.md`)
- [ ] Captcha (resto da US-CONSULTA-001)
- [ ] Smoke visual mobile + 1280px (screenshot) — encontrada e não-encontrada
