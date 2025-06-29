<?php
session_start();
require_once 'connexion.php';

// --- Sécurité et Vérification de la session ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_nom = $_SESSION['user_nom'] ?? 'Membre';

// --- Redirection si l'utilisateur n'est pas un membre ---
if ($user_role !== 'membre') {
    // Rediriger vers le dashboard approprié (ex: chef) ou une page d'erreur
    header('Location: chef_dashboard.php'); 
    exit();
}

// --- Logique spécifique au rôle 'membre' ---
$projets = [];

// REQUÊTE SQL CORRIGÉE : Utilise projet_membres et la bonne jointure pour le chef
$sql = "
    SELECT 
        p.id, p.nom, p.description,
        chef.nom as chef_nom,
        -- Utilise COALESCE pour retourner 0 si AVG est NULL (aucune tâche)
        COALESCE((SELECT AVG(t.progression) FROM taches t WHERE t.projet_id = p.id), 0) as project_progress,
        (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id AND t.assigne_a = :user_id) as my_total_tasks,
        (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id AND t.assigne_a = :user_id AND t.statut = 'en cours') as my_ongoing_tasks,
        (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id AND t.assigne_a = :user_id AND t.statut = 'non commencé') as my_todo_tasks
    FROM projets p
    JOIN projet_membres pm ON p.id = pm.projet_id
    LEFT JOIN users chef ON p.cree_par = chef.id -- Le chef est celui qui a créé le projet
    WHERE pm.utilisateur_id = :user_id
    GROUP BY p.id, chef.nom
    ORDER BY p.date_creation DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $user_id]);
$projets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Tableau de Bord</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --light-bg: #f4f7f6; --light-card-bg: #ffffff; --light-text: #343a40; --light-border: #e9ecef;
            --light-accent: #5e72e4; --light-header: #32325d; --light-shadow: rgba(0,0,0,0.06);

            --dark-bg: #171923; --dark-card-bg: #2d3748; --dark-text: #e2e8f0; --dark-border: #4a5568;
            --dark-accent: #805ad5; --dark-header: #9f7aea; --dark-shadow: rgba(0,0,0,0.2);
        }

        body {
            margin: 0;
            font-family: 'Poppins', 'Segoe UI', 'Roboto', sans-serif;
            background-color: var(--light-bg);
            color: var(--light-text);
            transition: background-color 0.3s, color 0.3s;
        }
        body.dark-mode {
            background-color: var(--dark-bg);
            color: var(--dark-text);
        }

        #bg-animation { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        
        .main-wrapper { display: flex; }

        .sidebar {
            width: 260px;
            background-color: var(--light-card-bg);
            border-right: 1px solid var(--light-border);
            height: 100vh;
            position: fixed;
            display: flex; flex-direction: column;
            transition: background-color 0.3s, border-color 0.3s;
            box-shadow: 0 0 30px var(--light-shadow);
        }
        body.dark-mode .sidebar { background-color: var(--dark-card-bg); border-right-color: var(--dark-border); box-shadow: 0 0 30px var(--dark-shadow); }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid var(--light-border); }
        body.dark-mode .sidebar-header { border-bottom-color: var(--dark-border); }
        .sidebar-header .logo { width: 40px; margin-bottom: 10px; }
        .sidebar-header h2 { font-size: 1.2em; margin: 0; color: var(--light-header); }
        body.dark-mode .sidebar-header h2 { color: var(--dark-header); }
        
        .sidebar-nav { flex-grow: 1; list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 15px; text-decoration: none; padding: 15px 25px; margin: 5px 15px; border-radius: 8px; color: #525f7f; transition: all 0.3s; }
        body.dark-mode .sidebar-nav a { color: #a0aec0; }
        .sidebar-nav a:hover { background-color: var(--light-accent); color: white; }
        body.dark-mode .sidebar-nav a:hover { background-color: var(--dark-accent); color: white; }
        .sidebar-nav a.active { background-color: var(--light-accent); color: white; font-weight: 600; box-shadow: 0 4px 6px rgba(50,50,93,.11), 0 1px 3px rgba(0,0,0,.08); }
        body.dark-mode .sidebar-nav a.active { background-color: var(--dark-accent); box-shadow: none; }
        
        .sidebar-footer { padding: 20px; border-top: 1px solid var(--light-border); }
        body.dark-mode .sidebar-footer { border-top-color: var(--dark-border); }
        .user-profile { text-align: center; }
        .user-profile .user-name { font-weight: 600; }
        .user-profile a { color: var(--light-accent); text-decoration: none; font-size: 0.9em; }
        body.dark-mode .user-profile a { color: var(--dark-accent); }
        
        .main-content { margin-left: 260px; padding: 30px; width: calc(100% - 260px); }
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .content-header h1 { color: var(--light-header); font-size: 2em; }
        body.dark-mode .content-header h1 { color: var(--dark-header); }

        /* NOUVEAU : Grille de cartes de projet */
        .project-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .project-card {
            background-color: var(--light-card-bg);
            border-radius: 12px;
            box-shadow: 0 7px 30px -10px var(--light-shadow);
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: all 0.3s ease;
        }
        .project-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px -10px rgba(0,0,0,0.15); }
        body.dark-mode .project-card { background-color: var(--dark-card-bg); box-shadow: 0 7px 30px -10px var(--dark-shadow); }
        
        .card-header h3 { margin: 0; font-size: 1.2em; color: var(--light-header); }
        body.dark-mode .card-header h3 { color: var(--dark-header); }
        .card-header small { color: #8898aa; }
        
        .card-stats { display: flex; justify-content: space-around; text-align: center; background-color: var(--light-bg); padding: 10px; border-radius: 8px; }
        body.dark-mode .card-stats { background-color: #2d374880; }
        .stat-item .count { font-size: 1.4em; font-weight: 600; color: var(--light-accent); }
        body.dark-mode .stat-item .count { color: var(--dark-accent); }
        .stat-item .label { font-size: 0.8em; color: #8898aa; }
        
        .progress-bar-container { width: 100%; background-color: var(--light-border); border-radius: 20px; height: 8px; overflow: hidden; }
        body.dark-mode .progress-bar-container { background-color: var(--dark-border); }
        .progress-bar-fill { height: 100%; width: 0%; background: linear-gradient(90deg, var(--light-accent), #a678ff); border-radius: 20px; transition: width 0.5s ease-out; }
        body.dark-mode .progress-bar-fill { background: linear-gradient(90deg, var(--dark-accent), #bf9cff); }
        
        .card-footer { margin-top: auto; }
        .btn-view-project { display: inline-block; background-color: var(--light-accent); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; transition: background-color 0.3s; font-weight: 600; }
        body.dark-mode .btn-view-project { background-color: var(--dark-accent); }
        .btn-view-project:hover { opacity: 0.9; }

        /* --- Theme Toggle Switch --- */
        .theme-switch-wrapper { display: flex; align-items: center; gap: 10px; }
        .theme-switch { display: inline-block; height: 24px; position: relative; width: 50px; }
        .theme-switch input { display:none; }
        .slider { background-color: #ccc; bottom: 0; cursor: pointer; left: 0; position: absolute; right: 0; top: 0; transition: .4s; border-radius: 24px; }
        .slider:before { background-color: #fff; bottom: 3px; content: ""; height: 18px; left: 3px; position: absolute; transition: .4s; width: 18px; border-radius: 50%; }
        input:checked + .slider { background-color: var(--dark-accent); }
        input:checked + .slider:before { transform: translateX(26px); }

    </style>
</head>
<body>
<canvas id="bg-animation"></canvas>

<aside class="sidebar">
    <div class="sidebar-header">
        <img src="http://googleusercontent.com/image_generation_content/0" alt="Logo" class="logo">
        <h2>Groupe1App</h2>
    </div>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="#" class="active"><i class="fas fa-fw fa-home"></i> Mes Projets</a></li>
            <li><a href="calendrier.php"><i class="fas fa-fw fa-calendar-alt"></i> Mon Calendrier</a></li>
            <li><a href="tache.php"><i class="fas fa-fw fa-check-square"></i> Mes Tâches</a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <i class="fas fa-user"></i>
            <span class="user-name"><?php echo htmlspecialchars($user_nom); ?></span><br>
            <a href="logout.php">Déconnexion</a>
        </div>
    </div>
</aside>

<main class="main-content">
    <header class="content-header">
        <h1>Mon Tableau de Bord</h1>
        <div class="theme-switch-wrapper">
            <i class="fas fa-sun"></i>
            <label class="theme-switch" for="theme-toggle">
                <input type="checkbox" id="theme-toggle" />
                <div class="slider round"></div>
            </label>
            <i class="fas fa-moon"></i>
        </div>
    </header>

    <?php if (empty($projets)): ?>
        <div class="project-card" style="text-align: center; grid-column: 1 / -1;">Vous n'êtes assigné à aucun projet pour le moment.</div>
    <?php else: ?>
        <div class="project-grid">
            <?php foreach ($projets as $projet): ?>
                <div class="project-card">
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($projet['nom']); ?></h3>
                        <small>Chef de projet : <?php echo htmlspecialchars($projet['chef_nom'] ?? 'N/A'); ?></small>
                    </div>
                    <p class="card-description" style="color: #8898aa; font-size: 0.9em;">
                        <?php echo htmlspecialchars(substr($projet['description'], 0, 100)) . '...'; ?>
                    </p>
                    <div class="card-stats">
                        <div class="stat-item">
                            <span class="count"><?php echo $projet['my_total_tasks']; ?></span>
                            <span class="label">Total Tâches</span>
                        </div>
                        <div class="stat-item">
                            <span class="count"><?php echo $projet['my_ongoing_tasks']; ?></span>
                            <span class="label">En Cours</span>
                        </div>
                        <div class="stat-item">
                            <span class="count"><?php echo $projet['my_todo_tasks']; ?></span>
                            <span class="label">À Faire</span>
                        </div>
                    </div>
                    <div class="progress-bar-container" title="Progression globale du projet : <?php echo round($projet['project_progress']); ?>%">
                        <div class="progress-bar-fill" style="width: <?php echo round($projet['project_progress']); ?>%;"></div>
                    </div>
                    <div class="card-footer">
                        <a href="importer_fichier.php?id=<?php echo $projet['id']; ?>" class="btn-view-project">
                            contunier le projet <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Theme Toggle ---
    const themeToggle = document.getElementById('theme-toggle');
    const body = document.body;

    function applyTheme(theme) {
        body.classList.toggle('dark-mode', theme === 'dark');
        themeToggle.checked = (theme === 'dark');
    }

    themeToggle.addEventListener('change', function() {
        const selectedTheme = this.checked ? 'dark' : 'light';
        localStorage.setItem('theme', selectedTheme);
        applyTheme(selectedTheme);
    });

    const savedTheme = localStorage.getItem('theme') || 'light';
    applyTheme(savedTheme);

    // --- NOUVELLE ANIMATION "CONSTELLATION" ---
    const canvas = document.getElementById('bg-animation');
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    let particles = [];

    const options = {
        particleColor: "rgba(187, 134, 252, 0.5)", // --dark-accent
        lineColor: "rgba(187, 134, 252, 0.1)",
        particleAmount: 50,
        defaultSpeed: 0.3,
        variantSpeed: 0.3,
        defaultRadius: 2,
        variantRadius: 1,
        linkRadius: 180,
    };
    
    body.classList.contains('dark-mode') ? 
        (options.particleColor = "rgba(187, 134, 252, 0.5)", options.lineColor = "rgba(187, 134, 252, 0.1)") :
        (options.particleColor = "rgba(94, 114, 228, 0.5)", options.lineColor = "rgba(94, 114, 228, 0.1)");
    
    class Particle {
        constructor() {
            this.x = Math.random() * canvas.width;
            this.y = Math.random() * canvas.height;
            this.speed = options.defaultSpeed + Math.random() * options.variantSpeed;
            this.directionAngle = Math.floor(Math.random() * 360);
            this.color = options.particleColor;
            this.radius = options.defaultRadius + Math.random() * options.variantRadius;
            this.vector = {
                x: Math.cos(this.directionAngle) * this.speed,
                y: Math.sin(this.directionAngle) * this.speed
            };
        }
        update() {
            this.border();
            this.x += this.vector.x;
            this.y += this.vector.y;
        }
        border() {
            if (this.x >= canvas.width || this.x <= 0) this.vector.x *= -1;
            if (this.y >= canvas.height || this.y <= 0) this.vector.y *= -1;
            if (this.x > canvas.width) this.x = canvas.width;
            if (this.y > canvas.height) this.y = canvas.height;
            if (this.x < 0) this.x = 0;
            if (this.y < 0) this.y = 0;
        }
        draw() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.closePath();
            ctx.fillStyle = this.color;
            ctx.fill();
        }
    }

    function linkParticles() {
        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const a = particles[i];
                const b = particles[j];
                const distance = Math.sqrt(Math.pow(b.x - a.x, 2) + Math.pow(b.y - a.y, 2));

                if (distance < options.linkRadius) {
                    ctx.strokeStyle = options.lineColor;
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(a.x, a.y);
                    ctx.lineTo(b.x, b.y);
                    ctx.stroke();
                    ctx.closePath();
                }
            }
        }
    }

    function setup() {
        particles = [];
        for (let i = 0; i < options.particleAmount; i++) {
            particles.push(new Particle());
        }
        window.requestAnimationFrame(loop);
    }
    
    function loop() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (const particle of particles) {
            particle.update();
            particle.draw();
        }
        linkParticles();
        window.requestAnimationFrame(loop);
    }

    window.addEventListener("resize", () => {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        setup();
    });

    setup();
});
</script>

</body>
</html>