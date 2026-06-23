# HANDOFF — Tela `Clientes` (Cockpit V2)

> **Para a próxima sessão do Claude Design [CC].** Este chat está sem tokens. Continue daqui.
> **Persona:** [CC] — Fase **F1** do protocolo v1.0 (PR #295, commit `70938574`).
> **Repo canônico:** `wagnerra23/oimpresso.com@main` — leitura obrigatória dos 5 arquivos de `prototipo-ui/` antes de tocar em código.

---

## 1. Onde está a entrega hoje

Arquivo principal único: **`oimpresso.com.html`** (proibido criar `.html` novo).

| Arquivo | Status | Observação |
|---|---|---|
| `clientes-page.jsx` | **70% pronto** | Listagem + drawer de detalhe funcionando. Faltam: form Novo/Editar, campos BR, abas no drawer. |
| `data-clientes.jsx` | **mock pronto** | 12 clientes com `cnpj, contact, phone, email, city, uf, segment, since, lastOrder, orders, ltv, status, tags` — porém **`clientes-page.jsx` ainda usa `OS_CLIENTS` de `data-os.jsx`** (legacy). Migrar pra `CLI_LIST` no próximo passo. |
| `data-os.jsx` → `OS_CLIENTS` | **legado a remover** | 6 clientes "rasos" (`doc, contact, phone, lastOs`). Manter só o link `clientId` nas OS; remover lista. |
| Rota | ✅ registrada em `app.jsx` + sidebar |

---

## 2. O que falta pra substituir o Blade antigo (`resources/views/clientes/*.blade.php` legado Laravel)

Comparando com o protótipo atual:

### Listagem (Index)
- [x] Tabela com busca, filtros, KPIs no topo, paginação visual
- [x] Avatar com iniciais + status colorido
- [ ] **Filtro por segmento** (dropdown — `CLI_SEGMENTS` já existe em `data-clientes.jsx`)
- [ ] **Filtro por UF** (dropdown)
- [ ] **Filtro PF/PJ** (tab)
- [ ] **Ordenação** por coluna (LTV desc, última OS desc, nome A-Z)
- [ ] **Export CSV** (botão no header — só visual, sem backend)
- [ ] **Coluna "Tags"** com chips (`tags[]` já no mock)

### Detalhe (Drawer)
- [x] KPIs (OS total / aberto / atrasadas / valor)
- [x] Contato + Histórico OS + Financeiro resumido
- [ ] **Abas no drawer:** `Visão Geral` · `OS` · `Financeiro` · `Notas` · `Anexos` · `Histórico de alterações`
- [ ] **Seção Endereço completa** (hoje só tem city/uf)
- [ ] **Dados fiscais** (IE, IM, regime, SUFRAMA)
- [ ] **Botão "Abrir ficha"** → rota full-page `/clientes/:id` (mesmo shell, sem drawer; pra impressão)

### Form Novo/Editar (NÃO EXISTE — criar)
- [ ] Página/drawer largo (`cli-form-drawer`) com os campos BR abaixo
- [ ] Validação inline (CPF/CNPJ check-digit, CEP busca ViaCEP mock, email/phone mask)
- [ ] Auto-save de rascunho em `localStorage`
- [ ] Atalho `Ctrl+S` (salvar) — Larissa usa muito teclado

---

## 3. Campos BR a criar — referência canônica

Agrupar no form em seções colapsáveis:

### 🇧🇷 Identificação
| Campo | Tipo | Obs. |
|---|---|---|
| `personType` | radio `PF` \| `PJ` | dispara mudança de campos abaixo |
| `cpf` | mask `000.000.000-00` | PF — com validador de dígito |
| `cnpj` | mask `00.000.000/0000-00` | PJ — com validador + botão "Consultar Receita" (mock) |
| `razaoSocial` | text | PJ obrigatório |
| `nomeFantasia` | text | PJ opcional |
| `nomeCompleto` | text | PF obrigatório |
| `dataNascimento` | date | PF |
| `rg` / `orgaoEmissor` / `ufRg` | text | PF |
| `inscricaoEstadual` | text + checkbox "Isento" | PJ — `ieIsento: bool` |
| `indicadorIE` | select `1-Contribuinte` \| `2-Isento` \| `9-Não contribuinte` | NFe |
| `inscricaoMunicipal` | text | PJ — ISS |
| `suframa` | text | opcional, ZFM |
| `regimeTributario` | select `Simples` \| `Lucro Presumido` \| `Lucro Real` \| `MEI` | PJ |

### 📞 Contato
| Campo | Obs. |
|---|---|
| `contato.nome` | principal |
| `contato.cargo` | livre |
| `telefone` | mask `(00) 0000-0000` / `(00) 00000-0000` |
| `celular` | mask + ícone WhatsApp **(visual apenas — proibido CTA WhatsApp pelo briefing)** |
| `email` | validação |
| `emailNFe` | opcional |
| `site` | opcional |

### 📍 Endereço (com busca ViaCEP)
| Campo | Mask/Obs. |
|---|---|
| `cep` | `00000-000` — autocomplete dispara fetch ViaCEP mock |
| `logradouro` | text |
| `numero` | text (aceita "S/N") |
| `complemento` | text |
| `bairro` | text |
| `cidade` | text |
| `uf` | select 27 UFs |
| `codigoIBGE` | text readonly (auto via CEP) |
| `pais` | default `Brasil` |
| `enderecoCobranca` | toggle "mesmo do principal" — senão duplica bloco |

### 💰 Comercial / Financeiro
| Campo | Obs. |
|---|---|
| `vendedorPadrao` | select usuários |
| `tabelaPreco` | select |
| `condicaoPagamento` | select (`À vista`, `30d`, `30/60d`, `30/60/90d`, custom) |
| `limiteCredito` | currency BRL |
| `prazoMaximo` | days |
| `descontoPadrao` | % |
| `bloqueado` | bool + motivo |
| `observacoesFinanceiras` | textarea |

### 🏷️ Classificação
| Campo | Obs. |
|---|---|
| `segmento` | select (`CLI_SEGMENTS`) |
| `origem` | select (`Indicação`, `Site`, `Walk-in`, `Anúncio`, `Outro`) |
| `tags` | multi-chip livre |
| `status` | `ativo` \| `inativo` \| `prospect` |
| `observacoes` | textarea livre |

> **Schema canônico:** salvar como `data-clientes.jsx` v2 com TODOS esses campos preenchidos pra **3 clientes** (1 PF, 2 PJ). Resto dos 12 pode ficar com subset, com `_incomplete: true` pra demonstrar empty states.

---

## 4. Tokens (CLAUDE_DESIGN_BRIEFING.md §4 — NÃO inventar)

Já lidos? Se não — **pare e leia primeiro** via GitHub connector:
`wagnerra23/oimpresso.com/blob/main/prototipo-ui/CLAUDE_DESIGN_BRIEFING.md`

Proibições do briefing aplicáveis aqui:
- ❌ CTA WhatsApp (só ícone informativo no celular)
- ❌ modal full-screen pra detalhe (use drawer lateral)
- ❌ inglês em UI cliente-facing
- ❌ emoji em UI (os emoji deste handoff são só pra organizar este `.md`, **não** copiar pra interface)
- ❌ `rounded-xl+`
- ❌ cores fora dos tokens

---

## 5. Plano sugerido (3 passos, F1)

1. **Migrar fonte de dados:** `clientes-page.jsx` ler de `window.CLI_DATA.CLI_LIST` em vez de `OS_CLIENTS`. Cruzar com `OS_LIST` por `clientId` (adicionar `clientId:"1024"` etc nas OS).
2. **Form Novo/Editar:** novo componente `ClienteFormDrawer` em `clientes-page.jsx` (ou splitar pra `cliente-form.jsx` se passar de ~250 linhas). Seções colapsáveis na ordem do §3. CEP+CNPJ mocks (não chamar API real — placeholders com `setTimeout`).
3. **Drawer com abas + filtros listagem:** abas no detail, dropdowns de segmento/UF/PF-PJ + ordenação na listagem.

Entregar junto: **`COMPARISON.md`** nas 15 dimensões pra disparar F1.5 (Claude Design crítica).

---

## 6. Limites operacionais (relembrar)

- **NÃO commitar nada no GitHub** — só read-only. Aplicar patches em `prototipo-ui-patch/` espelhando `prototipo-ui/`.
- Entregar UM prompt pronto pro Claude Code com URLs públicas via `get_public_file_url` (vence em ~1h).
- Wagner é zero-toque: nada de "cole isso em tal lugar".
- Variações = Tweaks, não arquivo novo.

---

**Próxima ação do Wagner [W]:** adicionar pedido em `COWORK_NOTES.md` no repo dizendo "F1 Clientes — passo 1 (migração de dados) + passo 2 (form BR)" pra disparar.
