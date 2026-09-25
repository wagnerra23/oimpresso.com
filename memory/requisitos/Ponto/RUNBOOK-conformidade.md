---
owner: W
last_validated: "2026-09-25"
slug: ponto-runbook-conformidade
title: "Ponto — Runbook do Painel de Conformidade CLT (/ponto/conformidade)"
type: runbook
module: Ponto
tela: Ponto/Conformidade
status: ativo
date: 2026-09-25
related_adrs:
  - 0413-ponto-fechamento-competencia-conformidade-relatorios-legais
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
---

# RUNBOOK — Painel de Conformidade CLT (`Ponto/Conformidade`)

> Tela **nova** — não existe Blade legada (o protótipo diz: *"não existe no Blade, que só tem telas
> soltas"*). Nasce pela thread 05 do playbook Ponto, destravada pela
> [ADR 0413](../../decisions/0413-ponto-fechamento-competencia-conformidade-relatorios-legais.md) **D0**.
> Tela **flat** (`Pages/Ponto/Conformidade.tsx`), igual ao `component:` do charter do protótipo.

## 1. O que é

Por competência (`?mes=AAAA-MM`), lista as violações que a **apuração já detectou**, uma verificação
por KPI, e caso a caso numa tabela com colaborador, dia, apurado, limite e atalho pro Espelho.

| Rota | Backend | Renderiza |
|---|---|---|
| `GET /ponto/conformidade` | `ConformidadeController@index` → `ConformidadeService::competencia()` | `Inertia::render('Ponto/Conformidade')` |

Props: `mes` (eager, estado de filtro) · `painel` (**deferida** — agrega a competência inteira).

## 2. As 6 verificações e de onde vêm

| id | título | lei citada | fonte (nunca recalculada aqui) |
|---|---|---|---|
| `jornada_aberta` | Jornada sem fechamento | CLT Art. 74 §2º | `qtd_marcacoes` ímpar · `divergencias[].chave = falta` |
| `interjornada` | Interjornada abaixo do mínimo | CLT Art. 66 | `interjornada_violacao_minutos` (RN-004) |
| `intrajornada` | Intrajornada abaixo do mínimo | CLT Art. 71 | `intrajornada_violacao_minutos` (RN-003) |
| `he` | HE acima do limite diário | CLT Art. 59 | `divergencias[].chave = he_acima_limite` (RN-006) |
| `nsr` | NSR fora de sequência | Portaria MTP 671/2021 Anexo I | **não medido** — a apuração não expõe a sequência |
| `sem_pis` | Colaborador ativo sem PIS | — (conferência, decisão [W] aberta) | `ponto_colaborador_config.pis` |

Limites: `config('pontowr2.clt.*')` — os mesmos que o `ApuracaoService` usa.

## 3. Regras duras

- **Somente leitura** (ADR 0413 D0). Nenhum POST/PUT/DELETE nesta rota; correção vai pro Espelho ou Intercorrências.
- **Não reimplementar apuração.** Dia sem apuração rodada não aparece — o painel não inventa.
- `business_id` explícito nas duas queries do service, além do global scope (defesa dupla, provada por bite-test).
- Sem artigo, não é apontamento: `sem_pis` renderiza como **conferência**.

## 4. Testes

`Modules/Ponto/Tests/Feature/ConformidadeContratoTest.php` (lane `ponto-pest.yml`) — UC-CONF-01..08.
Rodar só no CT 100.

## 5. Pendências

- NSR: extrair `PontoHealthCommand::checkNsrSequencial` para o `NsrService` e medir por competência.
- UC-CONF-03 (contagem casa com o Fechamento) espera a thread 04.
- Base legal do "sem PIS": decisão [W].
