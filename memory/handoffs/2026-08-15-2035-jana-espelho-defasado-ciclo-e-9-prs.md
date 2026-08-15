---
date: "2026-08-15"
time: "20:35 BRT"
slug: jana-espelho-defasado-ciclo-e-9-prs
tldr: "[W] pediu pra ver a âncora do /ia e aprovar. Aprovou (jana-merge.jsx) — e o resto da sessão saiu de perseguir por que o meu render não batia com o Cowork dele: o chat-jana.jsx do espelho estava 7 SEMANAS atrás. 9 PRs mergeados. O maior aprendizado não é técnico: EU travei duas vezes com medo de estragar o espelho, e [W] furou as duas. A primeira trava era real (remendar à mão evapora no export — 3h14min medido); a segunda era medição minha viciada. Baixar é fiel, e provei com hash + controle."
prs: [5802, 5803, 5805, 5806, 5807, 5809, 5814, 5816]
decided_by: [W]
next_steps:
  - "[W]: BRL scan vai reclamar em TODO download futuro do protótipo — os mocks têm valores. O #5816 mergeou com ele vermelho (advisory). Decisão de propagar (allowlist com motivo 'fixture de protótipo') ou tratar caso a caso segue aberta; não deixar implícita é justamente o que o #5814 registrou."
  - "97 dos 121 arquivos do espelho seguem SEM VEREDITO (--sla rc=1). O método está provado ponta a ponta, mas o volume não cabe: 174 arquivos = 4,72 MB ≈ 1,4M tokens só de leitura. As 10 ÂNCORAS (598 KB) cabem numa sessão dedicada — são as que governam tela via charter."
  - "`.jc-updated-b` não existe em CSS nenhum (nem vivo, nem espelho): o botão Atualizar que desceu no #5816 chega SEM ESTILO. É gap do Cowork vivo, não do espelho — conserto nasce lá."
---

# A âncora da Jana, o espelho defasado, e o ciclo que ninguém rodava

## Estado MCP no momento do fechamento

⚠️ **O servidor MCP esteve INALCANÇÁVEL a sessão inteira** — nenhuma tool `mcp__oimpresso__*` disponível. Operei sem `brief-fetch` (o hook de SessionStart trouxe um brief em fallback). Registro em vez de omitir.

Fallback filesystem que o [`how-trabalhar.md`](../how-trabalhar.md) §Fallback autoriza:

- `sessions-recent` → `ls -t memory/sessions/`: as 2 irmãs de 08-13 (`ancora-jana-consertada`, `espelho-cowork-medir-vs-consertar`) + `2026-08-12-refutacao-lote-pr5675`
- `decisions-search since:2026-08-14` → `git log -- memory/decisions/`: **nenhuma ADR nova**
- `cycles-active` / `my-work` → **não consultados** (sem MCP, sem fallback equivalente — não invento estado)
- `whats-active` → **não rodado**, e isso é falha minha registrada: abri PR sobre vermelho de CI compartilhado sem checar sessão paralela (a emenda §5 2026-08-13 manda). Duas sessões irmãs apareceram sozinhas no caminho (`#5817`/`#5819` consertaram o `maquinas-inventario` enquanto eu diagnosticava).

## O que aconteceu

[W] pediu duas coisas simples: abrir a tela pra ver a âncora, e aprovar. A âncora é `prototipo-ui/cowork/jana-merge.jsx`, resolvida por `ancora.mjs` a partir do charter v6 — **aprovada**.

O resto saiu de uma observação dele olhando o próprio Cowork: *"os fundos vermelho do a receber? o botão no topo plano pro"*. Duas coisas que o meu render não tinha.

**Causa raiz, achada por data de git:** o `chat-jana.jsx` do espelho está em **2026-06-23**, enquanto os irmãos desceram em 08-13. O `jana-merge.jsx` novo passa `plano`/`exportar`/`onConfig`/`onRefresh` para um `JanaHeader` velho que não os recebia — as props caíam no vazio. O PR #5761 chamava-se *"3 arquivos do espelho DEFASADOS"* e este não estava entre os 3.

## Os 9 PRs

| PR | O que entrou |
|---|---|
| [#5802](https://github.com/wagnerra23/oimpresso.com/pull/5802) | 3 notas do contrato do Painel afirmando estado que apodreceu |
| [#5803](https://github.com/wagnerra23/oimpresso.com/pull/5803) | catraca de lint apertada em 504 (a11y −48; **0 entradas afrouxadas**) |
| [#5805](https://github.com/wagnerra23/oimpresso.com/pull/5805) | lápide do remendo à mão, reescrita pelo adversário |
| [#5806](https://github.com/wagnerra23/oimpresso.com/pull/5806) | `--preview-ds` era cego pras 7 fontes que o CSS pede por dentro |
| [#5807](https://github.com/wagnerra23/oimpresso.com/pull/5807) | `--reconcile` do ledger ligado no CI (detectava e ninguém rodava) |
| [#5809](https://github.com/wagnerra23/oimpresso.com/pull/5809) | SLA do espelho vai pro resumo do PR (media certo, ninguém lia) |
| [#5814](https://github.com/wagnerra23/oimpresso.com/pull/5814) | a rotina de frescor **não tem executor** — declarado, não esquecido |
| [#5816](https://github.com/wagnerra23/oimpresso.com/pull/5816) | `chat-jana.jsx` desce do vivo — 7 semanas fechadas |

## O aprendizado que não é técnico

**Travei duas vezes com medo de estragar o espelho. [W] furou as duas, e ele estava certo nas duas — por razões diferentes.**

**Trava 1 — real.** Remendei o espelho à mão (Edit de trechos) pra escapar do teto do `get_file`. [W] cortou: *"porque copiar e não baixar o arquivo original? espelho deveria ser igual nunca modificado?"*. Medido: o conserto à mão do #5738 durou **3h14min** até o `--export-from` do #5761 desfazer. Virou a lápide do #5805.

**Trava 2 — minha medição estava viciada.** Afirmei que baixar o CSS regrediria contraste AA. Medi **expressão CSS num `<div>` solto**, não o elemento renderizado — e ainda li valor **cacheado** duas vezes. Corrigido: baixar dá 4.32 contra 4.26 do espelho, em **qualquer** ambiente. Era **melhor**, não pior. A camada de conversão que eu ia construir morreu na medição.

**A distinção que destravou tudo é do [W]:** *"baixar deve baixar, a conversão é mais um passo depois de baixar tudo"*. Eu tratava as duas como a mesma coisa.

## Sete erros de método, seis da mesma família

Medir com instrumento parecido em vez do instrumento certo:

1. expressão CSS em vez do elemento renderizado (**3×**)
2. CSS cacheado lido como atual (**2×**)
3. `rc` capturado através de pipe (era do `tail`; o real era 1)
4. `ENOENT` aceito como prova de fail-closed
5. `grep` ancorado inventando "5 órfãs" que não existiam
6. `ls` em vez da porta viva para "casos ausentes"
7. **reimplementei o `contentHash`** num `node -e` e obtive `c757775b…` onde o manifest publica `b5131a47…` — a função estava exportada e testada

O 7º é o mais grave e só apareceu porque comparei com o manifest. Se eu tivesse comparado *baixado vs meu-espelho*, os dois usariam minha função errada, bateriam entre si, e o veredito falso seria consistente.

## O que provou que o download é fiel

**Teste de identidade.** Escrevi o conteúdo do vivo e diffei contra o espelho:

- `chat-jana.css` → 34 linhas, **todas esperadas**, zero lixo
- `chat-jana.jsx` → **68 linhas**, todas semanticamente coerentes

E o `.jsx` derrubou minha própria medição: eu tinha dito *"13 linhas, só o JanaHeader"*, olhando o bloco que **eu escolhi** olhar. O delta real incluía `BriefDiario` (ganhou `onChip`/`onAudio`), `AcaoRow` (ganhou `onCta`) e o `send()` inteiro (resposta por assunto, 5 ramos regex em vez de 1 fixa). **Remendar teria deixado 55 linhas de fora, em silêncio.**

**Hash com controle:** `63311ef001c1ff48` → `0a35431df3fe7f71`, batendo com o que o `--export-from` reportou; `classifyMirror` → `SYNC`.

## Quem era responsável, já que ninguém rodava

Pergunta do [W]: *"máquina que não é chamada é bug"*. Medido:

- o `--compare` — único modo que responde *"o espelho está atrasado?"* — **não roda em CI**: exige snapshot do `get_file`, cuja auth é interativa (ADR 0315). Os 2 hits de `DesignSync` em `.github/` são **comentários explicando que não dá**
- existe um SLA declarado no código: `SLA_DAYS = 14`
- **o vigia funcionou**: `--sla` gritava `rc=1` desde 08-13. O que falhou foi o alarme ser ilegível dentro de um step `continue-on-error`

Conserto: **#5809** (alarme visível) + **#5814** (ausência de executor declarada em `AUTOMATIONS.md`). O desenho não estava errado — a ausência estava **implícita**, e foi isso que deixou 7 semanas passarem.

## Bateria final em main fresco

```
espelho + design ..... 5 verdes · --sla rc=1  (o alarme dos 97, legítimo)
ciclo + memória ...... 7 verdes  (sec5-derive, two-strikes, memory-health, contrato)
lint/a11y/âncora ..... 4 verdes  (eslint 2100 Δ0 · a11y 246/246)
```
