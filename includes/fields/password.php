<?php

declare(strict_types=1);

namespace SCF\Fields;

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Fields\SCFPassword')) {

	/**
	 * Classe du champ Password
	 */
	class SCFPassword {

		/**
		 * Constructeur de la classe
		 * Accroche les hooks
		 * Définie les constantes
		 */
		function __construct() {
			add_filter( 'scf_default_args', array($this, 'addDefaultArgs') );
			add_filter( 'scf_script_inline', array($this, 'addScriptInline') );
			add_filter( 'scf_types_availables', array($this, 'init') );
			add_filter( 'scf_types_with_group_label', array($this, 'groupLabel') );
			add_action( 'scf_enqueue_scripts_password', array($this, 'enqueueScripts') );
			add_action( 'scf_field_password', array($this, 'displayHtml') );
			add_action( 'scf_field_password', array($this, 'displayValidation'), 20 );
			add_action( 'scf_field_password', array($this, 'displayPasswordShow'), 30 );
			add_action( 'scf_before_description_password', array($this, 'displayHint') );

			define('SCF_NONE',		0x00);
			define('SCF_UPPERCASE',	0x01);
			define('SCF_LOWERCASE',	0x02);
			define('SCF_NUMBER',	0x04);
			define('SCF_SPECIAL',	0x08);
			define('SCF_LENGTH',	0x10);
			define('SCF_COMMON',	0x20);
			define('SCF_ALL',		SCF_UPPERCASE | SCF_LOWERCASE | SCF_NUMBER | SCF_SPECIAL | SCF_LENGTH | SCF_COMMON);
		}

		/**
		 * Ajoute les arguments par défaut nécessaires au type Password
		 * Filtre scf_default_args
		 *
		 * @param array $args Arguments par défaut
		 * @return array Arguments modifiés
		 */
		public function addDefaultArgs($args) {
			$args['show_password'] = true;
			$args['password_hint'] = true;
			$args['force_required'] = SCF_UPPERCASE | SCF_LOWERCASE | SCF_NUMBER | SCF_LENGTH;
			return $args;
		}

		/**
		 * Ajoute les scripts inline nécessaires au type Password
		 * Filtre scf_script_inline
		 *
		 * @param array $scripts Liste des variables JS inline à intégrer
		 * @return array Liste des variables JS modifiées
		 */
		public function addScriptInline($scripts) {
			$scripts['scf_pass_force'] = apply_filters('scf_password_strength', array(
				'scf_check_password_uppercase' => SCF_UPPERCASE,
				'scf_check_password_lowercase' => SCF_LOWERCASE,
				'scf_check_password_number' => SCF_NUMBER,
				'scf_check_password_special' => SCF_SPECIAL,
				'scf_check_password_length' => SCF_LENGTH,
				'scf_check_password_common' => SCF_COMMON,
			));
			return $scripts;
		}

		/**
		 * Initialise le type Password
		 * Filtre scf_types_availables
		 *
		 * @param string[] $types Listes des types disponibles
		 * @return string[] Types disponibles modifiés
		 */
		public function init($types) {
			$types[] = 'password';
			return $types;
		}

		/**
		 * Marque le type Password comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function groupLabel($types) {
			$types[] = 'password';
			return $types;
		}

		/**
		 * Enregistre le script des mots de passe les plus communs
		 * Action scf_enqueue_scripts_{$type}
		 *
		 * @param void
		 * @return void
		 */
		public function enqueueScripts() {
			wp_enqueue_script('scf-pass');
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

			$input_args['type'] = 'password';
			if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) $input_args['name'] = $args['name'];
			$input_args['value'] = (isset($args['value'])) ? strval($args['value']) : '' ;
			$input_args['placeholder'] = (isset($args['placeholder'])) ? strval($args['placeholder']) : '' ;
			$input_args['autocomplete'] = (isset($args['autocomplete'])) ? strval($args['autocomplete']) : 'on' ;
			if (isset($args['readonly']) && $args['readonly']) $input_args['readonly'] = 'readonly';
			if (isset($args['focus']) && $args['focus']) $input_args['autofocus'] = 'autofocus';
			if (isset($args['disabled']) && $args['disabled'] && !in_array($args['disabled'], array('on_pointer', 'on_touchable'))) $input_args['disabled'] = 'disabled';
			if (isset($args['force_required']) && intval($args['force_required'])>0 && isset($args['password_hint']) && $args['password_hint']) $input_args['data-scf-force'] = strval($args['force_required']);
			if (isset($args['format']) && !empty($args['format'])) $input_args['data-format'] = $args['format'];
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
		 * @param array $args Arguments du champ
		 * @return void
		 */
		public function displayValidation($args) {
			if (isset($args['show_validation']) && $args['show_validation']) { ?><span class="scf-valid"></span><?php }
		}

		/**
		 * Affiche le code HTML du bouton afficher/masquer le mot de passe
		 *
		 * @param array $args Arguments du champ
		 * @return void
		 */
		public function displayPasswordShow($args) {
			if (isset($args['show_password']) && $args['show_password']) {
				?><button
					type="button"
					class="scf-show-password"
					title="<?php echo esc_attr(__('Afficher/Masquer le mot de passe', 'simple-coherent-form')); ?>"
					tabindex="-1"
					aria-hidden="true"
					onmousedown="if(!document.body.classList.contains('scf-touch-device')){this.parentNode.firstElementChild.setAttribute('type', 'text');}"
					onmouseup="if(!document.body.classList.contains('scf-touch-device')){this.parentNode.firstElementChild.setAttribute('type', 'password');}"
					onclick="if(document.body.classList.contains('scf-touch-device')){p=this.parentNode.firstElementChild;p.type=p.type==='text'?'password':'text';}event.preventDefault();">
				</button><?php
			}
		}

		/**
		 * Affiche le code HTML de l'aide
		 *
		 * @param array $args Arguments du champ
		 * @return void
		 */
		public function displayHint($args) {
			if (isset($args['password_hint']) && $args['password_hint']) {

				$force = (isset($args['force_required']) && intval($args['force_required'])>0) ? $args['force_required'] : 0;

				$hints = apply_filters( 'scf_password_hints', array(
					'uppercase' => array('label' => __('Une majuscule', 'simple-coherent-form'),           'force' => SCF_UPPERCASE),
					'length'    => array('label' => __('8 caractères', 'simple-coherent-form'),            'force' => SCF_LENGTH),
					'lowercase' => array('label' => __('Une minuscule', 'simple-coherent-form'),           'force' => SCF_LOWERCASE),
					'special'   => array('label' => __('Un caractère spécial', 'simple-coherent-form'),    'force' => SCF_SPECIAL),
					'number'    => array('label' => __('Un chiffre', 'simple-coherent-form'),              'force' => SCF_NUMBER),
					'common'    => array('label' => __('Ne pas être trop commun', 'simple-coherent-form'), 'force' => SCF_COMMON),
				), $args );
				
				?><span class="scf-password-hint-details">
					<span class="scf-password-hint-details-text"><?php esc_html_e('Pour plus de sécurité, votre mot de passe doit comporter au moins&nbsp;:', 'simple-coherent-form'); ?></span><?php
					foreach ($hints as $key => $hint) {
						?><span class="scf-password-hint-details-item scf-password-hint-details-item-<?php echo esc_attr($key); ?>">
							<span class="scf-password-hint-details-item-valid"></span>
							<?php
								echo esc_html($hint['label']);
								echo (!intval($force & $hint['force'])) ? '<span class="scf-password-hint-details-item-optional">' . esc_html__('Recommandé', 'simple-coherent-form') . '</span>' : '';
							?>
						</span><?php
					}
				?></span><?php
			}
		}
	}

	new SCFPassword();
}
