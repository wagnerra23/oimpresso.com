CREATE TABLE `companySettings` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`razaoSocial` varchar(255),
	`nomeFantasia` varchar(255),
	`cnpj` varchar(18),
	`inscricaoEstadual` varchar(32),
	`inscricaoMunicipal` varchar(32),
	`regimeTributario` enum('simples_nacional','lucro_presumido','lucro_real') DEFAULT 'simples_nacional',
	`cep` varchar(9),
	`endereco` text,
	`cidade` varchar(120),
	`uf` varchar(2),
	`telefone` varchar(32),
	`email` varchar(320),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `companySettings_id` PRIMARY KEY(`id`),
	CONSTRAINT `companySettings_userId_unique` UNIQUE(`userId`)
);
--> statement-breakpoint
CREATE TABLE `fiscalDocuments` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`tipo` enum('NFe','NFCe','NFSe') NOT NULL,
	`referenciaTipo` varchar(32) NOT NULL,
	`referenciaId` varchar(36) NOT NULL,
	`customerId` varchar(36),
	`status` enum('rascunho','processando','autorizado','cancelado','rejeitado') NOT NULL DEFAULT 'rascunho',
	`chaveAcesso` varchar(64),
	`numero` varchar(32),
	`serie` varchar(16),
	`providerRef` varchar(64),
	`valor` decimal(12,2) NOT NULL,
	`pdfKey` varchar(255),
	`xmlKey` varchar(255),
	`providerResponse` text,
	`erroMensagem` text,
	`ambiente` enum('producao','homologacao') NOT NULL DEFAULT 'homologacao',
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `fiscalDocuments_id` PRIMARY KEY(`id`),
	CONSTRAINT `fiscalDocuments_providerRef_unique` UNIQUE(`providerRef`)
);
--> statement-breakpoint
ALTER TABLE `companySettings` ADD CONSTRAINT `companySettings_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `fiscalDocuments` ADD CONSTRAINT `fiscalDocuments_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `fiscalDocuments` ADD CONSTRAINT `fiscalDocuments_customerId_customers_id_fk` FOREIGN KEY (`customerId`) REFERENCES `customers`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
CREATE INDEX `idx_fiscalDocuments_userId` ON `fiscalDocuments` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_fiscalDocuments_referencia` ON `fiscalDocuments` (`referenciaTipo`,`referenciaId`);--> statement-breakpoint
CREATE INDEX `idx_fiscalDocuments_customerId` ON `fiscalDocuments` (`customerId`);--> statement-breakpoint
CREATE INDEX `idx_fiscalDocuments_status` ON `fiscalDocuments` (`status`);