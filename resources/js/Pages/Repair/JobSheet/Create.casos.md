---
id: resources-js-pages-repair-job-sheet-create-casos
casos: Abertura de OS · /repair/job-sheet/create
irmaos: Create.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — "a OS nasce no negócio da sessão" e "o custo digitado em pt-BR não infla" valem em qualquer refactor
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Abertura de OS

> Derivados do [Create.charter.md](Create.charter.md) e do
> [RUNBOOK-jobsheet-create.md](../../../../../memory/requisitos/Repair/RUNBOOK-jobsheet-create.md),
> mais o contrato real de `JobSheetController::create/store` — **não** do `.tsx`
> (teste derivado da implementação é tautológico, §5 2026-06-05).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **UC-JSC-04 é Tier 0 por assunto (VALOR).** O `estimated_cost` atravessa
> `Util::num_uf`, o mesmo parser do incidente de 2026-06-05 (biz=4: uma venda de
> centenas foi gravada como centenas de milhares). Por isso o aceite prova o valor por
> **dois caminhos independentes**, como manda a REGRA MESTRE de
> [`proibicoes.md`](../../../../../memory/proibicoes.md). Estes casos **medem** o
> parser; **mexer** nele não é escopo deles.
>
> ⚠️ **O módulo não tem SDD/CU para esta tela.** O `SPEC.md` do Repair tem três US e
> nenhuma cobre a abertura de OS. Cada UC abaixo é derivado do **charter** (que é lei) e
> descreve o que o **controller garante hoje** — dito na cara em vez de fingir uma
> âncora documental que não existe.

---

## UC-JSC-01 · A OS nasce no negócio de quem a abriu (Tier 0)
- **Persona:** ninguém — este caso existe porque a falha seria **silenciosa** e cruzaria a fronteira de tenant.
- **Aceite:** Dado que estou logado num negócio · Quando envio o formulário de abertura **com um `business_id` de outro negócio embutido** · Então a OS é criada no negócio da **sessão**, o autor registrado sou **eu**, e a contagem de OS do outro negócio não muda.
- **Por que é assim:** o `store` monta `$input` por lista branca (`$request->only(...)`, que não inclui `business_id`) e depois **força** `$input['business_id']` e `$input['created_by']` a partir da sessão e do usuário autenticado. É o anti-hook literal do charter: *"NÃO cria OS de outro biz"* ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Regressão que defende:** trocar a lista branca por `$request->all()` faria o campo do formulário mandar na gravação — e a OS nasceria dentro do negócio de outro cliente.
- **Teste:** `Modules/Repair/Tests/Feature/JobSheetCreateContratoTest.php` — *"UC-JSC-01: a OS nasce no negócio da sessão, ignorando business_id do formulário"*.
- **Status: ⬜** _(teste existe e cita o UC; veredito pendente — ver §Revalidação)_

## UC-JSC-02 · A OS nasce fora do pipeline
- **Persona:** quem abre a OS no balcão e ainda não decidiu por qual fluxo ela vai correr.
- **Aceite:** Dado o formulário de abertura · Quando crio a OS (mesmo enviando um estágio junto) · Então a OS nasce **sem estágio** — entrar no pipeline é um ato posterior, feito no detalhe da OS.
- **Por que é assim:** o charter declara como Non-Goal *"FSM pipeline iniciação (OS nasce legacy)"*, e o RUNBOOK repete: *"na criação, OS nasce SEM `current_stage_id`"*. Quem inicia o pipeline é a ação própria do FSM ([ADR 0143](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)).
- **Regressão que defende:** iniciar o pipeline no `store` colocaria toda OS nova num fluxo com regras de transição e papéis — inclusive as que só precisam do status legado.
- **Teste:** `JobSheetCreateContratoTest` — *"UC-JSC-02: a OS nasce fora do pipeline FSM, com estágio vazio"*.
- **Status: ⬜** _(teste existe e cita o UC; veredito pendente)_

## UC-JSC-03 · O botão escolhido decide para onde eu vou
- **Persona:** quem atende no balcão e já sabe que vai emendar o cadastro com as peças, ou com os documentos do aparelho.
- **Aceite:** Dado que criei a OS · Quando escolho *salvar* · Então vou para o detalhe dela; Quando escolho *salvar e adicionar peças* · Então vou para a tela de peças **daquela** OS; Quando escolho *salvar e enviar documentos* · Então vou para a tela de documentos **daquela** OS.
- **Por que é assim:** os três destinos são Goal explícito do charter (*"Submit types: save · save_and_add_parts · save_and_upload_docs"*) e vêm preservados do Blade legado — é ergonomia que o operador já tem hoje.
- **Regressão que defende:** um redirect que ignore o botão joga o atendente de volta na lista, e ele reabre a OS na mão a cada atendimento.
- **Teste:** `JobSheetCreateContratoTest` — *"UC-JSC-03: cada submit_type leva a OS recém-criada ao seu destino"* (os três casos num dataset; cada um confere que o destino aponta para o id recém-criado, não para uma rota genérica).
- **Status: ⬜** _(teste existe e cita o UC; veredito pendente)_

## UC-JSC-04 · O custo estimado digitado em pt-BR não infla (Tier 0 · VALOR)
- **Persona:** quem atende digita `1.234,56` porque é assim que se escreve dinheiro aqui.
- **Aceite:** Dado um custo digitado em formato brasileiro · Quando abro a OS · Então o valor gravado é **idêntico** ao que o parser canônico do projeto (`Util::num_uf`) produz para a mesma entrada — verificado por dois caminhos independentes: (A) o valor lido do banco depois do POST, (B) o parser chamado direto, sem passar pela tela. E nenhuma dessas entradas pode virar um número absurdo.
- **Por que o aceite é uma identidade, e não um número fixo:** fixar `1234.56` à mão criaria um segundo oráculo do parser dentro do teste, que drifaria dele. O contrato que interessa é *"esta tela usa o parser canônico do projeto"* — e é isso que a identidade prova.
- **Regressão que defende:** a classe do incidente de 2026-06-05 — o ponto lido como separador de milhar transformando centenas em centenas de milhares. O caso concreto do dano está registrado na REGRA MESTRE de [`proibicoes.md`](../../../../../memory/proibicoes.md); aqui ele vira teste.
- **Teste:** `JobSheetCreateContratoTest` — *"UC-JSC-04: o custo estimado digitado em pt-BR é gravado pelo parser canônico"*.
- **Status: ⬜** _(teste existe e cita o UC; veredito pendente)_

## UC-JSC-05 · Sem permissão, a abertura não existe
- **Persona:** usuário do negócio que não trabalha com OS.
- **Aceite:** Dado usuário sem `job_sheet.create` · Quando abro o formulário **ou** submeto uma OS · Então **403** nas duas — e nenhuma OS é criada.
- **Por que o aceite confere a contagem também:** um 403 na resposta que ainda assim gravasse seria pior do que um 200 honesto, e nenhum assert de status code sozinho pega isso.
- **Teste:** `JobSheetCreateContratoTest` — *"UC-JSC-05: usuário sem job_sheet.create recebe 403 na abertura de OS"*.
- **Status: ⬜** _(teste existe e cita o UC; veredito pendente)_

## UC-JSC-06 · Cada OS sai com o seu número
- **Persona:** o cliente que sai da loja com um número na mão e liga citando esse número.
- **Aceite:** Dado que abro duas OS no mesmo negócio · Quando ambas são criadas · Então cada uma tem número **não vazio** e os dois números são **diferentes**.
- **Por que é assim:** o `store` gera o número por contador de referência do negócio, prefixado pelo que estiver em `business.repair_settings` (é o `job_sheet_prefix` que a tela de configurações grava — UC-RSET-01).
- **Regressão que defende:** número repetido faz duas OS responderem pela mesma ligação; número vazio tira do cliente a única referência que ele tem.
- **Teste:** `JobSheetCreateContratoTest` — *"UC-JSC-06: cada OS aberta recebe um número não vazio e distinto"*.
- **Status: ⬜** _(teste existe e cita o UC; veredito pendente)_

---

## Rastreabilidade

| UC | Defendido por | Eixo |
|---|---|---|
| 01 | `JobSheetCreateContratoTest` | Tier 0 — isolamento (ADR 0093) |
| 02 | `JobSheetCreateContratoTest` | Non-Goal do charter — FSM (ADR 0143) |
| 03, 06 | `JobSheetCreateContratoTest` | ergonomia preservada do legado |
| 04 | `JobSheetCreateContratoTest` | Tier 0 — VALOR (REGRA MESTRE) |
| 05 | `JobSheetCreateContratoTest` | permissão |

## Onde estes casos são provados — e onde **não** são

A lane `modules-pest.yml` (matrix `Repair`) **dispara** neste PR: os `paths:` dela incluem
`Modules/Repair/**` e `resources/js/Pages/Repair/**`. Mas ela roda `vendor/bin/pest` com
`DB_CONNECTION=sqlite` `:memory:` e **sem migrate** — o schema UltimatePOS é MySQL-only.
Nessa lane estes UCs **pulam**, e o verde dela prova só que o arquivo carrega.

Duas consequências que ficam ditas em vez de descobertas depois:

1. **A prova real sai do CT 100** (MySQL, `docker exec oimpresso-staging`), nunca local
   ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
   `0 failed` não é prova de nada — o que se lê é a contagem de **assertions** (LC-13).
2. **A lane não dispara em `synchronize`** (`types: [opened, reopened, ready_for_review]`),
   então um push posterior ao PR não a re-executa; é preciso disparo manual.

## Revalidação — 2026-09-05 (contrato inicial)

Tela sem casos até aqui: o módulo tinha **1** `casos.md` em 14 telas
(`node scripts/governance/module-surface.mjs Repair`). Este arquivo nasce com os seis UCs
acima e o teste que os cita.

O `Status:` de cada UC segue `⬜` até o run do CT 100 responder — declarar `✅` antes do
veredito seria exatamente o que o G-7 existe para pegar.
