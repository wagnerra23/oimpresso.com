---
sessao: "05"
titulo: casos.md com UC · PaymentGateways (promover backlog) (abrir com `/onda prontidao --thread 05`)
dono: "[CL]"
base: árvore dd380c33a374 (lida 2026-09-23 11:20 UTC)
prefixo: Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.casos.md · tests/
nao_toca: Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.tsx · Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.charter.md
---
# 05 · casos.md com UC · PaymentGateways (promover backlog)

## Telas
- `Settings/PaymentGateways/Index` · charter `Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.charter.md` · alvo `Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.casos.md`

**Nota:** O `Index.casos.md` **existe** (6.404 B), mas só tem `## Backlog de casos (sem id — entram quando um teste citar o UC-id)` (:36). Zero UC com id ⇒ o readiness conta 0. O trabalho é **promover** itens do backlog a UC-id com teste citando, não reescrever o arquivo.

## O que fazer
1. Ler `scripts/lib/uc-regex.mjs`: é ele que conta UC (`contaUCs` delega para lá). O heading precisa casar **com essa lib**, não com a intuição.
2. Usar como molde uma tela pronta do mesmo arquétipo, por exemplo `resources/js/Pages/Cliente/Index.casos.md`.
3. Derivar os UCs **do charter + controller real** (`Inertia::render`), nunca do protótipo. Mínimo: 1 UC do caminho feliz da persona. Não inventar fluxo que a tela não tem.
4. Cada UC-id citado por ≥1 teste (casos-gate G-2). Teste roda no CT 100, nunca local.

## PARAR SE
- O charter contradisser o que o `.tsx` faz: parar e reportar. Charter é oráculo, e consertar charter está fora do prefixo.
- Passar de 300 linhas: dividir por tela, 1 PR por tela.

## Prova
- `Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.casos.md` contém UC-id reconhecido pela lib
- teste citando cada UC-id · `_saida-05.md` com a saída de `node scripts/qa/prototipo-readiness.mjs` mostrando as telas fora de 1-ciclo
