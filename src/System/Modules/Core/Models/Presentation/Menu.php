<?php

namespace Core\Models\Presentation;

use \Core\Models\Load;
use \Core\Models\Config;
use \Core\Models\Router;
use \Core\Controllers\Controller;

/**
 * Menu Type
 */
abstract class MenuType
{
    const MAIN_MENU = 100;
    const SUB_MENU = 200;
    const SUB_MENU_NEXT_LEVEL = 201;
    const TABS = 300;
}

/**
 * Generate Menus
 */
class Menu
{
    public int $type;
    public array $menuList = [];
    public $preMenuList = '';
    public $postMenuList = '';
    public $preMenu = '';
    public $postMenu = '';

    private array $_itemDefaults = [
        'title' => 'No Title',
        'end_icon' => '',
        'before_icon' => '',
        'after_icon' => '',
        'url' => '',
        'show' => true,
        'active' => false,
        'nav_class' => '',
        'contents' => '',
    ];

    public function __construct()
    {
    }

    private function prepareUrl($url)
    {
        return str_replace(
            [
                '%base_url',
                '%module_url',
                '%controller_url',
                '%method_url',
                '%module',
                '%controller',
                '%class',
                '%method',
            ],
            [
                rtrim(Router::$base_url, '/'),
                rtrim(Controller::moduleUrl(), '/'),
                rtrim(Controller::controllerUrl(), '/'),
                rtrim(Controller::methodUrl(), '/'),
                Router::$module,
                Router::$controller,
                Router::$class,
                Router::$method,
            ],
            $url
        );
    }

    public function html()
    {
        $preMenuListContent = '';
        if (is_callable($this->preMenuList)) {
            $preMenuListContent = ($this->preMenuList)();
        } else {
            $preMenuListContent = $this->preMenuList;
        }
        $postMenuListContent = '';
        if (is_callable($this->postMenuList)) {
            $postMenuListContent = ($this->postMenuList)();
        } else {
            $postMenuListContent = $this->postMenuList;
        }
        $preMenuContent = '';
        if (is_callable($this->preMenu)) {
            $preMenuContent = ($this->preMenu)();
        } else {
            $preMenuContent = $this->preMenu;
        }
        $postMenuContent = '';
        if (is_callable($this->postMenu)) {
            $postMenuContent = ($this->postMenu)();
        } else {
            $postMenuContent = $this->postMenu;
        }


        $menuItems = [];
        foreach ($this->menuList as $item) {
            // Merge defaults
            $item = array_merge($this->_itemDefaults, $item);

            // Can we show the item?
            $shouldShow = is_callable($item['show']) ? $item['show']() : $item['show'];
            if ($shouldShow === false) {
                continue;
            }

            // Is the item active?
            $item['active'] = is_callable($item['active']) ? $item['active']() : $item['active'];

            // Custom contents
            if (is_callable($item['contents'])) {
                $item['contents'] = $item['contents']();
            }

            // Fix url
            $item['url'] = $this->prepareUrl($item['url']);

            $menuItems[] = $item;
        }

        $viewData = [
            'pre_menu_list' => $preMenuListContent,
            'post_menu_list' => $postMenuListContent,
            'pre_menu' => $preMenuContent,
            'post_menu' => $postMenuContent,
            'menu_items' => $menuItems,
        ];
        return Load::view("Views/components/menu_type_{$this->type}.html", $viewData, true);
    }

    // MARK: Twig
    public static function registerTwig()
    {
        $function = new \Twig\TwigFunction(
            'DisplayMenu',
            function (Menu $instance) {
                return $instance->html();
            },
            ['is_safe' => ['html']]
        );
        Config::$items['view_engine']->addFunction($function);
    }

    public static function registerMenu(Menu $instance)
    {
        if ($instance->type == MenuType::MAIN_MENU) {
            Config::$items['view_data']['menu_main'] = $instance;
        }
        if ($instance->type == MenuType::SUB_MENU) {
            Config::$items['view_data']['menu_submenu'] = $instance;
        }
        if ($instance->type == MenuType::TABS) {
            Config::$items['view_data']['menu_tabs'] = $instance;
        }
    }

    public static function hideMenus($menuFlags)
    {
        if ($menuFlags & MenuType::MAIN_MENU) {
            unset(Config::$items['view_data']['menu_main']);
        }
        if ($menuFlags & MenuType::SUB_MENU) {
            unset(Config::$items['view_data']['menu_submenu']);
        }
        if ($menuFlags & MenuType::TABS) {
            unset(Config::$items['view_data']['menu_tabs']);
        }
    }
}
