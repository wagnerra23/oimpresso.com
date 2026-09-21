# CHECKLIST 15D — Consulta de Produtos

> ⚠ **AUDITORIA DESATUALIZADA — 2026-08-17.** Este documento mede a tela **anterior** às 27
> ondas de refinamento (agosto/2026). Achados sobre avatar por linha, KPI "Ativos", chips de
> filtro, coluna "Disponibilidade", preço "a partir de", popovers fixáveis e cores cruas
> referem-se a elementos **removidos ou substituídos**. Precisa ser re-rodado antes de usar como
> base de decisão. Ver `README.md` §15 pendência 1.


**Data:** 2026-08-19 · **Avaliador:** design (sessão de construção) · **Persona de peso:** Larissa · balcão

## Veredito

| Item | Resultado |
|---|---|
| Falhas Nielsen 10 | 1 (visibilidade do estado do sistema — sem carga/erro) |
| Persona | Larissa · balcão (50–80 consultas/dia) |
| Score ponderado | **79 / 100** — aceitável com pendências |
| Regras binárias | AP1 **reprova** (5 cores cruas) · AP2 3 exceções medidas · AP4–AP7 passam |

## 1 · Sanity check Nielsen 10

| # | Heurística | Falha? | Evidência |
|---|---|---|---|
| 1 | Visibilidade do estado | **sim** | sem Skeleton nem estado de erro; troca de página é silenciosa |
| 2 | Correspondência com o mundo real | não | "Em estoque", "Sob encomenda", "m²", "a partir de" — vocabulário do balcão |
| 3 | Controle e liberdade | não | Esc, clique-fora, limpar filtro, limpar busca, voltar ao catálogo |
| 4 | Consistência e padrões | não | os três popovers têm a mesma mecânica; ícones só do lucide |
| 5 | Prevenção de erro | não | consulta de leitura; nada destrutivo |
| 6 | Reconhecer > lembrar | não | chips mostram o filtro ativo; contadores nas abas |
| 7 | Flexibilidade | não | atalho `/`, hover como atalho e clique como fixação |
| 8 | Estética minimalista | não | três níveis na linha, detalhe sob demanda |
| 9 | Recuperação de erro | n/a | não há erro apresentável ainda (ver #1) |
| 10 | Ajuda | parcial | botão "?" no rodapé sem conteúdo |

## 2 · Persona

**Larissa, balconista.** Atende cliente ao telefone e no balcão, consulta o catálogo dezenas de
vezes por turno, decide preço na hora. Pesam **3** para ela: speed-to-task, discoverability, error
recovery, cognitive load, affordance, microcopy, i18n. Brand confidence pesa **1**.

## 3 · As 15 dimensões

| # | Dimensão | Nota | Peso | Contribuição | Por quê — medido |
|---|---|---|---|---|---|
| 1 | Density | 85 | 2 | 170 | linha de 3 níveis em ~56px; 8 colunas em 1000px |
| 2 | Discoverability | 80 | 3 | 240 | sublinhado tracejado no preço e em "N locais" sinaliza o overlay; ★/⋯ não sinalizam função |
| 3 | Speed-to-task | 90 | 3 | 270 | `/` foca a busca; hover revela sem clique; 9 tarefas sem sair da lista |
| 4 | Error recovery | 70 | 3 | 210 | chips e "Limpar filtros" resolvem recorte errado; sem estado de erro de carga |
| 5 | Cognitive load | 85 | 3 | 255 | detalhe sob demanda; nenhuma coluna nova para variação, local ou faixa |
| 6 | Aesthetic-usability | 80 | 2 | 160 | densidade coerente com a referência de produção |
| 7 | Affordance | 70 | 3 | 210 | gatilhos são `<button aria-haspopup>`; ★/⋯ prometem ação que não existe |
| 8 | Brand confidence | 75 | 1 | 75 | 5 cores fora de token enfraquecem a leitura de sistema |
| 9 | Mobile fit | 40 | 1 | 40 | sem colapso abaixo de 1000px; rolagem horizontal |
| 10 | A11y WCAG | 60 | 2 | 120 | teclado e Esc ok; contraste não medido; alvo 28px |
| 11 | i18n PT-BR | 95 | 3 | 285 | BRL `R$ 1.234,56`, datas dd/mm/aaaa, sem string em inglês visível |
| 12 | Performance percebida | 70 | 2 | 140 | estrutura pinta antes do dado; sem esqueleto na troca de página |
| 13 | Information hierarchy | 90 | 2 | 180 | nome → resumo → variações; dinheiro alinhado à direita e tabular |
| 14 | Microcopy | 85 | 3 | 255 | "a partir de", "0 na Loja — saldo em outro local.", "Nenhum item neste recorte" |
| 15 | Internal consistency | 80 | 2 | 160 | mesma mecânica nos três popovers; 3 exceções ao DS |

```
Σ contribuições = 2 770 · máximo = 35 × 100 = 3 500
score = 2770 / 3500 × 100 = 79,1  →  79 / 100
```

## 4 · Instrumentos

**4.1 Mobile fit** — não aplicável ao alvo declarado (cockpit desktop). Medido: abaixo de 1000px a
tabela rola na horizontal; o rodapé de paginação acompanha a largura visível (correção aplicada).

**4.2 A11y** — verificado: foco alcança os três gatilhos de overlay e as ações da linha; Esc fecha
popovers e menu; `aria-haspopup="dialog"`, `role="dialog"`, `aria-label` nos ícones. Não
verificado: contraste, leitor de tela, zoom 200%.

**4.3 Estados obrigatórios**

| Estado | Existe? |
|---|---|
| Vazio de recorte | sim, com dica contextual |
| Primeira carga (vazio absoluto) | não |
| Carregando | não |
| Erro | não |
| Sem permissão | parcial — colunas somem, mas não há aviso |

## Anexo A — regras binárias

| Código | Regra | Resultado |
|---|---|---|
| AP1 | Zero cor crua | **✗** 5 literais (ADR 0401, pedido do produto) |
| AP2 | Não reinventar componente do DS | ✗ com exceções nomeadas: nº1 menu (chevron forçado) · nº2 paginação (sem primeira/última) · nº3 abas (badge inativo claro) |
| AP4 | Ícones só do lucide | ✓ desenhados como SVG inline |
| AP5 | Sem gradiente decorativo | ✓ |
| AP6 | Sem emoji | ✓ |
| AP7 | Status badge = dot + texto | ✓ pílulas de disponibilidade têm dot + rótulo + número |
| — | Espaçamento múltiplo de 4 | ✓ exceto altura 38 da busca |
| — | Tipografia do DS | ✓ |
| — | Token novo = ADR antes | ✓ nenhum token novo (as cores cruas são o oposto: cor sem token — ADR 0401) |

## Pendências, em ordem de custo

1. **Baixo** — remover ou ligar ★ e ⋯ (CU-PROD-21).
2. **Baixo** — conteúdo do botão "?" ou remoção.
3. **Baixo** — medir contraste dos seis pares listados no LAUDO §4.
4. **Médio** — Skeleton na troca de página + EmptyState de erro e sem permissão.
5. **Médio** — tokenizar as cinco cores (fecha ADR 0401 e destrava o tema escuro).
6. **Alto** — colapso responsivo dos filtros (CU-PROD-22).
7. **Alto** — extrair dado e estilos para a estrutura de camadas do manual de handoff.
