---
slug: 0431-pcp-sai-do-desejo-modules-producao
number: 431
title: "PCP sai do estado de desejo: nasce Modules/Producao como camada única de produção"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-15"
module: pcp  # vocabulário fechado do adr.schema (MODULE_VALIDOS); o módulo de código é Modules/Producao
quarter: 2026-Q4
tags: [pcp, producao, apontamento, cross-vertical, modulo-novo, feature-wish]
supersedes: []
supersedes_partially:
  - 0152-modules-pcp-feature-wish
superseded_by: []
related:
  - 0152-modules-pcp-feature-wish
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0093-multi-tenant-isolation-tier-0
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0024-instalacao-1-clique-modulos
pii: false
review_triggers:
  - "o piloto biz=1 (WR2) não apontar produção em 30 dias depois da virada (thread 11)"
  - "a PWA do operador falhar a câmera em mais de 2 aparelhos (D2 → avaliar Capacitor)"
  - "Repair, ComunicacaoVisual ou OficinaAuto passarem a gravar apontamento próprio (camada única quebrada)"
---

# ADR 0431 — PCP sai do estado de desejo: nasce `Modules/Producao`

## Contexto

A [ADR 0152](0152-modules-pcp-feature-wish.md) deixou o PCP como feature-wish: sem sinal de
cliente, sem scaffold, e com a decisão D1 (`Modules/Pcp` novo × reaproveitar
`Modules/IProduction`) adormecida até um piloto existir.

Em 2026-10-15 o Cowork entregou o pedido de Produção para Comunicação Visual (handoff 55,
importado no [PR #9433](https://github.com/wagnerra23/oimpresso.com/pull/9433)), com protótipo
das vistas `pcv-*` e o contrato `Producao.casos.md` (UC-PCV-01..30). [W] respondeu todas as
decisões no mesmo dia, textual: *"eu autorizo tudo, nome da pasta 'Producao' · e sim quero PCP e
autorizado fazer"*. Onde disse "autorizo tudo", vale a recomendação já escrita na proposta
([ADR-PROPOSAL](../requisitos/Producao/ADR-PROPOSAL.md)). O registro das respostas está em
`prototipo-ui/cowork/Wagner/cowork-inbox/producao/playbook/_DECISOES-W-2026-10-15.md`.

Pela [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md), pedido do dono é decisão, não
proposta ([ADR 0382](0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md)): a falta de
cliente pagante que sustentava a 0152 não trava trabalho dirigido por [W].

## Decisão

1. **O PCP deixa de ser feature-wish.** Esta ADR substitui a 0152 **só no ponto do PCP**
   (`supersedes_partially`). O resto da 0152 — os princípios P1–P3 e o registro de por que se
   esperou — fica como história.
2. **D1 = `Modules/Producao`**: módulo novo, prefixo de rota `/producao`, permissões
   `producao.*`, chave de sistema `producao`. Não `Modules/Pcp`, e não renomear o `IProduction`.
3. **`Modules/IProduction` já não existe**: foi removido no
   [PR #842](https://github.com/wagnerra23/oimpresso.com/pull/842) (prototipos não ativos). Nada a
   apagar aqui; a prova "ausente" do playbook já passa.
4. **A documentação do domínio muda de pasta**: `memory/requisitos/Pcp/` →
   `memory/requisitos/Producao/`, porque a máquina de ativação de módulo exige a pasta com o nome
   do módulo (`documentation-loop.mjs --enforce-activation`). Os IDs `US-PCP-*` ficam como estão.
   O rename entra como classe A em `governance/ghost-rename-map.json`, que é por onde o
   deadlink-gate resolve link de ADR imutável para a pasta antiga.
5. **D2–D5 e as decisões da 2ª rodada** valem como respondidas em 2026-10-15: PWA; cronômetro
   híbrido com padrão automático; capacidade e custo-hora configuráveis por empresa com valores
   sugeridos; gargalo por regra simples na V1; permissões `producao.etapa.mover` e
   `producao.desempenho.ver`; Produção é a **camada única de PCP** e CV, Repair e Oficina seguem
   donos da OS; piloto biz=1 e depois um cliente de CV; turno configurável (padrão 08h–18h);
   aviso ao próximo dentro do app; reserva de material no orçamento aprovado; aprovação de arte no
   link `/os/{codigo}` do Repair; encaixe de mídia na versão 2.
6. **Esta ADR só abre o módulo.** O esqueleto nasce sem tabela, sem tela e com o pacote do
   superadmin `default => false`. O fundo (thread 02) e as telas (03–10) vêm em PRs próprios,
   na ordem do ROADMAP.

## Multi-tenant Tier 0

Toda tabela `pcp_*`/`producao_*` que as threads seguintes criarem nasce com `business_id`
indexado + FK + global scope ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)); apontamentos e
eventos são append-only; job assíncrono recebe `$businessId` no construtor. Esta ADR não cria
tabela nenhuma.

## Consequências

- Some o estado "módulo PCP sem casa": a decisão D1 deixa de bloquear.
- Quem ler a 0152 vai encontrar links para `requisitos/Pcp/`. Eles resolvem pela entrada classe A
  do rename-map; o corpo da 0152 não é editado (append-only).
- O espelho do Cowork (`prototipo-ui/cowork/Wagner/cowork-inbox/producao/`) ainda cita
  `memory/requisitos/Pcp/`. O espelho não se edita aqui; a correção é pedido ao Cowork, declarado
  no `_saida-01.md`.

## Alternativas descartadas

- **Manter os docs em `requisitos/Pcp/` e criar `requisitos/Producao/` só com os arquivos que a
  ativação pede**: daria dois SPECs para o mesmo domínio.
- **Renomear para `Modules/Pcp`**: [W] escolheu `Producao`.
