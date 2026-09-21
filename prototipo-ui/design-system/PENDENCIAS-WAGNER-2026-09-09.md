# Pendências para decisão do [W]

**Data:** 09/09/2026 · **Origem:** sync do design system (espelho) com o `oimpresso.com`

Contexto em uma linha: os 9 componentes que mudaram no repo desde o último sync foram lidos e 5 já foram espelhados. O que está abaixo é o que **não** dá para resolver lendo código — precisa de decisão sua.

Uma ressalva de método, para você calibrar a confiança: o `github_compare` truncou nas três tentativas, então a medição foi por hash de arquivo entre o commit do sync de 01/09 e o `main`. A janela é mais larga que "desde sexta" — sem um identificador de commit de 04/09 não consegui estreitar. Parte das 9 mudanças pode ser de 01→04/09.

---

## 1. Status badge: migrar os 6 domínios antigos de FILL para SOFT?

**O que é.** O repo migrou as badges de status de fundo sólido para fundo tintado + borda — a regra AP7: "status é ESTADO, e estado usa fundo tintado + borda, não preenchimento". Os 4 domínios novos (`producao`, `arquivo_prazo`, `ajuste_estoque`, `transferencia_estoque`) já entraram no espelho nesse formato.

**O que falta.** Seis domínios antigos seguem em fundo sólido no espelho: `documento`, `fiscal`, `os`, `intercorrencia`, `prioridade`, `payment`.

**Por que não fiz.** Alinhar restiliza os seis de uma vez, em telas que já estão em produção.

**O que ajuda a decidir.** No repo o mesmo remédio foi aplicado em duas ondas (#6268 reclassificou 9 badges; #6325 registrou a distinção — "`danger` é o par SOFT, `destructive` é o fill"), e a medição do dia da correção encontrou **49 entradas em fundo sólido**, todas originadas de um tipo copiado à mão que não permitia escrever a resposta certa.

**Já resolvido no caminho, sem precisar de você:** a tinta do fundo sólido era `#fff`. Medido: branco sobre `--color-warning` (L 0.70) dá ~2,2:1 e sobre `--color-success` (L 0.62) ~3,2:1 — os dois reprovam o mínimo de 4,5:1. Agora consome o par `-foreground`, que é exatamente a tinta que os tokens v1.3.0 levaram a quase-preto. No repo esse par foi de 1,25:1 para 7,72:1 (UI-0033).

---

## 2. PLATAFORMA tem 4 itens no design e 1 em produção. Qual é a intenção?

**O que é.** Decisão sua de 08/09: a Forja saiu dos atalhos de topo e virou item do grupo PLATAFORMA — novo, último, neutro (sem matiz), único fechado por default. Isso está espelhado.

**A divergência.** O design (`GROUP_META` do Cowork, `data.jsx`) declara PLATAFORMA com **Tarefas · Equipe · Governança · Forja**. O código do repo whitelista só `['Forja']`, e a Governança declara `group => 'sistema'` no DataController dela — ou seja, renderiza em SISTEMA, não em PLATAFORMA.

**O que fiz.** Espelhei o comportamento de produção (só Forja), por ser o que de fato aparece na tela.

**A pergunta.** Os outros três itens do design são intenção não implementada, ou o design está desatualizado?

---

## 3. Cor de acento: quem abriu o sistema antes de 08/06 continua vendo azul

**O que é.** A cor de acento vem de um seletor de matiz salvo no navegador de cada pessoa (`oimpresso.cockpit.tweaks.accentHue`), nunca no servidor. Até 08/06/2026 o padrão era azul (220); depois passou a ser roxo (295). Não houve migração. Quem abriu o sistema antes daquela data tem o azul gravado e continua vendo azul.

**Por que não é resolvível por código.** O valor `220` gravado por quem foi *defaultado* pro azul é byte-idêntico ao `220` gravado por quem *escolheu* azul no seletor. Não se registrou procedência junto do valor. Nenhuma consulta separa as duas populações.

**Opções, com o custo de cada uma:**

1. **Não fazer nada.** Divergência permanente; some só por rotatividade de navegador.
2. **Limpar todo `220`.** Resolve a marca, atropela em silêncio quem escolheu azul.
3. **Perguntar uma vez** ("o padrão mudou para roxo — manter azul ou atualizar?"). É a única opção que resolve a ambiguidade em vez de adivinhar. Custa um aviso na tela.
4. **Gravar procedência de agora em diante** (distinguir "default aplicado" de "usuário escolheu"). Não resolve o passivo, mas faz a próxima troca de padrão ser migrável. Vale independente das três acima.

**Escopo.** É código de produção do `oimpresso.com`; o espelho é não-fonte (ADR 0315/0299). Verifiquei que o defeito não existe no espelho: o único default de acento aqui já é 295, e é propriedade de tweak, não persistência de navegador. Levantado pelo Code.

---

## 4. Errata: a ADR 0374 está VIVA — e isso é reincidência

**O que é.** O pacote do ciclo de 08/09 afirma em **5 pontos** que a ADR 0374 foi revogada. Ela não foi. A emenda é a **ADR 0389** (`decided_at 2026-09-03`), e ela toca uma única linha: escrita inline apenas onde não há rota de máquina, sob 4 condições.

**Onde corrigir.** `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` e `CONSTITUICAO-COWORK.md:5`.

**Por que está aqui.** É reincidência, não deslize isolado — vale decidir se o remédio é corrigir o texto ou fechar a porta que deixa o pacote afirmar revogação sem conferir a ADR.

---

## 5. Bloqueio operacional: o bundle do ciclo não foi regenerado

O bundle vigente é de **07/09 21:19, 281 arquivos**. O pedido registrado é regenerar ao fim de cada ciclo, e o ciclo de 08/09 não rodou.

**Consequência medida:** sem ele a Onda 7 não fecha veredito — faltam **273 arquivos com `unchecked = 0`**.

Este é o único item da lista que é execução, não decisão. Mas trava o veredito.

---

## 6. Atos de governança aguardando sua assinatura

- **Ratificação da ADR 0390** — hoje em "proposto", precisa ir a "aceito".
- **Duas decisões de Sells:** no Index, de qual gap é o dono; no Create, qual é a âncora no charter.

Nenhum dos três tem efeito no espelho — são atos do lado do repo.

---

## Resumo em uma linha cada

| # | Pendência | Tipo |
| --- | --- | --- |
| 1 | Fill → soft nos 6 domínios antigos de status badge | design |
| 2 | PLATAFORMA: 4 itens no design vs 1 em produção | intenção |
| 3 | Azul 220 travado no navegador dos usuários antigos | produto |
| 4 | Errata da ADR 0374 (reincidência em 5 pontos) | registro |
| 5 | Bundle do ciclo de 08/09 não regenerado | execução |
| 6 | ADR 0390 + 2 decisões de Sells | governança |
