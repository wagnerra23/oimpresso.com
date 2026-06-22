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
- **Status: 🧪** — prova de 2026-06-11 ficou stale após a mudança de navegação de 2026-06-22 (link "Caixa do dia" no dropdown Visões). O comportamento de UC-S10 (título **Vendas** + pílulas de pagamento) **não** foi tocado; rebaixado honestamente por G-7 (ADR 0264) até `npm run e2e:check` + `npm run casos:results` no harness CI regravarem o manifesto e devolverem o ✅. Sem fingir prova, sem `casos:baseline:write`.

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
- 2026-06-11 · [CL] criado na Onda Q2 (mandato ONDAS-QUALIDADE) com UC-S10 + spec `sells-index.spec.ts`.
- 2026-06-22 · [CL] link "Caixa do dia" (`/vendas/caixa`) adicionado ao dropdown Visões de `Index.tsx` (tela viva órfã de navegação — `SellController@inertiaCaixa` → `Sells/Caixa/Index`, ADR 0192 Onda 6). `last_run` bumpado e UC-S10 rebaixado ✅→🧪 (G-7: prova e2e stale, não re-rodável fora do harness CI). Refs ADR 0264, ADR 0192.
