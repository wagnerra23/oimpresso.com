---
sessao: "16d"
titulo: Saída da thread 16 (4ª passada) — o smoke em prod do redirecionamento de /asset/revocation
dono: "[CL]"
medido_em: 2026-09-30
base_medida: ca44a3d54 (origin/main fresco)
arquivos_de_producao_tocados: 0
complementa: "_saida-16c.md (#8286), que não registrou smoke em prod"
---

# 16d · Smoke em prod do redirecionamento

Feito em `oimpresso.com`, biz=1 (WR2 Sistemas), usuário superadmin, depois do merge do [#8286](https://github.com/wagnerra23/oimpresso.com/pull/8286) e do [#8297](https://github.com/wagnerra23/oimpresso.com/pull/8297).

- **Navegação direta** (`fetch('/asset/revocation')`, como o browser): a resposta é redirect, e o destino final é `/asset/allocation` (200, página com `data-page`).
- **Visita Inertia** (`X-Inertia` + `X-Requested-With`, como o clique no menu): destino final `/asset/allocation`, componente `Patrimonio/Alocacoes`, 200. O JSON cru do DataTables, que o `_saida-16c` descreve como o defeito anterior, **não** aparece.
- **Cabeçalho de Alocações:** o botão "Devoluções" saiu.
- **Sub-nav do Patrimônio:** "Painel · Bens · Alocações · Manutenções · Configurações · Auditoria". O ghost "Devoluções", que o `_saida-16c` deixou "de fora, com motivo", foi tirado depois pelo #8297.

## O que continua fora
- **Índice do Cowork:** a prova 1 da thread 16 ainda exige `Inertia::render('Patrimonio/Alocacoes'` no controller, e a decisão (b) trocou isso por redirecionamento (§"O que o índice precisa refletir" do `_saida-16c`). É edição do Cowork, não do espelho.
- **Não medido:** usuário não-superadmin. O controle de 403 sem assinatura é do `SmokeRoutesTest`, não desta passada.
