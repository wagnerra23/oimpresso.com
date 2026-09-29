CREATE TABLE `ops` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`pedidoId` varchar(36) NOT NULL,
	`cliente` varchar(255) NOT NULL,
	`produto` varchar(255) NOT NULL,
	`status` enum('fila','andamento','revisao','concluido') NOT NULL DEFAULT 'fila',
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `ops_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `pedidos` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`cliente` varchar(255) NOT NULL,
	`produto` varchar(255) NOT NULL,
	`valor` decimal(12,2) NOT NULL,
	`status` enum('novo','aprovado','execucao','entregue') NOT NULL DEFAULT 'novo',
	`data` varchar(32) NOT NULL,
	`tipo` varchar(64) NOT NULL DEFAULT 'Venda',
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `pedidos_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `produtos` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`nome` varchar(255) NOT NULL,
	`categoria` varchar(64) NOT NULL,
	`preco` decimal(12,2) NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `produtos_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `transacoes` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`tipo` enum('receita','despesa') NOT NULL,
	`descricao` varchar(255) NOT NULL,
	`valor` decimal(12,2) NOT NULL,
	`data` varchar(32) NOT NULL,
	`categoria` varchar(64) NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `transacoes_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
ALTER TABLE `ops` ADD CONSTRAINT `ops_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `ops` ADD CONSTRAINT `ops_pedidoId_pedidos_id_fk` FOREIGN KEY (`pedidoId`) REFERENCES `pedidos`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `pedidos` ADD CONSTRAINT `pedidos_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `produtos` ADD CONSTRAINT `produtos_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `transacoes` ADD CONSTRAINT `transacoes_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
CREATE INDEX `idx_ops_userId` ON `ops` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_ops_pedidoId` ON `ops` (`pedidoId`);--> statement-breakpoint
CREATE INDEX `idx_pedidos_userId` ON `pedidos` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_produtos_userId` ON `produtos` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_transacoes_userId` ON `transacoes` (`userId`);