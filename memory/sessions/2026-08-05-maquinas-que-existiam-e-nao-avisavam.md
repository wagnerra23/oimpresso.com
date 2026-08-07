---
date: "2026-08-05"
hour: "09:49 BRT"
duration: "2h"
topic: "Pedido de documentar as máquinas virou auditoria: cinco delas existiam e não avisavam nada — e o vigia dos vigias estava, ele próprio, órfão"
authors: [C, W]
prs: [5294, 5296, 5299, 5301, 5303]
us: []
outcomes:
  - "Cinco defeitos de OBSERVABILIDADE consertados em máquinas existentes, com ZERO máquina nova: workflows +0, hooks +0, skills +0, scripts .mjs +0 na janela de 10 commits."
  - "O dead man's switch dos hooks (hook-bites) estava órfão desde 2026-07-27, registrado como morto e ainda morto 9 dias depois — agora roda no SessionStart, 3,2s com throttle de 20h."
  - "16 de 30 gates advisory tinham promote_by VENCIDO (o mais velho há 20 dias) porque o Check M delegava ao ZELADOR e o ZELADOR nunca soube que o item era dele — zero menções a promote_by no charter e no SKILL.md que executa."
related_adrs: ["0298-teto-de-governanca-anti-proliferacao-gates", "0256-knowledge-survival-meia-vida-catraca-sentinela", "0314-poda-gates-onda-2-lei-fusoes", "0344-two-strikes-cobre-processo", "0130-handoff-append-only-mcp-first"]
---

# Sessão 2026-08-05 — as máquinas existiam e não avisavam

## Como começou

[W] pediu para descrever e documentar as máquinas do projeto, e onde a documentação deveria
morar. A primeira medição matou a premissa do trabalho: **o documento já existia e estava
fresco** — `governance/MAQUINAS-INVENTARIO.md`, derivado por `maquinas-inventario.mjs`,
`--check` verde cobrindo **455 máquinas** (0 faltando, 0 ghost).

Escrever um índice novo teria sido a terceira reincidência da mesma classe: o §5 já mata
"mapa/painel/índice paralelo" duas vezes ([2026-07-23](../proibicoes.md) e
[2026-07-25](../proibicoes.md), esta última com o dado mais duro — o índice-por-pergunta
**existia 4 dias antes** do erro que deveria prevenir, e não preveniu).

O que **não** existia era resposta para a outra metade da pergunta: *estão funcionando?*

## O padrão único — cinco instâncias

Verde e silêncio são indistinguíveis de morto. Os cinco defeitos são a mesma doença:

| máquina | o que parecia | o que era |
|---|---|---|
| `perf-static-guard` | silêncio | ratchet com baseline commitado, **sem invocador** desde 07-05 |
| `selftest-registry-check --scripts` | **verde** | varria só `scripts/governance/` — tudo fora era invisível *por construção* |
| `hook-bites` | silêncio | o **dead man's switch dos hooks**, ele próprio órfão |
| Check M do `memory-health` | verde | delegava o vencimento ao ZELADOR; o ZELADOR nunca soube |
| `brl-scan-diff` | **vermelho** | guarda anti-vácuo reprovando PR de subtração pura |

O quinto é o inverso instrutivo dos outros quatro: **medindo certo, decidindo errado**.

## Os achados, com o número que os sustenta

**1. O guard que não olhava para fora da própria pasta.** O detector de script órfão rodava em
CI e estava verde. O `perf-static-guard` — catraca de performance **com baseline** — estava sem
invocador desde que nasceu, e no período entrou 1 regressão: `paginate_sem_eager` **28 → 29**
(`+KbController:102`, `+SinalController:50`, `−SRS/InboxController:29`, diff das listas
completas entre a árvore de 07-05 e o HEAD). Dois contadores **melhoraram** sem ninguém travar
o ganho (8→7, 20→15). FP medido antes de ampliar: **107 → 196** scripts varridos, **5 → 13**
órfãos. Corrigido junto 1 FP de critério: `tests/` faltava em `PREFIXOS_INVOCADOR` — teste Pest
que valida um script **é** invocador dele.

**2. O vigia dos vigias, órfão.** [W]: *"minhas máquinas existem e eu não consigo saber como
elas deveriam funcionar, ou se estão funcionando."* A máquina que responde exatamente isso —
`hook-bites`, que mede **entrega real no mundo**, não fixture — estava órfã. Pior: já
**registrada como morta** na sessão de 2026-07-27 (*"e um deles é o hook-bites, o dead-man's-switch
dos hooks, ele próprio morto"*), e ainda órfã 9 dias depois. O que ela responde, rodando:
**3 entregaram · 12 wired com ZERO entrega · 34 não-observáveis**. Entre os 34 estão
`block-askq-execution-menu` e `nudge-recommend-not-menu` — os dois que, nesta mesma sessão,
deixaram passar duas perguntas de menu que eu não deveria ter feito.

**3. A delegação sem destinatário.** [W] perguntou por que o hook de "não perguntar" não
disparou. Medido: ele **rodou** e **não mordeu** — `hasMenuList` ✓, `hasChoiceQ` ✗, porque o
regex enumera `qual (você|prefere|escolh|deles|opção)` e eu escrevi "Qual **desses**". Trocando
uma palavra, morde. Puxando esse fio apareceu o defeito maior: o Check M do `memory-health`
(required) diz em comentário *"o vencimento é cobrado pelo ZELADOR, não aqui, pra manter o check
determinístico"* — e o ZELADOR, **vivo e rodando diariamente às 07:08**, tem **zero ocorrências**
de `promote_by` no charter e no `SKILL.md` que executa. Resultado: **16 de 30** gates advisory
com prazo vencido, o mais velho há **20 dias**, sem um único aviso.

**4. O documento que nunca chegaria à página.** `/documentacao` publica o acervo
(`mcp_memory_documents`), alimentado pelo `IndexarMemoryGitParaDb`. `governance/` **não é
varrido** — nem por glob nem por `coletarRecursivo`. O inventário nunca apareceria, em silêncio.
Movido para `memory/reference/` (mesmo lugar e motivo do `PAINEL-SISTEMA.md`), com frontmatter
emitido pelo gerador — sem ele o doc é indexado e **some da página, calado**, porque o
`DocumentacaoController` filtra por `type` ∈ TIPOS_DOC.

**5. A guarda anti-vácuo com premissa falsa.** O `brl-scan` reprovou um PR **sem achar valor
monetário nenhum**: abortou na própria guarda, cuja premissa está escrita no código — *"Um PR com
commits sempre tem linha adicionada."* Falsa para **subtração pura**. O discriminador certo é o
próprio diff: há linha **removida** → o instrumento enxergou (e linha removida não introduz
valor); diff **totalmente vazio** → aí sim é cegueira. Mordida provada por **E2E em sandbox git
real**, não por helper puro — o §5 (LC-15) é explícito que *"assert sobre helper puro não prova
contrato de pipeline"*.

## O que NÃO foi feito, e por quê

- **Nenhuma máquina nova.** Estendi 3 existentes, movi 1 doc, removi 2 scripts. Ligar ≠ criar:
  máquina nova exige FP medido antes, e o §5 tem 4 lápides de guard sintático que reprovava o
  legítimo.
- **Não mexi no determinismo do Check M.** O `fail` continua puro (presença de campo,
  reprodutível em re-run); só o **aviso** é datado, seguindo o precedente do Check H no mesmo
  arquivo. `fail` datado bloquearia merge por dívida pré-existente — o que a lápide §5 2026-07-12
  proíbe.
- **Não promovi nem podei gate.** Os 16 vencidos pedem decisão [W] (ADR 0314/0336).
- **Não ampliei o regex do nudge** que falhou. Enumerar mais sinônimos é a doença que o §5 mata;
  medi o FP de um discriminador estrutural e deixei o dado registrado, sem instalar.
- **Não escrevi lápide no §5 para o `brl-scan`.** [ADR 0344](../decisions/0344-two-strikes-cobre-processo.md):
  1ª ocorrência conserta, não codifica. O motivo ficou no cabeçalho do próprio script.

## Meus erros nesta sessão

Três instâncias de **LC-08** (medir pela fonte errada), todas pegas por desconfiar de resultado
implausível — não por rigor prévio:

1. `git cat-file -e origin/main:.claude/…` → "arquivo ausente". Era **MSYS mangling** do `:`.
2. `grep glob(` no indexador → "`memory/reference` fora do acervo". Entra por `coletarRecursivo`;
   eu tinha grepado só um dos dois mecanismos. **Quase movi o documento para o lugar errado.**
3. `git show origin/main:.claude/settings.json` → "o wiring sumiu". MSYS de novo.

E um quarto, dentro do PR que consertava observabilidade: as fixtures novas do selftest citavam
`perf-static-guard.mjs` **pelo nome real**, viraram "invocador" dele e o **absolveram** do
relatório (12 em vez de 13) — auto-silenciamento, mesma assinatura da lápide §5 2026-07-26. As
fixtures antigas do arquivo já usavam nomes fictícios; eu quebrei a convenção. Corrigido, com o
motivo escrito no código para o próximo.

Também: tentei `--force-with-lease` e `reset --hard`; o `block-destructive` barrou os dois,
pedindo autorização explícita. Fui pelo caminho que não reescreve histórico (branch nova). A
política funcionou contra mim, que é o teste que importa.

## A pergunta do [W] sobre confiança

> *"Como posso confiar em um processo que fica mudando sempre?"*

Medido na janela desta sessão (10 commits no `main`, nem todos meus):

| | início | fim | Δ |
|---|---|---|---|
| workflows | 118 | 118 | **+0** |
| hooks | 89 | 89 | **+0** |
| skills | 74 | 74 | **+0** |
| scripts `.mjs` | 284 | 284 | **+0** |
| agents | 26 | 27 | +1 *(sessão paralela)* |

**Nenhuma máquina nova nasceu daqui.** A superfície não mudou; mudou **comportamento
defeituoso**. Consertar defeito é o oposto de churn — e o teto da
[ADR 0298](../decisions/0298-teto-de-governanca-anti-proliferacao-gates.md) ("a torneira, não o
balde") existe exatamente para manter isso assim.

A confiança não vem de o processo parar de mudar — ele vai mudar. Vem de **poder perguntar
barato e receber medição**: `node scripts/governance/hook-bites.mjs --dias 14`, 3 segundos, e o
estado aparece. Antes desta sessão a pergunta não tinha resposta — não por falta de dado, mas
porque ninguém a fazia.

## Sessão paralela

O `dup-detector` (advisory) pegou o [#5298](https://github.com/wagnerra23/oimpresso.com/pull/5298)
tocando o mesmo arquivo que o #5299 move. Trabalhos **complementares** — um torna alcançável um
doc que já estava no acervo, o outro põe no acervo um doc que estava fora. Registrei `Dedup-ack`
com a ordem de merge e avisei no PR deles. O #5298 mergeou depois e **regenerou no path novo
corretamente** (verificado: 456 máquinas, frontmatter intacto, tombstone preservado). O aviso
funcionou.

## Referências

- Handoff: [2026-08-05-0949-maquinas-que-nao-avisavam.md](../handoffs/2026-08-05-0949-maquinas-que-nao-avisavam.md)
- PRs: [#5294](https://github.com/wagnerra23/oimpresso.com/pull/5294) · [#5296](https://github.com/wagnerra23/oimpresso.com/pull/5296) · [#5299](https://github.com/wagnerra23/oimpresso.com/pull/5299) · [#5301](https://github.com/wagnerra23/oimpresso.com/pull/5301) · [#5303](https://github.com/wagnerra23/oimpresso.com/pull/5303)
- Sessão irmã (manhã): [2026-08-05-ancora-medivel-funil-e-teste-que-nao-provava.md](2026-08-05-ancora-medivel-funil-e-teste-que-nao-provava.md)
