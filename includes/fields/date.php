<?php

declare(strict_types=1);

namespace SCF\Fields;

use \Datetime;

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Fields\SCFDate')) {

	/**
	 * Classe du champ Date
	 */
	class SCFDate {

		/**
		 * Constructeur de la classe
		 * Accroche les hooks
		 */
		function __construct() {
			add_filter( 'scf_default_args', array($this, 'addDefaultArgs') );
			add_filter( 'scf_args', array($this, 'parseDatepickerArgs'), 10, 2 );
			add_filter( 'scf_types_availables', array($this, 'init') );
			add_filter( 'scf_types_with_group_label', array($this, 'groupLabel') );
			add_filter( 'scf_wrapper_classes', array($this, 'addWrapperClasses'), 10, 2 );
			add_action( 'scf_enqueue_scripts_date', array($this, 'enqueueScripts') );
			add_action( 'scf_field_date', array($this, 'displayHtml') );
			add_action( 'scf_field_date', array($this, 'displayValidation'), 20 );
		}

		/**
		 * Ajoute les arguments par défaut nécessaires au type Date
		 * Filtre scf_default_args
		 *
		 * @param array $args Arguments par défaut
		 * @return array Arguments modifiés
		 */
		public function addDefaultArgs($args) {
			$args['datepicker'] = array(
				'show_calendar'			=> true,
				'show_on_focus'			=> true,
				'editable'				=> true,
				'position'				=> 'auto',
				'format'				=> get_option('date_format', 'd/m/Y'),
				'min_date'				=> false,
				'max_date'				=> false,
				'first_day'				=> intval(get_option('start_of_week', '0')),
				'show_short_day_names'	=> true,
				'day_names'				=> array(
					__('Dimanche', 'simple-coherent-form'),
					__('Lundi', 'simple-coherent-form'),
					__('Mardi', 'simple-coherent-form'),
					__('Mercredi', 'simple-coherent-form'),
					__('Jeudi', 'simple-coherent-form'),
					__('Vendredi', 'simple-coherent-form'),
					__('Samedi', 'simple-coherent-form')
				),
				'short_day_names'		=> array(
					__('Dim.', 'simple-coherent-form'),
					__('Lun.', 'simple-coherent-form'),
					__('Mar.', 'simple-coherent-form'),
					__('Mer.', 'simple-coherent-form'),
					__('Jeu.', 'simple-coherent-form'),
					__('Ven.', 'simple-coherent-form'),
					__('Sam.', 'simple-coherent-form')
				),
				'month_names'			=> array(
					__('Janvier', 'simple-coherent-form'),
					__('Février', 'simple-coherent-form'),
					__('Mars', 'simple-coherent-form'),
					__('Avril', 'simple-coherent-form'),
					__('Mai', 'simple-coherent-form'),
					__('Juin', 'simple-coherent-form'),
					__('Juillet', 'simple-coherent-form'),
					__('Août', 'simple-coherent-form'),
					__('Septembre', 'simple-coherent-form'),
					__('Octobre', 'simple-coherent-form'),
					__('Novembre', 'simple-coherent-form'),
					__('Décembre', 'simple-coherent-form')
				),
				'disable_weekdays'		=> array(),
				'disable_days'			=> array(),
				'pikaday_options'		=> array()
			);
			return $args;
		}

		/**
		 * Parse les arguments par défaut du datepicker
		 * Filtre scf_args
		 *
		 * @param array $args     Paramètres du champ
		 * @param array $defaults Valeurs par défaut
		 * @return array Paramètres du champ avec les valeurs du datepicker complétées
		 */
		public function parseDatepickerArgs($args, $defaults) {
			if (isset($args['datepicker']) && is_array($args['datepicker']) && isset($defaults['datepicker']) && is_array($defaults['datepicker'])) {
				$args['datepicker'] = wp_parse_args( $args['datepicker'], $defaults['datepicker'] );
			} else if (isset($args['datepicker']) && $args['datepicker'] && isset($defaults['datepicker'])) {
				$args['datepicker'] = $defaults['datepicker'];
			}
			return $args;
		}

		/**
		 * Initialise le type Date
		 * Filtre scf_types_availables
		 *
		 * @param string[] $types Listes des types disponibles
		 * @return string[] Types disponibles modifiés
		 */
		public function init($types) {
			$types[] = 'date';
			return $types;
		}

		/**
		 * Marque le type Date comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function groupLabel($types) {
			$types[] = 'date';
			return $types;
		}

		/**
		 * Ajoute la classe scf-datepicker au groupe
		 * Filtre scf_wrapper_classes
		 *
		 * @param string[] $classes Classes du groupe
		 * @param string[] $args Paramètres du champ
		 * @return string[] Classes modifiées
		 */
		public function addWrapperClasses($classes, $args) {
			if ($args['type']=='date' && (isset($args['datepicker']) && (is_array($args['datepicker']) || $args['datepicker']))) $classes[] = 'scf-datepicker';
			return $classes;
		}

		/**
		 * Enregistre le script du datepicker
		 * Action scf_enqueue_scripts_{$type}
		 *
		 * @param void
		 * @return void
		 */
		public function enqueueScripts() {
			wp_enqueue_script('scf-pikaday');
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

			$input_args['type'] = 'date';
			if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $input_args['name'] = $args['name'];
			$input_args['value'] = (isset($args['value'])) ? strval($args['value']) : '' ;
			$input_args['placeholder'] = (isset($args['placeholder'])) ? strval($args['placeholder']) : '' ;
			$input_args['autocomplete'] = (isset($args['autocomplete'])) ? strval($args['autocomplete']) : 'on' ;
			if ((isset($args['readonly']) && $args['readonly']) || (isset($args['datepicker']['editable']) && !$args['datepicker']['editable'])) $input_args['readonly'] = 'readonly';
			if (isset($args['disabled']) && $args['disabled'] && !in_array($args['disabled'], array('on_pointer', 'on_touchable'))) $input_args['disabled'] = 'disabled';
			if (isset($args['format']) && !empty($args['format'])) $input_args['data-format'] = $args['format'];
			if (!isset($input_args['enterkeyhint'])) $input_args['enterkeyhint'] = 'next';
			if (!isset($input_args['tabindex'])) $input_args['tabindex'] = 0;
			if (isset($args['focus']) && $args['focus']) $input_args['autofocus'] = 'autofocus';
			
			$input_args = apply_filters('scf_input_args', $input_args, $args);
			
			/* ID du datepicker */
			$datepicker_name = (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) ? strval($args['name']) : 'anonymous' ;
			$datepicker_id = 'scf-datepicker-' . strval( crc32( esc_attr(strval($args['type'])) . '-' . esc_attr($datepicker_name) . '-' . \SCF\Front\SCFFront::$ID ) );
			$input_args['id'] = $datepicker_id;

			/* ID du wrapper */
			$wrapper_id = 'scf-group-input-' . strval( crc32( esc_attr(strval($args['type'])) . '-' . ((isset($args['name']) && is_string($args['name']) && !empty($args['name'])) ? esc_attr(strval($args['name'])) : 'anonymous') . '-' . \SCF\Front\SCFFront::$ID ) );
			$wrapper_id = apply_filters('scf_wrapper_id', $wrapper_id, $args);

			/* Paramètres par défaut du datepicker */
			$pikaday_args_json = json_encode(array());

			if (isset($args['datepicker']) && is_array($args['datepicker'])) {

				$datepicker_args = $args['datepicker'];

				/* Code JS à ne pas échapper */
				$js_codes = array(
					'arg_field'       => "document.getElementById('" . $datepicker_id . "')",
					'arg_on_select'   => (isset($args['format'])&&!empty($args['format'])) ? "function(){ let date = moment(this._o.trigger.value, this._o.format, true); if (date.isValid()) this.setDate(date.toDate(), true); scf_format_recheck_value(this._o.field); this._o.field.blur(); }" : "function(){ scf_format_recheck_value(this._o.field); this._o.field.blur(); }",
					'arg_before_open' => "function(){ scf_format_recheck_value(document.getElementById('" . $datepicker_id . "')); scf_date_recheck_value(document.getElementById('" . $datepicker_id . "')); }",
					'arg_container'   => "document.getElementById('" . $wrapper_id . "').getElementsByClassName('scf-input')[0]"
				);

				if (isset($datepicker_args['format'])) {
					$format = strval($datepicker_args['format']);
				} else {
					$format = 'Y-m-d';
				}

				$format_php = $format_moment = $format;

				/* Si le format est fournie au format PHP, le convertie au format Moment.js */
				if (apply_filters('scf_date_format_php', true, $args)) {
					$format_moment = strtr(
						$format,
						array(
							'A'=>'A',
							'a'=>'a',
							'B'=>'',
							'c'=>'YYYY-MM-DD[T]HH:mm:ssZ',
							'D'=>'ddd',
							'd'=>'DD',
							'e'=>'zz',
							'F'=>'MMMM',
							'G'=>'H',
							'g'=>'h',
							'H'=>'HH',
							'h'=>'hh',
							'I'=>'',
							'i'=>'mm',
							'j'=>'D',
							'L'=>'',
							'l'=>'dddd',
							'M'=>'MMM',
							'm'=>'MM',
							'N'=>'E',
							'n'=>'M',
							'O'=>'ZZ',
							'o'=>'YYYY',
							'P'=>'Z',
							'r'=>'ddd, DD MMM YYYY HH:mm:ss ZZ',
							'S'=>'o',
							's'=>'ss',
							'T'=>'z',
							't'=>'',
							'U'=>'X',
							'u'=>'SSSSSS',
							'v'=>'SSS',
							'W'=>'W',
							'w'=>'e',
							'Y'=>'YYYY',
							'y'=>'YY',
							'Z'=>'',
							'\\A'=>'[A]',
							'\\a'=>'[a]',
							'\\B'=>'[]',
							'\\c'=>'[YYYY-MM-DD[T]HH:mm:ssZ]',
							'\\D'=>'[ddd]',
							'\\d'=>'[DD]',
							'\\e'=>'[zz]',
							'\\F'=>'[MMMM]',
							'\\G'=>'[H]',
							'\\g'=>'[h]',
							'\\H'=>'[HH]',
							'\\h'=>'[hh]',
							'\\I'=>'[]',
							'\\i'=>'[mm]',
							'\\j'=>'[D]',
							'\\L'=>'[]',
							'\\l'=>'[dddd]',
							'\\M'=>'[MMM]',
							'\\m'=>'[MM]',
							'\\N'=>'[E]',
							'\\n'=>'[M]',
							'\\O'=>'[ZZ]',
							'\\o'=>'[YYYY]',
							'\\P'=>'[Z]',
							'\\r'=>'[ddd, DD MMM YYYY HH:mm:ss ZZ]',
							 '\\S'=>'[o]',
							'\\s'=>'[ss]',
							'\\T'=>'[z]',
							'\\t'=>'[]',
							'\\U'=>'[X]',
							'\\u'=>'[SSSSSS]',
							'\\v'=>'[SSS]',
							'\\W'=>'[W]',
							'\\w'=>'[e]',
							'\\Y'=>'[YYYY]',
							'\\y'=>'[YY]',
							'\\Z'=>'[]'
						)
					);
				}

				/* Paramètres de Pikaday */
				$pikaday_args = array(
					'field'                                      => '{{arg_field}}',
					'showDaysInNextAndPreviousMonths'            => true,
					'enableSelectionDaysInNextAndPreviousMonths' => false,
					'selectMonth'                                => false,
					'selectYear'                                 => false,
					'onSelect'                                   => '{{arg_on_select}}',
					'beforeOpen'                                 => '{{arg_before_open}}',
					'container'                                  => '{{arg_container}}',
					'bound'                                      => (!isset($datepicker_args['show_on_focus']) || $datepicker_args['show_on_focus']),
					'format'                                     => $format_moment,
					'position'                                   => (isset($datepicker_args['position']) && $datepicker_args['position']=='top') ? 'top left' : 'bottom left',
					'reposition'                                 => (!isset($datepicker_args['position']) || !in_array($datepicker_args['position'], array('top', 'bottom'))),
					'firstDay'                                   => (isset($datepicker_args['first_day'])) ? intval($datepicker_args['first_day']) : 0,
					'showShortDaysName'                          => (!isset($datepicker_args['show_short_day_names']) || $datepicker_args['show_short_day_names']),
					'i18n'                                       => array(
						'previousMonth'                              => __('Mois précédent', 'simple-coherent-form'),
						'nextMonth'                                  => __('Mois suivant', 'simple-coherent-form'),
						'months'                                     => (isset($datepicker_args['month_names']) && is_array($datepicker_args['month_names'])) ? $datepicker_args['month_names'] : null,
						'weekdays'                                   => (isset($datepicker_args['day_names']) && is_array($datepicker_args['day_names'])) ? $datepicker_args['day_names'] : null,
						'weekdaysShort'                              => (isset($datepicker_args['short_day_names']) && is_array($datepicker_args['short_day_names'])) ? $datepicker_args['short_day_names'] : null,
					),
				);
				
				/* Date minimum */
				if (isset($datepicker_args['min_date']) && (is_a($datepicker_args['min_date'], 'DateTime') || is_string($datepicker_args['min_date']))) {
					$min_date = $datepicker_args['min_date'];
					if (is_string($datepicker_args['min_date'])) $min_date = new DateTime($datepicker_args['min_date']);

					if (is_a($min_date, 'DateTime')) {
						$js_codes['arg_min_date'] = "new Date(" . $min_date->format('Y') . ", " . (intval($min_date->format('m'))-1) . ", " . $min_date->format('d') . ", 12)";
						$pikaday_args['minDate'] = '{{arg_min_date}}';
						$input_args['min'] = $min_date->format('Y-m-d');
					}
				}

				/* Date maximum */
				if (isset($datepicker_args['max_date']) && (is_a($datepicker_args['max_date'], 'DateTime') || is_string($datepicker_args['max_date']))) {
					$max_date = $datepicker_args['max_date'];
					if (is_string($datepicker_args['max_date'])) $max_date = new DateTime($datepicker_args['max_date']);

					if (is_a($max_date, 'DateTime')) {
						$js_codes['arg_max_date'] = "new Date(" . $max_date->format('Y') . ", " . (intval($max_date->format('m'))-1) . ", " . $max_date->format('d') . ", 12)";
						$pikaday_args['maxDate'] = '{{arg_max_date}}';
						$input_args['max'] = $max_date->format('Y-m-d');
					}
				}

				/* Date par défaut */
				$default_date = null;
				if (isset($args['value']) && (is_a($args['value'], 'DateTime') || is_string($args['value']))) {
					$default_date = $args['value'];

					/* Si la valeur */
					if (is_string($default_date)) {
						$value_format = (apply_filters('scf_date_format_php', true, $args)) ? $format_php : 'Y-m-d';
						$value_format = apply_filters('scf_date_value_format', $value_format, $args);
						try {
							$date = DateTime::createFromFormat($value_format, strval($default_date));
							$default_date = $date;
						} catch (Exception $e) {}
					}
				}

				if (is_a($default_date, 'DateTime')) {
					$js_codes['arg_default_date'] = "new Date(" . $default_date->format('Y') . ", " . (intval($default_date->format('m'))-1) . ", " . $default_date->format('d') . ", 12)";
					$pikaday_args['defaultDate'] = '{{arg_default_date}}';
					$pikaday_args['setDefaultDate'] = true;
				}


				/* Disable date or days */
				$disabled_weekdays_fn = 'false';
				$disabled_days_fn = 'false';

				/* Désactive certains jours de la semaine */
				if (isset($datepicker_args['disable_weekdays']) && is_array($datepicker_args['disable_weekdays']) && count($datepicker_args['disable_weekdays'])>0) {

					/* Conversion jour en toute lettre et chiffre */
					$days_int_converter = array('sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6);

					/* Jours désactivés */
					$disabled_days = array();

					foreach ($datepicker_args['disable_weekdays'] as $day) {
						if (is_int($day)) {
							$disabled_days[] = $day;
						} else if (is_string($day)) {
							if (isset($days_int_converter[strtolower($day)])) {
								$disabled_days[] = intval($days_int_converter[strtolower($day)]);
							}
						} else if (is_a($day, 'DateTime')) {
							$disabled_days[] = intval($day->format('w'));
						}
					}

					$disabled_weekdays_fn = json_encode($disabled_days) . '.includes(parseInt(date.getDay()))';
				}

				/* Désactive certains jours de l'année */
				if (isset($datepicker_args['disable_days']) && is_array($datepicker_args['disable_days']) && count($datepicker_args['disable_days'])>0) {

					/* Jours désactivés */
					$disabled_days = array();

					foreach ($datepicker_args['disable_days'] as $day) {
						if (is_string($day)) $day = new DateTime($day);
						if (is_a($day, 'DateTime')) {
							$disabled_days[] = $day->format('Y') . "-" . (intval($day->format('m'))-1) . "-" . intval($day->format('j'));
						}
					}

					$disabled_days_fn = json_encode($disabled_days) . '.includes(date.getFullYear() + "-" + date.getMonth() + "-" + date.getDate())';
				}

				$js_codes['arg_disable_day'] = "function(date){ return (" . $disabled_weekdays_fn ." || " . $disabled_days_fn . "); }";
				if ($disabled_weekdays_fn!=='false' || $disabled_days_fn!=='false') $pikaday_args['disableDayFn'] = '{{arg_disable_day}}';

				/* Parse les valeurs par défaut de pikaday */
				if (isset($datepicker_args['pikaday_options']) && is_array($datepicker_args['pikaday_options']) && count($datepicker_args['pikaday_options'])>0) {
					$pikaday_args = wp_parse_args($datepicker_args['pikaday_options'], $pikaday_args);
				}

				$js_codes = apply_filters( 'scf_date_pikaday_args_js', $js_codes, $pikaday_args, $args, $datepicker_args);
				$pikaday_args = apply_filters( 'scf_date_pikaday_args', $pikaday_args, $args, $datepicker_args);

				/* Encodage des paramètres de pikaday */
				$pikaday_args_json = json_encode($pikaday_args, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

				/* Réhabilitation des codes JS dans l'encodage */
				foreach ($js_codes as $js_code_slug => $js_code) {
					$pikaday_args_json = str_replace( array('"{{' . $js_code_slug . '}}"', "'{{" . $js_code_slug . "}}'"), $js_code, $pikaday_args_json );
				}
				
			}

			?><input <?php
				foreach ($input_args as $input_args_key => $input_args_value) { echo esc_attr($input_args_key) . '="' . esc_attr($input_args_value) . '" '; }
			?>/><?php

			if (isset($args['datepicker']) && (is_array($args['datepicker']) || $args['datepicker'])) {
				?><script type="text/javascript">
					if (('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0)) {
						<?php if (isset($args['format']) && !empty($args['format'])) { ?>
							document.getElementById('<?php echo esc_attr($datepicker_id); ?>').setAttribute("data-format", "");
						<?php } ?>
						document.getElementById('<?php echo esc_attr($wrapper_id); ?>').classList.remove("scf-datepicker");
					} else {
						var picker_<?php echo esc_attr(str_replace('-', '_', $datepicker_id)); ?> = null;
						window.addEventListener("load", function(e){
							picker_<?php echo esc_attr(str_replace('-', '_', $datepicker_id)); ?> = new Pikaday(<?php echo $pikaday_args_json; ?>);
						});
						<?php if (isset($datepicker_args['show_calendar']) && $datepicker_args['show_calendar']) { ?>
							document.write("<span class='scf-calendar'></span>");
						<?php } ?>
					}
				</script><?php
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

	new SCFDate();
}
