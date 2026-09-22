---
title: "RUNBOOK — promoção integral de protótipo"
type: runbook
owner: W
module: Manufacturing
last_validated: "2026-09-22"
status: ativo
---

# RUNBOOK — promoção integral de protótipo

## Objetivo

Promover uma tela de protótipo para produção com rastreabilidade suficiente para
provar o que entrou, o que não entrou e por quê. Este runbook evita a conclusão
falsa de que uma tela está "igual ao protótipo" a partir de comparação parcial.

Aplica-se a cada tela da Manufacturing: não existe aceite único para um módulo
inteiro. O estado do módulo é a soma dos aceites individuais de suas telas.

## Regra de conclusão

Uma tela só está **concluída** quando todos os itens do inventário estiverem
`ACEITO` ou `FORA DE ESCOPO APROVADO`, cada qual com sua evidência. Uma tela com
qualquer item pendente, bloqueado ou sem evidência está **parcialmente publicada**.

Não usar as expressões "igual ao protótipo", "pronta" ou "completa" sem anexar
ou referenciar a matriz de aceite final. Um medidor visual parcial permite afirmar
somente quais dimensões ele comparou; nunca fidelidade integral.

## Pré-condições

Antes de editar código, o agente deve registrar para a tela:

| Campo | Registro exigido |
|---|---|
| Módulo e tela | Nome e rota exatos |
| Fonte oficial | Caminho ou URL do protótipo aprovado |
| Revisão | Commit, PR, versão ou data da fonte |
| Decisor | Pessoa que escolheu a fonte |
| Referências rejeitadas | Protótipos similares que não devem ser usados |
| Matriz de aceite | Local versionado onde será acompanhada |

Se houver mais de uma versão possível do protótipo, o trabalho fica `BLOQUEADO`
até decisão humana explícita. Não assumir que o arquivo mais novo, mais bonito ou
mais próximo do código atual é a fonte correta.

## Precedência

Para **forma** (layout, hierarquia, cor, tipografia, ícone, rótulo e afordância),
o protótipo oficial prevalece. Para **comportamento**, prevalecem teste verde,
casos de uso, charter e SPEC, nessa ordem. Permissão, disponibilidade de dados e
regras de valor não são definidos pela aparência: devem ser confirmados nas fontes
de negócio e no código.

Quando a fonte oficial divergir de um caso, charter ou teste, registrar a
divergência e corrigir o artefato perdedor no mesmo trabalho; nunca deixar os
contratos silenciosamente incompatíveis.

## Matriz de aceite por tela

Criar uma matriz versionada antes da implementação. Cada elemento observável do
protótipo é uma linha própria. Não agrupar itens em termos vagos como "ajustes de
tabela" ou "formulário revisado".

| ID | Área | Item | Classe | Fonte/regra | Aceite observável | Evidência | Situação |
|---|---|---|---|---|---|---|---|
| T01 | Cabeçalho | título | visual | protótipo | texto e hierarquia conferem | print | NÃO INICIADO |
| T02 | Filtros | período | comportamento existente | filtro atual | altera a lista corretamente | teste + print | NÃO INICIADO |
| T03 | Tabela | coluna de margem | dado/regra de valor | serviço aprovado | valor e formato corretos | teste + prova | BLOQUEADO |
| T04 | Drawer | criar ordem | fluxo | caso de uso | abre, valida, salva e reporta erro | teste de fluxo | NÃO INICIADO |

Situações permitidas:

- `NÃO INICIADO`
- `EM IMPLEMENTAÇÃO`
- `PRONTO PARA VALIDAR`
- `ACEITO`
- `BLOQUEADO`
- `FORA DE ESCOPO APROVADO`

`ACEITO` exige evidência. `FORA DE ESCOPO APROVADO` exige quem aprovou, data e
motivo. Nenhum outro estado encerra um item.

## Inventário obrigatório

Ler o protótipo de cima para baixo e registrar, quando existirem:

1. Cabeçalho, breadcrumb, título, descrição e ações principais.
2. Busca, filtros, chips, ordenação e filtros ativos.
3. Indicadores, cartões, totais e métricas.
4. Abas, navegação interna e estados selecionados.
5. Lista ou tabela: cada coluna, badge, menu, paginação, ordenação e estados
   vazio, carregando e erro.
6. Modais, drawers e formulários: campos, máscaras, obrigatoriedade, validações,
   erros, sucesso e cancelamento.
7. Fluxos: criar, editar, excluir/cancelar, aprovar, imprimir/exportar e abrir
   detalhes.
8. Tooltips, ícones, rodapé, atalhos e responsividade mostrados no protótipo.
9. Permissões: quem vê e quem pode executar cada ação.

Quando o protótipo mostrar um elemento sem definir seu comportamento, registrar
`NÃO DEFINIDO NO PROTÓTIPO`; não inventar regra.

## Classificação antes de implementar

Todo item da matriz deve receber uma classe:

| Classe | Tratamento |
|---|---|
| Visual | Pode ser implementado diretamente e comparado visualmente. |
| Comportamento existente | Reutilizar comportamento confirmado e testá-lo. |
| Dado existente | Confirmar endpoint, query e permissão de origem. |
| Dado novo | Criar trabalho de backend antes de exibir o componente. |
| Regra de valor/estoque | Seguir a regra mestre: dupla confirmação, impacto antes→depois e aprovação humana. |
| Permissão | Confirmar pacote, perfil e comportamento sem autorização. |
| Fluxo novo | Especificar e testar antes de declarar funcional. |
| Fora de escopo | Só mediante aprovação explícita. |

O agente não pode usar dado fictício para fazer a tela parecer correta. Se o
backend não entrega o dado necessário, o item permanece bloqueado ou recebe uma
implementação de backend propriamente especificada.

## Ordem de execução

1. Fixar a fonte oficial e criar a matriz de aceite.
2. Levantar bloqueios de dado, regra, permissão e fluxo novo.
3. Implementar dados e regras confirmadas.
4. Implementar estrutura da tela e estados de carregamento, vazio e erro.
5. Implementar fluxos e suas validações.
6. Implementar tabela, filtros, indicadores, ações, modais e drawers.
7. Ajustar a aparência contra o protótipo oficial.
8. Anexar evidências e atualizar a matriz.
9. Só então solicitar ou declarar o aceite da tela.

Não começar pelo acabamento visual se a tela depende de dado ausente ou de regra
de negócio ainda não decidida.

## Evidência de aceite

### Itens visuais

Comparar no mesmo tema e resolução de referência. Salvar ou vincular:

- imagem do protótipo oficial;
- imagem da implementação;
- revisão da fonte usada;
- diferenças intencionais aprovadas.

Comparadores automáticos são complementares. Eles não provam por si só fluxos,
drawers, modais, permissões, ícones, rodapé ou regra de negócio.

### Tabelas

Validar cada coluna de forma independente: presença, ordem, rótulo, formato,
alinhamento, origem do dado, ação associada e comportamento nos estados vazio e
carregado.

### Formulários e drawers

Validar abrir, fechar sem salvar, preencher, validar campos, reportar erro do
servidor, salvar, atualizar a lista e negar a operação para perfil sem permissão.

### Valores e estoque

Mudanças em custo, margem, desperdício, estoque, total, preço ou qualquer outro
valor obedecem à Regra Mestre do repositório. Antes de merge/deploy, provar o
resultado por dois caminhos independentes, apresentar a tabela antes→depois e
obter aprovação humana explícita.

## Relatório final obrigatório

O relatório de uma tela deve conter:

```text
Tela: <nome e rota>
Fonte oficial: <caminho/URL e revisão>
Itens inventariados: <n>
Aceitos: <n>
Fora de escopo aprovados: <n>
Pendentes: <lista ou nenhum>
Bloqueados: <lista ou nenhum>
Evidência visual: <links/caminhos>
Evidência de fluxo: <testes/resultado>
Evidência de dados, valor e permissões: <testes/provas>
Diferenças aprovadas: <lista ou nenhuma>
Declaração: CONCLUÍDA | PARCIALMENTE PUBLICADA | BLOQUEADA
```

Usar `CONCLUÍDA` apenas sem pendências, bloqueios ou itens sem evidência. Se o
resultado for parcial, enumerar o que falta e o responsável pela próxima decisão.

## Módulos com várias telas

Manter uma matriz por tela e uma visão consolidada do módulo. Trabalhar por tela
ou onda pequena sem dependências, registrando o resultado versionado antes da
próxima onda. Uma aprovação visual de uma tela não aprova as demais.

O agente deve retomar o trabalho a partir da matriz registrada, e não de memória
de conversa. Contexto longo pode omitir detalhes; o inventário versionado é a
fonte de acompanhamento.

## Prompt operacional

Use este comando ao iniciar uma promoção:

> Promova o protótipo da tela `<tela>` para produção seguindo o RUNBOOK de
> promoção integral. Antes de editar, confirme a fonte oficial e sua revisão,
> crie a matriz de aceite com cada seção, coluna, ação, drawer, formulário,
> estado e fluxo, e classifique cada item. Não declare fidelidade integral por
> comparação parcial. Implemente apenas regras e fontes confirmadas; registre
> evidência para cada item. Ao final, informe aceitos, pendentes, bloqueados e
> diferenças aprovadas, usando `CONCLUÍDA` somente se a matriz estiver sem itens
> pendentes, bloqueados ou sem evidência.
