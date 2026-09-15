---
modulo: ancora
titulo: Contrato de evidência da âncora — o que autoriza dizer "esta tela tem âncora"
dono: "[CC]"
estatuto: decisão de design, proposta a [W] · vincula o que EU escrevo desde já
base: 752041ac450d
data: 2026-09-09
---
# Contrato de evidência da âncora

## O problema, em uma frase
Hoje "tem âncora" significa **três coisas diferentes** conforme quem pergunta: o `--list` aceita um caminho declarado (D3: carimba `hasSource` sem provar arquivo), o comando de 1 tela exige `--staging` pra enxergar o mesmo arquivo (D1), e a skill `comparar-design-prod` avisa que responde *qual arquivo*, nunca *qual view*. Três respostas, uma palavra — e 20 charters do Ponto apontando pro mesmo `.jsx`. Sem escala de evidência, `✓` não quer dizer nada e ninguém confia no verde.

## A decisão — 5 níveis, cada um com comando, dono e falsificador
Cada nível **contém** o anterior. Nada é afirmado por nível que não teve o seu comando rodado **no turno**.

| nível | significa | provado por | falsificador |
|---|---|---|---|
| **E0 · DECLARADO** | o charter cita um caminho | leitura do frontmatter | nenhum — **não é evidência**. É intenção |
| **E1 · ABRE** | o caminho **resolvido** existe no git, no `LUGAR_FIXO`, **sem flag** | `caminhoDaAncora` + `existsSync` (threads 01 e 03) | arquivo ausente/renomeado ⇒ E0 |
| **E2 · SÍMBOLO** | o arquivo contém **o símbolo citado** e a faixa bate | `simbolosCitados` + leitura do `.jsx` (`function X` / `const X =` / `window.X`) | símbolo ausente, ou 1 arquivo servindo N charters **sem** símbolo ⇒ E1 |
| **E3 · CONTEÚDO** | protótipo **e** tela passam nas sondas de conformidade e a tradução é fiel | `ds-anchor-check.mjs --check` (R1–R7b) | qualquer achado em charter `live` ⇒ E2 |
| **E4 · PIXEL** | os dois renders batem | T7 `design-diff --compare --check`, **prod deployada** | qualquer diff ⇒ E3 |

## As quatro regras que caem disto
1. **A palavra segue o nível.** "Tem âncora" = E1. "É a âncora **desta** tela" = E2. "Está conforme" = E3. **"Igual"/"paridade" só no E4** — e o E4 não roda daqui, então eu nunca o afirmo.
2. **Piso por `status:` do charter.** `draft` ⇒ **E1**. Promover a `live` ⇒ **E3** (mesma severidade que o `ds-anchor-check` já aplica: draft avisa, live falha). Onda de módulo que declara "âncora de implementação" ⇒ **E2**, senão a onda está ancorada num arquivo, não numa tela.
3. **Ambiguidade não é evidência — é veredito nulo.** Query que casa N charters não devolve um `✓` sorteado: lista candidatos e sai não-zero (thread 02). Match fraco silencioso rebaixa a E0.
4. **`via='component'` nunca conta.** Âncora = a própria tela é tautologia (o charter `Repair/Settings` já recusa isso em prosa). No `--list` ela sai como **E0**, marcada, e continua contando na régua só enquanto `D-COMPONENT` não tiver o número medido — a remoção do código é de [W], a **desclassificação como evidência é minha e vale já**.

## O que o contrato NÃO faz
- **Não decide o formato** `arquivo :: símbolo :: faixa` — isso é `D-SIMBOLO`, de [W]. O contrato só diz: **é ele que carrega o E2**, e hoje um único charter do corpus medido (`Sells/Caixa/Index` → `vendas-extras.jsx · função VendasCaixaPage (linhas 123-354)`) já está no nível. Sem `D-SIMBOLO`, todo módulo muitos-para-um fica **teto E1**.
- **Não mexe em frescor.** Frescor é eixo separado por desenho: um arquivo pode ser E3 e estar velho. Nível de evidência e idade **não se somam num selo só**.
- **Não reclassifica charter nenhum.** Aplicar a régua ao corpus é medição, e ela não foi feita: dos 189 charters, **43 não foram medidos** (varredura parcial, 328/400 arquivos). Não medido ⇒ **sem nível**, não E0.
- **Não vira gate novo.** Os níveis são lidos pelas máquinas que **já existem** (`ancora.mjs`, `ds-anchor-check.mjs`, `design-coverage`, T7). Se algum elo já existir em máquina, **estende-se** — não se cria a segunda.

## Efeito imediato nas 3 threads (sem mudar prefixo nem prova)
- **01** (bundle sem `--staging`) = tornar o **E1 alcançável pela porta de 1 tela**. É o que faz as duas portas do mesmo arquivo pararem de discordar.
- **02** (query ambígua) = **proibir E-nenhum disfarçado de E1**.
- **03** (`--list` prova o arquivo) = **`hasSource` passa a significar E1**, e o campo `via` passa a dizer o nível. `hasSource` **continua existindo** (o `design-coverage` lê) — muda o significado, não o nome.

## Fonte da máquina (delta pro `00-INDICE.md`)
```json
{
 "modulo": "ancora",
 "delta": "decisoes",
 "decisoes": [
  {
   "id": "D-EVIDENCIA",
   "pergunta": "O que autoriza afirmar que uma tela tem âncora?",
   "respondida": true,
   "resposta": "escala E0..E4 — E0 declarado (nao e evidencia) · E1 abre · E2 simbolo · E3 conteudo (ds-anchor-check) · E4 pixel (T7). Piso: draft=E1, live=E3, onda=E2. via='component' e E0. Ambiguidade = veredito nulo.",
   "dono": "[CC]",
   "ratifica": "[W]",
   "define": "NIVEL_EVIDENCIA"
  }
 ]
}
```
