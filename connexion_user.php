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

    $query = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'membre'");
    $query->execute([$email]);
    $user = $query->fetch();

    // Vérification non sécurisée pour la démo
    if ($user && $password === $user['mot_de_passe']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = 'membre';
        header('Location: user_dashboard.php');
        exit();
    } else {
        $error = "L'email ou le mot de passe est incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Connexion | Groupe1App</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');

    :root {
        --primary-color: #6D28D9; /* Violet profond */
        --secondary-color: #EC4899; /* Rose vibrant */
        --background-start: #F5F7FA;
        --background-end: #C3CFE2;
        --form-background: rgba(255, 255, 255, 0.6);
        --text-color: #1F2937;
        --placeholder-color: #9CA3AF;
    }
    
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
      font-family: 'Poppins', sans-serif;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, var(--background-start), var(--background-end));
      overflow: hidden;
      position: relative;
    }

    /* --- NOUVELLE ANIMATION D'ORIGAMIS --- */
    #origami-container {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        overflow: hidden;
        z-index: 0;
    }
    
    .origami {
        position: absolute;
        font-size: 50px; /* Taille de base de l'icône */
        color: rgba(255, 255, 255, 0.7);
        right: -100px; /* Démarrage hors de l'écran à droite */
        animation-name: fly-across;
        animation-timing-function: linear;
        animation-iteration-count: infinite;
        z-index: 0;
        --rotation: 0; /* Variable CSS pour la rotation */
    }

    @keyframes fly-across {
        0% { transform: translateX(0) rotate(calc(var(--rotation) * -20deg)); }
        100% { transform: translateX(-120vw) rotate(calc(var(--rotation) * 20deg)); }
    }
    /* --- FIN DE L'ANIMATION --- */

    .login-container {
      background-color: var(--form-background);
      padding: 40px 30px;
      border-radius: 20px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.1);
      z-index: 1;
      width: 90%;
      max-width: 400px;
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(15px);
    }
    
    .logo {
        width: 120px;
        margin-bottom: 15px;
    }

    h2 {
      margin-bottom: 25px;
      color: var(--text-color);
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
      border: 1px solid #D1D5DB;
      background-color: #F9FAFB;
      font-size: 16px;
      font-family: 'Poppins', sans-serif;
      transition: all 0.3s;
    }
    
    .input-field:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(109, 40, 217, 0.2);
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
        box-shadow: 0 8px 20px rgba(109, 40, 217, 0.3);
    }

    .error {
      color: #DC2626;
      background-color: #FEF2F2;
      border: 1px solid #FCA5A5;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 15px;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <div id="origami-container"></div>

  <div class="login-container">
    <img src="http://googleusercontent.com/image_generation_content/0" alt="Logo Groupe1App" class="logo">
    <h2>Espace Membre</h2>
    
    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
    
    <form method="post">
      <div class="input-group">
        <input type="email" name="email" class="input-field" placeholder="Email" required>
        <i class="fas fa-envelope"></i>
      </div>
      <div class="input-group">
        <input type="password" name="password" class="input-field" placeholder="Mot de passe" required>
        <i class="fas fa-lock"></i>
      </div>
      <button type="submit" class="submit-btn">Se connecter</button>
    </form>
  </div>

  <script>
    // --- SCRIPT POUR L'ANIMATION DES ORIGAMIS ---
    const origamiContainer = document.getElementById('origami-container');
    const numberOfOrigamis = 15; // Nombre d'oiseaux à l'écran

    for (let i = 0; i < numberOfOrigamis; i++) {
        const origami = document.createElement('div');
        origami.classList.add('origami');
        
        // Utilisation de l'icône "avion en papier" de Font Awesome
        origami.innerHTML = '<i class="fas fa-paper-plane"></i>';

        const startY = Math.random() * 90; // Position verticale de départ en %
        const duration = Math.random() * 10 + 8; // Durée de 8s à 18s
        const scale = Math.random() * 0.8 + 0.5; // Échelle de 0.5 à 1.3
        const delay = Math.random() * 5; // Délai avant départ
        
        origami.style.top = `${startY}vh`;
        origami.style.transform = `scale(${scale})`;
        origami.style.animationDuration = `${duration}s`;
        origami.style.animationDelay = `${delay}s`;
        
        // Variable CSS pour une rotation de lacet unique
        origami.style.setProperty('--rotation', Math.random() * 2 - 1);
        
        origamiContainer.appendChild(origami);
    }
  </script>
</body>
</html>