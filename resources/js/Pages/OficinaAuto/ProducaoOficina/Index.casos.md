---
casos: Produção / Kanban da Oficina · /oficina-auto/producao
irmaos: Index.charter.md (lei) · Index.decisoes.md (debate)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-06-02"
---

# Casos de Uso & Aceite — Produção / Kanban da Oficina

> **Importado do handoff de design (Cowork) 2026-06-04.** Os checks `live` foram escritos contra o protótipo de design (`oficina-page.jsx`); ao virar teste real, mapear pro código de produção [`Index.tsx`](Index.tsx). Estados refletem o **modelo (A) reparo** travado no charter, não as colunas caçamba do código atual (dívida F3).
>
> **Como roda:** `live` = checável no protótipo · `static` = wiring no código (grep) · `manual` = [W]/usuário confirma visual.
> **Status:** ✅ passa · 🧪 em teste · ⬜ não verificado · ❌ quebrou (regressão → vira lição).

---

## UC-01 · Ver o pátio num relance
- **Persona:** Larissa (balcão, 1280px), 8h da manhã.
- **Como usa:** abre Oficina e vê todas as OS organizadas por etapa, sem rolar, pra saber o que está pegando.
- **Aceite:** Dado a tela carregada · Quando foco=Etapa · Então **5 colunas** (Recepção → Diagnóstico → Aguardando peças → Em execução → Pronto), cada uma com a contagem de OS.
- **Status: ✅**

## UC-02 · Os números do dia
- **Persona:** Larissa / Wagner (governança).
- **Aceite:** Então **6 KPIs** com valor numérico (Recepção · Diagnóstico · Aguardando peças · Execução · Urgentes · Valor em curso).
- **Status: ✅**

## UC-03 · Achar uma OS na hora
- **Persona:** Larissa (cliente ligou citando a placa).
- **Aceite:** Quando digita na busca (placa/nome/sintoma/#OS) · Então o kanban mostra só as OS que casam + contador de resultados.
- **Status: ✅**

## UC-04 · Reorganizar por box ou mecânico
- **Persona:** gestor de oficina.
- **Aceite:** Quando foco=Mecânico · Então as colunas viram os mecânicos (não as etapas).
- **Status: ✅** (design validado 2026-06-02: 5 colunas João/Pedro/Carlos/Diego + Sem mecânico).

## UC-05 · Abrir a OS como documento vivo
- **Persona:** Larissa / mecânico (tablet).
- **Aceite:** Quando clica no card · Então abre o **drawer** com as **11 seções na ordem travada** do charter (🔒). Fecha no backdrop/✕.
- **Status: ✅ (drawer travado)**

## UC-06 · Avançar de etapa sem furar a regra (D-01)
- **Persona:** mecânico.
- **Como usa:** arrasta o card pra próxima coluna (ou clica o botão "Triagem→/Iniciar→/Entregar→"); só avança se o gate da etapa estiver completo. Se faltar algo, o drawer abre no que falta.
- **Aceite:** Dado gate completo · Quando solta/clica · Então avança (toast verde). Dado gate incompleto · Então não avança, abre o drawer no checklist (toast âmbar).
- **Status: 🧪 (aguarda veredito [W] — ver D-01)**

## UC-07 · Cadastrar um carro que chegou
- **Persona:** Larissa (veículo no balcão).
- **Aceite:** Quando clica "Nova OS" · Então abre o drawer de criação; salvar adiciona o card em Recepção.
- **Status: ✅**

## UC-08 · Ver o que vira nota quando o carro fica pronto
- **Persona:** Larissa (entrega).
- **Aceite:** Dado stage=pronto + venda derivada · Então o drawer mostra o card Vendas×Oficina com Total · Peças(NF-e) · MO(NFS-e) + badges fiscais.
- **Status: ⬜** (depende de venda derivada + impl da seção 2 do drawer — ver D-09).

## UC-09 · Ritmo de balcão sem pensar em imposto
- **Persona:** Larissa (1280px, fila no balcão).
- **Aceite:** Dado viewport 1280px · Então **sem scroll horizontal**; colunas rolam internamente.
- **Status: ✅** (design validado 2026-06-02: overflow 0px).

## UC-10 · Trabalhar do jeito que prefere
- **Persona:** Larissa (calmo) vs. dia de pico (pressão).
- **Aceite:** Quando muda Pressão=calmo · Então a tira de urgente some; Pressão=pressão · Então urgentes pulsam.
- **Status: ✅** (design validado 2026-06-02: `ofc-mood-calmo`).

---

## Como rodar a suíte
1. **Static:** grep do componente — o wiring de cada UC existe? (pega regressão de "sumiu a função").
2. **Live:** rota Produção → checar os asserts `live`.
3. **Cadência:** rodar ao fim de toda mexida na Oficina. UC que vira ❌ = regressão → lição + conserto antes de seguir.

## Trilha do tempo
- 2026-06-02 · [CC] criou a suíte (10 UCs) a partir do inventário aprovado do charter (design).
- 2026-06-04 · [CC] importado pro repo via handoff. Pointers `Index.*`; nota de mapeamento design→produção adicionada.
