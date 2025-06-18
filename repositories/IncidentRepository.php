<?php
namespace Repositories;

use Config\Database;
use Models\Incident;
use PDO;

class IncidentRepository {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Getter pour la connexion à la base de données
    public function getDb() {
        return $this->db;
    }
    
    public function findAll($filters = [], $page = 1, $limit = 50) {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];
        
        // Construction des filtres
        if (!empty($filters['type'])) {
            $where[] = "ti.nom = :type";
            $params[':type'] = $filters['type'];
        }
        
        if (!empty($filters['tournee'])) {
            $where[] = "t.numero = :tournee";
            $params[':tournee'] = $filters['tournee'];
        }
        
        if (!empty($filters['ville'])) {
            $where[] = "i.ville = :ville";
            $params[':ville'] = $filters['ville'];
        }
        
        if (!empty($filters['statut'])) {
            $where[] = "i.statut = :statut";
            $params[':statut'] = $filters['statut'];
        }
        
        if (!empty($filters['date_debut'])) {
            $where[] = "i.date_incident >= :date_debut";
            $params[':date_debut'] = $filters['date_debut'];
        }
        
        if (!empty($filters['date_fin'])) {
            $where[] = "i.date_incident <= :date_fin";
            $params[':date_fin'] = $filters['date_fin'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(i.nom_client LIKE :search OR i.numero_colis LIKE :search2 OR i.numero_reclamation LIKE :search3)";
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
            $params[':search3'] = '%' . $filters['search'] . '%';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Requête principale
        $sql = "
            SELECT 
                i.*,
                t.numero as tournee_numero,
                ti.nom as type_incident_nom
            FROM incidents i
            LEFT JOIN tournees t ON i.tournee_id = t.id
            LEFT JOIN types_incidents ti ON i.type_incident_id = ti.id
            $whereClause
            ORDER BY i.date_incident DESC, i.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $incidents = [];
        while ($row = $stmt->fetch()) {
            $incidents[] = new Incident($row);
        }
        
        // Compter le total
        $countSql = "
            SELECT COUNT(*) as total
            FROM incidents i
            LEFT JOIN tournees t ON i.tournee_id = t.id
            LEFT JOIN types_incidents ti ON i.type_incident_id = ti.id
            $whereClause
        ";
        
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetch()['total'];
        
        return [
            'data' => $incidents,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit)
        ];
    }
    
    public function findById($id) {
        $sql = "
            SELECT 
                i.*,
                t.numero as tournee_numero,
                ti.nom as type_incident_nom
            FROM incidents i
            LEFT JOIN tournees t ON i.tournee_id = t.id
            LEFT JOIN types_incidents ti ON i.type_incident_id = ti.id
            WHERE i.id = :id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $data = $stmt->fetch();
        return $data ? new Incident($data) : null;
    }
    
    public function create(Incident $incident) {
        $sql = "
            INSERT INTO incidents (
                tournee_id, type_incident_id, nom_client, adresse_complete,
                ville, code_postal, numero_colis, numero_reclamation,
                date_incident, description, statut, priorite, client_repondu
            ) VALUES (
                :tournee_id, :type_incident_id, :nom_client, :adresse_complete,
                :ville, :code_postal, :numero_colis, :numero_reclamation,
                :date_incident, :description, :statut, :priorite, :client_repondu
            )
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tournee_id' => $incident->getTourneeId(),
            ':type_incident_id' => $incident->getTypeIncidentId(),
            ':nom_client' => $incident->getNomClient(),
            ':adresse_complete' => $incident->getAdresseComplete(),
            ':ville' => $incident->getVille(),
            ':code_postal' => $incident->getCodePostal(),
            ':numero_colis' => $incident->getNumeroColis(),
            ':numero_reclamation' => $incident->getNumeroReclamation(),
            ':date_incident' => $incident->getDateIncident(),
            ':description' => $incident->getDescription(),
            ':statut' => $incident->getStatut(),
            ':priorite' => $incident->getPriorite(),
            ':client_repondu' => $incident->getClientRepondu() ?? 'en_attente'
        ]);
        
        $incident->setId($this->db->lastInsertId());
        return $incident;
    }
    
    public function update(Incident $incident) {
        $sql = "
            UPDATE incidents SET
                tournee_id = :tournee_id,
                type_incident_id = :type_incident_id,
                nom_client = :nom_client,
                adresse_complete = :adresse_complete,
                ville = :ville,
                code_postal = :code_postal,
                numero_colis = :numero_colis,
                numero_reclamation = :numero_reclamation,
                date_incident = :date_incident,
                description = :description,
                statut = :statut,
                priorite = :priorite,
                client_repondu = :client_repondu,
                updated_at = NOW()
            WHERE id = :id
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $incident->getId(),
            ':tournee_id' => $incident->getTourneeId(),
            ':type_incident_id' => $incident->getTypeIncidentId(),
            ':nom_client' => $incident->getNomClient(),
            ':adresse_complete' => $incident->getAdresseComplete(),
            ':ville' => $incident->getVille(),
            ':code_postal' => $incident->getCodePostal(),
            ':numero_colis' => $incident->getNumeroColis(),
            ':numero_reclamation' => $incident->getNumeroReclamation(),
            ':date_incident' => $incident->getDateIncident(),
            ':description' => $incident->getDescription(),
            ':statut' => $incident->getStatut(),
            ':priorite' => $incident->getPriorite(),
            ':client_repondu' => $incident->getClientRepondu() ?? 'en_attente'
        ]);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM incidents WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public function getStatistics($filters = []) {
        $where = [];
        $params = [];
        
        // Réutiliser les mêmes filtres
        if (!empty($filters['date_debut'])) {
            $where[] = "i.date_incident >= :date_debut";
            $params[':date_debut'] = $filters['date_debut'];
        }
        
        if (!empty($filters['date_fin'])) {
            $where[] = "i.date_incident <= :date_fin";
            $params[':date_fin'] = $filters['date_fin'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Statistiques générales
        $stats = [];
        
        // Total incidents
        $sql = "SELECT COUNT(*) as total FROM incidents i $whereClause";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $stats['total'] = $stmt->fetch()['total'];
        
        // Par statut
        $sql = "
            SELECT statut, COUNT(*) as count 
            FROM incidents i 
            $whereClause 
            GROUP BY statut
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $stats['par_statut'] = $stmt->fetchAll();
        
        // Par type
        $sql = "
            SELECT ti.nom as type, COUNT(*) as count 
            FROM incidents i
            LEFT JOIN types_incidents ti ON i.type_incident_id = ti.id
            $whereClause
            GROUP BY ti.nom
            ORDER BY count DESC
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $stats['par_type'] = $stmt->fetchAll();
        
        // Par tournée
        $sql = "
            SELECT t.numero as tournee, COUNT(*) as count 
            FROM incidents i
            LEFT JOIN tournees t ON i.tournee_id = t.id
            $whereClause
            GROUP BY t.numero
            ORDER BY count DESC
            LIMIT 10
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $stats['par_tournee'] = $stmt->fetchAll();
        
        // Par ville
        $sql = "
            SELECT ville, COUNT(*) as count 
            FROM incidents i
            $whereClause
            GROUP BY ville
            ORDER BY count DESC
            LIMIT 10
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $stats['par_ville'] = $stmt->fetchAll();
        
        // Évolution par jour (derniers 30 jours)
        $sql = "
            SELECT DATE(date_incident) as date, COUNT(*) as count 
            FROM incidents 
            WHERE date_incident >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(date_incident)
            ORDER BY date
        ";
        $stmt = $this->db->query($sql);
        $stats['evolution'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    // Méthodes pour les données de référence
    public function getTournees() {
        $sql = "SELECT id, numero, nom FROM tournees ORDER BY numero";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    public function getTypesIncidents() {
        $sql = "SELECT id, nom, couleur FROM types_incidents ORDER BY nom";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }
    
    public function getVilles() {
        $sql = "SELECT DISTINCT ville FROM incidents WHERE ville IS NOT NULL ORDER BY ville";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}