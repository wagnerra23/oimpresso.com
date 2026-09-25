---
id: resources-js-pages-ponto-fechamento-index-charter
page: /ponto/fechamento
component: resources/js/Pages/Ponto/Fechamento/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/ponto-fechamento.jsx
runbook: memory/requisitos/Ponto/RUNBOOK-fechamento.md
owner: wagner
status: draft
last_validated: "2026-09-25"
parent_module: Ponto
related_us: [US-PONTO-015]
related_adrs: [413, 93, 358, 182]
alcance:
  rota: /ponto/fechamento
  rota_nome: ponto.fechamento
  permission: ponto.access
  menu_hook: Modules/Ponto/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: ponto_module
tier: B
charter_version: 1
---

# Page Charter — /ponto/fechamento (DRAFT)

> Tela nova da thread 04 do playbook Ponto. A lei é a
> [ADR 0413](../../../../../memory/decisions/0413-ponto-fechamento-competencia-conformidade-relatorios-legais.md)
> (decidida por [W] em 2026-09-14 e 2026-09-24). Plano: [RUNBOOK-fechamento](../../../../../memory/requisitos/Ponto/RUNBOOK-fechamento.md).
> Casos: [Index.casos.md](Index.casos.md).
>
> Backend: `FechamentoController@index` (ver, `ponto.access`) e `@store` (fechar, **`ponto.fechar`**) →
> `FechamentoService`.

## Mission
Fechar a competência (mês) do ponto: mostrar o que ainda bloqueia e registrar quem fechou, quando e
quais bloqueios aceitou — sem alterar marcação, apuração ou banco de horas.

## Goals — Features (faz)
- Escolher a competência (mês) e ver a situação: **Aberta** ou **Fechada** (com autor e data).
- Pré-checagem com grau por item: graves (dia em DIVERGENCIA · intercorrência não decidida ·
  violação apurada de Art. 66 / Art. 71 CLT) e de conferência (sem PIS · importação em andamento),
  cada um com atalho para a tela que resolve.
- **Fechar competência** quando não há grave; **Fechar aceitando os bloqueios** com confirmação,
  que ficam registrados na linha com o nome e a data.
- Depois de fechada, o único caminho oferecido é **Ir para Relatórios** (AFD/AEJ).

## Non-Goals — Features (NÃO faz) — todos da ADR 0413
- ❌ **Reabrir** competência (D1). Correção depois de fechada: anulação da marcação com trilha
  (`Marcacao::anular()`, Portaria MTP 671/2021). Pest GUARD: `UC-PTF-04`.
- ❌ **Gerar AFD/AEJ** ou condicionar a geração ao fechamento (D4 + W3).
- ❌ **Assinar digitalmente** o ato (D2) — registra autoria administrativa, sem validade de assinatura ICP.
- ❌ **Fechar com `ponto.access` só** (D1) — exige `ponto.fechar`. Pest GUARD: `UC-PTF-06`.
- ❌ **Alterar** marcação, apuração ou banco de horas ao fechar. Pest GUARD: `UC-PTF-05`.

## UX targets
- Cabe em 1280px sem scroll horizontal; AppShellV2 + PageHeader canon (ADR 0182) + `PontoSubNav`.

## Automation Anti-hooks
- ❌ Nenhum job fecha competência sozinho: fechar é ato humano com autor (D2).

## Refs
- Protótipo: `prototipo-ui/cowork/Wagner/ponto-fechamento.jsx` (`Fechamento`). Diferenças conscientes
  no RUNBOOK §4.
