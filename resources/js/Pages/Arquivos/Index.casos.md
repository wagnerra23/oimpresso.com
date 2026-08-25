---
casos: Arquivos/Index — carimbado do Padrão de Tela
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-08-25"
---

# Casos de Uso & Aceite — Arquivos/Index

> Nascido de `criar-tela.mjs`. **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. O stub `e2e/arquivos-index.spec.ts` já cita `UC-INDEX-01`.

> **Por que poucos UC com id aqui.** A tela nasceu no PR-1 ([#6216](https://github.com/wagnerra23/oimpresso.com/pull/6216),
> 2026-08-24: rota `GET /arquivos` viva) só com a vista **Acervo**; o PR-2 acrescentou a
> **Trilha** e, com ela, a barra de abas. Retenção e cofre chegam nos PR-3/4. Declarar os 14
> cenários do protótipo F1 como UC de uma vez criaria órfãos e quebraria o G-2, porque o teste
> que os defende ainda não existe. Eles ficam abaixo como `[BACKLOG]` — prosa honesta, sem id —
> e **viram UC na onda que traz o teste que os defende**. Fonte: `prototipo-ui/cowork/arquivos-page.jsx`.

---

## UC-INDEX-01 · O acervo lista o que o sistema guardou, com prazo e base legal
- **Persona:** Wagner (escritório, conformidade) — quer saber o que está guardado, de quem é,
  e até quando a lei manda guardar, sem abrir tela por tela.
- **Aceite:** Dado um business com arquivos anexados via `HasArquivos` · Quando abre `/arquivos`
  com a permissão `arquivos.access` · Então vê a lista do **próprio** business com nome do
  arquivo, dono (`arquivable`), bucket, disco, tamanho e data de vencimento — e nenhum arquivo
  de outro `business_id`.
- **Teste:** `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` — **5** asserções
  citando `UC-INDEX-01` no título do `it()` (scope não quebrado · sem `storage_path`/md5 ·
  leitura pura · `politica()` devolve prazo E base legal · entrada no sidebar com as 3 camadas)
  + `e2e/arquivos-index.spec.ts` (stub `test.fixme`, vira asserção quando a rota subir em prod).
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
- **[BACKLOG]** Arquivo em disco `vault` → selo com cadeado; baixar avisa que o link assinado vale 60 min e passa pelo `DownloadController`.
- **[BACKLOG]** Arquivo sem `arquivable` → linha marcada como urgente + selo "órfão" com o motivo em tooltip (órfão é achado, não item).
- **[BACKLOG]** Arquivo a ≤30 dias do prazo → coluna "Vence em" em vermelho + linha urgente.
- **[BACKLOG]** Arquivo com prazo vencido → rótulo "prazo vencido", nunca contagem negativa.
- **[BACKLOG]** Aba Retenção → tabela com os 8 contextos, prazo em anos/dias e a base legal literal.
- **[BACKLOG]** Existe arquivo além do prazo + grace → banner citando `HealthCheckCommand` check #4 e LGPD Art. 16.
- **[BACKLOG]** Aba Cofre → espaço por disco (vault × local) + os 3 achados com contagem.
- **[BACKLOG]** Arquivo de 65 MB → listado como acima do cap de 50 MB, com a razão (OOM) e ADR 0126.
- **[BACKLOG]** Dois arquivos com o mesmo MD5 → agrupados como duplicado, com a ressalva de que nem sempre é erro.
- **[BACKLOG]** Papel sem `arquivos.access` → sem-permissão explicando que o anexo da OS continua acessível por quem vê a OS.
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
- Nenhum botão de editar/apagar linha da trilha.
- A trilha filtra por `business_id` explicitamente — a tabela não tem model nem global
  scope, então aqui o `where` **é** a defesa Tier 0, não redundância dela.
- Sem `business_id` na sessão, a trilha devolve vazio (fail-closed) — nunca o log inteiro.
- Nenhuma vista lista arquivo de outro `business_id`.
- Excluir nunca chama hard-delete direto — só soft-delete + grace.
- Prazo exibido sempre com base legal; mudar prazo exige mudar `Config/config.php` **e**
  `Config/retention.php` (são espelho declarado, e divergir é achado de auditoria).

## Trilha do tempo
- 2026-07-11 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste). Refs: UI-0013 · ADR 0264 G-1/G-2.
- 2026-08-24 · [CL] preenchido a partir do protótipo F1 exportado do Cowork (`arquivos-page.jsx`) + do rascunho `cowork-inbox/modulos-faltantes/arquivos.casos.md`. Os 14 cenários entraram como `[BACKLOG]` (sem id) pra não nascer órfão no G-2; o item de reclassificar foi marcado `[BLOQUEADO]` porque o Service não suporta o contrato da Request. Refs: US-ARQ-013 · ADR 0360.
- 2026-08-25 · [CC] **UC-INDEX-02 (trilha) promovido de `[BACKLOG]` a UC** — a onda PR-2 trouxe o teste que o defende, que é a condição do G-2. Nasce `⬜`: o veredito é da lane, não da leitura. Refs: US-ARQ-013 · ADR 0093 (o `where` explícito numa tabela sem model) · ADR 0123 §8.
