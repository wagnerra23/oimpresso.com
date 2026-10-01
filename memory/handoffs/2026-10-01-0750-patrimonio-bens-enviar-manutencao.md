---
date: "2026-10-01"
time: "07:50 BRT"
slug: patrimonio-bens-enviar-manutencao
tldr: "Continuação da sessão da thread 19: botão 'Enviar pra manutenção' em Bens (linha #8310 e rodapé do drawer de detalhe #8316), thread 20 (#8272) mergeada com smoke prod e _saida-20 no Cowork, thread 16 fica no redirecionamento (#8286). Pendentes abertos em 3 chips."
prs: [8272, 8285, 8305, 8310, 8316]
decided_by: [W]
next_steps:
  - "Chip: smoke em prod do botão do rodapé do drawer do bem (#8316, deploy 520416310 já OK)"
  - "Chip: timeout do Playwright no visual-regression (cai em vários PRs, derruba o check inteiro)"
  - "Chip: falso 'morto' do mcp-drift-sentinel no cron-watchdog (LC-24)"
  - "Cowork: corrigir dependência 19→16 e a prova da thread 16 no 00-INDICE.md do Patrimônio"
---

# Patrimônio — "Enviar pra manutenção" a partir de Bens

Continuação de [2026-09-30-1548](2026-09-30-1548-patrimonio-thread-19-manutencoes-drawer.md).

## O que entrou no `main`
- **#8310** `98bff3df4`: chave "Enviar pra manutenção" em cada linha de Bens → `/asset/asset-maintenance/create?asset_id={id}`, com `permissoes.manutencao` (mesma permissão do `create()`). UC-BENS-12, charter v8 revoga o Non-Goal só para manutenção. **Smoke prod biz 1 feito:** clique abriu o drawer de manutenção com o bem escolhido.
- **#8316** `520416310`: o mesmo botão no rodapé do `_shared/DetalheBemDrawer` (prop `onEnviarManutencao`, 44px). Deploy OK; **smoke prod não feito** — virou chip.
- **#8272** (thread 20, de outra sessão) mergeado a pedido do [W]. Smoke prod: `/asset/settings` 200; create/show/edit/PUT/DELETE → 404. Salvar não exercitado (escrita em prod).
- **#8285**: registro do envio do `_saida-20` ao Cowork (projeto w).

## Decisões [W]
- Thread 16: [W] pediu o #8290, mas ele próprio já tinha mergeado o #8286 (redireciona `/asset/revocation` → `/asset/allocation`). **Fica o redirecionamento.** Um comentário meu no #8286 dizia "Fechado" (errado); corrigido no próprio comentário.

## Fora (com dono ou decisão)
- "X" do Sheet a 16px → #8334, outra sessão.
- Alocar e Editar bem no rodapé do drawer → fora (alocar é quantidade; editar já é ação da linha).
- Bem "TESTE — medição drawer (apagar)" no biz 1 → apagar é escrita em prod, decisão [W].
- Placar do Patrimônio (13/19): 05 bloqueada definitiva; 16-20 entregues em código, travadas só pelo índice do Cowork.

## Estado MCP no momento do fechamento
Brief #700 no resume: sem cycle ativo, 3 HITL [W], nada de Patrimônio em voo. Nenhuma task MCP criada ou atualizada nesta sessão.
