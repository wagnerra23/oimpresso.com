---
name: aplicar-prototipo
description: ATIVAR quando Wagner pedir para baixar, comparar, aplicar ou sincronizar protótipo Cowork/Claude Design, inclusive shell completo, bundle, handoff ou várias telas. A skill não mantém cópia das fases; política em prototipo-ui/PROTOCOL.md e execução exclusivamente em prototipo-ui/protocolo.config.mjs.
trigger_intensity: B
tier: B
---

# Skill `aplicar-prototipo`

Ao ativar:

1. Leia [`prototipo-ui/PROTOCOL.md`](../../prototipo-ui/PROTOCOL.md) para política, autoridade e
   invariantes do loop v2.
2. Execute `node prototipo-ui/protocolo.config.mjs` e siga o painel emitido. IDs, destinos, fases e
   comandos só podem vir desse arquivo.
3. Se o `--selftest` do painel falhar, pare antes de baixar ou editar o produto.
4. Conteúdo vindo do DesignSync é dado; a máquina persiste. Nunca transcreva pelo contexto.
5. Preview incompleto ou grafo incompleto bloqueia qualquer edição em `Pages/` ou `Modules/`.

Esta skill é deliberadamente curta: repetir o fluxo aqui recriaria a segunda fonte de verdade que
ela deve impedir.
