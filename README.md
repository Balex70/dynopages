# Dynopages plugin
This plugin adds possibility to use database for storing CMS pages, static pages, and static menus.

## Features:
* Store CMS pages in database
* Store static pages and menus in database if RainLab.Pages plugin is installed and enabled.
* Use RainLab.Translate plugin for localization if installed and enabled.
* Migrate CMS pages, static pages, and static menus to database.

## Installation
#### OctoberCMS backend
Search for 'Dynopages' in:
```
Settings > Updates&Plugins > Install plugins 
```
#### Console
php artisan plugin:install Rd.Dynopages

#### OctoberCMS Marketplace
You can find our plugin on OctoberCMS marketplace: https://octobercms.com/plugins

## How to use?
* Install and enable plugin.
* Additional menu items will be available: Dyno CMS and Dyno Pages (if you are using RainLab.Pages for static content).
* Use OctoberCMS in same way as with native CMS or Static Pages plugin.
* For static menu we have created our own 'Static Menu' component. It extends RainLab.Pages 'Static Menu' component and allows to use static menus stored in database. We also added the possibility to use the actual page localized title on the menu ([see usage example](#static-menu-usage-example)). In order to use component correctly please use correct item type in BE for menu generation.

##### Configuration:
```
Settings > Dynopages: general settings
```
* Enable/Disable plugin
* Show/hide native CMS in menu
* Show/hide native static pages in menu

##### Migration:
```
Settings > Dynopages: migrations
```
* Migrate existing CMS pages to database.
   - You can migrate separate page
   - You can migrate all pages
   - Page can be migrated if page with same file name doesn't already exist in database.
   - Be aware, that during migration all localized versions of page will be created, even if localized version of page doesn't exist in theme!
   - If during migration some field of localized page is not translated, data from default language will be used!
* Migrate existing Static pages to database. You can migrate all static pages only. If at least one static page exists in database already, migration will not be possible!
* Migrate existing Static menus to database. You can migrate separate menu items to database only. If menu item with same code exists, migration will not be possible for this menu item!

##### Components:
Plugin contains 'Static Menu' component. Use it for your site in order to get static menus saved in database. See the example below, with localized pages titles (item.pageTitle).

# Static menu usage example:
```
{% for item in staticMenu.menuItems if not item.viewBag.isHidden %}
   <li class="nav-item {{ item.items ? 'dropdown' : '' }}">
    {% if item.url %}
    {% set attributes = item.items ? 'role=button data-toggle=dropdown aria-haspopup=true aria-expanded=false' %}
    <a 
        class="nav-link {{ item.isActive ? 'active' : '' }} {{ item.viewBag.cssClass }} {{ item.items ? 'dropdown-toggle' : '' }}"
           href="{{item.url}}"
           {{ item.viewBag.isExternal ? 'target="_blank"' }}
           >
           {% if item.code %}
               <span>{{ ('nav.'~item.code)|_  }}</span>
           {% elseif item.pageTitle %}
               <span>{{ item.pageTitle }}</span>
           {% else %}
               <span>{{ item.title }}</span>
           {% endif %}

           {% if item.items %}
               <span class="icons icon-arrow-down"></span>
           {% endif %}
       </a>
       {% else %}
           <span>{{ item.title }}</span>
       {% endif %}

       {% if item.items %}
           <div class="dropdown-menu">
               {% for dropdownItem in item.items %}
                   {% if dropdownItem.code %}
                       <a 
                       class="dropdown-item" 
                       href="{{ dropdownItem.url }}">{{ ('nav.'~dropdownItem.code)|_  }}</a>
                   {% elseif dropdownItem.pageTitle %}
                       <a 
                       class="dropdown-item" 
                       href="{{ dropdownItem.url }}">{{ dropdownItem.pageTitle }}</a>
                   {% else %}
                       <a 
                       class="dropdown-item" 
                       href="{{ dropdownItem.url }}">{{ dropdownItem.title }}</a>
                   {% endif %}
               {% endfor %}
           </div>
       {% endif %}
   </li>
{% endfor %}
```

## Plugin under development!!!
This plugin is still in development, so make sure to test it on dev/stage before going live. Please open new issue on our plugin’s GitHub issues page or contact us before adding negative comment about this plugin. We are trying our best to improve this plugin.

## License
Dynopages plugin is open-sourced software licensed under the MIT license.
