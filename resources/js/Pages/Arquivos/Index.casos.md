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

> **Por que só 1 UC com id aqui.** A tela existe desde o PR-1 ([#6216](https://github.com/wagnerra23/oimpresso.com/pull/6216),
> 2026-08-24: rota `GET /arquivos` viva), mas só com a vista **Acervo** — trilha, retenção e
> cofre chegam nos PR-2/3/4. Declarar os 14 cenários do protótipo F1 como UC agora criaria 13
> órfãos e quebraria o G-2, porque o teste que os defende ainda não existe. Eles ficam abaixo
> como `[BACKLOG]` — prosa honesta, sem id — e **viram UC na onda que traz o teste que os
> defende**. Fonte: `prototipo-ui/cowork/arquivos-page.jsx`.

---

## UC-INDEX-01 · O acervo lista o que o sistema guardou, com prazo e base legal
- **Persona:** Wagner (escritório, conformidade) — quer saber o que está guardado, de quem é,
  e até quando a lei manda guardar, sem abrir tela por tela.
- **Aceite:** Dado um business com arquivos anexados via `HasArquivos` · Quando abre `/arquivos`
  com a permissão `arquivos.access` · Então vê a lista do **próprio** business com nome do
  arquivo, dono (`arquivable`), bucket, disco, tamanho e data de vencimento — e nenhum arquivo
  de outro `business_id`.
- **Teste:** `Modules/Arquivos/Tests/Feature/ArquivosAdminControllerTest.php` — **4** asserções
  citando `UC-INDEX-01` no título do `it()` (scope não quebrado · sem `storage_path`/md5 na
  linha · leitura pura · `politica()` devolve prazo E base legal)
  + `e2e/arquivos-index.spec.ts` (stub `test.fixme`, vira asserção quando a rota subir em prod).
- **Regressão que defende:** vazamento cross-tenant no acervo (ADR 0093, Tier 0) e prazo
  exibido sem a lei que o sustenta.
- **Status: 🧪** — a lane executou e o manifesto aterrissou: `scripts/casos-test-results.json`
  (gerado 2026-08-25, fonte `test-results/pest-arquivos-junit.xml`) traz
  `UC-INDEX-01 → verdict pass · tests 4`. Bate com as 4 asserções acima. Não é ✅ porque, pelo
  cabeçalho deste arquivo, 🧪 é exatamente isto — *teste cita o UC e passa*; o status veio do
  veredito, não da minha leitura (G-7). `ran_at` vem `null`, como em 314 dos 361 UCs do
  manifesto — é o normal do parser de JUnit, não sinal de que não rodou.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

Derivados do protótipo F1 (`arquivos-page.jsx`). A onda que implementar cada vista traz o teste
e promove o item a `UC-INDEX-NN`.

**Onda 1 — ler**

- **[BACKLOG]** Acervo com 10 arquivos → subtítulo com contagem e quantos estão cifrados (as 4 abas chegam com as vistas: PR-2 trilha · PR-3 retenção · PR-4 cofre).
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
- **[BACKLOG]** Aba Trilha → eventos read-only; nenhuma ação de editar ou apagar linha.
- **[BACKLOG]** Papel sem `arquivos.access` → sem-permissão explicando que o anexo da OS continua acessível por quem vê a OS.

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
- Nenhuma vista lista arquivo de outro `business_id`.
- Excluir nunca chama hard-delete direto — só soft-delete + grace.
- Prazo exibido sempre com base legal; mudar prazo exige mudar `Config/config.php` **e**
  `Config/retention.php` (são espelho declarado, e divergir é achado de auditoria).

## Trilha do tempo
- 2026-07-11 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste). Refs: UI-0013 · ADR 0264 G-1/G-2.
- 2026-08-24 · [CL] preenchido a partir do protótipo F1 exportado do Cowork (`arquivos-page.jsx`) + do rascunho `cowork-inbox/modulos-faltantes/arquivos.casos.md`. Os 14 cenários entraram como `[BACKLOG]` (sem id) pra não nascer órfão no G-2; o item de reclassificar foi marcado `[BLOQUEADO]` porque o Service não suporta o contrato da Request. Refs: US-ARQ-013 · ADR 0360.
