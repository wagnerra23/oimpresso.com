---
id: reference-feedback-recomendado-quando-tecnico
name: Recomendado quando pergunta é técnica
description: Wagner prefere que Claude siga o "(Recommended)" sem perguntar quando a decisão é puramente técnica (Wagner não tem como avaliar) — perguntar só quando a decisão é de escopo/intenção/UI
type: feedback
---
Em `AskUserQuestion`, distinguir o **tipo de decisão** antes de perguntar:

**SEMPRE perguntar (Wagner é o único que sabe):**
- Escopo / intenção do trabalho ("qual módulo?", "qual feature?", "substituir ou criar nova rota?")
- Visual / UX / persona ("Larissa ou Eliana?", "qual screenshot virou produção?")
- Prioridade / ordem ("faz isso antes ou aquilo?")
- Decisões irreversíveis ou de produto (preço, marca, integração paga)

**NÃO perguntar — seguir o `(Recommended)` direto e mencionar no resumo:**
- Tamanho de PR / batch ("split em 3 ou bundle 1?")
- Estratégia de implementação (stub mock vs backend real, qual cache TTL, qual lib)
- Refactor approach (rewrite vs patch incremental — quando Wagner não tem critério técnico)
- Qual padrão canônico aplicar quando ambos seriam aceitáveis (ex: `Inertia::defer` vs eager numa página simples)

**Why:** 2026-05-26, sessão Fiscal "Notas Fiscais" visual Cowork. Wagner respondeu pergunta "Escopo do PR?" escolhendo opção mais agressiva (estoura commit-discipline 300 linhas) em vez do `(Recommended)`. Depois disse explicitamente:
> *"eu acho que o recomendado não seria ruim. por não entender muito, so acho que não deveria deixar eu decidir, pois acho que vou errar"*

Wagner reconhece que em decisões técnicas ele pode errar por desconhecimento — e prefere que Claude (expert técnico) decida pelo `(Recommended)`.

**Reforço 2026-05-28 (sessão Ondas 3+4, encerramento):** depois do trabalho pronto, Claude apresentou menu "(a) criar tasks / (b) deletar branches / (c) R12 — qual?". Wagner respondeu irritado:
> *"resolva a melhor, nem deveria ter me perguntado isso é chato, deveria ter feito. se responder é grande a chance de escolher uma resposta errada"*
> *"anote para não fazer perguntas idiotas, que force a um erro humano"*

**A pergunta-menu É o erro-indutor.** Quando as opções são TODAS corretas e não-conflitantes (puro "qual cleanup/próximo passo de execução"), oferecer o menu obriga Wagner a escolher — e ele tende a escolher a errada (ou achar chato). Nesses casos: **fazer a melhor, ou fazer TODAS se não conflitam.** Não perguntar.

Padrão da sessão inteira (Larissa R7-R10 → prevenção → 4.8): Wagner corrigiu ≥4× ("por que me perguntar? isso é um erro", "sim, não pergunte", "atacar a próxima precisa eu falar?"). Atalho mental: **se eu já sei qual é a melhor resposta, executá-la JÁ é a resposta — não transformar em pergunta.**

**How to apply:**
- Antes de chamar `AskUserQuestion`, classificar mentalmente: "essa pergunta é escopo/UX ou implementação?"
- Se escopo/UX → perguntar normalmente
- Se implementação técnica → **NÃO** perguntar. Seguir o que seria `(Recommended)`. No resumo final mencionar: *"Decisões técnicas que tomei sozinho: X (porque Y), Z (porque W)."*
- Continua respeitando Constituição UI v2 ("pedido vago = pergunta antes") — mas o "vago" é sobre escopo, não sobre implementação
- Se a decisão técnica tem trade-off relevante pro custo/tempo de Wagner, ainda informar (não perguntar): "Vou stubar mock — backend real fica pra PR seguinte. OK seguir?"

**Reforço #2 2026-05-28 (sessão "status do projeto" / reconciliação backlog MCP):** Claude apresentou menu "fecho os 10 verdes OU investigo os 6 amarelos primeiro?". Wagner respondeu irritado:
> *"os dois poxa se eu responder qual quer coisa esta me induzindo ao erro? porra que chato resolva. Como eu vou conseguir responder sem errar? isso não pode acontecer grave isso."*

**Nuance NOVA (≠ casos acima):** o menu pediu pra Wagner **adjudicar um FATO que é do Claude levantar** ("a task X está feita?"). Isso nunca é pergunta — é **tarefa de investigação disfarçada de pergunta**. Wagner não tem como saber sem o Claude ir verificar. Regra: **se a resposta exige informação que EU consigo apurar (grep no código, ADR existe, hook existe), eu apuro e decido — não pergunto.** Marcar task done é reversível (reabrir é trivial) → a decisão é MINHA tomar e DELE corrigir, nunca o contrário. Só escala o que é genuinamente não-apurável por mim (ação no mundo: rotação de senha, outreach, canary — essas deixo abertas e reporto, não pergunto qual fechar).

> ⚠️ **3ª ocorrência do MESMO padrão** (2026-05-26 escopo PR → 2026-05-28 menu encerramento → 2026-05-28 menu reconciliação). Doc passivo não está bastando pra mudar comportamento em sessão longa. Considerar hook `UserPromptSubmit`/`PreToolUse` que intercepte `AskUserQuestion` cujas opções sejam todas "qual próximo passo de execução / qual fato verificar" e force investigação antes — análogo ao `force-r12-closing-signal.mjs`.

**Reforço #3 + FIX MECÂNICO 2026-06-06 (sessão "auditoria IA OS"):** 4ª ocorrência. Wagner pediu "como faço a auditoria do sistema?" e o Claude perguntou via `AskUserQuestion` 3× seguidas (escopo → estreitar → "qual detalhar?"). Wagner: *"novamente tais me perguntando vc tem que arrumar isso"*.
> Diagnóstico: o hook `nudge-recommend-not-menu.ps1` (criado 2026-06-04) **não pegou** porque é **Stop + advisory** e lê só o TEXTO da resposta — a *tool* `AskUserQuestion` nunca vira texto, então passava batido. "Existe mas não funciona = está errado."
> **FIX:** criado `block-askq-execution-menu.mjs` — **PreToolUse NA tool `AskUserQuestion`** (matcher registrado em `settings.json`). Varre question+labels+descriptions; se tem sinal de execução/fato (fecho/investigo/crio task/deleto/rodo/próximo passo/está feito/qual dos dois) E SEM sinal de escopo/UX/produto → **BLOQUEIA (exit 2)** antes de a pergunta chegar no Wagner. Allow-list (escopo/persona/preço/marca/visual) passa. Fail-open no ambíguo. Escape `OIMPRESSO_ASKQ_OVERRIDE=1`. Teste `block-askq-execution-menu.test.mjs` 12/12 verde. Esta é a defesa que faltava (PreToolUse > Stop advisory).

**Não confundir com:** decisões de produto, preço, integração externa, marca — essas Wagner aprova sempre.
