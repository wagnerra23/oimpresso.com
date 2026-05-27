# Sessão 2026-04-28 — Protótipo de UX integrada (Chat + Tarefas + AppShell)

**Operador:** Claude (Sonnet 4.5)
**Solicitante:** Wagner
**Worktree:** ambiente de design (fora do repo Laravel)
**Output:** protótipo HTML interativo `Oimpresso ERP - Chat.html` (auto-contido, React via CDN)

---

## Pedido

Wagner pediu pra desenhar a nova UX do oimpresso.com cobrindo Chat + Tarefas (inbox unificada) + Menu, integrada à AppShell.tsx existente, antes de migrar do Blade.

## Decisões de design tomadas

### 1. Sidebar dual com toggle Chat ↔ Menu
- Toggle no topo da sidebar (estilo ChatGPT). Persistência: `localStorage["oimpresso.sidebar.tab"]`.
- **Topo da sidebar (NÃO ALTERAR):** seletor de empresa + filial (dropdown com avatar gradiente, lista, ação "Adicionar empresa"). Wagner aprovou em 2026-04-28 — manter esse padrão em todas as telas migradas.
- **Aba Chat:** lista enxuta de conversas em formato de bullet list (estilo da imagem de referência). Sem abas internas — as abas Todos/OS/Equipe/Clientes vivem na coluna do meio.
- **Aba Menu:** espelho fiel do AppShell.tsx — accordion de grupos vindos de `LegacyMenuAdapter` + `menu.php`. Nenhum re-aprendizado pelo cliente final.
- **Rodapé:** menu de usuário (perfil, status, aparência, atalhos, sair).

### 2. Tela Chat com layout de 3 colunas
| Coluna | Conteúdo | Largura |
|---|---|---|
| Esquerda | Sidebar (já descrita) | 260px |
| Meio | **Header da conversa + abas Todos / OS / Equipe / Clientes + thread + composer** | flex 1 |
| Direita | **Apps vinculados à conversa** (OS, CRM, PNT, FIN) — só aparece quando a conversa tem contexto | 320px |

- "Em uma tela tem tudo que o usuário precisa." (citação Wagner, 2026-04-28). Nada de abrir nova aba pra ver a OS — ela aparece na lateral.
- Apps vinculados são **componentes específicos por tipo** (ver `viewerComponent` no contrato `TaskProvider`).

### 3. Tela Tarefas (rota separada)
- Master/detail clássico: lista à esquerda, viewer à direita.
- Inbox unificada agregando todos os módulos via `TaskProvider`.
- Atalhos J/K (navegar), E (concluir), A (adiar).
- Filtros: Todas / Hoje / Atrasadas / Aprovações / Minhas.

### 4. Menu (Aba Menu da sidebar)
- Espelho fiel do AppShell. NÃO trocar labels, ordem ou ícones.
- Cliques abrem rotas tradicionais — viraram React conforme MWART (flag `inertia: true`).

## Tweaks expressivos disponíveis no protótipo

- **Vibe:** workspace / daylight / focus — reformula paleta + espaçamento + tom dos cards
- **Densidade:** Skim ↔ Briefing — controla altura de linhas e padding
- **Accent hue:** desliza 0–360° repintando accent + cores das origens (OS, CRM, FIN, PNT) proporcionalmente
- **Painel Laravel:** referência de migrations/rotas/event broadcast/echo snippet pra facilitar o port

## Contrato `TaskProvider` (a implementar no backend Laravel)

Cada módulo registra suas tarefas via interface única:

```php
// Modules/Officeimpresso/Tasks/OsAprovarArteTask.php
class OsAprovarArteTask implements TaskProvider {
  public function origin(): string { return 'OS'; }      // tag de origem
  public function color(): string  { return 'amber'; }   // cor do badge
  public function for(User $u): Collection { /* OS aguardando aprovação */ }
  public function viewerComponent(): string { return 'OsAprovarArte'; }
}
```

`TaskRegistry` agrega todos os providers ativos → endpoint `/api/tasks/inbox` → Inertia entrega para `Pages/Tarefas/Index.tsx`.

**Vantagem:** novo módulo = novo provider, sem tocar na tela de Tarefas.

## Componentes shared do projeto reutilizados conceitualmente

- `PageHeader`, `DataTable`, `PageFilters`, `KpiCard`
- `ModuleTopNav`, `StatusBadge`, `EmptyState`

## Princípios da nova UX (sintetizados)

1. **Sidebar dual com toggle Chat ↔ Menu no topo** — persiste em `localStorage`.
2. **Aba Menu = espelho fiel do AppShell atual** — mesma ordem/labels/ícones.
3. **Sem "projeto" no topo da sidebar.** Projeto vira breadcrumb dentro do header da página.
4. **"OS" no menu vira "Tarefas"** — inbox unificada agregando todos os módulos.
5. **Master/detail com viewer embutido.** Tarefa selecionada → painel direito mostra componente específico do tipo. Sem abrir novas telas.
6. **Atalhos de teclado obrigatórios:** J/K (navegar), E (concluir), A (adiar).
7. **Persistência total via localStorage:** empresa, aba, rota, conversa, filtro, tarefa selecionada — tudo sobrevive a F5.
8. **No Chat: abas Todos / OS / Equipe / Clientes na coluna do meio**, não na sidebar. Conversa sempre visível. Apps vinculados na lateral direita.

## Ordem de migração proposta

1. **Fase 1 (em andamento):** Refazer Chat sobre AppShell + tela de Tarefas unificada
2. **Fase 2:** Listagem de OS do Officeimpresso (piloto)
3. **Fase 3:** Telas de alta frequência — Clientes, Orçamentos, Produtos
4. **Fase 4:** Operacional de produção (fila, acabamento, expedição)
5. **Fase 5:** Decommission gradual do Blade

## Próximos passos

1. Validar protótipo com Eliana / equipe operacional WR2
2. Implementar interface `TaskProvider` em Laravel + `TaskRegistry` service
3. Criar `Pages/Tarefas/Index.tsx` no projeto real, importando padrões do protótipo
4. Migrar Chat existente pra layout 3 colunas
5. Adicionar atalhos J/K/E/A globais via hook `useKeyboardShortcuts`

## Refs

- Protótipo HTML — fora do repo (ambiente de design Claude)
- AppShell.tsx — `resources/js/Layouts/AppShell.tsx`
- LegacyMenuAdapter — backend que entrega `MenuItem[]` com flag `inertia`
- ADR a criar: **0039-padrao-mwart-task-provider.md** (formalizar contrato)

---

**Última atualização:** 2026-04-28 (sessão design Claude)
