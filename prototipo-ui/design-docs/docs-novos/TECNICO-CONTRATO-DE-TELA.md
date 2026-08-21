---
id: reference-tecnico-contrato-de-tela
name: Técnico — Contrato de tela
description: O trio componente + charter + casos que define uma tela pronta, o contrato que trava copy e estados no CI, e o pré-flight que evita reinventar o que já existe.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: tecnico
nav_order: 40
lente: [construir]
---

# Técnico — Contrato de tela

> Uma tela não está pronta porque "está bonita no protótipo". Está pronta quando existe o
> **trio** — e a prontidão é medida por máquina
> ([`prototipo-readiness.mjs`](../../scripts/qa/prototipo-readiness.mjs)), não por fila manual.

## O trio

| Arquivo | O que declara |
|---|---|
| `resources/js/Pages/<Mod>/<Tela>.tsx` | a implementação |
| `<Tela>.charter.md` | o que a tela **é**: propósito, persona, densidade, e o que ela **não** faz |
| `<Tela>.casos.md` | casos de uso numerados (UC) — a régua de aceite |
| `prototipo-ui/contrato/<tela>.contract.json` | seções + copy literal + estados; trava o comportamento no CI |

O charter tem schema próprio ([`charter.schema.json`](../../scripts/memory-schemas/charter.schema.json))
e dois gates: [`charter-us-lint`](../../scripts/governance/charter-us-lint.mjs) e
[`charter-live-signal`](../../scripts/governance/charter-live-signal.mjs) — charter marcado
`live` sem sinal de produção reprova.

O trio **fica colado ao fonte**, ao lado do `.tsx`: a [ADR 0365](../decisions/0365-trio-de-tela-fica-colocado-reverte-eixo-0364.md)
reverteu o eixo de localização da [0364](../decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md)
— o trio em si e o gate de UC ([ADR 0264](../decisions/0264-governanca-executavel-trio-dominio-e2e.md)) seguem intactos.

## Pré-flight (antes de tocar a tela)

1. Ler o `SPEC.md` e o `RUNBOOK*.md` do módulo, mais o charter da tela.
2. Checar o **frescor**: tela viva não se refaz — se puxa.
3. Não inventar token, Model ou componente que já existe. **Estender, nunca recriar.**
4. Conferir a lição catalogada antes de repetir o erro dela.

## Variação é tweak, não arquivo

Explorar duas versões de uma tela = alternância dentro do **mesmo** componente. Arquivo novo por
variação vira cópia — e cópia apodrece
([ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)). No fluxo do
protótipo isso é cobrado pelo
[`cowork-ssot-guard`](../../scripts/governance/cowork-ssot-guard.mjs), que reprova export de
duplicata, memória local e process-doc misturados ao build.

## Por que o contrato existe

Sem ele, "a tela mudou de comportamento" é uma descoberta de suporte. Com ele, é um teste
vermelho — antes do merge, do lado de cá do cliente.
