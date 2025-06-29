<?php
session_start();
require_once 'connexion.php';

// ===================================================================================
// PARTIE 1 : LOGIQUE PHP POUR LES ÉQUIPES (INCHANGÉE)
// ===================================================================================

$message = '';
$team_to_edit = null;

// --- Récupération des données nécessaires pour les formulaires ---
$projects_stmt = $pdo->query("SELECT id, nom FROM projets WHERE id NOT IN (SELECT projet_id FROM equipes WHERE projet_id IS NOT NULL) ORDER BY nom ASC");
$available_projects = $projects_stmt->fetchAll(PDO::FETCH_ASSOC);

$users_stmt = $pdo->query("SELECT id, nom, role FROM users ORDER BY nom ASC");
$all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$chefs_de_projet = array_filter($all_users, fn($user) => $user['role'] === 'chef');


// --- TRAITEMENT DE L'AJOUT / MODIFICATION D'UNE ÉQUIPE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_team'])) {
    $nom_equipe = trim($_POST['nom_equipe']);
    $projet_id = !empty($_POST['projet_id']) ? $_POST['projet_id'] : null;
    $chef_projet_id = !empty($_POST['chef_projet_id']) ? $_POST['chef_projet_id'] : null;
    $membres = $_POST['membres'] ?? [];
    $id = $_POST['id'] ?? null;

    if (empty($nom_equipe)) {
        $message = '<div class="message error">Le nom de l\'équipe est requis.</div>';
    } else {
        $pdo->beginTransaction();
        try {
            if (!empty($id)) {
                $sql = "UPDATE equipes SET nom_equipe = :nom_equipe, projet_id = :projet_id, chef_projet_id = :chef_projet_id WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nom_equipe' => $nom_equipe, ':projet_id' => $projet_id, ':chef_projet_id' => $chef_projet_id, ':id' => $id]);
                $equipe_id = $id;
                $_SESSION['message'] = '<div class="message success">Équipe mise à jour avec succès !</div>';
            }
            else {
                $sql = "INSERT INTO equipes (nom_equipe, projet_id, chef_projet_id) VALUES (:nom_equipe, :projet_id, :chef_projet_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nom_equipe' => $nom_equipe, ':projet_id' => $projet_id, ':chef_projet_id' => $chef_projet_id]);
                $equipe_id = $pdo->lastInsertId();
                $_SESSION['message'] = '<div class="message success">Équipe créée avec succès !</div>';
            }

            $stmt_delete_members = $pdo->prepare("DELETE FROM equipe_membres WHERE equipe_id = :equipe_id");
            $stmt_delete_members->execute([':equipe_id' => $equipe_id]);

            if (!empty($membres)) {
                $stmt_add_member = $pdo->prepare("INSERT INTO equipe_membres (equipe_id, utilisateur_id) VALUES (:equipe_id, :utilisateur_id)");
                foreach ($membres as $membre_id) {
                    $stmt_add_member->execute([':equipe_id' => $equipe_id, ':utilisateur_id' => $membre_id]);
                }
            }

            $pdo->commit();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '<div class="message error">Erreur : ' . $e->getMessage() . '</div>';
        }
    }
}


// --- TRAITEMENT DE LA SUPPRESSION ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM equipes WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $_SESSION['message'] = '<div class="message success">Équipe supprimée.</div>';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- PRÉPARATION POUR LA MODIFICATION ---
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $sql = "SELECT * FROM equipes WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $team_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

    if($team_to_edit) {
        $stmt_members = $pdo->prepare("SELECT utilisateur_id FROM equipe_membres WHERE equipe_id = :id");
        $stmt_members->execute([':id' => $id]);
        $team_to_edit['membres'] = $stmt_members->fetchAll(PDO::FETCH_COLUMN);

        if ($team_to_edit['projet_id']) {
            $current_project_stmt = $pdo->prepare("SELECT id, nom FROM projets WHERE id = :id");
            $current_project_stmt->execute(['id' => $team_to_edit['projet_id']]);
            $current_project = $current_project_stmt->fetch(PDO::FETCH_ASSOC);
            if($current_project) {
                array_unshift($available_projects, $current_project);
            }
        }
    }
}


// --- LECTURE & RECHERCHE DES ÉQUIPES ---
$search_query = "
    SELECT 
        e.id, e.nom_equipe,
        p.nom as projet_nom, p.date_debut, p.date_fin,
        u.nom as chef_nom,
        (SELECT COUNT(*) FROM equipe_membres em WHERE em.equipe_id = e.id) as nombre_membres
    FROM equipes e
    LEFT JOIN projets p ON e.projet_id = p.id
    LEFT JOIN users u ON e.chef_projet_id = u.id
    ORDER BY e.date_creation DESC";
$teams = $pdo->query($search_query)->fetchAll(PDO::FETCH_ASSOC);


if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Équipes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        (function() { const theme = localStorage.getItem('theme'); if (theme === 'dark') { document.documentElement.classList.add('dark-mode'); } })();
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        /* AJOUT de la variable --animation-color */
        :root { --bg-color: #f4f7f9; --panel-color: #ffffff; --border-color: #e2e8f0; --text-primary: #1a202c; --text-secondary: #4a5568; --accent-color: #3b82f6; --accent-hover: #2563eb; --shadow-color: rgba(0, 0, 0, 0.05); --animation-color: #a0aec0; }
        :root.dark-mode { --bg-color: #1a202c; --panel-color: #2d3748; --border-color: #4a5568; --text-primary: #edf2f7; --text-secondary: #a0aec0; --accent-color: #4299e1; --accent-hover: #63b3ed; --shadow-color: rgba(0, 0, 0, 0.2); --animation-color: #4a5568; }
        
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-primary); transition: background-color 0.3s, color 0.3s; line-height: 1.6; }
        
        /* AJOUT du style pour le canvas */
        #background-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; }
        
        .page-wrapper { max-width: 1300px; margin: 2rem auto; padding: 1.5rem; position: relative; z-index: 1; }
        
        .header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding: 1rem; background-color: color-mix(in srgb, var(--panel-color) 80%, transparent); backdrop-filter: blur(5px); border-radius: 12px; border: 1px solid var(--border-color); }
        .header-controls h1 { color: var(--text-primary); font-size: 1.75rem; font-weight: 700; }
        .header-controls h1 i { margin-right: 0.75rem; color: var(--accent-color); }
        #theme-toggle { background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease-in-out; }
        #theme-toggle:hover { color: var(--accent-color); border-color: var(--accent-color); }
        
        /* AJOUT d'un fond semi-transparent pour les cartes */
        .card { background-color: color-mix(in srgb, var(--panel-color) 90%, transparent); backdrop-filter: blur(5px); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px var(--shadow-color), 0 2px 4px -1px var(--shadow-color); transition: background-color 0.3s, box-shadow 0.3s; }
        .card h2 { font-size: 1.25rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-group-full { grid-column: 1 / -1; }
        label { font-weight: 500; margin-bottom: 0.5rem; display: block; color: var(--text-secondary); }
        input, select, textarea { width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-family: 'Inter', sans-serif; font-size: 1rem; transition: all 0.2s ease-in-out; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--accent-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent-color) 25%, transparent); }
        select[multiple] { height: 150px; padding: 1rem; }
        .btn { width: 100%; padding: 0.8rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background-color 0.2s ease-in-out, transform 0.1s ease; text-align: center; text-decoration: none; display: inline-block; }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background-color: var(--accent-color); color: white; }
        .btn-primary:hover { background-color: var(--accent-hover); }
        .btn-secondary { background-color: var(--panel-color); color: var(--text-secondary); border: 1px solid var(--border-color); }
        .btn-secondary:hover { background-color: var(--bg-color); border-color: var(--text-secondary); }
        .form-actions { align-self: end; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; text-align: left; }
        th, td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); }
        th { font-weight: 600; color: var(--text-secondary); text-transform: uppercase; font-size: 0.8rem; }
        tbody tr { transition: background-color 0.2s; }
        tbody tr:hover { background-color: color-mix(in srgb, var(--bg-color) 50%, var(--panel-color)); }
        .actions a { color: var(--text-secondary); margin: 0 0.6rem; font-size: 1.1rem; text-decoration: none; transition: color 0.2s; }
        .actions a.edit:hover { color: var(--accent-color); }
        .actions a.delete:hover { color: #e53e3e; }
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; font-weight: 500; }
        .message.success { background-color: #d1fae5; color: #065f46; }
        .message.error { background-color: #fee2e2; color: #991b1b; }
        :root.dark-mode .message.success { background-color: #064e3b; color: #a7f3d0; }
        :root.dark-mode .message.error { background-color: #7f1d1d; color: #fecaca; }
    </style>
</head>
<body>

<canvas id="background-canvas"></canvas>

<div class="page-wrapper">
<div style="margin-bottom: 2rem;">
    <a href="create_user.php" class="btn btn-secondary" style="width:auto;">
        ← Aller à la gestion des utilisateurs
    </a>
</div>
    <div class="header-controls">
        <h1><i class="fas fa-users"></i>Gestion des Équipes</h1>
        <button id="theme-toggle" title="Changer de thème"><i class="fas fa-moon"></i><span class="toggle-text">Mode Sombre</span></button>
    </div>

    <?php if ($message): echo $message; endif; ?>

    <div class="card" id="form-card">
        <h2><?= $team_to_edit ? 'Modifier l\'équipe' : 'Créer une nouvelle équipe' ?></h2>
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($team_to_edit['id'] ?? '') ?>">
            <div class="form-grid">
                <div class="form-group"><label for="nom_equipe">Nom de l'équipe</label><input type="text" id="nom_equipe" name="nom_equipe" value="<?= htmlspecialchars($team_to_edit['nom_equipe'] ?? '') ?>" required></div>
                <div class="form-group">
                    <label for="projet_id">Projet assigné</label>
                    <select id="projet_id" name="projet_id">
                        <option value="">-- Aucun projet --</option>
                        <?php foreach ($available_projects as $project): ?>
                            <option value="<?= $project['id'] ?>" <?= (($team_to_edit['projet_id'] ?? '') == $project['id']) ? 'selected' : '' ?>><?= htmlspecialchars($project['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="chef_projet_id">Chef de projet</label>
                    <select id="chef_projet_id" name="chef_projet_id">
                        <option value="">-- Aucun chef --</option>
                        <?php foreach ($chefs_de_projet as $chef): ?>
                            <option value="<?= $chef['id'] ?>" <?= (($team_to_edit['chef_projet_id'] ?? '') == $chef['id']) ? 'selected' : '' ?>><?= htmlspecialchars($chef['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-group-full">
                    <label for="membres">Membres de l'équipe</label>
                    <select id="membres" name="membres[]" multiple>
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= (in_array($user['id'], $team_to_edit['membres'] ?? [])) ? 'selected' : '' ?>><?= htmlspecialchars($user['nom']) ?> (<?= htmlspecialchars($user['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-actions"><button type="submit" name="save_team" class="btn btn-primary"><?= $team_to_edit ? 'Mettre à jour' : 'Enregistrer' ?></button></div>
                <?php if ($team_to_edit): ?><div class="form-group form-actions"><a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Annuler</a></div><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Liste des équipes</h2>
        <div class="table-wrapper">
             <table>
                <thead><tr><th>Nom Équipe</th><th>Projet</th><th>Chef de projet</th><th>Membres</th><th>Délais du projet</th><th style="text-align: right;">Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($teams)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 2rem;">Aucune équipe trouvée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($teams as $team): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($team['nom_equipe']) ?></strong></td>
                            <td><?= htmlspecialchars($team['projet_nom'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($team['chef_nom'] ?? 'N/A') ?></td>
                            <td><i class="fas fa-users"></i> <?= $team['nombre_membres'] ?></td>
                            <td>
                                <?php if ($team['date_debut'] && $team['date_fin']): ?>
                                    Du <?= date('d/m/Y', strtotime($team['date_debut'])) ?> au <?= date('d/m/Y', strtotime($team['date_fin'])) ?>
                                <?php else: echo 'N/A'; endif; ?>
                            </td>
                            <td class="actions" style="text-align: right;">
                                <a href="?edit=<?= $team['id'] ?>#form-card" title="Modifier" class="edit"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?= $team['id'] ?>" title="Supprimer" class="delete" onclick="return confirm('Êtes-vous sûr ?');"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                 </tbody>
             </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    // --- 1. GESTION DU THÈME CLAIR / SOMBRE ---
    const themeToggleBtn = document.getElementById('theme-toggle');
    const toggleIcon = themeToggleBtn.querySelector('i');
    const toggleText = themeToggleBtn.querySelector('.toggle-text');
    const updateButton = (theme) => {
        if (theme === 'dark') { toggleIcon.className = 'fas fa-sun'; if(toggleText) toggleText.textContent = 'Mode Clair'; } 
        else { toggleIcon.className = 'fas fa-moon'; if(toggleText) toggleText.textContent = 'Mode Sombre'; }
    };
    const currentTheme = localStorage.getItem('theme');
    updateButton(currentTheme === 'dark' ? 'dark' : 'light');
    themeToggleBtn.addEventListener('click', () => {
        const root = document.documentElement;
        root.classList.toggle('dark-mode');
        let theme = root.classList.contains('dark-mode') ? 'dark' : 'light';
        localStorage.setItem('theme', theme);
        updateButton(theme);
    });

    // --- 2. GESTION DES NOTIFICATIONS ---
    const message = document.querySelector('.message');
    if (message) {
        setTimeout(() => {
            message.style.transition = 'opacity 0.5s';
            message.style.opacity = '0';
            setTimeout(() => message.remove(), 500);
        }, 5000);
    }
    
    // --- 3. EFFET JS : ANIMATION "CONSTELLATION" EN ARRIÈRE-PLAN ---
    const canvas = document.getElementById('background-canvas');
    const ctx = canvas.getContext('2d');
    let animationFrameId;

    let settings = {
        particleColor: "var(--animation-color)",
        lineColor: "var(--animation-color)",
        particleAmount: 50,
        defaultRadius: 2,
        variantRadius: 2,
        defaultSpeed: 0.5,
        variantSpeed: 0.5,
        linkRadius: 180,
    };
    
    let particles;

    function getThemeColor() {
       return getComputedStyle(document.documentElement).getPropertyValue('--animation-color').trim();
    }

    function Particle(x, y) {
        this.x = x || Math.random() * canvas.width;
        this.y = y || Math.random() * canvas.height;
        this.radius = settings.defaultRadius + Math.random() * settings.variantRadius;
        this.speed = settings.defaultSpeed + Math.random() * settings.variantSpeed;
        this.directionAngle = Math.floor(Math.random() * 360);
        this.color = settings.particleColor;
        this.d = {
            x: Math.cos(this.directionAngle) * this.speed,
            y: Math.sin(this.directionAngle) * this.speed,
        };

        this.update = function() {
            this.x += this.d.x;
            this.y += this.d.y;
            if (this.x > canvas.width) this.x = 0;
            if (this.x < 0) this.x = canvas.width;
            if (this.y > canvas.height) this.y = 0;
            if (this.y < 0) this.y = canvas.height;
        };

        this.draw = function() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.closePath();
            ctx.fillStyle = this.color;
            ctx.fill();
        };
    }

    function setup() {
        particles = [];
        for (let i = 0; i < settings.particleAmount; i++) {
            particles.push(new Particle());
        }
        window.cancelAnimationFrame(animationFrameId);
        loop();
    }

    function loop() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        settings.particleColor = getThemeColor();
        settings.lineColor = getThemeColor();

        for (let i = 0; i < particles.length; i++) {
            particles[i].update();
            particles[i].draw();
        }

        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                let distance = Math.sqrt(Math.pow(particles[i].x - particles[j].x, 2) + Math.pow(particles[i].y - particles[j].y, 2));
                if (distance < settings.linkRadius) {
                    let opacity = 1 - (distance / settings.linkRadius);
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.closePath();
                    // Correction pour la couleur RGBA
                    let rgb = settings.lineColor.match(/\d+/g);
                    if(settings.lineColor.startsWith('#')) {
                       rgb = [parseInt(settings.lineColor.slice(1,3),16), parseInt(settings.lineColor.slice(3,5),16), parseInt(settings.lineColor.slice(5,7),16)];
                    }
                    ctx.strokeStyle = `rgba(${rgb[0]}, ${rgb[1]}, ${rgb[2]}, ${opacity})`;
                    ctx.stroke();
                }
            }
        }
        animationFrameId = window.requestAnimationFrame(loop);
    }
    
    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        setup();
    }

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === "class") {
                setup(); // Réinitialise l'animation avec les nouvelles couleurs
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    window.addEventListener('resize', resizeCanvas, false);
    resizeCanvas();
});
</script>

</body>
</html>