---
id: requisitos-connector-features-openapi-connector-tasks
feature: openapi-connector
module: Connector
---

# Tasks — OpenAPI 3.0 seguro para o Connector

> Estado `todo/doing/done` vive no MCP (`parent_plan:connector-openapi`). Este arquivo mantém
> ordem, dependências, cobertura e DoD. Execução de PHP/Scribe ocorre no CT 100.

### T-01 · Criar contrato failing-first da configuração segura do Scribe
> blocked_by: — · covers: AC-1, AC-2, AC-3, AC-5 · us: US-CONN-013 · estimate: 1h

Adicionar teste em `Modules/Connector/Tests/Feature/` que primeiro falha contra o estado atual:
matcher largo, response calls GET, fontes de Model reais e saída pública. O teste deve afirmar o
comportamento seguro, não chaves cosméticas da configuração.

**DoD:** o teste falha pelas violações conhecidas no estado anterior e passa somente quando a
configuração impede `oauth/*`, response calls, Model real e publicação antes do gate humano; arquivo
confirmado na suite Connector do `phpunit.xml` e executado no CT 100.

### T-02 · Tornar a geração fail-closed e sem dados reais
> blocked_by: T-01 · covers: AC-1, AC-2, AC-3, AC-5, AC-7 · us: US-CONN-013 · estimate: 1h

Curar `config/scribe.php` reutilizando o Scribe existente: escopo Connector, zero response call,
exemplos sintéticos e destino controlado. Não criar rota pública nem segunda configuração sem
necessidade demonstrada.

**DoD:** teste T-01 verde no CT 100; duas gerações no mesmo SHA terminam com exit 0 e diff vazio;
`route:list` não ganha superfície de documentação pública.

### T-03 · Curar contratos e cobrir o inventário do Connector
> blocked_by: T-02 · covers: AC-1, AC-3, AC-4, AC-6 · us: US-CONN-013 · estimate: 3h

Documentar autenticação, parâmetros, requests, responses, content-types e erros usando o charter,
FormRequests e testes existentes. Preservar endpoints Delphi literais e a ordem de middleware.

**DoD:** comparação entre `route:list --path=connector/api` e paths OpenAPI lista zero rota
Connector ausente e zero `oauth/*`; suites Connector existentes passam; scan do artefato encontra
zero segredo/PII e a revisão não mostra payload real.

### T-04 · GATE HUMANO — decidir audiência, URL e política de acesso
> blocked_by: T-03 · covers: AC-5 · us: US-CONN-013 · estimate: 30min

Apresentar a [W]/[F] o artefato controlado e as opções público, autenticado ou interno, incluindo
o conflito de `/docs`. Sem decisão explícita, a feature permanece não publicada.

**DoD:** decisão de audiência/URL/acesso registrada em comentário de PR ou ADR se estrutural;
nenhum deploy do artefato antes desse registro.

### T-05 · Servir o artefato conforme a decisão de acesso
> blocked_by: T-04 · covers: AC-5, AC-7 · us: US-CONN-013 · estimate: 1h

Implementar somente a opção aprovada, fail-closed, sem alterar `/connector/api/*` nem o contrato do
Officeimpresso. Se a decisão for “interno”, não criar rota pública apenas para fechar a task.

**DoD:** acesso permitido e negado provados conforme a decisão; duas gerações no mesmo SHA mantêm
diff vazio; rotas e middleware Connector permanecem idênticos no comparativo antes→depois.

### T-06 · Fechar o loop — smoke de suporte + âncora da US-CONN-013
> blocked_by: T-05 · covers: AC-6 · us: US-CONN-013 · estimate: 1h

Felipe ou Maiara executa o roteiro em um endpoint Delphi e um endpoint JSON: localizar auth,
request, response, content-type e erros sem abrir o código. Registrar o resultado literal e então
atualizar `**Implementado em:**` da US.

**DoD:** smoke humano registrado + `node scripts/governance/anchor-lint.mjs memory/requisitos/Connector/SPEC.md`
mostra US-CONN-013 `anchored_ok`, com ``verificado@<sha7> (<data>)`` apontando o artefato vivo.
