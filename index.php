<?php
require_once 'autoload.php';

use Services\IncidentService;

$service = new IncidentService();

// Récupérer les filtres
$filters = [
    'type' => $_GET['type'] ?? null,
    'tournee' => $_GET['tournee'] ?? null,
    'ville' => $_GET['ville'] ?? null,
    'statut' => $_GET['statut'] ?? null,
    'date_debut' => $_GET['date_debut'] ?? null,
    'date_fin' => $_GET['date_fin'] ?? null,
    'search' => $_GET['search'] ?? null
];

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Récupérer les données
try {
    $result = $service->getAllIncidents($filters, $page, 50);
    $incidents = $result['data'];
    
    // Statistiques
    $stats = $service->getStatistics($filters);
    
    // Données de référence
    $tournees = $service->getTournees();
    $types = $service->getTypesIncidents();
    $villes = $service->getVilles();
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard DPD - Gestion des Incidents</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #1f2937;
            --gray: #6b7280;
            --light: #f3f4f6;
            --white: #ffffff;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: var(--white);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
        }

        .nav-links a {
            color: var(--gray);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.3s;
        }

        .nav-links a:hover {
            background: var(--light);
            color: var(--primary);
        }

        .nav-links a.active {
            background: var(--primary);
            color: var(--white);
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Filtres */
        .filters-card {
            background: var(--white);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .filters-title {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: var(--gray);
        }

        .filter-control {
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.3s;
        }

        .filter-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-box {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 2.5rem 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .search-icon {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .filter-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }

        .stat-card.success::before {
            background: var(--secondary);
        }

        .stat-card.warning::before {
            background: var(--warning);
        }

        .stat-card.danger::before {
            background: var(--danger);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2rem;
            opacity: 0.2;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: bold;
            color: var(--dark);
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .stat-change {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .stat-change.positive {
            color: var(--secondary);
        }

        .stat-change.negative {
            color: var(--danger);
        }

        /* Charts */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .chart-header {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark);
        }

        /* Table */
        .table-card {
            background: var(--white);
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f9fafb;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f3f4f6;
        }

        tbody tr {
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        /* Badges */
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }

        .badge-nouveau {
            background: #dbeafe;
            color: #1e3a8a;
        }

        .badge-en_cours {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-resolu {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-ferme {
            background: #e5e7eb;
            color: #374151;
        }

        .priority-urgente {
            color: var(--danger);
            font-weight: 600;
        }

        .priority-haute {
            color: var(--warning);
            font-weight: 600;
        }

        .priority-normale {
            color: var(--primary);
        }

        .priority-basse {
            color: var(--gray);
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
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
            background: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--gray);
            color: var(--white);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray);
            color: var(--dark);
        }

        .btn-outline:hover {
            background: var(--light);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-link {
            padding: 0.5rem 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            color: var(--gray);
            text-decoration: none;
            transition: all 0.3s;
        }

        .page-link:hover {
            background: var(--light);
            color: var(--primary);
        }

        .page-link.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                 Dashboard Réclamation
            </div>
            <nav class="nav-links">
                <a href="index.php" class="active">Dashboard</a>
                <a href="admin.php">Administration</a>
                <a href="api/">API</a>
                <a href="export.php">Export</a>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- Filtres -->
        <div class="filters-card">
            <div class="filters-header">
                <h2 class="filters-title">🔍 Filtres</h2>
                <button onclick="resetFilters()" class="btn btn-outline">Réinitialiser</button>
            </div>
            
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Type d'incident</label>
                        <select name="type" class="filter-control">
                            <option value="">Tous les types</option>
                            <?php foreach ($types as $type): ?>
                                <option value="<?= htmlspecialchars($type['nom']) ?>" 
                                        <?= $filters['type'] === $type['nom'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Tournée</label>
                        <select name="tournee" class="filter-control">
                            <option value="">Toutes les tournées</option>
                            <?php foreach ($tournees as $tournee): ?>
                                <option value="<?= htmlspecialchars($tournee['numero']) ?>" 
                                        <?= $filters['tournee'] === $tournee['numero'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tournee['numero']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Ville</label>
                        <select name="ville" class="filter-control">
                            <option value="">Toutes les villes</option>
                            <?php foreach ($villes as $ville): ?>
                                <option value="<?= htmlspecialchars($ville) ?>" 
                                        <?= $filters['ville'] === $ville ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ville) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Statut</label>
                        <select name="statut" class="filter-control">
                            <option value="">Tous les statuts</option>
                            <option value="nouveau" <?= $filters['statut'] === 'nouveau' ? 'selected' : '' ?>>Nouveau</option>
                            <option value="en_cours" <?= $filters['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                            <option value="resolu" <?= $filters['statut'] === 'resolu' ? 'selected' : '' ?>>Résolu</option>
                            <option value="ferme" <?= $filters['statut'] === 'ferme' ? 'selected' : '' ?>>Fermé</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date début</label>
                        <input type="date" name="date_debut" class="filter-control" 
                               value="<?= htmlspecialchars($filters['date_debut'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date fin</label>
                        <input type="date" name="date_fin" class="filter-control" 
                               value="<?= htmlspecialchars($filters['date_fin'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Recherche</label>
                        <div class="search-box">
                            <input type="text" name="search" class="search-input" 
                                   placeholder="Client, colis, réclamation..." 
                                   value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                            <span class="search-icon">🔍</span>
                        </div>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Appliquer les filtres</button>
                </div>
            </form>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?= number_format($stats['total'], 0, ',', ' ') ?></div>
                <div class="stat-label">Total incidents</div>
                <?php if (!empty($stats['evolution'])): ?>
                    <?php 
                    $lastDay = end($stats['evolution']);
                    $prevDay = prev($stats['evolution']);
                    $change = $prevDay ? (($lastDay['count'] - $prevDay['count']) / $prevDay['count'] * 100) : 0;
                    ?>
                    <div class="stat-change <?= $change >= 0 ? 'positive' : 'negative' ?>">
                        <?= $change >= 0 ? '↑' : '↓' ?> <?= abs(round($change, 1)) ?>% vs hier
                    </div>
                <?php endif; ?>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= round($stats['taux_resolution'], 1) ?>%</div>
                <div class="stat-label">Taux de résolution</div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">⏱️</div>
                <div class="stat-value">
                    <?php 
                    $nouveaux = 0;
                    foreach ($stats['par_statut'] as $s) {
                        if ($s['statut'] === 'nouveau') $nouveaux = $s['count'];
                    }
                    echo number_format($nouveaux, 0, ',', ' ');
                    ?>
                </div>
                <div class="stat-label">Nouveaux incidents</div>
            </div>

            <div class="stat-card danger">
                <div class="stat-icon">🚨</div>
                <div class="stat-value">
                    <?php 
                    $urgents = 0;
                    if (isset($stats['priorites'])) {
                        foreach ($stats['priorites'] as $p) {
                            if ($p['priorite'] === 'urgente') $urgents = $p['count'];
                        }
                    }
                    echo number_format($urgents, 0, ',', ' ');
                    ?>
                </div>
                <div class="stat-label">Incidents urgents</div>
            </div>
        </div>

        <!-- Statistiques détaillées sans graphiques -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <!-- Répartition par Type -->
            <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; color: #1f2937;">📋 Répartition par Type</h3>
                <div style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($stats['par_type'] as $type): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6;">
                            <span style="font-size: 0.875rem;"><?= htmlspecialchars($type['type']) ?></span>
                            <span style="background: #2563eb; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">
                                <?= $type['count'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Répartition par Statut -->
            <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; color: #1f2937;">📊 Répartition par Statut</h3>
                <div>
                    <?php foreach ($stats['par_statut'] as $statut): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6;">
                            <span style="font-size: 0.875rem;"><?= ucfirst($statut['statut']) ?></span>
                            <span class="badge badge-<?= $statut['statut'] ?>" style="font-size: 0.75rem;">
                                <?= $statut['count'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Incidents par Tournée -->
            <div style="background: white; padding: 1.5rem; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
                <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem; color: #1f2937;">🚚 Incidents par Tournée</h3>
                <div style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($stats['par_tournee'] as $tournee): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6;">
                            <span style="font-size: 0.875rem;">Tournée <?= htmlspecialchars($tournee['tournee']) ?></span>
                            <span style="background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">
                                <?= $tournee['count'] ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Tableau des incidents -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="table-title">📋 Liste des Incidents</h3>
                <div>
                    <span style="color: var(--gray); font-size: 0.875rem;">
                        <?= $result['total'] ?> résultats
                    </span>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Tournée</th>
                            <th>Type</th>
                            <th>Client</th>
                            <th>Adresse</th>
                            <th>N° Colis</th>
                            <th>N° Réclamation</th>
                            <th>Statut</th>
                            <th>Priorité</th>
                            <th>Fichier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incidents as $incident): ?>
                            <tr>
                                <td><?= $incident['id'] ?></td>
                                <td><?= date('d/m/Y', strtotime($incident['date_incident'])) ?></td>
                                <td><?= htmlspecialchars($incident['tournee_numero'] ?? '') ?></td>
                                <td><?= htmlspecialchars($incident['type_incident_nom'] ?? '') ?></td>
                                <td><?= htmlspecialchars($incident['nom_client'] ?? '') ?></td>
                                <td><?= htmlspecialchars($incident['adresse_complete'] ?? '') ?></td>
                                <td><?= htmlspecialchars($incident['numero_colis'] ?? '') ?></td>
                                <td><?= htmlspecialchars($incident['numero_reclamation'] ?? '') ?></td>
                                <td>
                                    <span class="badge badge-<?= $incident['statut'] ?>">
                                        <?= ucfirst($incident['statut']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="priority-<?= $incident['priorite'] ?>">
                                        <?= ucfirst($incident['priorite']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($incident['fichier_reclamation'])): ?>
                                        <a href="<?= htmlspecialchars($incident['fichier_reclamation']) ?>" 
                                           target="_blank" style="color: #2563eb; text-decoration: none;">
                                            📎 Voir
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($result['pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($filters, ['page' => $page - 1])) ?>" 
                           class="page-link">Précédent</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($result['pages'], $page + 2);
                    
                    if ($start > 1): ?>
                        <a href="?<?= http_build_query(array_merge($filters, ['page' => 1])) ?>" class="page-link">1</a>
                        <?php if ($start > 2): ?>
                            <span>...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>" 
                           class="page-link <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($end < $result['pages']): ?>
                        <?php if ($end < $result['pages'] - 1): ?>
                            <span>...</span>
                        <?php endif; ?>
                        <a href="?<?= http_build_query(array_merge($filters, ['page' => $result['pages']])) ?>" 
                           class="page-link"><?= $result['pages'] ?></a>
                    <?php endif; ?>

                    <?php if ($page < $result['pages']): ?>
                        <a href="?<?= http_build_query(array_merge($filters, ['page' => $page + 1])) ?>" 
                           class="page-link">Suivant</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Données PHP vers JavaScript
        const statsData = <?= json_encode($stats) ?>;
        
        // Configuration des graphiques
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        
        // Configuration des graphiques
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        
        // Graphique des types
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: statsData.par_type.slice(0, 5).map(t => t.type),
                datasets: [{
                    data: statsData.par_type.slice(0, 5).map(t => t.count),
                    backgroundColor: [
                        '#2563eb',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#8b5cf6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 500
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    }
                }
            }
        });
        
        // Graphique des statuts
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: statsData.par_statut.map(s => {
                    const labels = {
                        'nouveau': 'Nouveau',
                        'en_cours': 'En cours',
                        'resolu': 'Résolu',
                        'ferme': 'Fermé'
                    };
                    return labels[s.statut] || s.statut;
                }),
                datasets: [{
                    data: statsData.par_statut.map(s => s.count),
                    backgroundColor: [
                        '#3b82f6',
                        '#fbbf24',
                        '#10b981',
                        '#9ca3af'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 500
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    }
                }
            }
        });
        
        // Graphique d'évolution - Simplifié
        const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
        new Chart(evolutionCtx, {
            type: 'line',
            data: {
                labels: statsData.evolution.slice(-7).map(e => {
                    const date = new Date(e.date);
                    return date.getDate() + '/' + (date.getMonth() + 1);
                }),
                datasets: [{
                    label: 'Incidents',
                    data: statsData.evolution.slice(-7).map(e => e.count),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.3,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 500
                },
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // Réinitialiser les filtres
        function resetFilters() {
            window.location.href = 'index.php';
        }
    </script>
</body>
</html>