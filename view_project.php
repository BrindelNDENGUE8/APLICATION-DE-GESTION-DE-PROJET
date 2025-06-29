<?php
session_start();
require_once 'connexion.php';

// =================================================================
// ROUTEUR ET SÉCURITÉ
// =================================================================

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'chef'])) {
    header('Location: login.php');
    exit();
}

$chef_id = $_SESSION['user_id'];
$project_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$project_id) {
    die("ID de projet invalide.");
}

function is_user_project_manager($pdo, $user_id, $project_id)
{
    // Vérifie si l'utilisateur est bien le créateur du projet.
    $stmt = $pdo->prepare("SELECT 1 FROM projets WHERE id = ? AND cree_par = ?");
    $stmt->execute([$project_id, $user_id]);
    return $stmt->fetchColumn() !== false;
}

if ($_SESSION['role'] === 'chef' && !is_user_project_manager($pdo, $chef_id, $project_id)) {
    die("Accès non autorisé à ce projet.");
}

// =================================================================
// HELPERS (Fonctions utilitaires)
// =================================================================

function get_initials($name)
{
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $w) {
        $initials .= mb_substr($w, 0, 1);
    }
    return strtoupper($initials);
}

function get_file_icon_class($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf':
            return 'fas fa-file-pdf';
        case 'doc':
        case 'docx':
            return 'fas fa-file-word';
        case 'xls':
        case 'xlsx':
            return 'fas fa-file-excel';
        case 'png':
        case 'jpg':
        case 'jpeg':
        case 'gif':
            return 'fas fa-file-image';
        default:
            return 'fas fa-file-alt';
    }
}

// =================================================================
// PARTIE API : GÈRE LES ACTIONS AJAX
// =================================================================

if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        switch ($action) {
                // --- Actions de gestion des tâches (existantes) ---
            case 'add_task':
                // ... (Votre logique existante, inchangée)
                break;
            case 'get_task_details':
                // ... (Votre logique existante, inchangée)
                break;
            case 'update_task':
                // ... (Votre logique existante, inchangée)
                break;
            case 'delete_task':
                // ... (Votre logique existante, inchangée)
                break;

                // --- [NOUVEAU] Actions de gestion des soumissions ---
            case 'update_submission_status':
                if ($_SESSION['role'] !== 'chef' && $_SESSION['role'] !== 'admin') {
                    echo json_encode(['success' => false, 'error' => 'Action non autorisée.']);
                    exit();
                }
                $submission_id = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);
                $status = in_array($_POST['status'], ['approuvé', 'rejeté']) ? $_POST['status'] : null;

                if ($submission_id && $status) {
                    $stmt = $pdo->prepare("UPDATE soumissions SET statut = ? WHERE id = ? AND projet_id = ?");
                    $stmt->execute([$status, $submission_id, $project_id]);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Données invalides.']);
                }
                break;

            case 'delete_submission':
                if ($_SESSION['role'] !== 'chef' && $_SESSION['role'] !== 'admin') {
                    echo json_encode(['success' => false, 'error' => 'Action non autorisée.']);
                    exit();
                }
                $submission_id = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);

                if ($submission_id) {
                    // 1. Récupérer le chemin du fichier pour le supprimer du disque
                    $stmt = $pdo->prepare("SELECT fichier_path FROM soumissions WHERE id = ? AND projet_id = ?");
                    $stmt->execute([$submission_id, $project_id]);
                    $path_to_delete = $stmt->fetchColumn();

                    // 2. Supprimer l'enregistrement de la base de données
                    $delete_stmt = $pdo->prepare("DELETE FROM soumissions WHERE id = ?");
                    $delete_stmt->execute([$submission_id]);

                    // 3. Supprimer le fichier physique du serveur
                    if ($path_to_delete && file_exists($path_to_delete)) {
                        unlink($path_to_delete);
                    }

                    echo json_encode(['success' => true, 'message' => 'Soumission supprimée.']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'ID de soumission invalide.']);
                }
                break;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
    }
    exit();
}

// =================================================================
// CHARGEMENT DES DONNÉES INITIALES DE LA PAGE
// =================================================================

$stmt = $pdo->prepare("SELECT * FROM projets WHERE id = ?");
$stmt->execute([$project_id]);
$projet = $stmt->fetch();
if (!$projet) {
    die("Projet non trouvé.");
}

// KPIs
$kpi_stmt = $pdo->prepare("SELECT (SELECT COUNT(*) FROM taches WHERE projet_id = :pid) as total_tasks, (SELECT COUNT(*) FROM taches WHERE projet_id = :pid AND statut = 'terminé') as completed_tasks, (SELECT COUNT(*) FROM taches WHERE projet_id = :pid AND date_fin_estimee < CURRENT_DATE AND statut != 'terminé') as overdue_tasks, (SELECT AVG(progression) FROM taches WHERE projet_id = :pid) as avg_progress FROM projets WHERE id = :pid");
$kpi_stmt->execute([':pid' => $project_id]);
$kpis = $kpi_stmt->fetch();
$days_left = $projet['date_fin'] ? ((new DateTime())->diff(new DateTime($projet['date_fin'])))->invert ? 0 : ((new DateTime())->diff(new DateTime($projet['date_fin'])))->days : 'N/A';

// Tâches
$tasks = $pdo->prepare("SELECT * FROM taches WHERE projet_id = ? ORDER BY date_fin_estimee");
$tasks->execute([$project_id]);
$project_tasks = $tasks->fetchAll(PDO::FETCH_ASSOC);

// Membres de l'équipe
$team_members = $pdo->prepare("SELECT u.id, u.nom, COUNT(t.id) as task_count FROM users u JOIN projet_membres pm ON u.id = pm.utilisateur_id LEFT JOIN taches t ON u.id = t.assigne_a AND t.projet_id = pm.projet_id AND t.statut != 'terminé' WHERE pm.projet_id = ? GROUP BY u.id, u.nom ORDER BY u.nom");
$team_members->execute([$project_id]);
$project_team = $team_members->fetchAll(PDO::FETCH_ASSOC);

// Fichiers soumis
$files_stmt = $pdo->prepare("SELECT s.id, s.fichier_path, s.message, s.date_soumission, s.statut, t.nom as nom_tache, u.nom as nom_utilisateur FROM soumissions s JOIN users u ON s.soumis_par_id = u.id LEFT JOIN taches t ON s.tache_id = t.id WHERE s.projet_id = ? ORDER BY s.date_soumission DESC");
$files_stmt->execute([$project_id]);
$project_files_raw = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

$project_files = array_map(function ($file) {
    $file['initials'] = get_initials($file['nom_utilisateur']);
    $file['icon_class'] = get_file_icon_class($file['fichier_path']);
    return $file;
}, $project_files_raw);


$workload_labels = json_encode(array_column($project_team, 'nom'));
$workload_data = json_encode(array_column($project_team, 'task_count'));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail du Projet: <?= htmlspecialchars($projet['nom']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ================================================================== */
        /* CSS AMÉLIORÉ                                                     */
        /* ================================================================== */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        :root {
            --bg-color: #f8fafc;
            --panel-color: #ffffff;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --accent-color: #4f46e5;
            --accent-hover: #4338ca;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            margin: 0;
        }

        .page-wrapper {
            display: flex;
        }

        .sidebar {
            width: 260px;
            background-color: var(--panel-color);
            border-right: 1px solid var(--border-color);
            height: 100vh;
            position: fixed;
            /* Mettez votre contenu de sidebar ici */
        }

        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 2rem;
        }

        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .card {
            background-color: var(--panel-color);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .card h2 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* KPIs et autres éléments de la page */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background-color: var(--panel-color);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        #kanban-board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 1.5rem;
        }

        .kanban-column {
            background-color: var(--bg-color);
            border-radius: 12px;
            padding: 15px;
        }

        .task-card {
            background-color: var(--panel-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            border-left: 5px solid;
        }

        .priority-haute {
            border-color: var(--danger-color);
        }

        .priority-moyenne {
            border-color: var(--warning-color);
        }

        .priority-basse {
            border-color: var(--success-color);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            overflow: auto;
            /* [MODIFIÉ] */
        }

        .modal-content {
            background-color: var(--panel-color);
            margin: 10% auto;
            padding: 30px;
            border-radius: 12px;
            max-width: 600px;
            position: relative;
            /* [NOUVEAU] */
        }

        /* [NOUVEAU] Styles pour la visionneuse de fichiers */
        .modal-content.large {
            max-width: 90vw;
            /* Plus large pour les documents */
            width: 900px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1rem;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: var(--text-secondary);
            line-height: 1;
        }

        .modal-body {
            height: 70vh;
        }

        #file-preview-content img,
        #file-preview-content iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 8px;
        }

        .no-preview {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
            background-color: var(--bg-color);
            border-radius: 8px;
        }

        .no-preview i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s;
            margin-top: 1.5rem;
        }

        .btn-primary:hover {
            background-color: var(--accent-hover);
        }

        .chart-container-small {
            position: relative;
            height: 300px;
            max-height: 300px;
            padding: 1.5rem;
        }

        /* [NOUVEAU] DESIGN DE LA LISTE DE FICHIERS */
        #project-files-list {
            max-height: 450px;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .file-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 10px;
            transition: all 0.3s ease-in-out;
            border-bottom: 1px solid var(--border-color);
        }

        .file-item.fading-out {
            opacity: 0;
            transform: scale(0.95);
        }

        .file-item:last-child {
            border-bottom: none;
        }

        .file-item:hover {
            background-color: #f9fafb;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .file-details .file-name-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 4px;
        }

        .file-details .file-name {
            font-weight: 500;
            text-decoration: none;
            color: var(--text-primary);
            cursor: pointer;
        }

        .file-details .file-name:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }

        .file-details .file-meta,
        .submission-message {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .submission-message {
            font-style: italic;
            margin-top: 5px;
        }

        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            text-transform: capitalize;
        }

        .status-soumis {
            background-color: #e0e7ff;
            color: #4338ca;
        }

        .status-approuvé {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-rejeté {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .file-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn {
            background: var(--panel-color);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: all 0.2s;
        }

        .action-btn.approve:hover,
        .action-btn.reject:hover,
        .action-btn.delete:hover {
            color: white;
            transform: scale(1.1);
        }

        .action-btn.approve:hover {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .action-btn.reject:hover {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
        }

        .action-btn.delete:hover {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .action-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background-color: var(--bg-color);
            transform: scale(1);
        }

        .empty-state {
            text-align: center;
            color: var(--text-secondary);
            padding: 2rem 0;
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <aside class="sidebar">
            </aside>

        <main class="main-content">
            <div class="header-controls">
                <h1><i class="fas fa-folder-open" style="color: var(--accent-color);"></i> <?= htmlspecialchars($projet['nom']) ?></h1>
                <a href="chef_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <div>
                        <div class="value"><?= round($kpis['avg_progress'] ?? 0) ?>%</div>
                        <div class="label">Progression</div>
                    </div>
                </div>
                <div class="kpi-card">
                    <div>
                        <div class="value"><?= $days_left ?></div>
                        <div class="label">Jours Restants</div>
                    </div>
                </div>
                <div class="kpi-card">
                    <div>
                        <div class="value"><?= $kpis['overdue_tasks'] ?></div>
                        <div class="label">Tâches en Retard</div>
                    </div>
                </div>
                <div class="kpi-card">
                    <div>
                        <div class="value"><?= $kpis['completed_tasks'] ?> / <?= $kpis['total_tasks'] ?></div>
                        <div class="label">Tâches Terminées</div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: flex-start;">
                <div class="card" style="margin:0;">
                    <h2><i class="fas fa-tasks"></i> Tâches du Projet</h2>
                    <div id="kanban-board">
                        <div class="kanban-column">
                            <h3>À Faire</h3>
                            <div class="task-list" data-status="non commencé"></div>
                        </div>
                        <div class="kanban-column">
                            <h3>En Cours</h3>
                            <div class="task-list" data-status="en cours"></div>
                        </div>
                        <div class="kanban-column">
                            <h3>Terminé</h3>
                            <div class="task-list" data-status="terminé"></div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; flex-direction: column; gap: 2rem;">
                    <div class="card" style="margin:0;">
                        <h2><i class="fas fa-chart-pie"></i> Charge de Travail</h2>
                        <div class="chart-container-small">
                            <canvas id="workloadChart"></canvas>
                        </div>
                    </div>

                    <div class="card" style="margin:0;">
                        <h2><i class="fas fa-inbox"></i> Boîte de Réception des Fichiers</h2>
                        <div id="project-files-list"></div>
                    </div>


                </div>
            </div>
        </main>
    </div>

    <div id="task-modal" class="modal">
        </div>

    <div id="file-preview-modal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3 id="file-preview-title">Prévisualisation du fichier</h3>
                <span class="close-btn" id="close-file-preview">&times;</span>
            </div>
            <div class="modal-body" id="file-preview-content">
                </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const initialTasks = <?= json_encode($project_tasks) ?>;
            const teamMembers = <?= json_encode($project_team) ?>;
            const projectFiles = <?= json_encode($project_files) ?>;

            // ==================================================================
            // [NOUVEAU] GESTION DE LA BOÎTE DE RÉCEPTION DES FICHIERS
            // ==================================================================
            function renderProjectFiles() {
                const listContainer = document.getElementById('project-files-list');
                if (!listContainer) return;

                listContainer.innerHTML = ''; // Vider la liste

                if (projectFiles.length === 0) {
                    listContainer.innerHTML = '<p class="empty-state">Aucun fichier n\'a été soumis pour ce projet.</p>';
                    return;
                }

                projectFiles.forEach((file) => {
                    const fileName = file.fichier_path.split('/').pop();
                    const fileElement = document.createElement('div');
                    fileElement.className = 'file-item';
                    fileElement.id = `submission-${file.id}`;

                    let isDecided = file.statut === 'approuvé' || file.statut === 'rejeté';
                    let disabled = isDecided ? 'disabled' : '';

                    fileElement.innerHTML = `
                    <div class="avatar" title="${file.nom_utilisateur}">${file.initials}</div>
                    <div class="file-details">
                        <div class="file-name-status">
                            <a class="file-name file-preview-trigger" data-path="${file.fichier_path}">${fileName}</a>
                            <span class="status-badge status-${file.statut}">${file.statut}</span>
                        </div>
                        <div class="file-meta">
                            Par <strong>${file.nom_utilisateur}</strong> le ${new Date(file.date_soumission).toLocaleDateString('fr-FR')}
                        </div>
                        ${file.message ? `<div class="submission-message">"${file.message}"</div>` : ''}
                    </div>
                    <div class="file-actions">
                        <button class="action-btn approve" data-id="${file.id}" data-status="approuvé" title="Approuver" ${disabled}><i class="fas fa-check"></i></button>
                        <button class="action-btn reject" data-id="${file.id}" data-status="rejeté" title="Rejeter" ${disabled}><i class="fas fa-times"></i></button>
                        <button class="action-btn delete" data-id="${file.id}" data-path="${fileName}" title="Supprimer Définitivement"><i class="fas fa-trash"></i></button>
                    </div>
                `;
                    listContainer.appendChild(fileElement);
                });
            }

            // Gestionnaire d'événements pour les actions (Approuver, Rejeter, Supprimer, Prévisualiser)
            document.getElementById('project-files-list').addEventListener('click', async function(e) {
                const button = e.target.closest('.action-btn');
                const previewTrigger = e.target.closest('.file-preview-trigger');

                // --- Logique pour la prévisualisation ---
                if (previewTrigger) {
                    e.preventDefault();
                    const filePath = previewTrigger.dataset.path;
                    const fileName = previewTrigger.textContent;
                    openFilePreview(filePath, fileName);
                    return;
                }

                if (!button || button.disabled) return;

                const submissionId = button.dataset.id;
                const formData = new URLSearchParams();
                formData.append('submission_id', submissionId);

                // --- Logique pour Approuver/Rejeter ---
                if (button.classList.contains('approve') || button.classList.contains('reject')) {
                    const newStatus = button.dataset.status;
                    formData.append('action', 'update_submission_status');
                    formData.append('status', newStatus);

                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            const badge = document.querySelector(`#submission-${submissionId} .status-badge`);
                            badge.className = `status-badge status-${newStatus}`;
                            badge.textContent = newStatus;
                            document.querySelectorAll(`#submission-${submissionId} .action-btn`).forEach(btn => {
                                if (!btn.classList.contains('delete')) btn.disabled = true;
                            });
                        } else {
                            alert(result.error || 'Une erreur est survenue.');
                        }
                    } catch (error) {
                        console.error(error);
                        alert('Erreur de communication.');
                    }
                }

                // --- Logique pour Supprimer ---
                if (button.classList.contains('delete')) {
                    const filePath = button.dataset.path;
                    if (confirm(`Êtes-vous sûr de vouloir supprimer définitivement la soumission pour "${filePath}" ?\nCette action est irréversible et supprimera aussi le fichier du serveur.`)) {
                        formData.append('action', 'delete_submission');
                        try {
                            const response = await fetch('', {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();
                            if (result.success) {
                                const elementToDelete = document.getElementById(`submission-${submissionId}`);
                                elementToDelete.classList.add('fading-out');
                                setTimeout(() => elementToDelete.remove(), 300); // Laisse le temps à l'animation CSS
                            } else {
                                alert(result.error || 'Une erreur est survenue.');
                            }
                        } catch (error) {
                            console.error(error);
                            alert('Erreur de communication.');
                        }
                    }
                }
            });

            // ==================================================================
            // [NOUVEAU] LOGIQUE DE LA MODALE DE PRÉVISUALISATION
            // ==================================================================
            const filePreviewModal = document.getElementById('file-preview-modal');
            const filePreviewTitle = document.getElementById('file-preview-title');
            const filePreviewContent = document.getElementById('file-preview-content');
            const closeFilePreviewBtn = document.getElementById('close-file-preview');

            // Fonction pour répliquer get_file_icon_class en JS
            function getFileIconClassJS(filename) {
                const ext = filename.split('.').pop().toLowerCase();
                switch (ext) {
                    case 'pdf':
                        return 'fas fa-file-pdf text-danger';
                    case 'doc':
                    case 'docx':
                        return 'fas fa-file-word text-info';
                    case 'xls':
                    case 'xlsx':
                        return 'fas fa-file-excel text-success';
                    case 'png':
                    case 'jpg':
                    case 'jpeg':
                    case 'gif':
                    case 'webp':
                        return 'fas fa-file-image';
                    default:
                        return 'fas fa-file-alt';
                }
            }


            function openFilePreview(path, name) {
                filePreviewTitle.textContent = `Prévisualisation : ${name}`;
                const ext = path.split('.').pop().toLowerCase();
                let contentHTML = '';

                if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) {
                    contentHTML = `<img src="${path}" alt="${name}">`;
                } else if (ext === 'pdf') {
                    contentHTML = `<iframe src="${path}"></iframe>`;
                } else {
                    const iconClass = getFileIconClassJS(name);
                    contentHTML = `
                    <div class="no-preview">
                        <i class="${iconClass}"></i>
                        <p>La prévisualisation n'est pas disponible pour ce type de fichier.</p>
                        <a href="${path}" class="btn-primary" download>Télécharger le fichier</a>
                    </div>`;
                }
                filePreviewContent.innerHTML = contentHTML;
                filePreviewModal.style.display = 'block';
            }

            // Logique pour fermer la modale
            closeFilePreviewBtn.onclick = () => filePreviewModal.style.display = 'none';
            window.onclick = function(event) {
                if (event.target == filePreviewModal) {
                    filePreviewModal.style.display = 'none';
                }
            };


            // ==================================================================
            // VOTRE LOGIQUE EXISTANTE POUR KANBAN ET GRAPHIQUES (INCHANGÉE)
            // ==================================================================

            // Logique du Kanban
            function renderKanban() {
                document.querySelectorAll('.task-list').forEach(col => col.innerHTML = '');
                initialTasks.forEach(task => {
                    const column = document.querySelector(`.task-list[data-status="${task.statut}"]`);
                    if (column) {
                        const taskCard = document.createElement('div');
                        taskCard.className = `task-card priority-${task.priorite}`;
                        taskCard.dataset.taskId = task.id;
                        taskCard.innerHTML = `<strong>${task.nom}</strong><div style="font-size:0.8rem; color: var(--text-secondary); margin-top: 5px;"><i class="fas fa-calendar-alt"></i> ${task.date_fin_estimee || 'N/A'}</div>`;
                        column.appendChild(taskCard);
                    }
                });
            }

            // Logique des modales de tâches
            function populateTaskForm(task = {}) {
                // ... (votre logique de remplissage de la modale de tâche ici)
            }

            // Initialisation du graphique de charge de travail
            const workloadCtx = document.getElementById('workloadChart').getContext('2d');
            new Chart(workloadCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= $workload_labels ?>,
                    datasets: [{
                        label: 'Tâches assignées',
                        data: <?= $workload_data ?>,
                        backgroundColor: ['#4f46e5', '#f59e0b', '#10b981', '#ef4444', '#3b82f6', '#8b5cf6', '#d946ef'],
                        borderColor: 'var(--panel-color)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    family: 'Inter'
                                }
                            }
                        }
                    }
                }
            });

            // APPELS INITIAUX AU CHARGEMENT DE LA PAGE
            renderKanban();
            renderProjectFiles(); // Appel de la fonction de rendu des fichiers
        });
    </script>
</body>

</html>