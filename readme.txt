=== Simple Coherent Form ===
Contributors: tombgtn
Tags: simple, coherent, form, style, input, select, cf7, scf, accessible, optimize, homogenous
Requires at least: 6.4.2
Tested up to: 6.5.4
Stable tag: 1.7.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple plugin to create coherent inputs between themes and plugins. Light, efficient, accessible and compatible with CF7. Best for developers.

== Description ==

SCF is a simple plugin to create inputs for your forms with the same style everywhere, in your plugins or your theme. This plugin manages many fields so that they are efficient, accessible, versatile and homogeneous everywhere. A lot of options and multiple hooks. Made by developers, for developers.
Compatible with CF7.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/simple-coherent-form` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the scf_input function to create inputs or let CF7 manage that

== Changelog ==

= 1.0 =
* Création du plugin

= 1.1 =
* Possibilité de renvoyer l'html et non pas seulement l'afficher

= 1.2 =
* Bugfix : Utilisation des DateTime au sein de namespaces pour les champs Date
* Bugfix : Formattage des numéros de téléphone envoyés en ajax lors du multilingue
* Bugfix : Correction du format de date dans les champs CF7

= 1.3 =
* Ajout des champs Wysiwyg et Fichier
* Possibilité de modifier l'intitulé lisible des types de fichiers pour l'aide

= 1.4 =
* Possibilité de rendre un champ déjà focus (autofocus)
* Variante synchrone de la fonction de validation de champs en JS
* Renforcement de l'accessibilité du champ select

= 1.5 =
* Amélioration de l'accessibilité des champs Fichier
* Possibilité de limiter le nombre de fichiers des champs Fichiers
* Amélioration de l'accessibilité des champs Select
* Traduction des textes en en_EN
* Divers bugfix sur l'accessibilité et sur les champs fichiers

= 1.6 =
* Ajout du champ Time

= 1.7 =
* Ajoute la possibilité de filtrer les valeurs d'un champs select