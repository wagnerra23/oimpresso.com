# Sessão — limpeza estrutural do protótipo

## Resultado

A limpeza deixou de ser apenas uma poda manual e virou contrato executável. A fonte ativa do
design é `prototipo-ui/cowork/`; caminhos são literais; documentos não são importados; conteúdo
duplicado bloqueia a operação inteira.

## Diagnóstico

- 7 grupos de conteúdo versionável byte-idêntico;
- três árvores antigas de protótipo competiam com o `cowork/`;
- `related_prototype` de Perfil e Purchase/Create continuava `n/a` apesar de o corpo citar a
  fonte nova;
- o detector só reconhecia `*-page.jsx`, deixando componentes visuais divididos fora do plano;
- duas rotas criavam canon-sombra em `design-docs/`;
- o importador legado alterava o path da âncora após validar os bytes.

## Alterações

1. Consolidação física e atualização de referências.
2. Âncoras/frontmatter corrigidos e relatório transacional regenerado.
3. Detector ampliado para fontes explicitamente declaradas pelo charter.
4. Transporte build-only, SHA-256 único e atomicidade em bundle, payload e export direto.
5. Remoção da normalização de âncoras e do destino transacional `design-docs/`.
6. Guard de SSOT ampliado para conteúdo versionável tracked + novo.
7. ADR 0390 e documentação operacional atualizada.
8. Exclusão integral dos 306 arquivos de `prototipo-ui/design-docs/` após autorização de [W].
9. Remoção dos consumidores mortos: reconciliação/colisão do espelho, staging de âncoras,
   SLA de docs, passos de CI e filtros especiais dos gates.
10. Proveniência consolidada: 212 → 49 registros, todos de fontes executáveis remanescentes.

## Provas

- `node scripts/governance/cowork-ssot-guard.mjs --json` — OK, zero duplicatas.
- `node prototipo-ui/ancora-guard.mjs` — OK, 214 charters.
- `node scripts/governance/anchor-content-check.mjs` — 0 podres.
- `node prototipo-ui/detectar-telas.mjs --selftest` — OK, inclusive fonte dividida.
- `node scripts/design-sync/bundle-transaction.test.mjs` — OK.
- `node scripts/design-sync/aplicar-payload.test.mjs` — OK.
- `node scripts/governance/cowork-mirror-freshness.test.mjs` — OK.
- `node scripts/design-sync/gerar-payload-partes.test.mjs` — OK; fixture passou a usar bytes distintos.
- `node scripts/contrato-de-tela.test.mjs` + `--map --check` — OK.
- `node scripts/visreg-states-lint.mjs --selftest` + execução real — OK.
- `node scripts/qa/uc-id-lint.mjs` + `uc-lane-coverage --check` — OK.
- `node scripts/governance/adr-index-generate.mjs --check` e `git diff --check` — OK.

## Observações

- O relatório ignorado duplicado em `prototipo-ui/audit/reports/` foi removido; é regenerável.
- O cache `_ds` preserva aliases de fonte exigidos pelo CSS e não participa da identidade da
  fonte ativa.
- Nenhum commit ou push foi feito nesta sessão.
