<?php
require_once '../../../../includes/connection.php';

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
                    echo json_encode([
                        'success' => true, 
                        'id' => $pdo->lastInsertId(), 
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
                    echo json_encode([
                        'success' => true, 
                        'id' => $pdo->lastInsertId(),
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
    global $pdo, $tenant_id;
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'lead':
            try {
                $id = $_GET['id'] ?? 0;
                
                $leadDateTime = $data['lead_date'];
                if (strlen($leadDateTime) == 10) {
                    $leadDateTime .= ' ' . date('H:i:s');
                }
                
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
                
                echo json_encode(['success' => true, 'message' => 'Lead updated successfully']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'followup':
            try {
                $id = $_GET['id'] ?? 0;
                
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
    global $pdo, $tenant_id;
    
    switch ($action) {
        case 'lead':
            try {
                $id = $_GET['id'] ?? 0;
                
                $stmt = $pdo->prepare("DELETE FROM followups WHERE lead_id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
                
                echo json_encode(['success' => true, 'message' => 'Lead and associated follow-ups deleted successfully']);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'followup':
            try {
                $id = $_GET['id'] ?? 0;
                $stmt = $pdo->prepare("DELETE FROM followups WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$id, $tenant_id]);
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