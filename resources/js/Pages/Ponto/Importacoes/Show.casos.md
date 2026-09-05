---
id: resources-js-pages-ponto-importacoes-show-casos
casos: Resultado da importação AFD · /ponto/importacoes/{id}
irmaos: Show.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F7 + §6.4 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a ponte entre o relógio físico (REP-A homologado) e a jornada apurada — duplicar aqui infla a folha.
owner: wagner
last_run: "2026-09-05"
last_run_ci: "5 de 5 VERDES no run 33942364334 da lane PHP / Pest (Ponto · MySQL) — UC-IMPSH-01/02/03 2 assertions cada, UC-IMPSH-04 6, UC-IMPSH-05 12; lane inteira 279 passed / 0 failed / 1 skipped / 939 assertions. Os 5 estão ALCANÇÁVEIS pelo manifesto G-7 (o #6794 converteu o BancoHorasImportacaoContratoTest pra it() com o UC no título 12s antes deste arquivo entrar — ver nota ⛓); todos viram ✅ no primeiro publish do cron casos-results-publish"
---

# Casos de Uso & Aceite — Resultado da importação AFD

> **Âncora:** `CU-PONTO-10`, `CU-PONTO-11` e `CU-PONTO-12` do
> [SDD §6.4/§6.5](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) + **US-PONTO-002** ·
> **Portaria MTE 1.510/2009** (AFD legacy, REP-A INMETRO) e **Portaria MTP 671/2021 Anexo I**
> (rastreabilidade). Fonte 4 (Delphi) **ausente** — SDD §0.1.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)`. Quem responde se ela bloqueia
> merge é [`governance/required-checks-baseline.json`](../../../../../governance/required-checks-baseline.json),
> **nunca esta linha** — artefato não afirma o próprio enforcement em presente, porque apodrece
> no primeiro flip ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-07-16). Fato datado:
> a lane nasceu advisory em 2026-07-17 e foi promovida a **required** em 2026-08-05
> ([ADR 0369](../../../../../memory/decisions/0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md),
> emenda 0314). O texto que estava aqui seguia dizendo *"advisory: não bloqueia merge"* — falso
> desde a promoção, e é a mesma correção que o `ponto-pest.yml` já tinha feito no lado dele.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-IMPSH-01 | Reimportar o mesmo arquivo não duplica marcação | must | `CU-PONTO-10` + US-PONTO-002 | `BancoHorasImportacaoContratoTest` | 🧪 sem veredito |
| UC-IMPSH-02 | A dedup é do meu empregador, não global | must `[T0]` | `CU-PONTO-10` + ADR 0093 | `BancoHorasImportacaoContratoTest` | 🧪 sem veredito |
| UC-IMPSH-03 | Importação de outro empregador → 404 | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `BancoHorasImportacaoContratoTest` | 🧪 sem veredito |
| UC-IMPSH-04 | As contagens exibidas refletem o que foi processado | must | `CU-PONTO-11` + US-PONTO-002 | `BancoHorasImportacaoContratoTest` | 🧪 predição REFUTADA — verde na lane, sem entrada no manifesto |
| UC-IMPSH-05 | A importação que falhou mostra o motivo da falha | must | `CU-PONTO-11` + charter §Goals | `ImportacaoShowContratoTest` | 🧪 VERDE na lane (12 assertions) |

> ⛓ **SUPERADO em 2026-09-05 — o texto abaixo é fato datado, preservado porque a razão de os
> quatro seguirem `🧪` MUDOU, e saber qual razão vale hoje importa.**
>
> A nota dizia: *"os UC-IMPSH-01..04 seguem `🧪` mesmo passando porque o
> `BancoHorasImportacaoContratoTest` é classe PHPUnit e cita o UC em **docblock**; método PHP não
> aceita hífen, então o `name` do `<testcase>` sai `Uc impshow 04 …` e o coletor do manifesto (que
> casa `UC-XXX-NN`) nunca os enxerga. O conserto é converter aquele arquivo pra `it()`, e ele é
> dívida alheia adiada de propósito"* — mais uma nota `🔀` dizendo que o
> [#6822](https://github.com/wagnerra23/oimpresso.com/pull/6822) estava fazendo essa conversão.
>
> **Era verdade quando escrita e durou 12 segundos.** O
> [#6794](https://github.com/wagnerra23/oimpresso.com/pull/6794) mergeou às 07:20:39 fazendo
> exatamente aquela conversão (7 `it('UC-…')` com o id no título) e este arquivo entrou pelo
> [#6802](https://github.com/wagnerra23/oimpresso.com/pull/6802) às 07:20:51 — duas sessões
> paralelas no mesmo alvo, e o `dup-detector` não podia ver o #6794 porque ele já não estava
> aberto. Medido hoje: os 7 títulos carregam o UC-id e o bloco `⛓ Ponto` do `casos:report` é **0**.
> O #6822 foi reduzido a esta correção — a conversão que ele trazia virou redundante.
>
> **A razão que CONTINUA valendo, e é outra:** os cinco seguem `🧪` e não `✅` porque o oráculo do
> G-7 é o manifesto `scripts/casos-test-results.json`, publicado por **cron**
> (`casos-results-publish.yml`, `on: schedule`) e não pelo PR. `✅` antes da entrada no manifesto é
> `status:unverified`. Agora eles são **alcançáveis** por ele — viram `✅` no primeiro publish
> após o merge, junto com o UC-IMPSH-05.

**[BACKLOG]:**

- `[BACKLOG]` O processamento assíncrono resolve o tenant sem sessão — o job recebe o `business_id` no
  construtor ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)); provar isso
  é teste de job, não de tela.
- `[BACKLOG]` Validação de integridade do AFD (NSR sequencial, hash encadeado, faltas detectadas) —
  US-PONTO-002 lista a aceitação, mas o contrato mora no `AfdParserService`; a tela só exibe o resultado.
- `[BACKLOG]` AFD legacy (1.510/2009) está **parcial** e AFDT está **deprecated** regulatoriamente
  (substituído por AEJ, Anexo VI — US-PONTO-009, `_pendente_`). US sem código **não** vira UC agora:
  UC órfão trava o merge de quem for implementar ([proibicoes §5](../../../../../memory/proibicoes.md) 2026-07-16).

---

## UC-IMPSH-01 · Reimportar o mesmo arquivo não duplica marcação · `must`

- **Persona:** RH que subiu o AFD do relógio, não teve certeza se funcionou, e subiu de novo. Cenário
  banal — e o erro dele não pode virar jornada em dobro na folha.
- **Aceite:** Dado um arquivo já importado com sucesso · Quando envio **exatamente o mesmo arquivo** de
  novo · Então a importação é **recusada** com aviso de que já foi importada (identificando quando), e
  **nenhuma** marcação nova é criada.
- **Teste:** `Modules/Ponto/Tests/Feature/BancoHorasImportacaoContratoTest.php` — `UC-IMPSH-01`.
- **Contrato:** `CU-PONTO-10` (SDD §6.4) · US-PONTO-002 (aceitação: *"Importação idempotente (mesma AFD
  pode ser re-uploadada sem duplicar marcacoes)"*) · `ImportacaoController@store` (dedup por
  `hash_file('sha256')`).
- **Regressão que defende:** a idempotência é garantida por **hash de conteúdo**, não por nome de arquivo.
  Trocar para nome (ou remover a checagem "porque o parser já ignora repetido") reintroduz duplicação —
  e marcação duplicada infla a jornada apurada, que vira HE paga em duplicidade.
- **Status: 🧪 sem veredito.**

---

## UC-IMPSH-02 · A dedup é do meu empregador, não global · `must` `[T0]`

- **Persona:** dois empregadores diferentes que usam o **mesmo modelo de REP-A**. Nada impede que gerem
  arquivos AFD byte-idênticos (mesmo layout, mesmo período, relógio recém-instalado sem marcações).
- **Aceite:** Dado que um arquivo com determinado conteúdo já foi importado por **outro** business ·
  Quando eu importo um arquivo de conteúdo idêntico no **meu** business · Então a importação é **aceita**
  (a dedup não me bloqueia por causa do arquivo alheio).
- **Teste:** `BancoHorasImportacaoContratoTest.php` — `UC-IMPSH-02`.
- **Contrato:** `CU-PONTO-10` · `ImportacaoController@store` (a busca de duplicata é
  `where('business_id', $businessId)->where('hash_arquivo', $hash)`) ·
  [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** este é o vetor **inverso** do vazamento de dados — não é dado escapando, é
  **negação de serviço cross-tenant**: remover o `business_id` da checagem de duplicata faria o arquivo de
  um empregador **bloquear** a importação de outro. Passa despercebido em review porque "menos filtro"
  parece inofensivo. E o storage segue o mesmo princípio: o arquivo é gravado sob
  `ponto/importacoes/{businessId}`, segregado por tenant.
- **Nota de teste:** biz=1 vs business fictício — **nunca biz=4** ([ADR 0101]).
- **Status: 🧪 sem veredito.**

---

## UC-IMPSH-03 · Importação de outro empregador → 404 · `must` `[T0]`

- **Persona:** plataforma multi-tenant. O detalhe da importação expõe nome do arquivo, hash e contagens —
  e a rota irmã permite **baixar o arquivo original**, que contém as marcações brutas do outro empregador.
- **Aceite:** Dado o id de uma importação de **outro** business · Quando acesso
  `/ponto/importacoes/{id}` · Então recebo **404**.
- **Teste:** `BancoHorasImportacaoContratoTest.php` — `UC-IMPSH-03`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 · LGPD Art. 7º II.
- **Regressão que defende:** `ImportacaoController@{show,baixarOriginal}` usam `Importacao::findOrFail($id)`
  **sem** filtro explícito — defesa única pelo global scope (SDD §9 D-5). Aqui o risco é maior que nas
  outras telas: o `baixarOriginal` entrega o **arquivo bruto**, não um resumo. Este UC ancora a proteção
  na porta de entrada.
- **Nota de teste:** biz=1 vs id fictício — **nunca biz=4** ([ADR 0101]).
- **Status: 🧪 sem veredito.**

---

## UC-IMPSH-04 · As contagens exibidas refletem o que foi processado · `must`

- **Persona:** RH que acabou de subir o AFD do mês. A pergunta é uma só: *"entrou tudo?"*. A tela responde
  com "Marcações criadas" e "Linhas ignoradas" — se esses números mentem, o RH aprova uma importação
  incompleta achando que está completa.
- **Aceite:** Dado uma importação concluída que registrou **N linhas com sucesso** (N > 0) · Quando abro o
  detalhe dela · Então a tela informa **N marcações criadas** — não zero.
- **Teste:** `BancoHorasImportacaoContratoTest.php` — `UC-IMPSH-04`.
- **Contrato:** `CU-PONTO-11` (SDD §6.4) · US-PONTO-002 (aceitação: *"`Importacao` registra arquivo +
  checksum + linhas processadas + erros"*) · Portaria MTP 671/2021 Anexo I (rastreabilidade da origem).
- **Regressão que defende:** **a regressão JÁ ACONTECEU** — é a **mesma classe do achado do espelho**
  (`Espelho/Show` UC-ESPSH-01), reincidindo em outra tela do mesmo módulo. Varredura contada:
  `linhas_criadas`/`linhas_ignoradas` aparecem **9 vezes** (3 no `ImportacaoController`, 6 consumindo no
  front) e **não existem na tabela** — a migration `ponto_importacoes` tem `linhas_total`,
  `linhas_processadas`, `linhas_sucesso` e `linhas_erro`. O `?? 0` do controller **mascara** o campo
  ausente, então toda importação bem-sucedida exibe **0 marcações criadas** (SDD §9 D-8).
- **Por que o assert é sobre o valor, não sobre a chave:** o contrato é *"a contagem exibida reflete o
  processado"*. Se a correção for expor sob outro nome, front e assert mudam **juntos** — o que não pode
  é a tela seguir dizendo zero quando o banco diz sete.
- **Status: 🧪 predição REFUTADA.** A previsão de vermelho valeu enquanto o controller lia o atributo
  fantasma; ela **caiu**. Recibo (JUnit da lane, run `33938659118` no `main`):
  `Uc impshow 04 contagens exibidas refletem o processado` → **pass, 6 assertions**. Segue `🧪` e não `✅`
  porque o oráculo do G-7 é o manifesto e a entrada dele ainda não foi publicada — `✅` sem entrada no
  manifesto é `status:unverified`, não elogio. O que mudou em 2026-09-05 (ver a nota ⛓): o caso deixou
  de ser **inalcançável** pelo manifesto e passou a estar apenas **aguardando o publish** do cron — o
  recibo acima ainda mostra o nome de método antigo porque é de um run anterior à conversão do #6794.

---

## UC-IMPSH-05 · A importação que falhou mostra o motivo da falha · `must`

- **Persona:** RH que subiu o AFD do mês e vê a importação marcada como falha. A única pergunta que
  importa é *"por quê?"* — sem a resposta ele não sabe se reenvia o arquivo, se corrige o relógio, ou se
  chama o suporte. Meses depois é o auditor que precisa saber por que aquele arquivo não virou marcação.
- **Aceite:** Dado uma importação cujo processamento **falhou** e registrou o motivo · Quando abro o
  detalhe dela · Então a tela me informa **esse motivo, em texto**. E o simétrico: dado uma importação
  que **não** falhou · Então nenhum alerta de erro é acionado.
- **Teste:** `Modules/Ponto/Tests/Feature/ImportacaoShowContratoTest.php` — `UC-IMPSH-05`.
- **Contrato:** `CU-PONTO-11` (SDD §6.4 — *"a importação mostra estado, contagens fiéis ao que foi
  processado, **erro quando houver**"*) · SDD §5.3 F7 (*"`Importacoes/Show` acompanha `estado`, …,
  `erro_mensagem`"*) · US-PONTO-002 (aceitação: *"registra arquivo + checksum + linhas processadas +
  **erros**"*) · [`Show.charter.md`](Show.charter.md) §Goals (*"Alerta de erro com `erro_mensagem` quando
  o processamento falha"*) · Portaria MTP 671/2021 Anexo I.
- **Regressão que defende:** é a **4ª instância** do atributo fantasma (SDD §9 D-8) e a mais silenciosa
  das quatro. O controller lia `erro_mensagem`, que não é coluna nem accessor — a real é `log` — e o
  `{i.erro_mensagem && <Alert>}` do `Show.tsx` **nunca renderizava**. O efeito não era um número errado:
  era uma importação que falhou **parecendo bem-sucedida**, sem nada na tela dizendo o contrário. Quem
  escreve o motivo é o produtor (`ProcessarImportacaoAfdJob` grava `estado=FALHOU` + `log`; o
  `AfdParserService` idem), então o dado sempre esteve lá — só não chegava ao operador.
- **Por que o assert não é sobre a chave `erro_mensagem`:** mesma razão do UC-IMPSH-04. Há mais de uma
  correção legítima (renomear a leitura, accessor, `$appends`), e assert por chave literal reprovaria as
  outras. O caso procura o **motivo gravado** em qualquer chave do payload e exige que ele chegue como
  **string não-vazia** — a forma que a tela consegue renderizar. Se a correção passar a expor uma lista
  de erros, tela e assert mudam **juntos**.
- **Por que o controle negativo entra no mesmo UC:** o contrato diz *"quando o processamento falha"*, e
  esse "quando" tem dois lados. O lado negativo defende um bug **real e adjacente**: a coluna irmã
  `erros_amostra` tem cast `array`, e expô-la devolveria `[]` — que é **truthy em JS**. O alerta
  "Erro no processamento" passaria a abrir em *toda* importação, inclusive nas que deram certo.
- **Nota de teste:** biz=1 (WR2 interno) — **nunca biz=4** (ROTA LIVRE). Sem `RefreshDatabase`: a lane
  proíbe (dropa schema e limpa o seed).
- **Status: 🧪 VERDE na lane.** Recibo (JUnit do run `33942364334`):
  `it UC-IMPSH-05 · a importação que falhou mostra o motivo da falha` → **pass, 12 assertions**
  (`279 passed · 0 failed · 1 skipped · 939 assertions` na lane inteira). Assertions > 0 é o que
  separa "passou" de "pulou" — `0 failed` sozinho não prova execução (LC-13).
  Fica `🧪` e **não** `✅` por um motivo mecânico, não por modéstia: o oráculo do G-7 é o
  manifesto `scripts/casos-test-results.json`, publicado por **cron** (`casos-results-publish.yml`,
  `on: schedule`) e não pelo PR — declarar `✅` antes de o manifesto ter a entrada seria
  `status:unverified`. Vira `✅` no primeiro publish após o merge.
