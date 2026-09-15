---
id: resources-js-pages-ponto-colaboradores-index-charter
page: /ponto/colaboradores
component: resources/js/Pages/Ponto/Colaboradores/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/ponto-telas.jsx
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-004]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/colaboradores (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/ColaboradorController@index` (rota `ponto.colaboradores.index`, permissão `ponto.access`). Lista de colaboradores com configuração de ponto (nome/email do HRM UltimatePOS).

---

## Mission
O gestor localiza colaboradores para configurar seus parâmetros de ponto. A tela lista quem está cadastrado no HRM com matrícula, CPF, escala e flags de ponto/banco de horas, com busca por matrícula/nome/CPF e atalho para editar a configuração de cada um. A **exibição** do documento é redigida (minimização, LGPD); a **busca** segue aceitando o CPF inteiro digitado — são coisas diferentes, e confundi-las quebraria `UC-COLIDX-01`.

---

## Goals — Features (faz)
- Lista paginada (25/pág) de colaboradores.
- Busca com debounce (350ms) por matrícula, nome ou CPF (partial reload).
- Colunas: matrícula, nome/email, **CPF / PIS redigidos** (3 últimos dígitos — `D-COLAB-CPF`,
  [W] 2026-09-14; inteiros só na tela Edit), escala, flags "Ponto" e "BH". PIS ausente aparece como
  **"PIS não cadastrado"**, não como vazio: o AFD da Portaria 671/2021 é chaveado por PIS.
- Atalho "Config" pro editar (`/ponto/colaboradores/{id}/editar`).
- Empty states distintos para "sem cadastro" e "busca sem resultado".

---

## Non-Goals — Features (NÃO faz)
- ❌ Não cadastra colaborador — cadastro é no HRM (UltimatePOS core).
- ❌ Não edita inline — edição é na tela Edit.
- ❌ Não lista colaborador de outro business — escopado por `business_id` (Tier 0 multi-tenant).
- ❌ Não exporta CPF/PIS em massa — PII de colaborador (LGPD).
- ❌ Não exibe CPF/PIS inteiros na lista — redigidos nos 3 últimos dígitos; inteiros só na tela Edit (`D-COLAB-CPF`, [W] 2026-09-14; defendido por `UC-COLIDX-03`).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- Busca dispara `router.get` com `only: ['colaboradores','search']` (partial reload, `replace`).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling.
- ❌ Não muta dados em GET (só leitura/busca).
- ❌ Não sincroniza colaboradores do HRM sozinha — a lista reflete o que já existe no core.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [x] Confirmar mascaramento de CPF na coluna (LGPD) — **[W] 2026-09-14 respondeu: mascarar, "é
      liberado ser igual ao protótipo"**. A forma vem do `D-COLAB-CPF` em
      [`ponto-telas.jsx`](../../../../../prototipo-ui/cowork/Wagner/ponto-telas.jsx): a lista mostra
      só os **3 últimos dígitos** de CPF e de PIS; inteiros **apenas no form de edição**, porque
      lista é tela de varredura e minimização de dado é o default. Supersede a posição de
      **2026-08-21** (*"pode deixar os dados sim é um ERP"*), que segue verdadeira como fato daquela
      data e sobre o **espelho** — não sobre esta coluna. Cadeia FORMA: protótipo soberano
      ([ADR UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)).
      ⚠️ Ainda **não aplicado** no `.tsx` (hoje `Index.tsx` renderiza o CPF inteiro): o produto só
      muda pelo fluxo de aplicação com `map.json` por tela, e `maskCPF` de
      [`Lib/br-mask.ts`](../../../../../resources/js/Lib/br-mask.ts) **formata, não redige** — medido,
      o front não tem helper de redação hoje.
