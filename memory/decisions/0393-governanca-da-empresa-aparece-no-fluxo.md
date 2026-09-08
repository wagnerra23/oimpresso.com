---
slug: 0393-governanca-da-empresa-aparece-no-fluxo
number: 393
title: "Governança da empresa aparece no fluxo de trabalho, não em tela própria — emenda de FORMATO à 0392"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-08"
module: governance
tags: [fronteira, audiencia, formato, produto, governance, auditoria, multi-tenant]
supersedes: []
superseded_by: []
supersedes_partially: []
related:
  - 0392-fronteira-governance-audiencia-enforcement-na-concessao
  - 0366-fronteira-jana-forja-governance-kb
  - 0127-modules-auditoria-undo-activity-log
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
pii: false
---

# ADR 0393 — Governança da empresa aparece no fluxo, não em tela própria

> **Nasce `proposto`.** **O merge deste PR é o ato de ratificação (R10).** Número alocado por
> `next-id.mjs` ([ADR 0304](0304-alocacao-numero-ciente-trabalho-em-voo.md)).
>
> **EMENDA de FORMATO à [ADR 0392](0392-fronteira-governance-audiencia-enforcement-na-concessao.md),
> que fica INTACTA.** A 0392 respondeu *onde está a fronteira* e *onde mora o enforcement*. Ela não
> respondeu — porque a pergunta veio depois — **que forma o lado da empresa tem**. Esta ADR responde
> só isso, e não contradiz nenhuma decisão dela.

## Contexto

### C-1 · O que a 0392 deixou em aberto

A 0392 fixou três coisas: a audiência de uma tela é definida pela permissão que abre a porta; o
enforcement vive na **concessão** dessa permissão, não na leitura do request; e as três telas
ambíguas do Governance têm destino individual (D-C). O `Custos` recebeu ali a formulação *"sai do
plano de engenharia, ou ganha um par"* — uma disjunção deixada em aberto.

O que nenhuma das duas ADRs dizia é **onde a governança da empresa mora quando ela sai do plano de
engenharia**. A resposta não é óbvia, e as duas saídas são caras de errar: um módulo novo, ou
presença nas telas que já existem.

### C-2 · A pergunta e a resposta de [W]

**[W], 2026-09-07:** *"a governança será que deve ser por empresa? eu uso a governança para
programar e os clientes usariam para a empresa deles, aqui tem um conflito de interesses. acredito
que sirva para os dois."*

Colocada a escolha de formato — tela própria para o cliente, ou presença no fluxo — **[W],
2026-09-08, textual:** *"que apareça onde o trabalho acontece"*.

### C-3 · A decisão descreve o que o sistema já faz

Medido em `origin/main` = `3702777431` (2026-09-08). A governança da empresa **já** vive embutida,
cada peça dentro da tela onde a decisão é tomada:

| onde | o que mostra | âncora |
|---|---|---|
| `resources/js/Pages/Cliente/_drawer/AuditoriaTab.tsx` | timeline de alterações do cadastro, paginada, com export | `ClienteAuditoriaController` · [ADR 0127](0127-modules-auditoria-undo-activity-log.md) |
| `resources/js/Pages/Sells/_components/SaleAuditTrail.tsx` | edições, emissões fiscais e transições de estágio, dentro do drawer da venda | `sale_stage_history` · [ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) |
| `resources/js/Pages/Financeiro/Unificado/_components/FinAuditTrail.tsx` | trilha do título, dentro do próprio título | idem |
| `resources/js/Pages/Sells/_components/FsmActionPanel.tsx` | as ações que **este** usuário pode executar neste estágio | RBAC do FSM |

E a alçada segue o mesmo desenho: `Financeiro/Unificado/Index.tsx` filtra por `aprovacao_status`
(`pendente` / `aprovado` / `rejeitado` / sem workflow) na própria lista de títulos. A pergunta
*"o que espera minha aprovação?"* já se responde onde o trabalho está.

Nenhuma dessas capacidades exigiu uma tela de governança, e nenhuma delas foi construída sob esse
nome. O padrão existe; o que faltava era declará-lo.

### C-4 · Por que a alternativa é pior, e não só diferente

Um cockpit de conformidade por tenant teria de ser alimentado pelas mesmas fontes que já são lidas
no fluxo — `activity_log`, `sale_stage_history`, `aprovacao_status` — e passaria a competir com
elas por atenção. O usuário que precisa saber quem mexeu no preço está **no cadastro do produto**,
não numa tela de auditoria; obrigá-lo a trocar de contexto para obter um dado que cabe ao lado do
campo é custo sem contrapartida. Para PME, o formato embutido também dispensa treinamento: a
governança aparece quando é relevante, não quando alguém lembra de procurá-la.

## Decisão

### D-A · O lado da empresa é PRESENÇA, não superfície

Governança da empresa — trilha, alçada, permissão, histórico — aparece **dentro da tela onde a
ação acontece**, com `business_id` da sessão. Ela cresce por presença: mais trilha embutida onde
falta, selo de quem aprovou, aviso de alçada no ponto da ação.

### D-B · Não existe módulo, tela ou cockpit de "governança do cliente"

Fica **proibido** criar `Modules/GovernancaEmpresa`, aba nova no Governance para tenant, ou
dashboard de conformidade por empresa. É a proposta que renasce sozinha toda vez que alguém lê
*"a governança serve para os dois"*, e foi decidida contra por [W] nesta data.

Se um dia a agregação for necessária, ela nasce como **resumo de pendências dentro do fluxo que já
existe** — do jeito que o filtro de `aprovacao_status` já faz na lista de títulos — nunca como um
segundo cockpit.

### D-C · O `Custos` da 0392 §D-C ganha forma

A disjunção que a 0392 deixou aberta (*"sai do plano de engenharia, ou ganha um par"*) resolve-se
pela regra D-A: o custo de IA da empresa é dado do cliente e passa a aparecer **onde ela já olha
IA**. Uma leitura da plataforma, se necessária, nasce sem escopo de tenant e fechada, como manda a
0392 §D-A.

O destino do `Audit` e do `QualidadeIa` **não muda**: seguem exatamente como a 0392 §D-C decidiu.

### D-D · Esta ADR não constrói nada, e nada nasce sem sinal

Nenhum código é escrito por causa desta decisão. O padrão já está no ar nas quatro superfícies de
C-3, e estendê-lo para onde falta é trabalho dirigido por **sinal de cliente**
([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)) — não por esta ADR.

⚠️ A 0105 vale para o agente, nunca para [W] ([ADR 0382](0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md)):
pedido do dono é decisão, e nenhum agente pode opor "falta sinal" a ele.

## Opções consideradas

### A) Cockpit de conformidade por tenant (rejeitada)

Superfície nova, alimentada pelas fontes que já são lidas no fluxo, competindo com elas. Custo de
contexto para o usuário, custo de manutenção para nós, e — pela 0392 §D-A — exigiria uma família de
permissões própria só para existir. Rejeitada por [W] em 2026-09-08.

### B) Presença no fluxo (adotada)

Descreve o que o sistema já faz em quatro superfícies, com duas ADRs de âncora (0127, 0143). Custo
marginal de estender; nenhum de inaugurar.

### C) Deixar em aberto (rejeitada)

Manter a disjunção da 0392 §D-C significa que a próxima sessão que ler *"serve para os dois"*
proporá o cockpit — que é exatamente o que a D-B agora barra.

## Consequências

**O que melhora:** o `Modules/Governance` deixa de ter ambiguidade de audiência. Ele é
integralmente plano de engenharia, e a exceção cross-tenant do Art. 6+8 volta a ter fundamento
coerente — não há mais tela de cliente convivendo com ela por acidente.

**O que fica mais difícil:** governança embutida é mais trabalhosa de inventariar. Não há uma tela
para abrir e conferir "o que temos de governança para o cliente" — a resposta está distribuída, e o
inventário passa a depender de busca por componente. Aceito conscientemente: é o custo de o dado
estar onde a decisão é tomada.

**O que não decorre daqui:** nada sobre o conflito A×B da concessão que a 0392 registrou nas
Consequências dela. Aquilo segue aberto e é decisão [W] independente.

## O que NÃO muda

- Tudo o que a [0392](0392-fronteira-governance-audiencia-enforcement-na-concessao.md) decidiu
  permanece: a audiência definida pela permissão, o enforcement na concessão, e o destino de
  `Audit` e `QualidadeIa`.
- A exceção cross-tenant do Governance (Art. 6+8) **permanece** para o plano de engenharia.
- `Modules/Governance` **não** é deprecado, absorvido nem consolidado (lápide §5 2026-07-31).
- `Modules/Auditoria` **não** é absorvido nem movido — a lápide §5 de 2026-07-30 já o classificou
  como capacidade de negócio, e esta ADR o confirma como um dos donos do padrão.
- Nenhuma tela é criada, movida ou consertada por esta ADR.

## Recibos

Medições em `origin/main` = `3702777431` (2026-09-08), repo não-raso.

| afirmação | como reproduzir |
|---|---|
| as 4 superfícies embutidas existem | `git cat-file -e HEAD:<path>` nos quatro paths de C-3 |
| alçada na lista de títulos | `git grep -c "aprovacao_status" HEAD -- resources/js/Pages/Financeiro/Unificado/Index.tsx` |
| trilha do cliente tem ADR própria | [0127](0127-modules-auditoria-undo-activity-log.md) citada no docblock do `ClienteAuditoriaController` |
| trilha da venda vem do FSM | [0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) · tabela `sale_stage_history` |
| a 0392 não cobre formato | `grep -ci "fluxo\|embutid\|AuditoriaTab\|SaleAuditTrail"` no arquivo da 0392 devolve 0 |
| decisão [W] | citação textual em C-2, sessão de 2026-09-08 |
