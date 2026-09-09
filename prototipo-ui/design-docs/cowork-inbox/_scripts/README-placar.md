# Placar do playbook

O índice declara o trabalho; o placar valida o schema e as dependências antes de avaliá-lo.
Rode `node prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs --indice <índice.md> --root . --proximo`.
Dependências Node: `ajv` e `ajv-formats`, as mesmas do validador de memória.

`contem`, `arquivo` e afins comprovam estrutura, não execução. Em 08/09/2026 o fechamento
passou a exigir também uma prova `execucao`, com `path` para o recibo JSON abaixo e
`testes` listando os arquivos de teste exigidos pelo plano. O recibo não escolhe a suíte.
Saídas antigas continuam como registro histórico; sem recibo verificável são “em curso”
(retomar/validar), não uma afirmação de que a correção antiga deixou de existir.
Desde 09/09/2026, revisão documental e comparação medida têm contratos próprios abaixo.
Todas as provas declaradas são obrigatórias; uma comparação não substitui o E2E declarado.

## Recibo de execução

Gerar **no ambiente que executou o teste**, a partir do processo finalizado. Não preencher
contagens à mão. O resumo é saída do dono existente:
`node scripts/tests/junit-summary.mjs <junit.xml> --out <summary.json>`.
Transferir esse resumo sem dados pessoais e o recibo pelo canal de artefatos existente.

```json
{
  "thread": "02",
  "runner": "ct100",
  "command": ["php", "artisan", "test", "--filter=NomeDoTeste"],
  "exitCode": 0,
  "summary": "caminho/summary.json",
  "testes": ["caminho/Teste.php"],
  "arquivos": {
    "caminho/summary.json": "SHA256_DOS_BYTES_DO_RESUMO",
    "caminho/Teste.php": "SHA256_DO_TESTE_EXECUTADO",
    "caminho/Service.php": "SHA256_DO_CODIGO_TESTADO",
    "caminho/_saida-02.md": "SHA256_DA_SAIDA"
  }
}
```

Os hashes são SHA-256 dos bytes UTF-8 exatos, calculados pela máquina; nenhuma normalização
de linha. Incluir todos os alvos declarados nas provas estruturais (no `um_de`, a alternativa
real), os testes, o resumo e a saída. Paths relativos ao repositório, sem `..`.
O resumo precisa comprovar cada arquivo de teste listado, sem falhas/erros/skips e com
assertions. Só `exitCode=0` não basta. `runner` aceita CT 100 ou CI; o placar **não executa**
o comando armazenado. Arquivo alterado invalida seu recibo e demanda nova verificação.

Limite: o vínculo de hashes não autentica o emissor nem prova que um teste exercita o fluxo
correto; revisar o teste e a origem da execução continua necessário. CI de backend não
certifica fidelidade visual. Aplicação visual segue `scripts/design-sync/status.mjs`.

## Fechamento e invalidações

Antes de despachar, ler rota → middleware → request/controller → Service → resposta/tela.
Restringir escrita pelo prefixo; permitir leitura dos consumidores necessários.
Se uma saída invalidar tarefa irmã, reconciliar **a ficha e o JSON do índice** no mesmo
fechamento, usando `bloqueio` quando a decisão já impede execução. Texto em `invalida:`
sozinho não altera o grafo; o placar não interpreta decisões em prosa.

Testes do instrumento: `node --test prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.test.mjs`.
Eles rodam no workflow existente `design-memory-gate.yml`.

## Alcance entre módulos

Use `node prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs --todos --root . --proximo`
para descobrir os índices disponíveis, sem lista fixa de nomes. O teste de integração percorre
o mesmo inventário; um novo módulo com playbook passa a ser validado automaticamente.
Nenhum índice selecionado ou índice inválido retorna 2, nunca verde por universo vazio.
O glob opcional depende de Node >=22; sem padrão, --todos funciona no Node 20 do CI.

Use também `--modulos --root .`: a entrada enumera cada módulo com SCOPE.md, liga os
playbooks por `modulo_codigo` explícito e aponta os demais aos contratos do módulo e
a design-sync. Não cria tarefas fictícias nem declara conclusão pelo inventário.
Para incorporar outro playbook, declarar o dono do código (ex.: HRM → Essentials),
as tarefas e o contrato de evidência adequado. Ausência de recibo continua sendo pendência.

Leitura de schema/grafo não comprova fluxos de negócio. TAP/Node não é aceito como se
fosse Pest; os formatos suportados são os explicitados abaixo.

## E2E — Playwright nativo

No índice: `{"tipo":"execucao","formato":"playwright-json","raiz_testes":"e2e","path":"recibos/02-e2e.json","testes":["e2e/exemplo.spec.ts"]}`.
O recibo mantém o envelope de execução acima; summary aponta ao JSON nativo do Playwright.
O reporter JSON foi acrescentado em playwright.config.ts ao lado do JUnit existente.
Rodar pelo harness/CI existente, sem mudar autenticação ou fixtures do módulo.

O adaptador confere todos os resultados, estatísticas e arquivos exigidos. Skip, flaky,
retry, erro global, expected failure e execução vazia recusam fechamento. Não há contagem
de assertions inventada: o formato nativo tem outros sinais. raiz_testes é a raiz relativa
do reporter no repositório (testDir = e2e na configuração atual), não o cwd da máquina.

## Parecer documental

No índice: `{"tipo":"revisao","path":"recibos/04-revisao.json","fontes":["levantamento.md"],"criterios":["rotas-conferidas"]}`.
O recibo contém thread, revisor identificado, resultado: aprovado, criterios com id,
resultado: aprovado e justificativa não vazia para cada critério declarado, além de arquivos
com SHA-256 das fontes e da saída. Não criar um parecer aprovado sem realizar a revisão.

Apenas escrita em .md ou .contract.json (ou tarefa de leitura sem prefixo) pode encerrar
por revisão. Isso não prova execução nem autoriza alteração de código. Mudança de fonte
ou critério ausente/reprovado invalida o fechamento; a decisão [W] continua prevalecendo.

## Comparação medida

No índice: tipo comparacao, path do recibo, fontes com os arquivos fonte/alvo, contrato
apontando ao .contract.json da tela e dimensoes exigidas (D2, D4, D6, D8, D9, SHELL).
O recibo contém thread, producao e prototipo (paths dos snapshots da sonda canônica),
além dos hashes das fontes, snapshots, contrato e saída. Colher os snapshots pelo processo
existente de design-diff; não escrever medições à mão.

O placar reexecuta somente design-diff.mjs --compare com --contrato, --check, --check-shell
e --json. O command do recibo não é executado. Identidade, proveniência, tema e dimensões
medidas precisam passar. Dados ausentes ou contrato sem copy para D0 não recebem um
carimbo de comparação. O resultado certifica só as dimensões exigidas; smoke, acessibilidade
e comportamento continuam com seus próprios testes. As tolerâncias pertencem a design-diff.

As fichas existentes de E2E em Ponto/Governança/HRM, leitura em Patrimônio/Governança e
Painel de Patrimônio foram ligadas aos novos formatos. Outros contratos devem ser escolhidos
a partir da ficha e dos testes reais, nunca pela simples presença de um arquivo de teste.
