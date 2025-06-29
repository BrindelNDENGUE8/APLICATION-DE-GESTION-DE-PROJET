<?php
// connexion_admin.php
session_start();
require_once 'connexion.php'; // Fichier contenant la connexion PDO à la base de données

// --- NOTE DE SÉCURITÉ IMPORTANTE ---
// Le code ci-dessous compare des mots de passe en clair.
// C'est une pratique dangereuse pour un site en production.
// Idéalement, les mots de passe devraient être "hashés" avec password_hash()
// lors de l'inscription, et vérifiés avec password_verify() ici.
// Exemple de vérification sécurisée :
// if ($user && password_verify($password, $user['mot_de_passe'])) { ... }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $query = $pdo->prepare("SELECT id, nom, email, mot_de_passe, role FROM users WHERE email = ? AND role = 'admin'");
    $query->execute([$email]);
    $user = $query->fetch();

    // Vérification actuelle (non sécurisée)
    if ($user && $password === $user['mot_de_passe']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nom'] = $user['nom'];
        $_SESSION['role'] = $user['role'];

        // Redirection spécifique pour cet utilisateur
        if ($email === 'ojoslath@gmail.com' && $password === 'admin') {
            header('Location: create_user.php');
        } else {
            header('Location: admin_dashboard.php');
        }
        exit();
    } else {
        $error = "Email ou mot de passe incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Connexion Admin | Groupe1App</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');

    :root {
        --primary-color: #3a7bd5;
        --secondary-color: #00d2ff;
        --background-color: #1a2940;
        --form-background: #ffffff;
        --text-color: #333;
        --placeholder-color: #aaa;
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
      background: var(--background-color);
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    /* --- EFFET ÉTOILES FILANTES --- */
    #background-animation {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        overflow: hidden;
        z-index: 0;
    }
    
    .star {
        position: absolute;
        color: white;
        right: -50px; /* Démarrage hors de l'écran à droite */
        animation-name: animateStar;
        animation-timing-function: linear;
        animation-iteration-count: infinite;
    }

    .star::before {
        content: '\f005'; /* Icône étoile Font Awesome */
        font-family: "Font Awesome 5 Free";
        font-weight: 900;
        font-size: 10px;
        text-shadow: 0 0 8px rgba(255, 255, 255, 0.8);
    }
    
    .star::after {
        content: '';
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        left: 10px;
        width: 150px;
        height: 1px;
        background: linear-gradient(90deg, rgba(255,255,255,0.6), transparent);
    }

    @keyframes animateStar {
        0% { transform: translateX(0); }
        100% { transform: translateX(-120vw); }
    }
    /* --- FIN DE L'EFFET --- */

    .login-container {
      background-color: var(--form-background);
      padding: 40px 30px;
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(0,0,0,0.4);
      z-index: 1;
      width: 90%;
      max-width: 400px;
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.2);
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
      padding: 14px 14px 14px 50px; /* Espace pour l'icône */
      border-radius: 10px;
      border: 1px solid #ddd;
      background-color: #f9f9f9;
      font-size: 16px;
      font-family: 'Poppins', sans-serif;
      transition: all 0.3s;
    }
    
    .input-field:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.2);
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
      background: linear-gradient(120deg, var(--primary-color), var(--secondary-color));
      color: white;
      font-size: 18px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }

    .submit-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(58, 123, 213, 0.4);
    }

    .error {
      color: #e74c3c;
      background-color: rgba(231, 76, 60, 0.1);
      border: 1px solid rgba(231, 76, 60, 0.2);
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 15px;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <div id="background-animation"></div>

  <div class="login-container">
    <img src="http://googleusercontent.com/image_generation_content/0" alt="Logo Groupe1App" class="logo">
    <h2>Espace Administrateur</h2>
    
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
    // --- SCRIPT POUR L'ANIMATION DES ÉTOILES FILANTES ---
    const backgroundAnimation = document.getElementById('background-animation');
    
    setInterval(() => {
        const star = document.createElement('div');
        star.classList.add('star');

        const startY = Math.random() * 100;
        const duration = Math.random() * 2 + 3; // Durée entre 3s et 5s

        star.style.top = `${startY}vh`;
        star.style.animationDuration = `${duration}s`;
        
        backgroundAnimation.appendChild(star);
        
        // Supprime l'étoile après l'animation pour garder la page performante
        setTimeout(() => {
            star.remove();
        }, 5000); // 5s

    }, 400); // Crée une nouvelle étoile toutes les 400ms
  </script>
</body>
</html>