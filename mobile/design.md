# Design da Interface Móvel - Mini ERP

## Visão Geral
Um ERP profissional otimizado para dispositivos móveis em orientação retrato (9:16), com foco em navegação intuitiva com uma mão e feedback visual claro.

## Paleta de Cores
- **Primária**: `#0a7ea4` (Azul profissional)
- **Sucesso**: `#22C55E` (Verde)
- **Aviso**: `#F59E0B` (Âmbar)
- **Erro**: `#EF4444` (Vermelho)
- **Fundo**: `#ffffff` (Light) / `#151718` (Dark)
- **Superfície**: `#f5f5f5` (Light) / `#1e2022` (Dark)
- **Texto Primário**: `#11181C` (Light) / `#ECEDEE` (Dark)
- **Texto Secundário**: `#687076` (Light) / `#9BA1A6` (Dark)

## Estrutura de Telas

### 1. Dashboard (Visão Geral)
**Conteúdo:**
- Métricas resumidas em cards (Produtos, Vendas, Produção, Financeiro)
- Lista de pedidos recentes com data, cliente, valor e status
- Indicadores visuais de performance

**Funcionalidade:**
- Toque em uma métrica para navegar para a seção correspondente
- Toque em um pedido para ver detalhes

### 2. Produtos
**Conteúdo:**
- Tabela scrollável com: Nome, Categoria, Preço, Ações
- Formulário destacado em card para adicionar novo produto
- Campos: Nome, Categoria (select), Preço
- Validação visual de campos obrigatórios

**Funcionalidade:**
- Adicionar produto com validação
- Deletar produto com confirmação
- Ordenação por coluna (Nome, Preço)

### 3. Vendas (Kanban)
**Conteúdo:**
- Abas com badges de contagem: Novo (3), Aprovado (2), Em Execução (1), Entregue (4)
- Cards de pedidos em colunas Kanban
- Modal para novo pedido com validação visual
- Confirmação ao fechar modal sem salvar

**Funcionalidade:**
- Criar novo pedido (cliente, produto, valor)
- Arrastar cards entre colunas
- Confirmação ao sair do modal sem salvar
- Histórico de notificações (toast com permanência)

### 4. Produção (Kanban)
**Conteúdo:**
- Colunas: Fila, Em Andamento, Revisão, Concluído
- Cards com: Produto, Cliente, Pedido ID (com tooltip ao passar mouse)
- Indicação visual de qual pedido originou a OP

**Funcionalidade:**
- Gerar OP de pedidos aprovados
- Arrastar cards entre colunas
- Hover mostra pedido de origem

### 5. Financeiro
**Conteúdo:**
- Resumo de receitas (total, mês atual, pendente)
- Gráfico de receitas por mês
- Lista de transações com filtros
- Formulário para adicionar transação

**Funcionalidade:**
- Visualizar receitas por período
- Filtrar transações por tipo e data
- Adicionar nova transação

## Fluxos Principais

### Fluxo de Venda
1. Dashboard → Clica em "Vendas"
2. Clica em "+ Novo Pedido"
3. Preenche: Cliente, Produto, Valor
4. Validação visual de campos obrigatórios
5. Clica "Salvar"
6. Notificação de sucesso (permanece visível)
7. Card aparece em "Novo"

### Fluxo de Produção
1. Dashboard → Clica em "Produção"
2. Clica em "Gerar OP de pedidos aprovados"
3. Novos cards aparecem em "Fila"
4. Arrasta para "Em Andamento"
5. Hover mostra "Pedido #101" de origem

### Fluxo de Financeiro
1. Dashboard → Clica em "Financeiro"
2. Visualiza resumo de receitas
3. Clica em "+ Nova Transação"
4. Preenche dados com validação
5. Notificação de sucesso

## Componentes Reutilizáveis

| Componente | Uso |
|-----------|-----|
| `Card` | Exibir dados em containers destacados |
| `Badge` | Contagem de itens nas abas |
| `Modal` | Formulários e confirmações |
| `Toast` | Notificações com histórico |
| `Table` | Listas de produtos com ordenação |
| `KanbanBoard` | Colunas de status com drag-and-drop |
| `MetricCard` | Resumo de números no dashboard |

## Validações e Feedback

- **Campos obrigatórios**: Borda vermelha + ícone de erro
- **Sucesso**: Toast verde com ícone ✓
- **Erro**: Toast vermelho com ícone ✗
- **Histórico**: Notificações permanecem visíveis por 5 segundos
- **Confirmação**: Modal ao sair sem salvar

## Considerações de UX

- Navegação por abas na base (tab bar)
- Badges de contagem nas abas para contexto rápido
- Drag-and-drop para Kanban em mobile (com feedback visual)
- Formulários em modals destacados
- Confirmações antes de ações destrutivas
- Histórico de notificações acessível
