---
casos: Lista de vendas · /sells
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-06-11"
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
- **Status: ✅**

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
