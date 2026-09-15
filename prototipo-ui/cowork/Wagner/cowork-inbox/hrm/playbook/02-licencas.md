---
sessao: "02"
titulo: Licenças — Page (lista + saldo por tipo)
dono: "[CL]"
base: 159e572dd448
prefixo: resources/js/Pages/Essentials/Licencas.tsx OU Licencas/Index.tsx (+ charter/casos ao lado — flat × pasta decide o criar-tela.mjs) · EssentialsLeaveController.php (@index · @getUserLeaveSummary) · prototipo-ui/contrato/essentials-licencas.contract.json · Tests/Feature/HrmLicencaTest.php (ESTENDER, não recriar) · e2e/essentials-licencas.spec.ts · .github/workflows/essentials-pest.yml (lane)
nao_toca: @changeStatus · @destroy · @activity · Services/LeaveRequestService.php · as 8 irmãs vivas de Pages/Essentials/ (Metas.tsx, Settings/, Holidays/, Todo/, Knowledge/, Documents/, Messages/, Reminders/) · AppShellV2 · DS
depende: — (vaga 1). Caminho = resources/js/Pages/Essentials/ (convenção viva, 14 .tsx; RESÍDUO 1 caiu — a árvore respondeu)
---
# 02 · Licenças

## A · Identidade — ancoragem dupla
- **alvo (layout):** `prototipo-ui/cowork/hrm-page.jsx` (`Licencas`) + `hrm-forms.jsx` (`FormLicenca`). Medido (EXPORT-HRM-2026-09-04 §3, dark, T1 estável 1009 nós): toolbar = busca + 2 `select.hrm-sel` (`aria-label` "Filtrar por situação" / "por tipo") + `.usr-count` "N de M" + `.hrm-spacer` + `.hrm-kbd` (`/` buscar · `n` novo) + `button.os-btn.primary` "Pedir licença" · grade do DS **8 colunas nesta ordem**: ref · tipo · quem{primary+sub} · período{primary+sub} · motivo · status · ação · linha `state=urgent` quando `pending` e início ≤ hoje, `archived` quando `cancelled` · subview **Saldo por tipo** = `os-table` 6 col: Tipo · Limite · Aprovado · Em análise · Consumo · Risco.
- **âncora (código):** `EssentialsLeaveController@index` (preservar o ramo `request()->ajax()` do DataTables enquanto blade legada o consumir) · `@getUserLeaveSummary` → JSON. **Irmã golden = `resources/js/Pages/Essentials/Metas.tsx` (#6869)**: mesmo pacote de 8 peças (tsx · charter com frontmatter `component:`/`runbook:` · casos · contrato `essentials-<tela>` · Pest · e2e · RUNBOOK · lane) — copiar a forma, não o conteúdo.
- arquétipo PT-01 + drawer PT-02 · persona Eliana/Wagner · rota `/hrm/leave` **existe** — zero rota nova.
- **charter/casos:** reaproveitar os textos revisados do commit `dbfc75fbcf` — `git fetch origin refs/pull/6800/head` · `git show dbfc75fbcf:Modules/Essentials/Resources/js/Pages/Hrm/Licencas/Index.charter.md` (e `Index.casos.md`). **Não reescrever.**

## B · Não inventar
- Componentes: `@/Components/ui/*` · `shared/{PageHeader,EmptyState}` · partial reload `only:[...]` como `Pages/Repair/Index.tsx`.
- Dados (lidos no `main` em 04/09): `essentials_leaves.{ref_no,user_id,essentials_leave_type_id,start_date,end_date,status,reason,status_note}` · `essentials_leave_types.{leave_type,max_leave_count,leave_count_interval}` · filtros `user_id · status · leave_type · start_date/end_date` · permissões `essentials.crud_all_leave · crud_own_leave · approve_leave` · `statusMap` do `LeaveRequestService` (pending/approved/cancelled — **não existe** "rejeitada").
- Copy literal do protótipo, PT-BR (lang já corrigido no #6778) · roxo `oklch(0.55 0.15 295)` light / `0.70` dark · zero hex.

## C · Comportamento (EARS)
| elemento | TAG | QUANDO → O SISTEMA DEVE | persiste | reversível | prova |
|---|---|---|---|---|---|
| busca | INPUT `aria-label` | digitado / tecla `/` → filtrar ref/colaborador/tipo/motivo | querystring | ✕ limpa | `.usr-count` muda |
| select situação · tipo | SELECT | escolhido → filtrar server-side (`status`, `leave_type`) | querystring | "Todas as situações" | total reduz |
| linha | TR `role=button` | clique → drawer da licença | — | `esc` fecha 1 nível | drawer monta |
| Aprovar / Cancelar | BUTTON (só `approve_leave`) | clique-no-filho **com `stopPropagation`** → POST `/hrm/change-status` {status, status_note} + notifica colaborador | grava | não (novo status) | Pest: status + `LeaveStatusNotification` |
| Pedir licença | BUTTON · tecla `n` | enviado → `status=pending`, `ref_no` com prefixo de `essentials_settings`, `NewLeaveNotification` aos admins | grava | não | Pest existente (#6797) |
Invariantes: permissão nega antes de renderizar · `user_id` do body validado por tenant (Tier 0) · filtro reversível · estado vazio com "Limpar busca e filtros" · sem número inventado ⇒ `—`.

## Execução
```
ARQUIVOS A EDITAR : resources/js/Pages/Essentials/Licencas{.tsx|/Index.tsx}  (CRIAR — via criar-tela.mjs Essentials/Licencas PT-01, que carimba tsx+charter+casos+e2e+contrato JUNTOS e decide flat × pasta)
                    resources/js/Pages/Essentials/Licencas/_components/SaldoPorTipo.tsx (CRIAR — ou inline se o gerador der flat)
                    EssentialsLeaveController.php (@index → Inertia::render('Essentials/Licencas'); @getUserLeaveSummary → JSON)
                    prototipo-ui/contrato/essentials-licencas.contract.json (o gerador carimba; o hrm-licencas.contract.json do cowork-inbox/hrm/ é INSUMO das seções/copy/estados — não copiar por cima)
                    Modules/Essentials/Tests/Feature/HrmLicencaTest.php (ESTENDER com os UC da tela) · e2e/essentials-licencas.spec.ts · lane em essentials-pest.yml
REUSAR            : statusMap · notificações existentes · átomos ui/shared · o teste do #6797
PASSO A PASSO     : 1) gh pr list --state open × estes arquivos (whats-active morto) 2) criar-tela.mjs 3) substituir charter/casos pelos textos de dbfc75fbcf
                    4) Inertia::render com leave_statuses + users + leave_types 5) grade 8 col na ORDEM do alvo
                    6) ações inline 7) subview saldo 8) npm run contrato:check 9) placar no PR 10) _saida-02.md
PARAR SE          : (a) coluna "Limite" sem fonte na tabela → "—" + linha no PR
                    (b) ramo ajax() ainda consumido por blade → preservar, não trocar por Inertia-only
                    (c) criar-tela.mjs gerar caminho fora de resources/js/Pages/Essentials/ → parar e reportar (não escolher à mão)
```

## Prova (o que o PLACAR confere no `main`)
- `resources/js/Pages/Essentials/Licencas.tsx` **ou** `Licencas/Index.tsx` + charter (frontmatter) + casos com ≥1 UC citado por teste
- `prototipo-ui/contrato/essentials-licencas.contract.json` válido em `contract.schema.json` e **vigente** · `e2e/essentials-licencas.spec.ts`
- `EssentialsLeaveController.php` contém `Inertia::render('Essentials/Licencas` (D4) · `HrmLicencaTest.php` verde na lane
- `_saida-02.md` nesta pasta · placar no corpo do PR
- Não verificável daqui: T7 `design-diff --compare --check` · screenshot prod dark 1280
