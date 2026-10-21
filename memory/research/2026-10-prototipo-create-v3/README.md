# Protótipo create-v3 — artefato navegável e pesquisas (2026-10-20)

**Data:** 2026-10-20 e 2026-10-21 · **Autor:** Claude Code [L+C], a pedido do Luiz · **Para:** consulta do [W]
**Tela:** [/sells/create-v3](../../../resources/js/Pages/Sells/CreateV3.charter.md) · **Âncora de design:** [`prototipo-ui/cowork/Wagner/venda-v3.jsx`](../../../prototipo-ui/cowork/Wagner/venda-v3.jsx)

Este pacote junta o que foi produzido sobre a tela Nova venda (create-v3) entre 20 e 21/10/2026: o protótipo em um HTML que abre sozinho, a pesquisa de casos de uso nos concorrentes e a pesquisa de como os concorrentes dividem a venda entre vendas, produção, financeiro e faturamento.

Nada aqui muda código, charter, âncora ou banco. É material de consulta.

## O que tem aqui

| Arquivo | O que é |
|---|---|
| [01-casos-de-uso-nos-concorrentes.md](01-casos-de-uso-nos-concorrentes.md) | 40 casos de uso que a tela deve ter, com prioridade, quem já oferece e se a create-v3 tem, tem em parte ou não tem |
| [02-quem-faz-o-que-na-venda.md](02-quem-faz-o-que-na-venda.md) | Como os concorrentes passam a venda de mão em mão, o que isso pede da tela e um checklist do que já dá para aplicar no protótipo |
| [img/](img/) | Os três diagramas das duas pesquisas, em PNG |
| [notas/](notas/) | Notas brutas da pesquisa, com a fonte de cada afirmação. `casos-de-uso/` alimenta o 01; `divisao-de-tarefas/` alimenta o 02 |
| [prototipo/venda-v3-create.html](prototipo/venda-v3-create.html) | O protótipo da tela num arquivo só |
| [prototipo/fonte/](prototipo/fonte/) | O que gera o HTML: script de build, ajustes do artefato e a moldura (barra de modo e tema) |

## Como abrir o protótipo

Abra `prototipo/venda-v3-create.html` no navegador. Ele precisa de internet só para baixar React e os ícones (lucide) do unpkg; o resto (design system, fontes IBM Plex, CSS e a tela) está embutido.

- A barra do topo troca entre **Nova venda** e **Editar venda**, e entre tema **claro** e **escuro**.
- É a âncora `venda-v3.jsx` compilada, sem o shell do app. Os dados são de cena e nada é gravado, igual à âncora.
- **Uma diferença da âncora:** no modal "Lançar item", o desconto tem dois campos ligados, em % e em R$. O total do item passa a usar a mesma conta da linha da grade. Pedido do Luiz em comentário no artefato, 20/10. O ajuste vive em [`prototipo/fonte/ajustes-artefato.cjs`](prototipo/fonte/ajustes-artefato.cjs) e é aplicado por cima da âncora no build; a âncora não foi editada.
- **Máscara Tier 0 de dinheiro:** o bundle do design system e um texto didático da âncora trazem valores de demonstração em reais. O build troca esses valores por travessão, porque o HTML mora em `memory/`.

Para gerar de novo a partir da âncora, rode na raiz do repo (precisa do `node_modules` instalado, por causa do esbuild):

```bash
node memory/research/2026-10-prototipo-create-v3/prototipo/fonte/build-createv3.cjs . memory/research/2026-10-prototipo-create-v3/prototipo/venda-v3-create.html
```

Se a âncora mudar onde um ajuste se apoia, o build para e diz qual ajuste não achou o trecho.

## O que as pesquisas concluíram

**01 — casos de uso** ([detalhe](01-casos-de-uso-nos-concorrentes.md))
- A tela precisa cobrir 40 casos de uso; 23 são essenciais. A create-v3 já tem 14 dos 40 e 11 dos 23 essenciais.
- Cinco das seis lacunas essenciais ficam entre o orçamento e a produção: composição com custo e margem, validade, PDF e envio por WhatsApp, aprovação online do orçamento e aprovação de arte. A sexta é emitir NF-e e NFS-e da mesma venda.

**02 — quem faz o quê** ([detalhe](02-quem-faz-o-que-na-venda.md))
- Nos concorrentes, a venda é um registro único e cada setor trabalha na sua fila, não na tela de venda.
- Os mais completos (Sign OS, shopVOX, Nortgraf, Protheus, Sankhya) separam o aceite do cliente da liberação para produção, que só sai com sinal, arte e crédito resolvidos.
- Para a create-v3: separar "Aprovar orçamento" de "Liberar para produção", mostrar cada trava com dono, travar o preço no aceite (mudança vira variação) e tirar "Faturar" do caminho obrigatório da entrega. São 15 mudanças possíveis dentro da tela e 6 que dependem da ligação com outros módulos.

## Limites

- Só fonte pública: site, central de ajuda, notas de versão e fóruns. Ninguém criou conta nem pediu demonstração. "Não documentado" quer dizer que não achamos, não que o sistema não faça.
- Mubisys, Holdprint, Ajors e Everflow só têm página de produto ou menu público; o Sign OS é de 2026 e tem poucos clientes; muitos artigos do shopVOX são de cerca de 2024.
- A proposta de etapas da venda no 02 é síntese nossa, não cópia de um sistema. Os nomes de papel novos são sugestão.
- Ficaram duas perguntas abertas: o que o efeito `BaixarFinanceiro` faz hoje, e a regra fiscal de faturar depois da entrega, que precisa ser confirmada com o contador.

## Versões vivas

As duas pesquisas também existem como documento editável no claude.ai, com os diagramas interativos e as colunas de prioridade e situação como listas de seleção. O protótipo também está publicado lá. Os links são privados do Luiz; só abrem para quem ele compartilhar.

- Documento: https://claude.ai/code/artifact/d7d1f956-b1cb-4749-b49e-9e78c6216248
- Protótipo: https://claude.ai/artifact/U2BptYAKhLgoxfN2Nq9oHn
