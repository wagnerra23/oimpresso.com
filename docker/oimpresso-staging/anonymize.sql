-- ─────────────────────────────────────────────────────────────────────────────
-- Anonimização LGPD do banco de STAGING (oimpresso_staging).
-- Roda APÓS importar o dump de produção, ANTES de o staging ficar acessível.
--
-- Estratégia:
--   1. PII de pessoas (contacts/users/transactions) → fake determinístico (por id)
--   2. Credenciais/tokens/certificados → TRUNCATE (staging não age no mundo real)
--   3. Conteúdo de conversas (WhatsApp/Jana/chat) → TRUNCATE (não precisa histórico real)
--
-- Idempotente. Rodar com `mysql --force` (tolera tabela ausente entre versões).
-- Ref: memory/reference/lgpd-mapa-tratamento.md
-- ─────────────────────────────────────────────────────────────────────────────
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_SAFE_UPDATES = 0;

-- ── contacts (titulares finais — PII pesada) ────────────────────────────────
UPDATE contacts SET
  name                   = CONCAT('Contato ', id),
  supplier_business_name = CASE WHEN supplier_business_name IS NOT NULL AND supplier_business_name <> '' THEN CONCAT('Empresa ', id) ELSE supplier_business_name END,
  nome_fantasia          = CASE WHEN nome_fantasia IS NOT NULL AND nome_fantasia <> '' THEN CONCAT('Fantasia ', id) ELSE nome_fantasia END,
  fantasia               = CASE WHEN fantasia IS NOT NULL AND fantasia <> '' THEN CONCAT('Fantasia ', id) ELSE fantasia END,
  contato                = CASE WHEN contato IS NOT NULL AND contato <> '' THEN CONCAT('Responsavel ', id) ELSE contato END,
  cpf_cnpj               = LPAD(id, 14, '0'),
  tax_number             = LPAD(id, 14, '0'),
  ie_rg = NULL, rg = NULL, ie = NULL, inscricao_estadual = NULL, inscricao_municipal = NULL, suframa = NULL,
  email                  = CASE WHEN email IS NOT NULL AND email <> '' THEN CONCAT('contato', id, '@staging.local') ELSE email END,
  email_billing = NULL, email_nfe = NULL,
  mobile                 = CASE WHEN mobile IS NOT NULL AND mobile <> '' THEN CONCAT('11', LPAD(MOD(id, 1000000000), 9, '9')) ELSE mobile END,
  landline = NULL, alternate_number = NULL, tel2 = NULL,
  rua = 'Rua Staging', numero = '100', bairro = 'Centro', cep = '00000000',
  street_name = 'Rua Staging', building_number = '100', additional_number = NULL,
  city = 'Cidade', state = 'SP', zip_code = '00000000',
  address = NULL, address_line_1 = 'Rua Staging, 100', address_line_2 = NULL,
  shipping_address = NULL, shipping_custom_field_details = NULL,
  complemento = NULL, land_mark = NULL, neighborhood = 'Centro',
  dob = NULL, nascimento = NULL, aniversario_mmdd = NULL,
  cargo = NULL, obs_comercial = NULL, mensagem_venda = NULL,
  site_url = NULL, legacy_raw = NULL;

-- ── users (operadores) — PII anonimizada ───────────────────────────────────
-- NOTA: reset de senha (bcrypt real) é feito pelo seed-from-prod.sh, que gera o
-- hash via `php artisan` e aplica UPDATE separado (hash não pode ser hardcoded).
UPDATE users SET
  surname     = CASE WHEN surname IS NOT NULL AND surname <> '' THEN CONCAT('U', id) ELSE surname END,
  first_name  = CONCAT('Usuario', id),
  last_name   = CONCAT('Staging', id),
  email       = CONCAT('user', id, '@staging.local'),
  remember_token = NULL,
  contact_no = NULL, contact_number = NULL, alt_number = NULL, family_number = NULL,
  address = NULL, permanent_address = NULL, current_address = NULL, guardian_name = NULL,
  dob = NULL, bank_details = NULL, id_proof_name = NULL, id_proof_number = NULL,
  social_media_1 = NULL, social_media_2 = NULL, fb_link = NULL, twitter_link = NULL,
  officeimpresso_senha = NULL, google_id = NULL, microsoft_id = NULL,
  service_staff_pin = NULL;

-- ── transactions (PII residual em entregas/notas/reparos) ───────────────────
UPDATE transactions SET
  shipping_details = NULL, shipping_address = NULL,
  delivered_to = NULL, delivery_person = NULL,
  additional_notes = NULL, staff_note = NULL,
  order_addresses = NULL,
  cpf_nota = CASE WHEN cpf_nota IS NOT NULL AND cpf_nota <> '' THEN LPAD(MOD(id,100000000000), 11, '0') ELSE cpf_nota END,
  placa = CASE WHEN placa IS NOT NULL AND placa <> '' THEN 'ABC0000' ELSE placa END,
  repair_security_pwd = NULL, repair_security_pattern = NULL,
  repair_serial_no = NULL, repair_defects = NULL;

-- ── Credenciais / tokens / certificados → TRUNCATE (staging não age externo) ─
TRUNCATE TABLE rb_boleto_credentials;
TRUNCATE TABLE payment_gateway_credentials;
TRUNCATE TABLE nfe_certificados;
TRUNCATE TABLE mcp_tokens;
TRUNCATE TABLE oauth_access_tokens;
TRUNCATE TABLE oauth_refresh_tokens;
TRUNCATE TABLE whatsapp_business_configs;
TRUNCATE TABLE whatsapp_business_phones;
TRUNCATE TABLE whatsapp_phone_user_access;

-- ── Conteúdo de conversas (PII por conteúdo) → TRUNCATE ─────────────────────
TRUNCATE TABLE whatsapp_messages;
TRUNCATE TABLE whatsapp_conversations;
TRUNCATE TABLE whatsapp_csat_responses;
TRUNCATE TABLE whatsapp_reminders;
TRUNCATE TABLE whatsapp_lid_pn_map;
TRUNCATE TABLE messages;
TRUNCATE TABLE essentials_messages;
TRUNCATE TABLE jana_mensagens;
TRUNCATE TABLE jana_conversas;
TRUNCATE TABLE jana_memoria_facts;
TRUNCATE TABLE jana_cache_semantico;
TRUNCATE TABLE jana_business_profile;
TRUNCATE TABLE copiloto_mensagens;
TRUNCATE TABLE docs_chat_messages;
TRUNCATE TABLE mcp_cc_messages;

SET FOREIGN_KEY_CHECKS = 1;
SET SQL_SAFE_UPDATES = 1;
SELECT 'ANONYMIZE_DONE' AS status;
