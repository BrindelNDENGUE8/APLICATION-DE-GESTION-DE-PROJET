<?php
session_start();
// require_once 'connexion.php'; // Votre connexion DB serait ici

// --- Sécurité de base ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'chef'])) {
    // header('Location: login.php');
    // exit();
}

$message = '';
$project_files = [];
$project_name = '';

// =================================================================
// PARTIE API : GÈRE L'UPLOAD ET LA LECTURE DE FICHIERS
// =================================================================
if (isset($_FILES['project_zip'])) {
    header('Content-Type: application/json');
    $upload_dir_zip = 'imports_zip/';
    $extract_dir = 'projets_extraits/';
    if (!is_dir($upload_dir_zip)) mkdir($upload_dir_zip, 0755, true);
    if (!is_dir($extract_dir)) mkdir($extract_dir, 0755, true);

    $file = $_FILES['project_zip'];

    if ($file['error'] === UPLOAD_ERR_OK && pathinfo($file['name'], PATHINFO_EXTENSION) === 'zip') {
        $project_name = basename($file['name'], '.zip');
        $zip_path = $upload_dir_zip . $project_name . '.zip';
        
        if (move_uploaded_file($file['tmp_name'], $zip_path)) {
            $zip = new ZipArchive;
            if ($zip->open($zip_path) === TRUE) {
                $extract_path = $extract_dir . $project_name;
                $zip->extractTo($extract_path);
                $zip->close();

                // Fonction pour lister les fichiers récursivement
                function list_files($dir) {
                    $result = [];
                    $items = scandir($dir);
                    foreach ($items as $item) {
                        if ($item == '.' || $item == '..') continue;
                        $path = $dir . DIRECTORY_SEPARATOR . $item;
                        if (is_dir($path)) {
                            $result[] = ['name' => $item, 'type' => 'folder', 'children' => list_files($path)];
                        } else {
                            $result[] = ['name' => $item, 'type' => 'file', 'path' => realpath($path)];
                        }
                    }
                    return $result;
                }

                echo json_encode(['success' => true, 'projectName' => $project_name, 'files' => list_files($extract_path)]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Impossible d\'ouvrir le fichier ZIP.']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur lors du déplacement du fichier uploadé.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Fichier invalide ou erreur d\'upload. Seuls les .zip sont acceptés.']);
    }
    exit();
}

// --- API pour lire le contenu d'un fichier ---
if (isset($_GET['action']) && $_GET['action'] === 'get_file_content') {
    header('Content-Type: application/json');
    $file_path = $_GET['path'];
    // Sécurité : Assurer que le chemin est bien dans le dossier des projets extraits
    if (strpos(realpath($file_path), realpath('projets_extraits')) === 0 && is_readable($file_path)) {
        $content = file_get_contents($file_path);
        $lang = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        echo json_encode(['success' => true, 'content' => $content, 'language' => $lang]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Accès au fichier non autorisé ou fichier illisible.']);
    }
    exit();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importer un Projet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-okaidia.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    <style>
        /* CSS complet pour l'interface inspirée de VS Code */
        @import url('https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&family=Inter:wght@400;500;600;700&display=swap');
        :root {
            --bg-color: #282a36; --sidebar-bg: #1e1f29; --editor-bg: #21222c;
            --text-primary: #f8f8f2; --text-secondary: #bd93f9; --text-accent: #ff79c6;
            --border-color: #44475a; --accent-color: #8be9fd; --green: #50fa7b; --red: #ff5555;
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-primary); margin: 0; height: 100vh; display: flex; flex-direction: column; }
        .main-wrapper { display: flex; flex-grow: 1; min-height: 0; }
        
        /* Sidebar (Explorateur de fichiers) */
        .file-explorer { width: 280px; background-color: var(--sidebar-bg); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; }
        .file-explorer-header { padding: 1rem; font-weight: 600; border-bottom: 1px solid var(--border-color); }
        #file-tree { flex-grow: 1; overflow-y: auto; padding: 0.5rem; }
        .file-tree-item { list-style: none; padding: 0.3rem 0.5rem; cursor: pointer; border-radius: 4px; display: flex; align-items: center; gap: 0.5rem; }
        .file-tree-item:hover { background-color: #ffffff10; }
        .file-tree-item.active { background-color: var(--accent-color); color: var(--bg-color); }
        .folder > .folder-header::before { content: '\f07b'; font-family: "Font Awesome 5 Free"; font-weight: 900; }
        .file::before { content: '\f15c'; font-family: "Font Awesome 5 Free"; font-weight: 400; }
        .folder > ul { padding-left: 1rem; display: none; }
        .folder.open > ul { display: block; }
        .folder.open > .folder-header::before { content: '\f07c'; }

        /* Contenu principal */
        .content-area { flex-grow: 1; display: flex; flex-direction: column; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 1rem; background-color: var(--sidebar-bg); border-bottom: 1px solid var(--border-color); }
        .btn { background: none; border: 1px solid var(--accent-color); color: var(--accent-color); padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer; transition: all 0.2s; }
        .btn:hover { background-color: var(--accent-color); color: var(--bg-color); }
        
        .editor-readme-grid { flex-grow: 1; display: grid; grid-template-columns: 2fr 1fr; min-height: 0; }
        @media (max-width: 1024px) { .editor-readme-grid { grid-template-columns: 1fr; } }
        
        /* Éditeur de code */
        #code-viewer-container { position: relative; height: 100%; }
        #code-viewer { height: 100%; overflow: auto; }
        #code-viewer pre { margin: 0; }
        .placeholder-text { display: flex; justify-content: center; align-items: center; height: 100%; color: #6272a4; font-size: 1.2rem; }

        /* Panneau README */
        .readme-panel { border-left: 1px solid var(--border-color); display: flex; flex-direction: column; }
        #readme-editor, #readme-preview { flex-grow: 1; padding: 1rem; min-height: 0; overflow-y: auto; font-family: 'Fira Code', monospace;}
        #readme-editor { background: #21222c; border: none; color: white; resize: none; outline: none; }
        #readme-preview { background: #282a36; }
        #readme-preview h1, #readme-preview h2 { border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin: 1rem 0; }
        #readme-preview code { background: #44475a; padding: 2px 5px; border-radius: 4px; }
        
        /* Zone d'import initiale */
        #import-container { width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; flex-direction: column; padding: 2rem; }
        #drop-zone { border: 3px dashed var(--border-color); border-radius: 20px; width: 100%; max-width: 600px; padding: 4rem 2rem; text-align: center; transition: all 0.3s; }
        #drop-zone.dragover { border-color: var(--accent-color); background-color: #ffffff10; }
        
        /* Barre d'actions en bas */
        .bottom-actions { background-color: var(--sidebar-bg); border-top: 1px solid var(--border-color); padding: 0.75rem 1rem; display: flex; justify-content: flex-end; gap: 1rem; }
    </style>
</head>
<body>

<div id="import-view">
    <div id="import-container">
        <h1>Importer un Projet</h1>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Sélectionnez ou glissez-déposez un fichier .zip pour commencer.</p>
        <div id="drop-zone">
            <i class="fas fa-file-zipper fa-3x" style="margin-bottom: 1rem; color: #6272a4;"></i>
            <p>Glissez votre fichier .zip ici</p>
            <p style="margin: 0.5rem 0; color: #6272a4;">ou</p>
            <input type="file" id="zip-input" accept=".zip" style="display: none;">
            <button class="btn" onclick="document.getElementById('zip-input').click();">Sélectionner un fichier</button>
        </div>
        <p id="upload-status" style="margin-top: 1rem;"></p>
    </div>
</div>

<div id="editor-view" style="display: none; height: 100%; display:flex; flex-direction:column;">
    <div class="main-wrapper">
        <aside class="file-explorer">
            <div class="file-explorer-header" id="project-title"></div>
            <ul id="file-tree"></ul>
        </aside>
        <div class="content-area">
            <div class="top-bar">
                <span id="current-file-path"></span>
                <a href="#" id="download-zip-btn" class="btn"><i class="fas fa-download"></i> Télécharger le ZIP</a>
            </div>
            <div class="editor-readme-grid">
                <div id="code-viewer-container">
                    <div id="code-viewer" class="language-markup">
                         <div class="placeholder-text">Sélectionnez un fichier à visualiser</div>
                    </div>
                </div>
                <div class="readme-panel">
                    <textarea id="readme-editor" placeholder="Rédigez votre README ici en utilisant la syntaxe Markdown..."></textarea>
                    <div id="readme-preview"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="bottom-actions">
        <button class="btn" style="border-color: var(--red); color: var(--red);"><i class="fas fa-times"></i> Annuler l'Import</button>
        <button class="btn" style="border-color: var(--green); color: var(--green);"><i class="fas fa-check"></i> Valider et Intégrer le Projet</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const importView = document.getElementById('import-view');
    const editorView = document.getElementById('editor-view');
    const dropZone = document.getElementById('drop-zone');
    const zipInput = document.getElementById('zip-input');
    const uploadStatus = document.getElementById('upload-status');
    const fileTree = document.getElementById('file-tree');
    const projectTitle = document.getElementById('project-title');
    const codeViewer = document.getElementById('code-viewer');
    const currentFilePathEl = document.getElementById('current-file-path');
    const readmeEditor = document.getElementById('readme-editor');
    const readmePreview = document.getElementById('readme-preview');

    // --- Gestion du Drag & Drop ---
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileUpload(files[0]);
        }
    });
    zipInput.addEventListener('change', () => {
        if (zipInput.files.length > 0) {
            handleFileUpload(zipInput.files[0]);
        }
    });

    // --- Upload et traitement du ZIP ---
    async function handleFileUpload(file) {
        uploadStatus.textContent = 'Upload en cours...';
        const formData = new FormData();
        formData.append('project_zip', file);

        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                uploadStatus.textContent = 'Projet traité avec succès !';
                displayEditor(result.projectName, result.files);
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            uploadStatus.textContent = `Erreur : ${error.message}`;
        }
    }

    // --- Affichage de l'éditeur ---
    function displayEditor(projectName, files) {
        importView.style.display = 'none';
        editorView.style.display = 'flex';
        projectTitle.textContent = projectName;
        fileTree.innerHTML = createFileTreeHTML(files);
        document.getElementById('download-zip-btn').href = `imports_zip/${projectName}.zip`;
    }

    // --- Création de l'arborescence de fichiers ---
    function createFileTreeHTML(items) {
        let html = '';
        items.forEach(item => {
            if (item.type === 'folder') {
                html += `<li class="folder"><div class="folder-header"><i class="fas fa-folder"></i> ${item.name}</div><ul>${createFileTreeHTML(item.children)}</ul></li>`;
            } else {
                html += `<li class="file" data-path="${item.path}"><i class="fas fa-file-code"></i> ${item.name}</li>`;
            }
        });
        return html;
    }

    // --- Événements sur l'arborescence ---
    fileTree.addEventListener('click', async (e) => {
        const target = e.target;
        if (target.closest('.folder-header')) {
            target.closest('.folder').classList.toggle('open');
        } else if (target.closest('.file')) {
            const fileItem = target.closest('.file');
            
            // Mettre en surbrillance le fichier actif
            document.querySelectorAll('.file.active').forEach(el => el.classList.remove('active'));
            fileItem.classList.add('active');

            const filePath = fileItem.dataset.path;
            currentFilePathEl.textContent = fileItem.textContent.trim();
            codeViewer.innerHTML = `<div class="placeholder-text">Chargement...</div>`;
            
            try {
                const response = await fetch(`?action=get_file_content&path=${encodeURIComponent(filePath)}`);
                const result = await response.json();
                if (result.success) {
                    const codeElement = document.createElement('code');
                    codeElement.className = `language-${result.language || 'markup'}`;
                    codeElement.textContent = result.content;
                    
                    const preElement = document.createElement('pre');
                    preElement.className = codeElement.className;
                    preElement.appendChild(codeElement);
                    
                    codeViewer.innerHTML = '';
                    codeViewer.appendChild(preElement);
                    Prism.highlightAllUnder(codeViewer);
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                 codeViewer.innerHTML = `<div class="placeholder-text" style="color:var(--red);">${error.message}</div>`;
            }
        }
    });

    // --- Éditeur README Markdown ---
    readmeEditor.addEventListener('input', () => {
        // Simple Markdown parser
        let html = readmeEditor.value
            .replace(/^# (.*$)/gim, '<h1>$1</h1>')
            .replace(/^## (.*$)/gim, '<h2>$1</h2>')
            .replace(/\*\*(.*)\*\*/gim, '<strong>$1</strong>')
            .replace(/\*(.*)\*/gim, '<em>$1</em>')
            .replace(/`([^`]+)`/gim, '<code>$1</code>')
            .replace(/\n/g, '<br>');
        readmePreview.innerHTML = html;
    });
});
</script>
</body>
</html>