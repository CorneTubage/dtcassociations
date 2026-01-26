# Gestion Associations pour Nextcloud

![Nextcloud](https://img.shields.io/badge/Nextcloud-0082C9?style=for-the-badge&logo=nextcloud&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue.js-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)

**Gestion Associations est une application Nextcloud destinée à simplifier la gestion administrative et technique des associations étudiantes de l'IUT.**

L'application offre une interface pour :

- Les Administrateurs et Superviseurs (Admin IUT) : Créer, renommer ou supprimer des associations et superviser l'ensemble des associations.

- Les Présidents d'association : Gérer les membres de leur bureau, attribuer des rôles (Trésorier, Secrétaire, Enseignant...) et gérer les accès.

    Important : L'application dépend de l'extension officielle Group folders pour la création des espaces de stockage et la gestion des permissions avancées (ACL).

## Fonctionnalités clés

- **Création automatique :** Génère une arborescence de dossiers standardisée (``officiel``, ``archive``, ``Comptes``...) à la création d'une association.

- **Gestion des rôles :** Synchronisation automatique des utilisateurs dans des groupes globaux (president, tresorier, secretaire, enseignent).

- **Permissions avancées :**
  - Dossiers ``archive`` et ``officiel`` protégés en écriture pour préserver la structure.
  - Dossier ``Comptes`` (Trésorerie) accessible en lecture/écriture uniquement pour les membres du bureau.
  - Accès en lecture seule pour les Enseignants et Invités.

- **Quotas** : Limitation automatique de l'espace de stockage (10 Go par défaut) pour chaque association.

## Prérequis

- **Nextcloud :** 30.0.0 ou supérieur

- **PHP :** 8.1 ou supérieur

- **Node.js & NPM :** Pour la compilation des assets Vue.js

- **Application requise :** [Group folders](https://apps.nextcloud.com/apps/groupfolders) (doit être installée et activée)

## Installation & Déploiement

### Installation Manuelle

1. Placez le dossier dtcassociations dans le répertoire apps/ ou custom_apps/ de votre Nextcloud.

1. Assurez-vous que les permissions sont correctes (utilisateur du serveur web, ex: www-data).

1. Activez l'application via la ligne de commande :

    ```bash
    #Sur une installation standard
    sudo -u www-data php occ app:enable dtcassociations

    #Sur Docker
    docker exec -u www-data nextcloud-app php occ app:enable dtcassociations
    ```

### Mise à jour

Après avoir remplacé les fichiers :

```bash
#Lance les migrations de base de données si nécessaire
php occ upgrade
```

## Développement

L'interface est construite avec **Vue.js 2.7** et **Vite**.

### Installation des dépendances

```bash
cd dtcassociations
npm install
```

### Compilation

- **Mode Développement (Watch) :** Recompile automatiquement à chaque changement.

    ```bash
    npm run watch
    ```

- **Mode Production (Build) :** À lancer avant de déployer (minifie le JS/CSS).

    ```bash
    npm run build
    ```

## Structure de dossiers générée

À la création d'une association, l'application génère automatiquement l'arborescence suivante (Group Folder) :

```text
Nom_Association/
├── archive/ (Lecture seule, sauf Admin/Président)
└── officiel/ (Lecture seule - Structure protégée)
├── Autres/
├── Papiers officiels de l'association/
│ ├── Documents Préfecture/
│ ├── Statuts/
│ └── Fiche Objectifs/
├── Comptes/ (Accès complet Membres, Lecture seule Enseignants/Invités)
│ ├── RIB/
│ ├── Relevés de comptes mensuels/
│ └── Notes de frais/
└── Rendus/
├── Comptes rendus mensuels/
├── Plan de gestion/
├── Bilan mi-parcours/
├── Rapport final/
└── Vidéo collectif/
```

## Technologies

- **Nextcloud** (Framework applicatif)

- **Vue.js 2.7** (Interface utilisateur, via Vite)

- **PHP** (Logique métier et API)

- **Group** Folders API (Gestion du stockage et des ACL)

- **SASS/CSS** (Design personnalisé respectant la charte graphique)

## Versions

- **1.0.9 :** Version initiale avec gestion CRUD complète, rôles et permissions ACL.

## Licence

Cette application est distribuée sous licence **AGPL-3.0-or-later**.
