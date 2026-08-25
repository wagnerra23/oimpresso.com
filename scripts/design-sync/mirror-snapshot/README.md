# mirror-snapshot — fonte versionada do runtime DesignSync

Este diretório é o **único destino versionado** dos artefatos compilados do Design System que o
shell `prototipo-ui/cowork/oimpresso.com.html` precisa para renderizar: CSS, bundle JavaScript e
fontes.

`prototipo-ui/cowork/_ds/` não é fonte. É cache ignorado pelo git, criado sob demanda pelo preview
a partir deste snapshot e pode ser apagado a qualquer momento. Nunca copie ou versione `_ds/`
dentro do espelho Cowork.

## Atualização

O painel executável [`protocolo.config.mjs`](../../../prototipo-ui/protocolo.config.mjs) é o dono
dos comandos de download, validação e materialização. Rode-o em vez de copiar uma receita deste
README.

O conteúdo precisa vir do payload/JSON do DesignSync e ser escrito pelo script; transcrição manual
é proibida. Um lote incompleto não substitui o snapshot vigente.

## Consumidores

- o preview materializa este snapshot no slug `_ds/<project-id>/` derivado do shell;
- o sentinela de drift usa os CSS versionados porque o CI não possui login no claude.ai/design;
- o applier do payload encaminha `_ds/**` para este diretório, sem criar uma segunda cópia
  versionada no espelho Cowork.

## Invariantes

- `prototipo-ui/cowork/_ds/` permanece no `.gitignore` e não pode conter arquivo rastreado;
- bundle ausente ou JavaScript inválido bloqueia o preview;
- fontes e demais dependências locais precisam fechar o grafo do shell;
- IDs, paths e comandos não são repetidos aqui: pertencem ao painel executável.

## Evolução

- 2026-08-18 — papel ampliado de snapshot de tokens para runtime completo; `_ds` do Cowork
  declarado explicitamente como cache derivado.
