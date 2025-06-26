<?php
/**
 * Paramètres admin pour Kkiapay Gateway
 */

defined('ABSPATH') || exit;

/**
 * Ajoute les paramètres Kkiapay à la section des passerelles de paiement
 */
add_filter('give_get_settings_gateways', 'give_kkiapay_add_settings', 10, 1);

function give_kkiapay_add_settings($settings) {
    $kkiapay_settings = [
        [
            'name' => __('Paramètres Kkiapay', 'give-kkiapay'),
            'id'   => 'give_kkiapay_settings_title',
            'type' => 'title',
        ],
        [
            'name' => __('Clé Publique', 'give-kkiapay'),
            'desc' => __('Entrez votre clé publique Kkiapay', 'give-kkiapay'),
            'id'   => 'give_kkiapay_public_key',
            'type' => 'text',
        ],
        [
            'name' => __('Clé Secrète (Live)', 'give-kkiapay'),
            'desc' => __('Entrez votre clé secrète Kkiapay pour le mode production', 'give-kkiapay'),
            'id'   => 'give_kkiapay_live_secret_key',
            'type' => 'text',
        ],
        [
            'name' => __('Clé Secrète (Test)', 'give-kkiapay'),
            'desc' => __('Entrez votre clé secrète Kkiapay pour le mode test', 'give-kkiapay'),
            'id'   => 'give_kkiapay_test_secret_key',
            'type' => 'text',
        ],
        [
            'name'    => __('Couleur du Thème', 'give-kkiapay'),
            'desc'    => __('Choisissez la couleur du bouton Kkiapay', 'give-kkiapay'),
            'id'      => 'give_kkiapay_theme_color',
            'type'    => 'color',
            'default' => '#6a1b9a',
        ],
        [
            'name'    => __('Texte du Bouton', 'give-kkiapay'),
            'desc'    => __('Texte à afficher sur le bouton de paiement', 'give-kkiapay'),
            'id'      => 'give_kkiapay_button_text',
            'type'    => 'text',
            'default' => __('Payer avec Kkiapay', 'give-kkiapay'),
        ],
        [
            'name' => __('Webhook URL', 'give-kkiapay'),
            'desc' => __('URL à configurer dans votre dashboard Kkiapay pour recevoir les notifications de paiement', 'give-kkiapay'),
            'id'   => 'give_kkiapay_webhook_url',
            'type' => 'text',
            'attributes' => [
                'readonly' => 'readonly',
                'value'    => home_url('/?give-listener=kkiapay'),
            ],
        ],
        [
            'type' => 'sectionend',
            'id'   => 'give_kkiapay_settings_end',
        ],
    ];

    return array_merge($settings, $kkiapay_settings);
}