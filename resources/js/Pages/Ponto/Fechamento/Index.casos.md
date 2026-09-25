---
id: resources-js-pages-ponto-fechamento-index-casos
casos: Fechamento da competência · /ponto/fechamento
irmaos: Index.charter.md (lei) · ADR 0413 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: fechar competência é irreversível por lei — o contrato defende o que NÃO pode acontecer.
owner: wagner
last_run: "2026-09-25"
---

# Casos de Uso & Aceite — Fechamento da competência

> **Âncora:** [ADR 0413](../../../../../memory/decisions/0413-ponto-fechamento-competencia-conformidade-relatorios-legais.md)
> (D1, D2, D4, W1, W3) + US-PONTO-015. Os UC derivam da ADR, não do código.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.
> Rodado no CT 100 em 2026-09-25 (clone isolado): **11 passed (35 assertions)** — ainda sem veredito
> de lane, por isso 🧪.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PTF-01 | A pré-checagem conta só o que é do próprio empregador | must `[T0]` | ADR 0093 + 0413 | `FechamentoContratoTest` | 🧪 |
| UC-PTF-02 | Com bloqueio grave aberto, fechar sem aceite é recusado e nada é gravado | must | ADR 0413 D2 | `FechamentoContratoTest` | 🧪 |
| UC-PTF-03 | Fechar aceitando registra quem, quando e quais bloqueios | must | ADR 0413 D2 + W3 | `FechamentoContratoTest` | 🧪 |
| UC-PTF-04 | Competência fechada não reabre | must | ADR 0413 D1 | `FechamentoContratoTest` · `CompetenciaAppendOnlyTest` | 🧪 |
| UC-PTF-05 | Fechar não altera marcação nem apuração | must | ADR 0413 + Portaria MTP 671/2021 | `FechamentoContratoTest` | 🧪 |
| UC-PTF-06 | Só quem tem `ponto.fechar` fecha | must | ADR 0413 D1 | `FechamentoContratoTest` | 🧪 |
| UC-PTF-07 | A tela mostra a competência, esconde o fechar de quem não pode e depois quem fechou | must | ADR 0413 D1 + D2 | `FechamentoContratoTest` | 🧪 |

## UC-PTF-01 · A pré-checagem conta só o que é do próprio empregador
- **Aceite:** Dado um dia em DIVERGENCIA e uma intercorrência pendente no tenant 98, e um dia em
  DIVERGENCIA no tenant 99 · Quando a pré-checagem do 98 roda · Então conta 1 divergência e 1 intercorrência.
- **Regressão que defende:** vazar bloqueio de outra empresa (bite-test: sem `business_id` → 2 ≠ 1).
- **Teste:** `FechamentoContratoTest` (título cita o id).
- **Status: 🧪** — rodado no CT 100 em 2026-09-25; sem veredito de lane ainda.

## UC-PTF-02 · Grave sem aceite é recusado
- **Aceite:** Dado dia em DIVERGENCIA · Quando fecha sem aceitar · Então recusa e `ponto_competencias` não ganha linha.
- **Teste:** `FechamentoContratoTest` (título cita o id).
- **Status: 🧪** — rodado no CT 100 em 2026-09-25; sem veredito de lane ainda.

## UC-PTF-03 · Aceite fica registrado
- **Aceite:** Dado dia em DIVERGENCIA · Quando fecha aceitando · Então a linha tem `fechada_por` do
  usuário e o bloqueio `divergencia` com n=1.
- **Teste:** `FechamentoContratoTest` (título cita o id).
- **Status: 🧪** — rodado no CT 100 em 2026-09-25; sem veredito de lane ainda.

## UC-PTF-04 · Não reabre
- **Aceite:** Dado competência fechada · Quando tenta fechar de novo, atualizar ou apagar a linha ·
  Então é recusado (serviço, model e trigger MySQL).
- **Teste:** `FechamentoContratoTest` (título cita o id).
- **Status: 🧪** — rodado no CT 100 em 2026-09-25; sem veredito de lane ainda.

## UC-PTF-05 · Não toca marcação nem apuração
- **Aceite:** Quando fecha · Então a contagem de `ponto_marcacoes` e dos dias em DIVERGENCIA fica igual.
- **Teste:** `FechamentoContratoTest` (título cita o id).
- **Status: 🧪** — rodado no CT 100 em 2026-09-25; sem veredito de lane ainda.

## UC-PTF-06 · Exige `ponto.fechar`
- **Aceite:** Dado usuário só com `ponto.access` · Quando `POST /ponto/fechamento` · Então 403 e nada
  gravado; com `ponto.fechar` · Então grava.
- **Teste:** `FechamentoContratoTest` (título cita o id).
- **Status: 🧪** — rodado no CT 100 em 2026-09-25; sem veredito de lane ainda.

## UC-PTF-07 · A tela mostra a situação e quem fechou
- **Aceite:** Dado usuário só com `ponto.access` · Quando abre `/ponto/fechamento?competencia=AAAA-MM` ·
  Então vê a competência aberta, `pode_fechar` falso e a pré-checagem (deferred) com os 5 itens; depois
  de fechada, vê o nome de quem fechou.
- **Teste:** `FechamentoContratoTest` (título cita o id).
- **Status: 🧪** — rodado no CT 100 em 2026-09-25; sem veredito de lane ainda.

**[BACKLOG]:**
- `[BACKLOG]` Chego na tela pelo menu (item e ghost "Fechamento") — provar pelo clique, em e2e com sessão.

## Trilha do tempo
- 2026-09-25 · [CL] criado na thread 04 (PR 5), UC derivados da ADR 0413. UC-PTF-07 no PR 6 (rota GET).
