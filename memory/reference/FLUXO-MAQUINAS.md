---
id: reference-fluxo-maquinas
name: Fluxo — revisão das máquinas do sistema
description: Como o Code descobre os fluxos, resolve suas máquinas, prova wiring e mordida e declara o que não conseguiu medir.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-09-22"
nav_group: fluxo
nav_order: 45
---

# Fluxo — revisão das máquinas do sistema

**Entrada:** todos os `memory/reference/FLUXO-*.md` e os paths executáveis citados neles.
O invocador humano é o Code; o workflow de governança executa a checagem estrutural.

```text
descobrir fluxos → validar contrato documental → resolver máquinas
  → localizar invocador → localizar teste ligado → executar provas existentes
  → consultar enforcement vivo → corrigir → repetir
```

A máquina é `scripts/governance/revisar-fluxos.mjs`. Ela grava JSON somente quando o chamador
redireciona `--json`; por padrão, o recibo sai no terminal. A decisão usa três estados:

- `PASSOU`: a prova executou e saiu zero;
- `FALHOU`: a prova executou e encontrou defeito;
- `NÃO MEDIDO`: faltou oráculo, ambiente ou autenticação.

O falso-verde principal é tratar script existente como script invocado, ou teste existente como
teste ligado ao CI. Por isso a revisão separa existência, wiring, mordida e enforcement vivo.

## Como provar

`node scripts/governance/revisar-fluxos.mjs --execute` pesquisa e executa as provas locais. Para
fechamento, o Code acrescenta `--live --strict`: o `--live` consulta
o GitHub em vez de ler baseline local, e o `--strict` reprova qualquer lacuna documental ou
máquina sem prova localizada. O bite-test está em
`scripts/governance/revisar-fluxos.test.mjs` e é invocado pelo workflow de governança.

## Limite

A análise estática localiza invocadores prováveis; só o oráculo vivo e um recibo de execução
provam que rodaram no ambiente real. Falha de acesso nunca vira sucesso.
