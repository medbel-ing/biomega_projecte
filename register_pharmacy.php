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

$error   = "";
$success = "";

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nif       = trim($_POST["nif"]       ?? "");
    $firstname = trim($_POST["firstname"] ?? "");
    $lastname  = trim($_POST["lastname"]  ?? "");
    $phone     = trim($_POST["phone"]     ?? "");
    $worktime  = trim($_POST["worktime"]  ?? "");
    $password  = trim($_POST["password"]  ?? "");
    $confirm   = trim($_POST["confirm"]   ?? "");
    $location  = trim($_POST["location"]  ?? "");
    $wilaya    = trim($_POST["wilaya"]    ?? "");

    // ── Validation ────────────────────────────────────────────────────────────
    if (empty($nif) || empty($firstname) || empty($lastname) || empty($phone) ||
        empty($worktime) || empty($password) || empty($location) || empty($wilaya)) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!ctype_digit($nif)) {
        $error = "Le NIF doit contenir uniquement des chiffres.";
    } elseif (!preg_match('/^0[567]\d{8}$/', $phone)) {
        $error = "Numéro de téléphone invalide (ex: 0555123456).";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Check if NIF already exists
        $chk = mysqli_prepare($conn, "SELECT NIF FROM pharmacy WHERE NIF = ?");
        mysqli_stmt_bind_param($chk, "i", $nif);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        if (mysqli_stmt_num_rows($chk) > 0) {
            $error = "Une pharmacie avec ce NIF existe déjà.";
        }
        mysqli_stmt_close($chk);

        // Check if phone already exists
        if (empty($error)) {
            $chk2 = mysqli_prepare($conn, "SELECT PhoneNumber FROM pharmacy WHERE PhoneNumber = ?");
            mysqli_stmt_bind_param($chk2, "i", $phone);
            mysqli_stmt_execute($chk2);
            mysqli_stmt_store_result($chk2);
            if (mysqli_stmt_num_rows($chk2) > 0) {
                $error = "Ce numéro de téléphone est déjà enregistré.";
            }
            mysqli_stmt_close($chk2);
        }

        if (empty($error)) {
            $full_location = $wilaya . " - " . $location;
            $sql = "INSERT INTO pharmacy (NIF, FirstName, LastName, PhoneNumber, WorkTime, Password, Location)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "issssss", $nif, $firstname, $lastname, $phone, $worktime, $password, $full_location);

            if (mysqli_stmt_execute($stmt)) {
                $success = "Votre pharmacie a été enregistrée avec succès ! Vous pouvez maintenant vous connecter.";
            } else {
                $error = "Erreur lors de l'enregistrement : " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

mysqli_close($conn);

// Algerian wilayas list
$wilayas = [
  "01 - Adrar","02 - Chlef","03 - Laghouat","04 - Oum El Bouaghi","05 - Batna",
  "06 - Béjaïa","07 - Biskra","08 - Béchar","09 - Blida","10 - Bouira",
  "11 - Tamanrasset","12 - Tébessa","13 - Tlemcen","14 - Tiaret","15 - Tizi Ouzou",
  "16 - Alger","17 - Djelfa","18 - Jijel","19 - Sétif","20 - Saïda",
  "21 - Skikda","22 - Sidi Bel Abbès","23 - Annaba","24 - Guelma","25 - Constantine",
  "26 - Médéa","27 - Mostaganem","28 - M'Sila","29 - Mascara","30 - Ouargla",
  "31 - Oran","32 - El Bayadh","33 - Illizi","34 - Bordj Bou Arréridj","35 - Boumerdès",
  "36 - El Tarf","37 - Tindouf","38 - Tissemsilt","39 - El Oued","40 - Khenchela",
  "41 - Souk Ahras","42 - Tipaza","43 - Mila","44 - Aïn Defla","45 - Naâma",
  "46 - Aïn Témouchent","47 - Ghardaïa","48 - Relizane","49 - El M'Ghair","50 - El Meniaa",
  "51 - Ouled Djellal","52 - Bordj Baji Mokhtar","53 - Béni Abbès","54 - Timimoun",
  "55 - Touggourt","56 - Djanet","57 - In Salah","58 - In Guezzam"
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Enregistrer votre Pharmacie — Bio Mega Pharme</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script>
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        colors: {
          "primary":                    "#005ea4",
          "primary-container":          "#0077ce",
          "primary-fixed":              "#d3e4ff",
          "on-primary":                 "#ffffff",
          "on-primary-container":       "#fdfcff",
          "tertiary":                   "#186a22",
          "tertiary-container":         "#358438",
          "on-tertiary":                "#ffffff",
          "secondary":                  "#4c616c",
          "secondary-container":        "#cfe6f2",
          "on-secondary-container":     "#526772",
          "surface":                    "#f8f9fa",
          "surface-container-lowest":   "#ffffff",
          "surface-container-low":      "#f3f4f5",
          "surface-container":          "#edeeef",
          "surface-container-high":     "#e7e8e9",
          "on-surface":                 "#191c1d",
          "on-surface-variant":         "#404752",
          "outline":                    "#707783",
          "outline-variant":            "#c0c7d4",
          "error":                      "#ba1a1a",
          "error-container":            "#ffdad6",
          "on-error-container":         "#93000a",
        },
        fontFamily: {
          "headline": ["Manrope"],
          "body":     ["Inter"],
        },
      },
    },
  }
</script>
<style>
  .material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  }
  body { font-family: 'Inter', sans-serif; }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .fade-up { animation: fadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both; }
  .fade-up-2 { animation: fadeUp 0.5s 0.1s cubic-bezier(0.16, 1, 0.3, 1) both; }

  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    20%       { transform: translateX(-6px); }
    40%       { transform: translateX(6px); }
    60%       { transform: translateX(-4px); }
    80%       { transform: translateX(4px); }
  }
  .shake { animation: shake 0.4s ease; }

  .field-group input,
  .field-group select {
    width: 100%;
    padding: 0.875rem 1rem 0.875rem 3rem;
    background: #e7e8e9;
    border: none;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    color: #191c1d;
    transition: box-shadow 0.2s;
    outline: none;
  }
  .field-group input:focus,
  .field-group select:focus {
    box-shadow: 0 0 0 2px rgba(0,94,164,0.2);
  }
  .field-group .icon {
    position: absolute;
    left: 0.9rem;
    top: 50%;
    transform: translateY(-50%);
    color: #707783;
    font-size: 1.25rem;
    pointer-events: none;
    transition: color 0.2s;
  }
  .field-group:focus-within .icon { color: #005ea4; }
</style>
</head>
<body class="bg-surface min-h-screen">

<!-- Header -->
<header class="bg-white/90 backdrop-blur-lg shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto flex items-center justify-between px-6 py-3">
    <a href="index.php" class="text-xl font-extrabold tracking-tighter text-blue-800" style="font-family:'Manrope',sans-serif;">
      Bio Mega Pharme
    </a>
    <a href="login.php" class="flex items-center gap-2 bg-primary text-white px-5 py-2 rounded-xl font-semibold text-sm hover:opacity-90 transition-all">
      <span class="material-symbols-outlined text-sm">login</span>
      Connexion
    </a>
  </div>
  <div class="h-px bg-slate-100 w-full"></div>
</header>

<main class="py-12 px-4">
  <div class="max-w-2xl mx-auto">

    <!-- Page header -->
    <div class="text-center mb-10 fade-up">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-2xl mb-5 shadow-md">
        <span class="material-symbols-outlined text-white text-3xl" style="font-variation-settings:'FILL' 1;">local_pharmacy</span>
      </div>
      <h1 class="text-3xl font-extrabold tracking-tight text-on-surface mb-2" style="font-family:'Manrope',sans-serif;">
        Enregistrer votre Pharmacie
      </h1>
      <p class="text-on-surface-variant text-sm max-w-md mx-auto">
        Rejoignez le réseau Bio Mega Pharme et connectez-vous avec des milliers de patients à travers l'Algérie.
      </p>
    </div>

    <!-- Card -->
    <div class="bg-surface-container-lowest rounded-2xl shadow-[0_20px_60px_-15px_rgba(0,94,164,0.10)] border border-outline-variant/10 p-8 fade-up-2">

      <!-- Error -->
      <?php if (!empty($error)): ?>
      <div class="shake mb-6 flex items-center gap-3 bg-error-container text-on-error-container text-sm font-semibold px-4 py-3 rounded-xl border border-error/20">
        <span class="material-symbols-outlined text-lg flex-shrink-0">error</span>
        <?php echo htmlspecialchars($error); ?>
      </div>
      <?php endif; ?>

      <!-- Success -->
      <?php if (!empty($success)): ?>
      <div class="mb-6 flex items-start gap-3 bg-green-50 text-green-800 text-sm font-semibold px-4 py-4 rounded-xl border border-green-200">
        <span class="material-symbols-outlined text-lg flex-shrink-0 text-green-600" style="font-variation-settings:'FILL' 1;">check_circle</span>
        <div>
          <?php echo htmlspecialchars($success); ?>
          <a href="login.php" class="block mt-2 underline text-primary font-bold">→ Aller à la page de connexion</a>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" action="register_pharmacy.php" class="space-y-5">

        <!-- Section: Identité -->
        <div>
          <p class="text-xs font-bold text-outline uppercase tracking-widest mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">badge</span>Informations d'identité
          </p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- NIF -->
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 ml-1">NIF <span class="text-error">*</span></label>
              <div class="relative field-group">
                <span class="material-symbols-outlined icon">tag</span>
                <input type="text" name="nif" required placeholder="Numéro d'identification fiscale"
                  value="<?php echo htmlspecialchars($_POST['nif'] ?? ''); ?>"/>
              </div>
            </div>

            <!-- WorkTime -->
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 ml-1">Horaire d'ouverture <span class="text-error">*</span></label>
              <div class="relative field-group">
                <span class="material-symbols-outlined icon">schedule</span>
                <input type="time" name="worktime" required
                  value="<?php echo htmlspecialchars($_POST['worktime'] ?? ''); ?>"/>
              </div>
            </div>

            <!-- First Name -->
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 ml-1">Prénom du responsable <span class="text-error">*</span></label>
              <div class="relative field-group">
                <span class="material-symbols-outlined icon">person</span>
                <input type="text" name="firstname" required placeholder="Prénom"
                  value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>"/>
              </div>
            </div>

            <!-- Last Name -->
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 ml-1">Nom du responsable <span class="text-error">*</span></label>
              <div class="relative field-group">
                <span class="material-symbols-outlined icon">person</span>
                <input type="text" name="lastname" required placeholder="Nom"
                  value="<?php echo htmlspecialchars($_POST['lastname'] ?? ''); ?>"/>
              </div>
            </div>

          </div>
        </div>

        <!-- Divider -->
        <div class="h-px bg-outline-variant/30"></div>

        <!-- Section: Contact & Localisation -->
        <div>
          <p class="text-xs font-bold text-outline uppercase tracking-widest mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">location_on</span>Contact & Localisation
          </p>

          <!-- Phone -->
          <div class="mb-4">
            <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 ml-1">Numéro de téléphone <span class="text-error">*</span></label>
            <div class="relative field-group">
              <span class="material-symbols-outlined icon">phone</span>
              <input type="tel" name="phone" required placeholder="0555123456"
                value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"/>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Wilaya -->
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 ml-1">Wilaya <span class="text-error">*</span></label>
              <div class="relative field-group">
                <span class="material-symbols-outlined icon">map</span>
                <select name="wilaya" required>
                  <option value="">Sélectionner une wilaya</option>
                  <?php foreach ($wilayas as $w): ?>
                    <option value="<?php echo htmlspecialchars($w); ?>"
                      <?php echo (($_POST['wilaya'] ?? '') === $w) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($w); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Location / Address -->
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 ml-1">Adresse précise <span class="text-error">*</span></label>
              <div class="relative field-group">
                <span class="material-symbols-outlined icon">home_pin</span>
                <input type="text" name="location" required placeholder="Rue, quartier, cité..."
                  value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"/>
              </div>
            </div>

          </div>
        </div>

        <!-- Divider -->
        <div class="h-px bg-outline-variant/30"></div>

        <!-- Section: Sécurité -->
        <div>
          <p class="text-xs font-bold text-outline uppercase tracking-widest mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">lock</span>Sécurité du compte
          </p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            <!-- Password -->
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 ml-1">Mot de passe <span class="text-error">*</span></label>
              <div class="relative field-group">
                <span class="material-symbols-outlined icon">lock</span>
                <input type="password" id="pass1" name="password" required placeholder="Min. 6 caractères"/>
                <button type="button" onclick="togglePwd('pass1','eye1')"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors">
                  <span id="eye1" class="material-symbols-outlined text-xl">visibility</span>
                </button>
              </div>
            </div>

            <!-- Confirm -->
            <div>
              <label class="block text-xs font-semibold text-on-surface-variant mb-1.5 ml-1">Confirmer le mot de passe <span class="text-error">*</span></label>
              <div class="relative field-group">
                <span class="material-symbols-outlined icon">lock_reset</span>
                <input type="password" id="pass2" name="confirm" required placeholder="Répéter le mot de passe"/>
                <button type="button" onclick="togglePwd('pass2','eye2')"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors">
                  <span id="eye2" class="material-symbols-outlined text-xl">visibility</span>
                </button>
              </div>
            </div>

          </div>
        </div>

        <!-- Terms notice -->
        <p class="text-xs text-on-surface-variant text-center px-4">
          En vous inscrivant, vous acceptez d'être contacté par l'équipe Bio Mega Pharme pour vérification de votre dossier pharmacien.
        </p>

        <!-- Submit -->
        <button type="submit"
          class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-primary to-primary-container text-on-primary py-4 rounded-xl font-bold text-sm tracking-tight shadow-md hover:shadow-lg hover:opacity-90 active:scale-[0.98] transition-all">
          <span class="material-symbols-outlined text-lg" style="font-variation-settings:'FILL' 1;">how_to_reg</span>
          Enregistrer ma Pharmacie
        </button>

      </form>
    </div>

    <!-- Already have account -->
    <p class="text-center text-sm text-on-surface-variant mt-6">
      Vous avez déjà un compte ?
      <a href="login.php" class="text-primary font-semibold hover:underline">Se connecter</a>
    </p>

  </div>
</main>

<script>
  function togglePwd(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') {
      input.type = 'text';
      icon.textContent = 'visibility_off';
    } else {
      input.type = 'password';
      icon.textContent = 'visibility';
    }
  }
</script>
</body>
</html>
