---
name: VISTAS-PUBLICADAS — registro das vistas navegáveis publicadas
description: Catálogo curado das páginas publicadas em claude.ai — tema, linhagem de versões, estado e o dono em git de que cada vista deriva. Vista é retrato datado; onde divergir do dono, o dono manda.
type: index
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
related_adrs:
  - "0256-knowledge-survival-meia-vida-catraca-sentinela"
  - "0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento"
---

# 📑 Vistas publicadas — registro e linhagem

> **O que é uma vista.** Uma página navegável publicada em `claude.ai` a partir do conhecimento que vive
> em git. Ela existe porque markdown em repositório não se lê com prazer — mas ela **não é documentação
> canônica**: é um **retrato datado**. Onde a vista divergir do dono linkado, **o dono manda**.
>
> **Nada aqui morre.** Vista superada vira `histórica` e continua acessível, com elo pra sucessora —
> o mesmo modelo append-only das ADRs ([ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)).
> Apagar história é perder a linhagem do raciocínio.

## Por que este registro é curado, e não gerado

As vistas moram fora do repositório — a árvore não as enxerga, então **nenhuma máquina consegue derivar
esta lista**. Isso é uma fraqueza declarada, não um descuido: um índice escrito à mão apodrece
([ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)). O que segura a
integridade é a trava de entrada — o hook [`vista-publicada-padrao.mjs`](../../.claude/hooks/vista-publicada-padrao.mjs),
que cobra o padrão **no momento da publicação**, não depois.

Estado medido em 2026-07-29, antes deste registro existir: **1 das 23 vistas** era citada em git.

## O padrão da vista

Toda vista publicada carrega, no topo do arquivo, um bloco declarado — é o que o hook lê:

```html
<!-- vista:
tema: sistema
estado: viva
medido_em: 2026-07-29
dono: memory/reference/PAINEL-SISTEMA.md
sucede: https://claude.ai/code/artifact/<uuid-da-anterior>
-->
```

E, visível na página:

| Elemento | Regra |
|---|---|
| **Banner de estado** | `viva` ou `histórica` no topo, não em rodapé — padrão Docusaurus (`unmaintained`) |
| **Data de medição** | a data em que os números foram medidos, não a de publicação |
| **Recibo de procedência** | as fontes em git e o comando que reproduz cada número derivado |
| **Precedência** | uma linha: *"vista datada; onde divergir do dono linkado, o dono manda"* |
| **Linhagem** | elo pra versão anterior do mesmo tema; a anterior nunca é editada nem apagada |

> ⚠️ **Limite honesto:** o hook verifica que a vista **declara** procedência — não que a procedência
> seja verdadeira. Declaração conferida ≠ conteúdo correto. Quem confere conteúdo é leitura humana.

## Confiabilidade desta classificação

**As 24 vistas foram abertas e lidas por dentro** (👁 em todas as linhas) na varredura de 2026-07-29 —
a dívida declarada na versão anterior deste arquivo, de que 21 estavam agrupadas *pelo título*, está paga.

O que a leitura mudou, em resumo — o detalhe fica na nota de cada tema:

- **3 vistas estavam no tema errado.** "Máquina de entrada" não é sobre organização do conhecimento;
  "Memória do processo" é auditoria de enforcement, não taxonomia de arquivo; "Plano de padronização de
  UI" é plano de campanha, não entrega pontual.
- **2 temas eram na verdade planos, não retratos** — e plano superado não é o mesmo que mapa superado.
- **Um tema virou dois, com prova literal:** o rodapé do manual do dono declara a trilogia
  *Mapa = o quê · Guia = como chegou · Manual = como operar*. A dúvida anotada aqui está resolvida.
- **Uma linhagem estava fora de ordem** (as duas grades de 07-17) e **uma sequência não estava
  registrada** (as 3 etapas da onda dark).
- **A série temporal das grades foi confirmada** — nenhuma supersede outra.

> ⚠️ O que a leitura **não** resolve: as vistas continuam sendo retratos datados, e vários números
> dentro delas envelheceram desde a publicação. Ler por dentro corrigiu o **agrupamento**, não
> re-verificou o **conteúdo** contra o repo de hoje.

---

## Tema: navegação da documentação

Dono em git: [`README.md`](../../README.md) — a porta documental única. Esta vista **renderiza** a rota
dela; não compete com ela.

| Data | Vista | Estado |
|---|---|---|
| 2026-07-29 | 👁 [Documentação do oimpresso — por onde entrar](https://claude.ai/code/artifact/20588245-f612-448d-8b7e-c6614024f607) | **viva** |

> Primeira vista nascida **dentro** do padrão: carrega o bloco `<!-- vista: -->`, banner de estado,
> recibo de procedência e a linha de precedência. Não repete o censo de propósito — aponta pro
> [`PAINEL-SISTEMA.md`](PAINEL-SISTEMA.md), que é gerado e mantido por rotina diária.

## Tema: mapa do sistema

Dono em git: [`GUIA-DO-SISTEMA.md`](../GUIA-DO-SISTEMA.md) · [`PAINEL-SISTEMA.md`](PAINEL-SISTEMA.md) (gerado)

| Data | Vista | Estado |
|---|---|---|
| 2026-08-02 | 👁 [oimpresso — documentação do sistema](https://claude.ai/code/artifact/18086cd7-5e33-434f-b369-bbf0db555017) | **viva** |
| 2026-07-29 | 👁 [oimpresso — fluxo do sistema, peças e diagramas](https://claude.ai/code/artifact/aa1bfaa6-d97b-4116-a6ab-55b39bbfa59d) | **viva** |
| 2026-07-12 | 👁 [mapa-sistema-oimpresso](https://claude.ai/code/artifact/a3c19a93-de01-4a04-82a4-d8ac7ade0106) | histórica |

> **A de 2026-08-02 é explicação, não retrato de estado.** Treze seções lidas de ponta a ponta: o que
> é (as 3 camadas), por que foi construído assim (as 3 eras), as camadas A/B/C, onde roda, como o
> conhecimento é indexado, o que é observado, como uma decisão vira lei, os quatro fluxos de operação
> (venda · OS · cancelamento · deploy) e as linhas vermelhas Tier 0. Dono declarado: [`README.md`](../../README.md).
> Nasceu de [W] 2026-08-02 — *"não ficou nada bom de ler e entender… preciso da explicação documentação
> oficial do projeto"*. Nenhum número foi copiado para dentro: onde um valor importa, está o comando
> que o recalcula.
>
> ⚠️ **Duas vivas no mesmo tema, de propósito.** A de 07-29 (*"fluxo do sistema, peças e diagramas"*)
> **não** foi marcada histórica: superar vista é decisão de [W], e o append-only manda preservar a
> linhagem. Se [W] considerar que a de 08-02 a substitui, o caminho é marcá-la `histórica` com elo
> pra sucessora — nunca apagar.
>
> 📌 **2026-08-03 — o conteúdo da vista de 08-02 foi incorporado ao dono.** [W]: *"incorpore as
> melhorias, no https://oimpresso.com/documentacao"*. O que a vista tinha e o dono não tinha entrou
> no [`GUIA-DO-SISTEMA.md`](../GUIA-DO-SISTEMA.md) — o mapa das sete camadas com estado (A9.1), o
> invariante anti-atrofia (A9.2), o módulo Forja (A6), o elo pro diagrama C4 do runtime (A4) e os
> caminhos de código que faltavam. O trilho de sumário que [W] elogiou na vista virou mecanismo na
> rota: **derivado dos títulos a cada acesso**, não uma lista copiada. A partir daqui, **a rota
> `/documentacao` é a documentação oficial do sistema** e a vista é o retrato datado que a antecedeu.
> **Não a marquei `histórica`** — este registro diz, três linhas acima, que superar vista é decisão
> de [W]. Fica registrada a linhagem; o estado é dele.

> A histórica é *"Tudo que existe, em camadas"* — L0 produto → L5 em voo, com estado por peça. Ela é
> também a perna **"o quê"** da trilogia de 2026-07-12 (ver *manual do dono* e *história e linhagem*
> abaixo): as três se linkam entre si e foram escritas como conjunto.

## Tema: arquitetura técnica do código

Dono em git: [`governance/ARCHITECTURE.md`](../governance/ARCHITECTURE.md) · [`.claude/rules/`](../../.claude/rules/README.md)

| Data | Vista | Estado |
|---|---|---|
| 2026-07-21 | 👁 [Arquitetura oimpresso — mapa técnico](https://claude.ai/code/artifact/5122452d-c89c-4f22-a633-0b55a4b6f871) | **viva** |

> **Estava no tema "sistema inteiro" e não é a mesma coisa.** O título engana; o conteúdo é
> *"Como o oimpresso se organiza"* — um manual de arquitetura **de código** para quem vai programar:
> os dois mundos Blade/React, o caminho de um request, os grupos de `routes/web.php`, login, as três
> camadas de permissão, anatomia de módulo, e as pegadinhas de cada um. Não descreve estado nem
> progresso — descreve estrutura. Por isso não foi superada por mapa nenhum.

## Tema: planos de campanha

Dono em git: os roadmaps e SPECs de cada frente. Plano é **intenção datada**, não retrato de estado.

| Data | Vista | Estado |
|---|---|---|
| 2026-07-20 | 👁 [Plano do sistema inteiro — oimpresso](https://claude.ai/code/artifact/3ed6e38a-904e-4a8c-bbe6-3884d528fc0b) | **viva** |
| 2026-07-20 | 👁 [Plano de padronização de UI — 235 telas](https://claude.ai/code/artifact/6c1ce451-7740-4762-8cc3-fa6c51b8ff36) | **viva** |

> **Os dois estavam separados e são irmãos da mesma data.** O primeiro é o todo — 7 frentes (F0…F6),
> 5 fases, matriz por módulo e a lista dos 10 pontos que só o dono decide. O segundo detalha a frente
> de conformidade de UI: os 5 estados de tela, os 2 bloqueadores de escala e as fases 0→3.
>
> **Por que `viva` e não `histórica`:** o "Plano do sistema" estava marcado histórica no tema mapa —
> mas plano não é superado por um mapa novo; é superado quando é **executado ou substituído por outro
> plano**. Nenhum dos dois foi. ⚠️ São planos **datados**: o estado das fases não foi re-verificado
> nesta varredura, e os dois divergem entre si na contagem de telas (medições de scans diferentes).

## Tema: camada de IA

Dono em git: [`Jana/ARCHITECTURE.md`](../requisitos/Jana/ARCHITECTURE.md) (gerado) · [`PAINEL-SISTEMA.md`](PAINEL-SISTEMA.md) (gerado)

| Data | Vista | Estado |
|---|---|---|
| 2026-07-28 | 👁 [Camada de IA — como está e como deveria ficar](https://claude.ai/code/artifact/b8fd1262-86c5-4b51-ba86-bd2d769de4f0) | **viva** |
| 2026-07-28 | 👁 [Mapa técnico da IA do oimpresso](https://claude.ai/code/artifact/353be09f-b47a-44a4-ba2c-20d505e7a0eb) | histórica |

> **Linhagem confirmada, com uma ressalva que vale registrar.** As duas são do mesmo dia e cobrem os
> **mesmos seis fluxos** (chat, recall, kb-answer, KB Unificado, brief, MCP) — a viva os traz passo a
> passo com `arquivo:linha`, mais a grade de dez dimensões, o delta atual×alvo e o inventário de flags
> e tabelas. A supersessão está certa.
>
> ⚠️ **Mas a histórica guarda o que a viva não tem:** as **seis plantas Mermaid navegáveis** (com zoom
> e tela cheia). Quem quer *ver* a topologia vai nela; quem quer o veredito vai na viva.
>
> A vista viva carrega três números — tabelas, flags e integrações — que **nenhuma máquina deriva hoje**.
> Os três do censo que *são* derivados (agentes, tools MCP, provedores) têm dono gerado e cron diário.

## Tema: conhecimento e memória

Dono em git: [`GUIA-DO-SISTEMA.md`](../GUIA-DO-SISTEMA.md) · [ADR 0345](../decisions/0345-topicos-vivos-aprendizado-por-critica-revisada.md) (a taxonomia) · [`proibicoes.md §5`](../proibicoes.md)

| Data | Vista | Estado |
|---|---|---|
| 2026-07-23 | 👁 [Como o oimpresso guarda conhecimento](https://claude.ai/code/artifact/7673e18e-4bf4-4976-a0b9-e901a2322cb4) | **viva** |
| 2026-07-20 | 👁 [Como o conhecimento se organiza no oimpresso](https://claude.ai/code/artifact/4e0de55f-34a9-42a8-b7b7-5bfd4cf7b04e) | histórica |

> **O tema tinha 4 vistas; duas não eram deste tema** (ver *máquina de entrada* e *auditoria do
> processo* abaixo). Com elas fora, a viva passa a ser a de 07-23 — que era a que estava marcada
> histórica. As duas que ficam são de fato a mesma linhagem: *1 arquivo = 1 pergunta*, os artefatos
> por módulo e por tela, a hierarquia de confiança (comando > gerado > lei > curado à mão), e a regra
> de ouro de procurar o dono antes de criar doc novo.

## Tema: máquina de entrada

Dono em git: `US-INFRA-002` (Client Signal) · [`memory/dominio/`](../dominio/) (os dicionários que roteiam) · [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)

| Data | Vista | Estado |
|---|---|---|
| 2026-07-28 | 👁 [Máquina de entrada — o que chega decide, não o que se documenta](https://claude.ai/code/artifact/8a1ccc86-ccfd-43bf-88bf-b322a4f233a1) | **viva** |

> **Estava no tema "conhecimento e memória" — e não é sobre onde o conhecimento mora.** É sobre
> **ingestão de sinal externo**: reclamação de cliente, manual de concorrente, feedback em lote. Traz
> os três decisores que já existem (`feedback-capture`, `curador`, `TriageTool`), a divisão em três
> faixas (máquina decide / IA propõe / só o dono decide), o roteamento por dicionário de domínio, e as
> quatro ondas para ligar. Estar no tema errado a fazia parecer sucessora dos manuais de organização —
> que ela não é.

## Tema: auditoria do processo (enforcement)

Dono em git: [`proibicoes.md §5`](../proibicoes.md) · [`LICOES_CODE.md`](../LICOES_CODE.md) · [`prototipo-ui/PROCESSO_MEMORIA_CC.md`](../../prototipo-ui/PROCESSO_MEMORIA_CC.md)

| Data | Vista |
|---|---|
| 2026-07-09 | 👁 [Memória do processo — revisão profunda](https://claude.ai/code/artifact/6143e74b-9407-424a-98ab-ccad30ad483a) |

> **Estava no tema "conhecimento e memória"; o título engana.** Não é sobre taxonomia de arquivo — é
> a **auditoria adversarial do loop Cowork→Code**: 57 agentes, 13 categorias de erro, 36 consertos
> propostos, 26 sobreviventes, 10 rejeitados (que viraram entradas do §5). O diagnóstico-raiz é
> *"corrigir o comparador ≠ invocá-lo"*.
>
> **Sem estado viva/histórica, como as grades:** é medição datada de um momento, não um mapa que
> alguém substitui. Boa parte dos consertos que ela lista já shipou desde então — o que a torna
> **fóssil útil**, não afirmação sobre hoje.

## Tema: grades de réguas

Dono em git: [`LICOES_CODE.md`](../LICOES_CODE.md) · [`proibicoes.md §5`](../proibicoes.md) · [`reguas-do-sistema.js`](../../.claude/workflows/reguas-do-sistema.js)

> **Série temporal, não supersessão — confirmado por leitura.** Cada data é uma medição do mesmo
> instrumento em momento diferente; todas seguem válidas como histórico. Não há "histórica" neste
> tema: há **linha do tempo**. As quatro completas trazem o mesmo aparato (dimensões pontuadas 0-10,
> placar acima/à-frente/empatadas/refutadas, refutação adversarial, chips do que roubar) — o número de
> dimensões é que cresceu: 6 em julho/09-10, 11 a partir de 07-17, quando as ADRs 0333/0334 somaram os
> eixos *rodar-e-observar* e *servir-o-negócio*.

| Data (medição) | Vista | Escopo |
|---|---|---|
| 2026-07-22 | 👁 [Guardrails de integridade — grade 2026](https://claude.ai/code/artifact/46b5ebed-acd9-4d78-829a-64f82d48c313) | recorte — 3 classes |
| 2026-07-18 | 👁 [Grade de Réguas — IA OS · 2026-07-18 (corrigida pós-adversário)](https://claude.ai/code/artifact/586a9cca-c258-4e94-91b6-50196b6cf0af) | completa — 11 dim |
| 2026-07-17 (tarde) | 👁 [Grade de réguas — memoria-conhecimento](https://claude.ai/code/artifact/b96b68d5-748d-420e-b4a1-b9be46a5145f) | parcial — 1 de 11 |
| 2026-07-17 (manhã) | 👁 [Grade de Réguas — 2026-07-17](https://claude.ai/code/artifact/f775b9b5-2346-4119-91b1-518ae77f02f7) | completa — 11 dim |
| 2026-07-10 | 👁 [Grade de Réguas · 2026-07-10](https://claude.ai/code/artifact/5734f9ff-dce9-489c-8bb2-16f51732dc84) | completa — 6 dim |
| 2026-07-09 | 👁 [Grade das réguas — onde sou fraco vs acima do mercado](https://claude.ai/code/artifact/68fb943c-03c3-4f2b-9112-36d36fe36374) | completa — 6 dim |

> **Duas correções de ordem e escopo que a leitura impôs:**
>
> - **As duas de 07-17 estavam invertidas.** A `memoria-conhecimento` é a **posterior** — ela mesma diz
>   *"▲ era 7,0 · grade da manhã"* e se declara *"rodada parcial · 1 de 11 dimensões"*, repontuando a
>   dimensão para 7,4 depois de fechar dois chips. Não é uma grade paralela: é uma **re-medição de uma
>   linha** da grade completa da manhã.
> - **A de 07-18 media 07-18 e foi publicada em 07-19** — a coluna agora traz a data de medição, como
>   manda o padrão da vista.
>
> **Sobre os dois recortes:** `memoria-conhecimento` é parcial *do mesmo instrumento*. Já `Guardrails
> de integridade` usa o mesmo **método** (nota 0-10 vs best-of-breed, refutação adversarial, plano por
> ROI) sobre um **recorte próprio** — três classes de erro da base de conhecimento (editar arquivo
> gerado à mão, doc duplicado, arquivo órfão) que não são dimensões da grade do IA OS. Fica na série
> por parentesco de método, marcada como recorte.

## Tema: manual do dono

Dono em git: [`README.md`](../../README.md) · [`PAINEL-SISTEMA.md`](PAINEL-SISTEMA.md)

| Data | Vista | Estado |
|---|---|---|
| 2026-07-12 | 👁 [manual-do-dono-oimpresso](https://claude.ai/code/artifact/4abea172-b0e9-484d-8efd-f006ee9ae79e) | **viva** |

> *"Comece aqui quando se perder"* — como **operar**: o prompt de onboarding para colar numa sessão
> nova (com o truque de exigir 5 bullets como prova de que a IA entendeu), os dois comandos de
> auditoria que cobrem 95% dos casos, e para onde ir quando bate a confusão.

## Tema: história e linhagem

Dono em git: [`HISTORIA-LINHAGEM.md`](../HISTORIA-LINHAGEM.md) · [`proibicoes.md §5`](../proibicoes.md) · `memory/handoffs/`

| Data | Vista | Estado |
|---|---|---|
| 2026-07-12 | 👁 [guia-historia-oimpresso](https://claude.ai/code/artifact/ce7d8ccb-d200-49f9-a1e8-1917fef96b99) | **viva** |

> *"O que você já tentou fazer"* — como **chegou aqui**: o arco em 8 fases (2024 → programa SDD), os
> 7 padrões que se repetiram nos incidentes, o que foi avaliado e morto, o que ficou pela metade, e as
> 6 decisões esperando o dono.
>
> **A dúvida anotada na versão anterior está resolvida — por prova literal, não por julgamento.** O
> rodapé do manual declara: *"o 3º da família (Mapa = o quê · Guia = como chegou · Manual = como
> operar)"*, e as três se linkam entre si. São **temas distintos escritos como trilogia** em 2026-07-12,
> não versões do mesmo assunto. A perna "Mapa" é a
> [mapa-sistema-oimpresso](https://claude.ai/code/artifact/a3c19a93-de01-4a04-82a4-d8ac7ade0106),
> catalogada acima em *mapa do sistema*.

## Tema: onda dark do cockpit

Dono em git: `resources/css/` + tokens gerados · [UI-0023](../requisitos/_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md)

> **Sequência, não versões.** As três são etapas encadeadas do mesmo trabalho — PRs #3981 → #3982 →
> #3983 — e cada uma é um **pedido de aprovação visual** ao dono antes de tirar o PR do draft. Nenhuma
> supersede a anterior: a seguinte continua de onde a aprovada parou.

| Data | Etapa | Vista |
|---|---|---|
| 2026-07-08 | ① canvas dark (#3981) | 👁 [Canvas dark — comparativo por imagem](https://claude.ai/code/artifact/30403bc6-e33c-4755-81a7-39000a3ded4f) |
| 2026-07-08 | ② sidebar preta (#3982) | 👁 [FASE 2 — sidebar preta + dark 240 (buildado)](https://claude.ai/code/artifact/6462ba47-672b-41e0-8c00-f8893baf471c) |
| 2026-07-09 | ③ linhas do Financeiro (#3983) | 👁 [Financeiro dark — linhas antes/depois](https://claude.ai/code/artifact/de573ca9-876c-47da-943d-51bd89553245) |

> ① compara três opções de canvas (prod atual · snapshot ds-v6 · espelho do design) e registra a
> escolha do dono pela C. ② renderiza a fase seguinte com os valores exatos do `tokens:build` — a
> mudança visível é a sidebar preta também no modo claro. ③ isola um fix escopado: a divisória
> `--fin-line` sem variante dark ficava branca no escuro.

## Tema: pedidos de ação ao dono

Não são mapas nem relatórios — são **instruções passo a passo** para o dono executar o que a máquina
não pode fazer sozinha. Dono em git: o módulo respectivo.

| Data | Vista |
|---|---|
| 2026-07-16 | 👁 [Ligar o robô de teste das telas do Financeiro](https://claude.ai/code/artifact/32458e59-f17f-4504-837c-1c699090c983) |

> 4 passos, 3 deles do dono: criar a empresa `SMOKE TESTE`, ligar o módulo Financeiro nela, e guardar
> login e senha nos segredos do GitHub. A empresa é fake e vazia **de propósito** — o robô manda print
> para um provedor externo, e cliente real vazaria razão social e valores.

---

## Como publicar uma vista nova

1. Escreva a página com o bloco `<!-- vista: -->` no topo e os cinco elementos visíveis do padrão.
2. Publique. O hook confere o bloco e avisa o que faltar.
3. **Adicione a linha aqui** — tema, data, URL, estado.
4. Se o tema já tinha uma viva: marque a anterior como `histórica` e aponte `sucede:` pra ela.
   **Nunca edite nem despublique a anterior** — ela é o histórico.

> **Antes de criar tema novo, confira se o assunto já tem dono aqui.** Metade dos erros que a varredura
> de 2026-07-29 corrigiu vinha de agrupar **pelo título**: `Mapa técnico`, `Memória do processo` e
> `Máquina de entrada` soam como temas que já existiam, e não eram. Título é hipótese; conteúdo é
> medição.
