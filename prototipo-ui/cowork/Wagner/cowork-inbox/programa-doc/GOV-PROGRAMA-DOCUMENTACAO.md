---
id: reference-gov-programa-documentacao
name: Governança — O programa de documentação (Trilha D)
description: O ciclo que mantém a documentação técnica e operacional viva — descobrir, medir, traduzir, publicar, operar, detectar drift, aprender e medir de novo. Onze estações, onze ondas, e a regra de que estado de execução nunca mora em markdown.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-06"
nav_group: governanca
nav_order: 40
lente: [operar, construir]
---

# Governança — O programa de documentação (Trilha D)

> **Não é escrever documentação. É manter um sistema que mede, traduz, publica, opera, detecta
> drift e aprende.** Autorizado por [W] em 05/08/2026; ciclo completo em 06/08/2026.

O plano dono é
[`PLANO-MESTRE.md` § Trilha D](../requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md) —
esta página é a leitura humana dele. Se divergirem, **o plano manda**.

## O que a trilha não faz

Ela **não cria** índice, roadmap, agente, gate ou cópia HTML. Reusa o que já existe: o inventário
derivado, os donos documentais no git, as tasks MCP, o workflow `documentacao-tecnica` e a rota
humana `/documentacao`. Documentação nova que exige máquina nova é sinal de que o escopo escorregou.

## O ciclo — onze estações

1. **Descobrir** — máquina, hook, MCP, módulo ou fluxo.
2. **Medir o estado real** — abrir a fonte e a configuração, rodar o probe, guardar o **ID estável** do achado.
3. **Classificar e localizar o dono** — qual camada, e qual arquivo já responde por ela.
4. **Priorizar o gap** — criticidade × impacto. Achado adjacente vira task nova, não desvio.
5. **Documentar no dono existente** — nunca um resumo paralelo.
6. **Validar tecnicamente** — fonte, links, diagrama, dependências, tenant, PII, ausência de segredo.
7. **Validar operacionalmente** — executar o runbook no ambiente certo; `last_validated` só muda quando o resultado real bateu.
8. **Publicar** — PR de **uma** intenção; [W] ratifica pelo merge; a rota humana atualiza no deploy ou no `quick-sync`.
9. **Operar e observar** — o doc em uso, com health-check e vital-signs por trás.
10. **Incidente ou drift** — a realidade discorda do que está escrito.
11. **Aprender e corrigir** — runbook, lição ou decisão — **e voltar pra estação 2.**

O ciclo **não termina em publicar**. É isso que separa o programa de uma campanha de escrita: a
estação 11 devolve o aprendizado à medição, e a próxima volta começa sabendo mais.

## O escopo — seis camadas

| Camada | O que entra | Dono |
|---|---|---|
| **Infraestrutura** | Hostinger, Proxmox, CT 100, GitHub Actions, Windows/Firebird, router, Tailscale, PBX, SVN, dispositivos | `reference/infra-*.md` + `Infra/RUNBOOK-*.md` |
| **Plataforma** | hooks, MCP, CI, skills, agents, scripts, baselines, observabilidade | índices gerados + arquitetura/runbooks Jana, Forja e Infra |
| **Aplicação** | kernel, módulos transversais, verticais, integrações | `SCOPE` · `BRIEFING` · `SPEC` · `SUPERFICIE` · `ARCHITECTURE` · `RUNBOOK-*` |
| **Fluxos** | venda, estoque, financeiro, fiscal, WhatsApp, Jana, migração, deploy, recuperação | [`GUIA-DO-SISTEMA.md`](../GUIA-DO-SISTEMA.md) aponta; o detalhe fica no dono |
| **Operação** | acesso, monitoramento, manutenção, backup, restore, rollback, incidentes | runbooks de operação + auditoria Ops/DR |
| **Visão humana** | a rota `/documentacao`, autenticada | renderiza o Guia |

**Fora de escopo:** documentação de produto por tela, cópia manual de inventário, reescrita de ADR
aceita, criação de máquina de governança, e corrigir achado adjacente no meio de outra etapa.

## O caminho de cada tipo

**Máquinas** — `inventário → arquitetura → acesso → operação → monitoramento → backup → restore → incidente`.
Cada máquina crítica declara função e responsável, serviços e dados, dependências, configuração
versionada, acesso (sem copiar segredo), probe de saúde, deploy/restart/rollback, backup com RPO e
RTO, falhas conhecidas e a última validação com evidência.

**Hooks** — `arquivo → índice gerado → família humana → cenário de bloqueio → troubleshooting`.
Responde quando dispara, que risco protege, se bloqueia ou alerta, que entrada examina, que mensagem
produz, como provar que morde e solta, e como diagnosticar falso positivo. **Não se escreve uma
segunda lista de hooks** — o índice gerado continua sendo o inventário.

**MCP** — `Git canon → sincronização → banco/cache → servidor CT 100 → autenticação → tool → auditoria`.
Cobre fronteiras, catálogo derivado das tools, tokens e papéis, isolamento por `business_id`, drift,
deploy e reload, health check, 401/403/404, reindexação, auditoria e o on/offboarding do time.

**Módulos** — as portas documentais aplicáveis, nunca uma lista manual concorrente: `SCOPE.md`
(responsabilidade e limites), `BRIEFING.md` (estado e capacidade), `SPEC.md` (requisitos),
`SUPERFICIE.md` (retrato derivado do código), `ARCHITECTURE.md` (quando houver integração relevante)
e `RUNBOOK-*.md` (operação e recuperação).

**Fluxos** — ator e ponto de entrada, máquinas e módulos atravessados, dado transportado, auth e
autorização, `business_id`, transação e idempotência, filas/retry/timeout, logs e alertas, falha
parcial, compensação ou rollback, e o procedimento de recuperação.

## As ondas

`D0` fundação · `D1` infra crítica · `D2` plataforma · `D3` MCP ponta a ponta · `D4` módulos
críticos · `D5` verticais e integrações · `D6` legado e rede local · `D7` fluxos transversais ·
`D8` continuidade · `D9` publicação e onboarding · `D10` manutenção contínua.

Ordem interna: kernel e transversais críticos → plataforma → verticais → integrações → legado. Uma
onda avança até o próximo bloqueio humano e para — não se abre trabalho paralelo pra esconder
dependência.

## Onde o estado mora — a regra que mais dói quando ignorada

| Estado | Fonte única |
|---|---|
| Intenção, ondas e DoD | o plano mestre — **um único** `## Status vivo` |
| Execução (`todo/doing/done`) | tasks MCP, `parent_plan=programa-ondas` |
| Fatos técnicos e procedimentos | os documentos donos no git — **ponteiro > cópia** |
| Inventários | `PAINEL-SISTEMA` + `MAQUINAS-INVENTARIO`, sempre derivados |
| Prova de correção | `documentation-loop` — o mesmo ID precisa sumir no recibo antes→depois |

Segredo nunca aparece como valor: só como ponteiro pro Vaultwarden. E **alteração de data não fecha
achado** — se o ID continua no recibo, o trabalho não aconteceu.

## O batimento é advisory, de propósito

Mudança em PR mostra os donos afetados; o batimento agendado atualiza os retratos derivados; a
revisão semanal oferece a fila de drift; o workflow executa **um** item por vez; `plan-health` acusa
plano velho. Nada disso decide conteúdo nem merge — detectar e oferecer é o trabalho da máquina;
escolher é humano. "Manter ativo" significa haver task aberta e achado sendo consumido, não mais um
gate.

## Quando a trilha termina

Quando toda máquina crítica tem dono, probe e runbook validado; hooks e tools MCP estão explicados
por família; cada módulo alcança suas portas; cada fluxo crítico declara auth, tenant, falha e
recuperação; runbooks críticos têm `owner` e `last_validated` sustentados por execução; segredo é só
ponteiro; `/documentacao` navega por tudo; os detectores rodaram de novo e o resíduo está fechado ou
justificado — e **um incidente já gerou aprendizado e voltou ao início do ciclo**. Esse último é a
prova de que a máquina gira, não de que alguém escreveu bastante.

## Onde isto **não** mora

Em que onda a trilha está, o que está `doing`, o que fechou — é **estado vivo** e vem das tasks MCP
(`parent_plan=programa-ondas`, [ADR 0070](../decisions/0070-jira-style-task-management-current-md-removed.md)),
nunca deste documento. Ler status daqui é ler o saldo bancário num extrato da semana passada.
