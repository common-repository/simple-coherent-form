/**
 * Version synchrone du script principal de Simple Coherent Form
 *
 * enqueue dans includes/front/front.php avec le slug scf-script-sync
 */

jQuery(function($) {
	/**
	 * Vérifie un groupe et renvoie les erreurs ou un nombre
	 * Les erreurs renvoyées sont au format object avec pour paramètres le code de l'erreur, le message, le groupe de l'input, l'item concerné (différent du groupe si le champ et des type radio ou checkbox) et la priorité de l'erreur
	 * Les codes d'erreurs se composent ainsi : 3 premiers chiffre selon le type de champ (100 erreurs communes ; 102 pour les nombres ; 103 pour les select ; 105 pour les urls ; 106 pour le e-mails ; 107 pour les téléphones ; 108 pour les mots de passe ; 109 pour les radios ; 110 pour les checkbox ; 111 pour les dates) suivie de l'identifiant unique de l'erreur
	 *
	 * @param Event       e           L'event ayant déclenché la vérification
	 * @param Node        input       L'input à vérifier
	 * @param Number|null check_error Indique une erreur spécifique à vérifier ou toutes les erreurs à null
	 * @return int|object[] Le tableau des erreurs si il y en a, un nombre négatif si c'est un champ facultatif non remplie, 0 sinon.
	 */
	window.sync_scf_validate_input = function(e, input, check_error = null) {

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


			/**
			 * Si aucune erreur en particulier doit être vérifié (donc doit renvoyer toute les erreurs) ou l'erreur a vérifié est une erreur provenant de la requête ajax ; attends la requête ajax pour renvoyer les erreurs
			 * Si l'on demande de verifier la présence d'une erreur qui n'est pas une erreur issue de l'ajax, renvoie simplement les erreurs (evite la requête)
			 * Permet de ne pas envoyer de requete ajax si c'est pour valider une erreur existante
			 */
			if (typeof check_error !== 'number' || check_error === 100003) {

				/* Fonction synchrone de la requete ajax */
				let ajax = () => {
					$.ajax({
						url: scf_ajax_url,
						data: {
							action: (group.hasClass('scf-unique')) ? "scf_check_unicity" : "scf_check_existence",
							security: scf_check_unicity_nonce,
							key: key,
							value: value
						},
						type: "POST",
						async: false,
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
									if (typeof result === 'object') out.push({
										code: 100003,
										message: error,
										group: group,
										item: item,
										priority: 22
									});
								}
							} catch (e) {}
						}
					});
				};
			}
		}

		/* Filtre les erreurs */
		out = wp.hooks.applyFilters('scfValidateErrors', out, input, e, check_error);
			
		/* Renvoie les erreurs */
		if (out.length>0) return out;
		if (!item.hasClass('scf-required') && !filled) return -1;
		return 0;
	}
});