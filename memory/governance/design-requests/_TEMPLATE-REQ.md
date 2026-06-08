---
req: REQ-NNN
data: 2026-MM-DD
tela: <Mod>/<Tela>
arquetipo: form | lista | dashboard | kanban | detalhe | relatorio | drawer
persona: <larissa | eliana | tecnico | ...>
status: received          # received | processing | done
origem: <handoff / issue / pedido Wagner / nota>
---

# REQ-NNN — <título curto do pedido>

## Delta manifest — o que mudou (ancorado por LINHA, não a tela toda)
> Claude Design lê SÓ isto, não relê a tela inteira (TraceLLM: localized rewrite).

- **arquivo:** `resources/js/Pages/<Mod>/<Tela>.tsx`
  - seção: `<nome>` · linha ~`NNN` · mudança: `<add campo X / trocar Y / ...>`

## Checkpoint — até onde fiz (granularidade = 1 seção, nunca no meio)
- [ ] `<seção A>` — untouched | processing | done
- [ ] `<seção B>` — untouched | processing | done

## Resultado
- **diff:** `<hash do commit / link do PR>`   # retorna igual no retry (idempotente)
- **grade pós:** `<nota screen-grade, se rodada>`
- **golden:** `<arquétipo + 10 regras R1-R10 conferidas?>`
