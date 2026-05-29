<?php
session_start();

// ── Guard ─────────────────────────────────────────────────────────────────────
if (!isset($_SESSION["table"]) || $_SESSION["table"] !== "admin") {
    header("Location: login.php");
    exit();
}

// ── DB Connection ─────────────────────────────────────────────────────────────
$conn = mysqli_connect("localhost", "root", "", "biomegadb");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

$success = "";
$error   = "";

// ── Handle Add Employee POST ──────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "add_employee") {

    $firstname = trim($_POST["firstname"] ?? "");
    $lastname  = trim($_POST["lastname"]  ?? "");
    $phone     = trim($_POST["phone"]     ?? "");
    $role      = trim($_POST["role"]      ?? "");
    $password  = trim($_POST["password"]  ?? "");

    if (empty($firstname) || empty($lastname) || empty($phone) || empty($role) || empty($password)) {
        $error = "All fields are required.";
    } else {
        // Map role to table
        $roleTableMap = [
            "commercialservice" => "commercialservice",
            "deliverymanager"   => "deliverymanager",
            "deliveryperson"    => "deliveryperson",
            "stockemployee"     => "stockemployee",
        ];

        if (!array_key_exists($role, $roleTableMap)) {
            $error = "Invalid role selected.";
        } else {
            $table = $roleTableMap[$role];

            // Check phone not already used in this table
            $check = mysqli_prepare($conn, "SELECT PhoneNumber FROM `$table` WHERE PhoneNumber = ?");
            mysqli_stmt_bind_param($check, "s", $phone);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);

            if (mysqli_stmt_num_rows($check) > 0) {
                $error = "This phone number is already registered in the $role table.";
            } else {
                $roleLabel = ucfirst($role);
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO `$table` (FirstName, LastName, PhoneNumber, Role, Password) VALUES (?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($stmt, "sssss", $firstname, $lastname, $phone, $roleLabel, $password);

                if (mysqli_stmt_execute($stmt)) {
                    $success = "Employee <strong>$firstname $lastname</strong> added successfully as <strong>$roleLabel</strong>.";
                } else {
                    $error = "Database error: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($check);
        }
    }
}

// ── Handle Delete ─────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "delete_employee") {
    $del_table = $_POST["del_table"] ?? "";
    $del_phone = $_POST["del_phone"] ?? "";

    $allowed = ["commercialservice","deliverymanager","deliveryperson","stockemployee"];
    if (in_array($del_table, $allowed) && !empty($del_phone)) {
        $stmt = mysqli_prepare($conn, "DELETE FROM `$del_table` WHERE PhoneNumber = ?");
        mysqli_stmt_bind_param($stmt, "s", $del_phone);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $success = "Employee removed successfully.";
    }
}

// ── Fetch All Employees from all 4 tables ────────────────────────────────────
$employeeTables = ["commercialservice", "deliverymanager", "deliveryperson", "stockemployee"];
$allEmployees   = [];

foreach ($employeeTables as $tbl) {
    $res = mysqli_query($conn, "SELECT *, '$tbl' as source_table FROM `$tbl`");
    while ($row = mysqli_fetch_assoc($res)) {
        $allEmployees[] = $row;
    }
}

$adminFirstname = htmlspecialchars($_SESSION["firstname"] ?? "Admin");
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>TronSport Medicamon | Employees</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        colors: {
          "primary-fixed-dim": "#a2c9ff", "tertiary": "#186a22", "secondary": "#4c616c",
          "on-primary": "#ffffff", "background": "#f8f9fa", "inverse-surface": "#2e3132",
          "surface-tint": "#0060a8", "inverse-on-surface": "#f0f1f2", "on-error": "#ffffff",
          "secondary-container": "#cfe6f2", "on-primary-container": "#fdfcff",
          "on-secondary-container": "#526772", "surface-container-lowest": "#ffffff",
          "on-primary-fixed": "#001c38", "tertiary-container": "#358438",
          "surface-container-high": "#e7e8e9", "on-tertiary": "#ffffff",
          "primary-container": "#0077ce", "surface-bright": "#f8f9fa",
          "surface-container-highest": "#e1e3e4", "on-background": "#191c1d",
          "secondary-fixed": "#cfe6f2", "inverse-primary": "#a2c9ff",
          "surface-dim": "#d9dadb", "surface-variant": "#e1e3e4", "on-secondary": "#ffffff",
          "error": "#ba1a1a", "outline-variant": "#c0c7d4", "surface": "#f8f9fa",
          "on-surface": "#191c1d", "error-container": "#ffdad6", "primary-fixed": "#d3e4ff",
          "surface-container": "#edeeef", "on-error-container": "#93000a",
          "on-secondary-fixed": "#071e27", "primary": "#005ea4", "outline": "#707783",
          "surface-container-low": "#f3f4f5", "on-surface-variant": "#404752"
        },
        fontFamily: { "headline": ["Manrope"], "body": ["Inter"], "label": ["Inter"] },
        borderRadius: {"DEFAULT":"0.125rem","lg":"0.25rem","xl":"0.5rem","full":"0.75rem"},
      },
    },
  }
</script>
<style>
  .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
  body { font-family:'Inter',sans-serif; }

  @keyframes fadeIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
  .fade-in { animation: fadeIn 0.35s ease both; }

  /* Modal */
  #modal-overlay {
    display: none;
    position: fixed; inset: 0; z-index: 100;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(4px);
    align-items: center; justify-content: center;
  }
  #modal-overlay.open { display: flex; }

  @keyframes slideUp {
    from{opacity:0;transform:translateY(30px) scale(0.97)}
    to  {opacity:1;transform:translateY(0)     scale(1)}
  }
  #modal-box { animation: slideUp 0.3s cubic-bezier(0.16,1,0.3,1) both; }

  @keyframes shake {
    0%,100%{transform:translateX(0)} 20%{transform:translateX(-5px)}
    40%{transform:translateX(5px)}   60%{transform:translateX(-3px)} 80%{transform:translateX(3px)}
  }
  .shake { animation: shake 0.4s ease; }
</style>
</head>
<body class="bg-surface text-on-surface font-body">

<!-- ── Top Nav ────────────────────────────────────────────────────────────── -->
<header class="bg-white/80 backdrop-blur-lg shadow-sm sticky top-0 z-50 flex justify-between items-center px-6 py-3 w-full">
  <div class="flex items-center gap-8">
    <span class="text-xl font-extrabold tracking-tighter text-blue-800 font-headline">TronSport Medicamon</span>
    <nav class="hidden md:flex items-center gap-6">
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_dashboard.php">Dashboard</a>
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_orders.php">Orders</a>
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_inventory.php">Inventory</a>
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_reports.php">Reports</a>
    </nav>
  </div>
  <div class="flex items-center gap-3">
    <button class="p-2 hover:bg-slate-50 rounded-full transition-colors">
      <span class="material-symbols-outlined text-slate-600">notifications</span>
    </button>
    <a href="logout.php" class="p-2 hover:bg-slate-50 rounded-full transition-colors flex items-center gap-1 text-sm font-semibold text-slate-600" title="Logout">
      <span class="material-symbols-outlined">logout</span>
    </a>
  </div>
</header>

<div class="flex min-h-screen">

  <!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
  <aside class="bg-slate-50 h-screen w-64 border-r border-slate-200 flex flex-col gap-2 p-4 fixed left-0 top-[60px] hidden lg:flex">
    <div class="mb-4 px-2">
      <h3 class="font-headline font-bold text-blue-900">Admin Portal</h3>
      <p class="text-xs text-on-surface-variant"><?php echo $adminFirstname; ?> • Operational</p>
    </div>
    <nav class="flex-1 flex flex-col gap-1">
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span><span class="text-sm">Dashboard</span>
      </a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_pharmacies.php">
        <span class="material-symbols-outlined">local_pharmacy</span><span class="text-sm">Pharmacies</span>
      </a>
      <a class="bg-blue-50 text-blue-700 rounded-lg font-bold flex items-center gap-3 px-3 py-2.5 transition-transform hover:translate-x-1" href="admin_employees.php">
        <span class="material-symbols-outlined">badge</span><span class="text-sm">Employees</span>
      </a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_orders.php">
        <span class="material-symbols-outlined">package_2</span><span class="text-sm">Orders</span>
      </a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_payments.php">
        <span class="material-symbols-outlined">payments</span><span class="text-sm">Payments</span>
      </a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_tracking.php">
        <span class="material-symbols-outlined">local_shipping</span><span class="text-sm">Tracking</span>
      </a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_settings.php">
        <span class="material-symbols-outlined">settings</span><span class="text-sm">Settings</span>
      </a>
      <a class="text-red-500 hover:bg-red-50 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1 mt-2" href="logout.php">
        <span class="material-symbols-outlined">logout</span><span class="text-sm font-bold">Logout</span>
      </a>
    </nav>
    <div class="mt-auto pt-4 border-t border-slate-200">
      <button onclick="openModal()" class="w-full bg-gradient-to-r from-primary to-primary-container text-white py-3 px-4 rounded-xl font-bold text-sm shadow-md flex items-center justify-center gap-2 active:scale-95 transition-transform">
        <span class="material-symbols-outlined text-lg">person_add</span>
        Add Employee
      </button>
    </div>
  </aside>

  <!-- ── Main Content ──────────────────────────────────────────────────────── -->
  <main class="flex-1 lg:ml-64 p-4 lg:p-8 space-y-6 bg-surface">

    <!-- Page Header -->
    <div class="fade-in flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="font-headline text-3xl font-extrabold tracking-tight text-on-surface">Employees</h1>
        <p class="text-on-surface-variant font-medium mt-1">
          Manage all staff across every department —
          <span class="font-bold text-primary"><?php echo count($allEmployees); ?> total</span>
        </p>
      </div>
      <button onclick="openModal()" class="flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-md hover:bg-primary/90 active:scale-95 transition-all">
        <span class="material-symbols-outlined text-lg">person_add</span>
        Add Employee
      </button>
    </div>

    <!-- Success / Error banners -->
    <?php if (!empty($success)): ?>
    <div class="fade-in flex items-center gap-3 bg-tertiary/10 text-tertiary border border-tertiary/20 px-5 py-3.5 rounded-xl font-semibold text-sm">
      <span class="material-symbols-outlined">check_circle</span>
      <?php echo $success; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="fade-in shake flex items-center gap-3 bg-error-container text-on-error-container border border-error/20 px-5 py-3.5 rounded-xl font-semibold text-sm">
      <span class="material-symbols-outlined">error</span>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Role filter tabs -->
    <div class="fade-in flex flex-wrap gap-2" id="filterTabs">
      <button onclick="filterTable('all')" class="filter-btn active-filter px-4 py-1.5 rounded-full text-xs font-bold border transition-all" data-filter="all">
        All (<?php echo count($allEmployees); ?>)
      </button>
      <?php
      $roleCounts = [];
      foreach ($allEmployees as $e) {
          $t = $e["source_table"];
          $roleCounts[$t] = ($roleCounts[$t] ?? 0) + 1;
      }
      $roleLabels = [
          "commercialservice" => "Commercial Service",
          "deliverymanager"   => "Delivery Manager",
          "deliveryperson"    => "Delivery Person",
          "stockemployee"     => "Stock Employee",
      ];
      foreach ($roleLabels as $key => $label):
          $cnt = $roleCounts[$key] ?? 0;
      ?>
      <button onclick="filterTable('<?php echo $key; ?>')" class="filter-btn px-4 py-1.5 rounded-full text-xs font-bold border transition-all" data-filter="<?php echo $key; ?>">
        <?php echo $label; ?> (<?php echo $cnt; ?>)
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Employees Table -->
    <div class="fade-in bg-surface-container-lowest rounded-2xl border border-outline-variant/15 shadow-sm overflow-hidden">
      <?php if (empty($allEmployees)): ?>
        <div class="flex flex-col items-center justify-center py-20 text-on-surface-variant">
          <span class="material-symbols-outlined text-5xl mb-3 opacity-30">group_off</span>
          <p class="font-bold text-lg">No employees yet</p>
          <p class="text-sm mt-1">Click "Add Employee" to get started.</p>
        </div>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm" id="employeeTable">
          <thead class="bg-surface-container-low border-b border-outline-variant/20">
            <tr>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Employee</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Role</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Phone</th>
              <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">ID</th>
              <th class="px-6 py-4 text-right text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/10" id="employeeRows">
            <?php
            $roleColors = [
                "commercialservice" => ["bg" => "bg-blue-100",   "text" => "text-blue-700"],
                "deliverymanager"   => ["bg" => "bg-purple-100", "text" => "text-purple-700"],
                "deliveryperson"    => ["bg" => "bg-orange-100", "text" => "text-orange-600"],
                "stockemployee"     => ["bg" => "bg-teal-100",   "text" => "text-teal-700"],
            ];
            foreach ($allEmployees as $emp):
                $tbl    = $emp["source_table"];
                $colors = $roleColors[$tbl] ?? ["bg" => "bg-slate-100", "text" => "text-slate-600"];
                $initials = strtoupper(substr($emp["FirstName"], 0, 1) . substr($emp["LastName"], 0, 1));
            ?>
            <tr class="hover:bg-surface-container-low transition-colors employee-row" data-role="<?php echo $tbl; ?>">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary text-sm flex-shrink-0">
                    <?php echo $initials; ?>
                  </div>
                  <div>
                    <p class="font-semibold text-on-surface"><?php echo htmlspecialchars($emp["FirstName"] . " " . $emp["LastName"]); ?></p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <span class="text-[11px] font-black px-2.5 py-1 rounded-full uppercase tracking-wide <?php echo $colors['bg'] . ' ' . $colors['text']; ?>">
                  <?php echo htmlspecialchars($roleLabels[$tbl] ?? $tbl); ?>
                </span>
              </td>
              <td class="px-6 py-4 text-on-surface-variant font-medium"><?php echo htmlspecialchars($emp["PhoneNumber"]); ?></td>
              <td class="px-6 py-4 text-on-surface-variant text-xs font-mono">#<?php echo htmlspecialchars($emp["ID"]); ?></td>
              <td class="px-6 py-4 text-right">
                <form method="POST" onsubmit="return confirm('Delete this employee? This cannot be undone.');" class="inline">
                  <input type="hidden" name="action"    value="delete_employee"/>
                  <input type="hidden" name="del_table" value="<?php echo $tbl; ?>"/>
                  <input type="hidden" name="del_phone" value="<?php echo htmlspecialchars($emp["PhoneNumber"]); ?>"/>
                  <button type="submit" class="p-2 rounded-lg text-error hover:bg-error-container transition-colors" title="Delete">
                    <span class="material-symbols-outlined text-base">delete</span>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- ── Add Employee Modal ─────────────────────────────────────────────────── -->
<div id="modal-overlay" onclick="closeModalOutside(event)">
  <div id="modal-box" class="bg-surface-container-lowest w-full max-w-lg mx-4 rounded-2xl shadow-2xl border border-outline-variant/15 p-8">

    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="font-headline font-extrabold text-xl text-on-surface">Add New Employee</h2>
        <p class="text-xs text-on-surface-variant mt-0.5">Fill in the details below to register a new staff member.</p>
      </div>
      <button onclick="closeModal()" class="p-2 hover:bg-surface-container rounded-lg transition-colors text-on-surface-variant">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="add_employee"/>

      <div class="grid grid-cols-2 gap-4">
        <!-- First Name -->
        <div>
          <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1.5">First Name</label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">person</span>
            <input type="text" name="firstname" required placeholder="Ahmed"
              value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>"
              class="w-full pl-10 pr-3 py-3 bg-surface-container-high border-none rounded-lg text-sm focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-outline/50"/>
          </div>
        </div>
        <!-- Last Name -->
        <div>
          <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1.5">Last Name</label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">person</span>
            <input type="text" name="lastname" required placeholder="Benali"
              value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>"
              class="w-full pl-10 pr-3 py-3 bg-surface-container-high border-none rounded-lg text-sm focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-outline/50"/>
          </div>
        </div>
      </div>

      <!-- Phone -->
      <div>
        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1.5">Phone Number</label>
        <div class="relative">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">phone</span>
          <input type="tel" name="phone" required placeholder="0551234567"
            value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
            class="w-full pl-10 pr-3 py-3 bg-surface-container-high border-none rounded-lg text-sm focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-outline/50"/>
        </div>
      </div>

      <!-- Role -->
      <div>
        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1.5">Role / Department</label>
        <div class="relative">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">badge</span>
          <select name="role" required
            class="w-full pl-10 pr-3 py-3 bg-surface-container-high border-none rounded-lg text-sm focus:ring-2 focus:ring-primary/20 transition-all appearance-none">
            <option value="" disabled selected>Select a role...</option>
            <option value="commercialservice" <?php echo (($_POST['role'] ?? '') === 'commercialservice') ? 'selected' : ''; ?>>Commercial Service</option>
            <option value="deliverymanager"   <?php echo (($_POST['role'] ?? '') === 'deliverymanager')   ? 'selected' : ''; ?>>Delivery Manager</option>
            <option value="deliveryperson"    <?php echo (($_POST['role'] ?? '') === 'deliveryperson')    ? 'selected' : ''; ?>>Delivery Person</option>
            <option value="stockemployee"     <?php echo (($_POST['role'] ?? '') === 'stockemployee')     ? 'selected' : ''; ?>>Stock Employee</option>
          </select>
        </div>
      </div>

      <!-- Password -->
      <div>
        <label class="block text-xs font-bold text-on-surface-variant uppercase tracking-wider mb-1.5">Password</label>
        <div class="relative">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">lock</span>
          <input type="password" name="password" id="modalPassword" required placeholder="••••••••"
            class="w-full pl-10 pr-10 py-3 bg-surface-container-high border-none rounded-lg text-sm focus:ring-2 focus:ring-primary/20 transition-all placeholder:text-outline/50"/>
          <button type="button" onclick="toggleModalPassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-primary">
            <span id="modalEyeIcon" class="material-symbols-outlined text-lg">visibility</span>
          </button>
        </div>
      </div>

      <!-- Buttons -->
      <div class="flex gap-3 pt-2">
        <button type="button" onclick="closeModal()"
          class="flex-1 py-3 rounded-xl border border-outline-variant text-on-surface-variant font-bold text-sm hover:bg-surface-container transition-colors">
          Cancel
        </button>
        <button type="submit"
          class="flex-1 py-3 rounded-xl bg-gradient-to-r from-primary to-primary-container text-white font-bold text-sm shadow-md hover:opacity-90 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
          <span class="material-symbols-outlined text-lg">person_add</span>
          Add Employee
        </button>
      </div>
    </form>
  </div>
</div>

<style>
  .filter-btn { background: white; color: #404752; border-color: #c0c7d4; }
  .filter-btn:hover { background: #f3f4f5; }
  .active-filter { background: #005ea4 !important; color: white !important; border-color: #005ea4 !important; }
</style>

<script>
  // ── Modal ──────────────────────────────────────────────────────────────────
  function openModal()  { document.getElementById('modal-overlay').classList.add('open'); }
  function closeModal() { document.getElementById('modal-overlay').classList.remove('open'); }
  function closeModalOutside(e) { if (e.target === document.getElementById('modal-overlay')) closeModal(); }

  function toggleModalPassword() {
    const input = document.getElementById('modalPassword');
    const icon  = document.getElementById('modalEyeIcon');
    input.type      = input.type === 'password' ? 'text' : 'password';
    icon.textContent = input.type === 'password' ? 'visibility' : 'visibility_off';
  }

  // ── Auto-open modal if there was a POST error ──────────────────────────────
  <?php if (!empty($error)): ?>
  window.addEventListener('DOMContentLoaded', openModal);
  <?php endif; ?>

  // ── Role filter ────────────────────────────────────────────────────────────
  function filterTable(role) {
    document.querySelectorAll('.filter-btn').forEach(btn => {
      btn.classList.toggle('active-filter', btn.dataset.filter === role);
    });
    document.querySelectorAll('.employee-row').forEach(row => {
      row.style.display = (role === 'all' || row.dataset.role === role) ? '' : 'none';
    });
  }
</script>

</body>
</html>
