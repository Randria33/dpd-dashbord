<?php
require_once 'autoload.php';

use Utils\Auth;
use Services\IncidentService;

// Vérifier l'authentification
Auth::requireAuth();

$service = new IncidentService();
$message = '';
$error = '';
$importResult = null;

// Traiter l'upload du fichier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvfile'])) {
    try {
        $uploadedFile = $_FILES['csvfile'];
        
        // Vérifier s'il y a une erreur d'upload
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur lors de l\'upload du fichier');
        }
        
        // Vérifier l'extension
        $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        if ($fileExtension !== 'csv') {
            throw new Exception('Le fichier doit être au format CSV');
        }
        
        // Créer le dossier uploads s'il n'existe pas
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Générer un nom unique pour le fichier
        $fileName = 'import_' . date('Y-m-d_His') . '_' . basename($uploadedFile['name']);
        $filePath = $uploadDir . $fileName;
        
        // Déplacer le fichier
        if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
            throw new Exception('Impossible de déplacer le fichier uploadé');
        }
        
        // Importer le CSV
        $importResult = $service->importCSV($filePath);
        $message = "Import terminé : {$importResult['imported']} incidents importés";
        
        // Supprimer le fichier après import (optionnel)
        // unlink($filePath);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import CSV - Dashboard DPD</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            line-height: 1.6;
        }

        .header {
            background: #1f2937;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.5rem;
        }

        .nav {
            display: flex;
            gap: 1rem;
        }

        .nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: background 0.3s;
        }

        .nav a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #1f2937;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e3a8a;
            border: 1px solid #bfdbfe;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }

        .file-input {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #f3f4f6;
            border: 2px dashed #d1d5db;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            width: 100%;
        }

        .file-input-label:hover {
            background: #e5e7eb;
            border-color: #9ca3af;
        }

        .file-input-label.has-file {
            background: #dbeafe;
            border-color: #3b82f6;
            border-style: solid;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            margin-left: 0.5rem;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .format-info {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 2rem;
        }

        .format-info h3 {
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }

        .format-info pre {
            background: #1f2937;
            color: #f3f4f6;
            padding: 1rem;
            border-radius: 0.375rem;
            overflow-x: auto;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .error-list {
            margin-top: 1rem;
            max-height: 200px;
            overflow-y: auto;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 0.375rem;
            padding: 1rem;
        }

        .error-list ul {
            list-style: none;
            padding: 0;
        }

        .error-list li {
            color: #991b1b;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .success-icon {
            color: #10b981;
            font-size: 3rem;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚚 Import CSV - DPD</h1>
        <div class="nav">
            <a href="index.php">Dashboard</a>
            <a href="admin.php">Administration</a>
            <a href="logout.php">Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($message) ?>
                <?php if ($importResult && !empty($importResult['errors'])): ?>
                    <div class="error-list">
                        <strong>Erreurs rencontrées :</strong>
                        <ul>
                            <?php foreach ($importResult['errors'] as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">📥 Importer des incidents depuis un fichier CSV</h2>
            
            <form method="POST" enctype="multipart/form-data" id="importForm">
                <div class="form-group">
                    <label class="form-label">Sélectionner un fichier CSV</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="csvfile" id="csvfile" class="file-input" accept=".csv" required>
                        <label for="csvfile" class="file-input-label" id="fileLabel">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">📁</div>
                            <div>Cliquez pour sélectionner un fichier CSV</div>
                            <div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">ou glissez-déposez le fichier ici</div>
                        </label>
                    </div>
                </div>

                <div style="display: flex; align-items: center;">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        Importer le fichier
                    </button>
                    <a href="admin.php" class="btn btn-secondary">
                        Retour à l'administration
                    </a>
                </div>
            </form>
        </div>

        <div class="format-info">
            <h3>📋 Format du fichier CSV</h3>
            <p style="margin-bottom: 1rem;">Le fichier CSV doit contenir les colonnes suivantes (dans cet ordre) :</p>
            <pre>tournee,type,nom,adresse,colis,reclamation,date,description</pre>
            
            <h3 style="margin-top: 1.5rem;">📝 Exemple de contenu</h3>
            <pre>310,Réclamation,Jean Dupont,123 Rue de la Paix 75001 Paris,COL123456,REC789,2024-01-15,Client mécontent
320,Colis endommagé,Marie Martin,45 Avenue des Champs 75008 Paris,COL789012,REC456,2024-01-14,Carton abîmé
330,Retard livraison,Pierre Bernard,78 Boulevard Haussmann 75009 Paris,COL345678,,2024-01-13,Livraison en retard</pre>

            <div class="alert alert-info" style="margin-top: 1rem;">
                <strong>Notes importantes :</strong>
                <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                    <li>Le fichier doit être encodé en UTF-8</li>
                    <li>La première ligne doit contenir les en-têtes</li>
                    <li>Les champs peuvent être séparés par des virgules ou des points-virgules</li>
                    <li>Les dates doivent être au format AAAA-MM-JJ</li>
                    <li>Les champs vides sont autorisés pour 'reclamation' et 'description'</li>
                </ul>
            </div>
        </div>

        <?php if ($importResult && $importResult['imported'] > 0): ?>
            <div class="card" style="text-align: center;">
                <div class="success-icon">✅</div>
                <h3 style="color: #065f46; margin-bottom: 1rem;">Import réussi !</h3>
                <p><?= $importResult['imported'] ?> incident(s) ont été importés avec succès.</p>
                <a href="admin.php" class="btn btn-primary" style="margin-top: 1rem;">
                    Voir les incidents
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Gestion du nom du fichier sélectionné
        document.getElementById('csvfile').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            const fileLabel = document.getElementById('fileLabel');
            
            if (fileName) {
                fileLabel.classList.add('has-file');
                fileLabel.innerHTML = `
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📄</div>
                    <div style="font-weight: 600;">${fileName}</div>
                    <div style="font-size: 0.875rem; color: #3b82f6; margin-top: 0.5rem;">Fichier sélectionné</div>
                `;
            }
        });

        // Drag and drop
        const fileLabel = document.getElementById('fileLabel');
        const fileInput = document.getElementById('csvfile');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileLabel.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            fileLabel.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileLabel.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            fileLabel.style.background = '#dbeafe';
            fileLabel.style.borderColor = '#3b82f6';
        }

        function unhighlight(e) {
            fileLabel.style.background = '';
            fileLabel.style.borderColor = '';
        }

        fileLabel.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files.length > 0 && files[0].name.endsWith('.csv')) {
                fileInput.files = files;
                const event = new Event('change', { bubbles: true });
                fileInput.dispatchEvent(event);
            }
        }
    </script>
</body>
</html>