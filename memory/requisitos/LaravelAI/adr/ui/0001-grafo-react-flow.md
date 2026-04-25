# ADR UI-0001 (LaravelAI) · React Flow para visualização de grafo

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: ui

## Contexto

Visualização de grafo precisa renderizar:
- Nodes coloridos por type (user, role, permission, resource, ADR)
- Arestas com labels (HAS_ROLE, CAN_ACCESS, etc.)
- Layout automático (force-directed ou hierárquico)
- Interatividade: zoom, pan, click, expand
- Performance até ~500 nodes simultâneos

Bibliotecas avaliadas:

| Lib | Stars | Tamanho | Pró | Contra |
|---|---|---|---|---|
| **React Flow** | 29k | ~150KB | API React-friendly, ativa, plugins | Performance cai > 1k nodes |
| **Cytoscape** | 10k | ~280KB | Mais features (algoritmos), maduro | API imperative, learning curve |
| **VisX (Airbnb)** | 19k | ~180KB | D3 wrapper, customizável | Sem layout pronto, mais código |
| **G6 (Antv)** | 11k | ~230KB | Chinese market dominante, ricas features | Docs mais EN limitada |
| **Sigma.js** | 11k | ~150KB | Performance excelente (>10k nodes), WebGL | Menos opinionated, sem React wrapper oficial |

## Decisão

**React Flow** como padrão para LaravelAI/graph.

Razões:
- API mais React-natural (estado-driven, hooks)
- Plugin ecossistema (minimap, controls, custom node types)
- Dagre layout plugin (hierárquico bom pra permissions)
- Comunidade ativa em 2026
- Documentação top-tier
- Performance OK até 500 nodes (suficiente pra MVP)

Quando avaliar mudar:
- Volume passar 1k nodes simultâneos
- Performance virar problema (FPS < 30)
- Precisar features avançadas que Cytoscape tem (algoritmos de communidade, etc.)

## Consequências

**Positivas:**
- Implementação rápida (estado React + memo)
- Custom nodes shadcn/ui-themed (consistência visual oimpresso)
- Plugins prontos pra zoom, pan, mini-map
- Click handlers triviais (hooks)
- Dark mode via Tailwind variables

**Negativas:**
- Performance limitada (>1k nodes começa a engasgar)
- Bundle size 150KB (lazy load só nessa rota — Inertia code-splits)
- Customização extrema requer conhecer SVG/HTML transforms

## Componentes React

```tsx
// resources/js/Pages/LaravelAI/Graph/Index.tsx
import { ReactFlow, MiniMap, Controls, Background } from 'reactflow';
import 'reactflow/dist/style.css';

function GraphPage({ initialNodes, initialEdges }: Props) {
  const [nodes, setNodes] = useState(initialNodes);
  const [edges, setEdges] = useState(initialEdges);

  return (
    <ReactFlow
      nodes={nodes}
      edges={edges}
      nodeTypes={{ user: UserNode, role: RoleNode, permission: PermissionNode, adr: AdrNode }}
      fitView
    >
      <Background variant="dots" />
      <MiniMap />
      <Controls />
    </ReactFlow>
  );
}

function UserNode({ data }: NodeProps) {
  return (
    <div className="bg-blue-100 border-2 border-blue-500 rounded p-2 dark:bg-blue-900">
      <span className="text-sm font-semibold">{data.label}</span>
    </div>
  );
}
```

## Layout pattern

- **Initial layout**: dagre (hierárquico) pra estrutura clara user → role → permission → resource
- **User pode toggle** pra force-directed (orgânico, melhor pra exploração)
- **Salvar layout** em `localStorage` por user

## Performance

- **Lazy load**: rota `/laravel-ai/graph` só carrega React Flow
- **Memoização**: `useMemo` em nodes filtrados
- **Pagination**: backend retorna max 500 nodes por request
- **Virtualization**: React Flow v11 tem viewport-only rendering (built-in)

## Tests obrigatórios

- E2E (Playwright): abrir `/laravel-ai/graph` → ver nodes → filtrar → click expande
- Component test (Vitest): UserNode renderiza com cor correta
- Snapshot test: layout dagre estável pra grafo conhecido

## Decisões em aberto

- [ ] Custom edge colors por relation type? Provável sim (CAN_ACCESS verde, GOVERNED_BY laranja)
- [ ] Animation em adicionar/remover nodes? Provável sim (UX agradável)
- [ ] Export PNG/SVG do grafo? Útil pra auditoria

## Alternativas consideradas

- **Cytoscape** — rejeitado: API imperative, mais código, learning curve maior
- **D3.js cru** — rejeitado: muita customização pra pouco benefício
- **Sigma.js** — interessante para escala futura; voltar quando >1k nodes

## Referências

- React Flow docs (https://reactflow.dev)
- Dagre layout (https://github.com/dagrejs/dagre)
- ARQ-0003 (Inertia + React)
- `_DesignSystem/adr/ui/0006-padrao-tela-operacional.md`
