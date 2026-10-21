---
sessao: "decisoes-w"
titulo: "venda-menu — C2 liga depois da janela da C1"
executor: "[CC]"
data: 2026-10-15
base: wagnerra23/oimpresso.com@main 93ff8a960b74
---
# _DECISOES-W-2026-10-15 — venda-menu

> **Fonte:** [W] 2026-10-15, formulário do Cowork (rodada única, opção recomendada escolhida). Já aplicado no `00-INDICE.md` deste playbook.

| id | pergunta | resposta | thread |
|---|---|---|---|
| D-C2 | Ligar Lista de POS, Remessas e Devoluções no biz=1? | **Sim**, depois de 2026-10-15 ~12:50Z, **se** a C1 não tiver incidente na janela | C2 |

## O que a sessão da C2 confere antes de ligar
- "Sem incidente" é **medido**, não presumido: erros 5xx e exceções das 3 rotas da C1 (`/discount`, `/import-sales`, `/sales-order`) no período desde o deploy do #9192 (2026-10-13 12:49Z). Se houver, **não liga** e devolve o achado num `_saida-C2`.
- Mesmo mecanismo da C1 (default no `config/mwart.php`, sem mexer no `.env`), e o mesmo smoke discriminante do `_saida-C1` §3.
- A C4 (todas as empresas) continua esperando o aviso de [W] à ROTA LIVRE.
