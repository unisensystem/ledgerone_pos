<?php
// Records a Create/Update/Delete action into activity_logs for the Audit Trail feature.
// Never let a logging failure break the actual business action (add/edit/delete) it's
// attached to - always fails silently into the PHP error log instead of throwing.
function logActivity($pdo, $tenant_id, $user_id, $action, $module, $record_id, $record_reference, $description, $old_values = null, $new_values = null) {
    try {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_name = $user['full_name'] ?? 'Unknown User';

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs
                (tenant_id, user_id, user_name, action, module, record_id, record_reference, description, old_values, new_values, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenant_id,
            $user_id,
            $user_name,
            $action,
            $module,
            $record_id,
            $record_reference,
            $description,
            $old_values !== null ? json_encode($old_values) : null,
            $new_values !== null ? json_encode($new_values) : null,
            $ip_address
        ]);
    } catch (Exception $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

// Builds a human-readable "Field: old → new" list for whichever fields actually changed,
// so an edit's activity log entry says exactly what was changed instead of just "Updated".
// $fieldLabels is [db_key => display label]; $old/$new are assoc arrays keyed the same way.
function buildChangeDescription($old, $new, $fieldLabels) {
    $changes = [];
    foreach ($fieldLabels as $key => $label) {
        $oldVal = $old[$key] ?? null;
        $newVal = $new[$key] ?? null;

        $oldIsEmpty = ($oldVal === null || $oldVal === '');
        $newIsEmpty = ($newVal === null || $newVal === '');

        if ($oldIsEmpty && $newIsEmpty) {
            continue; // both "no value" - not a real change
        }

        if (!$oldIsEmpty && !$newIsEmpty && is_numeric($oldVal) && is_numeric($newVal)) {
            // Old values come from the DB (often fixed-decimal, e.g. "0.00") and new values
            // from $_POST (plain numbers, e.g. "0") - compare numerically so formatting
            // differences alone don't get flagged as a change.
            $changed = (float)$oldVal != (float)$newVal;
        } else {
            $changed = $oldIsEmpty !== $newIsEmpty || (string)$oldVal !== (string)$newVal;
        }

        if ($changed) {
            $oldDisplay = $oldIsEmpty ? '(empty)' : $oldVal;
            $newDisplay = $newIsEmpty ? '(empty)' : $newVal;
            $changes[] = "$label: $oldDisplay \u{2192} $newDisplay";
        }
    }
    return $changes;
}
