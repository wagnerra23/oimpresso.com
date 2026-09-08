---
id: sessao-2026-09-05-como-integrar-ponto-hrm
tipo: plano-de-integracao
date: "2026-09-05"
topic: "Plano de integração Ponto × Essentials (HRM) — desenho existe na ADR 0014, código é zero; 7 ondas"
authors: ["C"]
related_adrs: ["0014-essentials-pontowr2-integracao", "0093-multi-tenant-isolation-tier-0"]
modulos: [Ponto, Essentials]
dono_do_tema: "ADR 0014 (arquivada, sem sucessora) — a saida e superseder, nunca abrir paralela"
decisor: "[W] 2026-09-05"
escopo_excluido: "folha com encargos (INSS/IRRF/FGTS/13o/ferias) — outro agente, memory/sessions/2026-09-05-arte-folha-encargos-br.md"
---

# Como integrar `Modules/Ponto` x `Modules/Essentials` (HRM)

> **As decisoes [W] de hoje sao dadas.** Este doc nao as reabre — mapeia onde cada uma
> encosta no codigo, o que ela quebra, e em que ordem executar.
>
> 1. A presenca web do HRM **cede lugar** ao Ponto. O Ponto e dono unico da jornada.
> 2. Licenca aprovada **bloqueia a marcacao** e sai da conta de ausencia.
> 3. Principio geral: **integrar em vez de manter silos.**

---

## 1. Inventario — o que ja existe

### 1.1 O dono do tema existe e nunca saiu do papel

| O que procurei | Onde achei | Estado |
|---|---|---|
| ADR que desenha Ponto x Essentials | [`0014-essentials-pontowr2-integracao`](../decisions/0014-essentials-pontowr2-integracao.md) | `aceito` · `arquivado` · `authority: reference` · [E] 2026-04-21 |
| Sucessora da 0014 | nenhuma | `git grep "supersedes.*14"` em `memory/decisions/` volta so hits de **UI-0014** (namespace diferente) |
| US no SPEC do Ponto sobre folha/HRM | nenhuma | os 14 `US-PONTO-*` nao cobrem o vinculo; `US-PONTO-005` **declara o gap** de feriado |
| US no SPEC do Essentials sobre jornada | nenhuma | so `R-ESSE-006/007` (permissoes Spatie de attendance) |
| Doc de sessao duplicado hoje | nenhum | `ls memory/sessions/2026-09-05-*` = 1 arquivo, de outro tema (UC-id) |

**A 0014 ja decidiu**: Shift = fonte do horario contratual; Ponto = dono das batidas
(append-only); Payroll alimentado pelo Ponto; `EssentialsHoliday` lido pelo Ponto;
`EssentialsLeave` respeitado como Intercorrencia. Nada disso foi construido.

### 1.2 As quatro provas de que ficou no papel

Confirmei a medicao do [W] e achei **uma quarta**, pior que as tres:

| # | Prova | Medicao |
|---|---|---|
| 1 | A folha nao le o Ponto | `git grep -E "ponto_apuracao\|ponto_banco_horas\|BancoHoras\|Modules.Ponto\|ponto_marcacoes" -- Modules/Essentials` → **0 linhas** |
| 2 | O Ponto nao le feriado | `git grep -i "feriado\|holiday" -- Modules/Ponto` → **3 hits, todos comentario/teste**. Nenhuma leitura de `EssentialsHoliday`. O `isFeriado()` da 0014 nunca existiu |
| 3 | `escala_atual_id` nao aponta pra Shift | 56 linhas no repo. A FK real e `REFERENCES ponto_escalas(id) ON DELETE SET NULL` e a validacao e `exists:ponto_escalas,id` ([`ColaboradorController.php:98`](../../Modules/Ponto/Http/Controllers/ColaboradorController.php)). A 0014 §1 diz "FK para Shift" — **o esquema contradiz a ADR**, nao apenas a ignora |
| 4 | **A unica referencia de codigo Ponto → Essentials e um ponteiro MORTO** | [`Modules/Ponto/Config/config.php:136`](../../Modules/Ponto/Config/config.php) aponta `EssentialsUserShiftHistory::class`. **Essa classe nao existe** — `Modules/Essentials/Entities/` tem `EssentialsUserShift.php` e `Shift.php`, e o [`phpstan-baseline.neon:8764`](../../phpstan-baseline.neon) registra `Class ... EssentialsUserShiftHistory not found` |

A prova 4 importa: nao e so que a integracao esta ausente. O unico fio que existe esta
**arrebentado e silenciado no baseline** ha meses.

### 1.3 A superficie que cede

**11 declaracoes de rota** em [`Modules/Essentials/Routes/web.php`](../../Modules/Essentials/Routes/web.php)
(linhas 69-98), que expandem para **22 endpoints** (`Route::resource` gera 7 cada):

- 8 declaracoes de presenca (14 endpoints) → **cedem**
- 3 declaracoes de turno (9 endpoints) → **ficam** (Shift e fonte, per 0014 §1)

**14 metodos publicos** em `AttendanceController` (745 linhas) = 1 construtor + **13 acoes**.
Cruzamento que fecha: 7 do `resource` + 7 explicitas = 14 rotas mapeadas, mas `attendance.show`
**nao tem metodo** — ja e rota morta hoje. 13 rotas vivas para 13 metodos.

**Assimetria de maturidade — quem cede e o legado:**

| | Ponto | Essentials/attendance |
|---|---|---|
| UI | **Inertia/React** — 10 pastas em `resources/js/Pages/Ponto/` | **Blade** — 14 views em `Resources/views/attendance/` |
| Contratos | 11 RUNBOOKs + SDD + charters + `.casos.md` | nenhum charter de attendance |
| Imutabilidade | triggers MySQL `trg_ponto_marcacoes_no_update` / `_no_delete` | nenhuma |

### 1.4 O que entrou ontem/hoje no HRM, e o que acontece com cada um

| PR | O que fez | Destino |
|---|---|---|
| [#6797](https://github.com/wagnerra23/oimpresso.com/pull/6797) | validacao de licenca (saldo no aprovar, 422) | **fica** — e o gatilho da decisao 2 |
| [#6789](https://github.com/wagnerra23/oimpresso.com/pull/6789) | guarda 422 na exclusao de turno | **fica, com emenda** — ver §3.7 |
| [#6799](https://github.com/wagnerra23/oimpresso.com/pull/6799) | faixas de meta | **fica** — nao toca jornada |
| [#6798](https://github.com/wagnerra23/oimpresso.com/pull/6798) | import de presenca endurecido + fila | **superficie que cede** — ver §2.4 |

---

## 2. O que migra, o que morre, o que fica

### 2.1 Tabela por tabela

| Tabela | Decisao | Destino do dado ja gravado |
|---|---|---|
| `essentials_attendances` | **CONGELA** (read-only) | **NAO migra pra `ponto_marcacoes`.** Razao em §2.2. Fica na base, servida por tela de consulta historica. Nao purgar (§5 2026-07-27: num ERP nao se apaga PII) |
| `essentials_shifts` | **FICA — vira fonte de fato** | intacto. Ganha leitor: `ponto_escalas` |
| `essentials_user_shifts` | **FICA** | intacto |
| `essentials_leaves` | **FICA — dono da licenca** | intacto. Ganha leitor: `ApuracaoService` + guard de criacao |
| `essentials_holidays` | **FICA — dono do feriado** | intacto. Ganha leitor: `ApuracaoService` |
| `ponto_colaborador_config` | fica (bridge `user_id` UNIQUE) | sem coluna nova; a ponte pro Shift nasce em `ponto_escalas` |
| `ponto_escalas` | fica, **ganha `essentials_shift_id`** nullable | intacto |
| `ponto_marcacoes` | fica — **dono unico da batida** | intacto e imutavel |
| `ponto_intercorrencias` | fica — recebe a licenca aprovada | intacto |
| `ponto_apuracao_dia` | fica — **passa a ser a fonte da folha** | intacto |
| `ponto_banco_horas` (saldo + movimentos) | fica | intacto |
| `ponto_reps`, `ponto_importacoes` | ficam | intactos |

### 2.2 Por que `essentials_attendances` NAO migra para `ponto_marcacoes`

Este e o achado que muda o plano, e ele e **legal, nao tecnico**.

O comando `pos:autoClockOutUser` ([`Modules/Essentials/Console/AutoClockOutUser.php`](../../Modules/Essentials/Console/AutoClockOutUser.php)),
agendado `everyThirtyMinutes` em `env=live` pelo `EssentialsServiceProvider:205`, **fabrica
saidas que o trabalhador nao bateu**:

```
$attendance->clock_out_time = $agora->toDateTimeString();
$attendance->clock_out_note = 'Saida automatica (auto clock-out do sistema)';
```

Ou seja: parte das linhas de `essentials_attendances` sao **invencao da maquina**. Importar
isso para `ponto_marcacoes` com `origem` de dispositivo colocaria batida ficticia dentro de
uma base append-only, com hash encadeado, NSR sequencial e valor probatorio perante o MTE.
Isso nao e migracao de dado — e **falsificacao de registro de ponto**.

**Regra:** o historico do HRM se preserva como historico do HRM. Se algum dia [W] quiser o
dado dentro do Ponto, o unico caminho defensavel e `origem=INTEGRACAO`, so as linhas com
`clock_out_note` diferente do texto de auto clock-out, e com a procedencia gravada — decisao
separada, com dupla prova. **Nao entra nas ondas abaixo.**

Efeito colateral: quando `essentials_attendances` congelar, o `pos:autoClockOutUser`
**precisa ser desagendado no mesmo PR**, senao segue escrevendo numa tabela declarada morta.

### 2.3 Rota por rota (as 11 declaracoes)

| # | Rota | Metodo | Decisao | Substituto no Ponto |
|---|---|---|---|---|
| 1 | `POST /hrm/import-attendance` | `importAttendance` | **morre** | `Ponto/Http/Controllers/ImportacaoController` + `ProcessarImportacaoAfdJob` |
| 2 | `resource /hrm/attendance` → `index` | `index` | **morre** | `Pages/Ponto/Espelho/Index.tsx` |
| 3 | ... `create` + `store` | `create`, `store` | **morre** | marcacao manual vira Intercorrencia `ESQUECIMENTO_MARCACAO` (com aprovacao), nunca insercao direta |
| 4 | ... `edit` + `update` | `edit`, `update` | **morre sem substituto** | editar batida e proibido por lei; o equivalente e `MarcacaoService::anular()` (cria nova, `origem=ANULACAO`) |
| 5 | ... `destroy` | `destroy` | **morre sem substituto** | idem — trigger de banco bloqueia DELETE |
| 6 | ... `show` | (nao existe) | **ja morta** | remover a rota fantasma |
| 7 | `POST /clock-in-clock-out` | `clockInClockOut` | **migra o conceito** | `Api/MobileMarcacaoController` → `MobileMarcacaoService` → `MarcacaoService` |
| 8 | `POST /validate-clock-in-clock-out` | `validateClockInClockOut` | **morre** com o import (§2.4) | validacao equivalente no importador do Ponto |
| 9 | `GET /get-attendance-by-shift` | `getAttendanceByShift` | **morre** | `Pages/Ponto/Espelho` filtrado por escala |
| 10 | `GET /get-attendance-by-date` | `getAttendanceByDate` | **morre** | `Pages/Ponto/Dashboard` + Espelho |
| 11 | `GET /get-attendance-row/{user_id}` | `getAttendanceRow` | **morre** | partial de datatable Blade — sem equivalente necessario |
| 12 | `GET /user-attendance-summary` | `getUserAttendanceSummary` | **migra a leitura** | mesma rota, fonte trocada para `ponto_apuracao_dia` |
| 13-15 | `shift/assign-users` (GET+POST) e `resource /shift` | `ShiftController` | **ficam** | — |

As 14 views Blade de `Resources/views/attendance/` saem junto, **exceto** `add_shift_users`,
`avail_shifts`, `current_shift` e `shift_modal`, que servem o `ShiftController` e ficam.

**"Quem esta no balcao agora"** — a capacidade operacional citada na coluna Recomendacao do D1
nao se perde: e uma query de `ponto_marcacoes` do dia corrente (ultima batida por colaborador,
sem par de saida). Muda de dono, nao morre.

### 2.4 O destino do #6798 (import de presenca)

O PR de ontem esta **bem construido** — `businessId` no construtor, fila dedicada nao-gated,
`tries = 1`, relatorio por chave escopada, `failed()` que publica. Ele nao se perde: **vira o
molde** do importador do Ponto.

E ele mesmo escreve o argumento pra mudar de casa:

> *"Uma tentativa so: o insert nao e idempotente (nao ha chave natural em
> `essentials_attendances`), entao retry duplicaria marcacao de jornada."*

`ponto_marcacoes` **tem** chave natural: `UNIQUE (rep_id, nsr)` mais hash encadeado. Do lado do
Ponto o mesmo Job pode ter `tries > 1` sem risco de duplicar jornada. O port ganha robustez que
o original nao podia ter.

---

## 3. Plug-points exatos

### 3.1 A folha lendo a apuracao (decisao 1)

O seam e **estreito**: duas funcoes em [`Modules/Essentials/Utils/EssentialsUtil.php`](../../Modules/Essentials/Utils/EssentialsUtil.php).

| Peca | Arquivo:linha | Acao |
|---|---|---|
| horas trabalhadas | `EssentialsUtil.php:26` `getTotalWorkDuration($unit, $user_id, $business_id, ...)` | trocar a fonte: hoje `SUM(TIMESTAMPDIFF(MINUTE, clock_in_time, clock_out_time))` sobre `EssentialsAttendance`; passa a `SUM(realizada_trabalhada_minutos)` sobre `ponto_apuracao_dia` |
| dias trabalhados | `EssentialsUtil.php:293` `getTotalDaysWorkedForGivenDateOfAnEmployee(...)` | hoje `count()` do `groupBy` da data de `clock_in_time`; passa a `COUNT(*)` de `ponto_apuracao_dia` com `realizada_trabalhada_minutos > 0` |

**Assinatura preservada** — os 5 sites chamadores nao mudam (contados, `PayrollController.php`
linhas **224, 227, 502, 840, 843**). A troca e interna as duas funcoes.

**A ponte de identidade:** as funcoes recebem `user_id`; `ponto_apuracao_dia` chaveia por
`colaborador_config_id`. O JOIN e `ponto_colaborador_config.user_id` (UNIQUE) para `id`.
Colaborador sem linha em `ponto_colaborador_config`, ou com `controla_ponto = false`, **nao tem
apuracao** — a funcao tem de devolver comportamento declarado, nunca cair em zero por acidente.
Zero silencioso aqui e salario a menos.

**Terceiro seam, ja existente:** `EssentialsUtil.php:272` `getTotalLeavesForGivenDateOfAnEmployee`
ja conta licenca em dias para a folha. Ele **fica** — e por isso a apuracao do Ponto nao pode
contar o mesmo dia como falta (decisao 2), senao o dia e descontado duas vezes.

### 3.2 O bloqueio por licenca (decisao 2) — onde o guard entra

**Chokepoint unico, contado:** a producao so cria marcacao por um caminho.
`git grep -E "Marcacao::create|new Marcacao|MarcacaoService"` devolve **121 linhas**; fora de
`Tests/`, o unico `create` e [`MarcacaoService.php:114`](../../Modules/Ponto/Services/MarcacaoService.php),
dentro de `registrarInterno()` (`:62`). `MobileMarcacaoService` e `AfdParserService` **delegam** a
ele por DI (provado por teste em `Wave28MobileMarcacaoTest`).

| Camada | Arquivo:linha | Papel |
|---|---|---|
| **guard primario** | `MarcacaoService.php:62` `registrarInterno()`, antes do `DB::transaction` | recusa ou sinaliza conforme a `origem` (§4.1) |
| **backstop** | `Marcacao.php:37` `boot()` com `static::creating` | pega qualquer escritor futuro que nao passe pelo Service |
| **fonte da licenca** | `EssentialsLeave` com status aprovado e data entre `start_date` e `end_date` | leitura escopada por `business_id` |

O backstop nao e zelo: a lapide §5 2026-08-12 registra que *"guarda acoplada ao COMANDO nao
protege o ARQUIVO"*. O Service protege o caminho de hoje; o `creating` protege a tabela.

### 3.3 A licenca saindo da conta de ausencia (decisao 2, segunda metade)

| Peca | Arquivo:linha | Acao |
|---|---|---|
| ponto de chamada | `ApuracaoService.php:37` `apurar(Colaborador, Carbon)` | inserir `aplicarLicencas()` **antes** de `aplicarIntercorrencias()` (`:343`) |
| efeito | novo `aplicarLicencas(ApuracaoDia, Colaborador, Carbon)` | dia coberto por licenca aprovada: `falta_minutos = 0`, `atraso_minutos = 0`, carga marcada como abonada; **sem** creditar `realizada_trabalhada_minutos` (nao houve trabalho) |
| rastro | `divergencias` (JSON, ja existe) | registrar o `licenca_id` que abonou o dia — auditor precisa ver **por que** o dia nao e falta |

**Nao reusar `IntercorrenciaService::criar()`**: ele forca `ESTADO_RASCUNHO` (`:17`) e o fluxo
seguinte exige `submeter()` e `aprovar()`. Licenca **ja aprovada** no HRM nao volta pra fila de
aprovacao do Ponto. Se a licenca virar Intercorrencia (o desenho da 0014), precisa de metodo
novo — `criarDeLicencaAprovada()` — nascendo em `ESTADO_APROVADA` com `aprovador_id` herdado.

**Alternativa mais barata, e a que recomendo para a Onda 2:** ler `essentials_leaves` direto na
apuracao, sem materializar Intercorrencia. Materializar cria um segundo registro que pode
divergir do original (a licenca pode ser cancelada depois). Leitura direta nao drifta. A
materializacao so valeria se o Ponto precisasse de aprovacao propria — e nao precisa, ja veio
aprovada.

### 3.4 O gatilho da aprovacao — Observer, nao Controller

Escritores de `EssentialsLeave` fora de teste, contados (`git grep` em `Modules/Essentials`,
16 linhas, 3 delas escrita):

| Site | O que faz |
|---|---|
| `EssentialsLeaveController.php:301` | `EssentialsLeave::create($input)` |
| `EssentialsLeaveController.php:415-417` | `changeStatus` — atribui `status` e chama `save()` |
| `EssentialsLeaveController.php:357` | `EssentialsLeave::where(...)->delete()` |

Plugar no `changeStatus` cobriria **um** dos tres. O plug-point certo e um **Observer em
`EssentialsLeave`** (evento `saved`), registrado no `EssentialsServiceProvider`.

**Armadilha nomeada:** a linha `:357` e `delete()` de *query builder* — **nao dispara evento de
model**. Um Observer nao ve a exclusao de uma licenca aprovada. Se a Onda 2 materializar
Intercorrencia, a licenca excluida deixa a Intercorrencia orfa abonando um dia que nao tem mais
licenca. E o segundo argumento a favor da leitura direta (§3.3): sem materializacao, apagar a
licenca corrige a apuracao na proxima reapuracao, sozinho.

### 3.5 O Shift como fonte do horario contratual (0014 §1)

Hoje `ponto_escalas` guarda `horarios_padrao` (JSON), `carga_diaria_minutos`,
`carga_semanal_minutos` e `dias_semana` — **duplicando** o que o `Shift` define — e ainda `tipo`,
`permite_banco_horas` e as tolerancias, que o Shift nao tem. A 0014 ja previa a divisao
("ponto_escalas apenas para configuracoes especificas de ponto"); o que ela nao previa e que a
escala fosse duplicar o horario.

| Peca | Arquivo:linha | Acao |
|---|---|---|
| esquema | migration nova em `Modules/Ponto/Database/Migrations/` | `ponto_escalas.essentials_shift_id` unsigned nullable + FK para `essentials_shifts(id)` com `ON DELETE SET NULL`. **Nome de indice explicito** (limite 64 chars) |
| leitura | `ApuracaoService.php:111` `carregarHorariosPrevistos(ApuracaoDia, Colaborador, Carbon)` | havendo `escala->essentials_shift_id`, o horario previsto vem do `Shift`; `horarios_padrao` vira fallback declarado |
| ponteiro morto | `Modules/Ponto/Config/config.php:136` | trocar `EssentialsUserShiftHistory` (inexistente) pela classe real `EssentialsUserShift` e **remover a entrada do `phpstan-baseline.neon:8764`** |

**Nao mexer** em `ponto_colaborador_config.escala_atual_id` — a FK pra `ponto_escalas` esta certa.
O vinculo com o Shift sobe um nivel: colaborador para escala, escala para shift.

### 3.6 O feriado (fecha gap ja declarado no SPEC)

`US-PONTO-005` diz, com ancora: *"falta calculo de HE 100% em domingo/feriado"*, `verificado@8af585a`.

| Peca | Arquivo:linha | Acao |
|---|---|---|
| HE 100% | `ApuracaoService.php:258` `aplicarRegraHoraExtra()` | consultar `EssentialsHoliday` do business na data; feriado leva multiplicador 100% (Art. 7o XVI CF/88) |
| DSR | `ApuracaoService.php:328` `aplicarRegraDsr()` | o comentario ja diz *"Domingos e feriados nao geram DSR sobre si mesmos"* — hoje so o domingo e checado |

### 3.7 A guarda de exclusao de turno (#6789) precisa de emenda

[`ShiftController::destroy`](../../Modules/Essentials/Http/Controllers/ShiftController.php) (`:266`)
conta duas pernas: `EssentialsUserShift` e `EssentialsAttendance` (`:291`). Quando
`essentials_attendances` congelar, a segunda perna vira **contagem de historico**: o turno passa a
poder ser apagado mesmo tendo apuracao viva do Ponto apontando pra ele.

**Acao:** somar uma terceira perna — `ponto_escalas.essentials_shift_id` — no mesmo PR da Onda 4.
A guarda e fail-closed por desenho (o proprio docblock diz *"contar a mais e bloquear, nunca a
menos e liberar"*); manter assim.

---

## 4. O bloqueio de licenca e a pegadinha de lei

`ponto_marcacoes` **nao tem `updated_at`** e tem dois triggers no banco (criados na
[migration `..._000004_...`](../../Modules/Ponto/Database/Migrations/2026_04_18_000004_create_ponto_marcacoes_table.php),
presentes no `mysql-schema.sql:7420` e `:7441`):

```
trg_ponto_marcacoes_no_update  -> SIGNAL SQLSTATE 45000
trg_ponto_marcacoes_no_delete  -> SIGNAL SQLSTATE 45000
```

Nao ha caminho de aplicacao que apague — a imutabilidade e do banco, nao do codigo.
**"Bloquear" so pode significar impedir a criacao**, e o guard vai em
`MarcacaoService::registrarInterno()` (§3.2).

### 4.1 Mas nem toda criacao pode ser bloqueada — o guard e por `origem`

`ponto_marcacoes.origem` e um enum de 6 valores, e eles tem naturezas juridicas diferentes:

| `origem` | Natureza | Comportamento com licenca aprovada |
|---|---|---|
| `REP_P`, `AFD`, `AFDT` | **leitura de equipamento** — o trabalhador de fato bateu | **ACEITA e sinaliza divergencia.** Recusar apaga do sistema um registro que existe no REP e no arquivo fiscal; a auditoria do MTE compara os dois e acha o buraco |
| `MANUAL` | digitada por humano na tela | **RECUSA** com 422 e mensagem citando a licenca |
| `INTEGRACAO` | trazida de outro sistema | **RECUSA** |
| `ANULACAO` | estorno de marcacao anterior | **nunca bloqueia** — anular e sempre permitido |

O motivo de `REP_P`/`AFD` nao bloquearem: um trabalhador em licenca que aparece e bate o ponto e
um **fato**, e um fato relevante (trabalho em periodo de afastamento tem consequencia
trabalhista). O sistema tem de registrar e gritar, nao esconder.

A sinalizacao vai em `ponto_apuracao_dia.divergencias` (JSON, ja existe) e leva o `estado` do dia
para `DIVERGENCIA` — o mesmo caminho que o RH ja usa no Espelho.

> **Este e o unico ponto do plano que peco a [W] ratificar**, porque e regra de negocio com
> consequencia trabalhista, nao escolha de tecnica. O resto do desenho eu decido e executo.

### 4.2 A licenca aprovada DEPOIS da marcacao existir

O guard so alcanca o futuro. Licenca retroativa aprovada hoje para a semana passada encontra
marcacoes ja gravadas — e imutaveis. **Nao se apaga.** Quem resolve e a apuracao: o
`aplicarLicencas()` (§3.3) roda na reapuracao e abona o dia, com o `licenca_id` no rastro.

Por isso o Observer da §3.4 tem de despachar `ReapurarDiaJob` para cada dia do intervalo da
licenca. Esse Job **ja recebe `$businessId` no construtor** (`ReapurarDiaJob.php:40`) — o padrao
ADR 0093 ja esta certo ali, e a chamada nova so precisa respeita-lo.

---

## 5. Ondas executaveis

1 PR = 1 intent, ate 300 linhas. A ordem nao e sugestao: a Onda 5 mexe em dinheiro e depende
das anteriores estarem certas.

| # | Onda | O que destrava | O que quebra se sair sozinha |
|---|---|---|---|
| **0** | **ADR sucessora da 0014** (`supersedes: [14]`, `authority: canonical`) + flip do frontmatter da 0014 (label `adr-metadata-normalization`) | da cobertura de governanca a todas as outras; registra que Shift e fonte, Ponto e dono da jornada, folha le apuracao | nada quebra. Sem ela, toda onda abaixo e mudanca sem dono declarado, e a 0014 segue afirmando um desenho que o codigo contradiz |
| **1** | **Ponto le `EssentialsHoliday`** (§3.6) | fecha o gap declarado em `US-PONTO-005`; HE 100% e DSR corretos | nada. E a unica onda sem dependencia — pode ir hoje. **Muda valor de HE**, entao a regra mestre Tier 0 se aplica |
| **2** | **Licenca sai da conta de ausencia** (§3.3 + Observer §3.4 + reapuracao §4.2) | decisao 2, metade "sai da ausencia"; para de contar falta em dia de licenca | se sair depois da Onda 5, a folha desconta falta em dia de licenca **e** conta a licenca em `getTotalLeavesForGivenDate` — desconto em dobro |
| **3** | **Guard de criacao por `origem`** (§3.2 + §4.1) | decisao 2, metade "bloqueia a marcacao" | se sair antes da Onda 2, bloqueia a batida mas a apuracao segue contando falta — o pior dos dois mundos |
| **4** | **`ponto_escalas.essentials_shift_id`** + leitura em `carregarHorariosPrevistos` + conserto do ponteiro morto + 3a perna da guarda #6789 (§3.5, §3.7) | 0014 §1 sai do papel; horario contratual passa a ter dono unico | se sair depois da Onda 5, a folha le apuracao calculada contra horario previsto duplicado — divergencia entre espelho e folha |
| **5** | **Folha le `ponto_apuracao_dia`** (§3.1) — **TIER 0 VALOR** | decisao 1 fecha: a hora paga passa a ser a hora do espelho fiscal | se sair antes das Ondas 1-4, paga HE sem feriado, desconta falta em licenca e usa horario previsto errado. **Nao pode ser a primeira** |
| **6** | **Portar o import (#6798) pro Ponto** (§2.4) | remove a razao de existir do `import-attendance` do HRM; ganha idempotencia via `(rep_id, nsr)` | se sair depois da Onda 7, o RH fica sem caminho de importacao de planilha no intervalo |
| **7** | **Congelar a superficie** (§2.3): remover as 8 declaracoes de rota de presenca, o `AttendanceController`, as 10 views Blade, **e desagendar `pos:autoClockOutUser`** | a presenca web do HRM cede lugar, de fato | se sair antes da 5 e da 6, o negocio fica sem tela de jornada e sem import, e a folha ainda lendo tabela que ninguem alimenta. **E a ultima, sempre** |

**Ondas 1 e 5 mexem em VALOR** (hora que vira salario). As duas exigem, antes do merge, dupla
prova por caminhos independentes e tabela antes/depois aprovada — regra mestre Tier 0 de
`proibicoes.md`. Na 5, o antes/depois se faz por colaborador num mes ja fechado:
`getTotalWorkDuration` velho contra novo, lado a lado, com a diferenca explicada linha a linha.

**A Onda 7 tambem atualiza** `memory/requisitos/Essentials/SUPERFICIE.md` e `BRIEFING.md` no mesmo
PR — a SUPERFICIE lista nominalmente o `AttendanceController`, o `AttendanceImportService`, as
migrations e as views.

---

## 6. Pegadinhas aplicaveis

| # | Pegadinha | Onde morde neste plano |
|---|---|---|
| 1 | **Multi-tenant Tier 0** ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)) | toda query nova (licenca, feriado, apuracao) escopada por `business_id`. O `ReapurarDiaJob` ja recebe `$businessId` no construtor (`:40`) — manter. O Observer roda em request, mas o Job que ele despacha **nao pode ler `session()`** |
| 2 | **`withoutGlobalScope` em CLI** | `ScopeByBusiness` e **no-op sem `auth()`**. O `AutoClockOutUser` documenta a armadilha e resolve com loop explicito por business. Command novo do Ponto copia esse padrao, com o comentario `// SUPERADMIN: <razao>` |
| 3 | **Append-only por lei** (Portaria MTP 671/2021 Art. 85, CLT Art. 74 §3) | triggers no banco bloqueiam UPDATE e DELETE em `ponto_marcacoes`. Guard so na criacao. Regressao aqui e **risco juridico, nao bug** |
| 4 | **Migrar dado fabricado** | `pos:autoClockOutUser` inventa `clock_out_time`. Ver §2.2 — nao migrar |
| 5 | **FSM canonico NAO se aplica** ([ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) | medido: nenhuma ocorrencia de `ExecuteStageActionService`, `GuardsFsmTransitions`, `current_stage_id` ou `FsmAuthorizationFlag` em `Modules/Ponto` nem em `Modules/Essentials` (**0 linhas**). A 0143 cobre Sells e Repair. `ponto_intercorrencias.estado` e `ponto_apuracao_dia.estado` sao maquinas proprias, com transicoes em `IntercorrenciaService`. **Nao converter nesta integracao** — seria outra intent |
| 6 | **`IntercorrenciaService::criar()` forca RASCUNHO** (`:17`) | licenca ja aprovada nao volta pra fila de aprovacao. Ver §3.3 |
| 7 | **`delete()` de query builder nao dispara evento de model** | `EssentialsLeaveController:357`. Ver §3.4 |
| 8 | **Regra mestre VALOR** (`proibicoes.md`) | Ondas 1 e 5. Dupla prova por caminhos independentes + antes/depois aprovado antes de aplicar |
| 9 | **Teste no tenant 98** ([ADR 0358](../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) | **biz=4 (ROTA LIVRE) proibido sem excecao** em teste, fixture, smoke ou exemplo. Cross-tenant sempre com o par |
| 10 | **Testes so no CT 100** | `php artisan test`, `pest` e `phpstan` sao bloqueados local e no Hostinger pelo hook `block-test-fora-ct100.mjs` |
| 11 | **Identificadores MySQL ate 64 chars** | `ponto_escalas.essentials_shift_id` (Onda 4) — passar nome explicito na FK e no indice |
| 12 | **Nao purgar** (§5 2026-07-27) | `essentials_attendances` congela; nao entra em rotina de expurgo |
| 13 | **MWART** ([ADR 0104](../decisions/0104-processo-mwart-canonico-unico-caminho.md)) | tela nova em `Pages/Ponto/**` exige RUNBOOK **antes** do Edit — o hook `block-mwart-violation.mjs` bloqueia em runtime e **nao tem override** |
| 14 | **Non-Goal do SPEC do Ponto** | `memory/requisitos/Ponto/SPEC.md:33` diz *"Folha de pagamento ... out of scope"*. A Onda 5 encosta nessa linha. Emendar no **mesmo PR**, distinguindo *alimentar a folha* (agora escopo) de *calcular encargos* (segue fora) — senao fica canon contradizendo canon |
| 15 | **`format_date` +3h** ([ADR 0066](../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)) | preservado para clientes legacy. Em jornada, um deslocamento de 3h falsifica entrada e saida no espelho. Comparacao de `momento` (datetime) com coluna `time` de parede tem de ser timezone-aware — o `AutoClockOutUser` ja mostra o padrao (`date_default_timezone_set($business->time_zone)`) |
| 16 | **LC-19 / lapide §5 2026-08-03** | a ADR 0014 e a dona. **Superseder, nunca abrir paralela** |
| 17 | **Rodar `whats-active`** antes de pegar onda | a colisao de 04/09 saiu de ninguem ter rodado |

### Observacao sem pegadinha documentada

Nao ha pegadinha catalogada sobre **congelar tabela viva mantendo o leitor**. O risco concreto
aqui: entre a Onda 5 e a Onda 7, `essentials_attendances` fica sem escritor (auto clock-out
desagendado) mas as telas Blade ainda a leem, e vao mostrar dado que para no tempo sem dizer que
parou. Mitigacao barata: banner declarado na `index.blade.php` na Onda 5, com a data do
congelamento — nao deixar a tela mentir por omissao.

---

## 7. Checklist pre-codigo

### Antes de qualquer Edit

- [ ] Rodar `whats-active` — duas sessoes ja estao neste pedido hoje
- [ ] Ler `memory/requisitos/Ponto/SPEC.md` e `memory/requisitos/Essentials/SPEC.md` (medir codigo nao autoriza plano novo)
- [ ] Ler o RUNBOOK da tela do Ponto que a onda tocar (11 existem em `memory/requisitos/Ponto/`)
- [ ] Confirmar com [W] o §4.1 (guard por `origem`) — unica pergunta em aberto
- [ ] ADR sucessora da 0014 aberta **antes** da Onda 1

### Governanca

- [ ] ADR nova com `supersedes: [14]`, `authority: canonical`, `lifecycle: ativo` — PR + aprovacao [W]
- [ ] Flip do frontmatter da 0014 para superseded com label **`adr-metadata-normalization`** (sem ela o gate Append-only reprova)
- [ ] `memory/requisitos/Ponto/SPEC.md` — emendar o Non-Goal da linha 33 na Onda 5
- [ ] `memory/requisitos/Essentials/SUPERFICIE.md` e `BRIEFING.md` — atualizar na Onda 7
- [ ] `memory/requisitos/Ponto/BRIEFING.md` — atualizar ao fim de cada onda que mude capacidade
- [ ] Feature flag: **nao**. Habilitar ou desabilitar modulo por business e via UI canonica, nunca hardcode por `business_id`

### Migrations

- [ ] Onda 4: `ponto_escalas.essentials_shift_id` nullable + FK com **nome explicito**, `down()` reversivel, idempotente em re-run
- [ ] Nenhuma migration destrutiva neste plano. Se alguma surgir, **PR separado**: o deploy roda `migrate --force` automatico, entao o merge **e** o ato de aplicar em producao

### Teste (CT 100, tenant 98 — biz=4 proibido)

- [ ] Guard de licenca: recusa `MANUAL` e `INTEGRACAO`, **aceita e sinaliza** `REP_P` e `AFD`, nunca bloqueia `ANULACAO`
- [ ] Append-only sobrevive: tentativa de UPDATE ou DELETE em `ponto_marcacoes` estoura SQLSTATE 45000
- [ ] Licenca retroativa: aprovar depois da marcacao existir — dia abona na reapuracao, marcacao **permanece**
- [ ] Licenca excluida por query builder — dia volta a contar como falta na reapuracao
- [ ] Cross-tenant: licenca do business A nao bloqueia marcacao do business B
- [ ] Folha: colaborador sem `ponto_colaborador_config`, ou com `controla_ponto = false` — comportamento declarado, nao zero acidental
- [ ] Feriado: HE 100% em feriado cadastrado; DSR nao gerado sobre o proprio feriado
- [ ] Job: `ReapurarDiaJob` despachado pelo Observer carrega `businessId` e nao le `session()`

### Smoke pos-deploy

- [ ] tenant 98 (CT 100): ciclo completo aprovar licenca, reapurar, espelho mostra dia abonado com o `licenca_id` no rastro
- [ ] biz=1 (smoke manual em prod): `getTotalWorkDuration` antes contra depois num mes fechado, tabela lado a lado
- [ ] Onda 7 mexe em UI, entao screenshot obrigatorio antes de declarar pronto

### Estimativa (IA-pair, [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md))

| Onda | Estimativa | Observacao |
|---|---|---|
| 0 ADR | 1h | governanca, sem codigo |
| 1 feriado | 2h | fecha gap ja declarado |
| 2 licenca na apuracao | 3h | Observer + reapuracao |
| 3 guard por origem | 2h | chokepoint unico |
| 4 Shift como fonte | 3h | migration + leitura + ponteiro morto + guarda #6789 |
| 5 folha le apuracao | 4h + **dupla prova** | Tier 0 valor — a prova costuma custar mais que o codigo |
| 6 port do import | 3h | o #6798 e o molde |
| 7 congelar superficie | 2h | + screenshot + SUPERFICIE e BRIEFING |
| **Total** | **~20h** | mais o relogio do mundo real da validacao de valor da Onda 5 |

---

## 8. Vinculos naturais fora deste par (so nomear, nao planejar)

Cumprindo a decisao 3 sem extrapolar escopo:

| Par | Vinculo natural | Sinal hoje |
|---|---|---|
| Ponto x **Financeiro** | horas apuradas viram provisao de folha e custo de mao de obra por periodo | `MarcacaoService` ja foi declarado contrato reusavel para o Financeiro (`Wave23MarcacaoServiceReuseContractTest`) — o teste existe, o consumo nao |
| Ponto x **Repair** e **OficinaAuto** | apontamento de hora do tecnico na OS cruzando com a jornada apurada (hora trabalhada contra hora faturada) | nenhum vinculo hoje |
| Ponto x **Forja** | mesma ideia no projeto: hora lancada na tarefa contra jornada | nenhum vinculo hoje |
| HRM x **Vestuario** (ROTA LIVRE) | escala de balcao contra pico de venda por faixa de horario | nenhum vinculo hoje |

Nenhum destes tem sinal de cliente ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) nem
foi pedido hoje. Ficam **nomeados, nao planejados**.

---

## Anexo — comandos que reproduzem as medicoes

```
git grep -E "ponto_apuracao|ponto_banco_horas|BancoHoras|ponto_marcacoes" -- Modules/Essentials
git grep -i "feriado|holiday" -- Modules/Ponto
git grep -n "escala_atual_id" -- . | wc -l
git grep -n "EssentialsUserShiftHistory" -- .
git grep -E "ExecuteStageActionService|GuardsFsmTransitions|current_stage_id" -- Modules/Ponto Modules/Essentials
git grep -nE "Marcacao::create|new Marcacao|MarcacaoService" -- "*.php" | wc -l
grep -n "public function" Modules/Essentials/Http/Controllers/AttendanceController.php
sed -n "69,98p" Modules/Essentials/Routes/web.php
awk "/CREATE TABLE .ponto_marcacoes./,/ENGINE=/" database/schema/mysql-schema.sql
```

Medicoes desta sessao valem para o worktree em **2026-09-05**. Numero que incomodar: **re-rode o
comando, nao edite o numero.**
