# Módulo Ponto WR2

Módulo de **Ponto Eletrônico** em conformidade com a **Portaria MTP 671/2021** (REP-P/REP-C/REP-A), CLT e Reforma Trabalhista. Desenvolvido como extensão do **UltimatePOS 6 + Essentials & HRM** pela **WR2 Sistemas**.

## Recursos principais

- Marcação de ponto via REP-P (web), REP-C (homologado) e importação AFD/AFDT
- Banco de horas com saldo, compensação e multiplicadores configuráveis
- Intercorrências de expediente (saídas/retornos) com fluxo de aprovação
- Apuração automática com regras CLT (tolerâncias, intrajornada, interjornada, HE, adicional noturno)
- Geração de AFD/AFDT/AEJ para fiscalização
- Assinatura digital de marcações com certificado ICP-Brasil A1
- Integração eSocial (S-1010, S-2230, S-2240)
- Multi-empresa via `business_id` do UltimatePOS

## Requisitos

- PHP 8.1+
- Laravel 10.x
- UltimatePOS v6.12+
- Essentials & HRM v5.4+
- MySQL 8.0+ (triggers obrigatórios para imutabilidade)
- Redis 7 (filas)

## Instalação

```bash
# 1. Copiar módulo para Modules/PontoWr2
# 2. Atualizar autoload
composer dump-autoload

# 3. Ativar no nWidart
php artisan module:enable PontoWr2

# 4. Rodar migrações
php artisan module:migrate PontoWr2

# 5. Publicar assets
php artisan module:publish PontoWr2

# 6. (Opcional) Seeders de demo
php artisan module:seed PontoWr2
```

## Estrutura

```
Modules/PontoWr2/
├── Config/           # Regras CLT, banco de horas, REP, eSocial
├── Console/Commands/ # Artisan: import AFD, fechar período
├── Database/
│   ├── Migrations/   # 8 tabelas de domínio
│   └── Seeders/
├── Entities/         # Models Eloquent (Marcacao, Intercorrencia, ...)
├── Http/
│   ├── Controllers/  # 10 controllers (1 por item do menu horizontal)
│   ├── Middleware/   # CheckPontoAccess
│   └── Requests/     # Form requests (validação)
├── Services/         # Regras de negócio (Apuracao, BH, AFD)
├── Providers/        # Service + Route providers
├── Resources/
│   ├── views/        # Blade (layout + seções)
│   ├── lang/pt-BR/   # Traduções
│   └── assets/       # JS + SASS
├── Routes/
│   ├── web.php
│   └── api.php
└── Tests/
```

## Conformidade legal

- **Portaria MTP 671/2021** — NSR sequencial, AFD/AFDT/AEJ, imutabilidade
- **CLT** — Art. 58 (jornada e tolerâncias), Art. 59 (HE), Art. 66 (interjornada), Art. 71 (intrajornada), Art. 73 (noturno)
- **Lei 13.467/2017** — Banco de horas por acordo individual (6 meses)
- **Lei 13.709/2018 (LGPD)** — Dados pessoais, retenção, consentimento
- **eSocial** — Leiautes S-1.2

## Licença

Proprietário — WR2 Sistemas
