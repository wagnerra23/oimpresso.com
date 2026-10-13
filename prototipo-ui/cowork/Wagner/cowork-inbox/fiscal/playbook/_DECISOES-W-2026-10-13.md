# _DECISOES-W-2026-10-13 — Fiscal

> **Fonte:** [W] 2026-10-13, no chat da sessão "Gerente da fila de merges (13/10)" (literal: *"consolidar no NfeBrasil"*).
> Origem: bloqueio da thread 23 ("consolidação NfeBrasil × NFSe ([W] pendente em fiscal-faturamento.md)").

| id | pergunta | resposta | thread |
|---|---|---|---|
| D-NFSE-CONSOLIDA | A NFS-e fica num módulo próprio (`Modules/NFSe`) ou passa a ser do `Modules/NfeBrasil`? | *Consolidar no NfeBrasil* | 23 |

## Efeito
- A thread 23 (retenções federais na NFS-e) destrava com o prefixo em `Modules/NfeBrasil/`, não em `Modules/NFSe/Services/`.
- O `Modules/NFSe` passa a ser candidato à deprecação, com plano próprio (agente `deprecar-modulo`) antes de mexer em tabela ou rota.
- `memory/dominio/fiscal-faturamento.md` registra a decisão com data.
