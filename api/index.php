<?php
// Autoloader simple
spl_autoload_register(function ($class) {
    $prefix = '';
    $baseDir = __DIR__ . '/../';
    
    // Remplacer les namespaces par des chemins
    $file = $baseDir . str_replace('\\', '/', $class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use Controllers\IncidentController;
use Utils\Response;

// Router simple
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Enlever le préfixe /api
$path = preg_replace('#^/api#', '', $path);

// CORS pour les requêtes OPTIONS
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

// Initialiser le contrôleur
$controller = new IncidentController();
$response = new Response();

// Routes
try {
    switch (true) {
        // GET /api ou /api/ - Documentation de l'API
        case $method === 'GET' && ($path === '' || $path === '/'):
            $response->json([
                'success' => true,
                'message' => 'API DPD Dashboard v1.0',
                'endpoints' => [
                    'incidents' => [
                        'GET /api/incidents' => 'Liste des incidents avec pagination et filtres',
                        'GET /api/incidents/{id}' => 'Détail d\'un incident',
                        'POST /api/incidents' => 'Créer un incident',
                        'PUT /api/incidents/{id}' => 'Modifier un incident',
                        'DELETE /api/incidents/{id}' => 'Supprimer un incident',
                        'GET /api/incidents/stats' => 'Statistiques des incidents',
                        'GET /api/incidents/export' => 'Exporter les incidents',
                        'POST /api/incidents/import' => 'Importer des incidents (CSV)'
                    ],
                    'references' => [
                        'GET /api/tournees' => 'Liste des tournées',
                        'GET /api/types-incidents' => 'Types d\'incidents',
                        'GET /api/villes' => 'Liste des villes'
                    ]
                ],
                'parametres' => [
                    'filtrage' => [
                        'type' => 'Filtrer par type d\'incident',
                        'tournee' => 'Filtrer par numéro de tournée',
                        'ville' => 'Filtrer par ville',
                        'statut' => 'Filtrer par statut (nouveau, en_cours, resolu, ferme)',
                        'date_debut' => 'Date de début (YYYY-MM-DD)',
                        'date_fin' => 'Date de fin (YYYY-MM-DD)',
                        'search' => 'Recherche textuelle'
                    ],
                    'pagination' => [
                        'page' => 'Numéro de page (défaut: 1)',
                        'limit' => 'Nombre d\'éléments par page (défaut: 50, max: 100)'
                    ]
                ],
                'exemples' => [
                    'Filtrage' => '/api/incidents?type=Réclamation&tournee=310&page=1',
                    'Recherche' => '/api/incidents?search=Dupont',
                    'Export CSV' => '/api/incidents/export?format=csv',
                    'Statistiques' => '/api/incidents/stats?date_debut=2024-01-01'
                ]
            ]);
            break;
            
        // GET /incidents/stats
        case $method === 'GET' && $path === '/incidents/stats':
            $controller->statistics();
            break;
            
        // GET /incidents
        case $method === 'GET' && $path === '/incidents':
            $controller->index();
            break;
            
        // GET /incidents/{id}
        case $method === 'GET' && preg_match('#^/incidents/(\d+)$#', $path, $matches):
            $controller->show($matches[1]);
            break;
            
        // POST /incidents
        case $method === 'POST' && $path === '/incidents':
            $controller->store();
            break;
            
        // PUT /incidents/{id}
        case $method === 'PUT' && preg_match('#^/incidents/(\d+)$#', $path, $matches):
            $controller->update($matches[1]);
            break;
            
        // DELETE /incidents/{id}
        case $method === 'DELETE' && preg_match('#^/incidents/(\d+)$#', $path, $matches):
            $controller->destroy($matches[1]);
            break;
            
        // POST /incidents/import
        case $method === 'POST' && $path === '/incidents/import':
            $controller->import();
            break;
            
        // GET /incidents/export
        case $method === 'GET' && $path === '/incidents/export':
            $controller->export();
            break;
            
        // GET /tournees
        case $method === 'GET' && $path === '/tournees':
            $controller->tournees();
            break;
            
        // GET /types-incidents
        case $method === 'GET' && $path === '/types-incidents':
            $controller->types();
            break;
            
        // GET /villes
        case $method === 'GET' && $path === '/villes':
            $controller->villes();
            break;
            
        // Route non trouvée
        default:
            $response->notFound("Route non trouvée");
    }
} catch (\Exception $e) {
    $response->error($e->getMessage(), 500);
}