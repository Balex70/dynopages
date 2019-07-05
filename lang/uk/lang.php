<?php

return [
    'plugin' => [
        'name' => 'Dynopages',
        'description' => 'Плагін для керування контентом використовуючи базу даних'
    ],
    'rd_dynopages_pages' => [
        'error_saving' => 'Помилка при збереженні сторінки!',
        'already_exists' => 'Сторінка :name вже існує!',
    ],
    'rd_dynopages_partials' => [
        'error_saving' => 'Помилка при збереженні фрагменту!',
        'already_exists' => 'Фрагмент :name вже існує!',
    ],
    'rd_dynopages_layouts' => [
        'error_saving' => 'Помилка при збереженні шаблону!',
        'already_exists' => 'Шаблон :name вже існує!',
    ],
    'rd_dynopages_static_pages' => [
        'error_saving' => 'Помилка при збереженні статичної сторінки!',
        'already_exists' => 'Статична сторінка :name вже існує!',
        'error_creating_conf' => 'Помилка при створенні конфігурації для статичних сторінок',
        'error_saving_conf' => 'Помилка при збереженні конфігурації для статичних сторінок',
    ],
    'url_not_unique' => 'Цей URL (:url) вже використовується іншою сторінкою (мова :lang)',
    'code_not_unique' => 'Цей код (:code) вже використовується іншим меню',
    'url_required' => 'URL є обов\'язковим для заповнення для основної мови (:lang)',
    'file_name_required' => 'Ім\'я файлу є обов\'язковим для заповнення',
    'file_name_not_unique' => 'Це Ім\'я файлу (:fileName) вже використовується іншою сторінкою',
    'settings' => [
        'label' => 'Налаштування',
        'description' => 'Керування налаштуваннями плагіна Dynopages',
        'breadcrumb_label' => 'Dynopages: загальні налаштування',
        'use_dynopages' => [
            'label' => 'Активувати плагін'
        ],
        'show_native_cms' => [
            'label' => 'Показувати оригінальне меню October CMS (October.Cms)'
        ],
        'show_native_static_pages' => [
            'label' => 'Показувати оригінальне меню для статичних сторінок (RainLab.Pages)'
        ]
    ],
    'permissions' => [
        'tab' => 'Dynopages',
        'access_settings' => 'Керування налаштуваннями',
        'manage_static_pages' => 'Керування статичними сторінками',
        'manage_menus' => 'Керування меню',
        'manage_content' => 'Керування контентом',
        'access_snippets' => 'Керування сніпетами',
        'manage_pages' => 'Керування сторінками',
        'manage_partials' => 'Керування фрагментами',
        'manage_layouts' => 'Керування шаблонами',
        'manage_assets' => 'Керування файлами',
        'manage_components' => 'Керування компонентами',
    ],
    'component' => [
        'static_page_name' => 'Статична сторінка',
        'static_page_description' => 'Виводить статичну сторінку в шаблоні',
        'static_page_use_content_name' => 'Використовувати поле "контент"',
        'static_page_use_content_description' => 'Якщо не відмічено, розділ вмісту не з\'явиться під час редагування статичної сторінки. Вміст сторінки визначатиметься виключно за допомогою плейсхолдерів та змінних',
        'static_page_default_name' => 'Шаблон за замовчуванням',
        'static_page_default_description' => 'Визначає чи є цей шаблон за замовчуванням для нових сторінок',
        'static_page_child_layout_name' => 'Шаблон для внутрішній сторінок',
        'static_page_child_layout_description' => 'Використовувати цей шаблон за замовчуванням для всіх новий внутрішніх сторінок',
        'static_menu_name' => 'Статичне меню',
        'static_menu_description' => 'Виводить меню (dynopages меню) в CMS шаблоні',
        'static_menu_code_name' => 'Меню',
        'static_menu_code_description' => 'Визначає код меню, яке буде виводитись компонентою',
        'static_breadcrumbs_name' => 'Статичні "хлібні крихти"',
        'static_breadcrumbs_description' => 'Виводить "хлібні крихти" для статичної сторінки',
    ],
    'cmspage' => [
        'menu_label' => 'Dyno CMS'
    ],
    'staticpage' => [
        'menu_label' => 'Dyno Pages'
    ],
    'migrations' => [
        'label' => 'Міграції',
        'description' => 'Мігрувати контент до бази даних',
        'breadcrumb_label' => 'Dynopages: міграції',
        'cms_page_tab' => 'CMS сторінки',
        'static_page_tab' => 'Статичні сторінки',
        'static_menu_tab' => 'Статичні меню',
        'page_hint' => 'Тут ви можете мігрувати сторінки в базу даних',
        'page_warning' => 'Майте на увазі, що під час міграції будуть створені всі локалізовані версії сторінки, навіть якщо локалізована версія сторінки не існує в темі! Якщо під час міграції якесь поле локалізованої сторінки не є перекладено, використовуються дані з мови за замовчуванням!',
        'migrate_all_pages' => 'Мігрувати всі сторінки (які ще не є в базі)',
        'all_pages_migrated' => 'Всі сторінки перенесені',
        'pages_not_exists' => 'Сторінок в темі не знайдено! Міграція не можлива!',
        'static_page_hint' => 'Тут ви можете мігрувати статичні сторінки в базу даних. Переносяться всі сторінки разом з BE меню.',
        'static_page_warning' => 'У вас не повинно бути жодної статичної сторінки перед міграцією!',
        'file_name' => 'Ім\'я файлу',
        'title' => 'Заголовок',
        'url' => 'URL',
        'action_status' => 'Дія/Статус',
        'page_exists' => 'Сторінка з таким ім\'ям файлу вже існує',
        'migrate' => 'Мігрувати',
        'migrated' => 'Перенесено',
        'language' => 'Мова',
        'static_content_migrate' => 'Мігрувати всі сторінки',
        'db_static_pages_exists' => 'У вас ще існують статичні сторінки! Міграція не можлива!',
        'static_pages_not_exists' => 'В темі не знайдено статичних сторінок! Міграція не можлива!',
        'static_pages_for_migrations' => 'Сторінки які будуть перенесені',
        'rainlab_pages_not_exists' => 'RailLab Pages плагін не встановлений або деактивований! Міграція не можлива!',
        'error' => 'Помилка під час міграції',
        'menu_code' => 'Меню код',
        'menu_exists' => 'Таке меню вже існує',
        'static_menu_hint' => 'Тут ви можете мігрувати статичні меню в базу даних.',
        'static_menu_warning' => 'Якщо статичне меню з ідентичним кодом вже існує, міграція цього меню буде не можливою!'
    ]
];
