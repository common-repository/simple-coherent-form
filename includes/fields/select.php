<?php

declare(strict_types=1);

namespace SCF\Fields;

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Fields\SCFSelect')) {

	/**
	 * Classe du champ Select
	 */
	class SCFSelect {

		/**
		 * Constructeur de la classe
		 * Accroche les hooks
		 */
		function __construct() {
			add_filter( 'scf_default_args', array($this, 'addDefaultArgs') );
			add_filter( 'scf_types_availables', array($this, 'init') );
			add_filter( 'scf_types_with_group_block', array($this, 'groupLabel') );
			add_filter( 'scf_wrapper_classes', array($this, 'addWrapperClasses'), 10, 2 );
			add_action( 'scf_field_select', array($this, 'displayHtml') );
		}

		/**
		 * Ajoute les arguments par défaut nécessaires au type Select
		 * Filtre scf_default_args
		 *
		 * @param array $args Arguments par défaut
		 * @return array Arguments modifiés
		 */
		public function addDefaultArgs($args) {
			$args['options_list'] = null;
			$args['options'] = array();
			$args['multiple'] = false;
			$args['tabindex'] = null;
			return $args;
		}

		/**
		 * Initialise le type Select
		 * Filtre scf_types_availables
		 *
		 * @param string[] $types Listes des types disponibles
		 * @return string[] Types disponibles modifiés
		 */
		public function init($types) {
			$types[] = 'select';
			return $types;
		}

		/**
		 * Marque le type Select comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function groupLabel($types) {
			$types[] = 'select';
			return $types;
		}

		/**
		 * Ajoute la classe scf-select-multiple au groupe
		 * Filtre scf_wrapper_classes
		 *
		 * @param string[] $classes Classes du groupe
		 * @param string[] $args Paramètres du champ
		 * @return string[] Classes modifiées
		 */
		public function addWrapperClasses($classes, $args) {
			if ($args['type']=='select' && isset($args['multiple']) && $args['multiple']) $classes[] = 'scf-select-multiple';
			return $classes;
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

			if (isset($args['options']) && is_array($args['options'])) {
				/* Fusionne les options fournies avec celles de la liste */
				$options = array_merge($options, $args['options']);
			}

			/* ID du select */
			$select_name = (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) ? strval($args['name']) : 'anonymous' ;
			$select_id   = 'scf-select-' . strval( crc32( esc_attr(strval($args['type'])) . '-' . esc_attr($select_name) . '-' . \SCF\Front\SCFFront::$ID ) );

			/* Style des options */
			$this->displayStyle($args, $select_id, $options);

			/* Checkbox pour ouverture du select */
			if (!isset($args['readonly']) || !$args['readonly']) {
				?><input type="checkbox" class="scf-select-2-opener" id="<?php echo esc_attr($select_id); ?>" tabindex="-1" hidden="hidden" data-name="scf-select-open"/><?php
			}

			$placeholder_args = (isset($args['attributes']) && is_array($args['attributes'])) ? $args['attributes'] : array() ;

			$placeholder_args['type']   = (isset($args['multiple']) && $args['multiple']) ? 'checkbox' : 'radio' ;
			if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $placeholder_args['name'] = $args['name'];
			$placeholder_args['hidden'] = 'hidden';
			$placeholder_args['id']     = esc_attr($select_id) . '-item-placeholder';
			$placeholder_args['class']  = 'scf-select-2-item-placeholder';
			$placeholder_args['value']  = '';
			$placeholder_args = apply_filters('scf_select_placeholder_input_args', $placeholder_args, $args);

			/* Récupère la/les valeur(s) du select */
			$select_values = array('');
			if (isset($args['value'])) {
				if (is_array($args['value'])) {
					$select_values = array_map('esc_attr', array_map('strval', $args['value']));
				} else {
					$select_values = array(esc_attr(strval($args['value'])));
				}
			}
			$select_values = array_filter($select_values);

			$this->displayPlaceholderInput($placeholder_args, $select_values);

			$activedescendant = in_array('', $select_values) ? $select_id . '-item-placeholder' : null;

			if (isset($options) && is_array($options)) {

				$inputs_args = (isset($args['attributes']) && is_array($args['attributes'])) ? $args['attributes'] : array() ;
				$inputs_args['type']   = (isset($args['multiple']) && $args['multiple']) ? 'checkbox' : 'radio' ;
				if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $inputs_args['name'] = $args['name'];
				$inputs_args['hidden'] = 'hidden';
				$inputs_args['class']  = 'scf-select-2-item-input';

				$inputs_args = apply_filters('scf_select_inputs_args', $inputs_args, $args);

				$i = 0;

				foreach ($options as $option) {
					if (!isset($option['value']) && !isset($option['label'])) continue;

					$i++;

					$option_value = (isset($option['value'])) ? esc_attr(strval($option['value'])) : esc_attr(strval($option['label'])) ;
					$option_label = (isset($option['label'])) ? esc_attr(strval($option['label'])) : esc_attr(strval($option['value'])) ;
					$option_label_selector = (isset($option['label_selector'])) ? esc_attr(strval($option['label_selector'])) : $option_label ;

					if (isset($args['readonly']) && $args['readonly'] && !in_array($option_value, $select_values)) continue;

					$input_args = (isset($option['attributes']) && is_array($option['attributes'])) ? $option['attributes'] : array() ;
					$input_args = array_merge($input_args, $inputs_args);

					if (isset($option['readonly']) && $option['readonly']) $input_args['readonly'] = 'readonly';
					if (isset($option['disabled']) && $option['disabled'] && !in_array($args['disabled'], array('on_pointer', 'on_touchable'))) $input_args['disabled'] = 'disabled';
					$input_args['id'] = esc_attr($select_id) . '-item-' . esc_attr($i);
					$input_args['data-length'] = esc_attr(strlen($option_label_selector));
					$input_args['value'] = $option_value;

					$input_args = apply_filters('scf_select_input_args', $input_args, $option, $args);

					?><input <?php
						foreach ($input_args as $input_args_key => $input_args_value) { echo esc_attr($input_args_key) . '="' . esc_attr($input_args_value) . '" '; }
						checked(in_array($option_value, $select_values), true, true);
					?>/><?php

					/* Attribut aria-activedescendant */
					if (!isset($activedescendant) && in_array($option_value, $select_values)) {
						$activedescendant = esc_attr($select_id) . '-option-item-' . esc_attr($i);
					}
				}
			}

			/* Select 2 */
			$this->displaySelect2($args, $select_id, $activedescendant, $options, $select_values);

			/* Select natif */
			$this->displaySelectNative($args, $options, $select_values);

		}

		/**
		 * Affiche le code CSS du select2
		 *
		 * @param array  $args      Arguments du champ
		 * @param string $select_id ID du select
		 * @return void
		 */
		public function displayStyle($args, $select_id, $options) {
			
			?><style type="text/css"><?php

				/* Nombre d'options dans le select */
				$nb_options_on_placeholder = 0;
				$nb_options = 0;

				if (isset($options) && is_array($options)) {

					for ($i=1; $i <= count($options); $i++) { 
						$option = $options[$i-1];

						if (!isset($option['value']) && !isset($option['label'])) continue;

						$nb_options++;
						$nb_options_on_placeholder++;

						/* Affichage de l'option sélectionnée */
						echo 'input#' . esc_attr($select_id) . '-item-' . esc_attr($i) . ':checked ~ .scf-select-2 > .scf-select-2-selector > span#' . esc_attr($select_id) . '-item-selector-' . esc_attr($i) . ' { display: inline-block!important; }';
						
						/* En cas de select multiple, limite le nombre d'options affichées dans le label */
						if (isset($args['multiple']) && $args['multiple']) {
							$cuts = intval(apply_filters('scf_select_multiple_number_cut', 2, $args));
							echo str_repeat('input.scf-select-2-item-input:checked ~ ', $cuts) . 'input#' . esc_attr($select_id) . '-item-' . esc_attr($i) . ':checked ~ .scf-select-2 > .scf-select-2-selector > span#' . esc_attr($select_id) . '-item-selector-' . esc_attr($i) . ' { display: none!important; }';
						}

						/* Style de l'option selectionnée dans la liste des options */
						$colors = \SCF\Front\SCFFront::getColors();
						$color = (isset($colors['select_option_color_check'])) ? $colors['select_option_color_check'] : '#0F292C' ;
						echo 'input#' . esc_attr($select_id) . '-item-' . esc_attr($i) . ':checked ~ .scf-select-2 > .scf-select-2-options > label[for="' . esc_attr($select_id) . '-item-' . esc_attr($i) . '"] { color: ' . $color .'; }';

						echo 'input#' . esc_attr($select_id) . '-item-' . esc_attr($i) . ':checked ~ .scf-select-2 > .scf-select-2-options > label[for="' . esc_attr($select_id) . '-item-' . esc_attr($i) . '"]:before { height: 7px; }';

						echo 'input#' . esc_attr($select_id) . '-item-' . esc_attr($i) . ':checked ~ .scf-select-2 > .scf-select-2-options > label[for="' . esc_attr($select_id) . '-item-' . esc_attr($i) . '"]:after { height: 11px; }';

						do_action( 'scf_select_style_option', $i, $select_id, $args);
					}
					
					/* En cas de select multiple, limite le nombre d'options affichées dans le label et affiche une mention "et X autres" */
					if (isset($args['multiple']) && $args['multiple']) {
						$cuts = intval(apply_filters('scf_select_multiple_number_cut', 2, $args));
						echo str_repeat('input.scf-select-2-item-input:checked ~ ', $cuts) . 'input.scf-select-2-item-input:checked ~ .scf-select-2 > .scf-select-2-selector > span#' . esc_attr($select_id) . '-item-more { display: inline-block!important; }';

						if (count($options)>$cuts) {
							for ($i=$cuts+1; $i <= count($options); $i++) {
								echo str_repeat('input.scf-select-2-item-input:checked ~ ', $cuts) . str_repeat('input.scf-select-2-item-input:checked ~ ', $i-$cuts) . '.scf-select-2 > .scf-select-2-selector > span#' . esc_attr($select_id) . '-item-more span.' . esc_attr($select_id) . '>-item-more-nb:before { content: "' . esc_html($i-$cuts) . '"; }';

								/* Enlève le s du pluriel */
								if (($i-$cuts)>1) {
									echo str_repeat('input.scf-select-2-item-input:checked ~ ', $cuts) . str_repeat('input.scf-select-2-item-input:checked ~ ', $i-$cuts) . '.scf-select-2 > .scf-select-2-selector > span#' . esc_attr($select_id) . '-item-more > span#' . esc_attr($select_id) . '-item-more-plural { display: inline-block!important; }';

									echo str_repeat('input.scf-select-2-item-input:checked ~ ', $cuts) . str_repeat('input.scf-select-2-item-input:checked ~ ', $i-$cuts) . '.scf-select-2 > .scf-select-2-selector > span#' . esc_attr($select_id) . '-item-more > span#' . esc_attr($select_id) . '-item-more-singular { display: none!important; }';
								}
							}
						}
					}
				}

				/* Si le champ n'est pas requis et qu'on est dans un select non multiple, ajoute le placeholder au nombre d'options affichées */
				if ((!isset($args['required']) || !$args['required']) && (!isset($args['multiple']) || !$args['multiple'])) {
					$nb_options++;
				}

				/* A l'ouverture du select, donne la bonne hauteur en fonction du nombre d'options (min 1) */
				if ($nb_options<=0) $nb_options = 1;
				if ($nb_options_on_placeholder<=0) $nb_options_on_placeholder = 1;

				echo 'input.scf-select-2-opener#' . esc_attr($select_id) . ':checked ~ .scf-select-2 > .scf-select-2-options { height: ' . esc_html(43 * $nb_options) . 'px; }';
				echo 'input.scf-select-2-opener#' . esc_attr($select_id) . ':checked ~ .scf-select-2-item-placeholder:checked ~ .scf-select-2 > .scf-select-2-options { height: ' . esc_html(43 * $nb_options_on_placeholder) . 'px; }';

			?></style><?php

		}

		/**
		 * Affiche le code HTML du select natif
		 *
		 * @param array    $args     Arguments du champ
		 * @param array    $options  Options du select
		 * @param string[] $selected Options sélectionnées
		 * @return void
		 */
		public function displaySelectNative($args, $options = array(), $selected = array()) {
			if (!is_array($options)) $options = array($options);
			if (!is_array($selected)) $selected = array($selected);
			if (!is_array($args)) $args = array($args);

			/* Classes du select natif */
			$select_native_classes_array = array('scf-select-native');
			
			if (isset($args['multiple']) && $args['multiple']) $select_native_classes_array[] = 'scf-select-native-multiple';

			$select_native_classes_array = apply_filters('scf_select_native_classes', $select_native_classes_array, $args);
			$select_native_classes = implode(' ', $select_native_classes_array);

			/* Arguments du select natif */
			$select_native_args = (isset($args['attributes']) && is_array($args['attributes'])) ? $args['attributes'] : array() ;

			if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $select_native_args['name'] = $args['name'];
			$select_native_args['autocomplete'] = (isset($args['autocomplete'])) ? strval($args['autocomplete']) : 'on' ;
			$select_native_args['class'] = $select_native_classes;
			if (isset($args['multiple']) && $args['multiple']) $select_native_args['multiple'] = 'multiple';
			if (isset($args['disabled']) && $args['disabled'] && !in_array($args['disabled'], array('on_pointer', 'on_touchable'))) $select_native_args['disabled'] = 'disabled';

			$select_native_args = apply_filters('scf_select_native_args', $select_native_args, $args);

			?><select <?php
				foreach ($select_native_args as $select_native_args_key => $select_native_args_value) { echo esc_attr($select_native_args_key) . '="' . esc_attr($select_native_args_value) . '" '; }
			?>><?php
			
			/* Filtrage des options du select natif */
			$options = apply_filters( 'scf_select_native_options', $options, $args);

			if (isset($options) && is_array($options)) {

				/* Si il y a un argument placeholder, met son nom comme label de choix vide, sinon -- */
				if (isset($args['placeholder']) && !empty($args['placeholder'])) {
					$placeholder_label = esc_html(strval($args['placeholder']));
				} else {
					$placeholder_label = apply_filters(
						'scf_select_native_option_placeholder_default',
						apply_filters(
							'scf_select_2_option_placeholder_default',
							'--',
							$args
						),
						$args
					);
				}

				/* Classes du placeholder */
				$item_classes_array = apply_filters(
					'scf_select_native_option_placeholder_classes',
					apply_filters(
						'scf_select_2_option_placeholder_classes',
						array('scf-select-2-option', 'scf-select-2-option-placeholder'),
						$placeholder_label,
						$args
					),
					$placeholder_label,
					$args
				);
				$item_classes = implode(' ', $item_classes_array);

				/* Argument du placeholder */
				$placeholder_args = array('value'=>'', 'class'=>$item_classes);
				if (!isset($selected) || !is_array($selected) || count($selected)<=0) $placeholder_args['selected'] = 'selected';

				/* Si une option est requise ou que c'est un select multiple, empeche le choix vide d'être selectionné */
				if ((isset($args['required']) && $args['required']) || (isset($args['multiple']) && $args['multiple'])) {
					$placeholder_args['disabled'] = 'disabled';
				}

				$placeholder_args = apply_filters('scf_select_native_placeholder_args', $placeholder_args, $args);

				/* Affichage du placeholder */
				?><option <?php
					foreach ($placeholder_args as $placeholder_args_key => $placeholder_args_value) { echo esc_attr($placeholder_args_key) . '="' . esc_attr($placeholder_args_value) . '" '; }
				?>><?php echo esc_html($placeholder_label); ?></option><?php

				for ($i=1; $i <= count($options); $i++) { 
					$option = $options[$i-1];

					if (!isset($option['value']) && !isset($option['label'])) continue;

					$option_label = (isset($option['label'])) ? strval($option['label']) : strval($option['value']) ;
					$option_value = (isset($option['value'])) ? strval($option['value']) : strval($option['label']) ;
					$option_label_native = (isset($option['label_native'])) ? strval($option['label_native']) : $option_label ;

					$item_classes_array = array('scf-select-2-option');
					if (isset($option['required']) && $option['required']) $item_classes_array[] = 'scf-required';
					if (isset($option['error']) && $option['error']) $item_classes_array[] = 'scf-error';

					$item_classes_array = apply_filters('scf_select_2_option_classes', $item_classes_array, $option, $args);
					$item_classes = implode(' ', $item_classes_array);

					$option_required = (isset($option['required']) && $option['required']) ? '*' : '';

					?><option value="<?php echo esc_attr($option_value); ?>" <?php selected(in_array($option_value, $selected), true, true); ?> class="<?php echo esc_attr($item_classes); ?>" data-length="<?php echo esc_attr(strlen($option_label_native . $option_required)); ?>"><?php echo esc_html($option_label_native . $option_required); ?></option><?php
				}
			}

			?></select><?php
		}

		/**
		 * Affiche le code HTML du placeholder
		 *
		 * @param array  $args     Arguments du placeholder
		 * @param array  $selected Valeurs du champ
		 * @return void
		 */
		public function displayPlaceholderInput($args = array(), $selected = array()) {
			?><input <?php
				foreach ($args as $key => $value) { echo esc_attr($key) . '="' . esc_attr($value) . '" '; }
				checked(in_array('', $selected), true, true);
			?>/><?php
		}

		/**
		 * Affiche le code HTML du select 2
		 *
		 * @param array  $args      Arguments du champ
		 * @param string $select_id ID du select
		 * @param string $active    ID de l'option selectionné
		 * @param array  $options   Options du select
		 * @param array  $selected  Option(s) selectionnée(s)
		 * @return void
		 */
		public function displaySelect2($args, $select_id, $active, $options = array(), $selected = array()) {
			
			/* Classe select */
			$select_2_classes_array = array('scf-select-2');
			if (isset($args['multiple']) && $args['multiple']) $select_2_classes_array[] = 'scf-select-2-multiple';

			$select_2_classes_array = apply_filters('scf_select_2_classes', $select_2_classes_array, $args);
			$select_2_classes = implode(' ', $select_2_classes_array);

			/* Select 2 : Groupe */
			echo '<div class="' . esc_attr($select_2_classes) . '">';

				$tabindex = isset($args['tabindex']) ? intval($args['tabindex']) : ((!isset($args['readonly']) || !$args['readonly']) ? 0 : null) ;
				$tabindex = apply_filters('scf_tabindex', $tabindex, $args );

				$label = apply_filters('scf_label', $args['label'], $args );

				$selector_args = array(
					'class'					=> 'scf-select-2-selector',
					'role'					=> 'combobox',
					'aria-expanded'			=> 'false',
					'aria-activedescendant'	=> (isset($active) ? esc_attr($active) : ''),
					'aria-haspopup'			=> 'listbox',
					'aria-owns'				=> esc_attr($select_id) . '-list-options',
				);
				if (isset($args['label'])) $selector_args['aria-label'] = esc_attr(apply_filters('scf_label_screen_reader', true, $args) ? apply_filters('scf_label_screen_reader_text', __('Champ ', 'simple-coherent-form'), $args) : '') . esc_attr($label);
				if (isset($tabindex)) $selector_args['tabindex'] = esc_attr($tabindex);

				$selector_args = apply_filters('scf_select_2_selector_args', $selector_args, $args, $select_id, $active, $options, $selected );

				/* Select 2 : Spoiler affichant la valeur selectionnée */
				?><span <?php
					foreach ($selector_args as $selector_args_key => $selector_args_value) { echo esc_attr($selector_args_key) . '="' . esc_attr($selector_args_value) . '" '; }
				?>><?php

					/* Affiche le placeholder si présent */
					/* Si il y a un argument placeholder, met son nom comme label de choix vide, sinon -- */
					if (isset($args['placeholder']) && !empty($args['placeholder'])) {
						$placeholder_label = esc_html(strval($args['placeholder']));
					} else {
						$placeholder_label = apply_filters(
							'scf_select_2_option_placeholder_default',
							'--',
							$args
						);
					}

					$placeholder_label = apply_filters('scf_select_2_selector_placeholder', $placeholder_label, $args);
					if (isset($placeholder_label)) {
						echo '<span class="scf-select-2-item-selector-placeholder">' . esc_html(strval($placeholder_label)) .'&nbsp;</span>';
					}

					$nb_options = 0;

					/* Affiche chaque option */
					if (isset($options) && is_array($options)) {

						$i = 0;

						foreach ($options as $option) {
							if (!isset($option['value']) && !isset($option['label'])) continue;

							$nb_options++;

							if (isset($args['readonly']) && $args['readonly'] && !in_array($option_value, $selected)) continue;

							$i++;

							$option_value = (isset($option['value'])) ? strval($option['value']) : strval($option['label']) ;
							$option_label = (isset($option['label'])) ? strval($option['label']) : strval($option['value']) ;
							$option_label_selector = (isset($option['label_selector'])) ? strval($option['label_selector']) : strval($option_label) ;

							echo '<span id="' . esc_attr($select_id) . '-item-selector-' . esc_attr($i) . '" style="display:none">' . wp_kses_post($option_label_selector) . '</span>';
						}
					}

					/* Affiche la mention "ex X autres" si select multiple */
					if (isset($args['multiple']) && $args['multiple']) {
						echo '<span id="' . esc_attr($select_id) . '-item-more" class="scf-select-2-selector-more" style="display:none"><span id="' . esc_attr($select_id) . '-item-more-singular">' . sprintf(_n('et %s autre', 'et %s autres', 1, get_stylesheet()), '<span class="' . esc_attr($select_id) . '-item-more-nb"></span>') . '</span><span id="' . esc_attr($select_id) . '-item-more-plural" style="display:none">' . sprintf(_n('et %s autre', 'et %s autres', 2, get_stylesheet()), '<span class="' . esc_attr($select_id) . '-item-more-nb"></span>') . '</span></span>';
					}

					/* Si le champ n'est pas requis et qu'on est dans un select non multiple, ajoute le placeholder au nombre d'options affichées */
					if ((!isset($args['required']) || !$args['required']) && (!isset($args['multiple']) || !$args['multiple'])) {
						$nb_options++;
					}

					/* A l'ouverture du select, donne la bonne hauteur en fonction du nombre d'options (min 1) */
					if ($nb_options<=0) $nb_options = 1;

				echo '&nbsp;</span>';

				/* Select 2 : Liste des options */
				echo '<div class="scf-select-2-options" id="' . esc_attr($select_id) . '-list-options" role="listbox" aria-multiselectable="' . esc_attr((isset($args['multiple']) && $args['multiple']) ? 'true' : 'false') . '" data-height="' . esc_attr(43 * $nb_options) . '">';

					/* Affiche le placeholder si présent */
					if ((!isset($args['required']) || !$args['required']) && (!isset($args['multiple']) || !$args['multiple'])) {

						/* Si il y a un argument placeholder, met son nom comme label de choix vide, sinon -- */
						if (isset($args['placeholder']) && !empty($args['placeholder'])) {
							$placeholder_label = esc_html(strval($args['placeholder']));
						} else {
							$placeholder_label = apply_filters(
								'scf_select_2_option_placeholder_default',
								'--',
								$args
							);
						}

						$item_classes_array = array('scf-select-2-option', 'scf-select-2-option-placeholder');

						$item_classes_array = apply_filters('scf_select_2_option_placeholder_classes', $item_classes_array, $placeholder_label, $args);
						$item_classes = implode(' ', $item_classes_array);

						echo '<span id="' . esc_attr($select_id) . '-option-item-placeholder" class="' . esc_attr($item_classes) . '" ' . (isset($tabindex) ? 'tabindex="' . esc_attr($tabindex) . '"' : '') . ' role="option" aria-selected="' . esc_attr(in_array('', $selected) ? 'true' : 'false') . '"><span>' . esc_html($placeholder_label) . '</span></span>';
					}

					/* Affiche chaque option */
					if (isset($options) && is_array($options)) {

						$i = 0;

						foreach ($options as $option) {
							if (!isset($option['value']) && !isset($option['label'])) continue;

							if (isset($args['readonly']) && $args['readonly'] && !in_array($option_value, $selected)) continue;

							$i++;

							$option_value = (isset($option['value'])) ? esc_attr(strval($option['value'])) : esc_attr(strval($option['label'])) ;
							$option_label = (isset($option['label'])) ? strval($option['label']) : strval($option['value']) ;

							$item_classes_array = array('scf-select-2-option');
							if (isset($option['required']) && $option['required']) $item_classes_array[] = 'scf-required';
							if (isset($option['error']) && $option['error']) $item_classes_array[] = 'scf-error';

							$item_classes_array = apply_filters('scf_select_2_option_classes', $item_classes_array, $option, $args);
							$item_classes = implode(' ', $item_classes_array);

							echo '<span data-for="' . esc_attr($select_id) . '-item-' . esc_attr($i) . '" id="' . esc_attr($select_id) . '-option-item-' . esc_attr($i) . '" class="' . esc_attr($item_classes) . '" ' . (isset($tabindex) ? 'tabindex="' . esc_attr($tabindex) . '"' : '') . ' style="display:none" role="option" aria-selected="' . esc_attr(in_array($option_value, $selected) ? 'true' : 'false') . '"><span>' . wp_kses_post($option_label) . ((isset($option['required']) && $option['required']) ? '<span class="scf-symbol-required"></span>' : '') . '</span></span>';
						}
					} else {
						echo '<span class="scf-select-2-no-option">' . esc_html(apply_filters('scf_select_2_no_option', __('Aucune option n\'est proposée.', 'simple-coherent-form'), $args)) . '</span>';
					}

				echo '</div>';
			echo '</div>';
		}
	}

	new SCFSelect();
}
