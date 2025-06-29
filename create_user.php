<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: connexion_admin.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin - Gestion des utilisateurs</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', sans-serif;
    }

    body {
      display: flex;
      min-height: 100vh;
      background: linear-gradient(120deg, #f0f2f5, #e4e9f0);
    }

    .sidebar {
      width: 250px;
      background-color: #1e3c72;
      color: white;
      padding-top: 30px;
      flex-shrink: 0;
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0;
      bottom: 0;
      left: 0;
    }

    .sidebar h2 {
      text-align: center;
      margin-bottom: 30px;
      font-size: 22px;
      text-transform: uppercase;
    }

    .sidebar a {
      padding: 15px 20px;
      text-decoration: none;
      color: white;
      display: flex;
      align-items: center;
      transition: background 0.3s;
    }

    .sidebar a i {
      margin-right: 10px;
    }

    .sidebar a:hover {
      background-color: #16315e;
    }

    .main {
      margin-left: 250px;
      padding: 40px;
      width: 100%;
      overflow-y: auto;
    }

    .main h1 {
      margin-bottom: 20px;
      font-size: 28px;
      color: #1e3c72;
    }

    .section {
      background-color: white;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      margin-bottom: 20px;
    }

    /* Animation simple */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .section {
      animation: fadeIn 0.5s ease;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <h2>Admin</h2>
    <a href="gestion_utilisateurs.php"><i class="fas fa-user-shield"></i> Gestion des utilisateurs</a>
    <a href="gestion_projets.php"><i class="fas fa-folder"></i> Gestion des projets</a>
    <a href="gestion_equipes.php"><i class="fas fa-users"></i> Gestion des équipes</a>
    <a href="supervision_globale.php"><i class="fas fa-chart-line"></i> Supervision</a>
    <a href="controle_ia.php"><i class="fas fa-robot"></i> Contrôle de l'IA</a>
    <a href="sauvegarde_restauration.php"><i class="fas fa-database"></i> Sauvegarde & restauration</a>
    <a href="parametres_admin.php"><i class="fas fa-cog"></i> Paramètres</a>
    <a href="maintenance_securite.php"><i class="fas fa-tools"></i> Maintenance & sécurité</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
  </div>

  <div class="main">
    <h1>Gestion des utilisateurs</h1>
    <div class="section">
      <p>Contenu spécifique à la gestion des utilisateurs (formulaire d'ajout, tableau des utilisateurs, etc.).</p>
    </div>
  </div>

  <script>
    // Effet visuel simple ou notifications futures
    document.addEventListener("DOMContentLoaded", function() {
      console.log("Admin dashboard prêt !");
    });
  </script>
</body>
</html>
