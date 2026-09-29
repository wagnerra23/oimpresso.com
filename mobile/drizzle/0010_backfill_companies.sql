-- F3-08 Multiempresa — backfill
-- Creates a default company per existing user, registers them as owner via
-- companyMembers, and propagates the new companyId into every domain table.
-- Idempotent: re-running is safe (uses IGNORE / WHERE NOT EXISTS guards).

-- 1) One default company per user (idempotent guard: only if user has none yet).
INSERT INTO companies (id, ownerUserId, nome, vertical, ativa)
SELECT UUID(), u.id,
       COALESCE(NULLIF(TRIM(u.name), ''), u.email, CONCAT('Empresa ', u.id)),
       'outro', 1
FROM users u
WHERE NOT EXISTS (
  SELECT 1 FROM companies c WHERE c.ownerUserId = u.id
);
--> statement-breakpoint

-- 2) Owner membership row for every owner who lacks one.
INSERT INTO companyMembers (id, companyId, userId, role)
SELECT UUID(), c.id, c.ownerUserId, 'owner'
FROM companies c
WHERE NOT EXISTS (
  SELECT 1 FROM companyMembers m
  WHERE m.companyId = c.id AND m.userId = c.ownerUserId
);
--> statement-breakpoint

-- 3) Propagate companyId into every domain table by joining on the creator's
--    default company. The subquery picks the OLDEST company owned by the user,
--    which for backfilled data is the one we just created.

UPDATE produtos t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE customers t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE pedidos t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE ops t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE transacoes t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE pushTokens t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE priceTables t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE quotes t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE artworks t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE vehicles t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE serviceOrders t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE inventory t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE inventoryMovements t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE whatsappMessages t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE companySettings t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE fiscalDocuments t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE paymentLinks t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE customerMagicLinks t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint
UPDATE customerSessions t
JOIN (SELECT ownerUserId, MIN(createdAt) AS minAt FROM companies GROUP BY ownerUserId) m
  ON m.ownerUserId = t.userId
JOIN companies c ON c.ownerUserId = m.ownerUserId AND c.createdAt = m.minAt
SET t.companyId = c.id
WHERE t.companyId IS NULL;
--> statement-breakpoint

-- 4) Child tables (no userId). Backfill via parent FK.
UPDATE orderItems oi
JOIN pedidos p ON p.id = oi.pedidoId
SET oi.companyId = p.companyId
WHERE oi.companyId IS NULL;
--> statement-breakpoint
UPDATE quoteItems qi
JOIN quotes q ON q.id = qi.quoteId
SET qi.companyId = q.companyId
WHERE qi.companyId IS NULL;
--> statement-breakpoint
UPDATE artworkApprovals aa
JOIN artworks a ON a.id = aa.artworkId
SET aa.companyId = a.companyId
WHERE aa.companyId IS NULL;
--> statement-breakpoint
UPDATE serviceOrderItems si
JOIN serviceOrders s ON s.id = si.serviceOrderId
SET si.companyId = s.companyId
WHERE si.companyId IS NULL;
--> statement-breakpoint
UPDATE serviceOrderPhotos sp
JOIN serviceOrders s ON s.id = sp.serviceOrderId
SET sp.companyId = s.companyId
WHERE sp.companyId IS NULL;
--> statement-breakpoint
UPDATE serviceOrderPublicTokens st
JOIN serviceOrders s ON s.id = st.serviceOrderId
SET st.companyId = s.companyId
WHERE st.companyId IS NULL;
