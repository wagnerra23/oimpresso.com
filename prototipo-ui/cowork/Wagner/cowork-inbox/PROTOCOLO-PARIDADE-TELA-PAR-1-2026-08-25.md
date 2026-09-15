# PROTOCOLO PAR-1 · Conferir e fazer paridade de tela (protótipo ⇄ `main`)

**Para:** [CL] Claude Code (executa no repo) e [CC] Cowork (executa no protótipo).
**Origem:** processo usado no import da Consulta de Produtos, 2026-08-25 — inclui os 5 erros que ele evita, todos cometidos naquele dia.
**Gatilho:** "confere a paridade da tela X" ou "faz a paridade da tela X".
**Saída obrigatória:** o **Laudo de Paridade** (§6). Sem laudo, a tarefa não está feita — mesmo que a tela esteja bonita.

---

## 0) Vocabulário do laudo (use estas 3 letras, nada mais)

| Letra | Significa | Quem decide |
| --- | --- | --- |
| **V** | Verificado: portado igual à fonte, medido. | [CL]/[CC] |
| **D** | Divergência **declarada**: diferente da fonte, com motivo escrito. | [W] ratifica |
| **F** | Falha: diferente da fonte sem motivo, ou não verificado. | corrigir antes de entregar |

Regra dura: **"não li" nunca vira V.** Se não leu o arquivo, é F.

---

## 1) Fixar a FONTE (e provar que é a atual)

1. `git fetch && git log -1 --format='%H %cI' origin/main -- <caminho/da/tela>` → registre sha + data no laudo.
2. Se o caminho não existir mais, `git log --diff-filter=D --name-only origin/main | grep <Tela>` — tela renomeada/movida é o caso mais comum de "paridade" contra arquivo morto.
3. **Proibido** usar cópia, espelho, `prototipo-ui/cowork/**`, `.bak`, ou memória do modelo como fonte. Fonte = `origin/main` neste turno.

> Erro que isto evita (real, 2026-08-25): o import saiu de um espelho de ~2 semanas atrás; a tela havia sido reescrita em 18–24/08 (abas por tipo, KPI-filtros, paginação). Paridade medida contra o arquivo velho deu "40% e conceitualmente errada".

---

## 2) Fechar o GRAFO DE IMPORTS (o arquivo de página não é a tela)

```bash
# imports locais da página, recursivo (1º e 2º nível já cobrem quase tudo)
grep -oE "from '(\./|\.\./|@/)[^']+'" <Tela>.tsx | sort -u
```

Para **cada** arquivo do grafo: leia e marque no laudo `lido: sim/não`. Um import não lido = F na seção que ele governa.

Onde a regra normalmente mora (procure nestes, não adivinhe):
- `_components/<dominio>.ts` — vocabulário, formatadores, estados, regra de negócio pura.
- `_components/Colunas.tsx` (ou equivalente) — **autorização** de coluna/célula.
- `_components/Kpi*`, `Filtro*`, `BulkBar`, `Detalhe*` — blocos com contrato próprio.

Se o arquivo vier truncado pela ferramenta: **grep os marcadores do trecho perdido** (`Esqueleto`, `Command*`, `AlertDialog`, `totais*`, `porPagina*`, `posicao=`) e leia por linha. Não conclua por inferência.

---

## 3) Extrair o CONTRATO da tela (antes de escrever qualquer linha)

Preencha esta tabela lendo a fonte. Cada célula vazia é uma pergunta pra [W], não um chute:

| Eixo | O que anotar |
| --- | --- |
| Árvore | ordem dos blocos, o que é `header`/`nav`/`toolbar`/`grid`/`footer` |
| Recorte | abas (chaves + rótulos), KPI-filtros, filtros, ordem, paginação — e **o que está na URL vs no localStorage** |
| Autorização | quais campos são gateados, por qual permissão, e o comportamento na ausência (**coluna não montada ≠ escondida**; ausência **nunca** imprime `0`/`—`) |
| Números do layout | larguras por coluna, `min-width` (somado ou fixo?), alturas de linha por densidade, breakpoints (e se são de **janela** ou de **container**) |
| Cor | receita medida de cada selo/placa (ex. selo 16%/30%/tom cheio; placa de ícone 14% sem borda) — sempre via token |
| Copy | rótulos **literais**, incluindo os que mudaram de nome ("Disponível" ≠ "Em estoque") |
| Removidos | o que a fonte **tirou** (abas, cards, chips). Remover é parte da paridade |
| Estados | dados/carregando/vazio/erro/sem-permissão e onde cada esqueleto entra |
| Teclado | atalhos, o que eles fazem na borda da página, e o que vale enquanto se digita |

---

## 4) Mapear o DADO antes da UI

1. Compare o tipo da linha da fonte (`ProdutoRow`, `ClienteRow`…) com o que o protótipo/mock tem.
2. Campo que falta: **declarar** (mapa explícito no arquivo de dados), nunca derivar de outro campo para "parecer apurado".
3. Campo que a fonte pode **não emitir** (permissão): reproduza a **ausência da chave**, não um valor neutro.

> Erro que isto evita: custo/margem/saídas derivados de preço e popularidade — número inventado com cara de apurado.

---

## 5) Portar e provar

- Tailwind → tokens do cockpit: `bg-primary/10` → `color-mix(in oklab, var(--accent) 10%, transparent)`; `text-primary-foreground` → `var(--accent-fg)`. **Zero cor crua** (`#fff`, `rgba(255,255,255,…)`, oklch literal) — AP1.
- Ordem e copy literais. Grupos e seções na sequência da fonte (a ordem É decisão de produto).
- Depois de montar, **medir** (não olhar):
  - contagens de aba/KPI batendo com o recorte;
  - `client` vs `scroll` de **cada** scroller e a **cadeia de overflow** (AP10: nó `flex-1` em coluna precisa de `h-full`/pai `flex flex-col min-h-0`);
  - a quem o `position:sticky` se ancora (wrapper com `overflow-x:auto` vira scrollport e deixa o sticky **inerte**);
  - contraste medido de todo texto sobre cor de marca (o accent inverte no dark: use `--accent-fg`);
  - breakpoints de container com a largura **disponível**, não a da janela.

**Gates do repo** (rodar, colar resultado no laudo):
```bash
npm run ui:lint && npm run casos:check && npm run contrato:check
node scripts/qa/prototipo-readiness.mjs
node scripts/cowork-paridade.mjs --check
node scripts/qa/cowork-ssot-guard.mjs
```

---

## 6) LAUDO DE PARIDADE (formato de saída — sempre este)

```
TELA: <rota> · fonte: <caminho>@<sha> (<data>)
GRAFO: <n> arquivos, <n> lidos            # não lido = F

| # | Eixo                | Fonte (resumo)                  | Aqui                          | V/D/F | Prova / motivo |
|---|---------------------|---------------------------------|-------------------------------|-------|----------------|
| 1 | Abas por tipo       | 6 abas + badge                  | idem                          | V     | contagens medidas |
| 2 | Custo/margem        | coluna não montada s/ permissão | idem                          | V     | papel=balcao: 0 th |
| 3 | Estoque desconhecido| imprime "—"                     | imprime número (mock)         | D     | mock declara saldo |
...

GATES: ui:lint ✅ · casos ✅ · contrato ✅ · readiness <n>/<n> · ssot-guard ✅
DIVERGÊNCIAS PARA [W]: <lista curta, 1 linha cada, com a pergunta a responder>
```

Regras do laudo: **toda D tem uma pergunta pro [W]**; nenhuma F sai na entrega; e a frase "paridade alta" só aparece com a tabela ao lado.

---

## 7) Modo "só conferir" (não escrever código)

Faça §1 → §3 e entregue apenas o laudo com V/D/F por eixo, mais o custo estimado de fechar cada F. Não abra editor. É o modo padrão quando [W] disser "confere".

## 8) Diferença entre os dois executores

- **[CL] no repo:** fonte é o working tree em `origin/main`; pode rodar os gates de verdade e abrir PR. Deve incluir no PR o laudo como corpo.
- **[CC] no Cowork:** fonte é leitura do `main` pelo conector (read-only); não roda gates nem commita — entrega o laudo no chat e, se houver build, o arquivo do protótipo. Nunca afirma "commitado".

---

## 9) Os cinco erros que este protocolo existe pra impedir

1. Fonte velha (espelho/memória) tratada como `main`.
2. Import não lido → componente recriado por inferência.
3. Dado derivado apresentado como apurado.
4. Permissão tratada como preferência de layout (esconder em vez de não montar).
5. "Verificado" sem medição — contraste, overflow, sticky e breakpoint só existem medidos.
