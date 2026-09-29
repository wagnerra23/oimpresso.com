CREATE TABLE `artworkApprovals` (
	`id` varchar(36) NOT NULL,
	`artworkId` varchar(36) NOT NULL,
	`decisao` enum('aprovado','rejeitado') NOT NULL,
	`comentario` text,
	`aprovadorNome` varchar(255),
	`aprovadorEmail` varchar(320),
	`ipAddress` varchar(64),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `artworkApprovals_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `artworks` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`opId` varchar(36),
	`quoteId` varchar(36),
	`titulo` varchar(255) NOT NULL,
	`descricao` text,
	`fileKey` varchar(255) NOT NULL,
	`mimeType` varchar(64) NOT NULL,
	`fileSizeBytes` int NOT NULL,
	`status` enum('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'pendente',
	`publicToken` varchar(64) NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `artworks_id` PRIMARY KEY(`id`),
	CONSTRAINT `artworks_publicToken_unique` UNIQUE(`publicToken`)
);
--> statement-breakpoint
CREATE TABLE `inventory` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`produtoId` varchar(36),
	`codigo` varchar(64),
	`nome` varchar(255) NOT NULL,
	`unidade` varchar(16) NOT NULL DEFAULT 'un',
	`quantidade` decimal(12,3) NOT NULL DEFAULT '0',
	`estoqueMinimo` decimal(12,3) NOT NULL DEFAULT '0',
	`custoUnit` decimal(12,2) NOT NULL DEFAULT '0',
	`precoVenda` decimal(12,2),
	`fornecedorPrincipal` varchar(255),
	`localizacao` varchar(120),
	`ativo` tinyint NOT NULL DEFAULT 1,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `inventory_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `inventoryMovements` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`inventoryId` varchar(36) NOT NULL,
	`tipo` enum('entrada','saida','ajuste','perda') NOT NULL,
	`quantidade` decimal(12,3) NOT NULL,
	`saldoApos` decimal(12,3) NOT NULL,
	`motivo` varchar(255),
	`referenciaTipo` varchar(32),
	`referenciaId` varchar(36),
	`custoUnitMovimento` decimal(12,2),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `inventoryMovements_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `priceTables` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`material` varchar(120) NOT NULL,
	`precoPorM2` decimal(12,2) NOT NULL,
	`acabamentoExtra` decimal(12,2) NOT NULL DEFAULT '0',
	`ativo` tinyint NOT NULL DEFAULT 1,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `priceTables_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `quoteItems` (
	`id` varchar(36) NOT NULL,
	`quoteId` varchar(36) NOT NULL,
	`descricao` varchar(255) NOT NULL,
	`material` varchar(120),
	`larguraCm` decimal(8,2) NOT NULL,
	`alturaCm` decimal(8,2) NOT NULL,
	`quantidade` decimal(12,3) NOT NULL DEFAULT '1',
	`areaM2` decimal(12,4) NOT NULL,
	`precoPorM2` decimal(12,2) NOT NULL,
	`acabamento` varchar(120),
	`valorTotal` decimal(12,2) NOT NULL,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `quoteItems_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `quotes` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`customerId` varchar(36),
	`titulo` varchar(255) NOT NULL,
	`validadeDias` int NOT NULL DEFAULT 7,
	`status` enum('rascunho','enviado','aprovado','rejeitado','convertido') NOT NULL DEFAULT 'rascunho',
	`valorTotal` decimal(12,2) NOT NULL DEFAULT '0',
	`observacoes` text,
	`pdfKey` varchar(255),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `quotes_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `serviceOrderItems` (
	`id` varchar(36) NOT NULL,
	`serviceOrderId` varchar(36) NOT NULL,
	`tipo` enum('peca','servico') NOT NULL,
	`codigo` varchar(64),
	`descricao` varchar(255) NOT NULL,
	`quantidade` decimal(12,3) NOT NULL DEFAULT '1',
	`valorUnit` decimal(12,2) NOT NULL,
	`valorTotal` decimal(12,2) NOT NULL,
	`fornecedor` varchar(255),
	`tempoEstimadoHoras` decimal(6,2),
	`mecanicoResponsavel` varchar(255),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `serviceOrderItems_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `serviceOrderPhotos` (
	`id` varchar(36) NOT NULL,
	`serviceOrderId` varchar(36) NOT NULL,
	`tipo` enum('entrada','durante','saida') NOT NULL,
	`fileKey` varchar(255) NOT NULL,
	`descricao` varchar(255),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `serviceOrderPhotos_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `serviceOrderPublicTokens` (
	`id` varchar(36) NOT NULL,
	`serviceOrderId` varchar(36) NOT NULL,
	`token` varchar(64) NOT NULL,
	`expiresAt` varchar(32),
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `serviceOrderPublicTokens_id` PRIMARY KEY(`id`),
	CONSTRAINT `serviceOrderPublicTokens_token_unique` UNIQUE(`token`)
);
--> statement-breakpoint
CREATE TABLE `serviceOrders` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`customerId` varchar(36) NOT NULL,
	`vehicleId` varchar(36) NOT NULL,
	`numero` int NOT NULL,
	`queixaCliente` text,
	`diagnostico` text,
	`kmEntrada` int,
	`kmSaida` int,
	`status` enum('recepcao','diagnostico','orcamento','aguardando_aprovacao','aguardando_pecas','em_execucao','revisao','pronto','entregue') NOT NULL DEFAULT 'recepcao',
	`valorPecas` decimal(12,2) NOT NULL DEFAULT '0',
	`valorMaoObra` decimal(12,2) NOT NULL DEFAULT '0',
	`valorTotal` decimal(12,2) NOT NULL DEFAULT '0',
	`dataEntrada` varchar(32) NOT NULL,
	`dataPrevista` varchar(32),
	`dataSaida` varchar(32),
	`aprovacaoCliente` tinyint,
	`aprovacaoData` varchar(32),
	`aprovacaoIp` varchar(64),
	`aprovacaoComentario` text,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `serviceOrders_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `vehicles` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`customerId` varchar(36) NOT NULL,
	`placa` varchar(8) NOT NULL,
	`chassi` varchar(17),
	`marca` varchar(64) NOT NULL,
	`modelo` varchar(120) NOT NULL,
	`ano` int,
	`cor` varchar(32),
	`kmAtual` int NOT NULL DEFAULT 0,
	`observacoes` text,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	`updatedAt` timestamp NOT NULL DEFAULT (now()) ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT `vehicles_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `whatsappMessages` (
	`id` varchar(36) NOT NULL,
	`userId` int NOT NULL,
	`customerId` varchar(36),
	`telefone` varchar(32) NOT NULL,
	`mensagem` text NOT NULL,
	`tipo` enum('enviada','recebida','falhou','pending') NOT NULL DEFAULT 'pending',
	`referenciaTipo` varchar(32),
	`referenciaId` varchar(36),
	`providerMessageId` varchar(64),
	`erro` text,
	`createdAt` timestamp NOT NULL DEFAULT (now()),
	CONSTRAINT `whatsappMessages_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
ALTER TABLE `ops` ADD `pipelineStage` enum('briefing','arte','aprovado','pre_impressao','impressao','acabamento','entregue');--> statement-breakpoint
ALTER TABLE `artworkApprovals` ADD CONSTRAINT `artworkApprovals_artworkId_artworks_id_fk` FOREIGN KEY (`artworkId`) REFERENCES `artworks`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `artworks` ADD CONSTRAINT `artworks_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `artworks` ADD CONSTRAINT `artworks_opId_ops_id_fk` FOREIGN KEY (`opId`) REFERENCES `ops`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `artworks` ADD CONSTRAINT `artworks_quoteId_quotes_id_fk` FOREIGN KEY (`quoteId`) REFERENCES `quotes`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `inventory` ADD CONSTRAINT `inventory_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `inventory` ADD CONSTRAINT `inventory_produtoId_produtos_id_fk` FOREIGN KEY (`produtoId`) REFERENCES `produtos`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `inventoryMovements` ADD CONSTRAINT `inventoryMovements_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `inventoryMovements` ADD CONSTRAINT `inventoryMovements_inventoryId_inventory_id_fk` FOREIGN KEY (`inventoryId`) REFERENCES `inventory`(`id`) ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `priceTables` ADD CONSTRAINT `priceTables_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `quoteItems` ADD CONSTRAINT `quoteItems_quoteId_quotes_id_fk` FOREIGN KEY (`quoteId`) REFERENCES `quotes`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `quotes` ADD CONSTRAINT `quotes_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `quotes` ADD CONSTRAINT `quotes_customerId_customers_id_fk` FOREIGN KEY (`customerId`) REFERENCES `customers`(`id`) ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `serviceOrderItems` ADD CONSTRAINT `serviceOrderItems_serviceOrderId_serviceOrders_id_fk` FOREIGN KEY (`serviceOrderId`) REFERENCES `serviceOrders`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `serviceOrderPhotos` ADD CONSTRAINT `serviceOrderPhotos_serviceOrderId_serviceOrders_id_fk` FOREIGN KEY (`serviceOrderId`) REFERENCES `serviceOrders`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `serviceOrderPublicTokens` ADD CONSTRAINT `serviceOrderPublicTokens_serviceOrderId_serviceOrders_id_fk` FOREIGN KEY (`serviceOrderId`) REFERENCES `serviceOrders`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `serviceOrders` ADD CONSTRAINT `serviceOrders_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `serviceOrders` ADD CONSTRAINT `serviceOrders_customerId_customers_id_fk` FOREIGN KEY (`customerId`) REFERENCES `customers`(`id`) ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `serviceOrders` ADD CONSTRAINT `serviceOrders_vehicleId_vehicles_id_fk` FOREIGN KEY (`vehicleId`) REFERENCES `vehicles`(`id`) ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `vehicles` ADD CONSTRAINT `vehicles_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `vehicles` ADD CONSTRAINT `vehicles_customerId_customers_id_fk` FOREIGN KEY (`customerId`) REFERENCES `customers`(`id`) ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `whatsappMessages` ADD CONSTRAINT `whatsappMessages_userId_users_id_fk` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE `whatsappMessages` ADD CONSTRAINT `whatsappMessages_customerId_customers_id_fk` FOREIGN KEY (`customerId`) REFERENCES `customers`(`id`) ON DELETE set null ON UPDATE no action;--> statement-breakpoint
CREATE INDEX `idx_artworkApprovals_artworkId` ON `artworkApprovals` (`artworkId`);--> statement-breakpoint
CREATE INDEX `idx_artworks_userId` ON `artworks` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_artworks_opId` ON `artworks` (`opId`);--> statement-breakpoint
CREATE INDEX `idx_inventory_userId` ON `inventory` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_inventory_produtoId` ON `inventory` (`produtoId`);--> statement-breakpoint
CREATE INDEX `idx_inventory_codigo` ON `inventory` (`codigo`);--> statement-breakpoint
CREATE INDEX `idx_inventoryMovements_userId` ON `inventoryMovements` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_inventoryMovements_inventoryId` ON `inventoryMovements` (`inventoryId`);--> statement-breakpoint
CREATE INDEX `idx_inventoryMovements_referenciaId` ON `inventoryMovements` (`referenciaId`);--> statement-breakpoint
CREATE INDEX `idx_priceTables_userId` ON `priceTables` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_quoteItems_quoteId` ON `quoteItems` (`quoteId`);--> statement-breakpoint
CREATE INDEX `idx_quotes_userId` ON `quotes` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_quotes_customerId` ON `quotes` (`customerId`);--> statement-breakpoint
CREATE INDEX `idx_serviceOrderItems_serviceOrderId` ON `serviceOrderItems` (`serviceOrderId`);--> statement-breakpoint
CREATE INDEX `idx_serviceOrderPhotos_serviceOrderId` ON `serviceOrderPhotos` (`serviceOrderId`);--> statement-breakpoint
CREATE INDEX `idx_serviceOrderPublicTokens_serviceOrderId` ON `serviceOrderPublicTokens` (`serviceOrderId`);--> statement-breakpoint
CREATE INDEX `idx_serviceOrders_userId` ON `serviceOrders` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_serviceOrders_customerId` ON `serviceOrders` (`customerId`);--> statement-breakpoint
CREATE INDEX `idx_serviceOrders_vehicleId` ON `serviceOrders` (`vehicleId`);--> statement-breakpoint
CREATE INDEX `idx_serviceOrders_status` ON `serviceOrders` (`status`);--> statement-breakpoint
CREATE INDEX `idx_vehicles_userId` ON `vehicles` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_vehicles_customerId` ON `vehicles` (`customerId`);--> statement-breakpoint
CREATE INDEX `idx_vehicles_placa` ON `vehicles` (`placa`);--> statement-breakpoint
CREATE INDEX `idx_whatsappMessages_userId` ON `whatsappMessages` (`userId`);--> statement-breakpoint
CREATE INDEX `idx_whatsappMessages_customerId` ON `whatsappMessages` (`customerId`);