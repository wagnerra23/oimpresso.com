---
id: resources-js-pages-repair-job-sheet-add-parts-casos
casos: Peças da OS · /repair/job-sheet/add-parts/{id}
irmaos: AddParts.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — "salvar peça não baixa estoque" e "a lista é substituída, não mesclada" valem em qualquer refactor
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Peças da OS

> Derivados do [AddParts.charter.md](AddParts.charter.md) e do
> [RUNBOOK-jobsheet-add-parts.md](../../../../../memory/requisitos/Repair/RUNBOOK-jobsheet-add-parts.md),
> mais o contrato real de `JobSheetController::addParts/saveParts` — **não** do `.tsx`
> (teste derivado da implementação é tautológico, §5 2026-06-05).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Esta tela é Tier 0 por assunto.** Ela mexe em peças de uma OS, então qualquer
> mudança que toque quantidade, preço, total ou baixa de estoque cai sob a REGRA MESTRE
> de [`proibicoes.md`](../../../../../memory/proibicoes.md) (dupla prova + tabela
> antes→depois + aval [W]). Estes casos **medem** o contrato vigente; **alterar** o
> cálculo não é escopo deles.
>
> ⚠️ **O módulo não tem SDD/CU para esta tela.** A ordem de fonte canônica manda derivar
> o UC da documentação (SDD §6 CU / SPEC US) e usar o código só para *confirmar*; aqui a
> primeira perna não existe — o `SPEC.md` do Repair tem três US e nenhuma cobre a OS.
> Então cada UC abaixo é derivado do **charter** (que é lei) e descreve o que o
> **controller garante hoje**, dito na cara em vez de fingir uma âncora que não há.

---

## UC-JSP-01 · Registrar as peças cobradas sem mexer no estoque
- **Persona:** quem atende no balcão anota as peças que vão ser cobradas do cliente enquanto o aparelho ainda está em diagnóstico.
- **Aceite:** Dado uma OS do meu negócio · Quando salvo a lista de peças · Então as peças ficam registradas na OS **e nenhuma linha de estoque se move** (nem contagem de linhas, nem soma de quantidade disponível).
- **Por que é assim:** dar baixa é side-effect do FSM (`ConsumirEstoque`, [ADR 0143](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)), acionado numa transição de estágio — não nesta tela. É o anti-hook literal do charter: *"NÃO consome estoque ao salvar parts (consumo via FSM action)"*.
- **Regressão que defende:** fazer a tela baixar estoque "para adiantar" tiraria peça do saldo de um orçamento que o cliente ainda pode recusar — e o estoque passaria a mentir para todo mundo, não só para esta OS.
- **Teste:** `Modules/Repair/Tests/Feature/JobSheetAddPartsContratoTest.php` — *"UC-JSP-01: salvar peças na OS não movimenta estoque"* (fotografa `variation_location_details` antes e depois; a asserção de que a peça FOI registrada impede que o verde venha de "não fez nada").
- **Status: 🧪** _passou no CT 100; a foto do estoque antes/depois é idêntica_

## UC-JSP-02 · A lista salva é a lista que vale
- **Persona:** quem corrige a lista depois que o técnico abriu o aparelho e viu que uma peça não era necessária.
- **Aceite:** Dado uma OS com duas peças · Quando submeto a lista contendo só a primeira · Então a segunda **some** da OS. E: Dado uma OS com peças · Quando submeto sem nenhuma peça · Então a OS fica sem peça alguma.
- **Por que é assim:** `saveParts` faz `$job_sheet->parts = $parts` — substituição do conjunto inteiro, nunca merge. É o mesmo contrato destrutivo já catalogado nas configurações do módulo (UC-RSET-03).
- **O que isto exige da tela:** a Page precisa enviar o **conjunto completo** a cada submit. Uma tela que mandasse só o que mudou apagaria o resto sem aviso — e o operador só descobriria na hora de cobrar.
- **Teste:** `JobSheetAddPartsContratoTest` — *"UC-JSP-02: salvar substitui a lista inteira de peças, não faz merge"* + *"UC-JSP-02: submeter sem nenhuma peça zera a lista da OS"*.
- **Status: 🧪** _passou no CT 100, nos dois testes (substituição e lista vazia)_

## UC-JSP-03 · Peças de OS de outro negócio são inalcançáveis (Tier 0)
- **Persona:** ninguém — este caso existe porque a falha seria **silenciosa** e cruzaria a fronteira de tenant.
- **Aceite:** Dado uma OS que pertence a outro negócio · Quando tento abrir a tela de peças **ou** submeter peças nela · Então **404** nas duas rotas, e a OS do outro negócio continua exatamente como estava.
- **Por que é assim:** `JobSheet::where('business_id', $business_id)->findOrFail($id)` — [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0 irrevogável.
- **Regressão que defende:** trocar o `where` por `find()` cru daria 404 na leitura mas gravaria na escrita — por isso o aceite exige as **duas** rotas e ainda confere que o dado alheio ficou intacto.
- **Teste:** `JobSheetAddPartsContratoTest`, em DOIS — *"UC-JSP-03: não lê nem altera as peças de OS de outro negócio"*
  (o dado do vizinho; verde) e *"UC-JSP-03: a tentativa de gravar peças em OS de outro negócio responde 404"*
  (o código de resposta; vermelho). Separados de propósito: um vermelho único esconderia que o isolamento do
  DADO está de fato garantido, e é isso que decide a gravidade do achado.
- **⚠️ Achado aberto (CT 100, 2026-09-05):** a escrita responde **500**, não 404 — `Undefined variable $job_sheet`
  em `JobSheetController.php:1139`. O `saveParts` engole a exceção do `findOrFail` num `catch (\Exception)` e o
  fluxo segue até um `redirect()` que usa a variável nunca atribuída; de quebra, o catch registra a falha como
  sucesso. O dado alheio **não** é tocado. Consertar mexe no caminho de gravação de peças (Tier 0, REGRA MESTRE)
  e é decisão [W].
- **Status: 🧪 / ❌** _PARCIAL — a metade que protege o DADO passou; a metade da RESPOSTA falhou: `500 is identical to 404`, `Undefined variable $job_sheet` em JobSheetController.php:1139_

## UC-JSP-04 · Sem permissão, a tela não existe
- **Persona:** usuário do negócio que não trabalha com OS.
- **Aceite:** Dado usuário sem `job_sheet.create` e sem `job_sheet.edit` · Quando acesso a tela ou submeto peças · Então **403** nas duas.
- **Por que é assim:** o charter fixa *"Permission `job_sheet.create` OR `edit`"*, e o gate do controller repete isso nas duas ações.
- **Teste:** `JobSheetAddPartsContratoTest` — *"UC-JSP-04: usuário sem job_sheet.create nem job_sheet.edit recebe 403"*.
- **Status: 🧪** _passou no CT 100, nas duas rotas_

## UC-JSP-05 · Peça de outro negócio não aparece na OS (Tier 0)
- **Persona:** ninguém — é uma fronteira de tenant, não uma tarefa.
- **Aceite:** Dado que gravo na OS um identificador de peça que pertence a **outro** negócio · Quando a tela monta a lista de peças usadas · Então essa peça **não** é exibida.
- **Por que este caso existe, e por que ele pode sair vermelho:** o JSON `parts` guarda `variation_id` cru, e `saveParts` grava o que vier do formulário sem conferir de quem é a peça. Do outro lado, `JobSheet::getPartsUsed()` resolve os nomes com `Variation::whereIn('id', ...)` — e `variations` **não tem** `business_id` (o dono do tenant é `products`), nem escopo global (medido em `app/Variation.php`: zero `addGlobalScope`). Se o isolamento não estiver garantido por outra via, o nome do produto do vizinho aparece na tela.
- **O que fazemos com o resultado:** este UC foi escrito para **medir**, não para presumir. Se o teste passar, o isolamento existe e fica travado. Se falhar, o `❌` **é o achado** — com recibo — e a correção é decisão [W], não conserto silencioso nesta sessão ([precedência Tier 0](../../../../../memory/proibicoes.md)).
- **Teste:** `JobSheetAddPartsContratoTest` — *"UC-JSP-05: peça de outro negócio não aparece na OS"*. Mede `getPartsUsed()`, que é a fonte exata do payload `parts` desta tela (o controller o chama e passa por `buildJobSheetPartsPayload`).
- **Status: ❌** _ACHADO Tier 0 — a peça do negócio vizinho É exibida: `Expecting […] not to contain 134`_

## UC-JSP-06 · Registrar peças não move o estágio da OS
- **Persona:** quem anota peças no meio do atendimento e não deveria, com isso, mudar a situação da OS.
- **Aceite:** Dado uma OS em qualquer estágio · Quando salvo peças (mesmo enviando um estágio junto no formulário) · Então o estágio da OS permanece o mesmo.
- **Por que é assim:** o charter declara *"Sem FSM (action não-transitiva)"*; quem transiciona é o `ExecuteStageActionService`, e a trait `GuardsFsmTransitions` bloqueia UPDATE direto em `current_stage_id` ([ADR 0143](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)).
- **Teste:** `JobSheetAddPartsContratoTest` — *"UC-JSP-06: salvar peças não altera o estágio FSM da OS"*.
- **Status: 🧪** _passou no CT 100_

---

## Rastreabilidade

| UC | Defendido por | Eixo |
|---|---|---|
| 01, 06 | `JobSheetAddPartsContratoTest` | Tier 0 — estoque e FSM (ADR 0143) |
| 02 | `JobSheetAddPartsContratoTest` | contrato destrutivo da gravação |
| 03, 05 | `JobSheetAddPartsContratoTest` | Tier 0 — isolamento (ADR 0093) |
| 04 | `JobSheetAddPartsContratoTest` | permissão |

## Onde estes casos são provados — e onde **não** são

A lane `modules-pest.yml` (matrix `Repair`) **dispara** neste PR: os `paths:` dela incluem
`Modules/Repair/**` e `resources/js/Pages/Repair/**`. Mas ela roda `vendor/bin/pest` com
`DB_CONNECTION=sqlite` `:memory:` e **sem migrate** — o schema UltimatePOS é MySQL-only.
Nessa lane estes UCs **pulam**, e o verde dela prova só que o arquivo carrega.

⚠️ **Existe uma segunda lane com MySQL real — `verticais-pest.yml` — e este arquivo está
FORA dela de propósito** (2026-09-05, [PR #6887](https://github.com/wagnerra23/oimpresso.com/pull/6887)).
A catraca de lá é allowlist **verde**: arquivo que avermelha fica de fora e vira dívida
datada no comentário da lane, porque vermelho permanente em lane advisory só ensina o time
a ignorá-la. Seguram este arquivo **dois** `[must]` provados vermelhos — o **UC-JSP-03**
(500 `Undefined variable $job_sheet` onde o contrato pede 404) e o **UC-JSP-05** (peça do
negócio vizinho exibida, Tier 0 · ADR 0093). Consertar mexe no caminho de gravação de peças
⇒ **REGRA MESTRE** valor/estoque, decisão [W]. O irmão `Show` entrou (5/5, 29 assertions).
Até lá, a prova destes UCs é só o CT 100.

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
