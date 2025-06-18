
<?php
require_once 'autoload.php';

use Utils\Auth;
use Services\IncidentService;
use Config\Database;

// Vérifier l'authentification
Auth::requireAuth();

$service = new IncidentService();
$db = Database::getInstance()->getConnection();
$user = Auth::getUser();

// Gérer les actions
$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Traiter les formulaires POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'create':
                $data = [
                    'tournee_id' => $_POST['tournee_id'],
                    'type_incident_id' => $_POST['type_incident_id'],
                    'nom_client' => $_POST['nom_client'],
                    'adresse_complete' => $_POST['adresse_complete'],
                    'numero_colis' => $_POST['numero_colis'],
                    'numero_reclamation' => $_POST['numero_reclamation'],
                    'date_incident' => $_POST['date_incident'],
                    'description' => $_POST['description'],
                    'statut' => $_POST['statut'],
                    'priorite' => $_POST['priorite'],
                    'client_repondu' => $_POST['client_repondu'] ?? 'en_attente'
                ];
                
                // Gérer l'upload de fichier
                if (isset($_FILES['fichier_reclamation']) && $_FILES['fichier_reclamation']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/uploads/reclamations/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileName = uniqid() . '_' . basename($_FILES['fichier_reclamation']['name']);
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['fichier_reclamation']['tmp_name'], $filePath)) {
                        $data['fichier_reclamation'] = 'uploads/reclamations/' . $fileName;
                    }
                }
                
                $service->createIncident($data);
                $message = "Incident créé avec succès";
                header('Location: admin.php?message=' . urlencode($message));
                exit;
                break;
                
            case 'edit':
                $id = $_POST['id'];
                $data = [
                    'tournee_id' => $_POST['tournee_id'],
                    'type_incident_id' => $_POST['type_incident_id'],
                    'nom_client' => $_POST['nom_client'],
                    'adresse_complete' => $_POST['adresse_complete'],
                    'numero_colis' => $_POST['numero_colis'],
                    'numero_reclamation' => $_POST['numero_reclamation'],
                    'date_incident' => $_POST['date_incident'],
                    'description' => $_POST['description'],
                    'client_repondu' => $_POST['client_repondu'] ?? 'en_attente'
                ];
                
                // Pour les chefs d'équipe, gérer uniquement les remarques
                if ($user['role'] === 'chef_equipe') {
                    if (!empty($_POST['nouvelle_remarque'])) {
                        $sql = "INSERT INTO remarques_incidents (incident_id, utilisateur_id, remarque) VALUES (:incident_id, :user_id, :remarque)";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':incident_id' => $id,
                            ':user_id' => $user['id'],
                            ':remarque' => $_POST['nouvelle_remarque']
                        ]);
                        
                        // Logger l'action manuellement
                        $logSql = "INSERT INTO logs_activite (utilisateur_id, action, entite, entite_id, type_action, created_at) 
                                   VALUES (:user_id, :action, :entite, :entite_id, :type_action, NOW())";
                        $logStmt = $db->prepare($logSql);
                        $logStmt->execute([
                            ':user_id' => $user['id'],
                            ':action' => 'Remarque ajoutée',
                            ':entite' => 'incident',
                            ':entite_id' => $id,
                            ':type_action' => 'remarque'
                        ]);
                        
                        $message = "Remarque ajoutée avec succès";
                    }
                    
                    // Mettre à jour seulement client_repondu pour les chefs d'équipe
                    if (isset($_POST['client_repondu'])) {
                        $updSql = "UPDATE incidents SET client_repondu = :client_repondu WHERE id = :id";
                        $updStmt = $db->prepare($updSql);
                        $updStmt->execute([
                            ':client_repondu' => $_POST['client_repondu'],
                            ':id' => $id
                        ]);
                    }
                } else {
                    // Admin peut tout modifier
                    $data['statut'] = $_POST['statut'];
                    $data['priorite'] = $_POST['priorite'];
                    
                    // Gérer l'upload de fichier
                    if (isset($_FILES['fichier_reclamation']) && $_FILES['fichier_reclamation']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = __DIR__ . '/uploads/reclamations/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $fileName = uniqid() . '_' . basename($_FILES['fichier_reclamation']['name']);
                        $filePath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['fichier_reclamation']['tmp_name'], $filePath)) {
                            $data['fichier_reclamation'] = 'uploads/reclamations/' . $fileName;
                        }
                    }
                    
                    $service->updateIncident($id, $data);
                    
                    // Gérer les validations en attente
                    if (isset($_POST['valider_changement'])) {
                        $validationId = $_POST['validation_id'];
                        $sql = "UPDATE validations_statut SET statut_validation = 'approuve', valide_par = :user_id, validated_at = NOW() WHERE id = :id";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([':user_id' => $user['id'], ':id' => $validationId]);
                        
                        // Appliquer le changement de statut
                        $valSql = "SELECT * FROM validations_statut WHERE id = :id";
                        $valStmt = $db->prepare($valSql);
                        $valStmt->execute([':id' => $validationId]);
                        $validation = $valStmt->fetch();
                        
                        if ($validation) {
                            $updSql = "UPDATE incidents SET statut = :statut, validation_requise = FALSE, valide_par = :user_id, date_validation = NOW() WHERE id = :id";
                            $updStmt = $db->prepare($updSql);
                            $updStmt->execute([
                                ':statut' => $validation['nouveau_statut'],
                                ':user_id' => $user['id'],
                                ':id' => $validation['incident_id']
                            ]);
                        }
                    }
                    
                    $message = "Incident mis à jour avec succès";
                }
                
                header('Location: admin.php?message=' . urlencode($message));
                exit;
                break;
                
            case 'add_remark':
                $incidentId = $_POST['incident_id'];
                $remarque = $_POST['remarque'];
                
                if (!empty($remarque)) {
                    $sql = "INSERT INTO remarques_incidents (incident_id, utilisateur_id, remarque) VALUES (:incident_id, :user_id, :remarque)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':incident_id' => $incidentId,
                        ':user_id' => $user['id'],
                        ':remarque' => $remarque
                    ]);
                    
                    // Mettre à jour la colonne remarques de l'incident
                    $updSql = "UPDATE incidents SET remarques = CONCAT(COALESCE(remarques, ''), :new_remark) WHERE id = :id";
                    $updStmt = $db->prepare($updSql);
                    $updStmt->execute([
                        ':new_remark' => "\n[" . date('d/m/Y H:i') . " - " . $user['nom'] . "] " . $remarque,
                        ':id' => $incidentId
                    ]);
                    
                    // Logger l'action manuellement au lieu d'utiliser la procédure
                    $logSql = "INSERT INTO logs_activite (utilisateur_id, action, entite, entite_id, type_action, created_at) 
                               VALUES (:user_id, :action, :entite, :entite_id, :type_action, NOW())";
                    $logStmt = $db->prepare($logSql);
                    $logStmt->execute([
                        ':user_id' => $user['id'],
                        ':action' => 'Remarque ajoutée',
                        ':entite' => 'incident',
                        ':entite_id' => $incidentId,
                        ':type_action' => 'remarque'
                    ]);
                    
                    $message = "Remarque ajoutée avec succès";
                }
                
                header('Location: admin.php?message=' . urlencode($message));
                exit;
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Gérer la suppression
if ($action === 'delete' && isset($_GET['id']) && $user['role'] === 'admin') {
    try {
        $service->deleteIncident($_GET['id']);
        $message = "Incident supprimé avec succès";
        header('Location: admin.php?message=' . urlencode($message));
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer les messages
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Données pour les formulaires
$tournees = $service->getTournees();
$types = $service->getTypesIncidents();

// Pour l'édition
$incident = null;
$remarques = [];
$validationEnAttente = null;

if ($action === 'edit' && isset($_GET['id'])) {
    $incident = $service->getIncident($_GET['id']);
    
    // Récupérer les remarques
    $remSql = "SELECT r.*, u.nom, u.prenom FROM remarques_incidents r 
               JOIN utilisateurs u ON r.utilisateur_id = u.id 
               WHERE r.incident_id = :id ORDER BY r.created_at DESC";
    $remStmt = $db->prepare($remSql);
    $remStmt->execute([':id' => $_GET['id']]);
    $remarques = $remStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Vérifier s'il y a une validation en attente
    if ($user['role'] === 'admin') {
        $valSql = "SELECT v.*, u.nom as demandeur_nom, u.prenom as demandeur_prenom 
                   FROM validations_statut v 
                   JOIN utilisateurs u ON v.utilisateur_id = u.id 
                   WHERE v.incident_id = :id AND v.statut_validation = 'en_attente'";
        $valStmt = $db->prepare($valSql);
        $valStmt->execute([':id' => $_GET['id']]);
        $validationEnAttente = $valStmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gestion des Incidents DPD</title>
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

        .nav a:hover, .nav a.active {
            background: rgba(255, 255, 255, 0.1);
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
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

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
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

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }

        tr:hover {
            background: #f9fafb;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-nouveau {
            background: #dbeafe;
            color: #1e3a8a;
        }

        .status-en_cours {
            background: #fef3c7;
            color: #92400e;
        }

        .status-resolu {
            background: #d1fae5;
            color: #065f46;
        }

        .status-ferme {
            background: #e5e7eb;
            color: #374151;
        }

        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .priority-urgente {
            background: #fee2e2;
            color: #991b1b;
        }

        .priority-haute {
            background: #fed7aa;
            color: #92400e;
        }

        .priority-normale {
            background: #dbeafe;
            color: #1e3a8a;
        }

        .priority-basse {
            background: #e5e7eb;
            color: #374151;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .logout-btn:hover {
            background: #dc2626;
        }

        .remarques-section {
            background: #f9fafb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }

        .remarque-item {
            background: white;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 0.375rem;
            border-left: 3px solid #2563eb;
        }

        .remarque-header {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }

        .remarque-text {
            color: #1f2937;
        }

        .validation-alert {
            background: #fef3c7;
            border: 1px solid #fde68a;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .file-preview {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #2563eb;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .file-preview:hover {
            text-decoration: underline;
        }

        .client-response {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .response-oui {
            color: #065f46;
        }

        .response-non {
            color: #991b1b;
        }

        .response-attente {
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚚 Administration DPD</h1>
        <div class="nav">
            <a href="index.php">Dashboard</a>
            <a href="admin.php" class="active">Incidents</a>
            <a href="tournees.php">Tournées</a>
            <a href="users.php">Utilisateurs</a>
            <a href="logs.php">Logs</a>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
            <!-- Liste des incidents -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Gestion des Incidents</h2>
                    <div>
                        <?php if ($user['role'] === 'admin'): ?>
                            <a href="?action=create" class="btn btn-primary">+ Nouvel Incident</a>
                        <?php endif; ?>
                        <a href="import.php" class="btn btn-secondary">Importer CSV</a>
                    </div>
                </div>

                <?php
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $result = $service->getAllIncidents([], $page, 20);
                $incidents = $result['data'];
                ?>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Tournée</th>
                            <th>Type</th>
                            <th>Client</th>
                            <th>Statut</th>
                            <th>Priorité</th>
                            <th>Client répondu</th>
                            <th>Fichier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incidents as $inc): ?>
                            <tr>
                                <td><?= htmlspecialchars($inc['id']) ?></td>
                                <td><?= date('d/m/Y', strtotime($inc['date_incident'])) ?></td>
                                <td><?= htmlspecialchars($inc['tournee_numero'] ?? '') ?></td>
                                <td><?= htmlspecialchars($inc['type_incident_nom'] ?? '') ?></td>
                                <td><?= htmlspecialchars($inc['nom_client'] ?? '') ?></td>
                                <td>
                                    <span class="status-badge status-<?= $inc['statut'] ?>">
                                        <?= ucfirst($inc['statut']) ?>
                                    </span>
                                    <?php if (!empty($inc['validation_requise'])): ?>
                                        <span style="color: #f59e0b;">⚠️</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="priority-badge priority-<?= $inc['priorite'] ?>">
                                        <?= ucfirst($inc['priorite']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="client-response response-<?= $inc['client_repondu'] ?? 'attente' ?>">
                                        <?php 
                                        $clientRepondu = $inc['client_repondu'] ?? 'en_attente';
                                        echo $clientRepondu === 'oui' ? '✅ Oui' : 
                                             ($clientRepondu === 'non' ? '❌ Non' : '⏳ En attente');
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($inc['fichier_reclamation'])): ?>
                                        <a href="<?= htmlspecialchars($inc['fichier_reclamation']) ?>" 
                                           target="_blank" class="file-preview">
                                            📎 Voir
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="?action=edit&id=<?= $inc['id'] ?>" class="btn btn-primary btn-sm">
                                            <?= $user['role'] === 'chef_equipe' ? 'Consulter' : 'Modifier' ?>
                                        </a>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <a href="?action=delete&id=<?= $inc['id'] ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet incident ?')">
                                                Supprimer
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($result['pages'] > 1): ?>
                    <div style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                        <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                            <a href="?page=<?= $i ?>" 
                               class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action === 'create' || $action === 'edit'): ?>
            <!-- Formulaire de création/édition -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <?= $action === 'create' ? 'Nouvel Incident' : 'Modifier l\'Incident' ?>
                    </h2>
                    <a href="admin.php" class="btn btn-secondary">Retour</a>
                </div>

                <?php if ($validationEnAttente && $user['role'] === 'admin'): ?>
                    <div class="validation-alert">
                        <strong>⚠️ Validation requise</strong><br>
                        <?= htmlspecialchars($validationEnAttente['demandeur_nom'] . ' ' . $validationEnAttente['demandeur_prenom']) ?>
                        demande à changer le statut de "<?= htmlspecialchars($validationEnAttente['ancien_statut']) ?>"
                        à "<?= htmlspecialchars($validationEnAttente['nouveau_statut']) ?>"<br>
                        <form method="POST" style="margin-top: 0.5rem; display: inline;">
                            <input type="hidden" name="validation_id" value="<?= $validationEnAttente['id'] ?>">
                            <input type="hidden" name="id" value="<?= $incident['id'] ?>">
                            <button type="submit" name="valider_changement" value="1" class="btn btn-success btn-sm">
                                Valider le changement
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <form method="POST" action="admin.php?action=<?= $action ?>" enctype="multipart/form-data">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?= $incident['id'] ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tournée *</label>
                            <select name="tournee_id" class="form-control" required 
                                    <?= $user['role'] === 'chef_equipe' ? 'disabled' : '' ?>>
                                <option value="">Sélectionner une tournée</option>
                                <?php foreach ($tournees as $tournee): ?>
                                    <option value="<?= $tournee['id'] ?>" 
                                            <?= ($incident && $incident['tournee_id'] == $tournee['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tournee['numero'] . ' - ' . $tournee['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($user['role'] === 'chef_equipe' && $incident): ?>
                                <input type="hidden" name="tournee_id" value="<?= $incident['tournee_id'] ?>">
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Type d'incident *</label>
                            <select name="type_incident_id" class="form-control" required
                                    <?= $user['role'] === 'chef_equipe' ? 'disabled' : '' ?>>
                                <option value="">Sélectionner un type</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?= $type['id'] ?>" 
                                            <?= ($incident && $incident['type_incident_id'] == $type['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($user['role'] === 'chef_equipe' && $incident): ?>
                                <input type="hidden" name="type_incident_id" value="<?= $incident['type_incident_id'] ?>">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nom du client *</label>
                        <input type="text" name="nom_client" class="form-control" 
                               value="<?= $incident ? htmlspecialchars($incident['nom_client']) : '' ?>" 
                               required <?= $user['role'] === 'chef_equipe' ? 'readonly' : '' ?>>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adresse complète *</label>
                        <input type="text" name="adresse_complete" class="form-control" 
                               value="<?= $incident ? htmlspecialchars($incident['adresse_complete']) : '' ?>" 
                               required <?= $user['role'] === 'chef_equipe' ? 'readonly' : '' ?>>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Numéro de colis</label>
                            <input type="text" name="numero_colis" class="form-control" 
                                   value="<?= $incident ? htmlspecialchars($incident['numero_colis'] ?? '') : '' ?>"
                                   <?= $user['role'] === 'chef_equipe' ? 'readonly' : '' ?>>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Numéro de réclamation</label>
                            <input type="text" name="numero_reclamation" class="form-control" 
                                   value="<?= $incident ? htmlspecialchars($incident['numero_reclamation'] ?? '') : '' ?>"
                                   <?= $user['role'] === 'chef_equipe' ? 'readonly' : '' ?>>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Date de l'incident *</label>
                            <input type="date" name="date_incident" class="form-control" 
                                   value="<?= $incident ? $incident['date_incident'] : date('Y-m-d') ?>" 
                                   required <?= $user['role'] === 'chef_equipe' ? 'readonly' : '' ?>>
                        </div>

                        <?php if ($user['role'] === 'admin'): ?>
                        <div class="form-group">
                            <label class="form-label">Statut *</label>
                            <select name="statut" class="form-control" required>
                                <option value="nouveau" <?= (!$incident || $incident['statut'] === 'nouveau') ? 'selected' : '' ?>>
                                    Nouveau
                                </option>
                                <option value="en_cours" <?= ($incident && $incident['statut'] === 'en_cours') ? 'selected' : '' ?>>
                                    En cours
                                </option>
                                <option value="resolu" <?= ($incident && $incident['statut'] === 'resolu') ? 'selected' : '' ?>>
                                    Résolu
                                </option>
                                <option value="ferme" <?= ($incident && $incident['statut'] === 'ferme') ? 'selected' : '' ?>>
                                    Fermé
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Priorité *</label>
                            <select name="priorite" class="form-control" required>
                                <option value="basse" <?= ($incident && $incident['priorite'] === 'basse') ? 'selected' : '' ?>>
                                    Basse
                                </option>
                                <option value="normale" <?= (!$incident || $incident['priorite'] === 'normale') ? 'selected' : '' ?>>
                                    Normale
                                </option>
                                <option value="haute" <?= ($incident && $incident['priorite'] === 'haute') ? 'selected' : '' ?>>
                                    Haute
                                </option>
                                <option value="urgente" <?= ($incident && $incident['priorite'] === 'urgente') ? 'selected' : '' ?>>
                                    Urgente
                                </option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Client a répondu ?</label>
                            <select name="client_repondu" class="form-control">
                                <option value="en_attente" <?= (!$incident || ($incident['client_repondu'] ?? 'en_attente') === 'en_attente') ? 'selected' : '' ?>>
                                    En attente
                                </option>
                                <option value="oui" <?= ($incident && ($incident['client_repondu'] ?? '') === 'oui') ? 'selected' : '' ?>>
                                    Oui
                                </option>
                                <option value="non" <?= ($incident && ($incident['client_repondu'] ?? '') === 'non') ? 'selected' : '' ?>>
                                    Non
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" 
                                  <?= $user['role'] === 'chef_equipe' ? 'readonly' : '' ?>><?= $incident ? htmlspecialchars($incident['description'] ?? '') : '' ?></textarea>
                    </div>

                    <?php if ($user['role'] === 'admin'): ?>
                    <div class="form-group">
                        <label class="form-label">Fichier de réclamation (max 2 Mo)</label>
                        <input type="file" name="fichier_reclamation" class="form-control" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <?php if ($incident && !empty($incident['fichier_reclamation'])): ?>
                            <div style="margin-top: 0.5rem;">
                                Fichier actuel : 
                                <a href="<?= htmlspecialchars($incident['fichier_reclamation']) ?>" 
                                   target="_blank" class="file-preview">
                                    📎 <?= basename($incident['fichier_reclamation']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($user['role'] === 'chef_equipe'): ?>
                    <div class="form-group">
                        <label class="form-label">Ajouter une remarque</label>
                        <textarea name="nouvelle_remarque" class="form-control" rows="3" 
                                  placeholder="Entrez votre remarque ici..."></textarea>
                    </div>
                    <?php endif; ?>

                    <?php if ($action === 'edit' && !empty($remarques)): ?>
                    <div class="remarques-section">
                        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">
                            💬 Historique des remarques
                        </h3>
                        <?php foreach ($remarques as $remarque): ?>
                            <div class="remarque-item">
                                <div class="remarque-header">
                                    <strong><?= htmlspecialchars($remarque['nom'] . ' ' . $remarque['prenom']) ?></strong>
                                    - <?= date('d/m/Y à H:i', strtotime($remarque['created_at'])) ?>
                                </div>
                                <div class="remarque-text">
                                    <?= nl2br(htmlspecialchars($remarque['remarque'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <?php if ($user['role'] === 'admin'): ?>
                            <button type="submit" class="btn btn-primary">
                                <?= $action === 'create' ? 'Créer' : 'Mettre à jour' ?>
                            </button>
                        <?php elseif ($user['role'] === 'chef_equipe' && $action === 'edit'): ?>
                            <button type="submit" class="btn btn-primary">
                                Ajouter la remarque
                            </button>
                        <?php endif; ?>
                        <a href="admin.php" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>