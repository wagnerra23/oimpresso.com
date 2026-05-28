---
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

**Não confundir com:** decisões de produto, preço, integração externa, marca — essas Wagner aprova sempre.
