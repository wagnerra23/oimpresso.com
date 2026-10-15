---
sessao: "E09"
titulo: Instalação: chegada, fotos e prova máxima
dono: "[CL]"
forma: Mobile Producao.dc.html · I1, I2, I3, I5, I6
---
# E09 · Instalação: chegada, fotos e prova máxima

Emenda da thread 09.
- **I2:** "Cheguei" grava hora + geolocalização e abre o apontamento da equipe; veículo aponta como máquina (horas + km saída/volta).
- **I3:** foto antes e depois obrigatórias; compressão no aparelho (1600 px, ~300 KB) + carimbo de data/hora/local; original guardado 90 dias, comprimida sem prazo (D-FOTO).
- **I5:** traço do dedo + OTP de 4 dígitos no WhatsApp do cliente (servidor envia). Sem sinal: assina e o OTP vai depois por link.
- **I6:** PDF com fotos, checklist, horários, local, aparelho, OTP confirmado e `sha256` do PDF gravado na OS; envio pelo servidor; OS → Entregue.

**Casos e teste:** UC-PCV-38, UC-PCV-39 — `ProducaoInstalacaoContratoTest.php`. Um `it` por UC, tenant 98×99, discriminante e controle negativo no recibo.
