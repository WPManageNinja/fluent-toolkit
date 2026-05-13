<?php

namespace FluentToolkit\Classes;

class UnifiedUiHandler
{

    protected $fluentPageSlugs = [
        'fluentcrm-admin',
        'fluent-cart',
        'fluent-boards'
    ];

    protected $apps = [];


    public function register()
    {

        $apps = [
            'fluentcrm-admin' => [
                'title'      => 'CRM',
                'icon_url'   => '',
                'menu_icons' => [

                ]
            ],
            'fluent-cart'     => [
                'title'      => 'Commerce',
                'icon_url'   => '',
                'menu_icons' => []
            ],
            'fluent-boards'   => [
                'title'      => 'Projects',
                'icon_url'   => '',
                'menu_icons' => []
            ]
        ];

        $this->apps = $apps;


        add_action('admin_init', function () {
            global $plugin_page;
            if (!isset($this->apps[$plugin_page])) {
                return;
            }

            $hookName = 'toplevel_page_' . $plugin_page;

            add_action($hookName, [$this, 'pushUnifiedUiToTop'], 1);
            add_action($hookName, function () {
                echo '</div></div>';
            }, 9999);

            // disable top menu
            add_filter('show_admin_bar', '__return_false', 9999);

         //   add_action('in_admin_header', [$this, 'pushUnifiedUiToTop'], 9999);

            add_action('admin_enqueue_scripts', [$this, 'loadUnifiedUi']);
        });


    }

    public function loadUnifiedUi($screen = '')
    {
        $pagePluginName = (string)str_replace('toplevel_page_', '', (string)$screen);

        if (!isset($this->apps[$pagePluginName])) {
            return;
        }

        wp_enqueue_style('fluent_unified_ui', FLUENT_BETA_TESTING_PLUGIN_URL . 'src/unified-ui.css', [], FLUENT_TOOLKIT_VERSION);
    }

    public function pushUnifiedUiToTop()
    {
        global $plugin_page, $submenu, $menu;
        $otherApps = $this->apps;
        unset($otherApps[$plugin_page]);
        $currentApp = isset($this->apps[$plugin_page]) ? $this->apps[$plugin_page] : [];
        $appPages = isset($submenu[$plugin_page]) ? $submenu[$plugin_page] : [];
        ?>

        <div class="fluent_uui">
            <div class="fluent_ui_sidebar">
                <div class="fui-apps-menu">
                    <h4><?php echo $currentApp['title']; ?></h4>
                    <div class="fui-apps-menu-inner">
                        <ul>
                            <?php
                            foreach ($appPages as $app) {
                                $isActive = false;
                                ?>
                                <li>
                                    <a href="<?php echo admin_url('admin.php?page='.$app[2]); ?>" class="fui-apps-menu-item <?php echo $isActive ? 'active' : '' ?>">
                                        <span class="fui-app-title"><?php echo esc_html($app[0]) ?></span>
                                    </a>
                                </li>
                                <?php
                            }
                            ?>
                        </ul>

                    </div>
                </div>
                <div class="fui_other_apps">
                    <h4>Apps</h4>
                    <div class="fui_other_apps_inner">
                        <ul>
                            <?php
                            foreach ($otherApps as $slug => $app) {
                                ?>
                                <li>
                                    <a href="<?php echo admin_url('admin.php?page='.$slug); ?>" class="fui-apps-menu-item">
                                        <span class="fui-app-title"><?php echo esc_html($app['title']) ?></span>
                                    </a>
                                </li>
                                <?php
                            }
                            ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="fui-app-content">

        <?php
    }

}
