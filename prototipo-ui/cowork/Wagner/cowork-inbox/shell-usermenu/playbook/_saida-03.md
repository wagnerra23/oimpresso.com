---
sessao: "_saida-03"
thread: "03 · + Adicionar empresa: item com role=menuitem e sem ação"
dono: "[CL]"
data: 2026-09-13
prefixo_tocado: resources/js/Components/cockpit/Sidebar.tsx · tests/sidebarMenuSemantics.spec.tsx
base_lida: wagnerra23/oimpresso.com@main (PR #7254)
natureza: RECIBO DE RECONCILIAÇÃO — ver §0
---
# _saida-03

## 0 · Por que este recibo não foi escrito pela sessão executora

Em 2026-09-13 o `cowork-inbox/` não existia no repo. O executor registrou em
[`memory/sessions/2026-09-13-sidebar-adicionar-empresa-thread03.md`](../../../../../../memory/sessions/2026-09-13-sidebar-adicionar-empresa-thread03.md)
e no [PR #7254](https://github.com/wagnerra23/oimpresso.com/pull/7254). **Nenhuma afirmação aqui é nova.**

## 1 · Feito (palavras do executor)

> Conserto aplicado em **1 site** (`Sidebar.tsx:522`): `aria-disabled=true` + `title` com o motivo;
> **+21/-1, zero CSS tocado**.

Saiu pela **opção 2** do playbook (desabilitar com motivo em vez de navegar), porque a permissão
que a rota exige não chega ao front por nenhum dos 4 caminhos medidos — navegar daria 403.

## 2 · A premissa do playbook foi CORRIGIDA pela medição

> "nos dois dropdowns, código separado" vale pro **PROTÓTIPO** (`CompanyPicker :70` +
> `CompanyPickerRail :478`), **não pro vivo** — o vivo tem **1 componente e 1 call-site**
> (`AppShellV2.tsx:597`), servindo os dois modos; o rail só troca a classe do `aside`.

E a varredura de classe que o playbook pedia: `role=menuitem` aparece **1 vez em 1** no arquivo
inteiro — **o conserto cobre a classe, não só a instância**.

## 3 · Provas

⚠️ Esta thread **não tem prova de arquivo** no §7 do índice (as três são `execucao`/`runtime`),
então a máquina não consegue aferi-la sozinha. Verificação direta em `origin/main` (2026-09-14):
`Sidebar.tsx` traz `aria-disabled="true"` + `title` no item, e `tests/sidebarMenuSemantics.spec.tsx`
existe. O veredito aqui é **leitura de código**, e está dito em voz alta por isso.
