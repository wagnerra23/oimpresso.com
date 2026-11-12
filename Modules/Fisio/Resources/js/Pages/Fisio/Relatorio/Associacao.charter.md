---
id: modules-fisio-pages-fisio-relatorio-associacao-charter
page: /fisio/relatorio-associacao
component: Modules/Fisio/Resources/js/Pages/Fisio/Relatorio/Associacao.tsx
related_prototype: "n/a (o protótipo do Fisio não desenha o relatório agregado da associação; herda PT-04 Dashboard)"
owner: wagner
status: draft
last_validated: "2026-11-11"
parent_module: Fisio
related_adrs: [93, 358, 437]
alcance:
  rota: /fisio/relatorio-associacao
  rota_nome: fisio.relatorio-associacao.index
  permission: fisio.relatorio
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/relatorio-associacao (DRAFT · relatório agregado da associação)

> Nasce na US-FISIO-062 (pedido [W] 2026-11-11). Padrão de Tela PT-04 Dashboard. Casos: [`Associacao.casos.md`](Associacao.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Dar à associação, por período, os números que editais, emendas e convênios pedem: quantas pessoas foram atendidas, quem são (faixa etária, sexo, município, renda, situação de trabalho), quanto vieram, quanto fizeram os exercícios em casa e se melhoraram — sem identificar ninguém.

## Goals — Features (faz)

- Período escolhido (padrão: do 1º de janeiro até hoje) — UC-FRA-01.
- Pessoas atendidas (cada uma contada uma vez), atendimentos com presença, taxa de presença e adesão média aos exercícios em casa — UC-FRA-01.
- Perfil por faixa etária, sexo, município, renda e situação de trabalho, a partir dos dados sociais opcionais (US-FISIO-061) — UC-FRA-01.
- Média da primeira e da última aplicação de FIQR, EVA, PGIC e sentar e levantar em 30 s, e a diferença — UC-FRA-01.
- Grupo com menos de 5 pessoas sai "<5" e sem média; período com menos de 5 atendidos mostra só o total; municípios pequenos somam em "Outros municípios" — UC-FRA-02.
- PDF do mesmo período, com uma linha no `activity_log` sem dado de paciente — UC-FRA-05.

## Non-Goals — Features (NÃO faz)

- ❌ Linha por paciente, lista de nomes, exportação em planilha individual (LGPD art. 11).
- ❌ Interpretar a melhora ou afirmar efeito do tratamento: mostra o número e o sentido da escala (ADR 0437 §5).

## Refs

- ADR 0437 §7 (LGPD) · `memory/requisitos/Fisio/SPEC.md` US-FISIO-062 · UI-0013 PT-04
