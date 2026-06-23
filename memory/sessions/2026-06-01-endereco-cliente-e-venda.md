# Sessão 2026-06-01 — Endereço do cliente e na venda (F1 · [CC])

**Pedido [W]:** "sync now tarefa endereço do cliente e na venda" — cadastrar endereço no cliente e usá-lo na venda.

## O que foi feito (F1, no Cowork — `oimpresso.com.html` + `*-page.jsx`)
- **`data-os.jsx`** — modelo de endereço por cliente: `OS_CLIENTS[i].addresses[] = { id,label,cep,logradouro,numero,complemento,bairro,cidade,uf,principal,entrega }` (15 clientes, mix SP capital + Guarulhos/Osasco/Diadema/SBC/Santo André p/ exercitar "outro município"). Helpers em `window.OS_DATA`: `cliEntregaAddr`, `cliPrincipalAddr`, `fmtAddrLinha`, `fmtAddrCidade` + `OS_MATRIZ_CIDADE`.
- **`clientes-page.jsx`** — `CliEnderecoSection` no `ClienteDetailDrawer`: cards (logradouro/nº/compl · bairro · cidade/UF · CEP), flags Cadastro/Entrega padrão, copiar, "Usar p/ entrega", form inline "Adicionar". `deriveCli` usa cidade/UF reais do endereço principal.
- **`vendas-create-page.jsx`** — seção Frete reescrita: ao marcar **Entrega**, picker dos endereços salvos do cliente (default = entrega) + "Outro endereço" (campos inline). `municipioOutro` derivado do endereço escolhido vs matriz → alimenta MDF-e automático. Removido o `<select>` manual de município (estado `municipio`/`setMunicipio`).
- **`clientes-page.css` / `vendas-create-page.css`** — estilos escopados (cards `.cli-addr-*`, picker `.vc-addr-*`, banner `.vc-entrega-dest`). Tokens canônicos.
- **`icons.jsx`** — +`copy`.

## Decisões
- Endereço de entrega na venda **deriva do cliente** (não digitação cega) e **decide o município fiscal** — conecta cadastro→fiscal sem passo manual.
- Cidade/UF da listagem de clientes passa a refletir o endereço real (antes era hash).

## Erros + correção
- Preview do harness instável (timeouts/"no preview pane") durante captura interativa do drawer/venda — verificação delegada ao verifier agent (iframe próprio). Página carrega sem erro de console.

## Residual / próximo passo
- F2 (screenshot [W]) → F3 ([CL] traduz pro repo: migration `customer_addresses` + `Sells/Create` + `Cliente/Show`). Entrada na fila: `COWORK_NOTES.md → 📥 Pendentes` (entrada 2026-06-01 "endereço do cliente e na venda").
- Replicar o padrão de Endereços nos outros cadastros (Fornecedor/Funcionário/Representante) quando fizer sentido.

## Refs
- `COWORK_NOTES.md` (pendente 2026-06-01) · `STATUS.md` (quadro: Vendas + Clientes) · ADR 0093 (multi-tenant) · ADR 0110 (Cockpit V2).
