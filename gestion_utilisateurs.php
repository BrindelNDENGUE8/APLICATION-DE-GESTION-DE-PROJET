<?php
session_start();
// La ligne ci-dessous inclut votre fichier et rend la variable $pdo disponible
require_once 'connexion.php';

// ===================================================================================
// PARTIE 1 : LOGIQUE PHP (INCHANGÉE)
// ===================================================================================

$message = '';
$user_to_edit = null;

// --- TRAITEMENT DE L'AJOUT / MODIFICATION D'UN UTILISATEUR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $id = $_POST['id'] ?? null;

    if (empty($nom) || empty($email) || empty($role)) {
        $message = '<div class="message error">Tous les champs sont requis.</div>';
    } else {
        if (!empty($id)) { // Mode modification
            $sql = "UPDATE users SET nom = :nom, email = :email, role = :role WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':nom' => $nom, ':email' => $email, ':role' => $role, ':id' => $id]);

            if (!empty($_POST['mot_de_passe'])) {
                $hashed_password = password_hash($_POST['mot_de_passe'], PASSWORD_ARGON2ID);
                $stmt_pass = $pdo->prepare("UPDATE users SET mot_de_passe = :mot_de_passe WHERE id = :id");
                $stmt_pass->execute([':mot_de_passe' => $hashed_password, ':id' => $id]);
            }
            $_SESSION['message'] = '<div class="message success">Utilisateur mis à jour avec succès !</div>';

        } else { // Mode ajout
             if (empty($_POST['mot_de_passe'])) {
                 $message = '<div class="message error">Le mot de passe est requis pour un nouvel utilisateur.</div>';
             } else {
                $hashed_password = password_hash($_POST['mot_de_passe'], PASSWORD_ARGON2ID);
                $sql = "INSERT INTO users (nom, email, mot_de_passe, role) VALUES (:nom, :email, :mot_de_passe, :role)";
                $stmt = $pdo->prepare($sql);
                try {
                    $stmt->execute([':nom' => $nom, ':email' => $email, ':mot_de_passe' => $hashed_password, ':role' => $role]);
                    $_SESSION['message'] = '<div class="message success">Utilisateur ajouté avec succès !</div>';
                } catch (PDOException $e) {
                    if ($e->getCode() == 23505) {
                         $_SESSION['message'] = '<div class="message error">Erreur : Cet email existe déjà.</div>';
                    } else {
                         $_SESSION['message'] = '<div class="message error">Une erreur est survenue.</div>';
                    }
                }
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// --- TRAITEMENT DE LA SUPPRESSION ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $_SESSION['message'] = '<div class="message success">Utilisateur supprimé.</div>';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- PRÉPARATION POUR LA MODIFICATION ---
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $sql = "SELECT id, nom, email, role FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// --- LECTURE & RECHERCHE AVANCÉE ---
$search_query = "SELECT id, nom, email, role, date_creation FROM users WHERE 1=1";
$params = [];
if (!empty($_GET['search_nom'])) {
    $search_query .= " AND nom ILIKE :nom";
    $params[':nom'] = '%' . $_GET['search_nom'] . '%';
}
if (!empty($_GET['search_email'])) {
    $search_query .= " AND email ILIKE :email";
    $params[':email'] = '%' . $_GET['search_email'] . '%';
}
if (!empty($_GET['search_role'])) {
    $search_query .= " AND role = :role";
    $params[':role'] = $_GET['search_role'];
}
$search_query .= " ORDER BY date_creation DESC";
$stmt = $pdo->prepare($search_query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Gestion des Utilisateurs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');

        :root {
            --bg-color: #121212;
            --panel-color: #1e1e1e;
            --border-color: #333;
            --text-color: #e0e0e0;
            --text-muted: #888;
            --accent-primary: #007bff;
            --accent-secondary: #28a745;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            padding: 2rem;
        }
        
        #particle-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1; /* Place le canvas en arrière-plan */
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background-color: rgba(30, 30, 30, 0.9); /* Fond légèrement transparent */
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
            position: relative;
            z-index: 1;
        }

        h1, h2 {
            color: var(--accent-primary);
            text-align: center;
            margin-bottom: 1.5rem;
        }
        h1 i { margin-right: 10px; }
        h2 { color: var(--text-color); border-bottom: 2px solid var(--accent-primary); padding-bottom: 0.5rem; text-align: left; margin-top: 2rem;}

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            align-items: flex-end;
        }
        
        .form-group { display: flex; flex-direction: column; }
        
        label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        input, select {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-color);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: opacity 0.3s;
            width: 100%;
        }
        .btn:hover { opacity: 0.85; }

        .btn-primary { background-color: var(--accent-primary); color: white; }
        .btn-secondary { background-color: #6c757d; color: white; text-decoration: none; display: block; text-align: center; }

        .table-wrapper { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2rem;
        }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { color: var(--accent-primary); }
        tbody tr:hover { background-color: #2a2a2a; }

        .actions a {
            color: var(--text-muted);
            margin: 0 0.5rem;
            font-size: 1.1rem;
            text-decoration: none;
        }
        .actions a:hover { color: var(--accent-secondary); }
        .actions a.delete:hover { color: #dc3545; }

        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; text-align: center; }
        .message.success { background-color: rgba(40, 167, 69, 0.2); color: #28a745; border: 1px solid #28a745; }
        .message.error { background-color: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid #dc3545; }
    </style>
</head>
<body>
<canvas id="particle-canvas"></canvas>

<div class="container">
    <h1><i class="fas fa-users-cog"></i>Gestion des Utilisateurs</h1>

    <?php if ($message): echo $message; endif; ?>

    <h2><?= $user_to_edit ? 'Modifier l\'utilisateur' : 'Ajouter un utilisateur' ?></h2>
    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
        <input type="hidden" name="id" value="<?= htmlspecialchars($user_to_edit['id'] ?? '') ?>">
        <div class="form-grid">
            <div class="form-group">
                <label for="nom">Nom Complet</label>
                <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($user_to_edit['nom'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_to_edit['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="mot_de_passe">Mot de passe</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="<?= $user_to_edit ? 'Laisser vide pour ne pas changer' : '' ?>">
            </div>
            <div class="form-group">
                <label for="role">Rôle</label>
                <select id="role" name="role" required>
                    <option value="membre" <?= (($user_to_edit['role'] ?? '') === 'membre') ? 'selected' : '' ?>>Membre</option>
                    <option value="chef" <?= (($user_to_edit['role'] ?? '') === 'chef') ? 'selected' : '' ?>>Chef de Projet</option>
                    <option value="admin" <?= (($user_to_edit['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="form-group">
                 <button type="submit" name="save_user" class="btn btn-primary"><?= $user_to_edit ? 'Mettre à jour' : 'Ajouter' ?></button>
            </div>
            <?php if ($user_to_edit): ?>
            <div class="form-group">
                 <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Annuler</a>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <h2>Rechercher des utilisateurs</h2>
    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET">
        <div class="form-grid">
             <div class="form-group">
                <label for="search_nom">Par Nom</label>
                <input type="text" id="search_nom" name="search_nom" value="<?= htmlspecialchars($_GET['search_nom'] ?? '') ?>">
            </div>
             <div class="form-group">
                <label for="search_email">Par Email</label>
                <input type="text" id="search_email" name="search_email" value="<?= htmlspecialchars($_GET['search_email'] ?? '') ?>">
            </div>
             <div class="form-group">
                <label for="search_role">Par Rôle</label>
                <select id="search_role" name="search_role">
                    <option value="">Tous les rôles</option>
                    <option value="membre" <?= (($_GET['search_role'] ?? '') === 'membre') ? 'selected' : '' ?>>Membre</option>
                    <option value="chef" <?= (($_GET['search_role'] ?? '') === 'chef') ? 'selected' : '' ?>>Chef de Projet</option>
                    <option value="admin" <?= (($_GET['search_role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Rechercher</button>
            </div>
             <div class="form-group">
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Réinitialiser</a>
            </div>
        </div>
    </form>

    <h2>Liste des utilisateurs</h2>
    <div class="table-wrapper">
         <table>
             <thead>
                 <tr>
                     <th>ID</th>
                     <th>Nom</th>
                     <th>Email</th>
                     <th>Rôle</th>
                     <th>Date Création</th>
                     <th>Actions</th>
                 </tr>
             </thead>
             <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" style="text-align: center;">Aucun utilisateur trouvé.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['nom']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($user['date_creation']))) ?></td>
                        <td class="actions">
                            <a href="?edit=<?= $user['id'] ?>#form-panel" title="Modifier"><i class="fas fa-edit"></i></a>
                            <a href="?delete=<?= $user['id'] ?>" class="delete" title="Supprimer" onclick="return confirm('Êtes-vous sûr ?');"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
             </tbody>
         </table>
    </div>
</div>

<script>
    // Script pour les messages éphémères
    document.addEventListener('DOMContentLoaded', () => {
        const message = document.querySelector('.message');
        if (message) {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s';
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 500);
            }, 5000);
        }
    });

    // Script pour l'animation de particules en arrière-plan
    const canvas = document.getElementById('particle-canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    let particlesArray;

    const mouse = {
        x: null,
        y: null,
        radius: (canvas.height/100) * (canvas.width/100)
    };

    window.addEventListener('mousemove', e => {
        mouse.x = e.x;
        mouse.y = e.y;
    });
    
    window.addEventListener('resize', () => {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        mouse.radius = (canvas.height/100) * (canvas.width/100);
        init();
    });
    
    window.addEventListener('mouseout', () => {
        mouse.x = undefined;
        mouse.y = undefined;
    });


    class Particle {
        constructor(x, y, directionX, directionY, size, color) {
            this.x = x;
            this.y = y;
            this.directionX = directionX;
            this.directionY = directionY;
            this.size = size;
            this.color = color;
        }

        draw() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2, false);
            ctx.fillStyle = this.color;
            ctx.fill();
        }

        update() {
            if (this.x > canvas.width || this.x < 0) {
                this.directionX = -this.directionX;
            }
            if (this.y > canvas.height || this.y < 0) {
                this.directionY = -this.directionY;
            }

            let dx = mouse.x - this.x;
            let dy = mouse.y - this.y;
            let distance = Math.sqrt(dx*dx + dy*dy);
            if (distance < mouse.radius + this.size){
                if (mouse.x < this.x && this.x < canvas.width - this.size * 10) {
                    this.x += 5;
                }
                if (mouse.x > this.x && this.x > this.size * 10) {
                    this.x -= 5;
                }
                if (mouse.y < this.y && this.y < canvas.height - this.size * 10) {
                    this.y += 5;
                }
                if (mouse.y > this.y && this.y > this.size * 10) {
                    this.y -= 5;
                }
            }

            this.x += this.directionX;
            this.y += this.directionY;
            this.draw();
        }
    }

    function init() {
        particlesArray = [];
        let numberOfParticles = (canvas.height * canvas.width) / 9000;
        for (let i = 0; i < numberOfParticles; i++) {
            let size = (Math.random() * 2) + 1;
            let x = (Math.random() * ((innerWidth - size * 2) - (size * 2)) + size * 2);
            let y = (Math.random() * ((innerHeight - size * 2) - (size * 2)) + size * 2);
            let directionX = (Math.random() * .4) - 0.2;
            let directionY = (Math.random() * .4) - 0.2;
            // Alterne les couleurs vives
            let color = (i % 2 === 0) ? '#007bff' : '#28a745';

            particlesArray.push(new Particle(x, y, directionX, directionY, size, color));
        }
    }

    function animate() {
        requestAnimationFrame(animate);
        ctx.clearRect(0,0,innerWidth, innerHeight);

        for (let i = 0; i < particlesArray.length; i++) {
            particlesArray[i].update();
        }
    }

    init();
    animate();
</script>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
  
    <a href="create_user.php" class="btn btn-secondary" style="width:auto;">← Retour</a>
</div>

</body>
</html>