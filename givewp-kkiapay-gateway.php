<?php
/**
 * Plugin Name: GiveWP - Kkiapay Gateway
 * Plugin URI: https://github.com/israelboco/givewp-kkiapay
 * Description: Intègre la passerelle de paiement Kkiapay à GiveWP.
 * Version: 2.1
 * Author: isboco
 * Author URI: https://github.com/israelboco
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: give-kkiapay
 */

defined('ABSPATH') || exit;

// Définition des constantes
define('GIVE_KKIAPAY_VERSION', '2.1');
define('GIVE_KKIAPAY_MIN_GIVE_VER', '4.0.0'); // Mise à jour pour GiveWP 4.4.0
define('GIVE_KKIAPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GIVE_KKIAPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GIVE_KKIAPAY_BASENAME', plugin_basename(__FILE__));

// Vérification de GiveWP
if (!class_exists('Give')) {
    add_action('admin_notices', 'give_kkiapay_missing_give_notice');
    return;
}

/**
 * Affiche un avertissement si GiveWP n'est pas installé
 */
function give_kkiapay_missing_give_notice() {
    echo '<div class="error"><p>';
    echo sprintf(
        __('GiveWP - Kkiapay Gateway nécessite GiveWP pour fonctionner. Veuillez installer et activer %sGiveWP%s.', 'give-kkiapay'),
        '<a href="' . admin_url('plugin-install.php?tab=search&s=givewp') . '">',
        '</a>'
    );
    echo '</p></div>';
}

// Initialisation du plugin
add_action('plugins_loaded', 'give_kkiapay_init', 11); // Priorité augmentée

function give_kkiapay_init() {
    // Vérification de la version de GiveWP
    if (version_compare(GIVE_VERSION, GIVE_KKIAPAY_MIN_GIVE_VER, '<')) {
        add_action('admin_notices', 'give_kkiapay_give_version_notice');
        return;
    }

    // Charge les fichiers nécessaires
    require_once GIVE_KKIAPAY_PLUGIN_DIR . 'includes/admin-settings.php';
    
    // Charge la traduction
    load_plugin_textdomain('give-kkiapay', false, dirname(GIVE_KKIAPAY_BASENAME) . '/languages/');

    // Enregistrement de la passerelle (priorité haute)
    add_filter('give_payment_gateways', 'give_kkiapay_register_gateway', 5, 1);
    
    // Force l'activation initiale
    add_action('give_init', 'give_kkiapay_force_activation');
    
    // Contrôle d'affichage
    add_filter('give_show_gateways', 'give_kkiapay_force_display', 10, 2);
    
    // Ajoute la section de configuration
    add_filter('give_get_sections_gateways', 'give_kkiapay_add_settings_section');
    
    // Traitement du paiement
    add_action('give_gateway_kkiapay', 'give_kkiapay_process_payment');
    
    // Formulaire de paiement
    add_action('give_kkiapay_cc_form', 'give_kkiapay_payment_form');
    
    // Vérification des webhooks
    add_action('init', 'give_kkiapay_listen_for_webhooks');
    
    // Ajout du support pour GiveWP Free
    add_filter('give_get_option_gateways', 'give_kkiapay_add_to_default_gateways');
}

/**
 * Ajoute Kkiapay aux passerelles par défaut
 */
function give_kkiapay_add_to_default_gateways($options) {
    if (!isset($options['gateways']['kkiapay'])) {
        $options['gateways']['kkiapay'] = '1';
    }
    return $options;
}

/**
 * Force l'activation de la passerelle
 */
function give_kkiapay_force_activation() {
    $options = get_option('give_settings');
    if (!isset($options['gateways']['kkiapay'])) {
        $options['gateways']['kkiapay'] = '1';
        update_option('give_settings', $options);
    }
}

/**
 * Enregistre la passerelle Kkiapay (optimisé pour GiveWP Free)
 */
function give_kkiapay_register_gateway($gateways) {
    return array_merge($gateways, [
        'kkiapay' => [
            'admin_label'    => esc_html__('Kkiapay', 'give-kkiapay'),
            'checkout_label' => esc_html__('Paiement Sécurisé (Kkiapay)', 'give-kkiapay'),
            'supports'       => ['donation'], // Essentiel pour GiveWP Free
            'give_default'   => true // Force l'inclusion
        ]
    ]);
}

/**
 * Force l'affichage de la passerelle
 */
function give_kkiapay_force_display($show, $gateway_id) {
    return ($gateway_id === 'kkiapay') ? true : $show;
}

/**
 * Affiche un avertissement si la version de GiveWP est trop ancienne
 */
function give_kkiapay_give_version_notice() {
    echo '<div class="error"><p>';
    echo sprintf(
        __('GiveWP - Kkiapay Gateway nécessite GiveWP version %s ou supérieure. Veuillez mettre à jour GiveWP.', 'give-kkiapay'),
        GIVE_KKIAPAY_MIN_GIVE_VER
    );
    echo '</p></div>';
}

/**
 * Ajoute la section de configuration Kkiapay
 */
function give_kkiapay_add_settings_section($sections) {
    $sections['kkiapay'] = esc_html__('Kkiapay', 'give-kkiapay');
    return $sections;
}

/**
 * Affiche le formulaire de paiement Kkiapay (optimisé)
 */
function give_kkiapay_payment_form($form_id) {
    wp_enqueue_style(
        'give-kkiapay-styles',
        GIVE_KKIAPAY_PLUGIN_URL . 'assets/css/give-kkiapay.css',
        [],
        GIVE_KKIAPAY_VERSION
    );

    wp_enqueue_script(
        'kkiapay-sdk',
        'https://cdn.kkiapay.me/k.js',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'give-kkiapay-script',
        GIVE_KKIAPAY_PLUGIN_URL . 'assets/js/give-kkiapay.js',
        ['jquery'],
        GIVE_KKIAPAY_VERSION,
        true
    );

    wp_localize_script('give-kkiapay-script', 'give_kkiapay_vars', [
        'public_key' => give_get_option('give_kkiapay_public_key', ''),
        'sandbox'    => give_is_test_mode() ? 'true' : 'false',
        'form_id'    => $form_id,
        'button_text' => give_get_option('give_kkiapay_button_text', __('Payer avec Kkiapay', 'give-kkiapay'))
    ]);

    echo '<div class="give-kkiapay-form-wrap">
        <button id="give-kkiapay-button" type="button" class="give-kkiapay-button">
            '. esc_html(give_get_option('give_kkiapay_button_text', __('Payer avec Kkiapay', 'give-kkiapay'))) .'
        </button>
        <div id="give-kkiapay-feedback"></div>
    </div>';
}

/**
 * Traitement du paiement Kkiapay (optimisé pour GiveWP Free)
 */
function give_kkiapay_process_payment($purchase_data) {
    // Validation basique pour GiveWP Free
    if (empty($_POST['kkiapay_transaction_id'])) {
        give_record_gateway_error(__('Erreur Kkiapay', 'give-kkiapay'), __('ID de transaction manquant', 'give-kkiapay'));
        give_send_back_to_checkout('?payment-mode=kkiapay');
        return;
    }

    $transaction_id = sanitize_text_field($_POST['kkiapay_transaction_id']);
    $payment_data = [
        'price'           => $purchase_data['price'],
        'give_form_title' => $purchase_data['post_data']['give-form-title'],
        'give_form_id'    => $purchase_data['post_data']['give-form-id'],
        'give_price_id'   => isset($purchase_data['post_data']['give-price-id']) ? $purchase_data['post_data']['give-price-id'] : '',
        'date'           => date('Y-m-d H:i:s'),
        'user_email'     => $purchase_data['user_email'],
        'purchase_key'   => $purchase_data['purchase_key'],
        'currency'       => give_get_currency(),
        'user_info'      => $purchase_data['user_info'],
        'status'         => 'pending',
        'gateway'        => 'kkiapay'
    ];

    $payment_id = give_insert_payment($payment_data);

    if ($payment_id) {
        give_update_payment_meta($payment_id, '_kkiapay_transaction_id', $transaction_id);
        give_send_to_success_page();
    } else {
        give_record_gateway_error(__('Erreur Kkiapay', 'give-kkiapay'), __('Échec de la création du paiement', 'give-kkiapay'));
        give_send_back_to_checkout('?payment-mode=kkiapay');
    }
}

/**
 * Écoute les webhooks Kkiapay (simplifié pour GiveWP Free)
 */
function give_kkiapay_listen_for_webhooks() {
    if (!isset($_GET['give-listener']) || $_GET['give-listener'] !== 'kkiapay') {
        return;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (empty($payload['transactionId'])) {
        status_header(400);
        exit;
    }

    $payment_id = give_get_purchase_id_by_transaction_id($payload['transactionId']);
    if ($payment_id) {
        switch ($payload['status']) {
            case 'SUCCESS':
                give_update_payment_status($payment_id, 'publish');
                break;
            case 'FAILED':
                give_update_payment_status($payment_id, 'failed');
                break;
        }
    }

    status_header(200);
    exit;
}

register_activation_hook(__FILE__, function() {
    // Activation par défaut
    $options = get_option('give_settings');
    $options['gateways']['kkiapay'] = '1';
    update_option('give_settings', $options);
});