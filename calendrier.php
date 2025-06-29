<?php
// Démarrage de la session et connexion à la BD
session_start();
require_once 'connexion.php';

// =================================================================
// ROUTEUR PRINCIPAL : Gère les appels API et l'affichage de la page
// =================================================================

// PARTIE 1 : TRAITEMENT DE L'APPEL API POUR LES ÉVÉNEMENTS
if (isset($_GET['action']) && $_GET['action'] === 'get_events') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Non autorisé']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $month = $_GET['month'] ?? date('m');
    $year = $_GET['year'] ?? date('Y');
    $events = [];

    // 1. Récupérer les tâches de l'utilisateur pour le mois donné
    $sql_tasks = "SELECT nom, date_fin_estimee as event_date, 'task' as type FROM taches WHERE assigne_a = ? AND EXTRACT(MONTH FROM date_fin_estimee) = ? AND EXTRACT(YEAR FROM date_fin_estimee) = ?";
    $stmt_tasks = $pdo->prepare($sql_tasks);
    $stmt_tasks->execute([$user_id, $month, $year]);
    $tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);

    // 2. Récupérer les fins de projet de l'utilisateur pour le mois donné
    $sql_projects = "SELECT p.nom, p.date_fin as event_date, 'project' as type FROM projets p JOIN projet_membres pm ON p.id = pm.projet_id WHERE pm.utilisateur_id = ? AND EXTRACT(MONTH FROM p.date_fin) = ? AND EXTRACT(YEAR FROM p.date_fin) = ?";
    $stmt_projects = $pdo->prepare($sql_projects);
    $stmt_projects->execute([$user_id, $month, $year]);
    $projects = $stmt_projects->fetchAll(PDO::FETCH_ASSOC);

    // Fusionner et formater les résultats
    $all_events = array_merge($tasks, $projects);
    $formatted_events = [];
    foreach ($all_events as $event) {
        if (!empty($event['event_date'])) {
            $formatted_events[$event['event_date']][] = ['title' => $event['nom'], 'type' => $event['type']];
        }
    }

    echo json_encode($formatted_events);
    exit(); // Très important pour ne pas afficher le HTML
}

// =================================================================
// PARTIE 2 : LOGIQUE DE CHARGEMENT DE LA PAGE
// =================================================================

// --- Sécurité et Vérification de la session ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'] ?? 'Membre';

// --- Récupérer le prochain projet à rendre pour le décompte ---
$next_project_deadline = null;
$sql_deadline = "
    SELECT nom, date_fin 
    FROM projets 
    WHERE date_fin >= CURRENT_DATE 
    AND id IN (SELECT projet_id FROM projet_membres WHERE utilisateur_id = ?)
    ORDER BY date_fin ASC 
    LIMIT 1";
$stmt_deadline = $pdo->prepare($sql_deadline);
$stmt_deadline->execute([$user_id]);
$next_project_deadline = $stmt_deadline->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Calendrier</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS du dashboard membre amélioré pour le calendrier */
        :root {
            --light-bg: #f4f7f6; --light-card-bg: #ffffff; --light-text: #343a40; --light-border: #e9ecef;
            --light-accent: #5e72e4; --light-header: #32325d; --light-shadow: rgba(0,0,0,0.06);

            --dark-bg: #171923; --dark-card-bg: #2d3748; --dark-text: #e2e8f0; --dark-border: #4a5568;
            --dark-accent: #805ad5; --dark-header: #9f7aea; --dark-shadow: rgba(0,0,0,0.2);
        }
        body { margin: 0; font-family: 'Poppins', 'Segoe UI', sans-serif; background-color: var(--light-bg); color: var(--light-text); transition: background-color 0.3s, color 0.3s; }
        body.dark-mode { background-color: var(--dark-bg); color: var(--dark-text); }
        #bg-animation { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        .main-wrapper { display: flex; }
        .sidebar { width: 260px; background-color: var(--light-card-bg); border-right: 1px solid var(--light-border); height: 100vh; position: fixed; display: flex; flex-direction: column; transition: all 0.3s; box-shadow: 0 0 30px var(--light-shadow); }
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
        
        .main-content { margin-left: 260px; padding: 30px; width: calc(100% - 260px); display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .content-header { grid-column: 1 / -1; display: flex; justify-content: space-between; align-items: center; margin-bottom: 0; }
        .content-header h1 { color: var(--light-header); font-size: 2em; }
        body.dark-mode .content-header h1 { color: var(--dark-header); }

        /* Styles du calendrier */
        #calendar-wrapper { background-color: var(--light-card-bg); border-radius: 12px; box-shadow: 0 7px 30px -10px var(--light-shadow); padding: 25px; }
        body.dark-mode #calendar-wrapper { background-color: var(--dark-card-bg); box-shadow: 0 7px 30px -10px var(--dark-shadow); }
        #calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        #calendar-header h2 { font-size: 1.5em; margin: 0; color: var(--light-header); }
        body.dark-mode #calendar-header h2 { color: var(--dark-header); }
        .nav-btn { background: none; border: 1px solid var(--light-border); border-radius: 50%; width: 40px; height: 40px; cursor: pointer; transition: all 0.3s; }
        body.dark-mode .nav-btn { border-color: var(--dark-border); }
        .nav-btn:hover { background-color: var(--light-accent); color: white; border-color: var(--light-accent); }
        body.dark-mode .nav-btn:hover { background-color: var(--dark-accent); border-color: var(--dark-accent); }
        
        #calendar-grid, .weekdays { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; }
        .weekdays div { padding: 10px; font-weight: 600; color: #8898aa; font-size: 0.9em; }
        #calendar-grid .day { padding: 10px; min-height: 100px; border: 1px solid var(--light-border); position: relative; cursor: pointer; transition: all 0.2s; }
        body.dark-mode #calendar-grid .day { border-color: var(--dark-border); }
        #calendar-grid .day:not(.other-month):hover { background-color: #f4f7f6; }
        body.dark-mode #calendar-grid .day:not(.other-month):hover { background-color: #ffffff10; }
        #calendar-grid .day.other-month { color: #ccc; }
        body.dark-mode #calendar-grid .day.other-month { color: #666; }
        #calendar-grid .day.today .day-number { background-color: var(--light-accent); color: white; border-radius: 50%; }
        body.dark-mode #calendar-grid .day.today .day-number { background-color: var(--dark-accent); }
        #calendar-grid .day.selected { border: 2px solid var(--light-accent); }
        body.dark-mode #calendar-grid .day.selected { border-color: var(--dark-accent); }
        
        .day-number { width: 30px; height: 30px; display: flex; justify-content: center; align-items: center; margin-bottom: 5px; }
        .events-container { display: flex; flex-direction: column; gap: 4px; }
        .event-pill { font-size: 0.75em; padding: 3px 8px; border-radius: 10px; color: white; text-overflow: ellipsis; white-space: nowrap; overflow: hidden; }
        .event-pill.task { background-color: #2dce89; }
        .event-pill.project { background-color: #f5365c; }
        
        /* Styles du panneau latéral (décompte et agenda) */
        .side-panel { display: flex; flex-direction: column; gap: 30px; }
        .countdown-card, .agenda-card { background-color: var(--light-card-bg); border-radius: 12px; box-shadow: 0 7px 30px -10px var(--light-shadow); padding: 25px; }
        body.dark-mode .countdown-card, body.dark-mode .agenda-card { background-color: var(--dark-card-bg); box-shadow: 0 7px 30px -10px var(--dark-shadow); }
        .countdown-card h3, .agenda-card h3 { margin: 0 0 15px 0; border-bottom: 1px solid var(--light-border); padding-bottom: 10px; font-size: 1.2em; color: var(--light-header); }
        body.dark-mode .countdown-card h3, body.dark-mode .agenda-card h3 { border-bottom-color: var(--dark-border); color: var(--dark-header); }
        
        #countdown-timer { display: flex; justify-content: space-around; text-align: center; }
        .time-block .value { font-size: 2.5em; font-weight: 600; color: var(--light-accent); }
        body.dark-mode .time-block .value { color: var(--dark-accent); }
        .time-block .label { font-size: 0.8em; color: #8898aa; text-transform: uppercase; }
        
        #agenda-list { list-style: none; padding: 0; margin: 0; }
        #agenda-list li { padding: 10px; border-bottom: 1px solid var(--light-border); }
        body.dark-mode #agenda-list li { border-bottom-color: var(--dark-border); }
        #agenda-list li:last-child { border-bottom: none; }
        #agenda-list .event-type { display: inline-block; width: 15px; height: 15px; border-radius: 50%; margin-right: 10px; }
        #agenda-list .event-type.task { background-color: #2dce89; }
        #agenda-list .event-type.project { background-color: #f5365c; }

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
            <li><a href="user_dashboard.php"><i class="fas fa-fw fa-home"></i> Mes Projets</a></li>
            <li><a href="#" class="active"><i class="fas fa-fw fa-calendar-alt"></i> Mon Calendrier</a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <i class="fas fa-user"></i>
            <span class="user-name"><?php echo htmlspecialchars($user_nom); ?></span><br>
            <a href="login.php?action=logout">Déconnexion</a>
        </div>
    </div>
</aside>

<main class="main-content">
    <header class="content-header">
        <h1>Mon Calendrier</h1>
        <div class="theme-switch-wrapper">
            <i class="fas fa-sun"></i>
            <label class="theme-switch" for="theme-toggle">
                <input type="checkbox" id="theme-toggle" />
                <div class="slider round"></div>
            </label>
            <i class="fas fa-moon"></i>
        </div>
    </header>
    
    <div id="calendar-wrapper">
        <div id="calendar-header">
            <button id="prev-month-btn" class="nav-btn"><i class="fas fa-chevron-left"></i></button>
            <h2 id="current-month-year"></h2>
            <button id="next-month-btn" class="nav-btn"><i class="fas fa-chevron-right"></i></button>
        </div>
        <div class="weekdays">
            <div>Dim</div><div>Lun</div><div>Mar</div><div>Mer</div><div>Jeu</div><div>Ven</div><div>Sam</div>
        </div>
        <div id="calendar-grid"></div>
    </div>

    <div class="side-panel">
        <div class="countdown-card">
            <h3>Prochaine Échéance</h3>
            <?php if ($next_project_deadline): ?>
                <div id="countdown-container" data-deadline="<?php echo $next_project_deadline['date_fin']; ?>">
                    <p>Projet : <strong><?php echo htmlspecialchars($next_project_deadline['nom']); ?></strong></p>
                    <div id="countdown-timer">
                        <div class="time-block"><span id="days" class="value">0</span><span class="label">Jours</span></div>
                        <div class="time-block"><span id="hours" class="value">0</span><span class="label">Heures</span></div>
                        <div class="time-block"><span id="minutes" class="value">0</span><span class="label">Min</span></div>
                        <div class="time-block"><span id="seconds" class="value">0</span><span class="label">Sec</span></div>
                    </div>
                </div>
            <?php else: ?>
                <p>Aucune échéance de projet à venir.</p>
            <?php endif; ?>
        </div>
        <div class="agenda-card">
            <h3>Agenda du jour</h3>
            <ul id="agenda-list">
                <li>Sélectionnez un jour pour voir les événements.</li>
            </ul>
        </div>
    </div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- GESTION DU THÈME ---
    const themeToggle = document.getElementById('theme-toggle');
    const body = document.body;
    function applyTheme(theme) { /* ... (identique à la version précédente) ... */ }
    themeToggle.addEventListener('change', function() { /* ... */ });
    const savedTheme = localStorage.getItem('theme') || 'light';
    applyTheme(savedTheme);

    // --- CALENDRIER INTERACTIF ---
    const calendarGrid = document.getElementById('calendar-grid');
    const currentMonthYearEl = document.getElementById('current-month-year');
    const prevMonthBtn = document.getElementById('prev-month-btn');
    const nextMonthBtn = document.getElementById('next-month-btn');
    const agendaList = document.getElementById('agenda-list');

    let currentDate = new Date();
    let eventsData = {};
    let selectedDayEl = null;

    async function fetchAndRenderCalendar() {
        const month = currentDate.getMonth() + 1;
        const year = currentDate.getFullYear();
        
        currentMonthYearEl.textContent = currentDate.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
        calendarGrid.innerHTML = '<div>Chargement...</div>';

        try {
            // Appel à l'API interne au fichier
            const response = await fetch(`?action=get_events&month=${month}&year=${year}`);
            eventsData = await response.json();
            renderCalendar();
        } catch (error) {
            console.error("Erreur de chargement des événements:", error);
            calendarGrid.innerHTML = '<div>Erreur de chargement.</div>';
        }
    }

    function renderCalendar() {
        calendarGrid.innerHTML = '';
        const month = currentDate.getMonth();
        const year = currentDate.getFullYear();
        
        const firstDayOfMonth = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Créer les cases vides pour les jours du mois précédent
        for (let i = 0; i < firstDayOfMonth; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.classList.add('day', 'other-month');
            calendarGrid.appendChild(emptyCell);
        }

        // Créer les cases pour chaque jour du mois
        for (let i = 1; i <= daysInMonth; i++) {
            const dayCell = document.createElement('div');
            dayCell.classList.add('day');
            
            const dayNumber = document.createElement('div');
            dayNumber.classList.add('day-number');
            dayNumber.textContent = i;
            dayCell.appendChild(dayNumber);
            
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            dayCell.dataset.date = dateStr;

            if (i === new Date().getDate() && month === new Date().getMonth() && year === new Date().getFullYear()) {
                dayCell.classList.add('today');
            }

            if (eventsData[dateStr]) {
                const eventsContainer = document.createElement('div');
                eventsContainer.classList.add('events-container');
                eventsData[dateStr].slice(0, 2).forEach(event => { // Affiche max 2 événements
                    const eventPill = document.createElement('div');
                    eventPill.classList.add('event-pill', event.type);
                    eventPill.textContent = event.title;
                    eventsContainer.appendChild(eventPill);
                });
                dayCell.appendChild(eventsContainer);
            }
            calendarGrid.appendChild(dayCell);
        }
    }
    
    calendarGrid.addEventListener('click', (e) => {
        const dayCell = e.target.closest('.day:not(.other-month)');
        if (!dayCell) return;
        
        if(selectedDayEl) selectedDayEl.classList.remove('selected');
        selectedDayEl = dayCell;
        selectedDayEl.classList.add('selected');

        const date = dayCell.dataset.date;
        agendaList.innerHTML = '';
        if(eventsData[date]) {
            eventsData[date].forEach(event => {
                const li = document.createElement('li');
                li.innerHTML = `<span class="event-type ${event.type}"></span> ${event.title}`;
                agendaList.appendChild(li);
            });
        } else {
            agendaList.innerHTML = '<li>Aucun événement pour ce jour.</li>';
        }
    });

    prevMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        fetchAndRenderCalendar();
    });
    nextMonthBtn.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        fetchAndRenderCalendar();
    });

    // --- DÉCOMPTE DU PROJET ---
    const countdownContainer = document.getElementById('countdown-container');
    if (countdownContainer) {
        const deadline = new Date(countdownContainer.dataset.deadline + "T23:59:59");

        setInterval(() => {
            const now = new Date();
            const diff = deadline - now;
            
            const d = Math.floor(diff / (1000 * 60 * 60 * 24));
            const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((diff % (1000 * 60)) / 1000);

            document.getElementById('days').textContent = d < 0 ? 0 : d;
            document.getElementById('hours').textContent = h < 0 ? 0 : h;
            document.getElementById('minutes').textContent = m < 0 ? 0 : m;
            document.getElementById('seconds').textContent = s < 0 ? 0 : s;

        }, 1000);
    }

    // Lancement initial
    fetchAndRenderCalendar();


    // --- ANIMATION DE FOND (identique à la version précédente) ---
    const canvas = document.getElementById('bg-animation');
    /* ... (coller ici tout le script de l'animation canvas 'Constellation' de la réponse précédente) ... */
    (function() { // encapsulé pour ne pas polluer le scope global
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        let particles = [];
        const options = {
            particleColor: "rgba(187, 134, 252, 0.5)",
            lineColor: "rgba(187, 134, 252, 0.1)",
            particleAmount: 50,
            defaultSpeed: 0.3,
            variantSpeed: 0.3,
            defaultRadius: 2,
            variantRadius: 1,
            linkRadius: 180,
        };
        const body = document.body;
        if (!body.classList.contains('dark-mode')) {
            options.particleColor = "rgba(94, 114, 228, 0.5)";
            options.lineColor = "rgba(94, 114, 228, 0.1)";
        }
        
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
                    const distance = Math.sqrt(Math.pow(particles[j].x - particles[i].x, 2) + Math.pow(particles[j].y - particles[i].y, 2));
                    if (distance < options.linkRadius) {
                        ctx.strokeStyle = options.lineColor;
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.stroke();
                        ctx.closePath();
                    }
                }
            }
        }
        function setup() {
            particles = [];
            for (let i = 0; i < options.particleAmount; i++) particles.push(new Particle());
            window.requestAnimationFrame(loop);
        }
        function loop() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            for (const particle of particles) { particle.update(); particle.draw(); }
            linkParticles();
            window.requestAnimationFrame(loop);
        }
        window.addEventListener("resize", () => { canvas.width = window.innerWidth; canvas.height = window.innerHeight; setup(); });
        setup();
    })(); // Fin de l'IIFE pour l'animation
});
</script>

</body>
</html>