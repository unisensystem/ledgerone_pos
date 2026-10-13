<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/role-helper.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

if (!isSuperAdmin($pdo, $user_id, $tenant_id)) {
    http_response_code(403);
    echo 'Only a Super Admin can export a log backup for deletion';
    exit;
}

$logType = $_GET['log_type'] ?? '';
$module = $_GET['module'] ?? '';
$action = $_GET['action'] ?? '';
$toDate = $_GET['to_date'] ?? '';
$format = $_GET['format'] ?? 'csv';

if (!in_array($logType, ['audit', 'login'], true) || !in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
    http_response_code(400);
    echo 'Invalid parameters';
    exit;
}

// Generous cap so a backup export can't exhaust memory on an enormous, unfiltered table.
$maxRows = $format === 'pdf' ? 2000 : 50000;

try {
    if ($logType === 'audit') {
        $where = ['tenant_id = ?'];
        $params = [$tenant_id];
        if ($module !== '') { $where[] = 'module = ?'; $params[] = $module; }
        if ($action !== '') { $where[] = 'action = ?'; $params[] = $action; }
        if ($toDate !== '') { $where[] = 'DATE(created_at) <= ?'; $params[] = $toDate; }

        $stmt = $pdo->prepare("
            SELECT created_at, user_name, action, module, record_reference, record_id, description
            FROM activity_logs
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC
            LIMIT $maxRows
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $headers = ['Date/Time', 'User', 'Action', 'Module', 'Record', 'Description'];
        $tableRows = array_map(function ($r) {
            return [$r['created_at'], $r['user_name'], $r['action'], $r['module'], $r['record_reference'] ?: $r['record_id'], $r['description']];
        }, $rows);
    } else {
        $where = ['tenant_id = ?'];
        $params = [$tenant_id];
        if ($action !== '') { $where[] = 'attempt_status = ?'; $params[] = $action; }
        if ($toDate !== '') { $where[] = 'DATE(attempted_at) <= ?'; $params[] = $toDate; }

        $stmt = $pdo->prepare("
            SELECT la.attempted_at, la.ip_address, la.email, la.username, la.attempt_status, la.failure_reason, u.full_name
            FROM login_attempts la
            LEFT JOIN users u ON u.id = la.user_id AND u.tenant_id = la.tenant_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY la.attempted_at DESC
            LIMIT $maxRows
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $headers = ['Date/Time', 'User', 'Email', 'IP Address', 'Status', 'Failure Reason'];
        $tableRows = array_map(function ($r) {
            return [$r['attempted_at'], $r['full_name'] ?: '-', $r['email'] ?: $r['username'], $r['ip_address'], $r['attempt_status'], $r['failure_reason'] ?: '-'];
        }, $rows);
    }

    $filenameBase = ($logType === 'audit' ? 'audit-trail' : 'login-activity') . '-backup-' . date('Y-m-d_His');

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($tableRows as $row) fputcsv($out, $row);
        fclose($out);
        exit;
    }

    if ($format === 'xlsx') {
        require_once '../../../../vendor/autoload.php';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Backup');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1')
            ->getFont()->setBold(true);
        $sheet->fromArray($tableRows, null, 'A2');
        foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $col) {
            $sheet->getColumnDimension($col)->setWidth(24);
        }
        $sheet->freezePane('A2');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    }

    if ($format === 'pdf') {
        require_once '../../../../vendor/autoload.php';
        $html = '<style>
            body { font-family: sans-serif; font-size: 10px; }
            h2 { margin-bottom: 4px; }
            .meta { color: #555; margin-bottom: 12px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; word-break: break-word; }
            th { background: #f0f0f0; }
        </style>';
        $html .= '<h2>' . ($logType === 'audit' ? 'Audit Trail' : 'Login Activity') . ' Backup</h2>';
        $html .= '<div class="meta">Generated ' . htmlspecialchars(date('Y-m-d H:i:s')) . ' &middot; ' . count($tableRows) . ' record(s)'
            . ($module !== '' ? ' &middot; Module: ' . htmlspecialchars($module) : '')
            . ($action !== '' ? ' &middot; Action: ' . htmlspecialchars($action) : '')
            . ($toDate !== '' ? ' &middot; Up to: ' . htmlspecialchars($toDate) : '') . '</div>';
        $html .= '<table><thead><tr>';
        foreach ($headers as $h) $html .= '<th>' . htmlspecialchars($h) . '</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($tableRows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.pdf"');
        echo $dompdf->output();
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo 'Export failed: ' . $e->getMessage();
    exit;
}
