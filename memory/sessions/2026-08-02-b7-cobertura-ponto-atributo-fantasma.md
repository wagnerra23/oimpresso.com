---
date: "2026-08-02"
hour: "23:00 BRT"
duration: "3h"
topic: "B7-cobertura no módulo Ponto: 4 das 14 telas ganharam casos.md, e a varredura que o SDD §10 Onda 1 pediu achou a 3ª e a 4ª instância do padrão 'atributo fantasma' — mais um 500 na edição de escala e a prova de que os 3 ContratoTest do trio SDD nunca rodaram verdes."
authors: [C]
prs: [5191]
outcomes:
  - "4 telas do Ponto fecharam o trio (ratchet 145 → 141): BancoHoras/Index (UC-BHIDX-01..04), Escalas/Form (UC-ESCF-01..03), Relatorios/Index (UC-RELIDX-01..02), Importacoes/Index (UC-IMPIDX-01..03). Todos em Pest it() com o UC no título — a forma que o manifesto G-7 enxerga."
  - "ACHADO 1 (o que pagou a sessão) — a varredura que o SDD §10 Onda 1 pediu e declarou não ter feito: EscalaController@edit lê `entrada`/`saida`/`almoco_inicio`/`almoco_fim`; as colunas são `hora_*` e não há accessor. A tela de edição de escala mostra TODOS os horários vazios, sempre. 3ª instância do padrão D-1/D-8, agora em 3 de 8 famílias de tela."
  - "ACHADO 2 — EscalaController@update recebe Illuminate\\Http\\Request e chama $request->validated(), método que só existe em FormRequest (medido: 0 em Http/Request.php, 0 macros no projeto). Salvar a edição de uma escala lança BadMethodCallException. O padrão certo está no mesmo módulo, ao lado: IntercorrenciaController usa IntercorrenciaRequest."
  - "ACHADO 3 — `erro_mensagem` também é fantasma na importação: o Show.tsx faz `{i.erro_mensagem && <Alert>}`, logo o alerta de erro NUNCA renderiza. Importação que falhou não mostra o motivo — consequência mais séria que o 'exibe 0' do D-8, e o SDD §5.3 F7 lista o campo sem notar."
  - "ACHADO 4 — o ledger de banco de horas NÃO é append-only na prática: BancoHorasMovimento sobrescreve update() e delete(), mas não save(), e Model::save() não passa por update(). Sem trigger DB (SDD §9 D-6), `$mov->minutos = X; $mov->save()` grava. A irmã Marcacao sobrevive porque TEM trigger. Tier 0 + [V0] — registrado, não corrigido."
  - "ACHADO 5 — os 3 *ContratoTest do trio SDD (nascidos 07-27) NUNCA rodaram verdes: 7 dos seus casos morrem com FK 1452 porque biz=99 não existe na lane e ninguém criou o stub. A lane não rodava há ≥100 runs (nenhum PR tocava Ponto), então o defeito ficou invisível. Somado ao UC-em-docblock, as 6 telas já cobertas do Ponto tinham DOIS motivos independentes para nunca virar ✅."
related_adrs: ["0264-governanca-executavel-trio-dominio-e2e", "0351-sdd-from-source", "0093-multi-tenant-isolation-tier-0", "0101-tests-business-id-1-nunca-cliente"]
---

# B7-cobertura no Ponto — as 4 telas e os 5 achados

Continuação do **B7** ([sessão anterior](2026-08-02-b7-cobertura-conciliacao-quarentena-ledger.md),
que fechou a 1ª tela do Financeiro). O alvo aqui foi o **Ponto**, escolhido porque o
[SDD](../requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) §6 já tinha os `CU-PONTO-01..14`
escritos — os `casos.md` derivam de lá, nunca do `.tsx`.

## O que foi entregue

| Tela | UCs | Âncora | Nota |
|---|---|---|---|
| `BancoHoras/Index` | `UC-BHIDX-01..04` | CU-PONTO-08/12 | o UC-03 prova que o **agregado** não vaza |
| `Escalas/Form` | `UC-ESCF-01..03` | achado medido + CU-PONTO-12 | 2 failing-first |
| `Relatorios/Index` | `UC-RELIDX-01..02` | CU-PONTO-14 + F8 | contrato é a honestidade da tela |
| `Importacoes/Index` | `UC-IMPIDX-01..03` | CU-PONTO-11/12 | 1 failing-first (D-8 na lista) |

Ratchet do `casos-gate`: **145 → 141**. Todos os testes em **Pest `it()` com o UC no título**.

## A varredura que o SDD pediu — e o que ela achou

O SDD §10 Onda 1 manda *"varrer as 4 famílias de tela não auditadas (Dashboard, Colaboradores,
Escalas, Configuracoes) atrás de outros atributos fantasma"* e declara honestamente *"não fiz
nesta corrida"*. Esta sessão fez.

**Método** (registrado para reuso): candidato = `$x->attr` com **underscore** — atributo Eloquent
é snake_case, método é camelCase — menos as colunas das migrations, menos os nomes declarados nas
Entities, menos os `rel_count` de `withCount` do próprio arquivo.

**Controle positivo:** o detector reencontrou sozinho o **D-1** (`tem_divergencia`) e o **D-8**
(`linhas_criadas`) — que é o que valida o método, não a minha leitura dele.

**Resultado por família:**

| Família | Veredito |
|---|---|
| `Dashboard` | limpa |
| `Colaboradores` | limpa |
| `Configuracoes` | limpa |
| **`Escalas`** | **4 atributos fantasma** (achado 1) |

E dois cuidados que a varredura exigiu:

- `turnos_count` saiu como candidato e foi **refutado** na leitura — vem de `withCount('turnos')`,
  é legítimo. **Candidato ≠ achado** ([§5 2026-07-15](../proibicoes.md)).
- `entrada` e `saida` **não têm underscore** e escaparam do filtro: o detector achou 2 dos 4, e os
  outros 2 só apareceram ao ler o bloco inteiro. O filtro detecta a **classe**, não a instância —
  quem confia só no output do detector perde metade.

## Os achados

### 1 · 🔴 A edição de escala mostra todos os horários vazios, sempre

`EscalaController@edit` monta o turno lendo 4 atributos que não existem:

| o controller lê | coluna real |
|---|---|
| `$t->entrada` | `hora_entrada` |
| `$t->saida` | `hora_saida` |
| `$t->almoco_inicio` | `hora_almoco_inicio` |
| `$t->almoco_fim` | `hora_almoco_fim` |

O `$fillable` de `EscalaTurno` lista as 4 **com** o prefixo; accessor/`appends` na entity = **0
ocorrências**. Os 4 resolvem `null` e o `.tsx` renderiza `{t.entrada ?? '—'}` — o `?? '—'` faz
aqui o mesmo papel do `?? 0` do D-8.

**É a 3ª instância do mesmo padrão** que o SDD nomeou: *o controller lê um atributo que o modelo
não tem, e a linguagem esconde*. Agora em **3 de 8 famílias** de tela do módulo.

### 2 · 🔴 Salvar a edição de uma escala lança exceção

`EscalaController@update` recebe **`Illuminate\Http\Request`** (import na linha 7) e chama
`$request->validated()`. Medido: `grep -c "function validated"` em `Illuminate/Http/Request.php` →
**0**; ele mora em `Foundation/Http/FormRequest.php:365`. `Request` usa `Macroable`, então varri
macro nas duas formas de aspas em `app/`, `Modules/`, `bootstrap/`, `config/` → **exit 1, zero**.

**Contraste no mesmo módulo:** `IntercorrenciaController@store/@update` recebem
`IntercorrenciaRequest` (um `FormRequest`) e chamam `validated()` legitimamente. O padrão certo
está ao lado do errado.

O `store()` (criar) funciona, então a tela **parece** boa — o erro só aparece na edição, o caminho
menos exercitado.

### 3 · 🔴 O alerta de erro da importação nunca aparece

Além de `linhas_criadas`/`linhas_ignoradas`, o `ImportacaoController` lê **`erro_mensagem`** — que
também não é coluna nem está no `$fillable` (as reais são `log` e `erros_amostra`). O
`Importacoes/Show.tsx:82` faz `{i.erro_mensagem && <Alert>…}`: como é sempre `null`, **o alerta
nunca renderiza**. Uma importação que falhou não mostra o motivo.

É pior que o "exibe 0" do D-8, e o SDD §5.3 F7 lista `erro_mensagem` entre os campos acompanhados
**sem notar** que é fantasma. Registrado no `[BACKLOG]` do `Importacoes/Index.casos.md` como
`UC-IMPSHOW-05` futuro — não abri caso na tela alheia (varrer em lote acorda gate diff-aware sobre
dívida de outrem, [§5 2026-07-12](../proibicoes.md)).

### 4 · 🔴 Tier 0 — o ledger de banco de horas não é append-only na prática

`BancoHorasMovimento` sobrescreve `update()` e `delete()` — mas **não `save()`**. E
`Model::save()` chama `performUpdate()` internamente: **não passa** pelo método público `update()`.

| | `Marcacao` | `BancoHorasMovimento` |
|---|---|---|
| override `update()`/`delete()` | ✅ | ✅ |
| override `save()` | ❌ | ❌ |
| **trigger MySQL** | ✅ `trg_ponto_marcacoes_no_update/delete` | ❌ **nenhum** |

A `Marcacao` sobrevive porque o **trigger** pega qualquer UPDATE. O ledger de banco de horas
**não tem trigger** (o SDD §9 D-6 registra a lacuna) — então `$mov->minutos = 999; $mov->save()`
**grava**. A formulação do SDD (*"SQL cru ainda edita o ledger"*) subestimava: não é só SQL cru, é
**Eloquent comum**.

Quem provou foi o `UC-BHSHOW-01`, escrito na sessão de 07-27 — ele reprovou com
*"Failed asserting that true is false"*, e ninguém tinha visto porque a lane não rodava.

Saldo de banco de horas vira dinheiro na rescisão (CLT Art. 59 §5º), então isto é **Tier 0 +
`[V0]`**. **Não corrigi** — é decisão [W], e o SDD estabelece o padrão (*"registra o achado e o
teste; não corrige o código"*). O fix mediu-se **seguro**: todo código de produção usa
`BancoHorasMovimento::create(...)`; varredura contada não achou **nenhum** `save()` em movimento
existente.

### 5 · 🔴 Os 3 `*ContratoTest` do trio SDD nunca rodaram verdes

O JUnit da 1ª corrida (run 30778424885) mostrou **13 fails**. Decompondo:

| causa | n |
|---|---:|
| **FK `1452` — biz=99 não existe e ninguém criou o stub** | **7** |
| failing-first por desenho (D-1, D-8) | 2 |
| append-only furado (achado 4) | 1 |
| FK outra (`usuario_criador_id`) | 1 |
| **meus** (mesmo defeito do biz=99) | 2 |

O SDD e o handoff de 07-27 diziam que **2** UCs eram failing-first por desenho. São 2 — mas há
**mais 7** que nunca chegaram a exercer nada: morrem no `INSERT`. O
`Wave27CrossTenantEscalaTest` — o único teste do módulo que já rodava verde — **documenta a
armadilha no próprio comentário** (*"o clone-de-prod do CT100 … NÃO tem biz=99 → o FK rejeita"*) e
cria o stub. Os 3 ContratoTest não copiaram essa parte.

Ficou invisível porque **a lane não rodava há ≥100 runs** (medido: nenhum PR tocou o Ponto desde
07-31; a lane é always-run com `paths-filter` interno, então "success" era skip-as-pass).

**Consequência composta:** as 6 telas do Ponto já cobertas tinham **dois** motivos independentes
para nunca virar ✅ — o UC em docblock (teto zero) **e** o teste morrendo no setup.

## Erros meus, registrados e não apagados

1. **Regex inline no shell devolveu ruído e eu quase reportei** — a 1ª varredura mandou `$1` pelo
   heredoc do bash, veio `undefined` em tudo. Refeita com script em arquivo.
2. **Evidência negativa inválida** — afirmei que `validated()` não existia rodando `grep` num
   worktree **sem `vendor/`**: o `||` disparou o "não existe" porque o comando **falhou**, não
   porque não achou. É a armadilha do `crontab -l` ([§5 2026-07-17](../proibicoes.md)). Refeito
   onde `vendor/` existe, com `grep -c` e exit code visível.
3. **`exit=0` do `head`, não do `grep`** — num pipeline, o `$?` é do último comando. Refeito sem
   pipe, redirecionando para arquivo.
4. **Meus UC-BHIDX-02/03 caíram no mesmo buraco que eu estava documentando** (biz=99 sem stub) —
   corrigido no mesmo PR.
5. **Dois defeitos de fixture pegos antes da lane**, nenhum visível ao `php -l`:
   `Importacao::ESTADO_CONCLUIDO` (a constante é `ESTADO_CONCLUIDA`) e `usuario_id` omitido sendo
   NOT NULL. Achados conferindo as colunas contra a **migration**, não relendo o teste.
6. **Escrevi 3 UCs invisíveis ao gate — a mesma classe que este PR documenta.** Batendo o report
   contra o que eu tinha escrito, a conta não fechava: `395 → 404` é **+9**, mas eu escrevera
   **12** UCs. Causa medida: o regex canônico
   ([`scripts/lib/uc-regex.mjs`](../../scripts/lib/uc-regex.mjs)) aceita prefixo de **até 6**
   caracteres (`[A-Z][A-Z0-9]{0,5}`), e `UC-ESCFORM-` tem **7** — `ucScanRe()` devolvia `[]` para
   os três. Eles não existiam para o G-2/G-5/G-7 **nem** para o manifesto: exatamente o "teto
   zero" que a sessão inteira está documentando, cometido por mim dentro do PR sobre isso.
   Renomeados para `UC-ESCF-` (4 chars); a conta passou a fechar em **407 = 395 + 12**.
   **A lição de método é o gesto, não o regex:** o defeito só apareceu porque conferi um número
   derivado (`ucs_declared`) contra o que eu sabia ter escrito. Sem essa subtração, os 3 UCs
   passariam verdes e vazios — e o `casos:check` **não teria reclamado**, porque um UC que o
   regex não enxerga simplesmente não entra na conta de nada.

## As 10 telas que faltam — e por que não varri em lote

| Situação | Telas | Caminho |
|---|---|---|
| **CU no SDD, prontas para o mesmo tratamento** | `Importacoes/Create`, `Intercorrencias/{Index,Create}` | CU-PONTO-05/06/10 — próxima leva |
| **Sem CU e sem achado** (varredura deu limpo) | `Colaboradores/{Index,Edit}`, `Configuracoes/{Index,Reps}`, `Dashboard/Index`, `Escalas/Index`, `Welcome` | ver abaixo |

Para as 7 sem CU, a única âncora honesta hoje é o **`CU-PONTO-12`** (*"nenhuma tela do Ponto expõe
dado de outro empregador"*), que é transversal por construção e vale para qualquer tela. Isso dá
**um** UC `[T0]` por tela — cobertura real, mas fina. Derivar mais que isso exigiria inventar
(proibido) ou ler o `.tsx` (tautológico, [§5 2026-06-05](../proibicoes.md)).

**Decisão de escopo:** parei nas 4 em vez de varrer as 14. Cada tela é um PR com gate próprio, e
a lane do Ponto **acabou de ganhar 4 arquivos novos** cujo veredito ainda não voltou — empilhar
mais 10 antes de ler o resultado seria repetir o erro que esta sessão achou nos ContratoTest de
07-27: escrever teste que ninguém viu rodar.

## Método que se pagou

- **O oráculo é o artifact, não o check.** A lane apareceu `FAILURE` e a leitura do resumo teria
  me feito culpar o produto. Baixar o JUnit separou 2 defeitos meus de 11 pré-existentes, e
  classificou os 11 por causa.
- **Parser próprio precisa de controle.** Meu 1º parse do JUnit **deslocava** nome × mensagem
  (não tratava `<testcase … />` self-closing) e me deu uma lista de falhas errada. O 2º parse,
  respeitando o self-closing, mudou o veredito: `UC-BHIDX-01` tinha **passado**.
- **Conferir a fixture contra a migration, não contra o teste.** Os 2 defeitos que peguei antes
  da lane (constante e NOT NULL) só aparecem comparando com a fonte do schema.
