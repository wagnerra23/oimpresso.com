# Evidências dos fluxos de aplicação — 2026-09-09

## Contexto

[W] autorizou adaptar o processo após a comparação do #7071. Foram mantidos os produtores existentes: PHPUnit/Pest, Playwright e design-diff. O trabalho alterou o instrumento do processo, não os fluxos de negócio dos módulos.

## Implementação

- execucao passou a consumir playwright-json, conferindo arquivos, resultados, estatísticas, erro global e retries. JUnit/Pest manteve o contrato anterior. O reporter JSON foi acrescido à configuração Playwright sem remover JUnit.
- revisao passou a exigir revisor, critérios explícitos aprovados com justificativa e hashes das fontes/saída. Só fecha escrita documental (.md/.contract.json) ou leitura; não encerra implementação PHP/TS/JS.
- comparacao passou a reexecutar design-diff canônico com identidade por contrato, check de bugs, shell e dimensões declaradas; vincula snapshots, contrato, fonte, alvo e saída por hash. Não executa command do recibo. O comparador segue sendo dono das tolerâncias.
- --modulos passou a enumerar os 32 SCOPE.md e ligar os quatro playbooks por modulo_codigo explícito. Os demais têm entrada pelos contratos do módulo e design-sync, sem geração de 28 planos fictícios.
- Fichas ligadas: E2E Ponto 01, Governança 02, HRM 02/03, Patrimônio 07; revisão Governança 01/03a/04 e Patrimônio 04; comparação Patrimônio 07. Outros trabalhos continuam exigindo seleção de evidência conforme o pedido e os testes reais.

## Prova

48 testes Node passaram, sem skips. Incluíram o CLI dos quatro índices, inventário de módulos, estados recusados de Playwright/revisão e execução real do comparador com snapshots controlados: igualdade passou; tema, identidade e bug foram recusados.

O Playwright instalado executou um spec controlado sem navegador/aplicação para verificar o reporter JSON; o resultado real foi aceito pelo adaptador. Uma segunda execução verificou que testDir=e2e emite paths relativos a e2e: raiz_testes das fichas foi corrigida para e2e. A configuração do projeto foi carregada via --list e encontrou o spec do Painel de Ponto. Nenhum E2E de aplicação foi declarado executado.

integrity-check passou em todos os invariantes hard; diff --check limpo. Sem Pest/PHPStan local, alteração de banco, cálculo ou dado de produção. MCP do projeto não estava exposto.

## Limites operacionais

Nenhuma entrega dos módulos foi marcada feita por esta alteração e nenhum recibo aprovado foi fabricado. O recibo de revisão verifica uma declaração vinculada aos arquivos; não autentica a identidade do revisor. Comparação mede as dimensões declaradas, não substitui smoke ou teste de comportamento. O contrato do Painel de Patrimônio tinha copy pendente; D0 deve recusar enquanto não houver identidade comprovada, sem inventar copy de [W].

A extensão permite utilizar os formatos nos módulos, mas não significa que todas as 40 tarefas tenham sido executadas ou que todo critério de negócio esteja coberto. Fichas ainda sem contrato de evidência permanecem explicitamente pendentes. O tamanho do patch reúne os adaptadores, seus testes e os consumidores em uma intenção; não cria novo workflow nem outro comparador.
