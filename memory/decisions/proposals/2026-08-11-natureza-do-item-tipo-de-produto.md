---
title: "Natureza do item (o 'Tipo' do Delphi) — migrar como marcações soltas, como tabela configurável por business, ou não migrar"
status: proposed
date: "2026-08-11"
decisores: [Wagner (aprova), Maiara (levantou), Claude Code (autor)]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0264-governanca-executavel-trio-dominio-e2e
origem: "Maiara 2026-08-11, ao revisar o protótipo do Catálogo Unificado: 'No delphi existem esses tipos de produto. No oimpresso seria correto remover? Como ficaria a migração dos dados nesse caso? Não estou entendendo.' — seguido de: 'Em comunicação visual/oficina/varejo os tipos legados não condizem, isso?'"
---

# Natureza do item — o "Tipo" do Delphi não foi migrado, e isso precisa de decisão

> **Não é bug nem dívida escondida.** É um campo do legado que **nunca teve destino decidido**. A
> pergunta aparece toda vez que alguém desenha o catálogo, é respondida de improviso, e some. Este
> documento existe pra ela ser respondida **uma vez**.

## 1 · O que existe no Delphi

`PRODUTO_TIPO` **não é lista fixa**: é **tabela configurável** — cada empresa cria os seus tipos. E
cada tipo carrega um **pacote de comportamentos**, via marcações:

`TEM_PRODUTO` · `TEM_SERVICO` · `TEM_MATERIAPRIMA` · `TEM_USOECONSUMO` · `PODE_SER_COMPRADO` · `PODE_SER_VENDIDO`

Fonte: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../../requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md)
§"Esclarecido por Wagner" — *"não é enum fixo: é tabela configurável de tipos, cada um com
comportamento próprio"* — e a linha `| Tipo | CODPRODUTO_TIPO + TEM_* | PRODUTO_TIPO |` do inventário
campo-a-campo.

Ou seja: no Delphi, **"Matéria-prima" é um rótulo que a empresa criou**, e atrás dele há um conjunto
de regras (não vendável, controla estoque, entra em composição).

## 2 · O que existe no oimpresso

Os **mesmos comportamentos**, mas soltos, marcados um a um no produto:

| Comportamento | Coluna |
|---|---|
| não pode ser vendido | `products.not_for_selling` (migration `2019_07_22_152649`) |
| controla estoque | `products.enable_stock` (migration `2017_08_08_115903:33`) |

**O que não existe é a etiqueta que agrupa.** Ninguém escreve *"este produto é Matéria-prima"* —
escreve-se *"não é vendável e controla estoque"*.

O conceito não foi removido: foi **decomposto**. Perde-se o nome e a configurabilidade; mantém-se o
comportamento.

### Armadilha de nome (já catalogada)

`products.type` **não** é isso. É **estrutura de variação** (`single` · `variable` · `modifier`), outro
conceito com o mesmo nome. Já está registrado como *falso-crédito* em
[`PARIDADE-charter-vs-legado.md`](../../requisitos/Produto/PARIDADE-charter-vs-legado.md) §"Falso-crédito 1".
Qualquer solução aqui **não pode** usar `products.type`.

## 3 · Como ficaria a migração

Cada `PRODUTO_TIPO` do Delphi vira uma combinação de marcações:

| Tipo no Delphi | Vira no oimpresso |
|---|---|
| Produto | vendável + controla estoque |
| Serviço | vendável + **não** controla estoque |
| Matéria-prima | **não** vendável + controla estoque |
| Uso e consumo | **não** vendável + controla estoque |

**O problema está nas duas últimas linhas:** matéria-prima e uso-e-consumo caem na **mesma**
combinação. Depois de migrado, não há como distingui-las. Se a distinção importa na operação, ela se
perde — silenciosamente, sem erro nenhum aparecer.

## 4 · O argumento que decide: os tipos não atravessam as verticais

Levantado por [M]. O oimpresso é **multi-vertical** por decisão arquitetural ([ADR 0121](../0121-oimpresso-modular-especializado-por-vertical.md)),
com três módulos vivos no repo — `Modules/ComunicacaoVisual`, `Modules/OficinaAuto`, `Modules/Vestuario`.

Os quatro tipos do Delphi vieram do **WR Comercial**, que atendia gráfica. Eles são **nativos da
comunicação visual** — e mal-ajustados no resto:

| Vertical | Produto | Serviço | Matéria-prima | Uso e consumo |
|---|---|---|---|---|
| **Comunicação visual** (gráfica) | banner, adesivo | arte, instalação | lona, tinta, papel — **é a estrutura de custo** | fita, solvente |
| **Oficina auto** | peça (revenda) | **mão de obra — central na OS** | não fabrica → vazio | estopa, desengraxante |
| **Vestuário** (ROTA LIVRE) | a peça | raro (ajuste) | **revenda pura → vazio** | sacola, etiqueta |

> ⚠️ **Isto é análise, não medição.** Não consultei o cadastro real de nenhum business pra contar
> quantos produtos usam cada tipo. Antes de decidir, vale medir — especialmente em ROTA LIVRE
> (`business_id=4`), que é o único vertical em produção.

**A conclusão que essa tabela força:** uma lista **fixa** com os quatro nomes do Delphi seria o pior
dos mundos — impõe vocabulário de gráfica a quem vende roupa, e deixa dois tipos permanentemente
vazios em duas das três verticais. A escolha real é entre **não ter rótulo** e **ter rótulo
configurável por business**.

## 5 · Opções

### A · Só as marcações — não recriar o rótulo

Migra cada tipo pra combinação de `not_for_selling` + `enable_stock`. Nenhum schema novo.

- ✅ Zero custo de implementação; nada de tela de configuração
- ✅ Não impõe vocabulário de uma vertical às outras
- ❌ Perde o nome — o operador não vê mais "Uso e consumo", vê duas caixinhas marcadas
- ❌ **Funde matéria-prima com uso-e-consumo** (§3)
- ❌ Perde a configurabilidade que o cliente já tem hoje no Delphi

Na tela, o filtro deixa de ser "tipo" e vira **filtro por comportamento** ("vendáveis", "insumos").

### B · Tabela configurável por business — fiel ao Delphi

Tabela nova (`product_natures` ou equivalente) com `business_id` + nome + as marcações de
comportamento, e uma coluna no produto apontando pra ela.

- ✅ Fiel ao que o cliente já tem; migração 1:1 sem perda
- ✅ Cada vertical cria o próprio vocabulário — resolve o §4 pela raiz
- ✅ Compatível com [ADR 0093](../0093-multi-tenant-isolation-tier-0.md): `business_id` escopado desde o nascimento
- ❌ Schema novo + tela de configuração + gate de permissão
- ❌ Mais um lugar onde configurar errado quebra o catálogo

Na tela, a aba de tipos é **dinâmica** — a lista varia por business.

### C · Não migrar — decidir que o conceito morre

Registrar que a natureza do item não existe no oimpresso e que o legado, ao migrar, perde o campo.

- ✅ Honesto e barato
- ❌ Só é aceitável se **nenhum** cliente migrado usa o campo de forma relevante — e isso **não foi
  medido**

## 6 · Recomendação (do autor, não decidida)

**B**, se e somente se a medição do §4 mostrar uso real. O argumento é o multi-vertical: um sistema que
se declara especializado por vertical não pode ter uma lista fixa de tipos nascida de uma vertical só.

Se a medição mostrar que quase todo mundo usa um tipo só, **A** — e o rótulo morre sem cerimônia.

**Antes de decidir, medir:** distribuição de `PRODUTO_TIPO` por cliente no Firebird do legado, e
quantos produtos por business no oimpresso hoje têm `not_for_selling=1`.

## 7 · O que fica travado enquanto isso

O protótipo do Catálogo Unificado tenta desenhar **abas por tipo de produto** (`Produto` · `Serviço` ·
`Matéria-prima` · `Uso e consumo` · `Composição` · `Variação`). Hoje elas **não têm de onde tirar o
dado** — e o desenho muda conforme a opção:

- Opção **A** → não é aba de "tipo": é filtro por comportamento, lista fixa e curta
- Opção **B** → aba **dinâmica**, montada do cadastro de cada business
- Opção **C** → a aba não existe

São três telas diferentes. Desenhar antes da decisão é retrabalho garantido — e foi o que aconteceu
três vezes na sessão de 2026-08-11.

> **Nota lateral (não é objeto desta proposta):** `products.type` aceita `combo` no banco desde a
> migration `2019_07_15_165136:21`, mas [`memory/dominio/estoque.md:45`](../../dominio/estoque.md)
> declara `["single","variable","modifier"]` e diz *"combo não existe no enum atual"*. O gate
> `dominio:check` não acusa porque o regex de `scripts/domain-dict-guard.mjs:167` casa só
> `ALTER … MODIFY <col> ENUM(…)` com identificador **sem crase** — e a migration de 2019 usa `CHANGE`
> com crase. Registrado aqui por ser o mesmo campo; merece correção própria.
