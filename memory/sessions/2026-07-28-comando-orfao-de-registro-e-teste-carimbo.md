---
date: "2026-07-28"
time: "14:41 BRT"
topic: "Comando órfão de REGISTRO (não de schedule) + o teste-carimbo que o escondeu por 2,4 meses"
prs: [4968]
decided_by: [W]
---

# TL;DR

Um briefing pedia decidir entre **(a) ligar** ou **(b) aposentar** o `project-mgmt:health`, descrito como "máquina órfã: existe, tem checks reais, zero invocadores". A medição **refutou a premissa em duas frentes**: a causa é mais grave (o comando nunca foi **registrado** no Artisan — não roda nem à mão) e a justificativa é menor (o gap que motivava ligá-lo é **zero**, não uma população). O teste que deveria ter pego isso provava registro com `app(Class::class)` — presence-gate, **3ª instância LC-11 em produção em 3 dias**. Fix mínimo mergeado ([#4968](https://github.com/wagnerra23/oimpresso.com/pull/4968)), smoke real verde em prod. Agendar e podar ficaram como decisão [W], com o sinal agora honesto.

# O que aconteceu

## 1. A premissa do briefing caiu na primeira medição

O briefing dizia "órfão de schedule — falta entrar no `Kernel.php` + `AUTOMATIONS.md`". Rodei o comando no CT 100 e ele respondeu:

```
There are no commands defined in the "project-mgmt" namespace
```

`ProjectMgmtServiceProvider::register()` estava **literalmente vazio** — sem `$this->commands([...])`. Varredura contada: **31 de 33 módulos** registram os próprios comandos; os **2** que não (`ProjectMgmt`, `ProductCatalogue`) são exatamente os 2 com comando morto. Não havia auto-discovery pra salvar — o `$this->load()` do `app/Console/Kernel.php:1538` cobre só `app/Console/Commands`, nunca `Modules/*/Console/Commands`.

Diferença que importa: **órfão de schedule** é uma máquina que existe e ninguém agenda; **órfão de registro** é uma máquina que não existe. As ações (a)/(b) do briefing pressupunham a primeira.

## 2. O teste-carimbo (LC-11, 3ª em produção em 3 dias)

O teste chamado `"F6 ProjectMgmtHealthCommand **registrado** + signature canon"` fazia:

```php
$cmd = app(ProjectMgmtHealthCommand::class);
expect($cmd)->toBeInstanceOf(ProjectMgmtHealthCommand::class);
```

O container resolve **qualquer classe concreta do disco**, com ou sem registro. O teste media *"a classe existe"* e afirmava *"o comando está registrado"*.

**Bite-test:** rodei o teste no **mesmo container** onde o artisan não conhecia o comando → `1 passed (3 assertions)`. Verde e comando inexistente, lado a lado. Nasceu em maio/2026 (Wave 17) — **~2,4 meses** assim.

Vale registrar o **método**, não só o achado: ele apareceu ao confrontar a **SAÍDA** do mecanismo (teste verde) com a **FONTE** que ele diz resolver (`artisan list`). Reler o assert não denunciaria nada — `app(Class)` *parece* prova de registro. É exatamente o predicado que a nota do LC-11 já prescrevia depois das instâncias de 07-26 e 07-27, e esta é a 3ª a validá-lo.

Recorte medido do padrão: **5** testes usam `"registrado + signature canon"` (`ProjectMgmt`, `ProductCatalogue`, `SRS`, `Woocommerce`); o carimbo escondeu defeito **só nos 2 sem registro** — nos outros o teste é fraco mas não mente. **Não** extrapolar isso pra "a população está mapeada".

## 3. O dado que desmontou o reflexo de "ligar"

Medido em **prod** (o CT 100 tem `mcp_tasks` **vazia** — teria dado o número errado):

| medida | valor |
|---|---|
| P0 ativas sem dono | 31 → depois **30** |
| dessas, em `status=todo` (já vistas pelo cron `mcp:tasks:unassigned` 06:45) | **30** |
| **delta exclusivo do check órfão** | **1** → depois **0** |

A task em `review` que dava o delta de 1 saiu no intervalo de ~1h entre as duas medições. Hoje o check é **100% redundante** com o cron das 06:45.

Somam-se: threshold `FAIL >= 5` com população 30 faria o alarme **nascer vermelho permanente** (o espelho do gate-de-teatro — vermelho que não pode ficar verde vira ruído que se aprende a ignorar), e **2 dos 4 checks são carimbo** (`Schema::hasTable` nunca falha num app com migrations).

## 4. O que o medidor revelou ao rodar em prod pela 1ª vez

```
unowned_tasks_p0   FAIL   30 tasks P0 SEM owner
active_projects    WARN   Nenhum project active
projects/tasks_table_present   OK  ×2
```

O WARN tem **recomendação errada**: sugere *"backlog parado OU todos projects em draft"*, mas `mcp_projects` tem **0 linhas no total** — a tabela nunca foi populada em prod. O que isso diz sobre o módulo é leitura do [W].

## 5. Erro meu no caminho

Usei `gh run rerun` pra revalidar o gate `infra-contract-required` **depois** de editar o PR body. Reruns **reusam o payload do evento original** — o body antigo — então o check ficava vermelho para sempre enquanto um run novo (disparado pelo `edited`) já estava verde. Instrumento errado pra pergunta; o run válido é o disparado por evento novo. Nota deixada no corpo do PR pra quem cruzar com o run vermelho no histórico.

# O fix (mínimo, sem podar capacidade)

- `$this->commands([...])` dentro de `runningInConsole()` nos 2 ServiceProviders — padrão do `VestuarioServiceProvider`, que o docblock do próprio comando já citava como referência
- o assert que exerce o verbo: `expect(array_keys(Artisan::all()))->toContain('<signature>')`

**Bite-test nas 2 direções, contra o registry vivo (475 comandos):** `project-mgmt:health` AUSENTE · `product-catalogue:health` AUSENTE · `vestuario:health` **PRESENTE** (controle positivo — módulo que já registrava).

**Não** foi ligado cron nem deletado nada: agendar e podar capacidade são soberania [W].

# Smoke real pós-deploy (R1)

Merge por [W] 17:12 UTC, deploy `success`. Prometido no Infra Contract e cumprido:

| eixo | resultado |
|---|---|
| `login` · `/` · `/business/register` | **200 / 200 / 302** — idêntico à baseline pré-merge (boot intacto) |
| `artisan list` em prod | os 2 comandos **presentes** |
| `project-mgmt:health --json` em prod | rodou pela 1ª vez desde que nasceu |

# Ressalva que fica aberta

**O assert corrigido não roda em CI nenhum.** O `modules-pest.yml` tem allowlist de 6 módulos (`Arquivos, ComunicacaoVisual, Fiscal, NfeBrasil, Repair, Vestuario`) e nenhum dos dois está nela, nem em outra lane. O **registro funciona em runtime independente disso**; o *teste* segue sem gate. Ligar a lane avermelharia dívida acumulada (o normal — §5 2026-07-28) e ficou fora do escopo.

# Registro canon

- `memory/proibicoes.md` §5 — lápide 2026-07-28 (o limite: provar registro com `app()`/`class_exists()`/`file_exists()`; cada registry tem seu oráculo — comando → `Artisan::all()`, rota → `route:list`, listener → `Event::getListeners()`, schedule → `runsInEnvironment()`, binding → `app()->bound()`)
- `memory/LICOES_CODE.md` LC-11 — Ocorrências 4→5, nota do Gate atualizada com o recorte medido

Um número da lápide (**31**, das medições iniciais) já envelheceu pra 30 no mesmo dia. **Não editei** — o §5 trata recibo como medição datada e manda *"re-rode a query, não edite o número"*. A variação em 1h é, por si só, a ilustração de por que a regra existe.

# Recomendação pendente ([W] decide)

Se o delta voltar a ser >0 e valer cobrir, o caminho canônico é **estender o predicado do `mcp:tasks:unassigned`** (`where('status','todo')` → status ativos) — que já é registrado, agendado e está no `AUTOMATIONS.md` — em vez de agendar um 2º medidor em paralelo (§5 2026-07-09 "duplica régua consolidada"). Aposentar os 2 comandos também é defensável agora que o sinal está honesto.
