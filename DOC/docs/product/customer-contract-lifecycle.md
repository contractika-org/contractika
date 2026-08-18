# Cycle de vie clients et contrats

Contractika reflète le cycle de vie opérationnel des clients et contrats gérés
dans AutoTask et Navision.

## Règles générales

La liste standard des clients affiche les clients actifs disposant d'au moins un
Service Account. La documentation source décrit la logique suivante :

* lorsqu'une société AutoTask devient un client, un Customer est créé dans Contractika ;
* lorsqu'une société devient inactive ou supprimée, Contractika la masque ou génère une alerte si elle possède encore un contrat ;
* tant qu'un client n'a pas de Service Account, son état actif suit AutoTask ;
* lorsqu'un client dispose d'un Service Account, son état actif suit principalement Navision via le champ de blocage ;
* lorsqu'un contrat actif est récupéré, le client associé est marqué comme ayant un Service Account.

## Nouveau prospect

Un prospect est d'abord créé dans AutoTask avec un type de société prospect. A ce
stade, il n'existe pas encore dans Navision ni dans Contractika comme client
opérationnel.

## Conversion en client

Lorsqu'un prospect devient client :

* son type de société est modifié dans AutoTask ;
* le client est créé manuellement dans Navision ;
* l'identifiant NAV est assigné dans AutoTask ;
* Contractika synchronise le client, puis l'affiche lorsqu'un Service Account est disponible.

Une fois l'identifiant NAV assigné, les informations Navision prévalent pour les
champs client comme le nom, la TVA, les conditions de paiement, le prix du point,
le discount et l'état bloqué.

## Création d'un contrat

Un contrat AutoTask actif devient un Service Account dans Contractika. Si le
client correspondant n'existe pas, Contractika génère une alerte afin que la
cohérence des données soit corrigée.

## Résiliation et désactivation

Lorsqu'un contrat est résilié dans AutoTask, le Service Account correspondant est
désactivé. Si le client ne possède plus de contrat actif, il n'est plus affiché
dans la liste standard.

Lorsqu'un client cesse d'être actif, Navision marque le client comme bloqué.
Contractika répercute cet état, puis AutoTask est mis à jour lors de la
synchronisation suivante.
