---
sessao: "06"
titulo: Contrato de tela do shell + gates — saída
dono: "[CL]"
base_lida: f7246bad11
data: 2026-09-11
---

# _saida-06 · Contrato de tela do shell da Sidebar

## 1 · Feito

- **`prototipo-ui/contrato/cockpit-sidebar.contract.json`** — 5 seções (`sb-modos` · `sb-topo` · `sb-corpo` · `sb-rodape` · `sb-alcas`), 17 copies literais, 24 estados, `ordem` de 4 ids.
- **5 âncoras `data-contract`**, 1 atributo cada, em elemento DOM que já existia. Diff total: **+5/−4**, zero wrapper, zero mudança de árvore, zero CSS.
- **§7 do índice corrigido** em dois pontos (detalhe em §3).

## 2 · O impasse — resolvido por medição, não no olho

O pedido trazia o impasse: o schema exige âncora `data-contract="<id>"` por seção, `contrato-de-tela.mjs:235` reprova sem ela, e os dois arquivos do shell tinham **0 âncoras** — mas o `nao_toca` da 06 os proibia.

**Medi antes de escolher:**

| o que medi | resultado |
|---|---|
| âncoras em `Sidebar.tsx` / `AppShellV2.tsx` | **0** e 0 (controle positivo: 73 arquivos do repo *têm*) |
| o gate varre todos os contratos? | sim — `git ls-files '*.contract.json'`, always-run, sem `continue-on-error`. Contrato sem âncora **não é contrato parcial, é CI vermelho** |
| existe válvula no `--contract`? | **não**. O `design-deviation` cobre só o `--map --check` |
| padrão do repo (6 contratos) | contrato + âncora no **mesmo commit** em 4; 1 dia antes em 2; **zero** contratos sem âncora |
| precedente de shell sem rota | `fiscal-subnav` (#7110) — contrato e âncora no mesmo commit `7e1a386536` |
| saída (a): a 04 leva as âncoras | **recria o deadlock** — a 04 está `pendente` (`shared.ts` sem `\| 'hidden'`) e o RESIDUO-5 segue `respondida: false` |

**Escolhi (b), na menor ampliação possível.** O `nao_toca` original existia para proteger *comportamento* — a própria linha dizia *"esta thread não muda comportamento, só o trava"* — e a âncora **é** o instrumento de travamento: atributo `data-*`, inerte. Medido: nenhum seletor de `cockpit.css` casa `[data-contract]` (o único CSS do repo que casa é `cowork-arquivos-bundle.css`, escopado em `.arq-page`).

**Colisão re-medida no dia:** o #7203, que tocava os 2 arquivos, **mergeou** às 11:59 UTC; o #7030 (aberto) toca `AppShellV2` nas linhas ~423-475, longe das 568-615.

### Por que a âncora do rodapé não foi como prop

`SidebarFooter` desestrutura 8 props e **não** faz spread do resto — a prop seria descartada em silêncio, e a âncora passaria o gate **estático** sendo **inerte no DOM** (LC-30). O repo documenta esse exato defeito em `Components/shared/KpiGrid.tsx:36-45`. Por isso a âncora vai no `<div className="sb-user-wrap">` que o próprio `SidebarFooter` já renderiza.

## 3 · Segundo defeito, que eu não estava procurando

A ficha declarava a prova da 06 como `revisao`. **Ela nunca poderia fechar**, e não por causa da minha ampliação: `placar-evidencia.mjs:67` recusa `revisao` quando *qualquer* item do prefixo não termina em `.md`/`.contract.json` — e o prefixo **original** já tinha `tests/Feature/Sidebar/`, que não casa. Rodado com a função real: `trava = true`, tanto no prefixo antigo quanto no novo.

Trocado para `execucao`, que é o que a nota antiga já antecipava (*"os gates PHP entram como execucao"*). A 06 tinha, portanto, **dois** motivos independentes para nunca fechar.

## 4 · Copy — conferida no vivo, e três correções ao §B da ficha

O pedido avisava: *string que não existe faz o gate falhar*. Conferi cada uma. **O §B da ficha estava errado em três pontos**, e o contrato segue o medido:

1. **Os grupos não são 5** (`vender · operar · financas · pessoas · sistema`) — são **9**: `CADASTRO COMERCIAL FINANÇAS FISCAL PRODUÇÃO ESTOQUE RH SISTEMA PLATAFORMA`. `label: 'VENDER'`, `'OPERAR'` e `'PESSOAS'` dão **0 ocorrência**; o mental-model antigo sobrevive só em comentário, e o próprio código diz que a ordem canon [W] 2026-05-22 o substituiu.
2. **Os contadores não são `chat · atendimento · tarefas`** — `tarefasCount` existe como prop, mas o JSX não renderiza Tarefas (*"Wagner 2026-05-22: Tarefas REMOVIDO"*). Os 3 shortcuts de topo são **IA · Visão geral · Atendimento**.
3. **`"Mostrar sidebar"` não existe no vivo** (0 ocorrências) — só no protótipo (2), como o pedido antecipou. **Não declarada.**

## 5 · Prova — mutação, não leitura

Rodei o gate real pelo CLI de fora contra fixture hermética (`--root`). Controles antes de qualquer veredito: fixture intacta → `rc=0`; âncora `sb-corpo` removida → `rc=1`.

Três baterias de mutação. A que vale é a terceira (**um sítio só** — alguém troca o rótulo num lugar):

| bateria | mordem |
|---|---|
| (a) mutar todas as ocorrências | 17/17 — é a mutação fácil, não prova nada |
| (b) mutar só linhas de código | 14/17 |
| **(c) mutar UM sítio — o realista** | **11/17** |

**Mordem (11):** `EMPRESAS` · `Nenhuma empresa disponível` · `tela(s) de` · `Sair` · `Configuração de atalhos: aba POS em Settings` · `Navegação principal` · `Abrir menu` · `Fechar menu` · `Cert vence em breve` · `Certificado vencido` · `Clique pra renovar`.

**Carimbo (6):** `Expandir sidebar` e `Recolher sidebar` (2 sítios cada, `title` + `aria-label`, que se protegem — trocar só o tooltip passa) · `Meu perfil` · `Aparência` · `Modo de trabalho` · `Disponível`.

**Errata minha, registrada e não apagada:** eu havia atribuído o carimbo de `Disponível` a ocorrência em comentário. A mutação mostrou outra razão — são **3 sítios de código** que se protegem entre si. Contar sítios não substituiu mutar.

Ficaram **fora** por serem carimbo puro os 9 rótulos de `SIDEBAR_GROUPS`: cada um tem de 4 a 8 ocorrências em comentário no próprio `Sidebar.tsx`. Endurecer isso exige casar a copy no **contexto** (o par `label: '...'`), que é mudança em `scripts/contrato-de-tela.mjs` — arquivo com PR em voo (#7138), não daqui.

## 6 · Gates

| gate | resultado |
|---|---|
| `--contract` (o contrato novo) | ✅ 5/5 seções + ordem coerente · `rc=0` |
| `--map --check` | ✅ `rc=0` · `cockpit/_sidebar` · fonte ✓ · **5/5** · 🟢 portado |
| `--anti-tautologia` | ✅ `0 reprovado(s)` · **aviso** em 5/17 copies, cada uma com razão nomeada no contrato |
| `contrato-de-tela.test.mjs` (selftest) | ✅ todos os controles |
| `cowork-ssot-guard.mjs` | ✅ fonte única OK |
| `prototipo-readiness.mjs` | ✅ `rc=0` |
| `placar-indice.test.mjs` | ✅ 48/48 |
| `--filter=AppShellUsageGate` (CT 100) | ✅ **1 passed · 2 assertions · 0 failed/skipped** → recibo |
| `--filter=Sidebar` (CT 100) | baseline `1 failed · 6 skipped · 77 passed (269 assertions)` → **com patch: idêntico** |
| `--filter=Cockpit` (CT 100) | baseline `16 failed · 4 skipped · 73 passed (404 assertions)` → **com patch: idêntico** |

**Sobre o `--map --check`:** a primeira execução deu `rc=0` **sem ter avaliado o meu contrato** — ele coleta por `git ls-files`, e o arquivo ainda não estava trackeado. Re-rodei depois do `git add`; só então o `cockpit/_sidebar` aparece no mapa. Verde de universo vazio não é verde.

**Sobre `Sidebar` e `Cockpit`:** as falhas são **pré-existentes** no CT 100 (que está em `755f6de79`, de 08/09, com database persistente). Medi **com e sem** o patch e os contadores são idênticos — prova de ausência de regressão, mas não serve de recibo, porque o validador exige `failed=0` e `skipped=0` por arquivo. Por isso o recibo usa o `AppShellUsageGateTest`. O CT 100 foi **restaurado** ao estado original (meus 2 paths limpos, 0 âncoras).

## 7 · Não feito — declarado, não escondido

- **A Sidebar não tem âncora computável.** `node prototipo-ui/ancora.mjs cockpit/Sidebar` → `✗ sem charter pra essa tela — NÃO invente âncora`. O §8.1 do índice já registra. A `fonte` do contrato é **declarada** a partir do que o playbook nomeia, não resolvida pela porta viva. Fica como resíduo, não como âncora provada.
- **`sb-rodape` fora da catraca de ordem**, de propósito: o gate lê a sequência por arquivo, e ele mora em `Sidebar.tsx` enquanto os outros 4 estão em `AppShellV2.tsx`.
- **6 das 17 copies são carimbo** (§5). Declaradas como tal no contrato.
- **`Abrir menu` / `Fechar menu` geram aviso no anti-tautologia** porque no protótipo vivem em `app.jsx`, não em `sidebar.jsx`, e `fonte` é string única no schema.
- **T7** (`design-diff --compare --check` nos dois renders, prod deployada) não é verificável daqui — segue com [W2].

## 8 · Resíduo para a thread 04

A 04 tem os 2 arquivos no prefixo dela. Quando rodar, encontra as âncoras já lá e precisa:

1. reancorar `sb-modos` / `sb-alcas` no estado novo (`hidden` + `SidebarReopenHandle`);
2. acrescentar `"Mostrar sidebar"` à copy de `sb-alcas`.

É **aditivo**, não conflitante.

## 9 · Prefixo tocado

```
prototipo-ui/contrato/cockpit-sidebar.contract.json          (novo)
resources/js/Layouts/AppShellV2.tsx                          (+4 atributos)
resources/js/Components/cockpit/Sidebar.tsx                  (+1 atributo)
prototipo-ui/design-docs/cowork-inbox/sidebar/playbook/00-INDICE.md          (§7: prefixo · nao_toca · prova)
prototipo-ui/design-docs/cowork-inbox/sidebar/playbook/_saida-06.md          (este)
prototipo-ui/design-docs/cowork-inbox/sidebar/playbook/recibos/06-junit.xml
prototipo-ui/design-docs/cowork-inbox/sidebar/playbook/recibos/06-execucao.summary.json
prototipo-ui/design-docs/cowork-inbox/sidebar/playbook/recibos/06-execucao.json
```
