<?php
session_start();
require_once 'connexion.php';

// --- Sécurité et Vérification du Rôle 'chef' ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef') {
    // Si ce n'est pas un chef, on pourrait le rediriger vers son propre tableau de bord ou la page de connexion
    header('Location: login.php'); 
    exit();
}

// --- Données de l'utilisateur connecté ---
$chef_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'] ?? 'Chef de Projet';

// --- Logique pour récupérer les projets gérés par le chef ---
$sql = "
    SELECT DISTINCT p.*, chef.nom as chef_nom,
    (SELECT AVG(t.progression) FROM taches t WHERE t.projet_id = p.id) as project_progress
    FROM projets p
    LEFT JOIN equipes e ON p.id = e.projet_id
    LEFT JOIN users chef ON e.chef_projet_id = chef.id
    WHERE p.cree_par = ? OR e.chef_projet_id = ?
    ORDER BY p.derniere_modification DESC, p.date_creation DESC";
    
$stmt = $pdo->prepare($sql);
$stmt->execute([$chef_id, $chef_id]);
$projets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Calcul des statistiques pour les cartes de résumé ---
$total_projets = count($projets);

$tasks_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM taches t 
    JOIN projets p ON t.projet_id = p.id 
    LEFT JOIN equipes e ON p.id = e.projet_id
    WHERE (p.cree_par = ? OR e.chef_projet_id = ?) AND t.statut != 'terminé' AND t.date_fin_estimee < NOW()");
$tasks_stmt->execute([$chef_id, $chef_id]);
$overdue_tasks = $tasks_stmt->fetchColumn();

// Fonction pour le statut (peut être mise dans un fichier d'aide)
function getProjectStatus($project) {
    $endDate = strtotime($project['date_fin']);
    $today = time();
    $progress = $project['project_progress'] ?? 0;
    if ($progress >= 100) return ['text' => 'Terminé', 'class' => 'status-completed'];
    if ($endDate < $today && $progress < 100) return ['text' => 'En Retard', 'class' => 'status-delayed'];
    return ['text' => 'En Cours', 'class' => 'status-on-track'];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Chef de Projet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-bg: #d46b08; /* Thème orange pour le chef */
            --main-bg: #fdfaf6;
            --text-light: #fff;
            --text-dark: #333;
            --accent-color: #a55204;
            --hover-bg: #a55204;
        }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: var(--main-bg); display: flex; }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: var(--text-light); height: 100vh; position: fixed; display: flex; flex-direction: column; }
        .sidebar-header { padding: 25px; font-size: 1.5em; font-weight: bold; text-align: center; border-bottom: 1px solid #ffffff30; }
        .sidebar-nav { flex-grow: 1; list-style: none; padding: 20px 0; margin: 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 15px; color: var(--text-light); text-decoration: none; padding: 15px 25px; transition: background-color 0.3s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background-color: var(--hover-bg); }
        .sidebar-footer { padding: 20px; border-top: 1px solid #ffffff30; }
        .main-content { margin-left: 260px; padding: 30px; width: calc(100% - 260px); }
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .content-header h1 { color: #333; margin: 0; }
        
        /* --- Cartes de résumé & IA --- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        .summary-card, .ai-card {
            background-color: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.07);
        }
        .ai-card { background: linear-gradient(135deg, #ffcc33, #d46b08); color: white; }
        .ai-card h3 { margin-top: 0; }
        .ai-card p { opacity: 0.9; }
        .btn-predict { background-color: #fff; color: var(--sidebar-bg); border:none; padding: 10px 20px; border-radius: 8px; cursor:pointer; font-weight:bold; }
        
        /* --- Table des projets --- */
        .table-container { background-color: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.07); }
        .project-table { width: 100%; border-collapse: collapse; }
        .project-table th, .project-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .status-tag { padding: 4px 10px; border-radius: 15px; font-weight: bold; font-size: 0.8em; color: #fff; }
        .status-completed { background-color: #17a2b8; }
        .status-delayed { background-color: #dc3545; }
        .status-on-track { background-color: #28a745; }

        /* --- Modale --- */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); }
        .modal-content { background-color: #fff; margin: 15% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; animation: slideIn 0.4s; }
        @keyframes slideIn { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { display:flex; justify-content: space-between; align-items:center; }
        #prediction-result { margin-top: 20px; padding: 15px; border-radius: 8px; background-color: #f8f9fa; display: none; }
        #prediction-result .loader { border: 4px solid #f3f3f3; border-top: 4px solid var(--accent-color); border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 10px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">CHEF DE PROJET</div>
    <nav><ul class="sidebar-nav">
        <li><a href="chef_dashboard.php" class="active"><i class="fas fa-fw fa-tachometer-alt"></i> Tableau de bord</a></li>
        <li><a href="chef_gestionEquipe.php"><i class="fas fa-fw fa-users"></i> Gestion Équipes</a></li>
        <li><a href="historique.php"><i class="fas fa-fw fa-history"></i> Historique</a></li>
    </ul></nav>
    <div class="sidebar-footer">
        <a href="logout.php"><i class="fas fa-fw fa-sign-out-alt"></i> Déconnexion</a>
    </div>
</aside>

<main class="main-content">
    <header class="content-header">
        <h1>Bienvenue, <?php echo htmlspecialchars($user_nom); ?> !</h1>
    </header>

    <div class="dashboard-grid">
        <div class="summary-card">
            <h3>Projets Actifs</h3>
            <p><?php echo $total_projets; ?></p>
        </div>
        <div class="summary-card">
            <h3>Tâches en Retard</h3>
            <p><?php echo $overdue_tasks; ?></p>
        </div>
        <div class="ai-card">
            <h3><i class="fas fa-brain"></i> Assistant IA</h3>
            <p>Obtenez une prédiction sur les risques de retard d'un projet et recevez des conseils.</p>
            <button class="btn-predict" id="open-predict-modal-btn">Lancer une Prédiction</button>
        </div>
    </div>

    <div class="table-container">
        <h2>Vos Projets</h2>
        <table class="project-table">
            <thead><tr><th>Projet</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($projets as $projet): $status = getProjectStatus($projet); ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($projet['nom']); ?></strong></td>
                        <td><span class="status-tag <?php echo $status['class']; ?>"><?php echo $status['text']; ?></span></td>
                        <td><a href="view_project.php?id=<?php echo $projet['id']; ?>">Voir l'avancé du projet <i class="fas fa-arrow-right"></i></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modale de Prédiction IA -->
<div id="prediction-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Prédiction de Retard par IA</h2>
            <span class="close-btn" style="cursor:pointer; font-size:24px;">&times;</span>
        </div>
        <div style="margin-top:20px;">
            <label for="project-select">Choisissez un projet à analyser :</label>
            <select id="project-select" style="width:100%; padding:8px; margin-top:5px;">
                <option value="">-- Sélectionner un projet --</option>
                <?php foreach($projets as $p): ?>
                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nom']); ?></option>
                <?php endforeach; ?>
            </select>
            <button id="get-prediction-btn" style="width:100%; margin-top:15px;" class="btn-predict">Obtenir la Prédiction</button>
        </div>
        <div id="prediction-result">
            <!-- Le résultat de la prédiction s'affichera ici -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('prediction-modal');
    const openModalBtn = document.getElementById('open-predict-modal-btn');
    const openModalLink = document.getElementById('open-predict-modal-link');
    const closeModalBtn = modal.querySelector('.close-btn');
    const getPredictionBtn = document.getElementById('get-prediction-btn');
    const resultDiv = document.getElementById('prediction-result');
    const projectSelect = document.getElementById('project-select');

    function showModal() { modal.style.display = 'block'; }
    function hideModal() { modal.style.display = 'none'; resultDiv.style.display = 'none'; }

    openModalBtn.addEventListener('click', showModal);
    openModalLink.addEventListener('click', showModal);
    closeModalBtn.addEventListener('click', hideModal);
    window.addEventListener('click', (e) => { if(e.target == modal) hideModal(); });

    getPredictionBtn.addEventListener('click', async function() {
        const projectId = projectSelect.value;
        if (!projectId) {
            alert('Veuillez sélectionner un projet.');
            return;
        }

        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div class="loader"></div>'; // Affiche un loader

        try {
            // Appel à l'API de prédiction simulée
            const response = await fetch(`api_predict_delay.php?project_id=${projectId}`);
            const data = await response.json();

            if (data.success) {
                let riskColor = data.prediction_percent > 65 ? 'var(--red)' : (data.prediction_percent > 35 ? 'var(--orange)' : 'var(--green)');
                resultDiv.innerHTML = `
                    <p>Analyse pour : <strong>${projectSelect.options[projectSelect.selectedIndex].text}</strong></p>
                    <p>Risque de retard estimé : <strong style="color:${riskColor}; font-size:1.2em;">${data.prediction_percent}%</strong></p>
                    <hr>
                    <p><strong>Suggestion de l'IA :</strong><br>${data.suggestion}</p>
                `;
            } else {
                resultDiv.innerHTML = `<p>Erreur : ${data.error}</p>`;
            }
        } catch (error) {
            resultDiv.innerHTML = '<p>Erreur de communication avec le serveur.</p>';
        }
    });
});
</script>
</body>
</html>
