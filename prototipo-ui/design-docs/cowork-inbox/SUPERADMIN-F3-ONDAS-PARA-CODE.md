# Superadmin — ondas F3 para o [CL] (Claude Code)

**Autor:** [CC] · **Data:** 19/08/2026 · **Fase:** F1 → F3 handoff
**Origem lida no `main` NESTE turno** (tree `d4ad08042926`): `Modules/Superadmin/**` — `Routes/web.php`, `Http/Controllers/*` (14), `Services/*` (4), `Http/Requests/*` (6), `Entities/*` (4), `Policies/PackagePolicy`, `Observers/BusinessAutoSubscriptionObserver`, `Listeners/*` (2), `Tests/Feature/*` (14), `Resources/js/Pages/Site/Pricing.tsx`, `Resources/views/**`.
**Protótipo F1:** `superadmin-page.jsx` + `superadmin-page.css` no Cowork (rotas `superadmin`, `sa-negocios`, `sa-assinaturas`, `sa-pacotes`, `sa-comunicador`, `sa-config`). Charter e casos: `cowork-inbox/SUPERADMIN-F1-2026-08-18.md`.
**Não commitado por mim** — as tools de git aqui são read-only.

---

## Resposta curta: **6 ondas**, precedidas de uma **Onda 0 de decisão sua**

Menos do que eu esperava, por um motivo bom: **o backend já foi feito.** Não há onda de "extrair service", "escrever policy" ou "criar teste de isolamento" — isso está no `main`, testado (14 feature tests, incluindo 4 de cross-tenant saturation). O que falta é **trocar a camada de view**: os controllers ainda devolvem `view('superadmin::…')` (Blade/AdminLTE) enquanto o único Inertia do módulo é `Resources/js/Pages/Site/Pricing.tsx`.

Então as 6 ondas são de **tradução de tela**, não de arquitetura. Numerei `SA-O1…SA-O6` de propósito: o repo já usa "Wave 18/23/25/27" para o endurecimento de backend, e reaproveitar esses números causaria confusão no log.

| Onda | Tela | Peso | Depende de |
|---|---|---|---|
| **SA-O0** | 3 decisões suas (abaixo) | — | você |
| **SA-O1** | Dashboard `/superadmin` | P | SA-O0 |
| **SA-O2** | Negócios (índice + drawer) | **G** | SA-O1 |
| **SA-O3** | Negócio: criar/editar/senha/toggle | M | SA-O2 |
| **SA-O4** | Assinaturas + pacotes | M | SA-O1 |
| **SA-O5** | Comunicador | P | SA-O1 |
| **SA-O6** | Configurações + limpeza do Blade | M | O1–O5 |

---

## O que **já existe** no `main` — usar, não recriar

Isto é a parte que evita retrabalho. O [CL] chama; não reescreve.

| Peça | Onde | Serve para |
|---|---|---|
| `SuperadminDashboardService` | `Services/` | `countNotSubscribedBusinesses()`, `buildMonthlyRevenueChart()` (12 m rolling), `statsForPeriod($ini,$fim)`, `countBusinessesByStatus()` — o docblock já diz "útil pra `Inertia::defer`" |
| `SubscriptionLifecycleService` | `Services/` | `approve()` (calcula `end_date` por `package_details.interval`), `expire()` (idempotente), `cancel($reason)`, `findOverdueApproved()` |
| `PackageManagerService` | `Services/` | CRUD/regras de pacote |
| `BusinessAuditService` | `Services/` | trilha de auditoria de negócio |
| `StoreBusinessRequest`, `UpdateBusinessPasswordRequest`, `StorePackageRequest`, `UpdatePackageRequest` | `Http/Requests/` | validação server-side já escrita — o form Inertia espelha estas regras, não inventa outras |
| `PackagePolicy` | `Policies/` | autorização de pacote |
| `BusinessAutoSubscriptionObserver` | `Observers/` | assinatura automática ao criar negócio — **o form de criação não deve duplicar isso** |
| `OnCobrancaPagaUpdateSubscription`, `OnCobrancaVencidaBloqueaSubscription` | `Listeners/` | Asaas → status. A UI **reflete**, não decide |
| `OtelHelper::spanBiz` | `app/Util/` | instrumentação canônica — todo método novo entra no mesmo padrão |
| `throttle:superadmin` (60/min) | `RouteServiceProvider` | já protege criar negócio, resetar senha, destruir tenant |
| `Resources/js/Pages/Site/Pricing.tsx` | módulo | **o precedente**: mostra onde página Inertia de módulo mora e como o charter acompanha (`Pricing.charter.md`) |

**Cross-tenant é intencional e documentado** (ADR 0093 §exceções Superadmin, repetido em 3 docblocks). Nenhuma onda deve adicionar escopo de tenant nestas queries — quebraria o produto.

---

## SA-O0 — 3 decisões antes de escrever código

Três widgets do meu protótipo **não têm origem no banco**. Não quero que o [CL] invente coluna nem que finja dado no Inertia. Cada um tem 3 saídas; escolha uma.

**1. Trial.** A UI mostra badge "Trial" e um funil trial→pago. No banco, `subscriptions.status` é `waiting`/`waiting_approval` → `approved` → `expired` (+ `cancelled`); **trial não é status** — é `trial_days` no pacote, congelado em `package_details`.
→ (a) derivar trial de `package_details.trial_days` + `start_date` (sem migration, é cálculo); (b) migration com `trial_end_date`; (c) tirar trial da UI.
*Minha recomendação: (a).* Resolve badge e funil sem tocar schema.

**2. Motivo de churn.** O gráfico de motivos não tem campo. `SubscriptionLifecycleService::cancel($reason)` **recebe** o motivo mas o docblock diz que gravar via `activity()->withProperties()` está fora do escopo do service.
→ (a) migration `subscriptions.cancel_reason` (enum curto + texto); (b) ler das properties do Spatie LogsActivity; (c) tirar o gráfico e deixar só a taxa de churn.
*Minha recomendação: (a).* (b) faz o dashboard depender de log, que é para auditoria, não para agregação.

**3. Taxa de abertura do comunicador.** `SuperadminCommunicatorLog` registra o envio; abertura não existe.
→ (a) migration + pixel/`opened_at`; (b) trocar por "entregues / falharam" (dado que o mailer já dá); (c) tirar a métrica.
*Minha recomendação: (b).* Pixel de rastreio em e-mail de plataforma é discussão de LGPD que não vale por essa métrica.

**Enquanto SA-O0 não voltar, o [CL] não começa SA-O1** — o dashboard é justamente onde esses três aparecem.

---

## Vocabulário: mapa status ↔ UI (trava a copy)

O Blade mostra o enum cru. A UI nova nunca mostra `waiting_approval`.

| DB `subscriptions.status` | UI PT-BR | Tom (`StatusBadge`) |
|---|---|---|
| `approved` + `end_date` futura | Ativa | `success` |
| `approved` + trial vigente (SA-O0.1) | Trial | `info` |
| `waiting` / `waiting_approval` | Pendente | `warning` |
| `expired` | Vencida | `danger` |
| `cancelled` | Cancelada | `neutral` |
| negócio sem registro em `subscriptions` | Sem assinatura | `neutral` |

`business.is_active` é **outro eixo** — "Ativo/Inativo" do negócio, nunca fundido com o status da assinatura. Duas colunas na tabela, como no protótipo.

---

## SA-O1 — Dashboard `/superadmin` · peso P

**Por que primeiro:** é a menor troca com o maior ganho de prova. O service já é paridade do controller legado; `SuperadminController::index()` só precisa parar de montar `CommonChart` e passar a `Inertia::render`.

- `Resources/js/Pages/Dashboard/Index.tsx` + `Index.charter.md` (copiar do protótipo: período, 4 KPI, funil, churn, receita por pacote, fila de vencimento, tendência 12 m, prioridades, recentes).
- `SuperadminController::index()` → `Inertia::render('Superadmin::Dashboard/Index', …)` chamando `SuperadminDashboardService`. Manter o span `superadmin.legacy.index` renomeado para `superadmin.dashboard.index`.
- `stats()` continua endpoint de período (o segmented Hoje/Semana/Mês/Ano chama `statsForPeriod`) — trocar o AJAX jQuery por partial reload do Inertia.
- KPIs pesados (funil, churn, receita/pacote) entram por `Inertia::defer` → skeleton do DS aparece de verdade.
- Rota `/pricing` **não se toca** (já é Inertia).

**DoD:** `/superadmin` renderiza Inertia sem AdminLTE; 4 KPI batem com os do Blade no mesmo banco; período recalcula sem recarregar a página; `SmokeRoutesTest` verde.
**Não fazer:** não apagar o Blade ainda (SA-O6 faz a limpeza); não mexer nos services.

## SA-O2 — Negócios: índice + drawer · peso **G** (a onda grande)

- `Pages/Business/Index.tsx` + charter + casos.
- `BusinessController::index()` → Inertia com paginação **server-side** (`->paginate()`), busca, e os 4 filtros (pacote · assinatura · status · última venda) como query string — o DataTables server-side atual sai.
- Drawer de detalhe (PT-02) por partial reload, sem rota nova de página.
- Uso contra o teto (`Progress`) vem de `package_details` (usuários/locais/produtos/faturas) contra contagem real.
- `Pagination`, `DataTable`/`DataTablePro`, `StatusBadge`, `Drawer` do DS. **Decisão pendente:** meu protótipo usa a tabela `os-table` do shell; o DS tem `DataTablePro`. Se o app migrar de grade, é aqui que se decide — vale uma linha sua.

**DoD:** 10 k negócios sem N+1 (eager load de `subscriptions` + dono); filtro e busca sobrevivem a refresh (estão na URL); admin de negócio toma 403; teste de isolamento existente continua verde.
**Não fazer:** sem exclusão em lote (charter, non-goal); não carregar todos os negócios no `props`.

## SA-O3 — Negócio: criar, editar, senha, ativar/desativar · peso M

- `Pages/Business/Form.tsx` (drawer de 3 seções do protótipo) — validação espelhando `StoreBusinessRequest`, com `errors` do Inertia nos campos.
- `updatePassword` → `UpdateBusinessPasswordRequest`, dentro do drawer, nunca em página própria.
- `toggleActive` e `destroy` viram POST/DELETE com confirmação `Modal` do DS. **Hoje são `GET`** (`/{business_id}/toggle-active/{is_active}` e `/business/{id}/destroy`) — ação destrutiva em GET é o tipo de coisa que um crawler dispara; trocar por método correto nesta onda (rota antiga pode ficar por um ciclo, redirecionando).
- Máscara CNPJ/telefone no cliente; a verdade continua no Request.
- `BusinessAutoSubscriptionObserver` já cria a assinatura inicial — o form **envia o pacote e deixa o observer agir**.

**DoD:** criar negócio dispara `NewBusinessWelcomNotification` uma vez; erro de validação volta no campo certo em PT-BR; excluir passa por Modal que diz o que se perde; `throttle:superadmin` respeitado.

## SA-O4 — Assinaturas + pacotes · peso M

- `Pages/Subscription/Index.tsx` (tabela + 4 KPI de status + filtros) e `Pages/Package/Index.tsx` (grid de cards) + `Package/Form.tsx`.
- Mudar status chama **`SubscriptionLifecycleService`** — sem `->update(['status'=>…])` no controller. Motivo obrigatório no cancelamento (e é o campo da decisão SA-O0.2).
- Editar vigência = `start_date`/`end_date`; a nota "prorrogar não gera cobrança" tem que ser verdade no código.
- Form de pacote espelha `StorePackageRequest`/`UpdatePackageRequest`; "0 = ilimitado" precisa ser o mesmo contrato do backend.
- Regras do charter que a UI promete e o [CL] confirma: R5 (reduzir limite não corta quem passou), R6 (pacote inativo sai da vitrine, quem assinou segue), R7 (privado só superadmin atribui — usar `PackagePolicy`).

**DoD:** aprovar calcula `end_date` pelo intervalo do pacote (teste do service já cobre); pacote com assinantes não é excluível; MRR do dashboard não conta pacote avulso nem gratuito.

## SA-O5 — Comunicador · peso P

- `Pages/Communicator/Index.tsx` — grupos como chips com contador, alcance recalculado no servidor (não confiar em contagem do cliente), prévia do e-mail, agendamento.
- `send` → fila (`ShouldQueue` em `SuperadminCommunicator`), nunca no request; "enviar teste para mim" é um `->to(auth()->user())`.
- `getHistory` vira prop paginada; métrica conforme SA-O0.3.
- R8 vale no servidor: negócio em dois grupos recebe **uma** vez (dedupe por `business_id`).

**DoD:** 112 negócios não estouram o request; agendamento aparece no histórico como "agendado para"; dedupe coberto por teste.

## SA-O6 — Configurações + limpeza · peso M

- `Pages/Settings/Edit.tsx` — as 6 seções como nav lateral (Aplicação · SMTP · Gateways · Pusher · Rotinas/backup · JS-CSS extra), `PUT /settings` com `SuperadminSettingsController::update` intacto.
- Seção de injeção de código com `Alert tone="danger"` e entrada no log de auditoria.
- **Limpeza:** remover `Resources/views/superadmin/`, `business/`, `packages/`, `superadmin_subscription/`, `communicator/`, `superadmin_settings/` e o `layouts/nav` do módulo depois que cada tela tiver par Inertia verde. `views/subscription/` (14 arquivos) e `views/pages/` **ficam** — dependem de SA-O0/D2/D3.
- `superadmin.contract.json` (rascunho no doc F1) entra no CI.

**DoD:** nenhuma rota de `/superadmin/*` renderiza Blade; `prototipo-readiness` marca as 6 telas ✅ (trio completo); guard `cowork-ssot-guard` verde.

---

## Fora destas 6 ondas (de propósito)

- **`/superadmin/usuarios` (Usuario360Controller)** — existe no `main` com 5 rotas (index, 360, lock, unlock, history) e **não tem tela no meu protótipo**. É uma tela nova de F1, não tradução; peço briefing antes de desenhar. Provável SA-O7.
- **`subscription/*`** (14 views) — é a tela que **o cliente** vê ao pagar, não o superadmin. Decisão D3 do charter: módulo do cliente ou daqui?
- **`frontend-pages` / `pages`** — CMS. Decisão D2.
- **Impersonar** ("entrar como este negócio") — decisão D1; precisa de banner permanente na sessão e trilha de auditoria. Não entra em SA-O2 sem seu ok.
- **Gateways** (PesaPal, Paystack, Flutterwave, PayPal) — não toco; o Brasil usa Asaas/Pix, e mexer nisso é escopo de `Modules/PaymentGateway`.

## Ordem que eu recomendo

`SA-O0` (você) → `SA-O1` (prova o padrão Inertia no módulo) → `SA-O2` (a onda que dá o trabalho) → `SA-O3` → `SA-O4` → `SA-O5` → `SA-O6` (fecha e limpa).

Se quiser resultado visível em um único ciclo: **SA-O1 sozinha** já tira o AdminLTE da cara do dashboard e prova o caminho para as outras cinco.
