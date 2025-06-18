<?php
require_once 'autoload.php';

use Utils\Auth;
use Config\Database;

// Vérifier l'authentification
Auth::requireAuth();

$db = Database::getInstance()->getConnection();

// Filtres
$filters = [
    'date_debut' => $_GET['date_debut'] ?? date('Y-m-d', strtotime('-7 days')),
    'date_fin' => $_GET['date_fin'] ?? date('Y-m-d'),
    'utilisateur' => $_GET['utilisateur'] ?? '',
    'type_action' => $_GET['type_action'] ?? '',
    'entite' => $_GET['entite'] ?? ''
];

// Construire la requête
$where = ["DATE(l.created_at) BETWEEN :date_debut AND :date_fin"];
$params = [
    ':date_debut' => $filters['date_debut'],
    ':date_fin' => $filters['date_fin']
];

if (!empty($filters['utilisateur'])) {
    $where[] = "l.utilisateur_id = :utilisateur";
    $params[':utilisateur'] = $filters['utilisateur'];
}

if (!empty($filters['type_action'])) {
    $where[] = "l.type_action = :type_action";
    $params[':type_action'] = $filters['type_action'];
}

if (!empty($filters['entite'])) {
    $where[] = "l.entite = :entite";
    $params[':entite'] = $filters['entite'];
}

$whereClause = implode(' AND ', $where);

// Récupérer les logs - Version simplifiée pour debug
$sql = "
    SELECT 
        l.*,
        u.nom as utilisateur_nom,
        u.prenom as utilisateur_prenom,
        u.role as utilisateur_role
    FROM logs_activite l
    LEFT JOIN utilisateurs u ON l.utilisateur_id = u.id
    WHERE DATE(l.created_at) BETWEEN :date_debut AND :date_fin
    ORDER BY l.created_at DESC
    LIMIT 500
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug : afficher le nombre de logs trouvés
echo "<!-- Debug: " . count($logs) . " logs trouvés -->";

// Récupérer les utilisateurs pour le filtre
$usersSql = "SELECT id, nom, prenom, email FROM utilisateurs ORDER BY nom";
$usersStmt = $db->query($usersSql);
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$statsSql = "
    SELECT 
        COUNT(*) as total,
        COUNT(DISTINCT utilisateur_id) as utilisateurs_actifs,
        COUNT(DISTINCT entite_id) as entites_modifiees,
        SUM(CASE WHEN type_action = 'statut' THEN 1 ELSE 0 END) as changements_statut,
        SUM(CASE WHEN type_action = 'remarque' THEN 1 ELSE 0 END) as remarques_ajoutees,
        SUM(CASE WHEN type_action = 'validation' THEN 1 ELSE 0 END) as validations
    FROM logs_activite
    WHERE DATE(created_at) BETWEEN :date_debut AND :date_fin
";

$statsStmt = $db->prepare($statsSql);
$statsStmt->execute([
    ':date_debut' => $filters['date_debut'],
    ':date_fin' => $filters['date_fin']
]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs et Historique - Dashboard DPD</title>
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
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .filters-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-control {
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

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .logs-table {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f9fafb;
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.875rem;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        .action-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .action-statut {
            background: #dbeafe;
            color: #1e3a8a;
        }

        .action-remarque {
            background: #fef3c7;
            color: #92400e;
        }

        .action-validation {
            background: #d1fae5;
            color: #065f46;
        }

        .action-creation {
            background: #e0e7ff;
            color: #4338ca;
        }

        .action-modification {
            background: #fed7aa;
            color: #92400e;
        }

        .action-suppression {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-fichier {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .role-badge {
            padding: 0.15rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .role-admin {
            background: #fee2e2;
            color: #991b1b;
        }

        .role-chef_equipe {
            background: #fef3c7;
            color: #92400e;
        }

        .role-operateur {
            background: #e0e7ff;
            color: #4338ca;
        }

        .timestamp {
            color: #6b7280;
            font-size: 0.75rem;
        }

        .entite-link {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }

        .entite-link:hover {
            text-decoration: underline;
        }

        .details {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.75rem;
            color: #6b7280;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .filter-actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📝 Logs et Historique</h1>
        <div class="nav">
            <a href="index.php">Dashboard</a>
            <a href="admin.php">Incidents</a>
            <a href="tournees.php">Tournées</a>
            <a href="users.php">Utilisateurs</a>
            <a href="logs.php" class="active">Logs</a>
            <a href="logout.php" style="background: #ef4444;">Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value"><?= number_format($stats['total'], 0, ',', ' ') ?></div>
                <div class="stat-label">Actions totales</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= $stats['utilisateurs_actifs'] ?></div>
                <div class="stat-label">Utilisateurs actifs</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= $stats['changements_statut'] ?></div>
                <div class="stat-label">Changements de statut</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= $stats['remarques_ajoutees'] ?></div>
                <div class="stat-label">Remarques ajoutées</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= $stats['validations'] ?></div>
                <div class="stat-label">Validations</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters-card">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Date début</label>
                        <input type="date" name="date_debut" class="form-control" 
                               value="<?= htmlspecialchars($filters['date_debut']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date fin</label>
                        <input type="date" name="date_fin" class="form-control" 
                               value="<?= htmlspecialchars($filters['date_fin']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Utilisateur</label>
                        <select name="utilisateur" class="form-control">
                            <option value="">Tous les utilisateurs</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" 
                                        <?= $filters['utilisateur'] == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['nom'] . ' ' . $user['prenom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Type d'action</label>
                        <select name="type_action" class="form-control">
                            <option value="">Toutes les actions</option>
                            <option value="statut" <?= $filters['type_action'] === 'statut' ? 'selected' : '' ?>>Changement de statut</option>
                            <option value="remarque" <?= $filters['type_action'] === 'remarque' ? 'selected' : '' ?>>Remarque</option>
                            <option value="validation" <?= $filters['type_action'] === 'validation' ? 'selected' : '' ?>>Validation</option>
                            <option value="creation" <?= $filters['type_action'] === 'creation' ? 'selected' : '' ?>>Création</option>
                            <option value="modification" <?= $filters['type_action'] === 'modification' ? 'selected' : '' ?>>Modification</option>
                            <option value="suppression" <?= $filters['type_action'] === 'suppression' ? 'selected' : '' ?>>Suppression</option>
                            <option value="fichier" <?= $filters['type_action'] === 'fichier' ? 'selected' : '' ?>>Fichier</option>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Appliquer les filtres</button>
                    <a href="logs.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- Logs -->
        <div class="logs-table">
            <div class="table-header">
                <h2 class="table-title">📋 Historique des actions</h2>
            </div>

            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <p>Aucune activité trouvée pour cette période</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Type</th>
                            <th>Entité</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="timestamp">
                                        <?= date('d/m/Y', strtotime($log['created_at'])) ?><br>
                                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($log['utilisateur_nom'] . ' ' . $log['utilisateur_prenom']) ?>
                                    <span class="role-badge role-<?= $log['utilisateur_role'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $log['utilisateur_role'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['action']) ?></td>
                                <td>
                                    <?php if ($log['type_action']): ?>
                                        <span class="action-badge action-<?= $log['type_action'] ?>">
                                            <?= ucfirst($log['type_action']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['entite'] === 'incident' && $log['entite_id']): ?>
                                        <a href="admin.php?action=edit&id=<?= $log['entite_id'] ?>" class="entite-link">
                                            <?= htmlspecialchars($log['entite_description'] ?? '') ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($log['entite_description'] ?? '') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="details">
                                        <?php
                                        if ($log['ancien_statut'] && $log['nouveau_statut']) {
                                            echo htmlspecialchars($log['ancien_statut']) . ' → ' . htmlspecialchars($log['nouveau_statut']);
                                        } elseif ($log['details_supplementaires']) {
                                            $details = json_decode($log['details_supplementaires'], true);
                                            if (is_array($details)) {
                                                echo htmlspecialchars(implode(', ', array_map(function($k, $v) {
                                                    return "$k: $v";
                                                }, array_keys($details), $details)));
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>