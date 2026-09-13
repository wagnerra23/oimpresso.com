# Pedido [CC] → [CL] · Jana — ondas de PR pra fechar o **módulo inteiro**

- **Revisão 2** · 2026-08-09 · autor [CC] (Cowork) · aguarda ratificação [W]
- **Base de leitura:** `origin/main` **lido neste turno** (2026-08-09, 20:24Z→22:14Z). O `main` **andou durante a sessão**: as leituras de código são do tree `f1a9606cca64`, as de governança do tree `00a25298e597`. Nenhum dos dois é commit sha — confira antes de aplicar.
- **Lido:** `Modules/Jana/**` (tree, 554 arq.) · `Http/routes.php` · `IndexController` · `MetasController` · `PeriodosController` · `AlertasController` · `SuperadminController` · as 9 views Blade · tree `resources/js/Pages/Jana/` · `Index.tsx` · `JanaAreaHeader.tsx` · `governance/route-hits.json` · `package.json` · `scripts/governance/module-surface.mjs` · `memory/requisitos/**/SUPERFICIE.md` (37 arquivos).
- **Substitui:** a tabela de ondas do `JANA-FASE2-2026-08-07.md` §2. As **fatias D–L daquele pedido continuam válidas e não são reescritas aqui**. Continua `JANA-FUSAO-2026-08-06.md` (ondas 1–4, aplicadas).

### O que mudou da revisão 1 pra esta

A revisão 1 tratava os 8 blades como dívida do módulo Jana. A leitura da governança mostrou que **é dívida do repositório, e o instrumento que enxergaria isso já existe e está cego**. Por isso entrou a **Onda 0**, transversal, antes de tudo — e a ordem das ondas do módulo passa a depender do que ela medir.

---

## 0.a Onde estamos — verificado no `main` em 2026-08-10T11:45Z (tree `aec4d8d5ac70`)

> ⚠️ **Colisão de numeração.** O repo já usa "Wave N" com outra contagem (`MetasController`: "D8.c (Wave 14)", `AlertasController`: "Wave 17"). As ondas deste documento são **[CC]-0 a [CC]-12** e não têm relação com aquelas — citar sempre com o prefixo.

| Onda | Estado | Prova lida neste turno |
|---|---|---|
| **[CC]-0** | 🟡 **metade feita** | A-1 e A-3 **fechados no `main`** (abaixo). A-2 mudou de forma; A-4 e P-1/P-2 abertos. |
| **[CC]-5** | 🟢 **fechada** | os três PRs entraram — ver quadro abaixo |
| **[CC]-6 · 7** | ⚪ não começou | `Pages/Jana/_components/` segue sem `AcaoHitlModal.tsx` e sem `JanaConfigDrawer.tsx` |
| **[CC]-8 · 10 · 11** | ⚪ não começou | as **8 blades de tela continuam vivas**, todas `@extends('layouts.app')` |
| **[CC]-9 · 12** | ⚪ não começou | — |

### O que fechou

| Item | Como está hoje no `main` |
|---|---|
| **S-1** ✅ | `MetasController@reapurar` faz `ApurarMetaJob::dispatch($meta, now(), (int) $meta->business_id)` — com `business_id` explícito porque o worker não tem `session()`, e mensagem de retorno honesta ("enfileirada … atualiza quando a fila processar"). Melhor que o que pedi. |
| **S-2** ✅ (a metade da [CC]-5) | `AlertasController@updateConfig` agora devolve **"Ainda não é possível salvar a configuração de alertas — nada foi alterado."** e o docblock diz o porquê + aponta a US-COPI-061. O FormRequest segue validando de propósito ("o contrato de entrada não deve regredir"). **Persistir continua aberto → [CC]-10.1.** |
| **S-3** ✅ | busca por `STUB` / `spec-ready` / `em breve` nas 9 views: **zero ocorrência**. Só sobra nos docblocks dos controllers (não renderiza). |
| **A-1** ✅ | `PAPEIS`: `Views (Blade)` está `listar: true` **desde 2026-08-09**, e o comentário no código dá exatamente a razão desta auditoria — o índice imprimia "9" acima de "4" e ninguém via quais eram os 9. `listar:false` sobrou só em `Testes (Pest)`, onde a regra vale. |
| **A-3** ✅ | `--migracao` existe e funciona. O docblock conta a história: nasceu no #5246, foi **deletado por acidente** num rebase (#5327), ficou **3 dias saindo `exit 2`** com o `package.json` órfão e 17 SCOPE.md citando uma fila morta. |

### O que isso muda no plano

- **A-2 encolhe e muda de dono.** Apareceu `scripts/governance/blade-migration-census.mjs` (ADR 0277), que **é o dono do eixo "servido"** — endpoint que entrega Blade, não call-site. O `--migracao` já junta censo + arquivos + `migracao_ui:` do SCOPE. Então A-2 **não é mais** "criar seção que mede": é **levar o recorte por rota pro `SUPERFICIE.md`** consumindo o `--resumo-json` do censo. Construir contador próprio agora seria segundo medidor do mesmo eixo — o próprio arquivo proíbe isso citando §5 proibicoes.
- **A-4 ficou barato**: `npm run migracao:report` roda e já imprime a fila ordenada por endpoint servido, com aviso de que **o núcleo é a maior fatia e não entra na fila dos módulos**. É rodar e levar a [W].
- **Item novo — S-1b (Tier 0):** o próprio `reapurar` documenta que a outra metade da US-COPI-031 ("apaga MetaApuracao do range") segue aberta **por decisão de design**: a rota não tem parâmetro de range, e apagar tudo pra reexecutar `now()` destruiria as 12 janelas que a US-COPI-011 exige na tela de detalhe. Reapurar intervalo **exige contrato de rota novo** → decisão [W], e cai naturalmente dentro da [CC]-8.2 (o detalhe da meta é quem mostra as 12 janelas).
- **Contexto novo pra [CC]-8:** `StoreMetaRequest` e `UpdateMetaRequest` já existem (Wave 14) com slug regex, whitelist de enums e mensagens PT-BR. O form Inertia da 8.3 **consome esse contrato**, não inventa outro — e a assimetria do `tipo_agregacao` deixa de ser "bug ou regra?" com uma pergunta: o `UpdateMetaRequest` aceita, a view não oferece.

---

## 0.b Estado medido no `main`

| Superfície | Rota | Camada hoje | Estado |
|---|---|---|---|
| Painel | `GET /ia` | Inertia `Jana/Index` | ✅ vivo, dado real (`SellsCockpitAggregator`) |
| Conversa | `GET /ia/conversa` | Inertia `Jana/Chat` | ✅ vivo |
| Memória | `GET /ia/memoria` | Inertia `Jana/Memoria` (controller no `Modules/KB`) | 🟠 vivo, abaixo do próprio charter |
| Pro | `GET /ia/pro` | Inertia `Jana/Pro` | 🟠 vivo, ativa estado **mock** |
| **Metas** | `/ia/metas` `+ create/show/edit` | **Blade AdminLTE** `copiloto::metas.*` | 🔴 órfã |
| **Alertas** | `/ia/alertas` `+ /config` | **Blade AdminLTE** `copiloto::alertas.*` | 🔴 órfã, stub declarado na tela |
| **Fonte da meta** | `/ia/metas/{id}/fonte` | **Blade AdminLTE** `copiloto::fontes.show` | 🔴 órfã, `<pre>` de JSON |
| **Superadmin metas** | `/ia/superadmin/metas` | **Blade AdminLTE** `copiloto::superadmin.metas` | 🔴 órfã |
| Períodos da meta | `POST/PATCH/DELETE /ia/metas/{m}/periodos` | — | 🔴 **rota sem tela nenhuma** |
| Digest semanal | e-mail | `emails/weekly-digest.blade.php` | ✅ Blade legítimo (**não migrar**) |

**O placar já estava escrito e ninguém leu.** `memory/requisitos/Jana/SUPERFICIE.md` (gerado por máquina, 572 arquivos em 19 papéis) traz, uma seção abaixo da outra:

```
## Views (Blade) — 9
## Telas (Inertia/React) — 4
```

Nove contra quatro. Mas o papel `Views (Blade)` no gerador tem `listar: false` — ele imprime **a contagem e um link de pasta**, sem nome de arquivo, sem rota, sem se alguém chega lá. O instrumento mediu certo e apresentou de um jeito que não acusa nada.

**Assimetria que define o pedido:** o backend está muito à frente da UI. `ApurarMetaJob`, `AlertaService`, `BriefDiarioService`, `SuggestionEngine`, `HitlEscalationService`, `DsrService`, `RetentionPurgeService`, `PiiRedactor`, `ProximaPerguntaService` **existem no `main`** — e as telas que deveriam acioná-los ou não existem, ou são stub Blade, ou têm botão decorativo.

**Fatias D e E do FASE2 NÃO foram aplicadas** (medido: `Pages/Jana/_components/` só tem `AssistantUiChat`, `JanaCockpit`, `JanaDrillDrawer`). F–L eu **não conferi** arquivo a arquivo.

---

## 1. Inventário atômico

Classes: **A** auditoria/instrumento · **B** blade órfão · **S** stub de backend (botão que mente) · **U** gap de UI já especificado no FASE2 · **N** drift de nomenclatura · **G** governança de tela.
Tier 0 = decisão de [W]. Tamanho: P ≤ meio dia · M ≤ 2 dias · G > 2 dias.

| ID | Item | Classe | Evidência no `main` | Tier | Tam | Onda |
|---|---|---|---|---|---|---|
| **A-1** | `Views (Blade)` do `module-surface.mjs` lista por arquivo (menos `emails/`) | A | `PAPEIS`: `{ rot: 'Views (Blade)', …, listar: false }` | — | P | 0 |
| **A-2** | Seção "Superfície servida" no `SUPERFICIE.md`: rota · camada · alcançável de onde · hits 30d · `TODO`/`STUB` | A | as 4 perguntas já têm dono (`anchor-lint`, `route-hits.json`, `no-mock-in-prod`) | — | M | 0 |
| **A-3** | `npm run migracao:report` está **morto** | A | `package.json` chama `module-surface.mjs --migracao`; o parser só conhece `--write`/`--check`/`--all` → `alvos` vazio → `exit(2)` | — | P | 0 |
| **A-4** | Rodar `--all` e pôr o retrato dos 37 módulos na mesa | A | 37 `SUPERFICIE.md` commitados; a semente do Cliente anota **"25 Blades VIVAS"** | Tier 0 (reordena investimento) | P | 0 |
| **S-1** | `reapurar` não despacha nada | S | `MetasController@reapurar`: `// TODO: dispatch(new ApurarMetaJob(...))`; o Job **existe** | — | P | 5 |
| **S-2** | `alertas/config` não persiste, e diz "Configuração salva." | S | `AlertasController@updateConfig`: `// TODO: persistir` | — | P | 5 → 10 |
| **S-3** | "STUB spec-ready" visível pro cliente em 2 telas | S | `alertas/index.blade.php` · `fontes/show.blade.php` | — | P | 5 |
| **S-4** | `MetasController` declarado STUB (sem filtro, sem permissão granular) | S | docblock da classe | — | M | 8 |
| **S-5** | `destroy` de meta existe na rota e **não tem gatilho em tela** | S | `Route::resource` + `metas/index.blade.php` | — | P | 8 |
| **B-1** | `metas/index` → Inertia | B | `@extends('layouts.app')` | — | M | 8 |
| **B-2** | `metas/show` → drawer ou tela FOCO | B | `metas/show.blade.php` | Tier 0 | M | 8 |
| **B-3** | `metas/create` + `edit` → form único | B | `edit` **não** edita `tipo_agregacao`; `UpdateMetaRequest` aceita | — | M | 8 |
| **B-4** | Períodos ganham UI | B | `PeriodosController` sem view | — | M | 8 |
| **B-5** | `alertas/index` → tela real ou aposentadoria | B | 1 `div.alert` + link | Tier 0 | M | 10 |
| **B-6** | `alertas/config` → drawer ligado ao `AlertaService` | B | desvio 10% hardcoded, 3 canais | — | M | 10 |
| **B-7** | `superadmin/metas` → migrar ou mudar de dono | B | **vaza a meta de faturamento da plataforma no empty state** | Tier 0 | P | 11 |
| **B-8** | `fontes/show` → editor com preview ou leitura auditada | B | `<pre>` de `json_encode` + aviso de stub | Tier 0 | G | 11 |
| **N-1** | Namespace de view `copiloto::` num módulo `Jana` | N | as 9 views | — | P | 12 |
| **N-2** | Permissões `jana.mcp.*` / `jana.cc.*` de telas que hoje são de outros módulos | N | comentários no `routes.php` | Tier 0 | M | 12 |
| **U-D** | Ação HITL de verdade | U | FASE2 D — `AcaoHitlModal.tsx` ausente | Tier 0 se tocar envio real | G | 6 |
| **U-L** | `jana.access` marcado nos papéis antes do deploy | U | FASE2 L; `default => false` | Tier 0 | P | 6 |
| **U-E** | Drawer "Configurar a Jana" | U | FASE2 E — `JanaConfigDrawer.tsx` ausente | — | G | 7 |
| **U-K** | Estados do Painel (carregando · vazio · erro) | U | FASE2 K | — | M | 7 |
| **U-H** | Metas: período, projeção e delta (server-side) | U | FASE2 H | Tier 0 (depende de B-2) | M | 8 |
| **U-I** | Semear a pergunta na Conversa (`?q=`) | U | FASE2 I | — | M | 8 |
| **U-F** | Memória cumprindo o próprio charter | U | FASE2 F | — | G | 9 |
| **U-G** | Exportar (Painel PDF · Metas CSV · Fatos LGPD auditado) | U | FASE2 G | — | M | 9 |
| **U-J** | Gating Grátis/Pro no Painel | U | FASE2 J; billing = Sprint JANA-B | Tier 0 | M | 12 |
| **G-1** | Trio (`.tsx` + `.charter.md` + `.casos.md`) das telas novas | G | hoje só `Memoria` e `Pro` têm `.casos.md` | — | M | por onda |
| **G-2** | `jana-index.contract.json` ganha `acoes`/`config`/`estados`/gating | G | FASE2 §3 | — | P | 12 |
| **G-3** | `jana:health-check` acusa Blade de tela viva em `/ia/*` | G | `HealthCheckCommand.php` (97 KB) existe | — | M | 12 |
| **G-4** | `prototipo-readiness.mjs` verde nas telas novas | G | CLAUDE.md §Export | — | P | 12 |
| **P-1** | Protótipo cita `AnaliseInadimplenciaService` / `AnaliseFaturamentoService` | — | `jana-merge.jsx:645-646`; **serviços não existem** — fonte real é `SellsCockpitAggregator`. Correção registrada no FASE2 §0 e nunca aplicada ao build | — | P | 0 |
| **P-2** | Protótipo mapeia `truck → "frota"` | — | `jana-merge.jsx:89`; [W] matou Frota em 2026-08-07 | — | P | 0 |

---

## 2. Anexo — os Blades (onde estão, quem chega, o que se aproveita)

**Onde:** `Modules/Jana/Resources/views/` — 9 arquivos em 5 pastas. **8 são telas**, 1 é e-mail. Todas `@extends('layouts.app')` → AdminLTE, fora do shell Inertia e do DS. Todas no namespace `copiloto::`.

### Quem chega neles

| Blade | Como se chega hoje | Hits (30d, ledger até 2026-07-25) |
|---|---|---|
| `metas/show` | **Único link vivo do módulo:** `Pages/Jana/Index.tsx:176` → `/ia/metas/{id}` no card de meta | ausente do ledger |
| `metas/index` | **Nenhum link.** O ghost `metas` saiu do menu em 2026-05-23 — `JanaAreaHeader.tsx:49` diz "metas … (MetasController ainda é Blade)" | ausente |
| `metas/create` · `edit` | só por dentro do index/show | ausente |
| `fontes/show` | só pelo botão "Fonte" dentro do `metas/show` | ausente |
| `alertas/index` · `config` | **nenhum link em UI** — só digitando a URL | ausente |
| `superadmin/metas` | nenhum link; `can:jana.superadmin` | ausente |

**Leitura honesta do ledger:** `route-hits.json` (janela 30d, última data 2026-07-25) registra `jana.index` com **4 hits** e nenhuma rota `jana.metas.*` / `jana.alertas.*` / `jana.superadmin.*` / `jana.fontes.*`. O `_meta` do próprio arquivo avisa: 0 hits = **"wired-porém-não-servido"**, não prova de que a tela quebrou. E a janela fechou há duas semanas.

**Não é resíduo esquecido.** O `Index.charter.md` **continua delegando pro Blade**: §Goals "click em meta → drilldown `/copiloto/metas/{id}`", §Anti-goals "⛔ edição inline (vai em `/copiloto/metas/{id}/edit`)". É a continuação declarada do fluxo do Painel, servida no shell errado — por isso o único clique real joga o usuário do Inertia no AdminLTE.

### Vai usar?

| Blade | Veredito | O que se aproveita |
|---|---|---|
| `metas/show` | **SIM — a capacidade** | destino do único clique; migra conteúdo (12 apurações + escopo + origem + 3 ações), descarta HTML → PR 8.2 |
| `metas/index` | **SIM — após decisão 8.0** | lista tem valor, ninguém chega → PR 8.1 |
| `metas/create` | **SIM — como spec** | enums (`R$`/`qtd`/`%`/`dias`, `soma`/`media`/`ultimo`/`contagem`) + `pattern="[a-z0-9_]+"` são o contrato do form |
| `metas/edit` | **SIM — fundido no create** | ⚠️ o `edit` não deixa editar `tipo_agregacao` e o `UpdateMetaRequest` aceita: ou vira `readonly` com o porquê, ou é bug |
| `alertas/config` | **só o shape** | `desvio_threshold` + 3 canais; o `AlertaService` já existe → PR 10.1 |
| `alertas/index` | **NÃO** | `div.alert` com "STUB spec-ready" |
| `superadmin/metas` | **a query sim, a tela não** | as 2 consultas são a lógica boa; o empty state que imprime a meta da plataforma sai já |
| `fontes/show` | **NÃO** | `<pre>` de JSON; o editor com preview é design novo, não migração |
| `emails/weekly-digest` | **MANTER** | e-mail é Blade por definição |

**Risco não verificado:** não conferi como o `layouts.app` renderiza alcançado a partir da sessão Inertia. O salto `Painel → /ia/metas/{id}` é o caminho mais provável de "abriu torto em produção" — teste manual de 1 minuto antes da onda 8.

---

## 3. Ondas de PR

### Onda 0 (transversal) — Enxergar antes de investir
> Não é do módulo Jana. É o instrumento que transforma esta auditoria em rotina — e que decide se a Jana é mesmo onde investir primeiro.

- **PR 0.1 — o Blade para de se esconder** · A-1: papel `Views (Blade)` passa a listar por arquivo quando a view está sob `Modules/*/Resources/views` **e não é `emails/`**.
- **PR 0.2 — seção "Superfície servida"** · A-2: uma linha por rota do módulo — camada · alcançável de onde · hits 30d · controller com `TODO`/`STUB`. Derivada, no `SUPERFICIE.md` que já é gerado (ADR 0256: "derivado sobrevive; escrito+lembrado apodrece"). `--check` falha em **dois fatos, só**: (a) rota com Page Inertia irmã ainda servindo Blade de tela; (b) controller servido em rota `web` com `TODO`/`STUB` no corpo.
- **PR 0.3 — ressuscitar `migracao:report`** · A-3: `--migracao` passa a existir e a ser o modo que responde às 4 perguntas.
- **PR 0.4 — o retrato** · A-4: rodar `--all`, levar a [W] a lista dos módulos por dívida de superfície. A semente do Cliente já anota **25 Blades vivas** contra as 8 da Jana.
- **PR 0.5 — consertar o protótipo** · P-1 + P-2: tirar os dois serviços inexistentes do `JmDrillDrawer` (fonte real: `SellsCockpitAggregator`) e o mapeamento de Frota. Um protótipo que mostra "origem do número" com serviço inventado é o hook que o charter proíbe.

**Cláusula de entrada:** nenhuma. **Cláusula de saída:** `npm run migracao:report` roda; o `SUPERFICIE.md` da Jana nomeia os 8 blades e diz quem chega em cada um; [W] confirma (ou reordena) a prioridade do módulo.

### Onda 5 — Verdade nos botões
> Roda em paralelo à 0. Nenhum código de produto novo — só parar de mentir.

- **PR 5.1 — `reapurar` despacha de verdade** · S-1: `dispatch(new ApurarMetaJob($meta, now()))` + retorno dizendo o que vai acontecer e quando.
- **PR 5.2 — `alertas/config` para de fingir** · S-2 (parcial): ou persiste em `essentials_settings`, ou o botão vira `disabled` com o motivo ao lado. **Não há terceira opção.**
- **PR 5.3 — "STUB spec-ready" sai da cara do cliente** · S-3: `EmptyState` honesto no lugar.

**Cláusula de saída:** zero string "STUB"/"em breve"/"TODO" renderizada em rota `/ia/*`. Teste que varre as views e falha se voltar.

### Onda 6 — A Jana age (FASE2 D + L)
- **PR 6.1 — `AcaoHitlModal.tsx` + `POST /ia/acoes/{id}/preparar`** · U-D.
- **PR 6.2 — rollout de `jana.access` nos papéis** · U-L. **Antes do merge de 6.1**, não depois.

**Cláusula de entrada:** [W] ratifica que a fila nasce **pendente** e nada sai sem aprovação por mensagem. **Cláusula de saída:** o rótulo descreve o que a rota faz naquele PR. Tocou envio real de WhatsApp → **Tier 0, para**.

### Onda 7 — A Jana obedece (FASE2 E + K)
- **PR 7.1 — `JanaConfigDrawer.tsx`** · U-E, persistência usuário+business (migration pequena, não localStorage).
- **PR 7.2 — estados do Painel** · U-K.

**Cláusula de saída:** HITL sem caminho de desligar, nem por payload. Nenhum número renderizado em estado de erro.

### Onda 8 — Metas sai do Blade
- **PR 8.0 — decisão registrada** · Tier 0: **drawer no Painel** × **tela FOCO `/ia/metas/{id}`**. Sem ela a onda não abre. Recomendação [CC]: **drawer** pro dia a dia + FOCO só pra edição (padrão PT-02 do resto do ERP).
- **PR 8.1 — índice em Inertia** · B-1 + S-4 + S-5: `PageHeader` + `DataTable` + filtro ativo/inativo + arquivar (o `destroy` que ninguém alcança).
- **PR 8.2 — detalhe da meta** · B-2, na forma decidida: 12 janelas + origem do número + apurações.
- **PR 8.3 — criar/editar** · B-3: form único, `tipo_agregacao` resolvido, `slug` imutável após criação.
- **PR 8.4 — períodos** · B-4: as 3 rotas ganham UI dentro do detalhe.
- **PR 8.5 — projeção e delta** · U-H, **server-side**. Cumulativa extrapola ritmo; média/taxa projeta tendência — nunca multiplica valor.
- **PR 8.6 — semear pergunta** · U-I: `novaConversa` aceita `?q=` validado; só então a CTA vira "Perguntar sobre isso".

**Cláusula de saída:** `Resources/views/metas/` apagada; nenhuma rota de meta renderiza Blade; trio (G-1) das telas novas; route name preservado onde a URL sobrevive, **301** onde não.

### Onda 9 — LGPD de verdade (FASE2 F + G)
- **PR 9.1 — Memória no nível do charter** · U-F.
- **PR 9.2 — Exportar** · U-G, depende de 9.1 (mesmo `activitylog`).

**Cláusula de saída:** export de fatos **não entra sem registro de auditoria** (quem, quando, quantos). `DsrService` e `PiiRedactor` já existem — usar, não recriar.

### Onda 10 — Alertas de verdade
- **PR 10.0 — decisão registrada** · Tier 0: **seção do Painel** × **tela própria**. Recomendação [CC]: seção + drawer de config — o módulo já tem tela demais.
- **PR 10.1 — config ligada ao `AlertaService`** · B-6 + S-2 (fecha o parcial da 5.2): limiar por meta, canais (in-app hoje; e-mail via `WeeklyDigestService`; WhatsApp **sem promessa de data**).
- **PR 10.2 — lista/inbox** · B-5, na forma decidida.

**Cláusula de saída:** canal que não existe não aparece com checkbox marcável. `Resources/views/alertas/` apagada.

### Onda 11 — A cauda
- **PR 11.0 — decisão registrada** · Tier 0, **duas**: (a) `superadmin/metas` migra ou **muda de dono** pra `/governance` (irmão do que a ADR 0366 §D-B já fez com custos e qualidade); (b) `fontes/show` vira **editor com preview** (SQL de cliente — risco) ou **leitura auditada**.
- **PR 11.1 — superadmin** · B-7. **Independente da decisão: o empty state que imprime a meta da plataforma sai.**
- **PR 11.2 — fonte da meta** · B-8.

**Cláusula de saída:** `Resources/views/` do módulo fica **só com `emails/`**.

### Onda 12 — Plano e fechamento
- **PR 12.1 — gating Grátis/Pro** · U-J, atrás de flag enquanto o billing for mock; o **layout** entra agora.
- **PR 12.2 — `copiloto::` → `jana::`** · N-1, só depois que as views de tela morrerem.
- **PR 12.3 — contrato + health-check + readiness** · G-2, G-3, G-4. O `jana:health-check` passa a **falhar** com Blade de tela viva em `/ia/*` — a versão por-módulo da guarda que a Onda 0 fez pro repo inteiro.
- **PR 12.4 — decisão de permissões** · N-2, Tier 0: ADR + migration próprios, **não pega carona em PR de UI**.

---

## 4. Cláusulas gerais (valem em todo PR deste pedido)

1. **C-1 — leitura no turno.** Nenhum PR abre sem leitura do `main` no mesmo turno. Espelho local ≠ git.
2. **C-2 — trio ou não existe.** Tela nova = `.tsx` + `.charter.md` + `.casos.md` com UC.
3. **C-3 — HITL travado ligado.** Nada sai sem aprovação por mensagem; não há payload que desligue.
4. **C-4 — `business_id` em toda leitura e escrita** (ADR 0093). Escrita em filho valida o pai (padrão de `PeriodosController::assertMetaDoTenant`).
5. **C-5 — DS vivo.** Tokens do `colors_and_type.css` + `cockpit_domains.css`, roxo `oklch(0.55 0.15 295)`, IBM Plex, PT-BR, sem emoji, sem `rounded-xl+` novo. **Nenhuma tela nova em AdminLTE.**
6. **C-6 — rota que morre vira 301** e o motivo fica no `routes.php` (esse arquivo já é o melhor registro do módulo — manter o padrão).
7. **C-7 — o botão promete só o que a rota entrega naquele PR.** Rótulo é contrato.
8. **C-8 — permissão nasce `false`** → marcar nos papéis antes do deploy.
9. **C-9 — número na tela cita fonte que existe no repo.** `SellsCockpitAggregator`, `ApuracaoService`, `BriefDiarioService` existem; `Analise*Service` **não** — vale pro protótipo também (P-1).
10. **C-10 — instrumento antes de mutirão.** Dívida que aparece em 2+ módulos vira guarda derivada (Onda 0), não lista à mão.
11. **C-11 — eu não commito.** Pedido zero-toque: cole no `main` ou abra Issue `cowork-intake`.

---

## 5. O que NÃO fazer

- Não migrar `emails/weekly-digest.blade.php` — e-mail é Blade por definição.
- Não criar script de auditoria novo: `module-surface.mjs`, `anchor-lint.mjs`, `route-hits.json` e `no-mock-in-prod.mjs` já existem. Estender, não recriar.
- Não recriar `AlertaService`, `ApurarMetaJob`, `DsrService`, `PiiRedactor`, `SuggestionEngine`, `HitlEscalationService`: **existem**.
- Não abrir tela nova de Metas/Alertas antes das decisões 8.0 e 10.0 — o módulo já pagou o preço de tela duplicada (Cockpit × Dashboard, ondas 1–4).
- Não renomear permissão de carona em PR de UI (N-2 é ADR próprio).
- Não construir a análise **Frota** ([W] 2026-08-07: item morto).

---

## 6. Fila de decisões de [W]

| # | Decisão | Trava | Onda |
|---|---|---|---|
| 0 | Depois do retrato dos 37 módulos: a Jana continua sendo a prioridade? | ordem das ondas 5–12 | 0 |
| 1 | Fila HITL nasce pendente; envio real de WhatsApp é escopo separado | onda 6 inteira | 6 |
| 2 | Meta: **drawer** × **tela FOCO** | onda 8 inteira | 8 |
| 3 | Alertas: **seção do Painel** × **tela própria** | onda 10 | 10 |
| 4 | `superadmin/metas`: migrar × mudar de dono pra `/governance` | 11.1 | 11 |
| 5 | Fonte da meta: editor com preview × leitura auditada | 11.2 | 11 |
| 6 | Gating Grátis/Pro: layout entra antes do billing? | 12.1 | 12 |
| 7 | Rename de permissões `jana.mcp.*` / `jana.cc.*` | 12.4 | 12 |

---

## 7. Limite honesto

Não escrevi no git. As ondas são proposta; as fatias D–L vêm do `JANA-FASE2-2026-08-07.md` e não foram reescritas. Conferi neste turno o que está em §0, §1 (coluna "evidência") e §2; o que não conferi está dito como não conferido — e o `main` andou entre a primeira e a última leitura desta sessão. O build F1 de referência segue em `prototipo-ui/cowork/jana-merge.jsx` + `.css`, com os dois defeitos P-1/P-2 ainda dentro dele.
