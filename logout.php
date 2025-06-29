<?php
// 1. Démarrer la session
// Il est impératif de démarrer la session pour pouvoir y accéder et la détruire.
session_start();

// 2. Détruire toutes les variables de session
// session_unset() libère toutes les variables de la session en cours.
session_unset();

// 3. Détruire la session elle-même
// session_destroy() détruit toutes les données associées à la session actuelle.
session_destroy();

// 4. Rediriger l'utilisateur vers la page de connexion
// Après la déconnexion, l'utilisateur est renvoyé vers la page de connexion (ou une autre page de votre choix).
// Assurez-vous d'avoir une page nommée 'login.php' ou modifiez le nom du fichier ci-dessous.
header("Location: login.php");

// 5. Stopper l'exécution du script
// exit() est une bonne pratique après une redirection pour s'assurer qu'aucun autre code n'est exécuté.
exit();
?>