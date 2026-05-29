<?php
session_start();

// ── Database connection ───────────────────────────────────────────────────────
$db_server = "localhost";
$db_user   = "root";
$db_pass   = "";
$db_name   = "biomegadb";

$conn = mysqli_connect($db_server, $db_user, $db_pass, $db_name);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ── Tables to search through ──────────────────────────────────────────────────
$tables = [
    "admin"             => "PhoneNumber",
    "pharmacy"          => "PhoneNumber",
    "commercialservice" => "PhoneNumber",
    "deliverymanager"   => "PhoneNumber",
    "deliveryperson"    => "PhoneNumber",
    "stockemployee"     => "PhoneNumber",
];

$error = "";

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $phone    = trim($_POST["phone"]    ?? "");
    $password = trim($_POST["password"] ?? "");

    if (empty($phone) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {

        $found         = false;
        $wrongPassword = false;

        foreach ($tables as $table => $phoneCol) {

            $sql  = "SELECT * FROM `$table` WHERE `$phoneCol` = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "s", $phone);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row    = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($row) {
                $found = true;

                // Password check (plain-text as stored in your DB)
                if ($password === $row["Password"]) {

                    $_SESSION["user_id"]   = $row["ID"]  ?? $row["NIF"] ?? null;
                    $_SESSION["phone"]     = $phone;
                    $_SESSION["firstname"] = $row["FirstName"];
                    $_SESSION["lastname"]  = $row["LastName"];
                    $_SESSION["role"]      = $row["Role"] ?? $table;
                    $_SESSION["table"]     = $table;

                    // Redirect per role
                    $redirects = [
                        "admin"             => "admin_dashboard.php",
                        "pharmacy"          => "pharmacy_dashboard.php",
                        "commercialservice" => "commercial_dashboard.php",
                        "deliverymanager"   => "delivery_manager_dashboard.php",
                        "deliveryperson"    => "delivery_person_dashboard.php",
                        "stockemployee"     => "stock_dashboard.php",
                    ];

                    header("Location: " . ($redirects[$table] ?? "dashboard.php"));
                    exit();

                } else {
                    $wrongPassword = true;
                    break;
                }
            }
        }

        if ($wrongPassword) {
            $error = "Incorrect password.";
        } elseif (!$found) {
            $error = "No account found with this phone number.";
        }
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Login - TronSport Medicamon</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        colors: {
          "primary-fixed-dim":          "#a2c9ff",
          "tertiary":                   "#186a22",
          "secondary":                  "#4c616c",
          "on-primary":                 "#ffffff",
          "background":                 "#f8f9fa",
          "on-primary-fixed-variant":   "#004881",
          "on-tertiary-fixed-variant":  "#005312",
          "inverse-surface":            "#2e3132",
          "on-tertiary-container":      "#f7fff1",
          "on-tertiary-fixed":          "#002204",
          "surface-tint":               "#0060a8",
          "inverse-on-surface":         "#f0f1f2",
          "on-error":                   "#ffffff",
          "secondary-container":        "#cfe6f2",
          "tertiary-fixed":             "#a3f69c",
          "on-primary-container":       "#fdfcff",
          "tertiary-fixed-dim":         "#88d982",
          "on-secondary-container":     "#526772",
          "surface-container-lowest":   "#ffffff",
          "on-primary-fixed":           "#001c38",
          "tertiary-container":         "#358438",
          "surface-container-high":     "#e7e8e9",
          "on-tertiary":                "#ffffff",
          "primary-container":          "#0077ce",
          "surface-bright":             "#f8f9fa",
          "surface-container-highest":  "#e1e3e4",
          "on-background":              "#191c1d",
          "secondary-fixed":            "#cfe6f2",
          "inverse-primary":            "#a2c9ff",
          "surface-dim":                "#d9dadb",
          "on-secondary-fixed-variant": "#354a53",
          "surface-variant":            "#e1e3e4",
          "on-secondary":               "#ffffff",
          "error":                      "#ba1a1a",
          "outline-variant":            "#c0c7d4",
          "surface":                    "#f8f9fa",
          "on-surface":                 "#191c1d",
          "error-container":            "#ffdad6",
          "primary-fixed":              "#d3e4ff",
          "surface-container":          "#edeeef",
          "on-error-container":         "#93000a",
          "secondary-fixed-dim":        "#b4cad6",
          "on-secondary-fixed":         "#071e27",
          "primary":                    "#005ea4",
          "outline":                    "#707783",
          "surface-container-low":      "#f3f4f5",
          "on-surface-variant":         "#404752"
        },
        fontFamily: {
          "headline": ["Manrope"],
          "body":     ["Inter"],
          "label":    ["Inter"]
        },
        borderRadius: {
          "DEFAULT": "0.125rem",
          "lg":      "0.25rem",
          "xl":      "0.5rem",
          "full":    "0.75rem"
        },
      },
    },
  }
</script>
<style>
  .material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  }
  body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
  .headline-font { font-family: 'Manrope', sans-serif; }

  @keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .animate-card {
    animation: fadeSlideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
  }

  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%       { transform: translateX(-6px); }
    40%       { transform: translateX(6px); }
    60%       { transform: translateX(-4px); }
    80%       { transform: translateX(4px); }
  }
  .shake { animation: shake 0.4s ease; }
</style>
</head>
<body class="bg-surface text-on-surface min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

<!-- Background blobs -->
<div class="absolute inset-0 z-0 pointer-events-none">
  <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-primary-container/10 blur-[120px] rounded-full"></div>
  <div class="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] bg-secondary-container/15 blur-[150px] rounded-full"></div>
</div>

<main class="relative z-10 w-full max-w-[480px]">
  <div class="animate-card bg-surface-container-lowest p-8 md:p-12 rounded-xl shadow-[0_24px_48px_-12px_rgba(0,94,164,0.08)] border border-outline-variant/10">

    <!-- Logo + Title -->
    <div class="flex flex-col items-center mb-10 text-center">
      <div class="w-16 h-16 bg-primary-container flex items-center justify-center rounded-xl mb-6 shadow-sm">
        <span class="material-symbols-outlined text-on-primary text-4xl" style="font-variation-settings:'FILL' 1;">medical_services</span>
      </div>
      <h1 class="headline-font text-3xl font-extrabold tracking-tighter text-primary mb-2">TronSport Medicamon</h1>
      <p class="text-on-surface-variant font-medium text-sm">Precision Medical Logistics Portal</p>
    </div>

    <!-- Error message -->
    <?php if (!empty($error)): ?>
    <div id="errorBox" class="shake mb-6 flex items-center gap-3 bg-error-container text-on-error-container text-sm font-semibold px-4 py-3 rounded-lg border border-error/20">
      <span class="material-symbols-outlined text-lg flex-shrink-0">error</span>
      <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="login.php" class="space-y-6">

      <div class="space-y-4">

        <!-- Phone -->
        <div class="relative group">
          <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5 ml-1">
            Phone Number
          </label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-xl group-focus-within:text-primary transition-colors">phone</span>
            <input
              type="tel"
              name="phone"
              required
              value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
              placeholder="0xxxxxxxxx"
              class="w-full pl-12 pr-4 py-3.5 bg-surface-container-high border-none rounded-lg focus:ring-2 focus:ring-primary/20 transition-all text-on-surface placeholder:text-outline/60 text-sm"
            />
          </div>
        </div>

        <!-- Password -->
        <div class="relative group">
          <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5 ml-1">
            Password
          </label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-xl group-focus-within:text-primary transition-colors">lock</span>
            <input
              id="passwordInput"
              type="password"
              name="password"
              required
              placeholder="••••••••"
              class="w-full pl-12 pr-12 py-3.5 bg-surface-container-high border-none rounded-lg focus:ring-2 focus:ring-primary/20 transition-all text-on-surface placeholder:text-outline/60 text-sm"
            />
            <button
              type="button"
              onclick="togglePassword()"
              class="absolute right-4 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors"
            >
              <span id="eyeIcon" class="material-symbols-outlined text-xl">visibility</span>
            </button>
          </div>
        </div>

      </div>

      <!-- Submit -->
      <div class="pt-4">
        <button
          type="submit"
          class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-primary to-primary-container text-on-primary py-3.5 px-6 rounded-lg font-bold text-sm tracking-tight shadow-md hover:shadow-lg hover:opacity-90 active:scale-[0.98] transition-all"
        >
          <span class="material-symbols-outlined text-lg">login</span>
          Login
        </button>
      </div>

    </form>

    <!-- Footer links -->
    <div class="mt-10 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-outline-variant/20 pt-8">
      <a href="register.php" class="text-sm font-semibold text-primary hover:text-primary-container transition-colors flex items-center gap-1">
        <span class="material-symbols-outlined text-lg">person_add</span>
        Register as Pharmacy
      </a>
      <a href="forgot_password.php" class="text-sm font-medium text-on-surface-variant hover:text-primary transition-colors">
        Forgot Password?
      </a>
    </div>

  </div>

  <!-- Version badge -->
  <footer class="mt-8 text-center">
    <p class="text-[11px] font-bold text-outline uppercase tracking-widest flex items-center justify-center gap-2">
      <span class="w-1.5 h-1.5 rounded-full bg-tertiary shadow-[0_0_8px_rgba(24,106,34,0.4)]"></span>
      System Operational: V2.4.0
    </p>
  </footer>
</main>

<!-- Support widget (large screens) -->
<div class="fixed bottom-8 right-8 hidden xl:flex items-center gap-4 bg-surface-container-low/80 backdrop-blur-md p-4 rounded-xl border border-outline-variant/20 shadow-sm">
  <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
    <span class="material-symbols-outlined text-primary text-xl">support_agent</span>
  </div>
  <div>
    <p class="text-[10px] font-bold text-outline uppercase tracking-tight">Need assistance?</p>
    <p class="text-xs font-semibold text-on-surface">Contact Support Hub</p>
  </div>
</div>

<script>
  function togglePassword() {
    const input = document.getElementById('passwordInput');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
      input.type      = 'text';
      icon.textContent = 'visibility_off';
    } else {
      input.type      = 'password';
      icon.textContent = 'visibility';
    }
  }
</script>

</body>
</html>
