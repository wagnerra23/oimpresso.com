---
sessao: "03"
titulo: COWORK-ESTRUTURA-E-TELAS.md — 3 regras mortas saem da ROTINA
executor: "[CC]"
base: 701f40c6ec66
---
# _saida 03

## As 3 afirmações — TODAS confirmadas mortas contra o `main`

O `PARAR SE` manda corrigir só o que bater e listar o resto. Bateram as três:

| afirmação do doc | medido no `main` | onde |
|---|---|---|
| "AO FECHAR O CICLO, REGENERE O PACOTE" + "é você, e não uma máquina do repo" | o CI gera, em `on: push: branches:[main], paths: prototipo-ui/cowork/Wagner/**` — e o workflow **não commita nada** | `.github/workflows/cowork-bundle.yml` |
| "`.md` não viaja no pacote" / guard dá erro com `.md` no `cowork/` | **R3** — `.md` vive dentro de um dono, "deixou de ser proibido (decisão [W] 2026-09-13)" | `cowork-ssot-guard.mjs:13` e `:91` |
| loop "Você exporta → cowork/", sem dizer como | zip do projeto importado pelo Code, com `/PURGE` | `receber-handoff.mjs --zip … --conta w [--apply]` |

A terceira eu não verifiquei só por leitura: **rodei a rota hoje**, no import do handoff 31 (#7736).

## O que mudou (4 pontos, só no prefixo)

1. **ROTINA §4** — deixou de mandar rodar `gerar-payload-partes` à mão. Agora: avisar [W], o Code importa com `receber-handoff`, o pacote em partes sai do CI, e o `/PURGE` está dito (o zip é o **estado da conta**, não um incremento — quem não sabe disso lê uma remoção como bug).
2. **"A máquina que protege isso"** — a lista de erros virou as 4 regras vigentes (R1 raiz · R2 donos · R3 `.md` dentro de um dono · R4 duplicata por dono).
3. **Blockquote da ROTINA** — repetia a mesma lista morta. Passou a **apontar** para a seção, em vez de restatear o que o guard sabe melhor (LC-10).
4. **Roteamento** — `prototipo-ui/` root → `memory/reference/prototipo-ui/` (ADR 0397 D3).
5. **Rodapé** — 1 linha datada; o histórico anterior ficou intacto.

**A frase morta saiu sem virar mentira:** o rodapé precisa dizer o que mudou, mas citá-la verbatim a reintroduziria no doc (e a §Prova exige que ela suma). Parafraseei — a história fica, a string sai. Medido: `grep -c` = **0**.

## Provas
- contém `cowork-bundle.yml` (2×) · contém `receber-handoff` (3×) · **não** contém a frase morta (0)
- 0 bytes de controle · `nao_toca` respeitado: zero `scripts/**`, zero `.github/**`

## ⚠️ Duas coisas que este PR NÃO fecha, e que são da FONTE

**1. Este recibo está fora do `nao_toca`, e eu sei disso.** A thread declara `nao_toca: prototipo-ui/**`, e a §Prova exige `_saida-03.md` — que só pode morar ali. A contradição é da spec, não da execução, e aparece igual nas threads 01 e 02. Li do único jeito que a torna coerente: **não tocar `_saida` alheio nem `00-INDICE.md`** (são o dado que o medidor consome), e escrever **apenas o próprio recibo**, que a Prova manda. Nenhum outro arquivo do espelho foi tocado.

**2. O `00-INDICE.md` no `main` não declara esta thread.** O zip do handoff 31 trouxe um índice com **2** threads (01, 02) e **sem** `03-rotina-cowork-desatualizada.md`. O índice **vivo** no projeto Cowork declara **3** e diz *"3 threads, nesta ordem: 02 → 01 → 03"*. Ou seja: **o pacote saiu atrás do projeto**.

Consequência honesta: enquanto a fonte não reexportar, o `placar-indice` **não vê** esta thread e este recibo fica órfão. Não corrigi pelo espelho — editá-lo à mão é proibido (ADR 0374) e `.md` não desce por `--export-from`. O conserto é **reexportar o pacote**, e é do lado do Cowork.

O pedido desta thread eu li direto do projeto por `DesignSync.get_file` (leitura é livre) — não transcrevi nada para o espelho.
