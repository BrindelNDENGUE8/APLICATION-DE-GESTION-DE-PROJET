<?php
session_start();
require_once 'connexion.php';

// --- Sécurité et Autorisation ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

// --- Récupération des données pour les filtres ---
$users = $pdo->query("SELECT id, nom FROM users ORDER BY nom")->fetchAll();
$projects = [];
if ($current_user_role === 'admin') {
    $projects = $pdo->query("SELECT id, nom FROM projets ORDER BY nom")->fetchAll();
} else {
    // Les autres utilisateurs ne voient que les projets auxquels ils sont liés
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id, p.nom 
        FROM projets p
        LEFT JOIN equipes e ON p.id = e.projet_id
        LEFT JOIN equipe_membres em ON e.id = em.equipe_id
        WHERE p.cree_par = ? OR e.chef_projet_id = ? OR em.utilisateur_id = ?
        ORDER BY p.nom
    ");
    $stmt->execute([$current_user_id, $current_user_id, $current_user_id]);
    $projects = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Modifications</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(270deg, #ff6ec4, #7873f5, #4adede, #6ee7b7);
            background-size: 800% 800%;
            animation: rainbow 15s ease infinite;
        }
        @keyframes rainbow { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.98);
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        h1 { color: #4b0082; }

        .filters {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }

        .filters > div { flex: 1; }

        .filters label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .filters select {
            width: 100%;
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        #history-table {
            width: 100%;
            border-collapse: collapse;
        }

        #history-table th, #history-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        #history-table th {
            background-color: #f2f2f2;
            color: #333;
        }

        #history-table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        .load-more-container {
            text-align: center;
            padding: 20px 0;
        }

        #load-more-btn {
            background-color: #7873f5;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        #load-more-btn:hover { background-color: #5a54d1; }
        #load-more-btn:disabled { background-color: #ccc; cursor: not-allowed; }

    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>Historique des Modifications</h1>
        <a href="user_dashboard.php" style="text-decoration:none; color:#4b0082;">Retour au Tableau de Bord</a>
    </header>

    <div class="filters">
        <div>
            <label for="project-filter">Filtrer par Projet</label>
            <select id="project-filter">
                <option value="">Tous les projets</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo $project['id']; ?>"><?php echo htmlspecialchars($project['nom']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="user-filter">Filtrer par Utilisateur</label>
            <select id="user-filter">
                <option value="">Tous les utilisateurs</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['nom']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <table id="history-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Utilisateur</th>
                <th>Projet</th>
                <th>Tâche</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <!-- Le contenu sera chargé par JavaScript -->
        </tbody>
    </table>

    <div class="load-more-container">
        <button id="load-more-btn">Charger plus</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.querySelector("#history-table tbody");
    const loadMoreBtn = document.getElementById("load-more-btn");
    const projectFilter = document.getElementById("project-filter");
    const userFilter = document.getElementById("user-filter");

    let currentPage = 1;
    let isLoading = false;

    async function loadHistory(page = 1, replace = false) {
        if (isLoading) return;
        isLoading = true;
        loadMoreBtn.disabled = true;
        loadMoreBtn.textContent = 'Chargement...';

        const projectId = projectFilter.value;
        const userId = userFilter.value;

        // Appel à une API dédiée pour récupérer les données en JSON
        const response = await fetch(`api_history.php?page=${page}&project_id=${projectId}&user_id=${userId}`);
        const data = await response.json();

        if (replace) {
            tableBody.innerHTML = ''; // Effacer le contenu existant si c'est un nouveau filtre
        }
        
        if (data.success && data.history.length > 0) {
            data.history.forEach(entry => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${new Date(entry.date_action).toLocaleString('fr-FR')}</td>
                    <td>${entry.utilisateur_nom}</td>
                    <td>${entry.projet_nom}</td>
                    <td>${entry.tache_nom}</td>
                    <td>${entry.action}</td>
                `;
                tableBody.appendChild(row);
            });
            loadMoreBtn.style.display = 'block'; // S'assurer que le bouton est visible
        } else {
            if (page === 1) { // Si c'est la première page et qu'il n'y a pas de données
                 tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Aucun historique trouvé pour les filtres sélectionnés.</td></tr>';
            }
            loadMoreBtn.style.display = 'none'; // Cacher le bouton s'il n'y a plus rien à charger
        }
        
        isLoading = false;
        loadMoreBtn.disabled = false;
        loadMoreBtn.textContent = 'Charger plus';
        currentPage = page;
    }

    // Gérer le clic sur "Charger plus"
    loadMoreBtn.addEventListener('click', () => {
        loadHistory(currentPage + 1, false);
    });

    // Gérer le changement des filtres
    function handleFilterChange() {
        loadHistory(1, true); // Recharger depuis la page 1 en remplaçant le contenu
    }

    projectFilter.addEventListener('change', handleFilterChange);
    userFilter.addEventListener('change', handleFilterChange);

    // Chargement initial des données
    loadHistory(1);
});
</script>

</body>
</html>
