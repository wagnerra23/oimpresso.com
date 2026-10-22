---
sessao: "10"
titulo: Trava da arte aprovada (UC-PCV-11) e link para a prova a partir de /os/{codigo}
dono: "[CL]"
base: 0c29a8505b8b
---
# 10 · Trava da arte aprovada e link para a prova a partir de /os/{codigo}

**A página de aprovação já existe e fica (D-ARTE-PAGINA, [W] 2026-10-21):** *"Mantém a /arte/{token} do #9645; a thread 10 da Produção passa a ser só a trava da arte (UC-PCV-11) e o link a partir de /os/{codigo} depois da 06."* O cliente aprova ou recusa em `/arte/{token}` (Blade pública, token de uso único, `pcp_aprovacoes_arte`).

O que sobra para esta thread:
1. **Trava (UC-PCV-11):** arte aprovada fica travada; trocar o arquivo da prova depois da aprovação pede nova aprovação do cliente. Não depende de nada e pode ser feita já.
2. **Link a partir de `/os/{codigo}`:** a página pública da OS (thread Repair 06) leva à prova de arte pendente. Só depois da Repair 06.

A fila de aprovação de arte (UC-PCV-04) foi entregue no Painel da thread 06.

**Casos e teste:** `cowork-inbox/producao/Producao.casos.md` — UC-PCV-11, em `ProducaoAprovacaoArteContratoTest.php`. Um `it` por UC, tenant 98×99, discriminante e controle negativo no recibo.
