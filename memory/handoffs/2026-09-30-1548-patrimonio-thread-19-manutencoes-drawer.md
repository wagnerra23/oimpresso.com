---
date: "2026-09-30"
time: "15:48 BRT"
slug: patrimonio-thread-19-manutencoes-drawer
tldr: "Patrimônio thread 19: manutenção em drawer (create/edit na Page, 2 Blades removidas) e Tier 0 no store/update (gravava em manutenção/bem de outra empresa). Smoke prod: 44px medidos, rotas removidas da 20 dão 404. Thread 16 fica no redirecionamento (#8286, decisão [W])."
prs: [8260, 8264, 8282, 8285]
decided_by: [W]
next_steps:
  - "Cowork: corrigir a dependência 19→16 no 00-INDICE.md do Patrimônio (não se edita no espelho)"
  - "Bens: botão 'Enviar pra manutenção' — destino já existe em /asset/asset-maintenance/create?asset_id={id}"
---

# Patrimônio thread 19 — manutenção em drawer; Tier 0 no destino

## O que entrou no `main`
- **#8260** `7f7037ce6`: Tier 0 no destino do formulário de manutenção. `update()` fazia
  `AssetMaintenance::find($id)` sem `business_id` (gravava na manutenção de outra empresa pelo
  id) e `store()` aceitava `asset_id` de outra empresa. Os dois dão 404 antes do `try` e o
  serviço também escopa. UC-MANU-05, Pest 98 × 99 com controle da própria empresa.
- **#8264** `be06c442f`: thread 19. `create`/`edit` devolvem a Page `Patrimonio/Manutencoes`
  com o drawer aberto via `renderManutencoes` (desenho do `renderBens` da 17). O ramo `ajax()`
  saiu e as 2 Blades `asset_maintenance/{create,edit}` foram removidas. Layout do
  `ManutencaoForm`, campos do Blade, sem custo (UC-MANU-03). CTA do rodapé da âncora + lápis
  por linha. UC-MANU-06, contrato de tela ganhou `rodape`, charter v3.
- **#8282** / **#8285**: registro do envio de `_saida-19` e `_saida-20` ao Cowork (projeto w).
- Thread 20 (#8272, de outra sessão) mergeada nesta sessão a pedido do [W].

## Smoke em produção (biz 1)
- `/asset/asset-maintenance/create`: drawer abre, 7 controles com **44px medidos**
  (`getBoundingClientRect`); o "X" do Sheet mede 16px (componente shadcn compartilhado).
  Sem texto de custo. `/{id}/edit` de id alheio → 404. Edição com dado real **não** feita:
  biz 1 não tem manutenção, e criar uma seria escrita em prod.
- `/asset/settings` (thread 20): 200, tela `Patrimonio/Configuracoes`; `create`, `show`,
  `edit`, `PUT` e `DELETE` → 404. Salvar não exercitado (escrita em prod).

## Decisões [W] nesta sessão
- Executar a 19 antes da 16 (sem dependência técnica; a aresta 19→16 do índice é do Cowork).
- Thread 16: pediu seguir o #8290, mas já tinha mergeado o #8286 (redireciona
  `/asset/revocation` → `/asset/allocation`). **Fica o redirecionamento.** Um comentário meu
  no #8286 dizia "Fechado" — errado, já corrigido no próprio comentário.

## Pendências
- **"X" do Sheet a 16px**, abaixo de 44px para o técnico no tablet: se for requisito, é no
  componente compartilhado, PR próprio.
- **`visual-regression`**: o step "Install Playwright system dependencies" estourou 5 min em
  vários PRs de hoje (instabilidade do runner) e o Governance/Dashboard diverge da baseline
  desde a thread 07. Nenhum dos dois é das threads 19/20.
- **cron-watchdog** acusou `mcp-drift-sentinel` morto com runs de hoje na API (LC-24 de novo);
  só re-rodado, não consertado.

## Estado MCP no momento do fechamento
Brief do início da sessão: cycle sem dados, 4 HITL pendentes do [W], nada de Patrimônio em
voo. Não houve task MCP criada ou atualizada nesta sessão.
