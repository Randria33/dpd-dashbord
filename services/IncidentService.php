<?php
namespace Services;

use Repositories\IncidentRepository;
use Models\Incident;
use Utils\Logger;
use Utils\Validator;

class IncidentService {
    private $repository;
    private $logger;
    private $validator;
    
    public function __construct() {
        $this->repository = new IncidentRepository();
        $this->logger = new Logger();
        $this->validator = new Validator();
    }
    
    public function getAllIncidents($filters = [], $page = 1, $limit = 50) {
        try {
            $this->logger->info("Récupération des incidents", ['filters' => $filters, 'page' => $page]);
            
            // Validation des paramètres
            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 50;
            
            // Nettoyer les filtres
            $cleanFilters = $this->sanitizeFilters($filters);
            
            $result = $this->repository->findAll($cleanFilters, $page, $limit);
            
            // Transformer les modèles en tableaux
            $result['data'] = array_map(function($incident) {
                return $incident->toArray();
            }, $result['data']);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la récupération des incidents", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function getIncident($id) {
        try {
            $this->logger->info("Récupération de l'incident", ['id' => $id]);
            
            if (!$this->validator->isValidId($id)) {
                throw new \InvalidArgumentException("ID invalide");
            }
            
            $incident = $this->repository->findById($id);
            
            if (!$incident) {
                throw new \Exception("Incident non trouvé");
            }
            
            return $incident->toArray();
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la récupération de l'incident", ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function createIncident($data) {
        try {
            $this->logger->info("Création d'un incident", ['data' => $data]);
            
            // Validation des données
            $errors = $this->validateIncidentData($data);
            if (!empty($errors)) {
                throw new \InvalidArgumentException(implode(', ', $errors));
            }
            
            // Créer le modèle
            $incident = new Incident($data);
            
            // Validation du modèle
            $modelErrors = $incident->validate();
            if (!empty($modelErrors)) {
                throw new \InvalidArgumentException(implode(', ', $modelErrors));
            }
            
            // Sauvegarder
            $incident = $this->repository->create($incident);
            
            $this->logger->info("Incident créé avec succès", ['id' => $incident->getId()]);
            
            return $incident->toArray();
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la création de l'incident", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function updateIncident($id, $data) {
        try {
            $this->logger->info("Mise à jour de l'incident", ['id' => $id, 'data' => $data]);
            
            if (!$this->validator->isValidId($id)) {
                throw new \InvalidArgumentException("ID invalide");
            }
            
            // Récupérer l'incident existant
            $incident = $this->repository->findById($id);
            if (!$incident) {
                throw new \Exception("Incident non trouvé");
            }
            
            // Validation des données
            $errors = $this->validateIncidentData($data, true);
            if (!empty($errors)) {
                throw new \InvalidArgumentException(implode(', ', $errors));
            }
            
            // Mettre à jour le modèle
            $incident->hydrate($data);
            
            // Validation du modèle
            $modelErrors = $incident->validate();
            if (!empty($modelErrors)) {
                throw new \InvalidArgumentException(implode(', ', $modelErrors));
            }
            
            // Sauvegarder
            $success = $this->repository->update($incident);
            
            if (!$success) {
                throw new \Exception("Erreur lors de la mise à jour");
            }
            
            $this->logger->info("Incident mis à jour avec succès", ['id' => $id]);
            
            return $this->getIncident($id);
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la mise à jour de l'incident", ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function deleteIncident($id) {
        try {
            $this->logger->info("Suppression de l'incident", ['id' => $id]);
            
            if (!$this->validator->isValidId($id)) {
                throw new \InvalidArgumentException("ID invalide");
            }
            
            // Vérifier que l'incident existe
            $incident = $this->repository->findById($id);
            if (!$incident) {
                throw new \Exception("Incident non trouvé");
            }
            
            $success = $this->repository->delete($id);
            
            if (!$success) {
                throw new \Exception("Erreur lors de la suppression");
            }
            
            $this->logger->info("Incident supprimé avec succès", ['id' => $id]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la suppression de l'incident", ['id' => $id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function getStatistics($filters = []) {
        try {
            $this->logger->info("Récupération des statistiques", ['filters' => $filters]);
            
            $cleanFilters = $this->sanitizeFilters($filters);
            $stats = $this->repository->getStatistics($cleanFilters);
            
            // Calculer des statistiques supplémentaires
            $stats['taux_resolution'] = $this->calculateResolutionRate($stats['par_statut']);
            $stats['priorites'] = $this->getPriorityDistribution();
            
            return $stats;
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de la récupération des statistiques", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function getTournees() {
        return $this->repository->getTournees();
    }
    
    public function getTypesIncidents() {
        return $this->repository->getTypesIncidents();
    }
    
    public function getVilles() {
        return $this->repository->getVilles();
    }
    
    public function importCSV($filePath) {
        try {
            $this->logger->info("Import CSV", ['file' => $filePath]);
            
            if (!file_exists($filePath)) {
                throw new \Exception("Fichier non trouvé");
            }
            
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                throw new \Exception("Impossible d'ouvrir le fichier");
            }
            
            // Lire l'en-tête
            $header = fgetcsv($handle);
            if (!$header) {
                throw new \Exception("Fichier CSV vide");
            }
            
            $imported = 0;
            $errors = [];
            $lineNumber = 1;
            
            // Mapper les tournées et types
            $tournees = $this->mapTournees();
            $types = $this->mapTypes();
            
            while (($data = fgetcsv($handle)) !== false) {
                $lineNumber++;
                
                try {
                    // Créer un tableau associatif
                    $row = array_combine($header, $data);
                    
                    // Mapper les données
                    $incidentData = [
                        'tournee_id' => $tournees[$row['tournee']] ?? null,
                        'type_incident_id' => $types[$row['type']] ?? null,
                        'nom_client' => $row['nom'] ?? '',
                        'adresse_complete' => $row['adresse'] ?? '',
                        'numero_colis' => $row['colis'] ?? '',
                        'numero_reclamation' => $row['reclamation'] ?? '',
                        'date_incident' => $row['date'] ?? date('Y-m-d'),
                        'description' => $row['description'] ?? '',
                        'statut' => 'nouveau',
                        'priorite' => 'normale'
                    ];
                    
                    $this->createIncident($incidentData);
                    $imported++;
                    
                } catch (\Exception $e) {
                    $errors[] = "Ligne $lineNumber: " . $e->getMessage();
                    $this->logger->warning("Erreur import ligne", ['line' => $lineNumber, 'error' => $e->getMessage()]);
                }
            }
            
            fclose($handle);
            
            $this->logger->info("Import CSV terminé", ['imported' => $imported, 'errors' => count($errors)]);
            
            return [
                'imported' => $imported,
                'errors' => $errors
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Erreur lors de l'import CSV", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    private function sanitizeFilters($filters) {
        $clean = [];
        
        if (!empty($filters['type'])) {
            $clean['type'] = htmlspecialchars($filters['type'], ENT_QUOTES, 'UTF-8');
        }
        
        if (!empty($filters['tournee'])) {
            $clean['tournee'] = htmlspecialchars($filters['tournee'], ENT_QUOTES, 'UTF-8');
        }
        
        if (!empty($filters['ville'])) {
            $clean['ville'] = htmlspecialchars($filters['ville'], ENT_QUOTES, 'UTF-8');
        }
        
        if (!empty($filters['statut'])) {
            $clean['statut'] = htmlspecialchars($filters['statut'], ENT_QUOTES, 'UTF-8');
        }
        
        if (!empty($filters['date_debut'])) {
            $clean['date_debut'] = $this->validator->validateDate($filters['date_debut']);
        }
        
        if (!empty($filters['date_fin'])) {
            $clean['date_fin'] = $this->validator->validateDate($filters['date_fin']);
        }
        
        if (!empty($filters['search'])) {
            $clean['search'] = htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8');
        }
        
        return $clean;
    }
    
    private function validateIncidentData($data, $isUpdate = false) {
        $errors = [];
        
        if (!$isUpdate || isset($data['tournee_id'])) {
            if (empty($data['tournee_id'])) {
                $errors[] = "La tournée est requise";
            }
        }
        
        if (!$isUpdate || isset($data['type_incident_id'])) {
            if (empty($data['type_incident_id'])) {
                $errors[] = "Le type d'incident est requis";
            }
        }
        
        if (!$isUpdate || isset($data['nom_client'])) {
            if (empty($data['nom_client'])) {
                $errors[] = "Le nom du client est requis";
            }
        }
        
        if (!$isUpdate || isset($data['date_incident'])) {
            if (!empty($data['date_incident']) && !$this->validator->validateDate($data['date_incident'])) {
                $errors[] = "Format de date invalide";
            }
        }
        
        // Validation du champ client_repondu si présent
        if (isset($data['client_repondu'])) {
            $validValues = ['oui', 'non', 'en_attente'];
            if (!in_array($data['client_repondu'], $validValues)) {
                $errors[] = "Valeur invalide pour 'Client a répondu'";
            }
        }
        
        return $errors;
    }
    
    private function calculateResolutionRate($statusData) {
        $total = 0;
        $resolved = 0;
        
        foreach ($statusData as $status) {
            $total += $status['count'];
            if (in_array($status['statut'], ['resolu', 'ferme'])) {
                $resolved += $status['count'];
            }
        }
        
        return $total > 0 ? round(($resolved / $total) * 100, 2) : 0;
    }
    
    private function getPriorityDistribution() {
        // Utiliser directement la connexion à la base de données
        $db = \Config\Database::getInstance()->getConnection();
        $sql = "SELECT priorite, COUNT(*) as count FROM incidents GROUP BY priorite";
        $stmt = $db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function mapTournees() {
        $tournees = $this->getTournees();
        $map = [];
        foreach ($tournees as $tournee) {
            $map[$tournee['numero']] = $tournee['id'];
        }
        return $map;
    }
    
    private function mapTypes() {
        $types = $this->getTypesIncidents();
        $map = [];
        foreach ($types as $type) {
            $map[$type['nom']] = $type['id'];
        }
        return $map;
    }
}