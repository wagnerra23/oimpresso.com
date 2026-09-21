# LAUDO de conferência — Consulta de Produtos

> ⚠ **AUDITORIA DESATUALIZADA — 2026-08-17.** Este documento mede a tela **anterior** às 27
> ondas de refinamento (agosto/2026). Achados sobre avatar por linha, KPI "Ativos", chips de
> filtro, coluna "Disponibilidade", preço "a partir de", popovers fixáveis e cores cruas
> referem-se a elementos **removidos ou substituídos**. Precisa ser re-rodado antes de usar como
> base de decisão. Ver `README.md` §15 pendência 1.


**Data:** 2026-08-19 · **Avaliador:** design (sessão de construção) · **Versão da tela:** pós-auditoria de estados de TabBar/filtros

## Contexto

| Item | Valor |
|---|---|
| Tela | Consulta de Produtos (`/products/unificado`) |
| Arquivo | `Consulta de Produtos.dc.html` |
| Como rodar | abrir o arquivo no navegador, na raiz do projeto |
| Especificação | `contexto/SPEC-consulta-produtos-v1.0.md` |
| Telas irmãs | Categorias · Insumos · BOM · Tabelas de preço · Histórico de uso |
| Design system | Office Impresso — Atual (`d7f88676-…`) |
| Plataforma alvo | cockpit desktop, largura útil ≥ 1000px |
| Tarefas conferidas | localizar item por busca; recortar por aba e KPI; filtrar e limpar; ordenar; consultar faixas de preço; consultar estoque por local; ler observação; abrir painel; paginar |

## 1 · Veredito

**APROVADO COM RESSALVAS.** Os nove caminhos conferidos completam. As ressalvas são de sistema
(cores fora de token, três exceções ao DS) e de cobertura (estados de carga/erro ausentes,
contraste não medido), não de funcionamento.

## 2 · Placar

| Eixo | Resultado | Bloqueadores |
|---|---|---|
| Funcionalidade | 9 de 9 tarefas completas · estados de overlay, flip e paginação verificados no DOM | nenhum |
| Acessibilidade | gatilhos como `<button aria-haspopup="dialog">`, Esc e clique-fora funcionando · contraste **não medido** · alvo de linha 28px (< 44px) | contraste não medido |
| Harmonia | 5 cores cruas · 1 família tipográfica · raios 0/4/6/8 · 1 sombra (`--shadow-pop`) · espaçamentos na grade 4 | 5 cores cruas |

## 3 · Achados

```
[ALTA] Cinco cores cruas substituem token e bloqueiam o tema escuro
Eixo: harmonia / acessibilidade
Onde: aba ativa rgb(231,248,253) · hover de aba rgb(241,245,249) · badge de contagem
      rgb(46,52,55) · cabeçalho da tabela e rodapé rgb(250,249,248) · linha selecionada
      rgb(248,247,252)
Medido: 5 literais fora de token na tela; nenhum tem par declarado para tema escuro.
Por que importa: no tema escuro essas superfícies claras invertem o contraste do texto que
      carregam; e a regra binária AP1 do projeto é "zero cor crua".
Correção sugerida: tokenizar em camada 1 (par claro/escuro) e referenciar por var(--*).
      Registrado na ADR 0401 — foram pedidas explicitamente pelo produto, copiadas da
      tela em produção.
```

```
[ALTA] Contraste não medido nas superfícies novas
Eixo: acessibilidade
Onde: badge de contagem (texto oklch(0.72 0.01 240) sobre rgb(46,52,55)); "A PARTIR DE"
      (--text-mute 10px sobre --surface); "N locais" (--text-mute 11px)
Medido: não medido nesta rodada.
Por que importa: --text-mute em corpo pequeno já reprovou AA em outra tela do produto
      (ADR 0322); é justamente o texto que qualifica número de dinheiro e de saldo.
Correção sugerida: medir par-a-par nos dois temas; se reprovar, trocar --text-mute por
      --text-dim (troca de token, não de cor nova).
```

```
[MÉDIA] Três exceções ao DS sem cobertura de teste
Eixo: harmonia / consistência
Onde: abas locais (em vez de TabBar), menu local (em vez de DropdownMenu), rodapé de
      paginação próprio (em vez de Pagination)
Medido: 3 exceções a AP2; o menu local perdeu Esc e clique-fora na primeira versão e
      precisou de correção — evidência do custo de reimplementar componente do DS.
Por que importa: cada exceção é comportamento que o DS já garantia e agora é
      responsabilidade da tela.
Correção sugerida: ADR 0402 propõe levar primeira/última e indicador "N / M" ao
      Pagination do DS; resolvido isso, a exceção nº2 morre.
```

```
[MÉDIA] Ausência de estados de carga, erro e sem permissão
Eixo: funcionalidade
Onde: a tela só tem o vazio de recorte ("Nenhum item neste recorte")
Medido: 0 dos 3 estados obrigatórios implementados (Skeleton, EmptyState variantes
      error/no-perm/offline).
Por que importa: com paginação server-side, cada troca de página é uma espera; sem
      esqueleto a tela parece travada.
Correção sugerida: Skeleton do DS em row×N durante a carga; EmptyState variante error com
      ação de repetir.
```

```
[BAIXA] Alvo de clique das ações abaixo de 44px
Eixo: acessibilidade
Onde: célula de ações (★ e ⋯), 28×28px medidos, célula de 72px
Medido: 28×28 (aprovado como decisão de densidade desktop).
Por que importa: inviabiliza uso por toque em tablet de balcão.
Correção sugerida: se o tablet entrar no escopo, promover a 40×40 e aumentar a coluna.
```

```
[BAIXA] Ações da linha sem comportamento
Eixo: funcionalidade / affordance
Onde: ★ (favoritar) e ⋯ (mais ações) apenas param a propagação do clique
Medido: CU-PROD-21 pendente.
Por que importa: affordance que não cumpre é pior que ausência.
Correção sugerida: ligar ao DropdownMenu com as ações reais, ou remover até existirem.
```

## 4 · Tabela de contraste

**Não medida nesta rodada.** Pares que precisam de medição, em ordem de risco: badge de contagem
inativo · "A PARTIR DE" (10px) · "N locais" (11px) · resumo de variações (11px `--text-mute`) ·
margem sob o piso em `--color-destructive` sobre a linha de alerta · rótulos do cabeçalho da
tabela (10,5px `--text-dim` sobre `rgb(250,249,248)`).

*Armadilha conhecida:* os componentes do DS têm `transition` de cor; amostrar logo após trocar o
tema lê a cor no meio da transição. Desligue transições antes de medir.

## 5 · O que foi verificado na funcionalidade

- Busca com atalho `/`, limpar busca, chips de filtro e "Limpar filtros" restaurando a lista.
- Recorte por aba e por KPI, com volta à página 1.
- Ordenação nas sete colunas, indicador correto por estado.
- Popover de preço: hover abre, clique fixa, Esc e clique-fora fecham, flip para cima quando não
  cabe (medido: `bottom` do diálogo dentro da viewport), reposicionamento na rolagem.
- Popover de estoque por local com alerta de zerado; gatilho acessível por teclado.
- Popover de observação com prévia, texto completo e ação para o painel.
- Painel lateral com as cinco seções e o item correto.
- Paginação: intervalo, indicador, desabilitados na primeira/última, troca de itens por página.
- Recorte por perfil: colunas Custo e Margem ausentes do DOM no perfil restrito.

## 6 · Não verificado

Contraste par-a-par · leitor de tela · zoom 200% · Lighthouse/axe · tema escuro · larguras abaixo
de 1000px · volume real (13 mil linhas) · impressão · navegação por teclado dentro dos popovers ·
comportamento com dado ausente (produto sem categoria, sem unidade, sem preço).

## 7 · Harmonia — números brutos

| Métrica | Valor |
|---|---|
| Cores cruas (hex/rgb/oklch literal) | 5 |
| Famílias tipográficas | 1 (IBM Plex, sans + mono) |
| Tamanhos de fonte distintos | 10 (10 · 10,5 · 11 · 11,5 · 12 · 12,5 · 13 · 13,5 · 16 · 22) |
| Raios distintos | 4 (0 · 4 · 6 · 8) |
| Sombras | 1 (`--shadow-pop`) |
| Valores fora da grade 4 | 2 (altura 38 da busca, 30 do avatar antes da troca pelo DS: hoje 32) |
