# Pedido [CC] → [CL] · Fusão das telas do módulo Jana

- **Data:** 2026-08-06 · autor [CC] (Cowork) · aguarda ratificação [W]
- **Origem visual (build F1):** `prototipo-ui/cowork/jana-merge.jsx` + `jana-merge.css` (+ patch em `chat-jana.jsx`)
- **Tela no Cowork:** app único `oimpresso.com.html`, rota `chat` (`window.JanaPage`)
- **Leitura de base:** espelho local da pasta anexada — **não li `origin/main` neste turno**. Confirme os caminhos antes de aplicar.

---

## 1. O que existe hoje (medido no espelho, não no main)

| Rota | Page | Estado | Conteúdo real |
|---|---|---|---|
| `/ia` | `Jana/Chat.tsx` | live | Chat 2-col + blocos estruturados |
| `/ia/cockpit` | `Jana/Cockpit.tsx` (1.023 ln) | live | Brief + KPIs + 6 análises + ações + chat — port do `chat-jana.jsx` |
| `/ia/dashboard` | `Jana/Dashboard.tsx` (375 ln) | live | `JanaCockpitV2` (brief + KPIs + análises + ações) **+ Metas/farol** |
| `/ia/painel` | `Jana/Painel.tsx` (52 ln) | draft | **Hub de 3 links** pra Dashboard/Chat/Cockpit |
| `/ia/memoria` | `KB/MemoriaController` | live | Fatos LGPD |
| `/ia/pro` | `Jana/Pro.tsx` | draft | Paywall, modo foco |

**Diagnóstico:** o mesmo cockpit está implementado **duas vezes** (`Cockpit.tsx` e `JanaCockpitV2` dentro de `Dashboard.tsx`), e `Painel.tsx` é um menu de links — exatamente o nível de navegação que a fusão elimina. Os charters não descrevem isso: `Cockpit.charter.md` diz `absorbs_when_live: Dashboard.tsx`, mas na prática quem tem o cockpit vivo + metas é o Dashboard.

## 2. Decisão proposta

**Uma tela, `/ia`, com abas de área** (ModuleTopNav dentro da página, abaixo do PageHeader — igual Clientes):

`Painel` (default) · `Conversa` · `Memória` — e `Metas` como **seção do Painel**, não aba.

- **Painel** = `JanaCockpitV2` (o que já roda em `/ia/dashboard`) + **seção Metas** (farol, % do alvo, projeção) com drawer de meta (série de 12 janelas, delta vs. janela anterior, "Editar meta" → tela FOCO, "Conversar com a Jana").
- **Conversa** = `Chat.tsx` como está, mais **histórico de conversas** à esquerda (280px, filtros todas/minhas/compartilhadas/arquivadas, recolhe pra 40px, overlay abaixo de 1100px, J/K + ⌘⇧H).
- **Memória** = tela LGPD do KB como aba, mantendo o deep-link `/ia/memoria`; edição inline com **motivo obrigatório** e apagar com confirmação.
- **`/ia/pro` e `/ia/metas*` ficam FOCO** (páginas de decisão/formulário, sem abas).

## 3. Arquivos a mexer

**Apagar**
- `resources/js/Pages/Jana/Cockpit.tsx` + `Cockpit.charter.md` (duplicata do cockpit; a versão viva é o `JanaCockpitV2`)
- `resources/js/Pages/Jana/Painel.tsx` + `Painel.charter.md` + `Modules/Jana/Http/Controllers/PainelController.php` (hub de links)

**Renomear / mover**
- `Jana/Dashboard.tsx` → `Jana/Index.tsx` (a tela de `/ia`), recebendo a aba ativa por prop `aba` (`painel|conversa|memoria`)
- `DashboardController@index` → `IndexController@index`, mantendo os payloads `metas`, `sellKpis`, `insightsAggregates`, `coworkAggregates`, `janaContext`
- `ChatController@index` passa a render `Jana/Index` com `aba='conversa'` (thread + histórico), preservando `conversas.*`

**Criar**
- `Jana/_components/JanaTabs.tsx` — as 3 abas (ModuleTopNav canon, ícones lucide, `<Link>` Inertia com `data-active` por URL)
- `Jana/_components/MetasSecao.tsx` + `MetaDrawer.tsx` — extraídos do bloco de metas do Dashboard atual
- `Jana/_components/HistoricoConversas.tsx` — lista + filtros + colapso (persistir em `oimpresso.jana.hist`)
- `Jana/_components/JanaConfigDrawer.tsx` — o "Configurar" do header: brief (hora/áudio), quais análises rodam, HITL travado ligado, retenção da memória, plano
- `Jana/_components/AcaoHitlModal.tsx` — prévia da mensagem + `idle → enviando → feito` (nada dispara sem aprovação por mensagem)
- `Jana/_components/DrillOrigem.tsx` — drawer "de onde vem esse número" por análise

**Rotas (`Modules/Jana/Http/routes.php`)**
```php
Route::get('/',            'ChatController@index')->name('jana.chat.index');   // aba conversa
Route::get('/painel',      'IndexController@index')->name('jana.index');        // aba painel (default de /ia?aba=)
Route::redirect('/cockpit',   '/ia/painel', 301);
Route::redirect('/dashboard', '/ia/painel', 301);
```
- `/ia/memoria` **fica** (deep-link LGPD citável) e passa a render com a aba Memória ativa.
- `jana.dashboard.index` está referenciado em nav/topnav e no `JanaAreaHeader` — **grep antes** e apontar pra `jana.index`; manter o nome de rota antigo como alias se aparecer em `Config/topnav.php` ou em e-mails/jobs.

**Charters** — fundir `Cockpit.charter.md` + `Dashboard.charter.md` + `Painel.charter.md` em **`Index.charter.md`** (`page: /ia`, `absorbs: [Cockpit.tsx, Painel.tsx, Dashboard.tsx]`, non-goals: sem CRUD de meta, sem billing, sem aba pro Pro) + `Index.casos.md` com UC por aba. `Chat.charter.md` e `Memoria.charter.md` passam a referenciar a tela única.

**Contrato de tela** (ADR 0286) — `prototipo-ui/contrato/jana-index.contract.json`: seções `abas`, `brief`, `kpis`, `metas`, `analises`, `acoes`; copy literal das abas (`Painel`/`Conversa`/`Memória`); estados `dados|vazio|erro|carregando` e o gating Grátis/Pro.

## 4. Regras que o protótipo já fixou (não afrouxar na tradução)

1. HITL não é opcional: o toggle "Aprovação obrigatória" é **travado ligado**.
2. Todo card de análise abre a origem do número (tabela + service + hora da apuração + escopo `business_id`).
3. Memória: editar exige **motivo**, apagar exige confirmação individual (LGPD Art. 18) — sem bulk.
4. Projeção de meta: cumulativa extrapola ritmo; **média/taxa projeta tendência da série** (ticket e utilização nunca multiplicam).
5. Nenhum botão que não faz nada: se a tela real é outra, o botão diz isso.
6. Texto de 10.5px usa `--text-dim` (`--text-mute` só ≥11.5px ou não-texto).
7. Painel abaixo de 768px avisa que foi desenhado pro escritório e aponta a Conversa.

## 5. Riscos

- `Dashboard.tsx` tem cabeçalho `@memcofre` com `tela: /copiloto/dashboard` e stories US-COPI-010/011/012 — atualizar junto com o rename, senão o freshness test acusa drift.
- Comentários no `routes.php` já registram muita migração (Governance/Forja/TeamMcp). Encaixar os 301 novos no bloco de redirects existente, não criar um segundo.
- `PainelController` carrega mock inteiro (`buildMockPayload`) — some com ele, não migre o mock.
- Permissão `jana.access` continua valendo pra tela única; nada de nova permissão por aba.

## 6. Pendências que ficam abertas (não são do [CL])

- Dados por empresa no protótipo (mock é do Martinho nas três empresas) — precisa decisão [W].
- Arquivar/renomear/apagar conversa, inserir fato manual, paginação da memória, período custom nas metas (o DS tem `PeriodBar`).
