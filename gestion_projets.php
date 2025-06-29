<?php
session_start();
require_once 'connexion.php';

// ===================================================================================
// PARTIE 1 : LOGIQUE PHP (INCHANGÉE)
// ===================================================================================

$message = '';
$project_to_edit = null;

$users_stmt = $pdo->query("SELECT id, nom FROM users ORDER BY nom ASC");
$users_list = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_project'])) {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
    $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;
    $cree_par = !empty($_POST['cree_par']) ? $_POST['cree_par'] : null;
    $id = $_POST['id'] ?? null;

    if (empty($nom)) {
        $message = '<div class="message error">Le nom du projet est requis.</div>';
    } else {
        if (!empty($id)) {
            $sql = "UPDATE projets SET nom = :nom, description = :description, date_debut = :date_debut, date_fin = :date_fin, cree_par = :cree_par WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nom' => $nom, ':description' => $description, ':date_debut' => $date_debut, ':date_fin' => $date_fin, ':cree_par' => $cree_par, ':id' => $id]);
            $_SESSION['message'] = '<div class="message success">Projet mis à jour avec succès !</div>';
        } else {
            $sql = "INSERT INTO projets (nom, description, date_debut, date_fin, cree_par) VALUES (:nom, :description, :date_debut, :date_fin, :cree_par)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nom' => $nom, ':description' => $description, ':date_debut' => $date_debut, ':date_fin' => $date_fin, ':cree_par' => $cree_par]);
            $_SESSION['message'] = '<div class="message success">Projet ajouté avec succès !</div>';
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM projets WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $_SESSION['message'] = '<div class="message success">Projet supprimé.</div>';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $sql = "SELECT * FROM projets WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $project_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

$search_query = "SELECT p.id, p.nom, p.description, p.date_debut, p.date_fin, u.nom as createur_nom FROM projets p LEFT JOIN users u ON p.cree_par = u.id WHERE 1=1";
$params = [];
if (!empty($_GET['search_nom'])) {
    $search_query .= " AND p.nom ILIKE :nom";
    $params[':nom'] = '%' . $_GET['search_nom'] . '%';
}
$search_query .= " ORDER BY p.date_creation DESC";
$stmt = $pdo->prepare($search_query);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Gestion des Projets</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        (function() {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark') {
                document.documentElement.classList.add('dark-mode');
            }
        })();
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        :root {
            --bg-color: #f4f7f9;
            --panel-color: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1a202c;
            --text-secondary: #4a5568;
            --accent-color: #3b82f6;
            --accent-hover: #2563eb;
            --shadow-color: rgba(0, 0, 0, 0.05);
            --animation-color: #a0aec0; /* Couleur des particules en mode clair */
        }

        :root.dark-mode {
            --bg-color: #1a202c;
            --panel-color: #2d3748;
            --border-color: #4a5568;
            --text-primary: #edf2f7;
            --text-secondary: #a0aec0;
            --accent-color: #4299e1;
            --accent-hover: #63b3ed;
            --shadow-color: rgba(0, 0, 0, 0.2);
            --animation-color: #4a5568; /* Couleur des particules en mode sombre */
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            transition: background-color 0.3s, color 0.3s;
            line-height: 1.6;
        }

        #background-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .page-wrapper {
            max-width: 1300px;
            margin: 2rem auto;
            padding: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background-color: color-mix(in srgb, var(--panel-color) 80%, transparent);
            backdrop-filter: blur(5px);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        
        .header-controls h1 { color: var(--text-primary); font-size: 1.75rem; font-weight: 700; }
        .header-controls h1 i { margin-right: 0.75rem; color: var(--accent-color); }

        #theme-toggle {
            background: var(--bg-color);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease-in-out;
        }
        #theme-toggle:hover { color: var(--accent-color); border-color: var(--accent-color); }

        .card {
            background-color: color-mix(in srgb, var(--panel-color) 90%, transparent);
            backdrop-filter: blur(5px);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px var(--shadow-color), 0 2px 4px -1px var(--shadow-color);
            transition: background-color 0.3s, box-shadow 0.3s;
        }
        .card h2 { font-size: 1.25rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-group-full { grid-column: 1 / -1; }
        label { font-weight: 500; margin-bottom: 0.5rem; display: block; color: var(--text-secondary); }
        input, select, textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.2s ease-in-out;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent-color) 25%, transparent);
        }
        textarea { resize: vertical; min-height: 100px; }
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
        <h1><i class="fas fa-folder-open"></i>Gestion des Projets</h1>
        <button id="theme-toggle" title="Changer de thème">
            <i class="fas fa-moon"></i>
            <span class="toggle-text">Mode Sombre</span>
        </button>
    </div>

    <?php if ($message): echo $message; endif; ?>

    <div class="card">
        <h2><?= $project_to_edit ? 'Modifier le projet' : 'Créer un nouveau projet' ?></h2>
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($project_to_edit['id'] ?? '') ?>">
            <div class="form-grid">
                <div class="form-group form-group-full"><label for="nom">Nom du Projet</label><input type="text" id="nom" name="nom" value="<?= htmlspecialchars($project_to_edit['nom'] ?? '') ?>" required></div>
                <div class="form-group form-group-full"><label for="description">Description</label><textarea id="description" name="description"><?= htmlspecialchars($project_to_edit['description'] ?? '') ?></textarea></div>
                <div class="form-group"><label for="date_debut">Date de début</label><input type="date" id="date_debut" name="date_debut" value="<?= htmlspecialchars($project_to_edit['date_debut'] ?? '') ?>"></div>
                <div class="form-group"><label for="date_fin">Date de fin</label><input type="date" id="date_fin" name="date_fin" value="<?= htmlspecialchars($project_to_edit['date_fin'] ?? '') ?>"></div>
                <div class="form-group">
                    <label for="cree_par">Chef de projet</label>
                    <select id="cree_par" name="cree_par">
                        <option value="">-- Non assigné --</option>
                        <?php foreach ($users_list as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= (($project_to_edit['cree_par'] ?? '') == $user['id']) ? 'selected' : '' ?>><?= htmlspecialchars($user['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-actions"><button type="submit" name="save_project" class="btn btn-primary"><?= $project_to_edit ? 'Mettre à jour' : 'Enregistrer' ?></button></div>
                <?php if ($project_to_edit): ?><div class="form-group form-actions"><a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Annuler</a></div><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Liste des projets</h2>
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET">
             <div class="form-grid" style="margin-bottom: 2rem;">
                 <div class="form-group" style="grid-column: span 2;"><label for="search_nom">Rechercher par nom de projet</label><input type="text" id="search_nom" name="search_nom" value="<?= htmlspecialchars($_GET['search_nom'] ?? '') ?>"></div>
                <div class="form-group form-actions"><button type="submit" class="btn btn-primary">Rechercher</button></div>
                <div class="form-group form-actions"><a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Réinitialiser</a></div>
            </div>
        </form>

        <div class="table-wrapper">
             <table>
                 <thead><tr><th>ID</th><th>Nom du Projet</th><th>Chef de projet</th><th>Début</th><th>Fin</th><th style="text-align: right;">Actions</th></tr></thead>
                 <tbody>
                    <?php if (empty($projects)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 2rem;">Aucun projet trouvé.</td></tr>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?= htmlspecialchars($project['id']) ?></td>
                            <td><strong><?= htmlspecialchars($project['nom']) ?></strong></td>
                            <td><?= htmlspecialchars($project['createur_nom'] ?? 'N/A') ?></td>
                            <td><?= $project['date_debut'] ? htmlspecialchars(date('d/m/Y', strtotime($project['date_debut']))) : 'N/A' ?></td>
                            <td><?= $project['date_fin'] ? htmlspecialchars(date('d/m/Y', strtotime($project['date_fin']))) : 'N/A' ?></td>
                            <td class="actions" style="text-align: right;">
                                <a href="?edit=<?= $project['id'] ?>#form-panel" title="Modifier" class="edit"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?= $project['id'] ?>" title="Supprimer" class="delete" onclick="return confirm('Attention, supprimer ce projet est irréversible. Êtes-vous sûr ?');"><i class="fas fa-trash-alt"></i></a>
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
        if (theme === 'dark') {
            toggleIcon.classList.remove('fa-moon');
            toggleIcon.classList.add('fa-sun');
            if(toggleText) toggleText.textContent = 'Mode Clair';
        } else {
            toggleIcon.classList.remove('fa-sun');
            toggleIcon.classList.add('fa-moon');
            if(toggleText) toggleText.textContent = 'Mode Sombre';
        }
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
                    ctx.strokeStyle = `rgba(${parseInt(settings.lineColor.slice(1,3),16)}, ${parseInt(settings.lineColor.slice(3,5),16)}, ${parseInt(settings.lineColor.slice(5,7),16)}, ${opacity})`;
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
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
  
    <button type="button" onclick="window.location.href='create_user.php'" class="btn btn-secondary" style="width:auto;">
        ← Retour
    </button>
</div>

</body>
</html>