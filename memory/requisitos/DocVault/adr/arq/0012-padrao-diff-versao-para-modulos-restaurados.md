# ADR 0012 (arq) — Padrão "Diff de versão" para módulos restaurados após upgrade

**Status:** Aceito
**Data:** 2026-04-24
**Contexto de aplicação:** DocVault · documentação de módulos

## Contexto

A migração 3.7 → 6.7 do UltimatePOS foi feita sem versionamento de código — o servidor recebeu arquivos manualmente e muitos módulos perderam código. A branch `origin/3.7-com-nfe` preserva o snapshot pré-migração.

Módulos restaurados a partir do 3.7 (ex.: Officeimpresso) geram dezenas de ADRs, commits e entradas no CHANGELOG. Quem for manter depois precisa responder rápido a:

- **"Este controller é igual ao 3.7 ou foi adaptado?"**
- **"Essa rota existia antes ou é nova?"**
- **"O contrato com cliente legado (Delphi) mudou?"**

Sem um doc consolidado, cada dúvida vira uma viagem pelos ADRs + git log + grep + diff. Experiência frustrante.

## Decisão

Quando um módulo é **restaurado a partir de branch histórica** (ex.: `origin/3.7-com-nfe`) por razão de bug/regressão, **criar um doc de referência** dentro de 24h da restauração com o formato abaixo:

### Estrutura obrigatória

1. **TL;DR de 1 linha** — o que mudou no nível macro
2. **Controllers restaurados** — tabela ou lista com:
   - Nome do arquivo
   - 3.7 (lógica original)
   - 6.7 (lógica atual)
   - **Adaptações L-version** (mudanças forçadas pelo upgrade do framework)
3. **Controllers já existentes no target** — o que foi ajustado
4. **Endpoints — tabela comparativa** com coluna "Mudança contrato" (crítico pra clientes legados)
5. **Infraestrutura nova** — o que foi adicionado POR CIMA (não substituindo)
6. **Route names / mudanças cosméticas** — separado de mudanças semânticas
7. **Como usar esta nota** — checklist de uso (debug, add endpoint, restaurar mais código)
8. **Relacionado** — links pros ADRs, testes, memórias

### Onde guardar

- Em **memória durável** (`~/.claude/projects/<projeto>/memory/reference_diff_<origem>_vs_<destino>_<modulo>.md`) — não no repo, porque o conteúdo é consulta operacional, não decisão permanente.
- Entrada no DocVault CHANGELOG referenciando o arquivo de memória.
- ADR (este) justifica o padrão e deixa rastro auditável.

### Nome do arquivo

`reference_diff_<versao_origem>_vs_<versao_destino>_<modulo>.md`
Exemplos:
- `reference_diff_3_7_vs_6_7_officeimpresso.md`
- `reference_diff_3_7_vs_6_7_connector.md` (quando o restante do Connector for restaurado)

## Consequências

### Positivas
- Resposta em segundos pra 80% das dúvidas de manutenção
- Reforça disciplina de preservar contratos (coluna "Mudança contrato" força pensar)
- Facilita auditoria ("o que mudamos desde o 3.7?")

### Negativas
- Mais um arquivo pra manter — se desatualizar, vira armadilha
- Requer atualização toda vez que adaptação nova é feita no controller
- Duplicação parcial com CHANGELOG do módulo (mas em granularidade diferente — CHANGELOG é por versão release, diff é por versão framework)

### Mitigação
- Colocar linha `**Última revisão:** YYYY-MM-DD` no topo
- Incluir entrada no CHANGELOG DocVault toda vez que arquivo é alterado (rastreabilidade)
- Versionar o doc: se mudança é grande, criar `v2` em vez de sobrescrever

## Alternativas consideradas

- **Apenas ADRs + CHANGELOG** — rejeitado: sem consolidação é caro navegar
- **Diff gerado automaticamente via script** — futuro (quando maduro): comando artisan que compara `Modules/X` com `origin/<ref>:Modules/X` e renderiza o template
- **Guardar no repo** (`memory/requisitos/<Modulo>/DIFF.md`) — rejeitado: conteúdo é operacional (pra agentes de manutenção), não requisito funcional do módulo

## Exemplo concreto

`reference_diff_3_7_vs_6_7_officeimpresso.md` — primeiro uso do padrão, criado junto com esta ADR. Cobre Officeimpresso + Connector controllers restaurados, 3 endpoints preservados contratualmente, 7 infraestruturas novas.

## Relacionado

- ADR 0017 (projeto) — Restauração Officeimpresso 3.7 → 6.7
- ADR 0021 (projeto) — Contrato real API Delphi (3 gerações)
- `feedback_delphi_contrato_imutavel.md` — regra operacional permanente
- DocVault CHANGELOG 0.4.0 — primeira aplicação deste padrão
