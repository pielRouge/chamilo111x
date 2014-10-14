<?php

/* For licensing terms, see /license.txt */

/**
 * Config the plugin
 * @author Angel Fernando Quiroz Campos <angel.quiroz@beeznest.com>
 * @package chamilo.plugin.tour
 */
require_once dirname(__FILE__) . '/config.php';

$pluginWebPath = api_get_path(WEB_PLUGIN_PATH) . 'tour/';

$userId = api_get_user_id();

$tourPlugin = Tour::create();

$config = $tourPlugin->getTourCofig();

$pages = array();

foreach ($config as $pageContent) {
    $pages[] = array(
        'pageClass' => $pageContent['pageClass'],
        'show' => $tourPlugin->checkTourForUser($pageContent['pageClass'], $userId)
    );
}

$_template['pages'] = json_encode($pages);

$_template['web_path'] = array(
    'intro_css' => "{$pluginWebPath}intro.js/introjs.min.css",
    'intro_theme_css' => "{$pluginWebPath}intro.js/introjs-nassim.css",
    'intro_js' => "{$pluginWebPath}intro.js/intro.min.js",
    'steps_ajax' => "{$pluginWebPath}ajax/steps.ajax.php",
    'save_ajax' => "{$pluginWebPath}ajax/save.ajax.php"
);
