# Sessão 2026-06-10 (g) — "Roda o fluxo": venda→produção→caixa ponta a ponta

## Pedido
[W] (após benchmark vs melhores, gaps 🔴 = sistema/fluxo, não tela): "roda o fluxo".

## O fio andado (como Larissa, com prova por screenshot/eval)
Vendas (Adicionar venda · CV · "banner" · Enter · Salvar e emitir) → Produção (fila) → Caixa (financeiro) → imposto. Travadas achadas e numeradas T1–T9.

## Consertos EXECUTADOS (verificados ✅ pelo verifier, 6/7 no round 1 + crash fix no round 2)
1. **T1 COSTURA (a maior):** o evento `oimpresso:venda-created` era disparado e NINGUÉM ouvia. Agora: `data-os.jsx` ingere → OS na fila (`stage:"producao"`, `fromVenda:"V-xxxx"`, id sequencial) quando `geraProducao`; `financeiro-data.jsx` ingere → título receivable pelo MESMO pipeline de enriquecimento (statusFor/links/competência), due +2d. `finish()` do vendas-create enriquecido (vendaId, clientName, firstItem, channel, geraProducao) + toast narrativo 5.2s. **Medido: OS #4822 1º card da fila · R-2661 R$80 links.venda=V-7841 · drawer Vínculos com chip Venda.**
2. **T2 PRODUÇÃO FANTASMA:** `view` do localStorage sem clamp (valor órfão → nem kanban nem list renderizava). Clamp aplicado (classe do `?lente=`). Verificado com valor 'lixo' → cai em kanban, 10 cards.
3. **T3 TECLADO no sidebar:** `div.sb-item` sem foco → role=button + tabIndex + Enter/Espaço nos 4 renders (51/51 itens medidos; Enter navega).
4. **T4 CONFIRMAÇÃO:** toast global JÁ existia (VdToastHost auto-monta em portal `#__vd_toast_root` — quase reinventei, L-11 evitado); mecanismo medido OK; invisibilidade era quirk do html-to-image com root secundário.
5. **T5 CTAs MORTOS no Financeiro:** "+ Novo lançamento" agora abre modal real (tipo/desc/contraparte/valor/venc → ledger + window.FIN_ROWS persiste + toast); "Exportar XLSX/PDF" stub virou **Exportar CSV real** (filtered → arquivo ;-separado BOM, definido APÓS `filtered` — gotcha Babel var-hoisting).
6. **CRASH achado pelo verifier no Novo lançamento:** status:"aberto" inventado não existia no STATUS_STYLES → `undefined.bg` → tela branca. Fix duplo: `window.FIN_STATUS_FOR = statusFor` (pipeline único) + **StatusBadge blindado** (status desconhecido → tom muted, dado ruim nunca derruba tela).

## Fila documentada (não feito nesta sessão — entra nas ondas)
- Overflow-x + CTA estourando no Adicionar venda; CTA "Nova venda" quebrando no hub.
- Busca de cliente fraca ("Bella" não acha "Loja Bella Moda").
- Hub Vendas + Manufacturing = geração visual antiga (ondas W).
- fin-fluxo/fin-concil sem breadcrumb (pré-existente).
- Clique na sugestão de produto não adiciona (só Enter) — verificar se é real ou era clique sintético.

## Lições
- Evento sem ouvinte = costura de mentira (a infra existia há semanas, morta).
- Estado de localStorage sem clamp = tela fantasma (2º caso; clamp vira padrão de todo estado persistido).
- Dado fora do domínio NUNCA pode derrubar a tela → componentes de mapa (badge etc.) com fallback obrigatório.

## Refs
vendas-create-page.jsx (finish) · data-os.jsx + financeiro-data.jsx (ouvintes) · producao-page.jsx (clamp) · sidebar.jsx (a11y) · financeiro-page.jsx (FinNovoLancamento/handleExport/StatusBadge) · benchmark no chat (15 dimensões, sistema ~6,5).
