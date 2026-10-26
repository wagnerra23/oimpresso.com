---
name: aplicar-prototipo
description: ATIVAR quando Wagner pedir para comparar ou aplicar protótipo no produto, inclusive várias telas. A fonte do protótipo é o arquivo sob prototipo-ui/ no git (ADR 0438) — não há download do Cowork. A skill não mantém cópia das fases; política em memory/reference/prototipo-ui/PROTOCOL.md e execução exclusivamente em scripts/design/protocolo.config.mjs.
trigger_intensity: B
tier: B
---

# Skill `aplicar-prototipo`

Ao ativar:

1. Leia [`memory/reference/prototipo-ui/PROTOCOL.md`](../../prototipo-ui/PROTOCOL.md) para política, autoridade e
   invariantes do loop v2.
2. Execute `node scripts/design/protocolo.config.mjs` e siga o painel emitido. IDs, destinos, fases e
   comandos só podem vir desse arquivo.
3. Se o `--selftest` do painel falhar, pare antes de editar o produto.
4. A fonte é o git: não baixe protótipo do Cowork (ADR 0438). Revisão visual é no Artefato publicado a partir do arquivo do repo.
5. Preview incompleto ou grafo incompleto bloqueia qualquer edição em `Pages/` ou `Modules/`.

Esta skill é deliberadamente curta: repetir o fluxo aqui recriaria a segunda fonte de verdade que
ela deve impedir.
