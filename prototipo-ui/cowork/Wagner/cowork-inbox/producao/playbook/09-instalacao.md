---
sessao: "09"
titulo: Instalação — checklist com foto antes/depois, assinatura e protocolo em PDF (a agenda é a da ComVis)
dono: "[CL]"
base: 0c29a8505b8b
---
# 09 · Instalação: checklist com foto antes/depois, assinatura e protocolo em PDF

Celular do instalador: checklist, foto antes/depois, assinatura na tela, protocolo em PDF anexado à OS e enviado ao cliente pelo WhatsApp (só cliente, D-AVISO).

**A agenda não é desta thread (D-AGENDA, [W] 2026-10-21):** *"A thread 09 reaproveita a agenda da ComVis (#9721) e fica só com checklist, fotos, assinatura e PDF (UC-PCV-25)."* Uma agenda só: a instalação agendada vem da `/comunicacao-visual/agenda` (#9721), lida pelo serviço `AgendaInstalacao` ou pelo evento `InstalacaoAgendada`. Nada de tabela nem tela de agenda no `Modules/Producao`, e nenhuma escrita em `Modules/ComunicacaoVisual`. O UC-PCV-24 fica atendido pela agenda da ComVis.

**Depende de:** thread 06 e do #9721 mergeado.

**Casos e teste:** `cowork-inbox/producao/Producao.casos.md` — UC-PCV-25. Um `it` por UC, tenant 98×99, discriminante e controle negativo no recibo.
