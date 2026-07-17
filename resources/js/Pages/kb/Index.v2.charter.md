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

> **A US que esta tela atende — [US-KB-001](../../../memory/requisitos/KB/SPEC.md):** *"Como Wagner
> governance, **quero ver** os ADRs do projeto como nós navegáveis, **para** consultar dependências
> sem grep cego no filesystem."* O **backend dela já está ✅ LIVE** (o `KbBridgeFromMcpJob` popula
> `kb_nodes` em prod há meses) — **o que falta é o "ver"**. Esta tela É o ver. Sem ela, a US está
> entregue pela metade: o dado existe, ninguém enxerga.
>
> A tela também **encosta** em US-KB-004 (trilhas) e US-KB-005 (troubleshooter) — os diálogos existem
> na header —, mas o contrato aqui é a **001**; as outras têm charter próprio quando saírem do mock.

## 2. O que a tela mostra (isto é o contrato)

Os **documentos canônicos que já existem** no repositório e que um robô (`KbBridgeFromMcpJob`) copia
pra dentro do banco **a cada 15 minutos, hoje, em produção**. São os documentos de verdade da empresa:
decisões de arquitetura, registros de sessão, contratos de tela, receitas operacionais, resumos de
módulo, especificações.

**Não mostra SOP inventado.** O corpus de gráfica (Roland VS-540 / HP Latex) que a tela exibe hoje é
ficção e **sai** — a persona "operadora de gráfica" não existe no cliente real (o piloto biz=4 é loja
de **vestuário**).

## 3. As categorias (painel esquerdo)

**São os 8 tipos de documento — não precisam ser inventadas, já vêm no dado:**

| Categoria | O que é |
|---|---|
| **Decisões (ADR)** | por que o sistema é como é |
| **Sessões** | o que foi feito, quando, por quem |
| **Contratos de tela** | o que cada tela promete fazer |
| **Receitas (Runbook)** | como executar uma operação |
| **Resumos (Briefing)** | estado consolidado de um módulo |
| **Especificações** | o que foi pedido |
| **Comparativos** | nós vs mercado |
| **Referências** | fatos e apontadores |

> **Invariante:** categoria = `kb_nodes.type`. Nada de classificador "inteligente" adivinhando —
> se o dado não traz o tipo, a categoria não existe. (A v1.0 previa classificar por *equipamento*,
> ex. `auto_match: {field:'equip', value:'Roland VS-540'}` — isso **morre** aqui: classificar um ADR
> de governança por impressora é ficção herdada do corpus falso.)

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

## 7. Decisões que só [W] pode tomar (bloqueiam o `status: live`)

| # | Decisão | Por que é sua |
|---|---|---|
| **D1** | **"Os dados" = os documentos canônicos** (ADR/session/charter/…)? Confirmar. | Se o que você quer são **SOPs operacionais escritos à mão** ("como trocar a tinta"), então **não existe dado nenhum** — alguém teria que escrever. É produto, não código. |
| **D2** | **O `/kb` de hoje continua, ou a V2 toma o lugar?** | O `/kb` atual tem **histórico de versões, soft-delete e filtro de PII** — a V2 **não tem**. Cutover sem isso é perder função. Coexistir é ter duas portas pro mesmo acervo. |
| **D3** | **As 8 categorias acima são as certas?** | É a espinha da navegação. Se você quiser agrupar por módulo (Financeiro/Oficina) em vez de por tipo, muda o desenho. |
| **D4** | **As cores** — a tela tem **68 cores cruas**; trocá-las por tokens é mudança visual (gate ADR 0114). | Design é seu (Tier 0). Pode ser PR separado, depois. |

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
