-- Backfill customers from existing pedidos.cliente values.
-- For each distinct (userId, cliente) we synthesize a customer row.
INSERT INTO `customers` (`id`, `userId`, `nome`, `tipo`)
SELECT UUID(), p.`userId`, p.`cliente`, 'PF'
FROM (
  SELECT DISTINCT `userId`, `cliente` FROM `pedidos`
  UNION
  SELECT DISTINCT `userId`, `cliente` FROM `ops`
) AS p
LEFT JOIN `customers` c
  ON c.`userId` = p.`userId` AND c.`nome` = p.`cliente`
WHERE c.`id` IS NULL;
--> statement-breakpoint
-- Link pedidos to their matching customer row.
UPDATE `pedidos` p
JOIN `customers` c ON c.`userId` = p.`userId` AND c.`nome` = p.`cliente`
SET p.`customerId` = c.`id`
WHERE p.`customerId` IS NULL;
--> statement-breakpoint
-- Same for ops.
UPDATE `ops` o
JOIN `customers` c ON c.`userId` = o.`userId` AND c.`nome` = o.`cliente`
SET o.`customerId` = c.`id`
WHERE o.`customerId` IS NULL;
--> statement-breakpoint
-- Seed an orderItem for every existing pedido so the new 1:N relationship is
-- populated. quantidade defaults to 1, valorUnit = valorTotal = pedidos.valor.
INSERT INTO `orderItems` (`id`, `pedidoId`, `descricao`, `quantidade`, `valorUnit`, `valorTotal`)
SELECT UUID(), p.`id`, p.`produto`, 1, p.`valor`, p.`valor`
FROM `pedidos` p
LEFT JOIN `orderItems` oi ON oi.`pedidoId` = p.`id`
WHERE oi.`id` IS NULL;
