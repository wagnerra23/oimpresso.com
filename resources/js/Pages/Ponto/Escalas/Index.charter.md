---
id: resources-js-pages-ponto-escalas-index-charter
page: /ponto/escalas
component: resources/js/Pages/Ponto/Escalas/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/ponto-telas.jsx
owner: wagner
status: draft
last_validated: "2026-09-28"
parent_module: Ponto
related_us: [US-PONT-005]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/escalas (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/EscalaController@index` (rota `ponto.escalas.index`, permissão `ponto.access`). Lista de escalas (padrões de jornada) do business.

---

## Mission
O gestor vê todas as escalas (padrões de jornada) cadastradas — nome, código, tipo, cargas diária/semanal, flag de banco de horas e quantidade de turnos — e navega para criar uma nova ou editar existente. É o índice do CRUD de escalas.

---

## Goals — Features (faz)
- Lista paginada (20/pág, no servidor — W11, ADR 0418) de escalas com contagem de turnos.
- Forma do protótipo (`ponto-telas.jsx`, símbolo `Escalas`; eixo FORMA ⇒ protótipo soberano,
  [ADR UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)),
  em três seções abaixo do cabeçalho: **barra** (nota de carga "480 = 8h, 2.640 = 44h" + "Nova
  escala") · **lista** (Card "Escalas cadastradas", contagem no título) · **nota** (turnos são
  leitura aqui). Seletores medidos em `governance/design/targets/ponto--escalas--index.secoes.json`.
- Colunas, nesta ordem: código, nome (sub-linha: horário do 1º turno, ou "sem turno configurado" —
  `UC-ESCIDX-06`), tipo (rótulo do enum), carga diária, carga semanal, turnos, banco de horas, ação.
  Supersede a ordem anterior deste item (nome primeiro), que era a do vivo antes da passada de FORMA.
- Atalho "Nova escala" (`/ponto/escalas/create`) na barra e "Editar" por linha
  (`/ponto/escalas/{id}/edit`) — rotas próprias (D-PONTO-DETALHE), não o form na mesma tela do protótipo.
- Ação "Remover" por linha, **só sem vínculo**; com vínculo, o lugar dela diz "Em uso por N colaborador(es)".
- Empty state com CTA de criar a primeira escala.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita inline — edição é na tela Form.
- ❌ Não gerencia turnos aqui — só mostra a contagem.
- ❌ Não lista escala de outro business — escopado por `business_id` (Tier 0 multi-tenant).
- ❌ Não exclui escala **em uso** — `D-ESC-DESTROY` ([W] 2026-09-14): remover entra na UI, mas
  **indisponível com vínculo**, com o motivo escrito ao lado. Supersede a redação anterior deste
  item (*"a UI não expõe — confirmar com Wagner"*), que era **pergunta aberta** e foi respondida.
  A trava vive no servidor (`EscalaController@destroy` → `Escala::podeSerRemovida`); o botão é
  conveniência. Defendido por `UC-ESCIDX-03` (UI) e `UC-ESCIDX-04` (servidor).
  A confirmação de remover usa o diálogo do DS, nunca `window.confirm` (R3 da ata: *"`window.confirm`
  não era pergunta"*, ATA-DECISOES-2026-09-14 linha 24). Região no map: `remover-escala`
  (`memory/requisitos/Ponto/escalas-index.map.json`).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- Paginação usa partial reload (`only: ['escalas']`).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz polling.
- ❌ Não muta dados em GET (só leitura/navegação).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [x] Definir se exclusão de escala (destroy) entra na UI e com quais guardas — DECIDIDO por [W]
      2026-09-14 (`D-ESC-DESTROY`, ATA-DECISOES-2026-09-14 linha 24): entra, indisponível com vínculo e
      com o motivo escrito. Ver o Non-Goal acima.
