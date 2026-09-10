# Reanálise do processo de aplicação — 2026-09-10

## Contexto

Pedido de [W]: reanalisar em branch fresca, incluindo a aplicabilidade aos módulos.
Base examinada: `origin/main` em `af09f7c3a0` (#7175), após fetch.
Branch: `codex/reanalisa-processo-prototipo-20260910`.
Escopo: revisão do instrumento de aplicação, índices e evidências. Nenhuma implementação de módulo foi alterada.

## Veredito

As defesas anteriores sobreviveram à integração, mas o processo ainda não comprovou aplicação completa em todos os módulos. Foram encontrados cinco problemas acionáveis: um falso positivo reproduzido com execução real do Playwright, um novo consumidor incompatível, caminhos desatualizados, fichas fora do índice e uma prova E2E inexequível como escrita.

## Achados

### 1. [P1] Execução filtrada pode encerrar um arquivo parcialmente testado

Local: `prototipo-ui/design-docs/cowork-inbox/_scripts/placar-formatos.mjs`, `validarPlaywright`, linhas 3–29; consumidor em `placar-evidencia.mjs`, linhas 76–78.

O adaptador compara os resultados com `stats.expected` do próprio relatório e exige presença do nome do arquivo. Não confronta a lista de casos/projetos esperados com um inventário independente. Um relatório de execução filtrada omite os testes não selecionados, sem contá-los como skips. O hash do arquivo completo não demonstra execução de todos os seus casos.

Reprodução em 2026-09-10: arquivo temporário com dois testes sem navegador (`passa`: 1 === 1; `falha`: 1 === 2), executado pelo CLI Playwright instalado, com reporter JSON e configuração isolada.

| Execução real | Código de saída | expected | unexpected | Adaptador aceitou |
|---|---:|---:|---:|---|
| Arquivo completo | 1 | 1 | 1 | não |
| Mesmo arquivo, `--grep passa` | 0 | 1 | 0 | sim |

Isso não depende de falsificar o JSON. O envelope também permite qualquer `command` não vazio, sem conferir seleção integral. Recomendação: vincular seleção de casos/projetos esperada à evidência e comparar identidades, cobrindo filtros/shards; não usar apenas a contagem declarada pelo relatório. Acrescentar este contracaso à suíte. A mesma hipótese deve ser examinada no adaptador JUnit antes de afirmar integralidade dele; não foi reproduzida em PHP nesta revisão.

### 2. [P2] DS-átomos declarou código e comandos no lugar de recibos e arquivos de teste

Local: `prototipo-ui/design-docs/cowork-inbox/ds-atomos/playbook/00-INDICE.md`, linhas 121, 143 e 166.

As tarefas 01–03 passaram pelo schema, mas `execucao.path` apontou a `card.tsx`, `KpiCard.tsx` e `Toolbar.tsx`; `testes` contém textos como `npm run test -- card`. O avaliador faz `JSON.parse` de `path` e exige hashes dos arquivos em `testes`. Portanto a implementação correta desses componentes não consegue encerrar as tarefas com essas configurações. Não substituir TSX por recibo: corrigir os campos para recibos separados e arquivos de teste reais, respeitando o produtor de relatório disponível. O teste dos playbooks reais verificou parse/schema, não a compatibilidade semântica desses consumidores.

### 3. [P2] Patrimônio 09–11 ainda procuram telas em subpastas inexistentes

Local: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/00-INDICE.md`, provas e prefixos de 09, 10 e 11.

O índice procurou `Alocacoes/Index.tsx`, `Manutencoes/Index.tsx` e `Configuracoes/Index.tsx`; a árvore examinada contém `Alocacoes.tsx`, `Manutencoes.tsx` e `Configuracoes.tsx`, com charters/casos ao lado. Isso causa diagnóstico falso de arquivo ausente. A ficha `14-provas-flat-09-10-11.md` já documentou o problema, mas não o corrigiu. Corrigir o endereço não bastará para declarar feito: essas três tarefas também não têm prova de conclusão configurada e dependem de 07. A frase da ficha 14 que atribui a pendência somente ao arquivo ausente não descreveu a régua examinada.

### 4. [P2] Dez fichas novas ficaram fora do universo do placar

Local: blocos JSON de `ponto/playbook/00-INDICE.md` e `patrimonio/playbook/00-INDICE.md`.

Ponto tem fichas 13–15, e Patrimônio tem 14–20, ausentes de `threads`. O placar lê apenas esse array: não lista essas entregas, não contabiliza seus bloqueios e não oferece retomada. Exemplo: Ponto 13 depende de DS-átomos 01/02; Patrimônio 17 depende de 16 e D-FORMS. Há também o arquivo histórico Patrimônio 06 fora do índice; ele não foi contado como trabalho novo. Recomendação: reconciliar as dez fichas e seus gates com o índice e verificar mecanicamente fichas ativas omitidas, com exclusão histórica explícita. Dependências entre playbooks precisam preservar o vínculo, sem inventar aprovação de produto.

### 5. [P2] Painel de Patrimônio exige um E2E que só contém fixme

Local: `e2e/patrimonio-index.spec.ts`, linhas 12 e 27; prova `execucao` da tarefa Patrimônio 07.

Os dois casos estão em `test.fixme`. Executar esse arquivo como escrito não produz casos aprovados: o adaptador recusa corretamente skips/execução vazia. A integração definiu uma exigência sem disponibilizar prova executável equivalente. O próprio spec aponta testes JS de conteúdo, mas eles não substituem silenciosamente o E2E exigido. É necessário preparar o harness/casos executáveis ou delimitar explicitamente outro contrato de evidência; não remover o bloqueio de skips. A comparação visual da mesma tarefa também tem copy pendente no contrato, com arrays vazios, e nenhum recibo foi encontrado.

## Inventário medido nesta base

| Playbook | Tarefas no JSON | Com tipo de evidência | Sem tipo de evidência, não bloqueadas | Bloqueadas |
|---|---:|---:|---:|---:|
| Âncora | 3 | 0 | 3 | 0 |
| Compras | 5 | 0 | 2 | 3 |
| DS-átomos | 5 | 3 incompatíveis | 0 | 2 |
| Fiscal | 3 | 0 | 3 | 0 |
| Governança | 5 | 4 | 0 | 1 |
| HRM | 11 | 2 | 8 | 1 |
| Patrimônio | 12 | 5 | 5 | 2 |
| Ponto | 12 | 1 | 8 | 3 |
| Total | 56 | 15, incluindo 3 incompatíveis | 29 | 12 |

Oito playbooks foram descobertos. Nenhuma tarefa foi certificada como feita pelo placar; isso não significa ausência de implementação no sistema. Dos 15 consumidores com tipo de evidência, 12 tarefas usaram caminhos de recibos próprios (13 recibos, pois Patrimônio 07 exige dois), todos ausentes nesta árvore; os outros três são o defeito DS-átomos. As dez fichas omitidas não estão no denominador de 56.

Foram encontrados 32 diretórios com SCOPE.md: seis ligados a playbooks por modulo_codigo e 26 sem playbook específico. Âncora e DS-átomos ficaram como entradas sem vínculo de módulo. Esse inventário aponta portas de entrada, não certifica fluxos de negócio. Portanto não há base para afirmar cobertura de todos os erros ou módulos.

## Validação e limites

- `node --test prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.test.mjs`: 48 passaram, zero falhas/skips.
- `node prototipo-ui/integrity-check.mjs`: todos os invariantes hard passaram, 225 charters com tela viva.
- Reprodução Playwright real descrita acima: execução integral recusada, seleção parcial aceita. Arquivos temporários de teste/configuração removidos.
- Inventário por descoberta dos índices, avaliação e comparação dos nomes de fichas com `threads`, sem lista fixa de módulos.
- Nenhum Pest/PHPStan local, E2E da aplicação, smoke de produção, merge ou deploy foi executado. Os resultados são sobre esta árvore e o instrumento, não sobre o ambiente vivo.

Próxima correção prioritária: impedir o falso positivo de seleção parcial; em seguida reconciliar consumidores, caminhos e fichas. A revisão não alterou os validadores nem marcou entregas como prontas.
