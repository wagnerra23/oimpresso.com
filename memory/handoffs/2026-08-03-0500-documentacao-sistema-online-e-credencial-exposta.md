---
date: "2026-08-03"
time: "05:00 BRT"
slug: "documentacao-sistema-online-e-credencial-exposta"
tldr: "🔴 Há CREDENCIAL EM TEXTO CLARO num repositório PÚBLICO — inclusive a senha do Vaultwarden, que abre os outros 27 segredos. 3 das 7 cópias são append-only e intocáveis, então rotação é a única mitigação e é ato do [W]. No resto: a documentação do sistema ganhou URL (oimpresso.com/documentacao — a página É o GUIA renderizado, zero HTML commitado), com busca e diagramas; 9 agents não carregavam há meses; e nasceu o agente documentacao-sistema porque desviei 4x do pedido."
decided_by: [W]
cycle: null
prs: [5168, 5170, 5171, 5173, 5182, 5186, 5188, 5199, 5201, 5205, 5210]
us: []
next_steps:
  - "🔴 ROTACIONAR — Vaultwarden PRIMEIRO (a senha exposta é a dele, e ele guarda os outros 27 segredos catalogados no _INDEX-SECRETS). Depois: SYSDBA/masterkey do Firebird (senha de fábrica, ~15 scripts), painel + root SSH da Central VoIP (CentOS 7 EOL), e os 5 tokens Bearer. Limpar arquivo NÃO substitui: o repo é público, o histórico permanece, e 3 das 7 cópias são append-only (1 ADR + 2 handoffs)."
  - "[W]: decidir se o repositório deve seguir PÚBLICO — o README declara 'Software proprietário'. Medido com gh repo view: visibility=PUBLIC."
  - "[W]: abrir /documentacao LOGADO e confirmar que os diagramas Mermaid desenham. Provei que a lib chegou (HTTP 200, 3.565.102 bytes exatos); a renderização exige sessão, que o agente não tem."
  - "Para usar o agente documentacao-sistema: abrir SESSÃO NOVA depois do merge do #5205 — agents são carregados no início da sessão. Pedir 'documenta o sistema' e ele PERGUNTA o escopo antes de escrever."
  - "[W]: 3 gaps da régua seguem abertos, por retorno — Vale (linter de prosa; único em nota ZERO com ferramenta madura, começar só pelas regras que bloqueiam) · multi-stakeholder (separar o Guia por público) · validação contra o vivo (o mais caro; exige promover detector de advisory a bloqueante, e o §5 tem 4 lápides de gate que reprovava o legítimo)."
  - "[W]: censo de máquinas — 3 ocorrências HOJE de 'adicionou máquina, não regenerou'. A solução intuitiva (hook ao criar arquivo) NÃO cobre o caso real: PR que já regenerou precisa regenerar de novo quando o main anda. O padrão do índice de ADRs (regenerar NO MERGE) não teria esse custo. Armar exige FP medido antes."
related_adrs: ["0256-knowledge-survival-meia-vida-catraca-sentinela", "0333-emenda-0330-eixo-rodar-e-observar-submedido", "0130-handoff-append-only-mcp-first"]
---

# Handoff 2026-08-03 05:00 — documentação do sistema no ar, e credencial exposta em repo público

## O que está no ar

**`oimpresso.com/documentacao`**, atrás de login (decisão [W]).

O desenho é o ponto: **a página É o `memory/GUIA-DO-SISTEMA.md` renderizado em runtime**. Zero
HTML commitado — não existe cópia para envelhecer. Alterou o Guia num PR? A página muda no
request seguinte. Foi recusado o caminho fácil (gerar e commitar HTML) porque é exatamente o
vício que a [ADR 0256] descreve: *derivado e enforçado sobrevive; escrito e lembrado apodrece*.

Três rotas: `/documentacao` (leitura guiada) · `/documentacao/buscar` (full-text sobre `adr`,
`reference`, `spec`, `runbook` — sessions e handoffs ficam fora por decisão [W]) ·
`/documentacao/{slug}` (qualquer documento do acervo).

**Smoke real:** `/` 200 · `/login` 200 · `/documentacao` 302→login · `/js/mermaid.min.js` 200
com os **3.565.102 bytes exatos** do arquivo local.

## 🔴 O que interrompe tudo

Reconciliando o `INFRA.md` (órfão, 2 meses parado) apareceu **credencial em texto claro**.
Levantamento completo: **12.850 arquivos varridos, 99 hits**. Reais:

- a credencial de dev **em 7 arquivos** — **3 append-only** (`decisions/0016`, 2 handoffs);
- **é a mesma senha do Vaultwarden** — o cofre dos outros **27 segredos**;
- `SYSDBA`/`masterkey` (fábrica do Firebird) em ~15 scripts;
- 5 tokens `Bearer`.

**O repositório é PÚBLICO.** Eu afirmei o contrário sem medir, e isso **inverteu o veredito de
risco** que já tinha sido entregue — corrigido só porque [W] pediu o adversário.

Consequência dura: `git filter-repo` **não resolve** (conteúdo público pode ter sido clonado,
cacheado, indexado) e **3 cópias não podem ser editadas** sem quebrar o append-only.
**Rotação é a única mitigação real.**

## Por que nenhuma máquina viu — o buraco é da composição

`gitleaks` varre o repo inteiro **e o histórico** (semanal, verde) mas procura assinatura de
token — par usuário/senha em prosa não casa. `memory-health` **reconheceria a forma** mas varre
só `memory/`; a raiz está fora. O RAG indexa `memory/*`, então o `INFRA.md` **nunca entrou no
índice**. Nenhuma quebrada isoladamente.

Corolário no §5: *"o scan está verde"* nunca significa *"não há segredo"* — significa *"nenhum
padrão conhecido apareceu onde ele olha"*.

## Outros dois achados de sistema

**9 agents não carregavam há meses** (#5168) — `description:` plano com `<example>` na coluna 0
faz o YAML ler `Context:`/`user:`/`assistant:` como chaves irmãs de `name:`. Frontmatter
inválido → agent descartado **em silêncio**. Entre os mortos: `whatsapp-doctor` (o SRE de
plantão do daemon), `capterra-senior`, `memoria-senior`. Discriminador **24/24**; fix
`description: |`. **Não existe índice nem gate para `.claude/agents/`** — por isso sobreviveu.

**`DESIGN.md` ensinava "sidebar light"** (#5173) citando UI-0009/0014 — **dois saltos atrás** do
canon (UI-0023 dark-fixo, aceito 2026-07-16, que classifica a afirmação anterior como
incorreta). Não era doc velho inofensivo: era instrução ativa para regressão, **verde em todos
os gates**. Só leitura humana pega esse tipo.

## O especialista — e por que ele existe

Desviei **quatro vezes** do mesmo pedido. [W]: *"eu peço mais sempre desvia do foco"*.

`documentacao-sistema` (#5205) carrega: **regra um — não adivinhe, pergunte**; escopo travado
(charter por tela, BRIEFING por módulo, ADR, gate e segurança estão **fora**); rotina de
**espionar as máquinas** antes de escrever (*"a divergência é o trabalho"*); **loop de 5 tempos**
(medir → traduzir → publicar → vigiar → **aprender**); e régua com **critérios, não notas**.

⚠️ Ele só existe em **sessão nova** — agents carregam no início da sessão.

## Estado MCP no momento do fechamento

- **brief-fetch** (SessionStart): cycle vazio · 5 HITL pendentes [W] · Brain B 0% · 11 tasks em voo
- **decisões 24h**: 1 ADR (4791 — trio de tela muda de casa para memory)
- **flags**: 659 US não atribuídas (516 sem dono) · SDD composta 55,3 · migration/PR/visual sem crítico
- **PRs desta sessão**: 10 mergeados, 1 com auto-merge armado (#5205)
- **two-strikes no SessionStart**: LC-08 (41x), LC-09, LC-10, LC-11 (5x), LC-13 (8x) sem gate —
  LC-08 e LC-13 incrementados **nesta sessão**, por erros meus

## A régua da documentação de sistema

Medida 2026-08-03 contra arc42 · C4 · ADR Nygard · Living Documentation: **64/100**.

Decision log **10** (369 ADRs append-only, índice gerado, merge = ratificação) · fonte única 9 ·
governança 9 · **diagramas como código 2 → fechado nesta sessão** · multi-stakeholder 4 ·
**prosa 0** (não há Vale nem equivalente).

⚠️ A primeira grade que fiz deu **52** e estava **errada de categoria** — comparei com
Diátaxis/Swimm/Mintlify, que são documentação de **produto**. [W] pegou. Mesma documentação,
régua errada, 12 pontos de diferença.
