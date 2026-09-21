---
date: "2026-09-21"
time: "1900 UTC"
slug: "importacao-conta-felipe-como-fazer-sem-rodar-em-circulo"
tldr: "Espelho do Felipe importado (#7620). A rota ZIP passa a aceitar a conta dele no #7656. A próxima sessão importa o .zip novo com UM comando, assim que #7656 e #7663 fecharem — e este handoff diz exatamente como, e o que NÃO fazer."
decided_by: [F]
cycle: null
prs: [7620, 7643, 7651, 7656, 7663]
us: []
next_steps:
  - "Esperar #7656 e #7663 fecharem e o [F] mandar o .zip novo (exportado depois da limpeza dos 30 repetidos no Cowork)"
  - "Importar com receber-handoff.mjs --conta felipe, primeiro SEM --apply, depois com --apply"
  - "Conferir a pasta contra o zip (inventário por hash, ignorando CR) e abrir o PR"
related_adrs: ["0397-prototipo-minimo-por-dono-e-ds-direto", "0404-ultimo-importado-e-autoridade-do-espelho", "0405-espelhos-cowork-independentes-por-conta"]
---

# Handoff 2026-09-21 19:00 UTC — importar o protótipo da conta do Felipe sem rodar em círculo

## TL;DR

A pasta `prototipo-ui/cowork/Felipe/` já tem o pacote "PROTÓTIPO OFICIAL - PRODUTO UNIFICADO V2" (#7620, integrado).

**A próxima sessão tem uma tarefa só:** importar o **próximo** `.zip` do Felipe pela ferramenta, com um comando. Para isso precisam fechar o **#7656**, que cadastra a conta do Felipe na importação, e o **#7663** (errata). Esta sessão gastou horas em idas e vindas que as regras abaixo evitam — **leia a seção "O que NÃO fazer" antes de tocar em qualquer arquivo**.

## O modelo combinado (Felipe + Wagner + Claude Design)

Registrado por escrito em [`CODE_NOTES.alinhamento-pastas-por-conta-2026-09-21.md`](../reference/prototipo-ui/CODE_NOTES.alinhamento-pastas-por-conta-2026-09-21.md) (#7643 + errata #7663).

| Pasta | Dono | Conta Cowork |
|---|---|---|
| `prototipo-ui/cowork/Wagner/` | [W] | projeto de telas do Wagner |
| `prototipo-ui/cowork/Felipe/` | [F] (usada por [F], [M], [L]) | `2e7d3640-825c-4c09-ac52-17c8469f3b91` — "PROTÓTIPO OFICIAL - PRODUTO UNIFICADO V2" |
| `prototipo-ui/design-system/` | projeto DS | **um só** para os dois |

- **O design system do Felipe NÃO é outro.** `49a36f76-2672-43f6-b955-c6cbb52f7f86` ("WAGNER Office Impresso — Design System") é a **cópia na conta do Felipe**, que ele sincroniza a partir do `main` do git. O `019dd02f-…` é o do Wagner. **Mesmo conteúdo, dois endereços — não há divergência nem decisão pendente.** [F] 2026-09-21, textual: *"O Design system que informei é o mesmo que do Wagner. Puxo as atualizações direto do main do git, então eles estão sincronizados. O ID é diferente porque importei o DS na minha conta"*. Registrado em `CONTAS.felipe.dsCopia` (`scripts/design/protocolo.config.mjs`, #7656) e visível no `--procedencia`.
- **"Duplicação", na fala do [F], é ANTIGO × NOVO:** arquivo velho na pasta dele contra a versão atualizada do pacote. Fica **só o que veio no pacote**, com as ligações do pacote.
- **A regra do Wagner vale ([ADR 0405](../decisions/0405-espelhos-cowork-independentes-por-conta.md), R4):** dentro da pasta de uma conta, o mesmo conteúdo não pode existir em dois caminhos. O [F] testou a pasta idêntica ao pacote, com repetições, e **mandou desfazer**.

## Como importar o próximo `.zip` (passo a passo)

Pré-requisitos: **#7656 integrado** e o [F] mandou o `.zip` exportado **depois** de o Claude Design:
- apagar os 30 repetidos (pedido em `C:\Users\Daniel\Desktop\Cowork-Felipe-remover-repetidos.md`);
- deixar o pacote com **um** design system só (o Cowork diz que já fez).

1. **Trabalhe numa cópia separada do repositório**, criada de `origin/main`, e nunca em `D:\oimpresso.com`. Outras sessões trocam a branch dessa pasta:
   ```bash
   git fetch origin main && git worktree add -b design/felipe-<slug> .claude/worktrees/<nome> origin/main
   ```
2. **Medir, sem gravar nada:**
   ```bash
   node scripts/design-sync/receber-handoff.mjs --zip "<caminho do zip>" --conta felipe
   ```
   - `--conta felipe` é obrigatório: o zip nomeia a raiz pelo nome do projeto, nunca pelo ID, e o passo 0 sai "indeterminado".
   - Confira a linha `[2b] DONO  Felipe -> prototipo-ui/cowork/Felipe/`.
3. **Promover:** repetir o mesmo comando com `--apply`.
4. **Conferir a pasta contra o zip:** um inventário por hash **ignorando CR**, com zero faltando e zero sobrando fora do pacote. E rodar `node scripts/governance/cowork-ssot-guard.mjs` numa cópia limpa.
5. **PR** com o recibo, e o `.gitleaksignore` já cobre o mock de credencial OAuth (ver abaixo).

## Recusas esperadas e o que fazer com cada uma

| Recusa | Por quê | O que fazer |
|---|---|---|
| `dsRequires: N design systems no pacote — ambiguo` | o pacote trouxe mais de uma pasta em `_ds/` | pedir ao Claude Design para deixar um DS só; **não** contornar filtrando cópia |
| `conteúdo duplicado no bundle: A == B` | repetido **dentro** do pacote (R4) | pedir ao Claude Design para apagar a cópia e religar; **não** gravar sem a ferramenta |
| R4 no guard: `erp-shell-v2/styles.css` = `design-system/public/cowork-preview/erp-shell-v2/styles.css` (e o `tweaks-panel.jsx`) | no Cowork eles ficam (não são repetidos lá), mas no repo são idênticos ao DS | tirar do lote e religar as páginas ao DS, como em #7620. Só some quando a regra virar "um lugar só" — decisão [W] |
| gitleaks em `officeimpresso-page.jsx` 761-765 | mock de credencial OAuth do protótipo | falso positivo verificado em 2026-08-20 (0 de 5 contra produção). Registrar o fingerprint do novo commit no `.gitleaksignore`, citando o bloco existente |

## O que NÃO fazer (cada item custou retrabalho nesta sessão)

- **Não trabalhar na pasta principal `D:\oimpresso.com`.** Outra sessão trocou a branch dela no meio do trabalho, e um `git rebase` acabou rebaseando a branch **dela**. Foi desfeito com `git reset --keep` antes de publicar, mas é a [LC-12](../LICOES_CODE.md) (estado global do repo).
- **Não importar só o shell**, sem `--full-tree`. A primeira importação trouxe 245 de 550 arquivos, e o [F] tinha pedido o pacote inteiro.
- **Não decidir sozinho o que é "duplicação".** Perguntar se é antigo × novo ou repetição dentro do pacote. Aqui foi antigo × novo.
- **Não comparar bytes crus.** Os arquivos do Cowork em `erp-shell-v2/` vêm com quebra de linha do Windows (CRLF), e o git grava em LF. Compare **sempre ignorando CR**. Isso fez o Claude Design contar "conteúdo diferente" onde era idêntico.
- **Não confundir contagem em caracteres com contagem em bytes** ao conversar com o Claude Design. Ele conta caracteres, o repo conta bytes UTF-8, e os acentos fazem a diferença.
- **Não repetir afirmação do Claude Design sem conferir no pacote.** A pasta `_ds/…019dd02f` foi chamada de "improvisada", e o próprio `mapa-do-terreno.md` do pacote mostrava que ela existia desde 09/09 (errata #7663).
- **Não tentar ler o projeto pelo `DesignSync` para trazer arquivos.** Ele corta em 256 KiB por arquivo (21 de 550 passariam do limite) e esta sessão não tem autorização (`/design-login` só roda num `claude` interativo). O Claude Code em terminal já vem com o app: `C:\Users\Daniel\AppData\Roaming\Claude\claude-code\<versão>\claude.exe`. O caminho de importação é o `.zip`.

## PRs

| PR | Status | Conteúdo |
|---|---|---|
| #7620 | integrado | espelho do Felipe = pacote inteiro, sem repetição, páginas religadas |
| #7643 | integrado | o combinado Code ↔ Cowork |
| #7651 | integrado (outra sessão) | design system do handoff de 18/09 |
| #7656 | **aberto** | rota ZIP aceita a conta do Felipe e grava no espelho dele + `dsCopia` |
| #7663 | **aberto** | errata: a pasta `019dd02f` não foi improvisada; `49a36f76` = cópia do Felipe |

## Bloqueios / pendências

- [ ] Aprovar #7656 e #7663 — owner: [W]/[F]
- [ ] Claude Design apagar os 30 repetidos e exportar o `.zip` de novo — owner: [F]
- [ ] Trocar a R4 de "bytes iguais" por "um lugar só", comparando sem CR (emenda à ADR 0405) — owner: [W]
- [ ] Dono do shell (proposta do Cowork: `prototipo-ui/design-system/`; hoje fica em `cowork/Wagner/`, ADR 0397 D4) — owner: [W] + [F]
- [ ] Proposta do Claude Design sobre `app.jsx`/`data.jsx` (rota e menu da V2) para o [W] — owner: [F]
- [ ] Ferramenta: a conversão do endereço do DS erra a profundidade em páginas dentro de subpastas (grava `../../design-system/`) — corrigido à mão nas páginas do #7620, a ferramenta segue com o erro

## Estado MCP no momento do fechamento

O servidor MCP `oimpresso` **não conectou** nesta sessão: `AUTH_HEADER_REJECTED`, HTTP 401, *"Header Authorization ausente ou inválido"*. Por isso `cycles-active`, `my-work`, `sessions-recent` e `decisions-search` **não foram consultados**. Não invento o estado vivo; a próxima sessão deve rodá-los ao abrir.

O que foi medido por outros meios, no fechamento: estado dos PRs pelo `gh` (tabela acima).
