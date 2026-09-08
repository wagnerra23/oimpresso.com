---
id: requisitos-governance-policies-gap
tela: governance/Policies (/governance/policies)
prototipo: prototipo-ui/cowork/governance-page.jsx
tela_viva: resources/js/Pages/governance/Policies.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — governance/Policies

> Protótipo = porte REVERSO do vivo (governance-page.jsx:1-3 "Espelha as telas vivas"; retrato de ~2026-08-23). Fase 1 = PARIDADE. Charter: `resources/js/Pages/governance/Policies.charter.md` (Non-Goals respeitados, nunca reabertos).

**Veredito:** PARIDADE com 2 itens a decidir — o retrato acrescenta ao vivo uma busca local e um aviso de "toggle sem histórico"; tudo o mais é o vivo, ou o vivo à frente.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header / PageHeader | `Policies.tsx:71-75` — `<PageHeader icon="settings" title="Policies (Governança)" description=…>`; layout `AppShellV2` em `:123`. Mockup: `governance-page.jsx:403-418` (h1 `TITULOS.politicas` + subtítulo de rota + selo `superadmin · cross-tenant`) | Nada — paridade (títulos adaptados; a mesma cabeça de página nos dois lados) |
| Abas do shell (sub-navegação) | `Policies.tsx:70` `<GovernancaSubNav active="policies" />`; a lista de abas vem do DataController (`_shared/GovernancaSubNav.tsx:16-17`, "NÃO duplicar a lista aqui"). Mockup: `governance-page.jsx:24-30` (`VIEWS` com 5 abas) + `:420-424` (`gov-tabs`) | Nada — paridade (mesmas vistas; no vivo a lista é derivada, não fixa) |
| KPIs (4 cards) | `Policies.tsx:77-82` `KpiGrid cols=4`: Rules total · Ativas · Triggered total · Categorias. Mockup: `governance-page.jsx:324-329` (Regras no total · Ativas · Disparos · Categorias) | Nada — paridade (mesmos 4 indicadores; só o rótulo difere) |
| Aviso "Alternar não deixa rastro" | Mockup: `governance-page.jsx:331-335` (`A.Nota tone="warn"` explicando que o toggle muda o enforcement sem entrar no histórico) · Vivo: nenhum aviso — a descrição do header (`Policies.tsx:74`) fala só de toggle/edição; `histor／rastro／history` → 0 hits | **Decidir.** O mockup (`governance-page.jsx:331-335`) desenha uma nota de aviso ao operador sobre a ausência de `mcp_governance_rule_history` (o charter registra o TODO em §UX Anti-patterns, `Policies.charter.md:62`, mas não decide sobre avisar na tela); o `Policies.tsx:68-119` não tem nota nenhuma entre KPIs e lista. Construir ou rejeitar por escrito. |
| Busca de políticas (chave/nome/categoria) + vazio "no-results" | Mockup: `governance-page.jsx:337-342` (input + hint "Desligadas continuam na lista") e `:346-348` (`A.Vazio variant="no-results"` com botão "Limpar busca") · Vivo: sem campo de busca nem filtro — `busca／search／filtr／<input` → 0 hits em `Policies.tsx` | **Decidir.** Busca local por `rule_key`/`name`/`category` (mockup `governance-page.jsx:302-306` filtra e `:337-342` desenha) ausente no `Policies.tsx:84-118`; não é Non-Goal (o charter só proíbe ESCONDER desligadas — a busca mantém todas até ser digitada). Construir ou rejeitar por escrito. |
| Lista agrupada por categoria (ordenação ativas → categoria → chave) | `Policies.tsx:84-118` `rules_by_category.map` → `Card` + `h3 capitalize` por categoria; ordenação vem do backend (charter §Goals). Mockup: `governance-page.jsx:302-306` (mesma ordenação em memória) + `:350-351` (h3 com contador de regras do grupo). Contador por grupo no vivo: `rules\.length` → 0 hits | Nada — paridade (mesmo agrupamento e ordem; o contador do grupo é rótulo, não capacidade) |
| Linha da regra (chave mono · nome · descrição · versão · disparos · estado) | `Policies.tsx:102-110` — `rule_key` font-mono, `name`, `description`, `Badge outline vN`, `N hits`; estado via `Switch checked` (`:94-95`). Mockup: `governance-page.jsx:354-371` (mesmos campos + `Selo` textual "Ativa/Desligada"; `Selo／Ativa"／Desligad` → 0 no vivo) | Nada — paridade (o estado é exibido pelo próprio Switch; o selo textual é rótulo redundante) |
| Toggle por linha | `Policies.tsx:52-66` — `router.post('/governance/policies/{id}/toggle')` com `preserveScroll`+`preserveState`, estado otimista (`:47-55`), rollback em `onError` (`:60-63`) e Switch desabilitado enquanto pendente (`:96`). Mockup: `governance-page.jsx:317-320` (só estado local) | Nada — vivo à frente (persistência real + otimista + rollback + trava de duplo clique; o retrato só alterna em memória) |
| Feedback do toggle (toast/flash) | Vivo: `Policies.tsx:62` `toast.error(...)` só no erro; sucesso é flash de sessão em `Modules/Governance/Http/Controllers/PoliciesController.php:65` `back()->with('status', "Policy #N ativada/desativada")`, lido por `resources/js/app.tsx:55-60` via `HandleInertiaRequests.php:99-113`. Mockup: `governance-page.jsx:319` (toast local) + `:436` (render). Se o flash chega ao React é pergunta de runtime (smoke), não de leitura | Nada — decisão já registrada (charter §UX Targets: flash "Policy #X ativada/desativada" via `back()->with('status')`); se o flash chega ao React é pergunta de smoke, fora da Fase 1 |
| Estado vazio "sem regras" | `Policies.tsx:84-85` `<EmptyState title="Sem rules ainda" …>` quando `rules_by_category.length === 0`. Mockup: não desenha (o `POLITICAS` mock nunca é vazio; só existe o vazio de busca em `:346-348`) | Nada — vivo à frente (vazio de catálogo existe só no vivo) |

## Recibos de ausência
- `grep -nEi 'histor|rastro|history' resources/js/Pages/governance/Policies.tsx` → 0
- `grep -nEi 'busca|search|filtr|<input' resources/js/Pages/governance/Policies.tsx` → 0
- `grep -nE 'rules\.length' resources/js/Pages/governance/Policies.tsx` → 0
- `grep -nE 'Selo|Ativa"|Desligad' resources/js/Pages/governance/Policies.tsx` → 0
- `grep -nE 'toast\.success|flash' resources/js/Pages/governance/Policies.tsx` → 0
