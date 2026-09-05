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
- **Regressão que defende:** aqui a defesa é **dupla** — o filtro `where('business_id', …)` do
  controller (que nesta consulta funciona, porque não há `orWhere` para neutralizá-lo) **e** o global
  scope do model. É o oposto do que acontece na busca de colaboradores, onde só o scope segura
  (`UC-COLIDX-01`). O caso existe para que a remoção de **qualquer uma** das duas ainda deixe a
  outra visível — e para que remover as duas não passe em silêncio.
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
