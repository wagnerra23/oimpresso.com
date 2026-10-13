---
sessao: "01"
titulo: "Contrato cliente-index — saída da thread"
autor: "[CL]"
data: 2026-10-13
base: origin/main 07e8891354
thread: 01-contratos.md
veredito: "entregue — contrato cliente-index derivado do alvo da A1; 5 seções com âncora + copy, nenhuma Page editada; mordida provada."
---
# _saida-01 · Contrato da lista de Clientes

## O que saiu
`governance/design/contracts/cliente-index.contract.json`, derivado do alvo `cliente--index` (A1, #9227), não do
`.tsx`. A Page já tinha âncora para cada seção do alvo com contraparte: **nenhuma Page foi editada**.

| seção do alvo | âncora na Page | copy |
|---|---|---|
| cabecalho | `header-identidade` | "cadastrados" |
| abas | `perspectivas-fornecedor-funcionario-representante` | Todos · Clientes · Fornecedores · Funcionários · Representantes · Outros |
| kpis | `kpis` (`_components/KpiStripClickable.tsx`) | 7 rótulos dos cards |
| toolbar | `filtros-busca` | Status · Tipo · Tags · Sem compra há · Saldo |
| tabela | `tabela-linhas` | Cliente · Documento · Cidade/UF · Frescor · VIP |

O copy entra só onde protótipo e tela dizem a mesma coisa. Foi conferido por máquina na `base.assinatura` do alvo e
no código da Page **sem comentário**, que é como o gate compara.

## Fora, e por quê
- **`linha`:** a linha da tabela não tem âncora própria. A tabela a cobre, e o `design-diff` da 02 mede a linha nos
  dois lados.
- **`paginacao`:** a Page tem o rodapé `Pagination`, sem âncora e com só "Por página" em comum. Ancorar exige o
  RUNBOOK de `Cliente/Index`, que não existe e que o hook `block-mwart-violation` cobra antes de editar a Page. A 02,
  que já vai editar `Cliente/Index` (título 18→22px), é o lugar natural para criar o RUNBOOK e ancorar o rodapé.
- **"Novo cliente":** a Page monta o botão por `'Novo ' + singular`, e o literal só existe em comentário.
- **`kpis` fora de `ordem`:** o gate ordena as âncoras por arquivo, e o `KpiStripClickable` é o 2º arquivo do alvo.
  A posição real (entre abas e toolbar) não é verificável por ele; as outras quatro estão em `ordem` e conferem.

## Provas
- `contrato-de-tela --contract`: 5 seções `âncora + copy presentes`, ordem coerente, rc=0.
- **Mordida:** como nenhuma Page mudou, a prova foi por mutação numa cópia isolada (`--root`). A cópia intacta deu
  rc=0; sem `data-contract="tabela-linhas"`, rc=1 com `seção "tabela-linhas" sem âncora`.
- Os modos do job "Contratos de tela", rodados localmente: os 89 contratos rc=0; `--map --check` rc=0
  (`Cliente/Index` 5/5 `🟢 portado`); `--anti-tautologia` sem aviso para este contrato, que entra entre os de toda a
  copy ancorada na fonte; `--preflight origin/main` rc=0; `contrato-de-tela.test.mjs` e `-relevante.test.mjs` rc=0.

## Placar
entregue 1 de 1 contrato · 5 seções · 0 Pages tocadas.
