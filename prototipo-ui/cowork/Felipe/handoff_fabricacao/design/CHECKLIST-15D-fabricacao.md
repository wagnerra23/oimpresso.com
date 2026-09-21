# CHECKLIST 15D — Fabricação (Manufacturing)

**Data:** 2026-09-01 · **Avaliador:** Claude (sessão de handoff) · **Viewport:** 914×540, tema claro
**Escopo:** as 5 abas + editor + formulário de ordem + 3 overlays. A folha PT-07 **não** entra no
score (não foi renderizada — ver LAUDO §6).

> Medição datada. Envelhece. O mesmo desenho tem score diferente para persona diferente — e a
> persona está declarada no §2.

---

## VEREDITO

| | |
|---|---|
| Falhas Nielsen 10 | **1 falha + 1 parcial** de 10 |
| Persona | **Larissa · balcão/orçamento** (forma preço, responde cliente, muitas vezes ao dia) |
| Score ponderado | **79 / 100** |
| Faixa | `60–80` = **aceitável com pendências** |
| Regras binárias (Anexo A) | 6 de 7 limpas · **AP1 com 3 exceções declaradas** |

---

## 1 · SANITY CHECK NIELSEN 10

| # | Heurística | Falha? | Evidência |
|---|---|---|---|
| 1 | Visibilidade do estado do sistema | não | toast de 2,6s em salvar/excluir/configurar; `fix` marca custo congelado; contador por aba; rodapé com total do período |
| 2 | Correspondência com o mundo real | não | vocabulário de gráfica: substrato, tinta, acabamento, plotagem, desperdício, m², m linear, via de produção |
| 3 | Controle e liberdade do usuário | **sim** | "Atualizar preço de venda do produto" reprecifica N receitas **sem desfazer** e sem confirmação — é a única escrita em massa da tela. `esc` e scrim fecham overlay; editor cancela sem salvar (esses passam) |
| 4 | Consistência e padrões | não | tabela, drawer, modal, campo e pílula seguem o mesmo padrão nas 5 abas; abas sublinhadas (canon do DS), nunca pill |
| 5 | Prevenção de erro | não | nome de receita duplicado avisa antes de criar; salvar bloqueado sem ingrediente; estoque insuficiente avisa antes de finalizar; botão `Atualizar` só habilita com mudança |
| 6 | Reconhecer em vez de lembrar | não | busca de insumo mostra nome + SKU + estoque + custo; a dica do desperdício mostra o rendimento calculado ao lado do campo |
| 7 | Flexibilidade e eficiência | não | `/` foca busca, `esc` fecha, KPI filtra em 1 clique, clone de receita ao criar, impressão em lote |
| 8 | Estética e design minimalista | não | 4 KPIs, 8 colunas, nenhum enfeite; a folha impressa é o único lugar com ornamento (e ele é funcional: marcas de corte, mira, tira de prova) |
| 9 | Ajudar a reconhecer/diagnosticar/recuperar erro | **parcial** | erros de validação são claros e apontam a saída (`Ver em Compras`), mas o override de consumo é **descartado em silêncio** ao trocar receita/quantidade (regra §6.1 do README) — a regra é certa, o aviso não existe |
| 10 | Ajuda e documentação | não | a tela explica a própria regra onde ela acontece: nota do custo ao vivo, nota do custo congelado, nota da conversão para unidade base, nota da permissão de leitura |

Duas falhas em dez: **não bloqueia** (o limite é 2). As duas viram requisito no README §18 e no
LAUDO (achado BAIXA "markup placeholder" cobre a primeira; a segunda é o item de aviso do
override).

---

## 2 · PERSONA E PESOS

**Larissa · balconista/orçamento.** Consulta custo e margem antes de fechar preço com cliente ao
telefone. Trabalha em monitor de loja, sob luz alta, com teclado e mouse. Não é ela quem monta
receita (é o Wagner) nem quem produz (é a Eliana), mas é ela que abre a tela dez vezes por dia.

Pesam **3** para ela: velocidade até a tarefa, descoberta, recuperação de erro, carga cognitiva,
affordance, microcopy, i18n, acessibilidade (luz de loja = contraste), hierarquia da informação.
Pesa **1**: mobile fit (ela não usa celular para isso) e brand confidence (o cliente não vê esta
tela — vê a folha impressa e o orçamento).

---

## 3 · AS 15 DIMENSÕES

| # | Dimensão | Nota | Peso | Contribuição | Por quê — MEDIDO |
|---|---|---:|---:|---:|---|
| 1 | Density | 85 | 2 | 170 | Linha de 44px, `thead` de 34px, 10 linhas por página, 8 colunas em 960px. Densidade de ERP sem aperto; sobra respiro nos KPIs (padding 11/13px) |
| 2 | Discoverability | 78 | 3 | 234 | 5 abas rotuladas com contador; 2 dos 4 KPIs são filtro e mostram anel quando ativos. Perde ponto: na aba Insumos, que a linha é clicável só se sabe pela frase ao lado da busca ("clique num insumo para ver quem sobe de custo") — não há affordance visual até o hover |
| 3 | Speed-to-task | 88 | 3 | 264 | "Quanto custa produzir X?" = 1 tecla (`/`) + digitar + 1 clique = drawer com custo unitário e margem. "Quem sobe se a lona subir 10%?" = 2 cliques + 1 arraste |
| 4 | Error recovery | 62 | 3 | 186 | Exclusão pede confirmação e explica o que se perde. **Mas:** escrita em massa de preço sem desfazer (Nielsen 3) e override de consumo descartado sem aviso (Nielsen 9). Nenhum "desfazer" na tela |
| 5 | Cognitive load | 82 | 3 | 246 | Um assunto por aba; o quadro de custo sempre com a mesma ordem (ingredientes → extra → total → unitário → venda → margem). Carga real está no editor, que mostra 6 colunas por linha de ingrediente — inerente ao BOM |
| 6 | Aesthetic-usability | 80 | 2 | 160 | Coerente com as telas ouro do produto. Desconto pelo achado MÉDIA do LAUDO: pílula, chip e contador de aba vazam com rótulo de duas palavras a 914px |
| 7 | Affordance | 74 | 3 | 222 | Linha da tabela é clicável só por `cursor: pointer` + `:hover` de fundo; checkbox para de propagar (certo); insumo sem receita **não** é clicável e mostra "sem receita" (certo). Falta: nada indica que o número de consumo no editor é editável antes do foco |
| 8 | Brand confidence | 88 | 1 | 88 | A folha PT-07 é assinatura de gráfica — marcas de corte, mira de registro, cotas com linha de medida, tira CMYK. Nenhuma outra tela do produto imprime assim |
| 9 | Mobile fit | 40 | 1 | 40 | Declaradamente desktop. Único breakpoint (1080px) colapsa o editor; tabelas rolam na horizontal (mínimo 900–1100px). Campo numérico de 26px é pequeno para dedo |
| 10 | A11y WCAG | 55 | 3 | 165 | 7 de 10 pares aprovam; `--text-mute` reprova nos dois temas (3,24 / 2,92 / 3,18 / 3,94) e `--accent` como texto reprova no escuro (2,64). Foco visível universal existe (camada 2); focus trap não |
| 11 | i18n PT-BR | 90 | 3 | 270 | Tudo em PT-BR, dinheiro `R$ 1.288,44`, data `dd/mm/aaaa`, decimal com vírgula, `tabular-nums`. Desconto: usa "Receita"/"Ingredientes" (herança do UltimatePOS) onde a gráfica diz "ficha técnica"/"insumos" — a aba Insumos já fala certo, a de Receitas não |
| 12 | Performance perceived | 85 | 2 | 170 | Todo derivado em `useMemo`; nenhum estado duplicado; nenhum efeito de rede. Sem skeleton — no alvo, lista de milhares de receitas via paginador de servidor precisará de um (`Components/ui/skeleton.tsx`) |
| 13 | Information hierarchy | 84 | 3 | 252 | KPI → filtro → tabela → detalhe, na ordem em que se decide. O número principal (custo unitário) é o maior e o único em acento nos três quadros de custo |
| 14 | Microcopy | 90 | 3 | 270 | Cada regra é explicada onde acontece, em frase de negócio: "a receita não guarda valor congelado", "rascunho · sem movimento de estoque", "finalizar vai deixar saldo negativo", "não é documento fiscal". Nenhum texto de sistema vazando |
| 15 | Internal consistency | 86 | 2 | 172 | Um vocabulário de componentes reusado nas 5 abas. Inconsistência medida: a lista de receitas pagina no cliente e a de ordens também, mas os filtros de período existem só nas abas de ordem/relatório — coerente com o domínio, não com o padrão de tela |

```
Σ contribuições = 2 909 · máximo = 37 × 100 = 3 700
score = 2909 / 3700 × 100 = 78,6  →  79 / 100
```

---

## 4 · INSTRUMENTOS

### 4.1 · Mobile fit

| Item | Medido |
|---|---|
| Largura mínima real das tabelas | 900px (insumos) · 940px (relatório) · 960px (receitas) · 1100px (ordens) |
| Breakpoints declarados | 1 (`max-width: 1080px`, colapsa o editor) |
| Alvo de toque < 44px | `.mfg-inp.num` (26px) · `.mfg-inp.sel` (26px) · `.mfg-mini` (22px) · `.mfg-pag button` (26px). Em `pointer: coarse` a camada 2 eleva botão/select/checkbox a 44px; `input[type=number]` **não** está na lista |
| Veredito | fora de escopo declarado. Não é dívida escondida: está no README §3.2 |

### 4.2 · Acessibilidade

| Item | Medido |
|---|---|
| Pares de contraste testados | 12 (6 tokens × 2 fundos, nos 2 temas) |
| Aprovados | 7 · **Reprovados: 3** (`--text-mute`, `--accent` texto no escuro, `--warn` texto no claro) |
| Foco visível | universal via `otimiza-ondas.css` L14: `outline: 2px solid var(--accent); outline-offset: 1px` + halo `--accent-soft` |
| `role` / `aria-label` em overlay | presentes em drawer e modal (4 ocorrências) |
| `aria-label` em checkbox de linha | presente (`Selecionar <nome>`, `Selecionar todas`) |
| Focus trap | **ausente** |
| Atalhos | `/` (busca) · `esc` (fecha) — sem conflito com campo de texto |
| Lighthouse / axe / leitor de tela / zoom 200% | **não rodados** |

### 4.3 · Estados obrigatórios

| Estado | Existe? | Onde |
|---|---|---|
| Vazio (primeira vez) | — | não aplicável ao protótipo (a cena sempre tem dado). **No alvo é obrigatório**: "Sem receitas cadastradas" com o caminho para criar |
| Vazio por filtro | ✅ | `Nenhuma receita encontrada` + o que ajustar; `Nenhuma produção no período`; `Sem produção no período` |
| Carregando | ❌ | não existe. No alvo: `Components/ui/skeleton.tsx` |
| Erro de sistema | ❌ | não existe. No alvo: `Components/ui/alert.tsx` com o que fazer |
| Sem permissão | ✅ | aba de produção não renderiza; botões não aparecem; editor avisa "apenas leitura" |
| Sucesso | ✅ | toast com o efeito, não com "ok": "Receita salva · custo recalculado", "Produção finalizada · estoque movimentado" |
| Destrutivo confirmado | ✅ | modal `.sm` com o que se perde e o que sobrevive |
| Subconjunto declarado | ✅ | quantidade em sub-unidade com rótulo; `fix` no custo congelado; "8 receitas · 6 ordens" no cabeçalho |

---

## ANEXO A — REGRAS BINÁRIAS

| Código | Regra | | Evidência |
|---|---|---|---|
| **AP1** | Zero cor crua (hex/rgb fora de token) | **✗ com 3 exceções declaradas** | (1) 4 × `rgba(0,0,0,.28–.42)` em scrim e sombra elevada — o DS não tem token para nenhum dos dois; (2) 11 cinzas no bloco `@media print` — papel não tem tema, ADR 0413; (3) 4 tintas de processo na tira de prova — valor de tinta, não cor de tema. **Zero cor crua na tela fora dessas** |
| **AP2** | Não reinventar componente do DS | ✓ | esta família não consome componente compilado do DS (só tokens) e não recria nenhum. No alvo, o diff do README §15.3 amarra cada peça a um componente existente; o único componente local que hoje existe no repo (`StatusPill`, `Index.tsx` L292-311) o pacote manda **apagar** |
| **AP4** | Ícones de uma fonte só | ✓ com contorno declarado | 4 ícones usados (`plus`, `search`, `printer`, `pencil`), todos do `icons.jsx` — espelho de 22 glifos do protótipo. O alvo usa `lucide-react` inteiro; o espelho **não** é portado |
| **AP5** | Sem gradiente decorativo | ✓ | zero `gradient` no arquivo de módulo |
| **AP6** | Sem emoji | ✓ | zero emoji na UI. Os únicos glifos textuais são setas de ordenação (`↑ ↓ ⇵`), paginação (`‹ ›`) e o `✕` de fechar |
| **AP7** | Status badge = dot + texto, nunca fill sólido | ✓ | `.mfg-pill` = texto no tom + borda 30% + fundo 8% do mesmo tom. Nenhum fundo sólido. O `StatusPill` do alvo também respeita (dot 1,5px + texto) |
| — | Espaçamento múltiplo de 4 | **✗ declarado** | 12 de 19 valores fora da grade de 4 (ímpares 1/3/5/7/9/11/13px e pares 2/6/10/14/18px). Herança dos literais do protótipo; a reimplementação no Tailwind resolve por construção |
| — | Tipografia do DS | **✗ declarado** | 10 tamanhos literais em vez da rampa `--fs-*`. Mesma herança, mesma resolução |
| — | Token novo = ADR antes | ✓ | **nenhum token novo criado** |

---

## PENDÊNCIAS, EM ORDEM DE CUSTO

| # | Pendência | Custo | Onde |
|---|---|---|---|
| 1 | `white-space: nowrap` em `.mfg-pill`, `.mfg-chip`, `.mfg-tab-n` + coluna de peso 160→176px | 3 linhas de CSS | LAUDO achado MÉDIA |
| 2 | Trocar `--text-mute` por `--text-dim` nos 9 seletores de rótulo | 9 linhas de CSS (ou 1 decisão no DS) | ADR 0410 |
| 3 | Avisar quando o override de consumo for descartado | 1 toast | Nielsen 9 |
| 4 | Acento com versão clara no tema escuro | 1 decisão no DS | ADR 0411 |
| 5 | Confirmação (ou desfazer) na escrita em massa de preço | 1 modal + regra de markup | Nielsen 3 + README §18.1 |
| 6 | Focus trap nos overlays | grátis no alvo (`ui/sheet`, `ui/alert-dialog`) | README §18.4 |
| 7 | Estados de carregando e de erro de sistema | 2 componentes do DS | §4.3 |
| 8 | Backend da aba Insumos (`usosDoInsumo` no `RecipeBomService`) | 1 método + teste | README §18.3 |
| 9 | Renderizar e medir a folha PT-07 | 1 conferência | LAUDO §6 |
| 10 | Rampa de tipografia e grade de espaçamento | resolvido pela reimplementação no alvo | Anexo A |

---

## 5 · RODADA DE CORREÇÃO

Nenhuma rodada aplicada nesta entrega — o pacote sai com os achados abertos, de propósito: as duas
correções de acessibilidade são **decisão do DS** (ADR 0410 e 0411), e aplicá-las por dentro da
tela esconderia o defeito de origem. As pendências 1, 3, 5 e 7 são da tela e cabem na primeira onda
da reimplementação.

Ao aplicar, recalcular: subir A11y de 55 para ~85 (pendências 2, 4, 6) e Error recovery de 62 para
~85 (pendências 3, 5) levaria o score a **≈ 87 / 100**.
