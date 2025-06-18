<?php
require_once 'autoload.php';

use Utils\Auth;
use Config\Database;

// Vérifier l'authentification et le rôle admin
Auth::requireAuth();
Auth::requireRole('admin');

$db = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $email = trim($_POST['email']);
                $nom = trim($_POST['nom']);
                $prenom = trim($_POST['prenom']);
                $role = $_POST['role'];
                $password = $_POST['password'];
                
                if (empty($email) || empty($nom) || empty($password)) {
                    throw new Exception('Email, nom et mot de passe sont requis');
                }
                
                // Hasher le mot de passe avec les bonnes options
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
                
                $sql = "INSERT INTO utilisateurs (email, mot_de_passe, nom, prenom, role) 
                        VALUES (:email, :password, :nom, :prenom, :role)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':email' => $email,
                    ':password' => $hashedPassword,
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':role' => $role
                ]);
                
                $message = "Utilisateur créé avec succès";
                break;
                
            case 'toggle':
                $id = $_POST['id'];
                $sql = "UPDATE utilisateurs SET actif = NOT actif WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute([':id' => $id]);
                
                $message = "Statut de l'utilisateur modifié";
                break;
                
            case 'delete':
                $id = $_POST['id'];
                
                // Empêcher la suppression de l'admin principal
                if ($id == 1) {
                    throw new Exception("Impossible de supprimer l'administrateur principal");
                }
                
                $sql = "DELETE FROM utilisateurs WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute([':id' => $id]);
                
                $message = "Utilisateur supprimé avec succès";
                break;
                
            case 'reset_password':
                $id = $_POST['id'];
                $newPassword = $_POST['new_password'];
                
                if (empty($newPassword)) {
                    throw new Exception('Le nouveau mot de passe est requis');
                }
                
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $sql = "UPDATE utilisateurs SET mot_de_passe = :password WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':password' => $hashedPassword,
                    ':id' => $id
                ]);
                
                $message = "Mot de passe réinitialisé avec succès";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer tous les utilisateurs
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM logs_activite WHERE utilisateur_id = u.id) as nb_actions,
        (SELECT COUNT(*) FROM remarques_incidents WHERE utilisateur_id = u.id) as nb_remarques
        FROM utilisateurs u 
        ORDER BY u.role, u.nom";
$stmt = $db->query($sql);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = [
    'total' => count($users),
    'admins' => count(array_filter($users, fn($u) => $u['role'] === 'admin')),
    'chefs' => count(array_filter($users, fn($u) => $u['role'] === 'chef_equipe')),
    'actifs' => count(array_filter($users, fn($u) => $u['actif']))
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Dashboard DPD</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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

        .btn-warning {
            background: #f59e0b;
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

        .badge-admin {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-chef {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-gestionnaire {
            background: #dbeafe;
            color: #1e3a8a;
        }

        .badge-operateur {
            background: #e0e7ff;
            color: #4338ca;
        }

        .badge-lecteur {
            background: #e5e7eb;
            color: #374151;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            width: 80%;
            max-width: 400px;
        }

        .modal-header {
            margin-bottom: 1rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .close {
            float: right;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>👥 Gestion des Utilisateurs</h1>
        <div class="nav">
            <a href="index.php">Dashboard</a>
            <a href="admin.php">Incidents</a>
            <a href="tournees.php">Tournées</a>
            <a href="users.php" class="active">Utilisateurs</a>
            <a href="logs.php">Logs</a>
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
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label">Total utilisateurs</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= $stats['admins'] ?></div>
                <div class="stat-label">Administrateurs</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= $stats['chefs'] ?></div>
                <div class="stat-label">Chefs d'équipe</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= $stats['actifs'] ?></div>
                <div class="stat-label">Comptes actifs</div>
            </div>
        </div>

        <!-- Formulaire d'ajout -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">➕ Ajouter un nouvel utilisateur</h2>
            </div>
            
            <form method="POST" class="form-inline">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group-inline">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" 
                           placeholder="utilisateur@dpd.fr" required style="width: 200px;">
                </div>
                
                <div class="form-group-inline">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="nom" class="form-control" 
                           placeholder="Nom" required style="width: 150px;">
                </div>
                
                <div class="form-group-inline">
                    <label class="form-label">Prénom</label>
                    <input type="text" name="prenom" class="form-control" 
                           placeholder="Prénom" style="width: 150px;">
                </div>
                
                <div class="form-group-inline">
                    <label class="form-label">Rôle *</label>
                    <select name="role" class="form-control" required>
                        <option value="chef_equipe">Chef d'équipe</option>
                        <option value="operateur">Opérateur</option>
                        <option value="lecteur">Lecteur</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
                
                <div class="form-group-inline">
                    <label class="form-label">Mot de passe *</label>
                    <input type="password" name="password" class="form-control" 
                           placeholder="••••••••" required style="width: 150px;">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Créer l'utilisateur
                </button>
            </form>
        </div>

        <!-- Liste des utilisateurs -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📋 Liste des utilisateurs</h2>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Nom Prénom</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Actions</th>
                        <th>Remarques</th>
                        <th>Dernière connexion</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['nom'] . ' ' . $user['prenom']) ?></td>
                            <td>
                                <span class="badge badge-<?= $user['role'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $user['actif'] ? 'badge-active' : 'badge-inactive' ?>">
                                    <?= $user['actif'] ? 'Actif' : 'Inactif' ?>
                                </span>
                            </td>
                            <td><?= $user['nb_actions'] ?></td>
                            <td><?= $user['nb_remarques'] ?></td>
                            <td><?= $user['derniere_connexion'] ? date('d/m/Y H:i', strtotime($user['derniere_connexion'])) : '-' ?></td>
                            <td>
                                <div class="actions">
                                    <?php if ($user['id'] != 1): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $user['actif'] ? 'btn-secondary' : 'btn-success' ?>">
                                                <?= $user['actif'] ? 'Désactiver' : 'Activer' ?>
                                            </button>
                                        </form>
                                        
                                        <button onclick="openResetModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['email']) ?>')" 
                                                class="btn btn-warning btn-sm">
                                            Réinitialiser MDP
                                        </button>
                                        
                                        <?php if ($user['nb_actions'] == 0): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    Supprimer
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal réinitialisation mot de passe -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close" onclick="closeResetModal()">&times;</span>
                <h3 class="modal-title">Réinitialiser le mot de passe</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" id="reset_user_id">
                
                <p style="margin-bottom: 1rem;">
                    Réinitialiser le mot de passe pour : <strong id="reset_user_email"></strong>
                </p>
                
                <div style="margin-bottom: 1rem;">
                    <label class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="new_password" class="form-control" 
                           placeholder="••••••••" required style="width: 100%;">
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">Réinitialiser</button>
                    <button type="button" class="btn btn-secondary" onclick="closeResetModal()">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openResetModal(userId, userEmail) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('reset_user_email').textContent = userEmail;
            document.getElementById('resetModal').style.display = 'block';
        }

        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }

        // Fermer la modal si on clique en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('resetModal');
            if (event.target === modal) {
                closeResetModal();
            }
        }
    </script>
</body>
</html>