# Protótipo — Perfil · "Meu perfil" (conta do usuário logado)

> Baseline versionado do protótipo Cowork pra a tela de Perfil. Importado pra fechar o furo
> "sem baseline → sem diff" da Fase 0 (`aplicar-prototipo`). Antes disso o protótipo só existia
> como zip solto no Downloads, fora de qualquer fila rastreável.

## Proveniência (intake)
- **Origem:** handoff bundle ComVis — `Oimpresso ERP Comunicação Visual -handoff (userperfil).zip` (full-app Cowork, 1186 arquivos).
- **Importado em:** 2026-06-24 (sessão de teste do protocolo `aplicar-prototipo`, [W]).
- **Arquivos:** `perfil-page.jsx` (React/host-global `window.PerfilPage`) · `perfil-page.css` (prefixo `.pf-`, token-driven claro/escuro) · `perfil.png` (screenshot de referência).
- **Intake canônico (ADR 0282):** Issue `cowork-intake` — adoção ainda zero; este import é o substituto rastreável enquanto o canal não pega.

## O que é
Redesign fiel da tela legada **`resources/views/user/profile.blade.php`** (UltimatePOS HRM). Cockpit V2, 4 abas:
1. **Conta** — prefixo/nome/sobrenome/email/idioma + foto.
2. **Mais informações** — nascimento/gênero/civil/sangue/responsável · contatos (3 telefones) · redes sociais · documento · endereços.
3. **Dados bancários** — titular/conta/banco/código/agência/CPF-CNPJ + nota LGPD.
4. **Segurança** — alterar senha (valida mismatch).

## Alvo real (Fase 0)
- **Tela viva:** NÃO existe Page Inertia ainda → tela **nova** (migração MWART de Blade).
- **Blade legado:** `resources/views/user/profile.blade.php` (+ `edit_profile_form_part.blade.php` pros campos HRM/`bank_details` JSON).
- **Rotas:** `GET /user/profile` → `UserController@getProfile` (`user.getProfile`) · `POST /user/update` → `UserController@updateProfile` · senha → `UserController@updatePassword`.
- **Paridade de campos:** 1:1 confirmada contra o Blade (sem invenção — anti-padrão LICOES_F3).

## Governança (Fase 2)
- **Tier 0 (ADR 0093):** `updateProfile` escopa SÓ ao `auth()->user()` — nunca `user_id` arbitrário. Mandatório no controller novo.
- **LGPD/PII:** `bank_details` (conta, CPF), endereços, grupo sanguíneo. Não logar; manter tratamento do JSON como no legado. Protótipo já carimba a nota LGPD na aba Banco.
- **Cliente-sinal (ADR 0105):** tela interna-admin, sem cliente pagante pedindo. Justificativa deste ciclo = **teste do protocolo** (trabalho de processo, não US de cliente).

## Decisões [W] (2026-06-24)
- **Profundidade:** Page Inertia + controller numa **rota nova** (ex: `/perfil`); `/user/profile` legado fica intacto; cutover decidido depois.
- **Header:** **forçar PageHeader v3 canônico** (UI-0013/0189) — o header de identidade `pf-head` do protótipo cede ao canon (header roxo universal + abas como SubNav).

## Refs
- Skill `aplicar-prototipo` · RUNBOOK `prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md`
- ADR 0104 (MWART) · 0093 (Tier 0) · 0105 (cliente-sinal) · 0282 (protocolo v2) · UI-0013/0189 (PageHeader canon)
