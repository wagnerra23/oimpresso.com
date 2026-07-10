---
slug: jana-runbook-cockpit
title: "Jana — Runbook da tela Cockpit V2 Analista IA (/ia/cockpit)"
type: runbook
module: Jana
owner: W
status: ativo
date: "2026-07-09"
last_validated: "2026-07-09"
---

# RUNBOOK — Cockpit V2 Analista IA (`/ia/cockpit`)

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0026](../../decisions/0026-posicionamento-erp-grafico-com-ia.md), [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md), [ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md), [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md), [charter](../../../resources/js/Pages/Jana/Cockpit.charter.md)
> **Validado:** re-validação **estática** contra `origin/main` em 2026-07-09 (rotas, controller, Page conferidos arquivo a arquivo). ⚠️ **Fluxo vivo (render em prod, troca de tab, chat streaming) NÃO exercitado em 2026-07.**

> **✅ SUPERSEDE CONSUMADO (registrado 2026-07-09):** o aviso de 2026-05-15 previa que o `Cockpit.tsx` V1 (138 lin · anti-pattern WhatsApp-style: tabs Todos/OS/Equipe/Clientes, `setTimeout(reply, 2400)`, resposta literal *"Recebido, vou verificar e te respondo já já 👍"*) seria substituído in-place pelo **V2 Analista IA** quando o pivot Cowork fosse aceito. **Aconteceu**: o `Cockpit.tsx` atual (1022 lin) é o V2 — header `@memcofre` declara `status: live (pivot Cowork aceito · supersedes MVP-piloto WhatsApp anti-pattern)`, story US-COPI-COCKPIT-002. A descrição do V1 foi movida pra seção **HISTÓRICO** no fim deste doc; as §1-§11 abaixo descrevem o V2 em prod.

Tela do **Analista IA (Jana)** — brief diário + KPIs + análises + ações HITL + chat single-thread. Layout 1-col scrollable (não é o 3-col conversacional do `/ia` Chat): não há multi-conversa aqui, é o painel analítico da Jana por business. Rota **paralela** ao `/ia` (Chat.tsx) — coexistem.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/ia/cockpit` | Login → URL → `AppShellV2` + `JanaAreaHeader` + header Jana + tabs `Dashboard`/`Analista IA` |
| Legacy redirects funcionam | `/copiloto/cockpit` e `/jana/cockpit` → 301 em cadeia → `/ia/cockpit` |
| Tab `Dashboard` (default) | Brief diário (saudação por hora + parágrafos ricos + chips de ação) + grid 4 KPIs + "Análises principais" (6 cards, kinds `buckets/sparkline/bars/list/donut/text`) + ações HITL |
| Tab `Analista IA` | Chat single-thread; resposta hoje vem de `startMockStream` (mock streaming client-side — ver §10) |
| Persistência da tab | `localStorage['oimpresso.jana.cockpit.tab']` (`'dashboard'` \| `'ia'`) |
| Header da aba do navegador | "Jana · Cockpit" (`<Head title>`); `AppShellV2 title` = "Jana · Analista IA" |
| Coexiste com `/ia` (Chat.tsx) | Abrir as duas — páginas diferentes, sem redirect entre elas |

## 1. Objetivo

Cockpit do **Analista IA** — a Jana entrega brief diário do negócio, monitora KPIs, detecta anomalias e sugere ações **HITL** (humano aprova antes de disparar). Audiência primária: dono/gerente (Wagner / Larissa), não operador de atendimento. Fonte de design da era do pivot: protótipo Cowork `chat-jana.jsx` — hoje vivo em [`prototipo-ui/cowork/chat-jana.jsx`](../../../prototipo-ui/cowork/chat-jana.jsx) (+ `.css`), que é o mesmo path que o charter aponta em `visual_source`.

Multi-tenant: Controller filtra `session('user.business_id')`; superadmin/`user_oimpresso` enxerga até 50 businesses no CompanyPicker (Tier 0 [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — filtro explícito pra não-super).

**Estado do dado (2026-07-09):** o payload `jana` ainda é **mock estruturado** ([`mockJanaPayload()` ChatController.php:555](../../../Modules/Jana/Http/Controllers/ChatController.php:555) — estrutura Martinho Caçambas biz=164). F2 pendente: substituir por `JanaCockpitDataService::buildPayload($businessId)` consultando Sells/Receivables/Frota com `business_id` scope. Há task ativa no backlog pra remover o `startMockStream` da rota live.

## 2. Pré-condições

- [ ] Módulo `Jana` instalado em `/manage-modules`
- [ ] Rota `Route::get('/cockpit', 'ChatController@cockpit')->name('jana.cockpit')` em [`Modules/Jana/Http/routes.php:40`](../../../Modules/Jana/Http/routes.php) — dentro do grupo prefix `ia` (middleware stack UltimatePOS + `throttle:120,1`)
- [ ] Page Inertia em [`resources/js/Pages/Jana/Cockpit.tsx`](../../../resources/js/Pages/Jana/Cockpit.tsx) (1022 lin) — módulo em **PascalCase**
- [ ] Charter ao lado: [`Cockpit.charter.md`](../../../resources/js/Pages/Jana/Cockpit.charter.md) (⚠️ frontmatter do charter ainda diz `status: spec-ahead-of-impl` e `page: /jana/cockpit` — drift a corrigir em PR próprio; a impl já alcançou a spec)
- [ ] Skill irmã `jana-arch` (stack ADRs 0035-0053) + `multi-tenant-patterns`
- [ ] **NÃO** esperar dados reais — payload `jana` é mock estruturado até F2 (ver §1)

**Validação:** `php artisan route:list --name=jana.cockpit` retorna 1 linha apontando pra `ChatController@cockpit`.

## 3. Passo-a-passo

### 1. Controller monta CompanyPicker real + payload `jana` mock e renderiza Inertia

```php
// Modules/Jana/Http/Controllers/ChatController.php:505
public function cockpit(Request $request)
{
    $businessId = $request->session()->get('user.business_id');
    $isSuper    = $user && ($user->user_type === 'superadmin' || $user->user_type === 'user_oimpresso');

    // CompanyPicker: superadmin → todas (limit 50); resto → só a sua (Tier 0 ADR 0093)
    $businessesDisponiveis = $isSuper
        ? \App\Business::orderBy('name')->limit(50)->get(['id', 'name'])
        : \App\Business::where('id', $businessId)->get(['id', 'name']);

    // Payload `jana` = mock estruturado seguindo o charter.
    // F2 (próxima): plugar JanaCockpitDataService (brief real + KPIs com business_id scope).
    $jana = $this->mockJanaPayload();  // :555

    return Inertia::render('Jana/Cockpit', [
        'businessNome' => session('business.name', 'Oimpresso Matriz'),
        'businesses'   => $businesses,
        /* usuario* props */
        'jana'         => $jana,
    ]);
}
```

### 2. Page: tab persistida em localStorage + render por tab

```tsx
// resources/js/Pages/Jana/Cockpit.tsx:936
const [tab, setTab] = useState<'dashboard' | 'ia'>(() => {
  if (typeof window === 'undefined') return 'dashboard';
  const v = localStorage.getItem(LS_TAB); // 'oimpresso.jana.cockpit.tab' (:133)
  return v === 'ia' ? 'ia' : 'dashboard';
});

useEffect(() => {
  try { localStorage.setItem(LS_TAB, tab); } catch { /* SSR/quota */ }
}, [tab]);
```

### 3. Render: AppShellV2 + JanaAreaHeader + nav de tabs + conteúdo

```tsx
// Cockpit.tsx:951
<AppShellV2 title="Jana · Analista IA" business={...} user={...}>
  <Head title="Jana · Cockpit" />
  <JanaAreaHeader active="cockpit" />
  <div className="px-6 py-5 max-w-[1280px] mx-auto">
    <JanaHeader data={jana} businessNome={businessNome} />
    <nav aria-label="Modo do Jana">{/* Dashboard | Analista IA */}</nav>
    {tab === 'dashboard' ? (
      <>
        <BriefDiario today={jana.today} brief={jana.brief} />
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
          {jana.kpis.map((k, i) => <KPICard key={i} kpi={k} />)}
        </div>
        {/* Análises principais (6 cards) + Ações HITL */}
      </>
    ) : (
      /* chat single-thread — streaming via startMockStream (mock, ver §10) */
    )}
  </div>
</AppShellV2>
```

> Notar: **NÃO usa `Cockpit.layout = ...`** — `<AppShellV2>` renderizado direto no `return` (mesmo padrão do Chat.tsx).

### 4. Build local + smoke

```bash
npm run build:inertia
grep -i "Pages/Jana/Cockpit" public/build-inertia/manifest.json
# Esperado: 1 linha com hash do bundle
```

## 4. Tokens CSS

Tokens shadcn semânticos (`text-primary`, `text-muted-foreground`, `border-border`, `bg-card`) + utilities Tailwind — conferidos no trecho de render (nav de tabs usa `border-primary text-primary` no ativo). Container central `max-w-[1280px] mx-auto` (cabe no monitor 1280px da Larissa). ⚠️ Auditoria token-a-token das 1022 linhas **não refeita em 2026-07** — em caso de dúvida, rodar `node prototipo-ui/ds-guard.mjs resources/js/Pages/Jana/Cockpit.tsx`.

## 5. Estados visuais

| Estado | Trigger | Implementação | Nota |
|---|---|---|---|
| `default` | — | tab `dashboard` com brief + KPIs + análises + HITL | dados mock (§1) |
| tab `ia` | click "Analista IA" | chat single-thread | resposta mock via `startMockStream` (:707) |
| `streaming` | envio de mensagem na tab ia | deltas de texto client-side (mock A5 amendment v2.1) | task pendente: plugar SSE real |
| KPI `emphasize` | `kpi.emphasize === true` | card destacado (crítico) | payload controla |
| Pills das análises | `pill.tone` | `crit/warn/ok/react` | payload controla |

⚠️ Estados `loading inicial` / `empty` / `error` não re-auditados em 2026-07 — herdam comportamento Inertia síncrono + error boundary do shell.

## 6. Responsividade

Grid de KPIs `grid-cols-2 lg:grid-cols-4`; container `max-w-[1280px]`. Shell (sidebar/LinkedApps) controlado pelo `AppShellV2`. ⚠️ Validação visual em 1280px real (Larissa) não refeita em 2026-07.

## 7. Atalhos

Sem atalhos próprios da tela verificados no código atual (⌘K global vem do shell). Tabs por click.

## 8. Component contract

### Props da Page ([Cockpit.tsx:120](../../../resources/js/Pages/Jana/Cockpit.tsx:120))

```tsx
interface Props {
  businessNome: string;
  businesses: BusinessOpt[];
  usuarioNome: string;
  usuarioNomeCurto: string;
  usuarioEmail: string;
  usuarioCargo: string;
  usuarioIniciais: string;
  jana: JanaData;   // person, biz, updatedAt, today, brief, kpis, analises, acoes, ...
}
```

Tipos principais do payload (declarados no próprio arquivo): `Brief` (greeting + paragraphs `RichRun[]` + chips), `Kpi` (label/value/delta/deltaCls/emphasize), `Analise` (kind ∈ `buckets|sparkline|bars|list|donut|text` + pill tone), ações HITL com tone `rose|violet|peach|grey`.

### Componentes

- [`@/Layouts/AppShellV2`](../../../resources/js/Layouts/AppShellV2.tsx) — shell-mãe
- [`./components/JanaAreaHeader`](../../../resources/js/Pages/Jana/components/JanaAreaHeader.tsx) — header sticky da área Jana
- Componentes locais no próprio arquivo: `JanaHeader`, `BriefDiario`, `KPICard`, cards de análise por kind, rows HITL, chat da tab ia

### Constantes localStorage

```ts
LS_TAB = 'oimpresso.jana.cockpit.tab'   // Cockpit.tsx:133
```

> As chaves da era V1 (`oimpresso.cockpit.chatTab`, `oimpresso.cockpit.conv`) pertencem ao cluster `Components/cockpit/shared.ts` usado pelo **Chat.tsx**, não a esta tela.

## 9. DoD checklist

- [x] Tela vive dentro de `AppShellV2` (inline no `return`)
- [x] Tokens shadcn semânticos no que foi conferido (nav tabs, grids)
- [x] PT-BR em todos os labels ("Dashboard", "Analista IA", "Análises principais", "Modo do Jana")
- [x] Multi-tenant: Controller filtra `session('user.business_id')` + limit explícito pro CompanyPicker
- [x] Tab persistida em `localStorage` dentro de `useEffect` (fix do anti-pattern V1 que escrevia em todo render)
- [x] Bundle Inertia: `Pages/Jana/Cockpit` no manifest
- [ ] **Backend real plugado (F2)** — `mockJanaPayload()` → `JanaCockpitDataService` com queries `business_id` scope
- [ ] **`startMockStream` removido da rota live** — task ativa no backlog (chat da tab ia responde mock)
- [ ] **Charter re-sincronizado** — frontmatter ainda `spec-ahead-of-impl` / `page: /jana/cockpit` (impl já é live em `/ia/cockpit`)
- [ ] Smoke visual 1280px + screenshot pós-mudança (R1)

## 10. Pegadinhas

- ❌ **Payload `jana` 100% mock** ([`mockJanaPayload()` :555](../../../Modules/Jana/Http/Controllers/ChatController.php:555)) — números exibidos NÃO são do business logado (estrutura Martinho biz=164 hardcoded). Nunca demonstrar pra cliente como se fosse dado real. F2 troca por Service com `business_id` scope.
- ❌ **`startMockStream` na rota live** ([Cockpit.tsx:707](../../../resources/js/Pages/Jana/Cockpit.tsx:707), chamado em :780) — o chat da tab "Analista IA" simula streaming client-side. Task ativa pra remover/plugar SSE real (`jana.conversas.mensagens.stream` já existe no Chat).
- ❌ **Charter drift** — `Cockpit.charter.md` diz `status_detail: spec-ahead-of-impl`, `page: /jana/cockpit` e aponta `visual_critique` pra path purgado (`_cowork-export-2026-05-15/`). A impl alcançou a spec; atualizar charter em PR próprio (não neste RUNBOOK).
- ❌ **`dangerouslySetInnerHTML` PROIBIDO** nesta tela (header `@memcofre`) — parser markdown custom limitado; F3 prevê `react-markdown` + `rehype-sanitize`.
- ⚠️ **`businesses` limit 50 hardcoded** no Controller — superadmin com >50 businesses perde o excedente do picker silenciosamente. Virar paginação/search quando escalar.
- ⚠️ **`session('business.name', 'Oimpresso Matriz')`** com fallback hardcoded — se a session não tiver business, header mostra nome genérico.
- ⚠️ **PII regex client-side no composer é só UX warning** — a redação real é server-side (`PiiRedactor`) no audit log.

Pegadinhas genéricas em [`.claude/skills/cockpit-runbook/GOTCHAS.md`](../../../.claude/skills/cockpit-runbook/GOTCHAS.md).

## 11. ADR de origem

- [ADR 0026 — Posicionamento ERP com IA](../../decisions/0026-posicionamento-erp-grafico-com-ia.md) — motivação de produto
- [ADR 0035 — Stack AI canônica](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — laravel/ai SDK (plug F2/SSE)
- [ADR 0039 — UI Chat Cockpit (padrão)](../../decisions/0039-ui-chat-cockpit-padrao.md) — layout-mãe da era V1; o V2 pivotou pra painel 1-col (paradigma Glean Home / Copilot M365)
- [ADR 0094 — Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — §5 SoC brutal + §7 transparência

**Stories cobertas:** US-COPI-COCKPIT-002 (V2 Analista IA · pivot Cowork 2026-05-15) — header do `.tsx`
**Tests:** sem suite Pest dedicada à tela hoje — plug F2 deve nascer com teste do shape do payload (contrato = charter, não a impl; ver proibições §"teste tautológico")

---

## HISTÓRICO — V1 MVP WhatsApp-style (era-anterior · morto)

> Registro condensado da implementação que este doc descrevia até 2026-05-15. **Nada desta seção existe mais no código.**

- **O que era:** `Cockpit.tsx` de 138 lin — clone WhatsApp com sidebar de conversas mock (3 grupos), `ChatTabs` Todos/OS/Equipe/Clientes, `ThreadContext`, resposta simulada `setTimeout(600/2400ms)` com texto literal *"Recebido, vou verificar e te respondo já já 👍"*, `localStorage` escrito em todo render (fora de `useEffect`), prop `conversaAtivaRealId` injetada e nunca consumida.
- **Por que morreu:** identificado como anti-pattern no amendment `COWORK_NOTES.amendment-jana-chat-block-renderer.md` (Cowork 2026-05-14/15) — a Jana não é um contato de WhatsApp, é analista. Pivot Cowork (dashboard cockpit) aceito → V2 substituiu in-place, como o charter (`supersedes_in_place`) previa.
- **Lápides das refs da era V1** (as 4 refs quebradas que o radar de frescor apontou):
  - `prototipo-ui/_cowork-export-2026-05-15/chat-jana.{jsx,css}` — dir quarentenado em `_BACKUP-NAO-USAR/snapshots-cowork/` (PR #1218, 2026-05-20, ordem Wagner "remover interpretações erradas") e depois **apagado do git** (PR #2977, 2026-06-18, "esteira ≠ armazém"; superseded por DS v6 + primitivos UI-0018). **Conteúdo sobrevive em [`prototipo-ui/cowork/chat-jana.jsx`](../../../prototipo-ui/cowork/chat-jana.jsx) + [`chat-jana.css`](../../../prototipo-ui/cowork/chat-jana.css)** (path canônico atual, o mesmo do charter `visual_source`).
  - `CRITIQUE-chat-jana-vs-amendment.md` (score F1.5 interim 78/100) — sem cópia viva; recuperável via git history: `git show 1070e3759b^:prototipo-ui/_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md`.

---

**Última atualização:** 2026-07-09 — re-validação de frescor (radar doc-freshness-score #4031, score 40 → alvo saudável). Supersede V1→V2 **consumado e registrado**: §1-§11 reescritas pro Cockpit V2 Analista IA live em `/ia/cockpit` (verificação estática contra `origin/main`: routes.php:40, ChatController@cockpit :505, mockJanaPayload :555, Cockpit.tsx 1022 lin, LS_TAB :133, tabs :936-:1020); V1 WhatsApp-mock condensado na seção HISTÓRICO com lápides das 4 refs purgadas (`_cowork-export-2026-05-15/` → quarentena PR #1218 → deleção PR #2977; protótipo sobrevive em `prototipo-ui/cowork/`). Fluxo vivo não exercitado em 2026-07.

**2026-05-15** — adicionado §AVISO supersede em curso V2 + refs charter/CRITIQUE/protótipo. §1-§11 (impl V1 da época) inalteradas. Original 2026-05-09 preservado (hoje condensado no HISTÓRICO acima).
