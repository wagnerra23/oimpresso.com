---
date: "2026-10-05"
time: "14:07 BRT"
slug: app-lojas-tela11-venda-rapida
tldr: "Tela 11 Venda rápida do app das lojas concluída: app #39 mergeado com ok [W], API do ERP (#8589/#8597) em produção e venda de conferência na empresa demo 235 batendo com a prévia (total e estoque). Trava de preço zero virou ajuste por empresa, padrão desligado (app #68 + ERP #8655)."
decided_by: [W]
next_steps:
  - "[W]: cancelar pela web a venda de conferência na empresa demo 235, se a demo precisar ficar limpa para os revisores das lojas"
  - "[W]: cobrança do GitHub Actions — o CI do oimpresso-app não inicia; os merges da tela 11 foram com verificação local"
---

# App das lojas — tela 11 Venda rápida concluída

## Estado MCP no momento

O servidor MCP oimpresso estava **desconectado** nesta sessão no fechamento (as tools `mcp__oimpresso__*` não
carregam). Retrato pelo fallback, Daily Brief #708 do SessionStart: sem cycle ativo, HITL pendente [W] = 4,
nenhuma ADR nova ligada à tela 11. Handoffs irmãos do dia: `0934 app-lojas-erp-coordenacao` e `1208
app-lojas-tela20-screenshots-textos-d16`.

## O que aconteceu

- **App (wagnerra23/oimpresso-app):**
  - **#39 · tela 11:** busca, carrinho, pagamento (PIX/Crédito/Débito/Dinheiro) e recibo, aberta pelo
    "+ Venda" de Pedidos. O app não calcula preço: a prévia é em centavos inteiros, o envio vai com texto
    de 2 casas e `total_previsto`, e a tela mostra o total devolvido pelo ERP. Contra venda duplicada: trava
    síncrona no Confirmar + `Idempotency-Key` mantida em rede/5xx/409. Sem câmera (ADR 0383). Boleto fora
    da v1 ([W]).
  - **#68 · ajuste de preço zero:** começou como bloqueio fixo; [W] mudou para configuração ("tem cliente
    que usa o preço zero para brindes") com **padrão desligado**. O app segue o campo `bloqueia_preco_zero`
    da busca, e ausente vale false. Mergeado no commit `428aca6`, com ok [W] dado à fila de merges.
- **ERP (feito pelas sessões ERP/coordenação, não por esta):** #8589 busca de produtos, #8597 criar venda
  (TransactionUtil, venda direta final fora da FSM, idempotência em `app_idempotencia`), #8655 ajuste
  `pos_settings.bloquear_venda_preco_zero_app` (deploy em produção às 14:23Z, confirmado pela coordenação; o campo vem `false` até alguém ligar a opção).
- **Conferência em produção:** [W] vendeu pelo app (`gestor.demo`, empresa 235, build de debug do main) 2
  Camisetas + 3 Canecas. A prévia que calculei antes e o total gravado pelo ERP foram iguais, e o estoque
  foi de 5 para 3 e de 6 para 3, como previsto. Detalhe com valores no comentário do app#39.
- **Limpeza:** checkouts `D:\oimpresso-app-venda` e `-tela11-build` removidos, junto com os branches
  locais (iguais ao head mergeado); app de conferência desinstalado; emulador fechado.

## Artefatos

- App: `src/telas/VendaRapida.tsx`, `src/venda.ts` (+ `venda.test.ts`), `src/demo-venda.test.ts`, e as
  partes da venda em `src/api.ts`, `src/demo.ts` e `src/styles/ponto-v4.css`.
- Prova e tabela antes→depois: comentários no wagnerra23/oimpresso-app#39 (ok [W] e conferência na 235).

## Persistência

Git: os PRs acima. MCP: desconectado; este handoff entra pelo webhook de `memory/`. BRIEFING: não há
BRIEFING para o app das lojas em `memory/requisitos/`.

## Próximos passos

Nada de código pendente na tela 11. Ver `next_steps` no frontmatter.

## Lições

- **Repasse de ok não é ok.** Duas sessões repassaram autorizações do [W] ("todos autorizados" e um ok no
  commit antigo do #68). A segunda teria mergeado o bloqueio fixo depois que o [W] já tinha mudado a
  decisão. Segurei as duas e pedi o ok aqui e no commit certo.
- **O CI da conta caiu em 2026-10-02** (cobrança): "verde" nos PRs do app passou a ser só verificação local
  (tsc, testes, build, browser). Cada merge do main gerava um alarme de CI que não era de código.
- **Comando em segundo plano mata o emulador no limite de tempo.** Abrir com `Start-Process`, desligado da
  sessão.
- **`git worktree remove` falha com "Filename too long"** nas pastas de build do Android. Conferir que não há
  junction e apagar o resto pelo prefixo `\\?\`.

## Pointers

- Contrato da API: `memory/requisitos/AppMobile/` (arquivos por tela, #8612) e os PRs #8597/#8655.
- Sessão de coordenação do lado ERP: handoff `2026-10-05-0934-app-lojas-erp-coordenacao.md`.
