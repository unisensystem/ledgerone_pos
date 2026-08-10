<?php
// ============================================================
//  leads.php  ·  Single-file: Backend + Frontend
//  Place at: /your-project/modules/sales/leads/leads.php
//  Requires: ../../../../includes/connection.php  ($pdo)
//            ../../../../includes/dashboard.php
// ============================================================
require_once '../../../../includes/connection.php';

// ── SESSION ──────────────────────────────────────────────────
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit;
}

// ════════════════════════════════════════════════════════════
//  AJAX  ─  all JSON actions
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');

    $action = $_GET['action'];
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];

    function resp($ok, $data = [], $msg = '') {
        echo json_encode(['success' => $ok, 'message' => $msg] + $data);
        exit;
    }

    $tid = (int)$GLOBALS['tenant_id'];
    $uid = (int)$GLOBALS['user_id'];

    try {

        // ── 1. SAVE / UPDATE LEAD ─────────────────────────
        if ($action === 'save_lead') {
            if (empty($input['company_name'])) throw new Exception('Company Name is required');
            if (empty($input['lead_date']))    throw new Exception('Lead Date is required');

            if (!empty($input['id'])) {
                // UPDATE
                $st = $pdo->prepare("UPDATE leads SET
                    lead_date=?, contact_name=?, company_name=?, email=?,
                    whatsapp=?, lead_source=?, service_type_id=?, lead_status=?,
                    priority=?, remarks=?, updated_by=?, updated_at=NOW()
                    WHERE id=? AND tenant_id=?");
                $st->execute([
                    $input['lead_date'],
                    $input['contact_name']   ?: null,
                    $input['company_name'],
                    $input['email']          ?: null,
                    $input['whatsapp']       ?: null,
                    $input['lead_source']    ?: null,
                    !empty($input['service_type_id']) ? (int)$input['service_type_id'] : null,
                    $input['lead_status']    ?: 'new',
                    $input['priority']       ?: 'medium',
                    $input['remarks']        ?: null,
                    $uid,
                    (int)$input['id'],
                    $tid,
                ]);
                resp(true, [], 'Lead updated successfully');
            } else {
                // INSERT — generate next lead code
                $st = $pdo->prepare("SELECT lead_code FROM leads
                                      WHERE tenant_id=? AND is_deleted=0
                                      ORDER BY id DESC LIMIT 1");
                $st->execute([$tid]);
                $last = $st->fetchColumn();
                $num  = $last ? ((int)preg_replace('/\D/', '', $last)) + 1 : 1;
                $code = 'LED' . str_pad($num, 3, '0', STR_PAD_LEFT);

                $st = $pdo->prepare("INSERT INTO leads
                    (tenant_id, lead_code, lead_date, contact_name, company_name,
                     email, whatsapp, lead_source, service_type_id, lead_status,
                     priority, remarks, created_by, updated_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $st->execute([
                    $tid, $code,
                    $input['lead_date'],
                    $input['contact_name']   ?: null,
                    $input['company_name'],
                    $input['email']          ?: null,
                    $input['whatsapp']       ?: null,
                    $input['lead_source']    ?: null,
                    !empty($input['service_type_id']) ? (int)$input['service_type_id'] : null,
                    $input['lead_status']    ?: 'new',
                    $input['priority']       ?: 'medium',
                    $input['remarks']        ?: null,
                    $uid, $uid,
                ]);
                resp(true, ['lead_code' => $code, 'id' => $pdo->lastInsertId()], 'Lead saved successfully');
            }
        }

        // ── 2. LOAD LEADS (with pagination & filters) ────
        if ($action === 'leads') {
            $page     = max(1, (int)($input['page']     ?? 1));
            $pageSize = max(1, (int)($input['page_size'] ?? 8));
            $offset   = ($page - 1) * $pageSize;

            $where  = ['l.tenant_id = ?', 'l.is_deleted = 0'];
            $params = [$tid];

            if (!empty($input['search_name'])) {
                $where[]  = 'l.contact_name LIKE ?';
                $params[] = '%' . $input['search_name'] . '%';
            }
            if (!empty($input['search_company'])) {
                $where[]  = 'l.company_name LIKE ?';
                $params[] = '%' . $input['search_company'] . '%';
            }
            if (!empty($input['status'])) {
                $where[]  = 'l.lead_status = ?';
                $params[] = $input['status'];
            }
            if (!empty($input['priority'])) {
                $where[]  = 'l.priority = ?';
                $params[] = $input['priority'];
            }

            $wSql = 'WHERE ' . implode(' AND ', $where);

            // Total count
            $ct = $pdo->prepare("SELECT COUNT(*) FROM leads l $wSql");
            $ct->execute($params);
            $total = (int)$ct->fetchColumn();

            // Data
            $st = $pdo->prepare("SELECT l.id, l.lead_code, l.lead_date,
                                         l.contact_name, l.company_name,
                                         l.email, l.whatsapp, l.lead_source,
                                         l.lead_status, l.priority, l.remarks,
                                         l.service_type_id,
                                         st.service_name
                                  FROM leads l
                                  LEFT JOIN lead_service_types st ON st.id = l.service_type_id AND st.tenant_id = l.tenant_id
                                  $wSql
                                  ORDER BY l.id DESC
                                  LIMIT $pageSize OFFSET $offset");
            $st->execute($params);
            resp(true, [
                'data'       => $st->fetchAll(PDO::FETCH_ASSOC),
                'total'      => $total,
                'page'       => $page,
                'page_size'  => $pageSize,
                'pages'      => (int)ceil($total / $pageSize),
            ]);
        }

        // ── 3. GET SINGLE LEAD ────────────────────────────
        if ($action === 'get_lead') {
            $st = $pdo->prepare("SELECT l.*, st.service_name
                                  FROM leads l
                                  LEFT JOIN lead_service_types st ON st.id = l.service_type_id
                                  WHERE l.id=? AND l.tenant_id=? AND l.is_deleted=0");
            $st->execute([(int)$input['id'], $tid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Lead not found');
            resp(true, ['data' => $row]);
        }

        // ── 4. DELETE LEAD ────────────────────────────────
        if ($action === 'delete_lead') {
            $st = $pdo->prepare("UPDATE leads SET is_deleted=1, updated_by=?, updated_at=NOW()
                                  WHERE id=? AND tenant_id=?");
            $st->execute([$uid, (int)$input['id'], $tid]);
            resp(true, [], 'Lead deleted');
        }

        // ── 5. LOAD SERVICE TYPES ─────────────────────────
        if ($action === 'service_types') {
            $st = $pdo->prepare("SELECT id, service_name FROM lead_service_types
                                  WHERE (tenant_id=? OR tenant_id=0) AND is_active=1
                                  ORDER BY service_name");
            $st->execute([$tid]);
            resp(true, ['data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ── 6. ADD SERVICE TYPE ───────────────────────────
        if ($action === 'add_service_type') {
            if (empty($input['service_name'])) throw new Exception('Service name is required');
            // check duplicate
            $ch = $pdo->prepare("SELECT id FROM lead_service_types
                                  WHERE service_name=? AND tenant_id=? AND is_active=1");
            $ch->execute([trim($input['service_name']), $tid]);
            if ($ch->fetchColumn()) throw new Exception('Service type already exists');

            $st = $pdo->prepare("INSERT INTO lead_service_types (tenant_id, service_name) VALUES (?,?)");
            $st->execute([$tid, trim($input['service_name'])]);
            resp(true, ['id' => $pdo->lastInsertId()], 'Service type added');
        }

        // ── 7. DELETE SERVICE TYPE ────────────────────────
        if ($action === 'delete_service_type') {
            $st = $pdo->prepare("UPDATE lead_service_types SET is_active=0
                                  WHERE id=? AND tenant_id=?");
            $st->execute([(int)$input['id'], $tid]);
            resp(true, [], 'Service type deleted');
        }

        // ── 8. NEXT LEAD CODE ─────────────────────────────
        if ($action === 'next_code') {
            $st = $pdo->prepare("SELECT lead_code FROM leads
                                  WHERE tenant_id=? AND is_deleted=0
                                  ORDER BY id DESC LIMIT 1");
            $st->execute([$tid]);
            $last = $st->fetchColumn();
            $num  = $last ? ((int)preg_replace('/\D/', '', $last)) + 1 : 1;
            $code = 'LED' . str_pad($num, 3, '0', STR_PAD_LEFT);
            resp(true, ['code' => $code]);
        }

    } catch (Exception $e) {
        http_response_code(400);
        resp(false, [], $e->getMessage());
    }
    exit;
}

require_once '../../../../includes/dashboard.php';

// ════════════════════════════════════════════════════════════
//  HTML OUTPUT STARTS HERE
// ════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Leads Management - ParcelPro ERP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── RESET & BASE ──────────────────────────────────────────── */
*{margin:0;padding:0;box-sizing:border-box;font-family:"Segoe UI",system-ui,sans-serif}
body{background:#f7f9fc;color:#2f3b4c;line-height:1.5;padding:24px}
.container{max-width:1300px;margin:0 auto;display:flex;flex-direction:column;gap:20px}

/* ── PAGE HEADER ─────────────────────────────────────────── */
.page-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.page-header h1{font-size:22px;font-weight:700;color:#0e1a2b;display:flex;align-items:center;gap:10px}
.page-header h1 i{color:#1f7bff}

/* ── BUTTONS ────────────────────────────────────────────── */
.btn{height:38px;padding:0 18px;border-radius:7px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:7px;font-family:inherit;transition:all .15s;white-space:nowrap}
.btn-primary{background:#1f7bff;color:#fff}
.btn-primary:hover{background:#1a6cdc}
.btn-secondary{background:#eff2f7;border:1.5px solid #c9cfda;color:#2f3b4c}
.btn-secondary:hover{background:#e4e8ef}
.btn-danger{background:#e34f4f;color:#fff}
.btn-danger:hover{background:#c94040}
.btn:disabled{opacity:.5;cursor:not-allowed}

/* ── CARD ───────────────────────────────────────────────── */
.card{background:#fff;border:1px solid #e1e6ee;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.card-head{padding:11px 18px;background:#f0f3f8;border-bottom:1px solid #e1e6ee;font-size:13px;font-weight:700;color:#1f7bff;display:flex;align-items:center;gap:8px;letter-spacing:.4px}
.card-head i{font-size:13px}
.card-body{padding:18px 20px}

/* ── FORM GRID ──────────────────────────────────────────── */
.fg{display:grid;gap:14px;margin-bottom:14px}
.fg-2{grid-template-columns:1fr 1fr}
.fg-3{grid-template-columns:1fr 1fr 1fr}
.fg:last-child{margin-bottom:0}
.f{display:flex;flex-direction:column;gap:4px}
.f label{font-size:12px;font-weight:600;color:#2f3b4c;display:flex;align-items:center;gap:4px}
.req{color:#e34f4f}

input[type=text],input[type=email],input[type=date],input[type=tel],input[type=number],select,textarea{
  height:36px;padding:0 11px;border:1.5px solid #d6dbe4;border-radius:6px;
  background:#fff;font-size:13px;color:#2f3b4c;
  transition:border-color .15s,box-shadow .15s;font-family:inherit;width:100%}
input:focus,select:focus,textarea:focus{outline:none;border-color:#1f7bff;box-shadow:0 0 0 3px rgba(31,123,255,.1)}
input[readonly]{background:#f2f4f8;color:#9aa1ae;border-color:#e1e4ea;cursor:default}
input::placeholder,textarea::placeholder{color:#b8bfc9;font-size:12.5px}
select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='11' height='11' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 9px center;padding-right:26px;cursor:pointer}
textarea{height:70px;padding:8px 11px;resize:vertical;font-size:13px}

.inline-row{display:flex;gap:6px;align-items:flex-end}
.inline-row>:first-child{flex:1}
.btn-icon{width:36px;height:36px;border-radius:6px;border:none;background:#1f7bff;color:#fff;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:background .15s;flex-shrink:0}
.btn-icon:hover{background:#1a6cdc}

.form-actions{display:flex;justify-content:flex-end;gap:8px;padding-top:14px;border-top:1px solid #e1e6ee;margin-top:14px}

/* ── FILTERS ────────────────────────────────────────────── */
.list-filters{padding:12px 16px;display:flex;gap:8px;align-items:center;border-bottom:1px solid #e1e6ee;flex-wrap:wrap}
.list-filters input,.list-filters select{height:32px;font-size:12.5px;border-radius:6px;border:1.5px solid #d6dbe4;padding:0 10px;background:#fff;font-family:inherit;color:#2f3b4c;transition:border-color .15s}
.list-filters input:focus,.list-filters select:focus{outline:none;border-color:#1f7bff}
.list-filters input{flex:1;min-width:130px}
.list-filters select{min-width:120px;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;padding-right:24px;cursor:pointer}
.list-filters input::placeholder{color:#b8bfc9}
.clear-btn{height:32px;padding:0 12px;border-radius:6px;border:1.5px solid #d6dbe4;background:#fff;font-size:12.5px;font-weight:600;color:#6b7280;cursor:pointer;font-family:inherit;transition:all .13s}
.clear-btn:hover{border-color:#e34f4f;color:#e34f4f;background:#fef2f2}

/* ── TABLE ──────────────────────────────────────────────── */
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px;min-width:780px}
thead tr{background:#1a1f2e}
thead th{padding:11px 13px;text-align:left;font-size:11.5px;font-weight:700;color:#fff;white-space:nowrap;letter-spacing:.3px}
tbody tr{border-bottom:1px solid #edf0f4;transition:background .1s}
tbody tr:last-child{border-bottom:none}
tbody tr:hover{background:#f7f9fc}
tbody td{padding:10px 13px;vertical-align:middle;color:#2f3b4c}

.lead-code-btn{background:none;border:none;cursor:pointer;color:#1f7bff;font-weight:700;font-size:13px;font-family:inherit;padding:0;display:inline-flex;align-items:center;gap:4px;text-decoration:underline;text-underline-offset:2px}
.lead-code-btn:hover{color:#1a6cdc}
.lead-code-btn i{font-size:10px;color:#9aa1ae}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:11.5px;font-weight:700;letter-spacing:.2px}
.b-new      {background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.b-contacted{background:#f0fdf4;color:#166534;border:1px solid #86efac}
.b-qualified{background:#fefce8;color:#854d0e;border:1px solid #fde047}
.b-lost     {background:#fef2f2;color:#991b1b;border:1px solid #fca5a5}
.b-converted{background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7}
.b-follow   {background:#fdf4ff;color:#7e22ce;border:1px solid #d8b4fe}

.p-high  {color:#dc2626;font-weight:700;font-size:12.5px}
.p-medium{color:#d97706;font-weight:700;font-size:12.5px}
.p-low   {color:#16a34a;font-weight:700;font-size:12.5px}

/* ROW ACTIONS */
.row-acts{display:flex;gap:5px;align-items:center}
.ra{width:30px;height:30px;border-radius:7px;border:none;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;transition:opacity .13s,transform .1s}
.ra:hover{opacity:.82;transform:scale(1.08)}
.ra-view  {background:#0ea5e9;color:#fff}
.ra-edit  {background:#f59e0b;color:#fff}
.ra-delete{background:#e34f4f;color:#fff}

/* EMPTY STATE */
.empty-state{padding:48px 24px;text-align:center;color:#9aa1ae}
.empty-state i{font-size:38px;margin-bottom:12px;display:block;color:#d1d5db}
.empty-state p{font-size:13.5px;color:#6b7280;font-weight:500}

/* PAGINATION */
.pagination{display:flex;justify-content:space-between;align-items:center;padding:11px 16px;border-top:1px solid #e1e6ee;flex-wrap:wrap;gap:8px}
.page-info{font-size:12.5px;color:#6b7280}
.page-btns{display:flex;gap:4px}
.pg{width:30px;height:30px;border-radius:6px;border:1.5px solid #e1e6ee;background:#fff;font-size:12.5px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#2f3b4c;transition:all .13s;font-family:inherit}
.pg:hover{border-color:#1f7bff;color:#1f7bff;background:#f0f6ff}
.pg.active{background:#1f7bff;border-color:#1f7bff;color:#fff}
.pg:disabled{opacity:.4;cursor:default;pointer-events:none}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(14,26,43,.45);z-index:9000;align-items:center;justify-content:center;backdrop-filter:blur(2px)}
.modal-overlay.show{display:flex;animation:fadeIn .18s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal-box{background:#fff;border-radius:10px;width:92%;max-width:440px;box-shadow:0 8px 32px rgba(0,0,0,.18);overflow:hidden;animation:slideUp .2s ease}
.modal-box.wide{max-width:600px}
@keyframes slideUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.modal-head{padding:13px 18px;border-bottom:1px solid #e1e6ee;display:flex;justify-content:space-between;align-items:center}
.modal-head h3{font-size:14px;font-weight:700;color:#0e1a2b;display:flex;align-items:center;gap:7px}
.modal-head h3 i{color:#1f7bff}
.modal-head h3 i.danger{color:#e34f4f}
.modal-close{background:none;border:none;color:#9aa1ae;font-size:17px;cursor:pointer;width:27px;height:27px;border-radius:5px;display:flex;align-items:center;justify-content:center;transition:background .13s}
.modal-close:hover{background:#f0f3f8;color:#2f3b4c}
.modal-body{padding:18px;font-size:13.5px;color:#2f3b4c;line-height:1.6}
.modal-footer{padding:12px 18px;border-top:1px solid #e1e6ee;display:flex;justify-content:flex-end;gap:8px}

/* Service type list */
.stype-list{display:flex;flex-direction:column;gap:6px;margin-top:12px;max-height:200px;overflow-y:auto}
.stype-item{display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#fafbfd;border:1px solid #e1e6ee;border-radius:6px;font-size:13px}
.stype-del{background:none;border:none;color:#e34f4f;cursor:pointer;font-size:12px;padding:2px 5px;border-radius:4px;transition:background .13s}
.stype-del:hover{background:#fef2f2}

/* Detail view grid */
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.detail-item{display:flex;flex-direction:column;gap:3px}
.detail-item .dl{font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.4px}
.detail-item .dv{font-size:13.5px;color:#0e1a2b;font-weight:500}

/* Spinner overlay */
.spinner-ov{display:none;position:fixed;inset:0;background:rgba(255,255,255,.55);z-index:9500;align-items:center;justify-content:center}
.spinner-ov.show{display:flex}
.spinner{width:38px;height:38px;border:4px solid #e1e6ee;border-top-color:#1f7bff;border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* Toast */
.toast{position:fixed;top:18px;right:20px;padding:10px 16px;border-radius:7px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:7px;box-shadow:0 4px 14px rgba(0,0,0,.15);z-index:9999;animation:slideUp .22s ease;color:#fff}
.toast.success{background:#2fbf71}
.toast.error  {background:#e34f4f}
.toast.info   {background:#1f7bff}

/* Autofill highlight */
.autofilled{background:#f0f6ff!important;border-color:#c8dbff!important}

/* Lead code chip in header */
.lead-chip{background:#f0f6ff;border:1.5px solid #c8dbff;border-radius:8px;padding:6px 16px;text-align:center;min-width:130px}
.lead-chip .no{font-family:"Courier New",monospace;font-size:16px;font-weight:700;color:#1f7bff}
.lead-chip .lbl{font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.6px;margin-top:1px}

@media(max-width:768px){
  body{padding:12px}
  .fg-2,.fg-3{grid-template-columns:1fr}
  .page-header{flex-direction:column;align-items:flex-start}
  .list-filters{flex-direction:column}
  .list-filters input,.list-filters select{min-width:100%}
}
</style>
</head>
<body>

<div class="container">

  <!-- ══ PAGE HEADER ══════════════════════════════════════════ -->
  <div class="page-header">
    <h1><i class="fas fa-funnel-dollar"></i> Leads Management</h1>
    <div style="display:flex;gap:8px;align-items:center">
      <div class="lead-chip">
        <div class="no" id="nextCodeDisplay">LED001</div>
        <div class="lbl">Next Lead No.</div>
      </div>
      <button class="btn btn-secondary" id="toggleBtn" onclick="toggleForm()">
        <i class="fas fa-toggle-on" id="toggleIcon"></i> Hide Form
      </button>
    </div>
  </div>

  <!-- ══ LEAD ENTRY FORM ═══════════════════════════════════════ -->
  <div class="card" id="formCard">
    <div class="card-head"><i class="fas fa-edit"></i> Lead Entry Form</div>
    <div class="card-body">

      <div class="fg fg-3">
        <div class="f">
          <label>Lead Code</label>
          <input type="text" id="leadCode" readonly/>
        </div>
        <div class="f">
          <label>Lead Date <span class="req">*</span></label>
          <input type="date" id="leadDate"/>
        </div>
        <div class="f">
          <label>Lead Source</label>
          <select id="leadSource">
            <option value="">Select Source</option>
            <option>Website</option>
            <option>Referral</option>
            <option>Cold Call</option>
            <option>Social Media</option>
            <option>Email Campaign</option>
            <option>Trade Show</option>
            <option>Walk-in</option>
          </select>
        </div>
      </div>

      <div class="fg fg-3">
        <div class="f">
          <label>Contact Name</label>
          <input type="text" id="contactName" placeholder="Enter contact person name"/>
        </div>
        <div class="f">
          <label>Company Name <span class="req">*</span></label>
          <input type="text" id="companyName" placeholder="Enter company name"/>
        </div>
        <div class="f">
          <label>Email</label>
          <input type="email" id="email" placeholder="e.g. name@company.com"/>
        </div>
      </div>

      <div class="fg fg-3">
        <div class="f">
          <label>WhatsApp</label>
          <input type="tel" id="whatsapp" placeholder="e.g. +923001234567"/>
        </div>
        <div class="f">
          <label>Service Type
            <button type="button" class="btn-icon" onclick="openServiceModal()" title="Manage Service Types" style="height:20px;width:20px;border-radius:4px;margin-left:4px">
              <i class="fas fa-cog" style="font-size:9px"></i>
            </button>
          </label>
          <select id="serviceType">
            <option value="">Select Service Type</option>
          </select>
        </div>
        <div class="f">
          <label>Lead Status</label>
          <select id="leadStatus">
            <option value="new">New</option>
            <option value="contacted">Contacted</option>
            <option value="qualified">Qualified</option>
            <option value="follow-up">Follow-up</option>
            <option value="converted">Converted</option>
            <option value="lost">Lost</option>
          </select>
        </div>
      </div>

      <div class="fg fg-3">
        <div class="f">
          <label>Priority</label>
          <select id="priority">
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="low">Low</option>
          </select>
        </div>
        <div class="f" style="grid-column:span 2">
          <label>Remarks</label>
          <input type="text" id="remarks" placeholder="Any additional notes..."/>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-secondary" onclick="resetForm()">
          <i class="fas fa-undo"></i> Reset
        </button>
        <button class="btn btn-primary" id="saveBtn" onclick="saveLead()">
          <i class="fas fa-save"></i> Save Lead
        </button>
      </div>

    </div>
  </div>

  <!-- ══ LEADS LIST ════════════════════════════════════════════ -->
  <div class="card">
    <div class="card-head"><i class="fas fa-list"></i> Leads List</div>

    <!-- Filters -->
    <div class="list-filters">
      <input type="text" id="fName"    placeholder="Search by name…"    oninput="filterLeads()"/>
      <input type="text" id="fCompany" placeholder="Search by company…" oninput="filterLeads()"/>
      <select id="fStatus" onchange="filterLeads()">
        <option value="">All Status</option>
        <option value="new">New</option>
        <option value="contacted">Contacted</option>
        <option value="qualified">Qualified</option>
        <option value="follow-up">Follow-up</option>
        <option value="converted">Converted</option>
        <option value="lost">Lost</option>
      </select>
      <select id="fPriority" onchange="filterLeads()">
        <option value="">All Priority</option>
        <option value="high">High</option>
        <option value="medium">Medium</option>
        <option value="low">Low</option>
      </select>
      <button class="clear-btn" onclick="clearFilters()"><i class="fas fa-times"></i> Clear</button>
    </div>

    <!-- Table -->
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:115px">Lead Code</th>
            <th>Contact Name</th>
            <th>Company</th>
            <th>Service Type</th>
            <th style="width:110px">Status</th>
            <th style="width:90px">Priority</th>
            <th style="width:90px">Date</th>
            <th style="width:105px">Actions</th>
          </tr>
        </thead>
        <tbody id="leadsBody"></tbody>
      </table>
    </div>

    <div id="emptyState" class="empty-state" style="display:none;">
      <i class="fas fa-funnel-dollar"></i>
      <p>No leads found. Try adjusting your filters or add a new lead above.</p>
    </div>

    <div class="pagination" id="paginationWrap" style="display:none;">
      <div class="page-info" id="pageInfo"></div>
      <div class="page-btns" id="pageBtns"></div>
    </div>
  </div>

</div><!-- /container -->

<!-- ══ SPINNER ══════════════════════════════════════════════ -->
<div class="spinner-ov" id="spinnerOv"><div class="spinner"></div></div>

<!-- ══ SERVICE TYPE MODAL ═══════════════════════════════════ -->
<div class="modal-overlay" id="serviceModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3><i class="fas fa-cogs"></i> Manage Service Types</h3>
      <button class="modal-close" onclick="closeModal('serviceModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="f" style="margin-bottom:10px">
        <label>Add New Service Type</label>
        <div class="inline-row" style="gap:8px">
          <input type="text" id="newServiceInput" placeholder="e.g. SEO, Social Media Marketing…"
                 onkeypress="if(event.key==='Enter') addServiceType()"/>
          <button class="btn btn-primary" style="height:36px;padding:0 14px;font-size:13px" onclick="addServiceType()">
            <i class="fas fa-plus"></i> Add
          </button>
        </div>
      </div>
      <div style="font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">Saved Service Types</div>
      <div class="stype-list" id="stypeList"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" style="height:34px" onclick="closeModal('serviceModal')">Close</button>
    </div>
  </div>
</div>

<!-- ══ DELETE CONFIRM MODAL ═════════════════════════════════ -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3><i class="fas fa-exclamation-triangle danger"></i> Confirm Delete</h3>
      <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
    </div>
    <div class="modal-body">
      Are you sure you want to delete lead <strong id="deleteRef"></strong>?
      <br><br><span style="color:#e34f4f;font-size:12.5px"><i class="fas fa-info-circle"></i> This action cannot be undone.</span>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" style="height:34px" onclick="closeModal('deleteModal')">Cancel</button>
      <button class="btn btn-danger"   style="height:34px" id="confirmDelBtn" onclick="confirmDelete()"><i class="fas fa-trash-alt"></i> Delete</button>
    </div>
  </div>
</div>

<!-- ══ VIEW DETAIL MODAL ════════════════════════════════════ -->
<div class="modal-overlay" id="viewModal">
  <div class="modal-box wide">
    <div class="modal-head">
      <h3><i class="fas fa-eye"></i> Lead Details — <span id="viewCode" style="color:#1f7bff"></span></h3>
      <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="detail-grid" id="viewGrid"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" style="height:34px" onclick="closeModal('viewModal')">Close</button>
    </div>
  </div>
</div>

<!-- ══ JAVASCRIPT ════════════════════════════════════════════ -->
<script>
// ── STATE ──────────────────────────────────────────────────
const API       = location.pathname; // same file, POST + ?action=xxx
let serviceTypes = [];
let currentPage  = 1;
const PAGE_SIZE  = 8;
let totalLeads   = 0;
let totalPages   = 0;
let pendingDeleteId = null;
let editingId       = null;
let formVisible     = true;
let filterTimer     = null;

// ── INIT ───────────────────────────────────────────────────
async function init() {
  document.getElementById('leadDate').value = new Date().toISOString().split('T')[0];
  showSpinner(true);
  await Promise.all([loadNextCode(), loadServiceTypes()]);
  showSpinner(false);
  await loadLeads();
}

// ── API HELPER ─────────────────────────────────────────────
async function api(action, body = {}) {
  const r = await fetch(`${API}?action=${action}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return r.json();
}

// ── LOADERS ────────────────────────────────────────────────
async function loadNextCode() {
  const d = await api('next_code');
  if (d.success) {
    document.getElementById('leadCode').value  = d.code;
    document.getElementById('nextCodeDisplay').textContent = d.code;
  }
}

async function loadServiceTypes() {
  const d = await api('service_types');
  if (!d.success) return;
  serviceTypes = d.data;
  rebuildServiceDropdown();
}

function rebuildServiceDropdown() {
  const sel = document.getElementById('serviceType');
  const cur = sel.value;
  sel.innerHTML = '<option value="">Select Service Type</option>' +
    serviceTypes.map(s => `<option value="${s.id}">${s.service_name}</option>`).join('');
  sel.value = cur;
}

async function loadLeads(page = currentPage) {
  currentPage = page;
  showSpinner(true);
  const d = await api('leads', {
    page,
    page_size:      PAGE_SIZE,
    search_name:    document.getElementById('fName').value.trim(),
    search_company: document.getElementById('fCompany').value.trim(),
    status:         document.getElementById('fStatus').value,
    priority:       document.getElementById('fPriority').value,
  });
  showSpinner(false);
  if (!d.success) { showToast(d.message || 'Failed to load leads', 'error'); return; }
  totalLeads  = d.total;
  totalPages  = d.pages;
  renderLeads(d.data);
}

// ── TOGGLE FORM ────────────────────────────────────────────
function toggleForm() {
  formVisible = !formVisible;
  document.getElementById('formCard').style.display = formVisible ? 'block' : 'none';
  document.getElementById('toggleIcon').className   = formVisible ? 'fas fa-toggle-on' : 'fas fa-toggle-off';
  document.getElementById('toggleBtn').innerHTML    = `<i class="${document.getElementById('toggleIcon').className}" id="toggleIcon"></i> ${formVisible ? 'Hide Form' : 'Show Form'}`;
}

// ── SERVICE TYPE MODAL ─────────────────────────────────────
function openServiceModal() {
  renderStypeList();
  openModal('serviceModal');
  setTimeout(() => document.getElementById('newServiceInput').focus(), 180);
}

function renderStypeList() {
  const el = document.getElementById('stypeList');
  if (!serviceTypes.length) {
    el.innerHTML = '<div style="text-align:center;color:#9aa1ae;padding:16px 0;font-size:12.5px">No service types added yet</div>';
    return;
  }
  el.innerHTML = serviceTypes.map(s => `
    <div class="stype-item">
      <span>${s.service_name}</span>
      <button class="stype-del" onclick="deleteStype(${s.id})"><i class="fas fa-trash-alt"></i></button>
    </div>`).join('');
}

async function addServiceType() {
  const inp = document.getElementById('newServiceInput');
  const val = inp.value.trim();
  if (!val) { inp.focus(); return; }
  const d = await api('add_service_type', { service_name: val });
  if (!d.success) { showToast(d.message || 'Failed', 'error'); return; }
  inp.value = '';
  await loadServiceTypes();
  document.getElementById('serviceType').value = d.id;
  renderStypeList();
  showToast('Service type added!');
}

async function deleteStype(id) {
  if (!confirm('Delete this service type?')) return;
  const d = await api('delete_service_type', { id });
  if (d.success) { await loadServiceTypes(); renderStypeList(); }
}

// ── SAVE LEAD ──────────────────────────────────────────────
async function saveLead() {
  const date    = document.getElementById('leadDate').value;
  const company = document.getElementById('companyName').value.trim();
  if (!date)    { showToast('Lead Date is required', 'error'); return; }
  if (!company) { showToast('Company Name is required', 'error'); return; }

  const payload = {
    id:              editingId || null,
    lead_date:       date,
    contact_name:    document.getElementById('contactName').value.trim(),
    company_name:    company,
    email:           document.getElementById('email').value.trim(),
    whatsapp:        document.getElementById('whatsapp').value.trim(),
    lead_source:     document.getElementById('leadSource').value,
    service_type_id: document.getElementById('serviceType').value || null,
    lead_status:     document.getElementById('leadStatus').value || 'new',
    priority:        document.getElementById('priority').value || 'medium',
    remarks:         document.getElementById('remarks').value.trim(),
  };

  const btn = document.getElementById('saveBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
  showSpinner(true);

  const d = await api('save_lead', payload);
  showSpinner(false);
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save"></i> Save Lead';

  if (!d.success) { showToast(d.message || 'Save failed', 'error'); return; }

  showToast(editingId ? 'Lead updated successfully!' : `Lead ${d.lead_code} saved!`);
  resetForm();
  await loadNextCode();
  await loadLeads(1);
}

// ── RESET FORM ─────────────────────────────────────────────
function resetForm() {
  ['contactName','companyName','email','whatsapp','remarks'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('leadSource').value  = '';
  document.getElementById('serviceType').value = '';
  document.getElementById('leadStatus').value  = 'new';
  document.getElementById('priority').value    = 'medium';
  document.getElementById('leadDate').value    = new Date().toISOString().split('T')[0];
  editingId = null;
  loadNextCode();
}

// ── FILTER ─────────────────────────────────────────────────
function filterLeads() {
  clearTimeout(filterTimer);
  filterTimer = setTimeout(() => loadLeads(1), 300);
}

function clearFilters() {
  ['fName','fCompany'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fStatus').value   = '';
  document.getElementById('fPriority').value = '';
  loadLeads(1);
}

// ── RENDER ─────────────────────────────────────────────────
const statusBadge = {
  new:       '<span class="badge b-new"><i class="fas fa-circle" style="font-size:7px"></i> New</span>',
  contacted: '<span class="badge b-contacted"><i class="fas fa-circle" style="font-size:7px"></i> Contacted</span>',
  qualified: '<span class="badge b-qualified"><i class="fas fa-circle" style="font-size:7px"></i> Qualified</span>',
  lost:      '<span class="badge b-lost"><i class="fas fa-circle" style="font-size:7px"></i> Lost</span>',
  converted: '<span class="badge b-converted"><i class="fas fa-circle" style="font-size:7px"></i> Converted</span>',
  'follow-up':'<span class="badge b-follow"><i class="fas fa-circle" style="font-size:7px"></i> Follow-up</span>',
};
const priorityHtml = {
  high:   '<span class="p-high"><i class="fas fa-arrow-up" style="font-size:10px"></i> High</span>',
  medium: '<span class="p-medium"><i class="fas fa-minus" style="font-size:10px"></i> Medium</span>',
  low:    '<span class="p-low"><i class="fas fa-arrow-down" style="font-size:10px"></i> Low</span>',
};

function renderLeads(data) {
  const tbody  = document.getElementById('leadsBody');
  const empty  = document.getElementById('emptyState');
  const pagW   = document.getElementById('paginationWrap');

  if (!data || data.length === 0) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    pagW.style.display  = 'none';
    return;
  }
  empty.style.display = 'none';
  pagW.style.display  = 'flex';

  tbody.innerHTML = data.map(l => `
    <tr>
      <td>
        <button class="lead-code-btn" onclick="viewLead(${l.id})">
          ${escHtml(l.lead_code)} <i class="fas fa-chevron-down"></i>
        </button>
      </td>
      <td>${escHtml(l.contact_name || '—')}</td>
      <td>${escHtml(l.company_name)}</td>
      <td>${escHtml(l.service_name || '—')}</td>
      <td>${statusBadge[l.lead_status] || escHtml(l.lead_status)}</td>
      <td>${priorityHtml[l.priority] || escHtml(l.priority)}</td>
      <td style="font-size:12px;color:#6b7280">${escHtml(l.lead_date || '')}</td>
      <td>
        <div class="row-acts">
          <button class="ra ra-view"   onclick="viewLead(${l.id})"               title="View"><i class="fas fa-eye"></i></button>
          <button class="ra ra-edit"   onclick="editLead(${l.id})"               title="Edit"><i class="fas fa-edit"></i></button>
          <button class="ra ra-delete" onclick="openDelete(${l.id},'${escHtml(l.lead_code)}')" title="Delete"><i class="fas fa-trash-alt"></i></button>
        </div>
      </td>
    </tr>`).join('');

  renderPagination();
}

function renderPagination() {
  const s = (currentPage - 1) * PAGE_SIZE + 1;
  const e = Math.min(currentPage * PAGE_SIZE, totalLeads);
  document.getElementById('pageInfo').textContent = `Showing ${s}–${e} of ${totalLeads} leads`;

  let html = `<button class="pg" onclick="loadLeads(${currentPage-1})" ${currentPage===1?'disabled':''}><i class="fas fa-chevron-left" style="font-size:9px"></i></button>`;
  for (let i = 1; i <= totalPages; i++) {
    if (totalPages <= 7 || i === 1 || i === totalPages || Math.abs(i - currentPage) <= 1)
      html += `<button class="pg ${i===currentPage?'active':''}" onclick="loadLeads(${i})">${i}</button>`;
    else if (Math.abs(i - currentPage) === 2)
      html += `<button class="pg" style="cursor:default;border:none;pointer-events:none">…</button>`;
  }
  html += `<button class="pg" onclick="loadLeads(${currentPage+1})" ${currentPage===totalPages?'disabled':''}><i class="fas fa-chevron-right" style="font-size:9px"></i></button>`;
  document.getElementById('pageBtns').innerHTML = html;
}

// ── VIEW ───────────────────────────────────────────────────
async function viewLead(id) {
  const d = await api('get_lead', { id });
  if (!d.success) { showToast('Failed to load lead', 'error'); return; }
  const l = d.data;
  document.getElementById('viewCode').textContent = l.lead_code;
  document.getElementById('viewGrid').innerHTML = `
    <div class="detail-item"><div class="dl">Lead Code</div><div class="dv">${escHtml(l.lead_code)}</div></div>
    <div class="detail-item"><div class="dl">Lead Date</div><div class="dv">${escHtml(l.lead_date)}</div></div>
    <div class="detail-item"><div class="dl">Contact Name</div><div class="dv">${escHtml(l.contact_name||'—')}</div></div>
    <div class="detail-item"><div class="dl">Company</div><div class="dv">${escHtml(l.company_name)}</div></div>
    <div class="detail-item"><div class="dl">Email</div><div class="dv">${escHtml(l.email||'—')}</div></div>
    <div class="detail-item"><div class="dl">WhatsApp</div><div class="dv">${escHtml(l.whatsapp||'—')}</div></div>
    <div class="detail-item"><div class="dl">Lead Source</div><div class="dv">${escHtml(l.lead_source||'—')}</div></div>
    <div class="detail-item"><div class="dl">Service Type</div><div class="dv">${escHtml(l.service_name||'—')}</div></div>
    <div class="detail-item"><div class="dl">Status</div><div class="dv">${statusBadge[l.lead_status]||escHtml(l.lead_status)}</div></div>
    <div class="detail-item"><div class="dl">Priority</div><div class="dv">${priorityHtml[l.priority]||escHtml(l.priority)}</div></div>
    <div class="detail-item" style="grid-column:span 2"><div class="dl">Remarks</div><div class="dv">${escHtml(l.remarks||'—')}</div></div>`;
  openModal('viewModal');
}

// ── EDIT ───────────────────────────────────────────────────
async function editLead(id) {
  const d = await api('get_lead', { id });
  if (!d.success) { showToast('Failed to load lead', 'error'); return; }
  const l = d.data;
  editingId = id;
  document.getElementById('leadCode').value    = l.lead_code;
  document.getElementById('leadDate').value    = l.lead_date;
  document.getElementById('contactName').value = l.contact_name || '';
  document.getElementById('companyName').value = l.company_name;
  document.getElementById('email').value       = l.email || '';
  document.getElementById('whatsapp').value    = l.whatsapp || '';
  document.getElementById('leadSource').value  = l.lead_source || '';
  document.getElementById('leadStatus').value  = l.lead_status;
  document.getElementById('priority').value    = l.priority;
  document.getElementById('remarks').value     = l.remarks || '';
  document.getElementById('serviceType').value = l.service_type_id || '';
  if (!formVisible) toggleForm();
  document.getElementById('formCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── DELETE ─────────────────────────────────────────────────
function openDelete(id, code) {
  pendingDeleteId = id;
  document.getElementById('deleteRef').textContent = code;
  openModal('deleteModal');
}

async function confirmDelete() {
  const btn = document.getElementById('confirmDelBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting…';
  const d = await api('delete_lead', { id: pendingDeleteId });
  btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete';
  closeModal('deleteModal');
  if (d.success) {
    showToast('Lead deleted.', 'error');
    // Go back a page if last item on page was deleted
    const newTotal = totalLeads - 1;
    const maxPage  = Math.ceil(newTotal / PAGE_SIZE) || 1;
    await loadLeads(Math.min(currentPage, maxPage));
    await loadNextCode();
  } else {
    showToast(d.message || 'Delete failed', 'error');
  }
  pendingDeleteId = null;
}

// ── MODAL HELPERS ──────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

// ── SPINNER / TOAST ────────────────────────────────────────
function showSpinner(v) { document.getElementById('spinnerOv').classList.toggle('show', v); }

function showToast(msg, type = 'success') {
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle';
  t.innerHTML = `<i class="fas fa-${icon}"></i> ${msg}`;
  document.body.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .4s'; setTimeout(() => t.remove(), 400); }, 3000);
}

// ── XSS HELPER ─────────────────────────────────────────────
function escHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close modals on overlay click & Escape
['serviceModal','deleteModal','viewModal'].forEach(id => {
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('show');
  });
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape')
    ['serviceModal','deleteModal','viewModal'].forEach(id => document.getElementById(id).classList.remove('show'));
});

// ── BOOT ───────────────────────────────────────────────────
init();
</script>
</body>
</html>