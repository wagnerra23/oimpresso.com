---
id: requisitos-financeiro-cobranca-index-gap
tela: Financeiro/Cobranca/Index (/financeiro/cobranca)
prototipo: prototipo-ui/cowork/pg-cobranca-page.jsx
tela_viva: resources/js/Pages/Financeiro/Cobranca/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Financeiro/Cobranca/Index

> ⚠️ **Tier 0 — esta tela mostra e origina cobrança.** Emitir, estornar, cancelar ou mudar conta
> destino mexe em dinheiro real. Toda ação marcada **Decidir.**, quando construída, exige a
> regra-mestre do projeto **antes do merge**: provar o efeito por dois caminhos independentes e
> apresentar a tabela antes→depois dos registros afetados, com aprovação [W] explícita. Nenhum
> valor monetário aparece neste documento.

## ⚠️ A âncora que o charter declara está DESATUALIZADA — e este gap NÃO usa ela

O charter aponta `related_prototype: prototipo-ui/cowork/prototipos/payment-gateway-ui/cobranca-page.jsx`.
Existem **duas cópias** do mesmo protótipo no espelho, e essa é a antiga. Medido:

| | `prototipos/payment-gateway-ui/cobranca-page.jsx` (charter) | `pg-cobranca-page.jsx` (bundle ativo) |
|---|---|---|
| Linhas | 830 | **998** |
| Bytes | 48.567 | **58.317** |
| Persistência dos filtros | não tem | `localStorage` no namespace `oimpresso.financeiro.cobranca.*` |
| `AiResumoMes` | ausente | presente (`:912`) |
| `CheatSheet` (atalho `?`) | ausente | presente (`:341`) |
| Está no bundle ativo do design-sync? | **não** | **sim** |

**Três provas de que a tela viva derivou da segunda, não da primeira:** o `Index.tsx:74` usa o
**mesmo namespace literal** de `localStorage`; o `Index.tsx:477` monta `AiResumoMes`; o
`Index.tsx:476` monta `CheatSheet`. Nenhuma das três existe na cópia que o charter cita.

Por isso a tabela abaixo mede contra `pg-cobranca-page.jsx`. **Trocar o `related_prototype` do
charter é decisão [W]** (o charter é lei) — aqui fica o achado com os números, não a mudança. As
duas cópias entraram no mesmo commit (`9da73296d3`, 2026-06-23), o que sugere cópia herdada, não
divergência de conteúdo deliberada. Consequência prática: o `gerar-map.mjs` avisa que a âncora
computada do charter não cita este arquivo — o aviso é o registro da divergência, não um defeito.

> **Uma quarta forma de veredito aparece nesta tabela: `Nada — decisão registrada`.** Parte do que
> o protótipo desenha **foi removida de propósito** e a razão está escrita no código, datada — o
> bloco "botões honestos" de 2026-05-31 (`_components/DrawerCobranca.tsx:118-123` e
> `Index.tsx:282-286`). Carimbar **Decidir.** ali reabriria decisão fechada.
>
> **A capacidade mora em sete arquivos.** Além do `Index.tsx` (536 ln), medi `_components/`:
> `DrawerCobranca` (306) · `SheetNovaCobranca` (242) · `AiResumoMes` (132) · `SheetRemessaRetorno`
> (83) · `FunnelStrip` (43) · `CheatSheet` (43) · `atoms` (172).
>
> **Non-Goals do charter, que NÃO viram gap:** mutação no GET, paginação clicável, exportar CSV ou
> PDF, atualização em tempo real, edição inline, layout de celular, comentários por linha,
> ordenação clicável de coluna, botão de estorno real, e o funil "Protesto" com job real.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | `Index.tsx:190-217` usa o `PageHeader` canônico v3 (ADR 0180/0190) e distribui as quatro ações do protótipo (`pg-cobranca-page.jsx:148-160`): "Nova cobrança" como botão primário, e "Resumir mês", "Gateways" e "Remessa/Retorno" no menu de excedente da sub-navegação (`Index.tsx:209-211`). A contagem "N em aberto" está no subtítulo. | Nada — paridade. A realocação para o menu de excedente é conformidade com o cabeçalho canônico, posterior ao protótipo; as quatro ações continuam alcançáveis. O contador "sync 09:14" do protótipo é instrumento de mockup. |
| Funil de cobrança | `Index.tsx:219-224` renderiza o `FunnelStrip` dentro de `Inertia::defer` com esqueleto; o componente (`_components/FunnelStrip.tsx`) tem as mesmas etapas do protótipo (`pg-cobranca-page.jsx:164-167`, componente em `:376-410`). | Nada — paridade. |
| Cartões de indicador | `Index.tsx:226-246` traz os mesmos quatro cartões do protótipo (`pg-cobranca-page.jsx:169-177`): três fixos (Pago no mês, Vencido, Em aberto) e um contextual, com os mesmos tons. O vivo os defere; o protótipo os calcula em memória. | Nada — paridade. |
| Abas de status e busca | `Index.tsx:248-286` tem as mesmas abas de status e o mesmo campo de busca do protótipo (`pg-cobranca-page.jsx:179-209`). O botão "Exportar" que o protótipo põe ao lado da busca (`:208`) não existe no vivo. | Nada — decisão registrada. O `Index.tsx:282-286` documenta a remoção ("botões honestos", 2026-05-31) e o charter lista exportar CSV ou PDF como Non-Goal, com o backend em backlog de onda. Reentra quando o endpoint existir — não é decisão pendente desta tabela. |
| Filtros por tipo, gateway, conta e origem | `Index.tsx:287-381` cobre os filtros do protótipo (`pg-cobranca-page.jsx:211-259`), com a mesma persistência em `localStorage` (mesmo namespace), e acrescenta o intervalo de vencimento, pedido por [W] em 2026-06-29 e anotado no próprio arquivo (`Index.tsx:319`). | Nada — vivo à frente. |
| Tabela de cobranças | `Index.tsx:382-471` tem a tabela do protótipo (`pg-cobranca-page.jsx:261-327`): linha inteira abre o drawer, mesmas colunas, e o estado vazio distingue o caso "filtro de gateway" do caso "filtro comum" (`Index.tsx:400`, componente em `:501-515`) — o mesmo par de causas do protótipo (`:411-435`). | Nada — paridade. |
| Drawer da cobrança | `_components/DrawerCobranca.tsx` repete a estrutura do protótipo (`pg-cobranca-page.jsx:436-533`): cabeçalho, origem, dados principais, render condicional por tipo, erro e linha do tempo. O rodapé é que difere: o protótipo oferece cinco ações (`:519-527` — baixar PDF, link de segunda via, copiar BR Code, estornar, cancelar) e o vivo mantém só "Copiar BR Code". | Nada — decisão registrada. `DrawerCobranca.tsx:118-123` nomeia as quatro ações removidas e a razão de cada uma: sem endpoint de PDF de boleto no controller, sem rota de estorno, e o cancelamento existente aponta para o model legado, não para a cobrança desta tela. O charter reforça duas delas como Non-Goal. A condição de reabertura já está escrita: reentram quando o endpoint existir — e aí, por mexerem em dinheiro, sob dupla confirmação e tabela antes→depois. |
