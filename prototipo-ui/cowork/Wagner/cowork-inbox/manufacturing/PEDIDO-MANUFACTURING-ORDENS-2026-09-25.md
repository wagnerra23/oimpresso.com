# PEDIDO — Fabricação · Ordens de produção (2026-09-25)

> Autor [CC] · base `wagnerra23/oimpresso.com@main` 2c115a5ca250 (lido 2026-09-25 18:46 UTC) · playbook: `playbook/00-INDICE.md`.

## Por quê
[W] pediu "copiar a Fabricação do main" e apontou que "em produção tem diferenças como KPIs". Li `resources/js/Pages/Manufacturing/Index.tsx` e `Recipes.tsx` inteiros. Receitas já era igual. Ordens de produção estava **atrás do vivo** — o protótipo foi alinhado (🔵 puxe o vivo). **O build desce no pacote do Cowork** (`cowork-bundle.yml` → `receber-handoff`); este pedido **não** pede PR do build.

## Diferenças fechadas nesta rodada (protótipo → igual ao vivo)
| # | o quê | vivo (`Index.tsx`) | protótipo antes |
|---|---|---|---|
| 1 | KPIs | Total · Finalizadas (filtra) · Pendentes · Valor total, com ícone | não tinha |
| 2 | Título / subtítulo | "Produção" · "N receitas · M ordens de produção" (sem "custo recalculado") | "Manufacturing" + custo recalculado |
| 3 | Limpar | botão "Limpar" com filtro ativo | não tinha |
| 4 | Vazio | "Nenhuma produção no filtro" / "Sem produções cadastradas" + "Limpar filtros" | "Nenhuma produção no período" |
| 5 | Situação | alinhada à esquerda | à direita |
| 6 | Linha | não abre detalhe | abria drawer |
| 7 | Colunas | sem ordenação | ordenáveis |
| 8 | Paginação | lista inteira | 10 por página |
| 9 | Datas | vazias = todas | agosto preenchido |

**Não fechadas:** "Nova produção" (vivo → Blade legado `/manufacturing/production/create`, que o protótipo não tem — segue abrindo o form) · data aplica no blur + botão lupa (protótipo aplica na hora; manter é decisão [W]).


## O que sobra para o Code (4 threads)
1. **Contrato `manufacturing-index`** — só existe `manufacturing-recipes.contract.json`. `Index.tsx` não tem nenhum `data-contract`. Seções/copy literais (tirados do `Index.tsx`, não inventados):
   - `cabecalho`: "Produção" · "Nova produção"
   - `abas`: "Receitas" · "Insumos" · "Ordens de produção" · "Relatório" · "Configurações"
   - `kpis`: "Total" · "Finalizadas" · "Pendentes" · "Valor total"
   - `filtros`: "Local" · "De" · "Até" · "Só finalizadas" · "Todos os locais"
   - `lista`: "Data" · "Referência" · "Local" · "Produto" · "Qtd" · "Custo total" · "Custo unit." · "Situação" · "Nenhuma produção no filtro" · "Sem produções cadastradas"
   Ordem `cabecalho → abas → kpis → filtros → lista`. Forma: igual a `manufacturing-recipes.contract.json`. No `.tsx` só entram atributos `data-contract`.
2. **Charter aponta a fonte certa** — `Index.charter.md` declara `related_prototype: prototipo-ui/cowork/Felipe/manufacturing-producao.jsx`, mas o host `cowork/Wagner/oimpresso.com.html` carrega `cowork/Wagner/manufacturing-producao.jsx` (outro blob; das 7 peças só `manufacturing-data.jsx` coincide entre os donos). **D-MFG-FONTE respondida por [W] em 2026-09-25: Wagner.** Os **5** charters do módulo apontam hoje `cowork/Felipe/` (lido 2026-09-25): `Index`→`manufacturing-producao.jsx` · `Recipes`→`manufacturing-page.jsx` · `Insumos`→`manufacturing-insumos.jsx` · `Report`→`manufacturing-producao.jsx` · `Settings`→`manufacturing-producao.jsx`. Trocar os 5 para `prototipo-ui/cowork/Wagner/<mesmo arquivo>` no mesmo PR.
3. **Aposentar `cowork/Felipe/manufacturing-*`** (7 arquivos) — [W]: "vai ser usado só esse agora". **Antes de apagar:** diff Felipe × Wagner e listar no `_saida-03.md` o que existe **só no Felipe**. `cowork/Felipe/auditoria-aderencia-fabricacao-v2.md` mede a raiz do Felipe maior que a do Wagner em 5 de 6 peças (ex. `-producao.jsx` 22.449 × 20.804 ch) e cataloga achados de a11y (B-01 linha sem teclado, C-01 abas sem seta) — se algum desses já estiver resolvido só lá, **vira pedido de volta pro [CC]** portar para o Wagner, não perda silenciosa. `handoff_fabricacao/` e os `.md` do Felipe ficam (histórico).

4. **Filtro De/Até aplica na hora** (D-MFG-DATA, [CC] escolheu por delegação de [W]). Hoje `Index.tsx` aplica no `onBlur` + botão lupa "Aplicar intervalo de datas"; Local e "Só finalizadas" já aplicam no change — a data é a única que pede 2 gestos. Fazer:
   - `onChange` de cada campo guarda o valor **e** chama `applyFilter` quando o intervalo fica válido: os dois vazios (limpa) **ou** os dois no formato `aaaa-mm-dd` com ano ≥ 2000 (o `<input type="date">` emite `0002-…` enquanto o ano é digitado — não pode disparar request);
   - só um preenchido = não aplica (mesma regra do `applyDateRange` atual);
   - remover o `onBlur` e o `<Button>` da lupa (e o import `Search` se ficar sem uso);
   - manter o partial reload `only:[productions,summary,filters]` e os rótulos De/Até;
   - acrescentar ao `Index.casos.md` 1 caso: "o intervalo de datas aplica ao escolher, sem botão".
   O protótipo **já** faz assim — não muda.

## Não fazer
- Não mexer em lógica/estilo de `Index.tsx` nem em `Recipes.tsx`.
- Não "corrigir" o subtítulo para incluir "custo recalculado" (charter §Forma: seria afirmação falsa).
- Não tocar `cowork/Felipe/**` nem `cowork/Wagner/**` (o build chega pelo pacote).

## Decisões [W]
- ~~**D-MFG-FONTE**~~ — **respondida 2026-09-25: Wagner, fonte única.**
- ~~**D-MFG-DATA**~~ — **respondida 2026-09-25: aplica na hora** (item 4).

## Fora do escopo (declarado)
Abas Insumos · Relatório · Configurações não comparadas. `MfgProducaoDrawer` ficou sem uso no protótipo. Sem alvo de runtime (`governance/design/targets/` não tem `manufacturing--*`) — nenhuma thread de pixel.
