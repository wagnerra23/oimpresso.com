---
date: "2026-09-01"
hour: "12:00 BRT"
topic: "Descida do design pela rota do pacote parou no PASSO 0; causa-raiz medida foi a regra escrita fora do read-order do Cowork"
authors: ["C"]
outcomes:
  - "PASSO 0 reprovou com 3 recibos independentes — bundle é o MESMO já aplicado em 25/08"
  - "Causa-raiz nova: regra de regenerar estava fora dos 6 documentos que o Cowork lê"
  - "PR #6501 move a regra pro passo 4 da ROTINA (item 1 do read-order) + errata no prompt + recibo no LC-08"
---

# Sessão — a descida não aconteceu, e descobrir *por quê* foi o trabalho

## O pedido

Executar a primeira descida completa do design pela rota do pacote (bundle), com um PASSO 0 explícito:
conferir o recibo `bundle regenerado` no `github.md` do Cowork e **parar** se o pacote estivesse velho.

## O que eu fiz, na ordem

1. **Não trabalhei no checkout de origem.** O guard do `SessionStart` avisou −86 commits; criei branch
   a partir de `origin/main` fresco e li todo canon de lá (`git show origin/main:<path>`).
2. **PASSO 0.** Baixei `github.md` (55.516 chars) → **0** hits de `regener`/`bundle regenerado`.
   Baixei `sync/bundle.manifest.json` → `generatedAt = 2026-08-24T22:49:15.818Z`.
   `list_files` confirmou manifesto único em `sync/`.
3. **Não parei no "não regeneraram".** O `bundleId 5023b274…` bateu com o commit `cf34d5de10`
   (#6260, 25/08, *"sincroniza o espelho pelo bundle 5023b274"*): é o **mesmo pacote já consumido**.
4. **Medi o custo de aplicar assim mesmo**, por sha256 e não por data: 229 idênticos, **16 divergem
   com o espelho à frente**, e **0 dos 12** arquivos-alvo estão no pacote. Aplicar seria regressão pura.
5. **[W] respondeu "não sei fazer isso"** — eu tinha terminado devolvendo um menu (*"cobrar o design
   ou autorizar a rota inferior"*), que é a LC-28. Voltei e fui atrás da causa.
6. **Medi o gerador em vez de citar lápide:** o cabeçalho do `gerar-payload-partes.mjs` declara
   *"NÃO roda do lado do agente consumidor"*. Não existe versão disso que eu faça sozinho.
7. **Achei o buraco:** o `CLAUDE.md` do projeto Cowork nomeia **6** documentos de read-order; contei
   `gerar-payload`/`bundle.manifest`/`sync/payload` nos seis → **0** (os 2 hits do `PROTOCOL.md` eram
   *"regenera o placar de tarefas"*, outro assunto). O pedido vivia num `CODE_NOTES.prompt-*` que não
   é nenhum dos seis — e o próprio prompt declarava a premissa que falhou.
8. **Consertei no dono certo:** regra vira passo 4 da `## 🔁 ROTINA` de `COWORK-ESTRUTURA-E-TELAS.md`
   (item 1 do read-order), com o comando e o recibo. Errata datada no prompt. Recibo no LC-08.
9. **Medi a rota alternativa antes de descartá-la:** baixei `scripts/cowork-paridade.mjs` — voltou
   **inline** (~9 KB, abaixo do piso). Sem rota fiel para os que faltam; transcrever é proibido.

## Onde eu errei nesta sessão

- **Devolvi menu de opções a [W]** no primeiro fechamento, quando a escolha de técnica era minha (LC-28).
  Só voltei a agir porque ele disse "não sei fazer isso".
- **Escrevi `--body-file /tmp/...`** e o `gh` não achou o arquivo: `/tmp` do Git Bash ≠ `%TEMP%` do
  binário nativo. É reincidência literal da §5 2026-08-21; corrigi com caminho absoluto do scratchpad.
- **Um `grep -E` com `\|`** foi barrado pelo `block-sonda-que-mente` — o guard estava certo, o padrão
  não casaria nada e eu leria "0 ocorrências" como resposta.

## O que a máquina pegou por mim

- `block-sonda-que-mente` (P4) barrou o `grep -E` com alternância escapada.
- `block-destructive` barrou um `rm -f` num comando composto.
- O `git merge` acusou o conflito de contador que uma resolução automática teria silenciado.

## Recibos que ficam

- Medições completas: handoff [`2026-09-01-1200-descida-design-bloqueada-regra-fora-do-read-order.md`](../handoffs/2026-09-01-1200-descida-design-bloqueada-regra-fora-do-read-order.md).
- Vermelho herdado do CI (RAGAS da Jana, não-required, não silenciado): comentário em
  [#6501](https://github.com/wagnerra23/oimpresso.com/pull/6501#issuecomment-5495570610).
