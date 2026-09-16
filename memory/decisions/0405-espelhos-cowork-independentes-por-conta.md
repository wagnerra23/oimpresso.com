---
slug: 0405-espelhos-cowork-independentes-por-conta
number: 405
title: "Espelhos Cowork independentes por conta, unicidade dentro de cada dono"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-16"
module: governance
tags: [design, cowork, importacao, espelho, isolamento]
supersedes: []
supersedes_partially: [0398-espelho-cowork-recebe-a-arvore-da-conta]
superseded_by: []
related:
  - 0404-ultimo-importado-e-autoridade-do-espelho
  - 0397-prototipo-minimo-por-dono-e-ds-direto
pii: false
---

# ADR 0405 — espaço independente para Wagner e Felipe

## Decisão expressa

Em 2026-09-16, após a importação completa encontrar 23 pares iguais entre contas, [W] determinou: **"não apague o do felipe, arrume um jeito de os dois ter o espaço necessario"**.

- `prototipo-ui/cowork/Wagner/` e `prototipo-ui/cowork/Felipe/` são namespaces independentes. Bytes iguais entre esses dois donos são permitidos, sem fundir caminhos ou retirar arquivos de outra conta.
- R4 continua recusando dois caminhos com os mesmos bytes **dentro da mesma conta**, inclusive quando existe uma terceira cópia na outra conta. Duplicação envolvendo `design-system/` permanece proibida; esta emenda não cria dois DS canônicos.
- Cada conta tem seu estado-base de bundle. Wagner conserva `scripts/design-sync/state/`; Felipe recebe `scripts/design-sync/state/Felipe/`. Uma importação de Felipe não substitui o manifesto ativo nem os recibos de Wagner.
- A limpeza obedece à ADR 0404 **por conta**. Somente um manifesto autenticado com `mirrorScope: tree` autoriza retirar sobras nunca gerenciadas. Um shell parcial não prova que os playbooks desapareceram da origem.
- A escolha do dono permanece explícita e sujeita à procedência. Duplicação entre contas não torna ambas autoridade de uma mesma Page de produção: charter/mapa continuam escolhendo o dono da fonte.

Esta emenda substitui a proibição global de duplicatas **somente no par de contas independentes**. Os fatos históricos da ADR 0398 foram preservados.

## Execução e limites

A recepção do ZIP de Wagner passou a gerar `--full-tree`, incluindo playbooks/contratos fora do shell, sem realimentar `sync/`. O consumidor v2 aceita `--owner Wagner|Felipe`; omissão preserva a compatibilidade com Wagner. Felipe exige v2, recusando payload legado parcial nessa opção. A recepção de ZIP continua usando a identificação de conta do protocolo; não se inventou um ID externo de projeto para Felipe.

O gate de fonte única passou a validar a visão de staging antes da promoção. Testes cobriram poda, inclusão, atualização, estado independente, base do delta, rollback e preservação do outro dono.

O ZIP 20 foi aplicado localmente: 695 fontes importáveis + 7 entradas de cache DS no manifesto. A árvore Wagner foi verificada sem sobras, ausências ou divergência de bytes. Os 24 arquivos reais de Felipe conservaram o fingerprint completo. Nenhum ZIP novo de Felipe foi fornecido ou importado nesta sessão; o espaço de estado está preparado, não se afirmou paridade com um export de Felipe não auditado.
