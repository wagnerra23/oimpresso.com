---
id: requisitos-produtos-spec-consulta-produtos-v1-0
type: spec
module: Produtos
status: ativo
owner: wagner
version: 1.0.0
data: 2026-08-19
related_docs: [MANUAL-HANDOFF-DE-DESIGN.md]
---

# SPEC — Consulta de Produtos (`/products/unificado`) · v1.0

> Documento de contexto **congelado**. Mudança de comportamento gera v1.1, não edição no lugar.

## 1 · Base empírica <!-- derivado -->

A tela nasceu da recriação do índice unificado de produtos existente
(`Pages/Produto/Unificado/Index.tsx` + 8 componentes de apoio) e evoluiu por comparação direta com
a **Consulta de Contatos em produção** (`oimpresso.com/contacts`), usada como referência visual de
listagem: faixa de abas com contadores, cartões de KPI clicáveis, faixa de filtros compacta,
tabela densa e rodapé de paginação.

## 2 · Visão geral <!-- curado -->

Consulta de leitura, não de edição. O usuário chega com uma pergunta pontual e sai com a resposta
sem abrir o cadastro. A regra de desenho é **revelação progressiva**: a linha mostra o essencial;
variações, estoque por local e observações aparecem por hover, clique ou painel lateral.

## 3 · Personas e pesos <!-- curado -->

| Persona | Volume | Dimensões que pesam 3 |
|---|---|---|
| **Larissa · balcão** (persona primária) | ~80 consultas/dia | speed-to-task, discoverability, cognitive load, affordance, information hierarchy, microcopy, i18n |
| Rafael · compras | ~20 consultas/dia | density, information hierarchy |
| Wagner · dono | eventual | brand confidence, aesthetic-usability |

## 4 · Governança

- `[T0]` Isolamento por tenant em toda consulta, incluindo contadores agregados.
- `[V0]` Custo, margem e piso são dado sensível: recorte no servidor por perfil.

## 5 · Casos de uso

| ID | Caso | Estado |
|---|---|---|
| CU-PROD-01 | Listar catálogo unificado com contadores por tipo | ok |
| CU-PROD-02 | Recortar por aba (produto, serviço, matéria-prima, kit, inativo) | ok |
| CU-PROD-03 | Recortar por KPI (ativos, estoque baixo, sem estoque, sem venda, margem baixa) | ok |
| CU-PROD-04 | Filtrar por categoria, tipo, unidade, marca, estoque e margem | ok |
| CU-PROD-05 | Buscar por descrição, código, referência ou categoria (atalho `/`) | ok |
| CU-PROD-06 | Remover filtro individual por chip e limpar todos | ok |
| CU-PROD-07 | Ordenar por qualquer coluna, asc/desc | ok |
| CU-PROD-08 | Identificar o item por miniatura, nome, código, unidade e categoria | ok |
| CU-PROD-09 | Ver resumo de variações sob o nome quando aplicável | ok |
| CU-PROD-10 | Ver preço "a partir de" quando há tabela de preço ou variações | ok |
| CU-PROD-11 | Consultar faixas de preço por quantidade sem sair da lista | ok |
| CU-PROD-12 | Saber que o preço varia por cor/tamanho | ok |
| CU-PROD-13 | Consultar estoque por local sem coluna extra | ok |
| CU-PROD-14 | Ver alerta de zerado num local com saldo em outro | ok |
| CU-PROD-15 | Identificar item com observação e ler a prévia | ok |
| CU-PROD-16 | Ler a observação completa e o tipo crítico (sob encomenda, exige aprovação) | ok |
| CU-PROD-17 | Abrir painel lateral com variações, preços, estoque e observações | ok |
| CU-PROD-18 | Paginar (primeira, anterior, próxima, última) e trocar itens por página | ok |
| CU-PROD-19 | Ver custo e margem apenas com perfil autorizado | ok |
| CU-PROD-20 | Alcançar as sub-telas (categorias, insumos, tabelas de preço, histórico) | ok |
| CU-PROD-21 | Favoritar item / abrir ações da linha | **pendente** (sem comportamento) |
| CU-PROD-22 | Colapsar filtros em telas estreitas | **pendente** |
| CU-PROD-23 | Estados de carregamento, erro e sem permissão | **pendente** |

## 6 · Non-goals

- Editar produto na lista (edição em linha, preço inline).
- Seleção múltipla e ações em massa (`BulkBar`).
- Importação/exportação real.
- Visão de grade/cartões.

## 7 · Requisitos não-funcionais

- Alvo: cockpit desktop, largura útil ≥ 1000px.
- Primeira pintura sem esperar dado: a lista tem estrutura antes dos valores.
- Nenhum overlay pode ser cortado por contêiner de rolagem.
- Números sempre tabulares; moeda em BRL `R$ 1.234,56`.

## 8 · Riscos e dívidas

| ID | Dívida | Impacto |
|---|---|---|
| D-1 | Cinco cores cruas fora de token (ADR 0401) | tema escuro reprova |
| D-2 | `Pagination`, `TabBar` e `DropdownMenu` do DS não atendem; três exceções a AP2 | divergência se o DS evoluir |
| D-3 | Faixas de preço derivadas do preço, não da tabela real | número ilustrativo |
| D-4 | Sem colapso responsivo dos filtros | uso em notebook estreito |
| D-5 | Sem estados de carga/erro | percepção de travamento |

## 9 · Rollout

1. Reimplementar a tela com dado real e paginação server-side.
2. Resolver ADR 0401 (tokenizar as cinco cores) antes de habilitar tema escuro.
3. Ligar CU-PROD-21 (ações da linha) com as policies reais.
