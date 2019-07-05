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
* For static menu we have created our own 'Static Menu' component. It extends RainLab.Pages 'Static Menu' component and allows to use static menus stored in database.

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
Plugin contains 'Static Menu' component. Use it for your site in order to get static menus saved in database.

## Plugin under development!!!
This plugin is still in development, so make sure to test it on dev/stage before going live. Please open new issue on our plugin’s GitHub issues page or contact us before adding negative comment about this plugin. We are trying our best to improve this plugin.

## License
Dynopages plugin is open-sourced software licensed under the MIT license.