---
thread: "03"
titulo: Planilhas · Voz do Cliente · OS — D1 (2ª rodada)
dono: "[CC]"
data: 2026-10-07
veredito: "entregue — aviso de exploração removido das 4 rotas; build acertado para descer"
---
# _saida-03 (refeito 2026-10-07)

D1 mudou ([W] 07/10): entram em produção. O recibo de 06/10 (aviso de exploração) fica superado.

**O aviso estava factualmente errado em 2 das 3** — lido no `main` @5d9da472e955: Planilhas tem backend vivo em `Modules/Spreadsheet` (Blade, `/spreadsheet/sheets`, 9 testes) e Voz do Cliente em `Modules/VozDoCliente` (Blade `caixa.blade.php`, `/voz-do-cliente`, permissão `vozdocliente.triar`). Só a OS da gráfica não tem dono claro (thread 06).

**Mudou no build:**
- `app.jsx` — `ExploracaoAviso` apagado (função + 4 usos: `os`, `planilhas`, `planilha-nova`, `voz`).
- `voz-do-cliente-page.jsx` — a lista **encolhia até sobrar só o cabeçalho** (`.os-page` é flex coluna com rolagem em Y; filho sem `flex-shrink:0` cede a altura). Colunas re-dimensionadas: "O que disse" ganhou 320px (era o resto e ficava ~90px, uma palavra por linha); coluna de ação ganhou rótulo "Ação" (cabeçalho vazio é A-falha).
- `modulos-faltantes.css` — `.vdc-page>*,.sup-page>*{flex-shrink:0}`; tabela da Voz `min-width:1130px`.
- `styles.css` — OS: cliente e produto não quebram palavra a palavra em 1280px (`min-width` 170/200).
- host — a Voz e o Suporte carregam `acessos-ds` junto (sem isso, abrir a rota direto renderizava sem KPIs e sem o vazio, porque `AcessosDS` chegava depois do primeiro render); `venda-cotacoes` puxa `orc-page` na frente.

**Prova:** `nao_contem ExploracaoAviso` em `app.jsx`, medida no espelho depois do import.
