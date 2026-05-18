---
name: feedback-localstorage-filter-stale-hint
description: Filtro state persistido em localStorage sem UI hint visível é trap. Pattern fix: pill colorido sempre visível quando filter ativo + estado stale forte quando dateTo < ontem + botão "Limpar filtro" 1-click.
type: feedback
---

# Filter em localStorage SEM UI hint visível esconde dados — trap conhecido

**Regra:** Quando uma listagem usa `localStorage` pra persistir filter de data/range/qualquer dimensão que **possa esconder linhas inteiras**, **OBRIGATÓRIO** exibir pill visível quando filter está ativo. Quando `dateTo < ontem` (filter stale), pill com cor amber + texto claro "Filtro antigo escondendo X". Botão "Limpar filtro" 1-click.

**Why:** Sessão 2026-05-18 — cliente Wagner reportou *"vendas feitas só aparece até dia 14"* (hoje era 18). Causa raiz: `localStorage[oimpresso.sells.dateTo]` ficou stuck com `'2026-05-14'` de alguma interação antiga. Sem hint visível, cliente passou 4 dias sem perceber que tinha filter ativo, achou que o sistema estava bugado, abriu suporte.

Fix arquitetural em PR #1071: pill acima das pills de status, 2 estados:

```tsx
{dateFilterActive && (
  <div className={'vd-date-filter-hint' + (dateFilterStale ? ' stale' : '')}>
    <span>{dateFilterStale ? '⚠' : '📅'}</span>
    <span>
      {dateFilterStale
        ? <b>Filtro antigo escondendo vendas novas:</b>
        : <b>Filtro de data ativo:</b>}
      {' '}<span className="range">{dateFilterLabel}</span>
      {dateFilterStale && <small>· vendas após {dateTo} ficam ocultas</small>}
    </span>
    <button onClick={clearDateFilter}>Limpar filtro</button>
  </div>
)}
```

**How to apply:**

Quando adicionar (ou auditar) state de filter persistido em qualquer Page Inertia:

1. **Computar `<filter>Active`** — boolean simples: filter difere de default
2. **Computar `<filter>Stale`** — `useMemo`: true se `dateTo` (ou equivalente) está no passado relativo a `new Date()` startOfDay
3. **Renderizar pill** ANTES da tabela:
   - Ativo normal → cor azul ou neutra
   - Stale → cor amber forte + texto explicando "X linhas podem estar ocultas"
4. **Botão "Limpar filtro"** que reseta STATE + localStorage simultaneamente (não esquecer lsSet vazio)
5. **NÃO auto-reset.** Filter legítimo (cliente quer ver vendas fevereiro mesmo em maio) é caso de uso real. Opt-in via botão é mais respeitoso.

**Aplicabilidade:**

| Tela com filter | Tem hint hoje? |
|---|---|
| `/sells` (Sells/Index.tsx) | ✅ PR #1071 |
| `/financeiro/unificado` | ❌ Auditar próxima sessão |
| `/contas-receber` Blade legacy | ❌ Auditar pós-migração Inertia |
| `/repair/dashboard` | ❌ Auditar |
| `/oficina-auto/producao-oficina` | ❌ Auditar |

Toda Page Inertia com `localStorage[...filter]` deve passar pelo mesmo padrão.

**Anti-patterns:**

- ❌ Auto-reset silencioso quando `dateTo < ontem` (quebra uso legítimo de filter histórico)
- ❌ Filter ativo SEM nenhum feedback visual além do popover do DateFilter (cliente esquece)
- ❌ Limpar só state mas não localStorage (volta após reload)
- ❌ Botão de limpar escondido dentro de menu/dropdown (precisa ser 1 click visível)

**Histórico:**

- 2026-05-18 — instalado após bug "vendas até dia 14" + fix PR #1071. PR #1034 (DateFilter reintegration) preservou keys legacy localStorage canon, mas sem UI hint o trap permaneceu invisível ~9 dias até cliente reclamar.
