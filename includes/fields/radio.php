<?php

declare(strict_types=1);

namespace SCF\Fields;

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Fields\SCFRadio')) {

	/**
	 * Classe du champ Radio
	 */
	class SCFRadio {

		/**
		 * Constructeur de la classe
		 * Accroche les hooks
		 * Définie les constantes
		 */
		function __construct() {
			add_filter( 'scf_default_args', array($this, 'addDefaultArgs') );
			add_filter( 'scf_types_availables', array($this, 'init') );
			add_filter( 'scf_types_with_group_block', array($this, 'groupBlock') );
			add_action( 'scf_field_radio', array($this, 'displayHtml') );
		}

		/**
		 * Ajoute les arguments par défaut nécessaires au type Radio
		 * Filtre scf_default_args
		 *
		 * @param array $args Arguments par défaut
		 * @return array Arguments modifiés
		 */
		public function addDefaultArgs($args) {
			$args['options_list'] = null;
			$args['options'] = array();
			return $args;
		}

		/**
		 * Initialise le type Radio
		 * Filtre scf_types_availables
		 *
		 * @param string[] $types Listes des types disponibles
		 * @return string[] Types disponibles modifiés
		 */
		public function init($types) {
			$types[] = 'radio';
			return $types;
		}

		/**
		 * Marque le type Radio comme de type Block, afin de ne pas l'entourer d'un label
		 * Filtre scf_types_with_group_block
		 *
		 * @param string[] $types Listes des types Block
		 * @return string[] Listes des types Block modifiés
		 */
		public function groupBlock($types) {
			$types[] = 'radio';
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

			$options = array();
			if (isset($args['options_list'])) {
				$options_list_value          = '';
				$options_list_label          = null;
				$options_list_label_native   = null;
				$options_list_label_selector = null;
				$options_list_order_by       = 'default';

				if (is_array($args['options_list']) && isset($args['options_list']['value']) && is_string($args['options_list']['value'])) {
					$options_list_value          = $args['options_list']['value'];
					$options_list_label          = (isset($args['options_list']['label']) && is_string($args['options_list']['label'])) ? $args['options_list']['label'] : null ;
					$options_list_label_native   = (isset($args['options_list']['label_native']) && is_string($args['options_list']['label_native'])) ? $args['options_list']['label_native'] : null ;
					$options_list_label_selector = (isset($args['options_list']['label_selector']) && is_string($args['options_list']['label_selector'])) ? $args['options_list']['label_selector'] : null ;
					$options_list_order_by       = (isset($args['options_list']['order_by']) && is_string($args['options_list']['order_by'])) ? $args['options_list']['order_by'] : 'default' ;
				} else if (is_string($args['options_list']) && !empty($args['options_list'])) {
					$options_list_value = $args['options_list'];
				}

				$options = scf_get_options_list($options_list_value, $options_list_label, $options_list_label_native, $options_list_label_selector, $options_list_order_by);
			}

			/* Récupère la/les valeur(s) de la radio */
			$select_values = array('');
			if (isset($args['value'])) {
				if (is_array($args['value'])) {
					$select_values = array_map('esc_attr', array_map('strval', $args['value']));
				} else {
					$select_values = array(esc_attr(strval($args['value'])));
				}
			}

			if (isset($args['options']) && is_array($args['options'])) {
				/* Fusionne les options fournies avec celles de la liste */
				$options = array_merge($options, $args['options']);
			}

			if (isset($options) && is_array($options)) {

				$inputs_args = (isset($args['attributes']) && is_array($args['attributes'])) ? $args['attributes'] : array() ;

				$inputs_args['type'] = 'radio';
				if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $inputs_args['name'] = $args['name'];

				$inputs_args = apply_filters('scf_input_args', $inputs_args, $args);

				/* Récupère une seule valeur */
				$select_values_values = array_values($select_values);
				$select_value = array_shift($select_values_values);

				if (isset($args['placeholder']) && !empty($args['placeholder'])) {
					$this->displayPlaceholder($args['placeholder'], $inputs_args, $select_value);
				}

				$i = 0;

				foreach ($options as $option) {
					if (!isset($option['value']) && !isset($option['label'])) continue;

					$i++;

					$option_value = (isset($option['value'])) ? esc_attr(strval($option['value'])) : esc_attr(strval($option['label'])) ;
					$option_label = (isset($option['label'])) ? strval($option['label']) : strval($option['value']) ;

					$input_args = (isset($option['attributes']) && is_array($option['attributes'])) ? $option['attributes'] : array() ;
					$input_args = array_merge($input_args, $inputs_args);

					if (isset($option['readonly']) && $option['readonly']) $input_args['readonly'] = 'readonly';
					if (isset($option['disabled']) && $option['disabled'] && !in_array($args['disabled'], array('on_pointer', 'on_touchable'))) $input_args['disabled'] = 'disabled';
					$input_args['value'] = $option_value;

					$input_args = apply_filters('scf_radio_input_args', $input_args, $option, $args);

					$item_classes_array = array('scf-radio-item');
					if ((!isset($args['placeholder']) || empty($args['placeholder'])) && $i==1) { $item_classes_array[] = 'scf-radio-first'; }
					if ($i>=count($options)) $item_classes_array[] = 'scf-radio-last';
					if (isset($option['required']) && $option['required']) $item_classes_array[] = 'scf-required';
					if (isset($option['error']) && $option['error']) $item_classes_array[] = 'scf-error';

					$item_classes_array = apply_filters('scf_radio_item_classes', $item_classes_array, $option, $args);
					$item_classes = implode(' ', $item_classes_array);

					$this->displayItem(
						$option_label,
						$item_classes,
						$input_args,
						($option_value === $select_value),
						boolval(isset($option['required']) && $option['required'])
					);
				}
			}

		}

		/**
		 * Affiche le code HTML du placeholder
		 *
		 * @param string $label    Label du placeholder
		 * @param array  $args     Arguments du placeholder
		 * @param array  $selected Valeurs du champ
		 * @return void
		 */
		public function displayPlaceholder($label, $args = array(), $selected = '') {
			?><label class="scf-radio scf-radio-placeholder" tabindex="0">
				<input <?php
					foreach ($args as $key => $value) { echo esc_attr($key) . '="' . esc_attr($value) . '" '; }
					checked($selected, '', true);
				?>/>
				<span class="scf-radio-label"><?php echo esc_html($label); ?></span>
			</label><?php
		}

		/**
		 * Affiche le code HTML d'une checkbox
		 *
		 * @param string $label    Label du placeholder
		 * @param array  $args     Arguments du placeholder
		 * @param array  $selected Valeurs du champ
		 * @return void
		 */
		public function displayItem($label, $classes = '', $args = array(), $selected = false, $required = false) {
			?><label class="<?php echo esc_attr($classes); ?>" tabindex="0">
				<input <?php
					foreach ($args as $key => $val) { echo esc_attr($key) . '="' . esc_attr($val) . '" '; }
					checked($selected, true, true);
				?>/>
				<span class="scf-radio-label"><?php
					echo $label; // HTML possible (no escaping)
					if ($required) {
						?><span class="scf-symbol-required"></span><?php
					}
				?></span>
			</label><?php
		}
	}

	new SCFRadio();
}
