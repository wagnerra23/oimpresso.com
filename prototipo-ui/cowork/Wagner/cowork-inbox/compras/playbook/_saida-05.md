---
sessao: "05"
titulo: "Smoke da grade tamanho × cor (US-COM-005) — aprovado pelo [W] por screenshot"
autor: "[CL]"
data: 2026-10-13
thread: 05-grade-smoke-bloqueada.md
veredito: "aprovado — [W] 2026-10-13 no chat: \"ok, aprovado\""
---

# _saida-05 · Smoke da grade

- **Onde:** produção, `/purchases/create`, **biz=1** (WR2 Sistemas), em 2026-10-13. Não foi no biz=4, que é o
  cliente piloto ROTA LIVRE, porque a regra R6 do projeto proíbe smoke ali.
- **Como:** filial WR2 Sistemas, busca "Buscar produto pra grade" com o produto variável `MARTELO`. A matriz abriu com
  as linhas P, M, G e Nova, as colunas de quantidade e total da linha, e a linha TOTAL somando. Nada foi salvo: a
  grade foi cancelada.
- **Limite do teste:** o produto do biz=1 só tem variação de tamanho. A dimensão de cor da grade tamanho × cor não foi
  exercitada neste smoke.
- **Aprovação:** o [W] viu o screenshot e respondeu *"ok, aprovado"* (2026-10-13).
- **Achado no smoke:** no tema escuro o quadro da grade ficava branco, porque usava cores fixas do tema claro.
  O conserto, que troca só a cor pelos tokens do DS, está no PR #9193. O nome do produto e o rótulo "Custo unit."
  em `Purchase/Create.tsx` ainda usam cor fixa e ficam como follow-up.
