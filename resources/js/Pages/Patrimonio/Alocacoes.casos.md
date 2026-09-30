---
casos: Patrimonio/Alocacoes — listagem de alocações do patrimônio (MWART, ADR 0104)
irmaos: Alocacoes.charter.md (lei) · memory/requisitos/AssetManagement/RUNBOOK-alocacoes.md (F1 PLAN)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Patrimonio/Alocacoes

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2 (ADR 0264): UC declarado sem teste citando o id = órfão.
> Testes que os defendem: [`Modules/AssetManagement/Tests/Feature/AlocacoesContratoTest.php`](../../../../Modules/AssetManagement/Tests/Feature/AlocacoesContratoTest.php) (leitura, UC-ALOC-01..04) ·
> [`Modules/AssetManagement/Tests/Feature/AlocacoesFormContratoTest.php`](../../../../Modules/AssetManagement/Tests/Feature/AlocacoesFormContratoTest.php) + [`tests/js/patrimonio-alocacoes-envio.test.tsx`](../../../../tests/js/patrimonio-alocacoes-envio.test.tsx) (escrita, UC-ALOC-06..08 — thread 18).

> **Por que quatro UC com id.** A onda 1 entrega a listagem migrada — e cada UC aqui tem teste
> que roda. Declarar de uma vez os cenários do protótipo (`patrimonio-page.jsx:409`) criaria
> órfãos e quebraria o G-2: o rodapé somado esbarra na REGRA MESTRE de quantidade, as
> contagens das pílulas pedem agregação nova, e alocar/editar/devolver dependem de formulários
> que hoje só existem como fragmento de modal jQuery. Eles ficam no `[BACKLOG]` abaixo — prosa
> honesta, sem id — e **viram UC na onda que trouxer o teste**.

> **Onde os testes rodaram:** CT 100 (`oimpresso-staging`, MySQL real), 2026-09-08 —
> nunca local ([`proibicoes.md §Ambiente`](../../../../memory/proibicoes.md)).

---

## UC-ALOC-01 · A listagem mostra as alocações da MINHA empresa, e só dela

- **Persona:** quem responde pelo patrimônio — precisa confiar que o rastro é da casa dele.
- **Aceite:** Dado dois businesses com alocações cadastradas · Quando o usuário do business A
  abre `/asset/allocation` · Então a listagem traz a alocação de A e **nenhuma** de B.
- **Teste:** `AlocacoesContratoTest.php` — **dois** `it()` citando `UC-ALOC-01`:
  1. o cenário direto (usuário de A não vê a alocação de B);
  2. **o espelho** — usuário de B, na mesma tela e mesma busca, **vê** a dele e **não** a de A.
     O espelho existe porque, sozinho, o `not->toContain` do primeiro também passaria se a
     alocação de B simplesmente não existisse. É o substituto do bite-test por mutação:
     remover o filtro de `business_id` provaria o mesmo, mas seria desligar proteção Tier 0.
- **Regressão que defende:** vazamento cross-tenant na listagem de alocações
  ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0).
  `AssetTransaction` **não tem global scope** — o isolamento é o filtro manual, e teste é o
  que impede alguém de removê-lo por engano.
- **Nota de método:** a busca do cenário casa o código das **duas** alocações de propósito —
  elas disputam a mesma página, então o isolamento é a única explicação possível para o
  resultado. Sem esse recorte o teste seria não-determinista: o CT 100 é base persistente e
  a paginação de 25 poderia jogar o fixture pra fora da primeira página, fazendo o teste
  passar por paginação em vez de por regra.
- **Status: 🧪**

---

## UC-ALOC-02 · A tela de Alocações é Inertia, no endereço que o [W] decidiu

- **Persona:** o próprio time — a migração precisa ser verificável, não afirmada.
- **Aceite:** Dado um usuário com o módulo assinado · Quando abre `/asset/allocation` · Então
  recebe **200** e a página Inertia é o componente **`Patrimonio/Alocacoes`**, com as props
  `filtros` e `permissoes`.
- **Teste:** `AlocacoesContratoTest.php` — `it()` citando `UC-ALOC-02`, com `assertInertia`.
- **Regressão que defende:** a URL **não muda** na migração (`allocation.index`,
  `GET /asset/allocation`), então status 200 sozinho não distingue "virou Inertia" de
  "continua Blade". O que distingue é o componente — e o assert do Inertia **verifica que o
  arquivo do componente existe no disco**. Defende também o endereço da
  [ADR 0394](../../../../memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md):
  mover a tela pra outra pasta quebra este teste.
- **Status: 🧪**

---

## UC-ALOC-03 · O recorte por situação é feito no servidor, não na página que já chegou

- **Persona:** quem quer ver só o que ainda está na mão de alguém.
- **Aceite:** Dado uma alocação **ativa** (nada devolvido) e outra **totalmente devolvida** ·
  Quando o usuário abre a aba **Ativas** · Então a página traz a ativa e **não** traz a
  devolvida; e quando abre **Devolvidas**, o resultado se inverte.
- **Teste:** `AlocacoesContratoTest.php` — `it()` citando `UC-ALOC-03`, exercitando os dois
  recortes na mesma execução.
- **Regressão que defende:** recorte implementado no cliente sobre a página corrente — que
  parece funcionar com poucos registros e mente com muitos, porque só enxerga as 25 linhas
  que já chegaram. O par (traz / não traz) vem junto pelo mesmo motivo do UC-01: um recorte
  que devolvesse ZERO linha satisfaria a negativa sozinho e passaria pelo motivo errado.
- **Nota de método:** o predicado vive em `HAVING`, não em `WHERE`, porque "devolvido" é uma
  agregação (`SUM` das transações filhas). Trocar por `WHERE` faria o recorte comparar a
  primeira linha filha em vez do total.
- **Status: 🧪**

---

## UC-ALOC-04 · A busca encontra a alocação pelo código, pelo bem ou pela pessoa

- **Persona:** quem procura "onde está a furadeira" ou "o que está com o João".
- **Aceite:** Dado duas alocações no mesmo business · Quando o usuário busca por um termo que
  casa só uma delas · Então a página devolvida traz **a que casa** e **não traz** a que não
  casa — valendo para o código da alocação e para o nome do bem.
- **Teste:** `AlocacoesContratoTest.php` — `it()` citando `UC-ALOC-04`.
- **Regressão que defende:** busca no cliente sobre a página corrente (mesmo vetor do UC-03),
  e perda de um dos campos buscáveis numa refatoração do `where` encadeado — o `orWhere` sem
  o closure de agrupamento é um clássico que vaza o filtro de tenant, e aqui o teste do UC-01
  e o deste caem juntos se isso acontecer.
- **Status: 🧪**

---

## UC-ALOC-06 · Alocar pelo drawer grava exatamente a quantidade e a data digitadas

- **Dado** um bem atribuível da minha empresa com saldo livre
- **Quando** abro `/asset/allocation/create` (botão **Alocar recurso**) e salvo o drawer
- **Então** o drawer manda a quantidade como `"2,5"` (vírgula decimal, **sem** milhar) e a data
  no formato da empresa (`30/09/2026 14:05`), e o banco grava `2.5000` e `2026-09-30 14:05`;
  pedido acima do saldo volta como **erro do campo quantidade**, vindo do servidor (trava da
  thread 02), e **nenhuma linha** nasce. O cliente não calcula saldo.
- **Teste:** dupla prova (REGRA MESTRE) — `patrimonio-alocacoes-envio.test.tsx` fixa as
  strings; `AlocacoesFormContratoTest.php` posta **as mesmas** e lê o banco. Três `it()` Pest
  citam `UC-ALOC-06` (drawer abre só com bens e pessoas do business · grava · saldo recusado).
- **Status:** 🧪 — vitest verde local; Pest aguarda a lane `assetmanagement-pest` (MySQL real).

## UC-ALOC-07 · Editar alocação abre o drawer só para alocação da minha empresa

- **Dado** uma alocação da minha empresa, uma de outra empresa e uma devolução minha
- **Quando** abro `/asset/allocation/{id}/edit` para cada uma
- **Então** a minha abre o drawer em modo editar com os valores gravados; a de outra empresa
  **e a devolução** dão 404 (antes, `findOrFail` sem tipo aceitava qualquer transação).
- **Teste:** `AlocacoesFormContratoTest.php` + vitest (PUT na rota certa, erro inline anunciado).
- **Foco devolvido** (a11y, 2026-09-30): fechar qualquer drawer devolve o foco ao botão que o
  abre — o da linha (Editar/Devolver) ou **Alocar recurso**, inclusive quando a página foi aberta
  direto pela URL. Medido em produção antes do conserto: foco no `BODY`. Teste:
  `tests/js/patrimonio-alocacoes-foco.test.tsx` (3 casos; sem o conserto, os 3 caem).
- **Status:** 🧪 — idem.

## UC-ALOC-08 · Devolver e Excluir devolução pelo drawer, com as duas pontas escopadas

- **Dado** uma alocação de 4 un. com 1 devolução minha **e** 1 devolução de outra empresa
  pendurada no mesmo `parent_id` (pré-condição do resíduo Tier 0 de `_saida-16b`)
- **Quando** abro `/asset/revocation/create?id=` (botão **Devolver** da linha)
- **Então** o drawer lista **só** a minha devolução (grão 1 : N), mostra 3 a devolver e aceita
  devolver até 3; devolver mais volta como erro do campo; o `asset_id` gravado é o **da
  alocação**, mesmo se o POST mandar outro; pai de outra empresa dá 404; **Excluir** apaga só
  devolução da minha empresa — id de alocação ou de outra empresa dá 404 e a linha fica.
- **Teste:** `AlocacoesFormContratoTest.php` (três `it()`) + vitest (confirmação antes do DELETE).
- **Status:** 🧪 — idem.

---

## Dívida declarada — "Devolvido" não é número auditado

⚠️ Não é UC porque **não é comportamento que esta onda defende** — é defeito herdado que ela
documenta em vez de esconder. O `leftJoin` de `asset_transactions as PT` por `parent_id`
(`AssetAllocationController::index()` `:70`), que alimenta `revoked_quantity`, **não filtra
`PT.business_id`**. Mesmo padrão do gêmeo catalogado em `_saida-01.md §9(a)` e confirmado pela
irmã Bens (`_saida-06-bens.md §5`), com thread dona.

Corrigir aqui esbarraria na REGRA MESTRE (mexer em quantidade exige prova por dois caminhos +
antes→depois + [W]) e em 1 PR = 1 intent. O que esta onda fez foi dar **um dono só** à
expressão (`AssetAllocationController::baseAllocationsQuery`), lida pelos **dois** ramos do
`index()` — antes ela existia inline no ramo do DataTables, e a próxima correção pousaria em
só um caminho. Teste de identidade: as 21 linhas da query, `diff` vazio.

⚠️ **Fato de 2026-09-11, superado:** este parágrafo dizia que não havia trava de saldo. A
thread 02 a pôs em `AssetAllocationService::criar()`, e em 2026-09-30 (thread 18) a recusa passou
a chegar ao drawer como erro do campo (UC-ALOC-06). **O que segue sem trava:** o
`atualizar()` (editar) — ele está no Service, `nao_toca` desta thread; declarado no `_saida-18`.

---

## [BACKLOG] — vira UC na onda que trouxer o teste

- [BACKLOG] O rodapé soma as unidades alocadas do recorte, com a prova dupla que a REGRA
  MESTRE exige para quantidade.
- [BACKLOG] As pílulas de situação trazem a contagem do **conjunto** (não da página corrente).
- [BACKLOG] A tela exige uma permissão `asset.*` própria, como a irmã Bens passou a exigir
  `asset.view` na thread 03. Hoje este controller tem só o gate de assinatura do módulo —
  a assimetria está declarada no §9 do RUNBOOK e é decisão [W].
- [BACKLOG] Ordenar por coluna (hoje a ordem é fixa: alocação mais recente primeiro, que é o
  default que o Blade já usava).
