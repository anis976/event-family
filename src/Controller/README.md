# Contrôleurs EventFamily

Tous les contrôleurs métier étendent **`AbstractAppController`** (pas `AbstractController` directement).

## Messages flash

```php
$this->addSuccessFlash('Message de succès.');
$this->addErrorFlash('Message d\'erreur.');
$this->addWarningFlash('Message d\'avertissement.');
$this->addInfoFlash('Message d\'information.');
```

Les messages s’affichent via `templates/components/_ef_flash_messages.html.twig` (layout `base` et `auth`).

Préférer les clés de traduction via `$this->trans()` (helper sur `AbstractAppController`, Symfony 8 n'a plus `trans()` sur `AbstractController`) :

```php
$this->addSuccessFlash($this->trans('ma_cle', [], 'messages'));
```

## Formulaires Symfony

- Thème Twig : `form/ef_form_theme.html.twig` (erreurs stylées, `is-invalid`)
- Résumé validation : `{% include 'components/_ef_form_validation_summary.html.twig' with { form: monForm } only %}`
