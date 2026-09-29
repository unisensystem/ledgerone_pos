<?php
/**
 * GL DEBUG PAGE  (read-only, safe to test)
 * Put this file in the SAME folder as your General Ledger page (index.php).
 * It only runs SELECT queries. It never changes any data.
 */
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once '../../../../includes/connection.php';

$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

// ---------- Voucher tables that post into accounting_ledger ----------
$SOURCES = [
  'receive_voucher'  => ['label'=>'Receive Voucher',  'no'=>'voucher_number','date'=>'voucher_date','amt'=>'amount',       'status'=>null],
  'payment_voucher'  => ['label'=>'Payment Voucher',  'no'=>'voucher_number','date'=>'voucher_date','amt'=>'amount',       'status'=>null],
  'expense_voucher'  => ['label'=>'Expense Voucher',  'no'=>'voucher_no',    'date'=>'date',        'amt'=>'total_amount', 'status'=>null],
  'journal_voucher'  => ['label'=>'Journal Voucher',  'no'=>'voucher_number','date'=>'voucher_date','amt'=>'total_debit',  'status'=>'status'],
  'transfer_voucher' => ['label'=>'Transfer Voucher', 'no'=>'voucher_number','date'=>'voucher_date','amt'=>'amount',       'status'=>null],
  'sale_invoice'     => ['label'=>'Sale Invoice',     'no'=>'bill_no',       'date'=>'sale_date',   'amt'=>'net_amount',   'status'=>'status'],
  'purchase_invoice' => ['label'=>'Purchase Invoice', 'no'=>'bill_no',       'date'=>'purchase_date','amt'=>'net_amount',  'status'=>'status'],
];
// these must match ledger debit total exactly (simple vouchers)
$AMOUNT_CHECK = ['receive_voucher','payment_voucher','expense_voucher','journal_voucher','transfer_voucher'];
// accounts the GL report hides on purpose
$HIDDEN_ACCOUNTS = [110, 107];
// tables the DB viewer may open (read-only)
$VIEW_TABLES = ['accounting_ledger','accounts','receive_voucher','payment_voucher','expense_voucher','expense_voucher_line',
  'journal_voucher','journal_voucher_line','transfer_voucher','sale_invoice','purchase_invoice','purchase_invoice_taxes',
  'sale_invoice_taxes','opening_balance_invoices','cash_opening','bank_accounts','payroll_entries','companies','posting_lock_dates','sub_accounts'];

function dateSql($col, $from, $to, &$p) {
    $s = '';
    if ($from) { $s .= " AND $col >= ?"; $p[] = $from; }
    if ($to)   { $s .= " AND $col <= ?"; $p[] = $to; }
    return $s;
}
function rows($pdo, $sql, $p = []) { $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
function one($pdo, $sql, $p = [])  { $r = rows($pdo, $sql, $p); return $r ? $r[0] : null; }

// company id of a ledger row, found through its voucher (same way the GL report does it)
$COMPANY_JOINS = "
  LEFT JOIN sale_invoice si     ON al.reference_table='sale_invoice'     AND al.reference_id=si.id
  LEFT JOIN purchase_invoice pi ON al.reference_table='purchase_invoice' AND al.reference_id=pi.id
  LEFT JOIN receive_voucher rv  ON al.reference_table='receive_voucher'  AND al.reference_id=rv.id
  LEFT JOIN payment_voucher pv  ON al.reference_table='payment_voucher'  AND al.reference_id=pv.id
  LEFT JOIN expense_voucher ev  ON al.reference_table='expense_voucher'  AND al.reference_id=ev.id
  LEFT JOIN journal_voucher jv  ON al.reference_table='journal_voucher'  AND al.reference_id=jv.id
  LEFT JOIN transfer_voucher tv ON al.reference_table='transfer_voucher' AND al.reference_id=tv.id";
$COMPANY_ID = "COALESCE(si.company_id,pi.company_id,rv.company_id,pv.company_id,ev.company_id,jv.company_id,tv.company_id)";

// ====================== API ======================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Not logged in']); exit; }

    $a    = $_GET['action'];
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to']   ?? '';
    try {
        $out = ['success'=>true];

        if ($a === 'summary') {
            $p = [$tenant_id]; $d = dateSql('al.date',$from,$to,$p);
            $all = one($pdo, "SELECT COUNT(*) n, COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c FROM accounting_ledger al WHERE al.tenant_id=? $d", $p);
            $ph = implode(',', array_map('intval',$HIDDEN_ACCOUNTS));
            $hid = one($pdo, "SELECT COUNT(*) n, COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c FROM accounting_ledger al WHERE al.tenant_id=? AND al.account_id IN ($ph) $d", $p);
            $hidAcc = rows($pdo, "SELECT al.account_id, a.name, COUNT(*) n, SUM(al.debit) d, SUM(al.credit) c
                                  FROM accounting_ledger al LEFT JOIN accounts a ON a.id=al.account_id
                                  WHERE al.tenant_id=? AND al.account_id IN ($ph) $d GROUP BY al.account_id, a.name", $p);
            $out['all'] = $all; $out['hidden'] = $hid; $out['hiddenAccounts'] = $hidAcc;
            $out['hiddenIds'] = $HIDDEN_ACCOUNTS;

            $out['counts'] = [
              'unbalanced' => count(unbalanced($pdo,$tenant_id,$from,$to)),
              'missing'    => count(missing($pdo,$tenant_id,$from,$to,$SOURCES)),
              'orphans'    => count(orphans($pdo,$tenant_id,$from,$to,$SOURCES)),
              'mismatch'   => count(mismatch($pdo,$tenant_id,$from,$to,$SOURCES,$AMOUNT_CHECK)),
              'dupes'      => count(dupes($pdo,$tenant_id,$from,$to)),
              'days'       => count(days($pdo,$tenant_id,$from,$to)),
            ];
            $pp = [$tenant_id]; $dd = dateSql('al.date',$from,$to,$pp);
            $nl = one($pdo, "SELECT COUNT(*) n, COALESCE(SUM(al.debit),0) d, COALESCE(SUM(al.credit),0) c
                             FROM accounting_ledger al $COMPANY_JOINS
                             WHERE al.tenant_id=? $dd AND $COMPANY_ID IS NULL", $pp);
            $out['noCompany'] = $nl;
        }
        elseif ($a === 'unbalanced') { $out['rows'] = unbalanced($pdo,$tenant_id,$from,$to); }
        elseif ($a === 'missing')    { $out['rows'] = missing($pdo,$tenant_id,$from,$to,$SOURCES); }
        elseif ($a === 'orphans')    { $out['rows'] = orphans($pdo,$tenant_id,$from,$to,$SOURCES); }
        elseif ($a === 'mismatch')   { $out['rows'] = mismatch($pdo,$tenant_id,$from,$to,$SOURCES,$AMOUNT_CHECK); }
        elseif ($a === 'dupes')      { $out['rows'] = dupes($pdo,$tenant_id,$from,$to); }
        elseif ($a === 'days')       { $out['rows'] = days($pdo,$tenant_id,$from,$to); }
        elseif ($a === 'companies') {
            $p = [$tenant_id]; $d = dateSql('al.date',$from,$to,$p);
            $out['rows'] = rows($pdo, "SELECT $COMPANY_ID AS company_id, co.company_name, COUNT(*) n,
                    SUM(al.debit) d, SUM(al.credit) c
                FROM accounting_ledger al $COMPANY_JOINS
                LEFT JOIN companies co ON co.id = $COMPANY_ID
                WHERE al.tenant_id=? $d GROUP BY company_id, co.company_name ORDER BY company_id", $p);
        }
        elseif ($a === 'lines') {
            $t = $_GET['table'] ?? ''; $id = (int)($_GET['id'] ?? 0); $desc = $_GET['desc'] ?? null;
            $p = [$tenant_id,$t,$id]; $extra = '';
            if ($id === 0 && $desc !== null && $desc !== '') { $extra = " AND al.description = ?"; $p[] = $desc; }
            $out['rows'] = rows($pdo, "SELECT al.id, al.date, al.transaction_type, al.account_id, a.name account_name, a.sub_account_id,
                  al.description, al.debit, al.credit, al.created_at
                FROM accounting_ledger al LEFT JOIN accounts a ON a.id=al.account_id
                WHERE al.tenant_id=? AND al.reference_table=? AND al.reference_id=? $extra ORDER BY al.id", $p);
        }
        elseif ($a === 'inspect') {
            $t = $_GET['table'] ?? ''; $id = (int)($_GET['id'] ?? 0);
            $out['source'] = null; $out['known'] = isset($SOURCES[$t]);
            if (isset($SOURCES[$t])) {
                $out['source'] = one($pdo, "SELECT * FROM `$t` WHERE tenant_id=? AND id=?", [$tenant_id,$id]);
                $out['amtCol'] = $SOURCES[$t]['amt']; $out['checkAmount'] = in_array($t,$AMOUNT_CHECK);
            }
            $out['rows'] = rows($pdo, "SELECT al.id, al.date, al.transaction_type, al.account_id, a.name account_name, a.sub_account_id,
                  al.description, al.debit, al.credit, al.created_at
                FROM accounting_ledger al LEFT JOIN accounts a ON a.id=al.account_id
                WHERE al.tenant_id=? AND al.reference_table=? AND al.reference_id=? ORDER BY al.id", [$tenant_id,$t,$id]);
        }
        elseif ($a === 'browse') {
            $p = [$tenant_id]; $w = dateSql('al.date',$from,$to,$p);
            if (!empty($_GET['table'])) { $w .= " AND al.reference_table=?"; $p[] = $_GET['table']; }
            if (($_GET['refid'] ?? '') !== '') { $w .= " AND al.reference_id=?"; $p[] = (int)$_GET['refid']; }
            if (!empty($_GET['account'])) { $w .= " AND al.account_id=?"; $p[] = (int)$_GET['account']; }
            if (!empty($_GET['q'])) { $w .= " AND (al.description LIKE ? OR a.name LIKE ?)"; $p[] = '%'.$_GET['q'].'%'; $p[] = '%'.$_GET['q'].'%'; }
            $page = max(1,(int)($_GET['page'] ?? 1)); $per = 100; $off = ($page-1)*$per;
            $tot = one($pdo, "SELECT COUNT(*) n, COALESCE(SUM(al.debit),0) d, COALESCE(SUM(al.credit),0) c
                              FROM accounting_ledger al LEFT JOIN accounts a ON a.id=al.account_id WHERE al.tenant_id=? $w", $p);
            $out['total'] = $tot; $out['page'] = $page; $out['per'] = $per;
            $out['rows'] = rows($pdo, "SELECT al.id, al.date, al.transaction_type, al.reference_table, al.reference_id, al.account_id,
                  a.name account_name, a.sub_account_id, al.description, al.debit, al.credit, al.created_at
                FROM accounting_ledger al LEFT JOIN accounts a ON a.id=al.account_id
                WHERE al.tenant_id=? $w ORDER BY al.date DESC, al.id DESC LIMIT $per OFFSET $off", $p);
        }
        elseif ($a === 'dbtables') { $out['tables'] = $VIEW_TABLES; }
        elseif ($a === 'dbview') {
            $t = $_GET['table'] ?? '';
            if (!in_array($t,$VIEW_TABLES,true)) throw new Exception('Table not allowed');
            $cols = array_column(rows($pdo, "SHOW COLUMNS FROM `$t`"), 'Field');
            $w = ''; $p = [];
            if (in_array('tenant_id',$cols)) { $w = " WHERE tenant_id=?"; $p[] = $tenant_id; }
            if (!empty($_GET['q'])) {
                $concat = "CONCAT_WS(' ', " . implode(',', array_map(fn($c)=>"`$c`", $cols)) . ")";
                $w .= ($w ? " AND " : " WHERE ") . "$concat LIKE ?"; $p[] = '%'.$_GET['q'].'%';
            }
            $page = max(1,(int)($_GET['page'] ?? 1)); $per = 50; $off = ($page-1)*$per;
            $cnt = one($pdo, "SELECT COUNT(*) n FROM `$t`$w", $p);
            $order = in_array('id',$cols) ? " ORDER BY id DESC" : "";
            $out['cols'] = $cols; $out['total'] = (int)$cnt['n']; $out['page'] = $page; $out['per'] = $per;
            $out['rows'] = rows($pdo, "SELECT * FROM `$t`$w$order LIMIT $per OFFSET $off", $p);
        }
        else { throw new Exception('Unknown action'); }

        echo json_encode($out);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// ---------- check functions ----------
// NOTE: "lines" is a reserved word in MariaDB/MySQL, so the alias is "line_count".
function unbalanced($pdo,$tid,$from,$to){
    $p=[$tid]; $d=dateSql('date',$from,$to,$p);
    // reference_id = 0 rows are grouped by description so different vouchers are not mixed together
    return rows($pdo, "SELECT reference_table, reference_id,
          CASE WHEN reference_id=0 THEN description ELSE '' END AS sub_key,
          MIN(transaction_type) AS transaction_type, MIN(date) AS date, COUNT(*) AS line_count,
          SUM(debit) AS d, SUM(credit) AS c, SUM(debit)-SUM(credit) AS diff
        FROM accounting_ledger WHERE tenant_id=? $d
        GROUP BY reference_table, reference_id, sub_key
        HAVING ABS(SUM(debit)-SUM(credit))>0.005 ORDER BY date DESC", $p);
}
function missing($pdo,$tid,$from,$to,$S){
    $all=[];
    foreach($S as $t=>$m){
        $dp=[]; $d=dateSql("s.`{$m['date']}`",$from,$to,$dp);
        $st = $m['status'] ? "s.`{$m['status']}`" : "NULL";
        // placeholder order: s.tenant_id, date filters, ledger tenant_id
        $params = array_merge([$tid], $dp, [$tid]);
        $r = rows($pdo,"SELECT '$t' AS tbl, s.id, s.`{$m['no']}` AS no, s.`{$m['date']}` AS date, s.`{$m['amt']}` AS amount, s.company_id, $st AS status
            FROM `$t` s WHERE s.tenant_id=? $d AND NOT EXISTS (
              SELECT 1 FROM accounting_ledger al WHERE al.tenant_id=? AND al.reference_table='$t' AND al.reference_id=s.id)
            ORDER BY s.id DESC", $params);
        foreach($r as $x){ $x['label']=$m['label']; $all[]=$x; }
    }
    return $all;
}
function orphans($pdo,$tid,$from,$to,$S){
    $all=[];
    foreach($S as $t=>$m){
        $p=[$tid]; $d=dateSql('al.date',$from,$to,$p);
        $r = rows($pdo,"SELECT '$t' AS tbl, al.reference_id, COUNT(*) AS line_count, SUM(al.debit) AS d, SUM(al.credit) AS c, MIN(al.date) AS date, MIN(al.description) AS sample
            FROM accounting_ledger al LEFT JOIN `$t` s ON s.id=al.reference_id AND s.tenant_id=al.tenant_id
            WHERE al.tenant_id=? AND al.reference_table='$t' AND s.id IS NULL $d
            GROUP BY al.reference_id, CASE WHEN al.reference_id=0 THEN al.description ELSE '' END ORDER BY MIN(al.date) DESC", $p);
        foreach($r as $x){ $x['label']=$m['label']; $all[]=$x; }
    }
    return $all;
}
function mismatch($pdo,$tid,$from,$to,$S,$CHK){
    $all=[];
    foreach($CHK as $t){
        $m=$S[$t]; $dp=[]; $d=dateSql("s.`{$m['date']}`",$from,$to,$dp);
        // placeholder order: subquery tenant_id, s.tenant_id, date filters
        $params = array_merge([$tid,$tid], $dp);
        $r = rows($pdo,"SELECT '$t' AS tbl, s.id, s.`{$m['no']}` AS no, s.`{$m['date']}` AS date, s.`{$m['amt']}` AS voucher_amount,
              x.d AS ledger_debit, x.c AS ledger_credit
            FROM `$t` s JOIN (SELECT reference_id, SUM(debit) AS d, SUM(credit) AS c FROM accounting_ledger
                              WHERE tenant_id=? AND reference_table='$t' GROUP BY reference_id) x ON x.reference_id=s.id
            WHERE s.tenant_id=? $d AND ABS(x.d - s.`{$m['amt']}`)>0.01 ORDER BY s.id DESC", $params);
        foreach($r as $x){ $x['label']=$m['label']; $all[]=$x; }
    }
    return $all;
}
function dupes($pdo,$tid,$from,$to){
    $p=[$tid]; $d=dateSql('date',$from,$to,$p);
    return rows($pdo,"SELECT reference_table, reference_id, account_id, date, debit, credit, COUNT(*) AS times, MIN(description) AS description
        FROM accounting_ledger WHERE tenant_id=? $d AND reference_id>0
        GROUP BY reference_table, reference_id, account_id, date, debit, credit, description
        HAVING COUNT(*)>1 ORDER BY date DESC", $p);
}
function days($pdo,$tid,$from,$to){
    $p=[$tid]; $d=dateSql('date',$from,$to,$p);
    return rows($pdo,"SELECT date, COUNT(*) AS line_count, SUM(debit) AS d, SUM(credit) AS c, SUM(debit)-SUM(credit) AS diff
        FROM accounting_ledger WHERE tenant_id=? $d GROUP BY date HAVING ABS(SUM(debit)-SUM(credit))>0.005 ORDER BY date DESC", $p);
}

// ---------- page ----------
if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LedgerOne ERP - GL Debug</title>
<style>
:root{--bg:#f4f6fb;--card:#fff;--ink:#1f2937;--sub:#6b7280;--line:#e5e7eb;--brand:#2f5bea;--ok:#12a150;--okbg:#e8f7ee;--bad:#d92d20;--badbg:#fdecea;--warn:#b7791f;--warnbg:#fff6e0}
*{box-sizing:border-box}body{margin:0;font:14px/1.5 system-ui,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}
.wrap{max-width:1300px;margin:0 auto;padding:20px}
h1{margin:0 0 4px;font-size:24px}.sub{color:var(--sub);margin:0 0 16px}
.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px;margin-bottom:16px}
.bar{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.bar label{display:block;font-size:12px;color:var(--sub);margin-bottom:3px}
input,select,button{font:inherit;padding:7px 10px;border:1px solid var(--line);border-radius:8px;background:#fff}
button{cursor:pointer;background:var(--brand);color:#fff;border-color:var(--brand)}button.ghost{background:#fff;color:var(--ink);border-color:var(--line)}
button.mini{padding:3px 9px;font-size:12px}
.big{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.stat{border:1px solid var(--line);border-radius:10px;padding:12px;background:#fff}
.stat .t{font-size:12px;color:var(--sub)}.stat .v{font-size:22px;font-weight:700}
.stat.ok{background:var(--okbg);border-color:#b7e4c7}.stat.bad{background:var(--badbg);border-color:#f5b5ae}.stat.warn{background:var(--warnbg);border-color:#f3d9a0}
.stat.click{cursor:pointer}.stat.click:hover{box-shadow:0 2px 8px rgba(0,0,0,.08)}
.verdict{padding:12px 14px;border-radius:10px;font-weight:600;margin-bottom:12px}
.verdict.ok{background:var(--okbg);color:var(--ok)}.verdict.bad{background:var(--badbg);color:var(--bad)}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px}
.tab{background:#fff;color:var(--ink);border:1px solid var(--line);padding:7px 12px;border-radius:999px}
.tab.on{background:var(--brand);color:#fff;border-color:var(--brand)}
.tab .n{display:inline-block;min-width:20px;padding:0 6px;margin-left:6px;border-radius:999px;background:#eef;color:var(--brand);font-size:12px}
.tab.on .n{background:rgba(255,255,255,.25);color:#fff}.tab .n.red{background:var(--badbg);color:var(--bad)}
.help{background:#eef3ff;border:1px solid #cddbff;border-radius:10px;padding:10px 14px;margin-bottom:12px;color:#243b8f}
.help b{color:#1b2c6b}
table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:7px 9px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{background:#f9fafb;position:sticky;top:0;font-size:12px;color:var(--sub);text-transform:uppercase;letter-spacing:.03em}
td.r,th.r{text-align:right;font-variant-numeric:tabular-nums}.scroll{overflow:auto;max-height:520px;border:1px solid var(--line);border-radius:8px}
.pill{display:inline-block;padding:1px 8px;border-radius:999px;font-size:12px;font-weight:600}
.pill.bad{background:var(--badbg);color:var(--bad)}.pill.ok{background:var(--okbg);color:var(--ok)}.pill.warn{background:var(--warnbg);color:var(--warn)}
.reasons li{margin-bottom:6px}.muted{color:var(--sub)}.empty{padding:22px;text-align:center;color:var(--ok);font-weight:600}
tr.sub td{background:#fafbff;padding:0 0 0 30px}tr.sub table{margin:6px 0}
code{background:#f1f3f9;padding:1px 6px;border-radius:5px}
.err{background:var(--badbg);color:var(--bad);padding:10px;border-radius:8px}
.pager{display:flex;gap:8px;align-items:center;margin-top:10px}
</style>
</head>
<body>
<?php require_once '../../../../includes/dashboard.php'; ?>
<div class="wrap">
  <h1>General Ledger Debug</h1>
  <p class="sub">Read-only test page. It only looks at data and never changes anything. Use it to find out <b>why</b> the GL is not balanced.</p>

  <div class="help"><b>How to read this page:</b> Every entry in the ledger has a <b>Debit</b> side and a <b>Credit</b> side. For each voucher, Debit total must be equal to Credit total. If not, the voucher is <b>unbalanced</b>. Start with the top box, then open the red tabs one by one.</div>

  <div class="card">
    <div class="bar">
      <div><label>From date (empty = all time)</label><input type="date" id="from"></div>
      <div><label>To date</label><input type="date" id="to"></div>
      <button onclick="loadAll()">Check Now</button>
      <button class="ghost" onclick="clearDates()">Clear dates</button>
      <span class="muted" id="stamp"></span>
    </div>
  </div>

  <div id="summary" class="card">Loading...</div>

  <div class="tabs" id="tabs"></div>
  <div class="card" id="panel"></div>
</div>

<script>
const TABS=[
 ['unbalanced','1. Unbalanced Vouchers','Vouchers where Debit is not equal to Credit. This is the main reason the GL does not balance. Click "Show lines" to see each entry.'],
 ['missing','2. Vouchers With No Ledger Entry','The voucher exists but nothing was posted in the ledger. Draft / pending items can be normal here. Posted ones are a problem.'],
 ['orphans','3. Ledger Without Voucher','Ledger entries that point to a voucher which does not exist (or has ID 0). Usually the voucher was deleted, or the ledger was saved before the voucher ID was known.'],
 ['mismatch','4. Amount Differs','The voucher says one amount but the ledger has a different amount. Usually happens after someone EDITS a voucher.'],
 ['dupes','5. Duplicate Entries','The exact same ledger line saved more than once. Usually a double click or an edit that inserted again.'],
 ['days','6. Day by Day','Days where total Debit is not equal to total Credit. Helps you find WHEN the problem started.'],
 ['companies','7. By Company','Ledger totals per company. Entries with no company disappear when you select a company in the GL report.'],
 ['browse','8. Ledger Browser','Search raw ledger rows. Type a voucher table and ID to inspect one voucher.'],
 ['dbview','9. Database Viewer','View any main table (read-only).']
];
let cur='unbalanced', SUM=null;
const $=id=>document.getElementById(id);
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const num=v=>Number(v||0);
const money=v=>num(v).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
function qs(o){const p=new URLSearchParams({from:$('from').value,to:$('to').value,...o});return p.toString();}
async function api(action,o={}){
  const r=await fetch('?action='+action+'&'+qs(o));const j=await r.json();
  if(!j.success)throw new Error(j.message||'Error');return j;
}
function clearDates(){$('from').value='';$('to').value='';loadAll();}

async function loadAll(){
  $('summary').innerHTML='Checking...';
  try{
    SUM=await api('summary');renderSummary();renderTabs();openTab(cur);
    $('stamp').textContent='Last checked: '+new Date().toLocaleTimeString();
  }catch(e){$('summary').innerHTML='<div class="err">'+esc(e.message)+'</div>';}
}

function renderSummary(){
  const s=SUM,d=num(s.all.d),c=num(s.all.c),diff=d-c,ok=Math.abs(diff)<0.01;
  const hd=num(s.hidden.d)-num(s.hidden.c);
  const ct=s.counts;
  let h='<div class="verdict '+(ok?'ok':'bad')+'">'+(ok?'GOOD: The full ledger is balanced. Debit = Credit.':'PROBLEM: The full ledger is NOT balanced. Debit is different from Credit by '+money(Math.abs(diff))+' ('+(diff>0?'Debit is bigger':'Credit is bigger')+').')+'</div>';
  h+='<div class="big">'+
   stat('Total Debit (all ledger rows)',money(d),'')+
   stat('Total Credit (all ledger rows)',money(c),'')+
   stat('Difference (Debit - Credit)',money(diff),ok?'ok':'bad')+
   stat('Ledger rows checked',s.all.n,'')+'</div>';
  h+='<h3 style="margin:16px 0 8px">Problem counts <span class="muted" style="font-weight:400;font-size:12px">(click a box to open it)</span></h3><div class="big">'+
   stat('Unbalanced vouchers',ct.unbalanced,ct.unbalanced?'bad':'ok','unbalanced')+
   stat('Vouchers with no ledger',ct.missing,ct.missing?'warn':'ok','missing')+
   stat('Ledger without voucher',ct.orphans,ct.orphans?'bad':'ok','orphans')+
   stat('Amount differs',ct.mismatch,ct.mismatch?'bad':'ok','mismatch')+
   stat('Duplicate entries',ct.dupes,ct.dupes?'warn':'ok','dupes')+
   stat('Unbalanced days',ct.days,ct.days?'bad':'ok','days')+'</div>';

  const R=[];
  if(ct.unbalanced) R.push('<b>'+ct.unbalanced+' voucher(s)</b> have Debit different from Credit. This normally happens when an <b>edit</b> saved only one side of the entry, or one side failed to save.');
  if(ct.missing) R.push('<b>'+ct.missing+' voucher(s)</b> have no ledger entry at all. After an <b>edit</b>, the old ledger rows may have been deleted and the new ones not posted. (Draft / pending ones are normal.)');
  if(ct.orphans) R.push('<b>'+ct.orphans+' ledger group(s)</b> point to a voucher that does not exist or has ID 0. The voucher was deleted, or the ledger was posted before the voucher ID was saved.');
  if(ct.mismatch) R.push('<b>'+ct.mismatch+' voucher(s)</b> have a ledger amount different from the voucher amount. This is typical after an <b>edit</b> that changed the amount but did not fix the ledger.');
  if(ct.dupes) R.push('<b>'+ct.dupes+' ledger line(s)</b> are saved more than once (same account, same amount).');
  if(Math.abs(hd)>=0.01) R.push('The GL report <b>hides account(s) '+s.hiddenIds.join(' and ')+'</b> on purpose. Their net amount is <b>'+money(hd)+'</b>, so the GL report shows a different total than the real ledger. If this equals the difference you see in the report, this is the reason.');
  if(s.noCompany.n>0) R.push('<b>'+s.noCompany.n+' ledger row(s)</b> cannot be linked to any company (opening balances, payroll, or vouchers with no company). When you choose a company in the GL report, these rows disappear and the report can look unbalanced.');
  R.push('<b>Report filters also break balance.</b> If you use Min/Max amount, Account number, Debit-only or Credit-only, or search text, the report shows only half of a voucher, so it will always look unbalanced. Clear all filters first.');
  h+='<h3 style="margin:16px 0 8px">Likely reasons</h3><ul class="reasons">'+R.map(x=>'<li>'+x+'</li>').join('')+'</ul>';
  if(s.hiddenAccounts.length){
    h+='<details><summary class="muted">Show hidden accounts detail</summary><div class="scroll" style="margin-top:8px"><table><tr><th>Account ID</th><th>Name</th><th class="r">Rows</th><th class="r">Debit</th><th class="r">Credit</th></tr>'+
    s.hiddenAccounts.map(a=>'<tr><td>'+a.account_id+'</td><td>'+esc(a.name)+'</td><td class="r">'+a.n+'</td><td class="r">'+money(a.d)+'</td><td class="r">'+money(a.c)+'</td></tr>').join('')+'</table></div></details>';
  }
  $('summary').innerHTML=h;
}
function stat(t,v,cls,tab){return '<div class="stat '+(cls||'')+(tab?' click':'')+'"'+(tab?' onclick="openTab(\''+tab+'\')"':'')+'><div class="t">'+t+'</div><div class="v">'+v+'</div></div>';}

function renderTabs(){
  const c=SUM.counts;
  $('tabs').innerHTML=TABS.map(t=>{
    const n=c[t[0]];const badge=(n!==undefined)?'<span class="n'+(n>0?' red':'')+'">'+n+'</span>':'';
    return '<button class="tab '+(t[0]===cur?'on':'')+'" onclick="openTab(\''+t[0]+'\')">'+t[1]+badge+'</button>';
  }).join('');
}
function openTab(k){cur=k;if(SUM)renderTabs();const t=TABS.find(x=>x[0]===k);
  $('panel').innerHTML='<div class="help">'+esc(t[2])+'</div><div id="body">Loading...</div>';
  ({unbalanced,missing,orphans,mismatch,dupes,days,companies,browse,dbview})[k]();
  $('tabs').scrollIntoView({behavior:'smooth',block:'start'});
}
const body=h=>$('body').innerHTML=h;
const fail=e=>body('<div class="err">'+esc(e.message)+'</div>');
const none=()=>body('<div class="empty">Nothing found. All good here.</div>');
const lbl=t=>esc(t.replace(/_/g,' '));

/* ---- tab 1 ---- */
async function unbalanced(){try{const j=await api('unbalanced');if(!j.rows.length)return none();
  body('<div class="scroll"><table><tr><th>Date</th><th>Voucher type</th><th>ID</th><th>Description</th><th class="r">Lines</th><th class="r">Debit</th><th class="r">Credit</th><th class="r">Difference</th><th></th></tr>'+
  j.rows.map((r,i)=>'<tr><td>'+esc(r.date)+'</td><td>'+lbl(r.reference_table)+'</td><td>'+r.reference_id+(r.reference_id==0?' <span class="pill warn">ID 0</span>':'')+'</td><td>'+esc(r.sub_key||'')+'</td><td class="r">'+r.line_count+'</td><td class="r">'+money(r.d)+'</td><td class="r">'+money(r.c)+'</td><td class="r"><span class="pill bad">'+money(r.diff)+'</span></td><td><button class="mini ghost" onclick="showLines('+i+',this)">Show lines</button></td></tr><tr class="sub" id="ln'+i+'" style="display:none"><td colspan="9"></td></tr>').join('')+'</table></div>');
  window._ub=j.rows;}catch(e){fail(e)}}
async function showLines(i,btn){const row=$('ln'+i);if(row.style.display!=='none'){row.style.display='none';return;}
  const r=window._ub[i];row.style.display='table-row';row.firstChild.innerHTML='Loading...';
  try{const j=await api('lines',{table:r.reference_table,id:r.reference_id,desc:r.sub_key||''});row.firstChild.innerHTML=linesTable(j.rows);}catch(e){row.firstChild.innerHTML=esc(e.message)}}
function linesTable(rs){const d=rs.reduce((a,x)=>a+num(x.debit),0),c=rs.reduce((a,x)=>a+num(x.credit),0);
  return '<table><tr><th>Ledger ID</th><th>Date</th><th>Account</th><th>Description</th><th class="r">Debit</th><th class="r">Credit</th><th>Saved at</th></tr>'+
  rs.map(x=>'<tr><td>'+x.id+'</td><td>'+esc(x.date)+'</td><td>'+x.account_id+' - '+esc(x.account_name)+'</td><td>'+esc(x.description)+'</td><td class="r">'+(num(x.debit)?money(x.debit):'')+'</td><td class="r">'+(num(x.credit)?money(x.credit):'')+'</td><td>'+esc(x.created_at)+'</td></tr>').join('')+
  '<tr><th colspan="4" class="r">Totals</th><th class="r">'+money(d)+'</th><th class="r">'+money(c)+'</th><th>'+(Math.abs(d-c)<0.01?'<span class="pill ok">Balanced</span>':'<span class="pill bad">Off by '+money(d-c)+'</span>')+'</th></tr></table>';}

/* ---- tab 2 ---- */
async function missing(){try{const j=await api('missing');if(!j.rows.length)return none();
  body('<div class="scroll"><table><tr><th>Type</th><th>ID</th><th>Voucher no.</th><th>Date</th><th class="r">Amount</th><th>Company</th><th>Status</th><th>Meaning</th><th></th></tr>'+
  j.rows.map(r=>{const draft=r.status&&['draft','pending'].includes(String(r.status).toLowerCase());
   return '<tr><td>'+esc(r.label)+'</td><td>'+r.id+'</td><td>'+esc(r.no)+'</td><td>'+esc(r.date)+'</td><td class="r">'+money(r.amount)+'</td><td>'+esc(r.company_id??'-')+'</td><td>'+esc(r.status??'-')+'</td><td>'+(draft?'<span class="pill ok">Draft - normal</span>':'<span class="pill bad">Posting missing</span>')+'</td><td><button class="mini ghost" onclick="inspectJump(\''+r.tbl+'\','+r.id+')">Inspect</button></td></tr>';}).join('')+'</table></div>');}catch(e){fail(e)}}
/* ---- tab 3 ---- */
async function orphans(){try{const j=await api('orphans');if(!j.rows.length)return none();
  body('<div class="scroll"><table><tr><th>Type</th><th>Voucher ID used</th><th>Sample description</th><th>Date</th><th class="r">Lines</th><th class="r">Debit</th><th class="r">Credit</th><th>Balanced?</th></tr>'+
  j.rows.map(r=>'<tr><td>'+esc(r.label)+'</td><td>'+r.reference_id+(r.reference_id==0?' <span class="pill warn">ID 0</span>':'')+'</td><td>'+esc(r.sample)+'</td><td>'+esc(r.date)+'</td><td class="r">'+r.line_count+'</td><td class="r">'+money(r.d)+'</td><td class="r">'+money(r.c)+'</td><td>'+(Math.abs(num(r.d)-num(r.c))<0.01?'<span class="pill ok">Yes</span>':'<span class="pill bad">No</span>')+'</td></tr>').join('')+'</table></div>');}catch(e){fail(e)}}
/* ---- tab 4 ---- */
async function mismatch(){try{const j=await api('mismatch');if(!j.rows.length)return none();
  body('<div class="scroll"><table><tr><th>Type</th><th>ID</th><th>Voucher no.</th><th>Date</th><th class="r">Voucher amount</th><th class="r">Ledger debit</th><th class="r">Ledger credit</th><th class="r">Gap</th><th></th></tr>'+
  j.rows.map(r=>'<tr><td>'+esc(r.label)+'</td><td>'+r.id+'</td><td>'+esc(r.no)+'</td><td>'+esc(r.date)+'</td><td class="r">'+money(r.voucher_amount)+'</td><td class="r">'+money(r.ledger_debit)+'</td><td class="r">'+money(r.ledger_credit)+'</td><td class="r"><span class="pill bad">'+money(num(r.ledger_debit)-num(r.voucher_amount))+'</span></td><td><button class="mini ghost" onclick="inspectJump(\''+r.tbl+'\','+r.id+')">Inspect</button></td></tr>').join('')+'</table></div>');}catch(e){fail(e)}}
/* ---- tab 5 ---- */
async function dupes(){try{const j=await api('dupes');if(!j.rows.length)return none();
  body('<div class="scroll"><table><tr><th>Date</th><th>Voucher type</th><th>ID</th><th>Account</th><th>Description</th><th class="r">Debit</th><th class="r">Credit</th><th class="r">Saved times</th></tr>'+
  j.rows.map(r=>'<tr><td>'+esc(r.date)+'</td><td>'+lbl(r.reference_table)+'</td><td>'+r.reference_id+'</td><td>'+r.account_id+'</td><td>'+esc(r.description)+'</td><td class="r">'+money(r.debit)+'</td><td class="r">'+money(r.credit)+'</td><td class="r"><span class="pill warn">'+r.times+'x</span></td></tr>').join('')+'</table></div>');}catch(e){fail(e)}}
/* ---- tab 6 ---- */
async function days(){try{const j=await api('days');if(!j.rows.length)return none();
  body('<div class="scroll"><table><tr><th>Date</th><th class="r">Lines</th><th class="r">Debit</th><th class="r">Credit</th><th class="r">Difference</th><th></th></tr>'+
  j.rows.map(r=>'<tr><td>'+esc(r.date)+'</td><td class="r">'+r.line_count+'</td><td class="r">'+money(r.d)+'</td><td class="r">'+money(r.c)+'</td><td class="r"><span class="pill bad">'+money(r.diff)+'</span></td><td><button class="mini ghost" onclick="dayJump(\''+r.date+'\')">See rows</button></td></tr>').join('')+'</table></div>');}catch(e){fail(e)}}
function dayJump(d){$('from').value=d;$('to').value=d;cur='browse';loadAll();}
/* ---- tab 7 ---- */
async function companies(){try{const j=await api('companies');
  body('<div class="scroll"><table><tr><th>Company</th><th class="r">Rows</th><th class="r">Debit</th><th class="r">Credit</th><th class="r">Difference</th><th>Status</th></tr>'+
  j.rows.map(r=>{const df=num(r.d)-num(r.c);const nc=r.company_id===null;
   return '<tr><td>'+(nc?'<b>No company / not linked</b>':esc(r.company_name||('Company '+r.company_id))+' <span class="muted">(#'+r.company_id+')</span>')+'</td><td class="r">'+r.n+'</td><td class="r">'+money(r.d)+'</td><td class="r">'+money(r.c)+'</td><td class="r">'+money(df)+'</td><td>'+(Math.abs(df)<0.01?'<span class="pill ok">Balanced</span>':'<span class="pill bad">Unbalanced</span>')+'</td></tr>';}).join('')+'</table></div>'+
  '<p class="muted">Tip: "No company" rows come from opening balances, payroll, stock etc. They have no company, so the GL report drops them when a company is selected.</p>');}catch(e){fail(e)}}
/* ---- tab 8 ---- */
const SRC=['','receive_voucher','payment_voucher','expense_voucher','journal_voucher','transfer_voucher','sale_invoice','purchase_invoice','accounts','opening_balance_invoices','employees','suppliers','bank_accounts','cash_opening','payroll_entries','stock_ledger'];
let bpage=1;
function browse(){body('<div class="bar" style="margin-bottom:12px"><div><label>Voucher table</label><select id="bt">'+SRC.map(s=>'<option value="'+s+'">'+(s?lbl(s):'All')+'</option>').join('')+'</select></div>'+
  '<div><label>Voucher ID</label><input id="bid" size="8" placeholder="e.g. 13347"></div><div><label>Account ID</label><input id="bac" size="8"></div><div><label>Search text</label><input id="bq" placeholder="description / account"></div>'+
  '<button onclick="bpage=1;runBrowse()">Search Ledger</button><button class="ghost" onclick="inspectNow()">Inspect this voucher</button></div><div id="bres" class="muted">Choose filters and press Search.</div>');runBrowse();}
async function runBrowse(){try{const j=await api('browse',{table:$('bt').value,refid:$('bid').value,account:$('bac').value,q:$('bq').value,page:bpage});
  const t=j.total,df=num(t.d)-num(t.c);
  $('bres').innerHTML='<p><b>'+t.n+'</b> rows found. Debit <b>'+money(t.d)+'</b>, Credit <b>'+money(t.c)+'</b>, Difference <span class="pill '+(Math.abs(df)<0.01?'ok':'bad')+'">'+money(df)+'</span></p>'+
   '<div class="scroll"><table><tr><th>Ledger ID</th><th>Date</th><th>Voucher</th><th>Account</th><th>Description</th><th class="r">Debit</th><th class="r">Credit</th><th>Saved at</th></tr>'+
   j.rows.map(x=>'<tr><td>'+x.id+'</td><td>'+esc(x.date)+'</td><td>'+lbl(x.reference_table)+' #'+x.reference_id+'</td><td>'+x.account_id+' - '+esc(x.account_name)+'</td><td>'+esc(x.description)+'</td><td class="r">'+(num(x.debit)?money(x.debit):'')+'</td><td class="r">'+(num(x.credit)?money(x.credit):'')+'</td><td>'+esc(x.created_at)+'</td></tr>').join('')+'</table></div>'+
   '<div class="pager"><button class="ghost mini" '+(j.page<=1?'disabled':'')+' onclick="bpage--;runBrowse()">Previous</button><span>Page '+j.page+'</span><button class="ghost mini" '+(j.page*j.per>=t.n?'disabled':'')+' onclick="bpage++;runBrowse()">Next</button></div>';
  }catch(e){$('bres').innerHTML='<div class="err">'+esc(e.message)+'</div>'}}
function inspectJump(t,id){cur='browse';openTab('browse');setTimeout(()=>{$('bt').value=t;$('bid').value=id;inspectNow();},50);}
async function inspectNow(){const t=$('bt').value,id=$('bid').value;if(!t||id===''){$('bres').innerHTML='<div class="err">Select a voucher table and type a voucher ID first.</div>';return;}
  try{const j=await api('inspect',{table:t,id:id});const rs=j.rows;const d=rs.reduce((a,x)=>a+num(x.debit),0),c=rs.reduce((a,x)=>a+num(x.credit),0);
   let v=[],bad=false;
   if(j.known&&!j.source){v.push('This voucher does not exist in <code>'+t+'</code>.');bad=true;}
   if(!rs.length){v.push('There are <b>no ledger entries</b> for this voucher. Posting is missing.');bad=true;}
   else{ if(Math.abs(d-c)>=0.01){v.push('Ledger is <b>unbalanced</b> by '+money(d-c)+'.');bad=true;}
     if(j.source&&j.checkAmount&&Math.abs(d-num(j.source[j.amtCol]))>=0.01){v.push('Voucher amount is <b>'+money(j.source[j.amtCol])+'</b> but ledger debit is <b>'+money(d)+'</b>. Amount was probably edited without fixing the ledger.');bad=true;}
     if(j.source&&!j.known){} }
   if(!bad)v.push('This voucher looks fine: it exists, has ledger entries, and Debit = Credit.');
   let src='';if(j.source){src='<h4>Voucher record</h4><div class="scroll" style="max-height:220px"><table>'+Object.entries(j.source).map(([k,x])=>'<tr><th>'+esc(k)+'</th><td>'+esc(x)+'</td></tr>').join('')+'</table></div>';}
   $('bres').innerHTML='<div class="verdict '+(bad?'bad':'ok')+'">'+v.join('<br>')+'</div>'+src+'<h4>Ledger entries</h4>'+(rs.length?'<div class="scroll">'+linesTable(rs)+'</div>':'<div class="muted">None</div>');
  }catch(e){$('bres').innerHTML='<div class="err">'+esc(e.message)+'</div>'}}
/* ---- tab 9 ---- */
let dpage=1;
async function dbview(){try{const j=await api('dbtables');
  body('<div class="bar" style="margin-bottom:12px"><div><label>Table</label><select id="dt" onchange="dpage=1;runDb()">'+j.tables.map(t=>'<option>'+t+'</option>').join('')+'</select></div><div><label>Search anything in row</label><input id="dq" placeholder="type and press Search"></div><button onclick="dpage=1;runDb()">Search</button></div><div id="dres">Loading...</div>');runDb();}catch(e){fail(e)}}
async function runDb(){try{const j=await api('dbview',{table:$('dt').value,q:$('dq').value,page:dpage});
  $('dres').innerHTML='<p class="muted">'+j.total+' rows (newest first, only your tenant). Showing '+j.per+' per page.</p><div class="scroll" style="max-height:560px"><table><tr>'+j.cols.map(c=>'<th>'+esc(c)+'</th>').join('')+'</tr>'+
   j.rows.map(r=>'<tr>'+j.cols.map(c=>'<td>'+esc(r[c])+'</td>').join('')+'</tr>').join('')+'</table></div>'+
   '<div class="pager"><button class="ghost mini" '+(j.page<=1?'disabled':'')+' onclick="dpage--;runDb()">Previous</button><span>Page '+j.page+'</span><button class="ghost mini" '+(j.page*j.per>=j.total?'disabled':'')+' onclick="dpage++;runDb()">Next</button></div>';
  }catch(e){$('dres').innerHTML='<div class="err">'+esc(e.message)+'</div>'}}

loadAll();
</script>
</body>
</html>