---
id: cowork-producao-cv-casos
casos: Painel de produção (comunicação visual) · rota pcv / pcv-* no protótipo
irmaos: producao-cv-page.jsx · producao-cv-os.jsx · producao-cv-campo.jsx · producao-cv-data.jsx · producao-cv.css (forma)
tecnica: Caso de uso = o que a pessoa faz + critério de aceite verificável (Dado/Quando/Então) + o teste que prova + se o protótipo já resolve
por_que: as telas existem só no protótipo. Sem os casos, o Code constrói pela aparência e testa o que renderiza, não o que a gráfica precisa.
owner: wagner
base: wagnerra23/oimpresso.com@main 0c29a8505b8b — memory/requisitos/Pcp/{SPEC,ADR-PROPOSAL,ROADMAP}.md · memory/research/2026-05-prospeccao/33-grade-producao-concorrentes.md
estado: protótipo pronto (2026-10-15) · backend inexistente · PCP autorizado e D1–D5 aprovados por [W] em 2026-10-15 (_DECISOES-W-2026-10-15.md) — módulo Modules/Producao
---

# Casos de uso — Painel de produção (OS como projeto)

> **Estatuto.** Derivados do protótipo e do SPEC do Pcp, **nunca de um `.tsx`** (não existe). Viram casos do
> `main` nas threads do playbook da Produção. Os caminhos de
> teste abaixo usam `Modules/Producao/` (D1 decidido em 2026-10-15).
>
> **Personas:** Wagner (gestor/PCP, 1440px) · operador de máquina (celular, toque ≥ 44px) · Larissa (balcão,
> 1280px) · instalador (celular, em campo).
>
> **Regras que valem para todos os UC:** `business_id` em toda leitura e escrita (ADR 0093) · apontamento é
> append-only (`pcp_appointments`, SPEC do Pcp) · horas e valores em PT-BR (vírgula decimal) · nenhum custo ou
> produtividade individual sem permissão (`producao.desempenho.ver`, LGPD Art. 7º).

## Como provar

| Camada | O que roda | Onde |
|---|---|---|
| **Contrato** | Pest, um `it('UC-PCV-NN · …')` por UC, tenant 98 × 99. Molde: `Modules/Crm/Tests/Feature/CrmPedidosContratoTest.php`. | `Modules/Producao/Tests/Feature/Producao<Vista>ContratoTest.php` (nome final = D1) |
| **Discriminante** | Todo teste semeia ao menos dois registros que só o critério certo separa (duas OS, uma atrasada e uma pronta; duas máquinas, uma acima do turno). | dentro do `it` |
| **Valor** | UC de custo e refugo provam a conta por dois caminhos: o número semeado conhecido e a soma devolvida (regra mestre de valor). Mudar fórmula de custo = aprovação [W]. | UC-PCV-15..18 |
| **Controle negativo** | Mutação citada no recibo: tirar o filtro de "pronto" e ver o UC-PCV-03 falhar. | `_saida-NN.md` |
| **Forma** | `design-diff --compare --check` contra o protótipo, escuro + claro, 1440 (gestor) / 1280 (balcão) / 390 (celular). | recibo da thread |
| **A11y** | A1–A12: alvo ≥ 44px no celular, foco visível, nada clicável sem nome, "Mover para…" sem arrastar, `role="timer"` no cronômetro. | recibo da thread |

**Legenda de estado:** ✅ o protótipo resolve (forma e regra visíveis) · 🟡 resolve em parte (diz o que falta) · ⬜ não resolve ainda.
Nenhum UC tem backend: ✅ quer dizer "pronto para virar Page", não "pronto em produção".

## Rastreabilidade

| UC | Caso de uso | Persona | Vista | Prio | Protótipo |
|----|-------------|---------|-------|------|-----------|
| UC-PCV-01 | Vejo o que trava a produção hoje e ajo dali | Gestor | Painel | must | ✅ |
| UC-PCV-02 | Os indicadores levam ao quadro já filtrado | Gestor | Painel | should | ✅ |
| UC-PCV-03 | "Atrasadas ou vencendo" é o mesmo número em toda a tela | Gestor | Painel · Quadro · Linha | must | ✅ |
| UC-PCV-04 | Fila de aprovação de arte com reenvio do link | Balcão | Painel | must | 🟡 |
| UC-PCV-05 | Quadro por setor ou por máquina, com a etapa atual | Gestor · Balcão | Quadro | must | ✅ |
| UC-PCV-06 | Filtro por texto, situação e responsável, igual no quadro e na linha | Gestor · Balcão | Quadro · Linha | must | ✅ |
| UC-PCV-07 | O PCP move uma etapa à mão, e isso fica registrado | Gestor | Quadro | should | 🟡 |
| UC-PCV-08 | Linha do tempo por máquina com fim de turno, bloqueio por arte e estouro | Gestor | Linha | must | ✅ |
| UC-PCV-09 | Gargalo previsto com sugestão de reprogramação aplicável e reversível | Gestor | Painel · Linha | should | ✅ |
| UC-PCV-10 | A OS é um projeto: etapas, responsável, máquina, previsto × apontado | Gestor · Balcão | Ficha | must | ✅ |
| UC-PCV-11 | A arte aprovada fica travada e anda com a OS | Balcão · Operador | Ficha · Apontamento | must | 🟡 |
| UC-PCV-12 | Roteiro por família vira as etapas da OS sem redigitar | Balcão | Roteiros → Ficha | must | 🟡 |
| UC-PCV-13 | Roteiro tem no máximo 2 trilhas em paralelo que se juntam | Gestor | Roteiros | must | ✅ |
| UC-PCV-14 | Etapa opcional entra pela opção marcada no orçamento | Balcão | Roteiros → Ficha | must | 🟡 |
| UC-PCV-15 | Custo real × esperado para o progresso, por OS | Gestor | Custo | must | ✅ |
| UC-PCV-16 | Material empenhado × consumido, com estouro sinalizado | Gestor | Ficha · Materiais | must | ✅ |
| UC-PCV-17 | Refugo com motivo entra no custo real | Operador · Gestor | Ficha · Apontamento | must | ✅ |
| UC-PCV-18 | Perdas por motivo e por máquina | Gestor | Custo | should | ✅ |
| UC-PCV-19 | O operador vê só as etapas dele hoje, na ordem | Operador | Apontamento | must | ✅ |
| UC-PCV-20 | QR abre a etapa certa e o cronômetro aponta início, pausa e fim | Operador | Apontamento | must | ✅ |
| UC-PCV-21 | Fechar a etapa passa a OS adiante e avisa o próximo | Operador | Apontamento | must | ✅ |
| UC-PCV-22 | Cronômetro esquecido fecha sozinho no fim do turno | Operador · Gestor | Apontamento | must | ⬜ |
| UC-PCV-23 | Apontamento sem internet guarda e envia depois | Operador | Apontamento | should | ⬜ |
| UC-PCV-24 | Agenda de instalação da equipe de campo | Gestor · Instalador | Instalação | must | ✅ |
| UC-PCV-25 | Checklist de campo com foto antes/depois e assinatura gera protocolo | Instalador | Instalação | must | 🟡 |
| UC-PCV-26 | Aproveitamento de mídia (sugestão de encaixe na versão 2) | Gestor | Painel | could | 🟡 |
| UC-PCV-27 | Produtividade por setor sem expor o indivíduo sem permissão | Gestor | Painel | should `[LGPD]` | 🟡 |
| UC-PCV-28 | Cada empresa vê só a sua produção | todos | todas | must `[T0]` | ⬜ |
| UC-PCV-29 | Uma fonte de eventos alimenta quadro, painel, linha, ficha, celular e instalação | todos | todas | must | ✅ |
| UC-PCV-30 | Reprogramar arrastando o bloco na linha do tempo (ou Alt + setas) | Gestor | Linha | must | ✅ |

---

## Painel

### UC-PCV-01 · Vejo o que trava a produção hoje e ajo dali · `must` · ✅
- **Persona:** Wagner, 8h da manhã.
- **Aceite:** Dado OS atrasadas, OS vencendo hoje, OS travadas por arte e uma máquina acima do turno · Quando abro o Painel · Então vejo os 4 indicadores e um alerta por problema, cada um com **uma ação** (ver na linha do tempo, abrir a OS) · E sem problema nenhum, nenhum alerta aparece (não há alerta decorativo).
- **Teste:** semear um caso de cada tipo + um dia limpo; o dia limpo devolve `alertas = []`.

### UC-PCV-02 · Os indicadores levam ao quadro já filtrado · `should` · ✅
- **Aceite:** Quando clico em "Atrasadas ou vencendo" · Então abre o Quadro com o filtro "Atrasadas ou vencendo" ligado e a contagem do filtro é igual à do indicador · Idem "Arte travando" · "Refugo" leva ao Custo.

### UC-PCV-03 · Um número só para atrasadas · `must` · ✅
- **Aceite:** Dado uma OS pronta com prazo vencido, uma em andamento vencida e uma vencendo hoje · Então o cabeçalho, o indicador e o filtro mostram **2** · E a OS pronta não conta.
- **Regra:** atrasada ou vencendo = prazo ≤ hoje **e** etapa atual ≠ pronto. Uma função só no servidor; as três telas leem dela.

### UC-PCV-04 · Fila de aprovação de arte · `must` · 🟡
- **Aceite:** Dado OS com arte "aguardando" e "ajuste pedido" · Então a fila mostra o que o cliente disse e quando · E "Reenviar link" reenvia o **mesmo** link de prova (não gera arte nova) · E "Enviar nova prova" só aparece em "ajuste pedido".
- **Falta:** o link de prova em si (tela pública de aprovação) não existe no protótipo da Produção. Reaproveitar o padrão do link com código do Repair (UC-COS-12..16) é decisão do Code com [W].

## Quadro e linha do tempo

### UC-PCV-05 · Quadro por setor ou por máquina · `must` · ✅
- **Aceite:** Dado OS em etapas de setores diferentes · Então cada OS aparece **uma vez**, na coluna da etapa atual · E "Por máquina" mostra só máquinas com OS · E o cartão traz nº, prioridade, cliente, etapa atual + máquina, progresso, prazo, pendência de arte e responsável.

### UC-PCV-06 · Filtro igual no quadro e na linha · `must` · ✅
- **Aceite:** Dado o filtro "Travadas por arte" + responsável "Marcos" · Quando troco do Quadro para a Linha · Então o filtro continua e os blocos visíveis são os das mesmas OS · E "Limpar" volta tudo.

### UC-PCV-07 · O PCP move uma etapa à mão · `should` · 🟡
- **Aceite:** Dado a OS no setor Impressão · Quando o PCP **arrasta o cartão** para a coluna Acabamento, ou usa ⋮ › "Mover para Acabamento" (teclado) · Então a OS muda de coluna · E o histórico da OS ganha "movida por <nome> de Impressão para Acabamento" · E o apontamento aberto na etapa anterior é fechado com motivo "movida pelo PCP".
- **Falta:** o fechamento do apontamento aberto. Permissão: `producao.etapa.mover` (aprovada).

### UC-PCV-08 · Linha do tempo por máquina · `must` · ✅
- **Aceite:** Dado blocos programados hoje · Então cada máquina mostra a carga em horas · E o bloco travado por arte aparece tracejado · E o bloco que termina depois das 18h tem borda de estouro · E Hoje/Amanhã troca o dia.

### UC-PCV-09 · Gargalo com sugestão aplicável · `should` · ✅
- **Aceite:** Dado a Roland com 10,4 h para um turno de 10 h e a Latex com folga · Então o alerta diz as duas cargas e sugere mover a OS de menor duração que resolve · Quando aplico · Então o bloco muda de máquina, a Roland cai para 9,3 h, o operador recebe aviso · E "Desfazer" restaura.
- **Regra:** sugestão por regra simples (D5 = a, ADR-PROPOSAL). Nunca move sozinho.

## A OS como projeto

### UC-PCV-10 · Ficha da OS · `must` · ✅
- **Aceite:** Abrir a OS mostra progresso, tempo apontado/previsto, custo real/orçado e as abas Etapas · Arte e arquivos · Materiais · Custo · Refugo · Histórico · Em Etapas cada linha traz setor, máquina, responsável, previsto × apontado (vermelho quando passa) e situação.
- **Forma:** drawer lateral de 760px (PT-02), não modal.

### UC-PCV-11 · Arte aprovada travada · `must` · 🟡
- **Aceite:** Dado a arte aprovada às 16:40 · Quando alguém troca o arquivo da arte · Então a OS volta para "aguardando aprovação" e um novo link é enviado · E o operador, no celular, abre sempre a versão aprovada.
- **Falta:** a troca de arquivo e o versionamento não estão no protótipo (só a lista).

### UC-PCV-12 · Roteiro vira etapas · `must` · 🟡
- **Aceite:** Dado o orçamento aprovado de um produto da família "Projeto com instalação" · Então a OS nasce com as etapas do roteiro, máquinas e tempos calculados (quantidade ÷ capacidade do posto) · E ninguém redigita etapa.
- **Falta:** o passo "orçamento aprovado → OS" não existe no protótipo; a ficha usa etapas já criadas.

### UC-PCV-13 · No máximo 2 trilhas · `must` · ✅
- **Aceite:** O roteiro aceita até 2 trilhas em paralelo, e elas sempre terminam numa etapa de junção nomeada · Uma 3ª trilha ou trilha sem junção é recusada ao salvar.
- **Por quê:** linha reta faz a impressão esperar a serralheria sem motivo; fluxograma livre ninguém treina.

### UC-PCV-14 · Etapa opcional pelo orçamento · `must` · 🟡
- **Aceite:** Dado o roteiro de adesivo com "Laminação · só com laminação" · Quando o orçamento vem **sem** laminação · Então a OS nasce sem essa etapa · Com laminação · Então nasce com ela.
- **Falta:** depende do UC-PCV-12.

## Custo, material e perdas

### UC-PCV-15 · Custo real × esperado · `must` valor · ✅
- **Aceite:** Dado uma OS orçada em 1.000,00, com 50% de progresso e 620,00 gastos · Então "esperado agora" = 500,00 e o desvio = +24% · E com 0% de progresso o esperado é 0,00 (sem dividir por zero).
- **Regra:** custo real = horas apontadas × custo-hora do posto + material baixado + refugo. Custo-hora é cadastro do posto, não constante.

### UC-PCV-16 · Empenhado × consumido · `must` · ✅
- **Aceite:** Dado 124 m² de lona empenhados e 133,4 m² baixados · Então o saldo mostra "estourou 9,4 m²" em vermelho · E o estouro entra no custo real e gera alerta ao PCP.
- **Integração:** empenho nasce do orçamento aprovado e reserva estoque; baixa vem do apontamento.

### UC-PCV-17 · Refugo com motivo · `must` valor · ✅
- **Aceite:** Quando o operador registra refugo (etapa, motivo da lista fechada, m²) · Então o refugo entra na aba Refugo e no custo real da OS · E o motivo é obrigatório.

### UC-PCV-18 · Perdas por motivo e por máquina · `should` · ✅
- **Aceite:** Dado refugos de 30 dias · Então o Custo mostra o total e o ranking por motivo e por máquina · E a soma das barras = o total (dupla prova).

## Celular do operador

### UC-PCV-19 · Minhas etapas de hoje · `must` · ✅
- **Aceite:** Dado o operador Diego · Quando abre o app · Então vê só as etapas atribuídas a ele hoje, na ordem programada, com máquina e horário · E não vê etapas de outro operador.

### UC-PCV-20 · QR e cronômetro · `must` · ✅
- **Aceite:** Quando lê o QR da OS · Então abre a etapa dele naquela OS (não a OS inteira) · E Iniciar/Pausar/Concluir gravam apontamentos append-only com usuário, máquina e horário · E o cronômetro tem `role="timer"` e botões ≥ 44px.

### UC-PCV-21 · Fechar a etapa passa adiante · `must` · ✅
- **Aceite:** Quando o operador conclui a etapa · Então a OS vai para a próxima etapa do roteiro (ou para a junção, se a outra trilha já terminou) · E o responsável seguinte é avisado.

### UC-PCV-22 · Cronômetro esquecido · `must` · ⬜
- **Aceite:** Dado um apontamento aberto às 18h · Então o sistema fecha às 18h com marca "fechado automaticamente" e avisa o PCP · E o operador pode corrigir no dia seguinte com justificativa (o original fica).

### UC-PCV-23 · Sem internet · `should` · ⬜
- **Aceite:** Dado o celular offline · Quando o operador aponta · Então o evento fica guardado com a hora real · E ao voltar a rede é enviado uma vez só (sem duplicar).

> **Modo terminal (tablet preso à máquina).** O caso é o UC-PCV-41, em `Producao-celular.casos.md` (forma T1 do `Mobile Producao.dc.html`, thread 12). Desde 2026-10-29 ele também está neste protótipo, na vista **Terminal**: romaneio do posto, entrada por crachá ou PIN, recusa de quem não opera o posto (US-PCP-030), botões de 88px e volta ao crachá depois de 2 minutos sem toque.

## Instalação

### UC-PCV-24 · Agenda de instalação · `must` · ✅
- **Aceite:** A agenda mostra dia, hora, cliente, endereço, OS, equipe, duração e veículo · E a OS só entra em "Instalação" quando todas as etapas de fábrica fecharam.

### UC-PCV-25 · Checklist com foto e assinatura · `must` · 🟡
- **Aceite:** Dado o checklist da instalação · Então "Concluir" só habilita com foto antes, foto depois e assinatura · E ao concluir nasce um protocolo (PDF) com fotos, checklist, horário e assinatura, anexado à OS e enviado ao cliente.
- **Falta:** captura real de câmera e assinatura na tela; o protótipo simula com toque.

## Transversais

### UC-PCV-26 · Encaixe de mídia · `could` · 🟡
- **Aceite (versão 1, D-ENCAIXE de 15/10):** Dado a mídia que o operador informou no apontamento das OS de uma bobina · Então mostra o % aproveitado e a sobra · E não mostra sugestão de agrupamento.
- **Versão 2:** a sugestão de agrupamento com a economia em m² e R$ (encaixe na bobina).
- **Falta:** o % ainda vem fixo no protótipo; deve sair da mídia informada no apontamento.

### UC-PCV-27 · Produtividade sem expor o indivíduo · `should` `[LGPD]` · 🟡
- **Aceite:** Sem `producao.desempenho.ver` · Então a produtividade aparece só por setor · Com a permissão · Então aparece por pessoa · E o acesso fica no log.
- **Falta:** a vista por pessoa. Permissão: `producao.desempenho.ver` (aprovada).

### UC-PCV-28 · Cada empresa vê só a sua produção · `must` `[T0]` · ⬜
- **Aceite:** Dado OS, máquinas, apontamentos e instalações das empresas 98 e 99 · Então nenhuma vista, contagem ou alerta da 98 mostra dado da 99 · E o QR de uma OS da 99 lido por operador da 98 responde "OS não encontrada".

### UC-PCV-30 · Reprogramar arrastando na linha do tempo · `must` · ✅
- **Aceite:** Dado um bloco da Roland às 14:30 · Quando o PCP arrasta para as 15:00 · Então ele encaixa de 15 em 15 min e o horário muda · Quando arrasta para a HP Latex (mesmo setor) · Então muda de máquina · Quando tenta soltar na CNC (outro setor) · Então a linha fica marcada como fora e nada muda · Quando o novo horário choca com outro bloco · Então recusa e diz com qual OS choca · Quando passa das 18h · Então move e marca estouro.
- **Teclado:** foco no bloco + Alt+←/→ (15 min) e Alt+↑/↓ (máquina do mesmo setor). Enter abre a OS.
- **Registro:** cada reprogramação vira evento (UC-PCV-29) e avisa o operador da máquina de destino no app (D-AVISO). Bloco concluído não move; bloco travado por arte move, mas segue travado.
- **Permissão:** `producao.etapa.mover` (a mesma do UC-PCV-07).

### UC-PCV-29 · Uma fonte de eventos para todas as telas · `must` · ✅
- **Aceite:** Dado um evento novo (operador inicia etapa, cliente aprova arte, PCP move ou reprograma, refugo, correção de apontamento, OS criada do orçamento, instalação concluída) · Então ele aparece **sem recarregar** no cartão do quadro (último evento), no painel lateral de Eventos do quadro, em "Acontecendo agora" no Painel, nos "Eventos das máquinas hoje" da Linha (se tiver máquina) e no Histórico da ficha · E é o mesmo registro em todas (append-only; correção é evento novo, não edição).
- **Filtros:** o painel de eventos do quadro mostra só eventos das OS visíveis no filtro; a Linha filtra pela máquina clicada.
- **Teste:** gravar um evento e ler as 5 superfícies pelo mesmo endpoint/consulta; um evento da empresa 99 não aparece em nenhuma (UC-PCV-28).
- **A11y:** as listas de eventos anunciam acréscimos (`aria-live="polite"`).

---

## Bateria a11y no protótipo (2026-10-15)

Sonda nas 7 vistas: elementos com foco sem nome **0** · `th` sem `scope` **0** · imagem sem `alt` **0** · um `h1` por vista · alvo < 44px no celular **1** (link "Abrir no mapa", 32px → corrigido para 44px).
Achado que **não é desta tela**: o ícone do `Alert` tom `info` do DS sai sem `aria-hidden` (1 por vista que usa info — Linha e Roteiros). Vai para `ds-atomos` como pedido de componente; a Page não deve contornar.
Não medido aqui: contraste (exige a sonda do protocolo com caso de sanidade) — fica para a A1 do Code.

## O que ainda falta (fora dos UC acima)

**Decisão [W]** — D-PCP, D1–D5 e permissões aprovados em 2026-10-15 (`_DECISOES-W-2026-10-15.md`). Restam as perguntas do formulário de 15/10 (relação com CV/Repair/Fabricação, piloto, turno, canal de aviso, empenho de estoque, link de prova, encaixe, ordem das fases).

**Forma (dá para fazer aqui, no protótipo)**
4. Ficha e linha do tempo respeitarem as trilhas em paralelo (hoje só o Roteiro mostra).
5. Passo "orçamento aprovado → OS" com escolha da família e das opções (UC-PCV-12, 14).
6. Estados carregando / vazio / erro em todas as vistas.
7. Link público de prova de arte (UC-PCV-04, 11) no padrão do link com código do Repair.
8. Correção de apontamento com justificativa (UC-PCV-22).

**Só no Code**
9. Tabelas `pcp_workstations`, `pcp_operations`, `pcp_appointments` (append-only), `pcp_schedules` (SPEC do Pcp).
10. Fechamento automático às 18h, fila offline, envio de aviso ao próximo responsável.
11. Encaixe de mídia (algoritmo), PDF do protocolo de instalação, captura de câmera e assinatura.
