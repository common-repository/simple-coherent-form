<?php

declare(strict_types=1);

namespace SCF\Front;

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Front\SCFFront')) {

	/**
	 * Classe principale du plugin
	 */
	class SCFFront {
		
		/**
		 * @var Singleton
		 * @access private
		 * @static
		 */
		private static $_instance = null;
		
		/**
		 * @var Listes d'options
		 * @access private
		 * @static
		 */
		private static $lists = array();
		
		/**
		 * @var ID du champ
		 * @access private
		 * @static
		 */
		public static $ID = 0;

		/**
		 * Méthode qui crée l'unique instance de la classe
		 * si elle n'existe pas encore puis la retourne.
		 *
		 * @param void
		 * @return Singleton
		 */
		public static function getInstance() {
			if(is_null(self::$_instance)) self::$_instance = new SCFFront();
			return self::$_instance;
		}
		
		/**
		 * Constructeur de la classe
		 *
		 * @param void
		 * @return void
		 */
		private function __construct() {
			$this->addHooks();
			do_action( 'scf_loaded' );
		}

		/**
		 * Ajout des hooks de wordpress
		 */
		public function addHooks() {

			add_action( 'wp_enqueue_scripts', array($this, 'addFrontScripts') );
			add_action( 'admin_enqueue_scripts', array($this, 'addFrontScripts') );
			add_filter( 'script_loader_tag', array($this, 'delayFrontScripts'), 10, 2);
			add_action( 'init', array($this, 'loadTextDomain'), 1 );

			/* AJAX */
			add_action( 'wp_ajax_scf_check_unicity', array($this, 'checkUnicity') );
			add_action( 'wp_ajax_nopriv_scf_check_unicity', array($this, 'checkUnicity') );
			add_action( 'wp_ajax_scf_check_existence', array($this, 'checkExistence') );
			add_action( 'wp_ajax_nopriv_scf_check_existence', array($this, 'checkExistence') );

			/* SCF */
			add_action( 'scf_label_group', array($this, 'displayLabel') );
			add_action( 'scf_optional', array($this, 'displayOptional') );
			add_action( 'scf_field_group', array($this, 'displayFieldGroup'), 10, 2 );
			add_action( 'scf_description', array($this, 'displayDescription') );
			add_action( 'scf_after_field', array($this, 'displayErrors') );
		}

		/**
		* Ajoute les scripts au front
		*
		* @param void
		* @return void
		*/
		public function addFrontScripts() {
			wp_register_script('scf-pass', plugin_dir_url(SIMPLE_COHERENT_FORM_FILE) . 'assets/js/passwords.js', array(), '1.0');
			wp_register_script('scf-purify', plugin_dir_url(SIMPLE_COHERENT_FORM_FILE) . 'assets/js/purify.js', array(), '3.0.5');
			wp_register_script('scf-squire', plugin_dir_url(SIMPLE_COHERENT_FORM_FILE) . 'assets/js/squire.js', array('scf-purify'), '2.0.3');
			wp_register_script('scf-pikaday', plugin_dir_url(SIMPLE_COHERENT_FORM_FILE) . 'assets/js/pikaday.js', array('moment'), '1.8.2');
			wp_register_script('scf-tel', plugin_dir_url(SIMPLE_COHERENT_FORM_FILE) . 'assets/js/libphonenumber-max.js', array(), '1.10.38');
			wp_register_script('scf-script', plugin_dir_url(SIMPLE_COHERENT_FORM_FILE) . 'assets/js/script.js', array('jquery', 'wp-hooks'), '1.1');
			wp_register_script('scf-script-sync', plugin_dir_url(SIMPLE_COHERENT_FORM_FILE) . 'assets/js/script.sync.js', array('jquery', 'wp-hooks', 'scf-script'), '1.1');
		
			$scripts = apply_filters( 'scf_script_inline', array(
				'scf_ajax_url'				=> admin_url( 'admin-ajax.php' ),
				'scf_check_unicity_nonce'	=> wp_create_nonce('scf_check_unicity_or_existence_hash'),
				'scf_errors'				=> $this->getErrors(),
				'scf_i18n'					=> apply_filters( 'scf_i18n', array() )
			) );

			$inline_script = '';
			foreach ($scripts as $key => $value) { $inline_script .= 'const ' . $key .' = ' . json_encode($value) . '; '; }
			wp_add_inline_script('scf-script', $inline_script, 'before');

			/* Regex de vérification de format (non compatible IE) */
			if (!is_admin()) { ?><script type="text/javascript">const regex_string = /(?<!\\\\)(?:\\\\\\\\)*%(?:\(([^\)]+)\))?(?:\[([^\]]+)])?(?:\{([^\}]+)})?([cCisarRf]{1})/;</script><?php }
		}

		/**
		 * Rend certains scripts asynchrone
		 *
		 * @param string $tag    code HTML du script
		 * @param string $handle slug du script
		 * @return string Le nouveau code HTML
		*/
		public function delayFrontScripts($tag, $handle) {
			if ( !in_array($handle, array('scf-pass', 'scf-tel')) ) return $tag;
			return str_replace( '<script', '<script async="async"', $tag );
		}

		/**
		* Active les Traductions du plugin
		*
		* @param void
		* @return void
		*/
		public function loadTextDomain() {
			load_plugin_textdomain( 'simple-coherent-form', false, basename(dirname(SIMPLE_COHERENT_FORM_FILE)) . '/languages' );
		}

		/**
		* Vérifie l'unicité du valeurs parmis les utilisateurs enregistrés (ex: verifie que l'user_login n'est pas déjà utilisé)
		*
		* @param void
		* @return void
		*/
		public function checkUnicity() {
			check_ajax_referer( 'scf_check_unicity_or_existence_hash', 'security' );

			if (isset($_POST['key']) && is_string($_POST['key']) && !empty($_POST['key']) && isset($_POST['value']) && is_string($_POST['value']) && !empty($_POST['value'])) {
				if (in_array($_POST['key'], array('ID', 'user_login', 'user_email', 'user_url', 'user_nicename', 'display_name'))) {
					$users = get_users(array(
						'search_columns' => array(sanitize_key(strtolower($_POST['key']))),
						'search' => sanitize_text_field($_POST['value'])
					));
				} else {
					$users = get_users(array(
						'meta_key' => sanitize_key(strtolower($_POST['key'])),
						'meta_value' => sanitize_meta(sanitize_key(strtolower($_POST['key'])), sanitize_text_field($_POST['value']), 'user')
					));
				}

				if ( (is_array($users) && count($users)>1) || (is_array($users) && count($users)==1 && $users[0]->ID!=get_current_user_id()) ) {
					wp_send_json_error();
					wp_die();
					die;
				}
			}

			if (apply_filters('scf_check_unicity', true)) {
				wp_send_json_success();
				wp_die();
			} else {
				wp_send_json_error();
				wp_die();
			}
		}

		/**
		* Vérifie l'existence d'un utilisateur avec la valeur d'un champ (ex: verifie que l'user_email existe pour se connecter)
		*
		* @param void
		* @return void
		*/
		public function checkExistence() {
			check_ajax_referer( 'scf_check_unicity_or_existence_hash', 'security' );

			if (isset($_POST['key']) && is_string($_POST['key']) && !empty($_POST['key']) && isset($_POST['value']) && is_string($_POST['value']) && !empty($_POST['value'])) {
				if (in_array($_POST['key'], array('ID', 'user_login', 'user_email', 'user_url', 'user_nicename', 'display_name'))) {
					$users = get_users(array(
						'search_columns' => array(sanitize_key(strtolower($_POST['key']))),
						'search' => sanitize_text_field($_POST['value'])
					));
				} else {
					$users = get_users(array(
						'meta_key' => sanitize_key(strtolower($_POST['key'])),
						'meta_value' => sanitize_meta(sanitize_key(strtolower($_POST['key'])), sanitize_text_field($_POST['value']), 'user')
					));
				}

				if (!is_array($users) || count($users)<=0) {
					wp_send_json_error();
					wp_die();
				}
			}

			if (apply_filters('scf_check_existence', true)) {
				wp_send_json_success();
				wp_die();
			} else {
				wp_send_json_error();
				wp_die();
			}
		}

		/**
		 * Récupère la liste des pays et renvoie les valeurs demandées pour les options de select2 et select natif
		 *
		 * @param string      $value          Valeur de l'option
		 * @param string|null $label          Intitulé de l'option dans la liste d'options du select2
		 * @param string|null $label_natif    Intitulé de l'option dans le select natif
		 * @param string|null $label_selector Intitulé de l'option dans le résumé du select2
		 * @param string      $order_by       Ordre de la liste des pays parmis [cca2, cca3, ccn3, cioc, name, native, tel, flag, rtl]
		 * @return array[] Liste des pays avec pour chacun un tableau de valeurs [cca2, cca3, ccn3, cioc, name, native, tel, flag, rtl]
		 */
		public static function getList($value, $label = null, $label_natif = null, $label_selector = null, $order_by = 'default') {

			$output = array();

			/* Récupère la mise en cache de la requête */
			if (isset(self::$lists[md5(serialize(func_get_args()))]) && apply_filters('scf_cache_options_lists', true)) return apply_filters('scf_options_list', self::$lists[md5(serialize(func_get_args()))]);

			if (in_array($value, array('cca2','cca3','ccn3','cioc','tel', 'name', 'native'))) {

				/* Récupération des données de pays depuis restcountries.com */
				$rep = file_get_contents( plugin_dir_path( SIMPLE_COHERENT_FORM_FILE ).'assets/js/restcountries.json');
				$items = json_decode($rep);

				/* Parcours des pays */
				foreach ($items as $item) {

					$output_item = array('value'=>'', 'label'=>'');
			
					/* Formatage de l'indicatif téléphonique */
					$item_tel = '+0';
					if (isset($item->idd) && isset($item->idd->root)) {
						$item_tel = $item->idd->root;
						if (isset($item->idd->suffixes) && is_array($item->idd->suffixes) && isset($item->idd->suffixes[0])) {
							$item_tel .= $item->idd->suffixes[0];
						}
					}

					/* Langue par défaut pour le nom */
					$lang = 'default';

					/* Langue utilisé si Polylang ou WPML installé */
					if (function_exists('pll_current_language') && pll_current_language('slug')) {
						$pll_current_lang = pll_current_language('slug');
						$key = array_search(strtoupper($pll_current_lang), array_column($items, 'cca2'));
						if ($key!==false) {
							$lang = strtolower($items[$key]->cca3);
						}
					} else if (defined('ICL_LANGUAGE_CODE')) {
						$pll_current_lang = ICL_LANGUAGE_CODE;
						$key = array_search(strtoupper($pll_current_lang), array_column($items, 'cca2'));
						if ($key!==false) {
							$lang = strtolower($items[$key]->cca3);
						}
					}

					$lang = apply_filters('scf_options_list_name_language', $lang);

					/* Indique si le nom du pays dans sa langue se lit de droite à gauche [true] ou inversement [false] */
					$is_native_rtl = false;

					/* Liste des pays dont la langue se lit de droite à gauche */
					$iso639_2_rtl = array('ara', 'syr', 'aii', 'cld', 'ckb', 'div', 'per', 'fas', 'pes', 'prs', 'tgk', 'aiq', 'bhh', 'haz', 'jpr', 'phv', 'deh', 'jdt', 'ttt', 'hau', 'heb', 'khw', 'kas', 'pus', 'pst', 'pbu', 'pbt', 'wne', 'snd', 'lss', 'sbn', 'urd', 'yid', 'ydd', 'yih', 'uzs');

					/* Par défaut, utilise la traduction du nom dans la langue du site ou le nom commun du pays */
					$native_name = ($lang=='default') ? $item->name->common : $item->translations->{$lang}->common ;

					/* Récupère le nom du pays dans sa propre langue */
					if (isset($item->name->nativeName)) {
						$native_names_array = get_mangled_object_vars($item->name->nativeName);
						$native_names = reset($native_names_array);
						$native_names_key = key($native_names_array);
						if (isset($native_names) && isset($native_names->common)) {
							$native_name = $native_names->common;
							if (in_array(strtolower($native_names_key), $iso639_2_rtl)) $is_native_rtl = true;
						}
					}

					/* Valeurs à remplacer */
					$label_cca2 = $item->cca2;
					$label_cca3 = (isset($item->cca3)) ? $item->cca3 : $label_cca2;
					$label_ccn3 = (isset($item->ccn3)) ? $item->ccn3 : $label_cca3;
					$label_cioc = (isset($item->cioc)) ? $item->cioc : $label_cca3;
					$label_name = ($lang=='default') ? $item->name->common : $item->translations->{$lang}->common ;
					$label_native = $native_name;
					$label_tel = $item_tel;
					$label_rtl = ($is_native_rtl) ? 'rtl' : 'ltr';
					$label_flag = '<span class="flag flag-' . strtolower($label_cca2) . ' option_list_flag_icon"></span>';

					switch ($value) {
						case 'cca2': $output_item['value'] = $label_cca2; break;
						case 'cca3': $output_item['value'] = $label_cca3; break;
						case 'ccn3': $output_item['value'] = $label_ccn3; break;
						case 'cioc': $output_item['value'] = $label_cioc; break;
						case 'name': $output_item['value'] = $label_name; break;
						case 'native': $output_item['value'] = $label_native; break;
						case 'tel': $output_item['value'] = $label_tel; break;
						default: break;
					}
			
					/* Valeur par défaut de label, label_native, label_selector */
					$output_item['label'] = $output_item['label_native'] = $output_item['label_selector'] = $output_item['value'];

					if ((isset($label) || isset($label_natif) || isset($label_selector)) && isset($item)) {

						$datas = array(
							'cca2'		=> esc_html($label_cca2),
							'cca3'		=> esc_html($label_cca3),
							'ccn3'		=> esc_html($label_ccn3),
							'cioc'		=> esc_html($label_cioc),
							'name'		=> esc_html($label_name),
							'native'	=> esc_html($label_native),
							'tel'		=> esc_html($label_tel),
							'flag'		=> $label_flag,
							'rtl'		=> esc_html($label_rtl)
						);

						$output_item['data'] = $datas;

						/* Remplace les codes [cca2, cca3, ccn3, cioc, name, native, tel, flag, rtl] entre accolades par la valeur en question */
						if (isset($label)) $output_item['label'] = str_replace(array_map(function($n) { return '{'.$n.'}'; }, array_keys($datas)), array_values($datas), $label);
						if (isset($label_natif)) $output_item['label_native'] = str_replace(array_map(function($n) { return '{'.$n.'}'; }, array_keys($datas)), array_values($datas), $label_natif);
						if (isset($label_selector)) $output_item['label_selector'] = str_replace(array_map(function($n) { return '{'.$n.'}'; }, array_keys($datas)), array_values($datas), $label_selector);

					}

					$output[] = $output_item;

				}

				/* Tri des options */
				uasort($output, function($a, $b) use ($order_by) {
					if (isset($a['data'][$order_by]) && isset($b['data'][$order_by])) {
						if (is_int($a['data'][$order_by]) && is_int($b['data'][$order_by])) {
							if ($a['data'][$order_by] == $b['data'][$order_by]) return 0;
							return ($a['data'][$order_by] < $b['data'][$order_by]) ? -1 : 1;
						} else {
							return strcmp($a['data'][$order_by], $b['data'][$order_by]);
						}
					}
					return 0;
				});

				/* Mise en cache de la requête */
				if (apply_filters('scf_cache_options_lists', true)) self::$lists[md5(serialize(func_get_args()))] = apply_filters('scf_options_list_saved', $output);
				
				return apply_filters('scf_options_list', $output);

			}

			return array();

		}

		/**
		* Retourne la liste des erreurs utilisée dans le JS
		*
		* @param void
		* @return string[] Liste des erreurs possibles
		*/
		public function getErrors() {
			return apply_filters('scf_errors', array(
				'required'				=> esc_html__('Ce champ est requis.', 'simple-coherent-form'),
				'number_min'			=> esc_html__('Veuillez renseigner un chiffre supérieur ou égal à {0}.', 'simple-coherent-form'),
				'number_max'			=> esc_html__('Veuillez renseigner un chiffre inférieur ou égal à {0}.', 'simple-coherent-form'),
				'format'				=> esc_html__('Le format ne correspond pas à celui attendu.', 'simple-coherent-form'),
				'format_date'			=> esc_html__('Veuillez renseigner une date valide.', 'simple-coherent-form'),
				'format_number'			=> esc_html__('Veuillez renseigner un chiffre.', 'simple-coherent-form'),
				'format_url'			=> esc_html__('Le format de l\'URL semble invalide.', 'simple-coherent-form'),
				'format_email'			=> esc_html__('Le format de cette adresse-mail semble invalide.', 'simple-coherent-form'),
				'option_required'		=> esc_html__('Une option obligatoire n\'a pas été sélectionnée.', 'simple-coherent-form'),
				'option_one'			=> esc_html__('Veuillez choisir une option.', 'simple-coherent-form'),
				'option_more'			=> esc_html__('Veuillez choisir une ou plusieurs option(s).', 'simple-coherent-form'),
				'pass_strong'			=> esc_html__('Le mot de passe n\'est pas assez sécurisé.', 'simple-coherent-form'),
				'pass_repeat'			=> esc_html__('Les mots de passe ne sont pas identiques.', 'simple-coherent-form'),
				'pass_incorrect'		=> esc_html__('Le mot de passe est incorrect.', 'simple-coherent-form'),
				'email_repeat'			=> esc_html__('Les adresses e-mail ne sont pas identiques.', 'simple-coherent-form'),
				'not_identical'			=> esc_html__('Les champs ne sont pas identiques.', 'simple-coherent-form'),
				'format_tel'			=> esc_html__('Ce numéro semble incorrect.', 'simple-coherent-form'),
				'field_exist'			=> esc_html__('Ce champ doit être unique. Cette valeur est déjà utilisée.', 'simple-coherent-form'),
				'email_exist'			=> esc_html__('Cette adresse e-mail est déjà utilisée.', 'simple-coherent-form'),
				'pass_exist'			=> esc_html__('Ce mot de passe est déjà utilisée.', 'simple-coherent-form'),
				'tel_exist'				=> esc_html__('Ce numéro est déjà utilisée.', 'simple-coherent-form'),
				'user_email_exist'		=> esc_html__('Un compte existe déjà avec cette adresse e-mail.', 'simple-coherent-form'),
				'username_exist'		=> esc_html__('Ce nom d\'utilisateur est déjà utilisé.', 'simple-coherent-form'),
				'field_unexist'			=> esc_html__('Veuillez entrer une valeur déjà utilisée.', 'simple-coherent-form'),
				'email_unexist'			=> esc_html__('Cette adresse e-mail n\'est pas enregistrée.', 'simple-coherent-form'),
				'pass_unexist'			=> esc_html__('Ce mot de passe n\'est pas enregistrée.', 'simple-coherent-form'),
				'tel_unexist'			=> esc_html__('Ce numéro n\'est pas enregistrée.', 'simple-coherent-form'),
				'user_email_unexist'	=> esc_html__('L\'adresse e-mail est incorrect.', 'simple-coherent-form'),
				'username_unexist'		=> esc_html__('Ce nom d\'utilisateur n\'est pas enregistré.', 'simple-coherent-form'),
				'file_max'				=> esc_html__('Nombre maximum de fichiers atteint.', 'simple-coherent-form'),
				'file_too_large'		=> esc_html__('Ce fichier est trop volumineux.', 'simple-coherent-form'),
				'file_bad_format'		=> esc_html__('Le format de ce fichier n\'est pas accepté.', 'simple-coherent-form'),
				'files_too_large'		=> esc_html__('Un ou plusieurs fichiers sont trop volumineux.', 'simple-coherent-form'),
				'files_bad_format'		=> esc_html__('Le format d\'un ou plusieurs fichiers n\'est pas accepté.', 'simple-coherent-form'),
			));
		}

		/**
		* Retourne la liste des couleurs
		*
		* @param void
		* @static
		* @return string[] Liste des couleurs avec le nom de la variable et sa couleur (hexa, rgb, rgba, hsl,...)
		*/
		public static function getColors() {

			$scf_hue = apply_filters( 'scf_color_hue', 35);
			$scf_saturation = apply_filters( 'scf_color_saturation', 1);

			$nuances = array(
				'nuance_1' => array( 'h' => $scf_hue, 's' => $scf_saturation*60, 'l' => 96 ),
				'nuance_2' => array( 'h' => $scf_hue, 's' => $scf_saturation*100, 'l' => 99 ),
				'nuance_3' => array( 'h' => $scf_hue, 's' => $scf_saturation*3, 'l' => 61 ),
				'nuance_4' => array( 'h' => $scf_hue, 's' => $scf_saturation*90, 'l' => 4 ),
				'nuance_5' => array( 'h' => $scf_hue, 's' => $scf_saturation*30, 'l' => 34 ),
				'nuance_6' => array( 'h' => $scf_hue, 's' => $scf_saturation*24, 'l' => 27 ),
				'nuance_7' => array( 'h' => $scf_hue, 's' => $scf_saturation*49, 'l' => 12 ),
				'nuance_8' => array( 'h' => $scf_hue, 's' => $scf_saturation*18, 'l' => 65 ),
				'nuance_9' => array( 'hex' => '#169D00' ),
				'nuance_10' => array( 'hex' => '#C10A31' ),
				'nuance_11' => array( 'h' => $scf_hue, 's' => $scf_saturation*60, 'l' => 32 ),
				'nuance_12' => array( 'h' => $scf_hue, 's' => $scf_saturation*56, 'l' => 53 ),
				'nuance_13' => array( 'h' => $scf_hue, 's' => $scf_saturation*22, 'l' => 70 ),
				'nuance_14' => array( 'h' => $scf_hue, 's' => $scf_saturation*23, 'l' => 32 ),
				'nuance_15' => array( 'h' => $scf_hue, 's' => $scf_saturation*23, 'l' => 41 ),
				'nuance_16' => array( 'h' => $scf_hue, 's' => $scf_saturation*29, 'l' => 10 ),
				'nuance_17' => array( 'h' => $scf_hue, 's' => $scf_saturation*0, 'l' => 100 ),
				'nuance_18' => array( 'h' => $scf_hue, 's' => $scf_saturation*8, 'l' => 48 ),
				'nuance_19' => array( 'h' => $scf_hue, 's' => $scf_saturation*24, 'l' => 85 ),
				'nuance_20' => array( 'h' => $scf_hue, 's' => $scf_saturation*20, 'l' => 81 ),
				'nuance_21' => array( 'h' => $scf_hue, 's' => $scf_saturation*8, 'l' => 49 ),
				'nuance_22' => array( 'h' => $scf_hue, 's' => $scf_saturation*0, 'l' => 91 ),
				'nuance_23' => array( 'h' => $scf_hue, 's' => $scf_saturation*28, 'l' => 87 ),
				'nuance_24' => array( 'h' => $scf_hue, 's' => $scf_saturation*37, 'l' => 80 ),
				'nuance_25' => array( 'h' => $scf_hue, 's' => $scf_saturation*73, 'l' => 19 ),
				'nuance_26' => array( 'h' => $scf_hue, 's' => $scf_saturation*29, 'l' => 87 ),
				'nuance_27' => array( 'h' => $scf_hue, 's' => $scf_saturation*60, 'l' => 26 ),
				'nuance_28' => array( 'h' => $scf_hue, 's' => $scf_saturation*36, 'l' => 64 ),
				'nuance_29' => array( 'h' => $scf_hue, 's' => $scf_saturation*24, 'l' => 49 ),
				'nuance_30' => array( 'h' => $scf_hue, 's' => $scf_saturation*85, 'l' => 26 ),
				'nuance_31' => array( 'h' => $scf_hue, 's' => $scf_saturation*40, 'l' => 42 ),
				'nuance_32' => array( 'h' => $scf_hue, 's' => $scf_saturation*85, 'l' => 26 ),
				'nuance_33' => array( 'h' => $scf_hue, 's' => $scf_saturation*10, 'l' => 69 ),
				'nuance_34' => array( 'h' => $scf_hue, 's' => $scf_saturation*85, 'l' => 95 ),
				'nuance_35' => array( 'h' => $scf_hue, 's' => $scf_saturation*85, 'l' => 95 ),
			);

			$nuances = apply_filters( 'scf_color_nuances', $nuances, $scf_hue, $scf_saturation );

			$nuances_str = array_combine(
				array_keys($nuances), 
				array_map(function($key, $color) {
					$out = null;
					
					if (isset($color['hex'])) {
						$out = $color['hex'];
					} else if (isset($color['rgba'])) {
						$out = $color['rgba'];
					} else if (isset($color['rgb'])) {
						$out = $color['rgb'];
					} else if (isset($color['hsla'])) {
						$out = $color['hsla'];
					} else if (isset($color['hsl'])) {
						$out = $color['hsl'];
					} else if (isset($color['r']) && isset($color['g']) && isset($color['b']) && isset($color['a'])) {
						$out = sprintf('rgba(%s, %s, %s, %s)', strval($color['r']), strval($color['g']), strval($color['b']), strval($color['a']));
					} else if (isset($color['r']) && isset($color['g']) && isset($color['b'])) {
						$out = sprintf('rgb(%s, %s, %s)', strval($color['r']), strval($color['g']), strval($color['b']));
					} else if (isset($color['h']) && isset($color['s']) && isset($color['l']) && isset($color['a'])) {
						$out = sprintf('hsla(%s, %s%%, %s%%, %s)', strval($color['h']), strval($color['s']), strval($color['l']), strval($color['a']));
					} else if (isset($color['h']) && isset($color['s']) && isset($color['l'])) {
						$out = sprintf('hsl(%s, %s%%, %s%%)', strval($color['h']), strval($color['s']), strval($color['l']));
					}

					return apply_filters( 'scf_color_'.$key, $out);
				}, array_keys($nuances), array_values($nuances))
			);

			$colors_by_nuance = array(

				'label_color' => 'nuance_4',
				'optional_color' => 'nuance_3',
				'valid_color' => 'nuance_9',
				'require_color' => 'nuance_10',
				'error_color' => 'nuance_10',
				'error_border_color' => 'nuance_10',
				'error_arrow_color' => 'nuance_10',

				'icon_color' => 'nuance_11',
				'icon_hover_color' => 'nuance_12',
				'icon_disabled_color' => 'nuance_13',

				'input_color' => 'nuance_14',
				'select_option_color' => 'nuance_14',
				'input_color_bg' => 'nuance_1',
				'input_color_placeholder' => 'nuance_3',

				'input_color_box_shadow' => array('inset 0 0 3px %s, 0 0 3px rgba(255,255,255,.4)', 'nuance_27'),
				'input_autofill_color_box_shadow' => array('inset 0 0 3px %s, inset 0 0 0 9999px %s, 0 0 3px rgba(255,255,255,.4)', 'nuance_27', 'nuance_1'),

				'wysiwyg_actionbar_color_box_shadow' => array('0 0 3px %s', 'nuance_27'),
				'wysiwyg_actionbar_arrow_color_box_shadow' => array('0.3px -0.3px 3px %s', 'nuance_27'),
				'wysiwyg_actionbar_color_border' => 'nuance_3',
				'wysiwyg_action_checked_color' => 'nuance_17',
				'wysiwyg_action_checked_color_bg' => 'nuance_27',

				'input_hover_color' => 'nuance_15',
				'input_hover_color_bg' => 'nuance_2',
				'input_hover_color_placeholder' => 'nuance_3',

				'input_focus_color' => 'nuance_16',
				'input_focus_color_bg' => 'nuance_17',
				'input_focus_color_placeholder' => 'nuance_3',

				'input_disabled_color' => 'nuance_18',
				'input_disabled_color_bg' => 'nuance_19',
				'input_disabled_color_stripe' => 'nuance_20',
				'input_disabled_color_placeholder' => 'nuance_21',

				'hint_color' => 'nuance_8',
				'hint_color_checked' => 'nuance_4',

				'details_color' => 'nuance_3',
				'details_color_check' => 'nuance_9',

				'datepicker_today_color_bg' => 'nuance_22',
				'datepicker_range_color_bg' => 'nuance_23',

				'select_option_color_check' => 'nuance_6',
				'select_option_color_placeholder' => 'nuance_8',
				'select_option_hover_color' => 'nuance_7',
				'select_option_hover_color_bg' => 'nuance_24',
				'select_option_hover_color_placeholder' => 'nuance_6',

				'select_option_hover_color_box_shadow' => array('inset 3px 0 3px -3px %s, inset -3px 0 3px -3px %s', 'nuance_5', 'nuance_5'),
				'select_option_last_hover_color_box_shadow' => array('inset 3px 0 3px -3px %s, inset -3px 0 3px -3px %s, inset 0 -3px 3px -3px %s', 'nuance_5', 'nuance_5', 'nuance_5'),
				'select_option_focus_color' => 'nuance_7',
				'select_option_focus_color_bg' => 'nuance_24',
				'select_option_focus_color_placeholder' => 'nuance_6',
				
				'select_option_focus_color_box_shadow' => array('inset 3px 0 3px -3px %s, inset -3px 0 3px -3px %s', 'nuance_5', 'nuance_5'),
				'select_option_last_focus_color_box_shadow' => array('inset 3px 0 3px -3px %s, inset -3px 0 3px -3px %s, inset 0 -3px 3px -3px %s', 'nuance_5', 'nuance_5', 'nuance_5'),

				'select_scrollbar_color' => 'nuance_25',
				'select_scrollbar_color_bg' => 'nuance_26',

				'select_arrow_color' => 'nuance_6',

				'select_tag_color' => 'nuance_17',
				'select_tag_color_bg' => 'nuance_27',
				'select_tag_color_more' => 'nuance_27',

				'button_selected_color' => 'nuance_17',
				'button_color_bg' => 'nuance_27',
				'button_color_box_shadow' => array('0 0 3px %s, 0 0 3px rgba(255,255,255,.4)', 'nuance_27'),

				'checkbox_color' => 'nuance_6',
				'checkbox_color_border' => 'nuance_27',
				'checkbox_hover_color_border' => 'nuance_27',
				'checkbox_hover_color_check' => 'nuance_28',
				'checkbox_focus_color_border' => 'nuance_27',
				'checkbox_focus_color_check' => 'nuance_28',
				'checkbox_active_color_border' => 'nuance_27',
				'checkbox_active_color_check' => 'nuance_29',

				'checkbox_checked_color' => 'nuance_7',
				'checkbox_checked_color_border' => 'nuance_27',
				'checkbox_checked_color_check' => 'nuance_34',
				'checkbox_checked_color_bg' => 'nuance_27',
				'checkbox_checked_hover_color_border' => 'nuance_31',
				'checkbox_checked_hover_color_check' => 'nuance_35',
				'checkbox_checked_hover_color_bg' => 'nuance_31',
				'checkbox_checked_focus_color_border' => 'nuance_31',
				'checkbox_checked_focus_color_check' => 'nuance_35',
				'checkbox_checked_focus_color_bg' => 'nuance_31',
				'checkbox_checked_active_color_border' => 'nuance_27',
				'checkbox_checked_active_color_check' => 'nuance_35',
				'checkbox_checked_active_color_bg' => 'nuance_27',

				'checkbox_disabled_color' => 'nuance_18',
				'checkbox_disabled_color_border' => 'nuance_33',

				'radio_color' => 'nuance_6',
				'radio_color_border' => 'nuance_27',
				'radio_hover_color_border' => 'nuance_27',
				'radio_hover_color_check' => 'nuance_28',
				'radio_focus_color_border' => 'nuance_27',
				'radio_focus_color_check' => 'nuance_28',
				'radio_active_color_border' => 'nuance_27',
				'radio_active_color_check' => 'nuance_29',

				'radio_checked_color' => 'nuance_7',
				'radio_checked_color_border' => 'nuance_27',
				'radio_checked_color_check' => 'nuance_30',
				'radio_checked_hover_color_border' => 'nuance_31',
				'radio_checked_hover_color_check' => 'nuance_32',
				'radio_checked_focus_color_border' => 'nuance_31',
				'radio_checked_focus_color_check' => 'nuance_32',
				'radio_checked_active_color_border' => 'nuance_27',
				'radio_checked_active_color_check' => 'nuance_32',

				'radio_disabled_color' => 'nuance_18',
				'radio_disabled_color_border' => 'nuance_33',
			);

			$colors = array();
			foreach ($colors_by_nuance as $key => $value) {
				if (is_string($value) && isset($nuances_str[$value])) {
					$colors[$key] = $nuances_str[$value];
				} else if (is_array($value)) {
					$format = array_shift($value);
					$issets = array_map(function($nuance) use ($nuances_str) {
						return isset($nuances_str[$nuance]) ? $nuances_str[$nuance] : false;
					}, $value);

					if (!in_array(false, $issets, true)) {
						$colors[$key] = vsprintf($format, $issets);
					}
				}
			}

			$colors['error_shadow_color'] = 'rgba(93,10,49,0.8)';
			$colors['checkbox_color_check'] = 'transparent';
			$colors['checkbox_color_bg'] = 'transparent';
			$colors['checkbox_hover_color_bg'] = 'transparent';
			$colors['checkbox_focus_color_bg'] = 'transparent';
			$colors['checkbox_active_color_bg'] = 'transparent';
			$colors['checkbox_disabled_color_bg'] = 'transparent';
			$colors['radio_color_check'] = 'transparent';
			$colors['select_tag_color_more_bg'] = 'transparent';

			return apply_filters( 'scf_colors', $colors, $scf_hue, $scf_saturation, $nuances_str );
		}

		/**
		* Affiche l'erreur en-dessous du champ
		*
		* @param array $args Paramètres de l'input
		* @return void
		*/
		public function displayErrors($args) {
			?><span class="scf-error-text"><?php echo esc_html($args['error_text']); ?></span><?php
		}

		/**
		* Enregistre la feuille de style
		*
		* @param void
		* @return void
		*/
		public function registerStyle() {
			add_action( 'wp_footer', array($this, 'printStyle') );
		}

		/**
		* Affiche la feuille de style avec les couleurs
		*
		* @param void
		* @return void
		*/
		public function printStyle() {
			$colors = self::getColors();

			$replace = array();
			foreach ($colors as $key => $value) {
				$replace['var(--'.$key.')'] = $value;
			}

			/* Récupérer le template css */
			$css = file_get_contents(plugin_dir_path(SIMPLE_COHERENT_FORM_FILE) . 'assets/css/style.tpl'.((defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min').'.css');
			
			/* Remplace les couleurs */
			$css = str_replace(array_keys($replace), array_values($replace), $css);

			// Replace relative path to img to absolute path
			$css = str_replace("url('..", "url('" . plugin_dir_url(SIMPLE_COHERENT_FORM_FILE) . 'assets', $css);

			?><style type="text/css"><?php echo $css ; ?></style><?php
		}

		/**
		* Affichage du label
		*
		* @param array $args Paramètres de l'input
		* @return void
		*/
		public function displayLabel($args) {
			/* Before label */
			do_action('scf_before_label_' . esc_attr(strtolower($args['type'])), $args);
			do_action('scf_before_label', $args);

			/* Label */
			$label = apply_filters('scf_label', $args['label'], $args );
			if (isset($label)) {
				?><span class="scf-label"><?php
					if (apply_filters('scf_label_screen_reader', true, $args)) {
						?><span class="scf-label-screen-reader"><?php
							echo esc_html(apply_filters('scf_label_screen_reader_text', __('Champ ', 'simple-coherent-form'), $args));
						?></span><?php
					}
					echo esc_html($label);
				?></span><?php
			}

			/* After label */
			do_action('scf_after_label_' . esc_attr(strtolower($args['type'])), $args);
			do_action('scf_after_label', $args);
		}

		/**
		* Affichage du statut Optionnel
		*
		* @param array $args Paramètres de l'input
		* @return void
		*/
		public function displayOptional($args) {
			if ((!isset($args['required']) || !$args['required']) && isset($args['display_optional_label']) && $args['display_optional_label']) {
				?><span class="scf-optional"><?php
					echo esc_html(apply_filters('scf_optional_text', __('Facultatif', 'simple-coherent-form')));
				?></span><?php
			}
		}

		/**
		* Affichage du champ
		*
		* @param array $args Paramètres de l'input
		* @return void
		*/
		public function displayFieldGroup($args, $type_group = true) {

			do_action('scf_before_field_' . esc_attr(strtolower($args['type'])), $args);
			do_action('scf_before_field', $args);

			if ($type_group) { ?><span class="scf-input"><?php }

			do_action('scf_field_' . esc_attr(strtolower($args['type'])), $args);
			do_action('scf_field', $args);

			if ($type_group) { ?></span><?php }

			do_action('scf_after_field_' . strtolower($args['type']), $args);
			do_action('scf_after_field', $args);
		}

		/**
		* Affichage de la description du champ
		*
		* @param array $args Paramètres de l'input
		* @return void
		*/
		public function displayDescription($args) {

			do_action('scf_before_description_' . strtolower($args['type']), $args);
			do_action('scf_before_description', $args);

			if (isset($args['description']) && $args['description']) {
				?><div class="scf-description"><?php echo wp_kses_post($args['description']); ?></div><?php
			}

			do_action('scf_after_description_' . strtolower($args['type']), $args);
			do_action('scf_after_description', $args);
		}

		/**
		* Fonction principale
		*
		* @static
		* @param array $args Paramètres de l'input
		* @return void
		*/
		public static function input($args) {

			$defaults = apply_filters('scf_default_args', array(
				'type'					=> 'text',
				'name'					=> '',
				'echo'				=> true,
				'required'				=> true,
				'display_optional_label'=> true,
				'readonly'				=> false,
				'disabled'				=> false,
				'value'					=> '',
				'label'					=> '',
				'description'			=> null,
				'always_show_label'		=> false,
				'placeholder'			=> '',
				'attributes'			=> array(),
				'wrapper_attributes'	=> array(),
				'autocomplete'			=> 'on',
				'unique'				=> false,
				'already_exists'		=> false,
				'show_validation'		=> true,
				'identical'				=> '',
				'format'				=> '',
				'focus'					=> false,
				'conditional'			=> array(),
				'error'					=> false,
				'error_text'			=> ''
			));
			$args = wp_parse_args( $args, $defaults );

			$args = apply_filters('scf_args', $args, $defaults);

			/* Vérification du type de champ */
			$types_availables = apply_filters('scf_types_availables', array(), $args);
			if (!isset($args['type']) || !is_string($args['type']) || empty($args['type']) || !in_array(strtolower($args['type']), $types_availables)) return false;

			/* Liste des types de champs entourés d'un label */
			$types_with_group_label = apply_filters('scf_types_with_group_label', array(), $args);

			/* Liste des types de champs entourés d'une div */
			$types_with_group_block = apply_filters('scf_types_with_group_block', array(), $args);

			/* ID du champ */
			self::$ID = (isset(self::$ID)) ? self::$ID+1 : 1 ;

			/* Ajoute le style global */
			$front = self::getInstance();
			$front->registerStyle();

			/* Insère le script JS */
			wp_enqueue_script('scf-script');

			/* Enregistre d'autres scripts/styles pour chaque champ */
			do_action('scf_enqueue_scripts_' . esc_attr(strtolower($args['type'])));
			do_action('scf_enqueue_scripts');

			/* Classes du Wrapper */
			$wrapper_classes_array = array('scf-group');
			$wrapper_classes_array[] = 'scf-' . $args['type'];
			if (isset($args['always_show_label']) && $args['always_show_label']) $wrapper_classes_array[] = 'scf-label-always-visible';
			if (isset($args['readonly']) && $args['readonly']) $wrapper_classes_array[] = 'scf-readonly';
			if (isset($args['required']) && $args['required']) $wrapper_classes_array[] = 'scf-required';
			if (isset($args['disabled']) && $args['disabled']=='on_touchable') $wrapper_classes_array[] = 'scf-disabled-on-touchable';
			if (isset($args['disabled']) && $args['disabled']=='on_pointer') $wrapper_classes_array[] = 'scf-disabled-on-pointer';
			if (isset($args['unique']) && $args['unique']) $wrapper_classes_array[] = 'scf-unique';
			if (isset($args['already_exists']) && $args['already_exists']) $wrapper_classes_array[] = 'scf-exists';
			if (isset($args['error']) && $args['error']) $wrapper_classes_array[] = 'scf-error';
			if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $wrapper_classes_array[] = 'scf-group-name-'.esc_attr(sanitize_title($args['name']));

			$wrapper_classes_array = apply_filters('scf_wrapper_classes', $wrapper_classes_array, $args);
			$wrapper_classes = implode(' ', $wrapper_classes_array);

			/* ID du Wrapper */
			$wrapper_id = 'scf-group-input-' . strval( crc32( esc_attr(strval($args['type'])) . '-' . ((isset($args['name']) && is_string($args['name']) && !empty($args['name'])) ? esc_attr(strval($args['name'])) : 'anonymous') . '-' . self::$ID ) );
			$wrapper_id = apply_filters('scf_wrapper_id', $wrapper_id, $args);

			/* Conditions d'affichage du Wrapper */
			$wrapper_conditional = '';
			if (isset($args['conditional']) && is_array($args['conditional']) && count($args['conditional']) > 1) {
				$conditional_format_recursive = function($conditions) use (&$conditional_format_recursive) {
					if (isset($conditions['name']) && isset($conditions['value'])) {
						/* Champ et valeur */
						$condition = array('name'=>$conditions['name'], 'value'=>$conditions['value']);// $conditions['name'] . '={' . serialize($conditions['value']) . '}';
						/* Operateur de comparaison */
						$condition['compare'] = (isset($conditions['compare']) && in_array($conditions['compare'], array('=','!=','<','<=','=<','>','>=','=>'))) ? $conditions['compare'] : '=';
						/* Typage des valeurs */
						$condition['type'] = (isset($conditions['type']) && in_array($conditions['type'], array('NUMERIC','STRING','DATE','BOOL'))) ? $conditions['type'] : 'STRING';
						/* Typage de la valeur demandé */
						switch ($condition['type']) {
							case 'NUMERIC':
								$condition['value'] = intval($condition['value']);
							break;
							case 'STRING':
								$condition['value'] = strval($condition['value']);
							break;
							case 'DATE':
								if (is_a($condition['value'], 'DateTime') || is_string($condition['value'])) {
									$date = (is_string($condition['value'])) ? new DateTime($condition['value']) : $condition['value'];
									if (is_a($date, 'DateTime')) $condition['value'] = $date->format('Y-m-d');
								}
							break;
							case 'BOOL':
								$condition['value'] = boolval($condition['value']);
							break;
							
							default: break;
						}
						return $condition;
					} else if (isset($conditions['relation']) && count($conditions)>2) {
						$condition = array('relation'=>(('OR' === strtoupper( $conditions['relation'] )) ? 'OR' : 'AND'));
						foreach ($conditions as $key => $value) {
							if ($key === 'relation' || !is_array($value) || count($value)<=1) continue;
							$subcondition =  $conditional_format_recursive($value);
							if (is_array($subcondition)) $condition[] = $subcondition;
						}
						return $condition;
					}
					return false;
				};
				$wrapper_conditional = $conditional_format_recursive($args['conditional']);
			}

			/* Arguments du Wrapper */
			$wrapper_args = (isset($args['wrapper_attributes']) && is_array($args['wrapper_attributes'])) ? $args['wrapper_attributes'] : array() ;

			$wrapper_args['class'] = $wrapper_classes;
			if (isset($wrapper_id) && !empty($wrapper_id)) $wrapper_args['id'] = $wrapper_id;
			if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $wrapper_args['data-name'] = $args['name'];
			if (isset($args['identical']) && is_string($args['identical']) && !empty($args['identical'])) $wrapper_args['data-scf-identical'] = strval($args['identical']);
			if (isset($wrapper_conditional) && is_array($wrapper_conditional) && count($wrapper_conditional)>1) $wrapper_args['data-scf-conditional'] = strval(json_encode($wrapper_conditional));
			if (isset($args['unique'])) {
				if (is_string($args['unique']) && !empty($args['unique'])) {
					$wrapper_args['data-scf-unique'] = $args['unique'];
				} else if ($args['unique']) {
					if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $wrapper_args['data-scf-unique'] = $args['name'];
				}
			}
			if (isset($args['already_exists'])) {
				if (is_string($args['already_exists']) && !empty($args['already_exists'])) {
					$wrapper_args['data-scf-exists'] = $args['already_exists'];
				} else if ($args['already_exists']) {
					if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $wrapper_args['data-scf-exists'] = $args['name'];
				}
			}

			$wrapper_args = apply_filters('scf_wrapper_args', $wrapper_args, $args);

			/* Balise HTML du wrapper */
			$wrapper_element = (in_array(strtolower($args['type']), $types_with_group_label)) ? 'label' : ((in_array(strtolower($args['type']), $types_with_group_block)) ? 'div' : 'span');
			$wrapper_element = esc_attr(apply_filters('scf_wrapper_element', $wrapper_element, $args));

			/* Lancement du buffer */
			ob_start();

			do_action('scf_before_wrapper_' . esc_attr(strtolower($args['type'])), $args);
			do_action('scf_before_wrapper', $args);

			echo "<$wrapper_element ";
			foreach ($wrapper_args as $wrapper_args_key => $wrapper_args_value) { echo esc_attr($wrapper_args_key) . '="' . esc_attr($wrapper_args_value) . '" '; }
			echo ">";

			do_action('scf_label_group', $args);
			do_action('scf_optional', $args);
			do_action('scf_field_group', $args, boolval(in_array(strtolower($args['type']), $types_with_group_label)));
			do_action('scf_description', $args);

			echo "</$wrapper_element>";

			do_action('scf_after_wrapper_' . esc_attr(strtolower($args['type'])), $args);
			do_action('scf_after_wrapper', $args);

			/* Récupération du buffer */
			$html = ob_get_clean();

			$html = apply_filters('scf_input', $html, $args);

			if (isset($args['echo']) && $args['echo']) {
				echo $html;
			} else {
				return $html;
			}

			return true;
		}
	}

	SCFFront::getInstance();
}
