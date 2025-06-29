<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrôle de l'IA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap');

        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f2f5;
            color: #333;
            margin: 0;
            padding: 2em;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 800px;
            background: #fff;
            padding: 2em;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .header {
            margin-bottom: 2em;
        }

        .header h1 {
            font-size: 2.5em;
            color: #2c3e50;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header .fas {
            margin-right: 0.5em;
            color: #3498db;
        }

        .control-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5em;
            padding: 1.5em;
            background-color: #f8f9fa;
            border-radius: 10px;
        }

        .control-section p {
            margin: 0;
            font-size: 1.2em;
            font-weight: 500;
        }

        /* Modern Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2ecc71;
        }

        input:focus + .slider {
            box-shadow: 0 0 1px #2ecc71;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        #predictions {
            display: none;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5em;
            margin-bottom: 2em;
        }

        .prediction-card {
            background: #fff;
            padding: 1.5em;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: left;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .prediction-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.12);
        }

        .prediction-card h3 {
            margin-top: 0;
            color: #3498db;
            border-bottom: 2px solid #f0f2f5;
            padding-bottom: 0.5em;
        }

        .prediction-card p {
            font-size: 0.95em;
            line-height: 1.6;
        }
        
        .prediction-card .label {
            font-weight: 700;
            color: #2c3e50;
        }

        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .update-btn, .back-btn {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 0.8em 1.5em;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none; /* For the back button anchor */
            display: inline-flex;
            align-items: center;
        }

        .update-btn .fas, .back-btn .fas {
            margin-right: 0.5em;
        }
        
        .back-btn {
            background-color: #95a5a6;
        }

        .update-btn:hover, .back-btn:hover {
            transform: translateY(-3px);
        }
        
        .update-btn:hover {
            background-color: #2980b9;
        }
        
        .back-btn:hover {
            background-color: #7f8c8d;
        }

        .update-btn.updating {
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-robot"></i> Contrôle de l’IA (module prédictif)</h1>
    </div>

    <div class="control-section">
        <p>Activer ou désactiver la prédiction de retard</p>
        <label class="toggle-switch">
            <input type="checkbox" id="iaToggle">
            <span class="slider"></span>
        </label>
    </div>

    <div id="predictions">
        <div class="prediction-card">
            <h3><i class="fas fa-exclamation-triangle"></i> Risque Élevé</h3>
            <p><span class="label">Cause Possible:</span> Forte demande imprévue sur le serveur.</p>
            <p><span class="label">Suggestion:</span> Allouer dynamiquement des ressources supplémentaires.</p>
        </div>
        <div class="prediction-card">
            <h3><i class="fas fa-shield-alt"></i> Risque Modéré</h3>
            <p><span class="label">Cause Possible:</span> Maintenance programmée d'un service dépendant.</p>
            <p><span class="label">Suggestion:</span> Informer les utilisateurs d'un ralentissement potentiel.</p>
        </div>
        <div class="prediction-card">
            <h3><i class="fas fa-check-circle"></i> Risque Faible</h3>
            <p><span class="label">Cause Possible:</span> Fluctuation mineure du trafic réseau.</p>
            <p><span class="label">Suggestion:</span> Aucune action immédiate requise, monitoring continu.</p>
        </div>
    </div>

    <div class="actions">
        <a href="create_user.php" class="back-btn"><i class="fas fa-arrow-left"></i> Retour</a>
        <button class="update-btn" id="updateBtn" disabled><i class="fas fa-sync-alt"></i> Forcer une mise à jour</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const iaToggle = document.getElementById('iaToggle');
        const predictionsSection = document.getElementById('predictions');
        const updateBtn = document.getElementById('updateBtn');

        iaToggle.addEventListener('change', function() {
            if (this.checked) {
                predictionsSection.style.display = 'grid';
                updateBtn.disabled = false;
            } else {
                predictionsSection.style.display = 'none';
                updateBtn.disabled = true;
            }
        });

        updateBtn.addEventListener('click', function() {
            // Add updating animation
            this.classList.add('updating');
            this.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Réévaluation en cours...';

            // Simulate a delay for re-evaluation
            setTimeout(() => {
                this.classList.remove('updating');
                this.innerHTML = '<i class="fas fa-sync-alt"></i> Forcer une mise à jour';
                
                // Optional: You could fetch new data and update the cards here.
                // For demonstration, we'll just show an alert.
                alert('Les risques ont été réévalués avec succès !');
            }, 2000);
        });
    });
</script>

</body>
</html>