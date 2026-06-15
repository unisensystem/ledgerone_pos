<?php
// One-time migration: widen suppliers.salesman_id from INT(11) to VARCHAR(255)
// DELETE THIS FILE after running it.
require_once 'includes/connection.php';

header('Content-Type: text/plain');

try {
    // 1. Drop FK constraint if it still exists
    $fkCheck = $pdo->query("
        SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'suppliers'
          AND CONSTRAINT_NAME = 'fk_suppliers_salesman'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ")->fetch();

    if ($fkCheck) {
        $pdo->exec("ALTER TABLE suppliers DROP FOREIGN KEY fk_suppliers_salesman");
        echo "Dropped FK: fk_suppliers_salesman\n";
    } else {
        echo "FK fk_suppliers_salesman not found (already removed or never existed)\n";
    }

    // 2. Drop the index if it still exists
    $idxCheck = $pdo->query("
        SELECT INDEX_NAME FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'suppliers'
          AND INDEX_NAME = 'fk_suppliers_salesman'
        LIMIT 1
    ")->fetch();

    if ($idxCheck) {
        $pdo->exec("ALTER TABLE suppliers DROP INDEX fk_suppliers_salesman");
        echo "Dropped index: fk_suppliers_salesman\n";
    } else {
        echo "Index fk_suppliers_salesman not found (already removed)\n";
    }

    // 3. Change column type
    $col = $pdo->query("
        SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'suppliers'
          AND COLUMN_NAME = 'salesman_id'
    ")->fetch();

    if ($col && $col['DATA_TYPE'] !== 'varchar') {
        $pdo->exec("ALTER TABLE suppliers MODIFY COLUMN salesman_id VARCHAR(255) DEFAULT NULL");
        echo "Column salesman_id changed to VARCHAR(255)\n";
    } else {
        echo "Column already VARCHAR({$col['CHARACTER_MAXIMUM_LENGTH']}) — no change needed\n";
    }

    // 4. Drop and recreate triggers so they compile against the new schema
    $pdo->exec("DROP TRIGGER IF EXISTS trg_suppliers_balance_insert");
    $pdo->exec("DROP TRIGGER IF EXISTS trg_suppliers_balance_update");
    echo "Dropped old triggers\n";

    $pdo->exec("
        CREATE TRIGGER trg_suppliers_balance_insert
        BEFORE INSERT ON suppliers FOR EACH ROW
        BEGIN
            SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
            IF NEW.is_blacklisted = TRUE THEN
                SET NEW.status = 'BLACKLISTED';
            END IF;
        END
    ");
    $pdo->exec("
        CREATE TRIGGER trg_suppliers_balance_update
        BEFORE UPDATE ON suppliers FOR EACH ROW
        BEGIN
            SET NEW.current_balance = COALESCE(NEW.opening_debit_amount, 0) - COALESCE(NEW.opening_credit_amount, 0);
            IF NEW.is_blacklisted = TRUE THEN
                SET NEW.status = 'BLACKLISTED';
            ELSEIF NEW.is_blacklisted = FALSE AND OLD.status = 'BLACKLISTED' THEN
                SET NEW.status = 'ACTIVE';
            END IF;
            SET NEW.updated_at = CURRENT_TIMESTAMP;
        END
    ");
    echo "Recreated triggers against new VARCHAR(255) schema\n";

    // 5. Verify
    $verify = $pdo->query("
        SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'suppliers'
          AND COLUMN_NAME = 'salesman_id'
    ")->fetch();
    echo "\nFinal column type: {$verify['DATA_TYPE']}({$verify['CHARACTER_MAXIMUM_LENGTH']})\n";
    echo "\nMigration complete. DELETE this file now.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
