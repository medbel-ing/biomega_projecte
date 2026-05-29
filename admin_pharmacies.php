<?php
session_start();

// ── Guard ─────────────────────────────────────────────────────────────────────
if (!isset($_SESSION["table"]) || $_SESSION["table"] !== "admin") {
    header("Location: login.php");
    exit();
}

// ── DB ────────────────────────────────────────────────────────────────────────
$conn = mysqli_connect("localhost", "root", "", "biomegadb");
if (!$conn) die("DB connection failed: " . mysqli_connect_error());

$firstname = htmlspecialchars($_SESSION["firstname"] ?? "Admin");
$lastname  = htmlspecialchars($_SESSION["lastname"]  ?? "");
$success   = "";
$error     = "";

// ── Handle DELETE ─────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_nif"])) {
    $nif = (int) $_POST["delete_nif"];

    // Count linked orders via asined_order
    $chk = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM asined_order WHERE pharmacy_id = ?");
    mysqli_stmt_bind_param($chk, "i", $nif);
    mysqli_stmt_execute($chk);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    $has_orders = ((int)$r["cnt"]) > 0;
    mysqli_stmt_close($chk);

    if ($has_orders && !isset($_POST["force_delete"])) {
        $error = "Cette pharmacie possède des commandes assignées. Confirmez la suppression forcée.";
        $_SESSION["pending_delete_nif"] = $nif;
    } else {
        // Delete asined_order rows too if force
        if ($has_orders) {
            $da = mysqli_prepare($conn, "DELETE FROM asined_order WHERE pharmacy_id = ?");
            mysqli_stmt_bind_param($da, "i", $nif);
            mysqli_stmt_execute($da);
            mysqli_stmt_close($da);
        }
        $del = mysqli_prepare($conn, "DELETE FROM pharmacy WHERE NIF = ?");
        mysqli_stmt_bind_param($del, "i", $nif);
        if (mysqli_stmt_execute($del)) {
            $success = "Pharmacie #$nif supprimée avec succès.";
            unset($_SESSION["pending_delete_nif"]);
        } else {
            $error = "Erreur lors de la suppression : " . mysqli_error($conn);
        }
        mysqli_stmt_close($del);
    }
}

// ── Search ────────────────────────────────────────────────────────────────────
$search = trim($_GET["q"] ?? "");

if ($search !== "") {
    $sql  = "SELECT * FROM pharmacy WHERE
             NIF LIKE ? OR FirstName LIKE ? OR LastName LIKE ? OR
             PhoneNumber LIKE ? OR Location LIKE ?
             ORDER BY NIF ASC";
    $stmt = mysqli_prepare($conn, $sql);
    $like = "%" . $search . "%";
    mysqli_stmt_bind_param($stmt, "sssss", $like, $like, $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, "SELECT * FROM pharmacy ORDER BY NIF ASC");
}

$pharmacies = [];
while ($row = mysqli_fetch_assoc($result)) {
    $pharmacies[] = $row;
}

// ── Order counts per pharmacy via asined_order ────────────────────────────────
// Also fetch order status by joining asined_order → order
$order_data = []; // [nif => ["total"=>n, "orders"=>[...]] ]

foreach ($pharmacies as $p) {
    $nif = (int)$p["NIF"];

    $q = mysqli_prepare($conn,
        "SELECT ao.order_id, ao.deliveryperson_id,
                o.Status, o.Date, o.IsUrgen, o.Tracking
         FROM asined_order ao
         LEFT JOIN `order` o ON ao.order_id = o.Tracking
         WHERE ao.pharmacy_id = ?
         ORDER BY o.Date DESC");
    mysqli_stmt_bind_param($q, "i", $nif);
    mysqli_stmt_execute($q);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($q), MYSQLI_ASSOC);
    mysqli_stmt_close($q);

    $order_data[$nif] = [
        "total"     => count($rows),
        "orders"    => $rows,
        "delivered" => count(array_filter($rows, fn($r) => (int)($r["Status"] ?? -1) === 1)),
        "pending"   => count(array_filter($rows, fn($r) => (int)($r["Status"] ?? -1) === 0)),
        "urgent"    => count(array_filter($rows, fn($r) => (int)($r["IsUrgen"] ?? 0) === 1)),
    ];
}

$total_pharmacies = count($pharmacies);
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Pharmacies — Bio Mega Pharme Admin</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
  tailwind.config = {
    darkMode:"class",
    theme:{extend:{colors:{
      "primary":"#005ea4","primary-container":"#0077ce","primary-fixed":"#d3e4ff",
      "on-primary":"#ffffff","tertiary":"#186a22","tertiary-container":"#358438",
      "on-tertiary":"#ffffff","secondary":"#4c616c","secondary-container":"#cfe6f2",
      "on-secondary-container":"#526772","surface":"#f8f9fa",
      "surface-container-lowest":"#ffffff","surface-container-low":"#f3f4f5",
      "surface-container":"#edeeef","surface-container-high":"#e7e8e9",
      "on-surface":"#191c1d","on-surface-variant":"#404752","outline":"#707783",
      "outline-variant":"#c0c7d4","error":"#ba1a1a","error-container":"#ffdad6",
      "on-error-container":"#93000a","inverse-surface":"#2e3132","inverse-on-surface":"#f0f1f2",
    },fontFamily:{"headline":["Manrope"],"body":["Inter"]},
    borderRadius:{"DEFAULT":"0.125rem","lg":"0.25rem","xl":"0.5rem","full":"0.75rem"}}},
  }
</script>
<style>
  .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}
  body{font-family:'Inter',sans-serif;}
  @keyframes fadeIn{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
  .fade-in{animation:fadeIn .35s ease both;}
  #deleteModal{display:none;}
  #deleteModal.open{display:flex;}
  #ordersModal{display:none;}
  #ordersModal.open{display:flex;}
  .pharm-row:hover{background:#f3f4f5;}
  .status-pill{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:.04em;}
</style>
</head>
<body class="bg-surface text-on-surface">

<!-- ── Top Nav ────────────────────────────────────────────────────────────── -->
<header class="bg-white/90 backdrop-blur-lg shadow-sm sticky top-0 z-50 flex justify-between items-center px-6 py-3 w-full">
  <div class="flex items-center gap-8">
    <span class="text-xl font-extrabold tracking-tighter text-blue-800" style="font-family:Manrope,sans-serif;">Bio Mega Pharme</span>
    <nav class="hidden md:flex items-center gap-6">
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_dashboard.php">Dashboard</a>
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_orders.php">Orders</a>
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_employees.php">Employees</a>
    </nav>
  </div>
  <div class="flex items-center gap-3">
    <button class="p-2 hover:bg-slate-50 rounded-full transition-colors">
      <span class="material-symbols-outlined text-slate-600">notifications</span>
    </button>
    <a href="logout.php" class="p-2 hover:bg-slate-50 rounded-full" title="Logout">
      <span class="material-symbols-outlined text-slate-600">logout</span>
    </a>
  </div>
</header>

<div class="flex min-h-screen">

  <!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
  <aside class="bg-slate-50 h-screen w-64 border-r border-slate-200 flex flex-col gap-2 p-4 fixed left-0 top-[57px] hidden lg:flex">
    <div class="mb-4 px-2">
      <h3 class="font-bold text-blue-900" style="font-family:Manrope,sans-serif;">Admin Portal</h3>
      <p class="text-xs text-on-surface-variant"><?php echo $firstname." ".$lastname; ?> • Operational</p>
    </div>
    <nav class="flex-1 flex flex-col gap-1">
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform" href="admin_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span><span class="text-sm">Dashboard</span></a>
      <a class="bg-blue-50 text-blue-700 rounded-lg font-bold flex items-center gap-3 px-3 py-2.5" href="admin_pharmacies.php">
        <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">local_pharmacy</span><span class="text-sm">Pharmacies</span></a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform" href="admin_employees.php">
        <span class="material-symbols-outlined">badge</span><span class="text-sm">Employees</span></a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform" href="admin_orders.php">
        <span class="material-symbols-outlined">package_2</span><span class="text-sm">Orders</span></a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform" href="admin_payments.php">
        <span class="material-symbols-outlined">payments</span><span class="text-sm">Payments</span></a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform" href="admin_tracking.php">
        <span class="material-symbols-outlined">local_shipping</span><span class="text-sm">Tracking</span></a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg hover:translate-x-1 transition-transform" href="admin_settings.php">
        <span class="material-symbols-outlined">settings</span><span class="text-sm">Settings</span></a>
      <a class="text-red-500 hover:bg-red-50 flex items-center gap-3 px-3 py-2.5 rounded-lg mt-2" href="logout.php">
        <span class="material-symbols-outlined">logout</span><span class="text-sm font-bold">Logout</span></a>
    </nav>
    <div class="mt-auto pt-4 border-t border-slate-200">
      <button onclick="window.location='admin_orders.php?action=new_emergency'"
        class="w-full bg-gradient-to-r from-primary to-primary-container text-white py-3 px-4 rounded-xl font-bold text-sm shadow-md flex items-center justify-center gap-2 active:scale-95 transition-transform">
        <span class="material-symbols-outlined text-lg">add_circle</span>New Emergency Order
      </button>
    </div>
  </aside>

  <!-- ── Main ────────────────────────────────────────────────────────────── -->
  <main class="flex-1 lg:ml-64 p-4 lg:p-8 space-y-6 bg-surface">

    <!-- Header -->
    <div class="fade-in flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-on-surface" style="font-family:Manrope,sans-serif;">Pharmacies</h1>
        <p class="text-on-surface-variant text-sm mt-1">
          <span class="font-bold text-primary"><?php echo $total_pharmacies; ?></span>
          pharmacie(s) enregistrée(s) — triées par NIF
        </p>
      </div>
      <a href="register_pharmacy.php"
        class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:opacity-90 active:scale-95 transition-all shadow-sm">
        <span class="material-symbols-outlined text-lg">add_circle</span>Ajouter une Pharmacie
      </a>
    </div>

    <!-- Flash messages -->
    <?php if (!empty($success)): ?>
    <div class="fade-in flex items-center gap-3 bg-green-50 text-green-800 border border-green-200 px-4 py-3 rounded-xl text-sm font-semibold">
      <span class="material-symbols-outlined text-green-600" style="font-variation-settings:'FILL' 1;">check_circle</span>
      <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="fade-in flex items-center gap-3 bg-error-container text-on-error-container border border-error/20 px-4 py-3 rounded-xl text-sm font-semibold">
      <span class="material-symbols-outlined">error</span>
      <?php echo htmlspecialchars($error); ?>
      <?php if (isset($_SESSION["pending_delete_nif"])): ?>
      <form method="POST" class="ml-auto flex-shrink-0">
        <input type="hidden" name="delete_nif" value="<?php echo (int)$_SESSION['pending_delete_nif']; ?>"/>
        <input type="hidden" name="force_delete" value="1"/>
        <button type="submit" class="bg-error text-white px-4 py-1.5 rounded-lg text-xs font-bold hover:opacity-90">
          Forcer la suppression
        </button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="fade-in">
      <form method="GET" action="admin_pharmacies.php" class="flex gap-2 flex-wrap">
        <div class="relative flex-1 min-w-[220px] max-w-md">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">search</span>
          <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
            placeholder="NIF, nom, téléphone, wilaya..."
            class="w-full pl-10 pr-4 py-2.5 bg-surface-container-lowest border border-outline-variant/30 rounded-xl text-sm focus:ring-2 focus:ring-primary/20 focus:outline-none"/>
        </div>
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-xl font-semibold text-sm hover:opacity-90 active:scale-95 transition-all">
          Chercher
        </button>
        <?php if ($search !== ""): ?>
        <a href="admin_pharmacies.php" class="px-4 py-2.5 border border-outline-variant/30 rounded-xl text-sm font-medium text-on-surface-variant hover:bg-surface-container transition-colors">
          Effacer
        </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Stats bar -->
    <?php
      $total_orders_all   = array_sum(array_column($order_data, "total"));
      $total_delivered    = array_sum(array_column($order_data, "delivered"));
      $total_pending      = array_sum(array_column($order_data, "pending"));
      $total_urgent       = array_sum(array_column($order_data, "urgent"));
    ?>
    <div class="fade-in grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/15 px-4 py-3 flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-primary text-lg" style="font-variation-settings:'FILL' 1;">local_pharmacy</span>
        </div>
        <div>
          <p class="text-xs text-on-surface-variant">Pharmacies</p>
          <p class="text-lg font-extrabold text-on-surface" style="font-family:Manrope,sans-serif;"><?php echo $total_pharmacies; ?></p>
        </div>
      </div>
      <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/15 px-4 py-3 flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-primary text-lg" style="font-variation-settings:'FILL' 1;">package_2</span>
        </div>
        <div>
          <p class="text-xs text-on-surface-variant">Total commandes</p>
          <p class="text-lg font-extrabold text-on-surface" style="font-family:Manrope,sans-serif;"><?php echo $total_orders_all; ?></p>
        </div>
      </div>
      <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/15 px-4 py-3 flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-green-600 text-lg" style="font-variation-settings:'FILL' 1;">check_circle</span>
        </div>
        <div>
          <p class="text-xs text-on-surface-variant">Livrées</p>
          <p class="text-lg font-extrabold text-green-700" style="font-family:Manrope,sans-serif;"><?php echo $total_delivered; ?></p>
        </div>
      </div>
      <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/15 px-4 py-3 flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-error-container flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-error text-lg" style="font-variation-settings:'FILL' 1;">priority_high</span>
        </div>
        <div>
          <p class="text-xs text-on-surface-variant">Urgentes</p>
          <p class="text-lg font-extrabold text-error" style="font-family:Manrope,sans-serif;"><?php echo $total_urgent; ?></p>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="fade-in bg-surface-container-lowest rounded-2xl border border-outline-variant/15 shadow-sm overflow-hidden">

      <?php if (empty($pharmacies)): ?>
      <div class="flex flex-col items-center justify-center py-20 text-center px-6">
        <span class="material-symbols-outlined text-6xl text-outline/30 mb-4" style="font-variation-settings:'FILL' 1;">local_pharmacy</span>
        <h3 class="font-bold text-lg text-on-surface mb-2" style="font-family:Manrope,sans-serif;">Aucune pharmacie trouvée</h3>
        <p class="text-on-surface-variant text-sm mb-6">
          <?php echo $search ? "Aucun résultat pour \"".htmlspecialchars($search)."\"." : "Aucune pharmacie enregistrée."; ?>
        </p>
        <a href="register_pharmacy.php" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:opacity-90">
          <span class="material-symbols-outlined text-lg">add_circle</span>Enregistrer la première pharmacie
        </a>
      </div>

      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-surface-container-low border-b border-outline-variant/20">
            <tr>
              <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70 w-16">NIF</th>
              <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Responsable</th>
              <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Téléphone</th>
              <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Localisation</th>
              <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Horaire</th>
              <th class="px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Commandes liées</th>
              <th class="px-5 py-3.5 text-center text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/10">
            <?php foreach ($pharmacies as $i => $p):
              $nif  = (int)$p["NIF"];
              $data = $order_data[$nif] ?? ["total"=>0,"orders"=>[],"delivered"=>0,"pending"=>0,"urgent"=>0];
            ?>
            <tr class="pharm-row transition-colors" style="animation:fadeIn .3s ease <?php echo $i*0.04; ?>s both;">

              <!-- NIF -->
              <td class="px-5 py-4">
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-primary/10 text-primary font-extrabold text-xs" style="font-family:Manrope,sans-serif;">
                  #<?php echo $nif; ?>
                </span>
              </td>

              <!-- Name -->
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary to-primary-container text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
                    <?php echo strtoupper(mb_substr($p["FirstName"], 0, 1)); ?>
                  </div>
                  <div>
                    <p class="font-semibold text-on-surface"><?php echo htmlspecialchars($p["FirstName"]." ".$p["LastName"]); ?></p>
                    <p class="text-xs text-on-surface-variant">Pharmacien(ne)</p>
                  </div>
                </div>
              </td>

              <!-- Phone -->
              <td class="px-5 py-4">
                <a href="tel:<?php echo htmlspecialchars($p["PhoneNumber"]); ?>"
                  class="flex items-center gap-1.5 text-primary font-medium hover:underline">
                  <span class="material-symbols-outlined text-sm">phone</span>
                  <?php echo htmlspecialchars($p["PhoneNumber"]); ?>
                </a>
              </td>

              <!-- Location -->
              <td class="px-5 py-4 max-w-[180px]">
                <div class="flex items-start gap-1.5">
                  <span class="material-symbols-outlined text-sm text-outline mt-0.5 flex-shrink-0">location_on</span>
                  <span class="text-on-surface-variant text-xs leading-relaxed"><?php echo htmlspecialchars($p["Location"]); ?></span>
                </div>
              </td>

              <!-- WorkTime -->
              <td class="px-5 py-4">
                <div class="flex items-center gap-1.5 text-on-surface-variant text-xs">
                  <span class="material-symbols-outlined text-sm">schedule</span>
                  <?php echo htmlspecialchars($p["WorkTime"]); ?>
                </div>
              </td>

              <!-- Orders -->
              <td class="px-5 py-4">
                <?php if ($data["total"] === 0): ?>
                  <span class="status-pill bg-surface-container text-on-surface-variant">
                    <span class="material-symbols-outlined text-xs">remove</span> Aucune
                  </span>
                <?php else: ?>
                  <button
                    onclick='openOrders(<?php echo $nif; ?>, <?php echo htmlspecialchars(json_encode($data["orders"]), ENT_QUOTES); ?>)'
                    class="inline-flex items-center gap-2 bg-primary/10 hover:bg-primary/20 text-primary px-3 py-1.5 rounded-full text-xs font-bold transition-colors mb-1.5">
                    <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1;">package_2</span>
                    <?php echo $data["total"]; ?> commande<?php echo $data["total"]>1?"s":""; ?>
                  </button>
                  <div class="flex flex-wrap gap-1.5">
                    <?php if ($data["delivered"] > 0): ?>
                    <span class="status-pill bg-green-100 text-green-700">✓ <?php echo $data["delivered"]; ?> livrée<?php echo $data["delivered"]>1?"s":"";?></span>
                    <?php endif; ?>
                    <?php if ($data["pending"] > 0): ?>
                    <span class="status-pill bg-secondary-container text-on-secondary-container">⏳ <?php echo $data["pending"]; ?> en attente</span>
                    <?php endif; ?>
                    <?php if ($data["urgent"] > 0): ?>
                    <span class="status-pill bg-error-container text-error">🚨 <?php echo $data["urgent"]; ?> urgente<?php echo $data["urgent"]>1?"s":"";?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- Actions -->
              <td class="px-5 py-4 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button
                    onclick='confirmDelete(<?php echo $nif; ?>, "<?php echo htmlspecialchars(addslashes($p["FirstName"]." ".$p["LastName"])); ?>", <?php echo $data["total"]; ?>)'
                    class="p-2 rounded-lg hover:bg-error-container text-error transition-colors" title="Supprimer">
                    <span class="material-symbols-outlined text-lg">delete</span>
                  </button>
                </div>
              </td>

            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="px-5 py-3 border-t border-outline-variant/20 bg-surface-container-low flex items-center justify-between text-xs text-on-surface-variant">
        <span><?php echo $total_pharmacies; ?> résultat<?php echo $total_pharmacies>1?"s":""; ?></span>
        <span>Trié par NIF croissant · Données via <code>asined_order</code></span>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- ── Delete Modal ────────────────────────────────────────────────────────── -->
<div id="deleteModal" class="fixed inset-0 z-[100] items-center justify-center bg-black/50 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8">
    <div class="flex flex-col items-center text-center mb-6">
      <div class="w-16 h-16 rounded-full bg-error-container flex items-center justify-center mb-4">
        <span class="material-symbols-outlined text-error text-3xl" style="font-variation-settings:'FILL' 1;">delete_forever</span>
      </div>
      <h2 class="font-extrabold text-xl text-on-surface mb-1" style="font-family:Manrope,sans-serif;">Supprimer la pharmacie ?</h2>
      <p class="text-on-surface-variant text-sm" id="modalPharmName"></p>
    </div>
    <div id="modalOrderWarning" class="hidden mb-5 flex items-center gap-3 bg-error-container text-on-error-container px-4 py-3 rounded-xl text-sm font-semibold">
      <span class="material-symbols-outlined text-lg flex-shrink-0">warning</span>
      <span id="modalOrdersText"></span>
    </div>
    <p class="text-xs text-on-surface-variant text-center mb-6">Cette action est <strong>irréversible</strong>. Les assignations liées seront aussi supprimées.</p>
    <form method="POST" action="admin_pharmacies.php">
      <input type="hidden" name="delete_nif" id="deleteNifInput" value=""/>
      <div class="flex gap-3">
        <button type="button" onclick="closeDeleteModal()"
          class="flex-1 px-5 py-3 border border-outline-variant/40 rounded-xl font-semibold text-sm text-on-surface hover:bg-surface-container transition-colors">
          Annuler
        </button>
        <button type="submit"
          class="flex-1 flex items-center justify-center gap-2 px-5 py-3 bg-error text-white rounded-xl font-bold text-sm hover:opacity-90 active:scale-95 transition-all">
          <span class="material-symbols-outlined text-lg">delete</span>Supprimer
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Orders Detail Modal ─────────────────────────────────────────────────── -->
<div id="ordersModal" class="fixed inset-0 z-[100] items-center justify-center bg-black/50 backdrop-blur-sm p-4">
  <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full max-h-[80vh] flex flex-col">
    <!-- Modal header -->
    <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/20 flex-shrink-0">
      <div>
        <h2 class="font-extrabold text-lg text-on-surface" style="font-family:Manrope,sans-serif;">
          Commandes — Pharmacie <span id="ordersNifLabel" class="text-primary"></span>
        </h2>
        <p class="text-xs text-on-surface-variant mt-0.5" id="ordersTotalLabel"></p>
      </div>
      <button onclick="closeOrdersModal()" class="p-2 hover:bg-surface-container rounded-full transition-colors">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <!-- Modal body -->
    <div class="overflow-y-auto flex-1 p-6" id="ordersListContainer">
      <!-- Populated by JS -->
    </div>
    <!-- Modal footer -->
    <div class="px-6 py-4 border-t border-outline-variant/20 flex-shrink-0">
      <a href="admin_orders.php" class="text-xs text-primary font-semibold hover:underline flex items-center gap-1">
        <span class="material-symbols-outlined text-sm">open_in_new</span>Voir toutes les commandes
      </a>
    </div>
  </div>
</div>

<script>
// ── Delete modal ──────────────────────────────────────────────────────────────
function confirmDelete(nif, name, orderCount) {
  document.getElementById("deleteNifInput").value = nif;
  document.getElementById("modalPharmName").textContent = name + "  (NIF #" + nif + ")";
  const warn = document.getElementById("modalOrderWarning");
  if (orderCount > 0) {
    warn.classList.remove("hidden");
    warn.classList.add("flex");
    document.getElementById("modalOrdersText").textContent =
      "Cette pharmacie a " + orderCount + " commande(s) assignée(s). Elles seront désassignées.";
  } else {
    warn.classList.add("hidden");
    warn.classList.remove("flex");
  }
  document.getElementById("deleteModal").classList.add("open");
}
function closeDeleteModal() {
  document.getElementById("deleteModal").classList.remove("open");
}
document.getElementById("deleteModal").addEventListener("click", function(e){
  if(e.target===this) closeDeleteModal();
});

// ── Orders modal ──────────────────────────────────────────────────────────────
function openOrders(nif, orders) {
  document.getElementById("ordersNifLabel").textContent = "#" + nif;
  document.getElementById("ordersTotalLabel").textContent = orders.length + " commande(s) liée(s)";

  const container = document.getElementById("ordersListContainer");

  if (orders.length === 0) {
    container.innerHTML = '<p class="text-sm text-on-surface-variant text-center py-8">Aucune commande.</p>';
  } else {
    const statusLabel = (s) => {
      if (s === null || s === undefined) return '<span class="status-pill bg-surface-container text-on-surface-variant">Inconnu</span>';
      return parseInt(s) === 1
        ? '<span class="status-pill bg-green-100 text-green-700">✓ Livré</span>'
        : '<span class="status-pill bg-secondary-container text-on-secondary-container">⏳ En attente</span>';
    };

    container.innerHTML = orders.map(o => `
      <div class="flex items-center justify-between py-3 border-b border-outline-variant/10 last:border-0">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 ${parseInt(o.IsUrgen)===1 ? 'bg-error-container' : 'bg-primary/10'}">
            <span class="material-symbols-outlined text-base ${parseInt(o.IsUrgen)===1 ? 'text-error' : 'text-primary'}"
              style="font-variation-settings:'FILL' 1;">
              ${parseInt(o.IsUrgen)===1 ? 'priority_high' : 'package_2'}
            </span>
          </div>
          <div>
            <p class="text-sm font-bold text-on-surface">${o.Tracking || o.order_id}</p>
            <p class="text-xs text-on-surface-variant">${o.Date || '—'} · Livreur: ${o.deliveryperson_id || '—'}</p>
          </div>
        </div>
        <div class="text-right flex flex-col items-end gap-1">
          ${statusLabel(o.Status)}
          ${parseInt(o.IsUrgen)===1 ? '<span class="status-pill bg-error-container text-error">🚨 Urgent</span>' : ''}
        </div>
      </div>
    `).join("");
  }

  document.getElementById("ordersModal").classList.add("open");
}
function closeOrdersModal() {
  document.getElementById("ordersModal").classList.remove("open");
}
document.getElementById("ordersModal").addEventListener("click", function(e){
  if(e.target===this) closeOrdersModal();
});
</script>
</body>
</html>
