---
id: requisitos-estoque-stock-adjustment-create-gap
tela: StockAdjustment/Create (/stock-adjustments/create)
prototipo: prototipo-ui/cowork/estoque-forms.jsx + estoque-page.jsx
tela_viva: resources/js/Pages/StockAdjustment/Create.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — StockAdjustment/Create

> **Âncora resolvida por `bundle_source`** (`ancora.mjs StockAdjustment/Create` → `âncora ✓ [-page.jsx (bundle · bundle_source)] estoque-page.jsx`); o charter declara `related_prototype: n/a (herda PT-02 Form-Drawer)` — coexistência prevista.
>
> **Porte reverso** (cabeçalho `estoque-page.jsx:1-3` cita `StockAdjustment/Create` no vivo lido em 2026-08-22) → expectativa-base é paridade. Frescor: verificação estrutural **SYNC** (ver nota completa no [gap do Index](stock-adjustment-index-gap.md)).
>
> **Região do protótipo:** `FormAjuste` (`estoque-forms.jsx:157-249`), montado pela aba `ajuste-novo` (`estoque-page.jsx:641-657`), mais os componentes que ele reusa — `BuscaProduto` (`:12-66`) e `LinhasItens` (`:68-150`).
>
> ⚠️ **Tier 0 — esta tela ESCREVE estoque e valor.** Toda linha marcada "Decidir" que mexa em quantidade, custo unitário, total ou saldo carrega, na própria ação, a exigência da REGRA MESTRE (proibicoes.md): **dupla confirmação do cálculo por dois caminhos independentes + tabela antes→depois apresentada ao [W] antes de aplicar**.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho e ações do formulário | `PageHeader` com "Cancelar" (volta para a lista) e "Salvar", este desabilitado enquanto `form.processing` ou `recuperadoExcede` (`Create.tsx:131-145`) | Nada — paridade. O protótipo tem os mesmos dois botões (`estoque-forms.jsx:240-246`) e o mesmo bloqueio de submit. |
| Dados do ajuste | Local (select de `business_locations`), Referência com placeholder "(auto-gerado)", Data e Tipo — este com destaque visual vermelho quando `abnormal` (`Create.tsx:147-203`; `TYPE_LABEL` em `:53-56`) | Nada — paridade. O protótipo tem o mesmo bloco "Dados do ajuste" (`estoque-forms.jsx:190-205`), com o mesmo placeholder de referência auto-gerada (`:196`). O destaque cromático do tipo anormal é divergência declarada no charter (`divergence_from_blueprint: "Tipo Normal/Abnormal destacado com cor (abnormal=rose perda)"`). |
| Busca e adição de produto | Um `Input` de texto livre com ícone de lupa e um botão "Adicionar linha"; o nome do produto é **digitado** (`product_name` é string editável na linha, `Create.tsx:213-231` e `:275-280`), e `product_id`/`variation_id` nascem `null` (`:29-30`) | **Decidir.** No protótipo `BuscaProduto` (`estoque-forms.jsx:12-66`) é autocomplete real contra o catálogo, **bloqueado até escolher o local** (placeholder "Escolha o local primeiro", `:32`) e ciente do que já foi adicionado (`jaTem`). O vivo não vincula a linha a um produto do catálogo. ⚠️ **Toca ESTOQUE**: sem `product_id`, a baixa depende de resolução no servidor. Antes de construir, medir o que o `StockAdjustmentController@store` faz hoje com `product_name` sem id — o comportamento atual é pré-requisito da decisão, não detalhe. Construir ou rejeitar por escrito. |
| Lote e validade nas linhas | Ausente — `lot_no_line_id` existe no tipo mas nasce `null` e não tem controle na UI (`Create.tsx:34`) | **Decidir.** O protótipo tem coluna "Lote e validade" condicionada à flag `lote` (`estoque-forms.jsx:78`) e é o que liga o ajuste ao lote vencido na aba Vencimentos. ⚠️ **Toca ESTOQUE** (baixa por lote ≠ baixa por produto). Construir ou rejeitar por escrito. |
| Coluna "Efeito no saldo" | Ausente | **Decidir.** O protótipo mostra, por linha, o efeito da quantidade sobre o saldo (`estoque-forms.jsx:80`) — é o feedback que evita lançar ajuste maior que o disponível. ⚠️ **Toca ESTOQUE**: exibir saldo disponível por local exige o backend expor esse número, e exibi-lo errado induz lançamento errado. Construir ou rejeitar por escrito. |
| Valor recuperado e R-ADJ-003 | Campo "Valor recuperado (R$)" com validação client-side de `recuperado ≤ total`, mensagem de erro explícita e bloqueio do submit (`Create.tsx:85`, `:132-149`, `:144`) | Nada — vivo à frente. O protótipo tem os mesmos três números no fecho (`estoque-forms.jsx:231-233`) mas **não** implementa o bloqueio da R-ADJ-003; o vivo bloqueia no cliente e o charter registra que o servidor também valida. |
| Totais (ajustado / recuperado / perda líquida) | Os três, com "Perda líquida" em destaque, todos atrás de `permissions.view_purchase_price`, e mensagem honesta quando falta permissão (`Create.tsx:150-162`) | Nada — paridade. Mesmos três números e mesmos rótulos no protótipo (`estoque-forms.jsx:231-233`). O gate de permissão é do vivo. |
| Motivo / notas | `Textarea` com placeholder "Razão do ajuste (perda, quebra, inventário)…" (`Create.tsx:166-178`) | Nada — paridade. O protótipo usa um exemplo mais concreto no placeholder (`estoque-forms.jsx:227`: *"Ex: lona riscada na descarga — 3 bobinas inutilizadas."*). Copy é ajuste de redação, não capacidade. |
| Pré-preenchimento vindo de outra tela | Ausente | Nada — não é gap desta tela. O protótipo pré-preenche o ajuste a partir de um lote vencido (`baixarVencido`, `estoque-page.jsx:530-536`) e do fechamento de uma contagem (`fecharContagem`, `:539-555`). As duas origens são as abas **Vencimentos** e **Contagem**, que não têm tela viva — o pré-preenchimento nasce com elas, se nascerem. |
| Multi-tenant e permissões | `permissions.view_purchase_price` gateia preço unitário, subtotal e todo o bloco de valores (`Create.tsx:97-112`, `:132`); locais vêm escopados do controller | Nada — vivo à frente. O protótipo simula com `D.can(papel, "preco")` sobre mock. |
