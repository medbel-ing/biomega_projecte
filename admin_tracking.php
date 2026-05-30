<?php
session_start();

// ── Guard ──────────────────────────────────────────────────────────────────────
if (!isset($_SESSION["table"]) || $_SESSION["table"] !== "admin") {
    header("Location: login.php");
    exit();
}

$firstname = htmlspecialchars($_SESSION["firstname"] ?? "Admin");
$lastname  = htmlspecialchars($_SESSION["lastname"]  ?? "");

// ── DB Connection ──────────────────────────────────────────────────────────────
$conn = mysqli_connect("localhost", "root", "", "biomegadb");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

// ── Handle Force GPS POST ──────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["force_gps_phone"])) {
    $targetPhone = mysqli_real_escape_string($conn, $_POST["force_gps_phone"]);
    $adminName   = mysqli_real_escape_string($conn, $firstname . " " . $lastname);
    // Upsert into delivery_location with force flag
    mysqli_query($conn,
        "INSERT INTO delivery_location (PhoneNumber, Latitude, Longitude, Status, GpsForced, ForcedAt, ForcedByAdmin)
         VALUES ('$targetPhone', 0, 0, 0, 1, NOW(), '$adminName')
         ON DUPLICATE KEY UPDATE
           GpsForced = 1,
           ForcedAt = NOW(),
           ForcedByAdmin = '$adminName'"
    );
    header("Location: admin_tracking.php?forced=" . urlencode($targetPhone));
    exit();
}

$forcedMsg = isset($_GET["forced"]) ? $_GET["forced"] : null;

// ── Fetch Delivery Persons with latest location ────────────────────────────────
$deliveryPersonsRes = mysqli_query($conn,
    "SELECT d.ID, d.FirstName, d.LastName, d.PhoneNumber,
            l.Latitude, l.Longitude, l.UpdatedAt, l.Status AS OnlineStatus,
            l.GpsForced, l.ForcedAt, l.ForcedByAdmin,
            COUNT(DISTINCT ao.ID) AS ActiveOrders
     FROM deliveryperson d
     LEFT JOIN delivery_location l ON d.PhoneNumber = l.PhoneNumber
     LEFT JOIN asined_order ao ON d.PhoneNumber = ao.deliveryperson_id
     LEFT JOIN `order` o ON ao.order_id = o.Tracking AND o.Status = 0
     GROUP BY d.PhoneNumber
     ORDER BY l.UpdatedAt DESC"
);
$deliveryPersons = [];
while ($row = mysqli_fetch_assoc($deliveryPersonsRes)) {
    $deliveryPersons[] = $row;
}

// ── Route history ──────────────────────────────────────────────────────────────
$routeHistoryRes = mysqli_query($conn,
    "SELECT PhoneNumber, Latitude, Longitude, UpdatedAt
     FROM delivery_location_history
     WHERE UpdatedAt >= NOW() - INTERVAL 8 HOUR
     ORDER BY PhoneNumber, UpdatedAt ASC"
);
$routeHistory = [];
while ($row = mysqli_fetch_assoc($routeHistoryRes)) {
    $routeHistory[$row["PhoneNumber"]][] = [
        "lat" => (float)$row["Latitude"],
        "lng" => (float)$row["Longitude"],
        "time"=> $row["UpdatedAt"],
    ];
}

// ── Assigned orders ────────────────────────────────────────────────────────────
$assignedOrdersRes = mysqli_query($conn,
    "SELECT ao.deliveryperson_id, ao.order_id,
            o.otalAmount, o.IsUrgen, o.Status,
            p.FirstName AS ph_first, p.LastName AS ph_last, p.Location
     FROM asined_order ao
     JOIN `order` o ON ao.order_id = o.Tracking
     LEFT JOIN pharmacy p ON ao.pharmacy_id = p.NIF
     WHERE o.Status = 0
     ORDER BY o.IsUrgen DESC"
);
$assignedOrders = [];
while ($row = mysqli_fetch_assoc($assignedOrdersRes)) {
    $assignedOrders[$row["deliveryperson_id"]][] = $row;
}

// ── KPIs ───────────────────────────────────────────────────────────────────────
$totalDP  = count($deliveryPersons);
$onlineDP = count(array_filter($deliveryPersons, fn($d) =>
    !empty($d["Latitude"]) && !empty($d["UpdatedAt"]) &&
    strtotime($d["UpdatedAt"]) > time() - 600
));
$offlineDP   = $totalDP - $onlineDP;
$forcedCount = count(array_filter($deliveryPersons, fn($d) => !empty($d["GpsForced"])));

mysqli_close($conn);

$palette  = ["#0060a8","#186a22","#b45309","#7c3aed","#be123c","#0891b2","#d97706","#059669"];
$dpColors = [];
foreach ($deliveryPersons as $i => $dp) {
    $dpColors[$dp["PhoneNumber"]] = $palette[$i % count($palette)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Live Tracking | TronSport Medicamon</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  tailwind.config = {
    darkMode:"class",
    theme:{extend:{
      colors:{
        "primary-fixed-dim":"#a2c9ff","tertiary":"#186a22","secondary":"#4c616c",
        "on-primary":"#ffffff","background":"#f8f9fa","on-primary-fixed-variant":"#004881",
        "inverse-surface":"#2e3132","surface-tint":"#0060a8","inverse-on-surface":"#f0f1f2",
        "on-error":"#ffffff","secondary-container":"#cfe6f2","on-primary-container":"#fdfcff",
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
  @keyframes fadeIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
  .fade-in{animation:fadeIn 0.4s ease both;}
  @keyframes pulse-dot{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.6);opacity:.6}}
  .pulse-dot{animation:pulse-dot 2s ease-in-out infinite;}
  @keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
  @keyframes beacon{0%{transform:scale(1);opacity:.8}100%{transform:scale(2.8);opacity:0}}
  .beacon-ring{animation:beacon 1.5s ease-out infinite;}
  #map{height:calc(100vh - 230px);min-height:420px;border-radius:1rem;z-index:1;}
  .leaflet-popup-content-wrapper{border-radius:12px!important;box-shadow:0 4px 24px rgba(0,0,0,.15)!important;padding:0!important;overflow:hidden;}
  .leaflet-popup-content{margin:0!important;}
  .dp-card.selected{border-color:#005ea4!important;background:#eff6ff!important;}
  .dp-card{cursor:pointer;transition:all .2s;}
  .dp-card:hover{background:#f0f4ff!important;}
  /* Force GPS button pulse */
  @keyframes forceBtn{0%,100%{box-shadow:0 0 0 0 rgba(186,26,26,.4)}70%{box-shadow:0 0 0 8px rgba(186,26,26,0)}}
  .force-btn-pulse{animation:forceBtn 2s ease-in-out infinite;}
  /* Toast */
  #toast{transition:opacity .4s,transform .4s;}
</style>
</head>
<body class="bg-surface text-on-surface font-body">

<?php if ($forcedMsg): ?>
<div id="forced-toast" style="position:fixed;top:80px;right:24px;z-index:9999;
  background:#005ea4;color:white;padding:12px 20px;border-radius:14px;
  font-size:13px;font-weight:700;box-shadow:0 4px 20px rgba(0,0,0,.2);
  display:flex;align-items:center;gap:8px;">
  <span class="material-symbols-outlined text-lg" style="font-variation-settings:'FILL' 1;">gps_fixed</span>
  GPS request sent to <?php echo htmlspecialchars($forcedMsg); ?>
</div>
<script>setTimeout(()=>{const t=document.getElementById('forced-toast');if(t)t.remove();},4000);</script>
<?php endif; ?>

<!-- ── Top Nav ──────────────────────────────────────────────────────────────── -->
<header class="bg-white/80 backdrop-blur-lg shadow-sm sticky top-0 z-50 flex justify-between items-center px-6 py-3 w-full">
  <div class="flex items-center gap-8">
    <span class="text-xl font-extrabold tracking-tighter text-blue-800 font-headline">TronSport Medicamon</span>
    <nav class="hidden md:flex items-center gap-6">
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_dashboard.php">Dashboard</a>
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_orders.php">Orders</a>
      <a class="text-blue-700 font-bold border-b-2 border-blue-600 px-1 py-1" href="admin_tracking.php">Tracking</a>
    </nav>
  </div>
  <div class="flex items-center gap-3">
    <div class="hidden sm:flex items-center gap-2 bg-tertiary/10 text-tertiary px-3 py-1.5 rounded-full text-xs font-bold">
      <span class="w-2 h-2 rounded-full bg-tertiary pulse-dot inline-block"></span>
      Live · <span id="last-refresh"><?php echo date("H:i:s"); ?></span>
    </div>
    <?php if ($forcedCount > 0): ?>
    <div class="flex items-center gap-2 bg-error/10 text-error px-3 py-1.5 rounded-full text-xs font-bold">
      <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1;">gps_fixed</span>
      <?php echo $forcedCount; ?> GPS request<?php echo $forcedCount>1?'s':''; ?> pending
    </div>
    <?php endif; ?>
    <button onclick="location.reload()" class="p-2 hover:bg-slate-50 rounded-full active:scale-95 transition-colors" title="Refresh">
      <span class="material-symbols-outlined text-slate-600">refresh</span>
    </button>
    <a href="logout.php" class="p-2 hover:bg-slate-50 rounded-full active:scale-95 transition-colors" title="Logout">
      <span class="material-symbols-outlined text-slate-600">logout</span>
    </a>
  </div>
</header>

<div class="flex min-h-screen">

  <!-- ── Sidebar ──────────────────────────────────────────────────────────────── -->
  <aside class="bg-slate-50 h-screen w-64 border-r border-slate-200 flex flex-col gap-2 p-4 fixed left-0 top-[60px] hidden lg:flex">
    <div class="mb-4 px-2">
      <h3 class="font-headline font-bold text-blue-900">Admin Portal</h3>
      <p class="text-xs text-on-surface-variant"><?php echo $firstname." ".$lastname; ?> • Operational</p>
    </div>
    <nav class="flex-1 flex flex-col gap-1">
      <a href="admin_dashboard.php" class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform">
        <span class="material-symbols-outlined">dashboard</span><span class="text-sm">Dashboard</span>
      </a>
      <a href="admin_pharmacies.php" class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform">
        <span class="material-symbols-outlined">local_pharmacy</span><span class="text-sm">Pharmacies</span>
      </a>
      <a href="admin_employees.php" class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform">
        <span class="material-symbols-outlined">badge</span><span class="text-sm">Employees</span>
      </a>
      <a href="admin_orders.php" class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform">
        <span class="material-symbols-outlined">package_2</span><span class="text-sm">Orders</span>
      </a>
      <a href="admin_payments.php" class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform">
        <span class="material-symbols-outlined">payments</span><span class="text-sm">Payments</span>
      </a>
      <a href="admin_tracking.php" class="bg-blue-50 text-blue-700 rounded-lg font-bold flex items-center gap-3 px-3 py-2.5 hover:translate-x-1 transition-transform">
        <span class="material-symbols-outlined">local_shipping</span><span class="text-sm">Tracking</span>
      </a>
      <a href="admin_settings.php" class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform">
        <span class="material-symbols-outlined">settings</span><span class="text-sm">Settings</span>
      </a>
      <a href="logout.php" class="text-red-500 hover:bg-red-50 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform mt-2">
        <span class="material-symbols-outlined">logout</span><span class="text-sm font-bold">Logout</span>
      </a>
    </nav>
  </aside>

  <!-- ── Main ─────────────────────────────────────────────────────────────────── -->
  <main class="flex-1 lg:ml-64 p-4 lg:p-6 space-y-5 bg-surface">

    <!-- Header -->
    <div class="fade-in flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="font-headline text-3xl font-extrabold tracking-tight text-on-surface">Live Tracking</h1>
        <p class="text-on-surface-variant font-medium mt-0.5">Real-time positions & route history. Force GPS if a driver is offline.</p>
      </div>
      <div class="flex gap-3 flex-wrap">
        <div class="bg-surface-container-lowest border border-outline-variant/15 rounded-xl px-4 py-2 flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-tertiary pulse-dot"></span>
          <span class="text-sm font-bold text-tertiary"><?php echo $onlineDP; ?> Online</span>
        </div>
        <div class="bg-surface-container-lowest border border-outline-variant/15 rounded-xl px-4 py-2 flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
          <span class="text-sm font-bold text-slate-500"><?php echo $offlineDP; ?> Offline</span>
        </div>
        <?php if ($forcedCount > 0): ?>
        <div class="bg-error/10 border border-error/20 rounded-xl px-4 py-2 flex items-center gap-2">
          <span class="material-symbols-outlined text-error text-base" style="font-variation-settings:'FILL' 1;">gps_fixed</span>
          <span class="text-sm font-bold text-error"><?php echo $forcedCount; ?> Awaiting GPS</span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-[360px_1fr] gap-5 fade-in">

      <!-- ── Delivery Persons List ─────────────────────────────────────────── -->
      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/15 shadow-sm flex flex-col overflow-hidden">
        <div class="p-4 border-b border-outline-variant/10">
          <h2 class="font-headline font-bold text-base text-on-surface">Delivery Personnel</h2>
          <p class="text-xs text-on-surface-variant mt-0.5">Click card to focus map · Use Force GPS for offline drivers</p>
        </div>

        <?php if (empty($deliveryPersons)): ?>
        <div class="flex flex-col items-center py-16 text-on-surface-variant">
          <span class="material-symbols-outlined text-5xl opacity-20 mb-3">local_shipping</span>
          <p class="font-semibold">No delivery personnel yet</p>
          <a href="admin_employees.php?action=add" class="mt-3 text-xs text-primary font-bold hover:underline">Add one →</a>
        </div>
        <?php else: ?>
        <div class="flex-1 overflow-y-auto divide-y divide-outline-variant/10 max-h-[calc(100vh-320px)]">
          <?php foreach ($deliveryPersons as $dp):
            $phone     = $dp["PhoneNumber"];
            $hasLoc    = !empty($dp["Latitude"]) && !empty($dp["Longitude"]);
            $isOnline  = $hasLoc && strtotime($dp["UpdatedAt"]) > time() - 600;
            $isForced  = !empty($dp["GpsForced"]);
            $color     = $dpColors[$phone];
            $orders    = $assignedOrders[$phone] ?? [];
            $urgentCnt = count(array_filter($orders, fn($o) => $o["IsUrgen"] == 1));
          ?>
          <div class="dp-card p-4 border border-transparent rounded-xl mx-2 my-1 <?php echo $isForced && !$isOnline ? 'bg-red-50/60' : ''; ?>"
               data-phone="<?php echo htmlspecialchars($phone); ?>"
               data-lat="<?php echo $hasLoc ? $dp['Latitude'] : ''; ?>"
               data-lng="<?php echo $hasLoc ? $dp['Longitude'] : ''; ?>"
               data-name="<?php echo htmlspecialchars($dp['FirstName'].' '.$dp['LastName']); ?>"
               onclick="focusDriver(this)">

            <!-- Top row: avatar + name + status -->
            <div class="flex items-center gap-3">
              <div class="relative flex-shrink-0">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm"
                     style="background:<?php echo $color; ?>">
                  <?php echo strtoupper(substr($dp["FirstName"],0,1).substr($dp["LastName"],0,1)); ?>
                </div>
                <!-- GPS forced beacon ring -->
                <?php if ($isForced && !$isOnline): ?>
                <div class="absolute inset-0 rounded-full border-2 border-error beacon-ring"></div>
                <?php endif; ?>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <p class="font-bold text-sm text-on-surface truncate"><?php echo htmlspecialchars($dp["FirstName"]." ".$dp["LastName"]); ?></p>
                  <?php if ($urgentCnt > 0): ?><span class="text-[9px] font-black bg-error text-white px-1.5 py-0.5 rounded uppercase"><?php echo $urgentCnt; ?> URGENT</span><?php endif; ?>
                  <?php if ($isForced && !$isOnline): ?><span class="text-[9px] font-black bg-error/10 text-error px-1.5 py-0.5 rounded uppercase flex items-center gap-0.5"><span class="material-symbols-outlined text-[10px]">gps_fixed</span>GPS Requested</span><?php endif; ?>
                </div>
                <p class="text-xs text-on-surface-variant"><?php echo htmlspecialchars($phone); ?></p>
              </div>
              <span class="flex items-center gap-1 text-[10px] font-bold flex-shrink-0 <?php echo $isOnline ? 'text-tertiary' : 'text-slate-400'; ?>">
                <span class="w-2 h-2 rounded-full <?php echo $isOnline ? 'bg-tertiary pulse-dot' : 'bg-slate-300'; ?>"></span>
                <?php echo $isOnline ? 'Online' : 'Offline'; ?>
              </span>
            </div>

            <!-- Location row -->
            <?php if ($hasLoc): ?>
            <div class="mt-2 flex items-center gap-1.5 text-[10px] text-on-surface-variant bg-surface-container-low rounded-lg px-2 py-1">
              <span class="material-symbols-outlined text-[13px]" style="font-variation-settings:'FILL' 1;">location_on</span>
              <span><?php echo number_format((float)$dp["Latitude"],5); ?>, <?php echo number_format((float)$dp["Longitude"],5); ?></span>
              <span class="ml-auto font-semibold"><?php echo date("H:i", strtotime($dp["UpdatedAt"])); ?></span>
            </div>
            <?php else: ?>
            <div class="mt-2 flex items-center gap-1.5 text-[10px] text-slate-400 bg-slate-50 rounded-lg px-2 py-1">
              <span class="material-symbols-outlined text-[13px]">location_off</span>
              <span>No location data yet</span>
              <?php if ($isForced): ?>
              <span class="ml-auto text-error font-bold">Waiting for response…</span>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Orders mini list -->
            <?php if (!empty($orders)): ?>
            <div class="mt-2 space-y-1">
              <?php foreach (array_slice($orders,0,2) as $ord): ?>
              <div class="flex items-center justify-between text-[10px] font-medium bg-surface-container-low rounded px-2 py-1">
                <span class="flex items-center gap-1">
                  <?php if($ord["IsUrgen"]): ?><span class="material-symbols-outlined text-error text-[11px]">priority_high</span><?php endif; ?>
                  #<?php echo htmlspecialchars($ord["order_id"]); ?>
                </span>
                <span class="text-on-surface-variant truncate max-w-[110px]"><?php echo htmlspecialchars($ord["ph_first"]." ".$ord["ph_last"]); ?></span>
              </div>
              <?php endforeach; ?>
              <?php if(count($orders)>2): ?><p class="text-[10px] text-primary font-bold text-right">+<?php echo count($orders)-2; ?> more</p><?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- ★ FORCE GPS BUTTON ★ -->
            <?php if (!$isOnline): ?>
            <form method="POST" action="admin_tracking.php" onsubmit="return confirmForce('<?php echo htmlspecialchars($dp['FirstName'].' '.$dp['LastName']); ?>')" onclick="event.stopPropagation()">
              <input type="hidden" name="force_gps_phone" value="<?php echo htmlspecialchars($phone); ?>"/>
              <button type="submit"
                class="mt-3 w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-black uppercase tracking-wide transition-all active:scale-95
                  <?php echo $isForced
                    ? 'bg-orange-100 text-orange-600 border border-orange-300 hover:bg-orange-200'
                    : 'bg-error text-white hover:bg-red-700 force-btn-pulse'; ?>">
                <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1;">gps_fixed</span>
                <?php echo $isForced ? '⏳ Re-send GPS Request' : '📡 Force GPS Activation'; ?>
              </button>
            </form>
            <?php else: ?>
            <div class="mt-3 flex items-center gap-2 justify-center text-[10px] font-bold text-tertiary bg-tertiary/5 rounded-xl py-2">
              <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1;">gps_fixed</span>
              GPS Active — tracking live
            </div>
            <?php endif; ?>

          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="p-3 border-t border-outline-variant/10 bg-surface-container-low">
          <div class="flex items-center gap-4 text-[10px] font-semibold text-on-surface-variant flex-wrap">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-tertiary"></span>Online</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-slate-300"></span>Offline</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-error"></span>GPS Forced</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-primary"></span>Route</span>
          </div>
        </div>
      </div>

      <!-- ── Map ──────────────────────────────────────────────────────────── -->
      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/15 shadow-sm overflow-hidden flex flex-col">
        <div class="p-3 border-b border-outline-variant/10 flex items-center gap-3 flex-wrap">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-lg">map</span>
            <span class="font-headline font-bold text-sm text-on-surface">Live Map</span>
          </div>
          <div class="flex gap-2 ml-auto flex-wrap">
            <button onclick="showAllDrivers()" class="text-xs font-bold bg-surface-container border border-outline-variant/20 px-3 py-1.5 rounded-lg hover:bg-surface-container-high transition-colors flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]">group</span>Show All
            </button>
            <button onclick="toggleRoutes()" id="route-toggle-btn" class="text-xs font-bold bg-primary/10 text-primary border border-primary/20 px-3 py-1.5 rounded-lg hover:bg-primary/20 transition-colors flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]">route</span>Routes ON
            </button>
            <button onclick="toggleSatellite()" class="text-xs font-bold bg-surface-container border border-outline-variant/20 px-3 py-1.5 rounded-lg hover:bg-surface-container-high transition-colors flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]">satellite</span>Satellite
            </button>
          </div>
        </div>
        <div id="map" class="flex-1 rounded-b-2xl"></div>
      </div>

    </div>
  </main>
</div>

<!-- Mobile Bottom Nav -->
<nav class="md:hidden fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 pb-6 pt-3 bg-white/90 backdrop-blur-xl border-t border-slate-200">
  <a href="admin_dashboard.php" class="flex flex-col items-center text-slate-400">
    <span class="material-symbols-outlined">grid_view</span>
    <span class="text-[10px] font-semibold uppercase">Dashboard</span>
  </a>
  <a href="admin_orders.php" class="flex flex-col items-center text-slate-400">
    <span class="material-symbols-outlined">auto_stories</span>
    <span class="text-[10px] font-semibold uppercase">Orders</span>
  </a>
  <a href="admin_tracking.php" class="flex flex-col items-center bg-blue-100 text-blue-800 rounded-xl px-3 py-1.5">
    <span class="material-symbols-outlined">local_shipping</span>
    <span class="text-[10px] font-semibold uppercase">Tracking</span>
  </a>
  <a href="admin_settings.php" class="flex flex-col items-center text-slate-400">
    <span class="material-symbols-outlined">settings</span>
    <span class="text-[10px] font-semibold uppercase">Settings</span>
  </a>
</nav>

<script>
const DELIVERY_PERSONS = <?php echo json_encode($deliveryPersons); ?>;
const ROUTE_HISTORY    = <?php echo json_encode($routeHistory); ?>;
const ASSIGNED_ORDERS  = <?php echo json_encode($assignedOrders); ?>;
const DP_COLORS        = <?php echo json_encode($dpColors); ?>;

const DEFAULT_CENTER = [36.1898, 5.4135];
let map, tileNormal, tileSatellite;
let markers = {}, routeLines = {};
let showRoutes = true, isSatellite = false;

function initMap() {
  map = L.map('map', { zoomControl: true }).setView(DEFAULT_CENTER, 12);
  tileNormal = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors', maxZoom: 19
  }).addTo(map);
  tileSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: '© Esri', maxZoom: 19
  });
  renderAll();
}

function makeDriverIcon(color, initials, isOnline, isForced) {
  const ring = isForced && !isOnline
    ? `<div style="position:absolute;inset:-4px;border-radius:50%;border:2px solid #ba1a1a;animation:beacon 1.5s ease-out infinite;"></div>` : '';
  const dot = isOnline
    ? `<div style="position:absolute;bottom:-5px;left:50%;transform:translateX(-50%);width:9px;height:9px;border-radius:50%;background:${color};opacity:.5;animation:pulse-dot 2s ease-in-out infinite;"></div>` : '';
  return L.divIcon({
    className: '',
    html: `<div style="position:relative;width:40px;height:40px;">
      ${ring}
      <div style="width:40px;height:40px;border-radius:50%;background:${color};
        border:3px solid white;box-shadow:0 2px 10px rgba(0,0,0,.3);
        display:flex;align-items:center;justify-content:center;
        color:white;font-weight:700;font-size:13px;font-family:Inter,sans-serif;position:relative;">
        ${initials}${dot}
      </div>
    </div>`,
    iconSize:[40,40], iconAnchor:[20,20], popupAnchor:[0,-24],
  });
}

function renderAll() {
  Object.values(markers).forEach(m => map.removeLayer(m));
  Object.values(routeLines).forEach(l => map.removeLayer(l));
  markers = {}; routeLines = {};
  const bounds = [];

  DELIVERY_PERSONS.forEach(dp => {
    const phone    = dp.PhoneNumber;
    const color    = DP_COLORS[phone] || '#005ea4';
    const initials = (dp.FirstName[0]||'')+(dp.LastName[0]||'');
    const hasLoc   = dp.Latitude && dp.Longitude;
    const isOnline = hasLoc && (Date.now()/1000 - new Date(dp.UpdatedAt).getTime()/1000) < 600;
    const isForced = !!dp.GpsForced;
    const orders   = ASSIGNED_ORDERS[phone] || [];

    const history = ROUTE_HISTORY[phone];
    if (history && history.length > 1 && showRoutes) {
      routeLines[phone] = L.polyline(history.map(p=>[p.lat,p.lng]), {
        color, weight:3, opacity:.7, dashArray:'6 4'
      }).addTo(map);
    }

    if (!hasLoc) return;
    const lat = parseFloat(dp.Latitude), lng = parseFloat(dp.Longitude);
    bounds.push([lat, lng]);

    const icon = makeDriverIcon(color, initials.toUpperCase(), isOnline, isForced);
    const marker = L.marker([lat, lng], { icon }).addTo(map);

    const lastSeen = dp.UpdatedAt ? new Date(dp.UpdatedAt).toLocaleTimeString('fr-DZ',{hour:'2-digit',minute:'2-digit'}) : '—';
    const ordersHtml = orders.map(o =>
      `<div style="display:flex;justify-content:space-between;font-size:11px;padding:2px 0;border-bottom:1px solid #f0f0f0">
         <span>${o.IsUrgen?'🔴':'📦'} #${o.order_id}</span>
         <span style="color:#666">${o.ph_first} ${o.ph_last}</span>
       </div>`).join('') || '<p style="font-size:11px;color:#999">No active orders</p>';

    const forceBtnHtml = !isOnline
      ? `<form method="POST" action="admin_tracking.php" onsubmit="return confirmForce('${dp.FirstName} ${dp.LastName}')">
           <input type="hidden" name="force_gps_phone" value="${phone}"/>
           <button type="submit" style="margin-top:10px;width:100%;background:${isForced?'#fff7ed':'#ba1a1a'};
             color:${isForced?'#c2410c':'white'};border:${isForced?'1px solid #fdba74':'none'};
             border-radius:10px;padding:8px;font-size:12px;font-weight:800;cursor:pointer;
             display:flex;align-items:center;justify-content:center;gap:6px;letter-spacing:.03em;text-transform:uppercase;">
             📡 ${isForced ? 'Re-send GPS Request' : 'Force GPS Activation'}
           </button>
         </form>` : '';

    const popup = `
      <div style="min-width:230px;font-family:Inter,sans-serif;">
        <div style="background:${color};padding:12px 14px;color:white;">
          <p style="font-weight:800;font-size:14px;margin:0">${dp.FirstName} ${dp.LastName}</p>
          <p style="font-size:11px;margin:2px 0 0;opacity:.85">${phone}</p>
        </div>
        <div style="padding:12px 14px;">
          <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
            <span style="width:8px;height:8px;border-radius:50%;background:${isOnline?'#186a22':'#aaa'};display:inline-block"></span>
            <span style="font-size:11px;font-weight:700;color:${isOnline?'#186a22':'#999'}">${isOnline?'Online':'Offline'}</span>
            <span style="font-size:10px;color:#999;margin-left:auto">Last: ${lastSeen}</span>
          </div>
          <p style="font-size:10px;font-weight:700;color:#666;margin:0 0 4px;text-transform:uppercase">Active Orders (${orders.length})</p>
          ${ordersHtml}
          <p style="margin-top:6px;font-size:10px;color:#aaa">📍 ${lat.toFixed(5)}, ${lng.toFixed(5)}</p>
          ${forceBtnHtml}
        </div>
      </div>`;

    marker.bindPopup(popup, { maxWidth: 280 });
    marker.on('click', () => highlightCard(phone));
    markers[phone] = marker;
  });

  if (bounds.length) map.fitBounds(L.latLngBounds(bounds).pad(0.2));
}

function focusDriver(el) {
  const phone = el.dataset.phone;
  const lat = parseFloat(el.dataset.lat);
  const lng = parseFloat(el.dataset.lng);
  document.querySelectorAll('.dp-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  if (!isNaN(lat) && !isNaN(lng)) {
    map.flyTo([lat, lng], 15, { duration: 1.2 });
    setTimeout(() => markers[phone] && markers[phone].openPopup(), 1300);
  }
}
function highlightCard(phone) {
  document.querySelectorAll('.dp-card').forEach(c => {
    if (c.dataset.phone === phone) { c.classList.add('selected'); c.scrollIntoView({behavior:'smooth',block:'nearest'}); }
    else c.classList.remove('selected');
  });
}
function showAllDrivers() {
  document.querySelectorAll('.dp-card').forEach(c => c.classList.remove('selected'));
  const bounds = Object.values(markers).map(m => m.getLatLng());
  if (bounds.length) map.fitBounds(L.latLngBounds(bounds).pad(0.2));
}
function toggleRoutes() {
  showRoutes = !showRoutes;
  const btn = document.getElementById('route-toggle-btn');
  btn.innerHTML = `<span class="material-symbols-outlined text-[14px]">route</span>Routes ${showRoutes?'ON':'OFF'}`;
  btn.className = showRoutes
    ? 'text-xs font-bold bg-primary/10 text-primary border border-primary/20 px-3 py-1.5 rounded-lg hover:bg-primary/20 transition-colors flex items-center gap-1'
    : 'text-xs font-bold bg-surface-container border border-outline-variant/20 px-3 py-1.5 rounded-lg hover:bg-surface-container-high transition-colors flex items-center gap-1';
  renderAll();
}
function toggleSatellite() {
  isSatellite = !isSatellite;
  if (isSatellite) { map.removeLayer(tileNormal); tileSatellite.addTo(map); }
  else             { map.removeLayer(tileSatellite); tileNormal.addTo(map); }
}
function confirmForce(name) {
  return confirm(`Send GPS activation request to ${name}?\n\nThey will see a mandatory popup on their screen that they cannot close until GPS is enabled.`);
}

// Auto-refresh every 30s
setInterval(() => location.reload(), 30000);
document.addEventListener('DOMContentLoaded', initMap);
</script>
</body>
</html>
