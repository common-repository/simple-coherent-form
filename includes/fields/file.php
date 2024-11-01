<?php

declare(strict_types=1);

namespace SCF\Fields;

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('\SCF\Fields\SCFFile')) {

	/**
	 * Classe du champ File
	 */
	class SCFFile {

		private $salt = 'gcGbqzGrQI2PU2m6yfjAOIJDE8';

		private $id_folder = '';

		/**
		 * Constructeur de la classe
		 * Accroche les hooks
		 */
		function __construct() {
			add_filter( 'scf_default_args', array($this, 'addDefaultArgs') );
			add_filter( 'scf_types_availables', array($this, 'init') );
			add_filter( 'scf_types_with_group_label', array($this, 'groupLabel') );
			add_action( 'scf_field_file', array($this, 'displayHtml') );
			add_action( 'scf_field_file', array($this, 'displayValidation'), 20 );
			add_action( 'scf_before_description_file', array($this, 'displayHint') );
			add_action( 'plugins_loaded', array($this, 'formatFileTmpPost') );
			add_action( 'scf_deleting_file', array($this, 'deletingFile') );
			add_action( 'scf_generate_cron_deleting', array($this, 'generateCronJobs') );
			add_filter( 'scf_script_inline', array($this, 'addScriptNonce') );
			add_filter( 'scf_i18n', array($this, 'addTranslation') );

			/* AJAX */
			add_action( 'wp_ajax_scf_file_upload', array($this, 'uploadFile') );
			add_action( 'wp_ajax_nopriv_scf_file_upload', array($this, 'uploadFile') );
			add_action( 'wp_ajax_scf_get_id_upload', array($this, 'generateUploadFileID') );
			add_action( 'wp_ajax_nopriv_scf_get_id_upload', array($this, 'generateUploadFileID') );
			add_action( 'wp_ajax_scf_remove_file_upload', array($this, 'removeUploadedFile') );
			add_action( 'wp_ajax_nopriv_scf_remove_file_upload', array($this, 'removeUploadedFile') );
			add_action( 'wp_ajax_scf_check_files_exists', array($this, 'checkExistenceFiles') );
			add_action( 'wp_ajax_nopriv_scf_check_files_exists', array($this, 'checkExistenceFiles') );
		}

		/**
		 * Ajoute les arguments par défaut nécessaires au type File
		 * Filtre scf_default_args
		 *
		 * @param array $args Arguments par défaut
		 * @return array Arguments modifiés
		 */
		public function addDefaultArgs($args) {
			$args['accept'] = '*';
			$args['size'] = 10485760;
			$args['multiple'] = false;
			$args['maxfiles'] = 0;
			$args['file_hint'] = true;
			return $args;
		}

		/**
		 * Initialise le type File
		 * Filtre scf_types_availables
		 *
		 * @param string[] $types Listes des types disponibles
		 * @return string[] Types disponibles modifiés
		 */
		public function init($types) {
			$types[] = 'file';
			return $types;
		}

		/**
		 * Marque le type File comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function groupLabel($types) {
			$types[] = 'file';
			return $types;
		}

		/**
		 * Ajoute les arguments par défaut nécessaires au type File
		 * Filtre scf_default_args
		 *
		 * @param array $args Arguments par défaut
		 * @return array Arguments modifiés
		 */
		public function addScriptNonce($inline) {
			$inline['scf_check_files_exists_nonce'] = wp_create_nonce('scf_check_files_existence_hash');
			return $inline;
		}

		/**
		 * Ajoute les traductions utilisées dans le script
		 * Filtre scf_i18n
		 *
		 * @param array $translations Les traductions utilisées dans le script
		 * @return array Traductions modifiées
		 */
		public function addTranslation($translations) {
			$translations['file_item_remove_label'] = __('Supprimer le fichier', 'simple-coherent-form');
			$translations['file_item_remove_proceeding'] = __('En cours', 'simple-coherent-form');
			$translations['file_item_remove_done'] = __('Fichier supprimé', 'simple-coherent-form');
			return $translations;
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

			$real_name = '';
			$complete_name = '';

			if (isset($args['name']) && is_string($args['name']) && !empty($args['name'])) {
				$real_name = (substr($args['name'], -2) === '[]') ? substr($args['name'], 0, -2) : $args['name'];
				$complete_name = (isset($args['multiple']) && $args['multiple']) ? $real_name . '[]' : $real_name;
			}

			$input_args['name'] = $complete_name;

			$input_args['accept'] = (isset($args['accept']) && (is_array($args['accept']) || is_string($args['accept']))) ? implode(', ', ((is_string($args['accept'])) ? array($args['accept']) : $args['accept'])) : '*';
			if (empty($input_args['accept'])) $input_args['accept'] = '*';
			if (isset($args['size']) && is_int($args['size']) && $args['size']>0) $input_args['data-size'] = $args['size'];
			if (isset($args['readonly']) && $args['readonly']) $input_args['readonly'] = 'readonly';
			if (isset($args['disabled']) && $args['disabled'] && !in_array($args['disabled'], array('on_pointer', 'on_touchable'))) $input_args['disabled'] = 'disabled';
			if (!isset($input_args['tabindex'])) $input_args['tabindex'] = 0;
			if (isset($args['multiple']) && $args['multiple']) $input_args['multiple'] = 'multiple';
			if (isset($args['maxfiles']) && $args['maxfiles']>0) $input_args['data-maxfiles'] = intval($args['maxfiles']);

			$input_args = apply_filters('scf_input_args', $input_args, $args);

			$input_args['data-hash'] = wp_hash_password($this->salt . esc_attr($input_args['data-size']) . esc_attr($input_args['accept']));

			?><input type="file" <?php
				foreach ($input_args as $input_args_key => $input_args_value) { echo esc_attr($input_args_key) . '="' . esc_attr($input_args_value) . '" '; }
			?>/>
			<span class="scf-file-button"><?php esc_html_e('Choisir un fichier', 'simple-coherent-form'); ?></span>
			<input type="hidden" name="<?php echo esc_attr($real_name . '-file-tmp'); ?>"/>
			<span class="scf-files-number" data-number="0"><span class="scf-files-number-singular"><?php printf(_n('%s fichier selectionné', '%s fichiers selectionnés', 1, 'simple-coherent-form'), ''); ?></span><span class="scf-files-number-plural"><?php printf(_n('%s fichier selectionné', '%s fichiers selectionnés', 2, 'simple-coherent-form'), ''); ?></span></span>
			<ul class="scf-files-list" aria-label="<?php
				printf(
					esc_attr('Liste des fichiers téléversés pour le champ « %s »', 'simple-coherent-form'),
					(isset($args['label']) ? $args['label'] : $args['name'])
				);
			?>"></ul><?php 
		}

		/**
		 * Retrouve les champs fichier au sein des paramètres POST et modifie leur dossier tmp par celui d'upload
		 *
		 * @static
		 * @return void
		 */
		public static function formatFileTmpPost() {
			if (isset($_POST) && is_array($_POST) && count($_POST)>0 && !is_admin()) {
				foreach ($_POST as $key => $value) {
					if (is_string($key) && substr($key, -9) == '-file-tmp' && isset($_FILES[substr($key, 0, -9)]) && !empty($value)) {
						$files_id = array_filter(array_map('trim', array_filter(explode(', ', $value))));
						foreach ($files_id as $file_id) {

							/* Verifie l'existence du fichier */
							$dir = wp_get_upload_dir();
							$path = $dir['basedir'] . '/scf_uploads/' . $file_id . '/';

							if (is_dir($path) && count(scandir($path)) > 2) {
								foreach (scandir($path) as $file) {
									if ($file!=='..' && $file!=='.') {
										$file_name     = $file;
										$file_type     = mime_content_type($path . $file);
										$file_tmp_name = $path . $file;
										$file_error    = 0;
										$file_size     = filesize($path . $file);
										
										if (is_array($_FILES[substr($key, 0, -9)]['name'])) {
											$_FILES[substr($key, 0, -9)]['name'][] = $file_name;
										} else {
											$_FILES[substr($key, 0, -9)]['name'] = $file_name;
										}
										
										if (is_array($_FILES[substr($key, 0, -9)]['type'])) {
											$_FILES[substr($key, 0, -9)]['type'][] = $file_type;
										} else {
											$_FILES[substr($key, 0, -9)]['type'] = $file_type;
										}
										
										if (is_array($_FILES[substr($key, 0, -9)]['tmp_name'])) {
											$_FILES[substr($key, 0, -9)]['tmp_name'][] = $file_tmp_name;
										} else {
											$_FILES[substr($key, 0, -9)]['tmp_name'] = $file_tmp_name;
										}
										
										if (is_array($_FILES[substr($key, 0, -9)]['error'])) {
											$_FILES[substr($key, 0, -9)]['error'][] = $file_error;
										} else {
											$_FILES[substr($key, 0, -9)]['error'] = $file_error;
										}
										
										if (is_array($_FILES[substr($key, 0, -9)]['size'])) {
											$_FILES[substr($key, 0, -9)]['size'][] = $file_size;
										} else {
											$_FILES[substr($key, 0, -9)]['size'] = $file_size;
										}

										break;
									}
								}
							}
						}

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

		/**
		 * Affiche le code HTML de l'aide
		 *
		 * @param array $args Arguments du champ
		 * @return void
		 */
		public function displayHint($args) {
			if (isset($args['file_hint']) && $args['file_hint']) {

				$format_accepted = (isset($args['accept']) && (is_array($args['accept']) || is_string($args['accept']))) ? ((is_string($args['accept'])) ? explode(',', $args['accept']) : $args['accept']) : array('*');
				if (empty($format_accepted)) $format_accepted = array('*');
				$format_accepted = array_map('trim', $format_accepted);

				?><span class="scf-file-hint-details" aria-hidden="true"><?php
					if (!in_array('*', $format_accepted)) {

						/* Si un message générique est affiché pour tous le même format, masque les format sous-jacents */
						if (in_array('audio/*', $format_accepted)) {
							$format_accepted = array_filter($format_accepted, function($i) { return strpos($i, 'audio/')!==0; });
							$format_accepted[] = 'audio/*';
						}
						if (in_array('video/*', $format_accepted)) {
							$format_accepted = array_filter($format_accepted, function($i) { return strpos($i, 'video/')!==0; });
							$format_accepted[] = 'video/*';
						}
						if (in_array('text/*', $format_accepted)) {
							$format_accepted = array_filter($format_accepted, function($i) { return strpos($i, 'text/')!==0; });
							$format_accepted[] = 'text/*';
						}
						if (in_array('image/*', $format_accepted)) {
							$format_accepted = array_filter($format_accepted, function($i) { return strpos($i, 'image/')!==0; });
							$format_accepted[] = 'image/*';
						}
						if (in_array('font/*', $format_accepted)) {
							$format_accepted = array_filter($format_accepted, function($i) { return strpos($i, 'font/')!==0; });
							$format_accepted[] = 'font/*';
						}

						/* Rends les formats lisibles et supprime les doublons */
						$format_accepted = array_map(array($this, 'convertFormatReadable'), $format_accepted);
						$format_accepted = array_unique($format_accepted);

						/* Filtre les formats affichés */
						$format_accepted = apply_filters('scf_file_hint_formats', $format_accepted, $args);

						?><span class="scf-file-hint-details-text"><?php
							echo esc_html(_n(
								_n('Ce fichier doit être au format&nbsp;:', 'Ces fichiers doivent être au format&nbsp;:', ((isset($args['multiple']) && $args['multiple']) ? 2 : 1), 'simple-coherent-form'),

								_n('Les formats acceptés pour ce fichier sont&nbsp;:', 'Les formats acceptés pour ces fichiers sont&nbsp;:', ((isset($args['multiple']) && $args['multiple']) ? 2 : 1), 'simple-coherent-form'),

								/* Si un message générique est affiché, la phrase doit être au pluriel car plusieurs formats acceptés */
								((count($format_accepted)>1 || in_array('audio/*', $format_accepted) || in_array('video/*', $format_accepted) || in_array('text/*', $format_accepted) || in_array('image/*', $format_accepted) || in_array('font/*', $format_accepted)) ? 2 : 1)
							));
						?></span><?php

						foreach ($format_accepted as $format) {
							?><span class="scf-file-hint-details-item"><?php echo esc_html($format); ?></span><?php
						}
					}


					if (isset($args['size']) && is_int($args['size']) && $args['size']>0) {
						?><span class="scf-file-hint-details-text"><?php
							$size = strval($args['size']);

							/* Converti le format de Bytes à un format lisible */
							$units = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
							$factor = floor((strlen($size) - 1) / 3);
							$size = sprintf("%s", strval(floatval(number_format(floatval($size / pow(1024, $factor)), 2)))) . @$units[$factor];

							echo esc_html(sprintf(
								_n('Ce fichier ne doit pas excéder %s.', 'Ces fichiers ne doivent pas excéder %s chacun.', ((isset($args['multiple']) && $args['multiple']) ? 2 : 1), 'simple-coherent-form'),
								apply_filters('scf_file_hint_size', $size, $args)
							));
						?></span><?php
					}
					

					if (isset($args['multiple']) && $args['multiple'] && isset($args['maxfiles']) && is_int($args['maxfiles']) && $args['maxfiles']>0) {
						?><span class="scf-file-hint-details-text"><?php
							echo esc_html(sprintf(
								_n('%s fichier maximum.', '%s fichiers maximum.', $args['maxfiles'], 'simple-coherent-form'),
								apply_filters('scf_file_hint_maxfiles', $args['maxfiles'], $args)
							));
						?></span><?php
					}

				?></span><?php

				?><span class="scf-file-hint-details-screen-reader scf-label-screen-reader"><?php

					if (isset($args['multiple']) && $args['multiple']) {
						if (isset($args['maxfiles']) && is_int($args['maxfiles']) && $args['maxfiles']>0) {
							echo esc_html(sprintf(
								_n('%s fichier maximum.', '%s fichiers maximum.', $args['maxfiles'], 'simple-coherent-form'),
								apply_filters('scf_file_hint_maxfiles', $args['maxfiles'], $args)
							));
						} else {
							esc_html_e('Plusieurs fichiers autorisés.', 'simple-coherent-form');
						}
					}

					?> <?php

					if (isset($args['size']) && is_int($args['size']) && $args['size']>0) {
						$size = strval($args['size']);

						/* Converti le format de Bytes à un format lisible */
						$units = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
						$factor = floor((strlen($size) - 1) / 3);
						$size = sprintf("%s", strval(floatval(number_format(floatval($size / pow(1024, $factor)), 2)))) . @$units[$factor];

						echo esc_html(sprintf(
							_n('Ce fichier ne doit pas excéder %s.', 'Ces fichiers ne doivent pas excéder %s chacun.', ((isset($args['multiple']) && $args['multiple']) ? 2 : 1), 'simple-coherent-form'),
							apply_filters('scf_file_hint_size', $size, $args)
						));
					}

					?> <?php

					if (!in_array('*', $format_accepted)) {

						/* Si un message générique est affiché pour tous le même format, masque les format sous-jacents */
						if (in_array('audio/*', $format_accepted)) {
							$format_accepted = array_filter($format_accepted, function($i) { return strpos($i, 'audio/')!==0; });
							$format_accepted[] = 'audio/*';
						}
						if (in_array('video/*', $format_accepted)) {
							$format_accepted = array_filter($format_accepted, function($i) { return strpos($i, 'video/')!==0; });
							$format_accepted[] = 'video/*';
						}
						if (in_array('text/*', $format_accepted)) {
							$format_accepted = array_filter($format_accepted, function($i) { return strpos($i, 'text/')!==0; });
							$format_accepted[] = 'text/*';
						}
						if (in_array('image/*', $format_accepted)) {
							$format_accepted = array_filter($format_accepted, function($i) { return strpos($i, 'image/')!==0; });
							$format_accepted[] = 'image/*';
						}
						if (in_array('font/*', $format_accepted)) {
							$format_accepted = array_filter($format_accepted, function($i) { return strpos($i, 'font/')!==0; });
							$format_accepted[] = 'font/*';
						}

						/* Rends les formats lisibles et supprime les doublons */
						$format_accepted = array_map(array($this, 'convertFormatReadable'), $format_accepted);
						$format_accepted = array_unique($format_accepted);

						/* Filtre les formats affichés */
						$format_accepted = apply_filters('scf_file_hint_formats', $format_accepted, $args);

						echo esc_html(_n(
							'Format accepté&nbsp;:',
							'Formats acceptés&nbsp;:',

							/* Si un message générique est affiché, la phrase doit être au pluriel car plusieurs formats acceptés */
							((count($format_accepted)>1 || in_array('audio/*', $format_accepted) || in_array('video/*', $format_accepted) || in_array('text/*', $format_accepted) || in_array('image/*', $format_accepted) || in_array('font/*', $format_accepted)) ? 2 : 1),

							'simple-coherent-form'
						));

						?> <?php

						$first = true;
						foreach ($format_accepted as $format) {
							if ($first) {
								$first = false;
							} else {
								echo '; ';
							}
							echo esc_html($format);
						}

						echo '.';
					}

				?></span><?php
			}
		}

		/**
		 * Renvoie le format d'un fichier selon son type mime ou son extension
		 *
		 * @param string $format Le format au type mime ou extension
		 * @return string Le format lisible
		 */
		public static function convertFormatReadable($format) {
			$format = strtolower($format);
			$format_readable = $format;

			switch ($format) {


				/* GENERIQUE */

				case 'audio/*' :
					$format_readable = __('Tous les formats audio', 'simple-coherent-form'); break;
					
				case 'video/*' :
					$format_readable = __('Tous les formats video', 'simple-coherent-form'); break;
					
				case 'text/*' :
					$format_readable = __('Tous les formats de texte', 'simple-coherent-form'); break;
					
				case 'image/*' :
					$format_readable = __('Tous les formats d\'image', 'simple-coherent-form'); break;
					
				case 'font/*' :
					$format_readable = __('Tous les formats de police', 'simple-coherent-form'); break;


				/* AUDIO */

				case 'audio/aac':
				case 'audio/x-aac':
				case '.aac':
				case '.m4a' :
					$format_readable = __('Fichier audio AAC', 'simple-coherent-form'); break;

				case 'audio/midi':
				case '.midi':
				case '.mid':
				case '.kar':
				case '.rmi' :
					$format_readable = __('Fichier audio MIDI', 'simple-coherent-form'); break;

				case 'audio/ogg':
				case '.oga':
				case '.ogg':
				case '.spx' :
					$format_readable = __('Fichier audio OGG', 'simple-coherent-form'); break;

				case 'audio/x-wav':
				case 'audio/wav':
				case 'audio/vnd.wave':
				case 'audio/wave':
				case 'audio/x-pn-wav':
				case '.wav' :
					$format_readable = __('Fichier audio WAV', 'simple-coherent-form'); break;

				case 'audio/mpeg':
				case '.m2a':
				case '.m3a':
				case '.mp2':
				case '.mp2a':
				case '.mpga' :
					$format_readable = __('Fichier audio MPEG', 'simple-coherent-form'); break;

				case '.mp3' :
					$format_readable = __('Fichier audio MP3', 'simple-coherent-form'); break;

				case 'audio/mp4':
				case '.mp4a' :
					$format_readable = __('Fichier audio MP4', 'simple-coherent-form'); break;

				case 'audio/webm':
				case '.weba' :
					$format_readable = __('Fichier audio WEBM', 'simple-coherent-form'); break;

				case 'audio/opus':
				case '.opus' :
					$format_readable = __('Fichier audio OPUS', 'simple-coherent-form'); break;

				case 'audio/3gpp' :
					$format_readable = __('Fichier audio 3GPP', 'simple-coherent-form'); break;

				case 'audio/3gpp2' :
					$format_readable = __('Fichier audio 3GPP2', 'simple-coherent-form'); break;

				case 'audio/adpcm':
				case '.adp' :
					$format_readable = __('Fichier audio ADPCM', 'simple-coherent-form'); break;

				case 'audio/x-aac':
				case '.aac' :
					$format_readable = __('Fichier audio AAC', 'simple-coherent-form'); break;

				case 'audio/aiff':
				case 'audio/x-aiff':
				case '.aiff':
				case '.aif':
				case '.aifc':
				case '.aff' :
					$format_readable = __('Fichier audio AIF, AIFC, AIFF et AFF', 'simple-coherent-form'); break;

				case 'audio/flac':
				case 'audio/x-flac':
				case '.flac' :
					$format_readable = __('Fichier audio FLAC', 'simple-coherent-form'); break;

				case 'audio/x-matroska':
				case '.mka' :
					$format_readable = __('Fichier audio MKA', 'simple-coherent-form'); break;

				case 'audio/vnd.dece.audio':
				case '.uva' :
					$format_readable = __('Fichier audio UVA', 'simple-coherent-form'); break;

				case 'audio/vnd.digital-winds':
				case '.eol' :
					$format_readable = __('Fichier audio EOL', 'simple-coherent-form'); break;

				case 'audio/vnd.dra':
				case '.dra' :
					$format_readable = __('Fichier audio DRA', 'simple-coherent-form'); break;

				case 'audio/vnd.dts':
				case '.dts' :
					$format_readable = __('Fichier audio DTS', 'simple-coherent-form'); break;

				case 'audio/vnd.dts.hd':
				case '.dtshd' :
					$format_readable = __('Fichier audio DTS HD', 'simple-coherent-form'); break;

				case 'audio/vnd.rip':
				case '.rip' :
					$format_readable = __('Fichier audio RIP', 'simple-coherent-form'); break;

				case 'audio/vnd.lucent.voice':
				case '.lvp' :
					$format_readable = __('Fichier audio LVP', 'simple-coherent-form'); break;

				case 'audio/x-mpegurl':
				case '.m3u' :
					$format_readable = __('Fichier audio M3U', 'simple-coherent-form'); break;

				case 'audio/vnd.ms-playready.media.pya':
				case '.pya' :
					$format_readable = __('Fichier audio PYA', 'simple-coherent-form'); break;

				case 'audio/x-ms-wma':
				case '.wma' :
					$format_readable = __('Fichier audio WMA', 'simple-coherent-form'); break;

				case 'audio/x-ms-wax':
				case '.wax' :
					$format_readable = __('Fichier audio WAX', 'simple-coherent-form'); break;

				case 'audio/vnd.nuera.ecelp4800':
				case '.ecelp4800' :
					$format_readable = __('Fichier audio ECELP 4800', 'simple-coherent-form'); break;

				case 'audio/vnd.nuera.ecelp7470':
				case '.ecelp7470' :
					$format_readable = __('Fichier audio ECELP 7470', 'simple-coherent-form'); break;

				case 'audio/vnd.nuera.ecelp9600':
				case '.ecelp9600' :
					$format_readable = __('Fichier audio ECELP 9600', 'simple-coherent-form'); break;

				case 'audio/x-pn-realaudio':
				case '.ra':
				case '.ram' :
					$format_readable = __('Fichier audio RAM', 'simple-coherent-form'); break;

				case 'audio/x-pn-realaudio-plugin':
				case '.rmp' :
					$format_readable = __('Fichier audio RMP', 'simple-coherent-form'); break;

				case 'audio/basic':
				case '.au':
				case '.snd' :
					$format_readable = __('Fichier audio Sun', 'simple-coherent-form'); break;


				/* FONT */

				case 'font/otf':
				case 'application/x-font-otf':
				case '.otf' :
					$format_readable = __('Police OpenType', 'simple-coherent-form'); break;

				case 'font/ttf':
				case '.ttf' :
					$format_readable = __('Police TrueType', 'simple-coherent-form'); break;

				case 'font/woff':
				case '.woff' :
					$format_readable = __('Police WOFF', 'simple-coherent-form'); break;

				case 'font/woff2':
				case '.woff2' :
					$format_readable = __('Police WOFF2', 'simple-coherent-form'); break;

	
				/* IMAGE */

				case 'image/avif':
				case '.avif':
				case '.avifs' :
					$format_readable = __('Image AVIF', 'simple-coherent-form'); break;

				case 'image/bmp':
				case '.bmp' :
					$format_readable = __('Image Bitmap', 'simple-coherent-form'); break;

				case 'image/cgm':
				case '.cgm' :
					$format_readable = __('Image CGM', 'simple-coherent-form'); break;

				case 'image/g3fax':
				case '.g3' :
					$format_readable = __('Image G3', 'simple-coherent-form'); break;

				case 'image/ief':
				case '.ief' :
					$format_readable = __('Image IEF', 'simple-coherent-form'); break;

				case 'image/heic':
				case '.heic' :
					$format_readable = __('Image au format Apple (HEIC)', 'simple-coherent-form'); break;

				case '.heif' :
					$format_readable = __('Image HEIF', 'simple-coherent-form'); break;

				case 'image/gif':
				case '.gif' :
					$format_readable = __('Image GIF', 'simple-coherent-form'); break;

				case 'image/x-icon':
				case '.ico' :
					$format_readable = __('Icône', 'simple-coherent-form'); break;

				case 'image/jpeg':
				case 'image/pjpeg':
				case '.jpe':
				case '.jpg':
				case '.jpeg':
				case '.pjpg':
				case '.jfif':
				case '.jfif-tbnl':
				case '.jif':
				case '.jfi' :
					$format_readable = __('Image JPEG', 'simple-coherent-form'); break;

				case 'image/png':
				case 'image/x-png':
				case 'image/vnd.mozilla.apng':
				case '.png' :
					$format_readable = __('Image PNG', 'simple-coherent-form'); break;

				case 'image/prs.btif':
				case '.btif' :
					$format_readable = __('Image BTIF', 'simple-coherent-form'); break;

				case 'image/svg+xml':
				case '.svg':
				case '.svgz' :
					$format_readable = __('Image vectoriel (SVG)', 'simple-coherent-form'); break;

				case 'image/tiff':
				case '.tif':
				case '.tiff' :
					$format_readable = __('Image TIFF', 'simple-coherent-form'); break;

				case 'image/webp':
				case '.webp' :
					$format_readable = __('Image WEBP', 'simple-coherent-form'); break;

				case 'image/vnd.adobe.photoshop':
				case '.psd' :
					$format_readable = __('Image Photoshop (PSD)', 'simple-coherent-form'); break;
				
				case 'image/vnd.djvu':
				case '.djv, .djvu' :
					$format_readable = __('Image DJV', 'simple-coherent-form'); break;
				
				case 'image/vnd.dwg':
				case '.dwg' :
					$format_readable = __('Image DWG', 'simple-coherent-form'); break;
				
				case 'image/vnd.dxf':
				case '.dxf' :
					$format_readable = __('Image DXF', 'simple-coherent-form'); break;
				
				case 'image/vnd.fastbidsheet':
				case '.fbs' :
					$format_readable = __('Image FBS', 'simple-coherent-form'); break;
				
				case 'image/vnd.fpx':
				case '.fpx' :
					$format_readable = __('Image FPX', 'simple-coherent-form'); break;
				
				case 'image/vnd.fst':
				case '.fst' :
					$format_readable = __('Image FST', 'simple-coherent-form'); break;
				
				case 'image/vnd.fujixerox.edmics-mmr':
				case '.mmr' :
					$format_readable = __('Image MMR', 'simple-coherent-form'); break;
				
				case 'image/vnd.fujixerox.edmics-rlc':
				case '.rlc' :
					$format_readable = __('Image RLC', 'simple-coherent-form'); break;
				
				case 'image/vnd.ms-modi':
				case '.mdi' :
					$format_readable = __('Image MDI', 'simple-coherent-form'); break;
				
				case 'image/vnd.net-fpx':
				case '.npx' :
					$format_readable = __('Image NPX', 'simple-coherent-form'); break;
				
				case 'image/vnd.wap.wbmp':
				case '.wbmp' :
					$format_readable = __('Image WBMP', 'simple-coherent-form'); break;
				
				case 'image/vnd.xiff':
				case '.xif' :
					$format_readable = __('Image XIF', 'simple-coherent-form'); break;
				
				case 'image/x-adobe-dng':
				case '.dng' :
					$format_readable = __('Image DNG', 'simple-coherent-form'); break;
				
				case 'image/x-canon-cr2':
				case '.cr2' :
					$format_readable = __('Image CR2', 'simple-coherent-form'); break;
				
				case 'image/x-canon-crw':
				case '.crw' :
					$format_readable = __('Image CRW', 'simple-coherent-form'); break;
				
				case 'image/x-cmu-raster':
				case '.ras' :
					$format_readable = __('Image RAS', 'simple-coherent-form'); break;
				
				case 'image/x-cmx':
				case '.cmx' :
					$format_readable = __('Image CMX', 'simple-coherent-form'); break;
				
				case 'image/x-epson-erf':
				case '.erf' :
					$format_readable = __('Image ERF', 'simple-coherent-form'); break;
				
				case 'image/x-freehand':
				case '.fh, .fh4, .fh5, .fh7, .fhc' :
					$format_readable = __('Image Freehand', 'simple-coherent-form'); break;
				
				case 'image/x-fuji-raf':
				case '.raf' :
					$format_readable = __('Image RAF', 'simple-coherent-form'); break;
				
				case 'image/x-icns':
				case '.icns' :
					$format_readable = __('Image ICNS', 'simple-coherent-form'); break;
				
				case 'image/x-kodak-dcr':
				case '.dcr' :
					$format_readable = __('Image DCR', 'simple-coherent-form'); break;
				
				case 'image/x-kodak-k25':
				case '.k25' :
					$format_readable = __('Image Kodak K25', 'simple-coherent-form'); break;
				
				case 'image/x-kodak-kdc':
				case '.kdc' :
					$format_readable = __('Image Kodak KDC', 'simple-coherent-form'); break;
				
				case 'image/x-minolta-mrw':
				case '.mrw' :
					$format_readable = __('Image Minolta MRW', 'simple-coherent-form'); break;
				
				case 'image/x-nikon-nef':
				case '.nef' :
					$format_readable = __('Image Nikon NEF', 'simple-coherent-form'); break;
				
				case 'image/x-olympus-orf':
				case '.orf' :
					$format_readable = __('Image Olympus ORF', 'simple-coherent-form'); break;
				
				case 'image/x-panasonic-raw':
				case '.raw, .rw2, .rwl' :
					$format_readable = __('Image RAW', 'simple-coherent-form'); break;
				
				case 'image/x-pcx':
				case '.pcx' :
					$format_readable = __('Image PCX', 'simple-coherent-form'); break;
				
				case 'image/x-pentax-pef':
				case '.pef, .ptx' :
					$format_readable = __('Image Pentax PEF', 'simple-coherent-form'); break;
				
				case 'image/x-pict':
				case '.pct, .pic' :
					$format_readable = __('Image PCT et PIC', 'simple-coherent-form'); break;
				
				case 'image/x-portable-anymap':
				case '.pnm' :
					$format_readable = __('Image PNM', 'simple-coherent-form'); break;
				
				case 'image/x-portable-bitmap':
				case '.pbm' :
					$format_readable = __('Image PBM', 'simple-coherent-form'); break;
				
				case 'image/x-portable-graymap':
				case '.pgm' :
					$format_readable = __('Image PGM', 'simple-coherent-form'); break;
				
				case 'image/x-portable-pixmap':
				case '.ppm' :
					$format_readable = __('Image PPM', 'simple-coherent-form'); break;
				
				case 'image/x-rgb':
				case '.rgb' :
					$format_readable = __('Image RGB', 'simple-coherent-form'); break;
				
				case 'image/x-sigma-x3f':
				case '.x3f' :
					$format_readable = __('Image Sigma X3F', 'simple-coherent-form'); break;
				
				case 'image/x-sony-arw':
				case '.arw' :
					$format_readable = __('Image Sony ARW', 'simple-coherent-form'); break;
				
				case 'image/x-sony-sr2':
				case '.sr2' :
					$format_readable = __('Image Sony SR2', 'simple-coherent-form'); break;
				
				case 'image/x-sony-srf':
				case '.srf' :
					$format_readable = __('Image Sony SRF', 'simple-coherent-form'); break;
				
				case 'image/x-xbitmap':
				case '.xbm' :
					$format_readable = __('Image XBM', 'simple-coherent-form'); break;
				
				case 'image/x-xpixmap':
				case '.xpm' :
					$format_readable = __('Image XPM', 'simple-coherent-form'); break;
				
				case 'image/x-xwindowdump':
				case '.xwd' :
					$format_readable = __('Image XWD', 'simple-coherent-form'); break;


				/* VIDEO */

				case 'video/3gpp':
				case '.3gp' :
					$format_readable = __('Video 3GPP', 'simple-coherent-form'); break;

				case 'video/3gpp2':
				case '.3g2' :
					$format_readable = __('Video 3GPP2', 'simple-coherent-form'); break;

				case 'video/h261':
				case '.h261' :
					$format_readable = __('Video H261', 'simple-coherent-form'); break;

				case 'video/h263':
				case '.h263' :
					$format_readable = __('Video H263', 'simple-coherent-form'); break;

				case 'video/h264':
				case '.h264' :
					$format_readable = __('Video H264', 'simple-coherent-form'); break;

				case 'video/jpeg':
				case '.jpgv' :
					$format_readable = __('Video JPEG', 'simple-coherent-form'); break;

				case 'video/jpm':
				case '.jpgm':
				case '.jpm' :
					$format_readable = __('Video JPM', 'simple-coherent-form'); break;

				case 'video/mj2':
				case '.mj2':
				case '.mjp2' :
					$format_readable = __('Video MJ2', 'simple-coherent-form'); break;

				case 'video/mp2t':
				case '.ts' :
					$format_readable = __('Video TS', 'simple-coherent-form'); break;

				case 'video/mp4':
				case '.mp4':
				case '.mp4v':
				case '.mpg4' :
					$format_readable = __('Video MP4', 'simple-coherent-form'); break;

				case 'video/mpeg':
				case '.m1v':
				case '.m2v':
				case '.mpa':
				case '.mpe':
				case '.mpeg':
				case '.mpg' :
					$format_readable = __('Video MPEG', 'simple-coherent-form'); break;

				case 'video/ogg':
				case '.ogv' :
					$format_readable = __('Video OGG', 'simple-coherent-form'); break;


				case 'video/quicktime':
				case '.mov':
				case '.qt' :
					$format_readable = __('Vidéo Quicktime (MOV)', 'simple-coherent-form'); break;

				case 'video/vnd.fvt':
				case '.fvt' :
					$format_readable = __('Vidéo FVT', 'simple-coherent-form'); break;

				case 'video/vnd.mpegurl':
				case '.m4u':
				case '.mxu' :
					$format_readable = __('Vidéo M4U', 'simple-coherent-form'); break;

				case 'video/vnd.ms-playready.media.pyv':
				case '.pyv' :
					$format_readable = __('Vidéo PYV', 'simple-coherent-form'); break;

				case 'video/vnd.vivo':
				case '.viv' :
					$format_readable = __('Vidéo Vivo', 'simple-coherent-form'); break;

				case 'video/webm':
				case '.webm' :
					$format_readable = __('Vidéo WEBM', 'simple-coherent-form'); break;

				case 'video/x-f4v':
				case '.f4v' :
					$format_readable = __('Vidéo F4V', 'simple-coherent-form'); break;

				case 'video/x-fli':
				case '.fli' :
					$format_readable = __('Vidéo FLI', 'simple-coherent-form'); break;

				case 'video/x-flv':
				case '.flv' :
					$format_readable = __('Vidéo FLV', 'simple-coherent-form'); break;

				case 'video/x-m4v':
				case '.m4v' :
					$format_readable = __('Vidéo M4V', 'simple-coherent-form'); break;

				case 'video/x-matroska':
				case '.mkv' :
					$format_readable = __('Vidéo MKV', 'simple-coherent-form'); break;

				case 'video/x-ms-asf':
				case '.asf':
				case '.asx' :
					$format_readable = __('Vidéo ASF', 'simple-coherent-form'); break;

				case 'video/x-ms-wm':
				case '.wm' :
					$format_readable = __('Vidéo WM', 'simple-coherent-form'); break;

				case 'video/x-ms-wmv':
				case '.wmv' :
					$format_readable = __('Vidéo WMV', 'simple-coherent-form'); break;

				case 'video/x-ms-wmx':
				case '.wmx' :
					$format_readable = __('Vidéo WMX', 'simple-coherent-form'); break;

				case 'video/x-ms-wvx':
				case '.wvx' :
					$format_readable = __('Vidéo WVX', 'simple-coherent-form'); break;

				case 'video/x-msvideo':
				case '.avi' :
					$format_readable = __('Vidéo AVI', 'simple-coherent-form'); break;

				case 'video/x-sgi-movie':
				case '.movie' :
					$format_readable = __('Vidéo Movie', 'simple-coherent-form'); break;


				/* DOCUMENT */

				case 'application/x-abiword':
				case '.abw' :
					$format_readable = __('Document AbiWord', 'simple-coherent-form'); break;

				case 'application/msword':
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.template':
				case '.doc':
				case '.docx':
				case '.dot':
				case '.dotx':
				case '.wiz' :
					$format_readable = __('Document Microsoft Word (DOC et DOCX)', 'simple-coherent-form'); break;

				case 'application/vnd.oasis.opendocument.presentation':
				case '.odp' :
					$format_readable = __('Document Presentation OpenDocument (ODP)', 'simple-coherent-form'); break;

				case 'application/vnd.oasis.opendocument.spreadsheet':
				case '.ods' :
					$format_readable = __('Document Calc OpenDocument (ODS)', 'simple-coherent-form'); break;

				case 'application/vnd.oasis.opendocument.text':
				case '.odt' :
					$format_readable = __('Document Texte OpenDocument (ODT)', 'simple-coherent-form'); break;

				case 'application/pdf':
				case 'application/x-pdf':
				case '.pdf' :
					$format_readable = __('Document PDF', 'simple-coherent-form'); break;

				case 'application/vnd.ms-powerpoint':
				case '.ppt' :
					$format_readable = __('Document PowerPoint (PPT)', 'simple-coherent-form'); break;

				case 'application/vnd.ms-excel':
				case '.xls' :
					$format_readable = __('Document Excel (XLS)', 'simple-coherent-form'); break;

				case 'text/csv':
				case 'application/csv':
				case 'text/x-csv':
				case 'application/x-csv':
				case 'text/x-comma-separated-values':
				case 'text/comma-separated-values':
				case '.csv' :
					$format_readable = __('Document Calc (CSV)', 'simple-coherent-form'); break;

				case 'application/xhtml+xml':
				case '.xhtml' :
					$format_readable = __('Document XHTML', 'simple-coherent-form'); break;

				case 'application/rtf':
				case '.rtf' :
					$format_readable = __('Document Rich Text Format (RTF)', 'simple-coherent-form'); break;

				case 'application/xml':
				case '.xml' :
					$format_readable = __('Document XML', 'simple-coherent-form'); break;

				case 'application/vnd.mozilla.xul+xml':
				case '.xul' :
					$format_readable = __('Document XUL', 'simple-coherent-form'); break;

				case 'text/css':
				case '.css' :
					$format_readable = __('Document CSS', 'simple-coherent-form'); break;

				case 'text/html':
				case '.htm':
				case '.html' :
					$format_readable = __('Document HTML', 'simple-coherent-form'); break;

				case 'text/calendar':
				case '.ics' :
					$format_readable = __('Calendrier (ICAL)', 'simple-coherent-form'); break;

				case 'application/javascript':
				case 'text/javascript':
				case 'application/ecmascript':
				case 'application/x-ecmascript':
				case 'application/x-javascript':
				case 'text/ecmascript':
				case 'text/javascript1.0':
				case 'text/javascript1.1':
				case 'text/javascript1.2':
				case 'text/javascript1.3':
				case 'text/javascript1.4':
				case 'text/javascript1.5':
				case 'text/jscript':
				case 'text/livescript':
				case 'text/x-ecmascript':
				case 'text/x-javascript':
				case '.js' :
					$format_readable = __('Document JavaScript', 'simple-coherent-form'); break;

				case 'application/json':
				case '.json' :
					$format_readable = __('Document JSON', 'simple-coherent-form'); break;

				case 'text/markdown':
				case 'text/x-markdown':
				case '.md':
				case '.markdown':
				case '.mdown':
				case '.markdn' :
					$format_readable = __('Document Markdown', 'simple-coherent-form'); break;

				case 'text/mathml':
				case 'application/mathml+xml':
				case '.mathml':
				case '.mml' :
					$format_readable = __('Document MathML', 'simple-coherent-form'); break;

				case 'text/plain':
				case '.conf':
				case '.def':
				case '.diff':
				case '.in':
				case '.ksh':
				case '.list':
				case '.log':
				case '.pl':
				case '.text':
				case '.txt' :
					$format_readable = __('Document Texte', 'simple-coherent-form'); break;

				case 'text/prs.lines.tag':
				case '.dsc' :
					$format_readable = __('Document DSC', 'simple-coherent-form'); break;

				case 'text/richtext':
				case '.rtx' :
					$format_readable = __('Document RTX', 'simple-coherent-form'); break;

				case 'text/sgml':
				case '.sgm':
				case '.sgml' :
					$format_readable = __('Document SGM', 'simple-coherent-form'); break;

				case 'text/tab-separated-values':
				case '.tsv' :
					$format_readable = __('Document TSV', 'simple-coherent-form'); break;

				case 'text/troff':
				case '.man':
				case '.me':
				case '.ms':
				case '.roff':
				case '.t':
				case '.tr' :
					$format_readable = __('Document TROFF', 'simple-coherent-form'); break;

				case 'text/uri-list':
				case '.uri':
				case '.uris':
				case '.urls' :
					$format_readable = __('Document URI', 'simple-coherent-form'); break;

				case 'text/vnd.curl':
				case '.curl' :
					$format_readable = __('Document CURL', 'simple-coherent-form'); break;

				case 'text/vnd.curl.dcurl':
				case '.dcurl' :
					$format_readable = __('Document DCURL', 'simple-coherent-form'); break;

				case 'text/vnd.curl.mcurl':
				case '.mcurl' :
					$format_readable = __('Document MCURL', 'simple-coherent-form'); break;

				case 'text/vnd.curl.scurl':
				case '.scurl' :
					$format_readable = __('Document SCURL', 'simple-coherent-form'); break;

				case 'text/vnd.fly':
				case '.fly' :
					$format_readable = __('Document FLY', 'simple-coherent-form'); break;

				case 'text/vnd.fmi.flexstor':
				case '.flx' :
					$format_readable = __('Document FLX', 'simple-coherent-form'); break;

				case 'text/vnd.graphviz':
				case '.gv' :
					$format_readable = __('Document GV', 'simple-coherent-form'); break;

				case 'text/vnd.in3d.3dml':
				case '.3dml' :
					$format_readable = __('Document 3DML', 'simple-coherent-form'); break;

				case 'text/vnd.in3d.spot':
				case '.spot' :
					$format_readable = __('Document SPOT', 'simple-coherent-form'); break;

				case 'text/vnd.sun.j2me.app-descriptor':
				case '.jad' :
					$format_readable = __('Document JAD', 'simple-coherent-form'); break;

				case 'text/vnd.wap.si':
				case '.si' :
					$format_readable = __('Document SI', 'simple-coherent-form'); break;

				case 'text/vnd.wap.sl':
				case '.sl' :
					$format_readable = __('Document SL', 'simple-coherent-form'); break;

				case 'text/vnd.wap.wml':
				case '.wml' :
					$format_readable = __('Document WML', 'simple-coherent-form'); break;

				case 'text/vnd.wap.wmlscript':
				case '.wmls' :
					$format_readable = __('Document WMLS', 'simple-coherent-form'); break;

				case 'text/x-asm':
				case '.asm':
				case '.s' :
					$format_readable = __('Document ASM', 'simple-coherent-form'); break;

				case 'text/x-fortran':
				case '.f':
				case '.f77':
				case '.f90':
				case '.for' :
					$format_readable = __('Document Fortran', 'simple-coherent-form'); break;

				case 'text/x-java-source':
				case '.java' :
					$format_readable = __('Document JAVA', 'simple-coherent-form'); break;

				case 'text/x-pascal':
				case '.p':
				case '.pas':
				case '.pp':
				case '.inc' :
					$format_readable = __('Document Pascal', 'simple-coherent-form'); break;

				case 'text/x-python':
				case '.py' :
					$format_readable = __('Document Python', 'simple-coherent-form'); break;

				case 'text/x-setext':
				case '.etx' :
					$format_readable = __('Document ETX', 'simple-coherent-form'); break;

				case 'text/x-uuencode':
				case '.uu' :
					$format_readable = __('Document UU', 'simple-coherent-form'); break;

				case 'text/x-vcalendar':
				case '.vcs' :
					$format_readable = __('Document VCS', 'simple-coherent-form'); break;

				case 'text/x-vcard':
				case '.vcf' :
					$format_readable = __('Document VCard', 'simple-coherent-form'); break;

	
				/* ARCHIVE */

				case 'application/octet-stream':
				case '.arc' :
					$format_readable = __('Archive ARC', 'simple-coherent-form'); break;

				case 'application/x-bzip':
				case '.bz' :
					$format_readable = __('Archive BZip', 'simple-coherent-form'); break;

				case 'application/x-bzip2':
				case '.bz2' :
					$format_readable = __('Archive BZip2', 'simple-coherent-form'); break;

				case 'application/x-rar-compressed':
				case '.rar' :
					$format_readable = __('Archive RAR', 'simple-coherent-form'); break;

				case 'application/x-tar':
				case '.tar' :
					$format_readable = __('Archive TAR', 'simple-coherent-form'); break;

				case 'application/zip':
				case '.zip' :
					$format_readable = __('Archive ZIP', 'simple-coherent-form'); break;

				case 'application/x-7z-compressed':
				case '.7z' :
					$format_readable = __('Archive 7-zip', 'simple-coherent-form'); break;


				/* AUTRE */

				case 'application/vnd.amazon.ebook':
				case '.azw' :
					$format_readable = __('E-Book Amazon Kindle', 'simple-coherent-form'); break;

				case 'application/epub+zip':
				case '.epub' :
					$format_readable = __('Livre Electronique (EPUB)', 'simple-coherent-form'); break;

				case 'application/octet-stream':
				case '.bin' :
					$format_readable = __('Fichier binaire', 'simple-coherent-form'); break;

				case 'application/x-csh':
				case '.csh' :
					$format_readable = __('Script C-Shell', 'simple-coherent-form'); break;

				case 'application/java-archive':
				case '.jar' :
					$format_readable = __('Archive Java (JAR)', 'simple-coherent-form'); break;

				case 'application/vnd.apple.installer+xml':
				case '.mpkg' :
					$format_readable = __('Paquet d\'installation Apple', 'simple-coherent-form'); break;

				case 'application/ogg':
				case '.ogx' :
					$format_readable = __('Fichier OGX', 'simple-coherent-form'); break;

				case 'application/x-sh':
				case '.sh' :
					$format_readable = __('Script Shell', 'simple-coherent-form'); break;

				case 'application/x-shockwave-flash':
				case '.swf' :
					$format_readable = __('Document Flash (SWF)', 'simple-coherent-form'); break;

				case 'application/vnd.visio':
				case '.vsd' :
					$format_readable = __('Microsoft Visio', 'simple-coherent-form'); break;

				default: break;

			}

			return apply_filters('scf_input_format_readable', $format_readable, $format);
		}

		/**
		 * Marque le type File comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function changeUploadDir($dir) {
			$dir['path'] = $dir['basedir'] . '/scf_uploads/' . $this->id_folder;
			$dir['url'] = $dir['baseurl'] . '/scf_uploads/' . $this->id_folder;
			$dir['subdir'] = '/scf_uploads/' . $this->id_folder;
			return $dir;
		}

		/**
		 * Marque le type File comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function deletingFile($id, $error = false) {
			$dir = wp_get_upload_dir();
			$path = $dir['basedir'] . '/scf_uploads/' . $id . '/';
			$this->removeUploadDir($path, $error);
			wp_clear_scheduled_hook('scf_deleting_file', array(strval($id)), true);
		}

		/**
		 * Recoit un fichier, verifie son format et sa taille et le stocke dans le dossier upload
		 * Requete AJAX scf_file_upload
		 *
		 * @return void
		 */
		public function uploadFile() {
			if (!apply_filters('scf_accept_upload_file', true)) wp_send_json_error('cache forbidden');

			if ( !isset($_FILES['file']) ) wp_send_json_error('file missing');
			if ( !isset($_POST['accept']) ) wp_send_json_error('accept missing');
			if ( empty($_POST['accept']) ) $_POST['accept'] = esc_attr('*');
			if ( !isset($_POST['size']) ) wp_send_json_error('size missing');
			if ( !isset($_POST['hash']) ) wp_send_json_error('hash missing');
			if ( !isset($_POST['id']) ) wp_send_json_error('id missing');
			if ( !isset($_POST['hash_id']) ) wp_send_json_error('hash id missing');

			/* Verifie si les MIME acceptés et la taille maximum n'a pas été modifié */
			if ( !wp_check_password($this->salt . $_POST['size'] . $_POST['accept'], $_POST['hash']) ) wp_send_json_error('hash error');

			/* Verifie si l'id du dossier n'a pas été modifié */
			if ( !wp_check_password($this->salt . $_POST['id'], $_POST['hash_id']) ) wp_send_json_error('hash id error');

			do_action('scf_before_upload_file');

			$this->id_folder = sanitize_text_field($_POST['id']);
			$file = $_FILES['file'];

			/* Vérifie si le fichier est d'un MIME acceptés et d'une taille inférieure à la limite */
			$files_mime_accepted = array_map('trim', explode(',', sanitize_text_field($_POST['accept'])));
			$file_mime_parts = explode('/', $file['type']);

			$file_parts = explode( '.', $file['name'] );
			$file_ext = array_pop($file_parts);
			$file_name = implode('.', $file_parts);

			$max_files_size = intval(sanitize_text_field($_POST['size']));

			if ( !in_array($file['type'], $files_mime_accepted) && !in_array('*', $files_mime_accepted) && !in_array($file_mime_parts[0] . '/*', $files_mime_accepted) && !in_array('.' . $file_ext, $files_mime_accepted) ) wp_send_json_error('mime not accepted');
			if ( $file['size']>$max_files_size ) wp_send_json_error('file too large');

			if ( !function_exists('wp_handle_upload') ) require_once( ABSPATH . 'wp-admin/includes/file.php' );

			/* Enable CRON to remove file */
			$timestamp_deleting = time() + intval(apply_filters('scf_file_deleting_timeout', 1800));
			$rand = wp_generate_password(5, false);
			do { $rand = wp_generate_password(5, false); } while (get_option('scf_file_cron_' . $timestamp_deleting . '_' . $rand) !== false);
			add_option('scf_file_cron_' . $timestamp_deleting . '_' . $rand , $this->id_folder);
			wp_schedule_single_event(time(), 'scf_generate_cron_deleting');

			/* Move to upload directory */
			add_filter( 'upload_dir', array($this, 'changeUploadDir') );
			$upload = wp_handle_upload( $file, array( 'test_form' => false ) );
			remove_filter( 'upload_dir', array($this, 'changeUploadDir') );

			if ( !empty($upload['error']) ) wp_send_json_error($upload['error']);

			do_action('scf_after_upload_file');

			wp_send_json_success(array('timeout' => $timestamp_deleting));
		}

		/**
		 * Créer les taches cron de suppression à partir des options
		 * Si plusieurs demande de cron arrive en même temps (fichier multiple par ex), permet de stocker les id pour faire la même demande de cron et évite que les taches cron s'ecrase et que seul la dernière s'effectue.
		 */
		public function generateCronJobs() {
			/* Récupère toutes les options */
			$options = wp_load_alloptions();

			/* Parcours les options pour trouver celles concernant un fichier à supprimer */
			foreach ($options as $slug => $value) {
				if (substr($slug, 0, 14)!=='scf_file_cron_') continue;

				/* Récupère le timestamp */
				$slug_parts = explode('_', substr($slug, 14));
				$timestamp = intval($slug_parts[0]);

				/* Pour chaque fichier à supprimer, créer la tache cron correspondante */
				wp_schedule_single_event($timestamp, 'scf_deleting_file', array($value), true);

				/* Supprime l'option de la base */
				delete_option($slug);
			}
			
		}

		/**
		 * Marque le type File comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function generateUploadFileID() {
			if (!empty($_SERVER['HTTP_CLIENT_IP'])) { $ip = $_SERVER['HTTP_CLIENT_IP']; }
			elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ip = $_SERVER['HTTP_X_FORWARDED_FOR']; }
			else { $ip = $_SERVER['REMOTE_ADDR']; }
			$id = strval(crc32(wp_rand() . $ip . round(microtime(true))));
			wp_send_json_success(array(
				'ID' => esc_attr($id),
				'hash' => esc_attr(wp_hash_password($this->salt.esc_attr($id))),
				'nonce' => esc_attr(wp_create_nonce('scf_upload_file_removal')),
			));
		}

		/**
		 * Marque le type File comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		private function removeUploadDir($path, $error = true) {
			if (!is_dir($path)) {
				if ($error) {
					wp_send_json_error('dir not found');
				} else {
					return false;
				}
			}

			if (substr($path, strlen($path) - 1, 1) != '/') $path .= '/';
			
			$files = glob($path . '*', GLOB_MARK);
			foreach ($files as $file) {
				if (is_dir($file)) {
					$this->removeUploadDir($file);
				} else {
					unlink($file);
				}
			}
			rmdir($path);
		}

		/**
		 * Marque le type File comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function removeUploadedFile() {
			if ( !isset($_POST['id']) ) wp_send_json_error('id missing');
			if ( !isset($_POST['hash']) ) wp_send_json_error('hash missing');
			if ( !isset($_POST['nonce']) ) wp_send_json_error('nonce missing');

			if ( ! wp_verify_nonce( $_POST['nonce'], 'scf_upload_file_removal' ) ) wp_send_json_error('nonce incorrect');

			/* Verifie si l'id du dossier n'a pas été modifié */
			if ( !wp_check_password($this->salt . $_POST['id'], $_POST['hash']) ) wp_send_json_error('hash error');

			$this->deletingFile(sanitize_text_field($_POST['id']), true);

			wp_send_json_success();
		}

		/**
		 * Marque le type File comme de type Label, afin de l'entourer d'un label
		 * Filtre scf_types_with_group_label
		 *
		 * @param string[] $types Listes des types Label
		 * @return string[] Listes des types Label modifiés
		 */
		public function checkExistenceFiles() {
			if ( !isset($_POST['files']) ) wp_send_json_error('files missing');
			if ( !is_array($_POST['files']) ) wp_send_json_error('files incorrect');
			if ( !isset($_POST['security']) ) wp_send_json_error('nonce missing');

			if ( ! wp_verify_nonce( $_POST['security'], 'scf_check_files_existence_hash' ) ) wp_send_json_error('nonce incorrect');

			$out = array();

			foreach ($_POST['files'] as $file_id => $file_hash) {
				$out[$file_id] = false;
				if ( wp_check_password($this->salt . $file_id, $file_hash) ) {

					/* Enlève l'id des options pour que la tache cron scf_generate_cron_deleting ne créer pas la tache cron de suppression */
					$options = wp_load_alloptions();
					foreach ($options as $slug => $value) {
						if (substr($slug, 0, 14)!=='scf_file_cron_') continue;
						if ($value!==$file_id) continue;
						delete_option($slug);
					}

					/* Enlève les taches cron qui supprime ce fichier */
					wp_clear_scheduled_hook('scf_deleting_file', array(strval($file_id)), true);

					/* Verifie l'existence du fichier */
					$dir = wp_get_upload_dir();
					$path = $dir['basedir'] . '/scf_uploads/' . $file_id . '/';

					if (is_dir($path) && count(scandir($path)) > 2) {
						$out[$file_id] = true;
					}
				}
			}

			wp_send_json_success($out);
		}
	}

	new SCFFile();
}
