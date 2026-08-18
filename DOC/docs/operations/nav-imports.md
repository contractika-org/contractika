# Imports NAV

Les imports NAV permettent de rapprocher les lignes de facturation Navision avec
les Service Accounts Contractika. Ils créent les crédits nécessaires pour refléter
les factures, provisions, Service Packages et notes de crédit.

## Objectif

Navision émet des lignes de facture pour renouveler certains services ou
facturer des prestations. Contractika importe ces lignes dans une table d'attente
avant de les réconcilier avec un client et un Service Account.

Les lignes réconciliées génèrent des lignes de Service Account de type crédit,
positives pour une facture et négatives pour une note de crédit.

## Fenêtre d'import

La documentation source indique que les imports portent sur une période maximale
de deux mois en arrière. Les lignes déjà importées sont ignorées sur base de leur
numéro de document et de leur numéro de ligne.

## Etapes de traitement

1. Récupération des lignes Navision récentes.
2. Chargement dans une table d'attente dédiée.
3. Pré-traitement des champs et résolution automatique des correspondances.
4. Tentative de réconciliation avec le client et le Service Account.
5. Création de la ligne de crédit lorsque la ligne est valide.

## Réconciliation

La réconciliation identifie :

* le client via l'identifiant NAV ;
* le Service Account cible ;
* la quantité de points ;
* le prix unitaire ;
* le montant ;
* l'unité de mesure.

Si le mapping ne peut pas être établi, la ligne est marquée en erreur. Si le prix
ou l'unité de mesure semble incohérent, la ligne reçoit une alerte. Les alertes
peuvent être ignorées, mais les erreurs empêchent la réconciliation.

## Champs principaux

| Champ Contractika | Origine Navision | Rôle |
|---|---|---|
| `extref_document_no` | Document No. | Identifiant de facture. |
| `extref_line_no` | Line No. | Identifiant de ligne. |
| `extref_customer` | Sell-to Customer No. | Identifiant client NAV. |
| `extref_description2` | Description 2 | Référence contrat ou Service Account. |
| `extref_uom_code` | Unit of Measure Code | Unité de mesure. |
| `extref_unit_price` | Unit Price | Prix unitaire à comparer au prix client. |
| `extref_quantity` | Quantity | Quantité de points attendue. |
| `extref_amount` | Amount | Montant de la ligne. |
