<?php
// TEMPORARY DEBUG FILE — dumps the full `employees` table (all columns, current
// tenant only) so overtime_rate_per_hour / overtime_rate / overtime_rate_type
// values can be checked directly without opening phpMyAdmin.
//
// Upload this next to employee-add.php (same folder) and open it in the browser
// while logged in. DELETE THIS FILE once you're done debugging — it exposes raw
// employee/salary data with no access control beyond "logged in".

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../../../includes/connection.php';

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

// Columns worth watching for the overtime-rate bug. Everything else is shown too,
// but these get a highlighted column header/cells.
$highlightCols = ['overtime_rate_per_hour', 'overtime_rate', 'overtime_rate_type', 'base_salary'];

$stmt = $pdo->prepare("SELECT * FROM employees WHERE tenant_id = ? ORDER BY id DESC");
$stmt->execute([$tenant_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$columns = $rows ? array_keys($rows[0]) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DEBUG - Employees Table Dump</title>
<style>
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #f8fafc; padding: 20px; }
  h1 { font-size: 18px; margin-bottom: 4px; }
  p.warn { color: #dc2626; font-weight: 700; margin-bottom: 16px; }
  .wrap { overflow-x: auto; max-width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
  table { border-collapse: collapse; font-size: 12px; white-space: nowrap; }
  th, td { border: 1px solid #e2e8f0; padding: 6px 10px; text-align: left; }
  th { background: #f1f5f9; position: sticky; top: 0; z-index: 1; }
  th.hl, td.hl { background: #fef3c7; }
  th.hl { background: #fde68a; font-weight: 800; }
  tr:nth-child(even) td:not(.hl) { background: #fafbff; }
  .meta { font-size: 12px; color: #64748b; margin-bottom: 10px; }
</style>
</head>
<body>
  <h1>DEBUG: employees table (tenant_id = <?php echo htmlspecialchars($tenant_id); ?>)</h1>
  <p class="warn">⚠ Delete this file after debugging — it has no access control besides login.</p>
  <p class="meta"><?php echo count($rows); ?> rows. Highlighted columns: <?php echo implode(', ', $highlightCols); ?></p>

  <div class="wrap">
    <table>
      <thead>
        <tr>
          <?php foreach ($columns as $col): ?>
            <th class="<?php echo in_array($col, $highlightCols, true) ? 'hl' : ''; ?>"><?php echo htmlspecialchars($col); ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="<?php echo count($columns); ?>">No employees found for this tenant.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <tr>
            <?php foreach ($columns as $col): ?>
              <td class="<?php echo in_array($col, $highlightCols, true) ? 'hl' : ''; ?>">
                <?php echo $row[$col] === null ? '<i>NULL</i>' : htmlspecialchars($row[$col]); ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>