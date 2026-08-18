# Points à confirmer

La documentation source contient plusieurs éléments historiques ou ambigus. Ils
sont conservés ici sans arbitrage afin de ne pas transformer des incertitudes en
règles définitives.

## Bonus Reports

Le Bonus Report est présenté comme trimestriel, mais la période décrite ensuite
est d'un mois. La granularité réelle doit être confirmée.

## Unité de mesure NAV

La logique d'alerte mentionne l'unité `PTS`, tandis que la description du champ
`extref_uom_code` mentionne `PNT`. La valeur canonique doit être confirmée.

## Forfait

La description initiale présente le forfait comme un nombre fixe d'heures ou de
jours par mois. Une autre section le décrit comme un package sans renouvellement.
Il faut confirmer s'il s'agit de deux usages historiques ou d'une évolution du
modèle.

## EuroJob

Certaines sections décrivent encore EuroJob comme composant du flux historique.
Ces références expliquent l'origine des règles métier, mais doivent être
distinguées du fonctionnement réellement actif si EuroJob n'est plus utilisé.
