---
slug: 0345-topicos-vivos-aprendizado-por-critica-revisada
number: 345
title: "Tópicos vivos e aprendizado por crítica independente revisada"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-21"
module: governance
related:
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0093-multi-tenant-isolation-tier-0
  - 0230-metodo-governance-scorecard-grade-10
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0314-poda-gates-onda-2-lei-fusoes
accepted_via: "Wagner 2026-07-21 no chat: concordo, pode seguir isso em PR"
---

# ADR 0345 — Tópicos vivos e aprendizado por crítica independente revisada

## Contexto

O BRIEFING acumulava resumo, lista de arquivos, requisito, opinião e histórico. Isso dificultava localizar o dono de uma reclamação e fazia fatos derivados apodrecerem. Em paralelo, o scorecard de função corria o risco de transformar leitura do código em opinião circular: o código prova o comportamento existente, mas não prova sozinho que esse comportamento é desejado.

## Decisão

1. `memory/requisitos/<Modulo>/BRIEFING.md` ficou definido como **resumo e índice**. Ele aponta para `SCOPE.md`, `SUPERFICIE.md`, `SPEC.md`, charters/casos e tópicos; não recopia esses conteúdos.
2. Um tema estável passou a ter uma unidade própria em `memory/requisitos/<Modulo>/topicos/<id>.md`, criada pelo template e validada por `topico.schema.json` em modo grace/forward-only.
3. Cada tópico separa comportamento observado, intenção externa, parecer, benefícios/custos de fazer e de não fazer, evidências, contradições e histórico de revisão.
4. O aprendizado durável do sistema passou a ser o ciclo:
   - críticos de IA propõem afirmações e objeções independentes;
   - a IA central reconcilia evidências, duplicações e conflitos;
   - o humano aprova ou rejeita decisões arquiteturais/produto;
   - o resultado aprovado entra em Git como tópico, ADR, SPEC, charter/caso ou teste;
   - hooks e gates detectam drift e regeneram somente fatos determinísticos.
5. A IA central é **sintetizadora**, não oráculo. Ela não apaga parecer minoritário e deve usar `incerto` quando falta contrato/intenção.
6. Parecer persistente por função ficou limitado a risco relevante: valor/estoque, tenant, escrita em banco, API pública, segurança/compliance ou alto fan-in.

## O que “treinamento” significa neste repositório

- **Não altera pesos do modelo:** treinamento/fine-tuning do modelo-base ocorre fora do repositório e não é produzido por este loop.
- **Contexto temporário:** a IA aprende durante uma tarefa enquanto o contexto está carregado; isso some ao encerrar a sessão.
- **Memória organizacional durável:** Git, schemas, ADRs, tópicos, testes e índices fornecem conhecimento recuperável para qualquer IA futura.
- **Evolução por avaliação:** ideias novas entram como propostas com proveniência e só viram canon após revisão. Rejeições permanecem registradas para evitar regressão.

Fine-tuning não foi escolhido para fatos do produto que mudam rápido. Recuperar o canon atual do Git e avaliar contra testes/contratos oferece atualização, auditoria e rollback melhores.

## Consequências positivas

- Uma reclamação pode resolver módulo → tópico → tela/model/função/teste por âncoras estruturadas.
- Críticas independentes aumentam a chance de encontrar premissas erradas e ideias novas.
- `incerto` impede que ausência de contexto vire bug inventado.
- A evolução fica auditável: quem propôs, quem revisou, evidência e decisão permanecem no histórico.

## Custos e riscos

- Mais arquivos pequenos exigem índice e convenção rigorosos.
- Várias IAs podem gerar volume sem qualidade; por isso crítica sem evidência não é promovida.
- Uma IA central pode criar gargalo ou viés de síntese; pareceres divergentes não podem ser apagados.
- Hooks LLM autônomos podem corromper o canon; por isso só fatos determinísticos são gravados automaticamente, e prosa entra como proposta revisável.

## Alternativas rejeitadas

- **Um BRIEFING grande por módulo:** simples no início, mas mistura donos e apodrece por duplicação.
- **Toda IA escreve canon diretamente:** rápido, porém sem autoridade, proveniência e rollback semântico.
- **Fine-tuning contínuo com documentos do repo:** caro, opaco e stale para conhecimento que muda por PR.
- **Parecer sobre toda função:** cria ruído e carimbo; risco determina o escopo persistente.

## Implantação

O schema de tópico nasceu em grace e forward-only. O piloto começou em Produto; não houve backfill em massa. Promoção a required exige falso-positivo zero no piloto e nova decisão humana, conforme ADR 0314.
