---
id: requisitos-ponto-aprovacoes-index-gap
tela: Ponto/Aprovacoes/Index (/ponto/aprovacoes)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Aprovacoes/Index.tsx
gerado_em: 2026-09-28
---

# GAP-SPEC — Ponto/Aprovacoes/Index

> **Origem:** thread `16-gap-aprovacoes.md` do playbook do Ponto. Decisões citadas só existem em
> `ATA-DECISOES-2026-09-14.md` (mesma pasta do playbook).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e` (2026-09-24), símbolo `Aprovacoes`
> (`:13-143`). As faixas que a thread cita (`:13-110`) são de 14/09 e **não valem mais**: o build de
> 24/09 já trouxe a faixa de KPI (`:60-62`), a paginação 20/pág (`:33`) e os diálogos de aprovar e
> rejeitar (`:127-139`). Três das cinco "divergências fechadas" da thread foram consertadas no
> protótipo antes desta medição.
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/Aprovacoes/Index.tsx` @ `e4289e688` (592 linhas)
> e `Modules/Ponto/Http/Controllers/AprovacaoController.php`. Toda linha abaixo saiu de `grep -n`.
> **Régua:** o contrato vem do charter (`Aprovacoes/Index.charter.md`, `status: draft`) e do protótipo.
> O `.tsx` só responde se a região existe e onde — nunca define o caso.

| Parte | Estado no vivo | Ação |
|---|---|---|
| KPIs por estado | **Paridade.** Vivo: `KpiGrid cols={6}` com um `KpiCard` por estado que filtra ao clicar (`Index.tsx:262-278`). Protótipo: `Kpi` por estado com `onClick` alternando o filtro (`ponto-telas.jsx:60-62`). Goal do charter (`Index.charter.md:31`). | Nada — paridade de comportamento. A forma (tamanho do tile, ícone) é assunto da thread 15/34, não deste gap. |
| Barra de filtros | **Diverge em um campo.** Vivo: `PageFilters` com Tipo e Prioridade, chips ativos e reset (`Index.tsx:281-320`); o Estado é filtrado só pelos KPIs. Protótipo: 3 selects (Estado, Tipo, Prioridade) + `Limpar` + contador de pendentes no filtro (`ponto-telas.jsx:63-80`). O contador "N pendentes no filtro" não existe no vivo. | Protótipo à frente só no contador. O select de Estado duplicaria o KPI-filtro que o vivo já tem. **Não medido:** se o contador vale a linha; fica para a passada de FORMA (thread 15). |
| Fila de aprovações | **Diverge em colunas.** Vivo: 8 colunas — seleção, Colaborador (nome + `mat.`), Tipo (+ `impacta apuração`), Data, Estado, Prioridade, **Criada** (humanizado), Ações (`Index.tsx:346-366`). Protótipo: 7 colunas, sem Criada, e o Colaborador leva `matrícula · cargo` (`ponto-telas.jsx:82-85`, `:98`). O alerta `impacta_apuracao` existe nos dois (`Index.tsx:404-408` · `ponto-telas.jsx:99`). | Vivo à frente na coluna Criada. O cargo na sub-linha é protótipo à frente e depende de o payload trazer o cargo (não medido no controller). A thread 17 grava `data-contract="aprovacoes-fila-de-aprovacoes"` no card. |
| Ação por linha | **Paridade.** Vivo: `Ver` (link para `/ponto/intercorrencias/{id}`) sempre, e `Aprovar` + `Rejeitar` só em `PENDENTE` (`Index.tsx:430-458`). Protótipo: mesmo trio, mesma condição (`ponto-telas.jsx:103-111`). | Nada. |
| Diálogo de aprovar | **Paridade.** Vivo: `AlertDialog` com alerta de `impacta_apuracao` (`Index.tsx:510-533`). Protótipo: `Modal` do DS com o mesmo alerta (`ponto-telas.jsx:127-132`). | Nada. |
| Diálogo de rejeitar | **Paridade.** Vivo: `Dialog` com `Textarea` 5 a 500 caracteres, contador e validação no submit (`Index.tsx:536-579`, regra em `:193`). Protótipo: `Modal` com `PtTexto` 500, contador e mínimo 5 (`ponto-telas.jsx:133-139`, regra em `:51`). | Nada. O `window.prompt` que a thread acusava já saiu do protótipo. |
| Paginação | **Paridade.** Vivo: 20 por página no servidor (`AprovacaoController.php:71`), navegação com partial reload `only: ['aprovacoes','filtros']` (`Index.tsx:467-490`). Protótipo: `usePagina(lista.length, 20)` (`ponto-telas.jsx:33`). Charter: 20/pág (`Index.charter.md:30`). | Nada. A divergência 15 × 20 da thread foi corrigida no protótipo. |
| Barra de lote | **Protótipo à frente, pendente de decisão.** Vivo: `BulkActionBar` só com `Aprovar selecionadas` e um `confirm()` nativo (`Index.tsx:497-507`, `:216`); a rota de lote é só de aprovação (`Modules/Ponto/Http/routes.php:47`). Protótipo: `Aprovar N` + `Rejeitar N` com motivo único obrigatório + `Limpar seleção` (`ponto-telas.jsx:118-126`). O charter só declara aprovação em lote (`Index.charter.md:34`). | **Decidir [W].** Rejeição em lote é comportamento novo (rota nova + regra de motivo único) e a ata de 14/09 não responde. Pendente, não inventado. O `confirm()` nativo do vivo é defeito independente da decisão: o DS tem diálogo, e a regra R3 da ata já tratou `window.confirm` como "não era pergunta" (D-ESC-DESTROY). |
| Rodapé legal | **Ausente no vivo.** Protótipo: `<Legal />` (`ponto-telas.jsx:140`). No vivo, `grep -n "Legal\|Portaria"` em `Index.tsx` = 0. O Non-Goal de append-only está no charter (`Index.charter.md:43`). | Protótipo à frente. Entra com a passada de FORMA (thread 15); não é comportamento. |
| Estado vazio | **Paridade.** Vivo: `EmptyState` com duas variantes — "Caixa vazia" e "Nenhum resultado" com `Limpar filtros` (`Index.tsx:326-343`). Protótipo: `Vazio` `first` e `filtered` com o mesmo par de títulos (`ponto-telas.jsx:86-88`). | Nada. |

## Filtro default

O vivo abre com `estado = PENDENTE` no servidor (`AprovacaoController.php:26`); o protótipo também
(`ponto-telas.jsx:17`). O charter não declara default. É convergência, não divergência — a pergunta
da thread ("pergunta pro charter") segue aberta como emenda de charter, dona a thread 27.

## Fora deste gap (guarda contra pedido inventado)

- Non-Goals do charter: não cria nem edita intercorrência aqui; não altera marcação (append-only,
  Portaria MTP 671/2021); não edita campo em massa (`Index.charter.md:41-44`).
- Anti-hooks: sem polling, sem mutação em GET (`Index.charter.md:61-62`).
- `Inertia::defer` e o partial reload são hooks do vivo; o protótipo não os modela e não deve.
