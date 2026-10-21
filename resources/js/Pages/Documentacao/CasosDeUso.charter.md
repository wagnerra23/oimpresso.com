---
page: /documentacao/casos-de-uso
component: resources/js/Pages/Documentacao/CasosDeUso.tsx
related_runbook: memory/requisitos/Documentacao/RUNBOOK-casos-de-uso.md
owner: wagner
status: draft
last_validated: "2026-10-20"
parent_module: Documentacao
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
related_us: [US-DOC-001]
tier: B
charter_version: 1
---

# Page Charter — Documentacao/CasosDeUso

> Os casos de uso do catálogo de fluxos (UC-FLX-NN) dentro da documentação do sistema. Tabela
> filtrável por processo e estado, **derivada em runtime** de `CasosDoPrototipo::CASOS` — a mesma
> fonte da aba "Casos de uso" de `/fluxos`. Casos: [CasosDeUso.casos.md](CasosDeUso.casos.md).

## Goal

[W] quer ver os casos de uso na documentação **sem texto escrito à mão que envelheça**. Id, título,
regra, estado e US vêm do servidor; a tela só dá layout e filtro. Cada US leva ao SPEC onde mora e
cada caso lista os `.casos.md` que o citam — links derivados da árvore pelo servidor
(`RastroDosCasos`), nunca uma lista de arquivos escrita à mão (`UC-CASOS-04`, `UC-CASOS-05`).

## Non-Goals

- ❌ **Não copia caso para markdown nem para o `.tsx`** — qualquer id/título/estado literal no TSX é defeito (`UC-CASOS-03`)
- ❌ **Não edita nem cria caso** — read-only; mudar o catálogo é mudar o `fluxos-data.jsx` e exportar de novo
- ❌ **Não mantém mapa caso → arquivo à mão** — o vínculo sai da árvore do repo a cada acesso (cache curto)
- ❌ **Não expõe tenant nem PII** — conteúdo global de governança (ADR 0093)

## Automation Anti-hooks

- ❌ Nenhum agente preenche o estado de um caso na tela — ele sai de `CasosDoPrototipo::ESTADOS` (`UC-CASOS-02`)

## UX Targets

- Cabe em 1280px; a tabela larga rola sozinha, a coluna de leitura nunca rola de lado
