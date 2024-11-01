<?php

declare(strict_types=1);

namespace SCF\Fields;

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Fields\SCFWysiwyg')) {

	/**
	 * Classe du champ Wysiwyg
	 */
	class SCFWysiwyg {

		/**
		 * Constructeur de la classe
		 * Accroche les hooks
		 */
		function __construct() {
			add_filter( 'scf_default_args', array($this, 'addDefaultArgs') );
			add_filter( 'scf_types_availables', array($this, 'init') );
			add_filter( 'scf_types_with_group_label', array($this, 'groupLabel') );
			add_action( 'scf_enqueue_scripts_wysiwyg', array($this, 'enqueueScripts') );
			add_action( 'scf_field_wysiwyg', array($this, 'displayHtml') );
			add_action( 'scf_field_wysiwyg', array($this, 'displayValidation'), 20 );
		}

		/**
		 * Ajoute les arguments par défaut nécessaires au type Wysiwyg
		 * Filtre scf_default_args
		 *
		 * @param array $args Arguments par défaut
		 * @return array Arguments modifiés
		 */
		public function addDefaultArgs($args) {
			$args['rows'] = 20;
			$args['cols'] = 20;
			$args['actionbars'] = array(
				'top' => array('bold', 'italic', 'underline')
			);
			return $args;
		}

		/**
		 * Initialise le type Wysiwyg
		 * Filtre scf_types_availables
		 *
		 * @param string[] $types Listes des types disponibles
		 * @return string[] Types disponibles modifiés
		 */
		public function init($types) {
			$types[] = 'wysiwyg';
			return $types;
		}

		/**
		 * Marque le type Wysiwyg comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function groupLabel($types) {
			$types[] = 'wysiwyg';
			return $types;
		}

		/**
		 * Enregistre le script du wysiwyg
		 * Action scf_enqueue_scripts_{$type}
		 *
		 * @param void
		 * @return void
		 */
		public function enqueueScripts() {
			wp_enqueue_script('scf-squire');
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
			$input_args['class'] = (isset($args['class'])) ? array($args['class']) : array() ;
			$input_args['class'][] = 'scf-wysiwyg-textarea';
			$input_args['class'] = implode(' ', $input_args['class']);

			$input_args = apply_filters('scf_input_args', $input_args, $args);

			$editor_args = array('class' => 'scf-wysiwyg-editor', 'style' => 'display:none;');
			if (isset($args['rows'])) { $editor_args['style'] .= 'height: ' . ((intval($args['rows']) * 21) + 22) . 'px'; }

			if (isset($args['actionbars']) && is_array($args['actionbars'])) {
				foreach ($args['actionbars'] as $actionbar_key => $actionbar) {
					?><div class="scf-wysiwyg-actionbar scf-wysiwyg-actionbar-<?php echo esc_attr($actionbar_key); ?>"><?php
						$actionbar = (array) $actionbar;

						foreach ($actionbar as $action) {
							switch ($action) {
								case 'bold':
									?><span role="button" data-tag="b" data-action="bold" data-action-on-active="removeBold" class="scf-wysiwyg-button-bold">B</span><?php
								break;
								case 'italic':
									?><span role="button" data-tag="i" data-action="italic" data-action-on-active="removeItalic" class="scf-wysiwyg-button-italic">I</span><?php
								break;
								case 'underline':
									?><span role="button" data-tag="u" data-action="underline" data-action-on-active="removeUnderline" class="scf-wysiwyg-button-underline">U</span><?php
								break;
								
								default: break;
							}
						}
					?></div><?php
				}
			}

			?><textarea <?php
				foreach ($input_args as $input_args_key => $input_args_value) { echo esc_attr($input_args_key) . '="' . esc_attr($input_args_value) . '" '; }
			?>><?php echo (isset($args['value'])) ? esc_html(strval($args['value'])) : ''; ?></textarea><div <?php
				foreach ($editor_args as $editor_args_key => $editor_args_value) { echo esc_attr($editor_args_key) . '="' . esc_attr($editor_args_value) . '" '; }
			?>><?php echo (isset($args['value'])) ? esc_html(strval($args['value'])) : ''; ?></div><?php

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

	new SCFWysiwyg();
}
