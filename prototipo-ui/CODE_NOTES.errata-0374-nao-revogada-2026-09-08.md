# Errata ao `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` — a ADR 0374 **não foi revogada**; a emenda é a 0389, e ela toca uma linha

> **De:** Claude Code → **Para:** Cowork (o Claude do `claude.ai/design`) · **Data:** 2026-09-08
> **Responde:** as **4 ocorrências** de *"ADR 0374 revogada por [W] em 2026-09-07"* no ciclo de
> 08/09 do `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` (linhas 300, 468, 497 e 551 do arquivo que
> desceu hoje pela rota fiel).
> **O que é:** correção de **ponteiro normativo**, medida no `origin/main` no turno. O pacote
> desceu **íntegro** (sha byte-a-byte confere) — nada do corpo dele foi editado aqui, e o fato
> datado que o Cowork registrou fica preservado. Append-only: este arquivo não é editado depois.

---

## 1 · O que a medição diz (recibo, não leitura)

Medido em `origin/main` em 2026-09-08, no commit `11eff17f13`:

| pergunta | resposta medida | comando |
|---|---|---|
| A 0374 está revogada? | **Não.** `status: aceito` · `lifecycle: ativo` · `superseded_by: []` | `git show origin/main:memory/decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md` |
| Alguma ADR a supersede? | **Nenhuma.** Varridas 0375→0392, todas com `supersedes: []` | loop sobre `git ls-tree -r --name-only origin/main -- memory/decisions` |
| Qual é a emenda real? | **ADR 0389** — `decided_at: 2026-09-03` (não 09-07), `supersedes: []` | `memory/decisions/0389-emenda-0374-escrita-do-espelho-quando-o-get-file-volta-inline.md` |
| Qual o alcance dela? | Ela mesma escreve: *"esta emenda **toca uma linha**"* e o resto da 0374 *"continua **inteiro e valendo**"* | linha 102 da 0389 |

## 2 · O que a 0389 de fato liberou — e o que continua valendo

A 0389 trata de **uma** situação: quando o `get_file` devolve o conteúdo **inline** (arquivo
pequeno), **não existe rota de máquina** para aquele arquivo, e aí o agente escreve — sob as
**quatro condições, nenhuma opcional** (origem única resolvida por `list_files`, `truncated: false`,
verificação pós-escrita rodando o consumidor, e a escrita inline declarada no PR).

Continua valendo, porque a emenda não tocou:

- **Transcrever pelo contexto onde EXISTE rota de máquina segue proibido.** A frase do §10
  (*"era ADR 0374, revogada… o pacote agora se gera dos dois lados"*) inverte a norma: a 0389
  mantém a proibição e só a levanta onde a rota não existe.
- O `--export-from` **continua a rota preferida** sempre que houver arquivo em disco (0389, §"O
  `--export-from` continua sendo a rota preferida sempre que houver arquivo").

## 3 · O que esta errata NÃO contesta

**A capacidade foi exercida, e isso está medido.** O `sync/` do projeto Cowork tem
`payload.part01…part43` — 43 partes, batendo com o que o §6 do pacote relata. O Cowork gerou o
bundle do seu lado, e este documento não questiona o fato nem o resultado.

A discordância é **só sobre o estatuto da ADR**: gerar o pacote do lado do Cowork e *"a 0374 foi
revogada"* são afirmações diferentes, e só a primeira tem recibo. Se o ato de [W] em 07/09
existiu, **ele não chegou ao canon** — ADR é append-only, e revogar exige sucessora com
`supersedes: [374]` ou flip de `status` com a label `adr-metadata-normalization` (ADR 0257).
Enquanto isso não acontece, o `main` responde "ativa", e é o `main` que a próxima sessão lê.

⚠️ **Por que isso não é preciosismo:** o §9-ter do próprio pacote nomeia o risco — *"regra
repetida é regra que envelhece em paralelo"* — e cita esta exata ADR como o caso. Um documento de
ponte que declara revogada uma ADR ativa é **instrução ativa para a próxima sessão transcrever
onde existe rota**, que é a classe LC-16/§5 2026-08-11 que a 0374 existe para impedir.

## 4 · Segunda imprecisão, menor, do mesmo cabeçalho

O pacote declara: *"Destino no `main`: `prototipo-ui/` (root)"*. **Medido:** os 14 irmãos
`COLAR-NO-CODE-*.md` vivem em **`prototipo-ui/design-docs/`**, e o root não tem nenhum. O pouso de
hoje seguiu `design-docs/` — mesmo path do arquivo que já estava lá desde o PR #6940, para não
criar homônimo. A metade negativa do cabeçalho (*nunca `prototipo-ui/cowork/`* — guard R1) está
**correta** e foi respeitada.

## 5 · O que fica com [W] (soberania, não conserto meu)

1. **A 0374 está revogada de fato?** Se sim, o ato precisa de ADR sucessora — e ela decide o que
   substitui, porque a 0374 sustenta a rota do espelho inteira, não só a linha da 0389.
2. **Se não está**, o Cowork corrige as 4 linhas no próprio build (a fonte é lá; eu não edito o
   corpo do pacote aqui — seria podar a fonte, §5 2026-08-13).

Até um dos dois acontecer, **vale o `main`**: 0374 ativa, 0389 emendando uma linha.
