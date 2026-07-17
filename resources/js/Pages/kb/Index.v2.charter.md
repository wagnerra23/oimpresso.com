---
page: /kb/v2
component: resources/js/Pages/kb/Index.v2.tsx
controller: Modules\KB\Http\Controllers\KbController@indexV2
route: kb.v2
status: draft
owner: wagner
parent_module: KB
related_us: [US-KB-001]
persona_principal: Wagner / governança (1440px desktop)
persona_secundaria: Larissa / operacional (1280px balcão) — só quando existir SOP escrito à mão
charter_version: 2.0
charter_at: 2026-07-17
related_adrs:
  - 0150-kb-unificado-grafo-conhecimento-modulo-ia-central # proposta
  - 0039-ui-chat-cockpit-padrao
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0114-prototipo-ui-cowork-loop-formalizado
related_briefing: ../../../memory/requisitos/KB/BRIEFING.md
related_schema: ../../../memory/requisitos/KB/SCHEMA-DB-V1.md
related_prototype: ../../../prototipo-ui/cowork/kb-page.jsx
mwart_pattern_reuse:
  blueprint_cowork: prototipo-ui/cowork/kb-page.jsx
  blueprint_screenshot_approval: pendente (gate F1.5)
  derived_screens: [Index.v2]
  divergence_from_blueprint: "tri-pane sidebar+lista+leitor (port direto JSX→TSX)"
---

# Charter — `kb/Index.v2.tsx` · v2.0 **DRAFT (aguarda [W])**

> **O que mudou da v1.0 (2026-05-16):** a v1.0 descrevia uma tela de **SOPs de gráfica com dados
> inventados** ("fallback MOCK_NODES" era Goal 7). [W] 2026-07-17: *"eu quero os dados, mas com o
> design do KB"*. Esta v2.0 descreve a MESMA tela servindo os **documentos canônicos reais**. O
> desenho não muda; a fonte de dados muda — e é isso que a torna verdadeira.

---

## 1. Em uma frase

**O leitor dos documentos da empresa, num layout de três colunas** — categorias à esquerda, lista de
documentos no meio, documento aberto à direita — com busca instantânea e `⌘K`.

> **A US que esta tela atende — [US-KB-001](../../../../memory/requisitos/KB/SPEC.md):** *"Como Wagner
> governance, **quero ver** os ADRs do projeto como nós navegáveis, **para** consultar dependências
> sem grep cego no filesystem."* O **backend dela já está ✅ LIVE** (o `KbBridgeFromMcpJob` popula
> `kb_nodes` em prod há meses) — **o que falta é o "ver"**. Esta tela É o ver. Sem ela, a US está
> entregue pela metade: o dado existe, ninguém enxerga.
>
> A tela também **encosta** em US-KB-004 (trilhas) e US-KB-005 (troubleshooter) — os diálogos existem
> na header —, mas o contrato aqui é a **001**; as outras têm charter próprio quando saírem do mock.

## 1-bis. O nome muda junto (achado do encaixe no template, [W] aprovou o mockup)

| | Hoje | Vira |
|---|---|---|
| **Título** | "Procedimentos Operacionais Padrão" | **"Base de conhecimento"** |
| **Menu / breadcrumb** | Conhecimento › **SOPs** | Conhecimento › **Documentos** |
| **Subtítulo** | "18 SOPs · … · **MOCK (Agent A pendente)**" | "3.016 documentos · **atualizado há N min**" |

> **Por quê:** "SOP" (procedimento operacional) só fazia sentido com o corpus de gráfica. Servindo
> ADR/sessão/charter, o nome vira mentira de rótulo — a pessoa lê "Procedimentos" e encontra decisão
> de arquitetura. E o subtítulo troca o **aviso de que é falso** pelo **frescor do bridge**, que é o
> número que passa a importar quando o dado é real.
>
> ⚠️ **Toca o menu do ERP** (`Modules/KB/Http/Controllers/DataController.php`) — é produto, [W] confirma.

## 2. O que a tela mostra (isto é o contrato)

Os **documentos canônicos que já existem** no repositório e que um robô (`KbBridgeFromMcpJob`) copia
pra dentro do banco **a cada 15 minutos, hoje, em produção**. São os documentos de verdade da empresa:
decisões de arquitetura, registros de sessão, contratos de tela, receitas operacionais, resumos de
módulo, especificações.

**Não mostra SOP inventado.** O corpus de gráfica (Roland VS-540 / HP Latex) que a tela exibe hoje é
ficção e **sai** — a persona "operadora de gráfica" não existe no cliente real (o piloto biz=4 é loja
de **vestuário**).

## 3. As categorias (painel esquerdo) — **medidas, não estimadas**

**São os tipos de documento — não precisam ser inventadas, já vêm no dado.** Contagem real do
acervo (medida em 2026-07-17, `git ls-files memory/**/*.md` = **3.016**):

| Categoria | Quantos | O que é |
|---|---:|---|
| **Decisões (ADR)** | 447 | por que o sistema é como é |
| **Sessões** | 453 | o que foi feito, quando, por quem |
| **Handoffs** | 258 | o estado que uma sessão passa pra próxima |
| **Contratos de tela** | 237 | o que cada tela promete fazer |
| **Receitas (runbook)** | 152 | como executar uma operação |
| **Referências** | 125 | fatos e apontadores |
| **Resumos (briefing)** | 80 | estado consolidado de um módulo |
| **Especificações (spec)** | 59 | o que foi pedido |
| **Comparativos** | 11 | nós vs mercado |
| **Diversos** | **639** | **o que não tem tipo** — planos, pegadinhas, índices, lições |

> **Invariante:** categoria = `kb_nodes.type`. Nada de classificador "inteligente" adivinhando —
> se o dado não traz o tipo, a categoria não existe. (A v1.0 previa classificar por *equipamento*,
> ex. `auto_match: {field:'equip', value:'Roland VS-540'}` — isso **morre** aqui: classificar um ADR
> de governança por impressora é ficção herdada do corpus falso.)

> ### ⚠️ "Diversos: 639" é dívida VISÍVEL de propósito — não é bug de desenho
>
> Dos 3.016 documentos, **~1.600 têm tipo** e **~1.400 não**. Além dos 639 sem classificação em
> `requisitos/`, o bridge hoje **nem lista** `handoffs` (258) · `sprints` (40) · `governance` (9) ·
> `audits` (6) · `dominio` (6) entre os tipos que aceita (`KbBridgeFromMcpJob::tipos()`).
>
> **A tela mostraria pouco mais da metade do acervo — e ninguém perceberia**, porque o que falta
> simplesmente não aparece. Esconder isso seria a meia-verdade que já custou caro aqui (o `⬜` do
> UC-09 escondia um `❌`; o rótulo "MOCK" no cabeçalho não cobria os botões que mentiam).
>
> **Decisão de desenho:** a categoria "Diversos" **existe e mostra o número**. Feia por fora,
> honesta por dentro: você olha a lateral e sabe que 639 documentos ainda não foram organizados.
> A alternativa (omitir) faria a tela parecer completa mentindo. **Fechar essa dívida = dar tipo aos
> 639 + estender `tipos()` pra handoffs/sprints/governance/audits/dominio** — trabalho real, PR
> próprio, não bloqueia esta tela.

## 4. O que dá pra fazer (Goals)

1. **Ler** um documento inteiro no painel direito, sem recarregar a página.
2. **Navegar por categoria** — clicar em "Decisões" e ver só os ADRs, com a contagem certa.
3. **Buscar** por título/etiqueta/autor, com resposta enquanto digita.
4. **`⌘K`** abre a paleta de comando; `Esc` fecha; `/` foca a busca.
5. **Favoritar** um documento e ele continuar favorito depois.
6. **Ver o que está velho** — o painel de saúde mostra o que precisa de revisão.
7. **Seguir link entre documentos** — um ADR que cita outro abre com um clique.
8. **1280px sem barra de rolagem horizontal** (Larissa, balcão).

## 5. O que a tela NÃO faz (Non-Goals)

> [W] aprova esta lista. Cada item vira teste.

- **Não edita** documento canônico. Eles vêm do git — a fonte é o repositório, e a tela é **leitura**.
  Editar aqui criaria duas verdades. (Documento escrito à mão, editável, é outro assunto — §7.)
- **Não substitui o `/kb` atual** sem decisão explícita — ver §7.
- Não faz CRUD de trilhas/troubleshooters (charters próprios).
- Não carrega 1000+ documentos sem virtualização.
- Não sincroniza em tempo real.

## 6. O que a tela NUNCA pode fazer (Anti-hooks — viram teste que bloqueia merge)

- **NUNCA mostrar documento de outro business** (multi-tenant Tier 0 — ADR 0093).
- **NUNCA afirmar sucesso de uma ação que não aconteceu.** Se o botão não persiste, o aviso diz que
  é demonstração. (Isto está aqui porque **aconteceu**: 4 botões respondiam *"Artigo re-verificado e
  marcado como fresco"* sem gravar nada — ver `casos.md` UC-KBV2-10.)
- NUNCA escrever no banco ao abrir a tela (ler é ler).
- NUNCA disparar Jobs, e-mail, WhatsApp ou IA ao abrir — IA só na ação explícita "Perguntar ao KB".
- NUNCA registrar PII em log/auditoria.
- NUNCA usar cor crua (`bg-blue-100`) no lugar de token do Design System.

## 7. Decisões [W] — estado em 2026-07-17

| # | Decisão | Status |
|---|---|---|
| **D1** | "Os dados" = os documentos canônicos (ADR/session/charter/…) | ✅ **RESPONDIDA** — [W]: *"eu quero os dados, mas com o design do KB"*, sobre o mockup que exibia ADR 0340/0339 reais. |
| **D3** | As categorias = os tipos do documento | ✅ **RESPONDIDA** — [W] aprovou o mockup com a lateral por tipo: *"eu gostei sim, ficou bom"*. |
| **D5** | "Diversos: 639" visível na lateral | ✅ **RESPONDIDA** — [W] viu o mockup **com** a linha "Diversos 639" exposta e aprovou (§3). Dívida fica **à vista**, não escondida. |
| **D2** | **O `/kb` de hoje continua, ou a V2 toma o lugar?** | 🔴 **ABERTA** — o `/kb` tem **histórico de versões, soft-delete e filtro de PII** que a V2 **não tem**. Cutover sem isso **perde função**. Enquanto indefinido: **coexistem** (`/kb` legado · `/kb/v2` novo) — é o estado atual e não bloqueia o Controller. |
| **D4** | As **68 cores cruas** → tokens (gate visual ADR 0114) | 🔴 **ABERTA** — mudança visual, PR separado, não bloqueia o Controller. **Mas bloqueia a baseline definitiva:** contratar a tela no visreg congela em pixel o que existir na hora (§8-bis). |

## 8-bis. O caminho até a tela viva (medido, não estimado)

O gate `visual-regression` (**required**) reprova qualquer PR que toque a tela **ou o Controller que
a serve** enquanto ela não estiver contratada em `tests/Browser/visreg-screens.json`. Medido hoje:

```
classify(KbController.php c/ indexV2) → reason: controller-inertia · screens: [kb/Index.v2]
coverage → uncovered: [kb/Index.v2] → BLOQUEIA
```

Não há atalho (o veredito adversarial de 2026-07-16 mediu todos: `abort`/flag/delete bloqueiam
igual; rename de path/ServiceProvider passam mas são *laundering*, proibidos por `proibicoes §5`).

**Ordem obrigatória:**

1. **Contratar a tela** no visreg (entry + baseline gerada no runner canônico via `visreg:update`) —
   exige **[W] no gate F1.5**. Agora isso **faz sentido**: a tela vai ficar no ar. (Enquanto a
   decisão era "freezer", contratar era absurdo — baseline de tela que sairia do ar.)
2. **Controller `indexV2`** + revogar UC-KBV2-06 **no mesmo commit** (ele asserta `missing('nodes')`
   — deixaria o CI vermelho *por ter funcionado*).
3. **Toasts** ([#4365](https://github.com/wagnerra23/oimpresso.com/pull/4365)) — com a tela viva, os 4 `toast.success` mentirosos deixam de ser inofensivos.
4. **Cores (D4)** — PR próprio; a baseline é re-gerada com aprovação [W].

## 8. Como se prova que está pronto (contrato executável)

Estes são os testes que nascem deste charter — sem eles, `status` não vira `live`:

```php
it('serve documentos REAIS do business (nunca MOCK_NODES quando há dado)')
it('isola por business_id — biz=1 não vê documento de biz=99')   // Tier 0
it('categoria = type do documento, com contagem correta')
it('não escreve no banco ao abrir (GET é leitura)')
it('não dispara Job/IA ao abrir')
it('nenhuma ação afirma sucesso sem persistir')                   // UC-KBV2-10
it('abre em 1280px sem scroll horizontal')                        // visual/manual
```

> **Atenção — dívida que precisa morrer junto:** hoje existe um teste **required** afirmando que a
> tela **não recebe dados** (`missing('nodes')`, UC-KBV2-06 da v1.0). Ele foi honesto na era-mock,
> mas hoje **proíbe a promoção**: ligar o Controller deixaria o CI vermelho *por ter funcionado*.
> Ele é revogado no mesmo PR que entrega o Controller — não antes, não depois.

## 9. Comparáveis canônicos

- **Notion** (tri-pane + leitor) — referência de layout
- **Obsidian** (cross-link, grafo) — referência de navegação entre documentos
- **Linear** (⌘K, densidade) — referência de atalhos
- Excluídos: Confluence (peso enterprise), Wiki.js (sem paleta), Outline (sem grafo)

## 10. Refs

- Blueprint Cowork: `prototipo-ui/cowork/kb-page.jsx`
- Casos (contrato executável): [`Index.v2.casos.md`](Index.v2.casos.md)
- V3 atual (docs canônicos, dado real): [`Index.charter.md`](Index.charter.md)
- Bridge que popula o acervo: `Modules/KB/Jobs/KbBridgeFromMcpJob.php` (cron 15min, `app/Console/Kernel.php`)
- [ADR 0110 — Cockpit V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md) · [ADR 0114 — gate visual](../../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) · [ADR 0093 — Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-16 | Wave J | Charter draft v1.0 — port Cowork, tela **mock-first** (Goal 7 = "fallback MOCK_NODES"). Nunca saiu de draft; gate visual nunca fechou. |
| 2026-07-17 | [CC] | **v2.0** — [W]: *"quero os dados, mas com o design do KB"*. Reescrito pro acervo **real** (o bridge já popula `kb_nodes` em prod). Categorias = os 8 `type` do dado (mata o classificador-por-equipamento da v1.0). Persona "operadora de gráfica" removida (não existe no cliente). Anti-hook novo: ação não afirma o que não fez. §7 lista as 4 decisões [W] que bloqueiam `live`. **Aguarda [W]** — nenhum código escrito até D1 ser respondida. |
