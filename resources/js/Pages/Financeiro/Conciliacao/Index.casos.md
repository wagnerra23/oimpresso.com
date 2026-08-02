---
id: resources-js-pages-financeiro-conciliacao-index-casos
casos: Conciliação bancária · /financeiro/conciliacao
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-02"
---

# Casos de uso — /financeiro/conciliacao

> **Status:** ✅ passa (provado pelo manifesto da lane) · 🧪 teste existe e passou em run
> avulso, mas a lane **não** produz veredito pra ele (quarentena) · ⬜ não verificado ·
> ❌ quebrou.

> **Derivação (ordem-de-fonte, não do `.tsx`):** os UC abaixo derivam do
> [SDD Financeiro §6.2 `CU-FIN-10/11/12`](../../../../../memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md)
> e dos **Goals** do [charter](Index.charter.md) (lista unificada das duas origens,
> [ADR 0236](../../../../../memory/decisions/0236-extrato-conciliacao-modelo-unificado.md) Fase 1).
> O código foi lido só para **confirmar** comportamento. Persona: Eliana [E] (financeiro).
> US âncora: `US-FIN-009`.

> ⚠️ **Por que quase nada aqui é ✅ (e isso é honesto, não preguiça).** Três dos quatro
> arquivos de teste desta tela estão na
> [quarentena da lane](../../../../../.github/financeiro-pest-quarantine.list) — bucket **B
> (RefreshDatabase incompatível com o processo compartilhado)**, não por estarem errados.
> A própria lista registra: *"Três deles PASSAM isolados … e ficam aqui só por causa do
> compartilhamento"*. Teste fora da lane **não gera veredito** (G-7), então declarar ✅ seria
> "status sem prova". Só `ConciliacaoLeExtratoApiTest` roda na lane required.

---

## Recibo do run (2026-08-02, CT 100 · MySQL real)

Rodado um arquivo por processo (attribution limpa) no container `oimpresso-staging`,
database `oimpresso_staging`. Os 4 blobs conferidos idênticos aos de `origin/main`
(`ConciliacaoController` `d62be2db`, testes `40412192`/`4d901c7b`/`731a3abe`) — o
checkout do CT 100 é de 2026-07-23 e estes arquivos não mudam desde 2026-06-08.

| Arquivo | Veredito | Assertions |
|---|---|---|
| `ConciliacaoUploadDedupeTest` | 4 passed | 24 |
| `ConciliacaoMatchScoreTest` | 3 passed | 15 |
| `ConciliacaoAuditReabrirTest` | **1 failed**, 4 passed | 15 |

`assertions > 0` em todos — nenhum passou por não-execução (§5 2026-07-24 · LC-13).

---

## CU-FIN-10 — Importar OFX é idempotente por hash

## UC-FCC-01 — Upload de OFX cria as linhas novas do extrato
Status: 🧪 (`ConciliacaoUploadDedupeTest::test_happy_path_importa_todas_as_transacoes_novas` — 2026-08-02 passou avulso; lane não roda)
Eliana envia um arquivo OFX. Cada `<STMTTRN>` do arquivo vira uma linha pendente em
`fin_bank_statement_lines`, com valor/data/descrição **como vieram da fonte**.
**Pronto quando:** a contagem de linhas criadas = a contagem de `<STMTTRN>` do arquivo.

## UC-FCC-02 — Reenviar o mesmo OFX não duplica linha
Status: 🧪 (`ConciliacaoUploadDedupeTest::test_fitid_duplicado_e_pulado_sem_excecao_e_count_correto` + `::test_upload_duplicado_double_click_e_idempotente`)
O `FITID` de cada transação é a chave de deduplicação. Reenviar o arquivo inteiro — ou o
double-click que dispara dois POSTs concorrentes — **pula** o que já existe, sem exceção,
e reporta a contagem correta de importadas. Âncora: `US-FIN-009` DoD; charter Automation
Hooks (*"`upload()` … faz `insertOrIgnore` idempotente (anti-race)"*).
**Pronto quando:** 2º upload do mesmo arquivo cria 0 linhas e não lança.

## UC-FCC-03 — Linha importada nasce com o business_id do tenant `[T0]`
Status: 🧪 (`ConciliacaoUploadDedupeTest::test_todas_as_linhas_recebem_business_id_do_tenant`)
Toda linha gravada pelo upload carrega o `business_id` da sessão — nunca nulo, nunca de
outro tenant. Âncora: [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
**Pronto quando:** nenhuma linha criada tem `business_id` diferente do da sessão.

---

## CU-FIN-11 — `match_score` discrimina candidatos

> Este CU **corrige o charter**, que descrevia *"score fixo 0.85 no MVP"*. O 0.85 constante
> era o **bug B1** ([ADR 0236](../../../../../memory/decisions/0236-extrato-conciliacao-modelo-unificado.md)),
> já corrigido: `ConciliacaoController::calcularMatchScore()` computa `0.7·valor + 0.3·data`.
> Precedência (teste verde > casos > charter): o charter perdeu e foi corrigido no mesmo PR.

## UC-FCC-04 — Valor exato no mesmo dia dá score ≈1.0, nunca a constante 0.85
Status: 🧪 (`ConciliacaoMatchScoreTest` — *"valor exato + mesmo dia gera score ~1.0 (NÃO o constante 0.85)"* + *"score nunca é o constante 0.85 pra candidatos distintos"*)
Candidato com o mesmo valor e a mesma data recebe score próximo de 1.0. Dois candidatos
**distintos** nunca recebem o mesmo 0.85 — se receberem, o score voltou a ser decorativo e a
UI mente ao mostrar "match 85%".
**Pronto quando:** `match_score` do par exato > 0.85 e dois candidatos distintos têm scores distintos.

## UC-FCC-05 — Candidato com data afastada pontua estritamente menos
Status: 🧪 (`ConciliacaoMatchScoreTest` — *"candidato com data afastada tem score estritamente menor que o exato"*)
Dentro da janela de ±3 dias, o componente de data decai — logo o candidato afastado tem
score **estritamente menor** que o do mesmo dia. Âncora: `US-FIN-009` DoD
(`valor_exato + tolerancia_3_dias`).
**Pronto quando:** `score(data afastada) < score(mesmo dia)` para o mesmo valor.

---

## CU-FIN-12 — `reabrir()` é reversível, idempotente e tenant-safe `[T0]`

## UC-FCC-06 — Reabrir volta a linha pra pendente e zera o vínculo
Status: ❌ **não provado** — o teste morre no setup, não na asserção (recibo abaixo)
Reabrir uma linha conciliada devolve `status = pendente`, e zera `titulo_id` **e**
`match_score` (o vínculo com o título é desfeito por inteiro, não pela metade).

> **O achado (run 2026-08-02, CT 100):** `ConciliacaoAuditReabrirTest.php:73 (verificado@8cd20a3)`
> insere a linha de fixture com `titulo_id = 12345` **hardcoded**, que não existe em `fin_titulos` →
> `SQLSTATE[23000] … foreign key constraint fails (fin_bank_statement_lines_titulo_id_foreign)`.
> O caso **nunca chega** a exercer `reabrir()`. Portanto o comportamento central deste CU
> `[must]` `[T0]` está **sem prova** — não reprovado. É defeito de fixture, não de produto:
> os outros 4 casos do mesmo arquivo passam. Correção = decisão [W] (ver `[BACKLOG]` abaixo).

## UC-FCC-07 — Reabrir duas vezes não quebra
Status: 🧪 (`ConciliacaoAuditReabrirTest` — *"reabrir() é idempotente — linha já pendente continua pendente (sem erro)"*)
Reabrir uma linha que já está pendente é no-op silencioso — não lança, não muda estado.
**Pronto quando:** 2ª chamada devolve sucesso e o status segue `pendente`.

## UC-FCC-08 — Reabrir linha de outro business devolve 404 `[T0]`
Status: 🧪 (`ConciliacaoAuditReabrirTest` — *"Tier 0: reabrir() de linha de OUTRO business retorna 404 (ADR 0093)"*)
A linha de outro tenant não é "proibida" — ela **não existe** para esta sessão (404, não 403).
Âncora: [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md);
charter Automation Anti-hooks (*"Não acessa dados de outro `business_id`"*).
**Pronto quando:** POST de reabertura em linha de outro business responde 404 e não altera a linha.

## UC-FCC-09 — Conciliar e ignorar deixam trilha de auditoria
Status: 🧪 (`ConciliacaoAuditReabrirTest` — *"match() escreve entrada de auditoria…"* + *"ignorar() escreve entrada de auditoria…"*)
`match()` e `ignorar()` gravam entrada via `FinanceiroAuditLogger`. Sem trilha, a conciliação
vira mudança de dinheiro sem autor.
**Pronto quando:** cada ação produz 1 entrada de auditoria referenciando a linha.

---

## Lista unificada das duas origens (charter Goals · ADR 0236 Fase 1)

> ⚠️ **ERRATA 2026-08-02 (mesmo dia).** A 1ª redação deste bloco dizia que estes quatro *"são
> os únicos desta tela que **podem virar ✅** pelo manifesto"*. **É FALSO, e o erro é meu.**
> Eles rodam mesmo na lane required (`ConciliacaoLeExtratoApiTest` não está na quarentena), mas
> `casos-results-collect.mjs` lê o UC do atributo `name` do `<testcase>` — ou seja, do **título**
> do teste. Estes são métodos PHPUnit (`public function test_index_lista_linha_api_alem_de_ofx`),
> cujo `name` no JUnit não carrega UC nenhum; eu pus o id em **docblock**, que o coletor não lê.
> Medido, não inferido: dos **82 UCs do manifesto, 82 vêm de título `it()`/`test()` e 0 de método
> `test_`**. Nome de método PHP não aceita hífen, e o regex canônico (`scripts/lib/uc-regex.mjs`)
> exige `UC-FCC-NN` — logo **não há forma de docblock que funcione**. Como estão, `UC-FCC-10..13`
> nunca chegam ao G-7. Ver `[BACKLOG]` abaixo.
>
> Lição da classe LC-11 (presence ≠ prova): o G-2 ficou verde porque eu **escrevi a string do id
> no arquivo** — isso prova acoplamento de texto, não que o teste exerça o caso.

## UC-FCC-10 — A lista mostra extrato de API junto com o de OFX
Status: 🧪 (`ConciliacaoLeExtratoApiTest::test_index_lista_linha_api_alem_de_ofx` — lane required)
A tela reúne `fin_bank_statement_lines` (OFX) **e** `fin_extrato_lancamentos` (sync API do
banco) no mesmo shape, com a coluna **Origem** distinguindo Banco de OFX.
**Pronto quando:** `props.linhas` contém a linha de `origem = 'api'`.

## UC-FCC-11 — A sugestão marca a linha na tabela de origem certa
Status: 🧪 (`ConciliacaoLeExtratoApiTest::test_sugerir_matches_casa_linha_api_e_marca_na_tabela_do_extrato` — lane required)
`sugerirMatches()` roda contra as duas origens; ao casar uma linha de API com um título
aberto, grava `status = sugerido` + `titulo_id` **em `fin_extrato_lancamentos`**, não na
tabela do OFX.
**Pronto quando:** a linha de API fica `sugerido` com `titulo_id` preenchido.

## UC-FCC-12 — Conciliar/ignorar com `origem=api` atualiza a tabela do extrato
Status: 🧪 (`ConciliacaoLeExtratoApiTest::test_match_origem_api_atualiza_tabela_extrato` + `::test_ignorar_origem_api_atualiza_tabela_extrato` — lane required)
O POST carrega a `origem`; o controller resolve a tabela por ela. `match` deixa
`conciliado` + `titulo_id`; `ignorar` deixa `ignorado`. Sem `window.location.reload()`
(charter UX Anti-patterns).
**Pronto quando:** a linha de API reflete o novo status e o redirect volta pra tela sem erro de sessão.

## UC-FCC-13 — Linha de API de outro business nunca é conciliada `[T0]`
Status: 🧪 (`ConciliacaoLeExtratoApiTest::test_match_api_respeita_business_id_tier0` — lane required)
O UPDATE de match filtra por `business_id` da sessão. Uma linha pertencente a outro business
permanece intocada (`status` segue nulo).
**Pronto quando:** após o POST cross-tenant, `status` da linha do outro business continua nulo.

---

## Backlog declarado (prosa honesta, ainda sem UC — não tem teste que cite)

- `[BACKLOG]` Consertar a fixture do `ConciliacaoAuditReabrirTest` (`titulo_id = 12345`
  hardcoded viola FK) para que **UC-FCC-06** possa produzir veredito. É o único `[must]`
  `[T0]` desta tela sem prova.
- `[BACKLOG]` Converter os 5 métodos de `ConciliacaoLeExtratoApiTest` para `it('UC-FCC-NN · …')`
  (estilo Pest), única forma do UC chegar ao `name` do `<testcase>` e portanto ao manifesto G-7.
  Enquanto não for feito, `UC-FCC-10..13` ficam presos em 🧪 **mesmo rodando verde na lane
  required** — o veredito existe, o coletor é que não sabe a qual UC atribuí-lo. É o único item
  desta lista que destrava ✅ de verdade nesta tela.
- `[BACKLOG]` Tirar os 3 arquivos da quarentena movendo-os pra job com banco próprio — a
  própria lista aponta esse encaminhamento (*"job separado com banco próprio — não é defeito
  do teste"*). Enquanto não sair, nenhum UC-FCC-01..09 pode passar de 🧪.
- `[BACKLOG]` Os Non-Goals do charter (❌ editar linha · ❌ conciliação N:N · ❌ desfazer
  conciliação confirmada · ❌ export) ainda não têm Pest GUARD. Charter manda virar guarda;
  quem preenche Non-Goal é [W].
