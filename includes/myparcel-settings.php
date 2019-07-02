<?php declare(strict_types=1);

add_action('admin_enqueue_scripts', 'settingPageJsCss', 999);

/**
 *
 * @return void
 */
function settingPageJsCss(): void
{
    wp_enqueue_script('validation', plugins_url('', __FILE__) . '/../assets/admin/js/jquery.validate.js', '', '', false);
    wp_register_script('setting_page_js', plugins_url('', __FILE__) . '/../assets/admin/js/setting-page.js', '', '', true);
    wp_enqueue_script('setting_page_js');
}

add_action('admin_init', 'registerSettings');

/**
 * Register setting in setting panel
 *
 * @return void
 */
function registerSettings(): void
{
    add_option('client_key', '');
    add_option('client_secret_key', '');
    add_option('ship_exists', '0');
    add_option('act_test_mode', '0');

    register_setting('myplugin_options_group', 'client_key');
    register_setting('myplugin_options_group', 'client_secret_key');
    register_setting('myplugin_options_group', 'ship_exists');
    register_setting('myplugin_options_group', 'act_test_mode');
    register_setting('myplugin_options_group', 'checkValidation', 'validationCallBack');
}

/**
 *
 * @return bool
 */
function validationCallBack(): bool
{
    $error = false;
    $clientKey = get_option('client_key');
    $secretKey = get_option('client_secret_key');
    if (empty($clientKey) || empty($secretKey)) {
        $error = true;
    }
    if ($error) {
        add_settings_error('show_message', esc_attr('settings_updated'), __('Settings NOT saved. Please fill all the required fields.'), 'error');
        add_action('admin_notices', 'printErrors');
        updateOption();
        return false;
    } else {

        add_settings_error('show_message', esc_attr('settings_updated'), __('Settings saved.'), 'updated');
        add_action('admin_notices', 'printErrors');
        return true;
    }

}

/**
 *
 * @return void
 */
function printErrors(): void
{
    settings_errors('show_message');
}

/**
 *
 * @return void
 */
function updateOption(): void
{
    update_option('client_key', '');
    update_option('client_secret_key', '');
    update_option('ship_exists', '0');
}

add_action('admin_menu', 'addSettingMenu');

/**
 *
 * @return void
 */
function addSettingMenu(): void
{
    add_options_page('API Setting', 'MyParcel.com API setting', 'manage_options', 'api_setting', 'settingPage');
}

/**
 *
 * @return void
 */
function settingPage(): void
{
    global $woocommerce;
    $countries_obj = new WC_Countries();
    $countries = $countries_obj->__get('countries');
    prepareHtmlForSettingPage();
} 
?>