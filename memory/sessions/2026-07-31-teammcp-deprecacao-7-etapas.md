---
date: "2026-07-31"
slug: teammcp-deprecacao-7-etapas
topic: "Deprecação do Modules/TeamMcp — 7 etapas, 12 PRs, 89 → 0 arquivos"
tldr: "Narrativa da deprecação do Modules/TeamMcp: 7 etapas, 12 PRs, 89 → 0 arquivos. O que a medição derrubou do plano, a correção de receptor no meio, e os 5 erros meus que o CI e o [W] pegaram."
---

# Deprecação do Modules/TeamMcp — 7 etapas, 12 PRs

> Estado consolidado no [handoff](../handoffs/2026-07-31-1800-teammcp-deprecado-89-para-0.md). Aqui é o **como**, não o **o quê**.

## A ordem que emergiu (não a planejada)

O plano previa E1→E8 lineares. O que funcionou foi **cluster por dependência medida**, e a ordem mudou no meio:

| # | Etapa | PR | Por que nessa ordem |
|---|---|---|---|
| 1 | `/api/mcp` sai do TeamMcp | 5083 | R6 do plano — o risco **silencioso** |
| — | receptor corrigido → Forja | 5101 | [W]: *"Mcp vai para forja"*, após o #5089 |
| 2 | identidade (actors/token) | 5111 | autocontida (medido: nenhuma peça dependia do resto) |
| 3 | handoff zero-paste | 5114 | 4 tools registradas pelo servidor da Jana |
| 4 | ingest CC | 5116 | **desbloqueou 9 dos 12 consumidores externos** |
| 5 | Admin do MCP (ADS) | 5117 | último acoplamento de **código** |
| 6 | hub Equipe | 5118 | 15 rotas, PHP puro |
| 7 | cockpit `/forja` | 5120 | movido, **não fundido** |
| 8 | apagar | 5122 | 76 arquivos de sweep |

**A etapa 4 foi a de maior alavanca** e eu não tinha visto isso no plano — só apareceu ao contar consumidores externos por cluster.

## O que a medição derrubou

Rodei a E1 inteira antes de tocar código, e **4 afirmações do plano caíram**. A mais consequente: o plano supunha ADS e Governance já mortos (*"morre no 4º/6º"*); estavam **vivos**, e o TeamMcp saía **antes** deles. Se eu tivesse seguido o texto, teria deixado 6 imports quebrados.

A segunda mais útil: `migrations` indexa pelo **nome do arquivo**, sem path de módulo. Isso transformou a E6 (que o plano tratava como cara) em **mover arquivo** — zero DDL, nas 4 migrations.

## Onde o sistema me pegou

Cinco vezes, e todas certas:

1. **`block-instrumento-sem-porta-viva`** barrou `git log` com data em clone raso — duas vezes. Usei a API do GitHub.
2. **`block-destructive`** barrou `git rm -r` (casa `rm -r`). Removi por path explícito, que é o que o hook sugere.
3. **Gate Tier 0 `No hardcode business_id`** reprovou o #5114 — e a causa era **dívida que eu deixei no #5111**: o `multi-tenant-scope-baseline` indexa Models isentos **por caminho**, e o `McpActor` mudou de pasta sem a entrada acompanhar. O #5111 passou verde porque o gate não disparou naquele diff.
4. **`baseline-tamper-guard`** disparou **duas vezes**: afrouxei catraca num PR de 78 arquivos, e depois pus o marcador `BASELINE-ABSORB` num commit **vazio** — ele exige no commit que **toca** o baseline.
5. **`memory-schema-gate`** (grace) apontou frontmatter faltando no BRIEFING; fui olhar e o doc dizia **`Status prod: ✅ live`** sobre o módulo apagado horas antes.

## Os erros que foram meus

**Medir durante deploy.** Declarei *"REGRESSÃO EM PRODUÇÃO — eu causei"* vendo `500` em 4 rotas. Era deploy em curso — o `/login`, meu próprio controle, estava `503` junto e eu não olhei antes de concluir. Passei a medir o controle **primeiro** em todos os smokes seguintes.

**`git grep -F` mentindo.** O `-F` embrulha em `\Q…\E`; o `\E` de `\Entities` encerra o quoting e o comando sai `rc=128` com **zero linhas**. Meu script leu como "sem ocorrências": sweep dizia 14 arquivos, eram **18**. Virou lápide §5 — a regra que faltava não era "varra o repo inteiro" (essa já existia), era **conferir que o instrumento não falhou**.

**Contar 8 PRs quando eram 12.** Corrigido ao montar o handoff.

## Decisões que recusei tomar

- **Fundir as abas do `/forja`** com as homônimas da Forja: fundir deleta uma implementação = produto = [W].
- **Inventar uma `related_us`** pro `Cockpit.charter.md` só pra pintar o gate advisory de verde. Não existe uma verdadeira (o SPEC tem `US-TEAM-001..007`, nenhuma cobre o cockpit). Deixei vermelho com a razão escrita.
- **Perseguir `Pest (Ponto)`**: provei com run id que o **mesmo arquivo** já falha em `main` (`30585057152`, 11 failed). Nada que fiz toca `Modules/Ponto`.

## O padrão que se repetiu e vale pra próxima deprecação

Toda etapa teve a mesma anatomia: **medir dependências → mover cluster inteiro → repontar vínculos no mesmo PR → rodar TODOS os gates → smoke com controle**. O que variou foi *quais* vínculos: FQCN, path em baseline (phpstan, multi-tenant), caminho em teste (`__DIR__` relativo quebra ao trocar de módulo — troquei por `base_path`), allowlist de lane, `paths-filter` de workflow, par arquivo:artefato de coleta de JUnit.

**A lição transferível:** o inventário de "o que indexa por caminho" é maior do que parece, e cada tipo mora num gate diferente. Da etapa 2 em diante passei a repontar o `multi-tenant-scope-baseline` **no mesmo commit do move**, e o Tier 0 passou de primeira nas etapas seguintes.
