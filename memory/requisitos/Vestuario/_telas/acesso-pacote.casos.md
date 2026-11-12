---
id: requisitos-vestuario-telas-acesso-pacote-casos
casos: Acesso às rotas do Vestuario pelo pacote da empresa · /vestuario/etiquetas, /vestuario/curva-compra, /vestuario/remarcacao
irmaos: ../SDD-tela-etiqueta-tag-v1.0.md (CU-VEST-08)
tecnica: Caso de uso = narrativa de quem acessa + critério de aceite verificável (Dado/Quando/Então)
por_que: o módulo é vendido por pacote; empresa que não o contratou não pode abrir as telas pela URL.
owner: wagner
last_run: "2026-11-09"
last_run_ci: "0 UC executado na lane — nasce neste PR"
related_us: [US-VEST-020, US-VEST-031, US-VEST-032]
---

# Casos de Uso & Aceite — Acesso pelo pacote da empresa

> **2ª casa do contrato** (`memory/requisitos/<Mod>/_telas/`): a regra vale para as três áreas do módulo,
> não para uma tela. Deriva do SDD `CU-VEST-08` ("não há entry de sidebar nem acesso"), nunca do código.
>
> Os testes rodam nos tenants 98 × 99. Nada é testado na biz=4.

## Rastreabilidade

| UC | O que defende | Peso | Âncora | Teste |
|---|---|---|---|---|
| UC-VPAC-01 | empresa sem `vestuario_module` recebe 403 em etiquetas, curva e remarcação; com o pacote, acessa | must `[T0]` | CU-VEST-08 | `VestuarioPacoteGateTest` |

## UC-VPAC-01 · Sem o pacote não há acesso `[T0]`
- **Persona:** usuário de uma empresa que não contratou o módulo Vestuário.
- **Aceite:** Dado uma empresa sem `vestuario_module` no pacote · Quando abre ou chama qualquer rota de
  `/vestuario/etiquetas`, `/vestuario/curva-compra` ou `/vestuario/remarcacao` · Então recebe 403, mesmo com as
  permissões da função e com a remarcação ligada. Dado uma empresa com o pacote · Então a mesma chamada
  chega à tela ou à validação do controller.
- **Teste:** `Modules/Vestuario/Tests/Feature/VestuarioPacoteGateTest.php`
- **Regressão que defende:** empresa sem o pacote abrir as telas pela URL, ou rota nova do módulo nascer sem o gate.
- **Status: ⬜** (não verificado — nasce neste PR; roda na lane `verticais-pest`)
