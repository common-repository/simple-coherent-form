<?php
/**
 * Plugin Name: Simple Coherent Form
 * Plugin URI: https://simpleplugins.fr/scf/
 * Description: Create coherent input between themes and plugins
 * Version: 1.7.2
 * Author: Tom Baumgarten
 * Author URI: https://www.tombgtn.fr/
 * Text Domain: simple-coherent-form
 * Domain Path: /languages
 * License: GPL2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

if ( ! defined( 'SIMPLE_COHERENT_FORM_FILE' ) ) define( 'SIMPLE_COHERENT_FORM_FILE', __FILE__ );

require_once( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/front/front.php' );
require_once( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/front/helpers.php' );

/* Liaison à Contact Form 7 */
include_once( dirname(SIMPLE_COHERENT_FORM_FILE) . '/plugins/cf7.php' );

/* Chargement des types de champs */
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/checkbox.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/date.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/email.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/message.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/number.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/password.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/radio.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/select.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/tel.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/text.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/textarea.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/time.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/url.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/wysiwyg.php' );
include_once ( dirname(SIMPLE_COHERENT_FORM_FILE) . '/includes/fields/file.php' );
