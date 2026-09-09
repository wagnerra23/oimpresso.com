# Comparação do placar entre módulos — 2026-09-08

## Contexto

[W] pediu comparar se a correção do processo funcionaria para todos os módulos. Universo medido na árvore do PR #7063, base integrada 7929a8704b: 32 diretórios de requisitos com SCOPE.md e 4 índices de playbook encontrados em todos os paths versionados. O catálogo gerado tinha contagens diferentes; não foi usado como denominador de arquivos existentes.

**Veredito: não é correto declarar cobertura universal.** O instrumento passou a ler os quatro playbooks e suas dependências, mas só três tarefas declararam contrato de execução. Isso é diferente de provar os fluxos dos módulos.

## Comparação medida

| Playbook / dono no código | Tarefas | Contratos de execução declarados | Resultado da leitura após correção |
|---|---:|---:|---|
| Governança / Governance | 5 | 0 | Schema e grafo válidos; 03a preservado; nenhum fechamento certificado |
| HRM / Essentials | 11 | 0 | Variáveis e alternativas de arquivos avaliadas; nenhum fechamento certificado |
| Patrimônio / AssetManagement | 12 | 3 | Dependências e decisões preservadas; recibos ainda ausentes |
| Ponto / Ponto | 12 | 0 | Guardas, alternativas e decisões avaliadas; nenhum fechamento certificado |

Total: 40 tarefas, 3 com contrato de execução e 37 sem esse contrato (incluindo tarefas bloqueadas). Os três contratos declarados não equivalem a três execuções comprovadas.

Os 28 diretórios com SCOPE.md sem playbook deste instrumento foram: Arquivos, Auditoria, Cms, Compras, ComunicacaoVisual, Connector, ConsultaOs, Crm, Financeiro, Fiscal, Forja, Jana, KB, Manufacturing, NfeBrasil, NFSe, Officeimpresso, OficinaAuto, PaymentGateway, ProductCatalogue, RecurringBilling, Repair, Spreadsheet, Superadmin, Vestuario, VozDoCliente, Whatsapp e Woocommerce. Ausência de playbook não significa ausência de processo/testes: aplicação visual tem o dono scripts/design-sync/status.mjs e cada módulo tem seus contratos próprios.

## Falhas corrigidas nesta comparação

- A suíte anterior enumerava somente Patrimônio, HRM e Ponto. Governança já existia e ficava fora: seu id 03a e nota_caminho eram rejeitados pelo schema. Agora o teste descobre os índices e aceita o formato de subtask já usado.
- --todos dependia de globSync indisponível no Node 20 do workflow e retornava verde com zero alvos. Descoberta sem padrão passou a funcionar no Node 20; zero índices retorna 2. Um índice inválido não impede a análise dos demais nem permite rc=0.
- Os três outros índices apontavam para scripts/qa/placar-indice.mjs, inexistente, e parte da prosa mantinha o fechamento antigo. Foram corrigidos os comandos e os ponteiros para o contrato único.

## Limites que a comparação revelou

O recibo execucao consome o produtor PHPUnit/Pest scripts/tests/junit-summary.mjs. JUnit sem assertions, E2E, TAP/Node, parecer de leitura e comparação visual não têm fechamento automaticamente adaptado. Testou-se o produtor real com XML controlado: PHPUnit com assertions gerou evidência aceita; JUnit sem assertions foi recusado. Isso não executou PHPUnit ou o browser.

Não se escolheu uma suíte arbitrária para preencher os 37 contratos faltantes, nem se fabricou aprovação para tarefas documentais. Para ampliar o fechamento, cada tipo precisa consumir a evidência de seu executor existente e cada ficha precisa declarar o que exige. As tarefas ainda podem ser iniciadas conforme grafo/decisões; conclusão e liberação de sucessoras dependem de evidência válida. Logo, o patch não deve ser anunciado como motor de conclusão universal.

## Validação

30 testes Node passaram, sem falhas/skips; incluíram CLI --todos, os quatro índices reais e integração com o produtor de resumo. integrity-check passou em todos os invariantes hard. Nenhum fluxo de negócio ou tela dos 32 módulos foi declarado testado ponta a ponta. MCP do projeto indisponível na sessão; comparação por Git/arquivos locais.

Reproduzir: node --test prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.test.mjs

Comparar índices: node prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs --todos --root . --proximo
