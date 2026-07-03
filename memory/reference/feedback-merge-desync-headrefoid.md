# Merge de PR pode ENGOLIR commits por desync do GitHub (headRefOid stale)

> **Origem:** 2026-07-03, sessão da régua-por-tela OficinaAuto. Wagner: *"interessante isso, como seria o guard? vai crescer sim"* (time MCP crescendo → o guard tem que escalar sem depender de ninguém lembrar).

## O que aconteceu

Escrevi e pushei o handoff da régua (`2026-07-03-1501-...md`, commit `a1782b7d7c`) no branch do PR #3763. O squash-merge do #3763 usou um **`headRefOid` STALE** — só o 1º commit (os scorecards) — então o 2º commit (o handoff) **nunca landou em `main`**. Os scorecards/ROADMAP sobreviveram; o handoff sumiu, sem erro nenhum. **Mesmo padrão do #3732** (onda Cliente: *"#3732 squash-mergeado incompleto por desync GitHub — branch avançou, PR travou headRefOid, CI não re-rodou"*).

Gatilho concreto: **merge (UI ou CLI) disparado logo depois de um push** — a aba do navegador / o objeto-PR do GitHub ainda tinha o head velho.

## Por que o gate de CI pós-merge NÃO pega isso

Quando a causa é o **próprio GitHub estar com estado velho**, um detector pós-merge que pergunta ao GitHub "quais arquivos esse PR tinha?" recebe a **mesma resposta velha** (o arquivo some E some a evidência de que existiu; a branch é deletada no merge). **Detecção depois do merge é cega pra esse caso.** Um "gate de integridade" verde em cima do dado já corrompido seria a *suíte que mente* que a [ADR 0271](../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) baniu.

O único ponto confiável é **ANTES do merge**: comparar o que empurrei (`git rev-parse HEAD`) com o head que o GitHub acha que é do PR (`headRefOid`), e **pinar o SHA no merge**.

## O guard (2 camadas)

**Camada 1 — prevenção atômica (a garantia).** Mergear via API pinando o SHA:
```bash
gh api -X PUT "repos/$OWNER/$REPO/pulls/$PR/merge" -f sha="$(git rev-parse HEAD)" -f merge_method=squash
```
A API **retorna 409 ("Head branch was modified")** se o head tiver mexido entre o check e o merge — garantia do servidor, atômica, race-proof. Empacotado em [`scripts/gh/safe-merge.sh`](../../scripts/gh/safe-merge.sh) (pré-check `headRefOid==HEAD` + merge pinado + rede pós-merge que confere os arquivos add/mod em `origin/main`).

**Camada 2 — política humana.** Quem merge pela **UI do GitHub** dá **F5 na página antes** de clicar merge (a aba pode ter head velho), ou deixa o merge pro Claude (que usa a Camada 1).

## Disciplina Claude (escala com o time)

Como ~todo merge aqui é agent-driven, o guard vive na disciplina de merge do Claude (skill [`commit-discipline`](../../.claude/skills/commit-discipline/SKILL.md) §"Merge seguro"): **todo `gh pr merge` vira `scripts/gh/safe-merge.sh <PR>`**. Assim toda sessão — Wagner, Felipe, Maiara, Luiz, Eliana — herda o guard sem lembrar.

## Sinal de que aconteceu (pra pós-mortem)

- `gh pr merge` diz **"was already merged"** com um `mergeCommit` cujo head **≠** o teu último push.
- Um arquivo que você commitou e pushou **não está em `origin/main`** depois do merge, mas os outros do mesmo PR estão.
- Recuperação: re-landar o conteúdo perdido num PR novo (foi o #3767, verificado com `git ls-tree origin/main` desta vez).
