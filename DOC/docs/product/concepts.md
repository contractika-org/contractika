# Concepts métier

Contractika repose sur un modèle de contrats valorisés en points. Les
prestations encodées en heures dans AutoTask sont transformées en mouvements de
points selon la durée, le rôle du prestataire, le type de service et les règles
de coefficient applicables.

## Concepts principaux

| Concept | Rôle dans Contractika |
|---|---|
| Customer | Client synchronisé depuis AutoTask puis enrichi par Navision. |
| Service Account | Représentation Contractika d'un contrat AutoTask et de son solde de points. |
| SA Line | Mouvement porté sur un Service Account : débit de prestation ou crédit de facturation. |
| Time Entry | Prestation encodée dans AutoTask, rattachée à un ticket. |
| Report | Rapport périodique des lignes d'un Service Account. |
| NAV Line | Ligne de facturation importée de Navision avant réconciliation. |
| Alert | Anomalie détectée par un test de cohérence. |

## Contrats clients

Les prestations de Netika sont réalisées dans le cadre de contrats définissant
les modalités de tarification et de facturation. Un même client peut disposer de
plusieurs contrats.

Les types décrits dans la documentation source sont :

* **service package** : paquet de points prépayé, renouvelable selon les besoins ;
* **provision** : nombre de points facturé mensuellement, avec régularisation ;
* **forfait** : enveloppe fixe ou package sans renouvellement selon les usages historiques ;
* **régie ou cas particuliers** : prestations hors modèle standard ou clients sans contrat.

## Facturation des prestations

AutoTask fonctionne en heures et en tickets. Contractika traduit ces prestations
en points. Le calcul tient compte :

* de la durée totale de la prestation, incluant le déplacement éventuel ;
* du rôle ou profil du prestataire ;
* de l'urgence ;
* de la plage horaire ;
* du service souscrit par le client, par exemple coaching ou omnium.

La formule fonctionnelle est :

```text
duration = end_time - start_time + travel_duration
points = duration * coefficient
```

Les soldes de points sont ensuite mis à jour par Service Account. Les crédits
proviennent principalement des factures et notes de crédit importées depuis
Navision.

## Prestataires et bonus

Les prestataires encodent leurs prestations dans AutoTask. Le rôle associé à la
prestation est utilisé pour déterminer le coefficient applicable. Les prestations
servent également de base à certains Bonus Reports. Certains contrats peuvent
être exclus du calcul des bonus à l'aide de tags.
