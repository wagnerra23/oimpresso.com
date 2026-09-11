# Protótipo: fonte única, importação literal e zero duplicata versionável

**Data:** 2026-09-11 12:09 BRT  
**Branch:** `codex/prototipo-fonte-unica-sem-duplicatas`  
**Decisão:** [ADR 0390](../decisions/0390-prototipo-fonte-unica-build-sem-canon-sombra.md)

## Pedido e causa

[W] apontou que a limpeza anterior ficou limitada à raiz: ainda havia conteúdo duplicado,
arquivos mortos e âncoras que mudavam de lugar ao importar. A auditoria confirmou 7 grupos de
duplicata exata entre arquivos versionáveis e duas interferências no transporte: `.md` era
desviado automaticamente para `design-docs/`, e o applier legado achatava paths de
`related_prototype` quando encontrava um basename homônimo.

## O que foi feito

- consolidou `compras-grade-matrix`, `perfil` e `inventario-migracao` no `cowork/`; 16 caminhos
  duplicados/legados foram removidos ou movidos, com referências vivas atualizadas;
- corrigiu os frontmatters de `User/Perfil` e `Purchase/Create`: as fontes agora aparecem no
  relatório como `ANCHORED`, ligadas às Pages existentes;
- o detector passou a incluir fonte dividida declarada no charter, mesmo sem sufixo
  `-page.jsx` (`compras-grade-matrix.jsx` era invisível antes);
- bundle v2, payload legado e `--export-from` passaram a recusar lote com documento/canon,
  extensão fora do build ou SHA-256 duplicado; a operação é atômica;
- a transação deixou de tocar `design-docs/` e a normalização automática de âncoras foi removida;
- após autorização explícita de [W], os **306 arquivos** de `design-docs/` foram excluídos;
- saíram também os dois utilitários exclusivos do espelho antigo, os modos de staging/SLA de
  documentos, os passos correspondentes do CI e as exceções que escondiam aquela árvore;
- a proveniência foi podada de 212 para 49 registros executáveis, sem referências à árvore removida;
- `cowork-ssot-guard` passou a comparar conteúdo de arquivos tracked e novos, não apenas pastas;
- protocolo, README, fila e estrutura foram alinhados à fonte única.

## Medição e validação

- duplicatas exatas versionáveis em `prototipo-ui/`: **7 grupos → 0**;
- allowlist transitório de protótipos: **3 → 0**;
- `ancora-guard`: **214 charters, OK**;
- conteúdo de âncoras: **73 resolvíveis, 0 podres**, 5 avisos `NO-MODULE` herdados;
- relatório transacional: `perfil-page.jsx` e `compras-grade-matrix.jsx` agora resolvem,
  respectivamente, `User/Perfil.tsx` e `Purchase/Create.tsx`;
- selftests do detector, bundle, applier e frescor: verdes.
- contrato de tela, estados de visreg, IDs/lanes de UC e índice de ADRs: verdes.

## Fronteira explícita

`prototipo-ui/cowork/_ds/` é cache ignorado e regenerável de preview. O bundle upstream atual
entrega o mesmo arquivo variável IBM Plex Sans sob quatro aliases de peso; esses aliases não são
fonte versionável nem dados mortos e foram preservados para não quebrar as quatro referências
`@font-face`. A proibição de duplicata incide sobre fontes versionáveis/ativas. A antiga árvore
`design-docs/` não existe mais no worktree; seu conteúdo continua recuperável pelo histórico Git.

## Próximo passo

Revisar o diff humano e, quando aprovado, commitar. A próxima importação já nasce fail-closed e
não consegue recriar as classes removidas.
