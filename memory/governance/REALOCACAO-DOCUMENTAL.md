# Realocação documental — runbook

> Porta operacional única para classificar, mover e religar documentos. O fluxo
> implementa as três camadas da [ADR 0334](../decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md)
> sem fazer migração mecânica em massa.

## Regra de segurança

Trabalhe com **um arquivo ou um lote pequeno e coeso por vez**. A ordem é obrigatória:

```text
classificar → revisar → adversário → dry-run → git mv + relink → validar → commit
```

O classificador propõe. O adversário pode impedir. Só o executor movimenta. O executor
exige worktree limpa, usa `git mv`, limita os paths alteráveis ao plano e restaura o
estado anterior se qualquer verificação falhar.

## As três camadas

| Camada | Conteúdo | Porta-mãe |
|---|---|---|
| Produto ERP | módulos usados pelo cliente | `memory/requisitos/<Modulo>/BRIEFING.md` |
| Produto IA | Jana e memória do negócio do cliente | `memory/requisitos/Jana/BRIEFING.md` |
| IA-OS | processo de construção, gates, hooks e governança | [ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) |

## Procedimento

### 1. Gere e leia o plano

```powershell
npm run docs:relocation:classify -- --source memory/ARQUIVO.md > plano-realocacao.json
```

Confira no JSON: `target`, `classification.layer`, `classification.door`, `confidence`
e todos os `rewrites`. Para indicar um destino já existente na estrutura:

```powershell
npm run docs:relocation:classify -- --source memory/ARQUIVO.md --target memory/governance/arquivo.md > plano-realocacao.json
```

Não aprove plano com receita possivelmente antiga sem revisão humana. Não mova ADR,
session, handoff, porta canônica nem arquivo gerado: o adversário deve rejeitá-los.

### 2. Rode a contraprova e o ensaio

```powershell
npm run docs:relocation:adversary -- --plan plano-realocacao.json
npm run docs:relocation:execute -- --plan plano-realocacao.json
```

O primeiro comando precisa retornar `APPROVE`; o segundo, `DRY_RUN_OK`. `REVIEW` pede
julgamento e registro de revisores no plano. `REJECT` não pode ser sobreposto pelo agente.

### 3. Aplique e inspecione

```powershell
npm run docs:relocation:execute -- --plan plano-realocacao.json --apply
git diff --cached --stat
git diff --cached --check
```

Nesse ponto a mudança está apenas staged. Confira se o arquivo antigo sumiu, o destino
existe, os links vivos apontam para a autoridade correta e documentos históricos estão
marcados como históricos. Para uma operação que não exige curadoria posterior, `--commit`
pode ser combinado com `--apply` e grava o recibo automaticamente.

### 4. Valide e consulte o rastro

```powershell
npm run docs:relocation:execute:selftest
npm run docs:relocation:adversary:selftest
node scripts/governance/onboarding-paths-check.mjs
node scripts/governance/system-map.mjs --check
npm run docs:relocation:history
git log --follow -- memory/governance/arquivo.md
```

`docs:relocation:history` é a lista durável dos deslocamentos. Ela deriva dos trailers
`Document-Plan-SHA256`, `Document-Base-SHA` e `Document-Move` gravados no Git; não existe
um segundo ledger manual para ficar divergente.

## Quando parar

Pare sem mover se ocorrer qualquer um destes casos:

- confiança abaixo de 0,90 ou receita possivelmente stale;
- backlink ou link de saída sem rewrite exato;
- destino colide, não existe ou discorda da camada/porta;
- seria necessário editar histórico append-only;
- o plano foi gerado em outro SHA;
- a worktree contém mudanças não relacionadas.

O rollback automático cobre falhas durante a execução. Se a operação já foi commitada,
a correção é um novo commit explícito; não apague nem reescreva o histórico publicado.
