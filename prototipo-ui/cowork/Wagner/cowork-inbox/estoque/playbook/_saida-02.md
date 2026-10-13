---
sessao: "_saida-02"
thread: "02 · Contratos estoque-ajustes · estoque-transferencias (derivados do alvo)"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR desta thread (claude/estoque-02-contratos)"
base_lida: wagnerra23/oimpresso.com@main 279fe625a0
---
# _saida-02

## Entregue
- `governance/design/contracts/estoque-ajustes.contract.json` (tela `StockAdjustment/Index`)
- `governance/design/contracts/estoque-transferencias.contract.json` (tela `StockTransfer/Index`)

Os dois derivam dos alvos da A1 (`governance/design/targets/estoque--{ajustes,transferencias}--index.alvo.json`),
não do `.tsx`. `fonte` = `prototipo-ui/cowork/Wagner/estoque-page.jsx`.

| contrato | seções (na ordem) | copy travada |
|---|---|---|
| `estoque-ajustes` | `est-aj-cabecalho` · `est-aj-toolbar` · `est-aj-tabela` | "Novo ajuste" · legenda do trilho · Data, Tipo, Valor ajustado, Recuperado, Motivo do ajuste, Lançado por, Ações |
| `estoque-transferencias` | `est-tr-cabecalho` · `est-tr-toolbar` · `est-tr-tabela` | "Nova transferência" · legenda dos estados · Data, Status, Mexeu no saldo?, Frete, Total, Ações |

Cada string de copy foi conferida por máquina nos dois lados: está na `base.assinatura` do alvo (protótipo) e o
gate a acha no código da Page.

## O `nao_toca: resources/js/Pages/` foi relaxado — e por quê
O gate "Contratos de tela" é required e roda todo `*.contract.json`. Ele exige a âncora `data-contract="<id>"` e a
copy literal **na Page**. As duas Index tinham 0 âncoras: sem tocá-las, o contrato nascia vermelho. Decisão do
gerente da fila (2026-10-13, técnica, não de produto), seguindo o precedente de [#8934](https://github.com/wagnerra23/oimpresso.com/pull/8934)
(telas-soltas thread 01) e [#8873](https://github.com/wagnerra23/oimpresso.com/pull/8873) (atendimento thread 05):

- só atributos `data-contract`, mais um `div` em volta do `PageHeader` (que não é sticky);
- zero handler, zero dado, zero copy, zero classe;
- o card de filtros é `sticky`: ganhou o atributo direto, sem `div` em volta, para não mudar o comportamento;
- nota nos dois `Index.casos.md` dizendo que nenhum UC mudou nem foi reexecutado.

## Seções do alvo sem contraparte na Page
`abas` · `subabas` · `periodo` · `rodape`. A Page é uma tela só (sem as abas do `EstoquePage` nem as sub-abas de
status), o período mora dentro do card de filtros, e não há rodapé de totais nem paginação. Ficaram fora do
contrato; entram quando a Page as ganhar.

## Decisão [W] — divergências da `_saida-00`, fora do contrato
Nada disso foi travado (copy só onde protótipo e vivo coincidem):
- filtro de local: vivo "Todas as filiais" × protótipo "Todos os locais";
- "Mexeu no saldo?": vivo "Sim"/"Não" × protótipo "moveu"/"só reserva";
- status "Finalizada" só no protótipo;
- colunas "Itens" e id (`AJ-0142`/`TRF-0088`) só no protótipo; cabeçalho "Ajuste"/"Transferência" × "Ref. Nº";
- "Local" × "Filial"; "Origem → destino" × "Origem → Destino";
- paginação de 10 em 10 (protótipo) × até 200 linhas sem paginar (vivo).

## Provas
- `node scripts/contrato-de-tela.mjs --contract <c>`: `✅ limpo` nos dois (3 seções · âncora + copy · ordem).
- **Mordida:** o mesmo contrato contra a Page do `main` (sem âncoras), via `--root`, deu rc=1 com
  `sem âncora data-contract` nas 3 seções. Com a Page nova, rc=0.
- Os 4 modos do job, rodados localmente: todos os 107 contratos (rc=0) · `--map --check` (rc=0; as duas telas
  `3/3 🟢 portado`) · `--anti-tautologia` (rc=0, nenhum aviso para os dois contratos novos) ·
  `contrato-de-tela.test.mjs` (`todos os controles passam`).
