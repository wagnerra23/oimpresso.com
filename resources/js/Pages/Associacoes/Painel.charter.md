---
page: /associacoes
component: resources/js/Pages/Associacoes/Painel.tsx
owner: wagner
status: draft
parent_module: Associacoes
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
alcance:
  rota: /associacoes
  rota_nome: associacoes.painel        # name() da rota — é o que o guard procura
  permission: associacoes.access      # declarada em DataController::user_permissions
  menu_hook: Modules/Associacoes/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: associacoes_module              # superadmin_package
tier: B
charter_version: 1
---

# Page Charter — Associacoes/Painel (DRAFT · carimbado do PT-04)

> Nascida do Padrão de Tela **PT-04 Dashboard** via `criar-tela.mjs` (UI-0013 — herança
> de padrão, NÃO bespoke). Golden do arquétipo: [PT-04](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-04-Dashboard.md).
> Sobe de `draft` → `live` com screenshot aprovado por Wagner.

## Mission

Dar à diretoria da Uniafibro-SC (e ao representante de cada associação) a foto da rede em uma
tela: quantas associações, cidades e associadas ativas, e o que falta de documentação
(ADR 0451 onda 5 · US-ASSOC-004).

## Goals — Features (faz)

- Associações por situação (ativa · em adesão · desligada), cidades e associadas ativas (total e por associação)
- Pendências de documentação: sem estatuto, sem ata da diretoria, certidão vencida, diretoria
  que mudou sem ata nova (no prazo × atrasada após `associacoes.prazo_ata_diretoria_dias`, D7)
- Alcance pela regra D4: com `associacoes.rede` vê a rede; sem ela, só as associações em que é representante
- Toda prop que consulta é `Inertia::defer`
- PT-BR em todo label/placeholder/mensagem

## Non-Goals — Features (NÃO faz)

- ❌ Mostrar dado pessoal de associada (nome, telefone, contato) — só números e nomes de associação (ADR 0451 D5)
- ❌ Bloquear a associação com ata atrasada — o atraso avisa, não bloqueia (ADR 0451 D7)
- ❌ Cadastrar ou editar associação/documento — isso é a tela da onda 3

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE)

## Refs

- Casos: [Painel.casos.md](Painel.casos.md) · RUNBOOK: [RUNBOOK-painel.md](../../../../memory/requisitos/Associacoes/RUNBOOK-painel.md)
- ADR 0451 (D4, D5, D7) · SPEC US-ASSOC-004
- Padrão de Tela: PT-04 Dashboard (KpiGrid + KpiCard)
- Constituição UI v2: UI-0013
