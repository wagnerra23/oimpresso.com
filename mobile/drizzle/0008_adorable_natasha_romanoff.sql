CREATE TABLE `customerMagicLinks` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`customerId` varchar(36) NOT NULL,
	`token` varchar(96) NOT NULL,
	`expiresAt` varchar(32) NOT NULL,
	`usedAt` varchar(32),
	`origem` varchar(32),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `customerMagicLinks_id` PRIMARY KEY(`id`),
	CONSTRAINT `customerMagicLinks_token_unique` UNIQUE(`token`)
);
--> statement-breakpoint
CREATE TABLE `customerSessions` (
	`id` varchar(36) NOT NULL,
	`customerId` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`sessionToken` varchar(96) NOT NULL,
	`expiresAt` varchar(32) NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `customerSessions_id` PRIMARY KEY(`id`),
	CONSTRAINT `customerSessions_sessionToken_unique` UNIQUE(`sessionToken`)
);
--> statement-breakpoint
CREATE TABLE `paymentLinks` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`customerId` varchar(36),
	`referenciaTipo` varchar(32) NOT NULL,
	`referenciaId` varchar(36) NOT NULL,
	`valor` decimal(12,2) NOT NULL,
	`descricao` varchar(255),
	`metodoPreferido` enum('pix','boleto','cartao','qualquer') DEFAULT 'qualquer',
	`status` enum('pendente','pago','vencido','cancelado','estornado','falhou') NOT NULL DEFAULT 'pendente',
	`providerPaymentId` varchar(64),
	`paymentUrl` varchar(500),
	`vencimento` varchar(32),
	`pagoEm` varchar(32),
	`netValue` decimal(12,2),
	`providerResponse` text,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `paymentLinks_id` PRIMARY KEY(`id`),
	CONSTRAINT `paymentLinks_providerPaymentId_unique` UNIQUE(`providerPaymentId`)
);
--> statement-breakpoint
ALTER TABLE `customerMagicLinks` ADD CONSTRAINT `customerMagicLinks_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `customerMagicLinks` ADD CONSTRAINT `customerMagicLinks_customerId_customers_id_fk` FOREIGN KEY (`customerId`) REFERENCES `customers`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `customerSessions` ADD CONSTRAINT `customerSessions_customerId_customers_id_fk` FOREIGN KEY (`customerId`) REFERENCES `customers`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `customerSessions` ADD CONSTRAINT `customerSessions_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `paymentLinks` ADD CONSTRAINT `paymentLinks_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `paymentLinks` ADD CONSTRAINT `paymentLinks_customerId_customers_id_fk` FOREIGN KEY (`customerId`) REFERENCES `customers`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
CREATE INDEX `idx_customerMagicLinks_customerId` ON `customerMagicLinks` (`customerId`);--> statement-breakpoint
CREATE INDEX `idx_customerMagicLinks_expiresAt` ON `customerMagicLinks` (`expiresAt`);--> statement-breakpoint
CREATE INDEX `idx_customerSessions_sessionToken` ON `customerSessions` (`sessionToken`);--> statement-breakpoint
CREATE INDEX `idx_customerSessions_customerId` ON `customerSessions` (`customerId`);--> statement-breakpoint
CREATE INDEX `idx_paymentLinks_userId` ON `paymentLinks` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_paymentLinks_customerId` ON `paymentLinks` (`customerId`);--> statement-breakpoint
CREATE INDEX `idx_paymentLinks_referencia` ON `paymentLinks` (`referenciaTipo`,`referenciaId`);--> statement-breakpoint
CREATE INDEX `idx_paymentLinks_status` ON `paymentLinks` (`status`);