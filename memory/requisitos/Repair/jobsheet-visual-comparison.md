# Visual Comparison — JobSheet Wave 3 B6 MWART

> **Wave:** W3-B6 Repair · **Sprint:** MWART massiva 2026-05-15
> **Telas:** JobSheet/Index (preserve), Show, Edit, Create, AddParts
> **Refs:** [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) · [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) · [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)

## Pattern Reuse declarado

Blueprint canônico: **`prototipo-ui/prototipos/os/cowork-app.jsx`** (listagem + detalhe OS Cowork).
Blueprint complementar: **`prototipo-ui/prototipos/producao-oficina/F1.html`** (Kanban produção — não usado nestas 5 telas).

| Tela | Blueprint reuse | Divergência |
|---|---|---|
| JobSheet/Index | os/cowork-app.jsx (lista + tabs + KPIs) | tabela ainda DataTables AJAX legacy (Wave anterior); estrutura header já alinhada |
| JobSheet/Show | os/cowork-app.jsx (detail panel) | + FSM panel lateral (ADR 0143 - não existe no blueprint) |
| JobSheet/Edit | os/cowork-app.jsx (form variant) | tabs UX (Cliente/Aparelho/Defeitos/Checklist) — blueprint não tem tabs |
| JobSheet/Create | os/cowork-app.jsx (NewOsModal pattern) | wizard step substitui modal compacto |
| JobSheet/AddParts | divergência justificada | sem blueprint; adopta pattern POS de linha-de-peça |

## Status visual aprovação

**PENDENTE Wagner sign-off** via screenshot (não tabela markdown — ADR 0114).

Gate F1.5 visual: aguarda execução `npm run build` + screenshot real em monitor 1280px (ROTA LIVRE quirk — mesmo que biz=4 NÃO use Repair, padrão herdado).

## 15 dimensões qualitativas (skill `mwart-comparative V4`)

| # | Dimensão | Index | Show | Edit | Create | AddParts |
|---|---|---|---|---|---|---|
| 1 | Hierarquia visual | OK | OK | OK | OK | OK |
| 2 | Densidade informacional | OK | OK | OK | OK | OK |
| 3 | Affordance ações | OK | + FSM panel | tabs com erro indicator | submit múltiplo | add/remove row |
| 4 | Consistência tipografia | shadcn/tw | shadcn/tw | shadcn/tw | shadcn/tw | shadcn/tw |
| 5 | Cores semânticas | StatusBadge map | STAGE_COLOR_MAP | — | — | — |
| 6 | Spacing rítmico | grid gap-4 | grid gap-4 | grid gap-3 | grid gap-3 | tabela compacta |
| 7 | Empty states PT-BR | OK | OK | "sem checklist" | — | "Nenhuma peça" |
| 8 | Loading states | Deferred fallback | Deferred fallback | Deferred | Deferred | — |
| 9 | Erro handling | server-validation 302 | toast (FSM execute) | per-field errors | per-field errors | — |
| 10 | Mobile responsivo | grid-cols-1 md:3 | lg:grid-cols-3 | md:grid-cols-2 | md:grid-cols-2 | tabela rolável |
| 11 | A11y (labels) | aria via shadcn | aria via shadcn | Label HTML | Label HTML | Label HTML |
| 12 | Performance defer | (Wave anterior) | parts/activities/anexos defer | options defer | options defer | — |
| 13 | XSS guard | DOM API (Wave anterior) | textContent | useForm typed | useForm typed | typed inputs |
| 14 | i18n PT-BR | OK | OK | OK | OK | OK |
| 15 | Charter declared | charter v2 (este Wave) | charter novo | charter novo | charter novo | charter novo |

## Output Pest

Veja `Wave3B6JobSheetShowTest.php` (5 testes), `Wave3B6JobSheetEditTest.php` (3 testes), `Wave3B6JobSheetCreateTest.php` (3), `Wave3B6JobSheetAddPartsTest.php` (3), `Wave3B6RepairShowTest.php` (4 testes).

Total: 18 testes Pest novos. Padrão "auto-skip dev env" — não falha CI se dev DB não tem fixtures (UltimatePOS 100+ migrations + triggers não rodam sqlite).
