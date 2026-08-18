---
date: "2026-08-18"
time: "10:45 BRT"
slug: design-sync-truncado-preview-fail-closed
tldr: "O download do Design System não perdeu drawers no fonte: perdeu o bundle compilado no teto de 256 KiB. O JSON real dizia truncated:true, mas o exportador descartava o metadado e o preview incompleto saía 0. O PR #5910 transforma truncamento, bundle inválido/ausente e fontes ausentes em STOP antes de qualquer edição de produto."
prs: [5910]
decided_by: [W]
next_steps:
  - "Autenticar o Claude/DesignSync no navegador da sessão e baixar `_ds_bundle.js` completo + as três fontes mono 400/500/600; persistir com `--ds-runtime`."
  - "Rodar `node scripts/governance/cowork-mirror-freshness.mjs --preview-ds`; só exit 0 autoriza comparação/aplicação."
  - "Revisar/mergear #5910. Não reverter em lote os PRs de produto já mergeados sem decisão humana por arquivo."
---

# DesignSync truncava o bundle e o preview apagava drawers em silêncio

## TL;DR

O fonte tinha drawers e eventos; o runtime não, porque `_ds_bundle.js` chegou truncado e o preview
tratava dependências ausentes como aviso. O PR #5910 fecha os dois silêncios antes de editar produto.

## O que realmente aconteceu

O fonte da Jana em `prototipo-ui/cowork/jana-merge.jsx` contém `JmMetaDrawer`, callbacks,
eventos, skeleton e dropdown. A perda ocorreu na dependência compilada: a resposta persistida real
de `DesignSync.get_file` para `_ds_bundle.js` tinha `truncated:true`, 259.769 caracteres e terminava
no meio de `label: 'Pa`. O teto observado foi 256 KiB. CSS pequeno desceu inteiro; o bundle não.

O exportador lia somente `path` e `content`, descartava `truncated` e gravava JavaScript cortado.
Depois, `--preview-ds` apenas avisava que o bundle/fontes faltavam e encerrava 0. Sem `Drawer` no
runtime, o próprio protótipo executava o fallback `if (!Drawer || !meta) return null`; a parte
interativa desaparecia, embora continuasse no fonte. A tela degradada foi tratada como evidência.

## Auditoria do que o agente editou

O trabalho já mergeado foi além de baixar artefatos. A sequência medida no histórico foi:

| PR/commit | escopo resumido |
|---|---|
| #5878 `e24303d7b` | configuração do drawer — 9 arquivos, +508/−12 |
| #5882 `0efaa94f6` | meta drawer — 9 arquivos, +670/−134 |
| #5889 `fa7357886` | backend HITL — 11 arquivos, +468/−10 |
| #5895 `9f259fa57` | modal HITL frontend — 13 arquivos, +417/−44 |
| #5897 `88fa46031` | correção CSS de header/pill — 16 arquivos, +30/−19 |
| #5901 `56085cb30` | payload de preview do chat — 4 arquivos, +249/−3 |

Não houve reversão automática. Esses merges misturam comportamento legítimo e implementação de
produto; desfazê-los em bloco seria uma ação destrutiva diferente do pedido. O PR #5910 toca **zero**
arquivos em `Pages/` ou `Modules/`.

## Correção do protocolo

- `decodeDesignSyncPayload()` recusa `truncated:true` antes da escrita e nomeia que nada foi escrito;
- `isBase64:true` é decodificado para `Buffer`, preservando fontes/assets byte a byte;
- `--ds-runtime` grava bundle, CSS e assets no único snapshot que `--preview-ds` consome;
- templates/fontes editáveis continuam em `--ds`; o runtime recusa tipo errado e path traversal;
- `--preview-ds` valida `_ds_bundle.js` com o parser do Node e sai 1 para bundle/fonte ausente ou inválido;
- hook, skill e protocolo mandam **PARAR** e proíbem editar `Pages/`/`Modules/` enquanto o preview não sair 0.

## Provas executadas

- suite `cowork-mirror-freshness.test.mjs`: verde, incluindo truncamento sem escrita, base64
  byte-idêntico, traversal recusado, bundle ausente e bundle sintaticamente cortado;
- `protocolo.config.mjs --selftest`: verde;
- os dois testes dos hooks de opt-in DesignSync: verdes;
- `node --check` no comparador e no hook: verde;
- `git diff --check`: verde;
- busca no diff por `Modules/`/`resources/js/Pages/`: zero.

O payload real truncado também foi passado ao novo `--export-from --ds-runtime`: saída 2 e nenhuma
escrita. O preview local atual sai 1, corretamente, porque ainda faltam `_ds_bundle.js` completo e
três fontes mono.

## Limite residual e estado MCP

O navegador interno abriu a página do Claude, mas a sessão não estava autenticada. Portanto não foi
possível recuperar o artefato completo nesta rodada. Candidatos antigos de `_ds_bundle.js` no temp
passavam no parser, porém não continham Drawer/Skeleton; usar um deles seria remendo stale, e foram
recusados.

Não houve canal MCP/DesignSync exposto ao Codex nesta sessão. A evidência veio dos JSONs persistidos,
git e fonte local. A ausência de consulta MCP foi indisponibilidade de canal, não afirmação de que o
estado remoto não existe.
