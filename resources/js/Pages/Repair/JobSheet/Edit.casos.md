---
id: resources-js-pages-repair-job-sheet-edit-casos
casos: Edição de OS · /repair/job-sheet/{id}/edit
irmaos: Edit.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — "editar não move o estágio" e "o cliente só é avisado quando o status muda" valem em qualquer refactor
owner: wagner
autor: "[C] 2026-09-05"
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Edição de OS

> Derivados do [Edit.charter.md](Edit.charter.md) e do
> [RUNBOOK-jobsheet-edit.md](../../../../../memory/requisitos/Repair/RUNBOOK-jobsheet-edit.md),
> mais o contrato real de `JobSheetController::edit/update` — **não** do `.tsx`
> (teste derivado da implementação é tautológico, §5 2026-06-05).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **UC-JSE-06 é Tier 0 por assunto (VALOR)** — mesma doutrina do UC-JSC-04: o aceite
> prova o custo por dois caminhos independentes (REGRA MESTRE,
> [`proibicoes.md`](../../../../../memory/proibicoes.md)). Estes casos **medem**;
> **alterar** o cálculo não é escopo deles.
>
> ⚠️ **O módulo não tem SDD/CU para esta tela.** O `SPEC.md` do Repair tem três US e
> nenhuma cobre a edição de OS. Cada UC abaixo é derivado do **charter** (que é lei) e
> descreve o que o **controller garante hoje** — dito na cara em vez de fingir uma
> âncora documental que não existe.

---

## UC-JSE-01 · Editar a OS não move o estágio dela (Tier 0)
- **Persona:** quem corrige um dado do cadastro e não deveria, com isso, empurrar a OS adiante no fluxo.
- **Aceite:** Dado uma OS em qualquer estágio · Quando salvo a edição **enviando um estágio junto no formulário** · Então o estágio permanece o mesmo — e o resto do formulário **é** salvo.
- **Por que o aceite exige as duas metades:** confirmar só que o estágio não mudou daria verde também se a requisição inteira tivesse falhado. A segunda asserção é o controle positivo do caso.
- **Por que é assim:** quem transiciona é o `ExecuteStageActionService`, e a trait `GuardsFsmTransitions` bloqueia UPDATE direto em `current_stage_id` ([ADR 0143](../../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)). O charter diz duas vezes: Non-Goal *"Editar `current_stage_id` (FSM via Show panel)"* e anti-hook *"NÃO UPDATE direto current_stage_id"*. Na prática o `update` monta `$input` por lista branca que **não inclui** o campo.
- **Regressão que defende:** acrescentar `current_stage_id` à lista branca abriria um caminho paralelo ao FSM — transição sem papel, sem regra e sem registro no histórico.
- **Teste:** `Modules/Repair/Tests/Feature/JobSheetEditContratoTest.php` — *"UC-JSE-01: salvar a edição não move o estágio FSM da OS"*.
- **Status: 🧪** _passou no CT 100; o estágio ficou intacto e o resto do formulário foi salvo_

## UC-JSE-02 · OS de outro negócio é inalcançável (Tier 0)
- **Persona:** ninguém — este caso existe porque a falha seria **silenciosa** e cruzaria a fronteira de tenant.
- **Aceite:** Dado uma OS de outro negócio · Quando tento abrir a edição **ou** submeter a alteração · Então **404** nas duas rotas, e o dado do outro negócio continua exatamente como estava.
- **Por que é assim:** `JobSheet::where('business_id', $business_id)->findOrFail($id)` — [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0 irrevogável.
- **Regressão que defende:** o 404 na leitura sem o mesmo filtro na escrita — por isso o aceite cobre as **duas** rotas e ainda confere o dado alheio depois.
- **Teste:** `JobSheetEditContratoTest`, em DOIS — *"UC-JSE-02: não lê nem altera OS de outro negócio"* (o dado do
  vizinho; verde) e *"UC-JSE-02: a tentativa de editar OS de outro negócio responde 404"* (o código de resposta;
  vermelho).
- **⚠️ Achado aberto (CT 100, 2026-09-05):** a escrita responde **302**, não 404 — o `update` engole a exceção do
  `findOrFail` e cai no `redirect()->back()` com "algo deu errado". Mesma família do achado em `saveParts`, que ali
  produz 500. O dado alheio **não** é tocado; o que quebra é o contrato da resposta — um recurso de outro negócio
  precisa ser indistinguível de inexistente, e um 302 confirma que o id existe. A correção é decisão [W].
- **Status: 🧪 / ❌** _PARCIAL — a metade que protege o DADO passou; a metade da RESPOSTA falhou: `302 is identical to 404`_

## UC-JSE-03 · O cliente só é avisado quando a situação muda de verdade
- **Persona:** o cliente que deixou o aparelho e recebe mensagem quando ele fica pronto — e **só** então.
- **Aceite:** Dado uma OS · Quando salvo mudando o status · Então a mudança é anunciada (evento `RepairStatusChanged`); Quando salvo corrigindo qualquer outro campo **sem** mexer no status · Então **nada** é anunciado.
- **Por que é assim:** o `update` compara o status anterior com o novo e só emite o evento quando eles diferem. O evento é o que o módulo de mensagens traduz em aviso ao cliente.
- **Regressão que defende:** emitir a cada salvamento vira spam para quem está do outro lado — e uma correção de digitação no número de série mandaria "sua OS mudou de situação" para o cliente. É a metade que se perde primeiro num refactor, porque o caminho feliz continua funcionando.
- **Teste:** `JobSheetEditContratoTest` — *"UC-JSE-03: mudar o status da OS anuncia a mudança"* + *"UC-JSE-03: salvar sem mexer no status não anuncia nada ao cliente"* (o segundo é o controle negativo, e é o que de fato defende o cliente).
- **Status: 🧪** _passou no CT 100, nos dois testes — inclusive o controle negativo_

## UC-JSE-04 · O checklist salvo é o checklist que vale
- **Persona:** quem registrou os acessórios que vieram com o aparelho (carregador, capa) e depois volta para corrigir outro campo.
- **Aceite:** Dado uma OS com checklist preenchido · Quando salvo a edição **sem enviar o checklist** · Então o checklist da OS fica **vazio**.
- **Por que é assim:** o `update` faz `$input['checklist'] = []` quando o campo não vem — substituição, nunca merge. Mesmo contrato destrutivo já catalogado nas configurações do módulo (UC-RSET-03) e nas peças (UC-JSP-02).
- **O que isto exige da tela:** a Page precisa reenviar o conjunto completo a cada submit. Quem mandar só o que mudou apaga o resto sem aviso — e o que se perde aqui é a prova de quais acessórios o cliente entregou junto com o aparelho.
- **Teste:** `JobSheetEditContratoTest` — *"UC-JSE-04: salvar sem enviar o checklist apaga o checklist da OS"* (o arranjo confere que o checklist **existia** antes, senão o vazio final não provaria nada).
- **Status: 🧪** _passou no CT 100_

## UC-JSE-05 · Sem permissão, a edição não existe
- **Persona:** usuário do negócio que pode consultar OS mas não pode alterá-las.
- **Aceite:** Dado usuário sem `job_sheet.edit` · Quando abro a edição **ou** submeto a alteração · Então **403** nas duas — e o dado da OS continua o original.
- **Por que o aceite confere o dado também:** um 403 na resposta que ainda assim gravasse seria pior do que um 200 honesto.
- **Teste:** `JobSheetEditContratoTest` — *"UC-JSE-05: usuário sem job_sheet.edit recebe 403 na edição"*.
- **Status: 🧪** _passou no CT 100; o dado da OS ficou o original_

## UC-JSE-06 · O custo estimado editado em pt-BR não infla (Tier 0 · VALOR)
- **Persona:** quem revisa o orçamento depois do diagnóstico e digita o valor em formato brasileiro.
- **Aceite:** Dado um custo digitado em formato brasileiro · Quando salvo a edição · Então o valor gravado é **idêntico** ao que `Util::num_uf` produz para a mesma entrada — dois caminhos independentes: (A) o valor lido do banco depois do PUT, (B) o parser chamado direto. E nenhuma entrada pode virar um número absurdo.
- **Por que a edição tem UC próprio, e não herda o da abertura:** são dois métodos distintos do controller, cada um com sua lista branca e sua conversão. O incidente de 2026-06-05 mostrou que o mesmo defeito de parser vive em cada caminho separadamente — corrigir um não corrige o outro.
- **Teste:** `JobSheetEditContratoTest` — *"UC-JSE-06: o custo estimado editado em pt-BR é gravado pelo parser canônico"*.
- **Status: 🧪** _passou no CT 100 — as 3 entradas pt-BR batem com o parser canônico_

---

## Rastreabilidade

| UC | Defendido por | Eixo |
|---|---|---|
| 01 | `JobSheetEditContratoTest` | Tier 0 — FSM (ADR 0143) |
| 02 | `JobSheetEditContratoTest` | Tier 0 — isolamento (ADR 0093) |
| 03 | `JobSheetEditContratoTest` | o que chega ao cliente |
| 04 | `JobSheetEditContratoTest` | contrato destrutivo da gravação |
| 05 | `JobSheetEditContratoTest` | permissão |
| 06 | `JobSheetEditContratoTest` | Tier 0 — VALOR (REGRA MESTRE) |

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
