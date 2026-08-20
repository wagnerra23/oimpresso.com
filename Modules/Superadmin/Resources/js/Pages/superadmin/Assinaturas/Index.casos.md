---
id: modules-superadmin-pages-superadmin-assinaturas-index-casos
casos: Superadmin · Assinaturas · /superadmin/superadmin-subscription
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a tela onde o vocabulário do banco e o vocabulário do negócio NÃO coincidem — `trial` não é status, `approved` significa duas coisas conforme a data, e `declined` não é cancelamento. Sem casos travando a tradução, a próxima sessão "simplifica" o mapa e a tela passa a reportar cobrança errada sem quebrar nada visivelmente.
owner: wagner
last_run: "2026-08-20"
last_run_ci: "_pendente_ — o trio nasce nesta onda (SA-O4a). O veredito por UC entra no manifesto quando a lane rodar; até lá o Status é 🧪, nunca ✅."
---

# Casos de Uso & Aceite — Superadmin · Assinaturas (`/superadmin/superadmin-subscription`)

> **Âncora:** UC-SA-008 e UC-SA-009 do F1 do Cowork §2 (`cowork-inbox/SUPERADMIN-F1-2026-08-18.md`)
> — que descrevem as **ações**, entregues na SA-O4b (UC-SAASS-11 a 16) — cruzados com as invariantes do
> [RUNBOOK-assinaturas](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-assinaturas.md)
> §1/§6 e com a exceção de multi-tenant da
> [ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
> Os UCs derivam do **contrato**, nunca do `Index.tsx` nem do controller.

---

## UC-SAASS-01 · A lista responde em Inertia, não em DataTables · `must`

**Dado** que sou superadmin autenticado
**Quando** abro `/superadmin/superadmin-subscription`
**Então** recebo Inertia com o componente `superadmin/Assinaturas/Index` — não a view Blade nem
o JSON do `DataTables::of(...)`.

Status: 🧪

---

## UC-SAASS-02 · Admin de negócio é barrado ENQUANTO o superadmin passa · `must` `[T0]`

**Dado** um usuário comum de um negócio qualquer
**Quando** ele acessa a rota
**Então** recebe **403** — e o mesmo teste prova que o superadmin recebe **200**.

> As duas metades no mesmo caso de propósito: um teste que só prova o 403 fica verde se a rota
> quebrar para todo mundo.

Status: 🧪

---

## UC-SAASS-03 · A tela é cross-tenant por desenho · `must` `[T0]`

**Dado** assinaturas de **dois negócios diferentes**
**Quando** o superadmin abre a lista
**Então** as duas aparecem — nenhum escopo de `business_id` é aplicado.

> É o inverso do resto do ERP, e por isso está escrito: sem este caso, a próxima sessão "corrige"
> a ausência de escopo e esvazia a tela.

Status: 🧪

---

## UC-SAASS-04 · O enum do banco nunca chega à tela · `must`

**Dado** assinaturas nos cinco estados do enum (`approved`, `waiting`, `declined`, `expired`,
`cancelled`)
**Quando** o payload da lista é montado
**Então** cada linha traz `situacao` em PT-BR (**Ativa · Pendente · Bloqueada · Vencida ·
Cancelada**) e **nenhum** valor cru do enum aparece em campo algum do payload.

Status: 🧪

---

## UC-SAASS-05 · `approved` significa duas coisas, e a data decide · `must`

**Dado** duas assinaturas `approved` — uma com `end_date` no futuro, outra no passado
**Quando** a lista é montada
**Então** a primeira sai como **Ativa** e a segunda como **Vencida**.

> É a armadilha central da tela: ler `status` sozinho reporta assinatura morta como viva.

Status: 🧪

---

## UC-SAASS-06 · "Em trial" é derivado de data, não de status · `must`

**Dado** uma assinatura `approved` com `trial_end_date` ainda no futuro
**Quando** os KPI são calculados
**Então** ela conta no KPI **Em trial** — e uma consulta por `status = 'trial'` não existe em
lugar nenhum do caminho.

Status: 🧪

---

## UC-SAASS-07 · Bloqueada não vira cancelada · `must`

**Dado** uma assinatura `declined` (bloqueio por inadimplência)
**Quando** os KPI são calculados
**Então** ela **não** entra em *"vencidas ou canceladas"*; sai num contador próprio
(`bloqueadas`), e a tela diz que ele está fora do recorte dos 4 KPI.

Status: 🧪

---

## UC-SAASS-08 · Ordenar é whitelist, não request · `must` `[T0]`

**Dado** um pedido com `ordem=` apontando para coluna fora da lista permitida
**Quando** a consulta é montada
**Então** cai no default (`subscriptions.id` desc) e **nada** do request chega cru ao `orderBy`.

Status: 🧪

---

## UC-SAASS-09 · Filtro combinado sobrevive à paginação · `should`

**Dado** filtros de pacote e status aplicados
**Quando** navego para a página 2
**Então** os dois filtros continuam na URL e no resultado — o total não muda ao paginar.

Status: 🧪

---

## UC-SAASS-10 · A lista não escreve · `must` `[T0]`

**Dado** uma assinatura `approved` com `end_date` no passado
**Quando** o superadmin apenas **abre** a lista
**Então** o `status` no banco continua `approved` — a tela mostra "Vencida" sem gravar nada.

> Marcar `expired` na leitura transformaria abrir uma tela em escrita em massa sem trilha.
> Quem grava é o `SubscriptionLifecycleService::expire()`, por sweep.

Status: 🧪

---

## UC-SAASS-11 · Toda escrita de status passa pelo Lifecycle Service · `must` `[T0]`

**Dado** uma assinatura **pendente** (`waiting`, sem datas)
**Quando** o superadmin aplica a ação **Aprovar**
**Então** ela vira `approved` **com `start_date` e `end_date` preenchidos**, calculados a partir
do `package_details`.

> É o que separa a onda do legado. O legado fazia `$sub->status = $input['status']; save()` —
> escrita direta, sem calcular vigência e sem trilha de transição. Uma assinatura aprovada sem
> `end_date` nunca vence e nunca entra na fila de cobrança.

Status: 🧪

---

## UC-SAASS-12 · Cancelar é append-only e não encurta a vigência (R3) · `must` `[T0]`

**Dado** uma assinatura vigente
**Quando** o superadmin cancela, escolhendo um motivo e escrevendo uma observação
**Então** o registro **continua existindo** (`deleted_at` nulo), o `end_date` fica **igual ao que
era**, e o motivo e a observação ficam gravados.

> R3 do F1: cancelar **para de renovar**, o acesso continua até o fim já contratado. "Melhorar"
> isso zerando `end_date` tira no ato um acesso que o cliente pagou.

Status: 🧪

---

## UC-SAASS-13 · Transição que não se aplica não diz que salvou · `must`

**Dado** uma assinatura já aprovada
**Quando** o superadmin aplica **Aprovar** de novo
**Então** nada muda no banco **e a tela informa que nada mudou**.

> O guarda do Service devolvendo `false` não é erro — é ele funcionando. O defeito seria a tela
> dizer "salvo" para uma escrita que não houve.

Status: 🧪

---

## UC-SAASS-14 · Ação fora da lista não chega ao Service · `must` `[T0]`

**Dado** um pedido com `acao` fora de `{aprovar, vencer, cancelar}`
**Quando** ele chega ao controller
**Então** a validação rejeita e **o status no banco não muda**.

Status: 🧪

---

## UC-SAASS-15 · Vigência invertida é barrada · `should`

**Dado** um pedido de edição com **fim anterior ao início**
**Quando** ele é enviado
**Então** nada é gravado e a tela diz o porquê.

> Vigência invertida é erro de digitação, não estado válido: gravada, ela faz a assinatura nascer
> vencida e sumir da fila de cobrança sem ninguém entender por quê.

Status: 🧪

---

## UC-SAASS-16 · Escrita é barrada para quem não é superadmin · `must` `[T0]`

**Dado** um admin de negócio
**Quando** ele tenta mudar status **ou** vigência de qualquer assinatura
**Então** é barrado — **e o dado no banco continua idêntico**.

> A segunda metade é o que prova: `302` sozinho não distingue "barrado" de "redirect de
> sucesso". O veredito é o dado intacto.

Status: 🧪

---

## Testes mínimos (do F1 §2)

- **DQE:** 1 assinatura pendente · 1 vencida · 1 cancelada · 1 em trial · 1 bloqueada.
- **Borda:** filtro que zera a lista (vazio cita o cruzamento); última página ao filtrar; nome de
  negócio longo (elipse, sem quebra de token).
- **Plural PT-BR:** 1 assinatura / 2 assinaturas · 1 pendente / 2 pendentes.
- **Mono nunca quebra:** `dd/mm/aaaa`, identificador de transação, valor.
- **Permissão:** admin de negócio 403; superadmin 200 (UC-SAASS-02).
- **Escrita:** cada caso de ação usa fixture PRÓPRIA, recriada do zero. Reusar as fixtures de
  leitura faria o resultado depender da ordem dos casos — e a base do CT 100 persiste entre
  execuções, então "roda limpo" não vale lá.
