<?php

declare(strict_types=1);

namespace SCF\Fields;

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Fields\SCFTel')) {

	/**
	 * Classe du champ Tel
	 */
	class SCFTel {

		/**
		 * Constructeur de la classe
		 * Accroche les hooks
		 */
		function __construct() {
			add_filter( 'scf_default_args', array($this, 'addDefaultArgs') );
			add_filter( 'scf_types_availables', array($this, 'init') );
			add_filter( 'scf_types_with_group_block', array($this, 'groupLabel') );
			add_action( 'scf_before_field_tel', array($this, 'wrapScfInput') );
			add_action( 'scf_after_field_tel', array($this, 'wrapCloseScfInput') );
			add_filter( 'scf_wrapper_classes', array($this, 'addWrapperClasses'), 10, 2 );
			add_action( 'scf_enqueue_scripts_tel', array($this, 'enqueueScripts') );
			add_action( 'scf_field_tel', array($this, 'displayHtml') );
			add_action( 'scf_field_tel', array($this, 'displayValidation'), 20 );
			add_action( 'plugins_loaded', array($this, 'formatTelI18nPost') );
		}

		/**
		 * Ajoute les arguments par défaut nécessaires au type Tel
		 * Filtre scf_default_args
		 *
		 * @param array $args Arguments par défaut
		 * @return array Arguments modifiés
		 */
		public function addDefaultArgs($args) {
			$args['tel_country_choice'] = true;

			/* Pays par défaut */
			$default_country = apply_filters('scf_tel_country_default', 'fr');

			/* Liste des pays disponibles */
			$countries = scf_get_options_list('cca2');
			$countries = array_map(function($country) {
				return strtoupper($country['value']);
			}, $countries);

			/* Récupère l'IP du visiteur */
			if (isset($_SERVER['HTTP_CLIENT_IP'])) {
				$ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
			} else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
			} else {
				$ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
			}

			/* Récupère le pays selon l'IP */
			if (function_exists('\geoip_country_code_by_name') && rest_is_ip_address($ip)) {
				$country = \geoip_country_code_by_name($ip);
			} else if (isset($_SERVER['GEOIP_COUNTRY_CODE'])) {
				$country = sanitize_text_field($_SERVER['GEOIP_COUNTRY_CODE']);
			} else {
				$country = false;
			}

			if ($country && in_array(strtoupper($country), $countries)) {
				$args['tel_country_default'] = strtoupper($country);
			} else {

				$locale = false;

				/* Récupère la langue de préférence de l'utilisateur */
				if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
					$lang = sanitize_text_field($_SERVER['HTTP_ACCEPT_LANGUAGE']);
					$locale = locale_accept_from_http($lang);
				}

				/* Récupère la langue de préférence selon WPML, puis Polylang, puis Wordpress */
				if (!$locale) {
					$locale = get_locale();
					if (function_exists('pll_current_language')) {
						$locale = pll_current_language('locale');
					} else if (has_filter('wpml_current_language')) {
						$locale = apply_filters( 'wpml_current_language', $locale );
					}
				}

				/* Détermine le pays selon la langue */
				if ($locale && is_string($locale)) {
					$country = locale_get_region($locale);
					if (isset($country) && in_array(strtoupper($country), $countries)) {
						$country = strtoupper($country);
					} else {
						$country = $default_country;
					}
				} else {
					$country = $default_country;
				}
			}

			$args['tel_country_default'] = strtoupper($country);

			return $args;
		}

		/**
		 * Initialise le type Tel
		 * Filtre scf_types_availables
		 *
		 * @param string[] $types Listes des types disponibles
		 * @return string[] Types disponibles modifiés
		 */
		public function init($types) {
			$types[] = 'tel';
			return $types;
		}

		/**
		 * Marque le type Tel comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function groupLabel($types) {
			$types[] = 'tel';
			return $types;
		}

		/**
		 * Entoure le champ tel d'une balise .scf-input (ouvre la balise)
		 * Le champ tel est un input simple donc doit être entouré d'un .scf-input, mais doit être de type groupe puisque plusieurs champs à l'intérieur
		 *
		 * @return void
		 */
		public function wrapScfInput() {
			?><span class="scf-input"><?php
		}

		/**
		 * Entoure le champ tel d'une balise .scf-input (ferme la balise)
		 *
		 * @return void
		 */
		public function wrapCloseScfInput() {
			?></span><?php
		}

		/**
		 * Ajoute la classe scf-tel-no-country-choice au groupe
		 * Filtre scf_wrapper_classes
		 *
		 * @param string[] $classes Classes du groupe
		 * @param string[] $args Paramètres du champ
		 * @return string[] Classes modifiées
		 */
		public function addWrapperClasses($classes, $args) {
			if ($args['type']=='tel' && (!isset($args['tel_country_choice']) || !$args['tel_country_choice'])) $classes[] = 'scf-tel-no-country-choice';
			return $classes;
		}

		/**
		 * Enregistre le script de libphonenumber
		 * Action scf_enqueue_scripts_{$type}
		 *
		 * @param void
		 * @return void
		 */
		public function enqueueScripts() {
			wp_enqueue_script('scf-tel');
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

			$input_args['type'] = 'tel';
			if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $input_args['name'] = $args['name'];
			$input_args['value'] = (isset($args['value'])) ? strval($args['value']) : '' ;
			$input_args['placeholder'] = (isset($args['placeholder'])) ? strval($args['placeholder']) : '' ;
			$input_args['autocomplete'] = (isset($args['autocomplete'])) ? strval($args['autocomplete']) : 'on' ;
			if (isset($args['readonly']) && $args['readonly']) $input_args['readonly'] = 'readonly';
			if (isset($args['focus']) && $args['focus']) $input_args['autofocus'] = 'autofocus';
			if (isset($args['disabled']) && $args['disabled'] && !in_array($args['disabled'], array('on_pointer', 'on_touchable'))) $input_args['disabled'] = 'disabled';
			if (!isset($input_args['inputmode'])) $input_args['inputmode'] = 'tel';
			if (!isset($input_args['enterkeyhint'])) $input_args['enterkeyhint'] = 'next';
			if (!isset($input_args['tabindex'])) $input_args['tabindex'] = 0;

			$input_args = apply_filters('scf_input_args', $input_args, $args);

			?><input <?php
				foreach ($input_args as $input_args_key => $input_args_value) { echo esc_attr($input_args_key) . '="' . esc_attr($input_args_value) . '" '; }
			?>/><?php

			$country_selector_args = array(
				'echo'         => false,
				'name'         => (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) ? $args['name'].'-tel-country-selector' : 'scf-anonymous-input-'.md5(rand(0, 1000)).'-tel-country-selector',
				'type'         => 'select',
				'required'     => true,
				'readonly'     => ((isset($args['readonly']) && $args['readonly']) || !isset($args['tel_country_choice']) || !$args['tel_country_choice']) ? true : false,
				'disabled'     => (isset($args['disabled']) && $args['disabled'] && !in_array($args['disabled'], array('on_pointer', 'on_touchable'))) ? true : false,
				'label'        => null,
				'value'        => (isset($args['tel_country_default']) && is_string($args['tel_country_default']) && !empty($args['tel_country_default'])) ? strtoupper($args['tel_country_default']) : '',
				'placeholder'  => __('Choisissez votre pays', 'simple-coherent-form'),
				'options_list' => array(
					'value'          =>'cca2',
					'label'          => '{flag} {name} <em>{tel}</em>',
					'label_native'   => '{tel}',
					'label_selector' => '{flag} {tel}',
					'order_by'       => 'name'
				),
				'attributes'   => array(
					'tabindex'       => '-1'
				),
				'tabindex'     => -1
			);
			$country_selector_args = apply_filters('scf_input_tel_country_selector_args', $country_selector_args, $args);

			/* Ajoute une class rtl sur les pays qui se lisent de droite à gauche */
			if (apply_filters('scf_input_tel_country_selector_rtl', false, $country_selector_args, $args)) {
				$countries_rtl = scf_get_options_list('cca2', '{rtl}');
				$add_rtl_to_country = function($item_classes_array, $option, $args) use ($countries_rtl) {
					$key = array_search($option['value'], array_column($countries_rtl, 'value'));
					if (is_int($key) && $countries_rtl[$key]['label']==='rtl') $item_classes_array[] = 'rtl';
					return $item_classes_array;
				};
				add_filter( 'scf_select_2_option_classes', $add_rtl_to_country, 10, 3 );
			}

			/* Change l'attribut data-length pour qu'il ne prenne en compte que l'indicatif téléphonique */
			$change_data_length = function($input_args, $option, $args) {
				if (isset($input_args['data-length']) && isset($option['data']['tel'])) {
					$input_args['data-length'] = strlen(esc_attr($option['data']['tel']));
				}
				return $input_args;
			};
			add_filter( 'scf_select_input_args', $change_data_length, 10, 3 );

			add_filter( 'scf_select_native_options', array($this, 'orderTelCountryNativeSelect'), 10, 2 );

			$country_selector_input = scf_input($country_selector_args);

			echo '<span class="scf-tel-country-selector">' . $country_selector_input . '</span>';

			/* Supprime le filtre pour qu'il ne s'applique que sur le selecteur de pays dans le champ tel, pas sur les autres champs */
			if (apply_filters('scf_input_tel_country_selector_rtl', false, $country_selector_args, $args)) {
				remove_filter( 'scf_select_2_option_classes', $add_rtl_to_country, 10 );
			}

			/* Supprime le filtre pour qu'il ne s'applique que sur le selecteur de pays dans le champ tel, pas sur les autres champs */
			remove_filter( 'scf_select_input_args', $change_data_length, 10 );

			if (isset($args['show_validation']) && $args['show_validation']) { ?><span class="scf-valid"></span><?php }
		}

		/**
		 * Réordonne les options du select natif en fonction de l'indicatif téléphonique et non pas du nom du pays
		 * Filter scf_select_native_options
		 *
		 * @param array $options Liste des options du select natif
		 * @param array $args    Paramètres du champ
		 * @return array Liste des options du select natif ordonnées par indicatif téléphonique
		 */
		public function orderTelCountryNativeSelect($options, $args) {
			remove_filter( 'scf_select_native_options', array($this, 'orderTelCountryNativeSelect') );

			usort($options, function($a, $b) {
				if (!isset($a['value']) && !isset($a['label'])) return 0;
				if (!isset($b['value']) && !isset($b['label'])) return 0;

				$option_a_label = (isset($a['label'])) ? esc_attr(strval($a['label'])) : esc_attr(strval($a['value'])) ;
				$option_a_label_native = (isset($a['label_native'])) ? esc_attr(strval($a['label_native'])) : $option_a_label ;

				$option_b_label = (isset($b['label'])) ? esc_attr(strval($b['label'])) : esc_attr(strval($b['value'])) ;
				$option_b_label_native = (isset($b['label_native'])) ? esc_attr(strval($b['label_native'])) : $option_b_label ;

				return strcmp($option_a_label_native, $option_b_label_native);
			} );

			return $options;
		}

		/**
		 * Format un numéro de téléphone selon le pays et le numéro
		 *
		 * @static
		 * @param string $country Code du pays à deux lettres
		 * @param string $tel     Numéro de téléphone
		 * @param string $format  Format dans lequel renvoyé le numéro (par défaut : indicatif téléphonique + numéro)
		 * @return string Numéro de téléphone formaté
		 */
		public static function formatTelI18n($country, $tel, $format = '{tel}%s') {
			/* Récupère la liste des pays avec leur indicatif téléphonique et leur code CCA2 */
			$countries = array_values(scf_get_options_list((substr($country, 0, 1)=='+' ? 'tel' : 'cca2'), $format));

			/* Récupère le format selon le pays */
			$key = array_search($country, array_column($countries, 'value'));
			if (isset($countries[$key])) {
				$_format = $countries[$key]['label'];
			} else {
				$_format = $format;
			}

			/* Remplace le premier remplaceur dans le format par le numéro */
			return apply_filters( 'scf_format_tel', sprintf($_format, $tel), $country, $tel, $format);
		}

		/**
		 * Retrouve les champs téléphone au sein des paramètres POST et les formattent correctement puis les remplace
		 *
		 * @static
		 * @return void
		 */
		public static function formatTelI18nPost() {
			if (isset($_POST) && is_array($_POST) && count($_POST)>0 && !is_admin() && apply_filters('scf_tel_format_post_values', true)) {
				foreach ($_POST as $key => $value) {
					if (substr(strval($key), -21) == '-tel-country-selector' && isset($_POST[substr(strval($key), 0, -21)])) {
						$country = $value;
						$tel = sanitize_text_field($_POST[substr(strval($key), 0, -21)]);
						$format = apply_filters( 'scf_format_post_tel', '{tel}%s' );
						$_POST[substr(strval($key), 0, -21)] = self::formatTelI18n($country, $tel, $format);
						unset($_POST[$key]);
					}
				}
			}
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

	new SCFTel();
}
