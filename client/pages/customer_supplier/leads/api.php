<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

if (session_status() == PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$tenant_id = $_SESSION['tenant_id'] ?? null;
$user_id   = $_SESSION['user_id'] ?? null;

if (!$tenant_id || !$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';


function generateNextLeadCode($pdo, $tenant_id) {
    try {
        $stmt = $pdo->prepare("SELECT lead_code FROM leads WHERE tenant_id = ? AND lead_code LIKE 'LED%' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $lastLead = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastLead && $lastLead['lead_code']) {
            $lastNumber = (int)substr($lastLead['lead_code'], 3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return 'LED' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        return 'LED001';
    }
}

try {
    switch ($method) {
        case 'GET':
            handleGet($action);
            break;
        case 'POST':
            handlePost($action);
            break;
        case 'PUT':
            handlePut($action);
            break;
        case 'DELETE':
            handleDelete($action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGet($action) {
    global $pdo, $tenant_id;
    
    switch ($action) {
        case 'leads':
            $stmt = $pdo->prepare("SELECT * FROM leads WHERE tenant_id = ? ORDER BY id DESC");
            $stmt->execute([$tenant_id]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'services':
            $stmt = $pdo->prepare("SELECT DISTINCT service_type as service_name FROM leads WHERE tenant_id = ? AND service_type IS NOT NULL AND service_type != '' ORDER BY service_type");
            $stmt->execute([$tenant_id]);
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($services)) {
                $services = [
                    ['service_name' => 'Web Development'],
                    ['service_name' => 'SEO'],
                    ['service_name' => 'Social Media Marketing']
                ];
            }
            echo json_encode($services);
            break;
            
        case 'lead':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            $lead = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($lead ?: ['error' => 'Lead not found']);
            break;
            
        case 'followups':
            $leadId = $_GET['lead_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM followups WHERE lead_id = ? AND tenant_id = ? ORDER BY followup_date DESC, created_at DESC");
            $stmt->execute([$leadId, $tenant_id]);
            $followups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($followups);
            break;
            
        case 'followup':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM followups WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$id, $tenant_id]);
            $followup = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($followup ?: ['error' => 'Follow-up not found']);
            break;
            
        case 'next_lead_code':
            echo json_encode(['lead_code' => generateNextLeadCode($pdo, $tenant_id)]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePost($action) {
    global $pdo, $tenant_id, $user_id;
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'lead':
            try {
                $leadCode = generateNextLeadCode($pdo, $tenant_id);
                $leadDateTime = $data['lead_date'] . ' ' . date('H:i:s');
                
                $stmt = $pdo->prepare("
                    INSERT INTO leads (tenant_id, lead_code, lead_source, lead_date, contact_name, company_name, email, whatsapp, service_type, lead_status, priority) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $tenant_id,
                    $leadCode,
                    $data['lead_source'] ?: null,
                    $leadDateTime,
                    $data['contact_name'] ?: null,
                    $data['company_name'],
                    $data['email'] ?: null,
                    $data['whatsapp'] ?: null,
                    $data['service_type'] ?: null,
                    $data['lead_status'] ?: null,
                    $data['priority'] ?: null
                ]);
                
                if ($result) {
                    $newLeadId = $pdo->lastInsertId();

                    logActivity(
                        $pdo, $tenant_id, $user_id, 'create', 'Lead', $newLeadId, $leadCode,
                        "Created lead \"{$data['company_name']}\" ($leadCode)",
                        null,
                        ['lead_code' => $leadCode, 'company_name' => $data['company_name'], 'contact_name' => $data['contact_name'] ?: null, 'lead_status' => $data['lead_status'] ?: null]
                    );

                    echo json_encode([
                        'success' => true,
                        'id' => $newLeadId,
                        'lead_code' => $leadCode,
                        'message' => 'Lead created successfully'
                    ]);
                } else {
                    throw new Exception('Failed to insert lead record');
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Database error: ' . $e->getMessage()
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'followup':
            try {
                if (!$data || !isset($data['lead_id']) || !$data['lead_id']) throw new Exception('Invalid lead ID provided');
                if (empty($data['followup_date'])) throw new Exception('Follow-up date is required');
                if (empty($data['event_type'])) throw new Exception('Event type is required');
                if (empty($data['account_manager'])) throw new Exception('Account manager is required');
                if (empty($data['job_no'])) throw new Exception('Job number is required');
                
                $stmt = $pdo->prepare("
                    INSERT INTO followups (tenant_id, lead_id, followup_date, event_type, account_manager, job_no, remarks, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $result = $stmt->execute([
                    $tenant_id,
                    $data['lead_id'],
                    $data['followup_date'],
                    $data['event_type'],
                    $data['account_manager'],
                    $data['job_no'],
                    $data['remarks'] ?: null
                ]);
                
                if ($result) {
                    $newFollowupId = $pdo->lastInsertId();

                    $leadCodeStmt = $pdo->prepare("SELECT lead_code, company_name FROM leads WHERE id = ? AND tenant_id = ?");
                    $leadCodeStmt->execute([$data['lead_id'], $tenant_id]);
                    $leadInfo = $leadCodeStmt->fetch(PDO::FETCH_ASSOC);
                    $leadLabel = $leadInfo ? "{$leadInfo['lead_code']} ({$leadInfo['company_name']})" : "Lead #{$data['lead_id']}";

                    logActivity(
                        $pdo, $tenant_id, $user_id, 'create', 'Lead Followup', $newFollowupId, $leadLabel,
                        "Created follow-up for $leadLabel: {$data['event_type']} on {$data['followup_date']}",
                        null,
                        ['lead_id' => $data['lead_id'], 'followup_date' => $data['followup_date'], 'event_type' => $data['event_type'], 'account_manager' => $data['account_manager'], 'job_no' => $data['job_no']]
                    );

                    echo json_encode([
                        'success' => true,
                        'id' => $newFollowupId,
                        'message' => 'Follow-up created successfully'
                    ]);
                } else {
                    throw new Exception('Failed to insert follow-up record');
                }
                
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Database error: ' . $e->getMessage()
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'error' => $e->getMessage()
                ]);
            }
            break;
            
        case 'service':
            echo json_encode(['success' => true, 'message' => 'Service type will be available when used in leads']);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePut($action) {
    global $pdo, $tenant_id, $user_id;
    $data = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'lead':
            try {
                $id = $_GET['id'] ?? 0;

                $leadDateTime = $data['lead_date'];
                if (strlen($leadDateTime) == 10) {
                    $leadDateTime .= ' ' . date('H:i:s');
                }

                $oldStmt = $pdo->prepare("SELECT lead_code, lead_source, lead_date, contact_name, company_name, email, whatsapp, service_type, lead_status, priority FROM leads WHERE id = ? AND tenant_id = ?");
                $oldStmt->execute([$id, $tenant_id]);
                $oldLead = $oldStmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("
                    UPDATE leads SET
                    lead_source=?, lead_date=?, contact_name=?, company_name=?,
                    email=?, whatsapp=?, service_type=?, lead_status=?, priority=?
                    WHERE id=? AND tenant_id=?
                ");

                $result = $stmt->execute([
                    $data['lead_source'] ?: null,
                    $leadDateTime,
                    $data['contact_name'] ?: null,
                    $data['company_name'],
                    $data['email'] ?: null,
                    $data['whatsapp'] ?: null,
                    $data['service_type'] ?: null,
                    $data['lead_status'] ?: null,
                    $data['priority'] ?: null,
                    $id,
                    $tenant_id
                ]);

                if ($oldLead) {
                    $newLead = [
                        'lead_code' => $oldLead['lead_code'], 'lead_source' => $data['lead_source'] ?: null, 'lead_date' => $leadDateTime,
                        'contact_name' => $data['contact_name'] ?: null, 'company_name' => $data['company_name'],
                        'email' => $data['email'] ?: null, 'whatsapp' => $data['whatsapp'] ?: null,
                        'service_type' => $data['service_type'] ?: null, 'lead_status' => $data['lead_status'] ?: null, 'priority' => $data['priority'] ?: null
                    ];
                    $changeDescriptions = buildChangeDescription($oldLead, $newLead, [
                        'lead_source' => 'Source', 'contact_name' => 'Contact Name', 'company_name' => 'Company Name',
                        'email' => 'Email', 'whatsapp' => 'WhatsApp', 'service_type' => 'Service Type', 'lead_status' => 'Status', 'priority' => 'Priority'
                    ]);
                    $editDescription = !empty($changeDescriptions)
                        ? "Updated lead \"{$data['company_name']}\" ({$oldLead['lead_code']}): " . implode('; ', $changeDescriptions)
                        : "Updated lead \"{$data['company_name']}\" ({$oldLead['lead_code']}) - no tracked field changes";
                    logActivity($pdo, $tenant_id, $user_id, 'update', 'Lead', $id, $oldLead['lead_code'], $editDescription, $oldLead, $newLead);
                }

                echo json_encode(['success' => true, 'message' => 'Lead updated successfully']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'followup':
            try {
                $id = $_GET['id'] ?? 0;

                $oldStmt = $pdo->prepare("SELECT lead_id, followup_date, event_type, account_manager, job_no, remarks FROM followups WHERE id = ? AND tenant_id = ?");
                $oldStmt->execute([$id, $tenant_id]);
                $oldFollowup = $oldStmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("
                    UPDATE followups SET
                    followup_date=?, event_type=?, account_manager=?, job_no=?, remarks=?
                    WHERE id=? AND tenant_id=?
                ");

                $result = $stmt->execute([
                    $data['followup_date'],
                    $data['event_type'],
                    $data['account_manager'],
                    $data['job_no'],
                    $data['remarks'] ?: null,
                    $id,
                    $tenant_id
                ]);

                if ($oldFollowup) {
                    $leadCodeStmt = $pdo->prepare("SELECT lead_code, company_name FROM leads WHERE id = ? AND tenant_id = ?");
                    $leadCodeStmt->execute([$oldFollowup['lead_id'], $tenant_id]);
                    $leadInfo = $leadCodeStmt->fetch(PDO::FETCH_ASSOC);
                    $leadLabel = $leadInfo ? "{$leadInfo['lead_code']} ({$leadInfo['company_name']})" : "Lead #{$oldFollowup['lead_id']}";

                    $newFollowup = [
                        'followup_date' => $data['followup_date'], 'event_type' => $data['event_type'],
                        'account_manager' => $data['account_manager'], 'job_no' => $data['job_no'], 'remarks' => $data['remarks'] ?: null
                    ];
                    $changeDescriptions = buildChangeDescription($oldFollowup, $newFollowup, [
                        'followup_date' => 'Follow-up Date', 'event_type' => 'Event Type', 'account_manager' => 'Account Manager',
                        'job_no' => 'Job No', 'remarks' => 'Remarks'
                    ]);
                    $editDescription = !empty($changeDescriptions)
                        ? "Updated follow-up for $leadLabel: " . implode('; ', $changeDescriptions)
                        : "Updated follow-up for $leadLabel - no tracked field changes";
                    logActivity($pdo, $tenant_id, $user_id, 'update', 'Lead Followup', $id, $leadLabel, $editDescription, $oldFollowup, $newFollowup);
                }

                echo json_encode(['success' => true, 'message' => 'Follow-up updated successfully']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handleDelete($action) {
    global $pdo, $tenant_id, $user_id;

    switch ($action) {
        case 'lead':
            try {
                $id = $_GET['id'] ?? 0;

                $oldStmt = $pdo->prepare("SELECT * FROM leads WHERE id = ? AND tenant_id = ?");
                $oldStmt->execute([$id, $tenant_id]);
                $leadRow = $oldStmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("DELETE FROM followups WHERE lead_id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);

                $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);

                if ($leadRow) {
                    logActivity(
                        $pdo, $tenant_id, $user_id, 'delete', 'Lead', $id, $leadRow['lead_code'],
                        "Deleted lead \"{$leadRow['company_name']}\" ({$leadRow['lead_code']})",
                        $leadRow, null
                    );
                }

                echo json_encode(['success' => true, 'message' => 'Lead and associated follow-ups deleted successfully']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'followup':
            try {
                $id = $_GET['id'] ?? 0;

                $oldStmt = $pdo->prepare("SELECT f.*, l.lead_code, l.company_name FROM followups f LEFT JOIN leads l ON f.lead_id = l.id AND l.tenant_id = f.tenant_id WHERE f.id = ? AND f.tenant_id = ?");
                $oldStmt->execute([$id, $tenant_id]);
                $followupRow = $oldStmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("DELETE FROM followups WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);

                if ($followupRow) {
                    $leadLabel = $followupRow['lead_code'] ? "{$followupRow['lead_code']} ({$followupRow['company_name']})" : "Lead #{$followupRow['lead_id']}";
                    logActivity(
                        $pdo, $tenant_id, $user_id, 'delete', 'Lead Followup', $id, $leadLabel,
                        "Deleted follow-up for $leadLabel",
                        $followupRow, null
                    );
                }

                echo json_encode(['success' => true, 'message' => 'Follow-up deleted successfully']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'service':
            echo json_encode(['success' => true]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}
?>