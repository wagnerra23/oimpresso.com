---
sessao: "decisoes-w"
titulo: "Produção — PCP destravado; módulo Modules/Producao; D1–D5 e permissões aprovados"
executor: "[CC]"
data: 2026-10-15
base: wagnerra23/oimpresso.com@main 0c29a8505b8b
---
# _DECISOES-W-2026-10-15 — Produção (PCP)

> **Fonte:** [W] 2026-10-15, no chat: *"eu autorizo tudo, nome da pasta 'Producao' · e sim quero PCP e autorizado fazer"*.
> Onde [W] disse "autorizo tudo", vale a recomendação registrada no `memory/requisitos/Pcp/ADR-PROPOSAL.md`.

| id | pergunta | resposta | efeito |
|---|---|---|---|
| D-PCP | O PCP sai do "parado" da ADR 0152? | **Sim, autorizado fazer.** | O Code escreve a ADR que tira o PCP do estado de desejo (supersede da 0152 no ponto do PCP) e move o ADR-PROPOSAL para `accepted`. |
| D1 | Nome e pasta do módulo | **`Modules/Producao`** (não `Pcp`, não renomear `IProduction`) | Prefixo de rota `/producao`, permissões `producao.*`. `Modules/IProduction` (stub) é apagado na mesma ADR, se o Code confirmar que está vazio. |
| D2 | QR: PWA ou app nativo | **PWA** (Inertia + câmera do navegador) | Capacitor só se a câmera falhar em mais de 2 aparelhos. |
| D3 | Cronômetro | **Híbrido, padrão automático** (início/pausa/fim pelo QR; lançamento manual com justificativa) | `manual_entry` no apontamento; fechamento automático no fim do turno (UC-PCV-22). |
| D4 | Capacidade das máquinas | **Configurável por empresa**, com catálogo de valores sugeridos | Coluna de capacidade e custo-hora por posto, editável. |
| D5 | Gargalo | **Regra simples na V1**; análise por IA fica para depois | Sugestão nunca move sozinha (UC-PCV-09). |
| D-PERM | Quem move etapa e quem vê produtividade por pessoa | **Aprovado como proposto:** `producao.etapa.mover` e `producao.desempenho.ver` | Sem a 2ª, produtividade só por setor (UC-PCV-27). |

## 2ª rodada (formulário, mesmo dia — opção recomendada em todas)

| id | pergunta | resposta |
|---|---|---|
| D-RELACAO | Relação com CV, Assistência, Oficina e Fabricação | **Produção é a camada única de PCP.** CV, Assistência (Repair) e Oficina continuam donos da OS; as OS deles aparecem no quadro. Fabricação (receitas) fica como fonte de material. |
| D-PILOTO | Quem estreia | **biz=1 (WR2)**, depois um cliente de comunicação visual. |
| D-TURNO | Turno e fechamento automático | **Configurável por empresa**, padrão 08h–18h; o cronômetro aberto fecha no fim do turno (UC-PCV-22). |
| D-AVISO | Aviso ao próximo responsável | **Notificação dentro do app** (sino + celular do operador). WhatsApp só para o cliente. |
| D-EMPENHO | Reserva de material | **Sim:** o orçamento aprovado reserva o material; a baixa sai do apontamento (UC-PCV-16). |
| D-PROVA | Link de aprovação de arte | **Reaproveitar o link com código do Repair** (`/os/{codigo}`, Repair 06): mesma página, com o bloco de aprovar arte. |
| D-ENCAIXE | Encaixe de mídia | **Versão 2.** Na 1ª só % aproveitado com o que o operador informa (UC-PCV-26). |
| D-FASES | Ordem de entrega | **ROADMAP do Pcp:** fundo e cadastros → celular → quadro e linha → custo e instalação → virada. |
