<?php
require_once 'autoload.php';

use Utils\Auth;
use Config\Database;

// Vérifier l'authentification
Auth::requireAuth();

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $numero = trim($_POST['numero']);
                $nom = trim($_POST['nom']);
                $zone = trim($_POST['zone']);
                
                if (empty($numero) || empty($nom)) {
                    throw new Exception('Le numéro et le nom sont requis');
                }
                
                $sql = "INSERT INTO tournees (numero, nom, zone) VALUES (:numero, :nom, :zone)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':numero' => $numero,
                    ':nom' => $nom,
                    ':zone' => $zone
                ]);
                
                $message = "Tournée créée avec succès";
                break;
                
            case 'toggle':
                $id = $_POST['id'];
                $sql = "UPDATE tournees SET actif = NOT actif WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute([':id' => $id]);
                
                $message = "Statut de la tournée modifié";
                break;
                
            case 'delete':
                $id = $_POST['id'];
                
                // Vérifier s'il y a des incidents liés
                $checkSql = "SELECT COUNT(*) as count FROM incidents WHERE tournee_id = :id";
                $checkStmt = $db->prepare($checkSql);
                $checkStmt->execute([':id' => $id]);
                $count = $checkStmt->fetch()['count'];
                
                if ($count > 0) {
                    throw new Exception("Impossible de supprimer : $count incident(s) lié(s) à cette tournée");
                }
                
                $sql = "DELETE FROM tournees WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute([':id' => $id]);
                
                $message = "Tournée supprimée avec succès";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer toutes les tournées
$sql = "SELECT t.*, 
        (SELECT COUNT(*) FROM incidents WHERE tournee_id = t.id) as nb_incidents,
        (SELECT COUNT(*) FROM incidents WHERE tournee_id = t.id AND statut = 'nouveau') as nb_nouveaux
        FROM tournees t 
        ORDER BY t.numero";
$stmt = $db->query($sql);
$tournees = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Tournées - Dashboard DPD</title>
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

        .form-inline {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .form-group-inline {
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

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
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

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-count {
            background: #dbeafe;
            color: #1e3a8a;
            margin-left: 0.5rem;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 0.5rem;
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
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🚚 Gestion des Tournées</h1>
        <div class="nav">
            <a href="index.php">Dashboard</a>
            <a href="admin.php">Incidents</a>
            <a href="tournees.php" class="active">Tournées</a>
            <a href="logout.php" style="background: #ef4444;">Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-summary">
            <div class="stat-box">
                <div class="stat-value"><?= count($tournees) ?></div>
                <div class="stat-label">Tournées totales</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= count(array_filter($tournees, fn($t) => $t['actif'])) ?></div>
                <div class="stat-label">Tournées actives</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= array_sum(array_column($tournees, 'nb_incidents')) ?></div>
                <div class="stat-label">Total incidents</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= array_sum(array_column($tournees, 'nb_nouveaux')) ?></div>
                <div class="stat-label">Incidents nouveaux</div>
            </div>
        </div>

        <!-- Formulaire d'ajout -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">➕ Ajouter une nouvelle tournée</h2>
            </div>
            
            <form method="POST" class="form-inline">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group-inline">
                    <label class="form-label">Numéro *</label>
                    <input type="text" name="numero" class="form-control" 
                           placeholder="Ex: 710" required style="width: 100px;">
                </div>
                
                <div class="form-group-inline">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="nom" class="form-control" 
                           placeholder="Ex: Tournée Paris Nord-Est" required style="width: 250px;">
                </div>
                
                <div class="form-group-inline">
                    <label class="form-label">Zone</label>
                    <input type="text" name="zone" class="form-control" 
                           placeholder="Ex: Paris Nord-Est" style="width: 200px;">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Ajouter la tournée
                </button>
            </form>
        </div>

        <!-- Liste des tournées -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📋 Liste des tournées</h2>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Numéro</th>
                        <th>Nom</th>
                        <th>Zone</th>
                        <th>Statut</th>
                        <th>Incidents</th>
                        <th>Créée le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tournees as $tournee): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($tournee['numero']) ?></strong></td>
                            <td><?= htmlspecialchars($tournee['nom']) ?></td>
                            <td><?= htmlspecialchars($tournee['zone'] ?: '-') ?></td>
                            <td>
                                <span class="badge <?= $tournee['actif'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $tournee['actif'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-count"><?= $tournee['nb_incidents'] ?></span>
                                <?php if ($tournee['nb_nouveaux'] > 0): ?>
                                    <span class="badge badge-warning"><?= $tournee['nb_nouveaux'] ?> nouveaux</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($tournee['created_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $tournee['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $tournee['actif'] ? 'btn-secondary' : 'btn-success' ?>">
                                            <?= $tournee['actif'] ? 'Désactiver' : 'Activer' ?>
                                        </button>
                                    </form>
                                    
                                    <?php if ($tournee['nb_incidents'] == 0): ?>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette tournée ?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $tournee['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                Supprimer
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>