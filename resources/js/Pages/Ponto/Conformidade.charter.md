---
page: /ponto/conformidade
component: resources/js/Pages/Ponto/Conformidade.tsx
owner: wagner
status: draft
parent_module: Ponto
related_prototype: prototipo-ui/cowork/Wagner/ponto-fechamento.jsx
related_us: [US-PONTO-016]
related_adrs: [413, 93, 104]
alcance:
  rota: /ponto/conformidade
  rota_nome: ponto.conformidade.index        # name() da rota — é o que o guard procura
  permission: ponto.access      # middleware do grupo de rotas do Ponto
  menu_hook: Modules/Ponto/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: ponto_module              # superadmin_package
tier: A
charter_version: 1
---

# Page Charter — Ponto/Conformidade (Conformidade CLT · DRAFT)

> Carimbado do **PT-04 Dashboard** por `criar-tela.mjs`. Mission/Goals/Non-Goals vêm do charter do
> protótipo (`prototipo-ui/cowork/Wagner/resources/js/Pages/Ponto/Conformidade.charter.md`, F1 de
> 2026-08-20) e da [ADR 0413](../../../../memory/decisions/0413-ponto-fechamento-competencia-conformidade-relatorios-legais.md) D0.
> Casos irmãos: [`Conformidade.casos.md`](Conformidade.casos.md). Runbook:
> [`RUNBOOK-conformidade.md`](../../../../memory/requisitos/Ponto/RUNBOOK-conformidade.md).
> **Persona:** Wagner (risco) e Eliana (correção), antes de fechar a competência.

## Mission

Transformar a lei em lista de casos: seis verificações da competência, cada apontamento com o
artigo, o apurado, o limite e o atalho pro Espelho.

## Goals — Features (faz)

- Jornada sem fechamento (CLT Art. 74 §2º) — marcação ímpar ou falta sem intercorrência
- Interjornada abaixo de 11h (CLT Art. 66) e intrajornada abaixo de 60 min (CLT Art. 71)
- HE acima do limite diário (CLT Art. 59) e NSR fora de sequência (Portaria MTP 671/2021 Anexo I)
- Colaborador ativo sem PIS (conferência)
- KPI por verificação clicável + tabela caso a caso + seletor de competência
- PT-BR em todo label

## Non-Goals — Features (NÃO faz)

- ❌ **NÃO corrige** nada — leva pra onde se corrige (Espelho / Intercorrências). ADR 0413 D0: somente leitura
- ❌ **NÃO reimplementa apuração** — lê `ponto_apuracao_dia`; dia sem apuração não aparece
- ❌ **NÃO mostra zero** onde não mediu — verificação não medida sai "—" (hoje: NSR)
- ❌ **NÃO chama de apontamento** o que não tem artigo — sem artigo é conferência (hoje: sem PIS)

## Anti-hooks (NÃO faz automaticamente)

- ❌ Nenhuma escrita a partir de `/ponto/conformidade` (ADR 0413 §"Como se reconhece violação")
- ❌ Não dispara reapuração ao abrir nem ao trocar a competência

## UX Targets

- Cabe em 1280px; ordem das seções = protótipo: nota → 6 KPIs → tabela (6 colunas)
- Painel deferido (`Inertia::defer`) — o cabeçalho e o seletor de mês renderizam antes

## Refs

- Padrão de Tela: PT-04 Dashboard (KpiGrid + KpiCard) · Constituição UI v2: UI-0013
