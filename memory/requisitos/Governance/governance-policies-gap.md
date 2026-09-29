---
id: requisitos-governance-policies-gap
tela: governance/Policies (/governance/policies)
prototipo: prototipo-ui/cowork/Wagner/governance-page.jsx
tela_viva: resources/js/Pages/governance/Policies.tsx
gerado_em: 2026-09-29
---

# GAP-SPEC — governance/Policies

> Protótipo = porte REVERSO do vivo (governance-page.jsx:1-3 "Espelha as telas vivas"; retrato de ~2026-08-23). Fase 1 = PARIDADE. Charter: `resources/js/Pages/governance/Policies.charter.md` (Non-Goals respeitados, nunca reabertos).

**Veredito:** PARIDADE — os 2 itens que o retrato acrescentava ao vivo (busca local e aviso de "toggle sem histórico") foram CONSTRUÍDOS em 2026-09-09 (bloco abaixo); tudo o mais é o vivo, ou o vivo à frente. _(Até 2026-09-09 o veredito era "PARIDADE com 2 itens a decidir".)_

> **Decidido em 2026-09-09** (os dois itens CONSTRUÍDOS — front puro, zero fonte nova):
> - **Busca local.** `Policies.tsx` filtra `rule_key`/`name`/`description`/`category` em memória sobre o catálogo já carregado, com vazio `no-results` + "Limpar busca". O anti-hook do charter ("❌ Esconder rules disabled") fica respeitado por construção: sem termo digitado a lista volta inteira — o filtro é ação explícita, e o hint na toolbar diz isso.
> - **Aviso "Alternar não deixa rastro".** A afirmação foi **verificada antes de virar UI**: `mcp_governance_rule_history` tem ZERO migration no repo (só o TODO em `PoliciesController.php:19` e `PolicyToggleService.php:17`). O aviso torna visível ao operador o anti-hook que o charter já registrava ("❌ Toggle sem registrar histórico — sem isso, audit fica cego"). O bloco sai junto no dia em que a tabela existir (comentário no código diz isso).
>
> Travados por `tests/js/governance-filtros.test.tsx` — o e2e existente **não alcança** estes casos: `e2e/governance-policies.spec.ts` declara que nenhum seeder popula `mcp_governance_rules`, então a tela cai no `EmptyState` e os casos usam `test.skip`.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header / PageHeader | `Policies.tsx:94-98` — `<PageHeader icon="settings" title="Policies (Governança)" description=…>`; layout `AppShellV2` em `:199`. Mockup: `governance-page.jsx:404-415` (h1 `TITULOS.politicas` + subtítulo de rota + selo `superadmin · cross-tenant`) | Nada — paridade (títulos adaptados; a mesma cabeça de página nos dois lados) |
| Abas do shell (sub-navegação) | `Policies.tsx:93` `<GovernancaSubNav active="policies" />`; a lista de abas vem do DataController (`_shared/GovernancaSubNav.tsx:16-17`, "NÃO duplicar a lista aqui"). Mockup: `governance-page.jsx:25-30` (`VIEWS` com 4 abas — 2026-09-29: "Notas dos módulos" saiu, `:5`; eram 5 em 2026-09-06) + `:417-418` (`gov-tabs`) | Nada — paridade (mesmas vistas; no vivo a lista é derivada, não fixa) |
| KPIs (4 cards) | `Policies.tsx:100-105` `KpiGrid cols=4`: Rules total · Ativas · Triggered total · Categorias. Mockup: `governance-page.jsx:325-330` (Regras no total · Ativas · Disparos · Categorias) | Nada — paridade (mesmos 4 indicadores; só o rótulo difere) |
| Aviso "Alternar não deixa rastro" | Vivo (re-medido 2026-09-29): `Policies.tsx:113-123` — `<Alert>` com `AlertTitle` "Alternar não deixa rastro" (`:116`), renderizado só quando há regras (`:113`); o comentário `:107-112` amarra a saída do bloco à criação de `mcp_governance_rule_history` (citada em `PoliciesController.php:19` e `PolicyToggleService.php:17`). Mockup: `governance-page.jsx:332-336` (`A.Nota tone="warn"`, mesmo título e texto) | Nada — paridade: CONSTRUÍDO em 2026-09-09 (#7089, bloco "Decidido em 2026-09-09" acima); o aviso torna visível o anti-hook do charter (`Policies.charter.md:63`). |
| Busca de políticas (chave/nome/categoria) + vazio "no-results" | Vivo (re-medido 2026-09-29): `Policies.tsx:125-143` — `<Input type="search">` "Buscar por chave, nome ou categoria…" (`:129-136`) + hint "Desligadas continuam na lista" (`:137-139`); filtro em memória sobre `rule_key`/`name`/`description`/`category` (`:61-73`; sem termo a lista volta inteira, `:63`); vazio "Nenhuma política bate com essa busca" com "Limpar busca" (`:147-154`). Mockup: `governance-page.jsx:338-343` (input + hint) e `:346-349` (`A.Vazio variant="no-results"` com "Limpar busca"); filtro `:303-307` | Nada — paridade: CONSTRUÍDO em 2026-09-09 (#7089, bloco "Decidido em 2026-09-09" acima); o anti-hook do charter (`Policies.charter.md:62`, não esconder desligadas) segue respeitado — sem termo a lista volta inteira. |
| Lista agrupada por categoria (ordenação ativas → categoria → chave) | `Policies.tsx:156-194` `filteredGroups.map` → `Card` + `h3 capitalize` por categoria; ordenação vem do backend (charter §Goals). Mockup: `governance-page.jsx:303-307` (mesma ordenação em memória) + `:350-352` (h3 com contador de regras do grupo). Contador por grupo no vivo: 2026-09-29 — existe (`:162-164`, `group.rules.length`); em 2026-09-06 o recibo `rules\.length` dava 0 hits | Nada — paridade (mesmo agrupamento e ordem; o contador do grupo é rótulo, não capacidade) |
| Linha da regra (chave mono · nome · descrição · versão · disparos · estado) | `Policies.tsx:178-187` — `rule_key` font-mono, `name`, `description`, `Badge outline vN`, `N hits`; estado via `Switch checked` (`:170-176`, `checked` em `:171`). Mockup: `governance-page.jsx:355-372` (mesmos campos + `Selo` textual "Ativa/Desligada"; `Selo／Ativa"／Desligad` → 2026-09-29: 1 hit no vivo, `:138`, o hint da busca — não é selo) | Nada — paridade (o estado é exibido pelo próprio Switch; o selo textual é rótulo redundante) |
| Toggle por linha | `Policies.tsx:75-89` — `router.post('/governance/policies/{id}/toggle')` com `preserveScroll`+`preserveState`, estado otimista (`:52` + `:78`), rollback em `onError` (`:83-86`) e Switch desabilitado enquanto pendente (`:172`). Mockup: `governance-page.jsx:318-321` (só estado local) | Nada — vivo à frente (persistência real + otimista + rollback + trava de duplo clique; o retrato só alterna em memória) |
| Feedback do toggle (toast/flash) | Vivo: `Policies.tsx:85` `toast.error(...)` só no erro; sucesso é flash de sessão em `Modules/Governance/Http/Controllers/PoliciesController.php:65` `back()->with('status', "Policy #N ativada/desativada")`, que **não** é mapeado pelo `HandleInertiaRequests.php:108-123` — ele só lê `status.msg`/`status.success`/`status.error`/`status.info` e as chaves soltas, e aqui `status` é string (medido 2026-09-29); pela leitura, o flash não chega a `resources/js/app.tsx:55-76`. Mockup: `governance-page.jsx:320` (toast local) + `:429` (render). Confirmar em smoke | Nada — decisão já registrada (charter §UX Targets: flash "Policy #X ativada/desativada" via `back()->with('status')`); se o flash chega ao React é pergunta de smoke, fora da Fase 1 |
| Estado vazio "sem regras" | `Policies.tsx:145-146` `<EmptyState title="Sem rules ainda" …>` quando `rules_by_category.length === 0`. Mockup: não desenha (o `POLITICAS` mock nunca é vazio; só existe o vazio de busca em `:346-349`) | Nada — vivo à frente (vazio de catálogo existe só no vivo) |

## Recibos de ausência
_Re-contados em 2026-09-29; o número de 2026-09-06 vai entre parênteses. Os 4 primeiros deixaram de ser ausência com o #7089 (2026-09-09)._
- `grep -nEi 'histor|rastro|history' resources/js/Pages/governance/Policies.tsx` → 3 (`:108`, `:109` comentário; `:116` título do aviso) (era 0)
- `grep -nEi 'busca|search|filtr|<input' resources/js/Pages/governance/Policies.tsx` → 15 (busca `:54-73`, input `:129-134`, vazio `:149-153`) (era 0)
- `grep -nE 'rules\.length' resources/js/Pages/governance/Policies.tsx` → 2 (`:72` filtro, `:163` contador do grupo) (era 0)
- `grep -nE 'Selo|Ativa"|Desligad' resources/js/Pages/governance/Policies.tsx` → 1 (`:138`, hint da busca — não é selo) (era 0)
- `grep -nE 'toast\.success|flash' resources/js/Pages/governance/Policies.tsx` → 0
