<?php

/**
 * Plugin Name: GiveWP - Kkiapay Gateway
 * Plugin URI: https://github.com/israelboco/givewp-kkiapay
 * Description: Intègre la passerelle de paiement Kkiapay à GiveWP avec des fonctionnalités avancées.
 * Version: 2.0
 * Author: isboco
 * Author URI: https://github.com/israelboco
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: give-kkiapay
 */

// Empêche l'accès direct
defined('ABSPATH') || exit;

// Définition des constantes
define('GIVE_KKIAPAY_VERSION', '2.0');
define('GIVE_KKIAPAY_MIN_GIVE_VER', '2.8.0');
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
function give_kkiapay_missing_give_notice()
{
    echo '<div class="error"><p>';
    echo sprintf(
        __('GiveWP - Kkiapay Gateway nécessite GiveWP pour fonctionner. Veuillez installer et activer %sGiveWP%s.', 'give-kkiapay'),
        '<a href="' . admin_url('plugin-install.php?tab=search&s=givewp') . '">',
        '</a>'
    );
    echo '</p></div>';
}

// Initialisation du plugin
add_action('plugins_loaded', 'give_kkiapay_init');

function give_kkiapay_init()
{
    // Vérification de la version de GiveWP
    if (version_compare(GIVE_VERSION, GIVE_KKIAPAY_MIN_GIVE_VER, '<')) {
        add_action('admin_notices', 'give_kkiapay_give_version_notice');
        return;
    }



    // Charge les fichiers nécessaires
    require_once GIVE_KKIAPAY_PLUGIN_DIR . 'includes/admin-settings.php';

    // Charge la traduction
    load_plugin_textdomain('give-kkiapay', false, dirname(GIVE_KKIAPAY_BASENAME) . '/languages/');

    // Enregistre la passerelle
    add_filter('give_payment_gateways', 'give_kkiapay_register_gateway', 10, 1);

    // 2. Force l'activation
    add_filter('give_enabled_payment_gateways', 'give_kkiapay_force_enable', 15, 1);

    // 3. Contrôle d'affichage
    add_filter('give_show_gateways', 'give_kkiapay_force_display', 20, 2);

    // Ajoute la section de configuration
    add_filter('give_get_sections_gateways', 'give_kkiapay_add_settings_section');

    // Traitement du paiement
    add_action('give_gateway_kkiapay', 'give_kkiapay_process_payment');

    // Formulaire de paiement
    add_action('give_kkiapay_cc_form', 'give_kkiapay_payment_form');

    // Vérification des webhooks
    add_action('init', 'give_kkiapay_listen_for_webhooks');
}

/**
 * Affiche un avertissement si la version de GiveWP est trop ancienne
 */
function give_kkiapay_give_version_notice()
{
    echo '<div class="error"><p>';
    echo sprintf(
        __('GiveWP - Kkiapay Gateway nécessite GiveWP version %s ou supérieure. Veuillez mettre à jour GiveWP.', 'give-kkiapay'),
        GIVE_KKIAPAY_MIN_GIVE_VER
    );
    echo '</p></div>';
}

/**
 * Enregistre la passerelle Kkiapay
 */
function give_kkiapay_register_gateway($gateways) {
    $gateways['kkiapay'] = [
        'admin_label'    => esc_html__('Kkiapay', 'give-kkiapay'),
        'checkout_label' => esc_html__('Paiement Sécurisé Kkiapay', 'give-kkiapay'),
        'supports' => [
            'donation_form',
            'fee_recovery',
            'subscriptions'
        ]
    ];
    return $gateways;
}

function give_kkiapay_force_enable($enabled_gateways) {
    if (!isset($enabled_gateways['kkiapay'])) {
        $enabled_gateways['kkiapay'] = give_kkiapay_register_gateway([])['kkiapay'];
    }
    return $enabled_gateways;
}

function give_kkiapay_force_display($show, $gateway_id) {
    return ($gateway_id === 'kkiapay') ? true : $show;
}


/**
 * Ajoute la section de configuration Kkiapay
 */
function give_kkiapay_add_settings_section($sections)
{
    $sections['kkiapay'] = esc_html__('Kkiapay', 'give-kkiapay');
    return $sections;
}

/**
 * Affiche le formulaire de paiement Kkiapay
 */
function give_kkiapay_payment_form($form_id)
{
    $public_key = give_get_option('give_kkiapay_public_key', '');
    $sandbox = give_is_test_mode() ? 'true' : 'false';

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
        ['jquery', 'kkiapay-sdk'],
        GIVE_KKIAPAY_VERSION,
        true
    );

    wp_localize_script('give-kkiapay-script', 'give_kkiapay_vars', [
        'public_key' => $public_key,
        'sandbox'    => $sandbox,
        'form_id'    => $form_id,
        'ajax_url'   => admin_url('admin-ajax.php'),
        'loading'    => esc_html__('Traitement en cours...', 'give-kkiapay'),
        'error'      => esc_html__('Une erreur est survenue. Veuillez réessayer.', 'give-kkiapay'),
    ]);

    ob_start();
?>
    <div class="give-kkiapay-form-wrap">
        <button id="give-kkiapay-button" type="button" class="give-kkiapay-button">
            <?php esc_html_e('Payer avec Kkiapay', 'give-kkiapay'); ?>
        </button>
        <div id="give-kkiapay-feedback" class="give-kkiapay-feedback"></div>
    </div>
<?php
    echo ob_get_clean();
}

/**
 * Traitement du paiement Kkiapay
 */
function give_kkiapay_process_payment($purchase_data)
{
    // Vérification du nonce
    give_validate_nonce($purchase_data['gateway_nonce'], 'give-gateway');

    if (empty($_POST['kkiapay_transaction_id'])) {
        give_record_gateway_error(
            esc_html__('Erreur Kkiapay', 'give-kkiapay'),
            esc_html__('Transaction ID manquant.', 'give-kkiapay'),
            $purchase_data
        );
        give_send_back_to_checkout('?payment-mode=kkiapay');
        return;
    }

    $transaction_id = sanitize_text_field($_POST['kkiapay_transaction_id']);
    $secret_key = give_is_test_mode()
        ? give_get_option('give_kkiapay_test_secret_key', '')
        : give_get_option('give_kkiapay_live_secret_key', '');

    // Vérification de la transaction via l'API Kkiapay
    $response = wp_remote_get('https://api.kkiapay.me/api/v1/transactions/status/' . $transaction_id, [
        'headers' => [
            'Authorization' => 'Bearer ' . $secret_key,
            'Accept' => 'application/json',
        ],
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        give_record_gateway_error(
            esc_html__('Erreur Kkiapay', 'give-kkiapay'),
            sprintf(esc_html__('Erreur de connexion à l\'API Kkiapay: %s', 'give-kkiapay'), $response->get_error_message()),
            $purchase_data
        );
        give_send_back_to_checkout('?payment-mode=kkiapay');
        return;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code !== 200 || !isset($body['status']) || $body['status'] !== 'SUCCESS') {
        $error_message = isset($body['message']) ? $body['message'] : esc_html__('Statut de transaction inconnu', 'give-kkiapay');
        give_record_gateway_error(
            esc_html__('Erreur Kkiapay', 'give-kkiapay'),
            sprintf(esc_html__('Échec de la vérification du paiement: %s', 'give-kkiapay'), $error_message),
            $purchase_data
        );
        give_send_back_to_checkout('?payment-mode=kkiapay');
        return;
    }

    // Vérification du montant
    $amount_paid = isset($body['amount']) ? floatval($body['amount']) : 0;
    $expected_amount = give_sanitize_amount($purchase_data['price']);

    if ($amount_paid < $expected_amount) {
        give_record_gateway_error(
            esc_html__('Erreur Kkiapay', 'give-kkiapay'),
            sprintf(
                esc_html__('Montant payé (%s) inférieur au montant attendu (%s)', 'give-kkiapay'),
                give_format_amount($amount_paid),
                give_format_amount($expected_amount)
            ),
            $purchase_data
        );
        give_send_back_to_checkout('?payment-mode=kkiapay');
        return;
    }

    // Création du paiement
    $payment_data = [
        'price'           => $purchase_data['price'],
        'form_title'      => sanitize_text_field($purchase_data['post_data']['give-form-title']),
        'form_id'         => absint($purchase_data['post_data']['give-form-id']),
        'payment_gateway' => 'kkiapay',
        'user_email'     => sanitize_email($purchase_data['user_email']),
        'purchase_key'    => sanitize_text_field($purchase_data['purchase_key']),
        'currency'        => give_get_currency(),
        'user_info'       => $purchase_data['user_info'],
        'status'          => 'pending', // On met en pending jusqu'à confirmation du webhook
    ];

    $payment_id = give_insert_payment($payment_data);

    if (!$payment_id) {
        give_record_gateway_error(
            esc_html__('Erreur Kkiapay', 'give-kkiapay'),
            esc_html__('Échec lors de la création du paiement Give.', 'give-kkiapay'),
            $purchase_data
        );
        give_send_back_to_checkout('?payment-mode=kkiapay');
        return;
    }

    // Enregistrement des métadonnées
    give_update_payment_meta($payment_id, '_kkiapay_transaction_id', $transaction_id);
    give_update_payment_meta($payment_id, '_kkiapay_response', $body);

    // On attend le webhook pour confirmer le paiement
    give_update_payment_status($payment_id, 'pending');

    // Redirection vers la page de confirmation
    give_send_to_success_page();
}

/**
 * Écoute les webhooks Kkiapay
 */
function give_kkiapay_listen_for_webhooks()
{
    if (!isset($_GET['give-listener']) || $_GET['give-listener'] !== 'kkiapay') {
        return;
    }

    $payload = file_get_contents('php://input');
    $data = json_decode($payload, true);

    if (empty($data)) {
        status_header(400);
        exit;
    }

    // Vérification de la signature
    $secret_key = give_is_test_mode()
        ? give_get_option('give_kkiapay_test_secret_key', '')
        : give_get_option('give_kkiapay_live_secret_key', '');

    $signature = isset($_SERVER['HTTP_X_KKIAPAY_SIGNATURE']) ? $_SERVER['HTTP_X_KKIAPAY_SIGNATURE'] : '';
    $computed_signature = hash_hmac('sha256', $payload, $secret_key);

    if (!hash_equals($computed_signature, $signature)) {
        status_header(401);
        exit;
    }

    // Traitement de l'événement
    $event_type = isset($data['event']) ? sanitize_text_field($data['event']) : '';
    $transaction_id = isset($data['transactionId']) ? sanitize_text_field($data['transactionId']) : '';

    if (empty($transaction_id)) {
        status_header(400);
        exit;
    }

    // Trouver le paiement correspondant
    $payment_id = give_get_purchase_id_by_transaction_id($transaction_id);

    if (!$payment_id) {
        status_header(404);
        exit;
    }

    switch ($event_type) {
        case 'payment.success':
            give_update_payment_status($payment_id, 'publish');
            give_insert_payment_note($payment_id, __('Paiement confirmé via webhook Kkiapay', 'give-kkiapay'));
            break;

        case 'payment.failed':
            give_update_payment_status($payment_id, 'failed');
            give_insert_payment_note($payment_id, __('Paiement échoué via Kkiapay', 'give-kkiapay'));
            break;

        default:
            status_header(400);
            exit;
    }

    status_header(200);
    exit;
}

/**
 * Enregistrement des paramètres du plugin
 */
function give_kkiapay_plugin_activation()
{
    // Options par défaut
    $default_settings = [
        'give_kkiapay_public_key'       => '',
        'give_kkiapay_live_secret_key'  => '',
        'give_kkiapay_test_secret_key'  => '',
        'give_kkiapay_theme_color'      => '#6a1b9a',
        'give_kkiapay_button_text'      => __('Payer avec Kkiapay', 'give-kkiapay'),
    ];

    foreach ($default_settings as $key => $value) {
        if (empty(give_get_option($key))) {
            give_update_option($key, $value);
        }
    }
}

register_activation_hook(__FILE__, 'give_kkiapay_plugin_activation');
