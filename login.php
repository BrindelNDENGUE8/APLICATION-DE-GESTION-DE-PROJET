<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bienvenue sur notre Application de Gestion de Projets Intelligente</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            background: linear-gradient(135deg, #F8FAFC, #E0F7FA);
            color: #1A3C34;
            line-height: 1.6;
            overflow-x: hidden;
            position: relative;
        }

        #bubble-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        header {
            width: 100%;
            padding: 20px 5%;
            position: fixed;
            top: 0;
            left: 0;
            background: rgba(248, 250, 252, 0.95);
            backdrop-filter: blur(12px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: background 0.3s ease, transform 0.3s ease;
        }

        header.scrolled {
            background: rgba(248, 250, 252, 1);
            transform: translateY(0);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1A3C34;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .logo i {
            margin-right: 10px;
            color: #00C4B4;
            transition: transform 0.3s ease;
        }

        .logo:hover i {
            transform: rotate(360deg);
        }

        .nav-buttons button {
            background: #00C4B4;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            margin-left: 15px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        }

        .nav-buttons button:hover {
            background: #009688;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 196, 180, 0.4);
        }

        .hero {
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 0 20px;
            padding-top: 80px;
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #1A3C34;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .hero p {
            font-size: 1.2rem;
            max-width: 800px;
            margin-bottom: 40px;
            color: #4A5A57;
        }

        .hero-buttons .main-cta {
            background: #FF6B6B;
            color: #ffffff;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            display: inline-block;
            margin: 0 10px;
        }

        .hero-buttons .main-cta:hover {
            background: #E55A5A;
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(255, 107, 107, 0.4);
        }

        .hero-buttons .secondary-cta {
            background: transparent;
            border: 2px solid #00C4B4;
            color: #00C4B4;
            padding: 15px 30px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease, box-shadow 0.3s ease;
            display: inline-block;
            margin: 0 10px;
        }

        .hero-buttons .secondary-cta:hover {
            background: #00C4B4;
            color: #ffffff;
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0, 196, 180, 0.4);
        }

        .section {
            padding: 80px 5%;
            text-align: center;
            background: rgba(255, 255, 255, 0.9);
            margin: 20px auto;
            max-width: 1200px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 1;
        }

        .section h2 {
            font-size: 2.5rem;
            color: #00C4B4;
            margin-bottom: 30px;
            font-weight: 700;
        }

        .section h2::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: #FF6B6B;
            margin: 10px auto 0;
            border-radius: 2px;
        }

        .section p {
            font-size: 1.1rem;
            max-width: 900px;
            margin: 0 auto 30px;
            color: #4A5A57;
        }

        .features-grid {
            display: flex;
            flex-wrap: nowrap;
            justify-content: center;
            gap: 30px;
            margin-top: 40px;
        }

        .feature-item {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #00C4B4;
            flex: 1;
            min-width: 250px;
            max-width: 300px;
        }

        .feature-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .feature-item i {
            font-size: 2.5rem;
            color: #FF6B6B;
            margin-bottom: 20px;
            display: block;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .feature-item:hover i {
            transform: scale(1.2);
        }

        .feature-item h3 {
            font-size: 1.4rem;
            color: #1A3C34;
            margin-bottom: 15px;
            text-align: center;
        }

        .feature-item p {
            font-size: 0.95rem;
            color: #4A5A57;
            text-align: left;
        }

        .role-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 60px 5%;
            margin: 20px auto;
            max-width: 1200px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .role-section h2 {
            color: #FF6B6B;
        }

        .role-details {
            display: flex;
            flex-wrap: nowrap;
            justify-content: center;
            gap: 30px;
        }

        .role-card {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            flex: 1;
            min-width: 280px;
            max-width: 350px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #00C4B4;
        }

        .role-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .role-card h3 {
            font-size: 1.6rem;
            color: #1A3C34;
            margin-bottom: 15px;
            text-align: center;
        }

        .role-card ul {
            list-style: none;
            padding-left: 0;
        }

        .role-card ul li {
            position: relative;
            padding-left: 25px;
            margin-bottom: 10px;
            color: #4A5A57;
        }

        .role-card ul li::before {
            content: '✔';
            position: absolute;
            left: 0;
            color: #00C4B4;
            font-size: 1.1em;
        }

        footer {
            text-align: center;
            padding: 30px 20px;
            margin-top: 50px;
            background: #1A3C34;
            color: #F8FAFC;
            font-size: 0.9rem;
        }

        @media (max-width: 1024px) {
            .features-grid,
            .role-details {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            header {
                flex-direction: column;
                padding: 15px 5%;
            }
            .nav-buttons {
                margin-top: 15px;
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
            }
            .nav-buttons button {
                margin: 5px;
                padding: 8px 15px;
                font-size: 0.9rem;
            }
            .hero h1 {
                font-size: 2.5rem;
            }
            .hero p {
                font-size: 1rem;
            }
            .hero-buttons .main-cta,
            .hero-buttons .secondary-cta {
                font-size: 0.95rem;
                padding: 12px 25px;
                margin: 5px;
            }
            .section {
                padding: 60px 5%;
            }
            .section h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .hero h1 {
                font-size: 2rem;
            }
            .hero p {
                font-size: 0.9rem;
            }
            .nav-buttons button {
                width: 100%;
                margin: 5px 0;
            }
            .logo {
                font-size: 1.5rem;
            }
            .section h2 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <canvas id="bubble-canvas"></canvas>

    <header id="main-header">
        <a href="#" class="logo"><i class="fas fa-cubes"></i> Groupe1App</a>
        <nav class="nav-buttons">
            <button onclick="window.location.href='connexion_admin.php'">Connexion Administrateur</button>
            <button onclick="window.location.href='connexion_chef.php'">Connexion Gestionnaire</button>
            <button onclick="window.location.href='connexion_user.php'">Connexion Utilisateur</button>
        </nav>
    </header>

    <section class="hero">
        <h1>Gérez vos projets avec l'intelligence artificielle</h1>
        <p>
            Notre application révolutionnaire transforme la gestion de projets. Optimisez vos tâches, prévenez les retards
            et collaborez efficacement grâce à des prédictions intelligentes et des outils puissants.
        </p>
        <div class="hero-buttons">
            <a href="#fonctionnement" class="main-cta">Découvrir l'Application</a>
            <a href="login.php" class="secondary-cta">Commencer Maintenant</a>
        </div>
    </section>

    <section id="fonctionnement" class="section">
        <h2>Comment ça fonctionne ?</h2>
        <p>
            Notre plateforme s'intègre à vos processus existants pour apporter une valeur ajoutée à chaque étape de vos projets.
            Découvrez les piliers de notre approche :
        </p>
        <div class="features-grid">
            <div class="feature-item">
                <i class="fas fa-robot"></i>
                <h3>Prédictions Intelligentes</h3>
                <p>Grâce à l'IA, anticipez les retards potentiels, optimisez l'allocation des ressources et prenez des décisions éclairées pour respecter vos délais.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-tasks"></i>
                <h3>Gestion de Tâches Avancée</h3>
                <p>Créez, assignez et suivez vos tâches avec des statuts clairs, des progressions en temps réel et des priorités personnalisables.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-users-cog"></i>
                <h3>Collaboration Simplifiée</h3>
                <p>Communiquez facilement avec votre équipe grâce aux commentaires intégrés, partagez des fichiers et maintenez tout le monde sur la même longueur d'onde.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-chart-line"></i>
                <h3>Rapports et Analyses</h3>
                <p>Obtenez une vue d'ensemble de la performance de vos projets avec des tableaux de bord intuitifs et des rapports détaillés.</p>
            </div>
        </div>
    </section>

    <section id="roles" class="role-section">
        <h2>Des outils adaptés à chaque rôle</h2>
        <p>Notre application est conçue pour répondre aux besoins spécifiques de chaque membre de votre équipe :</p>
        <div class="role-details">
            <div class="role-card">
                <h3><i class="fas fa-crown"></i> Administrateur</h3>
                <ul>
                    <li>Gestion complète des utilisateurs et des rôles.</li>
                    <li>Surveillance globale des performances de l'application.</li>
                    <li>Accès aux journaux d'activité et audit.</li>
                    <li>Paramètres de sécurité avancés.</li>
                </ul>
            </div>
            <div class="role-card">
                <h3><i class="fas fa-user-tie"></i> Gestionnaire</h3>
                <ul>
                    <li>Création et suivi détaillé des projets.</li>
                    <li>Attribution des tâches et gestion des équipes.</li>
                    <li>Analyse des prédictions de l'IA pour l'optimisation.</li>
                    <li>Communication directe avec les membres.</li>
                </ul>
            </div>
            <div class="role-card">
                <h3><i class="fas fa-user"></i> Utilisateur</h3>
                <ul>
                    <li>Accès facile à ses tâches assignées.</li>
                    <li>Mise à jour de la progression et du statut des tâches.</li>
                    <li>Consultation des projets auxquels il participe.</li>
                    <li>Possibilité de commenter et de joindre des fichiers.</li>
                </ul>
            </div>
        </div>
    </section>

    <footer>
        © 2025 - Groupe1App. Tous droits réservés.
        <p>Conçu pour une gestion de projets plus intelligente et collaborative.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script>
        // Bubble animation
        const canvas = document.getElementById('bubble-canvas');
        const ctx = canvas.getContext('2d');
        let bubbles = [];

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        class Bubble {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = canvas.height + Math.random() * 100;
                this.radius = Math.random() * 10 + 5;
                this.speed = Math.random() * 2 + 1;
                this.color = Math.random() > 0.5 ? '#00C4B4' : '#FF6B6B';
                this.opacity = Math.random() * 0.4 + 0.2;
            }

            update() {
                this.y -= this.speed;
                this.x += Math.sin(this.y * 0.02) * 0.5; // Gentle horizontal sway
                if (this.y < -this.radius) {
                    this.y = canvas.height + this.radius;
                    this.x = Math.random() * canvas.width;
                }
            }

            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                ctx.fillStyle = this.color;
                ctx.globalAlpha = this.opacity;
                ctx.fill();
                ctx.globalAlpha = 1;
            }
        }

        function initBubbles() {
            bubbles = [];
            for (let i = 0; i < 50; i++) {
                bubbles.push(new Bubble());
            }
        }

        function animateBubbles() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            bubbles.forEach(bubble => {
                bubble.update();
                bubble.draw();
            });
            requestAnimationFrame(animateBubbles);
        }

        initBubbles();
        animateBubbles();

        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.getElementById('main-header');
            header.classList.toggle('scrolled', window.scrollY > 50);
        });

        // GSAP Animations
        document.addEventListener('DOMContentLoaded', () => {
            // Hero section animations
            gsap.from('.hero h1', {
                opacity: 0,
                y: 70,
                duration: 1.2,
                ease: 'power3.out',
                rotateX: 10
            });
            gsap.from('.hero p', {
                opacity: 0,
                y: 70,
                duration: 1.2,
                delay: 0.3,
                ease: 'power3.out'
            });
            gsap.from('.hero-buttons a', {
                opacity: 0,
                scale: 0.7,
                duration: 1,
                stagger: 0.2,
                delay: 0.6,
                ease: 'elastic.out(1, 0.5)'
            });

            // Section animations on scroll
            const sections = document.querySelectorAll('.section, .role-section');
            sections.forEach(section => {
                gsap.from(section, {
                    opacity: 0,
                    y: 70,
                    duration: 1,
                    ease: 'power3.out',
                    scrollTrigger: {
                        trigger: section,
                        start: 'top 80%',
                        toggleActions: 'play none none none'
                    }
                });
            });

            // Feature and role card animations
            gsap.from('.feature-item, .role-card', {
                opacity: 0,
                y: 50,
                duration: 0.8,
                stagger: 0.2,
                ease: 'power3.out',
                scrollTrigger: {
                    trigger: '.features-grid, .role-details',
                    start: 'top 80%',
                    toggleActions: 'play none none none'
                }
            });

            // Enhanced hover effects for buttons
            const buttons = document.querySelectorAll('.nav-buttons button, .hero-buttons a');
            buttons.forEach(btn => {
                btn.addEventListener('mouseenter', () => {
                    gsap.to(btn, { 
                        scale: 1.1, 
                        duration: 0.3, 
                        ease: 'power2.out',
                        boxShadow: '0 6px 16px rgba(0, 196, 180, 0.4)'
                    });
                });
                btn.addEventListener('mouseleave', () => {
                    gsap.to(btn, { 
                        scale: 1, 
                        duration: 0.3, 
                        ease: 'power2.out',
                        boxShadow: '0 4px 12px rgba(0, 0, 0, 0.2)'
                    });
                });
            });

            // Enhanced hover effects for cards
            const cards = document.querySelectorAll('.feature-item, .role-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    gsap.to(card, { 
                        scale: 1.05, 
                        boxShadow: '0 10px 25px rgba(0, 0, 0, 0.2)', 
                        duration: 0.3,
                        ease: 'power2.out'
                    });
                });
                card.addEventListener('mouseleave', () => {
                    gsap.to(card, { 
                        scale: 1, 
                        boxShadow: '0 4px 15px rgba(0, 0, 0, 0.1)', 
                        duration: 0.3,
                        ease: 'power2.out'
                    });
                });
            });
        });
    </script>
</body>
</html>