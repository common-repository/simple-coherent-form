<?php

/**
 * Fonction principale
 *
 * @static
 * @param array $args Paramètres de l'input
 * @return void
 */
function scf_input($args) {
	return \SCF\Front\SCFFront::input($args);
}

/**
 * Récupère la liste des pays et renvoie les valeurs demandées pour les options de select2 et select natif
 * Les labels et valeurs peuvent utiliser les remplaceurs suivants :
 * - {cca2}   : le code à 2 lettres du pays (ISO 3166-1 alpha-2)
 * - {cca3}   : le code à 3 lettres du pays (ISO 3166-1 alpha-3)
 * - {ccn3}   : le code numérique du pays (ISO 3166-1 numérique)
 * - {tel}    : l'indicatif téléphonique du pays
 * - {cioc}   : le code olympique du pays (uniquement pour les pays sur les listes olympiques)
 * - {flag}   : le code html du drapeau du pays
 * - {name}   : le nom du pays du la langue du site
 * - {native} : le nom du pays dans sa propre langue
 * - {rtl}    : si la langue du pays se lit de droite à gauche (rtl) ou de gauche à droite (ltr)
 *
 * @param string      $value          Valeur de l'option
 * @param string|null $label          Intitulé de l'option dans la liste d'options du select2
 * @param string|null $label_native   Intitulé de l'option dans le select natif
 * @param string|null $label_selector Intitulé de l'option dans le résumé du select2
 * @param string      $order_by       Ordre de la liste des pays parmis [cca2, cca3, ccn3, cioc, name, native, tel, flag, rtl]
 * @return array[] Liste des pays avec pour chacun un tableau de valeurs [cca2, cca3, ccn3, cioc, name, native, tel, flag, rtl]
 */
function scf_get_options_list($value, $label = null, $label_native = null, $label_selector = null, $order_by = 'default') {
	return \SCF\Front\SCFFront::getList($value, $label, $label_native, $label_selector, $order_by);
}
