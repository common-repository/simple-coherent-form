/**
 * Script principal de Simple Coherent Form
 *
 * enqueue dans includes/front/front.php avec le slug scf-script
 */


/* Polyfill de formatage de chaine de caractères */
if (!String.prototype.format) String.prototype.format = function(...args) { return this.replace(/(\{\d+\})/g, function(a) { return args[+(a.substr(1, a.length - 2)) || 0]; }); };

(function($) {

	var __scf_debug = false;

	/**
	 * File d'attente des timeouts des requêtes AJAX
	 * Permet d'attendre la fin d'un input avant d'envoyer une requete ajax et d'empêcher l'envoi si l'utilisateur continue de taper des caractères
	 */
	var scf_input_ajax_waiting_list = {};

	/**
	 * File d'attente des fichiers à envoyer par AJAX
	 */
	var scf_files_ajax_waiting_list = [];

	/**
	 * Stocke des informations sur le kreypress sur un select ouvert
	 * 
	 * @var string       letter  La/les lettre(s) tapée(s) recherchée(s)
	 * @var boolean      reset   Si au prochain keypress, la lettre doit écrasée la précédente ou l'ajouter à la suite
	 * @var timeout|null timeout Le timeout qui remet reset à true si un caractère n'est pas tapé avant un certain temps (1300ms)
	 * @var Node|null    option  L'option actuellement sélectionné suite à la recherche. Ainsi si la même lettre est tapé plusieurs fois, navigue entre les options correspondantes
	 */
	var scf_keypress_letter = {
		letter : '',
		reset : true,
		timeout : null,
		option : null,
	};


	/**
	 * Enregistre le champ tel focus lors de l'appui sur la touche echap
	 * Lors du keyup sur l'escape, le champ tel perd son focus et donc impossibilité de savoir quel champ était utilisée. Enregistre ce champ lors du keydown pour l'utiliser dans le keyup
	 */
	let keypress_esc_tel = false;


	/* Verifie si le mot de passe contient au moins une majuscule */
	window.scf_check_password_uppercase = function(password) { return /[A-Z]/.test(password); }

	/* Verifie si le mot de passe contient au moins une minuscule */
	window.scf_check_password_lowercase = function(password) { return /[a-z]/.test(password); }

	/* Verifie si le mot de passe contient au moins un chiffre */
	window.scf_check_password_number = function(password) { return /[0-9]/.test(password); }

	/* Verifie si le mot de passe contient au moins un caractère spécial */
	window.scf_check_password_special = function(password) { return /[^a-zA-Z0-9]/.test(password); }

	/* Verifie si le mot de passe contient au moins 8 caractères */
	window.scf_check_password_length = function(password) { return password.length>=8; }

	/* Verifie si le mot de passe n'est pas dans le dictionnaire de mdp */
	window.scf_check_password_common = function(password) {
		if (typeof common_passwords == 'undefined') return false;
		return !common_passwords.includes(password);
	}

	/**
	 * Vérifie un groupe et renvoie les erreurs ou un nombre
	 * Les erreurs renvoyées sont au format object avec pour paramètres le code de l'erreur, le message, le groupe de l'input, l'item concerné (différent du groupe si le champ et des type radio ou checkbox) et la priorité de l'erreur
	 * Les codes d'erreurs se composent ainsi : 3 premiers chiffre selon le type de champ (100 erreurs communes ; 102 pour les nombres ; 103 pour les select ; 105 pour les urls ; 106 pour le e-mails ; 107 pour les téléphones ; 108 pour les mots de passe ; 109 pour les radios ; 110 pour les checkbox ; 111 pour les dates) suivie de l'identifiant unique de l'erreur
	 *
	 * @async
	 * @param Event       e           L'event ayant déclenché la vérification
	 * @param Node        input       L'input à vérifier
	 * @param Number|null check_error Indique une erreur spécifique à vérifier ou toutes les erreurs à null
	 * @return int|object[] Le tableau des erreurs si il y en a, un nombre négatif si c'est un champ facultatif non remplie, 0 sinon.
	 */
	window.scf_validate_input = async function(e, input, check_error = null) {
		if (__scf_debug) console.log(arguments);

		/* Tableau des erreurs */
		let out = [];

		/* Groupe de l'input */
		let group = item = input.closest('.scf-group');

		/* Item du groupe */
		if (group.hasClass('scf-checkbox')) {
			item = input.closest('.scf-checkbox-item');
		} else if (group.hasClass('scf-radio')) {
			item = input.closest('.scf-radio-item');
		}

		/* Aucune erreur si on est en readonly, puisque l'utilisateur ne peut rien modifier */
		if (group.hasClass('scf-readonly')) return out;

		/* Verifie si l'item est rempli ou selectionné */
		let filled = (typeof input.val()=='undefined') ? false : input.val().length>0;
		if (group.hasClass('scf-checkbox')||group.hasClass('scf-radio')) filled = input.is(':checked');
		if (group.hasClass('scf-select')) filled = (group.find('input.scf-select-2-item-input:checked').length) ? true : false;
		if (group.hasClass('scf-select') && $('body').hasClass('scf-touch-device')) filled = (group.find('.scf-select-native option:selected').length) ? true : false;
		if (group.hasClass('scf-wysiwyg')) filled = (filled && input.val()!=='<p><br></p>') ? true : false;

		/* Vérifie si le champ est requis */
		if (item.hasClass('scf-required')) {

			/* Si le champ n'est pas remplie, renvoie une erreur */
			if (!filled) {

				/* En cas de select, n'emet pas d'erreur si il s'agit d'un changement de focus au sein du select */
				if (group.hasClass('scf-select') && e!==null && e.type === 'focusout' && e.relatedTarget) {
					if (!$(e.relatedTarget).closest('.scf-group').length || $(e.relatedTarget).closest('.scf-group').attr('id')!=group.attr('id')) {
						let error = (group.hasClass('scf-select-multiple')) ? scf_errors.option_more : scf_errors.option_one;
						out.push({
							code: 103001,
							message: error,
							group: group,
							item: group,
							priority: 10
						});
					}
				} else {
					let error = (group.hasClass('scf-checkbox')||group.hasClass('scf-radio')) ? scf_errors.option_required : scf_errors.required;
					if (group.hasClass('scf-select')) { error = (group.hasClass('scf-select-multiple')) ? scf_errors.option_more : scf_errors.option_one; }
					out.push({
						code: 100001,
						message: error,
						group: group,
						item: item,
						priority: 10
					});
				}

			/* Si le champ est rempli et qu'un format est demandé, verifie que la valeur n'est pas identique à celle du format par défaut (sinon considère le champ comme non rempli) */
			} else if ((group.hasClass('scf-text') || group.hasClass('scf-number') || group.hasClass('scf-email') || group.hasClass('scf-url') || group.hasClass('scf-date') || group.hasClass('scf-time') || group.hasClass('scf-datetime') || group.hasClass('scf-password')) && input.is('[data-format]') && typeof input.attr('data-format')!=='undefined' && input.attr('data-format')!=='' && typeof input.data('specificators')!=='undefined' && !scf_format_check_filled(input)) {
				out.push({
					code: 100001,
					message: scf_errors.required,
					group: group,
					item: item,
					priority: 10
				});
			}
		} else if (filled && (group.hasClass('scf-text') || group.hasClass('scf-number') || group.hasClass('scf-email') || group.hasClass('scf-url') || group.hasClass('scf-date') || group.hasClass('scf-time') || group.hasClass('scf-datetime') || group.hasClass('scf-password')) && input.is('[data-format]') && typeof input.attr('data-format')!=='undefined' && input.attr('data-format')!=='' && typeof input.data('specificators')!=='undefined' && !scf_format_check_filled(input)) {
			filled = false;
		}

		/* Verifie si une checkbox ou radio dans la liste est rempli si le groupe est requis */
		if (group.hasClass('scf-checkbox') && group.hasClass('scf-required') && filled && !group.find('.scf-checkbox-item > input:checked').length) {
			/* Si un évènement est fournie transmis (on blur) */
			if (e!==null && e.type === 'blur' ) {
				/* Si l'évènement possède un relatedTarget : element qui prend le focus */
				if(e.relatedTarget) {
					/* Si l'élèment qui prend le focus n'est pas dans le groupe, affiche l'erreur */
					if (!$(e.relatedTarget).is('.scf-checkbox-item') || !$(e.relatedTarget).closest('.scf-group').length || $(e.relatedTarget).closest('.scf-group').attr('id')!=group.attr('id')) {
						let error = (group.find('.scf-checkbox-item').length>1) ? scf_errors.option_more : scf_errors.required;
						out.push({
							code: 110002,
							message: error,
							group: group,
							item: group,
							priority: 11
						});
					}
				} else {
					let error = (group.find('.scf-checkbox-item').length>1) ? scf_errors.option_more : scf_errors.required;
					out.push({
						code: 110003,
						message: error,
						group: group,
						item: group,
						priority: 11
					});
				}
			} else {
				let error = (group.find('.scf-checkbox-item').length>1) ? scf_errors.option_more : scf_errors.required;
				out.push({
					code: 110004,
					message: error,
					group: group,
					item: group,
					priority: 11
				});
			}
		} else if (group.hasClass('scf-radio') && group.hasClass('scf-required') && filled && !group.find('.scf-radio-item > input:checked').length) {
			/* Si un évènement est transmis (on blur) */
			if (e!==null) {
				/* Si l'évènement possède un relatedTarget : element qui prend le focus */
				if(e.relatedTarget) {
					/* Si l'élèment qui prend le focus n'est pas dans le groupe, affiche l'erreur */
					if (!$(e.relatedTarget).is('.scf-radio-item') || !$(e.relatedTarget).closest('.scf-group').length || $(e.relatedTarget).closest('.scf-group').attr('id')!=group.attr('id')) {
						out.push({
							code: 109002,
							message: scf_errors.option_one,
							group: group,
							item: group,
							priority: 11
						});
					}
				} else {
					out.push({
						code: 109003,
						message: scf_errors.option_one,
						group: group,
						item: group,
						priority: 11
					});
				}
			} else {
				out.push({
					code: 109004,
					message: scf_errors.option_one,
					group: group,
					item: group,
					priority: 11
				});
			}
		}

		/* Si un format est demandé, verifie que le format est respecté */
		if ((group.hasClass('scf-text') || group.hasClass('scf-number') || group.hasClass('scf-email') || group.hasClass('scf-url') || group.hasClass('scf-date') || group.hasClass('scf-time') || group.hasClass('scf-datetime') || group.hasClass('scf-password')) && input.is('[data-format]') && typeof input.attr('data-format')!=='undefined' && input.attr('data-format')!=='' && typeof input.data('specificators')!=='undefined' && !scf_format_check_correct(input)) {
			out.push({
				code: 100004,
				message: scf_errors.format,
				group: group,
				item: item,
				priority: 12
			});
		}

		/* Si c'est un champ téléphone rempli */
		if (group.hasClass('scf-tel') && filled) {

			/* Récupère le pays du numéro de téléphone */
			let tel_input = group.find('.scf-tel-country-selector input.scf-select-2-item-input:checked');
			let tel_country = (tel_input.length) ? tel_input.first() : null;
			if ($('body').hasClass('scf-touch-device')) tel_country = (group.find('.scf-tel-country-selector .scf-select-native option:selected').length) ? group.find('.scf-tel-country-selector .scf-select-native') : null;

			/* Verifie la validité du téléphone via libphonenumber */
			if (typeof libphonenumber !== 'undefined' && typeof libphonenumber.isValidPhoneNumber !== 'undefined') {
				let country_code = '';
				if (tel_country.length) country_code = tel_country.val();
			
				if (!libphonenumber.isValidPhoneNumber(input.val(), country_code)) {
					out.push({
						code: 107002,
						message: scf_errors.format_tel,
						group: group,
						item: group,
						priority: 12
					});
				}
			}
		}

		/* Si c'est un champ e-mail rempli */
		if (group.hasClass('scf-email') && filled) {

			/* Verifie le format du mail */
			let reg = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
			if (!reg.test(input.val())) {
				out.push({
					code: 106002,
					message: scf_errors.format_email,
					group: group,
					item: group,
					priority: 13
				});
			}
		}

		/* Si c'est un champ fichier rempli */
		if (group.hasClass('scf-file') && filled) {

			/* Verifie la taille et le format du fichier */
			for (var i = 0; i < input.get(0).files.length; i++) {
				let file = input.get(0).files[i];
				let file_mime_parts = file.type.split('/');
				let file_name_parts = file.name.split('.');
				let file_extension  = '.' + file_name_parts.slice(-1);
				let accept = (input.attr('accept').length>0) ? input.attr('accept').split(',').map(e => e.trim()) : ['*'];

				if (!accept.includes('*') && !accept.includes(file_mime_parts[0]+'/*') && !accept.includes(file_mime_parts[0]) && !accept.includes(file.type) && !accept.includes(file_extension)) {
					let error = (group.hasClass('.scf-multiple')) ? scf_errors.files_bad_format : scf_errors.file_bad_format;
					out.push({
						code: 112001,
						message: error,
						group: group,
						item: group,
						priority: 12
					});
				}

				if (file.size > parseInt(input.data('size'))) {
					let error = (group.hasClass('.scf-multiple')) ? scf_errors.files_too_large : scf_errors.file_too_large;
					out.push({
						code: 112002,
						message: error,
						group: group,
						item: group,
						priority: 13
					});
				}
			}
		}

		/* Si c'est un champ date rempli */
		if (group.hasClass('scf-date') && filled) {

			/* Verifie la validité de la date via moment.js */
			let datepicker_id = 'picker_'+input.attr('id').replaceAll('-', '_');
			let datepicker = window[datepicker_id];
			if (typeof datepicker == 'object' && datepicker !== null) {
				let date = moment(input.val(), datepicker._o.format, true);
				if (!date.isValid()) {
					out.push({
						code: 111002,
						message: scf_errors.format_date,
						group: group,
						item: group,
						priority: 13
					});
				}
			}
		}

		/* Si c'est un champ URL rempli, vérifie le format de l'URL */
		let reg = /^(?:(?:(?:https?|ftp):)?\/\/)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z0-9\u00a1-\uffff][a-z0-9\u00a1-\uffff_-]{0,62})?[a-z0-9\u00a1-\uffff]\.)+(?:[a-z\u00a1-\uffff]{2,}\.?))(?::\d{2,5})?(?:[/?#]\S*)?$/i; // Regex credit : @diegoperini - https://github.com/diegoperini
		if (group.hasClass('scf-url') && filled && !reg.test(input.val())) {
			out.push({
				code: 105002,
				message: scf_errors.format_url,
				group: group,
				item: group,
				priority: 14
			});
		}

		/* Si c'est un champ nombre rempli */
		if (group.hasClass('scf-number') && filled) {

			/* Verifie le format numéraire */
			if (!/^[0-9]*$/.test(input.val())) {
				out.push({
					code: 102002,
					message: scf_errors.format_number,
					group: group,
					item: group,
					priority: 15
				});
			}

			/* Verifie si il est supérieur au minimum requis */
			if (input.is('[min]') && parseInt(input.val())<parseInt(input.attr('min'))) {
				out.push({
					code: 102003,
					message: scf_errors.number_min.format(input.attr('min')),
					group: group,
					item: group,
					priority: 16
				});
			}

			/* Verifie si il est inférieur au maximum fixé */
			if (input.is('[max]') && parseInt(input.val())>parseInt(input.attr('max'))) {
				out.push({
					code: 102004,
					message: scf_errors.number_max.format(input.attr('max')),
					group: group,
					item: group,
					priority: 17
				});
			}
		}
		
		/* Si c'est un champ mot de passe rempli */
		if (group.hasClass('scf-password') && filled) {

			/* Verifie si le mot de passe passe la sécurité */
			if (input.is('[data-scf-force]')) {
				let force = input.attr('data-scf-force');
				let test = true;
				$.each(scf_pass_force, function(key, value) {
					let fn = window[key];
					if ((force & value)>0 && !fn(input.val())) test = false;
				});
				if (!test) {
					out.push({
						code: 108002,
						message: scf_errors.pass_strong,
						group: group,
						item: group,
						priority: 18
					});
				}
			}
		}

		/* Si le champ doit être identique à un autre champ */
		if (group.is('[data-scf-identical]') && $('.scf-group input[name="' + group.attr('data-scf-identical') + '"]').length && filled) {

			/* Récupère la ou les valeurs du champ */
			let val1 = input.val();
			if (group.hasClass('scf-select')) {
				val1 = [];
				group.find('input.scf-select-2-item-input:checked').each(function() {
					val1.push($(this).val());
				});
			} else if (group.hasClass('scf-checkbox')) {
				val1 = [];
				group.find('.scf-checkbox-item > input:checked').each(function() {
					val1.push($(this).val());
				});
			} else if (group.hasClass('scf-radio')) {
				val1 = [];
				group.find('.scf-radio-item > input:checked').each(function() {
					val1.push($(this).val());
				});
			}

			/* Récupère la ou les valeurs du deuxième champ */
			let input2 = $('.scf-group input[name="' + group.attr('data-scf-identical') + '"]').first();
			let group2 = input2.closest('.scf-group');
			let val2 = input2.val();
			if (group2.hasClass('scf-select')) {
				val2 = [];
				group2.find('input.scf-select-2-item-input:checked').each(function() {
					val2.push($(this).val());
				});
			} else if (group2.hasClass('scf-checkbox')) {
				val2 = [];
				group2.find('.scf-checkbox-item > input:checked').each(function() {
					val2.push($(this).val());
				});
			} else if (group2.hasClass('scf-radio')) {
				val2 = [];
				group2.find('.scf-radio-item > input:checked').each(function() {
					val2.push($(this).val());
				});
			}
			
			/* Verifie leur similarité */
			if (val1!==val2) {

				/* Message particulier pour les emails */
				if (group.hasClass('scf-email')) {
					out.push({
						code: 106003,
						message: scf_errors.email_repeat,
						group: group,
						item: group,
						priority: 19
					});

				/* Message particulier pour les mots de passe */
				} else if (group.hasClass('scf-password')) {
					out.push({
						code: 108003,
						message: scf_errors.pass_repeat,
						group: group,
						item: group,
						priority: 20
					});

				/* Message générique */
				} else {
					out.push({
						code: 100002,
						message: scf_errors.not_identical,
						group: group,
						item: group,
						priority: 21
					});
				}
			}
		}

		/* Si le champ rempli doit être unique ou doit déjà exister */
		if ((group.hasClass('scf-unique') || group.hasClass('scf-exists')) && filled && (typeof out !== 'object' || out.length <= 0)) {

			/* Récupère la valeur du champ */
			let value = input.val();
			if (group.hasClass('scf-checkbox')||group.hasClass('scf-radio')) {
				value = group.find('input:checked').first().val();
			} else if (group.hasClass('scf-select')) {
				value = group.find('input.scf-select-2-item-input:checked').first().val();
			}

			/* Si une requête ajax allait être envoyée, l'annule avant de renvoyer la suivante */
			if (typeof scf_input_ajax_waiting_list[group.attr('id')] !== "undefined") clearTimeout(scf_input_ajax_waiting_list[group.attr('id')]);

			/* Récupère la clé sur laquelle doit être fait la vérification */
			let key = group.attr('data-name');
			if (group.hasClass('scf-unique')) {
				if (group[0].hasAttribute('data-scf-unique')) key = group.attr('data-scf-unique');
			} else {
				if (group[0].hasAttribute('data-scf-exists')) key = group.attr('data-scf-exists');
			}

			/* Fonction asynchrone d'attente */
			let wait = async (e, group) => {
				return new Promise((resolve, reject) => {
					scf_input_ajax_waiting_list[group.attr('id')] = setTimeout(() => {
						resolve(true);
					}, ((e!==null && e.type === 'input') ? 500 : 0 ));
				});
			};

			/* Fonction asynchrone de la requete ajax */
			let ajax = () => {
				return new Promise((resolve, reject) => {
					$.ajax({
						url: scf_ajax_url,
						data: {
							action: (group.hasClass('scf-unique')) ? "scf_check_unicity" : "scf_check_existence",
							security: scf_check_unicity_nonce,
							key: key,
							value: value
						},
						type: "POST",
						async: true,
						complete: function (ajax_response) {
							try {
								var response = JSON.parse(ajax_response.responseText);
								if (typeof response.success === "undefined" || response.success !== true) {
									let error = (group.hasClass('scf-unique')) ? scf_errors.field_exist : scf_errors.field_unexist;
									if (group.attr('data-name')=='user_email') {
										error = (group.hasClass('scf-unique')) ? scf_errors.user_email_exist : scf_errors.user_email_unexist;
									} else if (group.attr('data-name')=='username') {
										error = (group.hasClass('scf-unique')) ? scf_errors.username_exist : scf_errors.username_unexist;
									} else if (group.hasClass('scf-password')) {
										error = (group.hasClass('scf-unique')) ? scf_errors.pass_exist : scf_errors.pass_unexist;
									} else if (group.hasClass('scf-tel')) {
										error = (group.hasClass('scf-unique')) ? scf_errors.tel_exist : scf_errors.tel_unexist;
									} else if (group.hasClass('scf-email')) {
										error = (group.hasClass('scf-unique')) ? scf_errors.email_exist : scf_errors.email_unexist;
									}
									resolve({
										code: 100003,
										message: error,
										group: group,
										item: item,
										priority: 22
									});
								} else {
									resolve('');
								}
							} catch (e) {
								resolve('');
							}
						}
					});
				});
			};

			/**
			 * Fonction asynchrone d'attente et d'envoi de la requete ajax
			 * Attend 500ms avant d'envoyer la requête ajax, requête annulée si une autre demande de vérification est faites (cf. l.424)
			 * Permet ainsi de ne pas envoyer de requête ajax à chaque caractère tapé 
			 * 500ms est un temps augmenté de vitesse de frappe d'un enfant
			 */
			let timeout_ajax = async (e, group, ajax) => {
				var next = await wait(e, group);
				if (next) {
					return await ajax();
				}
				return true;
			}

			/**
			 * Si aucune erreur en particulier doit être vérifié (donc doit renvoyer toute les erreurs) ou l'erreur a vérifié est une erreur provenant de la requête ajax ; attends la requête ajax pour renvoyer les erreurs
			 * Si l'on demande de verifier la présence d'une erreur qui n'est pas une erreur issue de l'ajax, renvoie simplement les erreurs (evite la requête)
			 * Permet de ne pas envoyer de requete ajax si c'est pour valider une erreur existante
			 */
			if (typeof check_error !== 'number' || check_error === 100003) {

				/* Attente de la requête AJAX */
				let result = await timeout_ajax(e, group, ajax);

				/* Si une erreur est renvoyée */
				if (typeof result === 'object') out.push(result);
			}

		}

		/* Filtre les erreurs */
		out = wp.hooks.applyFilters('scfValidateErrors', out, input, e, check_error);
			
		/* Renvoie les erreurs */
		if (out.length>0) return out;
		if (!item.hasClass('scf-required') && !filled) return -1;
		return 0;
	}
	
	
	/**
	 * Valide un ensemble de champs
	 *
	 * @async
	 * @param Node[] fields Liste des champs à vérifier
	 * @return boolean Si l'ensemble des champs est valide
	 */
	window.scf_validate_fields = async function(fields) {
		if (__scf_debug) console.log(arguments);

		let validated = true;
		for (var i = 0; i < fields.length; i++) {
			if ($(fields[i]).closest('.scf-group').is(':visible')) {
				if ($(fields[i]).closest('.scf-group').hasClass('scf-textarea') && $(fields[i]).closest('.scf-group').children('.scf-input').length) {
					var input = $(fields[i]).closest('.scf-group').children('.scf-input').children('textarea');
				} else if ($(fields[i]).closest('.scf-group').children('.scf-input').length) {
					var input = $(fields[i]).closest('.scf-group').children('.scf-input').children('input');
				} else if ($(fields[i]).closest('.scf-group').hasClass('scf-field')) {
					var input = $(fields[i]).closest('.scf-group').find('input[type="file"]');
				} else {
					var input = $(fields[i]).closest('.scf-group').find('input');
				}

				/**
				 * Effectue les vérifications du champ
				 * La validation est pas optimisé, chaque champ doit attendre la vérification du précédent avant de commencer.
				 * Cela peut ralentir si plusieurs champs doivent être unique ou similaire (requête ajax)
				 */
				let errors = await scf_validate_input(null, input);

				/* Si une erreur est renvoyée, l'affiche et marque la liste de champs comme non valide */
				if (typeof errors == 'object' && errors.length > 0) {
					validated = false;
					errors.sort(function(a, b) { return a.priority - b.priority; });
					errors[0].item.addClass('scf-error');
					errors[0].group.attr('data-error', errors[0].code).children('.scf-error-text').html(errors[0].message);
				}
			}
		};
		return validated;
	}
	
	/**
	 * Verifie tous les champ file et supprime les fichier préchargé des input
	 *
	 * @param Node form Le formulaire à vérifier
	 * @return boolean Si le champ a au moins un caractère ou une option sélectionnée
	 */
	window.scf_get_files_temp = async function(form) {
		if (__scf_debug) console.log(arguments);

		let $form = $(form);

		if ($form.find('.scf-group.scf-file').length) {
			
			let files = {};

			$form.find('.scf-group.scf-file').each(function() {
				let input = $(this).find('input');
				let group = $(this);
				let hidden = $(this).find('input[type="hidden"][name="'+group.attr('data-name')+'-file-tmp"]');

				let files_id = (hidden.get(0).value.length>0) ? hidden.get(0).value.split(', ').filter(n => n) : [];

				files_id.forEach(function(id) {
					let item = group.find('.scf-files-list .scf-files-list-item[data-id="' + id + '"]').first();
					if (item.length && item.hasClass('sent') && item.data('hash').length) files[id] = item.data('hash');
				});
			});

			/* Fonction asynchrone de la requete ajax */
			let ajax = () => {
				return new Promise((resolve, reject) => {
					$.ajax({
						url: scf_ajax_url,
						data: {
							action: 'scf_check_files_exists',
							security: scf_check_files_exists_nonce,
							files: files,
						},
						context: $form,
						type: "POST",
						async: true,
						success: function (response) {

							$(this).find('.scf-group.scf-file').each(function() {
								let input = $(this).find('input[type="file"]').first();
								let group = $(this);
								let hidden = group.find('input[type="hidden"][name="'+group.attr('data-name')+'-file-tmp"]');

								/* Récupère tous les ids (fichiers envoyés ou non) */
								let files_hidden_list = (hidden.get(0).value.length>0) ? hidden.get(0).value.split(', ').filter(n => n) : [];
								let files_items_list = [];
								group.find('.scf-files-list .scf-files-list-item').each(function() { files_items_list.push($(this).data('id')); });

								files_hidden_list = files_hidden_list.map((x) => parseInt(x));
								files_items_list = files_items_list.map((x) => parseInt(x));

								let files_id = files_hidden_list.concat(files_items_list);
								files_id = files_id.filter(function(v, i, a) { return a.indexOf(v)===i; });


								let hidden_files = [];
								let input_files = new DataTransfer();

								files_id.forEach(function(id) {
									if (typeof response.data[id] === 'boolean' && response.data[id] === true) {

										/* Laisse le fichier dans l'input[type="hidden"] */
										hidden_files.push(id);
									} else {

										/* Laisse le fichier dans l'input[type="file"] */
										let item = group.find('.scf-files-list .scf-files-list-item[data-id="' + id + '"]').first();
										if (item.data('file') instanceof File) input_files.items.add(item.data('file'));
									}
								});
								
								/* Met à jour les fichier de l'input type file */
								input.get(0).files = input_files.files;

								/* Met à jour les id dans l'input type hidden */
								hidden.get(0).value = hidden_files.join(', ');
							});
						},
						complete: function (ajax_response) {
							resolve('');
						}
					});
				});
			};

			return await ajax();
		}
	}


	/**
	 * Au submit d'un formulaire, verifie tous les champs
	 *
	 * @async
	 * @param Event e L'event ayant déclenché la vérification
	 */
	window.scf_validate_form = function() {
		if (__scf_debug) console.log(arguments);

		$(this).get(0).addEventListener('submit', async function(e) {

			/* Bouton ayant effectué le submit */
			let submitter = $(this).data('submitter');

			/* Récupère l'event */
			e = e || window.event;

			/**
			 * Si le submit a déjà été validé
			 * Evite une boucle infinie puisque l'event submit, après vérification des champs, relance le submit
			 */
			if (!$(this).data('validated')) {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();
				
				/* Effectue les vérifications de tous les champs */
				let validated = await scf_validate_fields($(this).find('.scf-group'));

				/* Si tous les champs sont valides */
				if (validated) {

					/* Indique que le formulaire a été validé pour éviter boucle infinie */
					$(this).data('validated', true);

					await scf_get_files_temp($(this));

					/* Si le submit provient d'un bouton, ajoute sa valeur au formulaire */
					if (submitter) {
						let submit_name = submitter.attr('name');
						if (typeof submit_name !== 'undefined' && submit_name !== false) {
							let submit_val = submitter.attr('value');
							$("<input />").attr("type", "hidden").attr("name", submit_name).attr("value", submit_val).appendTo(this);
						}
					}

					/* Re-submit le formulaire sans vérification */
					if ($(this).hasClass('wpcf7-form')) {
						wpcf7.submit($(this).get(0), { submitter: e.submitter });
					} else {
						//$(this).get(0).dispatchEvent(new Event('submit'));
						$(this).trigger('submit');
					}

				/* Si au moins un champ est invalide */
				} else {
					$(this).removeData('submitter');
					$(this).data('validated', false);
				}
			}
		});
	}

	$('body form').each(scf_validate_form);
	

	/**
	 * Au clic sur un submit d'un formulaire (hors Contact form 7), indique ce bouton comme ayant effectué l'action.
	 * Permet d'utiliser un submit avec un nom et une valeur qui sera renvoyée
	 */
	window.scf_click_submit_form = function() {
		if (__scf_debug) console.log(arguments);

		$(this).closest('form').data('submitter', $(this));
	}

	$('body').on('click', 'form :input[type="submit"]', scf_click_submit_form);


	/**
	 * À la perte de focus d'un champ, verifie si des erreurs sont présentes et les affiche
	 *
	 * @param Event e L'event ayant déclenché la perte du focus
	 */
	window.scf_blur_field = function(e){
		if (__scf_debug) console.log(arguments);

		/* Effectue la vérification du champ */
		scf_validate_input(e, $(this)).then((errors) => {

			/* Si au moins une erreur est présente */
			if (typeof errors == 'object' && errors.length > 0) {

				/* Tri les erreurs par ordre de priorité */
				errors.sort(function(a, b) { return a.priority - b.priority; });

				/* Enlève la coche de validation */
				$(this).closest('.scf-group').removeClass('scf-validated');

				/* Affiche l'erreur */
				errors[0].item.addClass('scf-error');
				errors[0].group.attr('data-error', errors[0].code).children('.scf-error-text').html(errors[0].message);

			/* Si aucune erreur présente */
			} else {

				/* Enlève l'erreur et affiche la coche */
				$(this).closest('.scf-group').removeClass('scf-error').removeAttr('data-error').addClass('scf-validated').children('.scf-error-text').html('');
			}
		});
	}

	$('body').on('blur', '.scf-group input, .scf-group.scf-checkbox label.scf-checkbox-item input, .scf-group.scf-radio label.scf-radio-item input, .scf-group.scf-select .scf-select-2-selector, .scf-group.scf-select .scf-select-2-option, .scf-group.scf-textarea textarea, .scf-group.scf-wysiwyg textarea', scf_blur_field);


	/**
	 * À la perte de focus d'un select, referme le select
	 *
	 * @param Event e L'event ayant déclenché la perte du focus
	 */
	window.scf_blur_select = function(e) {
		if (__scf_debug) console.log(arguments);

		if (e!==null && e.relatedTarget) {
			/* Si le nouveau focus est en dehors du select */
			if (!$(e.relatedTarget).closest('.scf-group').length || $(e.relatedTarget).closest('.scf-group').attr('id')!=$(this).closest('.scf-group').attr('id')) {

				/* Ferme le select */
				$(this).closest('.scf-group').find('input.scf-select-2-opener').prop("checked", false).trigger('change');

				/* Reset la navigation clavier au sein du select */
				scf_keypress_letter = {
					letter : '',
					reset : true,
					timeout : null,
					option : null,
				};
			}
		}
	}

	$('body').on('blur', '.scf-group.scf-select .scf-select-2-selector, .scf-group.scf-select .scf-select-2-option', scf_blur_select);
	

	/**
	 * Au changement du select2, modifie la valeur dans le select natif
	 *
	 * @param Event e L'event ayant déclenché le changement
	 * @return void
	 */
	window.scf_change_select_natif = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Enlève le change event pour éviter boucle infinie */
		$('body').off('change', '.scf-group.scf-select select', window.scf_change_select_2);

		/* Récupère la/les valeur(s) du select */
		let val = [];
		$(this).closest('.scf-group.scf-select[data-name]').find('input[name="'+$(this).closest('.scf-group.scf-select[data-name]').attr('data-name')+'"]:checked').each(function() {
			val.push($(this).val());
		});

		/* Modifie les valeurs du select natif par celle du select2 */
		$(this).closest('.scf-group.scf-select').find('select').val(val);//.change();
		
		/* Remet le change event */
		$('body').on('change', '.scf-group.scf-select select', window.scf_change_select_2);
	}

	$('body').on('change', '.scf-group.scf-select input[type="radio"][name], .scf-group.scf-select input[type="checkbox"][name]', window.scf_change_select_natif);


	/**
	 * Au changement du select natif, modifie la valeur dans le select2
	 *
	 * @param Event e L'event ayant déclenché le changement
	 * @return void
	 */
	window.scf_change_select_2 = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Enlève le change event pour éviter boucle infinie */
		$('body').off('change', '.scf-group.scf-select input[type="radio"][name], .scf-group.scf-select input[type="checkbox"][name]', window.scf_change_select_natif);

		/* Récupère la/les valeur(s) du select */
		let vals = $(this).closest('.scf-group.scf-select').find('select').val();
		if (!Array.isArray(vals)) vals = [vals];

		/* Enlève toutes les valeurs du select 2 */
		$(this).closest('.scf-group.scf-select[data-name]').find('input[name="'+$(this).closest('.scf-group.scf-select[data-name]').attr('data-name')+'"]:checked').each(function() {
			$(this).prop('checked', false);
		});

		/* Coche dans le select 2 les valeur du select natif */
		for (var i = 0; i < vals.length; i++) {
			$(this).closest('.scf-group.scf-select[data-name]').find('input[name="'+$(this).closest('.scf-group.scf-select[data-name]').attr('data-name')+'"][value="'+vals[i]+'"]').each(function() {
				$(this).prop('checked', true).trigger('change');
			});
		};

		/* Remet le change event */
		$('body').on('change', '.scf-group.scf-select input[type="radio"][name], .scf-group.scf-select input[type="checkbox"][name]', window.scf_change_select_natif);
	}

	$('body').on('change', '.scf-group.scf-select select', window.scf_change_select_2);


	/**
	 * À l'ouverture d'un select, si celui-ci est trop bas pour afficher les options correctement, affiche les options au-dessus du champ
	 *
	 * @param Event e L'event ayant déclenché l'ouverture du select
	 */
	window.scf_position_select_options = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Si le select2 est ouvert */
		if (!$(this).closest('.scf-group.scf-select').find('.scf-select-2-opener').first().is(':checked')) {
			let options = $(this).closest('.scf-group.scf-select').find('.scf-select-2-options[data-height]').first();
			if (options) {

				/* Récupère la hauteur des options (valeur maximale à 236) */
				let height = parseInt(options.attr('data-height'));
				height = Math.min(height, 236);

				/* Si la hauteur dépasse de l'écran */
				if (options.offset().top + height > $(document).height()) {
					$(this).closest('.scf-select-2').addClass('scf-select-overflow');
				} else {
					$(this).closest('.scf-select-2').removeClass('scf-select-overflow');
				}
			} else {
				$(this).closest('.scf-select-2').removeClass('scf-select-overflow');
			}
		} else {
			$(this).closest('.scf-select-2').removeClass('scf-select-overflow');
		}
	}

	$('body').on('click', '.scf-select-2-selector[aria-owns]', scf_position_select_options);


	/**
	 * À l'ouverture et fermeture d'un select, change le aria-expanded
	 *
	 * @param Event e L'event ayant déclenché l'ouverture du select
	 */
	window.scf_select_toggle_change = function(e) {
		if (__scf_debug) console.log(arguments);

		if ($(this).is(':checked')) {
			$(this).closest('.scf-group').find('.scf-select-2-selector').attr('aria-expanded', true);
		} else {
			$(this).closest('.scf-group').find('.scf-select-2-selector').attr('aria-expanded', false);

			/* Si c'est le select2 du pays du téléphone qui est fermé, met le focus sur le champ téléphone */
			if ($(this).closest('.scf-group').parent().is('.scf-tel-country-selector')) {
				$(this).closest('.scf-group').parent().closest('.scf-group').find('input[type="tel"]').focus();
			}
		}
	}

	$('body').on('change', '.scf-select-2-opener', scf_select_toggle_change);


	/**
	* Au changement de la valeur d'un select, modifie aria-activedescendant et aria-selected
	 *
	 * @param Event e L'event ayant déclenché le changement de la valeur du select
	 */
	window.scf_select_change_option = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Trouve le label correspondant à l'option selectionné */
		let label = $(this).closest('.scf-group').find('.scf-select-2-option[data-for="'+$(this).attr('id')+'"]');
		if (label.length) {
			/* Modifie la valeur d'aria-activedescendant par l'id du label */
			$(this).closest('.scf-group').find('.scf-select-2-selector').attr('aria-activedescendant', label.attr('id'));
		}

		/* Parcours chaque label des options du select */
		$(this).closest('.scf-group').find('.scf-select-2-item-input, .scf-select-2-item-placeholder').each(function() {
			let label = $(this).closest('.scf-group').find('.scf-select-2-option[data-for="'+$(this).attr('id')+'"]');
			if (label.length) {

				/* Si l'option est selectionné met aria-selected a true, sinon false */
				if ($(this).is(':checked')) {
					label.attr('aria-selected', true);
				} else {
					label.attr('aria-selected', false);
				}
			}

		});

	}

	$('body').on('change', '.scf-select-2-item-input, .scf-select-2-item-placeholder', scf_select_change_option);
	

	/**
	 * Pour chaque champ conditionnel, ajoute un evenement au changement des champs qui font la condition pour vérifier si la condition est respectée ; affiche ou masque le champ en conséquence
	 */
	window.scf_set_conditional_events = function() {
		if (__scf_debug) console.log(arguments);

		let input = $(this);

		/* Ensemble des conditions */
		let conditions = input.data('scfConditional');

		/* Parcours les conditions récursivement */
		(function conditional_add_event_recursive(conditions) {

			/* Si une condition nom=valeur est trouvé */
			if (typeof conditions.name !== 'undefined' && typeof conditions.value !== 'undefined') {

				function conditional_on_event() {

					/* Si le champ n'est pas conditionnel, retourne vrai */
					if (!input.is('[data-scf-conditional]')) return true;

					let base_conditions = input.data('scfConditional');
					
					/* Fonction récursive qui verifie si les conditions sont respectés */
					let conditions_respected = (function conditional_check_recursive(conditions) {

						/* Si la condition est de type nom=valeur */
						if (typeof conditions.name !== 'undefined' && typeof conditions.value !== 'undefined') {

							/* Si un champ avec le nom de la condition existe */
							if ($('.scf-group.scf-group-name-'+conditions.name).length) {
								let group = $('.scf-group.scf-group-name-'+conditions.name).first();

								/* Récupère la/les valeur(s) du champ */
								let values = [group.find('input[name="'+conditions.name+'"]').first().val()];
								let value = conditions.value;
								if (group.is('.scf-select') || group.is('.scf-radio') || group.is('.scf-checkbox') || group.is('.scf-buttons')) {
									values = [];
									group.find('input[name="'+conditions.name+'"]:checked').each(function() { values.push($(this).val()); });
								}

								/* Si la condition exige un type date et que le champ est de type date, récupère le champ date au format Y-m-d via moment.js */
								if (group.is('.scf-date') && conditions.type=='DATE') {
									let input = group.find('input[name="'+conditions.name+'"]').first();
									let datepicker_id = 'picker_'+input.attr('id').replaceAll('-', '_');
									let datepicker = window[datepicker_id];
									if (typeof datepicker == 'object' && datepicker !== null) {
										let date = moment(input.val(), datepicker._o.format, true);
										if (date.isValid()) {
											values = [date.format('YYYY-MM-DD')];
										}
									}
								}

								/* Formate les deux valeurs de comparaison selon le type demandé */
								switch(conditions.type) {
									case 'NUMERIC' :
										value = Number(value);
										values = values.map(x => Number(x));
									break;
									case 'STRING' :
										value = String(value);
										values = values.map(x => String(x));
									break;
									case 'DATE' :
										value = new Date(String(value));
										values = values.map(x => new Date(String(x)));

										/* Convertie en numéraire pour faciliter la comparaison */
										value = Number(''+value.getFullYear()+(value.getMonth()+1)+value.getDate());
										values = values.map(x => Number(''+x.getFullYear()+(x.getMonth()+1)+x.getDate()));
									break;
									case 'BOOL' :
										value = Boolean(value);
										values = values.map(x => Boolean(x));
									break;
									default:
										value = String(value);
										values = values.map(x => String(x));
									break;
								}

								/* Compare les deux valeurs selon l'opérateur de comparaison */
								switch(conditions.compare) {
									case '=' :
										if (values.includes(value)) return true;
									break;
									case '!=' :
										if (!values.includes(value)) return true;
									break;
									case '<' :
										if (values.filter(v => v < value).length>0) return true;
									break;
									case '<=' :
									case '=<' :
										if (values.filter(v => v <= value).length>0) return true;
									break;
									case '>' :
										if (values.filter(v => v > value).length>0) return true;
									break;
									case '>=' :
									case '=>' :
										if (values.filter(v => v >= value).length>0) return true;
									break;
									default:
										if (values.includes(value)) return true;
									break;
								}
							}

							return false;

						/* Si il s'agit d'une composition de condition relié par un opérateur de comparaison */
						} else if (typeof conditions.relation !== 'undefined' && Object.keys(conditions).length>2) {

							/**
							 * Valeur renvoyé par défaut en fonction de relation
							 * Si c'est une condition OU, renvoie faux par défaut ; vrai si une comparaison est juste
							 * Si c'est une condition AND, renvoie vrai par défaut ; faux si une comparaison est fausse
							 */
							let output = ('OR' === conditions.relation.toUpperCase() ) ? false : true;

							/* Pour chaque sous-condition, la vérifie et effectue la comparaison */
							for (const key in conditions) {
								let value = conditions[key];
								if (key !== 'relation' && typeof value === 'object' && Object.keys(value).length>1) {

									/* Si la sous-condition est respectée */
									if (conditional_check_recursive(value)) {
										if (!output) return true;

									/* Si la sous-condition n'est pas respectée */
									} else {
										if (output) return false;
									}
								}
							}
							return output;
						}

						return false;

					})(base_conditions);

					/* Affiche ou masque le champ si les conditions sont respectés ou non */
					if (conditions_respected) {
						input.show();
					} else {
						input.hide();
					}

				}

				/* À la modification du champ avec le nom, vérifie la condition */
				$('body').on('input change', '.scf-group input[name="'+conditions.name+'"], .scf-group select[name="'+conditions.name+'"], .scf-group textarea[name="'+conditions.name+'"]', conditional_on_event);

				/* Initialise les champs conditionnels */
				$(document).ready(conditional_on_event);
			
			/* Si il s'agit d'une composition de condition relié par un opérateur de comparaison */
			} else if (typeof conditions.relation !== 'undefined' && Object.keys(conditions).length>2) {

				/* Pour chaque sous-condition, la parcours et ajoute l'evenement */
				for (const key in conditions) {
					let value = conditions[key];
					if (key !== 'relation' && typeof value === 'object' && Object.keys(value).length>1) {
						conditional_add_event_recursive(value);
					}
				}
			}
		})(conditions);
	}

	$('.scf-group[data-scf-conditional]').each(scf_set_conditional_events);
	
	
	/**
	 * Verifie si le label doit etre affiché
	 * Le champ doit avoir au moins 1 caractère ou 1 option selectionnée
	 *
	 * @param Node input L'input à vérifier
	 * @return boolean Si le champ a au moins un caractère ou une option sélectionnée
	 */
	window.scf_check_label_displayed = function(input) {
		if (__scf_debug) console.log(arguments);

		let group = input.closest('.scf-group');

		if (group.hasClass('scf-email') || group.hasClass('scf-password') || group.hasClass('scf-url') || group.hasClass('scf-number') || group.hasClass('scf-text')) {
			return input.val().length>0;
		} else if (group.hasClass('scf-tel')) {
			return group.find('.scf-input > input').val().length>0;
		} else if (group.hasClass('scf-select')) {
			return group.find('input.scf-select-2-item-input:checked').length>0;
		} else if (group.hasClass('scf-checkbox')) {
			return group.find('.scf-checkbox-item > input:checked').length>0;
		} else if (group.hasClass('scf-radio')) {
			return group.find('.scf-radio-item > input:checked').length>0;
		} else {
			return input.val().length>0;
		}
	}


	/**
	 * Lors du clic sur une option d'un select non multiple, ferme le select
	 */
	window.scf_close_select_on_option = function() {
		if (__scf_debug) console.log(arguments);

		/* Ferme le select */
		$(this).closest('.scf-group').find('.scf-select-2-opener').prop('checked', false).trigger('change');

		/* Reset la navigation clavier au sein du select */
		scf_keypress_letter = {
			letter : '',
			reset : true,
			timeout : null,
			option : null,
		};
	}

	$('body').on('click', '.scf-select-2:not(.scf-select-2-multiple) .scf-select-2-options > .scf-select-2-option', window.scf_close_select_on_option);


	/**
	 * Lors du clic sur le select, ouvre ou ferme le select en fonction de son état précédent
	 */
	window.scf_toggle_select_open = function() {
		if (__scf_debug) console.log(arguments);

		let chk = $(this).closest('.scf-group.scf-select').find('input.scf-select-2-opener');
		let checked = chk.prop('checked');
		chk.prop('checked', !checked).trigger('change');
	}

	$('body').on('click', '.scf-group.scf-select .scf-select-2-selector', window.scf_toggle_select_open );


	/**
	 * Lors du clic sur une option du select, selectionne l'option
	 */
	window.select_option = function() {
		if (__scf_debug) console.log(arguments);

		if ($(this).hasClass('scf-select-2-option-placeholder')) {
			$(this).closest('.scf-group.scf-select').find('input.scf-select-2-item-placeholder').prop('checked', true).trigger('change');
		} else if ($(this).is('[data-for]')) {
			$(this).closest('.scf-group.scf-select').find('input#'+$(this).attr('data-for')).prop('checked', true).trigger('change');
		}
	}

	$('body').on('click', '.scf-group.scf-select .scf-select-2-option', window.select_option);


	/**
	 * Lors du clic ailleurs dans le document que dans un select, ferme tous les select sauf celui cliqué
	 *
	 * @param Event e L'event ayant déclenché le clique
	 */
	window.scf_click_document = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Si le click n'est pas sur l'ouverture d'un select */
		if (!$(e.target).is('[type="checkbox"].scf-select-2-opener')) {

			/* Si le click est fait sur un element du select */
			if ($(e.target).closest('.scf-group.scf-select').length) {

				/* Pour tous les select ouverts */
				$('.scf-select-2-opener:checked').each(function() {

					/* Si le select en question n'est pas celui cliqué, le ferme */
					if ($(this).closest('.scf-group').find('.scf-select-2')[0]!=$(e.target).closest('.scf-group').find('.scf-select-2')[0]) {

						/* Ferme le select */
						$(this).prop('checked', false).trigger('change');

						/* Reset la navigation clavier au sein du select */
						scf_keypress_letter = {
							letter : '',
							reset : true,
							timeout : null,
							option : null,
						};
					}
				});
			} else {
				/* Ferme tous les selects */
				$('.scf-select-2-opener:checked').each(function() {
					$(this).prop('checked', false).trigger('change');
				});

				/* Reset la navigation clavier au sein du select */
				scf_keypress_letter = {
					letter : '',
					reset : true,
					timeout : null,
					option : null,
				};
			}
		}
	}

	$(document).on('click', scf_click_document);


	/**
	 * Scroll dans la liste d'options vers l'option recherché via le caractère rentré
	 * 
	 * @var Node   group  Le groupe du select concerné
	 * @var string letter La lettre tapée
	 */
	window.scf_select_move_char = function(group, letter) {
		if (__scf_debug) console.log(arguments);

		/* Si une lettre est bien renseignée */
		if ((typeof letter == 'string' || letter instanceof String) && letter.length>0) {

			/* Enlève le timeout qui reset l'enchainement de lettres */
			clearTimeout(scf_keypress_letter.timeout);

			/* Si la lettre précédemment tapée est la même, parcours au sein des options correspondantes */
			if (scf_keypress_letter.letter.length && scf_keypress_letter.option && scf_keypress_letter.letter.slice(-1) == letter) {

				/* Récupère la liste des options correspondant à la recherche */
				var options = group.find('.scf-select-2-options .scf-select-2-option').filter(function(i) {
					return ($(this).text().toLowerCase().trim().substr(0, scf_keypress_letter.letter.length) == scf_keypress_letter.letter.toLowerCase());
				});

				/* Si une ou plusieurs option(s) corresponde(nt) */
				if (options.length) {

					/* Récupère l'option suivant celle déjà sélectionnée (première si il s'agit de la dernière) */
					var next = options[($.inArray(scf_keypress_letter.option[0], options) + 1) % options.length];
					scf_keypress_letter.option = $(next);

					/* Si le select est ouvert, scroll jusqu'à l'option */
					if (group.find('.scf-select-2-opener').is(':checked')) {
						var scroll = scf_keypress_letter.option.position().top;
						group.find('.scf-select-2-options').scrollTop(group.find('.scf-select-2-options').scrollTop()+scroll);

					/* Si le select est fermé, sélectionne l'option */
					} else {
						var checkbox = group.find('input.scf-select-2-item-input#'+scf_keypress_letter.option.attr('data-for'));
						if (checkbox.length) {
							checkbox.prop("checked", true);
						}
					}
				}
			} else {

				/* Si la lettre doit se rajouter à la suite ou remplacer les caractères précédents */
				if (scf_keypress_letter.reset) {
					scf_keypress_letter.letter = letter.toLowerCase();
				} else {
					scf_keypress_letter.letter += letter.toLowerCase();
				}
				
				/* Récupère la liste des options correspondant à la recherche */
				var options = group.find('.scf-select-2-options .scf-select-2-option').filter(function(i) {
					return ($(this).text().toLowerCase().trim().substr(0, scf_keypress_letter.letter.length) == scf_keypress_letter.letter.toLowerCase());
				});

				/* Si une ou plusieurs option(s) corresponde(nt) */
				if (options.length) {

					/* Indique l'option sélectionnée comme étant la première de la liste */
					scf_keypress_letter.option = options.first();

					/* Si le select est ouvert, scroll jusqu'à l'option */
					if (group.find('.scf-select-2-opener').is(':checked')) {
						var scroll = scf_keypress_letter.option.position().top;
						group.find('.scf-select-2-options').scrollTop(group.find('.scf-select-2-options').scrollTop()+scroll);

					/* Si le select est fermé, sélectionne l'option */
					} else {
						var checkbox = group.find('input.scf-select-2-item-input#'+scf_keypress_letter.option.attr('data-for'));
						if (checkbox.length) {
							checkbox.prop("checked", true);
						}
					}

				/**
				 * Si aucune option ne correspond, reset les lettres et effectue la recherche avec juste la lettre rentré (et non plus l'enchainement de lettres)
				 * Ne relance pas la recherche via scf_select_move_char car cela crée une boucle infinie
				 */
				} else {

					/* Remplace l'enchainement de caractères par la lettre seule */
					scf_keypress_letter.letter = letter.toLowerCase();

					/* Récupère la liste des options correspondant à la nouvelle recherche */
					var options = group.find('.scf-select-2-options .scf-select-2-option').filter(function(i) {
						return ($(this).text().toLowerCase().trim().substr(0, scf_keypress_letter.letter.length) == scf_keypress_letter.letter.toLowerCase());
					});

					/* Si une ou plusieurs option(s) corresponde(nt) */
					if (options.length) {

						/* Indique l'option sélectionnée comme étant la première de la liste */
						scf_keypress_letter.option = options.first();

						/* Si le select est ouvert, scroll jusqu'à l'option */
						if (group.find('.scf-select-2-opener').is(':checked')) {
							var scroll = scf_keypress_letter.option.position().top;
							group.find('.scf-select-2-options').scrollTop(group.find('.scf-select-2-options').scrollTop()+scroll);

						/* Si le select est fermé, sélectionne l'option */
						} else {
							var checkbox = group.find('input.scf-select-2-item-input#'+scf_keypress_letter.option.attr('data-for'));
							if (checkbox.length) {
								checkbox.prop("checked", true);
							}
						}
					}
				}
			}

			/* Permet l'enchainement de plusieurs lettres */
			scf_keypress_letter.reset = false;

			/* Crée un timeout qui permet de réinitialiser les lettres tapées */
			scf_keypress_letter.timeout = setTimeout(() => {
				scf_keypress_letter.reset = true;
			}, 1300);
		}
	}


	/**
	 * Lorsqu'une touche d'une lettre (pour les touches spéciales voir keydown plus bas) est appuyée avec un select ouvert, scrolle les options ou selectionne la première option qui correspond à la recherche
	 *
	 * @param Event e L'event ayant déclenché le keypress
	 */
	window.scf_keypress_field = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Si le champ focus est un input, ne bouge pas les options, cela ecrit plutot la lettre dans le champ */
		if (!$(':focus').is('input')) {

			/* Si un ou plusieurs select(s) est ouvert, scrolle dans les options */
			if ($('.scf-group.scf-select .scf-select-2-opener:checked').length) {
				var letter = String.fromCharCode(e.which);
				$('.scf-group.scf-select .scf-select-2-opener:checked').each(function() {
					scf_select_move_char($(this).closest('.scf-group'), letter);
				});

			/* Si le champ focus est un select, selectionne l'option correspondant à la recherche */
			} else if ($(':focus').first().closest('.scf-group').is('.scf-select')) {
				var letter = String.fromCharCode(e.which);
				scf_select_move_char($(':focus').first().closest('.scf-group'), letter);
			}
		}
	}

	$('body').on('keypress', scf_keypress_field);


	/**
	 * À chaque modification d'un input, affiche le label ; teste les recommandation ; affiche la coche : efface les erreurs
	 */
	window.scf_input_field = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Récupère la valeur du champ */
		let input_val = $(this).val();

		/* Affiche le label si le champ a au moins 1 caractère ou 1 option selectionnée */
		if (scf_check_label_displayed($(this))) {
			$(this).closest('.scf-group').addClass('scf-label-visible');
		} else {
			$(this).closest('.scf-group').removeClass('scf-label-visible');
		}

		/* Teste les recommandations du mot de passe et les marque comme valide si elles sont respectées */
		if ($(this).closest('.scf-group').hasClass('scf-password')) {

			let hint_uppercase = $(this).closest('.scf-group').find('.scf-password-hint-details-item.scf-password-hint-details-item-uppercase');
			let hint_lowercase = $(this).closest('.scf-group').find('.scf-password-hint-details-item.scf-password-hint-details-item-lowercase');
			let hint_number    = $(this).closest('.scf-group').find('.scf-password-hint-details-item.scf-password-hint-details-item-number');
			let hint_special   = $(this).closest('.scf-group').find('.scf-password-hint-details-item.scf-password-hint-details-item-special');
			let hint_length    = $(this).closest('.scf-group').find('.scf-password-hint-details-item.scf-password-hint-details-item-length');
			let hint_common    = $(this).closest('.scf-group').find('.scf-password-hint-details-item.scf-password-hint-details-item-common');

			let valid_class = 'scf-password-hint-details-item-validated';

			if (scf_check_password_uppercase(input_val)) { hint_uppercase.addClass(valid_class); } else { hint_uppercase.removeClass(valid_class); }
			if (scf_check_password_lowercase(input_val)) { hint_lowercase.addClass(valid_class); } else { hint_lowercase.removeClass(valid_class); }
			if (scf_check_password_number(input_val))    { hint_number.addClass(valid_class);    } else { hint_number.removeClass(valid_class); }
			if (scf_check_password_special(input_val))   { hint_special.addClass(valid_class);   } else { hint_special.removeClass(valid_class); }
			if (scf_check_password_length(input_val))    { hint_length.addClass(valid_class);    } else { hint_length.removeClass(valid_class); }
			if (scf_check_password_common(input_val))    { hint_common.addClass(valid_class);    } else { hint_common.removeClass(valid_class); }

			if (input_val.length<=0) { $(this).closest('.scf-group').find('.scf-password-hint-details-item-validated').removeClass(valid_class); }
		}

		/* Si on change le pays d'un champ téléphone, revalide le numero */
		if ($(this).closest('.scf-group').parent().hasClass('scf-tel-country-selector')) {
			$(this).closest('.scf-group').parent().closest('.scf-group').find('input[type="tel"]').trigger('input');
		}

		/* Valide les champs en indiquant la précédente erreur ; si celle-ci n'a pas eu lieu avant la requete ajax, n'envoie pas la requete */
		scf_validate_input(e, $(this), (($(this).closest('.scf-group')[0].hasAttribute('data-error')) ? parseInt($(this).closest('.scf-group').attr('data-error')) : null)).then((errors) => {

			/* Si au moins une erreur est présente */
			if (typeof errors == 'object' && errors.length > 0) {

				/* Tri les erreurs par ordre de priorité */
				errors.sort(function(a, b) { return a.priority - b.priority; });

				/* Enlève la coche de validation */
				$(this).closest('.scf-group').removeClass('scf-validated');

				/**
				 * Si le champ n'affichait pas d'erreur ou la première erreur est différente de celle précédemment affichée, masque les erreurs
				 * Cela fait que l'erreur ne s'affichera que lors du blur et non pas à chaque fois qu'un input est rentré.
				 * Ca indique que l'erreur a été résolue mais n'interrompt pas l'utilisateur avec une nouvelle erreur alors qu'il est en train de taper 
				 */
				if (!$(this).closest('.scf-group')[0].hasAttribute('data-error') || parseInt(errors[0].code) !== parseInt($(this).closest('.scf-group').attr('data-error'))) {
					$(this).closest('.scf-group').removeClass('scf-error').children('.scf-error-text').html('');
				}

			/* Si le format correspond au format attendu, affiche la coche et masque les erreurs */
			} else if (errors === 0) {

				/* Masque les erreurs */
				$(this).closest('.scf-group').removeClass('scf-error').children('.scf-error-text').html('');

				/* Si le champ avait une erreur, revalide le champ en effectuant la requete ajax pour pouvoir bien mettre la coche */
				if ($(this).closest('.scf-group')[0].hasAttribute('data-error')) {
					scf_validate_input(e, $(this)).then((errors) => {
						/* Si, avec la requete ajax, il n'y a toujours pas d'erreurs, affiche la coche */
						if (errors === 0) $(this).closest('.scf-group').addClass('scf-validated');
					});

				/* Si il n'y avait pas d'erreur alors toute les verifications ont été faites, affiche la coche */
				} else {
					$(this).closest('.scf-group').addClass('scf-validated');
				}
			}
		});
	}

	$('body').on('input', '.scf-group input, .scf-group.scf-textarea textarea, .scf-group.scf-wysiwyg textarea', scf_input_field);
	

	/**
	 * Affiche la valeur de l'input avec son data-format et ses data-specificators
	 *
	 * @var Node input L'input à afficher la valeur selon son format
	 */
	window.scf_format_display_value = function(input) {
		if (__scf_debug) console.log(arguments);

		/* Empeche la fonctionnalité si le navigateur ne gere pas la regex */
		if (!$(input).is('[data-format]') || typeof $(input).attr('data-format')=='undefined' || $(input).attr('data-format')=='' || !regex instanceof RegExp) return;

		/* Récupération du format et des spécificateurs */
		let value = input.attr('data-format');
		let _specificators = $(input).data('specificators');

		/* Empeche la fonctionnalité si aucun spécificateur n'est fournie */
		if (typeof _specificators=='undefined') return;

		/* Effectue une copie de _specificators pour ne pas le modifier */
		let specificators = _specificators.slice();

		if (Array.isArray(specificators)) {

			/* Tri les spécificateurs en fonction de leur position */
			specificators.sort(function(a, b) {
				if (a['default_pos'] > b['default_pos']) {
					return -1;
				} else if (a['default_pos'] < b['default_pos']) {
					return 1;
				} else {
					return 0;
				}
			});

			/* Pour chaque spécificateurs, le remplace par sa valeur */
			for (var i = 0; i < specificators.length; i++) {
				let new_value = value.substr(0, specificators[i]['default_pos']);
				new_value    += specificators[i]['value'];
				new_value    += value.substr(specificators[i]['default_pos']+specificators[i]['default_length']);

				value = new_value;
			};
		}

		/* Affiche la valeur formatée */
		$(input).val(value);
	}
	

	/**
	 * Selectionne le premier spécificateur disponible
	 *
	 * @var Node input L'input auquel selectionner le spécificateur
	 */
	window.scf_format_first_specificator = function(_input) {
		if (__scf_debug) console.log(arguments);

		let input = $(_input);
		let _specificators = input.data('specificators');

		/* Empeche la fonctionnalité si aucun spécificateurs n'est fournie */
		if (typeof _specificators=='undefined') return;

		/* Effectue une copie de _specificators pour ne pas le modifier */
		let specificators = _specificators.slice();

		if (Array.isArray(specificators)) {

			/* Tri les spécificateurs en fonction de leur position */
			specificators.sort(function(a, b) {
				if (a['default_pos'] < b['default_pos']) {
					return -1;
				} else if (a['default_pos'] > b['default_pos']) {
					return 1;
				} else {
					return 0;
				}
			});

			/* Parcours tous les spécificateurs */
			for (var i = 0; i < specificators.length; i++) {

				/* Le premier spécificateur qui a toujours sa valeur par défaut */
				if (specificators[i]['value'] === specificators[i]['default']) {

					/* Début et fin de la selection */
					let start_select = specificators[i]['replace_pos'];
					let length_select = specificators[i]['replace_pos']+specificators[i]['value'].length;
					input.data('specificator-selected', i);

					/* Selectionne un ou plusieurs caractère(s) de l'input */
					input.get(0).setSelectionRange(start_select, start_select);
					input.get(0).setSelectionRange(start_select, length_select);

					return;
				}
			};

			/* Si tous les spécificateurs ont une valeur, prend le premier de la liste */

			/* Début et fin de la selection */
			let start_select = specificators[0]['replace_pos'];
			let length_select = specificators[0]['replace_pos']+specificators[0]['value'].length;
			input.data('specificator-selected', 0);

			/* Selectionne un ou plusieurs caractère(s) de l'input */
			input.get(0).setSelectionRange(start_select, start_select);
			input.get(0).setSelectionRange(start_select, length_select);
		}

		return;
	}
	

	/**
	 * Selectionne le spécificateur suivant celui déjà selectionné
	 *
	 * @var Node input L'input auquel selectionner le spécificateur
	 */
	window.scf_format_next_specificator = function(_input) {
		if (__scf_debug) console.log(arguments);

		let input = $(_input);
		let _specificators = input.data('specificators');

		/* Empeche la fonctionnalité si aucun spécificateurs n'est fournie */
		if (typeof _specificators=='undefined') return;

		/* Effectue une copie de _specificators pour ne pas le modifier */
		let specificators = _specificators.slice();
		
		/* Récupération du spécificateur actuellement sélectionné */
		let specificator = input.data('specificator-selected');

		/* Si aucun spécificateur actuellement sélectionné, met le curseur à -1 pour selectionner le premier selecteur */
		if (typeof specificator === 'undefined' || specificator === false) specificator = -1;

		if (Array.isArray(specificators)) {

			/* Tri les spécificateurs en fonction de leur position */
			specificators.sort(function(a, b) {
				if (a['default_pos'] < b['default_pos']) {
					return -1;
				} else if (a['default_pos'] > b['default_pos']) {
					return 1;
				} else {
					return 0;
				}
			});

			/* Parcours tous les spécificateurs */
			for (var i = 0; i < specificators.length; i++) {

				/* Si le spécificateur actuellement sélectionné a été dépassé */
				if (i > specificator) {

					/* Début et fin de la selection */
					let start_select = specificators[i]['replace_pos'];
					let end_select = specificators[i]['replace_pos']+specificators[i]['value'].length;
					input.data('specificator-selected', i);

					/* Selectionne un ou plusieurs caractère(s) de l'input */
					input.get(0).setSelectionRange(start_select, start_select);
					input.get(0).setSelectionRange(start_select, end_select);

					break;
				}
			};
		}
	}
	

	/**
	 * Selectionne le spécificateur précédant celui déjà selectionné
	 *
	 * @var Node input L'input auquel selectionner le spécificateur
	 */
	window.scf_format_prev_specificator = function(_input) {
		if (__scf_debug) console.log(arguments);

		let input = $(_input);
		let _specificators = input.data('specificators');

		/* Empeche la fonctionnalité si aucun spécificateurs n'est fournie */
		if (typeof _specificators=='undefined') return;

		/* Effectue une copie de _specificators pour ne pas le modifier */
		let specificators = _specificators.slice();
		
		/* Récupération du spécificateur actuellement sélectionné */
		let specificator = input.data('specificator-selected');

		/* Si aucun spécificateur actuellement sélectionné, met le curseur à -1 pour selectionner le premier selecteur */
		if (typeof specificator === 'undefined' || specificator === false) specificator = -1;

		if (Array.isArray(specificators)) {

			/* Tri les spécificateurs en fonction de leur position */
			specificators.sort(function(a, b) {
				if (a['default_pos'] < b['default_pos']) {
					return -1;
				} else if (a['default_pos'] > b['default_pos']) {
					return 1;
				} else {
					return 0;
				}
			});

			/* Parcours tous les spécificateurs et selectionne le précédent */
			let new_specificator = 0;
			for (var i = 0; i < specificators.length; i++) {
				if (i >= specificator) { break; } else { new_specificator = i; }
			};

			/* Début et fin de la selection */
			let start_select = specificators[new_specificator]['replace_pos'];
			let end_select = specificators[new_specificator]['replace_pos']+specificators[new_specificator]['value'].length;
			input.data('specificator-selected', new_specificator);

			/* Selectionne un ou plusieurs caractère(s) de l'input */
			input.get(0).setSelectionRange(start_select, start_select);
			input.get(0).setSelectionRange(start_select, end_select);
		}
	}
	

	/**
	 * Remet la valeur du spécificateur selectionné à défaut
	 *
	 * @var Node input L'input auquel selectionner le spécificateur
	 */
	window.scf_format_reset_specificator = function(_input) {
		if (__scf_debug) console.log(arguments);

		let input = $(_input);
		let _specificators = input.data('specificators');

		/* Empeche la fonctionnalité si aucun spécificateurs n'est fournie */
		if (typeof _specificators=='undefined') return;

		/* Effectue une copie de _specificators pour ne pas le modifier */
		let specificators = _specificators.slice();
		
		/* Récupération du spécificateur actuellement sélectionné */
		let specificator = input.data('specificator-selected');

		/* Si aucun spécificateur actuellement sélectionné, met le curseur à -1 pour selectionner le premier selecteur */
		if (typeof specificator === 'undefined' || specificator === false) specificator = -1;

		if (Array.isArray(specificators)) {

			/* Tri les spécificateurs en fonction de leur position */
			specificators.sort(function(a, b) {
				if (a['default_pos'] < b['default_pos']) {
					return -1;
				} else if (a['default_pos'] > b['default_pos']) {
					return 1;
				} else {
					return 0;
				}
			});

			/* Parcours tous les spécificateurs et remet la valeur de celui sélectionné à defaut */
			for (var i = 0; i < specificators.length; i++) {
				if (i == specificator) {
					specificators[i]['value'] = specificators[i]['default'];
					break;
				}
			};

			input.data('specificators', specificators);

			/* Remodifie la valeur affichée */
			scf_format_display_value(input);
		}
	}
	

	/**
	 * Verifie si un champ formatté est remplie
	 * Au moins un sélecteur doit avoir une valeur différente de celle par défaut pour être considéré comme rempli
	 *
	 * @var Node input L'input auquel selectionner le spécificateur
	 */
	window.scf_format_check_filled = function(_input) {
		if (__scf_debug) console.log(arguments);

		let input = $(_input);
		let _specificators = input.data('specificators');

		/* Empeche la fonctionnalité si aucun spécificateurs n'est fournie */
		if (typeof _specificators=='undefined') return;

		/* Effectue une copie de _specificators pour ne pas le modifier */
		let specificators = _specificators.slice();

		if (Array.isArray(specificators)) {

			/* Parcours tous les spécificateurs et si un spécificateur à une valeur différente de sa valeur par défaut, renvoie vrai */
			for (var i = 0; i < specificators.length; i++) {
				if (specificators[i]['value'] !== specificators[i]['default']) {
					return true;
				}
			};
		}
		
		return false;
	}
	

	/**
	* Verifie si le champ est correct (si au moins un des champs a la même valeur que la valeur par défaut (mais pas tous) ou une valeur qui ne respecte pas le regex, renvoie faux)
	**/
	/**
	 * Verifie si un champ formatté est correctement rempli
	 * Tous les sélecteurs doit avoir une valeur différente de celle par défaut et doivent respecter le regex correspondant
	 *
	 * @var Node input L'input auquel selectionner le spécificateur
	 */
	window.scf_format_check_correct = function(_input) {
		if (__scf_debug) console.log(arguments);

		let input = $(_input);
		let _specificators = input.data('specificators');

		/* Empeche la fonctionnalité si aucun spécificateurs n'est fournie */
		if (typeof _specificators=='undefined') return;
		
		/* Effectue une copie de _specificators pour ne pas le modifier */
		let specificators = _specificators.slice();

		/* Si tous les spécificateurs sont à défaut */
		let all_default = true;

		/* Si au moins un spécificateur est à défaut */
		let one_default = false;

		if (Array.isArray(specificators)) {
			
			/* Parcours tous les spécificateurs */
			for (var i = 0; i < specificators.length; i++) {

				/* Si il a la valeur par défaut, indique qu'un spécificateur est à défaut */
				if (specificators[i]['value'] === specificators[i]['default']) {
					one_default = true;
				} else {

					/* Indique que tous les spécificateurs ne sont pas à défaut */
					all_default = false;

					/* Si le spécificateur n'a pas de format, le champ est incorrect */
					if (typeof specificators[i]['type'] === 'undefined') return false;

					/* Regex de validation par défaut */
					let validator = new RegExp("^[[a-zA-Z0-9$&+,:;=?@#|'<>.^*()%!-][^a-zA-Z0-9\\s\\(\\)\\[\\]\\{\\}\\\\^\\$\\|\\?\\*\\+\\.\\<\\>\\-\\=\\!\\_]]$");

					/* Indique qu'on verifie une regex et non pas une fonction (met check_by_regex à false pour les spécificateurs de type fonction) */
					let check_by_regex = true;

					/* Regex pour vérifier une chaine de caractère avec uniquement l'alphabet latin */
					if (specificators[i]['type'] === 'c') { validator = new RegExp("^[a-zA-Z]$"); }

					/* Regex pour vérifier une chaine de caractère avec l'alphabet latin et les symboles */
					else if (specificators[i]['type'] === 'C') { validator = new RegExp("^[[a-zA-Z][^a-zA-Z0-9\\s\\(\\)\\[\\]\\{\\}\\\\^\\$\\|\\?\\*\\+\\.\\<\\>\\-\\=\\!\\_]]$"); }

					/* Regex pour vérifier un chiffre */
					else if (specificators[i]['type'] === 'i') { validator = new RegExp("^[0-9]$"); }

					/* Regex pour vérifier un symbole */
					else if (specificators[i]['type'] === 's') { validator = new RegExp("^[$&+,:;=?@#|'<>.^*()%!-]$"); }

					/* Regex pour vérifier tout caractère */
					else if (specificators[i]['type'] === 'a') { validator = new RegExp("^.*$"); }

					/* Regex pour vérifier via une regex fournie */
					else if (specificators[i]['type'] === 'r' && typeof specificators[i]['regex']!=='undefined') { validator = new RegExp("^["+specificators[i]['regex']+"]$"); }

					/* Regex pour vérifier via une regex fournie insensible à la casse */
					else if (specificators[i]['type'] === 'R' && typeof specificators[i]['regex']!=='undefined') { validator = new RegExp("^["+specificators[i]['regex']+"]$", 'i'); }

					/* Valide via une fonction fournie */
					else if (specificators[i]['type'] === 'f' && typeof specificators[i]['function']==='function') {
						validator = specificators[i]['function'];
						check_by_regex = false;
					}

					/* Si la vérification s'est bien passée */
					let key_correct = false;

					/* Si la validation se fait par une regex */
					if (check_by_regex) {
						key_correct = validator.test(specificators[i]['value']);

					/* Si la validation se fait par une fonction */
					} else {
						try {
							let result = validator(specificators[i]['value'], $(this), specificators);
							key_correct = Boolean(result);
						} catch (error) {}
					}

					/* Si le spécificateur ne passe pas le test, le champ est incorrect */
					if (!key_correct) return false;
				}
			}
		}

		/* Si tous ou aucun spécificateur n'est à défaut */
		return !one_default || all_default;
	}
	

	/**
	 * Récupère la valeur de l'input et la met dans les spécificateurs correspondants
	 * Si le champ a déjà une valeur, indique à chaque spécificateur quelle valeur correspond
	 *
	 * @var Node input L'input auquel selectionner le spécificateur
	 */
	window.scf_format_recheck_value = function(_input) {
		if (__scf_debug) console.log(arguments);

		let input = $(_input);
		let _specificators = input.data('specificators');

		/* Empeche la fonctionnalité si aucun spécificateurs n'est fournie */
		if (typeof _specificators=='undefined') return;

		/* Effectue une copie de _specificators pour ne pas le modifier */
		let specificators = _specificators.slice();

		if (Array.isArray(specificators)) {

			/* Tri les spécificateurs en fonction de leur position */
			specificators.sort(function(a, b) {
				if (a['default_pos'] < b['default_pos']) {
					return -1;
				} else if (a['default_pos'] > b['default_pos']) {
					return 1;
				} else {
					return 0;
				}
			});

			/* Récupère la valeur du champ */
			let value = input.val();

			/* Parcours tous les spécificateurs et remet la valeur de chaque à la lettre correspondante dans la valeur de l'input */
			for (var i = 0; i < specificators.length; i++) {
				specificators[i]['value'] = value.substr(specificators[i]['replace_pos'], specificators[i]['replace_length']);
			};

			input.data('specificators', specificators);
		}
	}
	

	/**
	 * Intitialise les spécificateur avec la regex globale
	 *
	 * @var Node input L'input auquel selectionner le spécificateur
	 */
	window.scf_initialize_specificators = function(_input) {
		if (__scf_debug) console.log(arguments);

		let input = $(_input);

		/* Récupère le format demandé */
		let format = input.attr('data-format');

		/* Empeche la fonctionnalité si le navigateur ne gere pas la regex */
		if (typeof format=='undefined' || format=='' || !regex instanceof RegExp) return;

		/* setSelectionRange n'est pas supporté par les champs de type email donc change les inputs en text (la validation se fera quand même via une regex d'email cf. ligne 245) */
		if (input.attr('type')=='email') input.attr('type', 'text');

		/* Tableau de l'ensemble des spécificateurs */
		let specificators = [];

		/* Incréments (i par spécificateur, j position du curseur dans le format, k position du curseur dans la valeur une foix formaté) */
		let i = j = k =0;

		/* Parcours des spécificateurs renvoyé par la regex globale */
		while ((match = regex.exec(format)) !== null) {

			/* Enregistrement du spécificateur */
			specificators.push({
				'value'          : (typeof match[1] == 'undefined') ? '-' : match[1],      // La valeur affichée du spécificateur
				'default'        : (typeof match[1] == 'undefined') ? '-' : match[1],      // La valeur par défaut si l'utilisateur sa saisie
				'i'              : i,                                                      // L'id incrémenté du spécificateur
				'regex'          : match[2],                                               // La regex fournie dans le spécificateur (uniquement pour les spécificateurs de type regex)
				'function'       : new Function('c', 'i', 's', match[3]),                  // La fonction fournie dans le spécificateur (uniquement pour les spécificateurs de type fonction)
				'type'           : match[4],                                               // Le type de données attendu (parmi les lettres c/C/i/s/a/r/R/f)
				'default_pos'    : match['index'],                                         // La position du spécificateur dans le format
				'default_length' : match[0].length,                                        // La longueur du spécificateur dans le format
				'replace_length' : (typeof match[1] == 'undefined') ? 1 : match[1].length, // La longueur du caractère de remplacement
				'replace_pos'    : match['index']-j+k,                                     // La position du caractère de remplacement dans la valeur formatée
			});

			i++;
			j += match[0].length;
			k += (typeof match[1] == 'undefined') ? 1 : match[1].length;
		}

		/* Enregistrement des spécificateurs */
		input.data('specificators', specificators);

		/* Si aucune valeur n'est fournie, change la valeur du champ par le format formaté */
		if (input.val()=='') { scf_format_display_value(input); }

		/* Si une valeur est fournie, renseigne la valeur des spécificateurs par celle de la valeur */
		else { scf_format_recheck_value(input); }
	}
	

	/**
	 * Vérifie le statut des boutons du wysiwyg
	 * event pathChange, select et cursor de Squire
	 *
	 * @param Event e L'event ayant déclenché la vérification
	 */
	window.scf_wysiwyg_check_status_button = function(event) {
		if (__scf_debug) console.log(arguments);

		let editor = this;
		$(editor._root).closest('.scf-group.scf-wysiwyg').find('.scf-input > .scf-wysiwyg-actionbar').each(function() {
			$(this).find('span[role="button"][data-tag]').each(function() {
				if (editor.hasFormat($(this).attr('data-tag'))) {
					$(this).addClass('active');
				} else {
					$(this).removeClass('active');
				}
			});
		});
	}
	

	/**
	 * Affiche l'actionbar float lors d'une selection
	 * event mouseup
	 *
	 * @param Editor editor L'editeur wysiwyg ou la selection a été faite
	 */
	window.scf_wysiwyg_display_float_actionbar = function(event) {
		if (__scf_debug) console.log(arguments);

		let selection = this.getSelection();
		if (selection instanceof Range && !selection.collapsed) {
			let select_pos = selection.getBoundingClientRect();
			let block_pos = $(this._root).closest('.scf-input').get(0).getBoundingClientRect();
			let top = select_pos.top - block_pos.top + select_pos.height;
			let left = select_pos.left - block_pos.left + (select_pos.width / 2);
			$(this._root).closest('.scf-group.scf-wysiwyg').find('.scf-input > .scf-wysiwyg-actionbar.scf-wysiwyg-actionbar-float').css('top', top+'px').css('left', left+'px').addClass('visible');
		}
	}
	

	/**
	 * Masque l'actionbar en float si la selection est enlevé
	 * event pathChange et cursor de Squire
	 *
	 * @param Event e L'event ayant déclenché la vérification
	 */
	window.scf_wysiwyg_hide_float_actionbar = function(event) {
		if (__scf_debug) console.log(arguments);

		let selection = this.getSelection();
		if (!selection instanceof Range || selection.collapsed) {
			$(this._root).closest('.scf-group.scf-wysiwyg').find('.scf-input > .scf-wysiwyg-actionbar.scf-wysiwyg-actionbar-float').removeClass('visible');
		}
	}
	

	/**
	 * Nettoie le textequi va être collé dans le wysiwyg
	 * event willPaste de Squire
	 *
	 * @param Event e L'event ayant déclenché la vérification
	 */
	window.scf_wysiwyg_paste_text_only = function(event) {
		if (__scf_debug) console.log(arguments);

		let text = event.detail.fragment.textContent;
		let paste = document.createRange().createContextualFragment(text);
		event.detail.fragment = paste;
	}
	

	/**
	 * Initialise les wysiwyg
	 *
	 * @var Node input L'input auquel selectionner le spécificateur
	 */
	window.scf_initialize_wysiwyg = function(_input) {
		if (__scf_debug) console.log(arguments);

		/* Récupère le textarea et l'editeur wysiwyg correspondant */
		let input = $(_input);
		let editor = input.nextAll('.scf-wysiwyg-editor');

		/* Initialise l'editeur wysiwyg */
		let wysiwyg = new Squire(editor.get(0), {
			blockTag: 'p',
		});

		/* Masque le textarea et affiche l'editeur wysiwyg */
		input.hide(0);
		editor.show(0);

		/* Insère le contenu du textarea si présent dans l'editeur wysiwyg */
		if (input.get(0).innerHTML.length>0) {
			wysiwyg._root.innerHTML = '<p>' + input.get(0).innerHTML + '</p>';
		}

		/* Check/Uncheck actionbar buttons */
		wysiwyg.addEventListener('pathChange', scf_wysiwyg_check_status_button);
		wysiwyg.addEventListener('select', scf_wysiwyg_check_status_button);
		wysiwyg.addEventListener('cursor', scf_wysiwyg_check_status_button);

		/* Remove all tags in paste */
		wysiwyg.addEventListener('willPaste', scf_wysiwyg_paste_text_only);

		/* Display floating actionbar on select */
		wysiwyg.addEventListener('select', scf_wysiwyg_display_float_actionbar);

		/* Hide floating actionbar on unselect */
		wysiwyg.addEventListener('pathChange', scf_wysiwyg_hide_float_actionbar);
		wysiwyg.addEventListener('cursor', scf_wysiwyg_hide_float_actionbar);
		
		input.data('editor', wysiwyg);
	}


	/**
	 * À chaque modification d'un wysiwyg, insère le contenu du wysiwyg dans le textarea
	 */
	window.scf_wysiwyg_to_textarea = function(e) {
		if (__scf_debug) console.log(arguments);

		let triggered = e.type==='focusout' ? 'blur' : (e.type==='focusin' ? 'focus' : e.type);

		/* Récupère le textarea précédent */
		let input = $(this).prevAll('.scf-wysiwyg-textarea');

		/* Récupère le contenu du wysiwyg */
		let cnt = $(this).get(0).innerHTML;

		/* Nettoie le code HTML par sécurité */
		/* TODO : Purifier le texte avec uniquement les balises acceptés dans les actionbar */
		cnt = DOMPurify.sanitize(cnt);

		/* Insère l'HTML dans le textarea */
		input.val(cnt);

		/* Execute le même event sur le textarea */
		input.trigger(triggered);
	}

	$('body').on('input blur focus', '.scf-group.scf-wysiwyg .scf-wysiwyg-editor', scf_wysiwyg_to_textarea);



	/**
	 * Lors du click sur un bouton du wysiwyg effectue l'action
	 *
	 * @param Event e L'event ayant déclenché le click
	 */
	window.scf_wysiwyg_action = function(e) {
		if (__scf_debug) console.log(arguments);

		var editor = $(this).closest('.scf-group.scf-wysiwyg').find('.scf-input > .scf-wysiwyg-textarea').data('editor');
		var action = ($(this).hasClass('active') && $(this).get(0).hasAttribute('data-action-on-active')) ? $(this).attr('data-action-on-active') : $(this).attr('data-action');
		if ( editor && editor[action] ) editor[action]();
	}

	$('body').on('click', '.scf-group.scf-wysiwyg .scf-input > .scf-wysiwyg-actionbar > span[role="button"][data-action]', scf_wysiwyg_action);


	/**
	 * Au changement d'un input type file, pré-envoie le fichier sur le serveur en cache
	 *
	 * @param Event e L'event ayant déclenché l'input
	 */
	window.scf_file_send_cache = async function(e) {
		if (__scf_debug) console.log(arguments);
		
		await $(this).closest('.scf-group.scf-file').find('.scf-files-list-item-cancel').trigger('click');
		$(this).closest('.scf-group.scf-file').find('input[type="hidden"][name="'+$(this.item).closest('.scf-group.scf-file').attr('data-name')+'-file-tmp"]').value = '';

		$(this).focus();
			
		/* Effectue la vérification du champ */
		scf_validate_input(e, $(this)).then((errors) => {
			
			let validated = true;

			/* Si une erreur est renvoyée, l'affiche et marque la liste de champs comme non valide */
			if (typeof errors == 'object' && errors.length > 0) {
				validated = false;
				errors.sort(function(a, b) { return a.priority - b.priority; });
				errors[0].item.addClass('scf-error');
				errors[0].group.attr('data-error', errors[0].code).children('.scf-error-text').html(errors[0].message);
			}

			/* Si le fichier est valide */
			if (validated) {

				let dt = new DataTransfer();
				let { files } = this;

				for (var i = 0; i < files.length; i++) {

					if (typeof $(this).attr('data-maxfiles')!='undefined' && parseInt($(this).attr('data-maxfiles'))>0 && i>=parseInt($(this).attr('data-maxfiles'))) break;

					let file = files[i];

					dt.items.add(file);

					let re = /(?:(.*)?(\.[^.]+))?$/;
					let file_name = (typeof re.exec(file.name)[1] =='string') ? re.exec(file.name)[1] : '';
					let file_ext = (typeof re.exec(file.name)[2] =='string') ? re.exec(file.name)[2] : '';

					let item = $('<li class="scf-files-list-item waiting"><span class="scf-files-list-item-symbol"></span><span class="scf-files-list-item-name">' + file_name + '</span><span class="scf-files-list-item-ext">' + file_ext + '</span><button type="button" role="button" tabindex="0" class="no--style scf-files-list-item-cancel" aria-label="' + scf_i18n.file_item_remove_label + ' ' + file_name + file_ext + '"></button><span class="scf-files-list-item-message"></span></li>');

					$(this).closest('.scf-group.scf-file').find('.scf-files-list').append(item);

					item.data('file', file);
					
					let getIDData = new FormData();
					getIDData.append("action", "scf_get_id_upload");
					getIDData.append("file_name", file.name);

					scf_files_ajax_waiting_list.push({
						file: file,
						input: this,
						item: item,

						ajax: $.ajax({
							url: scf_ajax_url,
							data: getIDData,
							context: {
								file: file,
								input: this,
								item: item,
							},
							cache: false,
							contentType: false,
							processData: false,
							type: "POST",
							async: true,
							success: function (response) {
								if (response.success && typeof response.data.ID === 'string' && typeof response.data.hash === 'string') {

									this.item.removeClass('waiting sending sent error deleting silent deleted').addClass('sending').attr('data-id', response.data.ID);

									this.item.data('id', response.data.ID);
									this.item.data('hash', response.data.hash);
									this.item.data('nonce', response.data.nonce);

									let uploadData = new FormData();
									uploadData.append("action", "scf_file_upload");
									uploadData.append("file", this.file);
									uploadData.append("accept", $(this.input).attr('accept'));
									uploadData.append("size", $(this.input).attr('data-size'));
									uploadData.append("hash", $(this.input).attr('data-hash'));
									uploadData.append("id", response.data.ID);
									uploadData.append("hash_id", response.data.hash);

									for (var j = 0; j < scf_files_ajax_waiting_list.length; j++) {
										if ( $(this.item).is($(scf_files_ajax_waiting_list[j].item)) ) {

											scf_files_ajax_waiting_list[j].ajax = $.ajax({
												url: scf_ajax_url,
												data: uploadData,
												context: this,
												cache: false,
												contentType: false,
												processData: false,
												type: "POST",
												async: true,
												success: function (response) {
													if (response.success) {
														this.item.removeClass('waiting sending sent error deleting silent deleted').addClass('sent');
														$(this.item).closest('.scf-group.scf-file').find('input[type="hidden"][name="'+$(this.item).closest('.scf-group.scf-file').attr('data-name')+'-file-tmp"]').get(0).value += $(this.item).data('id') + ', ';
													} else {
														this.item.removeClass('waiting sending sent error deleting silent deleted').addClass('silent');
													}
												},
												error: function (response) {
													this.item.removeClass('waiting sending sent error deleting silent deleted').addClass('silent');
												}
											});
										}
									}
								} else {
									this.item.removeClass('waiting sending sent error deleting silent deleted').addClass('silent');
								}
							},
							error: function (response) {
								this.item.removeClass('waiting sending sent error deleting silent deleted').addClass('silent');
							}
						}),
					});
				}

				this.files = dt.files;
			}
		});
	}

	$('body').on('change', '.scf-group.scf-file .scf-input > input[type="file"]', scf_file_send_cache);
	

	/**
	 * Efface un fichier de l'input
	 *
	 * @var Node input L'input auquel selectionner le spécificateur
	 */
	window.scf_file_remove_cache = async function(e) {
		if (__scf_debug) console.log(arguments);

		e.preventDefault();
		e.stopPropagation();

		let a11y_speak = document.createElement('div');
		let a11y_text = scf_i18n.file_item_remove_proceeding;
		a11y_speak.setAttribute('aria-live', 'polite');
		a11y_speak.setAttribute('class', 'screen-reader-text');
		document.body.appendChild(a11y_speak);
		window.setTimeout(function () { a11y_speak.innerHTML = a11y_text; }, 100);
		window.setTimeout(function () { document.body.removeChild(a11y_speak); }, 1000);

		let item = $(this).closest('.scf-files-list-item');

		item.removeClass('waiting sending sent error deleting silent deleted').addClass('deleting');

		/* Cancel ajax request */
		for (var i = 0; i < scf_files_ajax_waiting_list.length; i++) {
			if ( item.is($(scf_files_ajax_waiting_list[i].item)) ) {
				scf_files_ajax_waiting_list[i].ajax.abort();
			}
		}

		/* Supprime le fichier de l'input file */
		let group = $(this).closest('.scf-group');
		let input = group.find('input[type="file"]').first();
		let hidden = group.find('input[type="hidden"][name="'+group.attr('data-name')+'-file-tmp"]');

		let files_id = (hidden.get(0).value.length>0) ? hidden.get(0).value.split(', ').filter(n => n) : [];

		let input_files = new DataTransfer();

		for (var i = 0; i < input.get(0).files.length; i++) {
			let file = input.get(0).files[i];
			if (item.data('file') !== file) input_files.items.add(file);
		};
									
		/* Met à jour les fichiers de l'input type file */
		input.get(0).files = input_files.files;

		/* Remove temp file */
		if (typeof item.data('id') !== 'undefined' && typeof item.data('hash') !== 'undefined' && typeof item.data('nonce') !== 'undefined') {
			let uploadData = new FormData();
			uploadData.append("action", "scf_remove_file_upload");
			uploadData.append("id", item.data('id'));
			uploadData.append("hash", item.data('hash'));
			uploadData.append("nonce", item.data('nonce'));

			$.ajax({
				url: scf_ajax_url,
				data: uploadData,
				context: item,
				cache: false,
				contentType: false,
				processData: false,
				type: "POST",
				async: true,
				success: function (response) {
					if (response.success) {
						this.addClass('deleted');
						setTimeout(function() {
							this.slideUp("normal", function() { $(this).remove(); } );
						}.bind(this), 500);
					} else {
						this.removeClass('waiting sending sent error deleting silent deleted').addClass('error');
					}
				},
				error: function (response) {
					this.removeClass('waiting sending sent error deleting silent deleted').addClass('error');
				},
				complete: function() {
					let a11y_speak = document.createElement('div');
					let a11y_text = scf_i18n.file_item_remove_done;
					a11y_speak.setAttribute('aria-live', 'assertive');
					a11y_speak.setAttribute('class', 'screen-reader-text');
					document.body.appendChild(a11y_speak);
					window.setTimeout(function () { a11y_speak.innerHTML = a11y_text; }, 100);
					window.setTimeout(function () { document.body.removeChild(a11y_speak); }, 1000);
				}
			});
		} else {
			let a11y_speak = document.createElement('div');
			let a11y_text = scf_i18n.file_item_remove_done;
			a11y_speak.setAttribute('aria-live', 'assertive');
			a11y_speak.setAttribute('class', 'screen-reader-text');
			document.body.appendChild(a11y_speak);
			window.setTimeout(function () { a11y_speak.innerHTML = a11y_text; }, 100);
			window.setTimeout(function () { document.body.removeChild(a11y_speak); }, 1000);
		}
	}

	$('body').on('click', '.scf-group.scf-file .scf-files-list-item .scf-files-list-item-cancel', scf_file_remove_cache);
	


	/**
	 * Met à jour le datepicker avec la date de l'input
	 *
	 * @var Node input L'input auquel se réferrer
	 */
	window.scf_date_recheck_value = function(_input) {
		if (__scf_debug) console.log(arguments);

		let datepicker_id = 'picker_'+$(_input).attr('id').replaceAll('-', '_');
		let datepicker = window[datepicker_id];
		if (typeof datepicker == 'object' && datepicker !== null) {
			let date = moment(datepicker._o.trigger.value, datepicker._o.format, true);
			if (date.isValid()) datepicker.setDate(date.toDate(), true);
		}
	}
	

	/**
	 * Pour chaque input sur un champ formaté, vérifie si le caractère est autorisé
	 * Certains caractères ont des fonctions spéciales dans le formatage :
	 * - la flèche gauche et la flèche haut : déplace la selection sur le spécificateur précédent si il y en a un
	 * - la flèche droite et la flèche bas : déplace la selection sur le spécificateur suivant si il y en a un
	 * - le retour arrière (backspace) : remet la valeur du spécificateur selectionné à défaut et déplace la selection sur le spécificateur précédent si il y en a un
	 * - la touche suppr (delete) : remet la valeur du spécificateur selectionné à défaut
	 * - la tabulation : déplace la selection sur le spécificateur suivant si il y en a un, effectue son evenement normal (focus suivant) si il y en a pas
	 * - la shift+tabulation : déplace la selection sur le spécificateur précédent si il y en a un, effectue son evenement normal (focus précédent) si il y en a pas
	 *
	 * @param Event e L'event ayant déclenché l'input
	 */
	window.scf_format_keydown = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Empeche la fonctionnalité si le navigateur ne gere pas la regex */
		if (typeof $(this).attr('data-format')=='undefined' || $(this).attr('data-format')=='' || !regex instanceof RegExp) return;

		/* Réinitialise le spécificateur et selectionne le spécificateur précédent */
		if (e.code === 'Backspace' || e.keyCode === 8 || e.key === 'Backspace') {
			e.preventDefault();
			e.stopPropagation();
			scf_format_reset_specificator($(this));
			scf_format_prev_specificator($(this));

		/* Réinitialise le spécificateur */
		} else if (e.code === 'Delete' || e.keyCode === 46 || e.key === 'Delete') {
			e.preventDefault();
			e.stopPropagation();
			scf_format_reset_specificator($(this));

			/* Selectionne le spécificateur précédent puis suivant (retour au spécificateur actuel) pour reselectionné le sélécteur */
			let specificator = $(this).data('specificator-selected');
			if (typeof specificator === 'undefined' || specificator === false) specificator = 0;

			if (specificator==0) {
				scf_format_next_specificator($(this));
				scf_format_prev_specificator($(this));
			} else {
				scf_format_prev_specificator($(this));
				scf_format_next_specificator($(this));
			}

		/* Selectionne le spécificateur précédent */
		} else if (e.code === 'ArrowUp' || e.keyCode === 38 || e.key === 'ArrowUp' || e.code === 'ArrowLeft' || e.keyCode === 37 || e.key === 'ArrowLeft') {
			e.preventDefault();
			e.stopPropagation();
			scf_format_prev_specificator($(this));

		/* Selectionne le spécificateur suivant */
		} else if (e.code === 'ArrowDown' || e.keyCode === 40 || e.key === 'ArrowDown' || e.code === 'ArrowRight' || e.keyCode === 39 || e.key === 'ArrowRight') {
			e.preventDefault();
			e.stopPropagation();
			scf_format_next_specificator($(this));

		/* Selectionne le spécificateur précédent */
		} else if ((e.code === 'Tab' || e.keyCode === 9 || e.key === 'Tab') && e.shiftKey) {
			/* Si le spécificateur selectionné n'est pas le premier, déplace le curseur ; sinon effectue son action normale */
			if (parseInt($(this).data('specificator-selected')) > 0) {
				e.preventDefault();
				e.stopPropagation();
				scf_format_prev_specificator($(this));
			}

		/* Selectionne le spécificateur suivant */
		} else if (e.code === 'Tab' || e.keyCode === 9 || e.key === 'Tab') {
			let spec_selected = parseInt($(this).data('specificator-selected'));
			let specs_length  = $(this).data('specificators').length;

			/* Si le spécificateur selectionné n'est pas le dernier, déplace le curseur ; sinon effectue son action normale */
			if (spec_selected < specs_length-1) {
				e.preventDefault();
				e.stopPropagation();
				scf_format_next_specificator($(this));
			}

		/* Autre touches à l'exception de CTRL et ALT */
		} else if (e.key.length===1 && !e.ctrlKey && !e.altKey) {

			/* Récupère les spécificateur */
			let _specificators = $(this).data('specificators');

			/* Effectue une copie de _specificators pour ne pas le modifier */
			let specificators = _specificators.slice();

			/* Récupération du spécificateur actuellement sélectionné */
			let specificator = $(this).data('specificator-selected');

			/* Si aucun spécificateur actuellement sélectionné, met le curseur à -1 pour selectionner le premier selecteur */
			if (typeof specificator === 'undefined' || specificator === false) specificator = 0;

			if (Array.isArray(specificators)) {

				/* Tri les spécificateurs en fonction de leur position */
				specificators.sort(function(a, b) {
					if (a['default_pos'] < b['default_pos']) {
						return -1;
					} else if (a['default_pos'] > b['default_pos']) {
						return 1;
					} else {
						return 0;
					}
				});

				/* Si le spécificateur selectionné n'existe pas ou n'a pas de format, empêche l'input */
				if (typeof specificators[specificator] === 'undefined' || typeof specificators[specificator]['type'] === 'undefined') {
					e.preventDefault();
					return false;
				}

				/* Regex de validation par défaut */
				let validator = new RegExp("^[[a-zA-Z0-9$&+,:;=?@#|'<>.^*()%!-][^a-zA-Z0-9\\s\\(\\)\\[\\]\\{\\}\\\\^\\$\\|\\?\\*\\+\\.\\<\\>\\-\\=\\!\\_]]$");

				/* Indique qu'on verifie une regex et non pas une fonction (met check_by_regex à false pour les spécificateurs de type fonction) */
				let check_by_regex = true;

				/* Regex pour vérifier une chaine de caractère avec uniquement l'alphabet latin */
				if (specificators[specificator]['type'] === 'c') { validator = new RegExp("^[a-zA-Z]$"); }

				/* Regex pour vérifier une chaine de caractère avec l'alphabet latin et les symboles */
				else if (specificators[specificator]['type'] === 'C') { validator = new RegExp("^[[a-zA-Z][^a-zA-Z0-9\\s\\(\\)\\[\\]\\{\\}\\\\^\\$\\|\\?\\*\\+\\.\\<\\>\\-\\=\\!\\_]]$"); }

				/* Regex pour vérifier un chiffre */
				else if (specificators[specificator]['type'] === 'i') { validator = new RegExp("^[0-9]$"); }

				/* Regex pour vérifier un symbole */
				else if (specificators[specificator]['type'] === 's') { validator = new RegExp("^[$&+,:;=?@#|'<>.^*()%!-]$"); }

				/* Regex pour vérifier tout caractère */
				else if (specificators[specificator]['type'] === 'a') { validator = new RegExp("^.*$"); }

				/* Regex pour vérifier via une regex fournie */
				else if (specificators[specificator]['type'] === 'r' && typeof specificators[specificator]['regex']!=='undefined') { validator = new RegExp("^["+specificators[specificator]['regex']+"]$"); }

				/* Regex pour vérifier via une regex fournie insensible à la casse */
				else if (specificators[specificator]['type'] === 'R' && typeof specificators[specificator]['regex']!=='undefined') { validator = new RegExp("^["+specificators[specificator]['regex']+"]$", 'i'); }

				/* Valide via une fonction fournie */
				else if (specificators[specificator]['type'] === 'f' && typeof specificators[specificator]['function']==='function') {
					validator = specificators[specificator]['function'];
					check_by_regex = false;
				}

				/* Récupère le caractère demandé par l'input */
				let key = e.key;

				/* Si la vérification s'est bien passée */
				let key_correct = false;

				/* Si la validation se fait par une regex */
				if (check_by_regex) {
					key_correct = validator.test(key);

				/* Si la validation se fait par une fonction */
				} else {
					try {
						let result = validator(key, $(this), specificators);
						key_correct = Boolean(result);
					} catch (error) {}
				}

				/* Si le caractère correspond au spécificateur, enregistre sa valeur, affiche dans l'input et passe au spécificateur suivant */
				if (key_correct) {

					/* Indique au spécificateur sa nouvelle valeur */
					specificators[specificator]['value'] = key;

					$(this).data('specificators', specificators);

					/* Affiche la nouvelle valeur de l'input */
					scf_format_display_value($(this));

					/* Si le spécificateur sélectionné n'est pas le dernier, passe au suivant */
					if (parseInt($(this).data('specificator-selected')) < ($(this).data('specificators').length-1)) {
						scf_format_next_specificator($(this));

					/* Si le spécificateur sélectionné est le dernier, passe au champ suivant */
					} else {
						
						/* Simule un TAB en mettant le focus sur l'element focusable suivant */
						let focusables = $(":input, a[href], *[tabindex]:not([tabindex='-1'])").not(":disabled").not(":hidden").not("a[href]:empty");

						if ($(this).is('input[type="radio"]:not([name=""])')) {
							let name = $(this).attr('name').replace(/(!"#$%&'\(\)\*\+,\.\/:;<=>\?@\[\]^`\{\|\}~)/g, "\\\\$1");
							focusables = focusables.not("input[type=radio][name=" + name + "]").add($(this));
						}

						let currentIndex = focusables.index($(this));
						let nextIndex = (currentIndex + 1) % focusables.length;
						if (nextIndex <= -1) nextIndex = focusables.length + nextIndex;
						focusables.eq(nextIndex).focus();
					
						$(this).trigger('blur');
						e.preventDefault();
						return false;
					}

					e.preventDefault();

				/* Si le caractère ne passe pas le test, ne l'affiche pas */
				} else {
					e.preventDefault();
				}
			}
		}

		/* Trigger les events inputs */
		$(this).trigger('input');
	}

	$('body').on('keydown', '.scf-group .scf-input > input[data-format]', scf_format_keydown);


	/**
	 * Lors du focus sur un champ formaté, sélectionne le premier spécificateur non rempli
	 */
	window.scf_format_select_first_specificator = function() {
		if (__scf_debug) console.log(arguments);

		/* Empeche la fonctionnalité si le navigateur ne gere pas la regex */
		if (typeof $(this).attr('data-format')=='undefined' || $(this).attr('data-format')=='' || !regex instanceof RegExp) return;

		/* Sélectionne le premier spécificateur non rempli */
		scf_format_first_specificator($(this));
	}

	$('body').on('focus', '.scf-group .scf-input > input[data-format]', scf_format_select_first_specificator);


	/**
	 * Lors du click sur un champ formaté ayant le focus, sélectionne le spécificateur le plus proche du curseur
	 *
	 * @param Event e L'event ayant déclenché le click
	 */
	window.scf_format_select_closest_specificator = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Empeche la fonctionnalité si le navigateur ne gere pas la regex */
		if (typeof $(this).attr('data-format')=='undefined' || $(this).attr('data-format')=='' || !regex instanceof RegExp) return;
		
		/* Uniquement si le champ a déjà le focus */
		if ($(this).is(':focus')) {
			let input = $(this);

			let _specificators = $(this).data('specificators');

			/* Empeche la fonctionnalité si aucun spécificateurs n'est fournie */
			if (typeof _specificators=='undefined') return;

			/* Effectue une copie de _specificators pour ne pas le modifier */
			let specificators = _specificators.slice();

			if (Array.isArray(specificators)) {

				/* Tri les spécificateurs en fonction de leur position */
				specificators.sort(function(a, b) {
					if (a['default_pos'] < b['default_pos']) {
						return -1;
					} else if (a['default_pos'] > b['default_pos']) {
						return 1;
					} else {
						return 0;
					}
				});

				/* Récupère la position du curseur */
				let caret = $(this).get(0).selectionStart;

				/* Verifie si on est à l'intérieur un specificateur */
				for (var i = 0; i < specificators.length; i++) {
					let start_select = specificators[i]['replace_pos'];
					let end_select = specificators[i]['replace_pos']+specificators[i]['value'].length;
					if (caret > start_select && caret < end_select) {
						$(this).data('specificator-selected', i);

						$(this).get(0).setSelectionRange(start_select, start_select);
						$(this).get(0).setSelectionRange(start_select, end_select);

						e.preventDefault();
						return;
					}
				};

				/* ID du spécificateur sélectionné comme étant le plus proche du curseur */
				let j = 0;

				/* Parcours tous les spécificateurs */
				for (var i = 0; i < specificators.length; i++) {

					/* Si le départ du spécificateur est plus proche du curseur que celui précédemment sélectionné (si il sont à la même distance, prend celui dont la fin est le plus proche du curseur) */
					if ((Math.abs(caret - specificators[i]['replace_pos']) < Math.abs(caret - specificators[j]['replace_pos'])) ||
						(Math.abs(caret - specificators[i]['replace_pos']) == Math.abs(caret - specificators[j]['replace_pos']) && Math.abs(caret - (specificators[i]['replace_pos']+specificators[i]['value'].length)) < Math.abs(caret - (specificators[j]['replace_pos']+specificators[j]['value'].length)))) {
						/* Selectionne ce spécificateur */
						j = i;
					}
				};

				/* Début et fin de la selection */
				let start_select = specificators[j]['replace_pos'];
				let end_select = specificators[j]['replace_pos']+specificators[j]['value'].length;
				$(this).data('specificator-selected', j);

				/* Selectionne un ou plusieurs caractère(s) de l'input */
				$(this).get(0).setSelectionRange(start_select, start_select);
				$(this).get(0).setSelectionRange(start_select, end_select);

				e.preventDefault();
			}
		}
	}

	$('body').on('click', '.scf-group .scf-input > input[data-format]', scf_format_select_closest_specificator);


	/**
	 * Permet de fermer le calendrier au clique sur l'icone du calendrier
	 * Suis l'ordre d'execution des event qui est mousedown > blur > click > focus
	 * Cela permet d'ajouter un event click sur l'icone du calendrier uniquement quand le champ a le focus (focus non verifiable au moment du click puisque le click sur l'icone fait perdre le focus de l'input 	)
	 * Cet event click empeche le champ de recevoir le focus puisque l'icone est dans un label contenant le champ. Ainsi, lorsqu'on clique sur l'icone du calendrier et que le champ est focus (calendrier ouvert) cela permet de masquer le calendrier et de blur le champ
	 *
	 * @param Event e L'event ayant déclenché le click
	 */
	window.scf_calendar_mousedown = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Champ date */
		let input = $(this).closest('.scf-group').find('.scf-input input').first();

		/* Fonction lorsque l'input perd son focus */
		let blur_fn = function(e) {

			/* Empeche l'input de reprendre le focus */
			e.data.calendar.one('click', function(e) {
				e.stopPropagation();
				e.preventDefault();
			});
		}

		/* Seulement si l'input a le focus */
		input.one('blur', {calendar: $(this)}, blur_fn);
		input.one('focus', {blur_func: blur_fn}, function(e) {
			input.off('blur', e.data.blur_func);
		});
	}

	$('body').on('mousedown', '.scf-calendar', scf_calendar_mousedown);


	/**
	 * Lors d'une saisie dans le champ date, met à jour le datepicker en temps réel
	 *
	 * @param Event e L'event ayant déclenché le click
	 */
	$('body').on('input', '.scf-date .scf-input input', function(e) {
		scf_date_recheck_value(this);
	});


	/**
	 * Lorsque le DOM est prêt
	 */
	$(document).ready(function() {

		/**
		 * Désactive sur mobile les champs présent que sur pc
		 */
		$('body.scf-touch-device .scf-group.scf-disabled-on-touchable input, body.scf-touch-device .scf-group.scf-disabled-on-touchable select').each(function() {
			$(this).prop('disabled', true);
		});


		/**
		 * Désactive sur les champs présent que sur mobile
		 */
		$('body:not(.scf-touch-device) .scf-group.scf-disabled-on-pointer input, body:not(.scf-touch-device) .scf-group.scf-disabled-on-pointer select').each(function() {
			$(this).prop('disabled', true);
		});

		
		/**
		 * Trigger l'event input de chaque champ pour vérifier les valeurs déjà présente
		 */
		$('body .scf-group:not(.scf-error) .scf-input').each(function() {
			let input = $(this).children('input').last();
			input.trigger('input');
		});
		

		/**
		 * Modifie sur pc les select natifs pour les masquer en aria
		 * Sur mobile, conserve le select natif
		 */
		$('body:not(.scf-touch-device) .scf-group.scf-select .scf-input select.scf-select-native').each(function() {
			$(this).attr('aria-hidden', 'true');
		});
		

		/**
		 * Modifie sur pc les champs date par un champ texte
		 * Sur mobile, conserve le datepicker natif
		 */
		$('body:not(.scf-touch-device) .scf-group.scf-date .scf-input input:not([disabled])').each(function() {
			$(this).attr('type', 'text');
		});


		/**
		 * Initialise les spécificateurs des champs formatés
		 */
		$('.scf-group .scf-input > input[data-format]').each(function() {
			let input = $(this);
			scf_initialize_specificators(input);
		});


		/**
		 * Initialise les spécificateurs des champs formatés
		 */
		$('.scf-group.scf-wysiwyg .scf-input > .scf-wysiwyg-textarea').each(function() {
			let input = $(this);
			scf_initialize_wysiwyg(input);
		});
	});

	
	/**
	 * À l'event DOMContentLoaded (avec window.load) marque les champ tel en chargement en attente du chargement de libphonenumber
	 */
	window.scf_wait_phone_country = function(event) {
		if (__scf_debug) console.log(arguments);

		$('.scf-group.scf-tel > .scf-input > input').each(function() {
			var val = $(this).val();
			if (val) {
				$(this).closest('.scf-group').addClass('loading');
			}
		});
	}

	document.addEventListener('DOMContentLoaded', scf_wait_phone_country);


	/**
	 * Une fois tous les scripts chargés (dont libphonenumber), valide le numéro de téléphone pré-rentré et selectionne le pays correspondant
	 */
	window.scf_init_phone_country = function(event) {
		if (__scf_debug) console.log(arguments);

		$('.scf-group.scf-tel > .scf-input > input').each(function() {
			var val = $(this).val();
			if (val) {

				try {

					/* Si le script libphonenumber est chargé */
					if (typeof libphonenumber !== 'undefined' && typeof libphonenumber.parsePhoneNumber !== 'undefined') {

						/* Vérifie le numéro */
						var phone = libphonenumber.parsePhoneNumber(val);
						if (phone) {
							$(this).val(phone.nationalNumber);

							/* Selectionne le pays correspondant au numéro */
							$(this).closest('.scf-group').find('.scf-tel-country-selector input.scf-select-2-item-input[value="'+phone.country.toUpperCase()+'"]').prop('checked', true);
							$(this).closest('.scf-group').find('.scf-tel-country-selector select.scf-select-native').val(phone.country.toUpperCase());
						}
					}

				} catch (error) { console.error(error); }
				
				/* Enlève le loading mis par l'event DOMContentLoaded sur le champ */
				$(this).closest('.scf-group').removeClass('loading');
			}
		});
	}

	window.addEventListener('load', scf_init_phone_country);


	/**
	 * Lors du reset d'un formulaire, reinitialise les erreurs de champs
	 */
	window.scf_reset_form = function() {
		if (__scf_debug) console.log(arguments);

		$(this).find('.scf-group').each(function() {
			$(this).removeClass('scf-validated').removeClass('scf-error').children('.scf-error-text').html('');
		});
	}

	$('body').on('reset', 'form', scf_reset_form);


	/**
	 * Sur les listes d'options de select, agrandi la scrollbar au survol de celle-ci
	 */
	window.scf_select_enlarge_scrollbar = function(e) {
		let options = e.data.options;
		let dist = options.offset().left + options.width() - e.pageX;
		dist < 30 && dist > 0 ? options.addClass('scrollbar-large') : options.removeClass('scrollbar-large');
	}

	$('.scf-select-2-options').each(function() {
		$('body').on('mousemove', { options : $(this) }, scf_select_enlarge_scrollbar);
	});


	/**
	 * Lors de la pression d'une touche, ajoute une classe active ou selectionne l'option suivante (radio, checkbox, select)
	 */
	window.scf_keydown = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Lors de l'appui sur la touche ESPACE : si c'est une checkbox, une radio ou un select, met la classe active */
		if (e.code === 'Space' || e.keyCode === 32 || e.key === ' ' || e.key === 'Spacebar') {
			let focus = $(':focus').first();
			if (focus.is('.scf-checkbox-item') || focus.is('.scf-radio-item')) {
				focus.addClass("active");
				e.preventDefault();
				return false;
			} else if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-select')) {
				if (focus.is('.scf-select-2-selector')) focus.closest('.scf-group').find('.scf-select-2-selector').addClass("active");
				e.preventDefault();
				return false;
			}

		/* Lors de l'appui sur la touche ESC : si c'est un select ou un tel, met la classe active */
		} else if (e.code === 'Escape' || e.keyCode === 27 || e.key === 'Escape') {
			let focus = $(':focus').first();
			if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-select')) {
				focus.closest('.scf-group').find('.scf-select-2-selector').addClass("active");
				e.preventDefault();
				return false;
			} else if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-tel')) {
				keypress_esc_tel = focus;
				focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select .scf-select-2-selector').addClass("active");
				e.preventDefault();
				return false;
			}

		/* Lors de l'appui sur la touche ENTER : si c'est un select ou un tel, met la classe active */
		} else if (e.code === 'Enter' || e.keyCode === 13 || e.key === 'Enter') {
			let focus = $(':focus').first();
			if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-select')) {
				if (focus.is('.scf-select-2-selector')) focus.closest('.scf-group').find('.scf-select-2-selector').addClass("active");
				e.preventDefault();
				return false;
			} else if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-tel')) {
				focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select .scf-select-2-selector').addClass("active");
				e.preventDefault();
				return false;
			}

		/* Lors de l'appui sur la touche DEL : si c'est un select, met la classe active */
		} else if (e.code === 'Delete' || e.keyCode === 46 || e.key === 'Delete') {
			let focus = $(':focus').first();
			if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-select')) {
				e.preventDefault();
				return false;
			}

		/* Lors de l'appui sur la flèche BAS : si c'est un select, selectionne ou survol l'option suivante ; si c'est un tel, selectionne le pays suivant */
		} else if (e.code === 'ArrowDown' || e.keyCode === 40 || e.key === 'ArrowDown') {
			let focus = $(':focus').first();
			if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-select')) {

				/* Si le select est ouvert, survol l'option suivante (première si on est sur la derniere) */
				if (focus.closest('.scf-group').find('input.scf-select-2-opener').is(':checked')) {
					if (focus.is('.scf-select-2-option')) {
						let next = focus.next('.scf-select-2-option');
						if (next.length) {
							next.focus();
						} else {
							focus.closest('.scf-group').find('.scf-select-2-options > .scf-select-2-option').first().focus();
						}
					} else {
						focus.closest('.scf-group').find('.scf-select-2-options > .scf-select-2-option').first().focus();
					}
					e.preventDefault();
					return false;

				/* Si le select n'est pas multiple et est fermé, sélectionne l'option suivante */
				} else if (!focus.closest('.scf-group').hasClass('scf-select-multiple')) {
					let checked = focus.closest('.scf-group').find('input.scf-select-2-item-input:checked');
					if (checked.length) {
						let next = checked.next('.scf-select-2-item-input');
						if (next.length) {
							next.prop("checked", true);
						} else {
							focus.closest('.scf-group').find('input.scf-select-2-item-input').first().prop("checked", true);
						}
					} else {
						focus.closest('.scf-group').find('input.scf-select-2-item-input').first().prop("checked", true);
					}

					let a11y_speak = document.createElement('div');
					let a11y_text = focus.closest('.scf-group').find('.scf-select-2-option[data-for="'+focus.closest('.scf-group').find('input.scf-select-2-item-input:checked').first().attr('id')+'"]').first().text();
					a11y_speak.setAttribute('aria-live', 'assertive');
					a11y_speak.setAttribute('class', 'screen-reader-text');
					document.body.appendChild(a11y_speak);
					window.setTimeout(function () { a11y_speak.innerHTML = a11y_text; }, 100);
					window.setTimeout(function () { document.body.removeChild(a11y_speak); }, 1000);
					
					e.preventDefault();
					return false;
				}
			} else if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-tel')) {

				/* Si le select du pays est ouvert, survol le pays suivant (premier si on est sur le dernier) */
				if (focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select input.scf-select-2-opener').is(':checked')) {
					focus.closest('.scf-group').find('.scf-select-2-options > .scf-select-2-option').first().focus();
					e.preventDefault();
					return false;

				/* Si le select du pays est fermé, sélectionne le pays suivant */
				} else {
					let checked = focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select input.scf-select-2-item-input:checked');
					if (checked.length) {
						let next = checked.next('.scf-select-2-item-input');
						if (next.length) {
							next.prop("checked", true);
						} else {
							focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select input.scf-select-2-item-input').first().prop("checked", true);
						}
					} else {
						focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select input.scf-select-2-item-input').first().prop("checked", true);
					}
					e.preventDefault();
					return false;
				}
			}

		/* Lors de l'appui sur la flèche HAUT : si c'est un select, selectionne ou survol l'option précédente ; si c'est un tel, selectionne le pays précédent */
		} else if (e.code === 'ArrowUp' || e.keyCode === 38 || e.key === 'ArrowUp') {
			let focus = $(':focus').first();
			if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-select')) {

				/* Si le select est ouvert, survol l'option suivante (première si on est sur la derniere) */
				if (focus.closest('.scf-group').find('input.scf-select-2-opener').is(':checked')) {
					if (focus.is('.scf-select-2-option')) {
						let prev = focus.prev('.scf-select-2-option');
						if (prev.length) {
							prev.focus();
						} else {
							focus.closest('.scf-group').find('.scf-select-2-options > .scf-select-2-option').last().focus();
						}
					} else {
						focus.closest('.scf-group').find('.scf-select-2-options > .scf-select-2-option').last().focus();
					}
					e.preventDefault();
					return false;

				/* Si le select n'est pas multiple et est fermé, sélectionne l'option suivante */
				} else if (!focus.closest('.scf-group').hasClass('scf-select-multiple')) {
					let checked = focus.closest('.scf-group').find('input.scf-select-2-item-input:checked');
					if (checked.length) {
						let prev = checked.prev('.scf-select-2-item-input');
						if (prev.length) {
							prev.prop("checked", true);
						} else {
							focus.closest('.scf-group').find('input.scf-select-2-item-input').last().prop("checked", true);
						}
					} else {
						focus.closest('.scf-group').find('input.scf-select-2-item-input').last().prop("checked", true);
					}

					let a11y_speak = document.createElement('div');
					let a11y_text = focus.closest('.scf-group').find('.scf-select-2-option[data-for="'+focus.closest('.scf-group').find('input.scf-select-2-item-input:checked').first().attr('id')+'"]').first().text();
					a11y_speak.setAttribute('aria-live', 'assertive');
					a11y_speak.setAttribute('class', 'screen-reader-text');
					document.body.appendChild(a11y_speak);
					window.setTimeout(function () { a11y_speak.innerHTML = a11y_text; }, 100);
					window.setTimeout(function () { document.body.removeChild(a11y_speak); }, 1000);
					
					e.preventDefault();
					return false;
				}
			} else if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-tel')) {

				/* Si le select du pays est ouvert, survol le pays suivant (premier si on est sur le dernier) */
				if (focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select input.scf-select-2-opener').is(':checked')) {
					focus.closest('.scf-group').find('.scf-select-2-options > .scf-select-2-option').last().focus();
					e.preventDefault();
					return false;

				/* Si le select du pays est fermé, sélectionne le pays suivant */
				} else {
					let checked = focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select input.scf-select-2-item-input:checked');
					if (checked.length) {
						let prev = checked.prev('.scf-select-2-item-input');
						if (prev.length) {
							prev.prop("checked", true);
						} else {
							focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select input.scf-select-2-item-input').last().prop("checked", true);
						}
					} else {
						focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select input.scf-select-2-item-input').last().prop("checked", true);
					}
					e.preventDefault();
					return false;
				}
			}
		}
	}

	document.addEventListener('keydown', scf_keydown);


	/**
	 * Lors du relechament d'une touche, enlève la classe active et/ou coche l'option
	 */
	window.scf_keyup = function(e) {
		if (__scf_debug) console.log(arguments);

		/* Lors de l'appui sur la touche ESPACE : si c'est une checkbox, une radio ou un select, selectione l'option selectionnée ou ouvre/ferme le select */
		if ((e.code === 'Space' || e.keyCode === 32 || e.key === ' ' || e.key === 'Spacebar')) {
			let focus = $(':focus').first();

			/* Si c'est une checkbox ou une radio, coche ou décoche l'option sélectionné */
			if (focus.is('.scf-checkbox-item')) {
				let checkbox = focus.children('input[type="checkbox"]').first();
				checkbox.prop("checked", !checkbox.prop("checked"));
				focus.removeClass("active");
				checkbox.trigger('input');
			} else if (focus.is('.scf-radio-item')) {
				let checkbox = focus.children('input[type="radio"]').first();
				checkbox.prop("checked", !checkbox.prop("checked"));
				focus.removeClass("active");
				checkbox.trigger('input');
			} else if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-select')) {

				/* Si c'est une option d'un select qui est sélectionné, la coche */
				if (focus.is('.scf-select-2-option')) {
					let checkbox = $('#'+focus.attr('data-for'));

					/* Si ce n'est pas un select multiple, ferme le select après avoir selectionné l'option mais garde le focus sur le select */
					if (!focus.closest('.scf-group').hasClass('scf-select-multiple')) {
						checkbox.prop("checked", true).trigger('change');
						focus.closest('.scf-group').find('input.scf-select-2-opener').prop("checked", false).trigger('change');
						scf_keypress_letter = {
							letter : '',
							reset : true,
							timeout : null,
							option : null,
						};
					
						/* Garde le focus sur le select ou sur le champ tel */
						if (focus.closest('.scf-group').parent().is('.scf-tel-country-selector')) {
							focus.closest('.scf-group').find('.scf-select-2-selector').removeClass("active");
						} else {
							focus.closest('.scf-group').find('.scf-select-2-selector').removeClass("active").focus();
						}

					/* Si c'est un select multiple, selectionne l'option mais ne ferme pas le select */
					} else {
						checkbox.prop("checked", !checkbox.prop("checked"));
					}

				/* Si c'est le select entier qui est focus, change son statut (ouvert/fermé) */
				} else {
					let checkbox = focus.closest('.scf-group').find('input.scf-select-2-opener');
					checkbox.prop("checked", !checkbox.prop("checked")).trigger('change');
					if (focus.closest('.scf-group').parent().is('.scf-tel-country-selector')) {
						focus.closest('.scf-group').find('.scf-select-2-selector').removeClass("active");
					} else {
						focus.closest('.scf-group').find('.scf-select-2-selector').removeClass("active").focus();
					}
				}
			}

		/* Lors de l'appui sur la touche ESC : si c'est un select ou un tel, enlève la classe active et ferme le select */
		} else if ((e.code === 'Escape' || e.keyCode === 27 || e.key === 'Escape')) {
			let focus = $(':focus').first();

			/* Si on est dans un select, le ferme */
			if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-select')) {
				let checkbox = focus.closest('.scf-group').find('input.scf-select-2-opener');
				checkbox.prop("checked", false).trigger('change');
				if (focus.closest('.scf-group').parent().is('.scf-tel-country-selector')) {
					focus.closest('.scf-group').find('.scf-select-2-selector').removeClass("active");
				} else {
					focus.closest('.scf-group').find('.scf-select-2-selector').removeClass("active").focus();
				}

			/* Si le keypress indique que l'element focus est un champ tel (cf. explication dans la variable keypress_esc_tel ligne 39), ferme le sélecteur de pays */
			} else if (keypress_esc_tel && keypress_esc_tel.closest('.scf-group').length && keypress_esc_tel.closest('.scf-group').is('.scf-tel')) {
				let checkbox = keypress_esc_tel.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select input.scf-select-2-opener');
				checkbox.prop("checked", false).trigger('change');
				keypress_esc_tel.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select .scf-select-2-selector').removeClass("active");
			}

		/* Lors de l'appui sur la touche ENTER : si c'est un select ou un tel, selectione l'option selectionnée ou ouvre/ferme le select */
		} else if (e.code === 'Enter' || e.keyCode === 13 || e.key === 'Enter') {
			let focus = $(':focus').first();
			if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-select')) {

				/* Si c'est une option d'un select qui est sélectionné, la coche */
				if (focus.is('.scf-select-2-option')) {
					let checkbox = $('#'+focus.attr('data-for'));

					/* Si ce n'est pas un select multiple, ferme le select après avoir selectionné l'option mais garde le focus sur le select */
					if (!focus.closest('.scf-group').hasClass('scf-select-multiple')) {
						checkbox.prop("checked", true).trigger('change');
						focus.closest('.scf-group').find('input.scf-select-2-opener').prop("checked", false).trigger('change');
						scf_keypress_letter = {
							letter : '',
							reset : true,
							timeout : null,
							option : null,
						};

						/* Garde le focus sur le select ou sur le champ tel */
						if (focus.closest('.scf-group').parent().is('.scf-tel-country-selector')) {
							focus.closest('.scf-group').find('.scf-select-2-selector').removeClass("active");
						} else {
							focus.closest('.scf-group').find('.scf-select-2-selector').removeClass("active").focus();
						}

					/* Si c'est un select multiple, selectionne l'option mais ne ferme pas le select */
					} else {
						checkbox.prop("checked", !checkbox.prop("checked"));
					}

				/* Si c'est le select entier qui est focus, change son statut (ouvert/fermé) */
				} else {
					let checkbox = focus.closest('.scf-group').find('input.scf-select-2-opener');
					checkbox.prop("checked", !checkbox.prop("checked")).trigger('change');
					if (focus.closest('.scf-group').parent().is('.scf-tel-country-selector')) {
						focus.closest('.scf-group').find('.scf-select-2-selector').removeClass("active");
					} else {
						focus.closest('.scf-group').find('.scf-select-2-selector').removeClass("active").focus();
					}
				}

			/* Si c'est le tel qui est focus, ferme le selecteur de pays */
			} else if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-tel')) {
				let checkbox = focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select input.scf-select-2-opener');
				checkbox.prop("checked", !checkbox.prop("checked")).trigger('change');
				focus.closest('.scf-group').find('.scf-tel-country-selector .scf-group.scf-select .scf-select-2-selector').removeClass("active");
			}

		/* Lors de l'appui sur la touche DEL : si c'est un select, vide le select */
		} else if (e.code === 'Delete' || e.keyCode === 46 || e.key === 'Delete') {
			let focus = $(':focus').first();
			if (focus.closest('.scf-group').length && focus.closest('.scf-group').is('.scf-select')) {
				focus.closest('.scf-group').find('input.scf-select-2-item-input').each(function() {
					$(this).prop("checked", false);
				});
				focus.closest('.scf-group').find('input').first().trigger('input');
			}
		}
	}

	document.addEventListener('keyup', scf_keyup);
	
	/* Teste la regex globale pour les formats (celle-ci peut ne pas fonctionner car elle utilise un negative lookahead non compris par ie) */
	try { 
		if (regex_string) {
			regex = new RegExp(regex_string, 'gm');
		} else {
			regex = null;
		}
	} catch(e) { regex = null; }

})(jQuery);

/* Insère une classe pour les appareils tactiles (permet d'utiliser des styles natifs dans certains champs [ex : datepicker ou select]) */
if (('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0)) document.body.classList.add('scf-touch-device');