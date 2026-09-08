---
id: resources-js-pages-ponto-configuracoes-reps-casos
casos: Cadastro dos Registradores Eletrônicos de Ponto · /ponto/configuracoes/reps
irmaos: Reps.charter.md (lei) · Index.casos.md (a tela irmã) · RUNBOOK-configuracoes.md
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o identificador do REP é o que amarra cada marcação ao dispositivo que a gerou — é o campo que a fiscalização cruza, e um REP cadastrado errado contamina todo AFD gerado depois.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "2 UC rodados por mim no CT 100 (container oimpresso-staging, MySQL real), NAO em CI. Codigo do ConfiguracaoController@reps/@storeRep identico ao main no container (medido por git diff c1abe9548..origin/main, com controle positivo). CT100 != CI: base persiste entre runs — verde la e CANDIDATURA, nao veredito. Nota: o unico metodo que este arquivo NAO cobre e o @index da tela irma, coberto por UC-CFGIDX-01."
---

# Casos de Uso & Aceite — Cadastro de REPs

> **Âncora:** Portaria MTP 671/2021 Anexo I (formato do identificador) + `CU-PONTO-12` do
> [SDD §6.5](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) +
> [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) + o
> `Reps.charter.md` ao lado. Os UC derivam do **contrato**, nunca do `Reps.tsx`.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-CFGREP-01 | A lista de REPs não mostra dispositivo de outro empregador | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `ConfiguracaoContratoTest` | 🧪 verde no CT 100, sem veredito de lane |
| UC-CFGREP-02 | Identificador fora do formato da Portaria é recusado | must | Portaria 671/2021 Anexo I + charter §Goals | `ConfiguracaoContratoTest` | 🧪 verde no CT 100, sem veredito de lane |
| UC-CFGREP-03 | A lista traz o REP do meu empregador com a identificação dele | must | charter §Mission + US-PONTO-007 | `ConfiguracaoContratoTest` | 🧪 verde no CT 100, sem veredito de lane |
| UC-CFGREP-04 | REP cadastrado nasce no meu empregador, não no que veio na requisição | must `[T0]` | `CU-PONTO-12` + charter §Automation hooks | `ConfiguracaoContratoTest` | 🧪 verde no CT 100, sem veredito de lane |
| UC-CFGREP-05 | Tipo fora de REP-P/C/A é recusado | must | Portaria 671/2021 (tipos taxativos) | `ConfiguracaoContratoTest` | 🧪 verde no CT 100, sem veredito de lane |

**[BACKLOG]** (medido, sem teste que defenda — ou pergunta ainda aberta ao [W]):

- `[BACKLOG]` A unicidade do identificador é **global**, não por empregador: a regra é
  `unique:ponto_reps,identificador` sem cláusula de business. Medido — cadastrar um identificador já
  usado por **outro** empregador é recusado. Isso **provavelmente está certo** (pelo Anexo I o
  identificador é CNPJ de 14 dígitos + sequencial de 3, então é único por construção legal), mas
  contrasta com o `CU-PONTO-10`, que exige a idempotência da importação **escopada ao business**
  justamente pra um empregador não colidir com outro. Fica registrado porque tem dois efeitos que
  ninguém decidiu: matriz e filial com o mesmo CNPJ em businesses distintos colidiriam, e a mensagem
  de recusa **revela** que o identificador já existe em algum lugar do sistema (enumeração). Qual dos
  dois comportamentos é o desejado é decisão de [W], não inferência minha.
- `[BACKLOG]` O charter pergunta em §Pendências se **editar ou inativar** REP entra nesta tela (hoje
  só cadastra e lista). Enquanto não houver resposta, não há contrato para testar. Nota de compliance
  pra quem for decidir: apagar REP com marcação associada quebraria a rastreabilidade que o Anexo I
  exige — o caminho provável é inativar, não excluir.
- `[BACKLOG]` **O banco desta instalação não é `STRICT`, então a validação da aplicação é defesa
  única para o enum de tipo.** Medido no CT 100: `@@SESSION.sql_mode` = `NO_ENGINE_SUBSTITUTION` —
  sem `STRICT_TRANS_TABLES`. A coluna é `enum('REP_P','REP_C','REP_A')`, e em MySQL não-estrito um
  valor fora do enum **não** é recusado: vira **string vazia**. Hoje quem segura é a regra
  `in:REP_P,REP_C,REP_A` do `storeRep` (medido: tipo `REP_XX` é recusado). Consequência: afrouxar
  essa regra não produz erro — produz REP gravado **sem classificação legal**, e a Portaria MTP
  671/2021 distingue REP-P, REP-C e REP-A justamente para efeito de fiscalização. ✅ **PROMOVIDO a `UC-CFGREP-05`** — a razão de não virar UC era de
  CUSTO (teste próprio + bite-test), nunca de mérito, e o custo foi pago no PR complementar. O
  diagnóstico acima segue valendo palavra por palavra — é exatamente o que o caso defende; fica registrado com a medição para quem for
  mexer nas regras do `storeRep`. **Crédito:** vetor levantado por sessão paralela
  (`claude/ponto-casos-config-escalas`) e verificado aqui antes de entrar.
- `[BACKLOG]` **O `business_id` do REP não vem do cliente — e isso não tem trava.** Medido: enviar
  `business_id` de outro empregador **no corpo do POST** grava o REP no **meu** business. Funciona
  por construção — a chave não está nas regras do `validate`, e o controller a injeta da sessão
  depois. É defesa por omissão: basta alguém adicionar `business_id` às regras (por conveniência,
  num formulário multi-empresa) para o input do cliente passar a mandar. É a mesma classe do
  `UC-INTCRE-01` do módulo. ✅ **PROMOVIDO a `UC-CFGREP-04`** — ganhou exatamente esse teste:
  posta o `business_id` alheio no corpo e afirma em qual business o registro nasceu. **Crédito:** mesma sessão paralela; medido aqui.
- `[BACKLOG]` Nada cobre o **efeito** do REP cadastrado: que uma marcação criada por ele passe a citar
  aquele identificador no AFD. É o contrato mais valioso desta tela e o mais caro — atravessa
  `MarcacaoService` e o gerador de AFD, e é trabalho próprio.

---

## UC-CFGREP-01 · A lista de REPs não mostra dispositivo de outro empregador · `must` `[T0]`

- **Persona:** gestor de RH de um empregador. O identificador do REP carrega o **CNPJ** de quem o
  registrou — ver a lista de outro empregador é ver com que dispositivos ele bate ponto e sob que CNPJ.
- **Aceite:** Dado um REP cadastrado em **outro** empregador · Quando abro `/ponto/configuracoes/reps` ·
  Então o identificador e a descrição dele **não** aparecem na lista.
- **Teste:** `Modules/Ponto/Tests/Feature/ConfiguracaoContratoTest.php` — `UC-CFGREP-01`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 ·
  [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) ·
  charter §Non-Goals (*"Não lista REP de outro business"*).
- **Regressão que defende — e o limite dela:** aqui a defesa é **dupla** (o filtro
  `where('business_id', …)` do controller, que nesta consulta funciona porque não há `orWhere` para
  neutralizá-lo, **e** o global scope do model `Rep`), ao contrário da busca de colaboradores, onde
  só o scope segura (`UC-COLIDX-01`). Consequência honesta, medida na tela irmã de escalas com o
  mesmo formato de defesa: um caso deste tipo **só morde quando as duas caem** — com apenas uma
  removida, a outra segura e o teste passa verde. Ele é rede contra a perda **completa** do
  isolamento desta lista, não um detector de defesa enfraquecida.
- **Status: 🧪 verde no CT 100, sem veredito de lane.**

---

## UC-CFGREP-02 · Identificador fora do formato da Portaria é recusado · `must`

- **Persona:** gestor cadastrando um REP novo. O identificador não é um rótulo livre: pelo Anexo I da
  Portaria MTP 671/2021 ele tem **17 caracteres** (CNPJ de 14 + sequencial de 3), e é por ele que a
  fiscalização amarra cada marcação ao dispositivo.
- **Aceite:** Dado um identificador com tamanho diferente de 17 · Quando envio o cadastro · Então ele é
  **recusado**, o operador recebe o erro no campo do identificador, e **nenhum** REP é criado.
- **Teste:** `ConfiguracaoContratoTest.php` — `UC-CFGREP-02`.
- **Contrato:** Portaria MTP 671/2021 Anexo I · charter §Goals (*"identificador (17 chars)"* +
  *"Validação: identificador único de 17 chars"*).
- **Regressão que defende:** afrouxar a regra para `string|max:17` (ou tirar o `size`) deixa entrar
  identificador curto — que passa na tela, entra no banco, e só aparece como problema no **AFD gerado
  para fiscalização**, meses depois, quando o arquivo for rejeitado. O caso afirma **as três coisas**:
  que não foi sucesso, que o erro apontou o campo, e que a tabela não ganhou linha — só a primeira
  passaria também num 500.
- **Status: 🧪 verde no CT 100, sem veredito de lane.**

---

## UC-CFGREP-03 · A lista traz o REP do meu empregador com a identificação dele · `must`

- **Persona:** gestor/RH conferindo quais equipamentos estão registrados antes de uma fiscalização,
  ou antes de importar um AFD e precisar saber de qual REP ele veio.
- **Aceite:** Dado um REP cadastrado no meu business · Quando abro `/ponto/configuracoes/reps` ·
  Então ele aparece na lista com tipo, identificador e descrição.
- **Teste:** `Modules/Ponto/Tests/Feature/ConfiguracaoContratoTest.php` — `UC-CFGREP-03`.
- **Contrato:** charter §Mission (*"a tela lista os REPs cadastrados (tipo, identificador,
  descrição, local, ativo)"*) · US-PONTO-007 (nomeia `Rep` entre as models do módulo).
- **Regressão que defende:** `ConfiguracaoController@reps` monta cada linha por `transform()`,
  campo a campo. Um campo removido de lá não quebra a query nem o status 200 — a linha chega
  **sem o dado**, e o identificador é justamente o que amarra o equipamento ao registro perante o
  auditor. **E há um segundo motivo, de método:** o `UC-CFGREP-01` prova que o REP ALHEIO não
  entra; sozinho, ele seria satisfeito também por uma lista **sempre vazia**. Este caso é o
  controle positivo do par — sem ele, "a lista está isolada" e "a lista está quebrada" produzem
  o mesmo verde.
- **Status: 🧪 sem veredito.**

---

## UC-CFGREP-04 · REP cadastrado nasce no meu empregador, não no que veio na requisição · `must` `[T0]`

- **Persona:** a mesma do `UC-CFGREP-01`, com o vetor invertido. Isolamento de **leitura** e de
  **escrita** são propriedades diferentes: uma lista escopada certo convive com um `create` que
  grava no business errado — e o registro errado só aparece quando o **outro** empregador abrir a
  tela dele.
- **Aceite:** Dado que eu cadastro um REP pela tela enviando `business_id` de outro empregador no
  corpo · Quando o POST é aceito · Então o registro gravado pertence ao **meu** business.
- **Teste:** `ConfiguracaoContratoTest.php` — `UC-CFGREP-04`.
- **Contrato:** charter §Automation hooks (*"`storeRep` injeta `business_id` no create
  automaticamente (scope tenant)"*) · `CU-PONTO-12` ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** hoje funciona **por omissão**, não por trava — a chave `business_id`
  não está nas regras do `$request->validate([...])`, e o controller a injeta da sessão depois.
  Basta alguém acrescentar `business_id` às regras (por conveniência, num formulário
  multi-empresa) para o input do cliente passar a mandar. É a mesma classe do `UC-INTCRE-01` do
  módulo, onde o `business_id` **nunca** era atribuído no caminho de criação e registrar
  simplesmente não gravava.
- **Nota de teste:** o adversário é o biz fictício 99 via `garantirBizAlheio()` — **nunca biz=4**
  ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).
- **Status: 🧪 sem veredito.**

---

## UC-CFGREP-05 · Tipo fora de REP-P/C/A é recusado · `must`

- **Persona:** auditor fiscal do trabalho. Os três tipos são taxativos na Portaria (REP-P programa,
  REP-C convencional, REP-A alternativo) — não é enum de conveniência interna, é classificação
  legal que determina o regime de coleta e de fiscalização do equipamento.
- **Aceite:** Dado um tipo fora de `REP_P`/`REP_C`/`REP_A` · Quando submeto o cadastro · Então é
  recusado com erro no campo `tipo` e nenhum REP é criado.
- **Teste:** `ConfiguracaoContratoTest.php` — `UC-CFGREP-05`.
- **Contrato:** **Portaria MTP 671/2021** (tipos taxativos) · charter §Goals (*"tipo restrito ao
  enum"*) · `ConfiguracaoController@storeRep` (`in:REP_P,REP_C,REP_A`) · migration
  (`enum('tipo', [...])`).
- **Regressão que defende:** a regra `in:` do controller é **defesa única**, e isso foi **medido**,
  não suposto: `@@SESSION.sql_mode` na lane é `NO_ENGINE_SUBSTITUTION`, **sem** `STRICT_TRANS_TABLES`.
  Em MySQL não-estrito, um valor fora de um `enum` **não** é recusado — vira **string vazia**. Ou
  seja: afrouxar a regra `in:` não produz erro nenhum; produz REP gravado, aceito, listado e **sem
  classificação legal**. Por isso o caso asserta as duas metades — erro no campo certo **e**
  ausência do registro.
- **Crédito:** a medição do `sql_mode` é da sessão irmã, que a registrou como `[BACKLOG]` acima ao
  avaliar este vetor; aqui ele vira caso com teste.
- **Status: 🧪 sem veredito.**
