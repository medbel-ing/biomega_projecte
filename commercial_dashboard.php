<?php
session_start();

if (!isset($_SESSION["table"]) || $_SESSION["table"] !== "commercialservice") {
    header("Location: login.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "biomegadb");
if (!$conn) die("DB connection failed: " . mysqli_connect_error());

$firstname = htmlspecialchars($_SESSION["firstname"] ?? "Commercial");
$lastname  = htmlspecialchars($_SESSION["lastname"]  ?? "");
$success   = "";
$error     = "";

// ── Handle: ACHAT (mark order_item_link rows as purchased) ────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "achat") {
    $pharmacy_id = (int)($_POST["pharmacy_id"] ?? 0);
    $ids         = $_POST["link_ids"] ?? []; // array of order_item_link IDs

    if (empty($ids)) {
        $error = "Aucun article sélectionné pour l'achat.";
    } else {
        // Add an "achat" column if not exists — safe to run repeatedly
        mysqli_query($conn, "ALTER TABLE order_item_link ADD COLUMN IF NOT EXISTS `achat` tinyint(1) NOT NULL DEFAULT 0");

        $placeholders = implode(",", array_fill(0, count($ids), "?"));
        $stmt = mysqli_prepare($conn, "UPDATE order_item_link SET achat = 1 WHERE ID IN ($placeholders)");
        $types = str_repeat("i", count($ids));
        mysqli_stmt_bind_param($stmt, $types, ...$ids);
        if (mysqli_stmt_execute($stmt)) {
            $success = "Achat confirmé pour " . count($ids) . " article(s).";
        } else {
            $error = "Erreur lors de la confirmation : " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// ── Check if achat column exists ──────────────────────────────────────────────
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM order_item_link LIKE 'achat'");
$has_achat  = mysqli_num_rows($col_check) > 0;
if (!$has_achat) {
    mysqli_query($conn, "ALTER TABLE order_item_link ADD COLUMN `achat` tinyint(1) NOT NULL DEFAULT 0");
}

// ── Fetch all orders grouped by pharmacy ─────────────────────────────────────
// Each row = one order_item_link entry with pharmacy + item info

$rows_result = mysqli_query($conn, "
    SELECT
        oil.ID          AS link_id,
        oil.pharmacy_id,
        oil.orderitem_id,
        oil.contiti,
        oil.achat,
        oi.Name         AS item_name,
        oi.contiti      AS stock_contiti,
        p.FirstName     AS pharm_first,
        p.LastName      AS pharm_last,
        p.PhoneNumber   AS pharm_phone,
        p.Location      AS pharm_location,
        p.NIF           AS pharm_nif
    FROM order_item_link oil
    LEFT JOIN orderitem oi ON oi.ID  = oil.orderitem_id
    LEFT JOIN pharmacy  p  ON p.NIF  = oil.pharmacy_id
    ORDER BY oil.pharmacy_id ASC, oil.ID ASC
");

// Group by pharmacy_id
$pharmacies_orders = []; // [ pharmacy_id => [ info + items[] ] ]
while ($row = mysqli_fetch_assoc($rows_result)) {
    $pid = $row["pharmacy_id"];
    if (!isset($pharmacies_orders[$pid])) {
        $pharmacies_orders[$pid] = [
            "pharmacy_id"    => $pid,
            "pharm_nif"      => $row["pharm_nif"],
            "pharm_first"    => $row["pharm_first"],
            "pharm_last"     => $row["pharm_last"],
            "pharm_phone"    => $row["pharm_phone"],
            "pharm_location" => $row["pharm_location"],
            "items"          => [],
        ];
    }
    $pharmacies_orders[$pid]["items"][] = [
        "link_id"       => $row["link_id"],
        "orderitem_id"  => $row["orderitem_id"],
        "item_name"     => $row["item_name"] ?? "—",
        "contiti"       => (int)$row["contiti"],
        "stock_contiti" => (int)$row["stock_contiti"],
        "achat"         => (int)$row["achat"],
    ];
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$total_pharmacies = count($pharmacies_orders);
$total_items      = 0;
$total_achat      = 0;
foreach ($pharmacies_orders as $po) {
    $total_items += count($po["items"]);
    $total_achat += count(array_filter($po["items"], fn($i) => $i["achat"] === 1));
}
$total_pending = $total_items - $total_achat;

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Service Commercial — Bio Mega Pharme</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
  tailwind.config={darkMode:"class",theme:{extend:{colors:{
    "primary":"#005ea4","primary-container":"#0077ce","on-primary":"#ffffff",
    "tertiary":"#186a22","tertiary-container":"#1e8a2a","on-tertiary":"#ffffff",
    "secondary":"#4c616c","secondary-container":"#cfe6f2","on-secondary-container":"#526772",
    "surface":"#f8f9fa","surface-container-lowest":"#ffffff","surface-container-low":"#f3f4f5",
    "surface-container":"#edeeef","surface-container-high":"#e7e8e9",
    "on-surface":"#191c1d","on-surface-variant":"#404752","outline":"#707783",
    "outline-variant":"#c0c7d4","error":"#ba1a1a","error-container":"#ffdad6","on-error-container":"#93000a",
  },fontFamily:{"headline":["Manrope"],"body":["Inter"]}}}}
</script>
<style>
  .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}
  body{font-family:'Inter',sans-serif;}
  @keyframes fadeUp{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}
  .fade-in{animation:fadeUp .4s ease both;}
  .modal-bg{display:none;position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.5);
    backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:1rem;}
  .modal-bg.open{display:flex;}
  .pill{display:inline-flex;align-items:center;gap:3px;font-size:10px;font-weight:700;
    padding:2px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:.04em;}
  /* tab */
  .tab-btn{padding:.5rem 1.5rem;border-radius:.75rem;font-weight:600;font-size:.875rem;transition:all .2s;cursor:pointer;}
  .tab-btn.active{background:#005ea4;color:#fff;}
  .tab-btn:not(.active){color:#404752;}
  .tab-btn:not(.active):hover{background:#edeeef;}
  /* accordion */
  .pharm-body{display:none;}
  .pharm-body.open{display:block;}
  .chevron{transition:transform .2s;}
  .chevron.open{transform:rotate(180deg);}
</style>
</head>
<body class="bg-surface text-on-surface min-h-screen">

<!-- ── Header ────────────────────────────────────────────────────────────── -->
<header class="bg-white/90 backdrop-blur-lg shadow-sm sticky top-0 z-50 flex items-center justify-between px-6 py-3">
  <div class="flex items-center gap-3">
    <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center">
      <span class="material-symbols-outlined text-white text-xl" style="font-variation-settings:'FILL' 1;">storefront</span>
    </div>
    <div>
      <span class="text-lg font-extrabold tracking-tighter text-blue-900" style="font-family:Manrope,sans-serif;">Bio Mega Pharme</span>
      <span class="hidden sm:inline text-xs text-on-surface-variant ml-2">· Service Commercial</span>
    </div>
  </div>
  <div class="flex items-center gap-3">
    <div class="hidden sm:flex items-center gap-2 bg-surface-container px-3 py-1.5 rounded-full">
      <div class="w-7 h-7 rounded-full bg-gradient-to-br from-primary to-primary-container text-white flex items-center justify-center text-xs font-bold">
        <?php echo strtoupper(mb_substr($firstname,0,1)); ?>
      </div>
      <span class="text-sm font-semibold"><?php echo $firstname." ".$lastname; ?></span>
    </div>
    <a href="logout.php" class="p-2 hover:bg-slate-50 rounded-full" title="Déconnexion">
      <span class="material-symbols-outlined text-slate-600">logout</span>
    </a>
  </div>
</header>

<div class="flex min-h-screen">

  <!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
  <aside class="bg-slate-50 w-60 border-r border-slate-200 p-4 fixed left-0 top-[57px] h-screen hidden lg:flex flex-col gap-1">
    <div class="mb-4 px-2">
      <p class="font-bold text-blue-900 text-sm" style="font-family:Manrope,sans-serif;">Commercial Portal</p>
      <p class="text-xs text-on-surface-variant"><?php echo $firstname." ".$lastname; ?></p>
    </div>
    <a onclick="switchTab('orders')" class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer hover:translate-x-1 transition-transform bg-blue-50 text-blue-700 font-bold" id="side-orders">
      <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">receipt_long</span><span class="text-sm">Commandes</span>
    </a>
    <a onclick="switchTab('achats')" class="flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer hover:translate-x-1 transition-transform text-slate-600 hover:bg-slate-100" id="side-achats">
      <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1;">shopping_cart</span><span class="text-sm">Achats</span>
    </a>
    <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-500 hover:bg-red-50 mt-auto">
      <span class="material-symbols-outlined">logout</span><span class="text-sm font-bold">Déconnexion</span>
    </a>
  </aside>

  <!-- ── Main ─────────────────────────────────────────────────────────────── -->
  <main class="flex-1 lg:ml-60 p-4 lg:p-8 space-y-6">

    <!-- Flash -->
    <?php if (!empty($success)): ?>
    <div class="fade-in flex items-center gap-3 bg-green-50 text-green-800 border border-green-200 px-5 py-3.5 rounded-2xl text-sm font-semibold">
      <span class="material-symbols-outlined text-green-600" style="font-variation-settings:'FILL' 1;">check_circle</span>
      <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="fade-in flex items-center gap-3 bg-error-container text-on-error-container border border-error/20 px-5 py-3.5 rounded-2xl text-sm font-semibold">
      <span class="material-symbols-outlined">error</span>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="fade-in grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/15 shadow-sm p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-primary" style="font-variation-settings:'FILL' 1;">local_pharmacy</span>
        </div>
        <div>
          <p class="text-xs text-on-surface-variant">Pharmacies</p>
          <p class="text-2xl font-extrabold text-primary" style="font-family:Manrope,sans-serif;"><?php echo $total_pharmacies; ?></p>
        </div>
      </div>
      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/15 shadow-sm p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-primary" style="font-variation-settings:'FILL' 1;">medication</span>
        </div>
        <div>
          <p class="text-xs text-on-surface-variant">Total articles</p>
          <p class="text-2xl font-extrabold text-on-surface" style="font-family:Manrope,sans-serif;"><?php echo $total_items; ?></p>
        </div>
      </div>
      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/15 shadow-sm p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-secondary-container flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-on-secondary-container" style="font-variation-settings:'FILL' 1;">pending_actions</span>
        </div>
        <div>
          <p class="text-xs text-on-surface-variant">En attente</p>
          <p class="text-2xl font-extrabold text-on-surface" style="font-family:Manrope,sans-serif;"><?php echo $total_pending; ?></p>
        </div>
      </div>
      <div class="bg-surface-container-lowest rounded-2xl border border-green-100 shadow-sm p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center flex-shrink-0">
          <span class="material-symbols-outlined text-green-700" style="font-variation-settings:'FILL' 1;">shopping_cart_checkout</span>
        </div>
        <div>
          <p class="text-xs text-on-surface-variant">Achetés</p>
          <p class="text-2xl font-extrabold text-green-700" style="font-family:Manrope,sans-serif;"><?php echo $total_achat; ?></p>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="fade-in flex gap-2 bg-surface-container-lowest rounded-2xl p-1.5 border border-outline-variant/15 w-fit shadow-sm">
      <button class="tab-btn active" id="tab-orders-btn" onclick="switchTab('orders')">
        <span class="flex items-center gap-2">
          <span class="material-symbols-outlined text-base" style="font-variation-settings:'FILL' 1;">receipt_long</span>
          Commandes
          <?php if ($total_pending > 0): ?>
          <span class="bg-primary text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?php echo $total_pending; ?></span>
          <?php endif; ?>
        </span>
      </button>
      <button class="tab-btn" id="tab-achats-btn" onclick="switchTab('achats')">
        <span class="flex items-center gap-2">
          <span class="material-symbols-outlined text-base" style="font-variation-settings:'FILL' 1;">shopping_cart</span>
          Achats
          <?php if ($total_achat > 0): ?>
          <span class="bg-green-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?php echo $total_achat; ?></span>
          <?php endif; ?>
        </span>
      </button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 1 — COMMANDES                                                -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div id="tab-orders" class="fade-in space-y-4">

      <?php if (empty($pharmacies_orders)): ?>
      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/15 shadow-sm flex flex-col items-center py-20 text-center">
        <span class="material-symbols-outlined text-6xl text-outline/30 mb-4" style="font-variation-settings:'FILL' 1;">receipt_long</span>
        <h3 class="font-extrabold text-xl text-on-surface mb-2" style="font-family:Manrope,sans-serif;">Aucune commande</h3>
        <p class="text-sm text-on-surface-variant">Aucune pharmacie n'a encore passé de commande.</p>
      </div>

      <?php else: foreach ($pharmacies_orders as $pid => $po):
        $all_done  = count(array_filter($po["items"], fn($i) => $i["achat"] === 1)) === count($po["items"]);
        $some_done = !$all_done && count(array_filter($po["items"], fn($i) => $i["achat"] === 1)) > 0;
        $done_count= count(array_filter($po["items"], fn($i) => $i["achat"] === 1));
      ?>

      <!-- Pharmacy accordion card -->
      <div class="bg-surface-container-lowest rounded-2xl border <?php echo $all_done ? 'border-green-200' : 'border-outline-variant/15'; ?> shadow-sm overflow-hidden fade-in">

        <!-- Card header (clickable accordion) -->
        <button type="button" onclick="toggleAccordion('pharm-<?php echo $pid; ?>')"
          class="w-full flex items-center justify-between px-5 py-4 hover:bg-surface-container-low transition-colors text-left">
          <div class="flex items-center gap-4">
            <!-- Avatar -->
            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary to-primary-container text-white flex items-center justify-center font-extrabold text-lg flex-shrink-0">
              <?php echo strtoupper(mb_substr($po["pharm_first"] ?? "?", 0, 1)); ?>
            </div>
            <div>
              <p class="font-extrabold text-on-surface text-sm" style="font-family:Manrope,sans-serif;">
                <?php echo htmlspecialchars($po["pharm_first"]." ".$po["pharm_last"]); ?>
                <span class="text-outline font-normal ml-1">· NIF #<?php echo htmlspecialchars((string)$po["pharm_nif"]); ?></span>
              </p>
              <div class="flex items-center gap-3 mt-0.5 flex-wrap">
                <span class="text-xs text-on-surface-variant flex items-center gap-1">
                  <span class="material-symbols-outlined text-xs">phone</span>
                  <?php echo htmlspecialchars($po["pharm_phone"]); ?>
                </span>
                <span class="text-xs text-on-surface-variant flex items-center gap-1">
                  <span class="material-symbols-outlined text-xs">location_on</span>
                  <?php echo htmlspecialchars($po["pharm_location"]); ?>
                </span>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-3 flex-shrink-0 ml-4">
            <!-- Status pill -->
            <?php if ($all_done): ?>
            <span class="pill bg-green-100 text-green-700">✓ Acheté</span>
            <?php elseif ($some_done): ?>
            <span class="pill bg-secondary-container text-on-secondary-container">⏳ Partiel</span>
            <?php else: ?>
            <span class="pill bg-yellow-100 text-yellow-700">⚠ En attente</span>
            <?php endif; ?>
            <!-- Item count -->
            <span class="text-xs font-bold text-on-surface-variant bg-surface-container px-2.5 py-1 rounded-full">
              <?php echo count($po["items"]); ?> article<?php echo count($po["items"])>1?"s":""; ?>
            </span>
            <!-- Achat button (opens modal) -->
            <?php if (!$all_done): ?>
            <button type="button"
              onclick="event.stopPropagation(); openAchatModal(<?php echo $pid; ?>, '<?php echo htmlspecialchars(addslashes($po["pharm_first"]." ".$po["pharm_last"])); ?>', <?php echo htmlspecialchars(json_encode($po["items"])); ?>)"
              class="flex items-center gap-1.5 bg-primary text-white px-4 py-2 rounded-xl text-xs font-bold hover:opacity-90 active:scale-95 transition-all">
              <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1;">shopping_cart</span>
              Achats
            </button>
            <?php else: ?>
            <div class="flex items-center gap-1.5 bg-green-100 text-green-700 px-4 py-2 rounded-xl text-xs font-bold">
              <span class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1;">check_circle</span>
              Tout acheté
            </div>
            <?php endif; ?>
            <!-- Chevron -->
            <span class="material-symbols-outlined text-outline chevron" id="chev-<?php echo $pid; ?>">expand_more</span>
          </div>
        </button>

        <!-- Accordion body — items table -->
        <div class="pharm-body" id="pharm-<?php echo $pid; ?>">
          <div class="border-t border-outline-variant/15 overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-surface-container-low">
                <tr>
                  <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">#</th>
                  <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Article</th>
                  <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Stock dispo</th>
                  <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Qté commandée</th>
                  <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Statut achat</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-outline-variant/10">
                <?php foreach ($po["items"] as $idx => $item): ?>
                <tr class="hover:bg-surface-container-low/50 transition-colors">
                  <td class="px-5 py-3 text-xs text-outline"><?php echo $idx+1; ?></td>
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-2">
                      <span class="material-symbols-outlined text-primary text-base" style="font-variation-settings:'FILL' 1;">medication</span>
                      <span class="font-semibold text-on-surface"><?php echo htmlspecialchars($item["item_name"]); ?></span>
                    </div>
                  </td>
                  <td class="px-5 py-3">
                    <span class="text-xs font-bold text-on-surface-variant bg-surface-container px-2.5 py-1 rounded-full">
                      <?php echo $item["stock_contiti"]; ?>
                    </span>
                  </td>
                  <td class="px-5 py-3">
                    <span class="text-sm font-extrabold text-primary"><?php echo $item["contiti"]; ?></span>
                  </td>
                  <td class="px-5 py-3">
                    <?php if ($item["achat"] === 1): ?>
                    <span class="pill bg-green-100 text-green-700">
                      <span class="material-symbols-outlined text-xs" style="font-variation-settings:'FILL' 1;">check_circle</span>
                      Acheté
                    </span>
                    <?php else: ?>
                    <span class="pill bg-yellow-100 text-yellow-700">⏳ En attente</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Footer with achat progress -->
          <div class="px-5 py-3 border-t border-outline-variant/15 bg-surface-container-low flex items-center justify-between text-xs text-on-surface-variant">
            <span><?php echo $done_count; ?>/<?php echo count($po["items"]); ?> article<?php echo count($po["items"])>1?"s":""; ?> acheté<?php echo count($po["items"])>1?"s":""; ?></span>
            <!-- Progress bar -->
            <div class="flex items-center gap-2">
              <div class="w-24 h-1.5 bg-outline-variant/30 rounded-full overflow-hidden">
                <div class="h-full bg-green-500 rounded-full transition-all" style="width:<?php echo count($po["items"]) > 0 ? round($done_count/count($po["items"])*100) : 0; ?>%"></div>
              </div>
              <span><?php echo count($po["items"]) > 0 ? round($done_count/count($po["items"])*100) : 0; ?>%</span>
            </div>
          </div>
        </div>

      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!-- TAB 2 — ACHATS (purchased items only)                            -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div id="tab-achats" class="hidden fade-in space-y-4">

      <?php
        // Filter only items where achat = 1
        $purchased = [];
        foreach ($pharmacies_orders as $pid => $po) {
            $bought = array_filter($po["items"], fn($i) => $i["achat"] === 1);
            if (!empty($bought)) {
                $purchased[$pid] = array_merge($po, ["items" => array_values($bought)]);
            }
        }
      ?>

      <?php if (empty($purchased)): ?>
      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/15 shadow-sm flex flex-col items-center py-20 text-center">
        <span class="material-symbols-outlined text-6xl text-outline/30 mb-4" style="font-variation-settings:'FILL' 1;">shopping_cart</span>
        <h3 class="font-extrabold text-xl text-on-surface mb-2" style="font-family:Manrope,sans-serif;">Aucun achat confirmé</h3>
        <p class="text-sm text-on-surface-variant">Les articles confirmés apparaîtront ici.</p>
        <button onclick="switchTab('orders')" class="mt-4 inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-bold hover:opacity-90">
          <span class="material-symbols-outlined text-lg">receipt_long</span>Voir les commandes
        </button>
      </div>

      <?php else: ?>

      <!-- Summary card -->
      <div class="bg-green-50 border border-green-200 rounded-2xl px-5 py-4 flex items-center gap-4">
        <span class="material-symbols-outlined text-green-600 text-3xl" style="font-variation-settings:'FILL' 1;">shopping_cart_checkout</span>
        <div>
          <p class="font-extrabold text-green-800" style="font-family:Manrope,sans-serif;"><?php echo $total_achat; ?> article<?php echo $total_achat>1?"s":""; ?> achetés</p>
          <p class="text-xs text-green-700">sur <?php echo count($purchased); ?> pharmacie<?php echo count($purchased)>1?"s":""; ?></p>
        </div>
      </div>

      <?php foreach ($purchased as $pid => $po): ?>
      <div class="bg-surface-container-lowest rounded-2xl border border-green-200/60 shadow-sm overflow-hidden">
        <!-- Header -->
        <div class="flex items-center gap-4 px-5 py-4 border-b border-outline-variant/10">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-container text-white flex items-center justify-center font-bold flex-shrink-0">
            <?php echo strtoupper(mb_substr($po["pharm_first"] ?? "?", 0, 1)); ?>
          </div>
          <div>
            <p class="font-bold text-on-surface text-sm"><?php echo htmlspecialchars($po["pharm_first"]." ".$po["pharm_last"]); ?></p>
            <p class="text-xs text-on-surface-variant"><?php echo htmlspecialchars($po["pharm_location"]); ?></p>
          </div>
          <span class="ml-auto pill bg-green-100 text-green-700">
            <span class="material-symbols-outlined text-xs" style="font-variation-settings:'FILL' 1;">check_circle</span>
            <?php echo count($po["items"]); ?> acheté<?php echo count($po["items"])>1?"s":""; ?>
          </span>
        </div>
        <!-- Items list -->
        <div class="divide-y divide-outline-variant/10">
          <?php foreach ($po["items"] as $item): ?>
          <div class="flex items-center justify-between px-5 py-3">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-green-600 text-base" style="font-variation-settings:'FILL' 1;">medication</span>
              <span class="text-sm font-semibold text-on-surface"><?php echo htmlspecialchars($item["item_name"]); ?></span>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-sm font-extrabold text-primary">Qté: <?php echo $item["contiti"]; ?></span>
              <span class="pill bg-green-100 text-green-700">✓ Acheté</span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </main>
</div>

<!-- ── Achat Modal ─────────────────────────────────────────────────────────── -->
<div id="achatModal" class="modal-bg">
  <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[80vh] flex flex-col">
    <div class="flex items-center justify-between px-6 py-4 border-b border-outline-variant/10 flex-shrink-0">
      <div>
        <h2 class="font-extrabold text-xl text-on-surface" style="font-family:Manrope,sans-serif;">Confirmer les Achats</h2>
        <p class="text-xs text-on-surface-variant mt-0.5">Pharmacie : <strong id="achat-pharm-name"></strong></p>
      </div>
      <button onclick="closeAchatModal()" class="p-2 hover:bg-surface-container rounded-full transition-colors">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>

    <!-- Items checkboxes -->
    <div class="overflow-y-auto flex-1 p-6 space-y-2" id="achatItemsList"></div>

    <!-- Footer -->
    <div class="px-6 py-4 border-t border-outline-variant/10 flex-shrink-0">
      <form method="POST" action="commercial_dashboard.php" id="achatForm">
        <input type="hidden" name="action" value="achat"/>
        <input type="hidden" name="pharmacy_id" id="achatPharmacyId"/>
        <div id="achatHiddenIds"></div>
        <div class="flex gap-3">
          <button type="button" onclick="closeAchatModal()"
            class="flex-1 py-3 border border-outline-variant/40 rounded-xl font-semibold text-sm hover:bg-surface-container transition-colors">
            Annuler
          </button>
          <button type="button" onclick="submitAchat()"
            class="flex-1 flex items-center justify-center gap-2 bg-primary text-white py-3 rounded-xl font-bold text-sm hover:opacity-90 active:scale-95 transition-all">
            <span class="material-symbols-outlined text-lg" style="font-variation-settings:'FILL' 1;">shopping_cart_checkout</span>
            Confirmer l'achat
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ── Tab switching ─────────────────────────────────────────────────────────────
function switchTab(tab) {
  document.getElementById('tab-orders').classList.toggle('hidden', tab !== 'orders');
  document.getElementById('tab-achats').classList.toggle('hidden', tab !== 'achats');
  document.getElementById('tab-orders-btn').classList.toggle('active', tab === 'orders');
  document.getElementById('tab-achats-btn').classList.toggle('active', tab === 'achats');

  // Sidebar highlight
  ['orders','achats'].forEach(t => {
    const el = document.getElementById('side-' + t);
    if (!el) return;
    if (t === tab) {
      el.classList.add('bg-blue-50','text-blue-700','font-bold');
      el.classList.remove('text-slate-600','hover:bg-slate-100');
    } else {
      el.classList.remove('bg-blue-50','text-blue-700','font-bold');
      el.classList.add('text-slate-600','hover:bg-slate-100');
    }
  });
}

// ── Accordion ─────────────────────────────────────────────────────────────────
function toggleAccordion(id) {
  const body = document.getElementById(id);
  const chev = document.getElementById('chev-' + id.replace('pharm-',''));
  body.classList.toggle('open');
  chev.classList.toggle('open');
}

// ── Achat modal ───────────────────────────────────────────────────────────────
let currentItems = [];

function openAchatModal(pharmacyId, pharmName, items) {
  document.getElementById('achat-pharm-name').textContent = pharmName;
  document.getElementById('achatPharmacyId').value = pharmacyId;
  currentItems = items;

  const list = document.getElementById('achatItemsList');
  const pending = items.filter(i => i.achat == 0);

  if (pending.length === 0) {
    list.innerHTML = '<p class="text-sm text-center text-on-surface-variant py-6">Tous les articles ont déjà été achetés.</p>';
  } else {
    list.innerHTML = `
      <div class="flex items-center justify-between mb-3">
        <p class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">Sélectionner les articles à acheter</p>
        <button type="button" onclick="selectAllAchat()" class="text-xs text-primary font-bold hover:underline">Tout sélectionner</button>
      </div>` +
      pending.map(item => `
        <label class="flex items-center gap-3 p-3 rounded-xl border border-outline-variant/20 cursor-pointer hover:border-primary/40 hover:bg-primary/5 transition-all has-[:checked]:border-primary has-[:checked]:bg-primary/8">
          <input type="checkbox" class="achat-checkbox accent-primary" value="${item.link_id}" checked/>
          <div class="flex-1">
            <p class="font-semibold text-sm text-on-surface">${item.item_name}</p>
            <p class="text-xs text-on-surface-variant">Qté commandée : <strong class="text-primary">${item.contiti}</strong></p>
          </div>
          <span class="text-xs bg-surface-container text-on-surface-variant px-2 py-1 rounded-full font-bold">
            Stock: ${item.stock_contiti}
          </span>
        </label>`).join('');
  }

  document.getElementById('achatModal').classList.add('open');
}

function selectAllAchat() {
  document.querySelectorAll('.achat-checkbox').forEach(cb => cb.checked = true);
}

function submitAchat() {
  const checked = Array.from(document.querySelectorAll('.achat-checkbox:checked'));
  if (checked.length === 0) {
    alert('Veuillez sélectionner au moins un article.');
    return;
  }
  // Build hidden inputs
  const container = document.getElementById('achatHiddenIds');
  container.innerHTML = checked.map(cb =>
    `<input type="hidden" name="link_ids[]" value="${cb.value}"/>`
  ).join('');
  document.getElementById('achatForm').submit();
}

function closeAchatModal() {
  document.getElementById('achatModal').classList.remove('open');
}
document.getElementById('achatModal').addEventListener('click', function(e){
  if(e.target===this) closeAchatModal();
});
</script>
</body>
</html>