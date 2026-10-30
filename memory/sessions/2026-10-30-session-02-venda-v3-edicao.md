# Venda V3 — preparação da edição (onda 1)

Em 2026-10-30, a frente de edição foi preparada em `codex/venda-v3-edicao`, worktree
`D:/oimpresso.com/.worktrees/venda-v3-edicao`, a partir de `origin/main` `f23fe1a021`.
O PR #10226 de cliente/comissionado estava aberto na consulta inicial; #10225 já estava no main.
Não houve alteração de CreateV3.tsx, PDV, flags, merge ou deploy. O checkout compartilhado ficou intacto.

## Preparado para integração sequencial

- `AcessoEdicaoVenda` concentrou os guards do GET existente. A venda passou a ser resolvida
  por empresa antes da verificação do prazo; a devolução também foi consultada na mesma empresa.
  Permissões, prazo configurado no business e respostas 403/404/422/redirect foram preservados.
- `BaseEdicaoVendaV3` forneceu `form.edicaoV3` ao payload deferido existente: campos que o update
  sobrescreve mesmo sem controle na V3, inclusive despesas, observações, prazo e tabela.
  DECIMAL lido por num_uf foi serializado com vírgula, sem float nem cálculo novo.
- `carregarEdicaoV3` aguardou o form deferido e produziu snapshot independente, linhas com IDs e
  preço anterior ao desconto, pagamentos completos e trava fiscal recebida do servidor.
- `montarEdicaoV3` preparou PUT `/pos/{id}`, reutilizou `edicaoVenda.ts` e pediu o recálculo já
  existente no servidor. Preservou pagamentos pelo caminho `is_credit_sale=1`, sem reenviá-los
  como novos. Com nota válida, enviou somente textos livres, pelo fast path existente.

## Limites concretos

A Page ainda precisa consumir o adaptador e o mesmo `form`/`permissions`/`trava`/`urls` do GET
existente. Nenhuma rota ou flag da V3 foi ativada. O `form.edicaoV3` é adicional e não muda o
editor atual. A integração deverá escolher os controles da V3 e manter o teto de desconto e
demais configurações já implementadas pelas outras frentes.

Recebimentos existentes ficaram somente leitura; alterar/estornar usa o fluxo dedicado com
`sell.payments` (permissão adicional no payload). Não houve implementação de editor de pagamentos.
O adaptador recusou subunidades, quantidade secundária não zero, combos e modificadores; não
inventou um reenviador parcial dessas estruturas. Reparo foi reservado à onda 2, como solicitado.
Novas linhas também precisam do contrato de busca e metadata de estoque da integração; o
adaptador desta frente só aceitou identidades já existentes na venda. Portanto esta entrega
não autoriza substituir o editor em produção para todos os documentos.

## Validação e impacto

Vitest: 19 testes passaram em `edicao-dominio.test.ts` e `sells-edicao-venda.test.ts`.
ESLint do adaptador: sem erro (o arquivo de teste não pertence à configuração de lint).
Sintaxe PHP dos dois serviços e do controller: válida. Pest/PHPStan: execução CT100 pendente
no primeiro registro; o acesso LAN foi confirmado com a chave canônica, sem depender do Tailscale local.

| Caso sintético | Antes | Depois esperado |
|---|---|---|
| Abrir e desistir | 2 × (100 − 10) = 180; linha id 7 | mesmos valores e identidade; nenhuma escrita |
| Salvar sem alteração | 180; quantidade 2 | 180; quantidade 2; estoque e pagamentos iguais |
| Quantidade 2 → 3, sem nota/pagamento | 180; estoque 8 | 270; estoque 7, pelo cálculo existente do servidor |
| Nota autorizada + texto | valores e linhas originais | só texto livre alterado |
| Pagamento existente | IDs, conta, método, data, troco originais | mesmos registros, sem recriação |
| Venda alheia fora do prazo | poderia responder 422 antes do tenant | 404 antes dos guards |

A prova independente do exemplo foi 2 × (100 − 10) = 180, cruzada com a linha serializada
2 × 90 = 180; o teste existente do servidor cobre total por diferença, incluindo resíduo histórico.
Esses exemplos não são um dry-run de produção. Nenhum registro real foi alterado; merge/ativação
continuaram dependentes de aprovação humana após revisão do impacto e homologação.

## Estado MCP

`brief-fetch`, `my-work`, `cycles-active`, `sessions-recent` e `decisions-search` não estavam
disponíveis no catálogo da sessão. Foi usado o fallback filesystem: CLAUDE.md, Tier 0,
imports operacionais, handoff/sessão recentes, SPEC, RUNBOOK-edit, charter/casos/testes de Edit,
serviço canônico de trava e histórico remoto. Nenhuma consulta MCP foi alegada como realizada.
