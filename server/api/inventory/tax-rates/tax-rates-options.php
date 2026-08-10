<?php
require_once '../../../../includes/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$response = [
    'tax_types' => ['sales_tax', 'further_tax', 'wht', 'advance_tax', 'minimum_tax', 'value_addition_tax', 'fed'],
    'transaction_types' => ['sale', 'purchase', 'import', 'export', 'payment'],
    'applicable_to' => ['all', 'registered_company', 'unregistered', 'manufacturer', 'distributor', 'exporter', 'importer_commercial', 'importer_industrial'],
    'party_types' => ['registered_company', 'unregistered', 'manufacturer', 'distributor', 'exporter', 'importer_commercial', 'importer_industrial'],
    'deducted_by' => ['seller', 'buyer', 'customs'],
    'tax_authorities' => ['FBR', 'SBP', 'CUSTOMS', 'OTHER']
];

echo json_encode(['success' => true, 'data' => $response]);
?>
