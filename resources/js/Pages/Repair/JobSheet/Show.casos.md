---
id: resources-js-pages-repair-job-sheet-show-casos
casos: Detalhe da OS · /repair/job-sheet/{id}
irmaos: Show.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — "o técnico só vê a OS que atende" e "o painel FSM aponta para o Repair" valem em qualquer refactor
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Detalhe da OS

> Derivados do [Show.charter.md](Show.charter.md) e do
> [RUNBOOK-jobsheet-show.md](../../../../../memory/requisitos/Repair/RUNBOOK-jobsheet-show.md),
> mais o contrato real de `JobSheetController::show` — **não** do `.tsx`
> (teste derivado da implementação é tautológico, §5 2026-06-05).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **O módulo não tem SDD/CU para esta tela.** O `SPEC.md` do Repair tem três US e
> nenhuma cobre o detalhe da OS. Cada UC abaixo é derivado do **charter** (que é lei) e
> descreve o que o **controller garante hoje** — dito na cara em vez de fingir uma
> âncora documental que não existe.

---

## UC-JSS-01 · OS de outro negócio é inalcançável (Tier 0)
- **Persona:** ninguém — este caso existe porque a falha seria **silenciosa** e cruzaria a fronteira de tenant.
- **Aceite:** Dado uma OS que pertence a outro negócio · Quando abro o detalhe dela pela URL · Então **404**.
- **Por que é assim:** a consulta do `show` parte de `where('business_id', $business_id)` antes do `findOrFail` — [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0 irrevogável. É o anti-hook literal do charter: *"NÃO acessa OS de outro biz (Tier 0)"*.
- **Regressão que defende:** esta é a tela que mostra **tudo** da OS — cliente, aparelho, defeitos, peças, anexos, histórico. Um vazamento aqui não expõe um campo: expõe o atendimento inteiro de outro negócio.
- **Teste:** `Modules/Repair/Tests/Feature/JobSheetShowContratoTest.php` — *"UC-JSS-01: não abre o detalhe de OS de outro negócio"*.
- **Status: 🧪** _passou no CT 100 e **na lane** `Verticais · MySQL` (ver rodapé)_

## UC-JSS-02 · Sem permissão ampla, o técnico só vê a OS que é dele
- **Persona:** o técnico da bancada, que deve ver as OS que abriu ou que lhe foram atribuídas — não a carteira inteira da loja.
- **Aceite:** Dado usuário **sem** `job_sheet.view_all` e que não é administrador do negócio · Quando abro uma OS que **eu** criei · Então vejo; Quando abro uma OS **atribuída a mim** · Então vejo; Quando abro uma OS de um colega, sem relação comigo · Então **404**.
- **Por que é assim:** o `show` acrescenta à consulta um filtro por `service_staff` **ou** `created_by` para quem não é administrador e não tem `job_sheet.view_all`. O charter registra o par de permissões (*"`job_sheet.view_all` OU `view_assigned`"*) e este UC fixa o que a segunda metade significa na prática.
- **Como o teste garante a pré-condição:** ele **afirma** que o usuário não tem `job_sheet.view_all` antes de exercitar os três acessos. Sem essa asserção, uma mudança futura no bypass de permissões faria os três darem 200 e o caso ficaria verde provando o contrário do que promete.
- **Regressão que defende:** remover o filtro transforma "detalhe da minha OS" em "detalhe de qualquer OS da loja" para todo mundo com acesso ao módulo.
- **Teste:** `JobSheetShowContratoTest` — *"UC-JSS-02: quem não tem job_sheet.view_all só enxerga a OS que criou ou atende"*.
- **Status: 🧪** _passou no CT 100 e **na lane** — os três acessos, com a pré-condição de permissão afirmada_

## UC-JSS-03 · O painel de ações aponta para o Repair, nunca para Vendas
- **Persona:** ninguém — é uma armadilha de reuso, e nada além deste caso a pegaria.
- **Aceite:** Dado o detalhe de uma OS · Quando a tela é montada · Então os três endereços entregues ao painel de ações apontam para as rotas do Repair **daquela** OS: consultar ações, executar ação e iniciar pipeline.
- **Por que este caso existe:** o RUNBOOK marca isto como **risco R1 (MÉDIO)**. O painel de ações é compartilhado com Vendas e assume endereços `/sells/...`; o Repair injeta os seus por um wrapper. Se os endereços vazarem para os de Vendas, a tela dispara transição **no módulo errado** — e o sintoma não aparece na tela, aparece no pipeline de outra entidade.
- **Regressão que defende:** trocar o wrapper pelo componente compartilhado cru, num refactor de "remover duplicação", reintroduz exatamente o R1.
- **Teste:** `JobSheetShowContratoTest` — *"UC-JSS-03: o painel FSM recebe endereços do Repair, nunca os de Vendas"* (confere os três endereços contra o id da OS, não contra um prefixo genérico).
- **Status: 🧪** _passou no CT 100 e **na lane** — os três endereços conferidos contra o id da OS_

## UC-JSS-04 · A tela diz se a OS já entrou no pipeline
- **Persona:** quem abre a OS e precisa saber se opera pelo status legado ou pelas ações do pipeline.
- **Aceite:** Dado uma OS que nasceu fora do pipeline · Quando abro o detalhe · Então a tela declara que ela **não** está no pipeline (é o que faz aparecer "Iniciar pipeline" em vez das ações); Dado uma OS já em um estágio · Então a tela declara que ela **está**.
- **Por que é assim:** o charter descreve o painel como *"actions disponíveis ou 'Iniciar pipeline'"*, e o RUNBOOK chama isso de **estado FSM dual** — a coexistência entre o status legado e o pipeline canônico ([ADR 0143](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)).
- **Regressão que defende:** inverter a condição mostra "Iniciar pipeline" para uma OS que já está correndo — e um segundo início é a porta para dois fluxos concorrentes sobre a mesma OS.
- **⚠️ Cobertura condicionada ao ambiente, e isto fica dito:** a metade "já em um estágio" só é exercitada quando o banco tem pipeline semeado; sem isso, afirmá-la exigiria plantar uma referência inválida — e teste que planta a própria pré-condição global mente sobre o que o ambiente de fato tem (§5 2026-08-24). O teste **pula essa metade** em vez de fabricá-la, e a metade legada roda sempre. No run do CT 100 de 2026-09-05 havia pipeline semeado, então as **duas** metades rodaram; num banco sem pipeline, este UC prova só a primeira.
- **Teste:** `JobSheetShowContratoTest` — *"UC-JSS-04: a tela informa se a OS está no pipeline FSM"*.
- **Status: 🧪** _passou no CT 100 (lá a metade "já no pipeline" **rodou** — há pipeline semeado) e **na lane**. Mas a metade condicional **não é garantida por run**: sem estágio no banco o teste faz `return` silencioso, e `return` não aparece como `skipped` no sumário. Medido nos dois runs do rodapé — 29 vs **38** assertions no mesmo arquivo, com os mesmos 5 verdes: o ramo rodou num e não no outro. O que este UC garante SEMPRE é a metade legada_

## UC-JSS-05 · Sem permissão, o detalhe não existe
- **Persona:** usuário do negócio que não trabalha com OS.
- **Aceite:** Dado usuário sem nenhuma das permissões de OS · Quando abro o detalhe · Então **403**.
- **Por que é assim:** o gate do `show` aceita `job_sheet.view_assigned`, `job_sheet.view_all` **ou** `job_sheet.create` — quem não tem nenhuma delas não passa.
- **Teste:** `JobSheetShowContratoTest` — *"UC-JSS-05: usuário sem permissão de OS recebe 403 no detalhe"*.
- **Status: 🧪** _passou no CT 100 e **na lane**_

---

## Rastreabilidade

| UC | Defendido por | Eixo |
|---|---|---|
| 01 | `JobSheetShowContratoTest` | Tier 0 — isolamento (ADR 0093) |
| 02, 05 | `JobSheetShowContratoTest` | permissão e escopo de visibilidade |
| 03 | `JobSheetShowContratoTest` | risco R1 do RUNBOOK — reuso entre módulos |
| 04 | `JobSheetShowContratoTest` | coexistência legado × pipeline (ADR 0143) |

## Onde estes casos são provados — e onde **não** são

A lane `modules-pest.yml` (matrix `Repair`) **dispara** neste PR: os `paths:` dela incluem
`Modules/Repair/**` e `resources/js/Pages/Repair/**`. Mas ela roda `vendor/bin/pest` com
`DB_CONNECTION=sqlite` `:memory:` e **sem migrate** — o schema UltimatePOS é MySQL-only.
Nessa lane estes UCs **pulam**, e o verde dela prova só que o arquivo carrega.

✅ **Desde 2026-09-05 existe uma segunda lane, e nela os 5 UCs rodam de verdade:**
`verticais-pest.yml` (`PHP / Pest (Verticais · MySQL)`) — MySQL semeado pela
`.github/actions/pest-mysql-setup`. O arquivo entrou na allowlist no
[PR #6887](https://github.com/wagnerra23/oimpresso.com/pull/6887).
**Recibos**, lidos do sumário JUnit por arquivo e não do console — dois runs, porque entre
eles a allowlist da lane cresceu de 12 para 18 arquivos (merge de #6884 e #6886):

```
run 33997676926 (allowlist 12) → JobSheetShow: 5 tests · 5 passed · 0 skipped · 29 assertions
                                 lane inteira: 112 passed ·  9 skipped · 612 assertions
run 34001673840 (allowlist 18) → JobSheetShow: 5 tests · 5 passed · 0 skipped · 38 assertions
                                 lane inteira: 129 passed · 10 skipped · 733 assertions
```

`skipped: 0` com assertions de dois dígitos é o que separa "passou" de "pulou" — é a leitura
que a consequência 1 abaixo exige.

⚠️ **E os dois recibos, comparados, provam a ressalva do UC-JSS-04 em vez de só afirmá-la:**
o MESMO arquivo, com os MESMOS 5 testes passando, rendeu **29** assertions num run e **38**
no outro. A diferença é o ramo condicional do UC-JSS-04, que só executa quando há estágio em
`sale_process_stages` — e isso depende do que MAIS roda na lane, porque o banco é
compartilhado dentro do run e a ordem é aleatória. Ou seja: a cobertura desse ramo **não é
propriedade deste arquivo**, é da composição da lane naquele dia. Por isso o Status abaixo
não promete as duas metades. Os outros 3 contratos do JobSheet (`Create`, `Edit`,
`AddParts`) **continuam fora de toda lane**, por decisão registrada: têm `[must]` provados
vermelhos e a catraca da `verticais` só aceita arquivo verde. A dívida está datada no
comentário da própria lane.

Duas consequências que ficam ditas em vez de descobertas depois:

1. **A prova real sai do CT 100** (MySQL, `docker exec oimpresso-staging`), nunca local
   ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
   `0 failed` não é prova de nada — o que se lê é a contagem de **assertions** (LC-13).
2. **A lane não dispara em `synchronize`** (`types: [opened, reopened, ready_for_review]`),
   então um push posterior ao PR não a re-executa; é preciso disparo manual.

Os UCs que leem propriedades da tela (03 e 04) ligam o caminho Inertia por configuração
dentro do próprio teste — as flags MWART nascem desligadas, e depender do `.env` do
ambiente faria o resultado mudar de máquina para máquina.

## Revalidação — 2026-09-05 (contrato inicial)

Tela sem casos até aqui: o módulo tinha **1** `casos.md` em 14 telas
(`node scripts/governance/module-surface.mjs Repair`). Este arquivo nasce com os cinco UCs
acima e o teste que os cita.

### Recibo do run — CT 100, 2026-09-05

`tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test
Modules/Repair/Tests/Feature/JobSheet{AddParts,Create,Edit,Show}ContratoTest.php"`

**24 passed · 4 failed · 100 assertions.** A contagem de assertions é o que se lê aqui: os quatro
arquivos `Wave3B6JobSheet*` vizinhos saem verdes pulando tudo por falta de dado pré-existente
(`Sem JobSheet.`, `Precisa de 2+ biz.`), e `0 failed` num run que não rodou nada não prova coisa
alguma (LC-13). Por isso as fixturas destes contratos são criadas pelo próprio teste no tenant 98.

Os **4 vermelhos são achados**, não testes mal escritos — cada um está descrito no UC a que
pertence, com o erro literal. Nenhum deles é conserto desta sessão: dois mexem no caminho de
gravação (Tier 0, REGRA MESTRE) e um é divergência entre charter e código cujas duas saídas
apontam para lados opostos. A correção é decisão [W].

Durante a revisão, um destes testes ficou verde por engano meu: `not->toHaveKey($id, $mensagem)`
compara CHAVE + VALOR, então o texto virou o valor esperado e o assert deixou de morder. Corrigido
para comparar a lista de chaves, e re-medido — voltou a acusar. É a família da lápide §5 2026-07-28
(mensagem passada como needle), e fica registrado porque um falso verde é pior que um vermelho.
