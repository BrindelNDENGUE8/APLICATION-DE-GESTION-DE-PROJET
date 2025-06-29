<?php
session_start();
require_once 'connexion.php';

// =================================================================
// SÉCURITÉ ET INITIALISATION
// =================================================================

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$project_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$project_id) {
    die(json_encode(['error' => 'ID de projet invalide.']));
}

// --- Fonctions de Vérification des permissions ---
function is_user_project_member($pdo, $user_id, $project_id) {
    $stmt = $pdo->prepare("SELECT 1 FROM projet_membres WHERE projet_id = ? AND utilisateur_id = ?");
    $stmt->execute([$project_id, $user_id]);
    return $stmt->fetchColumn() !== false;
}

function is_user_project_manager($pdo, $user_id, $project_id) {
    $stmt = $pdo->prepare("SELECT 1 FROM projets WHERE id = ? AND cree_par = ?");
    $stmt->execute([$project_id, $user_id]);
    return $stmt->fetchColumn() !== false;
}

if (!is_user_project_member($pdo, $user_id, $project_id)) {
    die(json_encode(['error' => 'Accès non autorisé à ce projet.']));
}

// --- Gestion des chemins ---
$project_root_path = 'projets_stockes/' . $project_id;
if (!is_dir($project_root_path)) {
    mkdir($project_root_path, 0755, true);
}

$current_relative_path = trim(filter_input(INPUT_GET, 'path', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '', '/');
$current_absolute_path = realpath($project_root_path . '/' . $current_relative_path);

if ($current_absolute_path === false || strpos($current_absolute_path, realpath($project_root_path)) !== 0) {
    die(json_encode(['error' => 'Chemin invalide ou non autorisé.']));
}

// --- Fonctions de Journalisation ---
function log_action($pdo, $user_id, $project_id, $action, $tache_id = null) {
    $stmt = $pdo->prepare("INSERT INTO historique (projet_id, utilisateur_id, action, tache_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$project_id, $user_id, $action, $tache_id]);
}

function log_error($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, 'logs/errors.log');
}

// --- Calcul du chemin parent ---
$parent_path = dirname($current_relative_path);
$parent_path = ($parent_path === '.' || $parent_path === '/') ? '' : $parent_path;
$parent_url = "?id=$project_id" . ($parent_path ? "&path=" . urlencode($parent_path) : '');

// =================================================================
// PARTIE LECTURE DES DONNÉES (AVANT TRAITEMENT POST)
// =================================================================
$stmt = $pdo->prepare("SELECT nom, description, cree_par FROM projets WHERE id = ?");
$stmt->execute([$project_id]);
$projet = $stmt->fetch();
if (!$projet) {
    die(json_encode(['error' => 'Projet non trouvé.']));
}
$project_manager_id = $projet['cree_par'];

// =================================================================
// PARTIE TRAITEMENT DES FORMULAIRES (ACTIONS)
// =================================================================
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'save_file':
            $filename = basename(filter_input(INPUT_POST, 'filename', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $file_to_save = $current_absolute_path;
            if (is_writable($file_to_save)) {
                file_put_contents($file_to_save, $_POST['content']);
                $message = '<div class="message success">Fichier sauvegardé avec succès.</div>';
                log_action($pdo, $user_id, $project_id, "Sauvegarde du fichier: $filename");
            } else {
                $message = '<div class="message error">Erreur : Impossible de sauvegarder. Vérifiez les permissions.</div>';
            }
            break;

        case 'upload_files':
            if (isset($_FILES['files'])) {
                $upload_success = true;
                foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                        $target_path = $current_absolute_path . '/' . basename($_FILES['files']['name'][$key]);
                        if (move_uploaded_file($tmp_name, $target_path)) {
                            log_action($pdo, $user_id, $project_id, "Upload du fichier " . basename($target_path));
                        } else {
                            $upload_success = false;
                        }
                    }
                }
                $message = $upload_success ? '<div class="message success">Fichiers importés.</div>' : '<div class="message error">Erreur d\'importation.</div>';
            }
            break;

        case 'create_item':
            $item_name = basename(filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $item_type = $_POST['item_type'];
            $new_item_path = $current_absolute_path . '/' . $item_name;
            if (!file_exists($new_item_path)) {
                if ($item_type === 'file') {
                    file_put_contents($new_item_path, '');
                } else {
                    mkdir($new_item_path);
                }
                $message = '<div class="message success">Élément créé.</div>';
                log_action($pdo, $user_id, $project_id, "Création de $item_type: $item_name");
            } else {
                $message = '<div class="message error">Ce nom existe déjà.</div>';
            }
            break;

        case 'delete_item':
            $item_name = basename(filter_input(INPUT_POST, 'item_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $item_path = $current_absolute_path . '/' . $item_name;
            if (file_exists($item_path)) {
                if (is_dir($item_path)) {
                    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($item_path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                    foreach ($iterator as $file) {
                        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                    }
                    rmdir($item_path);
                } else {
                    unlink($item_path);
                }
                $message = '<div class="message success">Élément supprimé.</div>';
                log_action($pdo, $user_id, $project_id, "Suppression de l'élément: $item_name");
            }
            break;

        case 'rename_item':
            $old_name = basename(filter_input(INPUT_POST, 'old_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $new_name = basename(filter_input(INPUT_POST, 'new_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $old_path = $current_absolute_path . '/' . $old_name;
            $new_path = $current_absolute_path . '/' . $new_name;
            if (file_exists($old_path) && !file_exists($new_path)) {
                if (rename($old_path, $new_path)) {
                    $message = '<div class="message success">Élément renommé.</div>';
                    log_action($pdo, $user_id, $project_id, "Renommage de $old_name en $new_name");
                }
            } else {
                $message = '<div class="message error">Erreur lors du renommage.</div>';
            }
            break;

        case 'submit_file_to_manager':
            $item_path = filter_input(INPUT_POST, 'item_path', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $message_text = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $tache_id = filter_input(INPUT_POST, 'tache_id', FILTER_VALIDATE_INT) ?: null;

            if ($project_manager_id) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO soumissions (projet_id, tache_id, fichier_path, message, soumis_par_id, destinataire_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$project_id, $tache_id, $item_path, $message_text, $user_id, $project_manager_id]);
                    log_action($pdo, $user_id, $project_id, "Soumission du fichier : " . basename($item_path), $tache_id);
                    $message = '<div class="message success">Fichier soumis au chef de projet avec succès.</div>';
                } catch (PDOException $e) {
                    $message = '<div class="message error">Erreur lors de la soumission du fichier.</div>';
                    log_error("Erreur de soumission DB: " . $e->getMessage());
                }
            } else {
                $message = '<div class="message error">Impossible de trouver le chef de projet pour la soumission.</div>';
            }
            break;
    }
}

// =================================================================
// PARTIE LECTURE DES DONNÉES (POUR AFFICHAGE)
// =================================================================
$stmt = $pdo->prepare("SELECT id, nom FROM taches WHERE projet_id = ?");
$stmt->execute([$project_id]);
$taches = $stmt->fetchAll();

$items = [];
if (is_dir($current_absolute_path)) {
    foreach (scandir($current_absolute_path) as $item) {
        if ($item === '.' || $item === '..') continue;
        $items[] = ['name' => $item, 'type' => is_dir($current_absolute_path . '/' . $item) ? 'folder' : 'file'];
    }
}

$preview_content = null;
if (isset($_GET['preview']) && isset($_GET['path']) && is_file($current_absolute_path)) {
    $ext = strtolower(pathinfo($current_absolute_path, PATHINFO_EXTENSION));
    if (in_array($ext, ['txt', 'md', 'php', 'html', 'css', 'js', 'json', 'xml'])) {
        $preview_content = '<pre><code>' . htmlspecialchars(file_get_contents($current_absolute_path)) . '</code></pre>';
    } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'])) {
        $image_relative_path = 'projets_stockes/' . $project_id . '/' . $current_relative_path;
        $preview_content = '<img src="' . htmlspecialchars($image_relative_path) . '" style="max-width: 100%; border-radius: 8px;">';
    } else {
        $preview_content = '<p>Aperçu non disponible pour ce type de fichier.</p>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projet: <?= htmlspecialchars($projet['nom']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5; --danger: #dc2626; --success: #16a34a; --border: #e5e7eb;
            --background: #f9fafb; --text: #1f2937; --card-bg: #fff;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--background); color: var(--text); margin: 0; padding: 1rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .btn { background-color: var(--primary); color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 8px; border: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .btn-danger { background-color: var(--danger); }
        .btn-success { background-color: var(--success); }
        .card { border: 1px solid var(--border); border-radius: 12px; background: var(--card-bg); box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-top: 2rem; }
        .card h2 { padding: 1rem 1.5rem; margin: 0; border-bottom: 1px solid var(--border); font-size: 1.1rem; }
        .card-content { padding: 1.5rem; }
        .file-browser { border: 1px solid var(--border); border-radius: 12px; background: var(--card-bg); }
        .browser-header { padding: 1rem; border-bottom: 1px solid var(--border); }
        .file-list { list-style: none; padding: 0; margin: 0; }
        .file-item { display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); }
        .file-item:last-child { border-bottom: none; }
        .file-item a { text-decoration: none; color: var(--text); font-weight: 500; }
        .file-actions { margin-left: auto; display: flex; gap: 0.5rem; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal.active { display: flex; }
        .modal-content { background: var(--card-bg); padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%; }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .message.success { background-color: #dcfce7; color: #15803d; }
        .message.error { background-color: #fee2e2; color: #b91c1c; }
        textarea, select, input[type="text"], input[type="file"] { width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 1rem; box-sizing: border-box; }
        pre { background-color: #f3f4f6; padding: 1rem; border-radius: 8px; white-space: pre-wrap; word-wrap: break-word; }
        /* ======================================================= */
/* ==== CSS AMÉLIORÉ POUR EMBELLIR TOUS LES BOUTONS ====== */
/* ======================================================= */

/* --- Style de base pour tous les boutons --- */
.btn {
    padding: 0.6rem 1.2rem;          /* Espacement intérieur plus généreux */
    border: none;
    border-radius: 8px;
    font-weight: 600;               /* Police un peu plus affirmée */
    cursor: pointer;
    transition: all 0.25s ease-out; /* Transition douce pour tous les effets */
    text-decoration: none;
    display: inline-flex;           /* Permet d'aligner parfaitement icône et texte */
    align-items: center;
    justify-content: center;
    gap: 0.6rem;                    /* Espace entre l'icône et le texte */
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08); /* Ombre subtile pour un effet de profondeur */
    text-transform: none;           /* Assure que le texte n'est pas en majuscules */
}

/* --- Effet au survol --- */
.btn:hover {
    transform: translateY(-3px);    /* Fait "flotter" le bouton */
    box-shadow: 0 7px 14px rgba(0, 0, 0, 0.12); /* Ombre plus prononcée */
}

/* --- Effet au clic --- */
.btn:active {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
}

/* --- Couleurs spécifiques --- */

/* Bouton primaire (utilisé par défaut) */
.btn {
    background-color: var(--primary);
    color: white;
}
.btn:hover {
    background-color: #4338ca; /* Une teinte de --primary un peu plus foncée */
}

/* Bouton de succès (Envoyer) */
.btn-success {
    background-color: var(--success);
    color: white;
}
.btn-success:hover {
    background-color: #15803d; /* Une teinte de --success un peu plus foncée */
}

/* Bouton de danger (Supprimer) */
.btn-danger {
    background-color: var(--danger);
    color: white;
}
.btn-danger:hover {
    background-color: #b91c1c; /* Une teinte de --danger un peu plus foncée */
}
    </style>
</head>
<body>
<div class="container">
<div style="margin-bottom: 2rem;">
    <a href="user_dashboard.php" class="btn btn-secondary" style="width:auto;">
        ← Retour au dashboard
    </a>
    <div class="header">
        <h1><i class="fas fa-folder" style="color: var(--primary);"></i> <?= htmlspecialchars($projet['nom']) ?></h1>
    </div>

    <?php if ($message): ?>
        <?= $message ?>
    <?php endif; ?>

    <div class="file-browser">
        <div class="browser-header">
            <a href="?id=<?= $project_id ?>">Racine</a> /
            <?php
            $path_parts = explode('/', $current_relative_path);
            $cumulative_path = '';
            foreach ($path_parts as $part) {
                if (empty($part)) continue;
                $cumulative_path .= $part . '/';
                echo '<a href="?id=' . $project_id . '&path=' . urlencode($cumulative_path) . '">' . htmlspecialchars($part) . '</a> / ';
            }
            ?>
        </div>
        <ul class="file-list">
            <?php foreach ($items as $item): ?>
            <li class="file-item">
                <i class="fas <?= $item['type'] === 'folder' ? 'fa-folder' : 'fa-file-alt' ?>"></i>
                <a href="?id=<?= $project_id ?>&path=<?= urlencode($current_relative_path . '/' . $item['name']) ?>"><?= htmlspecialchars($item['name']) ?></a>
                <div class="file-actions">
                    <?php if ($item['type'] === 'file' && $user_id != $project_manager_id): ?>
                        <button class="btn btn-success" onclick="showSubmitFileModal('<?= htmlspecialchars($current_relative_path . '/' . $item['name']) ?>')"><i class="fas fa-paper-plane"></i> Envoyer</button>
                    <?php endif; ?>
                    <a href="?id=<?= $project_id ?>&path=<?= urlencode($current_relative_path . '/' . $item['name']) ?>&preview=1" class="btn">Aperçu</a>
                    <button class="btn" onclick="showRenameModal('<?= htmlspecialchars($item['name']) ?>')">Renommer</button>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Vraiment supprimer <?= htmlspecialchars($item['name']) ?> ?');">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="item_name" value="<?= htmlspecialchars($item['name']) ?>">
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <?php if (isset($_GET['path']) && !is_dir($current_absolute_path)): ?>
        <?php if (isset($_GET['preview'])): ?>
            <div class="card preview-viewer">
                <h2>Aperçu de <?= htmlspecialchars(basename($current_relative_path)) ?></h2>
                <div class="card-content">
                    <?= $preview_content ?? '<p>Aperçu non disponible.</p>' ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card file-editor">
                <h2>Éditeur: <?= htmlspecialchars(basename($current_relative_path)) ?></h2>
                <div class="card-content">
                    <form method="post">
                        <input type="hidden" name="action" value="save_file">
                        <input type="hidden" name="filename" value="<?= htmlspecialchars(basename($current_relative_path)) ?>">
                        <textarea name="content" style="min-height: 400px;"><?= htmlspecialchars(file_get_contents($current_absolute_path)) ?></textarea>
                        <button type="submit" class="btn">Sauvegarder les modifications</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="card">
        <h2>Actions Rapides</h2>
        <div class="card-content" style="display:flex; gap: 2rem;">
            <form method="post" style="flex:1;">
                <input type="hidden" name="action" value="create_item">
                <label>Créer un nouvel élément :</label>
                <input type="text" name="item_name" placeholder="Nom du fichier/dossier..." required>
                <select name="item_type">
                    <option value="file">Fichier</option>
                    <option value="folder">Dossier</option>
                </select>
                <button type="submit" class="btn">Créer</button>
            </form>
            <form method="post" enctype="multipart/form-data" style="flex:1;">
                <input type="hidden" name="action" value="upload_files">
                <label>Importer un ou plusieurs fichiers :</label>
                <input type="file" name="files[]" multiple required>
                <button type="submit" class="btn">Importer</button>
            </form>
        </div>
    </div>
</div>

<div id="renameModal" class="modal">
    <div class="modal-content">
        <h2>Renommer l'élément</h2>
        <form method="post">
            <input type="hidden" name="action" value="rename_item">
            <input type="hidden" id="renameOldName" name="old_name">
            <input type="text" id="renameNewName" name="new_name" required>
            <div style="display:flex; justify-content:flex-end; gap:1rem; margin-top:1rem;">
                <button type="button" class="btn" onclick="hideRenameModal()">Annuler</button>
                <button type="submit" class="btn btn-success">Renommer</button>
            </div>
        </form>
    </div>
</div>

<div id="submitFileModal" class="modal">
    <div class="modal-content">
        <h2>Envoyer un fichier au Chef de Projet</h2>
        <p>Fichier : <strong id="fileNameToSubmit"></strong></p>
        <form method="post">
            <input type="hidden" name="action" value="submit_file_to_manager">
            <input type="hidden" name="item_path" id="submitItemPath">
            <label for="tache_id_submit">Associer à une tâche (optionnel) :</label>
            <select name="tache_id" id="tache_id_submit">
                <option value="">Aucune tâche</option>
                <?php foreach ($taches as $tache): ?>
                    <option value="<?= $tache['id'] ?>"><?= htmlspecialchars($tache['nom']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="message_submit">Message (optionnel) :</label>
            <textarea name="message" id="message_submit" placeholder="Ajoutez un message..."></textarea>
            <div style="display:flex; justify-content:flex-end; gap:1rem; margin-top:1rem;">
                <button type="button" class="btn" onclick="hideSubmitFileModal()">Annuler</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Confirmer</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRenameModal(oldName) {
    document.getElementById('renameOldName').value = oldName;
    document.getElementById('renameNewName').value = oldName;
    document.getElementById('renameModal').classList.add('active');
}
function hideRenameModal() {
    document.getElementById('renameModal').classList.remove('active');
}

function showSubmitFileModal(itemPath) {
    const fileName = itemPath.split('/').pop(); 
    document.getElementById('fileNameToSubmit').innerText = fileName;
    document.getElementById('submitItemPath').value = itemPath;
    document.getElementById('submitFileModal').classList.add('active');
}
function hideSubmitFileModal() {
    document.getElementById('submitFileModal').classList.remove('active');
}

window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        hideRenameModal();
        hideSubmitFileModal();
    }
});
</script>
</body>
</html>