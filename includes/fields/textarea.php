<?php

declare(strict_types=1);

namespace SCF\Fields;

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Fields\SCFTextarea')) {

	/**
	 * Classe du champ Textarea
	 */
	class SCFTextarea {

		/**
		 * Constructeur de la classe
		 * Accroche les hooks
		 */
		function __construct() {
			add_filter( 'scf_default_args', array($this, 'addDefaultArgs') );
			add_filter( 'scf_types_availables', array($this, 'init') );
			add_filter( 'scf_types_with_group_label', array($this, 'groupLabel') );
			add_action( 'scf_field_textarea', array($this, 'displayHtml') );
			add_action( 'scf_field_textarea', array($this, 'displayValidation'), 20 );
		}

		/**
		 * Ajoute les arguments par défaut nécessaires au type Textarea
		 * Filtre scf_default_args
		 *
		 * @param array $args Arguments par défaut
		 * @return array Arguments modifiés
		 */
		public function addDefaultArgs($args) {
			$args['rows'] = 2;
			$args['cols'] = 20;
			return $args;
		}

		/**
		 * Initialise le type Textarea
		 * Filtre scf_types_availables
		 *
		 * @param string[] $types Listes des types disponibles
		 * @return string[] Types disponibles modifiés
		 */
		public function init($types) {
			$types[] = 'textarea';
			return $types;
		}

		/**
		 * Marque le type Textarea comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function groupLabel($types) {
			$types[] = 'textarea';
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

			if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $input_args['name'] = $args['name'];
			$input_args['placeholder'] = (isset($args['placeholder'])) ? strval($args['placeholder']) : '' ;
			$input_args['autocomplete'] = (isset($args['autocomplete'])) ? strval($args['autocomplete']) : 'on' ;
			if (isset($args['rows']) && !empty($args['rows'])) $input_args['rows'] = $args['rows'];
			if (isset($args['cols']) && !empty($args['cols'])) $input_args['cols'] = $args['cols'];
			if (isset($args['readonly']) && $args['readonly']) $input_args['readonly'] = 'readonly';
			if (isset($args['focus']) && $args['focus']) $input_args['autofocus'] = 'autofocus';
			if (isset($args['disabled']) && $args['disabled'] && !in_array($args['disabled'], array('on_pointer', 'on_touchable'))) $input_args['disabled'] = 'disabled';
			if (isset($args['format']) && !empty($args['format'])) $input_args['data-format'] = $args['format'];
			if (!isset($input_args['enterkeyhint'])) $input_args['enterkeyhint'] = 'next';
			if (!isset($input_args['tabindex'])) $input_args['tabindex'] = 0;

			$input_args = apply_filters('scf_input_args', $input_args, $args);

			?><textarea <?php
				foreach ($input_args as $input_args_key => $input_args_value) { echo esc_attr($input_args_key) . '="' . esc_attr($input_args_value) . '" '; }
			?>><?php echo (isset($args['value'])) ? esc_html(strval($args['value'])) : ''; ?></textarea><?php

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

	new SCFTextarea();
}
