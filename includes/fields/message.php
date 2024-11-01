<?php

declare(strict_types=1);

namespace SCF\Fields;

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Fields\SCFMessage')) {

	/**
	 * Classe du champ Message
	 */
	class SCFMessage {

		/**
		 * Constructeur de la classe
		 * Accroche les hooks
		 */
		function __construct() {
			add_filter( 'scf_types_availables', array($this, 'init') );
			add_filter( 'scf_types_with_group_block', array($this, 'groupBlock') );
			add_action( 'scf_field_message', array($this, 'displayHtml') );
			add_action( 'scf_after_field_message', array($this, 'removeError') );
			add_action( 'scf_label', array($this, 'removeLabel'), 10, 2 );
			add_action( 'scf_wrapper_args', array($this, 'removeAttributes'), 10, 2 );
			add_action( 'scf_wrapper_classes', array($this, 'removeClasses'), 10, 2 );
		}

		/**
		 * Initialise le type Message
		 * Filtre scf_types_availables
		 *
		 * @param string[] $types Listes des types disponibles
		 * @return string[] Types disponibles modifiés
		 */
		public function init($types) {
			$types[] = 'message';
			return $types;
		}

		/**
		 * Marque le type Message comme de type Block, afin de ne pas l'entourer d'un label
		 * Filtre scf_types_with_group_block
		 *
		 * @param string[] $types Listes des types Block
		 * @return string[] Listes des types Block modifiés
		 */
		public function groupBlock($types) {
			$types[] = 'message';
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
			if (isset($args['value']) && is_string($args['value']) && !empty($args['value'])) echo wp_kses_post($args['value']);
		}

		/**
		 * Enlève l'affichage des erreurs pour ce champ
		 * Action scf_after_field_{$type}
		 *
		 * @param array  $args     Arguments du champ
		 * @return void
		 */
		public function removeError($args) {
			$instance = \SCF\Front\SCFFront::getInstance();
			remove_action( 'scf_after_field', array($instance, 'displayErrors') );
		}

		/**
		 * Enlève le label pour ce champ
		 * Action scf_label
		 *
		 * @param string $label Label du champ
		 * @param array  $args  Arguments du champ
		 * @return void
		 */
		public function removeLabel($label, $args) {
			if ($args['type']=='message') {
				return null;
			}
			return $label;
		}

		/**
		 * Enlève les attributs pour le groupe de ce champ
		 * Action scf_wrapper_args
		 *
		 * @param string $attr Attributs du groupe de champ
		 * @param array  $args Arguments du champ
		 * @return void
		 */
		public function removeAttributes($attr, $args) {
			if ($args['type']=='message') {
				$wrapper_args = (isset($args['wrapper_attributes']) && is_array($args['wrapper_attributes'])) ? $args['wrapper_attributes'] : array() ;
				if (isset($attr['class'])) $wrapper_args['class'] = $attr['class'];
				if (isset($attr['id'])) $wrapper_args['id'] = $attr['id'];

				return $wrapper_args;
			}
			return $attr;
		}

		/**
		 * Enlève les attributs pour le groupe de ce champ
		 * Action scf_wrapper_classes
		 *
		 * @param string $attr Attributs du groupe de champ
		 * @param array  $args Arguments du champ
		 * @return void
		 */
		public function removeClasses($classes, $args) {
			if ($args['type']=='message') {
				$wrapper_classes_array = array('scf-group');
				$wrapper_classes_array[] = 'scf-' . $args['type'];
				if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $wrapper_classes_array[] = 'scf-group-name-'.esc_attr(sanitize_title($args['name']));

				return $wrapper_classes_array;
			}
			return $classes;
		}
	}

	new SCFMessage();
}
