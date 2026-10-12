<?php

declare(strict_types=1);

if (!defined('OFFICEFLOW_APP')) {
    http_response_code(404);
    exit;
}

final class InventoryService
{
    public const TYPES = ['received', 'issued', 'adjustment'];

    public static function list(PDO $pdo, string $search = '', int $limit = 300): array
    {
        $limit = max(1, min($limit, 500));
        $params = [];
        $where = '';
        $search = trim($search);
        if ($search !== '') {
            $where = ' WHERE (name LIKE :search OR sku LIKE :search OR category LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        $statement = $pdo->prepare('SELECT * FROM inventory_items' . $where . ' ORDER BY name ASC LIMIT ' . $limit);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create(PDO $pdo, int $userId, array $input): int
    {
        $values = self::validate($pdo, $input, null);
        $values['created_by'] = $userId;

        $statement = $pdo->prepare(
            'INSERT INTO inventory_items (sku, name, category, unit, quantity, reorder_level, location, created_by)
             VALUES (:sku, :name, :category, :unit, :quantity, :reorder_level, :location, :created_by)'
        );
        $statement->execute($values);
        $id = (int) $pdo->lastInsertId();

        $quantity = (float) $values['quantity'];
        if ($quantity > 0) {
            self::recordMovement($pdo, $id, $userId, 'received', $quantity, 'Opening quantity');
        }

        ActivityService::record($pdo, $userId, 'created', 'inventory', $id, 'Created inventory item #' . $id);
        return $id;
    }

    public static function update(PDO $pdo, int $id, int $userId, array $input): void
    {
        if (self::find($pdo, $id) === null) {
            throw new RuntimeException('That item no longer exists.');
        }
        $values = self::validate($pdo, $input, $id);
        unset($values['quantity']); // quantity only moves through adjust(), which writes the ledger
        $values['id'] = $id;

        $statement = $pdo->prepare(
            'UPDATE inventory_items
             SET sku = :sku, name = :name, category = :category, unit = :unit,
                 reorder_level = :reorder_level, location = :location
             WHERE id = :id'
        );
        $statement->execute($values);
        ActivityService::record($pdo, $userId, 'updated', 'inventory', $id, 'Updated inventory item #' . $id);
    }

    /**
     * Move stock and write the ledger entry in one transaction, so the quantity
     * column and the movement history can never disagree.
     */
    public static function adjust(PDO $pdo, int $itemId, int $userId, string $type, string $quantity, string $note = ''): void
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Choose a valid movement type.');
        }
        if (!is_numeric($quantity) || (float) $quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }
        $amount = round((float) $quantity, 2);

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $item = self::find($pdo, $itemId);
            if ($item === null) {
                throw new RuntimeException('That item no longer exists.');
            }

            $current = (float) $item['quantity'];
            $delta = match ($type) {
                'received' => $amount,
                'issued' => -$amount,
                default => $amount,
            };
            $next = $current + $delta;
            if ($next < 0) {
                throw new InvalidArgumentException('That would take the stock below zero.');
            }

            $update = $pdo->prepare('UPDATE inventory_items SET quantity = :quantity WHERE id = :id');
            $update->execute(['quantity' => number_format($next, 2, '.', ''), 'id' => $itemId]);

            self::recordMovement($pdo, $itemId, $userId, $type, $delta, $note);

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        ActivityService::record($pdo, $userId, 'stock_moved', 'inventory', $itemId, sprintf('Stock %s on item #%d', $type, $itemId));
    }

    public static function movements(PDO $pdo, int $itemId, int $limit = 40): array
    {
        $limit = max(1, min($limit, 200));
        $statement = $pdo->prepare(
            'SELECT m.id, m.transaction_type, m.quantity, m.note, m.created_at, u.name AS actor_name
             FROM inventory_transactions m
             LEFT JOIN users u ON u.id = m.created_by
             WHERE m.item_id = :item_id
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT ' . $limit
        );
        $statement->execute(['item_id' => $itemId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function recentMovements(PDO $pdo, int $limit = 25): array
    {
        $limit = max(1, min($limit, 100));
        $statement = $pdo->prepare(
            'SELECT m.id, m.transaction_type, m.quantity, m.note, m.created_at,
                    i.name AS item_name, u.name AS actor_name
             FROM inventory_transactions m
             INNER JOIN inventory_items i ON i.id = m.item_id
             LEFT JOIN users u ON u.id = m.created_by
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT ' . $limit
        );
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(PDO $pdo, int $id): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM inventory_items WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function lowStock(PDO $pdo, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));
        $statement = $pdo->prepare(
            'SELECT id, name, quantity, reorder_level, unit
             FROM inventory_items
             WHERE quantity <= reorder_level
             ORDER BY (quantity - reorder_level) ASC, name ASC
             LIMIT ' . $limit
        );
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function lowStockCount(PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM inventory_items WHERE quantity <= reorder_level')->fetchColumn();
    }

    public static function count(PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM inventory_items')->fetchColumn();
    }

    private static function recordMovement(PDO $pdo, int $itemId, int $userId, string $type, float $quantity, string $note): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO inventory_transactions (item_id, transaction_type, quantity, note, created_by)
             VALUES (:item_id, :type, :quantity, :note, :created_by)'
        );
        $statement->execute([
            'item_id' => $itemId,
            'type' => $type,
            'quantity' => number_format($quantity, 2, '.', ''),
            'note' => $note === '' ? null : mb_substr($note, 0, 255, 'UTF-8'),
            'created_by' => $userId,
        ]);
    }

    private static function validate(PDO $pdo, array $input, ?int $id): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $sku = trim((string) ($input['sku'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $unit = trim((string) ($input['unit'] ?? 'pcs'));
        $location = trim((string) ($input['location'] ?? ''));
        $quantity = trim((string) ($input['quantity'] ?? '0'));
        $reorder = trim((string) ($input['reorder_level'] ?? '0'));

        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 180) {
            throw new InvalidArgumentException('Item name must be between 2 and 180 characters.');
        }
        if ($category === '' || mb_strlen($category, 'UTF-8') > 80) {
            throw new InvalidArgumentException('Category is required and must be 80 characters or fewer.');
        }
        if ($sku !== '' && mb_strlen($sku, 'UTF-8') > 80) {
            throw new InvalidArgumentException('SKU must be 80 characters or fewer.');
        }
        if ($unit === '' || mb_strlen($unit, 'UTF-8') > 30) {
            throw new InvalidArgumentException('Unit is required and must be 30 characters or fewer.');
        }
        if ($location !== '' && mb_strlen($location, 'UTF-8') > 120) {
            throw new InvalidArgumentException('Location must be 120 characters or fewer.');
        }
        if (!is_numeric($quantity) || (float) $quantity < 0) {
            throw new InvalidArgumentException('Quantity must be zero or more.');
        }
        if (!is_numeric($reorder) || (float) $reorder < 0) {
            throw new InvalidArgumentException('Reorder level must be zero or more.');
        }

        if ($sku !== '') {
            $statement = $pdo->prepare('SELECT id FROM inventory_items WHERE sku = :sku LIMIT 1');
            $statement->execute(['sku' => $sku]);
            $existing = $statement->fetchColumn();
            if ($existing !== false && (int) $existing !== (int) $id) {
                throw new InvalidArgumentException('That SKU is already used by another item.');
            }
        }

        return [
            'sku' => $sku === '' ? null : $sku,
            'name' => $name,
            'category' => $category,
            'unit' => $unit,
            'quantity' => number_format((float) $quantity, 2, '.', ''),
            'reorder_level' => number_format((float) $reorder, 2, '.', ''),
            'location' => $location === '' ? null : $location,
        ];
    }
}
