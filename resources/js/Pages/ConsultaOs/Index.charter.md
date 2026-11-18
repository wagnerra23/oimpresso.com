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
related_us: [US-CONSULTA-001]
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
- Exibe, por OS encontrada, **só**: nº da OS, marca, aparelho, modelo, nº de série, status com cor, previsão de entrega e atividades (data, ação, quem — primeiro nome + inicial do sobrenome, decisão [W] 2026-11-17 —, nota) — a mesma lista do `/repair-status`
- Estado "OS não encontrada" claro quando o número não bate
- Layout público limpo (sem AppShellV2, sem sidebar do ERP), tokens DS

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO expõe dados internos/financeiros da OS ao público (só status de acompanhamento): sem custo/preço, senha do aparelho, defeitos, checklist, notas internas, `business_id`
- ❌ NÃO expõe dados do cliente (nome, CPF/CNPJ, endereço, e-mail) — nem pra quem buscou pelo celular
- ❌ NÃO expõe o nome completo de funcionário: quem mexeu na OS sai como primeiro nome + inicial do sobrenome, sem o tratamento (decisão [W] 2026-11-17 · UC-COS-62)
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

- ❌ NÃO facilita enumerar OS: `throttle:30,1` por IP nas rotas do portal e no `/repair-status`, tipo de busca em lista fechada, número com formato restrito (até 20 caracteres) e resposta "não encontrada" sem pista (UC-COS-03, UC-COS-07). Resíduo aberto: o nº da OS é sequencial por empresa e a busca procura em todas as empresas, então quem tenta números em sequência dentro do limite do throttle acha OS de qualquer empresa — decisão [W] pendente (UC-COS-09, ver Pendências)
- ❌ NÃO dispara notificação ao cliente ao consultar
- ❌ NÃO grava dado de negócio nem altera a OS em GET. Grava só o log de auditoria redigido da busca (`ConsultaOsController::auditarConsulta`: número via `PiiRedactor`, IP truncado, tipo, resultado) e o contador do throttle (UC-COS-06)

> **Errata 2026-11-17** (o teste verde vence o charter — [proibicoes.md](../../../../memory/proibicoes.md) §Precedência): este bloco dizia (a) "NÃO indexa/expõe OS por enumeração (proteção de token público — ver testes de segurança do módulo)" — não existe token público, e o `PublicTokenSecurityTest` saiu com o mock em 2026-10-02; a defesa real é a descrita acima; e (b) "NÃO grava nada em GET" — toda busca grava a linha de auditoria e o contador do throttle. Status do charter não muda: aprovar é do Wagner.

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [x] O que é exposto = paridade com o `/repair-status` (D-PORTAL, 2026-10-02)
- [ ] Identificar a empresa do cliente — hoje um nº válido é procurado em todas as empresas (decisão [W], `_saida-04.md`)
- [ ] Captcha (resto da US-CONSULTA-001)
- [ ] Smoke visual mobile + 1280px (screenshot) — encontrada e não-encontrada
