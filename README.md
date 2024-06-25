# Projet BBC par étapes

Le projet **BBC par étapes** est lauréat de l’appel à projet recherche de l’ADEME « Vers des Bâtiments Durables ». 

## Objectif du Projet

L'objectif principal de ce projet est de créer une méthodologie de rénovation par étapes visant l'atteinte du label BBC Rénovation après les travaux. La rénovation globale est la meilleure solution technique et économique pour atteindre ce niveau. Cependant, les rénovations par étapes performantes répondent à un manque de rénovations globales et performantes en transformant le flux de rénovations par gestes en rénovations « BBC par étapes ».

Le **BBC par étapes** permet de surmonter les obstacles liés à la rénovation globale en alliant rénovation performante et rénovation par gestes. Il propose une méthodologie de travaux par étapes avec une vision globale du projet.

## Grands Principes du BBC par étapes

1. **Nombre d'étapes** : La rénovation doit être réalisée en 3 étapes de travaux maximum, avec 40% de gain en énergie primaire après la première étape.
2. **Étanchéité à l’air** : Vérifiée à chaque étape, avec un traitement des ponts thermiques.
3. **Priorisation de l’enveloppe** : La première étape doit traiter 2 lots sur l’enveloppe du bâtiment ainsi que la ventilation.
4. **Exigences unitaires** : Des exigences de résultats ou de moyens pour chaque lot.
5. **Planification des travaux** : Anticipation et traitement des interactions et interfaces entre les étapes.
6. **Intégration des interfaces** : Travail basé sur les productions de Dorémi et du projet BBC par étapes.


## Fondements Techniques

L’outil s'appuie sur plusieurs rapports techniques :
- Productions du projet BBC par étapes
- Rapport sur la rénovation performante par étapes de l’ADEME, produit par Enertech et Dorémi
- 71 fiches grand public produites par Enertech et Dorémi avec le soutien de l’ADEME
- Fiches artisans de Dorémi et Enertech proposant des solutions techniques pour traiter les interfaces entre deux lots de travaux réalisés en deux étapes distinctes.

## Version web de l'outil
Cet outil permet à l'utilisateur de saisir les caractéristiques d'un bâtiment. Il lui sera alors proposé un scénario de rénovation en une ou plusieurs étapes ainsi qu'une feuille de route reprenant toutes les étapes et les points de vigilances des travaux pour a terme atteindre la niveau BBC-effinergie rénovation.
La version Web de l'outil est une adaptation de la version Excel de l'outil développé par POUGET Consultants en collaboration avec Le collectif Effinergie, L'Association AJENA, L'entreprise Eireno, Les sociologues Gaëtan Brisepierre et Viviane Hamon.

## l'API (Web service) de l'outil.
Une API a été développée par Effinergie et Pouget Consultants afin de vérifier l’adéquation d’un scénario de rénovation par étapes par rapport aux critères d’exigences de l’arrêté BBC rénovation 2024 et BBC première étape. Cette API permet de lire les xml audit et de générer une feuille de route à destination des auditeurs. Elle permet ainsi un lien direct avec les logiciels validés pour les calculs répondant aux exigences du label BBC Effinergie Rénovation.

## Informations Techniques
Cet outil a été créé pour être intégré dans un site web utilisant le CMS Joomla. L'API peut être utilisée comme service web. Il est aussi possible d'utiliser l'API en appelant directement les scripts PHP.
Le fonctionnement de cet outil se base en grande partie sur des fichiers contant les paramètres et tableaux de conditions (logigrammes, interactions, tables de correspondances...). Ces fichiers sont des fichiers CSV et se trouvent dans le répertoire 'res'.

### Bibliothèques et outils 
Pour l'hébergement du site :
- Joomla

Pour la génération de PDF : 
- DomPDF

Pour la visualisation des fichiers sur la page de test de l'API : 
- JSONViewer
- simpleXML