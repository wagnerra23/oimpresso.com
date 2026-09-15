---
sessao: "09"
titulo: Testes da ponte — o validador que roda ANTES do PR existir
autor: "[CC]"
criado: 2026-08-23
executado: sim — 39 reprovações reais na primeira rodada, sobre os meus próprios arquivos
---

# Testes da ponte

## Por que não bastavam os instrumentos do `_pedido-CL-instrumentos.md`

C.01–C.05 testam o **repo**: lane, catraca, tautologia, id de UC, ledger. Todos rodam **depois que o PR existe**.
As falhas de handoff acontecem **antes**: no Cowork, no arquivo que eu escrevo e você cola. Nenhum script do
`main` pode pegá-las — o [CL] só recusa, e a rodada se perde.

Este validador roda no artefato do Cowork. **É a única catraca do ciclo que age antes do PR.**

## O validador — `ponte-handoff-lint.mjs`

| Check | O que mata | Determinístico |
|---|---|---|
| **V.01** | pedido que cita arquivo do Cowork (invisível ao [CL]) sem anexo inline nem nota | sim |
| **V.02** | id de UC com prefixo > 6 chars (a regra do `uc-regex.mjs`) | sim |
| **V.03** | pedido sem prefixo permitido ou sem "o que é recusa legítima" | sim |
| **V.04** | sessão sem bloco "Contrato de paralelismo" (Lei 1) | sim |
| **V.05** | UC marcado ✅ sem veredito real em `last_run_ci` | sim |
| **V.06** | número medido afirmado sem data/base no frontmatter | sim |

**Controle negativo embutido** (Lei C): 3 fixtures que **têm** que reprovar. Se qualquer uma passar, o
validador aborta com exit 2 antes de julgar nada — catraca que não sabe reprovar não julga.

## Primeira rodada — 39 reprovações, todas minhas

| Achado | Gravidade | Estado |
|---|---|---|
| **4 pedidos com `UC-PTPAINEL-*`** (prefixo de 8 chars) enquanto o `casos.md` **já foi corrigido para `UC-PAINEL-*`** | **alta** — o `COLAR-NO-CODE` ficou **auto-contraditório**: corpo dizia PTPAINEL, anexo dizia PAINEL | ✅ corrigido (37 ids) |
| 2 pedidos citando `cowork-inbox/ponto-dashboard/Index.casos.md` sem anexo | alta — é exatamente o motivo de recusa que blindei num arquivo e esqueci nos outros | ✅ nota de handoff acrescentada |
| V.06 em `Index.casos.md` | falso positivo — o arquivo tem `last_run:`, meu check não o aceitava | ✅ check corrigido, arquivo intocado |

### O achado que importa
A correção do id **veio de fora** (você ou o [CL] arrumou o `casos.md` para `UC-PAINEL`) e **os 4 pedidos
não foram atualizados**. Isso é a classe de falha mais cara do ciclo: o artefato corrigido e o pedido que o
descreve divergem em silêncio. Colar qualquer um dos 4 entregaria ao [CL] dois ids diferentes para o mesmo UC.
Nenhum instrumento do `main` veria isso — o `main` não conhece o Cowork.

## Wiring

| Onde | Cadência | Gate |
|---|---|---|
| Antes de qualquer colagem — rodar sobre `cowork-inbox/ponte/` | a cada pedido gerado | **obrigatório**: pedido que reprova não é colado |
| Se descer pro repo: `scripts/qa/ponte-handoff-lint.mjs` + `.test.mjs` irmão em `governance-script-tests.yml` | por PR que toque `cowork-inbox/` | required desde já (é determinístico) |

## O que ele NÃO cobre (declarado)
- Não julga **conteúdo** de UC — se o critério de aceite é bom, só uma pessoa diz.
- Não vê o repo: não sabe se o `alvo` do contrato existe (isso é P.13 / C.01).
- Não vê PR, review ou deploy — segue sendo bloco E do `05-DIAGNOSTICO`, e é seu.
