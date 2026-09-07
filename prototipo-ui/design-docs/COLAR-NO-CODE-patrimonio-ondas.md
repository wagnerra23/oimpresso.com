# COLAR NO CODE — Patrimônio (`Modules/AssetManagement`) · doc único · 2026-09-04

> **Resposta curta: 66 arquivos** para o Code exportar/migrar o módulo — **20 podem começar hoje** e **46 estão travados**, sendo **44 numa única pergunta sua**: Patrimônio é módulo próprio (`Pages/Patrimonio/**`) ou seção do Estoque (`Pages/Estoque/Patrimonio/**`)? Contagem por frente no bloco 6.
> **Reescreve este mesmo arquivo** (anti-scatter §2-ter) — atualiza o plano de 2026-09-01 com a releitura do `main` de hoje e preserva as 11 perguntas ⛔ [W].
> **Ponte, não canon.** Não escrevo no git: desce por `cowork-inbox`/Issue → PR, ou [W] cola 1×. **Não exportar `.md` para `cowork/`** (R1 do guard).

---

## Arquivos lidos no `main` NESTE turno (3 + 2 árvores)

| # | arquivo | o que me disse |
|---|---|---|
| 1 | **`memory/requisitos/AssetManagement/SCOPE.md`** | **a trava-raiz, literal no frontmatter**: `migracao_ui: "bloqueado-escopo — aguarda decisao [W]; ver proibicoes e o SCOPE deste modulo"`. Também: `permission_prefix: assetmanagement.*` (o código usa `asset.*` — divergência D7), `trust_required: L3`, `charter_adr: 0080`, `url_prefixes: /asset/*`, missão: *"bem de uso interno, nunca item vendável de estoque"*, e `contains[]` com os 7 controllers |
| 2 | **`Modules/AssetManagement/Routes/web.php`** | as rotas reais: `prefix('asset')` com `throttle:60,1` + stack UltimatePOS; `Route::resource` de **assets · allocation · revocation · settings** (`as: asset`, pra não colidir com `Manufacturing\SettingsController`) **· asset-maintenance** + `GET asset/dashboard`. Nenhuma rota Inertia |
| 3 | **`Modules/AssetManagement/Services/AssetService.php`** | o dado real do formulário de bem: `asset_code · name · quantity · model · serial_no · category_id · location_id · purchase_date · unit_price · depreciation · is_allocatable · description · purchase_type`; `asset_code` gerado por `setAndGetReferenceCount` + `asset_code_prefix`; garantias por `start_dates[]`/`months[]`/`additional_cost`/`additional_note`; `Media::uploadMedia` para imagem; spans `OtelHelper::spanBiz`; **`business_id` obrigatório em todo método** (o Service não acessa session — o caller passa) |
| 4 | árvore **`resources/js/Pages/**`** (759 arquivos, filtro `asset\|patrimonio\|aloca`) | **0 de 759.** Não existe **nenhuma** tela Inertia de patrimônio — confirmado hoje, não herdado |
| 5 | árvore **`Modules/AssetManagement/**`** | 4 Entities (`Asset`, `AssetMaintenance`, `AssetTransaction`, `AssetWarranty`) · 4 Services · 2 Notifications · `AssetUtil` · `Config/retention.php` · Routes web+api. **`prototipo-ui/design-docs/`** tem `charters/Patrimonio.{charter,casos}.md` e `contrato-cowork/patrimonio.contract.json` (9.902 B) — **fora** do canon `Pages/**`, logo não contam como trio |

**Ancoragem dupla:** alvo de layout = protótipo medido (bloco 3); âncora de implementação = os arquivos acima + os 7 controllers/4 Services que **já existem**. **A produção não está "atrasada de design" — ela está sem UI**: aqui o backend existe e a tela não. É o inverso do Repair.

---

## 0 · Leis que não se renegociam

1. **`migracao_ui: bloqueado-escopo` está escrito no `main`.** Nenhum PR de tela abre antes de [W] decidir o endereço (pergunta 1). Errar o endereço = refazer 12 arquivos.
2. **Patrimônio ≠ estoque vendável** (missão do SCOPE): bem de uso interno. Não misturar com produto.
3. **Append-only allocate/revoke:** correção de alocação é **transação nova**, nunca `DELETE` em `asset_transactions`.
4. **`activity_log` nunca é purgado** — o próprio `retention.php` diz isso, mesmo quando o dado-fonte é anonimizado.
5. **Isolamento é manual** (não há scope global): `business_id` em toda consulta e em toda validação (Tier 0, ADR 0093).
6. **Não inventar coluna na UI.** Custo de manutenção, data de conclusão, valor residual, motivo de baixa e **placa** não existem no schema. Ou viram migration, ou saem da tela.
7. **Autoridade de token:** `TabBar` do DS → protótipo → produção. Medido no protótipo: `NAV.ds-tabbar.jm-tabs` com **7 abas de módulo + 3 sub-abas**, **10 de 10 com estado ARIA**. Zero cor crua.
8. **Migrar é reescrever nas primitivas do DS** — não trocar classes no Blade AdminLTE.

---

## 1 · Ordem das frentes + âncora (MAPA do protótipo colhido do DOM)

Raiz: `DIV.ptr-root.mp-page` → header (2) · `NAV.ds-tabbar.jm-tabs` (7) · corpo · `DIV.ptr-aviso-live` (`role=status aria-live=polite aria-atomic=true`).

| # | frente | protótipo (T1 estável) | âncora no `main` | trava |
|---|---|---|---|---|
| **0** | Medir (fila de migração, os 9 Pest, baseline VRT das 17 telas Blade) | — | `Modules/AssetManagement/Tests/**` · as 17 views AdminLTE | 🟢 |
| **1** | **Sanear o backend vivo** — D1..D5 (permissão com `&&`, `asset.view` sem guarda, alocação sem trava de saldo, `asset_id` sem tenant, whitelist de auditoria com coluna morta) | — | os 7 controllers · `StoreAssetAllocationRequest` · `AssetAllocationService` · `Asset.php` | 🟢 **sem pixel de UI** |
| **2** | SPEC × schema · 7 contratos · job de retenção LGPD | — | `SPEC.md` · `prototipo-ui/contrato/` · `Config/retention.php` | 🟢 (contratos dependem da pergunta 1) |
| **3** | Fundação da UI (SubNav + helpers + `Config.tsx` + props da listagem) | Configurações **831 nós** · `.mp-body` (1) · 7 campos | `AssetSettingsController` · `AssetController@index` | ⛔ **[W] 1** |
| **4** | **Bens** — a tela-âncora (tabela · toolbar · form · drawer · exclusão+bulk) | Bens **905 nós** · `.ptr-list` (3) · grade do DS **8 colunas** · 17 botões | `AssetController` + `StoreAssetRequest`/`UpdateAssetRequest` | ⛔ **[W] 1** |
| **5** | Alocações + revogação | Alocações: 3 sub-abas (Ativas · Revogadas · Todas) medidas | `AssetAllocationController` · `RevokeAllocatedAssetController` · `AssetAllocationService` | ⛔ **[W] 1** |
| **6** | Manutenções + Garantias | Manutenções **916 nós** (grade DS 8 col) · Garantias **797 nós** | `AssetMaitenanceController` · `AssetWarrantyService` | ⛔ **[W] 1, 3, 4** |
| **7** | Painel · Auditoria · a11y · desligar o Blade | Painel **999 nós** · `.mp-body` (6 painéis) · Auditoria **855 nós** | `AssetController@dashboard` (já entrega 4 agregados) · `activity_log` | ⛔ **[W] 1, 5** |
| **8** | Capacidades novas: depreciação · transferência · baixa · QR | — (sem coluna no schema) | `US-ASSET-W01..W04` (backlog 🔒) | ⛔ **[W] 6, 7, 8, 9** |

**Receita do MAPA (reexecutável):** `document.querySelector('.ptr-root')` → filhos; abas = `nav button`; **esperar duas leituras iguais** de `querySelectorAll('*').length`.

---

## 1-bis · Instrução de execução (as duas primeiras — as únicas sem trava)

```
FRENTE 1 · PR-A3 — saldo livre vira regra de servidor        🔴 (o mais importante do módulo)
  ARQUIVOS A EDITAR   : Modules/AssetManagement/Http/Requests/StoreAssetAllocationRequest.php
                        Modules/AssetManagement/Services/AssetAllocationService.php
                        Modules/AssetManagement/Tests/Feature/<o que o PR-P1 indicar>
  REUSAR (não recriar): a conta que JÁ existe em Asset::forDropdown
                        (quantity − SUM(allocate) + SUM(revoke), havingRaw('quantity > 0'))
                        — hoje ela é UI (monta dropdown); vira validação. Não escrever conta nova.
                        AssetUtil · os spans OtelHelper que o Service já tem
  CRIAR               : nada além do teste. Zero migration, zero tela.
  NÃO TOCAR           : asset_transactions (append-only: revogar NÃO apaga alocação)
                        AssetService/AssetWarrantyService/AssetMaintenanceService (outro PR)
                        as 17 views Blade (nenhuma linha nesta frente)
                        activity_log
  PASSO A PASSO       : 1) regra de saldo no FormRequest, mensagem em PT-BR
                        2) trava também is_allocatable = 0
                        3) teste: requisição direta sem passar pelo dropdown é recusada
                        4) invariante testada: SUM(allocate) − SUM(revoke) ≤ quantity
  DADO                : assets.quantity · assets.is_allocatable · asset_transactions.transaction_type
  PARAR SE            : (a) já houver Pest cobrindo (PR-P1 mede antes — senão duplica cobertura)
                        (b) a trava exigir lock de linha/transação distribuída → para e pergunta;
                            não inventar semáforo em PHP

FRENTE 1 · PR-A4 — asset_id escopado ao tenant             🔴 (Tier 0)
  ARQUIVOS A EDITAR   : os 3 FormRequests de allocation · revocation · maintenance
                        + 1 teste cross-tenant (biz=1 × biz=99, ADR 0101)
  REUSAR              : o padrão de gate que o SalesTargetController do Essentials já usa
                        (User::where('business_id',…)->findOrFail antes de escrever)
  NÃO TOCAR           : nada além dos 3 FormRequests e do teste
  PARAR SE            : `exists` escopado quebrar algum fluxo de superadmin → declarar e decidir
```

As frentes 3–8 abrem cada uma em **sessão limpa**, depois da resposta de [W] — não aqui.

---

## 2 · Onda 0a — a11y do ALVO (o que falhou foi corrigido AQUI)

Bateria no protótipo servido, dark, após estabilizar. T1: Bens **905** · Painel **999** · Manutenções **916** · Garantias **797** · Auditoria **855** · Config **831** (duas leituras iguais em cada). T5 de sanidade: `BUTTON` com `cursor: pointer` ✔.

| # | item | medido | veredito | ação |
|---|---|---|---|---|
| A1 | falso interativo | **0** falsos nas 6 views | ✅ | — |
| A3 | ícone sem nome | **0 de 8–26** em 5 das 6 views ✅ · Auditoria **3 de 11** | ✅ / 🟠 **DS** | o módulo já faz certo: o helper `Ic` de `patrimonio-page.jsx` emite `aria-hidden="true"` **e** há um botão-ícone com nome acessível obrigatório. Os 3 anônimos da Auditoria são do `Alert`/`PeriodBar` do DS → bloco 7 |
| A5 | ARIA nas abas | **10 de 10** (7 de módulo + 3 sub-abas) | ✅ | TabBar do DS |
| A7 | alvo <24px | **1 de 17** | ⚪ | decisão [W] (a mesma dos outros módulos) |
| A10 | `aria-live` | **2** — o módulo tem `.ptr-aviso-live` **próprio** com `role="status" aria-live="polite" aria-atomic="true"` | ✅ | melhor que CRM e Ponto antes da correção de ontem |
| — | campo sem rótulo | **1 de 1** em **todas** as 6 views: a busca do header (`.mp-busca input`) | 🔴 → ✅ | **corrigido no build**: `aria-label="Buscar bem, código ou número de série"` + `aria-hidden` no glifo `⌕` (`patrimonio-page.jsx`) |
| — | `th scope` | **0 de 8** em Bens e Manutenções | 🟠 **DS** | a grade é `DataGrid`/`DataTablePro` do DS (tabela sem classe, estilo inline). **4º módulo** onde eu meço o mesmo defeito → bloco 7 |
| — | tabelas próprias | **nenhuma** — o módulo usa só a grade do DS | ✅ | nada a corrigir do meu lado |

**Build alterado neste ciclo:** `patrimonio-page.jsx` · `oimpresso.com.html` (bump `?v=pat9a11y`). Zero mudança de layout.

---

## 3 · ALVO medido por seção (read-only, dark)

| tela | alvo |
|---|---|
| **Bens** | `.ptr-list` com **3 filhos** · grade do DS **8 colunas** · 3 sub-abas com contador (`Ativas 3` · `Revogadas 2` · `Todas 5` na view de alocações; em Bens as sub-abas são todos/alocáveis/em manutenção/garantia crítica) · 17 botões · 905 nós |
| **Painel** | `.mp-body` com **6 filhos** (KPIs + painéis + pendências) · 26 ícones · 999 nós. **Os KPIs do alvo devem casar com os 4 agregados que o `AssetController@dashboard` já calcula** — nenhum inventado |
| **Manutenções** | `.ptr-list` (3) · grade do DS 8 col · 916 nós |
| **Garantias** | `.mp-body` (1) · 797 nós — faixa vigente/vencendo ≤90d/vencida/sem garantia |
| **Auditoria** | `.mp-body` (1) · 855 nós · `Alert` "Append-only · log `assetmanagement.asset`" + `PeriodBar` do DS |
| **Configurações** | `.mp-body` (1) · **7 campos** · 831 nós (prefixos · notificações · retenção em leitura · ações) |
| abas do módulo | `NAV.ds-tabbar.jm-tabs` — **7**: Painel · Bens · Alocações · Manutenções · Garantias · Auditoria · Configurações (com contadores mono) |

---

## 4 · Comportamento + invariantes

1. **Saldo é conta de servidor** — "alocado", "livre" e "residual" nunca somados no cliente.
2. **Revogar não apaga alocação** (append-only); devolve unidade ao saldo e registra o código `REV-`.
3. **Exclusão de bem com alocação ativa é recusada**, com o motivo escrito; a trilha de auditoria **sobrevive** à exclusão.
4. **Código do bem é gerado no servidor** (`setAndGetReferenceCount` + `asset_code_prefix`) — nunca no cliente.
5. **Filtro e paginação continuam server-side** (yajra hoje; query string na Page) — não reimplementar como filtro de cliente.
6. **E-mail de manutenção só sai se o toggle do business estiver ligado** (`asset_settings`), reusando as 2 Notifications existentes.
7. Estado vazio diz por que e o que fazer; status nunca é só cor.

---

## 5 · Não inventar

- **Componentes:** `AppShellV2` · `@/Components/ui` · `DataGrid`/`StatusBadge`/`PeriodBar`/`EmptyState` do DS · drawer para detalhe (PT-02), modal só para confirmação (PT-04).
- **Tokens:** accent roxo `oklch(0.55 0.15 295)` light / `oklch(0.70 0.15 295)` dark; underline na aba ativa, nunca pill. Zero hex cru.
- **Dados:** exatamente os campos do `AssetService` (lidos hoje, listados no bloco de leitura) + `asset_transactions` + `asset_warranties` (`start_date`, `end_date`, `additional_cost`, `additional_note`) + `asset_maintenances` (`status`, `priority`, `details`, `maintenance_note`, `maitenance_id` *(sic)*, `created_by`, `assigned_to`).
- **Copy:** do `lang/pt/lang.php` do módulo e das decisões de [W] — **não** do protótipo. PT-BR, sentence case, sem emoji.

---

## 6 · DoD + PLACAR + **contagem de arquivos**

### PLACAR Patrimônio — 2026-09-04

```
Telas Inertia em produção ........ 0 de 759 arquivos de Pages  (confirmado hoje)
Telas Blade legacy ............... 17 views AdminLTE
Trio (charter+casos no canon) .... 0        (o que existe está em design-docs, fora de Pages/**)
Contratos de tela ................ 0 no prototipo-ui/contrato/
Backend ......................... 7 controllers · 4 Services · 4 Entities · 9 Pest  ← existe e funciona
Defeitos medidos no backend vivo . 9 (D1–D9; 4 deles 🔴)
Colunas que a UI pede e não há ... 5 (custo e datas de manutenção · fornecedor de garantia · baixa · placa)
Export de .jsx do protótipo ...... 0  (o build já está no main, byte a byte)
```

### Quantos arquivos o Code precisa (a resposta)

| frente | novos | editados | total | trava |
|---|---:|---:|---:|---|
| 0 · medir + baseline (1 E2E + 1 VRT) | 2 | 0 | **2** | 🟢 |
| 1 · sanear backend (D1–D5) — 5 PRs | 0 | **14** | **14** | 🟢 |
| 2 · SPEC + job de retenção LGPD | 1 | 3 | **4** | 🟢 |
| 0b · ADR do endereço + `SCOPE.md` | 1 | 1 | **2** | ⛔ **[W] 1** |
| 2b · 7 `.contract.json` | 7 | 0 | **7** | ⛔ **[W] 1** |
| 3–7 · a UI inteira: **7 Pages + 7 charters + 7 casos + 2 `_shared` + 6 `_components`** | 29 | — | **29** | ⛔ **[W] 1** |
| 3–7 · controllers → Inertia (`Asset`, `AssetSettings`, `AssetAllocation`, `RevokeAllocated`, `AssetMaitenance`) + `Routes/web.php` | 0 | 6 | **6** | ⛔ **[W] 1** |
| 6–7 · testes das telas (E2E/a11y/contrato) | 3 | 0 | **3** | ⛔ **[W] 1** |
| 8 · capacidades novas: 3 migrations (custo/datas · transferência · baixa) + 2 testes | 5 | 0 | **5** | ⛔ **[W] 3, 6, 7, 8** |
| **total** | **48** | **18** | **66** | **46 travados** |

**66 arquivos em 36 PRs.**
- **20 destravados hoje** (frentes 0, 1 e 2): a rede, os **4 defeitos 🔴 de segurança/tenant** e a política LGPD que o repo já promete. **Nada disso espera [W]** — e é o que conserta o que já roda em produção.
- **46 travados**, e **44 deles numa pergunta só** (o endereço). Se a resposta for "seção do Estoque", os 29 arquivos de UI viram `Pages/Estoque/Patrimonio/**`, o `PatrimonioSubNav` **não nasce** (−1) e os 7 contratos viram `estoque-patrimonio-*` — **as seções medidas no bloco 3 não mudam**.

**Margem declarada:** a contagem das frentes 3–7 é **estimativa de plano** (nenhuma dessas telas existe para eu medir diff real) e assume 1 arquivo por tela + drawer/form como componente. A frente 8 sobe se [W] decidir baixa em **tabela própria** (+1 Entity, +1 Service).

**DoD por PR:** ≤8 arquivos · ≤~350 linhas · migration nunca com UI · correção de permissão nunca com tela · a mutação de saldo leva o teste no **mesmo PR** · lanes required verdes (`Casos-coverage · ratchet`, `Unit`, lane do módulo, `cowork-ssot-guard`, `prototipo-readiness`) · placar no corpo do PR · contrato destilado no charter no mesmo PR.

---

## 7 · O que a ancoragem NÃO resolve

| # | item | natureza | dono |
|---|---|---|---|
| 1 | **`migracao_ui: bloqueado-escopo`** — está escrito no `main` e trava 44 dos 66 arquivos. ADR 0180 chama Patrimônio de "ghost de Estoque"; ADR 0182 escreve "Estoque (AssetManagement+)". O SCOPE não decide | **decisão [W]** | **[W]** |
| 2 | **5 colunas que a UI pede e o schema não tem** (custo e datas de manutenção · fornecedor de garantia · baixa/disposal · placa veicular). O protótipo mostra custo, residual, placa e baixa — **quatro coisas sem coluna** | schema + decisão | [W] 3, 6, 7 |
| 3 | **`depreciation` existe e é gravada** (medido hoje no `AssetService`, em `criar` **e** `atualizar`) **mas nunca é calculada** — e a SPEC lista depreciação como backlog 🔒. Nem tratar como pronta, nem como inexistente | divergência SPEC × código | [W] 6 |
| 4 | **Prefixo de permissão divergente**: SCOPE diz `assetmanagement.*`, código usa `asset.*` + gate de assinatura `assetmanagement_module` | governança | [W] 2 |
| 5 | **Grade do DS** sem `th scope` e com `TH` ordenável sem semântica — **4º módulo** com o mesmo achado (CRM, Repair, HRM, Patrimônio). Já é dívida sistêmica, não achado | dívida do DS | pedido DS próprio |
| 6 | **LGPD declarada sem executor:** `retention.php` tem as janelas e `enabled=false`; o job `assetmanagement:retention-purge` está em backlog | implementação pendente | frente 2 (destravada) |
| 7 | **Não medi** neste turno: `screen-coverage`/`blade-migration-census` do módulo · o conteúdo dos 9 Pest · o `AssetManagementHealthCommand` · onde `asset.*` é registrada como permissão. Os defeitos D1–D9 vêm da medição de **01/09**, não de hoje | verificação pendente | frente 0 |
| 8 | Zero `<main>` no documento do protótipo (AP9) · rota do `app.jsx` sem componente (C6) | fundação / cobertura declarada | fundação |

---

## 8 · Não medido, declarado

- **Os 9 defeitos do backend (D1–D9) foram medidos em 2026-09-01, não hoje.** Hoje reli `Routes/web.php`, `AssetService.php` e `SCOPE.md` — os três confirmam o quadro (rotas, campos, trava de escopo), mas **não verifiquei** de novo `AssetMaitenanceController` (o `&&`), `AssetController@index` (a guarda ausente), `StoreAssetAllocationRequest` (o `exists` sem tenant) nem `Asset.php` (o `purchase_amount` morto). Se um PR mexeu neles desde então, meu número está velho.
- **Não verifiquei** hoje: os 7 controllers · `AssetAllocationService` · `AssetWarrantyService` · `AssetMaintenanceService` · `AssetUtil` · `Config/retention.php` · as 7 migrations · os 9 Pest · `SPEC.md`/`SUPERFICIE.md`/`BRIEFING.md` · as 17 views Blade · `memory/proibicoes.md` (a busca dirigida voltou **bounded** — "0 ocorrências" ali **não é prova**) · os 192 scorecards.
- **Contraste (A8):** não medido (exige OKLCH→sRGB com caso de sanidade).
- **Largura:** medido em ~841px (janela do preview), não em 1280px.
- **A contagem das frentes 3–8 é plano, não diff** — nenhuma dessas telas existe.

---

## 9 · Recibo

- **Build alterado (só a11y):** `patrimonio-page.jsx` · `oimpresso.com.html`.
- **Ponte:** este arquivo — **doc único do Patrimônio** (reescrito, sem doc novo).
- **Charter/casos:** o que existe hoje está em `prototipo-ui/design-docs/charters/Patrimonio.{charter,casos}.md` — **fora** do canon `Pages/**`. Quando o endereço for decidido, o trio nasce **por tela**, no PR de cada tela (nunca em lote — `casos-gate` G-2).
- **Pacote (regra de saída):** **não regenerado** — o gerador exige os arquivos em disco e não roda do meu lado (ADR 0374). O ciclo fecha **sem pacote**:

  ```
  node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
  ```

---

## RESÍDUO Patrimônio — as 11 decisões de [W] (travam 46 dos 66 arquivos)

| # | pergunta | trava |
|---|---|---|
| **1** | **Módulo próprio (`Pages/Patrimonio/**`) ou seção do Estoque (`Pages/Estoque/Patrimonio/**`)?** ADR 0180 × ADR 0182 × SCOPE `bloqueado-escopo` | **44 arquivos / 20 PRs** |
| 2 | Prefixo de permissão canônico: `asset.*` (código) ou `assetmanagement.*` (SCOPE)? | ADR do endereço + guarda do índice |
| 3 | **Custo de manutenção entra?** A coluna não existe — sem ela não há "quanto custei manter" | migration + KPI do Painel |
| 4 | Garantias é tela própria ou filtro de Bens? (não existe no Blade — é capacidade nova) | tela de Garantias |
| 5 | Auditoria de patrimônio é aba do módulo ou é do `Modules/Auditoria`? | tela de Auditoria |
| 6 | Depreciação (`W01`): tem sinal de cliente? Linear ou SAC? Fonte contábil? **A coluna já existe e é gravada** | `bem-depreciacao` + KPI residual |
| 7 | Baixa/disposal (`W03`): `status` no `assets` ou tabela própria? Hoje "dar baixa" = **deletar o bem** | migration + relatório contábil |
| 8 | Transferência entre locais (`W02`): transação com histórico ou edição do `location_id`? | migration |
| 9 | QR code + scan mobile (`W04`) entra nesta rodada ou vira Non-Goal escrito? | escopo |
| 10 | Retenção LGPD: liga o `assetmanagement:retention-purge` em canary quando? | frente 2 (o código pode nascer já) |
| 11 | Placa veicular: patrimônio e Oficina Auto falam do mesmo veículo? Não há coluna `placa` | fora do plano até responder |

**Enquanto isso, as frentes 0, 1 e 2 (20 arquivos, 12 PRs) começam hoje** — e são justamente as que consertam os 4 defeitos 🔴 do que já está em produção.
