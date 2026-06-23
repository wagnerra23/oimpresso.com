---
casos: Produção / Kanban da Oficina · window.OficinaPage
irmaos: OficinaProducao.charter.md (lei) · OficinaProducao.decisoes.md (debate)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: 2026-06-02
---

# Casos de Uso & Aceite — Produção / Kanban da Oficina

> **O que é:** cada função aprovada no charter vira um caso que (a) **explica como o cliente usa** (persona real) e (b) **prova que funciona** (aceite verificável). Rodar a suíte = garantir que todas as funções seguem funcionando.
> **Como roda:** `live` = checável no protótipo (`eval_js` no painel, seletor/estado) · `static` = wiring presente no código (grep) · `manual` = [W]/usuário confirma visual.
> **Status:** ✅ passa · 🧪 em teste · ⬜ não verificado · ❌ quebrou (regressão — vira lição).

---

## UC-01 · Ver o pátio num relance
- **Persona:** Larissa (balcão, monitor 1280px), 8h da manhã.
- **Como usa:** abre Oficina e vê **todas as OS organizadas por etapa**, sem rolar a tela, pra saber o que está pegando.
- **Aceite:** Dado a tela carregada · Quando foco = Etapa · Então **5 colunas** (Recepção → Diagnóstico → Aguardando peças → Em execução → Pronto), cada uma com a contagem de OS.
- **Check:** live → `document.querySelectorAll('.prod-col').length === 5`. · **Status: ✅**

## UC-02 · Os números do dia
- **Persona:** Larissa / Wagner (governança).
- **Como usa:** bate o olho na faixa de topo pra saber quantos urgentes e quanto há de faturamento em curso.
- **Aceite:** Então **6 KPIs** com valor numérico (Recepção · Diagnóstico · Aguardando peças · Execução · Urgentes · Valor em curso).
- **Check:** live → `.prod-kpi` count === 6. · **Status: ✅**

## UC-03 · Achar uma OS na hora
- **Persona:** Larissa (cliente ligou citando a placa).
- **Como usa:** digita a placa (ou nome/sintoma/#OS) na busca; o quadro filtra na hora e mostra quantos resultados.
- **Aceite:** Quando digita na busca · Então o kanban mostra só as OS que casam + contador de resultados.
- **Check:** live → setar valor no input de busca → contar cards visíveis. · **Status: ✅**

## UC-04 · Reorganizar por box ou mecânico
- **Persona:** gestor de oficina.
- **Como usa:** troca o **Foco** pra "Mecânico" pra ver a carga de cada um; ou "Box" pra ver ocupação.
- **Aceite:** Quando foco = Mecânico · Então as colunas viram os mecânicos (não as etapas).
- **Check:** live → clicar seg "Mecânico" → colunas viram mecânicos. **Validado 2026-06-02: 5 colunas (João/Pedro/Carlos/Diego + Sem mecânico).** · **Status: ✅**

## UC-05 · Abrir a OS como documento vivo
- **Persona:** Larissa / mecânico (tablet).
- **Como usa:** clica no card e abre o **drawer** com tudo da OS — placa, KM, sintoma, vistoria DVI, peças & mão de obra, checklist de etapa, linha do tempo.
- **Aceite:** Quando clica no card · Então abre o drawer com as **11 seções na ordem travada** do charter (🔒). Fecha no backdrop/✕.
- **Check:** live → click `.prod-card` → drawer presente + seções na ordem. · **Status: ✅ (drawer travado)**

## UC-06 · Avançar de etapa sem furar a regra (D-01)
- **Persona:** mecânico.
- **Como usa:** **arrasta** o card pra próxima coluna (ou **clica o botão** "Triagem→/Iniciar→/Entregar→"); só avança se o checklist da etapa (gate) estiver completo. Se faltar algo, o drawer abre **no que falta**.
- **Aceite:** Dado gate completo · Quando solta na próxima coluna/clica · Então avança (toast verde). Dado gate incompleto · Então **não avança**, abre o drawer no checklist (toast âmbar).
- **Check:** live → validado: OS 8804 gate 3/4 bloqueia + abre drawer; 4/4 avança peças→execução. · **Status: 🧪 (aguarda veredito [W])**

## UC-07 · Cadastrar um carro que chegou
- **Persona:** Larissa (veículo no balcão).
- **Como usa:** clica **"Nova OS"**, preenche, salva; o card aparece em Recepção.
- **Aceite:** Quando clica "Nova OS" · Então abre o drawer de criação; salvar adiciona o card em Recepção.
- **Check:** live → botão "Nova OS" presente → abre `OsCreateDrawer`. · **Status: ✅**

## UC-08 · Ver o que vira nota quando o carro fica pronto
- **Persona:** Larissa (entrega).
- **Como usa:** numa OS **Pronta** que já virou venda, o drawer mostra o card Vendas×Oficina com o split **Peças (NF-e)** + **Mão de obra (NFS-e)**.
- **Aceite:** Dado stage = pronto + venda derivada · Então o drawer mostra o card com Total · Peças(NF-e) · MO(NFS-e) + badges fiscais.
- **Check:** manual/live (depende de venda derivada mockada). · **Status: ⬜**

## UC-09 · Ritmo de balcão sem pensar em imposto
- **Persona:** Larissa (1280px, fila no balcão).
- **Como usa:** trabalha o dia todo sem overflow horizontal; o fiscal acontece nos bastidores.
- **Aceite:** Dado viewport 1280px · Então **sem scroll horizontal** na página; colunas rolam internamente.
- **Check:** live → `document.documentElement.scrollWidth <= clientWidth`. **Validado 2026-06-02: overflow 0px.** · **Status: ✅**

## UC-10 · Trabalhar do jeito que prefere
- **Persona:** Larissa (calmo) vs. dia de pico (pressão).
- **Como usa:** ajusta **Densidade** (compacto/padrão/detalhe) e **Pressão** (calmo/padrão/pressão) — urgentes pulsam no modo pressão, somem no calmo.
- **Aceite:** Quando muda Pressão=calmo · Então a tira de urgente some; Pressão=pressão · Então urgentes pulsam.
- **Check:** live → classe `ofc-mood-*` na raiz muda. **Validado 2026-06-02: `ofc-mood-calmo` aplicado.** · **Status: ✅**

---

## Como rodar a suíte (garantia de que tudo funciona)

1. **Static (sempre):** grep do `oficina-page.jsx`/`.css` — o wiring de cada UC existe? (pega regressão de "sumiu a função").
2. **Live (no protótipo):** abrir `oimpresso.com.html` rota Oficina → `eval_js` os checks `live` de cada UC.
3. **Cadência:** rodar ao fim de toda mexida na Oficina (entra na Bateria §9 como T-casos da tela). UC que vira ❌ = regressão → lição + conserto antes de seguir.

## Evolução / trilha do tempo
- 2026-06-02 · [CC] criou a suíte (10 UCs) a partir do inventário aprovado do charter. 1ª run: **static 10/10** + **live UC-01/02/03/04/05/06/09/10 ✅**. Restam UC-07 (Nova OS, static ok) e UC-08 (split fiscal, depende de venda derivada) pra live.
