---
slug: 0435-fluxos-por-modelo-porte-atividade
number: 435
title: "Fluxos por modelo (porte × atividade): a empresa escolhe um modelo e o modelo monta os processos no motor FSM"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-16"
module: core
tags: [fsm, vendas, producao, compra, entrega, financeiro, modelos, multi-tenant]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0129-state-machine-canonica-fsm-rbac
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0434-fsm-pedido-retomar-pagamento-fora-das-etapas
  - 0433-fonte-do-prototipo-passa-ao-git
  - 0093-multi-tenant-isolation-tier-0
pii: false
review_triggers:
  - "a tela Fluxos e regras (F3) entrar no ERP e precisar editar etapa ou regra de um processo já aplicado"
  - "um documento novo (venda, OS, compra) passar a nascer no processo do modelo da empresa em vez de venda_com_producao"
  - "uma regra 'planejado' ganhar side effect próprio no ERP"
---

# ADR 0435 — Fluxos por modelo (porte × atividade)

## Contexto

O motor FSM do ERP é tabular e por empresa ([ADR 0129](0129-state-machine-canonica-fsm-rbac.md)):
`sale_processes` → `sale_process_stages` → `sale_stage_actions` (+ papéis por ação). Ele está em
produção desde 2026-05-12 ([ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) e já serve
documento que não é venda (a `OrdemProducao` da Comunicação Visual usa `current_stage_id`).

Hoje cada empresa recebe o mesmo processo de venda, `venda_com_producao`: uma linha única que mistura
orçamento, produção, faturamento, pagamento e entrega. A [ADR 0434](0434-fsm-pedido-retomar-pagamento-fora-das-etapas.md)
corrigiu três travas dessa linha, mas ela continua igual para a gráfica de 3 pessoas e para a de 200.

O [W] desenhou no protótipo "Fluxos e regras" (`prototipo-ui/cowork/Wagner/fluxos-data.jsx`,
`fluxos-processos.jsx`, `fluxos-page.jsx` e `fluxos-modelos.casos.md`; fonte no git desde a
[ADR 0433](0433-fonte-do-prototipo-passa-ao-git.md)) um catálogo de **7 modelos** escolhidos por
porte e atividade. O `fluxos-modelos.casos.md` diz que essa tela é a "UI admin /admin/fsm" que a
ADR 0129 deixou para o futuro, e que os modelos são os templates a clonar.

## Decisão ([W] 2026-10-16)

> "O fluxo do pedido segue o protótipo Fluxos e regras — muito mais maleável para empresas de
> vários tamanhos."

1. **Modelo.** Um modelo é um template nomeado (`balcao`, `cv-pequena`, `cv-pcp`, `grafica`,
   `brindes`, `instalacao`, `oficina`), com: portes a que serve (`micro`, `pequena`, `media`,
   `grande`), atividades a que serve (`cv`, `rapida`, `offset`, `brindes`, `textil`, `instal`,
   `oficina`), os processos que monta e os casos de uso (UC-FLX-*) que cobre. O catálogo dos 7 é
   fiel a `fluxos-data.jsx` e mora em código, versionado.
2. **Processo por domínio.** Cada modelo é um conjunto de processos, um por domínio: `venda`,
   `producao`, `compra`, `entrega`, `financeiro`. Nem todo modelo tem todos (o Balcão só tem venda e
   compra). **A venda é simples** (ex.: Orçamento → Aprovado → Faturado → Entregue → Pago) e **a
   produção é um processo próprio** — a OS por item, com Pausada/Retomar/Retrabalho —, não etapas
   dentro da venda. No motor, o domínio fica gravado no próprio processo (`sale_processes`).
3. **Etapa com passo.** Cada etapa do modelo vira uma `sale_process_stage` na ordem do protótipo,
   com o passo que o app mostra (colunas `passo`/`passo_rotulo`/`passo_ordem` da ADR 0434 item 4). A
   primeira é a inicial e a última é terminal. Toda etapa não-terminal tem a ação de avançar para a
   seguinte. Cada processo ganha também a etapa lateral **Cancelado** (terminal, `is_cancelled`),
   como no processo "Orçamento e venda" da aba Processos do protótipo, com a ação de cancelar a
   partir de qualquer etapa não-terminal.
4. **Regra por etapa.** Uma regra tem o formato do protótipo — **ao entrar em <etapa> → efeito** —
   e aponta para um caso de uso (UC-FLX-*). Ela é ligada à ação cujo destino é aquela etapa e:
   - aponta para um **side effect que já existe no ERP** (`App\Domain\Fsm\SideEffects\*`), quando o
     efeito do protótipo é o mesmo que o side effect faz; ou
   - fica **`planejado`**: gravada na ação como dado (`side_effect_payload`), sem classe, sem
     executar nada. Uma regra nunca aponta para classe inexistente, e uma regra planejada nunca
     muda valor nem estoque.
   Quando o efeito existente só se parece com o do protótipo (ex.: `ConsumirEstoque` baixa a
   quantidade reservada, o UC-FLX-14 pede a consumida), a regra fica planejada, com o side effect
   candidato anotado, até alguém provar que servem ao mesmo caso.
5. **Modelo da empresa.** Cada empresa guarda porte, atividade e o modelo escolhido, numa tabela
   própria (não na `business`). A recomendação é o **encaixe** do protótipo: porte igual vale 2,
   porte vizinho 1, atividade igual 2 (0 a 4; 4 = recomendado, 3 = serve, 1–2 = parcial).
6. **Aplicar modelo.** Aplicar cria — ou reaproveita, se já existirem — os processos do modelo no
   motor daquela empresa, com etapas, passos, ações e papéis. É idempotente e Tier 0: só toca
   processos do `business_id` da empresa. Não apaga nem desativa processo de outro modelo.
7. **Legado.** `venda_com_producao` e os demais processos que existiam antes do catálogo formam o
   modelo **`legado`**. Empresa que usa esses processos fica no legado até escolher um modelo, e o
   legado continua funcionando como hoje. Escolher um modelo não apaga o legado.
8. **Documentos em andamento ficam na etapa onde estão.** Aplicar um modelo nunca altera
   `current_stage_id`, `process_id` nem histórico de documento. Venda, OS ou compra que já está num
   processo termina nele.

### O que da ADR 0434 continua valendo

- **Itens 1 e 2** (retomar a produção a partir de Em espera; entregar a partir de Faturada, com o
  pagamento como situação do título) **continuam valendo para o modelo legado**, que é onde estão
  as empresas de hoje.
- **Itens 4 e 5** (passos do app vindos do processo, inclusive da OS da Comunicação Visual)
  continuam e são a base do item 3 desta ADR: os modelos gravam o passo na etapa da mesma forma.
- **Item 3** (o PCP conclui a produção da venda) não é o caminho dos modelos: aqui a produção é um
  processo próprio, e o PCP vai ligar à OS por item (fase 2). A ADR 0434 não é editada; este
  parágrafo registra o que muda daqui pra frente.

## Fases

- **F1 (esta):** ADR, catálogo dos 7 modelos em código, modelo da empresa e "aplicar modelo".
- **F2:** OS por item, PCP ligado à OS e a regra "Aprovado cria OS por item" (UC-FLX-01).
- **F3:** tela Fluxos e regras no ERP (a UI admin da ADR 0129).
- **F4:** app mobile.

## Consequências

- Documento novo continua nascendo no processo que o código escolhe hoje (`venda_com_producao` por
  padrão em `SaleFsmActionController::startPipeline`). Fazer o documento novo nascer no processo do
  modelo da empresa é decisão da F2/F3, porque muda o que o vendedor vê no dia seguinte.
- Os processos de compra, entrega e financeiro dos modelos ficam cadastrados, mas nenhum documento
  desses domínios anda no motor FSM hoje. Eles servem de catálogo até o documento existir.
- O motor executa **um** side effect por ação. Regra do protótipo que precisa de dois (reservar e
  baixar no mesmo passo) fica planejada até o motor aceitar mais de um.
- A regra mestre de valor/estoque ([proibicoes.md](../proibicoes.md)) vale para quem tirar uma regra
  de `planejado`: dupla prova e antes→depois antes de ligar.

## Alternativas descartadas

- **Um processo único configurável por flags:** é o `venda_com_producao` de hoje com mais
  condicionais; não separa venda de produção, que é o centro do protótipo.
- **Migrar as empresas do legado automaticamente para o modelo recomendado:** trocaria o fluxo do
  balcão sem aviso. [W] decidiu que a empresa fica no legado até escolher.
- **Mover documentos em andamento para o processo novo:** o mapeamento etapa a etapa não existe
  para todos os modelos, e o histórico é append-only.
