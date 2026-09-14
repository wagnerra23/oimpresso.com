# JANA — camadas que o Code deve olhar + telas novas (F1 [CC], 2026-08-27)

**Pedido pro [CL].** Não escrevo no git: este arquivo é a ponte. Build da fonte = `jana-telas-novas.jsx` + `jana-telas-novas.css` (+ 4 edições cirúrgicas em `jana-merge.jsx` / `app.jsx` / `oimpresso.com.html`).
**Lido no `main` neste turno** (nada aqui vem de memória): `Modules/Jana/Http/routes.php` · `Resources/permissions.php` · `Resources/menus/topnav.php` · `AlertasController` + `UpdateAlertasConfigRequest` + `views/alertas/{index,config}.blade.php` · `Services/AlertaService.php` · `Notifications/MetaDesvioNotification.php` · `AcaoHitlController` + `AcaoHitlService` · `SuperadminController` + `views/superadmin/metas.blade.php` · árvore `Modules/Jana/**` (504 arquivos) e `resources/js/Pages/Jana/**` (24).

---

## 0. Errata da rodada 1 — dois números meus estavam errados

| Eu disse (26–27/08) | O `main` diz hoje |
| --- | --- |
| `permissions.php` tem `group: 'Copiloto'` e 24 labels "Copiloto: …" | `'group' => 'Jana'`, `icon => compass`, **22** permissões, labels já "Jana: …" / "MCP: …" / "CC: …". O PR-0 de nome **não é em `permissions.php`** |
| "Copiloto" vivo = permissões | "Copiloto" vivo = `Resources/lang/pt/copiloto.php`, `menus/topnav.php` (`copiloto::copiloto.*`), as 8 views `copiloto::…`, `ChatCopilotoAgent`, `CopilotoDesvioDetectado`, `CopilotoDatabaseSeeder`, migrations `create_copiloto_*`, e o botão "Falar com Copiloto →" do `Cliente/Index.tsx` |

Chaves `jana.*` continuam intocáveis (#4853). O PR-0 encolhe: **lang + topnav + views + a label do botão do Cliente**.

---

## 1. Camadas esquecidas de aplicar (a tela existe; a camada nunca chegou nela)

1. **Permissão como camada de tela.** 22 permissões declaradas, o grupo `/ia` com `can:jana.access`, e a UI que nunca mostra a diferença. Aplicado agora: tweak **Papel de quem entrou** (funcionaria · dona · superadmin) — a aba Plataforma só existe com `jana.superadmin`, "Configurar alertas" só com `jana.metas.manage`, e a ausência aparece como `EmptyState variant="no-perm"`, não como botão morto.
2. **⚠️ P0 — o gate do superadmin não separa dono de empresa.** `SuperadminController::metas` confere `can('jana.superadmin')` **e** roda `withoutGlobalScope(ScopeByBusiness)`. O comentário do próprio `routes.php` registra que `Gate::before` devolve `true` em qualquer ability pra quem tem `Admin#{business_id}`. Logo: dona de empresa que chegue em `/ia/superadmin/metas` vê meta de outro cliente. Não é achado de design — é de tenancy (ADR 0093 Tier 0). Precisa de decisão antes de qualquer PR de fachada.
3. **Rate-limit.** Grupo `throttle:120,1`; `mensagens` e `mensagens/stream` em `60,1`. Nenhuma tela desenha o 429 — hoje o usuário vê o chat travar sem explicação.
4. **Validação → copy.** 7 FormRequests. `UpdateAlertasConfigRequest` é o caso extremo: tem `messages()` em PT-BR e a view estava com **todos os campos `disabled`** — mensagem escrita que nunca apareceu pra ninguém. As 4 mensagens estão agora na tela, literais.
5. **Fila/job como estado de tela.** `ApurarMetaJob`, `ExtrairFatosDaConversaJob`, `NarrarSaudeEcosistemaJob` + 46 comandos: "enfileirado", "apurando", "nunca apurada", "brief atrasado" são estados de produto, não detalhe de infra.
6. **Notificação.** `MetaDesvioNotification` = `database` + `broadcast`. Isso significa que **in-app é o único canal que existe hoje** — e-mail/WhatsApp na config de alertas nascem desligados de propósito.
7. **Config do módulo.** `Config/config.php` (53 KB), `memoria.php`, `retention.php`: o corte de 10% que a tela mostra sai de `copiloto.alertas.desvio_threshold_default`, não de constante minha.
8. **Contrato + testes.** `contrato/jana-painel.contract.json` ativo no CI (3 `_pendente_w`), `PainelContratoTest` (43 KB), `ProContractTest`, `JanaAccessGateTest`, `JanaViewsSemAndaimeTest`, `CockpitMockRemovidoTest`, `MultiTenantIsolation*Test`. Copy literal de tela **quebra CI** — as telas novas abaixo pedem contrato próprio, não emenda no do Painel.
9. **Fronteira de módulo.** Memória e Fontes são do `Modules\KB` (controllers), Custos/Qualidade/Governança MCP são do `Modules\Governance`, Roadmap/Tasks são da `Forja`. A URL diz `/ia/*`, o dono não é o Jana.
10. **Redirects 301** (8: `/ia/dashboard`, `/ia/cockpit`, `/ia/painel`, `/ia/admin/{custos,qualidade,governanca,roadmap}`, `/ia/kb`) — deep-link e bookmark são superfície de produto; três deles preservam query string com closure e isso não pode regredir.

---

## 2. Telas novas (construídas aqui — abas de área da tela única da Jana, sem `.html` novo)

| Área | Rota do vivo | Situação no `main` | O que entreguei |
| --- | --- | --- | --- |
| **Alertas** | `/ia/alertas` + `/ia/alertas/config` | index é Blade que diz "a lista ainda não existe"; config valida e **descarta** | Lista de desvios com projeção linear, severidade por múltiplo do corte (1× baixa · 1,5× média · 3× alta), projetado × realizado, canal, silenciar por meta, filtros severidade/status. Drawer de config com os campos exatos do FormRequest (`enabled`, `canais.{dashboard,email,whatsapp}`, `thresholds.{meta_atingida,meta_drift}`, `silencio_horario_{inicio,fim}`), limites 0–200 / 0–100 / `H:i` e as 4 mensagens de erro literais. Aviso de topo diz que hoje a rota não grava (US-COPI-061) |
| **Ações** | `/ia/acoes` (a fila que o controller chama de "PR próprio") | as 2 rotas HITL existem; a fila nunca foi desenhada | As 5 chaves de `AcaoHitlService::ACOES` com os 5 rótulos de CTA byte-idênticos, `alcance` distinguindo `null` (leitura) de número (envio), modal de prévia com o contexto que é gravado, recibo de aprovação (quem/quando) e aba "aprovadas". Nada promete disparo |
| **Plataforma** | `/ia/superadmin/metas` + `/ia/install` | Blade AdminLTE cru; docblock prometia agregação cross-business que **não existe** | Metas da plataforma (`business_id NULL`) + metas de clientes com empresa/período/última apuração, a pendência da agregação **declarada na tela** (não somei nada), o alerta P0 do gate, e o bloco de instalação (21 migrations · 4 seeders · 22 permissões) com confirmação destrutiva no desinstalar |

Ainda **não** desenhado, de propósito: `/ia/admin/jana-pro/preview` (endpoint JSON de debug, não é tela) e `emails/weekly-digest.blade.php` (o `JanaViewsSemAndaimeTest` exclui `emails/`).

---

## 3. Componentes divergentes — e por quê

| Componente | Divergência | Por que |
| --- | --- | --- |
| `JcIcon` (protótipo) | não existe no repo | repo é `lucide-react@^0.460` em todo lugar. Morre no handoff (já decidido na rodada 1) — mapa nome→Lucide por ícone usado |
| `Pages/Jana/Pro.tsx` | 13 blocos `style` inline | `Pages/Arquivos/Index.tsx` faz a mesma classe de tela com 10 componentes do DS e zero inline. Pro é a única tela cliente-facing da Jana fora do DS |
| `_components/JanaCockpit.tsx` (44 KB) | `startMockStream` — mock em rota live | a rota `/ia/cockpit` já foi desligada e 301 pro Painel; o componente ficou. Sai quebrado se alguém importar |
| `Pages/Jana/components/` (FabJana, JanaAreaHeader) | duas pastas pro mesmo papel | convenção do repo é `_components/`. Duas pastas = dois lugares pra procurar o mesmo header |
| `_components/AssistantUiChat.tsx` (16 KB) | chat paralelo ao `Chat.tsx` | dois donos do mesmo assunto; qual responde `/ia/conversa`? |
| Badges das telas Blade | fill sólido | AP7 manda pill tintada 6% + borda 22% + dot. A lista de alertas nova já nasce assim |
| `StatusBadge` do DS | não tem `kind` pra severidade de alerta | usei dot + texto na tela. Se severidade virar canon, entra como `kind="severidade"` **no DS**, não CSS na tela |
| `menus/topnav.php` | nav da IA com 3 destinos de outros módulos (`/governance/*`, `/kb`) | foram repontados em vez de removidos pra não tirar acesso de quem usava. Custa: o nav da IA leva pra fora da IA sem avisar |
| 8 views Blade AdminLTE (`metas/*`, `alertas/*`, `superadmin/metas`, `fontes/show`) | fora do DS e fora do Inertia | são as camadas que este protótipo absorveu (metas na rodada anterior; alertas e superadmin agora) |

---

## 4. Ordem sugerida de PRs

- **PR-0 (nome, sem comportamento):** `lang/pt/copiloto.php` → `jana.php`, `topnav.php`, as 8 views `copiloto::…`, botão do `Cliente/Index.tsx`. Chaves `jana.*` e route names `jana.*` intocados.
- **PR-1 (decisão, não código):** o gate do §1.2. Enquanto não decidido, `/ia/superadmin/metas` não deve ganhar fachada nova.
- **PR-2:** Alertas (index + config) em Inertia+DS, com `contrato/jana-alertas.contract.json` e os UCs; a persistência é a US-COPI-061 — se não entrar junto, a tela nasce com o aviso que está no protótipo.
- **PR-3:** fila `/ia/acoes` + Page própria, reusando `AcaoHitlService` (prévia continua do servidor).
- **PR-4:** Plataforma + `/ia/install`, depois do PR-1.
- **PR-5 (limpeza):** `JanaCockpit.tsx`, pasta `components/`, decisão sobre `AssistantUiChat`, `Pro.tsx` para o DS.

Gates a rodar em qualquer um: `cowork-ssot-guard`, `prototipo-readiness`, `cowork-paridade --check`, `PainelContratoTest`, `JanaAccessGateTest`, `JanaViewsSemAndaimeTest`, `MultiTenantIsolationTest`.
