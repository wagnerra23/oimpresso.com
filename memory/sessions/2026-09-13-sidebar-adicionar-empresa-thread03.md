---
date: "2026-09-13"
topic: "Thread 03 do playbook shell-usermenu: o '+ Adicionar empresa' do switcher tinha role=menuitem sem handler — vira aria-disabled com motivo, porque a permissão que a rota exige (username em config) não chega ao front por nenhum dos 4 caminhos medidos"
authors: ["C"]
prs: [7254]
outcomes:
  - "Conserto aplicado em 1 site (Sidebar.tsx:522): aria-disabled=true + title com o motivo; +21/-1, zero CSS tocado"
  - "Premissa do playbook CORRIGIDA: 'nos dois dropdowns, código separado' vale pro PROTÓTIPO (CompanyPicker :70 + CompanyPickerRail :478), não pro vivo — o vivo tem 1 componente e 1 call-site (AppShellV2.tsx:597), servindo os dois modos; rail só troca a classe do aside"
  - "Varredura da classe: role=menuitem aparece 1 vez em 1 no arquivo inteiro — o conserto cobre a classe, não só a instância"
  - "4 caminhos medidos para a permissão, todos negativos: props.auth.can (5 chaves do Ponto), superadminItems (filtra LABELS de outros módulos), shell.cockpit.businesses (derivado de user_type, regra diferente), clarityShare.user_type (null justamente pra superadmin)"
  - "Delta zero provado em tsc (314->314) e eslint (5->5, todos deslocados +20 linhas = saldo líquido do diff), medindo a baseline por git cat-file na MESMA árvore"
  - "Bite-test: 2 mutações, ambas caindo com AssertionError de contrato (não crash)"
  - "Reincidência minha da lápide §5 2026-08-18 (encode depois do open + emoji como surrogate) ao escrever o corpo do PR — arquivo truncou a 0 byte e o PR nasceu com body vazio; corrigido em minutos, não chegou a prod"
---

# Session log 2026-09-13 — thread 03 `shell-usermenu` · `+ Adicionar empresa`

## TL;DR

O item `+ Adicionar empresa` do rodapé do seletor de empresa tinha `role="menuitem"` e
**nenhum handler**: o leitor de tela anunciava algo acionável, e o clique não fazia nada.
Virou `aria-disabled="true"` com o motivo no `title`, porque a autorização que a rota de
destino exige **não existe no front** — e isso foi medido, não suposto.

[PR #7254](https://github.com/wagnerra23/oimpresso.com/pull/7254).

## O que a medição mudou no enunciado

Duas afirmações do playbook não sobreviveram à medição, e vale registrar porque a origem do
erro é a mesma nos dois casos: **o playbook descreve o protótipo, o trabalho acontece no vivo**.

1. *"Corrigir nos dois dropdowns — eles são código separado no arquivo."* O protótipo
   (`prototipo-ui/cowork/Wagner/sidebar.jsx`) de fato tem dois componentes, `CompanyPicker`
   (:70) e `CompanyPickerRail` (:478), cada um com sua cópia do botão. O vivo tem **um só**,
   com **um call-site** (`AppShellV2.tsx:597`), dentro do `<aside>` que serve os dois modos —
   `rail` acrescenta a classe `sb--rail` e nada mais. O conserto cobre os dois modos por
   construção.

2. *"Navegar pro destino de business."* O destino existe e a ação é real —
   `/superadmin/business` é `Route::resource` com `create`/`store` (`except(['edit','update'])`).
   Mas quem pode abri-lo é decidido pelo middleware `superadmin`, que **não usa permissão nem
   `user_type`**: compara o `username` contra `config('constants.administrator_usernames')`.

## Os 4 caminhos que o front tinha, e por que nenhum serve

| Caminho | O que traz | Serve? |
|---|---|---|
| `props.auth.can` | só as 5 chaves do Ponto (`HandleInertiaRequests:255-262`) | não contém |
| `superadminItems` | filtra **labels** (`Módulos`, `Backup`, `CMS`, `Conector`, …), cada um com permissão própria | nenhum é esta |
| `shell.cockpit.businesses` | derivado de `user_type` — regra **diferente** da do middleware | seria proxy |
| `clarityShare.user_type` | é `null` justamente para superadmin | invertido |

Usar qualquer um seria medir a propriedade errada. Caiu no **"Parar se"** do playbook, e a
saída foi a opção 2 dele: desabilitar **com o motivo**.

Vale dizer o que isso **não** custa: o botão hoje não faz nada **para ninguém**, então não há
capacidade perdida — ele apenas para de mentir. A tela segue acessível por URL direta, como o
próprio `Superadmin/DataController:119-123` registra desde 2026-05-22.

## Por que `aria-disabled` e não `disabled`

O `disabled` do HTML tiraria o item do foco (o padrão ARIA manda manter menuitem desabilitado
alcançável) **e** suprimiria o tooltip do `title` no Chrome/Firefox — que é justamente o
motivo que o playbook pede. O 2º caso de teste trava essa escolha, e a mutação correspondente
prova que ela morde.

## Recibos

- `vitest` nas 3 specs de sidebar: **20 passed** (eram 17 — o contador confirma os 3 novos).
- `tsc --noEmit`: **314 → 314** erros. `eslint`: **5 → 5** problems, todos deslocados **+20
  linhas**, que é o saldo líquido do diff (+21/−1). Baseline medida restaurando os blobs de
  `origin/main` por `git cat-file` na **mesma árvore** — condições idênticas.
- Bite-test: remover `aria-disabled` → `expected null to be 'true'`; trocar por `disabled`
  nativo → `expected true to be false`. **AssertionError** nas duas, não crash.

## Resíduos declarados (nenhum consertado à revelia)

1. O `:hover` de `.sb-dd-foot` segue dando feedback de clicável. Cosmético; matá-lo exige
   `resources/css/cockpit.css`, fora do prefixo da thread.
2. Habilitar de verdade exige expor a permissão ao front — e a lista de usernames em env não
   tem hoje forma óbvia de virar `can()`. Decisão de [W].
3. O `alert(… em breve, Fase 4 do cockpit)` no switch de empresa (`Sidebar.tsx:505` no main):
   **reportado, não consertado**, como a thread mandou. Natureza diferente — aquele item **tem**
   handler e **diz** que não faz; é TODO declarado, não promessa quebrada.
4. Smoke visual em produção não foi feito (exige login). Para um defeito de ARIA, o DOM
   renderizado nos testes prova mais que um screenshot — mas o smoke cabe ao merge.

## Erro meu, registrado

Ao escrever o corpo do PR em Python, usei `open(...).write(s.encode('utf-8'))` com um emoji
escrito como **par surrogate**. O `open` truncou o arquivo **antes** de o encode estourar: o
arquivo ficou com 0 byte e o `gh pr create --body-file` publicou um PR de corpo vazio. É a
lápide **§5 2026-08-18** na letra — *encode ANTES do open* e *emoji nunca como surrogate* —
e eu tinha aplicado a regra corretamente nas duas escritas de código desta mesma sessão,
falhando só na terceira. Corrigido com `gh pr edit` em minutos; não chegou a prod nem ao
histórico. A classe já tem lápide, então o que cabe é **incrementar ocorrência no ledger**, e
isso é outro intent — fica reportado ao [W], não enfiado neste PR.

## Endereço do recibo da thread

O `_saida-03.md` que o playbook pede **não tem endereço válido** hoje: o
`cowork-ssot-guard.mjs` R3 só admite `.md` em `prototipo-ui/cowork/<dono>/handoffs/<nome>.md`
(flat, sem subpasta). O recibo foi pro corpo do PR e para este log. O endereço canônico do
programa de playbooks fica **em aberto para o [W]**.
