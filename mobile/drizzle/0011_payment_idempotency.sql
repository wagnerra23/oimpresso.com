-- F3-03 P0 — End-to-end idempotency for payments + webhook dedup.
--
-- 1) Add `idempotencyKey` to paymentLinks + unique (companyId, idempotencyKey).
--    NULL values do NOT collide in MySQL unique indexes, so legacy rows
--    (idempotencyKey = NULL) remain valid.
-- 2) Create `webhookEvents` table with unique (provider, eventType, externalId)
--    so duplicate webhook deliveries fail INSERT with ER_DUP_ENTRY (1062) and
--    the handler returns 200 OK without re-processing.

ALTER TABLE `paymentLinks`
  ADD COLUMN `idempotencyKey` varchar(64) DEFAULT NULL;
--> statement-breakpoint
CREATE UNIQUE INDEX `unique_paymentLinks_company_idempotency`
  ON `paymentLinks` (`companyId`, `idempotencyKey`);
--> statement-breakpoint
CREATE TABLE `webhookEvents` (
  `id` varchar(36) NOT NULL,
  `provider` varchar(32) NOT NULL,
  `eventType` varchar(64) NOT NULL,
  `externalId` varchar(128) NOT NULL,
  `payload` text,
  `processedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `webhookEvents_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE UNIQUE INDEX `uniq_webhookEvents`
  ON `webhookEvents` (`provider`, `eventType`, `externalId`);
--> statement-breakpoint
CREATE INDEX `idx_webhookEvents_provider`
  ON `webhookEvents` (`provider`);
