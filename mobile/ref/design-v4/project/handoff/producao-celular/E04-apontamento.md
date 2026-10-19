---
sessao: "E04"
titulo: Celular: dois cronômetros, pausa com motivo, etapa alheia, saída do ponto
dono: "[CL]"
forma: Mobile Producao.dc.html · A1, A2, A3, A4, A7, A8
---
# E04 · Celular: dois cronômetros, pausa com motivo, etapa alheia, saída do ponto

Emenda da thread 04.
- **A1:** bloco "Rodando agora" lista o apontamento de pessoa + os de máquina iniciados pelo usuário; linha do ponto (entrada, presença).
- **A2:** QR de etapa de outra pessoa ou de outra etapa → mensagem com etapa e responsável atuais; **nenhum** apontamento gravado.
- **A3:** etapa de máquina mostra "Deixar rodando e fazer outra etapa" (volta ao A1 sem pausar).
- **A4:** pausar exige motivo do cadastro do posto.
- **A7 (se D-PONTO):** apontamento de pessoa aberto fecha no horário da saída do ponto; sem saída, no fim do turno. Horas de pessoa no dia ≤ presença; excedente > 30 min vira pendência do gestor. Ler o Ponto só por Service; nunca escrever.
- **A8:** fila local com chave de idempotência; envio repetido não duplica.

**Casos e teste:** UC-PCV-31, UC-PCV-34, UC-PCV-35, UC-PCV-36 — `ProducaoApontamentoContratoTest.php`. Um `it` por UC, tenant 98×99, discriminante e controle negativo no recibo.
