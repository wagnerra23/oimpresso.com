---
id: requisitos-estoque-stock-transfer-create-gap
tela: StockTransfer/Create (/stock-transfers/create)
prototipo: prototipo-ui/cowork/estoque-forms.jsx + estoque-page.jsx
tela_viva: resources/js/Pages/StockTransfer/Create.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — StockTransfer/Create

> **Âncora resolvida por `bundle_source`** (`ancora.mjs StockTransfer/Create` → `âncora ✓ [-page.jsx (bundle · bundle_source)] estoque-page.jsx`); o charter declara `related_prototype: n/a (herda PT-02 Form-Drawer)` — coexistência prevista.
>
> **Porte reverso** do vivo lido em 2026-08-22 (`estoque-page.jsx:1-3`) → expectativa-base é paridade. Frescor: **STALE** — `TabBar` no espelho x `window.CliTabs` no vivo (nota completa e errata no [gap do StockAdjustment/Index](stock-adjustment-index-gap.md)).
>
> **Região do protótipo:** `FormTransferencia` (`estoque-forms.jsx:253-356`), montado pela aba `transferencia-nova` (`estoque-page.jsx:657-676`), reusando `BuscaProduto` (`:12-66`) e `LinhasItens` (`:68-150`).
>
> ⚠️ **Tier 0 — esta tela ESCREVE estoque e valor.** Toda linha "Decidir" que mexa em quantidade, custo, frete, total ou saldo carrega, na própria ação, a exigência da REGRA MESTRE: **dupla confirmação do cálculo por dois caminhos independentes + tabela antes→depois apresentada ao [W] antes de aplicar**.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho e ações | `PageHeader` com título, subtítulo que nomeia o Tier 0 multi-tenant, "Cancelar" e "Salvar" — este bloqueado enquanto `origemDestinoIguais` (`Create.tsx:135-146`) | Nada — paridade. O protótipo tem os mesmos dois botões (`estoque-forms.jsx:345-353`), sob um `h2` e um parágrafo de contexto montados fora do formulário, na aba `transferencia-nova` (`estoque-page.jsx:657-662`). No vivo esse papel é do `PageHeader`. |
| Origem → Destino (R-XFER-004) | Bloco em destaque com selos **DE**/**PARA**, o destino **já filtrando a origem da lista** (`Create.tsx:225-228`), mensagem de erro explícita e submit bloqueado (`Create.tsx:190-240`, predicado em `:113-114`) | Nada — paridade na regra, com divergência de forma declarada. O protótipo faz as **duas** coisas que o vivo faz: valida `mesmoLocal` (`estoque-forms.jsx:270`) **e** filtra a origem da lista de destino (`:303`, `Object.keys(D.LOCAIS).filter((k) => k !== de)`). O que é do vivo é a **forma**: os selos DE/PARA em destaque, divergência declarada no charter (*"Origem→Destino destacado no topo"*). |
| Status | Select alimentado pelo servidor (`statuses`), default `pending` (`Create.tsx:70`, `:177-190`) | **Decidir.** O protótipo mostra, abaixo do select, o **efeito do status escolhido sobre o saldo** (`help={D.STATUS_TRF[status].efeito}`, `estoque-forms.jsx:294`) e, sem escolha, a frase *"O status decide se o saldo se move agora ou só reserva."* ⚠️ **Toca ESTOQUE** — é exatamente a informação que separa reserva de movimento (R-XFER-005). É copy derivada de dado que o vivo já tem; o texto por status precisa ser decidido pelo [W]. Construir ou rejeitar por escrito. |
| Limpeza de linhas ao trocar a origem | Ausente — trocar `location_id` mantém as linhas já adicionadas (`Create.tsx:206-210` só faz `setData`) | **Decidir.** O protótipo limpa as linhas e avisa: *"Origem trocada — as linhas foram limpas porque o saldo é da origem."* (`trocarDe`, `estoque-forms.jsx:279`). ⚠️ **Toca ESTOQUE**: manter linhas de outra origem é como um lançamento nasce apontando para saldo que não existe naquele local. Construir ou rejeitar por escrito. |
| Busca e adição de produto | `Input` de texto livre + botão "Adicionar item" (`Create.tsx:260`); `product_name` é digitado (`:290`) e `product_id`/`variation_id` nascem `null` (`:91-92`) | **Decidir.** No protótipo `BuscaProduto` (`estoque-forms.jsx:12-66`) é autocomplete contra o catálogo, bloqueado até escolher o local e ciente do que já está na lista. ⚠️ **Toca ESTOQUE**. Antes de construir, medir o que o `StockTransferController@store` faz com `product_name` sem id. Construir ou rejeitar por escrito. |
| Trava de saldo disponível na origem | Ausente — a quantidade é livre (`Create.tsx` não consulta saldo) | **Decidir.** O protótipo calcula o disponível na origem por linha (`dispDe`, `estoque-forms.jsx:270`), marca `negativa` e exibe alerta em vermelho *"Acima do disponível na origem"* citando INV-4 (`:320-321`), além de bloquear o submit (`podeSalvar`, `:272`). ⚠️ **Toca ESTOQUE e é o item mais pesado desta tela**: exige o backend expor disponível-por-local-e-lote, e exibir número errado induz lançamento errado. Construir ou rejeitar por escrito. |
| Lote e validade nas linhas | Ausente | **Decidir.** O protótipo tem coluna de lote condicionada à flag `lote` (`estoque-forms.jsx:78`) e bloqueia o salvamento quando o produto tem lotes e nenhum foi escolhido (`semLote`, `:271`). ⚠️ **Toca ESTOQUE**. Construir ou rejeitar por escrito. |
| Conservação do total | Ausente | **Decidir.** O protótipo afirma o invariante em tela: *"N sai de X · N entra em Y"* + *"Transferência não cria nem destrói saldo — o total da empresa fica igual (UC-EST-06)"* (`estoque-forms.jsx:323-327`). ⚠️ **Toca ESTOQUE**: é asserção sobre movimento de saldo; exibi-la exige que o backend de fato conserve, e essa prova é a dupla confirmação da REGRA MESTRE. Construir ou rejeitar por escrito. |
| Frete e totais | Campo "Frete (R$)" e o fecho Subtotal / Frete / Total, atrás de `view_purchase_price` (`Create.tsx:335-360`; `totalFinal` em `:84`) | Nada — paridade. Mesmos três números no protótipo (`estoque-forms.jsx:338-340`). O protótipo acrescenta o `help` *"Entra no custo do material que chega no destino"* no campo de frete (`:334`) — copy, não capacidade. |
| Notas / observação | `Textarea` (`Create.tsx:363-366`) | Nada — paridade. |
| Edição de transferência existente | Fora do escopo desta tela — Create é só criação | Nada — não é gap desta tela. O `FormTransferencia` do protótipo é create **e** edit pelo mesmo componente (`editar` em `estoque-forms.jsx:253`), acionado pelo drawer da lista. Se a edição vier, a decisão é da tela de detalhe, não desta. |
| Permissão por papel | `permissions.view_purchase_price` gateia custo unitário, subtotal e frete | Nada — vivo à frente. O protótipo tem uma tela inteira de "sem permissão" (`semPermissao`, `estoque-forms.jsx:281-284`) que no vivo é resolvida antes, no controller. |
