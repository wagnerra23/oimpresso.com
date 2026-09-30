-- v2 wizard: extends customers + produtos for the "Nova Pessoa" and "Novo
-- Produto" wizards (papéis, endereço estruturado, contato extra, fiscal PJ,
-- LGPD, comercial · produtos: tipo, SKU/GTIN, fiscal, estoque, ficha).
--
-- All additive — no data loss, no destructive changes.

ALTER TABLE `customers` ADD `papeis` json;--> statement-breakpoint
ALTER TABLE `customers` ADD `razao_social` varchar(255);--> statement-breakpoint
ALTER TABLE `customers` ADD `nome_fantasia` varchar(255);--> statement-breakpoint
ALTER TABLE `customers` ADD `inscricao_estadual` varchar(32);--> statement-breakpoint
ALTER TABLE `customers` ADD `indicador_ie` enum('contribuinte','isento','nao_contribuinte') DEFAULT 'nao_contribuinte';--> statement-breakpoint
ALTER TABLE `customers` ADD `cep` varchar(9);--> statement-breakpoint
ALTER TABLE `customers` ADD `logradouro` varchar(255);--> statement-breakpoint
ALTER TABLE `customers` ADD `numero` varchar(20);--> statement-breakpoint
ALTER TABLE `customers` ADD `complemento` varchar(100);--> statement-breakpoint
ALTER TABLE `customers` ADD `bairro` varchar(120);--> statement-breakpoint
ALTER TABLE `customers` ADD `cidade` varchar(120);--> statement-breakpoint
ALTER TABLE `customers` ADD `uf` varchar(2);--> statement-breakpoint
ALTER TABLE `customers` ADD `codigo_municipio_ibge` varchar(7);--> statement-breakpoint
ALTER TABLE `customers` ADD `whatsapp` varchar(32);--> statement-breakpoint
ALTER TABLE `customers` ADD `email_nfe` varchar(320);--> statement-breakpoint
ALTER TABLE `customers` ADD `aceita_whatsapp` tinyint DEFAULT 1;--> statement-breakpoint
ALTER TABLE `customers` ADD `aceita_email` tinyint DEFAULT 1;--> statement-breakpoint
ALTER TABLE `customers` ADD `aceita_sms` tinyint DEFAULT 0;--> statement-breakpoint
ALTER TABLE `customers` ADD `consentimento_data` varchar(32);--> statement-breakpoint
ALTER TABLE `customers` ADD `consentimento_ip` varchar(64);--> statement-breakpoint
ALTER TABLE `customers` ADD `classificacao` enum('A','B','C','D') DEFAULT 'C';--> statement-breakpoint
ALTER TABLE `customers` ADD `limite_credito` decimal(12,2) DEFAULT '0';--> statement-breakpoint
ALTER TABLE `customers` ADD `prazo_padrao_dias` int DEFAULT 0;--> statement-breakpoint
ALTER TABLE `produtos` ADD `tipo_item` enum('produto','servico','insumo') DEFAULT 'produto';--> statement-breakpoint
ALTER TABLE `produtos` ADD `sku` varchar(64);--> statement-breakpoint
ALTER TABLE `produtos` ADD `gtin` varchar(14);--> statement-breakpoint
ALTER TABLE `produtos` ADD `ncm` varchar(8);--> statement-breakpoint
ALTER TABLE `produtos` ADD `cfop` varchar(4);--> statement-breakpoint
ALTER TABLE `produtos` ADD `cest` varchar(7);--> statement-breakpoint
ALTER TABLE `produtos` ADD `origem` varchar(1) DEFAULT '0';--> statement-breakpoint
ALTER TABLE `produtos` ADD `unidade` varchar(6) DEFAULT 'UN';--> statement-breakpoint
ALTER TABLE `produtos` ADD `preco_custo` decimal(12,2);--> statement-breakpoint
ALTER TABLE `produtos` ADD `preco_promo` decimal(12,2);--> statement-breakpoint
ALTER TABLE `produtos` ADD `margem_lucro_percent` decimal(5,2);--> statement-breakpoint
ALTER TABLE `produtos` ADD `controla_estoque` tinyint DEFAULT 1;--> statement-breakpoint
ALTER TABLE `produtos` ADD `estoque_atual` decimal(12,3) DEFAULT '0';--> statement-breakpoint
ALTER TABLE `produtos` ADD `estoque_minimo` decimal(12,3) DEFAULT '0';--> statement-breakpoint
ALTER TABLE `produtos` ADD `localizacao` varchar(120);--> statement-breakpoint
ALTER TABLE `produtos` ADD `fornecedor_id` varchar(36);--> statement-breakpoint
ALTER TABLE `produtos` ADD `imagem_principal` varchar(255);--> statement-breakpoint
ALTER TABLE `produtos` ADD `gramatura` varchar(64);--> statement-breakpoint
ALTER TABLE `produtos` ADD `acabamento` varchar(120);--> statement-breakpoint
ALTER TABLE `produtos` ADD `descricao` text;
