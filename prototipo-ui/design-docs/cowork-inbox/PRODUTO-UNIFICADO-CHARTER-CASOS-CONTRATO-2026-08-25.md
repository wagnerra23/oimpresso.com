# Pedido pro Code — Catálogo Unificado (Produto): charter + casos + contrato de tela

**Origem:** Cowork [CC] · 2026-08-25 · onda 3 do import de `resources/js/Pages/Produto/Unificado/Index.tsx`
**Motivo:** a tela viva existe no `main` **sem** `.casos.md` e **sem** contrato — não fecha o trio do `scripts/qa/prototipo-readiness.mjs`. O charter existe (`Unificado/Index.charter.md`) mas não cobre busca, paginação, estados nem permissão de custo, que o protótipo agora exercita.
**Não escrevi no git** (só leio). Isto é o pedido; a redação abaixo é ponto de partida, não canon.

## 1) Complementar `resources/js/Pages/Produto/Unificado/Index.charter.md`

Seções a acrescentar (o resto do charter fica como está):

- **Busca (6 campos)** — nome · id do produto · **`sub_sku` da variação** (a etiqueta do balcão traz o SKU da variação, não o do pai — `Produto/Index.casos.md` UC-PIDX-02) · marca · categoria · OEM. Busca vazia = catálogo inteiro; busca sem resultado = empty-state `no-results` com "Limpar filtros", **nunca** lista vazia calada.
- **Inativos** — fora por padrão; toggle visível na toolbar traz de volta e a linha ganha selo `inativo` (UC-PIDX-07).
- **Paginação** — 12 por página em Produtos e Histórico, com meta "N–M de T". Trocar sub-tela/filtro volta pra página 1.
- **Permissão de custo** — as colunas Custo·margem e o KPI "Margem média" exigem `product.view_purchase_price`. Sem ela: coluna ausente, KPI "—", e o toggle "Mostrar custo" **desabilitado com motivo** (não escondido). Sem `product.view`: tela inteira em empty-state `no-perm`.
- **Estados** — `dados` · `carregando` (skeleton de linhas, respeita `prefers-reduced-motion`) · `vazio` (primeiro uso → "Novo produto") · `erro` (a consulta falhou, **nada foi alterado**, ação "Tentar de novo").
- **Detalhe = drawer PT-02**, nunca modal full-screen: cabeçalho com SKU + nome + categoria/unidade/prazo; KPIs (preço, custo·margem se permitido, estoque, saídas 30d); seções Variações · Preço por tabela · Composição/BOM · Últimas saídas; rodapé fixo "Usar na venda" / "Editar produto". `Esc` e clique no scrim fecham.
- **Estoque** — enquanto o controller não somar `variation_location_details.qty_available`, a coluna mostra `—` (desconhecido), **nunca `0`**. Manter a nota que já está no `.tsx`.

## 2) Criar `Unificado/Index.casos.md` — UCs mínimos

| UC | Persona | O que trava |
| --- | --- | --- |
| UC-PUNI-01 | Larissa | Busca por `sub_sku` da etiqueta (`CAM-AZ-M`) acha o produto-pai. |
| UC-PUNI-02 | Larissa | Produto recém-cadastrado aparece na lista/paginação — se cair fora, ela cadastra duplicado. |
| UC-PUNI-03 | qualquer tenant | Nenhuma das 5 sub-telas (produtos, categorias, insumos, tabelas, histórico) vaza dado de outro `business_id`. É o pior bug do projeto. |
| UC-PUNI-04 | balconista sem `view_purchase_price` | Custo/margem não aparecem em nenhuma sub-tela — inclusive na coluna Margem de Tabelas de preço e no KPI. |
| UC-PUNI-05 | qualquer operador | Abrir o catálogo é consulta: não muta `updated_at` nem gera evento de auditoria. |
| UC-PUNI-06 | Larissa | Filtro de tipo persistido não pode truncar o catálogo sem controle visível na tela (regressão real vista no protótipo em 2026-08-25). |
| UC-PUNI-07 | Larissa 1280px | 5 KPIs + tabela sem scroll horizontal em 1280; abaixo disso a tabela rola, o nome do produto trunca com ellipsis + `title`, e nada é cortado por `overflow:hidden`. |
| UC-PUNI-08 | operador | Tabela de preço é multiplicador sobre o balcão; margem por tabela recalcula com o custo real (nunca margem do balcão repetida). |

## 3) Criar `prototipo-ui/contrato/produto-unificado.contract.json` (ADR 0286)

```json
{
  "tela": "Produto/Unificado/Index",
  "rota": "/products/unificado",
  "secoes": ["page-header", "subnav", "toolbar", "kpi-strip", "conteudo", "paginacao", "drawer-detalhe"],
  "copy": {
    "titulo": "Catálogo",
    "subtelas": ["Produtos", "Categorias", "Insumos · BOM", "Tabelas de preço", "Histórico de uso"],
    "tipos": ["Todos", "Produto", "Serviço", "Composição"],
    "kpis": ["Catálogo ativo", "Populares · 30d", "Saídas em 30 dias", "Margem média", "Sem giro"],
    "kpi_subs": { "populares": "≥30 saídas/mês", "sem_giro": "0 saídas em 30d" },
    "acoes": ["Importar", "Novo produto", "Usar na venda", "Editar produto"],
    "custo_toggle": "Mostrar custo",
    "vazio_busca": "Nenhum produto encontrado",
    "vazio_primeiro": "Seu catálogo está vazio",
    "erro": "Não deu pra carregar o catálogo",
    "sem_perm": "Você não tem acesso ao catálogo"
  },
  "estados": ["dados", "carregando", "vazio", "erro", "sem-perm"],
  "invariantes": [
    "estoque desconhecido imprime '—', nunca 0",
    "custo/margem só com product.view_purchase_price",
    "detalhe em drawer lateral, nunca modal full-screen",
    "sem cor crua fora dos tokens; sidebar preta nos 2 modos"
  ]
}
```

## 4) Fora de escopo deste pedido

Somar `variation_location_details.qty_available` no `ProdutoUnificadoController` (a coluna Estoque). É PR separado, com caso próprio — o protótipo já mostra número porque é mock declarado (`PROD_METRICS` em `data-orc-prod.jsx`).
