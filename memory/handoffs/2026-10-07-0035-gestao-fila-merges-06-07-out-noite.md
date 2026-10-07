---
date: "2026-10-07"
time: "00:35 BRT"
slug: gestao-fila-merges-06-07-out-noite
tldr: "Gestão da fila de merges 06/10 noite → 07/10 madrugada. Fila do ERP e do app terminou vazia, ~45 PRs mergeados. Dois incidentes causados pela própria fila: o #8825 reverteu trabalho no main (restaurado no #8858) e o #8857 (--no-dev) derrubou o oimpresso.com com 500 por ~2h35 (recuperado à mão no Hostinger, revertido no #8880). Ferramentas da fila consertadas."
prs: [8811, 8813, 8817, 8824, 8825, 8834, 8836, 8843, 8847, 8849, 8853, 8857, 8858, 8870, 8872, 8873, 8874, 8876, 8877, 8880]
---

# Handoff — Gestão da fila de merges (06/10 noite → 07/10 madrugada)

Sessão "Gerente da fila de merges (sucessor de 06/10 tarde)". Autorização [W]: "gerencie o merge de todos, use o que precisar, não me pergunte", com as exceções de valor, migration destrutiva, cutover e baseline.

## Estado ao fechar (~03:35Z de 07/10)

- **Fila do ERP e do app: vazia.** Site no ar (`/login` 200); último deploy completo com sucesso (run 37569027159).
- **Aprovados pelo [W] e mergeados:** #8817 Comissionados (sem flag), #8824 Fator R, #8836 nota com itens reais, #8843 converter cotação, #8834 (aprovado como code owner por pedido dele), #8853 Nova receita, #8857 `--no-dev` (depois revertido), #8870 Etiquetas sem flag, #8874 motor ICMS-ST/FCP/DIFAL, #8876 SUPERFICIE só nos módulos tocados, #8877 relatório de comissão (`?tela=nova`).
- **Fila de "registra envio" ao Cowork: zerada.** Todas as threads da leva registraram, uma por vez.

## Incidente 1 — o #8825 reverteu trabalho no main (restaurado)

O squash do #8825, que só deveria registrar um envio, levou a árvore de um worktree **sujo** do resolvedor e reverteu 23 arquivos (#8813, #8836, `NfeEmissaoPorItemTest`, 7 `_saida`, entre outros). Causa raiz: o Monitor expirado **não mata o processo no Windows**. Havia 7 cópias do `lote.sh` e 5 do `vigia.sh` rodando ao mesmo tempo, disputando o worktree do resolvedor. Restaurado no **#8858**, um revert seletivo conferido arquivo a arquivo, com deploy completo conferido.

## Incidente 2 — o #8857 derrubou o oimpresso.com (500, ~00:45Z → ~03:20Z)

O primeiro deploy completo depois do `--no-dev` não bootou: `Class "Knuckles\Scribe\ScribeServiceProvider" not found`, a mesma armadilha do #2316 que o PR dizia fechar. O failsafe **não** segurou em 503: o site serviu 500. Recuperação à mão no Hostinger: backup em `~/bkp-cache-20261007/` (os caches e o `vendor/myfatoorah` residual, que travava o composer), `composer install` **com dev**, `package:discover`, `artisan up`. Revertido no **#8880**; o deploy completo seguinte passou e as rotas responderam.
Por que demorou: o `deploy-wait.sh` aceitava "sync leve" como deploy, e o vigia do `--no-dev` esperava o run do commit do merge (cancelado), não o deploy completo seguinte. Quando os deploys começaram a falhar, ele só gravou no log.

## Ferramentas (fora do repo): `D:/oimpresso.com/.claude/gestor-fila-scripts/`

- `lote.sh`: trava o head aprovado (3º campo do `lote.txt`); só apaga a própria linha do pin; **encerra sozinho aos 29 min** (rearmar).
- `resolver.sh`: limpa o worktree antes de começar; regenera o `SUPERFICIE.md` em conflito; **recusa o push se a resolução acrescentar arquivo fora do diff original do PR**.
- `deploy-wait.sh`: com `FULL=1`, exige o job "Deploy L13" com sucesso (não aceita sync leve).
- **Antes de rearmar qualquer Monitor**, mate as cópias vivas (PowerShell: `Get-CimInstance Win32_Process | ? CommandLine -match 'gestor-fila-scripts[\\/](lote|vigia|resolver)\.sh' | % { Stop-Process -Id $_.ProcessId -Force }`).

## Lições (medidas)

1. Monitor expirado não mata o script no Windows: as cópias se acumulam e corrompem estado compartilhado.
2. "Deploy com sucesso" no workflow não é deploy: confira o job "Deploy L13" (o sync leve não instala nada).
3. Depois de mudar o modo do vendor em produção, vigie o **próximo deploy completo** até o fim e alarme na hora se falhar.
4. O placar dos playbooks conta como "próximo" threads já entregues, porque o índice está desatualizado. Confira antes de despachar.
5. Agente sem `DesignSync` não consegue subir `_saida` ao Cowork; a sessão da fila consegue.

## Pendências

- **[W]:** responder a lista de decisões do #8872 (`memory/requisitos/_playbooks/DECISOES-W-PENDENTES-2026-10-07.md`, itens 1–7); emitir a nota de teste do #8836; testar no app o histórico de serviços e peças; cobrança do Actions; lojas do app; `.env` C1 de Vendas.
- **Chips abertos:** Fiscal 04/22; Tema escuro 02/04; Telas soltas 02; PUXAR Cliente/Estoque/Recorrente; PUXAR Produto/Essenciais/Vendas; Sistema 00; comissão lida sem filtro de empresa (Tier 0); `SuporteLogContratoTest` com username do Faker.
- **Sem `_saida` ainda:** Fiscal 06 (#8874) e Comissões 02 (#8877).
- O `--no-dev` só volta depois que o defeito do package manifest for entendido e testado contra um vendor que já tem require-dev instalado.

## Estado MCP no momento do fechamento

`brief-fetch` do início da sessão (Brief #722): cycle sem dado; 3 HITL pendentes; 674 US sem dono. Não consultei de novo: esta sessão não criou nem moveu task.
