---
sessao: "11"
titulo: Proposta de ADR — entrada por XML de compra
autor: "[CL]"
data: 2026-10-06
base: wagnerra23/oimpresso.com@main a8e0624504 (pós-#8806)
medido_em: produção Hostinger, 2026-10-06 17:44 (relógio do MySQL), só SELECT COUNT
prefixo_tocado: memory/decisions/proposals/2026-10-06-entrada-xml-compra.md + este arquivo
---
# _saida-11 · ADR de entrada por XML

## 1 · Feito
- `memory/decisions/proposals/2026-10-06-entrada-xml-compra.md` — `status: proposto`, cita UC-TRB-18 e UC-TRB-12.
  Propõe: NfeBrasil lê e converte, Compras cria a compra (reaproveita a US-COM-003, sem caminho novo);
  DF-e **e** upload pelo mesmo parser, upload recusado se o CNPJ do destinatário não for o do business;
  de-para lembrado por `(business_id, CNPJ do fornecedor, código do item)` → `variation_id`;
  CFOP espelho com lei citada; NCM divergente só avisa; CSOSN 500 só como sugestão confirmada;
  custo × crédito por regime sob a regra mestre de valor; importação 3102 como fase 5, dependente do motor.
- Testes: nenhum. A thread é só papel; não há código para testar.

## 2 · Não feito e por quê
- **Não citei o dispositivo legal da tabela CFOP.** A lei 4 exige citação literal; afirmar o artigo sem
  ler a fonte seria inventar. Fica como trabalho da fase 3, registrado na proposta (§3 D4).
- **Não investiguei por que o NSU nunca trouxe documento** (0 em produção). É fase 0 da proposta, sem dono.

## 3 · Pedido literal pro [W]
Responder as 5 perguntas da §6 da proposta: (1) dono Compras + NfeBrasil? (2) upload além do DF-e?
(3) CSOSN 500 só como sugestão? (4) importação nesta ADR ou em ADR própria? (5) quem pega a fase 0?
Ratificar = PR que troca `status: proposto` → `aceito`.

## 4 · Descobertas que mudam outra sessão
- **`nfe_dfe_recebidos` = 0 e `nfe_dfe_itens` = 0 em produção**; `nfe_dfe_nsu_state` = 1. A fonte DF-e
  nunca entregou documento. Afeta qualquer thread que conte com "entradas manifestadas" (saúde fiscal 14,
  checagem CT07 "entradas sem manifestação" do protótipo).
- **O 🔴 do §4 do índice está velho:** `ManifestacaoService.php:266` e `DistribuicaoDfeService.php:385`
  hoje selecionam só `name, tax_number_1`, sem a coluna `state` (último commit na pasta: `2e847f0c3a`,
  2026-09-04). A linha *"nenhuma manifestação chega à SEFAZ"* precisa ser reconferida antes de virar
  thread. Não editei o índice (não é do meu prefixo).
- **US-NFE-049 está `done` sem `produto_id_match`**: o aceite prometia a coluna, a migration não a cria.
  A proposta a absorve (fase 1), com `variation_id` no lugar.
- `app/Http/Controllers/PurchaseXmlController.php` e `NfeEntradaController.php` não têm rota: código morto.

## 5 · Prefixo tocado
Só os dois arquivos acima.
