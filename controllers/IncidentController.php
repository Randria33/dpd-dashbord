<?php
namespace Controllers;

use Services\IncidentService;
use Utils\Response;
use Utils\Logger;

class IncidentController {
    private $service;
    private $response;
    private $logger;
    
    public function __construct() {
        $this->service = new IncidentService();
        $this->response = new Response();
        $this->logger = new Logger();
    }
    
    public function index() {
        try {
            // Récupérer les paramètres
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
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            $result = $this->service->getAllIncidents($filters, $page, $limit);
            
            return $this->response->success("Incidents récupérés avec succès", $result);
            
        } catch (\Exception $e) {
            return $this->response->error($e->getMessage());
        }
    }
    
    public function show($id) {
        try {
            $incident = $this->service->getIncident($id);
            return $this->response->success("Incident récupéré avec succès", $incident);
            
        } catch (\Exception $e) {
            $code = $e->getMessage() === "Incident non trouvé" ? 404 : 500;
            return $this->response->error($e->getMessage(), $code);
        }
    }
    
    public function store() {
        try {
            // Récupérer les données JSON
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                return $this->response->error("Données invalides", 400);
            }
            
            $incident = $this->service->createIncident($data);
            
            return $this->response->success("Incident créé avec succès", $incident, 201);
            
        } catch (\InvalidArgumentException $e) {
            return $this->response->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return $this->response->error($e->getMessage());
        }
    }
    
    public function update($id) {
        try {
            // Récupérer les données JSON
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                return $this->response->error("Données invalides", 400);
            }
            
            $incident = $this->service->updateIncident($id, $data);
            
            return $this->response->success("Incident mis à jour avec succès", $incident);
            
        } catch (\InvalidArgumentException $e) {
            return $this->response->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            $code = $e->getMessage() === "Incident non trouvé" ? 404 : 500;
            return $this->response->error($e->getMessage(), $code);
        }
    }
    
    public function destroy($id) {
        try {
            $this->service->deleteIncident($id);
            
            return $this->response->success("Incident supprimé avec succès");
            
        } catch (\Exception $e) {
            $code = $e->getMessage() === "Incident non trouvé" ? 404 : 500;
            return $this->response->error($e->getMessage(), $code);
        }
    }
    
    public function statistics() {
        try {
            $filters = [
                'date_debut' => $_GET['date_debut'] ?? null,
                'date_fin' => $_GET['date_fin'] ?? null,
                'type' => $_GET['type'] ?? null
            ];
            
            $stats = $this->service->getStatistics($filters);
            
            return $this->response->success("Statistiques récupérées avec succès", $stats);
            
        } catch (\Exception $e) {
            return $this->response->error($e->getMessage());
        }
    }
    
    public function tournees() {
        try {
            $tournees = $this->service->getTournees();
            return $this->response->success("Tournées récupérées avec succès", $tournees);
            
        } catch (\Exception $e) {
            return $this->response->error($e->getMessage());
        }
    }
    
    public function types() {
        try {
            $types = $this->service->getTypesIncidents();
            return $this->response->success("Types d'incidents récupérés avec succès", $types);
            
        } catch (\Exception $e) {
            return $this->response->error($e->getMessage());
        }
    }
    
    public function villes() {
        try {
            $villes = $this->service->getVilles();
            return $this->response->success("Villes récupérées avec succès", $villes);
            
        } catch (\Exception $e) {
            return $this->response->error($e->getMessage());
        }
    }
    
    public function import() {
        try {
            if (!isset($_FILES['file'])) {
                return $this->response->error("Aucun fichier fourni", 400);
            }
            
            $file = $_FILES['file'];
            
            // Vérifier le type de fichier
            if ($file['type'] !== 'text/csv' && !preg_match('/\.csv$/i', $file['name'])) {
                return $this->response->error("Le fichier doit être au format CSV", 400);
            }
            
            // Vérifier les erreurs
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return $this->response->error("Erreur lors de l'upload du fichier", 400);
            }
            
            // Déplacer le fichier
            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filename = 'import_' . date('Y-m-d_His') . '_' . basename($file['name']);
            $filepath = $uploadDir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return $this->response->error("Erreur lors du déplacement du fichier", 500);
            }
            
            // Importer le CSV
            $result = $this->service->importCSV($filepath);
            
            return $this->response->success("Import terminé", $result);
            
        } catch (\Exception $e) {
            return $this->response->error($e->getMessage());
        }
    }
    
    public function export() {
        try {
            $format = $_GET['format'] ?? 'csv';
            $filters = [
                'type' => $_GET['type'] ?? null,
                'tournee' => $_GET['tournee'] ?? null,
                'ville' => $_GET['ville'] ?? null,
                'statut' => $_GET['statut'] ?? null,
                'date_debut' => $_GET['date_debut'] ?? null,
                'date_fin' => $_GET['date_fin'] ?? null
            ];
            
            // Récupérer tous les incidents
            $result = $this->service->getAllIncidents($filters, 1, 10000);
            $incidents = $result['data'];
            
            switch ($format) {
                case 'csv':
                    $this->exportCSV($incidents);
                    break;
                case 'excel':
                    $this->exportExcel($incidents);
                    break;
                case 'pdf':
                    $this->exportPDF($incidents);
                    break;
                default:
                    return $this->response->error("Format d'export non supporté", 400);
            }
            
        } catch (\Exception $e) {
            return $this->response->error($e->getMessage());
        }
    }
    
    private function exportCSV($incidents) {
        $filename = 'incidents_export_' . date('Y-m-d_His') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // BOM pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // En-têtes
        fputcsv($output, [
            'ID',
            'Tournée',
            'Type',
            'Client',
            'Adresse',
            'Ville',
            'Code Postal',
            'N° Colis',
            'N° Réclamation',
            'Date',
            'Description',
            'Statut',
            'Priorité',
            'Créé le'
        ], ';');
        
        // Données
        foreach ($incidents as $incident) {
            fputcsv($output, [
                $incident['id'],
                $incident['tournee_numero'],
                $incident['type_incident_nom'],
                $incident['nom_client'],
                $incident['adresse_complete'],
                $incident['ville'],
                $incident['code_postal'],
                $incident['numero_colis'],
                $incident['numero_reclamation'],
                $incident['date_incident'],
                $incident['description'],
                $incident['statut'],
                $incident['priorite'],
                $incident['created_at']
            ], ';');
        }
        
        fclose($output);
        exit;
    }
    
    private function exportExcel($incidents) {
        // Nécessite une bibliothèque comme PhpSpreadsheet
        // Pour l'instant, on redirige vers CSV
        $this->exportCSV($incidents);
    }
    
    private function exportPDF($incidents) {
        // Nécessite une bibliothèque comme TCPDF ou FPDF
        // Pour l'instant, on retourne une erreur
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Export PDF non implémenté'
        ]);
        exit;
    }
}