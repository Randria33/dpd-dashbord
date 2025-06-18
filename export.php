<?php
require_once 'autoload.php';

use Services\IncidentService;

$service = new IncidentService();

// Récupérer les paramètres
$format = $_GET['format'] ?? 'csv';
$filters = [
    'type' => $_GET['type'] ?? null,
    'tournee' => $_GET['tournee'] ?? null,
    'ville' => $_GET['ville'] ?? null,
    'statut' => $_GET['statut'] ?? null,
    'date_debut' => $_GET['date_debut'] ?? null,
    'date_fin' => $_GET['date_fin'] ?? null
];

// Si c'est une requête de téléchargement direct
if (isset($_GET['download'])) {
    try {
        // Récupérer tous les incidents
        $result = $service->getAllIncidents($filters, 1, 10000);
        $incidents = $result['data'];
        
        switch ($format) {
            case 'csv':
                exportCSV($incidents);
                break;
            case 'json':
                exportJSON($incidents);
                break;
            default:
                die('Format non supporté');
        }
    } catch (Exception $e) {
        die('Erreur : ' . $e->getMessage());
    }
}

function exportCSV($incidents) {
    $filename = 'incidents_export_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-têtes
    fputcsv($output, [
        'ID',
        'Date',
        'Tournée',
        'Type',
        'Client',
        'Adresse',
        'Ville',
        'Code Postal',
        'N° Colis',
        'N° Réclamation',
        'Statut',
        'Priorité',
        'Description',
        'Créé le'
    ], ';');
    
    // Données
    foreach ($incidents as $incident) {
        fputcsv($output, [
            $incident['id'],
            $incident['date_incident'],
            $incident['tournee_numero'],
            $incident['type_incident_nom'],
            $incident['nom_client'],
            $incident['adresse_complete'],
            $incident['ville'],
            $incident['code_postal'],
            $incident['numero_colis'],
            $incident['numero_reclamation'],
            $incident['statut'],
            $incident['priorite'],
            $incident['description'],
            $incident['created_at']
        ], ';');
    }
    
    fclose($output);
    exit;
}

function exportJSON($incidents) {
    $filename = 'incidents_export_' . date('Y-m-d_His') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($incidents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Données pour les filtres
$tournees = $service->getTournees();
$types = $service->getTypesIncidents();
$villes = $service->getVilles();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export des données - Dashboard DPD</title>
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

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .format-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .format-option {
            padding: 1.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .format-option:hover {
            border-color: #2563eb;
            background: #f9fafb;
        }

        .format-option.selected {
            border-color: #2563eb;
            background: #dbeafe;
        }

        .format-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .format-name {
            font-weight: 600;
            color: #1f2937;
        }

        .format-desc {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
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

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
        }

        .stats-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 0.5rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2563eb;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚚 Export des données</h1>
        <div class="nav">
            <a href="index.php">Dashboard</a>
            <a href="admin.php">Administration</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h2 class="card-title">📤 Exporter les incidents</h2>
            
            <form method="GET" action="" id="exportForm">
                <input type="hidden" name="download" value="1">
                
                <!-- Sélection du format -->
                <h3 class="section-title">Format d'export</h3>
                <div class="format-selector">
                    <div class="format-option selected" onclick="selectFormat('csv')">
                        <div class="format-icon">📊</div>
                        <div class="format-name">CSV</div>
                        <div class="format-desc">Excel, LibreOffice</div>
                    </div>
                    <div class="format-option" onclick="selectFormat('json')">
                        <div class="format-icon">{ }</div>
                        <div class="format-name">JSON</div>
                        <div class="format-desc">Développeurs, API</div>
                    </div>
                </div>
                <input type="hidden" name="format" id="format" value="csv">

                <!-- Filtres -->
                <h3 class="section-title">Filtres (optionnel)</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Type d'incident</label>
                        <select name="type" class="form-control">
                            <option value="">Tous les types</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?= htmlspecialchars($type['nom']) ?>">
                                    <?= htmlspecialchars($type['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tournée</label>
                        <select name="tournee" class="form-control">
                            <option value="">Toutes les tournées</option>
                            <?php foreach ($tournees as $tournee): ?>
                                <option value="<?= htmlspecialchars($tournee['numero']) ?>">
                                    <?= htmlspecialchars($tournee['numero'] . ' - ' . $tournee['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ville</label>
                        <select name="ville" class="form-control">
                            <option value="">Toutes les villes</option>
                            <?php foreach ($villes as $ville): ?>
                                <option value="<?= htmlspecialchars($ville) ?>">
                                    <?= htmlspecialchars($ville) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Statut</label>
                        <select name="statut" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="nouveau">Nouveau</option>
                            <option value="en_cours">En cours</option>
                            <option value="resolu">Résolu</option>
                            <option value="ferme">Fermé</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date début</label>
                        <input type="date" name="date_debut" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date fin</label>
                        <input type="date" name="date_fin" class="form-control">
                    </div>
                </div>

                <!-- Aperçu -->
                <div id="preview">
                    <?php
                    // Récupérer un aperçu des données
                    $previewResult = $service->getAllIncidents([], 1, 1);
                    $totalIncidents = $previewResult['total'];
                    ?>
                    <div class="stats-preview">
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($totalIncidents, 0, ',', ' ') ?></div>
                            <div class="stat-label">incidents à exporter</div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        Télécharger l'export
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        Retour au dashboard
                    </a>
                </div>
            </form>
        </div>

        <div class="alert">
            <strong>💡 Conseils :</strong>
            <ul style="margin-left: 1.5rem; margin-top: 0.5rem;">
                <li>Le fichier CSV peut être ouvert dans Excel, LibreOffice ou Google Sheets</li>
                <li>Le format JSON est utile pour les intégrations avec d'autres systèmes</li>
                <li>Les exports peuvent prendre quelques secondes pour de gros volumes</li>
                <li>Les filtres permettent de limiter l'export aux données pertinentes</li>
            </ul>
        </div>
    </div>

    <script>
        function selectFormat(format) {
            // Mettre à jour l'input hidden
            document.getElementById('format').value = format;
            
            // Mettre à jour l'apparence des options
            document.querySelectorAll('.format-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }

        // Mettre à jour l'aperçu en temps réel
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('change', updatePreview);
        });

        async function updatePreview() {
            // Ici on pourrait faire une requête AJAX pour obtenir le nombre exact
            // Pour l'instant, on garde le nombre total
        }
    </script>
</body>
</html>