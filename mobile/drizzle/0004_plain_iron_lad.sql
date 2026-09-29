CREATE TABLE `customers` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`nome` varchar(255) NOT NULL,
	`tipo` enum('PF','PJ') NOT NULL DEFAULT 'PF',
	`documento` varchar(20),
	`telefone` varchar(32),
	`email` varchar(320),
	`endereco` text,
	`observacoes` text,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `customers_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `orderItems` (
	`id` varchar(36) NOT NULL,
	`pedidoId` varchar(36) NOT NULL,
	`produtoId` varchar(36),
	`descricao` varchar(255) NOT NULL,
	`quantidade` decimal(12,3) NOT NULL DEFAULT '1',
	`valorUnit` decimal(12,2) NOT NULL,
	`valorTotal` decimal(12,2) NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `orderItems_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
ALTER TABLE `ops` ADD `customerId` varchar(36);--> statement-breakpoint
ALTER TABLE `pedidos` ADD `customerId` varchar(36);--> statement-breakpoint
ALTER TABLE `customers` ADD CONSTRAINT `customers_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `orderItems` ADD CONSTRAINT `orderItems_pedidoId_pedidos_id_fk` FOREIGN KEY (`pedidoId`) REFERENCES `pedidos`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `orderItems` ADD CONSTRAINT `orderItems_produtoId_produtos_id_fk` FOREIGN KEY (`produtoId`) REFERENCES `produtos`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
CREATE INDEX `idx_customers_userId` ON `customers` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_orderItems_pedidoId` ON `orderItems` (`pedidoId`);--> statement-breakpoint
ALTER TABLE `ops` ADD CONSTRAINT `ops_customerId_customers_id_fk` FOREIGN KEY (`customerId`) REFERENCES `customers`(`id`) ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `pedidos` ADD CONSTRAINT `pedidos_customerId_customers_id_fk` FOREIGN KEY (`customerId`) REFERENCES `customers`(`id`) ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
CREATE INDEX `idx_ops_customerId` ON `ops` (`customerId`);--> statement-breakpoint
CREATE INDEX `idx_pedidos_customerId` ON `pedidos` (`customerId`);