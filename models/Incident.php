<?php
namespace Models;

class Incident {
    private $id;
    private $tournee_id;
    private $type_incident_id;
    private $nom_client;
    private $adresse_complete;
    private $ville;
    private $code_postal;
    private $numero_colis;
    private $numero_reclamation;
    private $date_incident;
    private $description;
    private $statut;
    private $priorite;
    private $client_repondu;
    private $fichier_reclamation;
    private $created_at;
    private $updated_at;
    
    // Relations
    private $tournee_numero;
    private $type_incident_nom;
    
    public function __construct($data = []) {
        if (!empty($data)) {
            $this->hydrate($data);
        }
    }
    
    public function hydrate($data) {
        foreach ($data as $key => $value) {
            $method = 'set' . str_replace('_', '', ucwords($key, '_'));
            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                // Pour les propriétés directes
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }
    
    // Getters
    public function getId() { return $this->id; }
    public function getTourneeId() { return $this->tournee_id; }
    public function getTypeIncidentId() { return $this->type_incident_id; }
    public function getNomClient() { return $this->nom_client; }
    public function getAdresseComplete() { return $this->adresse_complete; }
    public function getVille() { return $this->ville; }
    public function getCodePostal() { return $this->code_postal; }
    public function getNumeroColis() { return $this->numero_colis; }
    public function getNumeroReclamation() { return $this->numero_reclamation; }
    public function getDateIncident() { return $this->date_incident; }
    public function getDescription() { return $this->description; }
    public function getStatut() { return $this->statut; }
    public function getPriorite() { return $this->priorite; }
    public function getClientRepondu() { return $this->client_repondu; }
    public function getFichierReclamation() { return $this->fichier_reclamation; }
    public function getCreatedAt() { return $this->created_at; }
    public function getUpdatedAt() { return $this->updated_at; }
    public function getTourneeNumero() { return $this->tournee_numero; }
    public function getTypeIncidentNom() { return $this->type_incident_nom; }
    
    // Setters
    public function setId($id) { $this->id = $id; }
    public function setTourneeId($tournee_id) { $this->tournee_id = $tournee_id; }
    public function setTypeIncidentId($type_incident_id) { $this->type_incident_id = $type_incident_id; }
    public function setNomClient($nom_client) { $this->nom_client = $nom_client; }
    public function setAdresseComplete($adresse_complete) { 
        $this->adresse_complete = $adresse_complete;
        // Extraire ville et code postal si possible
        $this->extractVilleCodePostal($adresse_complete);
    }
    public function setVille($ville) { $this->ville = $ville; }
    public function setCodePostal($code_postal) { $this->code_postal = $code_postal; }
    public function setNumeroColis($numero_colis) { $this->numero_colis = $numero_colis; }
    public function setNumeroReclamation($numero_reclamation) { $this->numero_reclamation = $numero_reclamation; }
    public function setDateIncident($date_incident) { $this->date_incident = $date_incident; }
    public function setDescription($description) { $this->description = $description; }
    public function setStatut($statut) { $this->statut = $statut; }
    public function setPriorite($priorite) { $this->priorite = $priorite; }
    public function setClientRepondu($client_repondu) { $this->client_repondu = $client_repondu; }
    public function setFichierReclamation($fichier_reclamation) { $this->fichier_reclamation = $fichier_reclamation; }
    public function setCreatedAt($created_at) { $this->created_at = $created_at; }
    public function setUpdatedAt($updated_at) { $this->updated_at = $updated_at; }
    public function setTourneeNumero($tournee_numero) { $this->tournee_numero = $tournee_numero; }
    public function setTypeIncidentNom($type_incident_nom) { $this->type_incident_nom = $type_incident_nom; }
    
    // Méthodes utilitaires
    private function extractVilleCodePostal($adresse) {
        // Regex pour extraire code postal et ville
        if (preg_match('/(\d{5})\s+(.+)$/', $adresse, $matches)) {
            $this->code_postal = $matches[1];
            $this->ville = trim($matches[2]);
        }
    }
    
    public function toArray() {
        return [
            'id' => $this->id,
            'tournee_id' => $this->tournee_id,
            'type_incident_id' => $this->type_incident_id,
            'nom_client' => $this->nom_client,
            'adresse_complete' => $this->adresse_complete,
            'ville' => $this->ville,
            'code_postal' => $this->code_postal,
            'numero_colis' => $this->numero_colis,
            'numero_reclamation' => $this->numero_reclamation,
            'date_incident' => $this->date_incident,
            'description' => $this->description,
            'statut' => $this->statut,
            'priorite' => $this->priorite,
            'client_repondu' => $this->client_repondu,
            'fichier_reclamation' => $this->fichier_reclamation,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'tournee_numero' => $this->tournee_numero,
            'type_incident_nom' => $this->type_incident_nom
        ];
    }
    
    // Validation
    public function validate() {
        $errors = [];
        
        if (empty($this->nom_client)) {
            $errors[] = "Le nom du client est requis";
        }
        
        if (empty($this->adresse_complete)) {
            $errors[] = "L'adresse complète est requise";
        }
        
        if (empty($this->date_incident)) {
            $errors[] = "La date d'incident est requise";
        }
        
        if (!in_array($this->statut, ['nouveau', 'en_cours', 'resolu', 'ferme'])) {
            $errors[] = "Statut invalide";
        }
        
        if (!in_array($this->priorite, ['basse', 'normale', 'haute', 'urgente'])) {
            $errors[] = "Priorité invalide";
        }
        
        return $errors;
    }
}