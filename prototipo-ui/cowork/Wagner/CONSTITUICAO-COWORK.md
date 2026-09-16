# CONSTITUIÇÃO COWORK — as leis que TODO pacote respeita (citadas, nunca copiadas)

> **Espelho, não fonte.** O SSOT é o git: `CLAUDE.md` · `memory/proibicoes.md` · `memory/INDEX.md` · os ADRs. Este arquivo é a **citação local** delas, para o pacote de módulo referenciar em uma linha em vez de recopiar o texto. **Se divergir do repo, manda o repo.**
>
> **Por que existe:** em 2026-09-08 medi 8 pacotes de módulo reemitindo o mesmo `## 0 · Leis que não se renegociam`. Texto copiado envelhece em paralelo — a ADR 0374 foi revogada em 07/09 e seguiu citada como vigente em 3 pontos do protocolo até 08/09. Regra repetida é regra que se contradiz sozinha.
>
> **Como citar** (1ª linha do `§0` de todo pacote):
> `constituição: CONSTITUICAO-COWORK.md@<sha> (C1–C13) + memory/proibicoes.md@<sha>`
> e no `§0` fica **só a lei daquele módulo**.

| # | lei | fonte no repo |
|---|---|---|
| **C1** | **Zero cor crua.** Roxo canon `oklch(0.55 0.15 295)` light / `oklch(0.70 0.15 295)` dark, sempre por token. **Autoridade:** `TabBar`/DS → protótipo medido → produção; onde os três discordam, ganha o primeiro. | ADR 0190 · 0235 |
| **C2** | PT-BR em toda UI cliente-facing · sentence case · sem emoji no app · sem `rounded-xl+` fora do canon · **sidebar preta nos dois modos**. | UI-0023 |
| **C3** | **Um `<main>` por documento** (AP9) + chain de overflow: nó `flex-1` em coluna precisa de `h-full` ou pai `flex flex-col min-h-0` (AP10). | PRE-MERGE-UI |
| **C4** | **Ancoragem dupla.** Alvo de layout = protótipo medido; âncora de implementação = arquivo real do `main`, reusando os átomos que já existem lá. O `main` responde *onde* e *com que dado*; o protótipo responde *como*. **Onde o `main` está à frente, corrige-se o build do Cowork — nunca se pede regressão.** | [W] 2026-09-03 · §4-bis |
| **C5** | **Onda = sessão limpa.** Quem executa lê o read-order no `main`, não a conversa. O pedido passa no **teste do estranho**. | §2-quater |
| **C6** | **Onda ≤ 1 PR ≤ 300 linhas de diff · 1 assunto · ≤ 8 arquivos.** Migration nunca junto com UI. Não cabe ⇒ **divide** (nunca agrupa). | §2-bis · §13.3 |
| **C7** | **Número sem fonte não renderiza:** `—` + linha no PR. Nunca `rand()`, nunca literal disfarçado de dado, nunca cálculo derivado inventado na UI. | §4 · L-42 |
| **C8** | **Nada derivado do build vira arquivo** (mapa, manifesto, inventário, retrato): é **comando**, gerado na hora. | ADR 0256 · L-42 |
| **C9** | **⛔ [W] trava o PR.** Abrir sem a decisão é inventar desenho — ou lei. | §11 |
| **C10** | **Placar obrigatório no corpo do PR.** Sem ele, omitir é grátis. | §7-bis |
| **C11** | **Réplica primeiro:** o protótipo é o contrato de **layout**; divergir é bug, salvo divergência declarada. | ADR 0388 · [W] 2026-09-02 |
| **C12** | **Medir e aplicar são passos separados.** O que a bateria a11y reprovar **no alvo** corrige-se no build do Cowork; não vira pedido. **Nada é "0 bug" antes do T7** (`design-diff --compare --check` nos dois renders, prod deployada). | §5-bis · §7-bis |
| **C13** | **Ausência se prova, não se conclui — e o acerto se registra.** "Zero resultado" não é evidência até rodar **controle positivo**; o espelho (`_ds/`) não é evidência sobre o `main`. Todo ciclo que lê o `main` deixa bloco em `COLAR-NO-CODE-ACERTOS-E-LICOES.md`: **acerto com sha** (o que NÃO se refaz) + **erro com a regra colada**. | §15 · [W] 2026-09-09 |

**O que esta constituição NÃO é:** não é lista de proibições do projeto (essa é `memory/proibicoes.md`, maior e com casos), não é o protocolo (`COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` — o *como gerar*), e **não é canon novo**: cada linha aqui já existia em ADR, memória ou seção do protocolo. Nenhuma lei nasce neste arquivo — **C13 nasceu no §15 da norma** (decisão [W] de 2026-09-09), e aqui é só a citação.
