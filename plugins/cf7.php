<?php

declare(strict_types=1);

namespace SCF\Plugins;

add_filter('wpcf7_autop_or_not', '__return_false');
add_action( 'wpcf7_contact_form', function($contact_form) {
	$contact_form->set_locale(get_locale());
});

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Plugins\CF7')) {

	/**
	 * Classe du champ Text
	 */
	class CF7 {
		
		/**
		 * @var Singleton
		 * @access private
		 * @static
		 */
		private static $_instance = null;

		private $tags = array();
		private $posted_data = array();
		private $uploaded_files = array();
		private $submission = null;

		/**
		 * Méthode qui crée l'unique instance de la classe
		 * si elle n'existe pas encore puis la retourne.
		 *
		 * @param void
		 * @return Singleton
		 */
		public static function getInstance() {
			if(is_null(self::$_instance)) self::$_instance = new CF7();
			return self::$_instance;
		}

		/**
		 * Constructeur de la classe
		 * Accroche les hooks
		 */
		function __construct() {
			$this->tags = array(
				'text'		=> array('label' => __('Texte', 'simple-coherent-form'),			'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'text*'		=> array('label' => __('Texte', 'simple-coherent-form'),			'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'email'		=> array('label' => __('E-mail', 'simple-coherent-form'),			'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'email*'	=> array('label' => __('E-mail', 'simple-coherent-form'),			'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'url'		=> array('label' => __('URL', 'simple-coherent-form'),				'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'url*'		=> array('label' => __('URL', 'simple-coherent-form'),				'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'tel'		=> array('label' => __('Téléphone', 'simple-coherent-form'),		'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'tel*'		=> array('label' => __('Téléphone', 'simple-coherent-form'),		'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'textarea'	=> array('label' => __('Zone de texte', 'simple-coherent-form'),	'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'textarea*'	=> array('label' => __('Zone de texte', 'simple-coherent-form'),	'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'number'	=> array('label' => __('Nombre', 'simple-coherent-form'),			'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'number*'	=> array('label' => __('Nombre', 'simple-coherent-form'),			'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'date'		=> array('label' => __('Date', 'simple-coherent-form'),				'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'date*'		=> array('label' => __('Date', 'simple-coherent-form'),				'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'time'		=> array('label' => __('Heure', 'simple-coherent-form'),			'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'time*'		=> array('label' => __('Heure', 'simple-coherent-form'),			'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'wysiwyg'	=> array('label' => __('Éditeur avancé', 'simple-coherent-form'),	'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'wysiwyg*'	=> array('label' => __('Éditeur avancé', 'simple-coherent-form'),	'features' => array('name-attr'=>true),								'callback' => array($this, 'addTagSimple')),
				'file'		=> array('label' => __('Fichier', 'simple-coherent-form'),			'features' => array('name-attr'=>true, 'file-uploading'=>true),		'callback' => array($this, 'addTagSimple')),
				'file*'		=> array('label' => __('Fichier', 'simple-coherent-form'),			'features' => array('name-attr'=>true, 'file-uploading'=>true),		'callback' => array($this, 'addTagSimple')),
				'select'	=> array('label' => __('Liste déroulante', 'simple-coherent-form'),	'features' => array('name-attr'=>true, 'selectable-values'=>true),	'callback' => array($this, 'addTagAdvanced')),
				'select*'	=> array('label' => __('Liste déroulante', 'simple-coherent-form'),	'features' => array('name-attr'=>true, 'selectable-values'=>true),	'callback' => array($this, 'addTagAdvanced')),
				'checkbox'	=> array('label' => __('Cases à cocher', 'simple-coherent-form'),	'features' => array('name-attr'=>true, 'selectable-values'=>true),	'callback' => array($this, 'addTagAdvanced')),
				'checkbox*'	=> array('label' => __('Cases à cocher', 'simple-coherent-form'),	'features' => array('name-attr'=>true, 'selectable-values'=>true),	'callback' => array($this, 'addTagAdvanced')),
				'radio'		=> array('label' => __('Bouton radio', 'simple-coherent-form'),		'features' => array('name-attr'=>true, 'selectable-values'=>true),	'callback' => array($this, 'addTagAdvanced')),
			);

			add_action( 'wpcf7_init', array($this, 'removeTags'), 20 );
			add_action( 'wpcf7_init', array($this, 'addTags'), 30 );
			add_action( 'wpcf7_admin_init', array($this, 'removeTagsGenerators'), 1 );
			add_action( 'wpcf7_admin_init', array($this, 'addTagsGenerators'), 30 );
			add_action( 'wpcf7_swv_create_schema', array($this, 'addFileRulesTypes'), 9, 2 );
			add_action( 'wpcf7_swv_create_schema', array($this, 'addSelectRulesTypes'), 9, 2 );
			add_filter( 'wpcf7_mail_tag_replaced_file', array($this, 'putFileInMail'), 11, 4 );
			add_filter( 'wpcf7_mail_tag_replaced_file*', array($this, 'putFileInMail'), 11, 4 );
			add_filter( 'wpcf7_mail_components', array($this, 'replaceAttachmentsInMail'), 10, 3);

			if (in_array('date', array_keys($this->tags))) {
				add_filter( 'wpcf7_validate_date', array($this, 'validateDate'), 90, 2 );
			}

			if (in_array('date*', array_keys($this->tags))) {
				add_filter( 'wpcf7_validate_date*', array($this, 'validateDate'), 90, 2 );
			}

			if (in_array('file', array_keys($this->tags))) {
				add_filter( 'wpcf7_validate_file', array($this, 'validateFile'), 90, 3 );
			}

			if (in_array('file*', array_keys($this->tags))) {
				add_filter( 'wpcf7_validate_file*', array($this, 'validateFile'), 90, 3 );
			}
		}

		/**
		 * Supprime les anciens champs de CF7
		 * Filtre wpcf7_init
		 *
		 * @param void
		 * @return void
		 */
		public function removeTags() {
			$tags = array_keys($this->tags);

			foreach ($tags as $tag) {
				wpcf7_remove_form_tag(strval($tag));
			}
		}

		/**
		 * Ajoute les différents champs possibles à CF7
		 * Filtre wpcf7_init
		 *
		 * @param void
		 * @return void
		 */
		public function addTags() {
			foreach ($this->tags as $tag_key => $tag_settings) wpcf7_add_form_tag($tag_key, $tag_settings['callback'], $tag_settings['features']);
		}

		/**
		 * Enlève les boutons déjà présent pour générer les champs
		 * Filtre wpcf7_admin_init
		 *
		 * @param void
		 * @return void
		 */
		public function removeTagsGenerators() {
			remove_action( 'wpcf7_admin_init', 'wpcf7_add_tag_generator_text', 15, 0 );
			remove_action( 'wpcf7_admin_init', 'wpcf7_add_tag_generator_number', 18, 0 );
			remove_action( 'wpcf7_admin_init', 'wpcf7_add_tag_generator_date', 19, 0 );
			remove_action( 'wpcf7_admin_init', 'wpcf7_add_tag_generator_textarea', 20, 0 );
			remove_action( 'wpcf7_admin_init', 'wpcf7_add_tag_generator_menu', 25, 0 );
			remove_action( 'wpcf7_admin_init', 'wpcf7_add_tag_generator_checkbox_and_radio', 30, 0 );
			//remove_action( 'wpcf7_admin_init', 'wpcf7_add_tag_generator_acceptance', 35, 0 );
			//remove_action( 'wpcf7_admin_init', 'wpcf7_add_tag_generator_quiz', 40, 0 );
			//remove_action( 'wpcf7_admin_init', 'wpcf7_add_tag_generator_captcha', 46, 0 );
			remove_action( 'wpcf7_admin_init', 'wpcf7_add_tag_generator_file', 50, 0 );
			//remove_action( 'wpcf7_admin_init', 'wpcf7_add_tag_generator_submit', 55, 0 );
		}

		/**
		 * Ajoute les différents boutons pour générer les champs
		 * Filtre wpcf7_admin_init
		 *
		 * @param void
		 * @return void
		 */
		public function addTagsGenerators() {
			$tag_generator = \WPCF7_TagGenerator::get_instance();

			foreach ($this->tags as $tag_key => $tag_settings) {
				$tag_name = ( '*' === substr( $tag_key, -1 ) ) ? substr( $tag_key, 0, -1 ) : $tag_key;
				$tag_generator->add($tag_name, $tag_settings['label'], array($this, 'addTagGenerator') );
			}
		}

		/**
		 * Ajoute les tags simple
		 *
		 * @param WPCF7_FormTag $tag Le nom du tag
		 * @return string Code HTML du tag
		 */
		public function addTagSimple($tag) {

			if ( empty( $tag->name ) ) return '';

			$validation_error = wpcf7_get_validation_error( $tag->name );
			$atts             = array('echo' => false, 'always_show_label' => true);

			if ( $validation_error ) {
				$atts['error'] = true;
				$atts['error_text'] = $validation_error;
			}

			$atts['type'] = $tag->basetype;

			$atts['name'] = $tag->name;
			if ( $tag->has_option( 'readonly' ) ) $atts['readonly'] = true;
			if ( $tag->has_option( 'disabled' ) ) $atts['disabled'] = true;
			if ( $tag->has_option( 'autofocus' ) ) $atts['focus'] = true;
			if ( !$tag->is_required() ) $atts['required'] = false;
			if ($tag->get_option( 'autocomplete', '[-0-9a-zA-Z]+', true )) $atts['autocomplete'] = $tag->get_option( 'autocomplete', '[-0-9a-zA-Z]+', true );
			if ($tag->get_option( 'rows', '[0-9]+', true )) $atts['rows'] = $tag->get_option( 'rows', '[0-9]+', true );

			if ($tag->basetype=='date') {
				$letter_day = 'j';
				$letter_month = 'm';
				$letter_year = 'a';
				$dizaine_year = substr(date('Y'), 2, 1);
				$unit_year = substr(date('Y'), 3);
				$atts['format'] = '%('.$letter_day.')[0-3]R%('.$letter_day.'){ return (s[0]["value"]=="3") ? (c=="0"||c=="1") : /^[0-9]$/.test(c) ; }f/%('.$letter_month.')[0-1]R%('.$letter_month.'){ return (s[2]["value"]=="1") ? (c=="0"||c=="1"||c=="2") : /^[0-9]$/.test(c) ; }f/%('.$letter_year.')[2-9]R%('.$letter_year.')i%('.$letter_year.'){ return (s[4]["value"]=="2"&&s[5]["value"]=="0") ? /^['.$dizaine_year.'-9]$/.test(c) : /^[0-9]$/.test(c) ; }f%('.$letter_year.'){ return (s[4]["value"]=="2"&&s[5]["value"]=="0"&&s[6]["value"]=="'.$dizaine_year.'") ? /^['.$unit_year.'-9]$/.test(c) : /^[0-9]$/.test(c) ; }f';
				$atts['datepicker'] = array('format'=>'d/m/Y', 'pikaday_options'=>array('enableSelectionDaysInNextAndPreviousMonths'=>true));
			}

			if ($tag->basetype=='date' && $tag->get_option( 'min', 'date', true )!==false) {
				$date = date_create_from_format('Y-m-d', $tag->get_option( 'min', 'date', true ));
				if (is_a($date, 'DateTime')) $atts['datepicker']['min_date'] = $date;
			} else if ($tag->basetype=='date' && $tag->get_option( 'min', 'int', true )!==false) {
				$date = new \DateTime();
				if (intval($tag->get_option( 'min', 'int', true ))>0) { $date->modify('+'.intval($tag->get_option( 'min', 'int', true )).' day'); }
				else if (intval($tag->get_option( 'min', 'int', true ))<0) { $date->modify(intval($tag->get_option( 'min', 'int', true )).' day'); }
				if (is_a($date, 'DateTime')) $atts['datepicker']['min_date'] = $date;
			} else if ($tag->get_option( 'min', '[0-9]+', true )) {
				$atts['min'] = intval($tag->get_option( 'min', '[0-9]+', true ));
			}

			if ($tag->basetype=='date' && $tag->get_option( 'max', 'date', true )!==false) {
				$date = date_create_from_format('Y-m-d', $tag->get_option( 'max', 'date', true ));
				if (is_a($date, 'DateTime')) $atts['datepicker']['max_date'] = $date;
			} else if ($tag->basetype=='date' && $tag->get_option( 'max', 'int', true )!==false) {
				$date = new \DateTime();
				if (intval($tag->get_option( 'max', 'int', true ))>0) { $date->modify('+'.intval($tag->get_option( 'max', 'int', true )).' day'); }
				else if (intval($tag->get_option( 'max', 'int', true ))<0) { $date->modify(intval($tag->get_option( 'max', 'int', true )).' day'); }
				if (is_a($date, 'DateTime')) $atts['datepicker']['max_date'] = $date;
			} else if ($tag->get_option( 'max', '[0-9]+', true )) {
				$atts['max'] = intval($tag->get_option( 'max', '[0-9]+', true ));
			}

			if ($tag->basetype=='file' && $tag->get_limit_option()>0) {
				$atts['size'] = intval($tag->get_limit_option());
			}

			if ($tag->basetype=='file' && $tag->has_option( 'multiple' )) {
				$atts['multiple'] = 'multiple';
			}

			if ($tag->basetype=='file' && $tag->get_option( 'maxfiles', 'int', true )!==false) {
				$atts['maxfiles'] = intval($tag->get_option( 'maxfiles', 'int', true ));
			}

			if ($tag->basetype=='file') {
				$value = (string) reset( $tag->values );
				$atts['accept'] = wpcf7_acceptable_filetypes($value, 'attr');
			} else {
				$value = (string) reset( $tag->values );
				if ( $tag->has_option( 'placeholder' ) or $tag->has_option( 'watermark' ) ) { $atts['placeholder'] = $value; $value = ''; }
				$value = $tag->get_default_option( $value );
				$value = wpcf7_get_hangover( $tag->name, $value );
				$atts['value'] = $value;
			}

			$label = $tag->content;

			if (isset($tag->content) && !empty($tag->content)) $atts['label'] = trim($label);

			$html = scf_input($atts);

			return $html;
		}

		/**
		 * Ajoute les tags avancé
		 *
		 * @param WPCF7_FormTag $tag Le nom du tag
		 * @return string Code HTML du tag
		 */
		public function addTagAdvanced($tag) {

			if ( empty( $tag->name ) ) return '';

			$validation_error = wpcf7_get_validation_error( $tag->name );
			$atts             = array('echo' => false, 'always_show_label' => true);

			if ( $validation_error ) {
				$atts['error'] = true;
				$atts['error_text'] = $validation_error;
			}

			$atts['type'] = $tag->basetype;

			$multiple = $tag->has_option( 'multiple' );

			$atts['name'] = $tag->name . ( $multiple||$tag->basetype==='checkbox' ? '[]' : '' );
			if ( $tag->has_option( 'readonly' ) ) $atts['readonly'] = true;
			if ( $tag->has_option( 'disabled' ) ) $atts['disabled'] = true;
			if ( $tag->has_option( 'autofocus' ) ) $atts['focus'] = true;
			if ( !$tag->is_required() ) $atts['required'] = false;
			if ( $multiple ) $atts['multiple'] = true;

			if ( $data = (array) $tag->get_data_option() ) {
				$tag->values = array_merge( $tag->values, array_values( $data ) );
				$tag->labels = array_merge( $tag->labels, array_values( $data ) );
			}

			$values = $tag->values;
			$labels = $tag->labels;

			$default_choice = $tag->get_default_option( null, array(
				'multiple' => $multiple,
			) );

			$hangover = wpcf7_get_hangover( $tag->name );

			foreach ( $values as $key => $value ) {
				if ( $hangover ) {
					$selected = in_array( $value, (array) $hangover, true );
				} else {
					$selected = in_array( $value, (array) $default_choice, true );
				}

				if ($selected) $atts['value'] = $value;

				$label = isset( $labels[$key] ) ? $labels[$key] : $value;

				$atts['options'][]  = array(
					'value' => $value,
					'label' => $label,
				);
			}

			if (isset($tag->content) && !empty($tag->content)) $atts['label'] = $tag->content;

			$html = scf_input($atts);

			return $html;
		}

		/**
		 * Valide la date
		 *
		 * @param bool $result Si la date est valide
		 * @param WPCF7_FormTag $tag Le nom du tag
		 * @return string Code HTML du tag
		 */
		public function validateDate($result, $tag) {
			remove_filter( 'wpcf7_validate_date', 'wpcf7_date_validation_filter' );
			remove_filter( 'wpcf7_validate_date*', 'wpcf7_date_validation_filter' );

			$result = new \WPCF7_Validation();

			$name = $tag->name;

			$min = '';
			$max = '';

			if ($tag->get_option( 'min', 'date', true )!==false) {
				$date = date_create_from_format('Y-m-d', $tag->get_option( 'min', 'date', true ));
				if (is_a($date, 'DateTime')) $min = $date;
			} else if ($tag->get_option( 'min', 'int', true )!==false) {
				$date = new \DateTime();
				if (intval($tag->get_option( 'min', 'int', true ))>0) { $date->modify('+'.intval($tag->get_option( 'min', 'int', true )).' day'); }
				else if (intval($tag->get_option( 'min', 'int', true ))<0) { $date->modify(intval($tag->get_option( 'min', 'int', true )).' day'); }
				if (is_a($date, 'DateTime')) $min = $date;
			}

			if ($tag->get_option( 'max', 'date', true )!==false) {
				$date = date_create_from_format('Y-m-d', $tag->get_option( 'max', 'date', true ));
				if (is_a($date, 'DateTime')) $max = $date;
			} else if ($tag->get_option( 'max', 'int', true )!==false) {
				$date = new \DateTime();
				if (intval($tag->get_option( 'max', 'int', true ))>0) { $date->modify('+'.intval($tag->get_option( 'max', 'int', true )).' day'); }
				else if (intval($tag->get_option( 'max', 'int', true ))<0) { $date->modify(intval($tag->get_option( 'max', 'int', true )).' day'); }
				if (is_a($date, 'DateTime')) $max = $date;
			}

			$post_name = sanitize_text_field($_POST[$name]);
			$val = isset( $post_name )
				? trim( strtr( (string) $post_name, "\n", " " ) )
				: '';
			$value = date_create_from_format('d/m/Y', $val);
			if ($value===false) {
				$value = date_create_from_format('Y-m-d', $val);
			}

			if ( $tag->is_required() and '' === $value ) {
				$result->invalidate( $tag, wpcf7_get_message( 'invalid_required' ) );
			} elseif ( '' !== $value and ! is_a( $value, 'DateTime' ) ) {
				$result->invalidate( $tag, wpcf7_get_message( 'invalid_date' ) );
			} elseif ( '' !== $value and ! empty( $min ) and $value < $min ) {
				$result->invalidate( $tag, wpcf7_get_message( 'date_too_early' ) );
			} elseif ( '' !== $value and ! empty( $max ) and $max < $value ) {
				$result->invalidate( $tag, wpcf7_get_message( 'date_too_late' ) );
			}

			return $result;
		}

		/**
		 * Renvoie la liste des fichiers uploadés
		 *
		 * @return array
		 */
		public function getUploadedFiles() {
			return $this->uploaded_files;
		}

		/**
		 * Renvoie la liste des fichiers uploadés
		 *
		 * @return array
		 */
		public function getSubmission() {
			return $this->submission;
		}

		/**
		 * Valide le fichier
		 * Crédit : Contact form 7
		 * Reproduit la validation de contact form 7 pour eviter
		 *
		 * @param bool $result Si la date est valide
		 * @param WPCF7_FormTag $tag Le nom du tag
		 * @return string Code HTML du tag
		 */
		public function validateFile($result, $tag, $args = '') {
			if (!is_array($args) || !isset($args['uploaded_files']) || is_wp_error( $args['uploaded_files'] ) || empty( $_FILES[$tag->name] )) return $result;

			$that = \WPCF7_Submission::get_instance();
			$this->submission = $that;

			$file = $_FILES[$tag->name];

			$args = array(
				'tag' => $tag,
				'name' => $tag->name,
				'required' => $tag->is_required(),
				'filetypes' => $tag->get_option( 'filetypes' ),
				'limit' => $tag->get_limit_option(),
				'schema' => $that->get_contact_form()->get_schema(),
			);

			$new_files = $this->custom_wpcf7_unship_uploaded_file( $file, $args );

			if ( is_wp_error( $new_files ) ) {
				$result->invalidate( $tag, $new_files );
			} else {
				if ( wpcf7_is_name( $tag->name ) ) {
					$paths = (array) $new_files;
					$uploaded_files = array();
					$hash_strings = array();

					foreach ( $paths as $path ) {
						if ( @is_file( $path ) and @is_readable( $path ) ) {
							$uploaded_files[] = $path;
							$hash_strings[] = md5_file( $path );
							add_filter( 'scf2acf_use_scf_uploaded_files', '__return_true' );
						}
					}

					$this->uploaded_files[$tag->name] = $uploaded_files;

					if ( empty( $this->posted_data[$tag->name] ) ) {
						$this->posted_data[$tag->name] = implode( ' ', $hash_strings );
					}
				}
			}

			return $result;
		}

		public function putFileInMail( $replaced, $submitted, $html, $mail_tag ) {
			$uploaded_files = $this->uploaded_files;
			$name = $mail_tag->field_name();

			if ( ! empty( $uploaded_files[$name] ) ) {
				$paths = (array) $uploaded_files[$name];
				$paths = array_map( 'wp_basename', $paths );

				$replaced = wpcf7_flat_join( $paths, array(
					'separator' => wp_get_list_item_separator(),
				) );
			}

			return $replaced;
		}

		/**
		 * Ajoute les fichier en attachment des mails
		 *
		 * @param xxx $components
		 * @param xxx $contact_form
		 * @param xxx $mail
		 * @return void
		 */
		public function replaceAttachmentsInMail( $components, $contact_form, $mail ) {
			$template = $mail->get( 'attachments' );
			$attachments = $components['attachments'];
			$uploaded_files = $this->uploaded_files;

			foreach ( (array) $uploaded_files as $name => $paths ) {
				if ( false !== strpos( $template, "[{$name}]" ) ) {
					$attachments = array_merge( $attachments, (array) $paths );
				}
			}

			$components['attachments'] = $attachments;

			return $components;
		}

		/**
		 * Fork de la fonction wpcf7_unship_uploaded_file de CF7
		 * Enlève la vérification de is_uploaded_file
		 *
		 * @param xxx $contact_form
		 * @param xxx $args
		 * @return void
		 */
		private function custom_wpcf7_unship_uploaded_file( $file, $args = '' ) {
			$args = wp_parse_args( $args, array(
				'required' => false,
				'filetypes' => '',
				'limit' => MB_IN_BYTES,
			) );

			foreach ( array( 'name', 'size', 'tmp_name', 'error' ) as $key ) {
				if ( ! isset( $file[$key] ) ) {
					$file[$key] = array();
				}
			}

			$names = wpcf7_array_flatten( $file['name'] );
			$sizes = wpcf7_array_flatten( $file['size'] );
			$tmp_names = wpcf7_array_flatten( $file['tmp_name'] );
			$errors = wpcf7_array_flatten( $file['error'] );

			foreach ( $errors as $error ) {
				if ( ! empty( $error ) and UPLOAD_ERR_NO_FILE !== $error ) {
					return new \WP_Error( 'wpcf7_upload_failed_php_error',
						wpcf7_get_message( 'upload_failed_php_error' )
					);
				}
			}

			if ( isset( $args['schema'] ) and isset( $args['name'] ) ) {
				$result = $args['schema']->validate( array(
					'file' => true,
					'field' => $args['name'],
				) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}

			// Move uploaded file to tmp dir
			$uploads_dir = wpcf7_upload_tmp_dir();
			$uploads_dir = wpcf7_maybe_add_random_dir( $uploads_dir );

			$uploaded_files = array();

			foreach ( $names as $key => $name ) {
				$tmp_name = $tmp_names[$key];

				/* Verifie si l'url temporaire existe et un fichier uploader via POST dans la requete (uniquement si le fichier n'est pas un cache de SCF, sinon ignore cette verification) */
				$dir = wp_get_upload_dir();
				$path = $dir['basedir'] . '/scf_uploads/';
				if ( empty( $tmp_name ) or (! is_uploaded_file( $tmp_name ) && strpos($tmp_name, $path)!==0) ) {
					continue;
				}

				$filename = $name;
				$filename = wpcf7_canonicalize( $filename, array( 'strto' => 'as-is' ) );
				$filename = wpcf7_antiscript_file_name( $filename );

				$filename = apply_filters( 'wpcf7_upload_file_name',
					$filename, $name, $args
				);

				$filename = wp_unique_filename( $uploads_dir, $filename );
				$new_file = path_join( $uploads_dir, $filename );

				if ( false === @rename( $tmp_name, $new_file ) ) {
					return new \WP_Error( 'wpcf7_upload_failed',
						wpcf7_get_message( 'upload_failed' )
					);
				}

				/* Lance la tache CRON pour la suppression des fichiers */
				$dir = wp_get_upload_dir();
				$id_file = str_replace($dir['basedir'] . '/scf_uploads/', '', dirname($tmp_name));
				$id_file = rtrim($id_file, '/');
				$timestamp_deleting = time() + intval(apply_filters('scf_file_deleting_timeout', 1800));
				wp_schedule_single_event($timestamp_deleting, 'scf_deleting_file', array(strval($id_file)));

				// Make sure the uploaded file is only readable for the owner process
				chmod( $new_file, 0400 );

				$uploaded_files[] = $new_file;
			}

			return $uploaded_files;
		}

		/**
		 * Supprime la schema rule "file" et remplace par les bons type de fichiers du champ
		 *
		 * @param xxx $schema
		 * @param xxx $contact_form
		 * @return void
		 */
		public function addFileRulesTypes( $schema, $contact_form ) {
			remove_action('wpcf7_swv_create_schema', 'wpcf7_swv_add_file_rules', 10, 2);
			
			$tags = $contact_form->scan_form_tags( array(
				'basetype' => array( 'file' ),
			) );

			foreach ( $tags as $tag ) {
				if ( $tag->is_required() ) {
					$schema->add_rule(
						wpcf7_swv_create_rule( 'requiredfile', array(
							'field' => $tag->name,
							'error' => wpcf7_get_message( 'invalid_required' ),
						) )
					);
				}

				$schema->add_rule(
					wpcf7_swv_create_rule( 'file', array(
						'field' => $tag->name,
						'accept' => explode( ',', wpcf7_acceptable_filetypes(
							(string) reset( $tag->values ), 'attr'
						) ),
						'error' => wpcf7_get_message( 'upload_file_type_invalid' ),
					) )
				);

				$schema->add_rule(
					wpcf7_swv_create_rule( 'maxfilesize', array(
						'field' => $tag->name,
						'threshold' => $tag->get_limit_option(),
						'error' => wpcf7_get_message( 'upload_file_too_large' ),
					) )
				);
			}
		}

		/**
		 * Supprime la schema rule "file" et remplace par les bons type de fichiers du champ
		 *
		 * @param xxx $schema
		 * @param xxx $contact_form
		 * @return void
		 */
		public function addSelectRulesTypes( $schema, $contact_form ) {
			remove_action('wpcf7_swv_create_schema', 'wpcf7_swv_add_select_enum_rules', 20, 2);

			$tags = $contact_form->scan_form_tags( array(
				'basetype' => array( 'select' ),
			) );

			$values = array_reduce(
				$tags,
				function ( $values, $tag ) {
					if ( ! isset( $values[$tag->name] ) ) {
						$values[$tag->name] = array();
					}

					$tag_values = array_merge(
						(array) $tag->values,
						(array) $tag->get_data_option()
					);

					if ( $tag->has_option( 'first_as_label' ) ) {
						$tag_values = array_slice( $tag_values, 1 );
					}

					$values[$tag->name] = array_merge(
						$values[$tag->name],
						$tag_values
					);

					return $values;
				},
				array()
			);

			foreach ( $values as $field => $field_values ) {
				$field_values = array_map(
					static function ( $value ) {
						return html_entity_decode(
							(string) $value,
							ENT_QUOTES | ENT_HTML5,
							'UTF-8'
						);
					},
					$field_values
				);

				$field_values = array_filter(
					array_unique( $field_values ),
					static function ( $value ) {
						return '' !== $value;
					}
				);

				$field_values = apply_filters('scf_select_field_values', $field_values, $contact_form);
				
				$schema->add_rule(
					wpcf7_swv_create_rule( 'enum', array(
						'field' => $field,
						'accept' => array_values( $field_values ),
						'error' => $contact_form->filter_message(
							__( "Undefined value was submitted through this field.", 'contact-form-7' )
						),
					) )
				);
			}
		}

		/**
		 * Crée l'interface de génération de tag
		 *
		 * @param xxx $contact_form
		 * @param xxx $args
		 * @return void
		 */
		public function addTagGenerator($contact_form, $args = '') {
			$args = wp_parse_args( $args, array() );
			$type = $args['id'];

			if ( !in_array($type, array_keys($this->tags)) ) {
				$type = 'text';
			}

			?><div class="control-box">
				<fieldset>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><?php echo esc_html( __( 'Type de champ', 'simple-coherent-form' ) ); ?></th>
								<td>
									<fieldset>
										<legend class="screen-reader-text"><?php echo esc_html( __( 'Type de champ', 'simple-coherent-form' ) ); ?></legend>
										<label><input type="checkbox" name="required" /> <?php echo esc_html( __( 'Champ obligatoire', 'simple-coherent-form' ) ); ?></label>
									</fieldset>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php echo esc_html( __( 'Nom', 'simple-coherent-form' ) ); ?></label>
								</th>
								<td>
									<input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $args['content'] . '-content' ); ?>"><?php echo esc_html( __( 'Libellé', 'simple-coherent-form' ) ); ?></label>
								</th>
								<td>
									<input type="text" name="content" class="tg-label oneline" id="<?php echo esc_attr( $args['content'] . '-content' ); ?>" />
								</td>
							</tr><?php

							switch ($type) {
								case 'file':
									?><tr>
										<th scope="row">
											<label for="<?php echo esc_attr( $args['content'] . '-values' ); ?>"><?php echo esc_html( __( 'Formats acceptés', 'simple-coherent-form' ) ); ?></label>
										</th>
										<td>
											<input type="text" name="values" class="oneline" id="<?php echo esc_attr( $args['content'] . '-accept' ); ?>" /><br />
											<label for="<?php echo esc_attr( $args['content'] . '-accept' ); ?>"><span class="description"><?php echo esc_html( __( "Renseignez les types MIME et/ou les extensions acceptés pour ce(s) fichier(s). (ex: text/plain, .jpg, .pdf, ...)", 'simple-coherent-form' ) ); ?></span></label>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="<?php echo esc_attr( $args['content'] . '-limit' ); ?>"><?php printf( esc_html( __( 'Taille du fichier %s(en bytes)', 'simple-coherent-form' ) ), '<br/>' ); ?></label>
										</th>
										<td>
											<input type="text" name="limit" class="filesize option oneline" id="<?php echo esc_attr( $args['content'] . '-limit' ); ?>" />
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="<?php echo esc_attr( $args['content'] . '-multiple' ); ?>"><?php echo esc_html( __( 'Multiple', 'simple-coherent-form' ) ); ?></label>
										</th>
										<td>
											<label><input type="checkbox" name="multiple" class="option" id="<?php echo esc_attr( $args['content'] . '-multiple' ); ?>" /> <?php echo esc_html( __( 'Permettre la selection multiple de fichiers.', 'simple-coherent-form' ) ); ?></label>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="<?php echo esc_attr( $args['content'] . '-maxfiles' ); ?>"><?php printf( esc_html( __( 'Nombre de fichiers maximum', 'simple-coherent-form' ) ), '<br/>' ); ?></label>
										</th>
										<td>
											<input type="number" name="maxfiles" class="option oneline" id="<?php echo esc_attr( $args['content'] . '-maxfiles' ); ?>" />
											<div><span class="description"><?php echo esc_html( __( "Laissez vide ou 0 pour ne pas mettre de limite.", 'simple-coherent-form' ) ); ?></span></div>
										</td>
									</tr><?php
								break;

								case 'select':
								case 'checkbox':
									?><tr>
										<th scope="row">
											<label for="<?php echo esc_attr( $args['content'] . '-values' ); ?>"><?php echo esc_html( __( 'Options', 'simple-coherent-form' ) ); ?></label>
										</th>
										<td>
											<label>
												<textarea name="values" class="values" id="<?php echo esc_attr( $args['content'] . '-values' ); ?>" ></textarea>
												<span class="description"><?php echo esc_html( __( 'Une option par ligne.', 'simple-coherent-form' ) ); ?></span>
											</label>
											<br/>
											<label><input type="checkbox" name="multiple" class="option" id="<?php echo esc_attr( $args['content'] . '-multiple' ); ?>" /> <?php echo esc_html( __( 'Permettre les sélections multiples.', 'simple-coherent-form' ) ); ?></label>
										</td>
									</tr><?php
								break;

								case 'radio':
									?><tr>
										<th scope="row">
											<label for="<?php echo esc_attr( $args['content'] . '-values' ); ?>"><?php echo esc_html( __( 'Options', 'simple-coherent-form' ) ); ?></label>
										</th>
										<td>
											<label>
												<textarea name="values" class="values" id="<?php echo esc_attr( $args['content'] . '-values' ); ?>" ></textarea>
												<span class="description"><?php echo esc_html( __( 'Une option par ligne.', 'simple-coherent-form' ) ); ?></span>
											</label>
										</td>
									</tr><?php
								break;
								
								default:
									?><tr>
										<th scope="row">
											<label for="<?php echo esc_attr( $args['content'] . '-values' ); ?>"><?php echo esc_html( __( 'Valeur par défaut', 'simple-coherent-form' ) ); ?></label>
										</th>
										<td>
											<input type="text" name="values" class="oneline" id="<?php echo esc_attr( $args['content'] . '-values' ); ?>" /><br />
											<label><input type="checkbox" name="placeholder" class="option" /> <?php echo esc_html( __( 'Utilisez ce texte comme texte indicatif du champ.', 'simple-coherent-form' ) ); ?></label>
										</td>
									</tr><?php
								break;
							}

						?></tbody>
					</table>
				</fieldset>
			</div>
			<div class="insert-box">
				<input type="text" name="<?php echo $type; ?>" class="tag code" readonly="readonly" onfocus="this.select()" />
				<div class="submitbox">
					<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insérer la balise', 'contact-form-7' ) ); ?>" />
				</div>
				<br class="clear" />
				<p class="description mail-tag">
					<label for="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>"><?php echo sprintf( esc_html( __( "Pour utiliser la valeur de ce champ dans un champ d’e-mail, vous devez insérer le nom de balise correspondante (%s) dans l’onglet E-mail.", 'contact-form-7' ) ), '<strong><span class="mail-tag"></span></strong>' ); ?><input type="text" class="mail-tag code hidden" readonly="readonly" id="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>" /></label>
				</p>
			</div><?php
		}
	}

	CF7::getInstance();
}
