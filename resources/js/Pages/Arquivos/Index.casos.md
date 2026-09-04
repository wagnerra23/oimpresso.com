---
casos: Arquivos/Index — carimbado do Padrão de Tela
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-04"
---

# Casos de Uso & Aceite — Arquivos/Index

> Nascido de `criar-tela.mjs`. **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. O stub `e2e/arquivos-index.spec.ts` já cita `UC-INDEX-01`.

> **Por que poucos UC com id aqui.** A tela nasceu no PR-1 ([#6216](https://github.com/wagnerra23/oimpresso.com/pull/6216),
> 2026-08-24: rota `GET /arquivos` viva) só com a vista **Acervo**; o PR-2 acrescentou a
> **Trilha** e, com ela, a barra de abas; o PR-4 acrescentou o **Cofre** e o PR-3 a
> **Retenção** — a onda 1 fecha com as 4 vistas do charter. Declarar os 14 cenários do protótipo F1 como UC de uma vez criaria órfãos e quebraria
> o G-2, porque o teste que os defende ainda não existe. Eles ficam abaixo como `[BACKLOG]` —
> prosa honesta, sem id — e **viram UC na onda que traz o teste que os defende**.
> Fonte: `prototipo-ui/cowork/arquivos-page.jsx`.

---

## UC-INDEX-01 · O acervo lista o que o sistema guardou, com prazo e base legal
- **Persona:** Wagner (escritório, conformidade) — quer saber o que está guardado, de quem é,
  e até quando a lei manda guardar, sem abrir tela por tela.
- **Aceite:** Dado um business com arquivos anexados via `HasArquivos` · Quando abre `/arquivos`
  com a permissão `arquivos.access` · Então vê a lista do **próprio** business com nome do
  arquivo, dono (`arquivable`), bucket, disco, tamanho e data de vencimento — e nenhum arquivo
  de outro `business_id`.
- **Teste:** `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` — **6** asserções
  citando `UC-INDEX-01` no título do `it()` (scope não quebrado · sem `storage_path`/hash ·
  leitura pura · `politica()` devolve prazo E base legal · todo bucket da Request existe no enum
  do banco · entrada no sidebar com as 3 camadas)
  + `e2e/arquivos-index.spec.ts` (stub `test.fixme`, vira asserção quando a rota subir em prod).
  _Este número dizia **5** e estava stale desde 2026-08-25: a asserção do bucket entrou com o fix
  do filtro ([#6244](https://github.com/wagnerra23/oimpresso.com/pull/6244)) e a enumeração aqui
  não acompanhou. Recontado com `grep -c "^it('UC-INDEX-01"` — 6 —, não de memória._
- **Regressão que defende:** vazamento cross-tenant no acervo (ADR 0093, Tier 0) e prazo
  exibido sem a lei que o sustenta.
- **Status: 🧪** — a lane executou e o manifesto aterrissou: `scripts/casos-test-results.json`
  (gerado 2026-08-25, fonte `test-results/pest-arquivos-junit.xml`) traz
  `UC-INDEX-01 → verdict pass · tests 4` — quatro, não cinco, porque o manifesto foi gerado
  ANTES de a asserção do sidebar entrar ([#6245](https://github.com/wagnerra23/oimpresso.com/pull/6245),
  2026-08-25); ela conta a partir do próximo `casos:results`. Não é ✅ porque, pelo
  cabeçalho deste arquivo, 🧪 é exatamente isto — *teste cita o UC e passa*; o status veio do
  veredito, não da minha leitura (G-7). `ran_at` vem `null`, como em 314 dos 361 UCs do
  manifesto — é o normal do parser de JUnit, não sinal de que não rodou.

---

## UC-INDEX-02 · A trilha mostra quem tocou em quê, sem deixar tocar nela
- **Persona:** Wagner (escritório, conformidade) — precisa responder *"quem baixou esse
  arquivo, quando, e de onde"* sem abrir terminal, e precisa que ninguém possa reescrever
  essa resposta.
- **Aceite:** Dado um business com eventos em `arquivos_audit_log` · Quando abre
  `/arquivos?tab=trilha` com a permissão `arquivos.access` · Então vê os eventos do
  **próprio** business em ordem cronológica (quando · ação · `#id` do arquivo · quem ·
  detalhe do payload), com filtro pelas ações que aquele business realmente registrou —
  e **nenhuma** ação de editar ou apagar linha em lugar nenhum da tela.
- **Teste:** `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` — **6**
  asserções citando `UC-INDEX-02` no título do `it()` (contadas com
  `grep -c "^it('UC-INDEX-02"`, não de memória): o caminho da trilha filtra por
  `business_id` · fail-closed sem sessão · a linha expõe `#id` e nunca o nome · o payload
  vira resumo legível · **cross-tenant 98 vs 99** · o filtro de ação não apaga os outros
  chips. As 4 primeiras dispensam banco (valem nas duas lanes); as 2 últimas rodam na lane
  MySQL, onde este arquivo já está na allowlist.
- **Regressão que defende:** **vazamento cross-tenant** — e esta é a parte que merece ser
  lida devagar. `arquivos_audit_log` **não tem model**, logo **não tem global scope**: o
  `where` por `business_id` no controller é a única coisa entre a trilha de um cliente e a
  de outro. O assert de código que proibia `where('business_id'` teve de ser escopado ao
  acervo por causa disso — aplicado à trilha, ele proibiria a própria defesa (é a regra de
  precedência de 2026-07-06). Defende também: prazo/nome de arquivo vazando pra vista de
  governança, e a trilha ganhar caminho de escrita.
- **Status: ⬜** — os testes existem e as asserções puras foram medidas fora do CI, mas
  ⬜ é o que o cabeçalho manda enquanto o veredito não veio da lane: 🧪 exige o manifesto
  (`scripts/casos-test-results.json`), não a minha leitura (G-7). Vira 🧪 com o run da
  `Arquivos · Pest (MySQL)` deste PR.

---

## UC-INDEX-03 · O cofre diz quanto está guardado e o que está errado — sem apagar nada
- **Persona:** Wagner (escritório, custo de disco e conformidade) — quer saber *"quanto o
  sistema está guardando, onde, e o que está fora do lugar"* sem abrir terminal, e precisa que
  a resposta não seja uma lista pra ele varrer à mão.
- **Aceite:** Dado um business com arquivos anexados · Quando abre `/arquivos?tab=cofre` com a
  permissão `arquivos.access` · Então vê o espaço **por disco** (contagem, tamanho, quantos
  cifrados) e os **3 achados** contados — arquivo acima do cap que o vault recusa, órfão sem
  `arquivable`, e mesmo conteúdo repetido — tudo do **próprio** business, com no máximo 5
  arquivos citados por achado, **nenhum hash**, **nenhum caminho de disco** e **nenhum botão que
  aja sobre eles**.
- **Teste:** `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` — **8** asserções
  citando `UC-INDEX-03` no título do `it()` (contadas com `grep -c "^it('UC-INDEX-03"`, não de
  memória): fail-closed sem sessão devolve `disponivel: false` e não "0 achados" · o leitor usa
  o model e **não** repete o `where` · **`toBase()` não derruba o global scope** · o cap vem da
  config que o vault cobra · o controller registra a prop de **uma** vista só · **cross-tenant
  98 vs 99** · duplicado separa registro repetido de disco ocupado 2× · o payload não carrega
  hash nem caminho (com controle positivo).
  As 5 primeiras dispensam banco (valem nas duas lanes); as 3 últimas rodam na lane MySQL.
  _A do `toBase()` nasceu de um vermelho de PHPStan (`Access to an undefined property
  Arquivo::$qtd_arquivos`, ×6): agregação hidratada em model carrega aliases que a classe não
  declara. A saída foi mudar a FORMA — `->toBase()->get()` —, não suprimir no baseline; e como
  isso passou a depender de `toBase()` preservar o scope, a propriedade virou teste em vez de
  leitura do vendor._
- **Regressão que defende:** **vazamento cross-tenant** — `arquivos` tem model e global scope, e
  aqui o erro caro é o simétrico ao da trilha: *repetir* o `where` esconderia uma quebra do
  scope. Defende também: (a) "não medi" virar "0 achados", que faz uma tela de governança
  afirmar saúde sem ter olhado; (b) hash e caminho de disco chegarem à vista de governança
  (LGPD Art. 37) — e este é o assert que **substituiu** o presence-gate no ponto em que ele
  deixou de caber, porque o leitor precisa do hash pra agrupar; (c) o cap virar `50` escrito na
  tela, divergindo do que o `VaultEncryptionService` recusa em runtime; (d) mais de uma prop
  deferida registrada de uma vez, que faria a vista fechada custar.
- **Status: ⬜** — os testes existem e as asserções puras foram medidas fora do CI, mas ⬜ é o
  que o cabeçalho manda enquanto o veredito não veio da lane: 🧪 exige o manifesto
  (`scripts/casos-test-results.json`), não a minha leitura (G-7). Vira 🧪 com o run da
  `Arquivos · Pest (MySQL)` deste PR.

---

## UC-INDEX-04 · A retenção mostra o que vence, sem apagar nada — e diz quem apaga
- **Persona:** Wagner (escritório, conformidade) e Eliana (financeiro) — precisam responder
  *"o que está passando do prazo e o que a lei manda guardar por quanto tempo"* sem abrir
  terminal, e precisam saber que olhar a tela **não** dispara nada.
- **Aceite:** Dado um business com arquivos de contextos diferentes · Quando abre
  `/arquivos?tab=retencao` com a permissão `arquivos.access` · Então vê os 4 KPIs (vence em
  30 · vence em 90 · no grace · passou do prazo), a política dos 8 contextos com **a base
  legal ao lado do prazo** e a contagem de arquivos de cada um, e os 4 cards de regra — tudo
  do **próprio** business, **sem nenhum botão que execute**, e com a tela declarando se o
  `arquivos:retention-cleanup` está agendado.
- **Teste:** `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` — **4**
  asserções citando `UC-INDEX-04` no título do `it()` (contadas com
  `grep -c "^it('UC-INDEX-04"`, não de memória): fail-closed sem sessão devolve
  `disponivel: false` em vez de "tudo em dia" · grace/aviso/estratégia vêm da config
  registrada · o prazo **por linha** vence o da policy · o que está no grace **não** conta
  como vencido. As 2 primeiras dispensam banco; as 2 últimas rodam na lane MySQL.
- **Regressão que defende:** (a) **"0 passaram do prazo" afirmado sem ter medido** — numa
  tela de LGPD essa é a frase mais perigosa que existe, e sem o portão fail-closed o global
  scope (que é fail-open) faria a vista contar o acervo de todos os tenants; (b) o prazo por
  contexto virar prazo global — `summary()` responde global, e usá-lo aqui seria número certo
  respondendo a pergunta errada; (c) `retention_days` da linha ser ignorado, o que diria
  "no prazo" para um arquivo vencido; (d) grace e vencido virarem o mesmo balde, fazendo o
  operador correr atrás de arquivo que o job já tratou; (e) `Config/retention.php` seguir
  **não registrado** — era o caso até esta leva, e sem isso a tela mostraria defaults inline
  como se fossem a política.
- **Status: ⬜** — os testes existem, mas ⬜ é o que o cabeçalho manda enquanto o veredito não
  veio da lane: 🧪 exige o manifesto (G-7), não a minha leitura.

---

## UC-INDEX-05 · Baixar sai da tela por link assinado — e o que não tem conteúdo não oferece
- **Persona:** Wagner (escritório, conformidade) — achou o XML da NF-e no acervo e precisa do
  arquivo, sem abrir a tela do dono nem pedir a alguém que rode um comando.
- **Aceite:** Dado um arquivo com conteúdo no storage · Quando a linha renderiza · Então há um
  botão só-ícone com `aria-label` cujo destino é uma **URL assinada** pro `arquivos.download`,
  válida por **60 min** · E quando o arquivo está apagado (soft-delete) ou sem conteúdo
  (anonimizado pela retenção), **não há botão** — porque o `DownloadController` devolveria 404
  nos dois casos · E montar o link **não escreve** em `arquivos_audit_log`: a tela segue leitura
  pura, e quem baixa de fato continua auditado no consumo (`signed_url_consumed`).
- **Teste:** `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` — **3** asserções
  citando `UC-INDEX-05` no título do `it()` (contadas com `grep -c "^it('UC-INDEX-05"`, não de
  memória): destino + assinatura + prazo · os dois casos sem conteúdo, com controle negativo ·
  leitura pura, com **controle positivo** (o `ArquivosService::signedUrl()` DEVE escrever, e
  escreve — senão "o contador não subiu" seria verdade num contador que não mede nada).
- **Regressão que defende:** servir arquivo do vault por `Storage::url` (Non-Goal do charter,
  ADR 0123 §6) · oferecer botão que leva a 404 · trocar o `URL::temporarySignedRoute` pelo
  `signedUrl()` do Service e passar a gravar 25 linhas de auditoria por render do acervo.
- **Status: 🧪** — a lane do CT 100 executou em 2026-08-27: **35 passed · 140 assertions**,
  contra **30 · 118** com o arquivo de teste anterior no mesmo código. O delta `+5/+22` é o
  contador provando que os testes novos rodaram, não o nome deles aparecendo no log.
  **Bite-test:** trocar `DOWNLOAD_EXPIRA_MIN` de 60 pra 1440 deixa o UC vermelho (`1 failed`).

---

## UC-INDEX-06 · "Vinculado a" mostra o dono em PT-BR e leva à tela dele quando ela existe
- **Persona:** Wagner (escritório, conformidade) — quer saber **de quem é** o arquivo, e chegar
  lá num clique. `ServiceOrder #4` é o nome da classe Eloquent, não o nome da coisa.
- **Aceite:** Dado um arquivo com `arquivable` · Quando a linha renderiza · Então a coluna mostra
  o **nome de negócio** do dono (`Ordem de serviço #4`) com o tipo técnico (`ServiceOrder`) na
  sub-linha em `font-mono` · E vira **link** somente quando o tipo tem rota provada em
  `php artisan route:list` — tipo sem rota fica texto, nunca link morto · E tipo fora do mapa cai
  no `class_basename` cru, que é o que a tela já fazia · E órfão continua ACHADO, sem rótulo nem
  destino.
- **Teste:** `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` — **2** asserções
  citando `UC-INDEX-06` no título do `it()` (contadas com `grep -c "^it('UC-INDEX-06"`): os dois
  tipos COM rota (`ServiceOrder` → `/oficina-auto/ordens-servico/{id}`, `JobSheet` →
  `/repair/job-sheet/{id}`) · o tipo com rótulo e SEM rota (`NfeEmissao`), o tipo fora do mapa e
  o órfão.
- **Regressão que defende:** imprimir nome de classe Eloquent como se fosse vocabulário de
  negócio · gerar link pra rota que não existe (`RouteNotFoundException` derrubaria o `paginate`
  inteiro, não só a célula) · perder o valor técnico, que é o que resolve a ambiguidade entre as
  duas "Ordem de serviço" (OficinaAuto × Repair).
- **Status: 🧪** — mesma run de UC-INDEX-05. **Bite-test:** anular a rota do `ServiceOrder` no
  mapa deixa o UC vermelho (`1 failed`).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

Derivados do protótipo F1 (`arquivos-page.jsx`). A onda que implementar cada vista traz o teste
e promove o item a `UC-INDEX-NN`.

**Onda 1 — ler**

- **[BACKLOG]** Acervo com 10 arquivos → subtítulo com contagem e quantos estão cifrados (a barra de abas nasceu no PR-2 com 2 das 4 vistas; retenção chega no PR-3 e cofre no PR-4).
- **[BACKLOG]** Filtrar bucket `sensitive` → só os do cofre; o contador do chip bate com a lista.
- **[BACKLOG]** Filtrar por `active` (o **default** do enum, logo o caso comum) → lista os arquivos
  daquele bucket. Vale como caso porque já falhou de verdade: até 2026-08-25 havia QUATRO listas
  de bucket em desacordo — enum do banco (7), o que o `CuradorEngine` grava (4), o que a
  `ListArquivosRequest` aceitava (`public`/`internal`/`sensitive`/`vault`) e os chips da tela
  (`sensitive`/`common`/`public`). Só `sensitive` existia nas quatro; `active` era rejeitado com
  **422** e `common`/`public` filtravam por valor inexistente (lista sempre vazia). Achado pelo
  [W] no smoke de produção, com dado real — nenhum gate estrutural pegaria.
- **[BACKLOG]** Arquivo sem `arquivable` → linha marcada como urgente + selo "órfão" com o motivo em tooltip (órfão é achado, não item).
- **[BACKLOG]** Arquivo a ≤30 dias do prazo → coluna "Vence em" em vermelho + linha urgente.
- **[BACKLOG]** Arquivo com prazo vencido → rótulo "prazo vencido", nunca contagem negativa.
- **[BACKLOG]** Papel sem `arquivos.access` → sem-permissão explicando que o anexo da OS continua acessível por quem vê a OS.
- **[BACKLOG]** Arquivo sem file físico no disco → hoje só o check #1 do `arquivos:health-check`
  vê isso, e o cofre **não** cobre: exige uma chamada de filesystem por linha (amostrada em 1000
  no comando), custo que não cabe num request web. Vira UC quando houver caminho barato — um
  campo materializado ou um resumo que o comando grave.
- **[BACKLOG]** Trilha de um arquivo específico → abrir a linha do acervo e ver só os eventos dele (hoje o filtro é por ação, não por arquivo: sem caminho de UI que leve até lá, o parâmetro nasceria órfão).

**Onda 2 — mutar o reversível**

- **[BACKLOG]** Excluir foto de OS → confirmação fala do grace de 30 dias e do `hard_delete` do job.
- **[BACKLOG]** Excluir XML de NF-e → confirmação avisa da guarda legal de 5 anos ("problema fiscal, não faxina").
- **[BACKLOG]** Restaurar dentro do grace → arquivo volta pro acervo e a trilha guarda o `restore`.
- **[BACKLOG]** Fora do grace → o botão de restaurar **não existe** (não basta estar desabilitado).

**Bloqueado — não vira UC sem decisão [W]**

- **[BLOQUEADO]** Reclassificar com `force_bucket` + `motivo` → hoje `ArquivosService::classify()`
  não aceita nenhum dos dois: roda o `CuradorEngine` automático e descarta o `motivo`. A
  `ReclassifyArquivoRequest` valida os dois campos e **nada os consome** (`force_bucket` tem zero
  consumidor de produção no repo). O PR-6 precisa de código de Service novo — e a regra 3 do
  prompt manda **parar e perguntar** em vez de inventar método.

## Anti-regressão

- Nenhum caminho de upload nesta tela.
- Baixar nunca sai por `Storage::url` — sempre link assinado de 60 min pro `DownloadController`.
- Montar a linha do acervo nunca escreve na trilha: o link vem de `URL::temporarySignedRoute`,
  não do `ArquivosService::signedUrl()`, que audita a cada chamada.
- Link do dono só existe pra tipo com rota provada em `route:list` — o `Route::has()` no
  servidor é a segunda perna, porque o módulo dono pode estar desinstalado neste boot.
- Nenhum botão de editar/apagar linha da trilha.
- Nenhum botão no cofre — nem o "Rodar dry-run do cleanup" que o protótipo desenha: é onda 3, e
  a onda 1 inteira é leitura.
- O cofre lê pelo model e **não** repete o `where` por `business_id` — ali repetir esconde a
  quebra do scope, ao contrário da trilha. O que ele acrescenta é o portão fail-closed.
- O cofre nunca responde "0 achados" quando não conseguiu medir: o payload marca `disponivel`.
- Hash e caminho de disco não saem no payload de nenhuma vista, nem quando o leitor precisa do
  hash pra agrupar duplicado.
- Só uma prop deferida é registrada por request — a da vista aberta.
- Prazo, cap e base legal vêm de config, nunca de número escrito na tela.
- A trilha filtra por `business_id` explicitamente — a tabela não tem model nem global
  scope, então aqui o `where` **é** a defesa Tier 0, não redundância dela.
- Sem `business_id` na sessão, a trilha devolve vazio (fail-closed) — nunca o log inteiro.
- Nenhuma vista lista arquivo de outro `business_id`.
- Excluir nunca chama hard-delete direto — só soft-delete + grace.
- Prazo exibido sempre com base legal; mudar prazo exige mudar `Config/config.php` **e**
  `Config/retention.php` (são espelho declarado, e divergir é achado de auditoria).

## Trilha do tempo
- 2026-08-27 · [CC] **UC-INDEX-05 (baixar) e UC-INDEX-06 (vinculado a) promovidos de `[BACKLOG]`/medição a UC** — a onda trouxe os testes que os defendem, que é a condição do G-2. O buraco foi MEDIDO com a mesma sonda nos dois lados (produção logada × protótipo vivo): a linha de produção tinha **6 colunas e 0 botões**, o protótipo tem **7** (a 7ª é a de ações) e `mono` em 5 delas contra **0 de 6** na produção. O item de backlog do `vault` saiu da lista — ele é exatamente o UC-INDEX-05. O que **não** entrou, e por quê: classificar e excluir **não têm endpoint** (varredura do `Routes/web.php` do módulo: só `index`, `download` e as 3 do Install) e o charter os agenda pra onda 2, travada na decisão [W] do PR-6. Refs: US-ARQ-013 · ADR 0123 §6 (signed URL 60 min) · ADR 0093 (o `find()` do DownloadController aplica o scope).
- 2026-07-11 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste). Refs: UI-0013 · ADR 0264 G-1/G-2.
- 2026-08-24 · [CL] preenchido a partir do protótipo F1 exportado do Cowork (`arquivos-page.jsx`) + do rascunho `cowork-inbox/modulos-faltantes/arquivos.casos.md`. Os 14 cenários entraram como `[BACKLOG]` (sem id) pra não nascer órfão no G-2; o item de reclassificar foi marcado `[BLOQUEADO]` porque o Service não suporta o contrato da Request. Refs: US-ARQ-013 · ADR 0360.
- 2026-08-25 · [CC] **UC-INDEX-02 (trilha) promovido de `[BACKLOG]` a UC** — a onda PR-2 trouxe o teste que o defende, que é a condição do G-2. Nasce `⬜`: o veredito é da lane, não da leitura. Refs: US-ARQ-013 · ADR 0093 (o `where` explícito numa tabela sem model) · ADR 0123 §8.
- 2026-08-26 · [CC] **UC-INDEX-04 (retenção) promovido de `[BACKLOG]` a UC** — a onda 1 FECHA aqui: as 4 vistas do charter existem. Os 2 itens de backlog que ele cobre (aba Retenção · banner do check #4) saíram da lista. Achado do caminho: `Config/retention.php` nunca foi registrado no provider — mesmo defeito do `config.php` em 24/08 —, então grace/aviso/estratégia eram inalcançáveis por `config()`. Refs: US-ARQ-013 · LGPD Art. 15-16.
- 2026-08-25 · [CC] **UC-INDEX-03 (cofre) promovido de `[BACKLOG]` a UC** — a onda PR-4 trouxe os testes. Os 3 itens de backlog que ele cobre (aba Cofre · acima do cap · MD5 repetido) saíram da lista, e um item NOVO entrou no lugar, honesto sobre o que a vista **não** cobre: arquivo sem file físico no disco, que exige chamada de filesystem por linha. Nasce `⬜` pelo mesmo motivo do UC-INDEX-02. Refs: US-ARQ-013 · ADR 0093 (aqui o `where` repetido é que seria o defeito) · ADR 0126 (cap do vault) · LGPD Art. 37.

## Revalidação — 2026-09-04 (PR #6767)

A tela mudou (`Index.tsx` ganhou a prop `caption` do `shared/DataTable` em dois pontos de
render: Acervo e Trilha), então o G-6 marcou os casos como stale. **Revalidado pelo CI, não
por leitura:** lane `PHP / Pest (Arquivos · MySQL)`, run
[33907004888](https://github.com/wagnerra23/oimpresso.com/actions/runs/33907004888) —
`ArquivosAdminControllerTest` **PASS com 140 assertions**. O filtro daquela lane inclui
`resources/js/Pages/Arquivos/**`, então ela **executou**; não foi skip-as-pass (conferido no
log pelo nome do teste + o contador, nunca pelo "0 failed" — §5 2026-07-24 · LC-13).
