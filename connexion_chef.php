<?php
session_start();
require_once 'connexion.php';

// --- NOTE DE SÉCURITÉ IMPORTANTE ---
// Pour une application professionnelle, il est crucial de ne jamais stocker de mots de passe en clair.
// Utilisez password_hash() lors de la création et password_verify() pour la connexion.
// if ($user && password_verify($password, $user['mot_de_passe'])) { ... }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $query = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'chef'");
    $query->execute([$email]);
    $user = $query->fetch();

    if ($user && $password === $user['mot_de_passe']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = 'chef';
        header('Location: chef_dashboard.php');
        exit();
    } else {
        $error = "Identifiants de gestionnaire incorrects.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Connexion Gestionnaire | Groupe1App</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');

    :root {
        --primary-color: #6d8b74; /* Vert doux (conservé) */
        --secondary-color: #d4a555; /* Doré (conservé) */
        --background-start: #f7f3e9;
        --background-end: #e8e8e8;
        --form-background: rgba(255, 255, 255, 0.95);
        --text-color: #333;
        --placeholder-color: #aaa;
        /* Nouvelle couleur pour les icônes */
        --icon-flow-color: rgba(109, 139, 116, 0.5);
    }
    
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
      font-family: 'Poppins', sans-serif;
      height: 100vh;
      overflow: hidden;
      background: linear-gradient(135deg, var(--background-start), var(--background-end));
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    /* --- NOUVELLE ANIMATION DE FLUX DE DONNÉES --- */
    #data-flow-container {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        z-index: 0;
    }

    .data-icon {
        position: absolute;
        bottom: -150px; /* Démarrage en bas */
        font-size: 2rem;
        user-select: none;
        color: var(--icon-flow-color);
        animation-name: float-up;
        animation-timing-function: ease-in-out;
        animation-iteration-count: infinite;
        /* Variables CSS pour une animation unique */
        --x-pos: 50vw;
        --x-drift: 0;
        --rotation: 0;
    }
    
    @keyframes float-up {
        0% {
            transform: translateY(0) translateX(var(--x-drift)) rotate(0);
            opacity: 0;
        }
        10% {
            opacity: 1;
        }
        90% {
            opacity: 1;
        }
        100% {
            bottom: 110vh; /* Monte jusqu'en haut de l'écran */
            transform: translateY(-100px) translateX(calc(var(--x-drift) * -1)) rotate(var(--rotation));
            opacity: 0;
        }
    }
    /* --- FIN DE L'ANIMATION --- */

    .login-container {
      background-color: var(--form-background);
      padding: 40px 30px;
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(0,0,0,0.1);
      z-index: 1;
      width: 90%;
      max-width: 400px;
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.8);
    }
    
    .logo {
        width: 120px;
        margin-bottom: 15px;
    }

    h2 {
      margin-bottom: 25px;
      color: var(--primary-color);
      font-weight: 600;
      font-size: 1.8em;
    }
    
    .input-group {
        position: relative;
        margin: 20px 0;
    }

    .input-group i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--placeholder-color);
        transition: color 0.3s;
    }

    .input-field {
      width: 100%;
      padding: 14px 14px 14px 50px;
      border-radius: 10px;
      border: 1px solid #ddd;
      background-color: #fff;
      font-size: 16px;
      font-family: 'Poppins', sans-serif;
      transition: all 0.3s;
    }
    
    .input-field:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(109, 139, 116, 0.2);
    }

    .input-field:focus + i {
        color: var(--primary-color);
    }

    .submit-btn {
      width: 100%;
      padding: 14px;
      margin-top: 20px;
      border-radius: 10px;
      border: none;
      background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
      color: white;
      font-size: 18px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }

    .submit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(109, 139, 116, 0.3);
    }

    .error {
      color: #c0392b;
      background-color: rgba(192, 57, 43, 0.1);
      border: 1px solid rgba(192, 57, 43, 0.2);
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 15px;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <div id="data-flow-container"></div>

  <div class="login-container">
    <img src="http://googleusercontent.com/image_generation_content/0" alt="Logo Groupe1App" class="logo">
    <h2>Espace Gestionnaire</h2>
    
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    
    <form method="post">
      <div class="input-group">
        <input type="email" name="email" class="input-field" placeholder="Email" required>
        <i class="fas fa-user-tie"></i>
      </div>
      <div class="input-group">
        <input type="password" name="password" class="input-field" placeholder="Mot de passe" required>
        <i class="fas fa-lock"></i>
      </div>
      <button type="submit" class="submit-btn">Se connecter</button>
    </form>
  </div>

  <script>
    // --- SCRIPT POUR L'ANIMATION DE FLUX DE DONNÉES ---
    const dataFlowContainer = document.getElementById('data-flow-container');
    const numberOfIcons = 25;
    
    // Liste d'icônes Font Awesome pour représenter les données
    const icons = [
        'fa-database', 'fa-server', 'fa-cloud', 'fa-code-branch', 
        'fa-file-alt', 'fa-shield-alt', 'fa-network-wired', 'fa-microchip'
    ];

    for (let i = 0; i < numberOfIcons; i++) {
        const iconWrapper = document.createElement('div');
        iconWrapper.classList.add('data-icon');
        
        // Choisit une icône au hasard dans la liste
        const randomIconClass = icons[Math.floor(Math.random() * icons.length)];
        iconWrapper.innerHTML = `<i class="fas ${randomIconClass}"></i>`;

        const scale = Math.random() * 0.8 + 0.4; // Échelle de 0.4 à 1.2
        const duration = Math.random() * 10 + 10; // Durée de 10s à 20s
        const delay = Math.random() * 10;
        
        iconWrapper.style.transform = `scale(${scale})`;
        iconWrapper.style.left = `${Math.random() * 100}vw`;
        iconWrapper.style.animationDuration = `${duration}s`;
        iconWrapper.style.animationDelay = `${delay}s`;
        
        // Variables CSS pour un mouvement de dérive et rotation unique
        iconWrapper.style.setProperty('--x-drift', `${Math.random() * 200 - 100}px`);
        iconWrapper.style.setProperty('--rotation', `${Math.random() * 360 - 180}deg`);

        dataFlowContainer.appendChild(iconWrapper);
    }
  </script>
</body>
</html>