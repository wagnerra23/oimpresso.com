---
date: "2026-09-22"
time: "10:15 BRT"
slug: buracos-prototipo-producao
tldr: "A auditoria encontrou 0 telas validadas em produção, 133 pares anteriores ao smoke, comparação visual ainda manual, mapas majoritariamente frágeis e um smoke que podia passar sem visitar a Page alterada. A PR #7648 faz Page sem rota falhar como NÃO MEDIDO e inclui Pages dos módulos."
prs: [7648]
decided_by: [W]
related_adrs:
  - 0384-design-sync-recibos-executaveis-por-tela
  - 0410-ratificacao-zero-baseline-no-funil-design
next_steps:
  - "Integrar a PR #7648 somente após o CI completo ficar verde"
  - "Corrigir a autenticação do smoke de produção e cadastrar cobertura além das 12 rotas atuais"
  - "Criar produtor verificável do ledger para smoked-staging e validated por tela e SHA implantado"
  - "Ligar recibos de teste ao caso de uso e à tela, em vez de aceitar comando livre compartilhado"
---

# Buracos atuais do protótipo até produção

## Resultado

O estado comprovado é **3 telas com smoke de CI e zero validadas em produção**. Dos 164 pares,
133 ainda estão em `compared`, `anchored` ou `to-create`. `compared` demonstra consistência do
mapa e dos hashes; não é comparação visual renderizada.

O smoke pós-merge também tinha um falso verde reproduzível: ao mudar uma Page sem `source`
exato em `routes.json`, ele selecionava apenas as rotas `nav_critical` e podia terminar verde
sem visitar a Page. A #7648 agora encerra como **NÃO MEDIDO** e falha nesses casos. Pages dentro
de `Modules/*/[Rr]esources/js/Pages` também passam a disparar o workflow.

## Dívida que continua aberta

- O catálogo tem só 12 rotas e o run real 35720880986 falhou na autenticação de produção.
- Não existe produtor encontrado para `smoked-staging` nem `validated`; o pós-merge guarda
  artefato/review, mas não atualiza o ledger por tela.
- Dos 580 pontos mapeados, 496 usam referência de linha e só 84 têm `data-contract`; existem
  66 TODOs de âncora e cobertura de 67 entre 223 charters.
- O recibo de teste é comando livre, sem vínculo obrigatório a tela/caso de uso. Três telas
  Fiscal compartilham o mesmo job, que comprova a lane e não cada comportamento.
- A cadeia exige tenant 1, enquanto a conta observada do smoke usa business 99.
- Cinco mudanças de transporte/estilo/playbook no bundle ativo não estão atribuídas a telas.

Nenhum desses itens foi convertido em baseline ou waiver. Eles permanecem dívida visível.

## Provas desta rodada

O teste novo da seleção de rotas passou, assim como o parse do runner, o gate do funil, as
82 verificações do `gate-selftest`, `memory-health` com zero falhas e `git diff --check`.

## Estado MCP no momento do fechamento

As tools MCP de ciclos, tarefas, sessões e decisões não estavam disponíveis nesta sessão do
Codex. O estado acima foi medido diretamente no Git, nos ledgers, scripts, workflows e runs da
PR/repositório; nenhum snapshot MCP foi simulado.
