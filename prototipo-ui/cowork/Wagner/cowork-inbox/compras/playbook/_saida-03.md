---
sessao: "_saida-03"
thread: "03 · Fornecedores — aba sem receptor"
dono: "[W] → [C]"
data: 2026-09-24
prefixo_tocado: Modules/Compras/Http/Controllers/DataController.php · resources/js/Pages/Compras/Index.charter.md
base_lida: wagnerra23/oimpresso.com@main 723d2b1e6
decisao: "D-FORN respondida por [W] em 2026-09-24 — saída (a): atalho para a lista de contatos que já existe"
---
# _saida-03

## 1 · Feito

- Menu Compras ganha o ghost **Fornecedores → `/cliente?type=supplier`**. A lista já existia:
  aba `supplier` / "Fornecedores" em `resources/js/Pages/Cliente/Index.tsx` (`SLOT2_TABS`) e
  whitelist `type=supplier` na rota `/cliente` (`routes/web.php`, ADR 0188).
- Non-Goal escrito no `Compras/Index.charter.md`: sem aba nem ficha própria de fornecedor no cockpit.

## 2 · Não feito, e por quê

- **Ficha do fornecedor** (histórico, prazo médio, ruptura) não nasce. É o supplier scorecard C11,
  segurado no `SPEC.md` §9. O próprio protótipo já mostra a aba como estado vazio
  ("a ficha do fornecedor ainda não existe aqui").
- A aba "Fornecedores" do protótipo (`compras-page.jsx`) deve virar link para a lista, **no Cowork**.
  O espelho não se edita.

## 3 · Pedido literal

> "Thread 03 — a aba 'Fornecedores' do protótipo de Compras: o que ela vira?" → **"Atalho pra lista existente (Recomendado)"** — [W] 2026-09-24.

## 4 · Descobertas

- O §1 do índice dizia "não existe `Pages/Fornecedor*`". É verdade, mas a lista **já existia**
  sob `Pages/Cliente/Index.tsx` com `type=supplier`. Não precisava de tela nova.

## 5 · Prefixo tocado

`Modules/Compras/Http/Controllers/DataController.php` (+5) · `resources/js/Pages/Compras/Index.charter.md` (+1) · este recibo.
