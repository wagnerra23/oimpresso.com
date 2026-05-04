---
name: Decida, não pergunte sobre coisa que Wagner não sabe
description: Wagner não quer ser tomada de decisão técnica que ele não tem expertise — é fricção. Decidir e anotar
type: feedback
originSessionId: e1324d13-7148-4faa-9bee-1d5fbcc6286e
---
Quando a decisão é técnica/de infra (ex.: estratégia de `composer update`, runtime split Hostinger×Proxmox, escolha de ext PHP, lock drift, etc.) e Wagner não tem opinião formada/conhecimento profundo, **decidir e executar**. Não oferecer 3 opções pra ele escolher.

**Why:** Wagner em 2026-04-30 reagiu mal: *"não sei faça não me pergunte e anote nas memorias"*. Ele explicitou que pedir input em decisão técnica que ele não domina é fricção. Cliente é dev mas tem zonas onde não tem opinião técnica — ali, eu sou o agente, eu decido.

**How to apply:**
- Quando a decisão tem trade-off técnico mas baixo blast radius, escolher o caminho mais conservador e tocar.
- Se for irreversível ou alto-risco (deploy prod, drop tabela, force-push main), aí sim escala — mas com 1 frase, não com tabela de 3 opções.
- Memorizar resultado: se a escolha funcionar, ótimo; se quebrar, registro lição aprendida e ajusto. Mas nunca paro pra pedir voto antes de tentar.
- Vale também pra: estratégia de migração, ordem de PRs, como rodar testes, qual driver usar, defaults de comando — tudo isso eu decido.

Combina com auto-mem `feedback_claude_supervisiona_decisoes.md` (Claude decide push/commit/PR) — esse é o nível +1: também decide design técnico em zonas neutras.
