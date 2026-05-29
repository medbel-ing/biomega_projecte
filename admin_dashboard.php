<?php
session_start();

// ── Guard: only admin can access ──────────────────────────────────────────────
if (!isset($_SESSION["table"]) || $_SESSION["table"] !== "admin") {
    header("Location: login.php");
    exit();
}

$firstname = htmlspecialchars($_SESSION["firstname"] ?? "Admin");
$lastname  = htmlspecialchars($_SESSION["lastname"]  ?? "");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>TronSport Medicamon | Admin Dashboard</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        colors: {
          "primary-fixed-dim": "#a2c9ff",
          "tertiary": "#186a22",
          "secondary": "#4c616c",
          "on-primary": "#ffffff",
          "background": "#f8f9fa",
          "on-primary-fixed-variant": "#004881",
          "inverse-surface": "#2e3132",
          "surface-tint": "#0060a8",
          "inverse-on-surface": "#f0f1f2",
          "on-error": "#ffffff",
          "secondary-container": "#cfe6f2",
          "on-primary-container": "#fdfcff",
          "on-secondary-container": "#526772",
          "surface-container-lowest": "#ffffff",
          "on-primary-fixed": "#001c38",
          "tertiary-container": "#358438",
          "surface-container-high": "#e7e8e9",
          "on-tertiary": "#ffffff",
          "primary-container": "#0077ce",
          "surface-bright": "#f8f9fa",
          "surface-container-highest": "#e1e3e4",
          "on-background": "#191c1d",
          "secondary-fixed": "#cfe6f2",
          "inverse-primary": "#a2c9ff",
          "surface-dim": "#d9dadb",
          "surface-variant": "#e1e3e4",
          "on-secondary": "#ffffff",
          "error": "#ba1a1a",
          "outline-variant": "#c0c7d4",
          "surface": "#f8f9fa",
          "on-surface": "#191c1d",
          "error-container": "#ffdad6",
          "primary-fixed": "#d3e4ff",
          "surface-container": "#edeeef",
          "on-error-container": "#93000a",
          "on-secondary-fixed": "#071e27",
          "primary": "#005ea4",
          "outline": "#707783",
          "surface-container-low": "#f3f4f5",
          "on-surface-variant": "#404752"
        },
        fontFamily: {
          "headline": ["Manrope"],
          "body": ["Inter"],
          "label": ["Inter"]
        },
        borderRadius: {"DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem"},
      },
    },
  }
</script>
<style>
  .material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  }
  body { font-family: 'Inter', sans-serif; }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .fade-in { animation: fadeIn 0.4s ease both; }
  .fade-in-1 { animation-delay: 0.05s; }
  .fade-in-2 { animation-delay: 0.10s; }
  .fade-in-3 { animation-delay: 0.15s; }
  .fade-in-4 { animation-delay: 0.20s; }
  .fade-in-5 { animation-delay: 0.25s; }
</style>
</head>
<body class="bg-surface text-on-surface font-body">

<!-- ── Top Navigation ─────────────────────────────────────────────────── -->
<header class="bg-white/80 backdrop-blur-lg shadow-sm shadow-blue-500/5 sticky top-0 z-50 flex justify-between items-center px-6 py-3 w-full">
  <div class="flex items-center gap-8">
    <span class="text-xl font-extrabold tracking-tighter text-blue-800 font-headline">TronSport Medicamon</span>
    <nav class="hidden md:flex items-center gap-6">
      <a class="text-blue-700 font-bold border-b-2 border-blue-600 px-1 py-1 transition-colors" href="admin_dashboard.php">Dashboard</a>
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_orders.php">Orders</a>
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_inventory.php">Inventory</a>
      <a class="text-slate-500 font-medium hover:text-blue-600 transition-colors" href="admin_reports.php">Reports</a>
    </nav>
  </div>
  <div class="flex items-center gap-4">
    <div class="relative hidden sm:block">
      <input class="bg-surface-container-low border-none rounded-full px-4 py-2 text-sm w-64 focus:ring-2 focus:ring-primary/20" placeholder="Search tracking ID..." type="text"/>
      <span class="material-symbols-outlined absolute right-3 top-2 text-on-surface-variant text-lg">search</span>
    </div>
    <button class="p-2 hover:bg-slate-50 transition-colors rounded-full active:scale-95">
      <span class="material-symbols-outlined text-slate-600">notifications</span>
    </button>
    <a href="logout.php" class="p-2 hover:bg-slate-50 transition-colors rounded-full active:scale-95 flex items-center gap-1 text-sm font-semibold text-slate-600" title="Logout">
      <span class="material-symbols-outlined text-slate-600">logout</span>
    </a>
  </div>
</header>

<div class="flex min-h-screen">

  <!-- ── Sidebar ──────────────────────────────────────────────────────── -->
  <aside class="bg-slate-50 h-screen w-64 border-r border-slate-200 flex flex-col gap-2 p-4 fixed left-0 top-[60px] hidden lg:flex">
    <div class="mb-4 px-2">
      <h3 class="font-headline font-bold text-blue-900">Admin Portal</h3>
      <p class="text-xs text-on-surface-variant"><?php echo $firstname . " " . $lastname; ?> • Operational</p>
    </div>
    <nav class="flex-1 flex flex-col gap-1">
      <a class="bg-blue-50 text-blue-700 rounded-lg font-bold flex items-center gap-3 px-3 py-2.5 transition-transform hover:translate-x-1" href="admin_dashboard.php">
        <span class="material-symbols-outlined">dashboard</span>
        <span class="text-sm">Dashboard</span>
      </a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_pharmacies.php">
        <span class="material-symbols-outlined">local_pharmacy</span>
        <span class="text-sm">Pharmacies</span>
      </a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_employees.php">
        <span class="material-symbols-outlined">badge</span>
        <span class="text-sm">Employees</span>
      </a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_orders.php">
        <span class="material-symbols-outlined">package_2</span>
        <span class="text-sm">Orders</span>
      </a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_payments.php">
        <span class="material-symbols-outlined">payments</span>
        <span class="text-sm">Payments</span>
      </a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_tracking.php">
        <span class="material-symbols-outlined">local_shipping</span>
        <span class="text-sm">Tracking</span>
      </a>
      <a class="text-slate-600 hover:bg-slate-100 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1" href="admin_settings.php">
        <span class="material-symbols-outlined">settings</span>
        <span class="text-sm">Settings</span>
      </a>
      <a class="text-red-500 hover:bg-red-50 flex items-center gap-3 px-3 py-2.5 rounded-lg transition-transform hover:translate-x-1 mt-2" href="logout.php">
        <span class="material-symbols-outlined">logout</span>
        <span class="text-sm font-bold">Logout</span>
      </a>
    </nav>
    <div class="mt-auto pt-4 border-t border-slate-200">
      <button onclick="window.location='admin_orders.php?action=new_emergency'" class="w-full bg-gradient-to-r from-primary to-primary-container text-white py-3 px-4 rounded-xl font-bold text-sm shadow-md flex items-center justify-center gap-2 active:scale-95 transition-transform">
        <span class="material-symbols-outlined text-lg">add_circle</span>
        New Emergency Order
      </button>
    </div>
  </aside>

  <!-- ── Main Content ─────────────────────────────────────────────────── -->
  <main class="flex-1 lg:ml-64 p-4 lg:p-8 space-y-8 bg-surface">

    <!-- Page Title -->
    <div class="fade-in">
      <h1 class="font-headline text-3xl font-extrabold tracking-tight text-on-surface">Admin Dashboard</h1>
      <p class="text-on-surface-variant font-medium mt-1">Welcome back, <?php echo $firstname; ?> — here's your operations overview.</p>
    </div>

    <!-- ── KPI Cards ──────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">

      <div class="fade-in fade-in-1 bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/15 flex flex-col justify-between hover:shadow-md transition-shadow">
        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Total Orders</span>
        <div class="mt-3 flex items-baseline gap-2">
          <span class="text-2xl font-headline font-extrabold text-on-surface">1,284</span>
          <span class="text-[10px] font-bold text-tertiary">+12%</span>
        </div>
      </div>

      <div class="fade-in fade-in-2 bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/15 flex flex-col justify-between hover:shadow-md transition-shadow">
        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Revenue</span>
        <div class="mt-3 flex items-baseline gap-2">
          <span class="text-2xl font-headline font-extrabold text-on-surface">DZD 4.2M</span>
          <span class="text-[10px] font-bold text-tertiary">↑ High</span>
        </div>
      </div>

      <div class="fade-in fade-in-3 bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/15 flex flex-col justify-between hover:shadow-md transition-shadow">
        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Pharmacies</span>
        <div class="mt-3 flex items-baseline gap-2">
          <span class="text-2xl font-headline font-extrabold text-on-surface">412</span>
          <span class="text-[10px] font-bold text-on-surface-variant">Active</span>
        </div>
      </div>

      <div class="fade-in fade-in-4 bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/15 flex flex-col justify-between hover:shadow-md transition-shadow">
        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Employees</span>
        <div class="mt-3 flex items-baseline gap-2">
          <span class="text-2xl font-headline font-extrabold text-on-surface">86</span>
          <span class="text-[10px] font-bold text-tertiary">94% Utility</span>
        </div>
      </div>

      <div class="fade-in fade-in-5 bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/15 flex flex-col justify-between hover:shadow-md transition-shadow">
        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Payments (Online/COD)</span>
        <div class="mt-3 flex items-center gap-1 h-2 bg-secondary-container rounded-full overflow-hidden">
          <div class="bg-primary h-full w-[65%]"></div>
        </div>
        <div class="mt-1 flex justify-between text-[10px] font-bold text-on-surface-variant">
          <span>65% Online</span>
          <span>35% COD</span>
        </div>
      </div>

    </div>

    <!-- ── Recent Orders + Quick Actions ─────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Recent Orders -->
      <div class="lg:col-span-2 bg-surface-container-lowest rounded-2xl border border-outline-variant/15 p-6 shadow-sm fade-in">
        <div class="flex items-center justify-between mb-6">
          <h2 class="font-headline font-bold text-lg text-on-surface">Recent Orders</h2>
          <button onclick="window.location='admin_orders.php'" class="text-xs font-bold text-primary hover:underline">View All</button>
        </div>
        <div class="space-y-3">

          <div class="flex items-center justify-between p-4 bg-surface-container-low rounded-xl hover:bg-surface-container transition-colors">
            <div class="flex items-center gap-4">
              <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-base">package_2</span>
              </div>
              <div>
                <p class="text-sm font-bold text-on-surface">#ORD-20458</p>
                <p class="text-xs text-on-surface-variant">Pharmacie El Amel — Kouba</p>
              </div>
            </div>
            <div class="text-right">
              <span class="text-[10px] font-black bg-tertiary/10 text-tertiary px-2 py-0.5 rounded-full uppercase tracking-wide">Delivered</span>
              <p class="text-xs text-on-surface-variant mt-1">Today, 11:42 AM</p>
            </div>
          </div>

          <div class="flex items-center justify-between p-4 bg-surface-container-low rounded-xl hover:bg-surface-container transition-colors">
            <div class="flex items-center gap-4">
              <div class="w-9 h-9 rounded-lg bg-orange-100 flex items-center justify-center">
                <span class="material-symbols-outlined text-orange-500 text-base">local_shipping</span>
              </div>
              <div>
                <p class="text-sm font-bold text-on-surface">#ORD-20457</p>
                <p class="text-xs text-on-surface-variant">Pharmacie Centrale — Bab Ezzouar</p>
              </div>
            </div>
            <div class="text-right">
              <span class="text-[10px] font-black bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full uppercase tracking-wide">In Transit</span>
              <p class="text-xs text-on-surface-variant mt-1">Today, 10:15 AM</p>
            </div>
          </div>

          <div class="flex items-center justify-between p-4 bg-surface-container-low rounded-xl hover:bg-surface-container transition-colors">
            <div class="flex items-center gap-4">
              <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-base">package_2</span>
              </div>
              <div>
                <p class="text-sm font-bold text-on-surface">#ORD-20456</p>
                <p class="text-xs text-on-surface-variant">Pharmacie Ibn Sina — Hussein Dey</p>
              </div>
            </div>
            <div class="text-right">
              <span class="text-[10px] font-black bg-tertiary/10 text-tertiary px-2 py-0.5 rounded-full uppercase tracking-wide">Delivered</span>
              <p class="text-xs text-on-surface-variant mt-1">Today, 09:30 AM</p>
            </div>
          </div>

          <div class="flex items-center justify-between p-4 bg-surface-container-low rounded-xl hover:bg-surface-container transition-colors">
            <div class="flex items-center gap-4">
              <div class="w-9 h-9 rounded-lg bg-error-container flex items-center justify-center">
                <span class="material-symbols-outlined text-error text-base">priority_high</span>
              </div>
              <div>
                <p class="text-sm font-bold text-on-surface">#ORD-20455 <span class="text-[9px] font-black bg-error text-white px-1.5 py-0.5 rounded ml-1">URGENT</span></p>
                <p class="text-xs text-on-surface-variant">Pharmacie El Shifa — Dar El Beida</p>
              </div>
            </div>
            <div class="text-right">
              <span class="text-[10px] font-black bg-secondary-container text-on-secondary-container px-2 py-0.5 rounded-full uppercase tracking-wide">Pending</span>
              <p class="text-xs text-on-surface-variant mt-1">Today, 08:05 AM</p>
            </div>
          </div>

        </div>
      </div>

      <!-- Quick Actions + Summary -->
      <div class="space-y-4 fade-in">

        <!-- Quick Actions -->
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/15 p-6 shadow-sm">
          <h2 class="font-headline font-bold text-lg text-on-surface mb-4">Quick Actions</h2>
          <div class="space-y-2">
            <button onclick="window.location='admin_orders.php?action=new_emergency'" class="w-full flex items-center gap-3 px-4 py-3 bg-primary text-white rounded-xl font-bold text-sm hover:bg-primary/90 active:scale-95 transition-all">
              <span class="material-symbols-outlined text-lg">add_circle</span>
              New Emergency Order
            </button>
            <button onclick="window.location='admin_employees.php?action=add'" class="w-full flex items-center gap-3 px-4 py-3 bg-surface-container-low text-on-surface rounded-xl font-bold text-sm hover:bg-surface-container active:scale-95 transition-all border border-outline-variant/20">
              <span class="material-symbols-outlined text-lg text-primary">person_add</span>
              Add Employee
            </button>
            <button onclick="window.location='admin_pharmacies.php?action=register'" class="w-full flex items-center gap-3 px-4 py-3 bg-surface-container-low text-on-surface rounded-xl font-bold text-sm hover:bg-surface-container active:scale-95 transition-all border border-outline-variant/20">
              <span class="material-symbols-outlined text-lg text-primary">local_pharmacy</span>
              Register Pharmacy
            </button>
            <button onclick="window.location='admin_reports.php'" class="w-full flex items-center gap-3 px-4 py-3 bg-surface-container-low text-on-surface rounded-xl font-bold text-sm hover:bg-surface-container active:scale-95 transition-all border border-outline-variant/20">
              <span class="material-symbols-outlined text-lg text-primary">bar_chart</span>
              Generate Report
            </button>
          </div>
        </div>

        <!-- System Status -->
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/15 p-6 shadow-sm">
          <h2 class="font-headline font-bold text-base text-on-surface mb-4">System Status</h2>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-on-surface-variant">Database</span>
              <span class="flex items-center gap-1.5 text-xs font-bold text-tertiary">
                <span class="w-2 h-2 rounded-full bg-tertiary"></span>Operational
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-on-surface-variant">Delivery Network</span>
              <span class="flex items-center gap-1.5 text-xs font-bold text-tertiary">
                <span class="w-2 h-2 rounded-full bg-tertiary"></span>24 Active
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-on-surface-variant">Payment Gateway</span>
              <span class="flex items-center gap-1.5 text-xs font-bold text-tertiary">
                <span class="w-2 h-2 rounded-full bg-tertiary"></span>Online
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-on-surface-variant">Last Sync</span>
              <span class="text-xs font-bold text-on-surface-variant">2 min ago</span>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ── Recent Employees ───────────────────────────────────────────── -->
    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/15 p-6 shadow-sm fade-in">
      <div class="flex items-center justify-between mb-6">
        <h2 class="font-headline font-bold text-lg text-on-surface">Recent Employees</h2>
        <button onclick="window.location='admin_employees.php'" class="text-xs font-bold text-primary hover:underline">Manage All</button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left border-b border-outline-variant/20">
              <th class="pb-3 text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Name</th>
              <th class="pb-3 text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Role</th>
              <th class="pb-3 text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Phone</th>
              <th class="pb-3 text-xs font-bold uppercase tracking-wider text-on-surface-variant/70">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/10">
            <tr class="hover:bg-surface-container-low transition-colors">
              <td class="py-3.5 font-semibold text-on-surface">Ahmed Benali</td>
              <td class="py-3.5 text-on-surface-variant">Delivery Person</td>
              <td class="py-3.5 text-on-surface-variant">0551 234 567</td>
              <td class="py-3.5"><span class="text-[10px] font-black bg-tertiary/10 text-tertiary px-2 py-0.5 rounded-full uppercase">Active</span></td>
            </tr>
            <tr class="hover:bg-surface-container-low transition-colors">
              <td class="py-3.5 font-semibold text-on-surface">Sara Hamidi</td>
              <td class="py-3.5 text-on-surface-variant">Commercial Service</td>
              <td class="py-3.5 text-on-surface-variant">0661 987 654</td>
              <td class="py-3.5"><span class="text-[10px] font-black bg-tertiary/10 text-tertiary px-2 py-0.5 rounded-full uppercase">Active</span></td>
            </tr>
            <tr class="hover:bg-surface-container-low transition-colors">
              <td class="py-3.5 font-semibold text-on-surface">Karim Medjber</td>
              <td class="py-3.5 text-on-surface-variant">Stock Employee</td>
              <td class="py-3.5 text-on-surface-variant">0770 456 123</td>
              <td class="py-3.5"><span class="text-[10px] font-black bg-orange-100 text-orange-600 px-2 py-0.5 rounded-full uppercase">On Leave</span></td>
            </tr>
            <tr class="hover:bg-surface-container-low transition-colors">
              <td class="py-3.5 font-semibold text-on-surface">Nadia Oussalah</td>
              <td class="py-3.5 text-on-surface-variant">Delivery Manager</td>
              <td class="py-3.5 text-on-surface-variant">0550 321 789</td>
              <td class="py-3.5"><span class="text-[10px] font-black bg-tertiary/10 text-tertiary px-2 py-0.5 rounded-full uppercase">Active</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- AI Assistant Button -->
<button class="fixed bottom-8 right-8 w-14 h-14 bg-primary text-on-primary rounded-full shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all z-[60] group">
  <span class="material-symbols-outlined text-2xl" style="font-variation-settings:'FILL' 1;">smart_toy</span>
  <div class="absolute right-full mr-4 bg-inverse-surface text-inverse-on-surface px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
    How can I help you today?
  </div>
</button>

<!-- Mobile Bottom Nav -->
<nav class="md:hidden fixed bottom-0 left-0 w-full z-50 flex justify-around items-center px-4 pb-6 pt-3 bg-white/90 backdrop-blur-xl border-t border-slate-200 shadow-[0_-4px_12px_rgba(0,0,0,0.05)]">
  <div class="flex flex-col items-center bg-blue-100 text-blue-800 rounded-xl px-3 py-1.5">
    <span class="material-symbols-outlined">grid_view</span>
    <span class="text-[10px] font-semibold uppercase">Dashboard</span>
  </div>
  <div class="flex flex-col items-center text-slate-400">
    <span class="material-symbols-outlined">auto_stories</span>
    <span class="text-[10px] font-semibold uppercase">Orders</span>
  </div>
  <div class="flex flex-col items-center text-slate-400">
    <span class="material-symbols-outlined">qr_code_scanner</span>
    <span class="text-[10px] font-semibold uppercase">Scan</span>
  </div>
  <div class="flex flex-col items-center text-slate-400">
    <span class="material-symbols-outlined">badge</span>
    <span class="text-[10px] font-semibold uppercase">Employees</span>
  </div>
  <div class="flex flex-col items-center text-slate-400">
    <span class="material-symbols-outlined">settings</span>
    <span class="text-[10px] font-semibold uppercase">Settings</span>
  </div>
</nav>

</body>
</html>
