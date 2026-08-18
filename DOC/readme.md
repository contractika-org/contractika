## Documentation Contractika

Ce dossier contient une documentation produit de Contractika, structurée pour une
publication Markdown/MkDocs.

La documentation est basée sur l'archive `contractika-documentation.zip` fournie
par l'utilisateur. Les fichiers contenus dans cette archive ont été utilisés
comme source descriptive uniquement.

```
/
├── docs
├── mkdocs.yml
└── nav.yml
```

### Structure

* `docs/index.md` présente la vue d'ensemble du logiciel.
* `docs/product/` décrit les concepts métier, les contrats et le cycle de vie client.
* `docs/architecture/` décrit les systèmes intégrés et les synchronisations.
* `docs/operations/` décrit les Service Accounts, les reports, les imports NAV et les alertes.
* `docs/reference/` reprend les points à confirmer issus de la documentation source.
* `docs/_assets/img/` contient les schémas et captures extraits de l'archive.

### Consultation locale

Si MkDocs est installé, lancer depuis ce dossier :

```bash
mkdocs serve
```

Le site sera disponible à l'adresse indiquée par MkDocs.
