---
slug: 0379-bundle-design-transacao-manifesto-delta-staging
number: 379
title: "Bundle Design vira transação com manifesto, delta, staging e inventário de aplicação"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-23"
module: governance
tags: [design, cowork, protocolo, manifesto, delta, staging, rollback, react]
supersedes: []
superseded_by: []
related:
  - 0239-design-system-ssot-git-fluxo-bidirecional
  - 0282-protocolo-v2-colapso-ratificacao
  - 0315-design-sync-governanca-fonte
  - 0336-cowork-recepcao-em-duas-camadas
  - 0374-cowork-mirror-pull-direto-sem-transcricao
---

# Bundle Design vira transação com manifesto, delta, staging e inventário de aplicação

> Nasce `proposto`; a ratificação é o flip próprio previsto pela convenção da
> [ADR 0257](0257-adr-status-lifecycle-kind-modelo-canonico.md). A autorização de implementação
> foi dada por [W] em 2026-08-23; o merge do flip continua sendo o ato formal.

## Contexto

O transporte anterior sabia juntar JSONs e verificar bytes por arquivo, mas não tinha estado
base/alvo, sequência obrigatória de partes nem fronteira transacional. Em lote completo, a
escrita ainda podia acontecer arquivo por arquivo. Também faltava responder operacionalmente:

- o consumidor baixa tudo ou somente o que mudou?;
- quais arquivos foram adicionados, modificados e removidos?;
- qual fonte do Design alimenta qual Page React e qual módulo?;
- a mudança foi apenas recebida, foi aplicada ou também foi testada?;
- uma evidência antiga ainda vale depois de mudar a fonte ou o alvo?

O problema ficou mais visível nos mapeamentos 1:N de Superadmin e Officeimpresso, importados de
Blade e transformados em React: pousar `officeimpresso-page.jsx` no espelho não prova que todas
as Pages correspondentes receberam a modificação.

## Decisão

**D-1 — Manifesto do estado-alvo.** Cada bundle declara todos os arquivos do estado final com
path, papel, bytes e SHA-256. A parte 01 carrega o manifesto; todas as partes repetem identidade,
base e cardinalidade. Sequência incompleta ou lote misto é recusado.

**D-2 — Snapshot uma vez, delta depois.** Sem base, o produtor emite snapshot. Com o manifesto
anterior, emite delta: somente `added/modified` carregam chunks; `deleted` é declaração de
remoção de arquivo já pertencente ao bundle; `unchanged` não trafega. O manifesto completo
emitido vira a base verificável da próxima rodada.

**D-3 — Validação integral antes da promoção.** O consumidor remonta os chunks, verifica SHA-256,
base ativa, estado-alvo e fechamento do grafo em staging. Depois troca os quatro roots do
protocolo: espelho Cowork, design-docs, runtime de preview e estado. Falha no swap executa
rollback dos roots já promovidos. Dry-run monta e valida o mesmo staging, mas não promove.

**D-4 — `_ds` continua derivado.** `_ds` serve a execução do preview e pode ser reconstruído;
não é fonte, histórico, base do delta nem prova de conclusão. O estado durável mora em
`scripts/design-sync/state/`: manifesto ativo, relatório de aplicação e ledger de evidências.

**D-5 — Recepção e aplicação são estados diferentes.** Após promover o transporte, o detector
gera inventário fonte → alvo React → módulo, preservando 1:N. Destino órfão/ambíguo bloqueia a
aplicação no produto, embora a fonte recebida não seja descartada. Remoção no Design nunca apaga
automaticamente a Page React.

**D-6 — Evidência é vinculada a hashes.** Marcar uma Page como aplicada registra hash da fonte,
hash do alvo, referência auditável e testes. Se fonte ou alvo mudar, a prova não casa e o
relatório volta a tela para pendente. Assim “aplicado” deixa de ser memória de conversa.

**D-7 — Compatibilidade é assimétrica.** Lote legado completo passa pela mesma transação; modo
legado parcial permanece somente como ponte de compatibilidade e não ganha semântica de estado.
Novos produtores usam exclusivamente o schema v2.

## Consequências

**Positivas:** transferência incremental, corrupção e parte ausente falham antes de qualquer
efeito; há rollback testável; a lista do que mudou e do que falta fazer é materializada; módulos
1:N deixam de depender de lembrança; evidência stale se invalida sozinha.

**Custos:** o snapshot inicial ainda baixa o estado inteiro; o consumidor usa espaço temporário
para quatro stagings; aplicação semântica no React continua exigindo trabalho de produto e teste;
o ledger precisa entrar no PR para que a prova sobreviva à máquina local.

**Rollback da decisão:** o código v2 pode ser revertido mantendo o leitor legado, mas bundles
v2 já emitidos deixam de ser consumíveis. O estado em `scripts/design-sync/state/` é preservável
e não depende do cache `_ds`.
