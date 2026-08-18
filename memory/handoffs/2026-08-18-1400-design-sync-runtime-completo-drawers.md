---
date: "2026-08-18"
time: "14:00 BRT"
slug: design-sync-runtime-completo-drawers
tldr: "O bundle integral fornecido por [W] foi provado como continuação exata do get_file truncado; as três fontes mono vieram do DesignSync autenticado. O snapshot canônico agora fecha 10/10 dependências, e o navegador confirmou os eventos de abrir/fechar drawers."
prs: [5914]
decided_by: [W]
next_steps:
  - "[W] revisar o drawer aberto no protótipo e autorizar explicitamente o merge do PR #5914 se estiver de acordo."
---

# O bundle estava completo; faltava colocá-lo na única fonte que o preview consome

## TL;DR

[W] forneceu `_ds_bundle.js` depois que a rota `get_file` do DesignSync cortou o arquivo em
256 KiB. A comparação byte a byte provou que a resposta truncada era prefixo exato do arquivo
fornecido: 259.769 caracteres coincidiram sem divergência, e o arquivo completo acrescentou os
27.585 caracteres restantes. O parser JavaScript aprovou as 9.204 linhas e o scan estático não
encontrou transporte de rede nem execução dinâmica.

## Runtime fechado

- bundle: 289.864 bytes, SHA-256
  `9d2f6ce4808e5c941910276980e97b75046222ce3ace023cb82b7f763c7415d8`;
- IBM Plex Mono 400: 14.708 bytes;
- IBM Plex Mono 500: 14.888 bytes;
- IBM Plex Mono 600: 15.620 bytes.

As fontes vieram de `DesignSync.get_file` com `truncated:false` e `isBase64:true`; a decodificação
exigiu assinatura `wOF2` antes de aceitar os bytes. O payload passou em `--dry` e depois foi
aplicado por `aplicar-payload.mjs`, portanto nenhum conteúdo foi transcrito para o snapshot.

`scripts/design-sync/mirror-snapshot/` segue como única fonte versionada. O comando
`--preview-ds` materializou 10 arquivos no cache gitignored `prototipo-ui/cowork/_ds/` e fechou com
zero ausentes e zero inválidos.

## Prova no navegador

Depois de recarregar o protótipo:

- `Ver origem de Receita mês` abriu o drawer `Faturamento` com fonte, leitura e ações;
- `Fechar` removeu o diálogo;
- `Configurar` abriu o drawer `Configurar a Jana` com toggles e conteúdo;
- o drawer `Faturamento` ficou aberto no navegador para revisão de [W].

## Testes

Passaram o parser do bundle, `aplicar-payload.test.mjs`,
`cowork-mirror-freshness.test.mjs`, `protocolo.config.mjs --selftest`, `--preview-ds` e
`git diff --check`. A única expectativa obsoleta da suíte — bundle deliberadamente ausente — foi
atualizada para o novo estado canônico; os BITE tests herméticos de dependência ausente continuam
verdes.

Nenhum arquivo de produto em `Modules/` ou `resources/js/Pages/` foi alterado.
