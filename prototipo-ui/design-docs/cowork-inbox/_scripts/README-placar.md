# Placar do playbook

O índice declara o trabalho; o placar valida o schema e as dependências antes de avaliá-lo.
Rode `node prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs --indice <índice.md> --root . --proximo`.
Dependências Node: `ajv` e `ajv-formats`, as mesmas do validador de memória.

`contem`, `arquivo` e afins comprovam estrutura, não execução. Em 08/09/2026 o fechamento
passou a exigir também uma prova `execucao`, com `path` para o recibo JSON abaixo e
`testes` listando os arquivos de teste exigidos pelo plano. O recibo não escolhe a suíte.
Saídas antigas continuam como registro histórico; sem recibo verificável são “em curso”
(retomar/validar), não uma afirmação de que a correção antiga deixou de existir.
Tarefas de medição sem teste automatizado também não recebem fechamento automático.

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

Leitura de schema/grafo não é cobertura dos fluxos de negócio. A prova execucao atual
consome o resumo PHPUnit/Pest com contagem de assertions. Não aceita automaticamente
JUnit de E2E sem esse campo, saída TAP/Node, comparação visual nem parecer documental.
Essas tarefas precisam de um contrato de evidência apropriado; até lá ficam sem
fechamento certificado. Não converter exitCode=0 em assertions inventadas.
Módulos sem playbook não pertencem ao universo deste placar; verificar sua aplicação
por scripts/design-sync/status.mjs e os contratos/testes próprios.
