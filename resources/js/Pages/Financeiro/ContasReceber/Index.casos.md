---
casos: Contas a Receber · /financeiro/contas-receber
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — Contas a Receber

> Tela Tier-0 (dinheiro a entrar). Charter retroativo criado na **Onda de correção Financeiro**
> ([ADR 0320], régua por tela). Persona: **Eliana [E]**.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Régua estendida (Onda 0b):** hoje esta tela tem **0 UC com teste que o defenda** — a
> cobertura de comportamento é o **débito** que o scorecard expõe ao lado da nota de UX (70).
> Os casos abaixo estão no **backlog sem id** de propósito: pela G-2 ([ADR 0264]), um `UC-*`
> declarado sem teste é órfão e **quebra o `casos-gate`**. Cada um só vira `UC-CR-NN` **no mesmo
> PR** que trouxer o teste (Pest/MySQL real no CT100 — [ADR 0062]). Declarar UC + teste = 1 PR.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- **[BACKLOG] Emitir boleto de título aberto gera remessa** — Dado título `receber` `aberto` sem
  boleto · Quando clico **Emitir boleto** · Então nasce `BoletoRemessa` (via `TituloService` →
  `CnabDirectStrategy`) com `nosso_numero`, o botão some e a coluna Boleto mostra o número. Candidato
  a UC + Pest (`ContaReceberController::emitirBoleto`).
- **[BACKLOG] Título já-com-boleto não re-emite** — Quando o título já tem `BoletoRemessa` não
  cancelado · Então o botão Emitir não renderiza e um novo POST é no-op (toast "já tem boleto").
- **[BACKLOG] Boleto sem conta bancária configurada falha com aviso** — Sem `fin_contas_bancarias`
  no business · Então a emissão devolve `DomainException` → flash `error` (não 500), e o rodapé
  linka `/financeiro/contas-bancarias`.
- **[BACKLOG] Filtro atrasado/semana/hoje não vaza título de outro business (Tier 0)** — Dado
  títulos de biz=1 e biz=99 · Quando filtro por vencimento · Então só aparecem os do business da
  session (ADR 0093). Parcialmente tocado por `MultiTenantIsolationTest` (rota responde + scope de
  planos), mas **sem UC-id citado** ainda.
- **[BACKLOG] Boleto é fluxo crítico observado** — a emissão registra span OTel
  `financeiro.boleto.emitir` com biz + titulo_id + falha de gateway (Wave 17 D9).

## Como rodar a suíte
1. **Pest (MySQL real):** lane do Financeiro no CT100 — quando os UCs acima ganharem teste.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-03 · [CC] criado na Onda de correção Financeiro (régua por tela, [ADR 0320]): charter +
  casos retroativos. Cobertura de comportamento nasce em **débito visível** (0 UC com teste) — a
  contradição UX-alto (70 Advanced) / comportamento-indefeso que o programa existe pra expor.

[ADR 0320]: ../../../../../memory/decisions/proposals/0320-programa-ondas-regua-correcao.md
[ADR 0264]: ../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0062]: ../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md
