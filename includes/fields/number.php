<?php

declare(strict_types=1);

namespace SCF\Fields;

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Fields\SCFNumber')) {

	/**
	 * Classe du champ Number
	 */
	class SCFNumber {

		/**
		 * Constructeur de la classe
		 * Accroche les hooks
		 */
		function __construct() {
			add_filter( 'scf_default_args', array($this, 'addDefaultArgs') );
			add_filter( 'scf_types_availables', array($this, 'init') );
			add_filter( 'scf_types_with_group_label', array($this, 'groupLabel') );
			add_action( 'scf_field_number', array($this, 'displayHtml') );
			add_action( 'scf_field_number', array($this, 'displayValidation'), 20 );
		}

		/**
		 * Ajoute les arguments par défaut nécessaires au type Number
		 * Filtre scf_default_args
		 *
		 * @param array $args Arguments par défaut
		 * @return array Arguments modifiés
		 */
		public function addDefaultArgs($args) {
			$args['min'] = null;
			$args['max'] = null;
			$args['step'] = null;
			$args['show_controls'] = true;
			return $args;
		}

		/**
		 * Initialise le type Number
		 * Filtre scf_types_availables
		 *
		 * @param string[] $types Listes des types disponibles
		 * @return string[] Types disponibles modifiés
		 */
		public function init($types) {
			$types[] = 'number';
			return $types;
		}

		/**
		 * Marque le type Number comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function groupLabel($types) {
			$types[] = 'number';
			return $types;
		}

		/**
		 * Affiche le code HTML
		 * Action scf_field_{$type}
		 *
		 * @param array $args Arguments du champ
		 * @return void
		 */
		public function displayHtml($args) {

			$input_args = (isset($args['attributes']) && is_array($args['attributes'])) ? $args['attributes'] : array() ;

			$input_args['type'] = 'number';
			if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $input_args['name'] = $args['name'];
			$input_args['value'] = (isset($args['value'])) ? strval($args['value']) : '' ;
			$input_args['placeholder'] = (isset($args['placeholder'])) ? strval($args['placeholder']) : '' ;
			$input_args['autocomplete'] = (isset($args['autocomplete'])) ? strval($args['autocomplete']) : 'on' ;
			if (isset($args['readonly']) && $args['readonly']) $input_args['readonly'] = 'readonly';
			if (isset($args['focus']) && $args['focus']) $input_args['autofocus'] = 'autofocus';
			if (isset($args['disabled']) && $args['disabled'] && !in_array($args['disabled'], array('on_pointer', 'on_touchable'))) $input_args['disabled'] = 'disabled';
			if (isset($args['show_controls']) && $args['show_controls']) $input_args['data-controls'] = 'true';
			if (isset($args['min'])) $input_args['min'] = floatval($args['min']);
			if (isset($args['max'])) $input_args['max'] = floatval($args['max']);
			if (isset($args['step'])) $input_args['step'] = floatval($args['step']);
			if (isset($args['format']) && !empty($args['format'])) $input_args['data-format'] = $args['format'];
			if (!isset($input_args['inputmode'])) $input_args['inputmode'] = 'numeric';
			if (!isset($input_args['enterkeyhint'])) $input_args['enterkeyhint'] = 'next';
			if (!isset($input_args['tabindex'])) $input_args['tabindex'] = 0;

			$input_args = apply_filters('scf_input_args', $input_args, $args);

			?><input <?php
				foreach ($input_args as $input_args_key => $input_args_value) { echo esc_attr($input_args_key) . '="' . esc_attr($input_args_value) . '" '; }
			?>/><?php

		}

		/**
		 * Affiche le code HTML de la coche de valisation
		 *
		 * @param array  $args     Arguments du champ
		 * @return void
		 */
		public function displayValidation($args) {
			if (isset($args['show_validation']) && $args['show_validation']) { ?><span class="scf-valid"></span><?php }
		}
	}

	new SCFNumber();
}
