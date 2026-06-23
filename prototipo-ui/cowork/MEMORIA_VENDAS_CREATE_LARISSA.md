# MEMÓRIA — Vendas/Create · reclamações reais da Larissa (produção)

> Capturado 2026-05-28 por [CC] a partir de relato direto do Wagner.
> Fonte de dor: tela `/sells/create` em produção (ROTA LIVRE · Larissa · balcão 1280×1024).
> Objetivo: **não repetir esses erros** no rebuild POS.

## 🔴 BUG CRÍTICO — valores errados (× por 10 / ponto no lugar da vírgula)

**Sintoma relatado:** "o `.` no lugar da vírgula, e multiplicou por 10 tudo."

**Causa raiz:** conta feita em cima de **string formatada** pt-BR. No Brasil `R$ 1.500,00`
(`,` = decimal, `.` = milhar). `parseFloat` / troca `,`↔`.` mal feita → ponto vira decimal,
valor explode ou encolhe. O `×10` é deslocamento de 1 dígito pela máscara de entrada.

**REGRA CANÔNICA (aplicar sempre em qualquer tela com dinheiro):**
1. Valor monetário = **inteiro em centavos** no estado (`3800`, não `"R$ 38,00"` nem `38.0`).
2. Exibição só na renderização via `Intl.NumberFormat("pt-BR",{style:"currency",currency:"BRL"})`.
3. Entrada = máscara pt-BR que monta centavos da direita pra esquerda. **NUNCA** `parseFloat`
   de display string.
4. Multiplicação/desconto/parcela = aritmética inteira em centavos; divide só pra exibir parcela.
5. Teste anti-regressão: digitar `1500` deve virar R$ 15,00 (não R$ 1.500,00 nem R$ 150,00).

## 🟡 Features que EXISTIRAM e foram perdidas (vendas-create-completo.jsx)

A versão rica foi abandonada na hora de simplificar. Larissa sente falta:
- **Bip de código de barras** (scanner USB = "digita" código + Enter) + botão câmera.
- **"Consumidor Final" walk-in** num clique.
- **Cálculo de m² automático** (largura × altura × qtd × preço) p/ comunicação visual.
- **Inferência fiscal** (NFC-e balcão / NF-e c/ CNPJ / NFS-e serviço).

## 🟢 Pedidos novos da Larissa (este relato)

1. **Bip → consulta → adiciona item** com a busca SEMPRE focada (fluxo de scanner contínuo).
2. **Consulta de cliente → se não achar, cadastra inline** sem sair da venda.
3. **Impressão** — o POS já imprime; o Create precisa imprimir também (recibo/DANFE).

## Decisões de design (Wagner 2026-05-28)

- **Page header gigante:** cortar → barra fina.
- **Footer fixo com KPIs:** remover → total num **painel de ticket sempre visível**.
- **Padrão alvo:** **POS 2 painéis** (esquerda = bip/busca + itens; direita = cliente + pagamento + total + salvar/imprimir). Zero scroll, scanner sempre pronto.
- ⚠️ Muda o charter `Sells/Create` (sai de pills+4 KPIs) → exige amendment + revisar os 39 testes anti-regressão (`SellsCreatePageTest.php`).

## ⚙️ Gotcha técnico (rebuild 2026-05-28)

Input monetário **não pode** ser componente custom (`<CentsInput value=…/>`) no Cowork:
a instrumentação de edição da plataforma (`data-om-id`) mistura o prop `value` e o campo
renderiza cru ("8000" em vez de "80,00"). **Inlinar o `<input value={fmt(x)} onChange=…/>`
direto no JSX** (igual ao input de qtd, que funciona). Validado: digitar `12550` → `125,50`,
desconto `2000` → total `105,50`. Sem ×10.
