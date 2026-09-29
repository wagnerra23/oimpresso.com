CREATE TABLE `companies` (
	`id` varchar(36) NOT NULL,
	`ownerUserId` int NOT NULL,
	`nome` varchar(255) NOT NULL,
	`vertical` enum('cv','mecanica','outro') NOT NULL DEFAULT 'outro',
	`ativa` tinyint NOT NULL DEFAULT 1,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `companies_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `companyMembers` (
	`id` varchar(36) NOT NULL,
	`companyId` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`role` enum('owner','admin','member') NOT NULL DEFAULT 'member',
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `companyMembers_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
ALTER TABLE `companySettings` DROP FOREIGN KEY `companySettings_userId_users_id_fk`;--> statement-breakpoint
ALTER TABLE `companySettings` DROP INDEX `companySettings_userId_unique`;--> statement-breakpoint
ALTER TABLE `companySettings` ADD CONSTRAINT `companySettings_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
CREATE INDEX `idx_companySettings_userId` ON `companySettings` (`userId`);--> statement-breakpoint
ALTER TABLE `artworkApprovals` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `artworks` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `companySettings` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `customerMagicLinks` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `customerSessions` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `customers` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `fiscalDocuments` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `inventory` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `inventoryMovements` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `ops` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `orderItems` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `paymentLinks` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `pedidos` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `priceTables` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `produtos` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `pushTokens` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `quoteItems` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `quotes` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `serviceOrderItems` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `serviceOrderPhotos` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `serviceOrderPublicTokens` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `serviceOrders` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `transacoes` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `vehicles` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `whatsappMessages` ADD `companyId` varchar(36);--> statement-breakpoint
ALTER TABLE `companySettings` ADD CONSTRAINT `companySettings_companyId_unique` UNIQUE(`companyId`);--> statement-breakpoint
ALTER TABLE `companies` ADD CONSTRAINT `companies_ownerUserId_users_id_fk` FOREIGN KEY (`ownerUserId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `companyMembers` ADD CONSTRAINT `companyMembers_companyId_companies_id_fk` FOREIGN KEY (`companyId`) REFERENCES `companies`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `companyMembers` ADD CONSTRAINT `companyMembers_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
CREATE INDEX `idx_companies_ownerUserId` ON `companies` (`ownerUserId`);--> statement-breakpoint
CREATE INDEX `idx_companyMembers_companyId` ON `companyMembers` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_companyMembers_userId` ON `companyMembers` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_artworkApprovals_companyId` ON `artworkApprovals` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_artworks_companyId` ON `artworks` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_customerMagicLinks_companyId` ON `customerMagicLinks` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_customerSessions_companyId` ON `customerSessions` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_customers_companyId` ON `customers` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_fiscalDocuments_companyId` ON `fiscalDocuments` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_inventory_companyId` ON `inventory` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_inventoryMovements_companyId` ON `inventoryMovements` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_ops_companyId` ON `ops` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_orderItems_companyId` ON `orderItems` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_paymentLinks_companyId` ON `paymentLinks` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_pedidos_companyId` ON `pedidos` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_priceTables_companyId` ON `priceTables` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_produtos_companyId` ON `produtos` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_pushTokens_companyId` ON `pushTokens` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_quoteItems_companyId` ON `quoteItems` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_quotes_companyId` ON `quotes` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_serviceOrderItems_companyId` ON `serviceOrderItems` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_serviceOrderPhotos_companyId` ON `serviceOrderPhotos` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_serviceOrderPublicTokens_companyId` ON `serviceOrderPublicTokens` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_serviceOrders_companyId` ON `serviceOrders` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_transacoes_companyId` ON `transacoes` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_vehicles_companyId` ON `vehicles` (`companyId`);--> statement-breakpoint
CREATE INDEX `idx_whatsappMessages_companyId` ON `whatsappMessages` (`companyId`);