---
proposal_id: funcao-scorecard-opiniao-ancorada-rubrica
status: accepted
promoted_to: 0345-topicos-vivos-aprendizado-por-critica-revisada
created: 2026-07-21
proposed_by: claude-code
decided_by: wagner
parent_adr: 0256 (knowledge survival) + 0230 (metodo governance scorecard)
related_adrs: [0256, 0230, 0155, 0093, 0264, 0320]
type: mecanismo-de-processo
---

# FUNCAO-scorecard — parecer "concordo/não" por função, ancorado em rubrica

- **Status:** aceito por [W] em 2026-07-21 e promovido à [ADR 0345](../0345-topicos-vivos-aprendizado-por-critica-revisada.md). **PILOTO** — só vale após o bite-test corrigido passar.
- **Data:** 2026-07-21 · **Autor:** [CC].
- **Origem:** pedido [W] 2026-07-21 — *"derive do código e escreva se concorda ou não com a função... lado positivo e negativo do que fazer e não fazer."* Pesquisa que ancora: [session 2026-07-21 arte-catálogo (PR #4611)](https://github.com/wagnerra23/oimpresso.com/pull/4611). É a **Fase C** do plano cuja Fase B é a `proposals/2026-07-21-taxonomia-arquivos-modulo.md`.

## Contexto — por que NÃO é opinião livre

O pedido natural ("a IA opina se concorda com cada função") tem uma armadilha provada: **uma IA que lê uma função e diz "concordo" carimba** — sicofância é propriedade estrutural do RLHF (Anthropic 2024; viés de leniência; auto-preferência). Livre, o parecer sempre tende a concordar. É o §5 2026-06-05 ("teste tautológico derivado do código") aplicado à opinião.

O modelo defensável (CodeScene Code Health, fitness functions, Backstage Soundcheck/Cortex): **a opinião é COMPILADA contra uma rubrica externa declarada ANTES**, com veredito por-critério grounded numa **citação extrativa** do código — nunca um "joinha" holístico. O oimpresso já faz isso certo por TELA (`screen-grade`, 16 dimensões + ratchet). Esta proposta ESTENDE esse padrão pro nível de FUNÇÃO — não inventa opinião livre.

## Decisão proposta

1. **Rubrica-primeiro** — [`FUNCAO-SCORECARD-METODO.md`](../../requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md) declara 8 critérios `{id, critério, âncora, como-medir binário, quando-n/a}` (C1 multi-tenant … C8 cobertura contada), TODOS derivados do canon (ADR 0093, proibicoes, §5, ADR 0264) — **nunca retro-derivados** da função julgada (senão a rubrica vaza o veredito).
2. **PLUGAR, NÃO FUNDIR** — sem nota agregada; 8 vereditos lado a lado + `totals`. (Fundir recria a superfície de sicofância.)
3. **Veredito é PARECER, não achado** — um `discordo` NÃO autoriza fix nem cria task; é candidato a US via [W]. Correção segue §5 2026-07-15 (varredura contada + âncora + teste vermelho) + REGRA MESTRE se valor/estoque.
4. **Evidência obrigatória** — todo veredito exige citação extrativa (trecho + linhas); sem citação = inválido.
5. **Juiz = skill** (`.claude/skills/funcao-scorecard`), não agente novo (é read-only + 1 YAML). Score-as-code: 1 YAML por arquivo em `memory/governance/scorecards/funcoes/`.
6. **Bite-test antes de confiar** (§5 do método) — o juiz precisa discriminar mutações sintéticas em fixture imutável, preservar um controle limpo e usar `incerto` quando falta intenção. Código de produção não é chamado de “defeito plantado”. **Se falhar, o piloto falha e a gente diz.** Nenhum YAML shippa antes.

## Prova de proveniência (anti-tautologia, mecânica)

O `METODO.md` (rubrica) é commitado **ANTES** de existir qualquer YAML de veredito — a rubrica não pode ter sido escrita pra caber numa função específica se nasce sem nenhuma função julgada. (Este PR = rubrica + skill + esta proposta, zero scorecard.)

## Consequências

- **Positivo:** o "concordo/não por função" do [W] vira defensável (aponta a regra + a linha, não o gosto). Generaliza um padrão já provado (screen-grade).
- **Risco (mitigado):** carimbo/sicofância → rubrica externa + citação + bite-test discriminante; opinião→fix → §0.3/§6 do método; contaminação via proibicoes always-on → T2 mede linha exata + controle limpo.
- **NÃO nesta fase:** sem gate/ratchet CI (catraca sobre veredito não-provado trava ruído), sem hook/automação, sem espalhar além do ProductUtil, sem nota agregada.

## Rollback
Proposta em `proposals/` — recusa = `status: rejected` (append-only). Se o bite-test falhar, a proposta é parked com a evidência (cultura lápide); o `METODO.md` + skill ficam como "tentado, não confiado".
