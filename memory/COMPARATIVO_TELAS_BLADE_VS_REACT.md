# Comparativo — Telas Blade (AdminLTE) vs React (Inertia+shadcn)

> Análise tela a tela do módulo PontoWR2 após a migração para React.
> Identifica funcionalidades perdidas, ganhos de usabilidade e o que vale recriar.

**Última atualização:** 2026-04-22 (sessão 10, pós-F13.4)

---

## 🧭 Resumo executivo

**React ganhou em:**
- Design moderno (shadcn/ui, dark mode por usuário, responsividade real mobile)
- UX (AlertDialog/Dialog confirmação, Toast sonner em todas ações, KPIs clicáveis, navegação SPA instantânea)
- Features novas: gráfico 7 dias no Dashboard, campo IA nas Intercorrências, busca debounced, polling em jobs assíncronos
- Menos risco operacional: botão "Deletar Escala" removido (colaboradores perderiam referência)

**Blade antigo é MAIS completo em:**
- **Intercorrências:** botões Editar e Submeter na lista (faltam no React — workflow trava)
- **Importações:** download do arquivo original (faltou — afeta auditoria legal)
- **Espelho:** busca por matrícula/nome/CPF (placeholder "em breve" no React)

**Veredito geral:** React está ~85% completo em relação ao Blade. As 3 ausências acima são críticas e devem ser priorizadas antes de considerar o Blade como "legado a deprecar".

---

## 📋 Tela a tela

### Dashboard

| Item | Blade | React |
|---|---|---|
| 6 KPIs com cores | ✅ | ✅ |
| Fila aprovações pendentes | ✅ | ✅ |
| Atividade recente (timeline) | ✅ com NSR | ✅ com REP identificador |
| Gráfico 7 dias | ❌ | ✅ **NOVO** |

**Veredito:** React ganhou. NSR → REP.identificador é diferença semântica menor.

### Aprovações

| Item | Blade | React |
|---|---|---|
| Filtros estado/tipo | ✅ | ✅ |
| Filtros prioridade | ❌ | ✅ |
| KPIs por estado clicáveis | ❌ | ✅ |
| Aprovar/Rejeitar inline | ✅ (confirm nativo) | ✅ (AlertDialog + Dialog com motivo) |
| **Aprovação em lote** | ❌ | ❌ |
| Paginação preservando filtros | ✅ | ✅ |

**Veredito:** React ganhou em UX. Ambos sem batch.

### Espelho — Index

| Item | Blade | React |
|---|---|---|
| Filtro mês | ✅ | ✅ reativo (sem botão Buscar) |
| **Busca matrícula/nome/CPF** | ✅ | ⚠️ disabled ("em breve") |
| Listagem + paginação | ✅ | ✅ |

**Veredito:** React perdeu a busca. **Recriar — alto valor.**

### Espelho — Show

| Item | Blade | React |
|---|---|---|
| Totalizadores mensais (6) | ✅ | ✅ |
| Navegação mês anterior/próximo | ✅ | ✅ |
| Botão "Imprimir PDF" | ✅ | ✅ |
| Tabela dia-a-dia | ✅ | ✅ com destaque weekend + divergências |
| Chips de marcações por dia | ✅ | ✅ com tipagem |

**Veredito:** React ganhou em clareza visual (dia destacado, tabela compacta).

### Intercorrências — Index

| Item | Blade | React |
|---|---|---|
| Filtros estado/tipo | ✅ | ✅ |
| Colunas básicas | ✅ | ✅ |
| **Botão Editar (RASCUNHO)** | ✅ | ❌ |
| **Botão Submeter (RASCUNHO → PENDENTE)** | ✅ | ❌ |
| Tradução dos tipos via i18n | ✅ `pontowr2::ponto.intercorrencia.tipos` | ⚠️ hardcoded em `Create.tsx` |

**Veredito:** 🔴 **React incompleto.** Sem Editar e Submeter, o colaborador fica travado depois de salvar rascunho. **Crítico recriar.**

### Intercorrências — Create

| Item | Blade | React |
|---|---|---|
| Form completo | ✅ | ✅ |
| **Campo IA** (descrição livre → preencher form) | ❌ | ✅ **NOVO** (OpenAI gpt-4o-mini) |
| Cache por hash SHA-256 | — | ✅ |
| Mascara PII (CPF/PIS/email) | — | ✅ |
| Upload de anexo (PDF/JPG/PNG) | ✅ | ⚠️ falta |

**Veredito:** React ganhou com IA. Falta upload de anexo.

### Intercorrências — Show

| Item | Blade | React |
|---|---|---|
| Dados completos | ✅ | ✅ |
| Motivo de rejeição | ✅ | ✅ destacado em Alert |
| Ações condicionais por estado | ✅ | ✅ Editar/Submeter/Cancelar |
| **Rota Edit em React** | ✅ | ❌ link aponta pra rota Blade antiga |

**Veredito:** ⚠️ React tem botão "Editar" mas **não tem tela Edit React**; leva pra Blade. Inconsistência UX.

### Banco de Horas — Index

Praticamente idêntico em funcionalidade. React ganhou em design.

### Banco de Horas — Show

| Item | Blade | React |
|---|---|---|
| Saldo destacado | ✅ | ✅ maior no React |
| Histórico paginado | ✅ | ✅ |
| Form ajuste manual | ✅ | ✅ |
| **Alert "append-only" explicativo** | ❌ | ✅ **NOVO** — educativo |

**Veredito:** React ganhou.

### Escalas — Index

| Item | Blade | React |
|---|---|---|
| Listagem | ✅ | ✅ |
| Coluna `turnos_count` | ❌ | ✅ **NOVO** |
| Botão **Deletar** | ✅ | ❌ (removido intencionalmente) |

**Veredito:** Remoção do delete é **melhoria de segurança** (deletar escala deixaria colaboradores órfãos). Se precisar, reativar via menu "Avançado".

### Escalas — Form (Create+Edit unificado)

| Item | Blade | React |
|---|---|---|
| Form base | ✅ via `_form.blade.php` partial | ✅ |
| Edit mostra turnos | ✅ read-only tabela | ✅ read-only |
| **CRUD de turnos por dia da semana** | ❌ | ❌ ambos incompletos |

**Veredito:** Empate. **Falta real** em ambos — implementar.

### Importações — Index

| Item | Blade | React |
|---|---|---|
| Listagem com estado | ✅ | ✅ |
| **Botão "Baixar original"** | ✅ | ❌ |

**Veredito:** ⚠️ **React perdeu.** Baixar arquivo é necessário por exigência legal (reter 5 anos) e reprocessamento. **Recriar — alto valor.**

### Importações — Create

| Item | Blade | React |
|---|---|---|
| Upload form | ✅ | ✅ com progress bar |
| Callout dedup SHA-256 | ✅ | ✅ em Alert |

**Veredito:** React ganhou com progress visual.

### Importações — Show

| Item | Blade | React |
|---|---|---|
| Metadata + progresso | ✅ | ✅ |
| **Polling automático** enquanto PROCESSANDO | ❌ | ✅ **NOVO** (3s) |

**Veredito:** React ganhou.

### Colaboradores — Index

| Item | Blade | React |
|---|---|---|
| Busca | ✅ com botão submit | ✅ reativa debounced 350ms |

**Veredito:** React ganhou em fluidez.

### Colaboradores — Edit

| Item | Blade | React |
|---|---|---|
| Form config ponto | ✅ | ✅ |
| Switch shadcn vs checkbox | checkbox | ✅ Switch (mais bonito) |

**Veredito:** React ganhou em design.

### Configurações — Index

| Item | Blade | React |
|---|---|---|
| 4 cards read-only | ✅ | ✅ com border-t colorida por tema |
| Referência aos artigos CLT | ✅ | ✅ |

**Veredito:** Empate funcional. React mais moderno.

### Configurações — REPs

| Item | Blade | React |
|---|---|---|
| Form de cadastro | ✅ | ✅ |
| Tabela paginada | ✅ | ✅ |
| **Desativar/editar REP existente** | ❌ | ❌ |

**Veredito:** Ambos incompletos. Só cadastram novos.

### Relatórios

Refactor puro. Mesma funcionalidade. React ganhou em design.

---

## 🎯 Recomendações de prioridade (o que recriar)

Ordem por ROI (usabilidade/funcionalidade × esforço):

### 🔴 Alta prioridade — funcionalidade crítica ausente

**1. Botões Editar + Submeter em Intercorrências/Index**
- **Por quê:** workflow essencial. Sem isso o colaborador fica travado após salvar rascunho
- **Esforço:** 2-3 horas (rotas existem no Blade, só adicionar coluna de ações + criar tela Edit React)
- **Valor:** desbloqueio imediato de fluxo

**2. Tela Edit em Intercorrências (React)**
- **Por quê:** Show tem botão "Editar" que hoje aponta pra Blade antigo (inconsistência UX)
- **Esforço:** 2 horas (reaproveitar Create.tsx)
- **Valor:** CRUD completo

**3. Download de arquivo original em Importações**
- **Por quê:** exigência legal (reter AFD 5 anos) + reprocessamento
- **Esforço:** 30 min (rota já existe no controller)
- **Valor:** compliance

### 🟡 Média prioridade — bom ter

**4. Busca em Espelho (matrícula/nome/CPF)**
- **Esforço:** 1 hora
- **Valor:** produtividade para empresas com 100+ colaboradores

**5. Upload de anexo em Intercorrências/Create**
- **Por quê:** atestado médico em PDF é uso real
- **Esforço:** 1-2 horas (Inertia `forceFormData`)
- **Valor:** completa o caso de uso

### 🟢 Baixa prioridade — nice to have

**6. Aprovação em lote** (checkboxes + botão "Aprovar selecionadas")
- **Esforço:** 3 horas
- **Valor:** produtividade RH (backend `aprovarEmLote` já existe)

**7. CRUD de turnos dentro de Escala** (faltava também no Blade)
- **Esforço:** 4-5 horas — é um sub-CRUD complexo
- **Valor:** completa o módulo

**8. Desativar/editar REP em Configurações**
- **Esforço:** 2 horas
- **Valor:** manutenção de ambiente

### ⚪ Não recriar (Blade antigo fazia pior ou não fazia)

- Deletar Escala (risco operacional, React certo em remover)
- NSR explícito na timeline de marcações (substitudo por REP.identificador)

---

## 🐛 Bugs detectados ao testar

1. **`Modules/Accounting/Helpers/general_helper.php` redeclara função `accounting()`**
   - Bloqueia execução de testes PHPUnit inteiro
   - Fix: guard clause `if (!function_exists('accounting'))`
   - Afeta: qualquer CI/CD rodando testes

2. **Rota Edit de Intercorrência aponta pra Blade legacy**
   - Link `/ponto/intercorrencias/{id}/edit` ainda é Blade (controller retorna `View`)
   - UX inconsistente depois do Show React

---

## 📍 Próximas ações sugeridas

1. Implementar **#1 e #2** juntos (Editar+Submeter na lista + tela Edit React) — ~4h de trabalho, desbloqueia workflow completo
2. Adicionar **#3** (download original) — 30min, quick-win de compliance
3. Fixar bug do `accounting()` helper — 2min, destrava toda suíte de testes
4. Depois considerar #4-8 conforme prioridade do Wagner

---

**Status do módulo:** PontoWR2 React está **funcionalmente equivalente ao Blade** exceto pelas 3 faltas críticas acima. Quando resolvidas, o Blade legacy pode ser deprecado.
