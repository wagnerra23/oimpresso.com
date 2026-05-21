# Visual Comparison — `/cliente/{id}` vs `/contacts/{id}` (Blade legacy)

> Esta comparação é um **redirect canon** pra `memory/requisitos/Crm/cliente-show-visual-comparison.md`. Page React = `resources/js/Pages/Cliente/Show.tsx`; módulo canônico = Crm (ADR 0149). Não duplicar conteúdo aqui.

## Visual comparison canônica

→ **[memory/requisitos/Crm/cliente-show-visual-comparison.md](../Crm/cliente-show-visual-comparison.md)**

## Matriz de paridade (snapshot 2026-05-21 — pós Wave US-CRM-063..067)

**Antes da Wave:** ~40% paridade funcional vs Blade.
**Pós Wave:** ~85% paridade funcional.

### Header / topo

| Item | Blade | React (pós-Wave) | Peso | Nota |
|---|:-:|:-:|:-:|:-:|
| Selector contact picker (trocar sem voltar) | ✅ | ❌ | 3 | 0/10 |
| Tipo (badge) | ✅ | ✅ | 1 | 10/10 |
| Endereço completo | ✅ | ✅ aside | 2 | 9/10 |
| Mobile/Celular | ✅ | ✅ aside | 2 | 10/10 |
| Add Discount (W-E US-067) | ✅ | ✅ modal | 4 | 10/10 |
| Botão Editar | ✅ | ✅ topo | 2 | 10/10 |
| Menu ações 8 itens (W-E US-067) | ✅ | ✅ dropdown | 5 | 9/10 |
| 4 StatCards | ❌ | ✅ | 3 | 10/10 |
| Loading skeletons | ❌ | ✅ Deferred | 2 | 10/10 |
| Badge "Inativo" | ❌ | ✅ | 1 | 10/10 |

**Subtotal Header pós-Wave:** ~87/100 (era 54)

### Tabs

| Tab | Blade | React (pós-Wave) | Peso | Nota |
|---|:-:|:-:|:-:|:-:|
| Ledger (W-B US-064) | ✅ | ✅ range + formato + export | 5 | 9/10 |
| Vendas (W-C US-065) | ✅ DataTable | ✅ Inertia partial reload + filtros | 5 | 9/10 |
| Pagamentos (W-A US-063) | ✅ | ✅ self-fetch | 5 | 9/10 |
| Documents & Note (W-D US-066) | ✅ | ✅ upload + autosave | 4 | 9/10 |
| Atividades | ✅ | ❌ escopo futuro | 3 | 0/10 |
| Pessoas de contato | ✅ | ❌ escopo futuro | 3 | 0/10 |
| Assinaturas | ✅ | ❌ escopo futuro | 2 | 0/10 |
| Reward Points | ✅ | ❌ escopo futuro | 1 | 0/10 |
| Resumo conta period-bounded | ✅ | ⚠️ LedgerTab abre legacy | 3 | 6/10 |

**Subtotal Tabs pós-Wave:** ~62/100 (era 11)

### Visual / UX

| Item | Blade | React | Peso | Nota |
|---|:-:|:-:|:-:|:-:|
| Modernidade Anthropic 2026 | 3/10 | 9/10 | 2 | 9/10 |
| Densidade | 4/10 | 9/10 | 2 | 9/10 |
| Mobile ≤640px | 4/10 | 8/10 | 2 | 8/10 |
| Acessibilidade ARIA | 5/10 | 9/10 (role=tab/tabpanel) | 2 | 9/10 |
| Dark mode | ❌ | ✅ | 1 | 10/10 |
| PII masked | ⚠️ plain | ✅ tax_number_masked | 3 | 10/10 |

**Subtotal Visual:** 90/100 (manteve)

### Nota consolidada pós-Wave

| Dimensão | Nota | Peso | Contribuição |
|---|:-:|:-:|:-:|
| Header | 87 | 20% | 17.4 |
| Tabs | 62 | 40% | 24.8 |
| Filtros/interações | 70 | 15% | 10.5 |
| Visual/UX | 90 | 15% | 13.5 |
| Segurança Tier 0 | 100 | 10% | 10.0 |

### **Nota final pós-Wave: 76.2 / 100** (era 38.7 pré-Wave)

## Gaps remanescentes (~25% restante)

| US futura | Gap | Prioridade |
|---|---|---|
| Atividades | Activity log inline (audit + suporte) | P1 |
| Pessoas de contato | Sub-contatos do cliente | P2 |
| Assinaturas | Recorrência | P3 |
| Reward Points | Condicional rp_enabled | P3 |
| Contact picker header | Trocar contato sem voltar | P2 |
| Ledger inline completo | Render dados em-tela (não abrir legacy) | P2 |

---

_Pointer-comparison criado 2026-05-21 pra resolver MWART gate path mismatch. Comparação canônica permanece em Crm/cliente-show-visual-comparison.md._
