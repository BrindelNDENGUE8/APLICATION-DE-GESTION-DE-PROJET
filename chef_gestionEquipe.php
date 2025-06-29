<?php
// Démarrage de la session au tout début du script
session_start();

// On utilise votre fichier de connexion existant
require_once 'connexion.php';

// =================================================================
// ROUTEUR PRINCIPAL
// =================================================================

// PARTIE 1 : TRAITEMENT DES APPELS API (AJAX)
if (isset($_POST['action'])) {
    
    // --- Sécurité de l'API ---
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef') {
        echo json_encode(['success' => false, 'error' => 'Accès API non autorisé.']);
        exit();
    }
    
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $chef_id = $_SESSION['user_id'];

    // Fonction de vérification des permissions
    function is_chef_of_project($pdo, $chef_id, $project_id) {
        $sql = "SELECT 1 FROM projets WHERE id = ? AND cree_par = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$project_id, $chef_id]);
        return $stmt->fetchColumn() !== false;
    }

    try {
        switch ($action) {
            case 'get_project_data':
                $project_id = $_POST['project_id'] ?? 0;
                if (!is_chef_of_project($pdo, $chef_id, $project_id)) throw new Exception('Permission refusée sur ce projet.');
                
                $sql_team = "SELECT u.id, u.nom, COUNT(t.id) as task_count FROM users u JOIN projet_membres pm ON u.id = pm.utilisateur_id LEFT JOIN taches t ON u.id = t.assigne_a AND t.projet_id = pm.projet_id AND t.statut != 'terminé' WHERE pm.projet_id = ? GROUP BY u.id, u.nom ORDER BY u.nom";
                $stmt_team = $pdo->prepare($sql_team);
                $stmt_team->execute([$project_id]);
                
                $sql_available = "SELECT id, nom FROM users WHERE role = 'membre' AND id NOT IN (SELECT utilisateur_id FROM projet_membres WHERE projet_id = ?)";
                $stmt_available = $pdo->prepare($sql_available);
                $stmt_available->execute([$project_id]);

                $sql_tasks = "SELECT id, nom, assigne_a FROM taches WHERE projet_id = ? ORDER BY nom";
                $stmt_tasks = $pdo->prepare($sql_tasks);
                $stmt_tasks->execute([$project_id]);

                echo json_encode(['success' => true, 'team_members' => $stmt_team->fetchAll(PDO::FETCH_ASSOC), 'available_users' => $stmt_available->fetchAll(PDO::FETCH_ASSOC), 'tasks' => $stmt_tasks->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            case 'add_member':
            case 'remove_member':
                $project_id = $_POST['project_id'];
                $user_id = $_POST['user_id'];
                if (!is_chef_of_project($pdo, $chef_id, $project_id)) throw new Exception('Permission refusée.');
                
                $sql = ($action === 'add_member')
                    ? "INSERT INTO projet_membres (projet_id, utilisateur_id) VALUES (?, ?)"
                    : "DELETE FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$project_id, $user_id]);
                echo json_encode(['success' => true]);
                break;

            case 'add_task':
                $project_id = $_POST['project_id'];
                if (!is_chef_of_project($pdo, $chef_id, $project_id)) throw new Exception('Permission refusée.');
                $sql = "INSERT INTO taches (nom, description, priorite, assigne_a, projet_id) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $assigne_a = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
                $stmt->execute([$_POST['task_name'], $_POST['task_description'], $_POST['task_priority'], $assigne_a, $project_id]);
                echo json_encode(['success' => true]);
                break;

            case 'assign_task':
                $project_id = $_POST['project_id'];
                $task_id = $_POST['task_id'];
                if (!is_chef_of_project($pdo, $chef_id, $project_id)) throw new Exception('Permission refusée.');
                $sql = "UPDATE taches SET assigne_a = ? WHERE id = ? AND projet_id = ?";
                $stmt = $pdo->prepare($sql);
                $assigne_a = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
                $stmt->execute([$assigne_a, $task_id, $project_id]);
                echo json_encode(['success' => true]);
                break;

            // NOUVELLE ACTION POUR SUPPRIMER UNE TÂCHE
            case 'delete_task':
                $project_id = $_POST['project_id'];
                $task_id = $_POST['task_id'];
                if (!is_chef_of_project($pdo, $chef_id, $project_id)) throw new Exception('Permission refusée.');

                $sql = "DELETE FROM taches WHERE id = ? AND projet_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$task_id, $project_id]);
                echo json_encode(['success' => true]);
                break;

            default:
                throw new Exception('Action API non reconnue.');
        }
    } catch (Exception $e) {
        // En cas d'erreur de base de données (ex: contrainte de clé étrangère), un message générique est plus sûr
        if ($e instanceof PDOException) {
            // error_log($e->getMessage()); // Logguer l'erreur réelle pour le débogage
            echo json_encode(['success' => false, 'error' => 'Erreur de base de données. L\'action a peut-être échoué.']);
        } else {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    exit();
}

// PARTIE 2 : TRAITEMENT DE L'ACTION DE DÉCONNEXION
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.php'); // Remplacez par le nom de votre page de connexion principale
    exit();
}

// =================================================================
// PARTIE 3 : LOGIQUE DE CHARGEMENT DE LA PAGE
// =================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef') {
    header('Location: login.php');
    exit();
}

$chef_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'] ?? 'Chef de Projet';

$sql = "SELECT id, nom FROM projets WHERE cree_par = ? ORDER BY nom";
$stmt = $pdo->prepare($sql);
$stmt->execute([$chef_id]);
$projets_geres = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Équipes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-bg: #d46b08; --main-bg: #fdfaf6; --text-light: #fff; --text-dark: #333;
            --accent-color: #a55204; --hover-bg: #a55204; --card-bg: #fff; --border-color: #eee;
            --success-bg: #28a745; --error-bg: #dc3545; --delete-color: #c82333;
        }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: #fdfaf6; display: flex; }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: var(--text-light); height: 100vh; position: fixed; display: flex; flex-direction: column; }
        .sidebar-header { padding: 25px; font-size: 1.5em; text-align: center; border-bottom: 1px solid #ffffff30; }
        .sidebar-nav { flex-grow: 1; list-style: none; padding: 20px 0; margin: 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 15px; color: var(--text-light); text-decoration: none; padding: 15px 25px; transition: background-color 0.3s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background-color: var(--hover-bg); }
        .sidebar-footer { padding: 20px; border-top: 1px solid #ffffff30; text-align: center; }
        .sidebar-footer a { color: var(--text-light); text-decoration: none; }
        
        .main-content { margin-left: 260px; padding: 30px; width: calc(100% - 260px); }
        .content-header h1 { color: #333; margin: 0 0 25px 0; }
        
        .management-container { background-color: var(--card-bg); padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.07); }
        .project-selector { margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        #project-select { font-size: 1.1em; padding: 8px; border-radius: 8px; border: 1px solid #ccc; flex-grow: 1; }
        
        .team-management-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .team-column h3, .tasks-container h3 { border-bottom: 2px solid var(--accent-color); padding-bottom: 10px; margin-top: 0; display: flex; justify-content: space-between; align-items: center; }
        
        .member-list { list-style: none; padding: 0; max-height: 250px; overflow-y: auto; border: 1px solid #f0f0f0; border-radius: 8px; padding: 5px; }
        .member-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-radius: 5px; margin-bottom: 5px; background-color: #f9f9f9; transition: opacity 0.3s; }
        .member-item.processing { opacity: 0.5; pointer-events: none; }
        .member-action-btn { background: none; border: none; cursor: pointer; font-size: 1.2em; }
        .btn-add { color: var(--success-bg); }
        .btn-remove { color: var(--error-bg); }
        .workload-badge { background-color: var(--accent-color); color: white; font-size: 0.8em; padding: 2px 8px; border-radius: 10px; margin-left: 10px; }
        
        .task-assignment-list { list-style: none; padding: 0; }
        /* NOUVEAU : Grille pour aligner nom de tâche, contrôles et bouton supprimer */
        .task-item { display: grid; grid-template-columns: 1fr auto auto; gap: 10px; align-items: center; padding: 15px; border-bottom: 1px solid var(--border-color); }
        
        .assignment-controls { display: flex; align-items: center; gap: 10px; }
        .assignment-controls select { padding: 8px; border-radius: 8px; border: 1px solid #ccc; width: 200px; }
        .unassign-btn { background: none; border: none; color: var(--error-bg); cursor: pointer; font-size: 1.1em; padding: 5px; opacity: 0.7; transition: opacity 0.3s; }
        .unassign-btn:hover { opacity: 1; }

        /* NOUVEAU : Style pour le bouton supprimer la tâche */
        .delete-task-btn { background: none; border: none; color: var(--delete-color); cursor: pointer; font-size: 1.1em; padding: 5px; opacity: 0.7; transition: opacity 0.3s; }
        .delete-task-btn:hover { opacity: 1; }
        
        #add-task-form-container { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px; display: none; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        #add-task-form-container input, #add-task-form-container select, #add-task-form-container textarea { width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 5px; border: 1px solid #ccc; box-sizing: border-box; }
        #add-task-form-container textarea { grid-column: 1 / -1; }
        #add-task-btn { background-color: var(--accent-color); color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer; width: 100%; grid-column: 1 / -1; }
        .btn-toggle-form { background: none; border: 1px solid var(--accent-color); color: var(--accent-color); cursor: pointer; padding: 5px 10px; border-radius: 5px; }

        #loader { text-align: center; padding: 40px; font-size: 1.2em; color: #888; display: none; }
        #loader .fas { animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        #toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 2000; }
        .toast { padding: 15px 20px; color: white; border-radius: 8px; margin-bottom: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); opacity: 0; transform: translateY(20px); transition: all 0.4s ease; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background-color: var(--success-bg); }
        .toast.error { background-color: var(--error-bg); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">CHEF DE PROJET</div>
    <nav><ul class="sidebar-nav">
        <li><a href="chef_dashboard.php"><i class="fas fa-fw fa-tachometer-alt"></i> Tableau de bord</a></li>
        <li><a href="#" class="active"><i class="fas fa-fw fa-users"></i> Gestion Équipes</a></li>
    </ul></nav>
    <div class="sidebar-footer">
        <a href="?action=logout"><i class="fas fa-fw fa-sign-out-alt"></i> Déconnexion</a>
    </div>
</aside>

<main class="main-content">
    <header class="content-header">
        <h1>Gestion des Équipes et des Tâches</h1>
    </header>

    <div class="management-container">
        <div class="project-selector">
            <label for="project-select">Sélectionner un projet :</label>
            <select id="project-select">
                <option value="">-- Choisissez un projet --</option>
                <?php foreach ($projets_geres as $projet): ?>
                    <option value="<?php echo $projet['id']; ?>"><?php echo htmlspecialchars($projet['nom']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="loader"><i class="fas fa-spinner"></i> Chargement...</div>
        <div id="management-content" style="display:none;">
            <div class="team-management-grid">
                <div class="team-column">
                    <h3 id="team-list-header"></h3>
                    <ul class="member-list" id="current-team-list"></ul>
                </div>
                <div class="team-column">
                    <h3 id="available-list-header"></h3>
                    <ul class="member-list" id="available-users-list"></ul>
                </div>
            </div>
            <div class="tasks-container">
                <h3>
                    <span><i class="fas fa-tasks"></i> Assignation des tâches</span>
                    <button class="btn-toggle-form" id="toggle-task-form-btn"><i class="fas fa-plus"></i> Ajouter</button>
                </h3>
                <div id="add-task-form-container">
                    <h4>Créer une nouvelle tâche</h4>
                    <form id="add-task-form">
                        <textarea id="new-task-description" placeholder="Description de la tâche..." rows="3"></textarea>
                        <div class="form-grid">
                            <input type="text" id="new-task-name" placeholder="Nom de la tâche" required>
                            <select id="new-task-assignee"></select>
                            <select id="new-task-priority">
                                <option value="basse">Priorité Basse</option>
                                <option value="moyenne" selected>Priorité Moyenne</option>
                                <option value="haute">Priorité Haute</option>
                            </select>
                        </div>
                        <button type="submit" id="add-task-btn">Créer et Assigner</button>
                    </form>
                </div>
                <ul class="task-assignment-list" id="task-assignment-list"></ul>
            </div>
        </div>
    </div>
</main>

<div id="toast-container"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // JS de base identique
    const projectSelect = document.getElementById('project-select');
    const loader = document.getElementById('loader');
    const managementContent = document.getElementById('management-content');
    const toggleTaskFormBtn = document.getElementById('toggle-task-form-btn');
    const addTaskFormContainer = document.getElementById('add-task-form-container');
    const addTaskForm = document.getElementById('add-task-form');

    function showToast(message, type = 'success') { /* ... fonction identique ... */ 
        const toastContainer = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }
    async function loadProjectData(projectId) { /* ... fonction identique ... */ 
        if (!projectId) {
            managementContent.style.display = 'none';
            return;
        }
        loader.style.display = 'block';
        managementContent.style.display = 'none';
        const result = await apiCall('get_project_data', { project_id: projectId });
        if (result.success) {
            updateTeamLists(result.team_members, result.available_users);
            updateTasksList(result.tasks, result.team_members);
            populateNewTaskAssignee(result.team_members);
            managementContent.style.display = 'block';
        }
        loader.style.display = 'none';
    }
    function updateTeamLists(teamMembers, availableUsers) { /* ... fonction identique ... */ 
        document.getElementById('team-list-header').innerHTML = `<i class="fas fa-users"></i> Membres de l'équipe (${teamMembers.length})`;
        document.getElementById('available-list-header').innerHTML = `<i class="fas fa-user-plus"></i> Utilisateurs disponibles (${availableUsers.length})`;
        const teamList = document.getElementById('current-team-list');
        const availableList = document.getElementById('available-users-list');
        teamList.innerHTML = ''; availableList.innerHTML = '';
        teamMembers.forEach(member => {
            teamList.innerHTML += `<li class="member-item" data-user-id="${member.id}"><span><i class="fas fa-user"></i> ${member.nom} <span class="workload-badge" title="Tâches assignées">${member.task_count}</span></span><button class="member-action-btn btn-remove" title="Retirer"><i class="fas fa-minus-circle"></i></button></li>`;
        });
        availableUsers.forEach(user => {
            availableList.innerHTML += `<li class="member-item" data-user-id="${user.id}"><span><i class="fas fa-user"></i> ${user.nom}</span><button class="member-action-btn btn-add" title="Ajouter"><i class="fas fa-plus-circle"></i></button></li>`;
        });
    }

    // --- MISE À JOUR : AFFICHAGE DE LA LISTE DES TÂCHES ---
    function updateTasksList(tasks, teamMembers) {
        const taskList = document.getElementById('task-assignment-list');
        taskList.innerHTML = '';
        if (tasks.length === 0) {
            taskList.innerHTML = '<li style="text-align:center; padding: 20px; color: #888;">Aucune tâche pour ce projet.</li>';
        } else {
            tasks.forEach(task => {
                let options = '<option value="">Non assignée</option>';
                teamMembers.forEach(member => {
                    options += `<option value="${member.id}" ${task.assigne_a == member.id ? 'selected' : ''}>${member.nom}</option>`;
                });

                const unassignButton = task.assigne_a ? `<button class="unassign-btn" title="Désassigner"><i class="fas fa-user-slash"></i></button>` : '';

                // NOUVEAU : Ajout du bouton de suppression
                const deleteButton = `<button class="delete-task-btn" title="Supprimer la tâche"><i class="fas fa-trash-alt"></i></button>`;

                taskList.innerHTML += `
                    <li class="task-item" data-task-id="${task.id}">
                        <span>${task.nom}</span>
                        <div class="assignment-controls">
                            <select class="task-assign-select">${options}</select>
                            ${unassignButton}
                        </div>
                        ${deleteButton}
                    </li>`;
            });
        }
    }
    
    function populateNewTaskAssignee(teamMembers) { /* ... fonction identique ... */ 
        const select = document.getElementById('new-task-assignee');
        select.innerHTML = '<option value="">Assigner plus tard</option>';
        teamMembers.forEach(member => { select.innerHTML += `<option value="${member.id}">${member.nom}</option>`; });
    }
    
    projectSelect.addEventListener('change', () => loadProjectData(projectSelect.value));

    // --- MISE À JOUR : GESTION DES CLICS SUR LES BOUTONS ---
    managementContent.addEventListener('click', async (e) => {
        // Clic sur "Ajouter/Retirer un membre"
        const memberButton = e.target.closest('.member-action-btn');
        if (memberButton) {
            const item = memberButton.closest('.member-item');
            const userId = item.dataset.userId;
            const action = memberButton.classList.contains('btn-add') ? 'add_member' : 'remove_member';
            item.classList.add('processing'); 
            memberButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; 
            const result = await apiCall(action, { user_id: userId });
            if(result.success) showToast(action === 'add_member' ? 'Membre ajouté' : 'Membre retiré');
            loadProjectData(projectSelect.value); 
            return;
        }

        // Clic sur "Désassigner"
        const unassignButton = e.target.closest('.unassign-btn');
        if (unassignButton) {
            unassignButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            const select = unassignButton.closest('.assignment-controls').querySelector('.task-assign-select');
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        // NOUVEAU : Clic sur "Supprimer la tâche"
        const deleteButton = e.target.closest('.delete-task-btn');
        if (deleteButton) {
            if (confirm('Êtes-vous sûr de vouloir supprimer définitivement cette tâche ?')) {
                deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                const taskItem = deleteButton.closest('.task-item');
                const taskId = taskItem.dataset.taskId;
                
                const result = await apiCall('delete_task', { task_id: taskId });
                if (result.success) {
                    showToast('Tâche supprimée.', 'success');
                    loadProjectData(projectSelect.value); // Recharger les données pour voir le changement
                } else {
                    // L'erreur est déjà affichée par apiCall
                    deleteButton.innerHTML = '<i class="fas fa-trash-alt"></i>'; // Rétablir l'icône en cas d'échec
                }
            }
        }
    });
    
    managementContent.addEventListener('change', async (e) => { /* ... fonction identique ... */ 
        if (e.target.classList.contains('task-assign-select')) {
            e.target.disabled = true;
            const result = await apiCall('assign_task', { 
                task_id: e.target.closest('.task-item').dataset.taskId, 
                user_id: e.target.value 
            });
            if(result.success) {
                const message = e.target.value ? 'Assignation mise à jour.' : 'Tâche désassignée.';
                showToast(message);
                loadProjectData(projectSelect.value);
            } else {
                e.target.disabled = false;
            }
        }
    });
    
    toggleTaskFormBtn.addEventListener('click', () => { /* ... fonction identique ... */
        const container = addTaskFormContainer;
        const isVisible = container.style.display === 'block';
        container.style.display = isVisible ? 'none' : 'block';
        toggleTaskFormBtn.innerHTML = isVisible ? '<i class="fas fa-plus"></i> Ajouter' : '<i class="fas fa-times"></i> Annuler';
    });
    
    addTaskForm.addEventListener('submit', async (e) => { /* ... fonction identique ... */
        e.preventDefault();
        const taskName = document.getElementById('new-task-name').value;
        if (!taskName) { showToast('Le nom de la tâche est obligatoire.', 'error'); return; }
        const result = await apiCall('add_task', {
            task_name: taskName,
            task_description: document.getElementById('new-task-description').value,
            task_priority: document.getElementById('new-task-priority').value,
            user_id: document.getElementById('new-task-assignee').value
        });
        if (result.success) {
            showToast('Tâche créée avec succès.');
            addTaskForm.reset();
            loadProjectData(projectSelect.value);
        }
    });

    async function apiCall(action, data = {}) { /* ... fonction identique ... */
        const projectId = data.project_id || projectSelect.value;
        const formData = new FormData();
        formData.append('action', action);
        formData.append('project_id', projectId);
        for(const key in data) {
            if (key !== 'project_id') {
                formData.append(key, data[key]);
            }
        }
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Erreur réseau');
            const result = await response.json();
            if (!result.success && result.error) {
                showToast(result.error, 'error');
            }
            return result;
        } catch (error) {
            showToast('Erreur de communication.', 'error');
            return { success: false };
        }
    }
});
</script>

</body>
</html>