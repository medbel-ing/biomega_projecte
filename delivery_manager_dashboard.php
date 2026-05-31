<?php
session_start();

// ── Guard ─────────────────────────────────────────────────────────────────────
if (!isset($_SESSION["table"]) || $_SESSION["table"] !== "deliverymanager") {
    header("Location: login.php");
    exit();
}

// ── DB Connection ─────────────────────────────────────────────────────────────
$conn = mysqli_connect("localhost", "root", "", "biomegadb");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

$success = "";
$error   = "";

// ── Handle Assign Order ───────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "assign") {
    $order_id    = trim($_POST["order_id"]    ?? "");
    $pharmacy_id = trim($_POST["pharmacy_id"] ?? "");
    $dp_phone    = trim($_POST["dp_phone"]    ?? "");

    if (empty($order_id) || empty($dp_phone)) {
        $error = "Please select a delivery person.";
    } else {
        // Check if already assigned
        $chk = mysqli_prepare($conn, "SELECT ID FROM asined_order WHERE order_id = ?");
        mysqli_stmt_bind_param($chk, "s", $order_id);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);

        if (mysqli_stmt_num_rows($chk) > 0) {
            // Update existing assignment
            $upd = mysqli_prepare($conn, "UPDATE asined_order SET deliveryperson_id = ? WHERE order_id = ?");
            mysqli_stmt_bind_param($upd, "ss", $dp_phone, $order_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
            $success = "Order <strong>#$order_id</strong> reassigned successfully.";
        } else {
            // New assignment
            $ins = mysqli_prepare($conn,
                "INSERT INTO asined_order (order_id, pharmacy_id, deliveryperson_id) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($ins, "sss", $order_id, $pharmacy_id, $dp_phone);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);
            $success = "Order <strong>#$order_id</strong> assigned successfully.";
        }
        mysqli_stmt_close($chk);
    }
}

// ── Handle Update Total Amount ────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_amount") {
    $order_id = trim($_POST["order_id"] ?? "");
    $amount   = intval($_POST["amount"] ?? 0);

    if (empty($order_id) || $amount < 0) {
        $error = "Invalid amount.";
    } else {
        $upd = mysqli_prepare($conn, "UPDATE `order` SET otalAmount = ? WHERE Tracking = ?");
        mysqli_stmt_bind_param($upd, "is", $amount, $order_id);
        if (mysqli_stmt_execute($upd)) {
            $success = "Amount updated for order <strong>#$order_id</strong>.";
        } else {
            $error = "Failed to update amount.";
        }
        mysqli_stmt_close($upd);
    }
}

// ── Fetch All Orders ──────────────────────────────────────────────────────────
$ordersResult = mysqli_query($conn,
    "SELECT o.*, a.deliveryperson_id, a.pharmacy_id AS assigned_pharmacy,
            d.FirstName AS dp_first, d.LastName AS dp_last,
            p.FirstName AS ph_first, p.LastName AS ph_last, p.Location AS ph_loc
     FROM `order` o
     LEFT JOIN asined_order a ON o.Tracking = a.order_id
     LEFT JOIN deliveryperson d ON a.deliveryperson_id = d.PhoneNumber
     LEFT JOIN pharmacy p ON a.pharmacy_id = p.NIF
     ORDER BY o.Date DESC"
);
$orders = [];
while ($row = mysqli_fetch_assoc($ordersResult)) $orders[] = $row;

// ── Fetch All Delivery Persons ────────────────────────────────────────────────
$dpResult = mysqli_query($conn, "SELECT * FROM deliveryperson ORDER BY FirstName");
$deliveryPersons = [];
while ($row = mysqli_fetch_assoc($dpResult)) $deliveryPersons[] = $row;

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalOrders    = count($orders);
$delivered      = count(array_filter($orders, fn($o) => $o["Status"] == 1));
$notDelivered   = count(array_filter($orders, fn($o) => $o["Status"] == 0));
$assigned       = count(array_filter($orders, fn($o) => !empty($o["deliveryperson_id"])));
$unassigned     = $totalOrders - $assigned;

$managerName = htmlspecialchars(($_SESSION["firstname"] ?? "") . " " . ($_SESSION["lastname"] ?? ""));
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>TronSport Medicamon | Delivery Manager</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
  tailwind.config = {
    darkMode:"class",
    theme:{extend:{
      colors:{
        "primary-fixed-dim":"#a2c9ff","tertiary":"#186a22","secondary":"#4c616c",
        "on-primary":"#ffffff","background":"#f8f9fa","inverse-surface":"#2e3132",
        "surface-tint":"#0060a8","inverse-on-surface":"#f0f1f2","on-error":"#ffffff",
        "secondary-container":"#cfe6f2","on-primary-container":"#fdfcff",
        "on-secondary-container":"#526772","surface-container-lowest":"#ffffff",
        "on-primary-fixed":"#001c38","tertiary-container":"#358438",
        "surface-container-high":"#e7e8e9","on-tertiary":"#ffffff",
        "primary-container":"#0077ce","surface-bright":"#f8f9fa",
        "surface-container-highest":"#e1e3e4","on-background":"#191c1d",
        "secondary-fixed":"#cfe6f2","inverse-primary":"#a2c9ff","surface-dim":"#d9dadb",
        "surface-variant":"#e1e3e4","on-secondary":"#ffffff","error":"#ba1a1a",
        "outline-variant":"#c0c7d4","surface":"#f8f9fa","on-surface":"#191c1d",
        "error-container":"#ffdad6","primary-fixed":"#d3e4ff","surface-container":"#edeeef",
        "on-error-container":"#93000a","on-secondary-fixed":"#071e27","primary":"#005ea4",
        "outline":"#707783","surface-container-low":"#f3f4f5","on-surface-variant":"#404752"
      },
      fontFamily:{"headline":["Manrope"],"body":["Inter"],"label":["Inter"]},
      borderRadius:{"DEFAULT":"0.125rem","lg":"0.25rem","xl":"0.5rem","full":"0.75rem"},
    }},
  }
</script>
<style>
  .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}
  body{font-family:'Inter',sans-serif;}
  @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
  .fade-in{animation:fadeIn 0.35s ease both;}
  .fade-in-1{animation-delay:.05s} .fade-in-2{animation-delay:.1s}
  .fade-in-3{animation-delay:.15s} .fade-in-4{animation-delay:.2s}

  /* Modal */
  .modal-overlay{display:none;position:fixed;inset:0;z-index:100;
    background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);
    align-items:center;justify-content:center;}
  .modal-overlay.open{display:flex;}
  @keyframes slideUp{
    from{opacity:0;transform:translateY(28px) scale(0.97)}
    to{opacity:1;transform:translateY(0) scale(1)}
  }
  .modal-box{animation:slideUp 0.3s cubic-bezier(0.16,1,0.3,1) both;}
  @keyframes shake{
    0%,100%{transform:translateX(0)} 20%{transform:translateX(-5px)}
    40%{transform:translateX(5px)}   60%{transform:translateX(-3px)} 80%{transform:translateX(3px)}
  }
  .shake{animation:shake 0.4s ease;}

  .filter-btn{background:white;color:#404752;border-color:#c0c7d4;}
  .filter-btn:hover{background:#f3f4f5;}
  .active-filter{background:#005ea4!important;color:white!important;border-color:#005ea4!important;}
</style>
</head>
<body class="bg-surface text-on-surface font-body">

<!-- ── Top Nav ────────────────────────────────────────────────────────────── -->
<header class="bg-white/80 backdrop-blur-lg shadow-sm sticky top-0 z-50 flex justify-between items-center px-6 py-3">
  <div class="flex items-center gap-8">
    <span class="text-xl font-extrabold tracking-tighter text-blue-800 font-headline">TronSport Medicamon</span>
    <span class="hidden md:block text-xs font-bold text-on-surface-variant bg-surface-container-low px-3 py-1 rounded-full border border-outline-variant/30">
      Delivery Manager Portal
    </span>
  </div>
  <div class="flex items-center gap-3">
    <span class="hidden sm:block text-sm font-semibold text-on-surface-variant"><?php echo $managerName; ?></span>
    <button class="p-2 hover:bg-slate-50 rounded-full transition-colors">
      <span class="material-symbols-outlined text-slate-600">notifications</span>
    </button>
    <a href="logout.php" class="p-2 hover:bg-slate-50 rounded-full transition-colors" title="Logout">
      <span class="material-symbols-outlined text-slate-600">logout</span>
    </a>
  </div>
</header>

<div class="flex min-h-screen">

  <!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
  <aside class="bg-slate-50 h-screen w-64 border-r border-slate-200 flex flex-col gap-2 p-4 fixed left-0 top-[60px] hidden lg:flex">
    <div class="mb-4 px-2">
      <h3 class="font-headline font-bold text-blue-900">Delivery Manager</h3>
      <p class="text-xs text-on-surface-variant"><?php echo $managerName; ?></p>
    </div>
    <nav class="flex-1 flex flex-col gap-1">
      <a href="delivery_manager_dashboard.php"
         class="bg-blue-50 text-blue-700 rounded-lg font-bold flex items-center gap-3 px-3 py-2.5 hover:translate-x-1 transition-transform">
        <span class="material-symbols-outlined">dashboard</span><span class="text-sm">Dashboard</span>
      </a>
      <a href="#orders-section"
         class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform">
        <span class="material-symbols-outlined">package_2</span><span class="text-sm">Orders</span>
      </a>
      <a href="#dp-section"
         class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform">
        <span class="material-symbols-outlined">local_shipping</span><span class="text-sm">Delivery Persons</span>
      </a>
      <a href="deliverymanager_tracking.php"
         class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform">
        <span class="material-symbols-outlined">location_on</span><span class="text-sm">Live Tracking</span>
      </a>
      <a href="logout.php"
         class="text-red-500 hover:bg-red-50 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform mt-2">
        <span class="material-symbols-outlined">logout</span><span class="text-sm font-bold">Logout</span>
      </a>
    </nav>
  </aside>

  <!-- ── Main ─────────────────────────────────────────────────────────────── -->
  <main class="flex-1 lg:ml-64 p-4 lg:p-8 space-y-8 bg-surface">

    <!-- Page title -->
    <div class="fade-in flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="font-headline text-3xl font-extrabold tracking-tight text-on-surface">Dashboard</h1>
        <p class="text-on-surface-variant font-medium mt-1">Manage orders and delivery assignments.</p>
      </div>
      <a href="deliverymanager_tracking.php"
         class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary text-white font-bold text-sm rounded-xl
                hover:bg-primary/90 active:scale-95 transition-all shadow-sm shadow-primary/20 self-start">
        <span class="material-symbols-outlined text-lg">location_on</span>
        Live Tracking
      </a>
    </div>

    <!-- Alerts -->
    <?php if (!empty($success)): ?>
    <div class="fade-in flex items-center gap-3 bg-tertiary/10 text-tertiary border border-tertiary/20 px-5 py-3.5 rounded-xl font-semibold text-sm">
      <span class="material-symbols-outlined">check_circle</span><?php echo $success; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="fade-in shake flex items-center gap-3 bg-error-container text-on-error-container border border-error/20 px-5 py-3.5 rounded-xl font-semibold text-sm">
      <span class="material-symbols-outlined">error</span><?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- ── KPI Cards ─────────────────────────────────────────────────────── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="fade-in fade-in-1 bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/15 hover:shadow-md transition-shadow">
        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Total Orders</span>
        <div class="mt-2 text-3xl font-headline font-extrabold text-on-surface"><?php echo $totalOrders; ?></div>
      </div>
      <div class="fade-in fade-in-2 bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/15 hover:shadow-md transition-shadow">
        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Delivered</span>
        <div class="mt-2 text-3xl font-headline font-extrabold text-tertiary"><?php echo $delivered; ?></div>
      </div>
      <div class="fade-in fade-in-3 bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/15 hover:shadow-md transition-shadow">
        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Not Delivered</span>
        <div class="mt-2 text-3xl font-headline font-extrabold text-orange-500"><?php echo $notDelivered; ?></div>
      </div>
      <div class="fade-in fade-in-4 bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/15 hover:shadow-md transition-shadow">
        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Unassigned</span>
        <div class="mt-2 text-3xl font-headline font-extrabold text-error"><?php echo $unassigned; ?></div>
      </div>
    </div>

    <!-- ── Orders Table ──────────────────────────────────────────────────── -->
    <div id="orders-section" class="fade-in bg-surface-container-lowest rounded-2xl border border-outline-variant/15 shadow-sm overflow-hidden">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-6 border-b border-outline-variant/15">
        <div>
          <h2 class="font-headline font-bold text-xl text-on-surface">All Orders</h2>
          <p class="text-xs text-on-surface-variant mt-0.5"><?php echo $totalOrders; ?> total orders</p>
        </div>
        <!-- Filter tabs -->
        <div class="flex flex-wrap gap-2">
          <button onclick="filterOrders('all')"        class="filter-btn active-filter px-3 py-1.5 rounded-full text-xs font-bold border transition-all" data-filter="all">All</button>
          <button onclick="filterOrders('unassigned')" class="filter-btn px-3 py-1.5 rounded-full text-xs font-bold border transition-all" data-filter="unassigned">Unassigned</button>
          <button onclick="filterOrders('assigned')"   class="filter-btn px-3 py-1.5 rounded-full text-xs font-bold border transition-all" data-filter="assigned">Assigned</button>
          <button onclick="filterOrders('delivered')"  class="filter-btn px-3 py-1.5 rounded-full text-xs font-bold border transition-all" data-filter="delivered">Delivered</button>
          <button onclick="filterOrders('pending')"    class="filter-btn px-3 py-1.5 rounded-full text-xs font-bold border transition-all" data-filter="pending">Pending</button>
        </div>
      </div>

      <?php if (empty($orders)): ?>
        <div class="flex flex-col items-center justify-center py-20 text-on-surface-variant">
          <span class="material-symbols-outlined text-5xl mb-3 opacity-30">inbox</span>
          <p class="font-bold text-lg">No orders yet</p>
        </div>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm" id="ordersTable">
          <thead class="bg-surface-container-low">
            <tr>
              <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Tracking #</th>
              <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Date</th>
              <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Amount (DZD)</th>
              <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Packages</th>
              <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Status</th>
              <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Pharmacy</th>
              <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Assigned To</th>
              <th class="px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Urgent</th>
              <th class="px-5 py-4 text-right text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/10">
            <?php foreach ($orders as $ord):
              $isAssigned  = !empty($ord["deliveryperson_id"]);
              $isDelivered = $ord["Status"] == 1;
              $isUrgent    = $ord["IsUrgen"] == 1;
              $dpName      = $isAssigned ? htmlspecialchars($ord["dp_first"] . " " . $ord["dp_last"]) : "—";
              $filterClass = ($isDelivered ? "row-delivered " : "row-pending ") . ($isAssigned ? "row-assigned" : "row-unassigned");
            ?>
            <tr class="hover:bg-surface-container-low/60 transition-colors order-row <?php echo $filterClass; ?>"
                data-status="<?php echo $isDelivered ? 'delivered' : 'pending'; ?>"
                data-assigned="<?php echo $isAssigned ? 'assigned' : 'unassigned'; ?>">

              <!-- Tracking -->
              <td class="px-5 py-4">
                <span class="font-mono font-bold text-primary text-sm">#<?php echo htmlspecialchars($ord["Tracking"]); ?></span>
                <?php if ($isUrgent): ?>
                  <span class="ml-1.5 text-[9px] font-black bg-error text-white px-1.5 py-0.5 rounded uppercase">URGENT</span>
                <?php endif; ?>
              </td>

              <!-- Date -->
              <td class="px-5 py-4 text-on-surface-variant"><?php echo htmlspecialchars($ord["Date"]); ?></td>

              <!-- Amount (editable inline) -->
              <td class="px-5 py-4">
                <form method="POST" class="flex items-center gap-1.5">
                  <input type="hidden" name="action"   value="update_amount"/>
                  <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($ord["Tracking"]); ?>"/>
                  <input type="number" name="amount"
                    value="<?php echo intval($ord["otalAmount"]); ?>"
                    min="0"
                    class="w-24 px-2 py-1.5 bg-surface-container-high border-none rounded-lg text-sm font-semibold focus:ring-2 focus:ring-primary/20 transition-all"/>
                  <button type="submit" title="Save amount"
                    class="p-1.5 rounded-lg bg-primary/10 text-primary hover:bg-primary/20 transition-colors">
                    <span class="material-symbols-outlined text-base">save</span>
                  </button>
                </form>
              </td>

              <!-- Packages -->
              <td class="px-5 py-4 font-semibold"><?php echo intval($ord["PackageNumber"]); ?></td>

              <!-- Status -->
              <td class="px-5 py-4">
                <?php if ($isDelivered): ?>
                  <span class="inline-flex items-center gap-1 text-[11px] font-black bg-tertiary/10 text-tertiary px-2.5 py-1 rounded-full uppercase">
                    <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span>Delivered
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center gap-1 text-[11px] font-black bg-orange-100 text-orange-600 px-2.5 py-1 rounded-full uppercase">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400"></span>No Delivery
                  </span>
                <?php endif; ?>
              </td>

              <!-- Pharmacy -->
              <td class="px-5 py-4">
                <?php if (!empty($ord["ph_first"])): ?>
                  <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-tertiary text-base" style="font-variation-settings:'FILL' 1;">local_pharmacy</span>
                    <div>
                      <p class="font-semibold text-on-surface text-xs"><?php echo htmlspecialchars($ord["ph_first"] . " " . $ord["ph_last"]); ?></p>
                      <?php if (!empty($ord["ph_loc"])): ?>
                        <p class="text-[10px] text-on-surface-variant truncate max-w-[120px]"><?php echo htmlspecialchars($ord["ph_loc"]); ?></p>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php else: ?>
                  <span class="inline-flex items-center gap-1 text-[11px] font-bold bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">⚠ Not assigned</span>
                <?php endif; ?>
              </td>

              <!-- Assigned To (delivery person) -->
              <td class="px-5 py-4">
                <?php if ($isAssigned): ?>
                  <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary">
                      <?php echo strtoupper(substr($ord["dp_first"],0,1).substr($ord["dp_last"],0,1)); ?>
                    </div>
                    <span class="font-semibold text-on-surface text-xs"><?php echo $dpName; ?></span>
                  </div>
                <?php else: ?>
                  <span class="text-xs text-on-surface-variant italic">Not assigned</span>
                <?php endif; ?>
              </td>

              <!-- Urgent -->
              <td class="px-5 py-4">
                <?php if ($isUrgent): ?>
                  <span class="material-symbols-outlined text-error text-xl" style="font-variation-settings:'FILL' 1;">priority_high</span>
                <?php else: ?>
                  <span class="text-outline text-xs">—</span>
                <?php endif; ?>
              </td>

              <!-- Actions -->
              <td class="px-5 py-4 text-right">
                <button
                  onclick="openAssignModal('<?php echo htmlspecialchars($ord["Tracking"]); ?>',
                                           '<?php echo htmlspecialchars($ord["assigned_pharmacy"] ?? ""); ?>',
                                           '<?php echo htmlspecialchars($ord["deliveryperson_id"] ?? ""); ?>')"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-bold hover:bg-primary/90 active:scale-95 transition-all">
                  <span class="material-symbols-outlined text-sm">assignment_ind</span>
                  <?php echo $isAssigned ? "Reassign" : "Assign"; ?>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Delivery Persons ───────────────────────────────────────────── -->
    <div id="dp-section" class="fade-in bg-surface-container-lowest rounded-2xl border border-outline-variant/15 shadow-sm p-6">
      <h2 class="font-headline font-bold text-xl text-on-surface mb-5">
        Delivery Persons
        <span class="text-sm font-semibold text-on-surface-variant ml-2">(<?php echo count($deliveryPersons); ?>)</span>
      </h2>

      <?php if (empty($deliveryPersons)): ?>
        <div class="flex flex-col items-center py-12 text-on-surface-variant">
          <span class="material-symbols-outlined text-5xl mb-3 opacity-30">local_shipping</span>
          <p class="font-bold">No delivery persons registered yet.</p>
        </div>
      <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <?php foreach ($deliveryPersons as $dp):
          $initials = strtoupper(substr($dp["FirstName"],0,1).substr($dp["LastName"],0,1));
          // Count assigned orders
          $dpPhone  = $dp["PhoneNumber"];
          $dpOrders = array_filter($orders, fn($o) => $o["deliveryperson_id"] === $dpPhone);
          $dpCount  = count($dpOrders);
        ?>
        <div class="bg-surface-container-low rounded-xl p-4 border border-outline-variant/15 hover:shadow-md transition-shadow flex items-center gap-4">
          <div class="w-11 h-11 rounded-full bg-gradient-to-br from-primary to-primary-container flex items-center justify-center text-white font-bold font-headline text-sm flex-shrink-0">
            <?php echo $initials; ?>
          </div>
          <div class="min-w-0">
            <p class="font-bold text-on-surface text-sm truncate"><?php echo htmlspecialchars($dp["FirstName"] . " " . $dp["LastName"]); ?></p>
            <p class="text-xs text-on-surface-variant"><?php echo htmlspecialchars($dp["PhoneNumber"]); ?></p>
            <p class="text-xs font-bold text-primary mt-0.5"><?php echo $dpCount; ?> order<?php echo $dpCount != 1 ? "s" : ""; ?> assigned</p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- ── Assign Modal ───────────────────────────────────────────────────────── -->
<div id="assignModal" class="modal-overlay" onclick="closeModalOutside(event,'assignModal')">
  <div class="modal-box bg-surface-container-lowest w-full max-w-md mx-4 rounded-2xl shadow-2xl border border-outline-variant/15 p-8">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="font-headline font-extrabold text-xl text-on-surface">Assign Order</h2>
        <p class="text-xs text-on-surface-variant mt-0.5">Select a delivery person for order <span id="modal-order-id" class="font-bold text-primary"></span></p>
      </div>
      <button onclick="closeModal('assignModal')" class="p-2 hover:bg-surface-container rounded-lg transition-colors">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <form method="POST" class="space-y-5">
      <input type="hidden" name="action"      value="assign"/>
      <input type="hidden" name="order_id"    id="modal-order-id-input"/>
      <input type="hidden" name="pharmacy_id" id="modal-pharmacy-id-input"/>

      <div>
        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-2">Delivery Person</label>
        <?php if (empty($deliveryPersons)): ?>
          <p class="text-sm text-error font-semibold">No delivery persons available. Please add one first.</p>
        <?php else: ?>
        <div class="space-y-2 max-h-64 overflow-y-auto pr-1" id="dp-radio-list">
          <?php foreach ($deliveryPersons as $dp):
            $initials = strtoupper(substr($dp["FirstName"],0,1).substr($dp["LastName"],0,1));
          ?>
          <label class="flex items-center gap-3 p-3 rounded-xl border border-outline-variant/20 hover:bg-surface-container-low cursor-pointer transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/5">
            <input type="radio" name="dp_phone" value="<?php echo htmlspecialchars($dp["PhoneNumber"]); ?>"
              class="accent-primary w-4 h-4"/>
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary to-primary-container flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
              <?php echo $initials; ?>
            </div>
            <div>
              <p class="font-bold text-on-surface text-sm"><?php echo htmlspecialchars($dp["FirstName"] . " " . $dp["LastName"]); ?></p>
              <p class="text-xs text-on-surface-variant"><?php echo htmlspecialchars($dp["PhoneNumber"]); ?></p>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="button" onclick="closeModal('assignModal')"
          class="flex-1 py-3 rounded-xl border border-outline-variant text-on-surface-variant font-bold text-sm hover:bg-surface-container transition-colors">
          Cancel
        </button>
        <?php if (!empty($deliveryPersons)): ?>
        <button type="submit"
          class="flex-1 py-3 rounded-xl bg-gradient-to-r from-primary to-primary-container text-white font-bold text-sm shadow-md hover:opacity-90 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
          <span class="material-symbols-outlined text-lg">assignment_ind</span>
          Confirm Assignment
        </button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<script>
  // ── Modal helpers ──────────────────────────────────────────────────────────
  function openAssignModal(orderId, pharmacyId, currentDp) {
    document.getElementById('modal-order-id').textContent      = '#' + orderId;
    document.getElementById('modal-order-id-input').value      = orderId;
    document.getElementById('modal-pharmacy-id-input').value   = pharmacyId;

    // Pre-select current delivery person if assigned
    if (currentDp) {
      const radios = document.querySelectorAll('#dp-radio-list input[type=radio]');
      radios.forEach(r => { r.checked = (r.value === currentDp); });
    } else {
      document.querySelectorAll('#dp-radio-list input[type=radio]').forEach(r => r.checked = false);
    }
    document.getElementById('assignModal').classList.add('open');
  }

  function closeModal(id)         { document.getElementById(id).classList.remove('open'); }
  function closeModalOutside(e,id){ if (e.target === document.getElementById(id)) closeModal(id); }

  // ── Order filter ───────────────────────────────────────────────────────────
  function filterOrders(f) {
    document.querySelectorAll('.filter-btn').forEach(b =>
      b.classList.toggle('active-filter', b.dataset.filter === f));

    document.querySelectorAll('.order-row').forEach(row => {
      const status   = row.dataset.status;
      const assigned = row.dataset.assigned;
      let show = false;
      if (f === 'all')        show = true;
      if (f === 'delivered')  show = status   === 'delivered';
      if (f === 'pending')    show = status   === 'pending';
      if (f === 'assigned')   show = assigned === 'assigned';
      if (f === 'unassigned') show = assigned === 'unassigned';
      row.style.display = show ? '' : 'none';
    });
  }
</script>

<!-- Mobile Bottom Nav -->
<nav class="md:hidden fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 pb-6 pt-3 bg-white/90 backdrop-blur-xl border-t border-slate-200 shadow-[0_-4px_12px_rgba(0,0,0,0.05)]">
  <a href="delivery_manager_dashboard.php" class="flex flex-col items-center bg-blue-100 text-blue-800 rounded-xl px-3 py-1.5">
    <span class="material-symbols-outlined">grid_view</span>
    <span class="text-[10px] font-semibold uppercase">Dashboard</span>
  </a>
  <a href="#orders-section" class="flex flex-col items-center text-slate-400">
    <span class="material-symbols-outlined">package_2</span>
    <span class="text-[10px] font-semibold uppercase">Orders</span>
  </a>
  <a href="deliverymanager_tracking.php" class="flex flex-col items-center text-slate-400">
    <span class="material-symbols-outlined">location_on</span>
    <span class="text-[10px] font-semibold uppercase">Tracking</span>
  </a>
  <a href="logout.php" class="flex flex-col items-center text-slate-400">
    <span class="material-symbols-outlined">logout</span>
    <span class="text-[10px] font-semibold uppercase">Logout</span>
  </a>
</nav>
</body>
</html>