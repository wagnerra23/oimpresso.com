---
casos: Compras · window.ComprasPage (compras-page.jsx)
irmaos: Compras.charter.md · compras-page.css
tecnica: Caso de uso = narrativa do cliente + aceite verificável (Dado/Quando/Então)
nota_tela: 9.4 (migrado DS roxo 295)
owner: wagner · last_run: 2026-06-02
---

# Casos de Uso & Aceite — Compras

> Derivados do código real (`compras-page.jsx`). FSM: rascunho → pedido → trânsito → recebido → conferido → pago.

## UC-K01 · Ver compras por status
- **Persona:** Eliana (financeiro). **Como usa:** alterna Todas / A pagar / Rascunhos / Em trânsito pra focar no que importa.
- **Aceite:** Quando clica um filtro · Então a lista mostra só as compras daquele estado + contagem.
- **Check:** static `filter==="abertas"/"rascunhos"/"transito"`. · **Status: ✅ static**

## UC-K02 · Os números de compras
- **Persona:** Eliana/Wagner. **Como usa:** vê A pagar, Em trânsito, Volume do mês, Fornecedores ativos.
- **Aceite:** Então 4 KPIs com valor (`kpi.aberto/transito/mes/fornec`).
- **Check:** static bloco `.kpis` + 4 `.kpi`. · **Status: ✅ static**

## UC-K03 · Entrar nota por XML ou nova compra
- **Persona:** Eliana. **Como usa:** "Importar XML" (entra a NF-e do fornecedor) ou "Nova compra" manual.
- **Aceite:** Quando clica · Então abre importação XML / criação.
- **Check:** static botões "Importar XML" + "Nova compra" + atalho I/N. · **Status: ✅ static**

## UC-K04 · Abrir a compra (FSM + abas)
- **Persona:** Eliana. **Como usa:** clica a compra → drawer com o trilho FSM (onde está) + abas (itens, etc.).
- **Aceite:** Quando clica · Então drawer com `fsm-track` marcando a etapa atual (`now`/`done`).
- **Check:** static `DrawerView` + `fsm-step` + `stageIdx`. · **Status: ✅ static · live ⬜**

## UC-K05 · Avançar pela ação certa da etapa
- **Persona:** Eliana. **Como usa:** a ação muda com a etapa — "Marcar recebida" (trânsito), "Conferir itens" (recebido), "Pagar agora" (conferido+devendo), "Enviar pedido" (rascunho).
- **Aceite:** Dado o stage · Então o botão de ação correspondente aparece no rodapé do drawer.
- **Check:** static os 4 botões condicionais por `p.stage`. · **Status: ✅ static · live ⬜**

## UC-K06 · Achar uma compra
- **Persona:** Eliana. **Como usa:** busca por NF-e, fornecedor, ref ou chave SEFAZ.
- **Aceite:** Quando digita · Então filtra; atalho `/` foca a busca.
- **Check:** static input placeholder "Buscar NF-e, fornecedor, ref, chave". · **Status: ✅ static · live ⬜**

## Evolução
- 2026-06-02 · [CC] criou a suíte (6 UCs) grounded em `compras-page.jsx`. Static a seguir; live pendente (rota Compras).
