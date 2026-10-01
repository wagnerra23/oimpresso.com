---
date: "2026-10-01"
time: "07:45 BRT"
slug: leva-handoff44-threads-playbook
tldr: "Handoff (44) importado (10 playbooks novos) e executado em sessões frescas por thread: ~60 PRs mergeados, 6 falhas Tier 0 fechadas no caminho, leva parada pelo limite de gasto mensal. Decisões [W] e uploads ao Cowork pendentes."
decided_by: [W]
prs: [8321, 8332, 8326, 8380, 8383]
next_steps:
  - "Subir os _saida-*.md pendentes ao Cowork: node scripts/design-sync/pendentes-cowork.mjs --plano"
  - "Responder as decisões [W] listadas abaixo"
  - "Logar no Chrome e conferir em produção as telas mexidas"
---

# Leva do handoff (44): threads de playbook em sessões frescas

## Estado MCP no momento
- Brief #700: sem cycle ativo; HITL Wagner 3; 23 commits/24h; ADRs novas 0420 e **0421 (aviso ao titular, desta leva)**.
- Sessões filhas: interrompidas por **limite de gasto mensal** (reset semanal 2026-10-04 13h). 5 chips foram iniciados pelo [W] em sessões próprias (Repair UCs skipped, contrato-de-tela em `Modules/*/Pages`, POST nos 30 módulos, `activity_log` business_id, permissões Officeimpresso no negócio operador).

## O que aconteceu
1. Handoff (44) importado pela rota ZIP (`receber-handoff --conta w`): 10 playbooks em `cowork-inbox/` (#8321).
2. [W] "Aprove todos" → gravado em `_DECISOES-W-2026-10-01.md` por playbook (#8332) e subido ao Cowork. Critério: aprovar = a opção que a thread já assume. **Falhou 3× na paridade** (Repair D1, Superadmin D1, QR do Officeimpresso D3): as sessões compararam os dois lados e pararam antes de perder dado — essas decisões voltaram ao [W].
3. Uma sessão nova (agente em worktree isolado) por thread, PR até o merge. Placar: Lote-trio, Arquivos, Crm (CL), Connector 01–05 e Produto 01/02/05/06/07/A2 completos; Atendimento 01–04; Repair 01; Superadmin 01/05; Officeimpresso 01/02/04/05(parcial)/06/08(parcial)/A1.
4. Leis do repo que mudaram o roteiro do Cowork (erratas nos `_saida`): IT2 (charter só com `.tsx`) e "Contratos de tela" (contrato só com a tela) — charter/contrato entram no PR da tela.

## Falhas reais fechadas no caminho (Tier 0 / produção)
- `salvar-equipamento/{business_id}` gravava em qualquer negócio (#8358; usuário central Delphi preservado, medido).
- `GET /connector/regenerate` rodava `passport:install --force` (#8374).
- Passport 13 com UUID em coluna int: criar credencial falhava desde 2026-04-21 (#8366).
- Segredo OAuth exposto nos painéis Connector/Officeimpresso (#8350, #8362).
- Import de preço gravava em produto de outro negócio (539 SKUs colidem; #8380, aprovado [W]).
- 3 contagens do Painel Crm sem `business_id` (#8369); `CrmUtil` pedindo colunas inexistentes → API 500 (#8373).
- Teste instável `FetchTemplatesTest` (estado estático do Eloquent; #8355). Lanes MySQL novas: Auditoria (#8339), Connector (#8382).

## Pendente
- **#8383** (Connector `show` 500 + testes `auth:api`): PII scan corrigido (fixture tinha CNPJ de cliente real); aguardando CI. A sessão morreu antes de rodar a lane 3×.
- **Uploads ao Cowork:** ~34 `_saida-*.md` não subiram (`pendentes-cowork.mjs --plano`). O índice do Cowork precisa aplicar as `_DECISOES-W` para o placar marcar `feito`.
- **Smoke em produção logado:** nenhuma tela mexida foi vista logada (sessões sem senha).
- `visual-regression` vermelho em todo PR: `Governance/Dashboard` 2,4051% herdado da main — baseline precisa de aprovação visual [W].

## Decisões [W] abertas
Officeimpresso 03 (dropar `senha`/`contra_senha` — irreversível, pedir "03 sim") · Repair D1 (venda com reparo: migrar/aposentar/manter) · QR do Officeimpresso (403 ou ajustar pacotes) · Superadmin D1 (Páginas × Cms) · Officeimpresso D1 (painel delegado × charter do Connector) · segredo com hash × contrato Delphi · canal do aviso ao titular (ADR 0421) e quem recebe `arquivos.restore`/`arquivos.governanca` · flag `useV2OfficeimpressoLicencas` · cascata ao excluir categoria pai · `EmptyState no-perm` (DS) · `UnitController@update` apaga unidade base (estoque).

## Próximos passos pra retomar
`node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/<mod>/playbook/00-INDICE.md` por módulo, depois `/onda <Mod> --thread <NN>`. Prompt-base das sessões: exceções do placar (decisão em `_DECISOES-W`, prova deslocada por IT2, contrato copiado de `_saida`).

## Lições
- "Aprovar em nome do [W]" decisões de redirecionar/fundir/aposentar sem medir paridade errou 3×; a condição de paridade no prompt é o que segurou. Decisão destrutiva irreversível não se aprova por critério genérico.
- Paralelismo alto saturou a fila do CI (200+ runs) e o orçamento; a fila por módulo é o limite natural.
