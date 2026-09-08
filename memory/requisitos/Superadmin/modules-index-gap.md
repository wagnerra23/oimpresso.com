---
id: requisitos-superadmin-modules-index-gap
tela: Pages/Modules/Index (/modulos)
prototipo: prototipo-ui/cowork/modulos-page.jsx
tela_viva: resources/js/Pages/Modules/Index.tsx
gerado_em: 2026-09-06
---

<!-- DUAS convencoes de nome valem aqui, e as duas foram medidas em 2026-09-06.

     1) BASENAME. Tem que ser o slug do <Mod/Tela> + "-gap.md" — e assim que
        prototipo-ui/gerar-contrato.mjs::escolherGap resolve <Mod/Tela> -> gap/map. Com outro
        nome, `node prototipo-ui/consumir-map.mjs <Mod/Tela>` sai rc=1 "map nao encontrado"
        mesmo com o arquivo no disco, e a Fase 4 fica inalcancavel pela forma canonica.

     2) PREFIXO `Pages/` nas mencoes a esta tela. O diretorio de telas Inertia se chama
        `Modules/`, igual ao diretorio de app-modules do Laravel — entao o token nu casa o
        MOD_REF_RE do scripts/governance/knowledge-drift.mjs e a tela vira "modulo-fantasma"
        (ghost NOVO, ratchet do sdd-scorecard reprova). Escrever `Pages/Modules/Index` diz a
        verdade e casa o lookbehind. O `ancora.mjs` aceita as duas formas (medido). -->

# GAP-SPEC — Pages/Modules/Index

> **Três donos já falam desta tela, e esta tabela não os contradiz.** (a) O
> [`modulos.contract.json`](../../../prototipo-ui/contrato/modulos.contract.json) trava a copy e a
> ordem de cinco seções e **declara por escrito** as duas que recortou, com razão — não as reabro
> como novidade, só as ancoro. (b) O charter (v2, 2026-08-19) tem quatro decisões [W], **três
> abertas** — D1 (versão do módulo), D3 (drawer PT-02) e D4 (instalação dentro do request) — e uma
> fechada, D2 (RBAC unificado em `manage_modules`, decidido em 2026-08-19). (c) O intake do design vive
> em [`cowork-inbox/modulos/`](../../../prototipo-ui/design-docs/cowork-inbox/modulos/) e é **pedido**
> (7 PRs), não fonte visual — nada dele entra aqui como gap.
>
> **Non-Goals do charter, que NÃO viram gap:** habilitar módulo por negócio, aplicar `business_id`
> scope, acesso a usuário comum, instalar ou remover código, derrubar tabela, editar `module.json`.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | `Index.tsx:157-178` traz o título "Gerenciador de Módulos" e a mesma linha de contagens do protótipo (`modulos-page.jsx:289-301`), inclusive o trecho condicional "N com erro" em vermelho. A copy bate com o contrato (`modulos.contract.json`, seção `modulos.header`). Faltam os dois rótulos que o protótipo põe à direita (`:298-299`): o atalho `/` para buscar e o selo "app-wide · cross-tenant". | **Decidir.** Região do mockup: `modulos-page.jsx:297-300`; ponto no vivo: `Index.tsx:176-177`. O selo de escopo diz em voz alta o que o charter chama de "rota cross-tenant intencional" — hoje isso só existe na documentação, não na tela. O atalho `/` exige o handler de teclado, que também não existe no vivo. Construir ou rejeitar por escrito. |
| Cartões de contagem | `Index.tsx:180-185` tem os quatro cartões na ordem do contrato (Total · Ativos · Inativos · Com erro), com os mesmos rótulos do protótipo (`modulos-page.jsx:303-308`). Os tons divergem por vocabulário do repo (`success`/`destructive` no vivo, `ok`/`danger` no protótipo), não por comportamento. | Nada — paridade. |
| Filtros por área e status | `Index.tsx:187-210` tem os dois `FilterDropdown` do protótipo (`modulos-page.jsx:316-330`) e `:212-235` traz os chips removíveis e o escape "limpar tudo" — as três copies que o contrato trava na seção `modulos.filtros`. | Nada — paridade. |
| Busca | `Index.tsx:241-260` usa o mesmo `placeholder` literal do contrato ("Buscar por nome, alias, descrição ou área…"), o mesmo `aria-label` e o botão de limpar, como o protótipo (`modulos-page.jsx:311-315`). Os quatro campos pesquisados são os mesmos. | Nada — paridade. |
| Tabela de módulos | `Index.tsx:266-374` tem as sete colunas do protótipo, e as cinco com rótulo batem com o contrato (Módulo · Área · Status · Migrations · Ativo). O que não existe é ordenação: `grep -c 'toggleSort'` no `.tsx` devolve **0**, enquanto o protótipo torna três cabeçalhos clicáveis com marcador de direção (`modulos-page.jsx:341-346`). | **Decidir.** Região do mockup: `modulos-page.jsx:341-346` (os três `<th>` com botão) mais o reducer de ordenação em `:249-263`; ponto no vivo: `Index.tsx:267-275` (o `<thead>`). O contrato de tela trava só a copy das colunas, então isto não é violação de contrato — é capacidade do protótipo que a tela não tem. Ordenar é local (a lista já vem inteira), logo não exige backend. Construir ou rejeitar por escrito. |
| Estado vazio | `Index.tsx:279-284` mostra uma linha única na tabela com "Nenhum módulo encontrado nesse filtro." O protótipo tem um bloco próprio (`modulos-page.jsx:388-398`): outra frase ("Nenhum módulo nesse filtro."), um parágrafo que ecoa os filtros aplicados e um botão "Limpar busca e filtros". | **Decidir.** Região do mockup: `modulos-page.jsx:388-398`; ponto no vivo: `Index.tsx:279-284`. **Decisão já mapeada, não inventada:** o `modulos.contract.json` recortou esta seção de propósito (`_nota_recorte`) e o charter a reserva como `[BACKLOG]` UC-MOD-10 — o contrato entra quando a tela alcançar o desenho. Construir ou rejeitar por escrito; se construir, a copy do contrato passa a valer e a seção volta pro `.contract.json`. |
| Drawer de detalhe | Não existe no vivo — `grep -c 'Drawer'` no `.tsx` devolve **0**. O protótipo tem um painel lateral PT-02 completo (`modulos-page.jsx:143-194`): identidade, selos de status, descrição, lista de dados com a contagem de migrations, nota e rodapé de ações; é aberto pela linha (`:403`). | **Decidir.** Região do mockup: `modulos-page.jsx:143-194`; ponto de entrada no vivo seria a linha da tabela em `Index.tsx:286-290`. **É a decisão D3 do charter**, explicitamente aberta e endereçada a [W], com `[BACKLOG]` reservado como UC-MOD-16 e o recorte registrado no `modulos.contract.json`. Não é achado novo: é a pendência nomeada, agora com âncora dos dois lados. Decidir. |
