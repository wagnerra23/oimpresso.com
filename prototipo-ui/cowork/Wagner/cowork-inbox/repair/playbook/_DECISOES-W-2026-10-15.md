---
sessao: "decisoes-w"
titulo: "Repair — link de acompanhamento: validade, loja na página, série obrigatória, acesso por WhatsApp"
executor: "[CC]"
data: 2026-10-15
base: wagnerra23/oimpresso.com@main 93ff8a960b74
---
# _DECISOES-W-2026-10-15 — Repair

> **Fonte:** [W] 2026-10-15, formulário do Cowork (rodada única, opção recomendada escolhida). Já aplicado no `00-INDICE.md`. Casos em `cowork-inbox/repair/ConsultaOs-link.casos.md`.

| id | pergunta | resposta | destrava | UC |
|---|---|---|---|---|
| D-LINK-VALIDADE | Por quantos dias o link abre depois da entrega? | **90 dias** (garantia de serviço); OS em aberto não expira | 06 | UC-COS-17 |
| D-LOJA | A página do link mostra a loja? | **Sim**: nome no topo, telefone do balcão no rodapé | 06 | UC-COS-19 |
| D-LEGADO | Série obrigatória na busca por nº da OS? | **Sim** | 06 | UC-COS-26 |
| D-ACESSO | Oferecer "Receber acesso" a quem não tem cadastro? | **Sim**, desligado por padrão (a loja liga); código por **WhatsApp**; walk-in nunca | 07 | UC-COS-23 |

## Efeito
- **UC-COS-19 e UC-COS-26 deixam de ser `should` e viram `must`.** UC-COS-23 deixa de ser `could` e vira `must` quando a config da loja estiver ligada.
- **UC-COS-04 (whitelist):** ganha o nome da loja e o telefone do balcão, só no payload do link (D-LOJA). O teste do UC-COS-04 precisa listar as duas chaves novas e continuar recusando qualquer dado do cliente.
- **UC-COS-08 e UC-COS-09:** com a série obrigatória, o caso "série só estreita" vira "sem série, 422". O segundo assert do UC-COS-09 continua documentando o cruzamento de empresas, agora só quando nº **e** série batem em duas empresas.
- **Canal do código (07):** WhatsApp, pelo módulo de WhatsApp que já existe no repo. A thread 07 confere no turno qual serviço envia mensagem ativa e não cria um novo.
