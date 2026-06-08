# HANDOFF · Clientes — para Claude Code

> **Status:** Mockup HTML/JSX pronto neste projeto (`Oimpresso ERP - Clientes.html`).
> **Score do método KB-9.75:** 9,4/10 (Refinos #1 + #2 + #3 aplicados).
> **Próxima sessão:** portar pra `wagnerra23/oimpresso.com` com Laravel 13 + Inertia v3 + React 19 + Tailwind v4 + shadcn/ui.

---

## 0. O que esse mockup entrega

Tela completa de **Clientes** dentro do Cockpit (sidebar dual dark + main, sem Apps Vinculados), com:

- **Listagem** densa: 32 clientes BR (mix PF/PJ), filtros, busca, 6 estados (cheia / vazio / busca / sem resultado / loading skeleton / linha selecionada), 3 layouts (tabela / cards / split)
- **Drawer 760px** com **8 tabs**: Identificação · Contato · Endereço · Comercial · Classificação · OSs · IA · Auditoria
- **Validação BR inline** (CPF/CNPJ mod 11, e-mail, CEP) + máscaras automáticas
- **Lookups externos mock**: BrasilAPI (CNPJ → razão social) e ViaCEP (CEP → endereço)
- **IA** (`window.claude.complete`) em 4 cards: resumo de relacionamento, reavaliar segmento/tags, próxima ação sugerida, score de risco (determinístico)
- **C2 LGPD**: timeline de auditoria com 6 tipos de eventos (created, field, status, view, OS, note) + botão "Exportar log"
- **C5 Anotações**: comentários por cliente (persistidos em `localStorage`)
- **S1 Favoritos pessoais**: estrela separada do VIP global (persiste em `localStorage`)
- **S3 Imprimir ficha**: exporta PDF com brand Oimpresso via `window.open` + `window.print`
- **N2 ⌘K Command Palette** com fuzzy de clientes + ações + Empty-state IA
- **N3 J/K + atalhos**: navegação por teclado completa (incluindo 1-8 trocar tab)
- **P2 Cheat-sheet** flutuante (?)
- **C4 Revalidar**: pílula laranja em clientes com cadastro > 365d e sem compra há > 180d

---

## 1. Estrutura de arquivos do mockup (referência)

```
Oimpresso ERP - Clientes.html       # entry — carrega tudo
cockpit.css                          # já existe no repo (manuais/copiloto/cockpit.css)
clientes.css                         # estilos da listagem + drawer
clientes-975.css                     # estilos do Refino #1 (cmdk, cheat-sheet, fresc pill)
clientes-tabs.css                    # estilos do Refino #3 (OSs, IA, Auditoria)

tweaks-panel.jsx                     # apenas pro mockup — NÃO portar
chat-icons.jsx                       # base de ícones Lucide-style
clientes-icons.jsx                   # adições + máscaras + validadores BR + relDate + avatarFor
clientes-data.jsx                    # 32 mocks (substituir por API real)
chat-sidebar.jsx                     # já existe — apenas garantir item "Clientes" ativo
clientes-listagem.jsx                # FilterDropdown + ActiveChip + EmptyState + LoadingSkeleton
clientes-table.jsx                   # ClientesPage + ClientesTable + Cards + Split
clientes-drawer.jsx                  # ClienteDrawer + 5 seções de form + HistoricoStrip
clientes-975.jsx                     # CommandPalette · CheatSheet · KBScore · ResumoIA
                                     # NoResultsIA · printFicha · FrescorPill · useFavoritos
                                     # AutoSugest · ComentariosBox · RevalidarPill
clientes-tabs.jsx                    # AuditTab + OssTab + IATab + AutoSugestForce + fakeAudit
clientes-app.jsx                     # root composition
```

**Portar para o repo real:** mapear cada `.jsx` em um `.tsx` em
`resources/js/Pages/Clientes/` ou `resources/js/Components/clientes/`.

---

## 2. Schema canônico (BR)

Campos do cliente, em 5 seções de formulário + 3 tabs de visualização.

### 2.1 Identificação

| Campo | Tipo | Máscara/Validação | Obs |
|---|---|---|---|
| `tipo` | enum('PF','PJ') | — | toggle no header do drawer |
| `nome` | string(255) | obrig., min 3 | "Razão social" se PJ, "Nome completo" se PF |
| `fantasia` | string(255) | opcional | só PJ |
| `doc` | string(18) | **CPF mod 11** se PF, **CNPJ mod 11** se PJ | máscara automática |
| `ie` | string(20) | opcional | só PJ — Inscrição estadual |
| `rg` | string(20) | opcional | só PF |
| `nascimento` | date | opcional | só PF |
| `contato` | string(120) | opcional | só PJ — nome do responsável |
| `cargo` | string(80) | opcional | só PJ |

### 2.2 Contato

| Campo | Tipo | Máscara/Validação | Obs |
|---|---|---|---|
| `tel` | string(20) | máscara `(00) 0 0000-0000` | principal |
| `tel2` | string(20) | máscara idem | alternativo |
| `email` | string(120) | regex e-mail | inline error |
| `site` | string(120) | opcional | — |
| `canal` | enum | `whatsapp`, `email`, `telefone`, `presencial` | radio |

### 2.3 Endereço (ViaCEP)

| Campo | Tipo | Máscara/Validação | Obs |
|---|---|---|---|
| `cep` | string(9) | `00000-000`, 8 dígitos | dispara busca ao blur |
| `endereco` | string(180) | obrig. | logradouro |
| `numero` | string(10) | obrig. | — |
| `complemento` | string(80) | opcional | apto, conjunto |
| `bairro` | string(80) | obrig. | — |
| `cidade` | string(120) | obrig. | — |
| `uf` | enum 27 UFs | obrig. | select |

**ViaCEP**: `GET https://viacep.com.br/ws/{cep}/json/` retorna `logradouro`, `bairro`, `localidade` (cidade), `uf`. No mockup é simulado com 700ms de delay.

### 2.4 Comercial

| Campo | Tipo | Obs |
|---|---|---|
| `limite` | integer (centavos) | limite de crédito; vazio = sem limite |
| `prazo` | integer | dias de prazo padrão |
| `tabelaPreco` | enum | `padrao`, `varejo`, `atacado`, `parceiro` |
| `pgto` | enum | `pix`, `boleto`, `cartao`, `dinheiro`, `transferencia` |
| `obsComercial` | text | observações livres |

### 2.5 Classificação

| Campo | Tipo | Obs |
|---|---|---|
| `segmento` | enum | `varejo`, `atacado`, `agência`, `corporativo`, `evento`, `governo` |
| `tags` | string[] | multi-select de 9 valores (ver `TAG_OPTIONS` em clientes-data.jsx) |
| `status` | enum | `ativo`, `inativo`, `bloqueado` |
| `vip` | boolean | flag global (diferente de `favorito_pessoal`) |

### 2.6 Tabs derivadas (read-only no MVP)

- **OSs**: `OrdemServico::where('cliente_id', $cliente->id)->latest()->paginate(14)`
- **Auditoria**: `AuditLog::where('subject_type', Cliente::class)->where('subject_id', ...)` — usar `spatie/laravel-activitylog` que já está no repo
- **IA**: 4 endpoints opcionais (ver §5.4) ou tudo client-side via `window.claude.complete`

---

## 3. Migração de banco (sketch)

```php
// database/migrations/2026_05_22_000000_create_clientes_table.php
Schema::create('clientes', function (Blueprint $t) {
    $t->id();
    $t->foreignId('company_id')->constrained();   // Tier 0 isolation (já existe)
    $t->foreignId('branch_id')->nullable();        // Tier 1 (futuro)

    $t->enum('tipo', ['PF', 'PJ']);
    $t->string('nome', 255);
    $t->string('fantasia', 255)->nullable();
    $t->string('doc', 18)->index();                // CPF ou CNPJ formatado
    $t->string('doc_clean', 14)->unique();         // apenas dígitos — busca rápida
    $t->string('ie', 20)->nullable();
    $t->string('rg', 20)->nullable();
    $t->date('nascimento')->nullable();
    $t->string('contato', 120)->nullable();
    $t->string('cargo', 80)->nullable();

    $t->string('tel', 20)->nullable();
    $t->string('tel2', 20)->nullable();
    $t->string('email', 120)->nullable();
    $t->string('site', 120)->nullable();
    $t->enum('canal', ['whatsapp','email','telefone','presencial'])->nullable();

    $t->string('cep', 9)->nullable();
    $t->string('endereco', 180)->nullable();
    $t->string('numero', 10)->nullable();
    $t->string('complemento', 80)->nullable();
    $t->string('bairro', 80)->nullable();
    $t->string('cidade', 120)->nullable();
    $t->string('uf', 2)->nullable();

    $t->integer('limite_centavos')->nullable();
    $t->integer('prazo_dias')->nullable();
    $t->string('tabela_preco', 20)->nullable();
    $t->string('pgto', 20)->nullable();
    $t->text('obs_comercial')->nullable();

    $t->string('segmento', 30)->nullable();
    $t->json('tags')->nullable();
    $t->enum('status', ['ativo','inativo','bloqueado'])->default('ativo');
    $t->boolean('vip')->default(false);

    $t->timestamps();
    $t->softDeletes();
});

// Tabela auxiliar para favoritos pessoais (S1)
Schema::create('cliente_favoritos', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained();
    $t->foreignId('cliente_id')->constrained();
    $t->timestamps();
    $t->unique(['user_id', 'cliente_id']);
});

// Comentários (anotações C5) — uma tabela polimórfica reusável
Schema::create('anotacoes', function (Blueprint $t) {
    $t->id();
    $t->morphs('subject');                         // subject_type + subject_id
    $t->foreignId('user_id')->constrained();
    $t->foreignId('company_id')->constrained();    // Tier 0
    $t->text('texto');
    $t->timestamps();
});
```

---

## 4. Rotas + controllers

```php
// routes/web.php — grupo dentro de Vendas
Route::middleware(['auth','verified'])->prefix('clientes')->name('clientes.')->group(function () {
    Route::get('/',              [ClienteController::class, 'index'])->name('index');
    Route::get('/{cliente}',     [ClienteController::class, 'show'])->name('show');
    Route::post('/',             [ClienteController::class, 'store'])->name('store');
    Route::put('/{cliente}',     [ClienteController::class, 'update'])->name('update');
    Route::delete('/{cliente}',  [ClienteController::class, 'destroy'])->name('destroy');

    Route::post('/{cliente}/favorito', [ClienteController::class, 'toggleFavorito']);
    Route::post('/{cliente}/anotacoes', [AnotacaoController::class, 'store']);
    Route::delete('/anotacoes/{anotacao}', [AnotacaoController::class, 'destroy']);

    Route::get('/{cliente}/ficha-pdf', [ClienteController::class, 'fichaPdf'])->name('ficha');
    Route::get('/{cliente}/auditoria', [ClienteController::class, 'auditoria'])->name('audit');

    Route::get('/lookup/cnpj/{cnpj}',  [ClienteLookupController::class, 'cnpj']);  // proxy BrasilAPI
    Route::get('/lookup/cep/{cep}',    [ClienteLookupController::class, 'cep']);   // proxy ViaCEP
});
```

**ClienteController@index** retorna:
```php
Inertia::render('Clientes/Index', [
    'clientes' => Cliente::filter($request)->paginate(50),
    'filters'  => $request->only(['q','tipo','status','uf','tags','sem_compra','com_saldo']),
    'meta'     => [
        'total'  => Cliente::count(),
        'ativos' => Cliente::where('status','ativo')->count(),
        'favoritos' => auth()->user()->clientesFavoritos()->pluck('cliente_id'),
    ],
]);
```

**Lookup CNPJ:** proxy server-side pro `https://brasilapi.com.br/api/cnpj/v1/{cnpj}` com cache de 30 dias (Redis). Não bater de frontend pra preservar rate limit + auditoria.

**Lookup CEP:** idem `https://viacep.com.br/ws/{cep}/json/`, cache 90 dias.

---

## 5. Componentes a portar (.tsx)

### 5.1 Listagem

| Mockup | Real (caminho sugerido) |
|---|---|
| `ClientesPage` | `Pages/Clientes/Index.tsx` |
| `ClientesTable` | `Components/clientes/ClientesTable.tsx` |
| `ClientesCards` | `Components/clientes/ClientesCards.tsx` |
| `ClientesSplit` | `Components/clientes/ClientesSplit.tsx` |
| `FilterDropdown` | reusar `PageFilters` shared se possível |
| `StatusPill` / `TipoPill` / `TagChip` | `Components/clientes/Pills.tsx` |
| `FrescorPill` | `Components/clientes/FrescorPill.tsx` |
| `EmptyState` / `NoResultsIA` | `Components/shared/EmptyState.tsx` (já existe — só estender) |

### 5.2 Drawer

| Mockup | Real |
|---|---|
| `ClienteDrawer` | `Pages/Clientes/Drawer.tsx` (sheet do shadcn/ui) |
| `SectionIdentificacao` / `Contato` / `Endereco` / `Comercial` / `Classificacao` | `Components/clientes/sections/*.tsx` |
| `HistoricoStrip` | `Components/clientes/HistoricoStrip.tsx` |
| `OssTab` | `Components/clientes/OssTab.tsx` |
| `IATab` (com 4 sub-cards) | `Components/clientes/IATab.tsx` |
| `AuditTab` | `Components/clientes/AuditTab.tsx` |

### 5.3 Helpers

| Mockup | Real |
|---|---|
| `BRMask` (cpf/cnpj/tel/cep) | `Lib/br-mask.ts` |
| `BRValidate` (mod 11 checks) | `Lib/br-validate.ts` |
| `BRL` (currency) | `Lib/format.ts` |
| `relDate` | `Lib/format.ts` |
| `avatarFor` / `initialsFor` | `Lib/avatar.ts` |
| `useFavoritos` | hook com `usePage().props.favoritos` + POST otimista |
| `useComments` | hook com `usePage().props.anotacoes` + POST otimista |

### 5.4 IA — substituir `window.claude.complete`

No mockup é client-side. Em produção, criar endpoints server-side pra preservar prompt + cache:

```
POST /clientes/{cliente}/ia/resumo       → ResumoIAController
POST /clientes/{cliente}/ia/sugest-tags  → SugestaoTagsController
POST /clientes/{cliente}/ia/proxima-acao → ProximaAcaoController
GET  /clientes/{cliente}/ia/risco        → RiscoController (determinístico, não precisa LLM)
```

Reusar `App\Services\Copiloto\ClaudeClient` que já existe no repo (módulo Copiloto). Todos os 4 endpoints devem honrar a quota `copiloto.admin.custos` (US-COPI-070).

**Score de risco** (`RiscoController`) é determinístico — calcula peso de sinais (saldo aberto, frescor, status, completude de dados). Não chama LLM. Já implementado em `clientes-tabs.jsx::RiscoCliente`.

---

## 6. KB-9.75 features mapeadas

| # | Categoria | Feature | Mockup | Backend a fazer |
|---|---|---|---|---|
| N2 | Nav | Command palette ⌘K | ✓ | `GET /search?q=` cross-module (já tem skeleton no Copiloto?) |
| N3 | Nav | J/K + atalhos | ✓ | nenhum |
| C2 | Cur | Histórico de alteração | ✓ (mock) | `spatie/laravel-activitylog` no model Cliente + endpoint `/auditoria` |
| C3 | Cur | Frescor pill | ✓ | computed accessor `$cliente->frescor` |
| C4 | Cur | Re-verificar | ✓ | computed `$cliente->needs_revalidacao` |
| C5 | Cur | Anotações inline | ✓ (localStorage) | tabela `anotacoes` polimórfica + endpoint |
| I1 | IA | Resumir relacionamento | ✓ | `POST /ia/resumo` |
| I3 | IA | Auto-sugest | ✓ | `POST /ia/sugest-tags` |
| I4 | IA | Empty-state IA | ✓ | reusar `POST /ia/perguntar` global |
| S1 | Saída | Favoritos pessoais | ✓ (localStorage) | tabela `cliente_favoritos` |
| S3 | Saída | Imprimir ficha | ✓ (window.print) | `GET /ficha-pdf` via DomPDF ou Browsershot |
| P2 | Princípio | Atalhos visíveis (cheat-sheet) | ✓ | nenhum |

---

## 7. Plano de execução (3 fases)

### F1 — MVP (~3-4 dias)

- Migration `clientes` + `cliente_favoritos` + `anotacoes`
- `ClienteController` CRUD + `FormRequest` com validators BR (mod 11)
- `Pages/Clientes/Index.tsx` com tabela e filtros
- `Drawer.tsx` com 5 seções de form + validação inline
- Proxies CNPJ + CEP (BrasilAPI + ViaCEP) com cache Redis
- **Cobertura KB-9.75:** tudo de Identificação a Classificação (5 tabs base)

### F2 — Refinos KB-9.75 (~2-3 dias)

- Favoritos pessoais (tabela + endpoints + hook)
- Anotações (tabela polimórfica + endpoints)
- Histórico de alteração via `activitylog`
- FrescorPill + RevalidarPill (computed accessors)
- Tabs OSs + Auditoria
- Atalhos teclado (J/K, ⌘K, 1-8, ⌘P, ?)
- Imprimir ficha PDF (Browsershot)

### F3 — IA (~2 dias)

- 4 endpoints IA reusando `ClaudeClient`
- IA tab com 4 cards
- Command palette empty-state IA
- AutoSugest pré-cadastro

---

## 8. Limites operacionais (NÃO fazer)

- **NÃO** mover Apps Vinculados pra Clientes — decisão do PO em 2026-05-21 (esta tela foca em CRUD de cliente, não cross-module). O painel direito permanece exclusivo do Copiloto e da Inbox.
- **NÃO** introduzir 6ª cor de origin badge para "CLI" — reusar **CRM** (azul) onde necessário (ver ADR UI-0008).
- **NÃO** usar `window.claude.complete` direto em produção — sempre via server-side endpoint que respeita quota `copiloto.admin.custos`.
- **NÃO** quebrar a ACL `tenant_id`/`company_id` — toda query de Cliente passa por scope global `BelongsToCompany`.
- **NÃO** chamar BrasilAPI/ViaCEP de frontend — sempre via proxy server-side com cache.
- **NÃO** persistir favoritos + anotações em `localStorage` no produto real — esse é truque do mockup; em produção é banco.

---

## 9. Checklist de pronto (validar antes de mergear F1)

- [ ] Larissa em monitor 1280×1024 consegue ver tabela inteira sem rolar horizontalmente
- [ ] Wagner cadastra cliente PJ novo com Buscar CNPJ funcionando em < 5s
- [ ] CPF/CNPJ inválido mostra erro inline antes do save
- [ ] CEP preenche endereço automaticamente (ViaCEP proxy)
- [ ] Filtros combinam (status=ativo + UF=SP + tags=varejo + sem_compra=90 → query otimizada)
- [ ] Drawer cabe em < 1200px sem overflow horizontal
- [ ] Sem console error em produção (`APP_ENV=production`)
- [ ] LGPD: endpoint `/auditoria` lista todos os acessos do user ao cliente
- [ ] PT-BR em todo label e mensagem de erro

---

## 10. Próximas iterações (após F3)

- **N5 Responsive**: 3-tabs em mobile abaixo de 1100px (Conversas/Thread/Contexto pattern)
- **S2 Modo apresentação**: read-only fullscreen pra reunião com chefe
- **G3 Trilhas**: onboarding "como cadastrar bem um cliente" pra novo atendente
- **Multi-tenant Tier 1**: filiais + `branch_id` em todas as queries

---

**Última atualização:** 2026-05-22
**Autor da spec:** Claude Design (mockup)
**Executor sugerido:** Claude Code (PR em `wagnerra23/oimpresso.com`)
