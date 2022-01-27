<?php

namespace Defaults\Data;

use \Core\Models\Router;
use \Core\Models\Config;
use \Core\Models\Load;
use \Core\Models\Presentation\Menu;
use \Core\Models\Presentation\MenuType;

class MainMenu extends Menu
{
    public function __construct()
    {
        $this->type = MenuType::MAIN_MENU;
        $this->menuList = [
            'example' => [
                'title' => '** Example **',
                'url' => '%base_url/defaults/welcome/example',
                'show' => function () {
                    return Config::$items['debug'] == true;
                },
                'active' => function () {
                    return Router::$method == 'example';
                }
            ],
        ];
        $this->postMenu = Load::view(
            'Defaults/Views/components/user_top_menu.html',
            return: true
        );
    }
}
