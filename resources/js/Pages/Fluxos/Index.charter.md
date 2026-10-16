---
page: /fluxos
component: resources/js/Pages/Fluxos/Index.tsx
owner: wagner
status: draft
parent_module: Fluxos
related_prototype: prototipo-ui/cowork/Wagner/fluxos-page.jsx
related_runbook: memory/requisitos/Fluxos/RUNBOOK-fluxos-index.md
alcance:
  rota: /fluxos
  rota_nome: fluxos.index        # routes/web.php (núcleo, grupo web do ERP)
  permission: fluxos.ver         # permissão do núcleo (PermissionCatalog + views de papel), não de DataController
  menu_hook: app/Http/Middleware/AdminSidebarMenu.php   # item "Fluxos e regras" no grupo SISTEMA
  pacote: n/a — tela do núcleo, sem módulo nWidart nem pacote
tier: B
charter_version: 1
---

# Page Charter — Fluxos/Index (DRAFT)

> Sistema › Fluxos e regras. Decisão de produto: ADR 0435 "Fluxos por modelo (porte × atividade)"
> (PR #9488), [W] 2026-10-16: o fluxo do pedido segue o protótipo "Fluxos e regras".
> Contrato: [Index.casos.md](Index.casos.md). Sobe de `draft` → `live` com screenshot aprovado por [W].

## Mission

Mostrar ao dono ou gerente quais modelos de fluxo (etapas e regras do motor FSM) servem para o
porte e a atividade da empresa, e deixar trocar o modelo em uso sem mexer nos documentos em
andamento.

## Goals — Features (faz)

- PageHeader "Fluxos e regras" + 4 abas na ordem do protótipo: Modelos · Processos · Fluxos da
  empresa · Casos de uso (`?aba=`).
- Aba Modelos: perfil porte (Micro · Pequena · Média · Grande) × atividade principal; os 7 modelos
  do catálogo ordenados pelo encaixe, com selo Recomendado (4) · Serve (3) · Parcial (1–2) ·
  Outro perfil (0), a frase do motivo, a cadeia do processo de destaque e as contagens.
- Encaixe, selo e motivo vêm do servidor (`CatalogoModelos::encaixe()`); a tela não recalcula.
- Acesso: `fluxos.ver` para abrir; `fluxos.configurar` para trocar o modelo (PR 3).
- PT-BR em todo texto.

## Non-Goals — Features (NÃO faz)

- ❌ Guardar uma cópia do catálogo no front: os modelos vêm de `App\Domain\Fsm\Modelos\CatalogoModelos`.
- ❌ Mudar a etapa de documento em andamento ao trocar de modelo (ADR 0435).

## Lacunas declaradas (o protótipo mostra, o catálogo ainda não tem)

- Descrição do porte ("6 a 20 pessoas") e "Inspirado em <concorrentes>" no rodapé do card: o
  catálogo não traz esses campos. Ficam fora até o catálogo ganhar o dado (pedido à sessão do backend).
- Perfil porte × atividade da EMPRESA: neste PR o perfil é só escolha local da tela (padrão
  Pequena · Comunicação visual, como no protótipo).

## UX Targets

- Cabe em 1280px sem scroll horizontal; grade de cards `auto-fill minmax(340px,1fr)` do bundle.

## Refs

- Protótipo: `prototipo-ui/cowork/Wagner/fluxos-page.jsx` + `fluxos-page.css` (copiado inteiro em
  `resources/css/cowork-fluxos-bundle.css`) + `fluxos-data.jsx`.
- Constituição UI v2: UI-0013 · Sidebar SISTEMA: ADR 0180 / UI-0023.
