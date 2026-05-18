# `_cowork-bundle/` — Bundle Cowork Financeiro INTEGRAL (referência canônica)

## O que é

Pasta com **TODOS** os arquivos JSX do Financeiro copiados na íntegra do bundle Cowork v2 (`prototipo-ui-patch/vendas-financeiro-completo/`), fetcheado via Anthropic API design 2026-05-18.

**Underscore prefix `_cowork-bundle/`** = excluído do auto-discovery do Inertia (não vira página automática). Esses arquivos são **referência de design** — não rodam em produção sem adaptação.

## Por que está aqui

Regra IRREVOGÁVEL Wagner 2026-05-18 (memory/reference/feedback-cowork-bundle-aplicar-inteiro.md):

> Pacote Cowork novo de módulo: PRIMEIRA aplicação = COPIAR CSS+JSX INTEIRO do bundle. Customização vem DEPOIS.

Cherry-pick falhou 3× no Financeiro ([PR #1085](https://github.com/wagnerra23/oimpresso.com/pull/1085) → [#1091](https://github.com/wagnerra23/oimpresso.com/pull/1091) → [#1092](https://github.com/wagnerra23/oimpresso.com/pull/1092)). Bundle integral funciona em Vendas/Pedidos/Cockpit.

## Manifest

| Arquivo | Bytes | Responsabilidade |
|---|---|---|
| `financeiro-app.jsx` | 58677 | **Página principal** — Visão Unificada, tabela, filtros, drawer. **Maior arquivo** — base pra `Pages/Financeiro/Unificado/Index.tsx` |
| `financeiro-telas-extras.jsx` | 37161 | **Telas adicionais** — Fluxo, DRE, Caixa, etc. Provável base pra `Pages/Financeiro/Fluxo/*`, `DRE/*`, `Caixa/*` |
| `financeiro-output.jsx` | 21875 | Output/Apresentação — folha PDF, modo apresentação, resumir mês (já portados nas ondas 7b/7c/9) |
| `financeiro-curation.jsx` | 18203 | Curadoria — conferido, comentários, audit, frescor (já portados na Onda 5) |
| `financeiro-ai.jsx` | 17670 | IA — anomalia, party history, month digest (já portados na Onda 6) |
| `financeiro-data.jsx` | 8197 | Seeders de dados mock (referência pra entender shape esperado) |
| `fsm-stepper.jsx` | 7095 | Stepper máquina-de-estado (FSM venda → títulos) — NOVO, não portado ainda |
| `financeiro-icons.jsx` | 5855 | Catálogo ícones — referência visual |
| `shell-app.jsx` | 35055 | Shell raiz Cowork — integra App + Sidebar + roteamento. Espelho do `AppShellV2.tsx` |
| `shell-data.jsx` | 17887 | Dados mock root (multi-tenant simulado) |

## Como usar (próximas ondas)

### NÃO fazer

- ❌ **NUNCA importar `.jsx` daqui em produção** — não é TypeScript, não tem props validation, não tem Inertia bindings
- ❌ **NUNCA editar arquivos aqui** — bundle é AUTO-GERADO. Edições viram drift entre canon Cowork e oimpresso
- ❌ **NUNCA renomear** — `.jsx` extension preservada pra deixar claro origem
- ❌ **NUNCA adicionar imports `@/` ou Inertia router** — fica em `.tsx` adaptado, não aqui

### SIM fazer

- ✅ **Ler como referência** quando adaptar pra `.tsx` Inertia (`Pages/Financeiro/Unificado/Index.tsx`, etc)
- ✅ **Copiar JSX inline** pro `.tsx` correspondente + adicionar type annotations
- ✅ **Cross-check com `cowork-financeiro-bundle.css`** — classes referenciadas aqui devem casar com CSS
- ✅ **Próximo bundle Cowork v3** (quando Wagner mandar): substitui esta pasta inteira

## Roadmap de adaptação `.jsx` → `.tsx` Inertia

| Onda | JSX bundle | Tela oimpresso | Status |
|---|---|---|---|
| 5/6/7 (feitas) | financeiro-curation/ai/output | `Pages/Financeiro/Unificado/Index.tsx` | ✅ portado (parcial) |
| 8/8b/8c (feitas) | financeiro-app (visão) | `Pages/Financeiro/Unificado/Index.tsx` | ✅ portado |
| **PRÓXIMA** | financeiro-app drawer | Index.tsx drawer canônico | Diff atual vs canon Cowork (3 abas + cores) |
| **PRÓXIMA** | financeiro-telas-extras (Fluxo) | `Pages/Financeiro/Fluxo/Index.tsx` | Blade legacy → Inertia novo |
| **PRÓXIMA** | financeiro-telas-extras (DRE) | `Pages/Financeiro/DRE/Index.tsx` | Blade legacy → Inertia novo |
| **PRÓXIMA** | financeiro-telas-extras (Caixa) | `Pages/Financeiro/Caixa/Index.tsx` | Blade legacy → Inertia novo |
| **PRÓXIMA** | fsm-stepper | `Pages/Financeiro/Unificado/_components/FsmStepper.tsx` | **NOVO** componente — ainda não há equivalente |

## Convenções de adaptação `.jsx` → `.tsx`

1. **Type annotations Wagner padrão**: `interface LancamentoLite { ... }` no top
2. **Imports `@/`**: usar alias do projeto (`@/Layouts/AppShellV2`, `@/Components/ui/button`)
3. **Inertia router**: substituir `useState(url)` fake do bundle por `useRouter()` real
4. **Multi-tenant Tier 0**: validar que toda query backend tem `business_id` filter (ver ADR 0093)
5. **Charter obrigatório**: cada `Pages/Financeiro/<Tela>/Index.tsx` precisa de `Index.charter.md` ao lado (ADR 0104 MWART)
6. **Pest test ANTES de Edit `.tsx`**: cobertura do shape dos lançamentos (não regressão Schema)

## Tier 0 multi-tenant guard

⚠️ Os JSX do bundle usam dados mock (`shell-data.jsx`). Ao adaptar pra `.tsx` real:

- Toda query Eloquent precisa de `business_id` scope (global scope)
- Job assíncrono passa `$businessId` no constructor
- Job NUNCA usa `session()` (não funciona em fila)
- Smoke biz=1 obrigatório (não biz=4 cliente — ADR 0101)

Detalhes em `memory/proibicoes.md` §"Multi-tenant Tier 0 IRREVOGÁVEL".

## Refs canon

- `memory/reference/feedback-cowork-bundle-aplicar-inteiro.md` — regra Tier 0
- `memory/proibicoes.md` §"Design System / Pacote Cowork novo" — proibições
- `resources/css/cowork-financeiro-bundle.css` — CSS partner deste bundle (9054 LOC)
- `memory/decisions/0104-processo-mwart-canonico-unico-caminho.md` — processo MWART obrigatório pra mudança em `Pages/<Mod>/<Tela>.tsx`

---

**Origem:** Anthropic API design fetch 2026-05-18 (`api.anthropic.com/v1/design/h/8x9zoRQElHn-tYqQg187rw`)
**Tarball:** 8.2MB, 667 arquivos extraídos
**Selecionados pra Financeiro:** 10 arquivos JSX, 248KB total
