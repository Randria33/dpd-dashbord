# Guide d'Installation - CMS Dashboard DPD

## 🚀 Installation Rapide

### Prérequis
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Apache avec mod_rewrite activé
- Extensions PHP : pdo, pdo_mysql, json, mbstring

### Étapes d'installation

1. **Cloner ou télécharger le projet**
```bash
git clone [url-du-repo] dpd-dashboard
cd dpd-dashboard
```

2. **Configurer la base de données**
   - Créer une base de données MySQL :
   ```sql
   CREATE DATABASE dpd_incidents CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   - Importer la structure depuis `database.sql` (inclus dans autoload.php)

3. **Configuration de l'application**
   - Copier le fichier de configuration :
   ```bash
   cp .env.example .env
   ```
   - Modifier `.env` avec vos paramètres de base de données

4. **Créer les dossiers nécessaires**
```bash
mkdir -p logs uploads exports backups
chmod 755 logs uploads exports backups
```

5. **Configurer Apache**
   - Pointer le DocumentRoot vers le dossier du projet
   - S'assurer que mod_rewrite est activé
   - Exemple de VirtualHost :
   ```apache
   <VirtualHost *:80>
       DocumentRoot /path/to/dpd-dashboard
       ServerName dpd-dashboard.local
       
       <Directory /path/to/dpd-dashboard>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

6. **Accéder à l'application**
   - Dashboard public : http://votre-domaine/
   - Administration : http://votre-domaine/admin.php
   - API : http://votre-domaine/api/

## 📁 Structure du Projet

```
dpd-dashboard/
├── api/                    # API REST
│   └── index.php          # Point d'entrée API
├── config/                # Configuration
│   └── Database.php       # Connexion BDD
├── models/                # Modèles de données
│   └── Incident.php       
├── repositories/          # Couche d'accès aux données
│   └── IncidentRepository.php
├── services/              # Logique métier
│   └── IncidentService.php
├── controllers/           # Contrôleurs API
│   └── IncidentController.php
├── utils/                 # Classes utilitaires
│   ├── Logger.php
│   ├── Validator.php
│   ├── Response.php
│   └── Auth.php
├── logs/                  # Fichiers de logs
├── uploads/               # Fichiers uploadés
├── exports/               # Exports générés
├── backups/               # Sauvegardes
├── index.php             # Dashboard principal
├── admin.php             # Interface admin
├── login.php             # Page de connexion
├── logout.php            # Déconnexion
├── autoload.php          # Autoloader + SQL
├── .htaccess             # Configuration Apache
├── .env.example          # Exemple de configuration
└── INSTALL.md            # Ce fichier
```

## 🔐 Identifiants par défaut

- **Email** : admin@dpd.fr
- **Mot de passe** : admin123

⚠️ **Important** : Changez ces identifiants après la première connexion !

## 🛠️ Configuration Avancée

### Variables d'environnement (.env)

- `DB_*` : Paramètres de connexion à la base de données
- `APP_*` : Configuration générale de l'application
- `LOG_*` : Configuration des logs
- `EXPORT_*` : Paramètres d'export
- `UPLOAD_*` : Configuration des uploads

### Permissions des fichiers

```bash
# Permissions recommandées
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 777 logs/ uploads/ exports/ backups/
```

### Tâches Cron recommandées

```bash
# Nettoyer les logs anciens (tous les dimanches à 3h)
0 3 * * 0 php /path/to/dpd-dashboard/scripts/clean-logs.php

# Sauvegarde quotidienne (tous les jours à 2h)
0 2 * * * php /path/to/dpd-dashboard/scripts/backup.php
```

## 📊 Utilisation

### Dashboard Principal
- Visualisation des statistiques en temps réel
- Filtrage avancé des incidents
- Graphiques interactifs
- Export des données

### Interface d'Administration
- Gestion complète des incidents (CRUD)
- Import de fichiers CSV
- Export en différents formats
- Gestion des utilisateurs (à venir)

### API REST
Documentation des endpoints principaux :

- `GET /api/incidents` - Liste des incidents
- `GET /api/incidents/{id}` - Détail d'un incident
- `POST /api/incidents` - Créer un incident
- `PUT /api/incidents/{id}` - Modifier un incident
- `DELETE /api/incidents/{id}` - Supprimer un incident
- `GET /api/incidents/stats` - Statistiques
- `GET /api/tournees` - Liste des tournées
- `GET /api/types-incidents` - Types d'incidents

## 🐛 Dépannage

### Erreur de connexion à la base de données
- Vérifier les paramètres dans `.env`
- S'assurer que MySQL est démarré
- Vérifier les permissions de l'utilisateur MySQL

### Page blanche / Erreur 500
- Activer l'affichage des erreurs PHP
- Vérifier les logs dans `logs/`
- S'assurer que toutes les extensions PHP sont installées

### API non accessible
- Vérifier que mod_rewrite est activé
- Contrôler le fichier `.htaccess`
- Tester avec une URL directe

## 📞 Support

Pour toute question ou problème :
- Documentation complète dans README.md
- Logs d'erreur dans `logs/`
- Contact : support@dpd.fr

## 🔄 Mises à jour

Pour mettre à jour l'application :
1. Sauvegarder la base de données
2. Sauvegarder le fichier `.env`
3. Remplacer les fichiers
4. Exécuter les migrations si nécessaires

---

Développé avec ❤️ pour DPD France