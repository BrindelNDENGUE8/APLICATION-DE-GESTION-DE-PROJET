<?php
session_start();
require_once 'connexion.php';

// --- Sécurité et Autorisation ---
// Seuls les admins et les chefs peuvent accéder à cette page
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'chef'])) {
    die("Accès refusé. Vous devez être administrateur ou chef de projet pour accéder à cette page.");
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

// --- Mode Édition ou Création ---
$project_id = $_GET['id'] ?? null;
$is_editing = !is_null($project_id);
$project = null;
$team_members = [];

if ($is_editing) {
    // On est en mode édition, on charge les données du projet
    $stmt = $pdo->prepare("SELECT * FROM projets WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();

    if (!$project) {
        die("Projet non trouvé.");
    }
    
    // Vérifier si l'utilisateur a le droit d'éditer CE projet (admin ou créateur/chef)
    $equipe_stmt = $pdo->prepare("SELECT chef_projet_id FROM equipes WHERE projet_id = ?");
    $equipe_stmt->execute([$project_id]);
    $chef_id = $equipe_stmt->fetchColumn();

    if ($current_user_role !== 'admin' && $project['cree_par'] !== $current_user_id && $chef_id !== $current_user_id) {
        die("Accès refusé. Vous n'êtes pas autorisé à modifier ce projet.");
    }
    
    // Charger les membres de l'équipe existante
    $members_stmt = $pdo->prepare("
        SELECT em.utilisateur_id FROM equipe_membres em
        JOIN equipes e ON em.equipe_id = e.id
        WHERE e.projet_id = ?
    ");
    $members_stmt->execute([$project_id]);
    $team_members_raw = $members_stmt->fetchAll(PDO::FETCH_COLUMN);
    $team_members = array_flip($team_members_raw); // Mettre les ID en clés pour une recherche facile
}


// --- Traitement du Formulaire (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'];
    $description = $_POST['description'];
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $chef_projet_id = $_POST['chef_projet_id'];
    $membres_ids = $_POST['membres'] ?? [];

    $pdo->beginTransaction();
    try {
        if ($is_editing) {
            // --- Mise à jour du projet existant ---
            $stmt = $pdo->prepare("UPDATE projets SET nom = ?, description = ?, date_debut = ?, date_fin = ?, modifie_par = ?, derniere_modification = NOW() WHERE id = ?");
            $stmt->execute([$nom, $description, $date_debut, $date_fin, $current_user_id, $project_id]);
            $current_project_id = $project_id;
        } else {
            // --- Création d'un nouveau projet ---
            $stmt = $pdo->prepare("INSERT INTO projets (nom, description, date_debut, date_fin, cree_par, modifie_par, derniere_modification) VALUES (?, ?, ?, ?, ?, ?, NOW()) RETURNING id");
            $stmt->execute([$nom, $description, $date_debut, $date_fin, $current_user_id, $current_user_id]);
            $current_project_id = $stmt->fetchColumn();
        }
        
        // --- Gestion de l'équipe ---
        // On vérifie si une équipe existe déjà pour ce projet
        $equipe_stmt = $pdo->prepare("SELECT id FROM equipes WHERE projet_id = ?");
        $equipe_stmt->execute([$current_project_id]);
        $equipe_id = $equipe_stmt->fetchColumn();
        
        if ($equipe_id) {
            // L'équipe existe, on met à jour le chef
            $update_equipe = $pdo->prepare("UPDATE equipes SET chef_projet_id = ? WHERE id = ?");
            $update_equipe->execute([$chef_projet_id, $equipe_id]);
        } else {
            // L'équipe n'existe pas, on la crée
            $insert_equipe = $pdo->prepare("INSERT INTO equipes (nom_equipe, projet_id, chef_projet_id) VALUES (?, ?, ?) RETURNING id");
            $insert_equipe->execute(["Équipe pour " . $nom, $current_project_id, $chef_projet_id]);
            $equipe_id = $insert_equipe->fetchColumn();
        }
        
        // Mettre à jour les membres : on supprime les anciens et on insère les nouveaux
        $delete_membres = $pdo->prepare("DELETE FROM equipe_membres WHERE equipe_id = ?");
        $delete_membres->execute([$equipe_id]);

        if (!empty($membres_ids)) {
            $insert_membre_stmt = $pdo->prepare("INSERT INTO equipe_membres (equipe_id, utilisateur_id) VALUES (?, ?)");
            foreach ($membres_ids as $membre_id) {
                $insert_membre_stmt->execute([$equipe_id, $membre_id]);
            }
        }
        
        $pdo->commit();
        header('Location: user_dashboard.php');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur lors de la sauvegarde du projet: " . $e->getMessage());
    }
}


// Récupérer tous les utilisateurs pour les listes de sélection
$users_stmt = $pdo->query("SELECT id, nom, role FROM users ORDER BY nom");
$all_users = $users_stmt->fetchAll();

$chefs = array_filter($all_users, fn($user) => in_array($user['role'], ['admin', 'chef']));
$membres = array_filter($all_users, fn($user) => $user['role'] === 'membre');

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_editing ? 'Modifier le Projet' : 'Créer un Projet'; ?></title>
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
            max-width: 800px;
            margin: 30px auto;
            padding: 30px;
            background-color: rgba(255, 255, 255, 0.98);
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        }
        h1 { color: #4b0082; text-align: center; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        input[type="text"], input[type="date"], textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
        }
        textarea { resize: vertical; min-height: 100px; }
        .team-selection {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
        }
        .team-selection h3 { margin-top: 0; color: #7873f5; }
        .members-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #eee;
            padding: 10px;
            border-radius: 5px;
        }
        .members-list div { margin-bottom: 5px; }
        .button-group { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; }
        button[type="submit"] {
            background-color: #4b0082;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        button[type="submit"]:hover { background-color: #36005c; }
        .cancel-link {
            color: #555;
            text-decoration: none;
            font-size: 16px;
        }
        .cancel-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $is_editing ? 'Modifier le Projet' : 'Créer un Nouveau Projet'; ?></h1>
        
        <form method="POST">
            <div class="form-group">
                <label for="nom">Nom du Projet</label>
                <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($project['nom'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="date_debut">Date de début</label>
                <input type="date" id="date_debut" name="date_debut" value="<?php echo htmlspecialchars($project['date_debut'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="date_fin">Date de fin</label>
                <input type="date" id="date_fin" name="date_fin" value="<?php echo htmlspecialchars($project['date_fin'] ?? ''); ?>" required>
            </div>

            <div class="form-group team-selection">
                <h3>Gestion de l'Équipe</h3>
                <div>
                    <label for="chef_projet_id">Chef de Projet</label>
                    <select id="chef_projet_id" name="chef_projet_id" required>
                        <option value="">-- Sélectionner un chef --</option>
                        <?php foreach($chefs as $chef): ?>
                            <option value="<?php echo $chef['id']; ?>" <?php echo (($project && ($chef_id ?? null) == $chef['id'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($chef['nom']); ?> (<?php echo htmlspecialchars($chef['role']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-top: 15px;">
                    <label>Membres de l'équipe</label>
                    <div class="members-list">
                        <?php foreach($membres as $membre): ?>
                            <div>
                                <input type="checkbox" name="membres[]" value="<?php echo $membre['id']; ?>" id="membre-<?php echo $membre['id']; ?>"
                                <?php echo isset($team_members[$membre['id']]) ? 'checked' : ''; ?>>
                                <label for="membre-<?php echo $membre['id']; ?>" style="font-weight:normal; display:inline;">
                                    <?php echo htmlspecialchars($membre['nom']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <a href="user_dashboard.php" class="cancel-link">Annuler</a>
                <button type="submit"><?php echo $is_editing ? 'Mettre à jour le Projet' : 'Créer le Projet'; ?></button>
            </div>
        </form>
    </div>
</body>
</html>