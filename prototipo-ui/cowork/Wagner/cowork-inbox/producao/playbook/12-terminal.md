---
sessao: "12"
titulo: Modo terminal na máquina
dono: "[CL]"
forma: Mobile Producao.dc.html · T1
---
# 12 · Modo terminal na máquina

Mesma PWA em `/producao/terminal/{posto}`. Fica presa ao posto (token de aparelho, sem sessão de usuário). Mostra o romaneio do dia daquele posto e o apontamento rodando. Troca de operador por crachá QR (câmera) ou PIN de 4 dígitos; cada toque grava com o usuário identificado. Botões 88px. Inativo 2 min → volta à tela de crachá.

**Casos e teste:** UC-PCV-41 — `ProducaoTerminalContratoTest.php`. Um `it` por UC, tenant 98×99, discriminante e controle negativo no recibo.
