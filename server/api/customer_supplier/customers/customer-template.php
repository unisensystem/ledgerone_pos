<?php
// Simple CSV template - no PhpSpreadsheet needed just for this
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="customer_import_template.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, [
    'Customer Name',
    'Shopkeeper Name',
    'Address',
    'Primary Phone',
    'Secondary Phone',
    'Email',
    'Opening Debit',
    'Opening Credit'
]);
// One example row to show the expected format
fputcsv($output, [
    'Faisal Rehman Traders',
    'Faisal',
    'Bhakkar',
    '03001234567',
    '',
    '',
    '5000',
    ''
]);
fclose($output);
exit;