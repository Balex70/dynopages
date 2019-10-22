<?php

return [
    'plugin' => [
        'name' => 'Dynopages',
        'description' => 'Plugin for managing content with database'
    ],
    'rd_dynopages_pages' => [
        'error_saving' => 'Error saving page!',
        'already_exists' => 'Page :name already exist!',
    ],
    'rd_dynopages_partials' => [
        'error_saving' => 'Error saving partial!',
        'already_exists' => 'Partial :name already exist!',
    ],
    'rd_dynopages_layouts' => [
        'error_saving' => 'Error saving layout!',
        'already_exists' => 'Layout :name already exist!',
    ],
    'rd_dynopages_static_pages' => [
        'error_saving' => 'Error saving page!',
        'already_exists' => 'Page :name already exist!',
        'error_creating_conf' => 'Error creating configuration for static pages',
        'error_saving_conf' => 'Error saving configuration for static pages',
    ],
    'url_not_unique' => 'This URL (:url) is already used by another page.',
    'code_not_unique' => 'This Code (:code) is already used by another menu.',
    'url_required' => 'The URL is required for default language (:lang)',
    'file_name_required' => 'File name is required',
    'file_name_not_unique' => 'This file name (:fileName) is already used by another page.',
    'settings' => [
        'label' => 'Settings',
        'description' => 'Manage Dynopages settings',
        'breadcrumb_label' => 'Dynopages: general settings',
        'use_dynopages' => [
            'label' => 'Activate plugin'
        ],
        'show_native_cms' => [
            'label' => 'Show native cms in menu'
        ],
        'show_native_static_pages' => [
            'label' => 'Show native static pages in menu'
        ]
    ],
    'permissions' => [
        'tab' => 'Dynopages',
        'access_settings' => 'Manage Settings',
        'manage_static_pages' => 'Manage static pages',
        'manage_menus' => 'Manage menus',
        'manage_content' => 'Manage content',
        'access_snippets' => 'Manage snippets',
        'manage_pages' => 'Manage CMS pages',
        'manage_partials' => 'Manage partials',
        'manage_layouts' => 'Manage layouts',
        'manage_assets' => 'Manage assets',
        'manage_components' => 'Manage components',
    ],
    'component' => [
        'static_page_name' => 'Static page',
        'static_page_description' => 'Outputs a static page in a CMS layout.',
        'static_page_use_content_name' => 'Use page content field',
        'static_page_use_content_description' => 'If unchecked, the content section will not appear when editing the static page. Page content will be determined solely through placeholders and variables.',
        'static_page_default_name' => 'Default layout',
        'static_page_default_description' => 'Defines this layout as the default for new pages',
        'static_page_child_layout_name' => 'Subpage layout',
        'static_page_child_layout_description' => 'The layout to use as the default for any new subpages',
        'static_menu_name' => 'Static menu',
        'static_menu_description' => 'Outputs a menu (dynopages menu) in a CMS layout.',
        'static_menu_code_name' => 'Menu',
        'static_menu_code_description' => 'Specify a code of the menu the component should output.',
        'static_breadcrumbs_name' => 'Static breadcrumbs',
        'static_breadcrumbs_description' => 'Outputs breadcrumbs for a static page.',
    ],
    'cmspage' => [
        'menu_label' => 'Dyno CMS'
    ],
    'staticpage' => [
        'menu_label' => 'Dyno Pages'
    ],
    'migrations' => [
        'label' => 'Migrations',
        'description' => 'Migrate content to DB',
        'breadcrumb_label' => 'Dynopages: migrations',
        'cms_page_tab' => 'CMS page',
        'static_page_tab' => 'Static Pages',
        'static_menu_tab' => 'Static Menus',
        'page_hint' => 'Here you can migrate pages to DB',
        'page_warning' => 'Be aware, that during migration all localized versions of page will be created, even if localized version of page doesn\'t exist in theme! If during migration some field of localized page is not translated, data from default language will be used!',
        'migrate_all_pages' => 'Migrate all CMS pages (if not already exists)',
        'all_pages_migrated' => 'All pages migrated',
        'pages_not_exists' => 'Theme doesn\'t contain any pages. Migration is not possible!',
        'static_page_hint' => 'Here you can migrate static pages to DB. All pages along with BE menu will be migrated!',
        'static_page_warning' => 'The migration will not be possible if you have static pages in the database!',
        'file_name' => 'File name',
        'title' => 'title',
        'url' => 'URL',
        'action_status' => 'Action/Status',
        'page_exists' => 'Page with this file name already exists',
        'migrate' => 'Migrate',
        'migrated' => 'Migrated',
        'language' => 'Language',
        'static_content_migrate' => 'Migrate all pages',
        'db_static_pages_exists' => 'You still have static pages! Migration is not possible!',
        'static_pages_not_exists' => 'You don\'t have static pages in theme! Migration is not possible!',
        'static_pages_for_migrations' => 'Pages that are going to be migrated',
        'rainlab_pages_not_exists' => 'RailLab Pages plugin not installed or deactivated! Migration is not possible!',
        'error' => 'Error during migration',
        'menu_code' => 'Menu code',
        'menu_exists' => 'Menu with this code already exists!',
        'static_menu_hint' => 'Here you can migrate static menus to DB.',
        'static_menu_warning' => 'If static menu with identical code already exists, migration of this menu item is not possible!'
    ],
    'menuitem' => [
        'cms_page' => 'Dyno CMS page',
        'static_page' => 'Dyno static page',
        'all_static_pages' => 'All Dyno static pages'
    ]
];
