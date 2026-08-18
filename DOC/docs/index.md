# Contractika

Contractika est le logiciel de gestion des contrats de services de Netika IT
Services. Il centralise les données issues d'AutoTask, Navision et SDWorx afin
de suivre les prestations, calculer les points consommés, maintenir les soldes
des contrats et produire les rapports utilisés pour la facturation et le
contrôle opérationnel.

## Objet du logiciel

Contractika remplace progressivement les usages historiques d'EuroJob pour la
gestion des contrats clients et des rapports de prestations. Le logiciel sert à
relier les prestations encodées dans AutoTask avec les informations de
facturation provenant de Navision, tout en tenant compte des données RH utiles
fournies par SDWorx.

Les fonctions principales sont :

* synchroniser les référentiels clients, employés, rôles, contrats et absences ;
* convertir les prestations AutoTask en lignes de Service Account valorisées en points ;
* maintenir les soldes des Service Accounts et renvoyer certains soldes vers AutoTask ;
* rapprocher les lignes de facturation Navision avec les Service Accounts ;
* générer, valider, envoyer ou archiver les reports de prestations ;
* produire des Bonus Reports et Cut-off Reports ;
* signaler les incohérences via un système d'alertes relançables.

## Vue fonctionnelle

![Architecture historique et interactions entre les outils](_assets/img/architecture-integrations.png)

AutoTask reste la source principale pour les prestations, les tickets, les
contrats et les rôles. Navision complète les données client et fournit les lignes
de facturation utilisées pour créditer les Service Accounts. SDWorx fournit les
informations RH, notamment les absences à synchroniser avec les agendas
opérationnels.

## Public cible

Cette documentation s'adresse principalement :

* aux équipes métier qui suivent les contrats, les soldes et les reports ;
* aux équipes opérationnelles qui corrigent les données source et les alertes ;
* aux développeurs qui maintiennent les synchronisations et les traitements ;
* aux responsables administratifs qui exploitent les rapports de contrôle.
