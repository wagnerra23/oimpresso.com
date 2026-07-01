---
casos: Lista de vendas · /sells
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-06-22"
---

# Casos de Uso & Aceite — Lista de vendas

> Tela P0 (mandato ONDAS-QUALIDADE Q2). `Status: ✅` só com veredito `pass` no manifesto G-7.
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## UC-S10 · Abrir a lista e enxergar a cobrança num relance
- **Persona:** Larissa / Kamila — "quem está devendo?" sem montar relatório.
- **Aceite:** Dado a tela carregada · Então o título **Vendas** renderiza e as pílulas de status por pagamento aparecem (default **Todas**; pagas/a-receber derivadas de `payment_status`).
- **Teste:** `e2e/sells-index.spec.ts` (Playwright, harness G-3 e2e-gate).
- **Status: 🧪** — prova parcial (rebaixado de verde por G-7 até o manifesto regravar).
- **Nota 🧪 (2026-06-22):** a prova no manifesto (`scripts/casos-test-results.json`, `ran_at: 2026-06-11`) ficou stale após a mudança de navegação de hoje (link "Caixa do dia" no dropdown Visões). O comportamento de UC-S10 (título **Vendas** + pílulas de pagamento) **não** foi tocado — o E2E `sells-index.spec.ts` segue passando no CI. Volta a verde quando `npm run e2e:check` + `npm run casos:results` regravarem o manifesto. Sem fingir prova, sem `casos:baseline:write`.

---

## UC-S11 · Da lista, iniciar a devolução de uma venda
- **Persona:** Larissa / caixa — cliente traz a peça de volta; iniciar o retorno pro estoque a partir da própria linha da venda, sem procurar outra tela.
- **Aceite:** Dado a lista carregada com ≥1 venda · Quando abro o menu **Ações da venda** (⋮) da linha e clico **Devolução** · Então o item aponta pra `/sell-return/add/{id}` (formulário de devolução daquela venda → retorno pro estoque).
- **Teste:** `e2e/sells-index.spec.ts` (Playwright, harness G-3 e2e-gate) — assere o menu ⋮ e o destino da Devolução por role/nome (L-24, sem classe CSS).
- **Regressão que defende:** o menu de Ações por linha existia (commit `d6f4dddcdc`) e sumiu no rewrite Cowork #1032 — drift que deixou a lista React sem o ponto de entrada da devolução (incidente 2026-07-01). Este UC + teste tornam a remoção uma **falha de CI**, não um sumiço silencioso. Refs: #3488 · #3494 · #3499 · [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md).
- **Status: 🧪** — spec escrito e defendido pelo e2e-gate; volta a ✅ quando `npm run e2e:check` + `npm run casos:results` regravarem o manifesto (G-7, sem fingir prova).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- **[BACKLOG] Filtrar pela pílula muda as linhas** — clicar "A receber" mostra só vendas `due/partial`; exige venda semeada com status variados no harness.
- **[BACKLOG] Drawer da venda (documento vivo)** — clicar na linha abre o drawer com FSM/pagamentos; espelhar âncoras pós-DS v6.
- **[BACKLOG] Imprimir resumo do caixa de hoje** — botão no header; exige stub de print (mesmo padrão UC-11 Oficina).

## Como rodar a suíte
1. **E2E:** `npm run e2e:check` no harness do CI (e2e-gate) — vereditos viram manifesto via `npm run casos:results` (merge per-UC).
2. **Cadência:** rodar ao fim de toda mexida em Sells/Index. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-01 · [CC] UC-S11 "Da lista, iniciar devolução" + spec no `sells-index.spec.ts` — fecha o gap por onde o menu de Ações sumiu no #1032 (adversário ancorado em ADR 0264/0256 + L-24: o que pega a regressão é UC+teste de comportamento, não gate de presença de charter). Refs #3488/#3494/#3499.
- 2026-06-11 · [CL] criado na Onda Q2 (mandato ONDAS-QUALIDADE) com UC-S10 + spec `sells-index.spec.ts`.
- 2026-06-22 · [CL] link "Caixa do dia" (`/vendas/caixa`) adicionado ao dropdown Visões de `Index.tsx` (tela viva órfã de navegação — `SellController@inertiaCaixa` → `Sells/Caixa/Index`, ADR 0192 Onda 6). `last_run` bumpado e UC-S10 rebaixado ✅→🧪 (G-7: prova e2e stale, não re-rodável fora do harness CI). Refs ADR 0264, ADR 0192.
