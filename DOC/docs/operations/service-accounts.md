# Service Accounts

Un Service Account est la représentation Contractika d'un contrat AutoTask. Il
porte le solde de points du contrat et rassemble les lignes qui débitent ou
créditent ce solde.

## Lignes de Service Account

Les lignes de Service Account ont deux origines principales :

* les prestations AutoTask, converties en débits de points ;
* les lignes Navision réconciliées, converties en crédits ou notes de crédit.

Les prestations sont regroupées par ticket, période et prestataire selon les
règles métier historiques. Le calcul des points tient compte de la durée, du rôle
du prestataire, de l'urgence, de la plage horaire et du type de service.

## Renouvellement des Service Packages

Les Service Packages sont des paquets prépayés. Lorsqu'un solde passe sous zéro
ou sous le plancher de renouvellement défini, Contractika génère un ticket dans
AutoTask pour avertir l'équipe commerciale.

Le ticket contient notamment :

* le client AutoTask ;
* le contrat AutoTask ;
* l'identifiant du Service Account ;
* le solde au jour de génération ;
* l'information de renouvellement automatique ;
* une échéance de traitement.

Si le renouvellement automatique est autorisé, une offre Service Package peut
être importée et facturée dans Navision. Sinon, l'offre doit être confirmée par
le client avant import.

## Soldes renvoyés vers AutoTask

La tâche `Push Contract Balances` synchronise les soldes Contractika vers les
contrats AutoTask, via les champs personnalisés de balance et de date de dernière
mise à jour. Elle est également responsable de la génération des tickets de
renouvellement lorsque les seuils sont atteints.

## Calcul des points

La tâche `Compute Points` vérifie que les points de toutes les lignes issues des
time entries ont bien été calculés. Elle agit comme contrôle de complétude sur
les lignes importées.
