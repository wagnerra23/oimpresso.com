---
sessao: "01"
titulo: Charter + casos do Programa — recibo
autor: "[C]"
data: 2026-09-25
---
# _saida-01 · Charter + casos do Programa

## Placar
entregue 2 de 2 provas · `Programa.charter.md` + `Programa.casos.md` em `resources/js/Pages/Documentacao/` ·
ausentes: nenhuma. **Non-Goals e Anti-hooks do charter vazios de propósito — pendente [W].**

## O que foi feito
- Carimbo por `node scripts/governance/criar-tela.mjs Documentacao/Programa PT-04 --prototipo prototipo-ui/cowork/Wagner/programa-doc-page.jsx --rota documentacao/programa --out <rascunho>`.
  Das 5 peças geradas entraram **charter, casos e o stub e2e**. `Programa.tsx` é `nao_toca` desta
  thread e o `.contract.json` (`governance/design/contracts/`) fica para a thread 02.
- ⚠️ **Exceção ao `nao_toca` — aprovada por [W] em 2026-09-26 (opção "A" no chat):** o esqueleto
  `Programa.tsx` do carimbo entrou. Sem ele o `integrity-check.mjs` reprova IT2 (charter sem `.tsx`
  irmão) e IT2b (`component:` morto), que são duros — e o merge levaria a quebra ao `main`. O stub
  não é renderizado por nada: a rota segue Blade. A thread 02 substitui o esqueleto pela tela real.
- ⚠️ **Exceção de prefixo declarada:** `e2e/documentacao-programa.spec.ts` está fora do prefixo
  `resources/js/Pages/Documentacao/`. Sem ele o `casos-gate` (required) reprova os 6 UCs como
  órfãos (G-2, medido: `❌ 6 violação(ões) NOVA(s)… uc-orphan`). É o stub do próprio carimbo, só
  `test.fixme` — cita os ids, não prova comportamento. Alternativa descartada: `casos:baseline:write`
  para esconder os 6, que seria grandfather de dívida nova.
- PT-04 Dashboard: o índice não indicava PT; é o mesmo PT do trio de 2026-08-06 (ver abaixo) e o que
  a estrutura pede (faixa de KPIs + seções).
- UCs `UC-PROGRA-01`–`06` derivados de SPEC `US-DOC-002` + contrato de paridade `AR-DOC-060`–`069` + ADR 0070
  (ordem de fonte canônica). Código só confirmou (`DocumentacaoController::programa`). Nada derivado de `.tsx`.

## Achados que a thread 02 precisa saber
1. **A rota já existe, em Blade.** `/documentacao/programa` → `DocumentacaoController::programa` +
   `resources/views/documentacao/programa.blade.php`. A thread 02 é migração, não tela do zero —
   a premissa "busca por `Programa.tsx` → 0" do `01-trio.md` é verdadeira mas incompleta.
2. **Já existia um trio de 2026-08-06** na branch `claude/documentacao-trio-pages-f1` (commit
   `4f0fc588d`) — o `Index.charter.md` em `main` (#7885) carrega os mesmos Non-Goals. Os ids `UC-PROGRA-01..04` foram
   preservados de lá; `05` (falha honesta, AR-DOC-061/063) e `06` (payload sem tenant, AC do SPEC) são novos.
3. **`UC-PROGRA-01` foi escrito no nível do PLANO, não por onda.** `AR-DOC-069` mediu que não há chave
   task→onda (§ D.3 sem coluna de task; `parent_plan` é por plano). O por-onda está no backlog do
   casos, nomeando a chave que falta — decisão [W].
4. **Sem bloco `alcance:` no charter.** O carimbo o gera no formato de módulo nWidart
   (`Modules/Documentacao/…DataController`, permission `documentacao.access`); nada disso existe.
   O `criar-tela` também imprime as "4 linhas de alcance" nesse formato — não se aplicam aqui.
5. O pedido do Cowork cita `prototipo-ui/contrato/programa-doc.contract.json` (pré-ADR 0397); o
   destino certo é `governance/design/contracts/` — já apontado no `01-trio.md`.

## Pendente [W] — Non-Goals e Anti-hooks
Candidatos que já circularam (**não copiados para o charter, atribuição não verificada**):
- o charter da branch `claude/documentacao-trio-pages-f1` traz 6 Non-Goals e 2 Anti-hooks sob o
  título *"Declarados por [W] em 2026-08-06"* — não achei registro independente dessa declaração;
- o rascunho do Cowork `cowork-inbox/programa-doc/Programa.charter.md` traz 7 Non-Goals e 4 Anti-hooks.
Os dois convergem em: read-only · estado só do MCP · sem cópia do plano · sem índice/gate novo · sem segredo.

## Gates
- `memory-schemas/validate.mjs` no charter: OK (casos fora de família com schema).
- `casos-coverage-guard.mjs`: ver corpo do PR (resultado colado lá).
