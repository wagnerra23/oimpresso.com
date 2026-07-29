---
name: VISTAS-PUBLICADAS — registro das vistas navegáveis publicadas
description: Catálogo curado das páginas publicadas em claude.ai — tema, linhagem de versões, estado e o dono em git de que cada vista deriva. Vista é retrato datado; onde divergir do dono, o dono manda.
type: index
authority: canonical
lifecycle: ativo
updated_at: "2026-07-29"
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

Das 24 vistas, **3 são conhecidas por dentro** (marcadas 👁): duas foram lidas e uma nasceu já dentro
do padrão. As outras 21 foram agrupadas **pelo título**, o que é hipótese, não medição — pode haver
surpresa ao abrir. Quem ler uma delas, corrija a linha aqui.

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

## Tema: sistema inteiro

Dono em git: [`README.md`](../../README.md) · [`GUIA-DO-SISTEMA.md`](../GUIA-DO-SISTEMA.md) · [`governance/ARCHITECTURE.md`](../governance/ARCHITECTURE.md)

| Data | Vista | Estado |
|---|---|---|
| 2026-07-29 | 👁 [oimpresso — fluxo do sistema, peças e diagramas](https://claude.ai/code/artifact/aa1bfaa6-d97b-4116-a6ab-55b39bbfa59d) | **viva** |
| 2026-07-21 | [Arquitetura oimpresso — mapa técnico](https://claude.ai/code/artifact/5122452d-c89c-4f22-a633-0b55a4b6f871) | histórica |
| 2026-07-20 | [Plano do sistema inteiro — oimpresso](https://claude.ai/code/artifact/3ed6e38a-904e-4a8c-bbe6-3884d528fc0b) | histórica |
| 2026-07-12 | [mapa-sistema-oimpresso](https://claude.ai/code/artifact/a3c19a93-de01-4a04-82a4-d8ac7ade0106) | histórica |

## Tema: camada de IA

Dono em git: [`Jana/ARCHITECTURE.md`](../requisitos/Jana/ARCHITECTURE.md) (gerado) · [`PAINEL-SISTEMA.md`](PAINEL-SISTEMA.md) (gerado)

| Data | Vista | Estado |
|---|---|---|
| 2026-07-28 | 👁 [Camada de IA — como está e como deveria ficar](https://claude.ai/code/artifact/b8fd1262-86c5-4b51-ba86-bd2d769de4f0) | **viva** |
| 2026-07-28 | [Mapa técnico da IA do oimpresso](https://claude.ai/code/artifact/353be09f-b47a-44a4-ba2c-20d505e7a0eb) | histórica |

> A vista viva deste tema carrega uma **grade de dez dimensões** (juízo datado, não fato derivável) e
> três números — tabelas, flags e integrações — que **nenhuma máquina deriva hoje**. Os três números do
> censo que *são* derivados (agentes, tools MCP, provedores) têm dono gerado e cron diário.

## Tema: conhecimento e memória

Dono em git: [`GUIA-DO-SISTEMA.md`](../GUIA-DO-SISTEMA.md) · [`proibicoes.md §5`](../proibicoes.md) · [ADR 0270](../decisions/0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento.md)

| Data | Vista | Estado |
|---|---|---|
| 2026-07-28 | [Máquina de entrada — o que chega decide, não o que se documenta](https://claude.ai/code/artifact/8a1ccc86-ccfd-43bf-88bf-b322a4f233a1) | **viva** |
| 2026-07-23 | [Como o oimpresso guarda conhecimento](https://claude.ai/code/artifact/7673e18e-4bf4-4976-a0b9-e901a2322cb4) | histórica |
| 2026-07-20 | [Como o conhecimento se organiza no oimpresso](https://claude.ai/code/artifact/4e0de55f-34a9-42a8-b7b7-5bfd4cf7b04e) | histórica |
| 2026-07-09 | [Memória do processo — revisão profunda](https://claude.ai/code/artifact/6143e74b-9407-424a-98ab-ccad30ad483a) | histórica |

## Tema: grades de réguas

Dono em git: [`LICOES_CODE.md`](../LICOES_CODE.md) · [`proibicoes.md §5`](../proibicoes.md) · [`reguas-do-sistema.js`](../../.claude/workflows/reguas-do-sistema.js)

> **Série temporal, não supersessão.** Aqui cada data é uma medição do mesmo instrumento em momento
> diferente — todas seguem válidas como histórico. Não há "histórica" neste tema: há **linha do tempo**.

| Data | Vista |
|---|---|
| 2026-07-22 | [Guardrails de integridade — grade 2026](https://claude.ai/code/artifact/46b5ebed-acd9-4d78-829a-64f82d48c313) |
| 2026-07-19 | [Grade de Réguas — 07-18 (corrigida pós-adversário)](https://claude.ai/code/artifact/586a9cca-c258-4e94-91b6-50196b6cf0af) |
| 2026-07-17 | [Grade de Réguas — 2026-07-17](https://claude.ai/code/artifact/f775b9b5-2346-4119-91b1-518ae77f02f7) |
| 2026-07-17 | [Grade de réguas — memoria-conhecimento](https://claude.ai/code/artifact/b96b68d5-748d-420e-b4a1-b9be46a5145f) |
| 2026-07-10 | [Grade de Réguas · 2026-07-10](https://claude.ai/code/artifact/5734f9ff-dce9-489c-8bb2-16f51732dc84) |
| 2026-07-09 | [Grade das réguas — onde sou fraco vs acima do mercado](https://claude.ai/code/artifact/68fb943c-03c3-4f2b-9112-36d36fe36374) |

## Tema: manual e história do dono

Dono em git: [`HISTORIA-LINHAGEM.md`](../HISTORIA-LINHAGEM.md) · [`README.md`](../../README.md)

| Data | Vista | Estado |
|---|---|---|
| 2026-07-12 | [manual-do-dono-oimpresso](https://claude.ai/code/artifact/4abea172-b0e9-484d-8efd-f006ee9ae79e) | **viva** |
| 2026-07-12 | [guia-historia-oimpresso](https://claude.ai/code/artifact/ce7d8ccb-d200-49f9-a1e8-1917fef96b99) | **viva** |

> Os dois têm a mesma data e temas distintos (manual de operação × linhagem histórica). Ficam ambos
> vivos até alguém ler e decidir se são de fato temas separados.

## Tema: trabalho pontual

Não são mapas — são entregas de uma tarefa específica. Dono em git: o módulo respectivo.

| Data | Vista |
|---|---|
| 2026-07-20 | [Plano de padronização de UI — 235 telas](https://claude.ai/code/artifact/6c1ce451-7740-4762-8cc3-fa6c51b8ff36) |
| 2026-07-16 | [Ligar o robô de teste das telas do Financeiro](https://claude.ai/code/artifact/32458e59-f17f-4504-837c-1c699090c983) |
| 2026-07-09 | [Financeiro dark — linhas antes/depois](https://claude.ai/code/artifact/de573ca9-876c-47da-943d-51bd89553245) |
| 2026-07-08 | [FASE 2 — sidebar preta + dark 240 (buildado)](https://claude.ai/code/artifact/6462ba47-672b-41e0-8c00-f8893baf471c) |
| 2026-07-08 | [Canvas dark — comparativo por imagem](https://claude.ai/code/artifact/30403bc6-e33c-4755-81a7-39000a3ded4f) |

---

## Como publicar uma vista nova

1. Escreva a página com o bloco `<!-- vista: -->` no topo e os cinco elementos visíveis do padrão.
2. Publique. O hook confere o bloco e avisa o que faltar.
3. **Adicione a linha aqui** — tema, data, URL, estado.
4. Se o tema já tinha uma viva: marque a anterior como `histórica` e aponte `sucede:` pra ela.
   **Nunca edite nem despublique a anterior** — ela é o histórico.
