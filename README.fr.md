# Rehearsal

**Essayer un changement dans l'admin de votre site Symfony avant la production.**

Rehearsal ajoute à votre admin une section qui liste les changements proposés pour le
site (une mise à jour de dépendances, une correction, une nouvelle page), chacun avec
ses notes, ce qu'il faut vérifier et ses résultats de tests. Pour chacun : **Valider**
(avec un commentaire facultatif) ou **Refuser avec un commentaire**. Une validation
émet un événement que votre application écoute pour fusionner et déployer ; Rehearsal
se contente d'enregistrer la décision.

Il est fait pour une copie du site construite depuis une branche (une préproduction,
un bac à sable) : vous ouvrez son admin, essayez le changement pour de vrai, et
décidez là.

*English documentation: [README.md](README.md)*

```bash
composer require acencyril/rehearsal-bundle
```

---

## Le fonctionnement

1. Quelque chose écrit les changements proposés dans `.rehearsal/changes.json` sur la
   branche : une personne, un script, ou [Uplift](https://github.com/acencyril/uplift-bundle)
   (`uplift:apply --rehearsal=.rehearsal/changes.json`).
2. La branche est déployée sur une copie. `rehearsal:sync` (ou la première visite de
   la section) repère les nouveaux changements, envoie l'e-mail récapitulatif s'il est
   activé et, en mode `auto`, valide ceux dont les tests sont passés.
3. Vous ouvrez la section, essayez chaque changement et décidez. Chaque décision est
   écrite dans le fichier des décisions et émet `ChangeValidated` ou `ChangeRejected`.
4. Votre écouteur, ou la CI qui lit `rehearsal:status --json`, fait la suite :
   fusionner, déployer, étiqueter, ou renvoyer le refus à qui a proposé le changement.

Une décision vaut pour une **révision** d'un changement. Quand le changement revient
avec une nouvelle révision (corrigé après un refus, ou testé de nouveau), il est de
nouveau à décider, avec la décision précédente et son commentaire affichés.

## Le fichier des changements

```json
{
    "title": "Maintenance de novembre",
    "summary": "Mises à jour de dépendances et une correction.",
    "link": "https://github.com/exemple/site/pull/12",
    "revision": "abc1234",
    "tests": {"status": "passed", "summary": "355 parcours"},
    "changes": [
        {
            "id": "formulaire-contact",
            "title": "Formulaire de contact plus court",
            "detail": "Deux champs au lieu de cinq.",
            "replaced": "Le formulaire à cinq champs",
            "check": "Envoyez un message depuis la page contact.",
            "url": "/fr/contact",
            "source": "manual",
            "revision": "abc1234",
            "tests": {"status": "failed", "summary": "1 échec", "failures": ["ContactTest::testSend"], "output": "…"}
        }
    ]
}
```

| Champ | |
|---|---|
| `id` | `a-z 0-9 . _ -`, 64 au plus, le même d'une révision à l'autre |
| `title`, `detail`, `replaced`, `check` | du texte ; les retours à la ligne sont gardés, le HTML est échappé |
| `url` | un chemin de ce site à ouvrir ; tout le reste (un autre site, `javascript:`) est ignoré |
| `source` | qui l'a proposé (`uplift`, `manual`…) |
| `revision` | le code sur lequel le changement a été testé ; par défaut, le `revision` du fichier |
| `tests` | `status` : `passed`, `failed` ou `not_run` ; `summary`, `failures`, `output` ; par défaut, les `tests` du fichier |
| `link` (fichier) | où en lire plus, `https://` seulement (`pr` est lu aussi) |

Les entrées sans `id` valide ou sans `title` sont ignorées ; un fichier absent ou
cassé donne une liste vide. L'ancienne forme de tests
`{"ok": 350, "ko": 2, "failures": [...]}` (parcours automatiques) est lue aussi.

## Validation : manuelle ou automatique

```yaml
rehearsal:
    validation: manual
```

- `manual` (par défaut) : rien ne va plus loin tant que personne n'a cliqué sur
  **Valider**.
- `auto` : un changement en attente est validé dès que ses tests sont passés
  (`by: "auto"`, `auto: true`). Un changement sans tests réussis (en échec, pas lancés,
  absents) attend toujours quelqu'un. À régler site par site : `auto` là où les tests
  font foi, `manual` ailleurs.

## Brancher la décision

```php
use Acencyril\RehearsalBundle\Event\ChangeValidated;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class DeployValidatedChange
{
    public function __invoke(ChangeValidated $event): void
    {
        // $event->change : id, title, revision, source, tests…
        // $event->decision : comment, by, at, auto
        // $event->set : tout le fichier des changements
        // ex. écrire un fichier que la CI surveille, appeler votre webhook de déploiement…
    }
}
```

`ChangeRejected` porte le commentaire ; `ChangesProposed` est émis une fois par
nouvelle révision de changement. Les événements portent le nom de leur classe :
écoutez chacun de ceux dont vous avez besoin.

Les commentaires sont ce que quelqu'un a tapé : ce sont des remarques à lire, jamais
des commandes à exécuter.

## Installation

Avec Symfony Flex, la recette enregistre le bundle et écrit
`config/packages/rehearsal.yaml` et `config/routes/rehearsal.yaml`. Sans Flex :

```php
// config/bundles.php
Acencyril\RehearsalBundle\RehearsalBundle::class => ['all' => true],
```

```yaml
# config/routes/rehearsal.yaml
rehearsal:
    resource: '@RehearsalBundle/config/routes.php'
    prefix: '/{_locale}/admin/rehearsal'
    trailing_slash_on_root: false
    requirements:
        _locale: 'en|fr'
```

Le préfixe est à vous : mettez la section là où vit votre admin. Le contrôleur vérifie
lui-même `rehearsal.role` (403 sinon) ; gardez le chemin derrière votre pare-feu pour
que les visiteurs soient invités à se connecter. Le bundle a besoin de SecurityBundle,
du traducteur, de Twig et de la protection CSRF (`framework.csrf_protection`).

## Configuration

```yaml
rehearsal:
    changes_file: '%kernel.project_dir%/.rehearsal/changes.json'
    decisions_file: '%kernel.project_dir%/var/rehearsal/decisions.json'
    validation: manual                  # ou auto
    role: ROLE_ADMIN
    parent_template: 'admin/layout.html.twig'
    mailer:
        enabled: true
        from: 'no-reply@exemple.fr'
        to: ['vous@exemple.fr']
        subject_prefix: '[Rehearsal]'
        locale: fr
```

### La page

La section étend `parent_template`, la mise en page de votre admin, qui doit définir
les blocs `title` et `body` (par défaut : une page nue). Pour changer le HTML,
surchargez `templates/bundles/RehearsalBundle/index.html.twig`, ou seulement son bloc
`rehearsal_content` ou `rehearsal_styles`. Les textes sont des clés de traduction
(domaine `rehearsal`, anglais et français).

### Les e-mails

Avec `mailer.enabled` et Symfony Mailer installé : un récapitulatif quand de nouveaux
changements apparaissent (avec ceux validés automatiquement), et un e-mail par
décision, avec un lien vers la section. Réglez `default_uri`
(`framework.router.default_uri`) pour que le lien soit juste depuis la console.

## Commandes

| Commande | |
|---|---|
| `rehearsal:sync` | repère les nouveaux changements (e-mail, `ChangesProposed`) et valide en mode `auto` ; à lancer après un déploiement ou par cron |
| `rehearsal:status` | où en est chaque changement ; `--json` pour la CI (`counts`, `all_validated`, chaque changement avec sa décision) |

## Tests

```bash
vendor/bin/phpunit
```

## Licence

MIT
